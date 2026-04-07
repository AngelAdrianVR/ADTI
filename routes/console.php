<?php

use App\Models\Payroll;
use Carbon\Carbon;
use Illuminate\Support\Facades\Schedule;

Schedule::command('payrolls:close')
    ->tuesdays() 
    ->at('00:00') // Ejecutar exactamente a las 12:00 AM
    ->timezone('America/Mexico_City') // Forzar tu zona horaria local
    ->when(function () {
        $activePayroll = Payroll::firstWhere('is_active', true);

        if (!$activePayroll || !$activePayroll->start_date) {
            return false; // No hay nómina activa o no tiene start_date
        }

        // Calcula si han pasado al menos 8 días desde start_date
        return Carbon::parse($activePayroll->start_date)->diffInDays(now()) > 8;
    });

// De paso, le aplicamos la misma regla a las vacaciones para que se calculen en la madrugada local
Schedule::command('users:update-vacations')
    ->dailyAt('01:00')
    ->timezone('America/Mexico_City');
// Schedule::command('payrolls:sync-incidents')->everyMinute();