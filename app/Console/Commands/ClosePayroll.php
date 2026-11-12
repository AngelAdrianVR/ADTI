<?php

namespace App\Console\Commands;

use App\Services\PayrollRolloverService;
use Illuminate\Console\Command;
use RuntimeException;

class ClosePayroll extends Command
{
    protected $signature = 'payrolls:close';

    protected $description = 'Cerrar catorcena y abrir una nueva (arrastra la jerarquía de autorización de tiempo extra y re-liga las asistencias que ya llegaron al período nuevo)';

    public function handle(PayrollRolloverService $rollover): int
    {
        try {
            $result = $rollover->closeAndOpenNext();
        } catch (RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $closed = $result['closed'];
        $opened = $result['opened'];

        $this->info("Catorcena {$closed->biweekly} cerrada y creada la {$opened->biweekly} (grupos de autorización copiados: {$result['groups']}).");

        // Los días que ya habían llegado a la ventana del período nuevo (típico
        // cuando el cron no corrió a tiempo) se mueven aquí: sin esto la pantalla
        // nueva los ignora y los muestra como falta injustificada.
        if ($result['moved'] > 0) {
            $this->line("Asistencias re-ligadas a la catorcena nueva: {$result['moved']} (niveles de aprobación remapeados: {$result['levels_remapped']}).");
        }

        if ($result['levels_lost'] > 0) {
            $this->warn("{$result['levels_lost']} registro(s) quedaron sin nivel de aprobación: revisa /payrolls/{$opened->id}/extra-hours-config");
        }

        if ($result['groups'] === 0) {
            $this->warn('La catorcena anterior no tenía jerarquía configurada: configura los grupos en /payrolls/' . $opened->id . '/extra-hours-config');
        } else {
            $this->line('Los costos de hora extra NO se copian automáticamente: usa "Copiar configuración" en la pantalla de tiempo extra si los necesitas.');
        }

        return self::SUCCESS;
    }
}
