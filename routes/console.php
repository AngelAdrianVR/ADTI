<?php

// use Illuminate\Foundation\Inspiring;
// use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

// Artisan::command('inspire', function () {
//     $this->comment(Inspiring::quote());
// })->purpose('Display an inspiring quote')->hourly();

Schedule::command('users:update-vacations')->daily();
Schedule::command('payrolls:close')->weeklyOn(2, '00:00') // El martes a la medianoche
    ->when(function () {
        // Solo ejecuta si la semana es par (es decir, cada dos semanas)
        return now()->weekOfYear % 2 === 0;
    });
