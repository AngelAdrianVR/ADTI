<?php

namespace App\Console\Commands;

use App\Models\Payroll;
use App\Models\PayrollUser;
use Illuminate\Console\Command;

/**
 * Auditoría (SOLO LECTURA) de los estados de autorización de tiempo extra.
 *
 * Detecta las inconsistencias que descuadraban los indicadores:
 *  - pending sin horas (residuo de clear-extra-time)
 *  - huérfanos con nivel NULL: rescatables (el colaborador tiene grupo) vs sin grupo
 *  - nivel actual que no corresponde al grupo del colaborador
 *  - nivel actual que YA tiene decisión (flujo "rebobinado" por recálculos)
 *  - decisiones en niveles superiores al actual (progreso retrocedido)
 *  - aprobados sin decisión / estados finales huérfanos
 *  - colaboradores con tiempo extra que no están en ningún grupo (cobertura)
 *
 * No modifica nada. Para aplicar cambios usar `extra-hours:reconcile --apply`.
 */
class AuditExtraHourStates extends Command
{
    protected $signature = 'extra-hours:audit-states
                            {--payroll= : Limitar a una catorcena (id)}
                            {--details : Listar las filas problemáticas}';

    protected $description = 'Reporte (solo lectura) de inconsistencias de autorización de tiempo extra';

    public function handle(): int
    {
        $payrollId = $this->option('payroll') ? (int) $this->option('payroll') : null;

        $this->info('=== Auditoría de estados de autorización de tiempo extra ===');

        $metrics = [
            'pending_sin_horas' => 'pending sin horas',
            'huerfano_rescatable' => 'sin flujo (colaborador con grupo)',
            'huerfano_sin_grupo' => 'sin flujo (colaborador sin grupo)',
            'nivel_ajeno' => 'nivel que no es del grupo del colaborador',
            'nivel_con_decision' => 'nivel actual ya decidido (flujo rebobinado)',
            'decision_nivel_superior' => 'decisión en nivel superior al actual',
            'approved_sin_decision' => 'aprobado sin decisiones',
            'final_huerfano' => 'final sin decisiones ni approved_at',
            'empleados_sin_cobertura' => 'colaboradores con TE sin grupo',
        ];

        $data = [];
        foreach ($metrics as $key => $label) {
            $data[$key] = $this->metric($key, $payrollId);
        }

        $payrollIds = collect($data)->flatMap(fn ($perPayroll) => array_keys($perPayroll))
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->sort()
            ->values();

        if ($payrollIds->isEmpty()) {
            $this->info('Sin hallazgos: no hay inconsistencias.');

            return self::SUCCESS;
        }

        $payrolls = Payroll::whereIn('id', $payrollIds)->get()->keyBy('id');

        $rows = $payrollIds->map(function ($id) use ($data, $payrolls) {
            $payroll = $payrolls->get($id);
            $row = [
                'id' => $id,
                'catorcena' => $payroll?->biweekly ?? '#' . $id,
                'inicio' => $payroll?->start_date?->toDateString(),
                'activa' => $payroll?->is_active ? 'sí' : 'no',
            ];
            foreach ($data as $key => $perPayroll) {
                $row[$key] = (int) ($perPayroll[$id] ?? 0);
            }

            return $row;
        })->all();

        $this->newLine();
        $this->table(array_merge(['id', 'catorcena', 'inicio', 'activa'], array_values($metrics)), $rows);

        $this->newLine();
        $this->line('Referencia:');
        foreach ($metrics as $key => $label) {
            $total = (int) array_sum(array_column($rows, $key));
            $this->line(sprintf('  %-45s %s', $label, $total));
        }

        if ($this->option('details')) {
            $this->newLine();
            $this->detailPendingWithoutHours($payrollId);
            $this->detailOrphans($payrollId);
            $this->detailRewound($payrollId);
            $this->detailUncoveredEmployees($payrollId);
        }

        $this->newLine();
        $this->comment('Ejecuta `php artisan extra-hours:reconcile --apply` para reparar los huérfanos y los pending sin horas.');

        return self::SUCCESS;
    }

