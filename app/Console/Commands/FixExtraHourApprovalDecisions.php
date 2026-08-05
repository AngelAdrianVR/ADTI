<?php

namespace App\Console\Commands;

use App\Models\ExtraHourApprovalDecision;
use App\Models\ExtraHourApprovalGroup;
use App\Models\PayrollUser;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class FixExtraHourApprovalDecisions extends Command
{
    protected $signature = 'extra-hours:fix-approved-decisions {--dry-run : Muestra qué se hará sin modificar la base de datos}';
    protected $description = 'Corrige días de tiempo extra aprobados sin decisiones registradas y limpia datos stale de aprobación (approved_at) en registros pendientes.';

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');

        if ($dryRun) {
            $this->info('=== MODO DRY RUN: no se modificarán datos ===');
        } else {
            $this->info('Iniciando corrección de decisiones de aprobación...');
        }

        // ─── PASO 1: Días 'approved' que NO tienen ninguna decisión registrada ───
        // (Incluye approved_at NULL: aprobación "fantasma" por flujo viejo revertido)
        $records = PayrollUser::where('extra_hour_status', 'approved')
            ->whereDoesntHave('approvalDecisions')
            ->get();

        $count = $records->count();
        $this->info("Paso 1: Se encontraron {$count} días 'approved' sin decisiones.");

        if ($count > 0) {
            if ($dryRun) {
                $this->printApprovedSummary($records);
            } else {
                $this->processApprovedRecords($records);
            }
        }

        // ─── PASO 2: Limpiar datos stale en días 'pending' con approved_at ───
        // (Ej: registro movido a pending por la corrección pero que conservaba approved_at/by del flujo viejo)
        $stalePending = PayrollUser::where('extra_hour_status', 'pending')
            ->whereNotNull('approved_at')
            ->get();

        $staleCount = $stalePending->count();
        $this->newLine();
        $this->info("Paso 2: Se encontraron {$staleCount} días 'pending' con approved_at stale.");

        foreach ($stalePending as $record) {
            if ($dryRun) {
                $this->line("  [pending→limpiar] payroll_user_id={$record->id}, user_id={$record->user_id}, fecha={$record->date->toDateString()} → se limpiarán approved_at, approved_by, approved_extra_hours, approved_extra_minutes.");
            } else {
                $record->updateQuietly([
                    'approved_at' => null,
                    'approved_by' => null,
                    'approved_extra_hours' => null,
                    'approved_extra_minutes' => null,
                ]);
            }
        }

        if ($dryRun) {
            $this->newLine();
            $this->info('Ejecuta sin --dry-run para aplicar los cambios.');
        } else {
            $this->newLine();
            $this->info('Corrección completada.');
        }

        return self::SUCCESS;
    }

    /**
     * Procesa los registros 'approved' sin decisiones.
     */
    private function processApprovedRecords($records): void
    {
        $count = $records->count();
        $bar = $this->output->createProgressBar($count);
        $bar->start();

        $updated = 0;              // Aprobado legítimo (approved_at NOT NULL) con decisión de nivel 1 (1 nivel)
        $movedBackToPending = 0;   // Aprobado legítimo con más niveles → pending en nivel 2
        $movedToPendingLevel1 = 0; // Sin approved_at (fantasma) → pending en nivel 1
        $movedToDirectMode = 0;    // Sin approved_at, sin grupo → pending modo directo
        $noGroup = 0;              // Modo directo legítimo (approved_at NOT NULL, sin grupo)
        $errors = 0;

        foreach ($records as $record) {
            try {
                $group = $this->findGroupForUser($record);
                $hasApprovedAt = $record->approved_at !== null;

                if (!$group) {
                    if ($hasApprovedAt) {
                        // Modo directo: sin grupo, no se insertan decisiones (comportamiento del servicio)
                        $noGroup++;
                    } else {
                        // 'approved' fantasma sin grupo → re-inicializar a pending modo directo
                        $record->updateQuietly([
                            'extra_hour_status' => 'pending',
                            'current_approval_level_id' => null,
                            'approved_at' => null,
                            'approved_by' => null,
                            'approved_extra_hours' => null,
                            'approved_extra_minutes' => null,
                        ]);
                        $movedToDirectMode++;
                    }
                    $bar->advance();
                    continue;
                }

                $levels = $group->levels()->orderBy('level')->get();

                if ($levels->isEmpty()) {
                    $this->warn("  [skip] payroll_user_id={$record->id} tiene grupo '{$group->name}' pero sin niveles configurados.");
                    $errors++;
                    $bar->advance();
                    continue;
                }

                DB::transaction(function () use ($record, $levels, $group, $hasApprovedAt, &$updated, &$movedBackToPending, &$movedToPendingLevel1) {
                    if (!$hasApprovedAt) {
                        // Aprobación fantasma: nunca se completó formalmente → volver a pending en nivel 1
                        $firstLevel = $levels->first();
                        $record->update([
                            'extra_hour_status' => 'pending',
                            'current_approval_level_id' => $firstLevel->id,
                            'approved_at' => null,
                            'approved_by' => null,
                            'approved_extra_hours' => null,
                            'approved_extra_minutes' => null,
                        ]);
                        $movedToPendingLevel1++;
                        return;
                    }

                    $firstLevel = $levels->first();

                    // Aprobación legítima del flujo viejo → resolver aprobador del nivel 1
                    $approverId = $this->resolveApproverForLevel($record, $firstLevel);

                    if (!$approverId) {
                        throw new \RuntimeException("No hay aprobadores configurados en el nivel '{$firstLevel->name}' del grupo '{$group->name}'.");
                    }

                    // Registrar la decisión del nivel 1
                    ExtraHourApprovalDecision::updateOrCreate(
                        [
                            'payroll_user_id' => $record->id,
                            'approval_level_id' => $firstLevel->id,
                            'approver_id' => $approverId,
                        ],
                        [
                            'status' => 'approved',
                            'comments' => 'Aprobación registrada retroactivamente por corrección de aprobadores (aprobado antes de la corrección del flujo por niveles).',
                            'decided_at' => $record->approved_at ?? now(),
                        ]
                    );

                    if ($levels->count() === 1) {
                        // Un solo nivel → el estado ya es 'approved', solo faltaba la decisión
                        $record->updateQuietly([
                            'extra_hour_status' => 'approved',
                            'current_approval_level_id' => null,
                        ]);
                        $updated++;
                    } else {
                        // Más de un nivel → mover a 'pending' en el nivel 2 y limpiar aprobación stale
                        $secondLevel = $levels->get(1);
                        $record->update([
                            'extra_hour_status' => 'pending',
                            'current_approval_level_id' => $secondLevel->id,
                            'approved_extra_hours' => null,
                            'approved_extra_minutes' => null,
                            'approved_by' => null,
                            'approved_at' => null,
                        ]);
                        $movedBackToPending++;
                    }
                });
            } catch (\Exception $e) {
                $this->error("  [error] payroll_user_id={$record->id}: {$e->getMessage()}");
                $errors++;
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        $this->info('Resultados Paso 1:');
        $this->info("  - Aprobados legítimos con decisión de nivel 1 (1 nivel): {$updated}");
        $this->info("  - Aprobados legítimos movidos a 'pending' en nivel 2: {$movedBackToPending}");
        $this->info("  - Aprobados fantasma movidos a 'pending' en nivel 1: {$movedToPendingLevel1}");
        $this->info("  - Aprobados fantasma (sin grupo) movidos a 'pending' modo directo: {$movedToDirectMode}");
        $this->info("  - Modo directo legítimo (sin grupo, sin decisiones): {$noGroup}");
        $this->info("  - Errores: {$errors}");
    }

    /**
     * Muestra un resumen de lo que se haría con los registros 'approved' sin decisiones.
     */
    private function printApprovedSummary($records): void
    {
        $wouldUpdate = 0;
        $wouldMoveToPending = 0;
        $wouldMoveToPendingLevel1 = 0;
        $wouldMoveToDirectMode = 0;
        $noGroup = 0;
        $noApprovers = [];

        foreach ($records as $record) {
            $group = $this->findGroupForUser($record);
            $hasApprovedAt = $record->approved_at !== null;

            if (!$group) {
                if ($hasApprovedAt) {
                    $noGroup++;
                    $this->line("  [modo directo] user_id={$record->user_id}, fecha={$record->date->toDateString()} → sin grupo, no requiere decisiones.");
                } else {
                    $wouldMoveToDirectMode++;
                    $this->line("  [approved→pending/directo] user_id={$record->user_id}, fecha={$record->date->toDateString()} → sin approved_at ni grupo, se moverá a 'pending' modo directo.");
                }
                continue;
            }

            $levels = $group->levels()->orderBy('level')->get();
            if ($levels->isEmpty()) {
                $this->line("  [skip] user_id={$record->user_id}, fecha={$record->date->toDateString()} → grupo '{$group->name}' sin niveles configurados.");
                continue;
            }

            if (!$hasApprovedAt) {
                $wouldMoveToPendingLevel1++;
                $this->line("  [approved→pending/nivel1] user_id={$record->user_id}, fecha={$record->date->toDateString()} → sin approved_at, se moverá a 'pending' en nivel 1 ({$levels->first()->name}).");
                continue;
            }

            $firstApproverId = $this->resolveApproverForLevel($record, $levels->first());
            if (!$firstApproverId) {
                $noApprovers[] = $record->id;
                $this->line("  [error potencial] user_id={$record->user_id}, fecha={$record->date->toDateString()} → nivel 1 sin aprobadores configurados.");
                continue;
            }

            if ($levels->count() === 1) {
                $wouldUpdate++;
                $this->line("  [aprobado→ok] user_id={$record->user_id}, fecha={$record->date->toDateString()} → se registrará decisión aprobada en nivel 1 ({$levels->first()->name}).");
            } else {
                $wouldMoveToPending++;
                $secondLevelName = $levels->get(1)->name;
                $this->line("  [approved→pending] user_id={$record->user_id}, fecha={$record->date->toDateString()} → se registrará decisión en nivel 1 ({$levels->first()->name}) y quedará pendiente en nivel 2 ({$secondLevelName}).");
            }
        }

        $this->newLine();
        $this->info('=== RESUMEN PASO 1 (DRY RUN) ===');
        $this->info("  - Días que quedarán aprobados con decisión de nivel 1: {$wouldUpdate}");
        $this->info("  - Días que volverán a 'pending' en nivel 2: {$wouldMoveToPending}");
        $this->info("  - Días 'approved' fantasma que volverán a 'pending' en nivel 1: {$wouldMoveToPendingLevel1}");
        $this->info("  - Días 'approved' fantasma (sin grupo) a 'pending' modo directo: {$wouldMoveToDirectMode}");
        $this->info("  - Días en modo directo (sin cambios): {$noGroup}");
        if (count($noApprovers) > 0) {
            $this->warn("  - ATENCIÓN: {$noApprovers} día(s) sin aprobadores configurados en el nivel 1 (no se modificarán).");
        }
    }

    /**
     * Encuentra el grupo de aprobación asignado al empleado en la nómina del registro.
     */
    private function findGroupForUser(PayrollUser $payrollUser): ?ExtraHourApprovalGroup
    {
        return $payrollUser->payroll->approvalGroups()
            ->whereHas('employees', fn ($q) => $q->where('user_id', $payrollUser->user_id))
            ->first();
    }

    /**
     * Resuelve el aprobador para un nivel:
     * 1. Si el registro tiene approved_by y ese usuario es aprobador del nivel → usarlo.
     * 2. Fallback: primer aprobador configurado en extra_hour_approval_level_user del nivel.
     */
    private function resolveApproverForLevel(PayrollUser $payrollUser, $level): ?int
    {
        // Preferir el aprobador original (approved_by) si es aprobador de este nivel
        if ($payrollUser->approved_by) {
            $isApprover = $level->approvers()
                ->where('user_id', $payrollUser->approved_by)
                ->exists();
            if ($isApprover) {
                return $payrollUser->approved_by;
            }
        }

        // Fallback: primer aprobador configurado en la tabla pivote del nivel
        $firstApprover = $level->approvers()->orderBy('extra_hour_approval_level_user.id')->first();

        return $firstApprover?->id;
    }
}