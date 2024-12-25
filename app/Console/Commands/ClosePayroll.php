<?php

namespace App\Console\Commands;

use App\Models\Payroll;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ClosePayroll extends Command
{
    protected $signature = 'payrolls:close';
    protected $description = 'Cerrar catorcena y abrir una nueva';

    public function handle()
    {
        $current = Payroll::firstWhere('is_active', true);
        Payroll::create([
            'start_date' => $current->start_date->addDays(14)->toDateString(),
            'biweekly' => $current->biweekly + 1,
        ]);

        $current->update(['is_active' => 0]);

        $this->info('Catorcena cerrada y creada una nueva');
        Log::info('Catorcena cerrada y creada una nueva');
    }
}
