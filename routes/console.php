<?php

use App\Models\Payroll;
use Carbon\Carbon;
use Illuminate\Support\Facades\Schedule;

Schedule::command('payrolls:close')
    ->tuesdays() // Revisa Martes a primera hora
    ->when(function () {
        $activePayroll = Payroll::firstWhere('is_active', true);

        if (!$activePayroll || !$activePayroll->start_date) {
            return false; // No hay nómina activa o no tiene start_date
        }

        // Calcula si han pasado al menos 8 días desde start_date
        return Carbon::parse($activePayroll->start_date)->diffInDays(now()) > 8;
    });
Schedule::command('users:update-vacations')->daily();
// Schedule::command('payrolls:sync-incidents')->everyMinute();