<?php

namespace App\Console\Commands;

use App\Models\Payroll;
use App\Models\PayrollUser;
use App\Services\ExtraHourApprovalService;
use Illuminate\Console\Command;

/**
 * Reconciliación (NO destructiva) de los estados de autorización de tiempo extra.
 *
 * Por defecto corre en modo DRY-RUN: sólo informa. Con `--apply` escribe.
 *
 * Qué hace:
 *  1. `pending` SIN horas (residuo de clear-extra-time) → se marcan como 'none'
 *     sin nivel: no hay nada que autorizar y hoy inflaban los contadores.
 *  2. Días sin flujo (nivel NULL) cuyo colaborador SÍ tiene grupo → se les asigna
 *     el primer nivel del grupo (rescate). Los de colaboradores sin grupo quedan
 *     intactos: se reportan como "sin jerarquía" (requieren configuración).
 *  3. Días cuyo nivel actual NO pertenece al grupo del colaborador → se reasignan
 *     al primer nivel del grupo correcto (o a NULL si no tiene grupo).
 *
 * NUNCA inventa decisiones, NUNCA reabre aprobaciones legítimas y NUNCA borra
 * historial. Sustituye a los antiguos comandos destructivos
 * (extra-hours:fix-approved-decisions, backfill-status, repair-orphan-states).
 */
class ReconcileExtraHourStates extends Command
{
    protected $signature = 'extra-hours:reconcile
                            {--apply : Escribe los cambios (sin esta bandera sólo informa)}
                            {--payroll= : Limitar a una catorcena (id)}
                            {--details : Listar cada cambio aplicado/propuesto}';

    protected $description = 'Reconcilia huérfanos y residuos de los estados de autorización de tiempo extra';

    public function handle(ExtraHourApprovalService $approvals): int
    {
        $apply = (bool) $this->option('apply');
        $payrollId = $this->option('payroll') ? (int) $this->option('payroll') : null;
        $details = (bool) $this->option('details');

        if (!$apply) {
            $this->warn('MODO DRY-RUN: no se escribirá nada. Usa --apply para aplicar.');
        }

        $payrolls = Payroll::query()
            ->when($payrollId, fn ($q) => $q->whereKey($payrollId))
            ->orderBy('id')
            ->get();

        if ($payrolls->isEmpty()) {
            $this->error('No se encontró la catorcena indicada.');

            return self::FAILURE;
        }

        $totals = ['cleaned' => 0, 'rescued' => 0, 'without_group' => 0, 'reassigned' => 0, 'without_group_days' => 0];

        foreach ($payrolls as $payroll) {
            $cleaned = $this->cleanPendingWithoutHours($payroll, $apply, $details);
            $orphans = $approvals->reconcileOrphans($payroll, !$apply);
            $reassigned = $this->reassignForeignLevels($payroll, $apply, $details);

            $totals['cleaned'] += $cleaned;
            $totals['rescued'] += $orphans['rescued'];
            $totals['without_group'] += $orphans['without_group'];
            $totals['reassigned'] += $reassigned['reassigned'];
            $totals['without_group_days'] += $reassigned['without_group'];

            if ($cleaned || $orphans['rescued'] || $orphans['without_group'] || $reassigned['reassigned']) {
                $this->line(sprintf(
                    'Catorcena %s (id %d): limpiados=%d, rescatados=%d, sin grupo=%d, reasignados=%d',
                    $payroll->biweekly,
                    $payroll->id,
                    $cleaned,
                    $orphans['rescued'],
                    $orphans['without_group'],
                    $reassigned['reassigned']
                ));

                if ($details) {
                    foreach ($orphans['details'] as $detail) {
                        $this->line('    [rescate] ' . $detail);
                    }
                    foreach ($reassigned['details'] as $detail) {
                        $this->line('    [reasignado] ' . $detail);
                    }
                }
            }
        }

        $this->newLine();
        $this->info('Resumen:');
        $this->line("  Días `pending` sin horas marcados como 'none': {$totals['cleaned']}");
        $this->line("  Huérfanos rescatados (nivel 1 de su grupo): {$totals['rescued']}");
        $this->line("  Huérfanos sin grupo (requieren configuración): {$totals['without_group']}");
        $this->line("  Días con nivel ajeno reasignados: {$totals['reassigned']}");
        $this->line("  Días con nivel ajeno y sin grupo: {$totals['without_group_days']}");

        if (!$apply) {
            $this->newLine();
            $this->comment('Ejecuta de nuevo con --apply para aplicar los cambios.');
        }

        return self::SUCCESS;
    }

