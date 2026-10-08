<?php

namespace App\Console\Commands;

use App\Models\Payroll;
use App\Models\PayrollUser;
use App\Models\PayrollComment;
use App\Models\VacationRequest;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SyncApprovedVacations extends Command
{
    protected $signature = 'payrolls:sync-vacations';
    protected $description = 'Revisa solicitudes de vacaciones aprobadas y genera el registro en la nómina correspondiente.';

    public function handle()
    {
        // 1. Traemos las nóminas activas o las que sean a futuro
        $payrolls = Payroll::where('is_active', true)
            ->orWhere('start_date', '>=', now()->toDateString())
            ->get();

        $processedDays = 0;

        foreach ($payrolls as $payroll) {
            $payrollStart = Carbon::parse($payroll->start_date);
            $payrollEnd = $payrollStart->copy()->addDays(13); // 14 días de la catorcena

            // 2. Buscar solicitudes aprobadas que coincidan con las fechas de esta nómina
            $requests = VacationRequest::with('reviewer')
                ->where('status', 'Aprobada')
                ->where('start_date', '<=', $payrollEnd->toDateString())
                ->where('end_date', '>=', $payrollStart->toDateString())
                ->get();

            foreach ($requests as $request) {
                $reqStart = Carbon::parse($request->start_date);
                $reqEnd = Carbon::parse($request->end_date);

                // 3. Iterar por cada día del rango solicitado
                for ($date = $reqStart->copy(); $date->lte($reqEnd); $date->addDay()) {
                    
                    // Solo procedemos si el día solicitado cae dentro de esta nómina
                    if ($date->between($payrollStart, $payrollEnd)) {
                        
                        // Opcional: Omitir domingos si en tu empresa no se cuentan como días de vacaciones
                        if ($date->dayOfWeek === 0) continue; 

                        $dateString = $date->toDateString();

                        // Buscar o crear el registro de asistencia de ese día
                        $payrollUser = PayrollUser::firstOrCreate(
                            [
                                'user_id' => $request->user_id,
                                'date' => $dateString,
                            ],
                            [
                                'payroll_id' => $payroll->id,
                                'incidence' => 'Vacaciones',
                            ]
                        );

                        // Si el registro ya existía pero no tenía la incidencia de Vacaciones, lo actualizamos
                        $wasUpdated = false;
                        if (!$payrollUser->wasRecentlyCreated && $payrollUser->incidence !== 'Vacaciones') {
                            $payrollUser->update(['incidence' => 'Vacaciones']);
                            $wasUpdated = true;
                        }

                        // 4. Generar el comentario automático de auditoría
                        if ($payrollUser->wasRecentlyCreated || $wasUpdated) {
                            $reviewerName = $request->reviewer ? $request->reviewer->name : 'Personal Autorizado';
                            $resolvedAt = $request->resolved_at ? Carbon::parse($request->resolved_at)->format('d/m/Y') : 'recientemente';

                            PayrollComment::firstOrCreate(
                                [
                                    'payroll_id' => $payroll->id,
                                    'user_id' => $request->user_id,
                                    'date' => $dateString,
                                ],
                                [
                                    'comments' => "Vacaciones autorizadas por {$reviewerName} el {$resolvedAt}."
                                ]
                            );
                            $processedDays++;
                        }
                    }
                }
            }
        }

        if ($processedDays > 0) {
            Log::info("Sincronización completada. Se registraron {$processedDays} días de vacaciones en las nóminas.");
            $this->info("Se registraron {$processedDays} días de vacaciones.");
        } else {
            $this->info("No se encontraron nuevos días de vacaciones por sincronizar.");
        }
    }
}