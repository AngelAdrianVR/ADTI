<?php

namespace App\Console\Commands;

use App\Models\ExtraHourApprovalGroup;
use App\Models\ExtraHourApprovalLevel;
use App\Models\Payroll;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ClosePayroll extends Command
{
    protected $signature = 'payrolls:close';

    protected $description = 'Cerrar catorcena y abrir una nueva (arrastra la jerarquía de autorización de tiempo extra)';

    public function handle(): int
    {
        $current = Payroll::firstWhere('is_active', true);
        $newPayroll = Payroll::create([
            'start_date' => $current->start_date->copy()->addDays(14)->toDateString(),
            'biweekly' => $current->biweekly + 1,
        ]);

        // Arrastrar la configuración de autorización: sin esto, la catorcena nueva
        // nace SIN jerarquía y todos los días procesados hasta que alguien configure
        // quedan "sin flujo de autorización" (era la causa recurrente de huérfanos
        // al inicio de cada catorcena).
        $copied = $this->copyApprovalGroups($current, $newPayroll);

        $current->update(['is_active' => 0]);

        $this->info("Catorcena cerrada y creada una nueva (grupos de autorización copiados: {$copied}).");
        if ($copied === 0) {
            $this->warn('La catorcena anterior no tenía jerarquía configurada: configura los grupos en /payrolls/' . $newPayroll->id . '/extra-hours-config');
        } else {
            $this->line('Los costos de hora extra NO se copian automáticamente: usa "Copiar configuración" en la pantalla de tiempo extra si los necesitas.');
        }

        Log::info('Catorcena cerrada y creada una nueva', [
            'payroll_cerrada' => $current->id,
            'payroll_nueva' => $newPayroll->id,
            'grupos_copiados' => $copied,
        ]);

        return self::SUCCESS;
    }

    /**
     * Copia grupos + niveles + aprobadores de una catorcena a otra.
     *
     * @return int Grupos copiados
     */
    private function copyApprovalGroups(Payroll $from, Payroll $to): int
    {
        $groups = $from->approvalGroups()->with(['employees', 'levels.approvers'])->orderBy('id')->get();

        foreach ($groups as $group) {
            $newGroup = ExtraHourApprovalGroup::create([
                'payroll_id' => $to->id,
                'name' => $group->name,
            ]);

            $newGroup->employees()->sync($group->employees->pluck('id'));

            foreach ($group->levels as $level) {
                $newLevel = ExtraHourApprovalLevel::create([
                    'payroll_id' => $to->id,
                    'approval_group_id' => $newGroup->id,
                    'level' => $level->level,
                    'name' => $level->name,
                ]);

                $newLevel->approvers()->sync($level->approvers->pluck('id'));
            }
        }

        return $groups->count();
    }
}
