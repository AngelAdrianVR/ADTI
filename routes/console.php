<?php

// use Illuminate\Foundation\Inspiring;
// use Illuminate\Support\Facades\Artisan;

use App\Models\Payroll;
use Carbon\Carbon;
use Illuminate\Support\Facades\Schedule;

// Artisan::command('inspire', function () {
//     $this->comment(Inspiring::quote());
// })->purpose('Display an inspiring quote')->hourly();

Schedule::command('users:update-vacations')->daily();
Schedule::command('payrolls:close')
    ->tuesdays() // Revisa cada día a medianoche
    ->when(function () {
        $activePayroll = Payroll::firstWhere('is_active', true);

        if (!$activePayroll || !$activePayroll->start_date) {
            return false; // No hay nómina activa o no tiene start_date
        }

        // Calcula si han pasado al menos 12 días desde start_date
        return now()->diffInDays(Carbon::parse($activePayroll->start_date)) >= 12;
    });
