<?php

namespace App\Http\Controllers;

use App\Models\BioTimeTransactions;
use App\Models\Payroll;
use App\Models\PayrollUser;
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
        // Método para crear asistencia manual (desde NoAttendanceCard)
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
                'checked_in_platform' => true, // Marca que fue manual/plataforma
            ]
        );

        // Si ya existía pero estaba vacío (ej. día futuro), actualizamos
        if (!$payrollUser->wasRecentlyCreated) {
            $payrollUser->update([
                'check_in' => $request->check_in,
                'check_out' => $request->check_out,
                'incidence' => 'Día normal',
            ]);
        }

        // Calcular tiempos
        $payrollUser->calculateLate();
        $payrollUser->calculateExtraTime();
    }

    // --- MÉTODO ACTUALIZADO PARA CREAR EL REGISTRO SI NO EXISTÍA ---
    public function update(Request $request)
    {
        // Usamos updateOrCreate en lugar de where()->first()
        // Si el registro de ese día no existe físicamente (era una "falta" virtual), lo crea.
        // Si ya existe, simplemente lo actualiza con los nuevos datos.
        $payrollUser = PayrollUser::updateOrCreate(
            [
                'user_id' => $request->user_id,
                'date' => $request->date,
            ],
            [
                'payroll_id' => $request->payroll_id,
                'check_in' => $request->check_in,
                'check_out' => $request->check_out,
                'incidence' => 'Día normal', // Al poner horas, deja de ser falta/descanso
            ]
        );

        // Recalcular lógica de negocio (retardos y extras)
        $payrollUser->calculateLate();
        $payrollUser->calculateExtraTime();
    }

    public function setIncidence(Request $request)
    {
        // Busca o crea un registro en la tabla PayrollUser basado en el 'date' y el 'user_id'
        $payrollUser = PayrollUser::firstOrCreate(
            [
                'date' => $request->date,
                'user_id' => $request->user_id
            ],
            [
                'payroll_id' => $request->payroll_id,
                'incidence' => $request->incidence,
                // 'additionals' => ,
            ]
        );

        // descontar vacaciones en caso de ser la incidencia
        if ($request->incidence == 'Vacaciones') {
            $user = User::find($request->user_id);
            $props = $user->org_props;
            $props['vacations'] = $props['vacations'] - 1;
            $user->org_props = $props;
            $user->save();
        }

        // Solo actualiza si el registro ya existía
        if (!$payrollUser->wasRecentlyCreated) {
            if ($payrollUser->incidence == 'Vacaciones') { //si originalmente eran vacaciones
                $user = User::find($request->user_id);
                $props = $user->org_props;
                // volver a sumar el dia
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
        // Busca o crea un registro en la tabla PayrollUser basado en el 'date' y el 'user_id'
        $payrollUser = PayrollUser::firstOrCreate(
            [
                'date' => $request->date,
                'user_id' => $request->user_id
            ],
            [
                'check_in' => $request->check_in,
                'check_out' => $request->check_out,
                'payroll_id' => $request->payroll_id,
                // 'additionals' => ,
            ]
        );

        // Solo actualiza si el registro ya existía
        if (!$payrollUser->wasRecentlyCreated) {
            $payrollUser->check_in = $request->check_in;
            $payrollUser->check_out = $request->check_out;
            if ($payrollUser->incidence == 'Vacaciones') { //si originalmente eran vacaciones
                $user = User::find($request->user_id);
                $props = $user->org_props;
                // volver a sumar el dia
                $props['vacations'] = $props['vacations'] + 1;
                $user->org_props = $props;
                $user->save();
            }
            $payrollUser->incidence = null;
            $payrollUser->save();
        }

        // calcular y actualizar retardo y horas extras
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

                // --- PROTECCIÓN ANTI-RÁFAGA DE BIOTIME (1 minuto) ---
                $punchTimeParsed = Carbon::parse($punchTimeStr);
                $isDuplicate = false;

                if ($existingEntry->check_in) {
                    $checkInParsed = Carbon::parse($existingEntry->check_in);
                    if ($checkInParsed->diffInMinutes($punchTimeParsed) <= 1) {
                        $isDuplicate = true;
                    }
                }

                if ($existingEntry->check_out) {
                    $checkOutParsed = Carbon::parse($existingEntry->check_out);
                    if ($checkOutParsed->diffInMinutes($punchTimeParsed) <= 1) {
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
                    } else if (strtotime($punchTimeStr) <= strtotime('17:49')) {
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

    // --- NUEVO MÉTODO PARA RECALCULAR TIEMPO EXTRA ---
    public function recalculateExtraTime()
    {
        // 1. Obtener la nómina activa actual
        $currentPayroll = Payroll::firstWhere('is_active', true);

        if (!$currentPayroll) {
            return response()->json(['message' => 'No hay una nómina activa actualmente para recalcular.'], 404);
        }

        // 2. Obtener todos los registros de asistencia de esta nómina
        $attendances = PayrollUser::where('payroll_id', $currentPayroll->id)->get();
        $processedCount = 0;

        // 3. Iterar y recalcular
        foreach ($attendances as $attendance) {
            // Solo recalculamos si tiene hora de entrada y salida registradas
            if ($attendance->check_in && $attendance->check_out) {
                // El método ya hace el $this->update() por dentro
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
