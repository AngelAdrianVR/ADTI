<?php

namespace App\Console\Commands;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Console\Command;

class UpdateUserVacations extends Command
{
    protected $signature = 'users:update-vacations';
    protected $description = 'Actualiza las vacaciones de los empleados cada 7 días';

    public function handle()
    {
        $users = User::whereNotIn('id', [1,2])->get(); // todos menos los directivos

        foreach ($users as $user) {
            $lastUpdate = Carbon::parse($user->org_props['updated_date_vacations']);

            // Verifica si han pasado 7 días desde la última actualización
            if ($lastUpdate->diffInDays(now()) >= 7) {
                $user->updateVacations();
            }
        }

        $this->info('Vacaciones actualizadas para usuarios según las condiciones.');
    }
}
