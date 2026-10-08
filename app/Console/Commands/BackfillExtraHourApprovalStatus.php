<?php

namespace App\Console\Commands;

use App\Models\ExtraHourApprovalDecision;
use App\Models\PayrollUser;
use App\Services\ExtraHourApprovalService;
use Illuminate\Console\Command;

class BackfillExtraHourApprovalStatus extends Command
{
    protected $signature = 'extra-hours:backfill-status';
    protected $description = 'Rellena extra_hour_status y current_approval_level_id en registros existentes de payroll_user.';

    public function handle(ExtraHourApprovalService $service): int
    {
        $this->info('Iniciando backfill de estados de aprobación...');

        $records = PayrollUser::where(function ($q) {
            $q->where('extra_hours', '>', 0)->orWhere('extra_minutes', '>', 0);
        })->get();

        $count = $records->count();
        $bar = $this->output->createProgressBar($count);
        $bar->start();

        $updated = 0;
        foreach ($records as $record) {
            $service->initializeWorkflow($record);

            // Si ya tiene decisión final (approved_at), forzar el estado correcto
            if ($record->approved_at) {
                $isRejected = $record->approved_extra_hours === 0 && $record->approved_extra_minutes === 0
                    && ($record->approved_extra_hours !== null);
                $record->updateQuietly([
                    'extra_hour_status' => $isRejected ? 'rejected' : 'approved',
                    'current_approval_level_id' => null,
                ]);
            }

            $updated++;
            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);
        $this->info("Backfill completado. {$updated} de {$count} registros actualizados.");

        return self::SUCCESS;
    }
}
