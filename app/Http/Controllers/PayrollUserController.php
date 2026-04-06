<?php

namespace App\Http\Controllers;

use App\Models\BioTimeTransactions;
use App\Models\Payroll;
use App\Models\PayrollUser;
use App\Models\PayrollComment;
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

    public function update(Request $request)
    {
        // Si el administrador deja los campos de entrada y salida vacíos
        if (empty($request->check_in) && empty($request->check_out)) {
            $existing = PayrollUser::where('user_id', $request->user_id)
                ->where('date', $request->date)
                ->first();
                
            if ($existing) {
                // Borramos el registro completo si era un día normal (para que vuelva a ser Falta).
                // Protegemos si tenía otra incidencia (ej. Vacaciones) para no borrarla por accidente.
                if ($existing->incidence === 'Día normal' || empty($existing->incidence)) {
                    $existing->delete();
                } else {
                    $existing->update([
                        'check_in' => null,
                        'check_out' => null
                    ]);
                    $existing->calculateLate();
                    $existing->calculateExtraTime();
                }
            }
            return;
        }

        // Si se enviaron horas, actualizamos o creamos el registro
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

        // Recalcular lógica de negocio
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
        // Identificar si es entrada o salida
        $employee = User::firstWhere('code', $emp_code);
        if ($employee) {

            // --- INICIO DE CAMBIOS ---
            
            $time = str_replace('+', ' ', $time);
            $punchDateTime = Carbon::parse($time); // Parsear el timestamp completo
            $punchDateStr = $punchDateTime->toDateString(); // Obtener la FECHA del punch
            $punchTimeStr = $punchDateTime->format('H:i'); // Obtener la HORA del punch

            // --- CORRECCIÓN DE CONSULTA ---
            // Buscar el período de nómina que CONTENGA esta fecha.
            // Ya que no hay 'end_date', calculamos el fin sumando 13 días a start_date (para un período de 14 días).
            $currentPayroll = Payroll::where('start_date', '<=', $punchDateStr)
                                    ->whereRaw('? <= DATE_ADD(start_date, INTERVAL 13 DAY)', [$punchDateStr])
                                    ->first();

            // Fallback a la nómina activa si no se encuentra un período (lógica original)
            if (!$currentPayroll) {
                $currentPayroll = Payroll::firstWhere('is_active', true);
                if (!$currentPayroll) {
                    Log::warning("No se encontró nómina activa o coincidente para el empleado {$emp_code} en la fecha {$punchDateStr}.");
                    return; // Salir si no hay nómina
                }
            }
            // --- FIN DE CORRECCIÓN DE CONSULTA ---

            // Buscar el registro de asistencia usando la FECHA DEL PUNCH, no la de hoy
            $existingEntry = PayrollUser::where('user_id', $employee->id)
                ->whereDate('date', $punchDateStr) // <-- CAMBIO CRÍTICO
                ->first();

            if (!$existingEntry) { //No existe registro de asistencia del empleado en cuestion
                $existingEntry = PayrollUser::create([
                    // 'emp_code' => $emp_code, // Este campo no existe en el modelo PayrollUser
                    'date' => $punchDateStr, // <-- CAMBIO CRÍTICO
                    'check_in' => $punchTimeStr, // Es el primer punch, se asigna a check_in
                    'user_id' => $employee->id,
                    'payroll_id' => $currentPayroll->id,
                ]);
                $employee->update(['paused' => null]);
            } else { //Ya existe registro de asistencia
                
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

            // sumar la transaccion a las procesadas del DIA DEL PUNCH
            $todaysTransactions = BioTimeTransactions::firstOrCreate(
                ['date' => $punchDateStr], // <-- CAMBIO CRÍTICO
            );
            $todaysTransactions->increment('quantity');

            // --- FIN DE CAMBIOS ---

            // Calcular tiempo extra y retardo
            $existingEntry->calculateLate();
            $existingEntry->calculateExtraTime();
        } else {
            Log::info("No se encontró al empleado con código {$emp_code}");
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

    // --- MÉTODOS PARA APROBACIÓN DE TIEMPO EXTRA ---

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