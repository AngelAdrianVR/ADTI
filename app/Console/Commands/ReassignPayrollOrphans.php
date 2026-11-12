<?php

namespace App\Console\Commands;

use App\Models\Payroll;
use App\Services\PayrollAttendanceReassignment;
use Illuminate\Console\Command;

class ReassignPayrollOrphans extends Command
{
    protected $signature = 'payrolls:reassign-orphans
                            {payroll? : ID de la catorcena destino (por defecto, la activa)}
                            {--apply : Aplica los cambios (sin esta bandera sólo se reporta)}';

    protected $description = 'Re-liga los registros de asistencia cuya fecha cae en la ventana de una catorcena pero quedaron guardados en otra (checadas capturadas antes de que corriera el cierre)';

    public function handle(PayrollAttendanceReassignment $reassignment): int
    {
        $target = $this->resolveTarget();

        if (!$target) {
            return self::FAILURE;
        }

        $start = $reassignment->windowStart($target);
        $end = $reassignment->windowEnd($target);

        $this->line("Catorcena destino: #{$target->id} (catorcena {$target->biweekly}) del {$start->toDateString()} al {$end->toDateString()}.");

        $orphans = $reassignment->orphansFor($target)
            ->get(['id', 'user_id', 'date', 'payroll_id', 'current_approval_level_id', 'extra_hour_status']);

        if ($orphans->isEmpty()) {
            $this->info('No hay registros fuera de lugar en esta ventana. Nada que hacer.');

            return self::SUCCESS;
        }

        $this->table(
            ['payroll_user', 'user_id', 'fecha', 'catorcena actual', 'nivel', 'tiempo extra'],
            $orphans->take(40)->map(fn ($row) => [
                $row->id,
                $row->user_id,
                $row->date?->toDateString(),
                $row->payroll_id,
                $row->current_approval_level_id ?? '—',
                $row->extra_hour_status ?? '—',
            ])->all()
        );

        if ($orphans->count() > 40) {
            $this->line('... y ' . ($orphans->count() - 40) . ' registro(s) más.');
        }

        // Resumen por catorcena de origen, para ver el tamaño del desfase.
        $porOrigen = $orphans->groupBy('payroll_id')
            ->map(fn ($group, $payrollId) => "catorcena #{$payrollId}: {$group->count()}")
            ->values()
            ->implode(' | ');

        $this->line("Total de registros fuera de lugar: {$orphans->count()} ({$porOrigen}).");

        if (!$this->option('apply')) {
            $this->warn('MODO DRY-RUN: no se modificó nada. Ejecuta el mismo comando con --apply para re-ligarlos.');

            return self::SUCCESS;
        }

        $result = $reassignment->reassignTo($target);

        $this->info("Registros re-ligados: {$result['moved']}. Niveles de aprobación remapeados: {$result['levels_remapped']}.");

        if ($result['levels_lost'] > 0) {
            $this->warn("{$result['levels_lost']} registro(s) quedaron sin nivel de aprobación: configura los grupos en /payrolls/{$target->id}/extra-hours-config y re-ejecuta.");
        }

        return self::SUCCESS;
    }

    private function resolveTarget(): ?Payroll
    {
        $id = $this->argument('payroll');

        if ($id) {
            $target = Payroll::find($id);

            if (!$target) {
                $this->error("No existe la catorcena con ID {$id}.");

                return null;
            }

            return $target;
        }

        $active = Payroll::where('is_active', true)->orderByDesc('start_date')->first();

        if (!$active) {
            $this->error('No hay catorcena activa. Indica el ID destino: php artisan payrolls:reassign-orphans {payroll}');

            return null;
        }

        return $active;
    }
}