    /**
     * Métrica agrupada por catorcena.
     *
     * @return array<int, int>  [payroll_id => conteo]
     */
    private function metric(string $key, ?int $payrollId): array
    {
        $base = fn () => PayrollUser::query()
            ->when($payrollId, fn ($q) => $q->where('payroll_id', $payrollId));

        $withExtra = function ($q) {
            $q->where(function ($w) {
                $w->where('extra_hours', '>', 0)->orWhere('extra_minutes', '>', 0);
            });
        };

        $hasGroupInPayroll = function ($sub) {
            $sub->selectRaw('1')
                ->from('extra_hour_approval_groups as g')
                ->join('extra_hour_approval_group_user as gu', 'gu.approval_group_id', '=', 'g.id')
                ->whereColumn('g.payroll_id', 'payroll_user.payroll_id')
                ->whereColumn('gu.user_id', 'payroll_user.user_id');
        };

        $query = match ($key) {
            'pending_sin_horas' => $base()
                ->where('extra_hour_status', 'pending')
                ->whereRaw('coalesce(extra_hours,0)=0 and coalesce(extra_minutes,0)=0'),

            'huerfano_rescatable' => tap($base(), $withExtra)
                ->where('extra_hour_status', 'pending')
                ->whereNull('current_approval_level_id')
                ->whereExists(fn ($sub) => $hasGroupInPayroll($sub)),

            'huerfano_sin_grupo' => tap($base(), $withExtra)
                ->where('extra_hour_status', 'pending')
                ->whereNull('current_approval_level_id')
                ->whereNotExists(fn ($sub) => $hasGroupInPayroll($sub)),

            'nivel_ajeno' => $base()
                ->whereNotNull('current_approval_level_id')
                ->whereNotExists(function ($sub) {
                    $sub->selectRaw('1')
                        ->from('extra_hour_approval_levels as l')
                        ->join('extra_hour_approval_group_user as gu', 'gu.approval_group_id', '=', 'l.approval_group_id')
                        ->whereColumn('l.id', 'payroll_user.current_approval_level_id')
                        ->whereColumn('gu.user_id', 'payroll_user.user_id');
                }),

            'nivel_con_decision' => $base()
                ->whereNotNull('current_approval_level_id')
                ->whereExists(function ($sub) {
                    $sub->selectRaw('1')
                        ->from('extra_hour_approval_decisions as d')
                        ->whereColumn('d.payroll_user_id', 'payroll_user.id')
                        ->whereColumn('d.approval_level_id', 'payroll_user.current_approval_level_id')
                        ->where('d.status', '!=', 'pending');
                }),

            'decision_nivel_superior' => $base()
                ->whereNotNull('current_approval_level_id')
                ->whereExists(function ($sub) {
                    $sub->selectRaw('1')
                        ->from('extra_hour_approval_decisions as d')
                        ->join('extra_hour_approval_levels as dl', 'dl.id', '=', 'd.approval_level_id')
                        ->join('extra_hour_approval_levels as cl', 'cl.id', '=', 'payroll_user.current_approval_level_id')
                        ->whereColumn('d.payroll_user_id', 'payroll_user.id')
                        ->whereColumn('dl.approval_group_id', 'cl.approval_group_id')
                        ->whereColumn('dl.level', '>', 'cl.level');
                }),

            'approved_sin_decision' => $base()
                ->where('extra_hour_status', 'approved')
                ->whereNotExists(function ($sub) {
                    $sub->selectRaw('1')
                        ->from('extra_hour_approval_decisions as d')
                        ->whereColumn('d.payroll_user_id', 'payroll_user.id');
                }),

            'final_huerfano' => $base()
                ->whereIn('extra_hour_status', ['approved', 'rejected'])
                ->whereNull('approved_at')
                ->whereNotExists(function ($sub) {
                    $sub->selectRaw('1')
                        ->from('extra_hour_approval_decisions as d')
                        ->whereColumn('d.payroll_user_id', 'payroll_user.id');
                }),

            'empleados_sin_cobertura' => tap($base(), $withExtra)
                ->whereNotExists(fn ($sub) => $hasGroupInPayroll($sub))
                ->selectRaw('payroll_id, count(distinct user_id) as n')
                ->groupBy('payroll_id')
                ->pluck('n', 'payroll_id')
                ->all(),

            default => collect(),
        };

        if (is_array($query)) {
            return $query;
        }

        return $query->selectRaw('payroll_id, count(*) as n')
            ->groupBy('payroll_id')
            ->pluck('n', 'payroll_id')
            ->all();
    }


