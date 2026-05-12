<?php

use App\Models\Payroll;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schedule;

//En el servido el cronjob debe estar a 1 hora más de lo que aqui se programe, por ejemplo 
//si aquí se programa a las 5:00 AM, el cronjob en el servidor debe estar a las 6:00 AM para que se ejecute correctamente

Schedule::command('users:update-vacations')
    ->dailyAt('05:00');

Schedule::command('payrolls:close')
    ->tuesdays()
    ->at('05:00') // Ejecutar exactamente a las 5:00 AM
    // ->timezone('America/Mexico_City') // Forzar tu zona horaria local
    ->when(function () {
        $activePayroll = Payroll::firstWhere('is_active', true);

        if (!$activePayroll || !$activePayroll->start_date) {
            Log::warning('No hay nómina activa o no tiene start_date, no se puede cerrar la nómina.');
            return false; // No hay nómina activa o no tiene start_date
        }

        Log::info(Carbon::parse($activePayroll->start_date)->diffInDays(now()) . ' días han pasado desde el inicio de la nómina activa.');
        // Calcula si han pasado al menos 8 días desde start_date
        return Carbon::parse($activePayroll->start_date)->diffInDays(now()) > 8;
    });

// Sincronizar las vacaciones aprobadas en las nóminas activas/futuras
Schedule::command('payrolls:sync-vacations')
    ->dailyAt('05:00');
