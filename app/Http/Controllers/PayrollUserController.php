<?php

namespace App\Http\Controllers;

use App\Models\BioTimeTransactions;
use App\Models\Payroll;
use App\Models\PayrollUser;
use App\Models\PayrollComment; // <--- AGREGAR ESTA IMPORTACIÓN
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PayrollUserController extends Controller
{
    public function index()
    {
        //
    }

    public function store(Request $request)
    {
        $payrollUser = PayrollUser::firstOrCreate(
            [
                'date' => $request->date,
                'user_id' => $request->user_id
            ],
            [
                'payroll_id' => $request->payroll_id,
                'check_in' => $request->check_in,
                'check_out' => $request->check_out,
                'incidence' => 'Día normal',
                'checked_in_platform' => true, 
            ]
        );

        if (!$payrollUser->wasRecentlyCreated) {
            $payrollUser->update([
                'check_in' => $request->check_in,
                'check_out' => $request->check_out,
                'incidence' => 'Día normal',
            ]);
        }

        $payrollUser->calculateLate();
        $payrollUser->calculateExtraTime();
    }

    public function update(Request $request)
    {
        $payrollUser = PayrollUser::where('user_id', $request->user_id)
            ->where('date', $request->date)
            ->first();

        if ($payrollUser) {
            $payrollUser->update([
                'check_in' => $request->check_in,
                'check_out' => $request->check_out,
                'incidence' => 'Día normal', 
            ]);

            $payrollUser->calculateLate();
            $payrollUser->calculateExtraTime();
        }
    }

    public function setIncidence(Request $request)
    {
        $payrollUser = PayrollUser::firstOrCreate(
            [
                'date' => $request->date,
                'user_id' => $request->user_id
            ],
            [
                'payroll_id' => $request->payroll_id,
                'incidence' => $request->incidence,
            ]
        );

        if ($request->incidence == 'Vacaciones') {
            $user = User::find($request->user_id);
            $props = $user->org_props;
            $props['vacations'] = $props['vacations'] - 1;
            $user->org_props = $props;
            $user->save();
        }

        if (!$payrollUser->wasRecentlyCreated) {
            if ($payrollUser->incidence == 'Vacaciones') { 
                $user = User::find($request->user_id);
                $props = $user->org_props;
                $props['vacations'] = $props['vacations'] + 1;
                $user->org_props = $props;
                $user->save();
            }
            $payrollUser->incidence = $request->incidence;
            $payrollUser->save();
        }
    }

    public function setAttendance(Request $request)
    {
        $payrollUser = PayrollUser::firstOrCreate(
            [
                'date' => $request->date,
                'user_id' => $request->user_id
            ],
            [
                'check_in' => $request->check_in,
                'check_out' => $request->check_out,
                'payroll_id' => $request->payroll_id,
            ]
        );

        if (!$payrollUser->wasRecentlyCreated) {
            $payrollUser->check_in = $request->check_in;
            $payrollUser->check_out = $request->check_out;
            if ($payrollUser->incidence == 'Vacaciones') { 
                $user = User::find($request->user_id);
                $props = $user->org_props;
                $props['vacations'] = $props['vacations'] + 1;
                $user->org_props = $props;
                $user->save();
            }
            $payrollUser->incidence = null;
            $payrollUser->save();
        }

        $payrollUser->calculateLate();
        $payrollUser->calculateExtraTime();
    }

    public function processBioTimeTransaction($time, $emp_code)
    {
        $time = str_replace('+', ' ', $time);
        $punchDateTime = Carbon::parse($time); 
        $punchDateStr = $punchDateTime->toDateString(); 
        $punchTimeStr = $punchDateTime->format('H:i'); 

        // 1. CORRECCIÓN CRÍTICA: SIEMPRE sumamos la transacción procesada al historial
        $todaysTransactions = BioTimeTransactions::firstOrCreate(
            ['date' => $punchDateStr], 
        );
        $todaysTransactions->increment('quantity');

        // 2. Identificar al empleado
        $employee = User::firstWhere('code', $emp_code);
        
        if ($employee) {
            $currentPayroll = Payroll::where('start_date', '<=', $punchDateStr)
                                    ->whereRaw('? <= DATE_ADD(start_date, INTERVAL 13 DAY)', [$punchDateStr])
                                    ->first();

            if (!$currentPayroll) {
                $currentPayroll = Payroll::firstWhere('is_active', true);
                if (!$currentPayroll) {
                    Log::warning("No se encontró nómina activa o coincidente para el empleado {$emp_code} en la fecha {$punchDateStr}.");
                    return; 
                }
            }

            $existingEntry = PayrollUser::where('user_id', $employee->id)
                ->whereDate('date', $punchDateStr) 
                ->first();

            if (!$existingEntry) { 
                $existingEntry = PayrollUser::create([
                    'date' => $punchDateStr, 
                    'check_in' => $punchTimeStr, 
                    'user_id' => $employee->id,
                    'payroll_id' => $currentPayroll->id,
                ]);
                $employee->update(['paused' => null]);
            } else { 
                
                // --- PROTECCIÓN ANTI-RÁFAGA DE BIOTIME (3 minutos) ---
                $punchTimeParsed = Carbon::parse($punchTimeStr);
                $isDuplicate = false;

                if ($existingEntry->check_in) {
                    $checkInParsed = Carbon::parse($existingEntry->check_in);
                    if ($punchTimeParsed->diffInMinutes($checkInParsed) <= 3) {
                        $isDuplicate = true;
                    }
                }
                
                if ($existingEntry->check_out) {
                    $checkOutParsed = Carbon::parse($existingEntry->check_out);
                    if ($punchTimeParsed->diffInMinutes($checkOutParsed) <= 3) {
                        $isDuplicate = true;
                    }
                }

                if ($isDuplicate) {
                    Log::info("BioTime Sync: Checada ignorada por ser muy cercana a la anterior (Empleado {$emp_code} a las {$punchTimeStr})");
                } else {
                    // Procesar normalmente si pasó el tiempo de gracia
                    if ($existingEntry->check_in && !$existingEntry->check_out) {
                         $existingEntry->update([
                            'check_out' => $punchTimeStr,
                        ]);
                        $employee->update(['paused' => null]);
                    }
                    else if (strtotime($punchTimeStr) <= strtotime('17:49')) {
                        $employee->setPause();
                    } else {
                         $existingEntry->update([
                            'check_out' => $punchTimeStr,
                        ]);
                        $employee->update(['paused' => null]);
                    }
                }
                // -----------------------------------------------------
            }

            // Calcular tiempo extra y retardo
            $existingEntry->calculateLate();
            $existingEntry->calculateExtraTime();
            
        } else {
            // Se loguea solo UNA vez gracias a que el contador de Python ya avanzó arriba
            Log::warning("BioTime Sincronización: Se ignoró un registro porque no se encontró al empleado con código {$emp_code}.");
        }
    }

    public function removeLate(Request $request)
    {
        $payrollUser = PayrollUser::firstWhere(
            [
                'date' => $request->date,
                'user_id' => $request->user_id
            ],
        );

        $payrollUser->update(['late' => 0]);
    }

    // --- NUEVOS MÉTODOS PARA APROBACIÓN DE TIEMPO EXTRA ---

    public function approveExtraTime(Request $request)
    {
        $request->validate([
            'date' => 'required|date',
            'user_id' => 'required|exists:users,id',
            'payroll_id' => 'required|exists:payrolls,id',
            'approved_extra_hours' => 'required|integer|min:0',
            'approved_extra_minutes' => 'required|integer|min:0|max:59',
            'comments' => 'nullable|string|max:1200'
        ]);

        $payrollUser = PayrollUser::firstWhere([
            'date' => $request->date,
            'user_id' => $request->user_id
        ]);

        if ($payrollUser) {
            // Actualizamos la información de aprobación
            $payrollUser->update([
                'approved_extra_hours' => $request->approved_extra_hours,
                'approved_extra_minutes' => $request->approved_extra_minutes,
                'approved_by' => auth()->id(),
                'approved_at' => now(),
            ]);

            // Si se escribió un comentario, lo guardamos o actualizamos
            if ($request->filled('comments')) {
                PayrollComment::updateOrCreate(
                    [
                        'payroll_id' => $request->payroll_id,
                        'user_id' => $request->user_id,
                        'date' => $request->date,
                    ],
                    [
                        'comments' => $request->comments
                    ]
                );
            }
        }
        
        return back();
    }

    public function revertExtraTime(Request $request)
    {
        $request->validate([
            'date' => 'required|date',
            'user_id' => 'required|exists:users,id',
        ]);

        $payrollUser = PayrollUser::firstWhere([
            'date' => $request->date,
            'user_id' => $request->user_id
        ]);

        if ($payrollUser) {
            // Limpiamos los campos de aprobación
            $payrollUser->update([
                'approved_extra_hours' => null,
                'approved_extra_minutes' => null,
                'approved_by' => null,
                'approved_at' => null,
            ]);
        }
        
        return back();
    }

    // --- FIN NUEVOS MÉTODOS ---

    public function recalculateExtraTime()
    {
        $currentPayroll = Payroll::firstWhere('is_active', true);

        if (!$currentPayroll) {
            return response()->json(['message' => 'No hay una nómina activa actualmente para recalcular.'], 404);
        }

        $attendances = PayrollUser::where('payroll_id', $currentPayroll->id)->get();
        $processedCount = 0;

        foreach ($attendances as $attendance) {
            if ($attendance->check_in && $attendance->check_out) {
                $attendance->calculateExtraTime();
                $processedCount++;
            }
        }

        return response()->json([
            'message' => 'Recálculo completado con éxito.',
            'payroll_id' => $currentPayroll->id,
            'records_updated' => $processedCount
        ]);
    }
}