    /** Filas `pending` sin tiempo extra (residuo de clear-extra-time). */
    private function detailPendingWithoutHours(?int $payrollId): void
    {
        $rows = PayrollUser::query()
            ->when($payrollId, fn ($q) => $q->where('payroll_id', $payrollId))
            ->where('extra_hour_status', 'pending')
            ->whereRaw('coalesce(extra_hours,0)=0 and coalesce(extra_minutes,0)=0')
            ->orderBy('payroll_id')
            ->limit(50)
            ->get(['id', 'payroll_id', 'user_id', 'date', 'current_approval_level_id']);

        if ($rows->isEmpty()) {
            return;
        }

        $this->newLine();
        $this->line('— Días `pending` sin horas (primero 50, se limpian con reconcile) —');
        foreach ($rows as $row) {
            $this->line(sprintf(
                '  pu=%-7d catorcena=%-3d user=%-4d fecha=%s nivel=%s',
                $row->id,
                $row->payroll_id,
                $row->user_id,
                $row->date?->toDateString(),
                $row->current_approval_level_id ?? 'NULL'
            ));
        }
    }

    /** Huérfanos por catorcena: rescatables y sin grupo. */
    private function detailOrphans(?int $payrollId): void
    {
        $rows = PayrollUser::query()
            ->when($payrollId, fn ($q) => $q->where('payroll_id', $payrollId))
            ->where('extra_hour_status', 'pending')
            ->whereNull('current_approval_level_id')
            ->where(function ($w) {
                $w->where('extra_hours', '>', 0)->orWhere('extra_minutes', '>', 0);
            })
            ->orderBy('payroll_id')
            ->limit(50)
            ->get(['id', 'payroll_id', 'user_id', 'date']);

        if ($rows->isEmpty()) {
            return;
        }

        $this->newLine();
        $this->line('— Días sin flujo de autorización (primero 50) —');
        foreach ($rows as $row) {
            $this->line(sprintf(
                '  pu=%-7d catorcena=%-3d user=%-4d fecha=%s',
                $row->id,
                $row->payroll_id,
                $row->user_id,
                $row->date?->toDateString()
            ));
        }
    }

    /** Filas cuyo nivel actual ya tiene decisión (flujo rebobinado). */
    private function detailRewound(?int $payrollId): void
    {
        $rows = PayrollUser::query()
            ->when($payrollId, fn ($q) => $q->where('payroll_id', $payrollId))
            ->whereNotNull('current_approval_level_id')
            ->whereExists(function ($sub) {
                $sub->selectRaw('1')
                    ->from('extra_hour_approval_decisions as d')
                    ->whereColumn('d.payroll_user_id', 'payroll_user.id')
                    ->whereColumn('d.approval_level_id', 'payroll_user.current_approval_level_id')
                    ->where('d.status', '!=', 'pending');
            })
            ->orderBy('payroll_id')
            ->limit(50)
            ->get(['id', 'payroll_id', 'user_id', 'date', 'current_approval_level_id', 'approved_at']);

        if ($rows->isEmpty()) {
            return;
        }

        $this->newLine();
        $this->line('— Días cuyo nivel actual ya tiene decisión (el badge los cuenta y el aprobador no puede decidir) —');
        foreach ($rows as $row) {
            $this->line(sprintf(
                '  pu=%-7d catorcena=%-3d user=%-4d fecha=%s nivel=%s approved_at=%s',
                $row->id,
                $row->payroll_id,
                $row->user_id,
                $row->date?->toDateString(),
                $row->current_approval_level_id,
                $row->approved_at ?? 'NULL'
            ));
        }
    }

    /** Colaboradores con tiempo extra que no están en ningún grupo (cobertura). */
    private function detailUncoveredEmployees(?int $payrollId): void
    {
        $rows = PayrollUser::query()
            ->when($payrollId, fn ($q) => $q->where('payroll_id', $payrollId))
            ->where(function ($w) {
                $w->where('extra_hours', '>', 0)->orWhere('extra_minutes', '>', 0);
            })
            ->whereNotExists(function ($sub) {
                $sub->selectRaw('1')
                    ->from('extra_hour_approval_groups as g')
                    ->join('extra_hour_approval_group_user as gu', 'gu.approval_group_id', '=', 'g.id')
                    ->whereColumn('g.payroll_id', 'payroll_user.payroll_id')
                    ->whereColumn('gu.user_id', 'payroll_user.user_id');
            })
            ->selectRaw('payroll_id, user_id, count(*) as dias')
            ->groupBy('payroll_id', 'user_id')
            ->orderBy('payroll_id')
            ->limit(50)
            ->get();

        if ($rows->isEmpty()) {
            return;
        }

        $this->newLine();
        $this->line('— Colaboradores con tiempo extra SIN grupo en su catorcena (no pueden ser autorizados) —');
        foreach ($rows as $row) {
            $this->line(sprintf(
                '  catorcena=%-3d user=%-4d dias=%d',
                $row->payroll_id,
                $row->user_id,
                $row->dias
            ));
        }
    }

}