    /**
     * Marca como 'none' los días `pending` sin horas (residuo de clear-extra-time):
     * no hay tiempo extra que autorizar y hoy sí cuentan en el badge.
     */
    private function cleanPendingWithoutHours(Payroll $payroll, bool $apply, bool $details): int
    {
        $rows = PayrollUser::where('payroll_id', $payroll->id)
            ->where('extra_hour_status', 'pending')
            ->whereRaw('coalesce(extra_hours,0)=0 and coalesce(extra_minutes,0)=0')
            ->get(['id', 'user_id', 'date', 'current_approval_level_id']);

        foreach ($rows as $row) {
            if ($details) {
                $this->line(sprintf(
                    '    [limpieza] pu=%d user=%d fecha=%s',
                    $row->id,
                    $row->user_id,
                    $row->date?->toDateString()
                ));
            }

            if ($apply) {
                $row->updateQuietly([
                    'extra_hour_status' => 'none',
                    'current_approval_level_id' => null,
                    'approved_extra_hours' => null,
                    'approved_extra_minutes' => null,
                    'approved_by' => null,
                    'approved_at' => null,
                    'proposed_extra_hours' => null,
                    'proposed_extra_minutes' => null,
                ]);
            }
        }

        return $rows->count();
    }

    /**
     * Reasigna los días cuyo nivel actual no pertenece al grupo del colaborador.
     *
     * @return array{reassigned:int,without_group:int,details:array<int,string>}
     */
    private function reassignForeignLevels(Payroll $payroll, bool $apply, bool $details): array
    {
        $rows = PayrollUser::where('payroll_id', $payroll->id)
            ->whereNotNull('current_approval_level_id')
            ->where(function ($w) {
                $w->where('extra_hours', '>', 0)->orWhere('extra_minutes', '>', 0);
            })
            ->whereNotExists(function ($sub) {
                $sub->selectRaw('1')
                    ->from('extra_hour_approval_levels as l')
                    ->join('extra_hour_approval_group_user as gu', 'gu.approval_group_id', '=', 'l.approval_group_id')
                    ->whereColumn('l.id', 'payroll_user.current_approval_level_id')
                    ->whereColumn('gu.user_id', 'payroll_user.user_id');
            })
            ->get(['id', 'user_id', 'date', 'extra_hour_status', 'current_approval_level_id']);

        $groups = [];
        foreach ($payroll->approvalGroups()->with('employees')->orderBy('id')->get() as $group) {
            foreach ($group->employees as $employee) {
                $groups[(int) $employee->id] ??= $group;
            }
        }

        $reassigned = 0;
        $withoutGroup = 0;
        $detailsList = [];

        foreach ($rows as $row) {
            $group = $groups[(int) $row->user_id] ?? null;
            $newLevelId = $group?->levels()->orderBy('level')->first()?->id;

            if (!$group) {
                $withoutGroup++;
            }

            $detailsList[] = sprintf(
                'pu=%d user=%d fecha=%s nivel %s → %s',
                $row->id,
                $row->user_id,
                $row->date?->toDateString(),
                $row->current_approval_level_id,
                $newLevelId ?? 'NULL (sin grupo)'
            );

            if ($apply) {
                $row->updateQuietly([
                    'extra_hour_status' => $newLevelId ? 'pending' : ($row->extra_hour_status ?? 'pending'),
                    'current_approval_level_id' => $newLevelId,
                ]);
            }

            if ($newLevelId) {
                $reassigned++;
            }
        }

        return ['reassigned' => $reassigned, 'without_group' => $withoutGroup, 'details' => $detailsList];
    }

}
