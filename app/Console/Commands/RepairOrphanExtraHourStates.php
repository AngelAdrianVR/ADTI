<?php

namespace App\Console\Commands;

use App\Models\ExtraHourApprovalLevel;
use App\Models\PayrollUser;
use App\Services\ExtraHourApprovalService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RepairOrphanExtraHourStates extends Command
{
    protected $signature = 'extra-hours:repair-orphan-states {--dry-run : Muestra qué se hará sin modificar la base de datos}';
    protected $description = 'Reinicia el flujo de aprobación de registros de tiempo extra con estados finales huérfanos (approved/rejected sin decisiones ni approved_at) y niveles de aprobación inexistentes.';

    public function handle(ExtraHourApprovalService $approvals): int
    {
        $dryRun = $this->option('dry-run');

        if ($dryRun) {
            $this->info('=== MODO DRY RUN: no se modificarán datos ===');
        } else {
            $this->info('Reparando estados huérfanos de tiempo extra...');
        }

        $records = PayrollUser::where(function ($q) {
            $q->where('extra_hours', '>', 0)->orWhere('extra_minutes', '>', 0);
        })->get();

        $fixed = 0;
        $skipped = 0;
        $errors = 0;

        foreach ($records as $record) {
            try {
                $decisionCount = $record->approvalDecisions()->count();
                $status = $record->extra_hour_status ?? 'none';
                $hasApprovedAt = $record->approved_at !== null;
                $levelId = $record->current_approval_level_id;
                $levelExists = $levelId ? ExtraHourApprovalLevel::whereKey($levelId)->exists() : true;

                $orphanFinal = in_array($status, ['approved', 'rejected'])
                    && $decisionCount === 0
                    && !$hasApprovedAt;

                $orphanLevel = $levelId && !$levelExists;

                if (!$orphanFinal && !$orphanLevel) {
                    $skipped++;
                    continue;
                }

                $detail = $orphanFinal
                    ? "estado final '{$status}' sin decisiones ni approved_at"
                    : "nivel actual {$levelId} inexistente";

                if ($dryRun) {
                    $this->line("  [reparar] payroll_user_id={$record->id}, user_id={$record->user_id}, fecha={$record->date->toDateString()} → {$detail}. Se re-inicializará el flujo.");
                } else {
                    DB::transaction(function () use ($record, $approvals) {
                        $record->updateQuietly([
                            'approved_extra_hours' => null,
                            'approved_extra_minutes' => null,
                            'approved_by' => null,
                            'approved_at' => null,
                        ]);
                        $approvals->initializeWorkflow($record, true);
                    });
                }
                $fixed++;
            } catch (\Exception $e) {
                $this->error("  [error] payroll_user_id={$record->id}: {$e->getMessage()}");
                $errors++;
            }
        }

        $this->newLine();
        $this->info("Registros con tiempo extra revisados: {$records->count()}");
        $this->info("Reparados / a reparar: {$fixed}");
        $this->info("Sin cambios: {$skipped}");
        $this->info("Errores: {$errors}");

        return self::SUCCESS;
    }
}