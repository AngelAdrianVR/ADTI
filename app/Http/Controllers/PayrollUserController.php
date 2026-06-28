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
                        'check_out' => null,
                        'break_start' => null,
                        'break_end' => null,
                        'break_minutes' => null,
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

        // Procesar tiempo de comida/break
        $this->processBreakUpdate($payrollUser, $request);

        // Recalcular lógica de negocio
        $payrollUser->calculateLate();
        $payrollUser->calculateExtraTime();
    }

    /**
     * Procesa la actualización de tiempos de comida desde el modal de edición.
     */
    private function processBreakUpdate(PayrollUser $payrollUser, Request $request)
    {
        $breakStart = $request->input('break_start');
        $breakEnd = $request->input('break_end');

        // Ambos vacíos: eliminar el registro de comida
        if (empty($breakStart) && empty($breakEnd)) {
            $payrollUser->update([
                'break_start' => null,
                'break_end' => null,
                'break_minutes' => null,
            ]);
            return;
        }

        // Solo inicio: dejar como pausa en curso (sin calcular minutos)
        if (!empty($breakStart) && empty($breakEnd)) {
            $payrollUser->update([
                'break_start' => $breakStart,
                'break_end' => null,
                'break_minutes' => null,
            ]);
            return;
        }

        // Ambos presentes: calcular la duración
        if (!empty($breakStart) && !empty($breakEnd)) {
            try {
                $start = Carbon::createFromFormat('H:i', trim($breakStart));
                $end = Carbon::createFromFormat('H:i', trim($breakEnd));

                // Si el fin es menor que el inicio (cruzó medianoche)
                if ($end->lessThan($start)) {
                    $end->addDay();
                }

                $minutes = $start->diffInMinutes($end);

                $payrollUser->update([
                    'break_start' => $breakStart,
                    'break_end' => $breakEnd,
                    'break_minutes' => $minutes,
                ]);
            } catch (\Exception $e) {
                Log::error('Error al calcular break en edición manual', [
                    'break_start' => $breakStart,
                    'break_end' => $breakEnd,
                    'error' => $e->getMessage(),
                ]);
                // Guardar sin calcular minutos
                $payrollUser->update([
                    'break_start' => $breakStart,
                    'break_end' => $breakEnd,
                    'break_minutes' => null,
                ]);
            }
            return;
        }

        // Solo fin sin inicio: no tiene sentido, ignorar
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

            // --- LÓGICA DE TURNOS ABIERTOS (SOPORTE NOCTURNO) ---
            $existingEntry = null;

            // 1. Buscar turno abierto reciente (menos de 18 horas desde el check-in)
            $openEntry = PayrollUser::where('user_id', $employee->id)
                ->whereNotNull('check_in')
                ->whereNull('check_out')
                ->orderBy('date', 'desc')
                ->first();

            if ($openEntry) {
                $safeDate = Carbon::parse($openEntry->date)->toDateString();
                $checkInDateTime = Carbon::parse($safeDate . ' ' . trim($openEntry->check_in));
                // Si la checada pertenece a un turno abierto válido (e.g., salida de la mañana tras entrada nocturna)
                if ($checkInDateTime->diffInHours($punchDateTime, false) >= 0 && $checkInDateTime->diffInHours($punchDateTime) < 18) {
                    $existingEntry = $openEntry;
                }
            }

            // 2. Si no es un cierre de un turno previo, buscamos/creamos la entrada para hoy
            if (!$existingEntry) {
                $existingEntry = PayrollUser::where('user_id', $employee->id)
                    ->whereDate('date', $punchDateStr)
                    ->first();
            }

            if (!$existingEntry) { // No existe registro válido o abierto
                $existingEntry = PayrollUser::create([
                    'date' => $punchDateStr,
                    'check_in' => $punchTimeStr, // Es el primer punch
                    'user_id' => $employee->id,
                    'payroll_id' => $currentPayroll->id,
                ]);
                $employee->update(['paused' => null]);
            } else { // Ya existe registro (posible cierre de turno)

                // --- PROTECCIÓN ANTI-RÁFAGA DE BIOTIME (CON SOPORTE NOCTURNO) ---
                $isDuplicate = false;

                if ($existingEntry->check_in) {
                    $safeDate = Carbon::parse($existingEntry->date)->toDateString();
                    $ciDateTime = Carbon::parse($safeDate . ' ' . trim($existingEntry->check_in));
                    if (abs($punchDateTime->diffInMinutes($ciDateTime, false)) <= 3) {
                        $isDuplicate = true;
                    }
                }

                if ($existingEntry->check_out && !$isDuplicate) {
                    $safeDate = Carbon::parse($existingEntry->date)->toDateString();
                    $coDateTime = Carbon::parse($safeDate . ' ' . trim($existingEntry->check_out));
                    // Si cruzó la medianoche, reajustamos el día del check_out para la comparación
                    if ($existingEntry->check_in && $coDateTime->lessThan(Carbon::parse($safeDate . ' ' . trim($existingEntry->check_in)))) {
                        $coDateTime->addDay();
                    }
                    if (abs($punchDateTime->diffInMinutes($coDateTime, false)) <= 3) {
                        $isDuplicate = true;
                    }
                }

                if ($isDuplicate) {
                    Log::info("BioTime Sync: Checada ignorada por ser muy cercana a la anterior (Empleado {$emp_code} a las {$punchTimeStr})");
                } else {
                    // --- DETECCIÓN DE REGRESO DE COMIDA (BREAK END) ---
                    // Si el registro ya tiene break_start pero NO break_end, y el nuevo punch
                    // está en un horario razonable después del break_start, es un regreso de comida.
                    if ($existingEntry->break_start && !$existingEntry->break_end) {
                        $safeDate = Carbon::parse($existingEntry->date)->toDateString();
                        $breakStartDateTime = Carbon::parse($safeDate . ' ' . trim($existingEntry->break_start));
                        $minutesSinceBreakStart = $breakStartDateTime->diffInMinutes($punchDateTime, false);
                        
                        // Solo consideramos regreso de comida si pasaron entre 20 min y 3 hrs desde el inicio
                        if ($minutesSinceBreakStart >= 20 && $minutesSinceBreakStart <= 180) {
                            // Es un regreso de comida: registrar fin del break y reabrir turno
                            $existingEntry->endBreak($punchTimeStr);
                            // Reabrir el turno: borrar check_out para permitir la salida de la tarde
                            $existingEntry->update(['check_out' => null]);
                            $employee->update(['paused' => null]);
                            
                            Log::info("BioTime Sync: Regreso de comida detectado para empleado {$emp_code} a las {$punchTimeStr}");
                        } else {
                            // Fuera del rango esperado para comida, tratar como nuevo check_in normal
                            // (actualizando el existente)
                            if ($minutesSinceBreakStart > 180) {
                                // Pasaron más de 3 horas, probablemente ya terminó el turno
                                // Registrar el fin del break de todas formas
                                $existingEntry->endBreak($punchTimeStr);
                                $existingEntry->update(['check_out' => $punchTimeStr]);
                                $employee->update(['paused' => null]);
                            } else {
                                // Menos de 20 min, probablemente solo fue al baño o similar
                                // Cancelar el break_start (no era comida real)
                                $existingEntry->update([
                                    'break_start' => null,
                                    'break_end' => null,
                                    'break_minutes' => null,
                                    'check_out' => $punchTimeStr,
                                ]);
                                $employee->update(['paused' => null]);
                            }
                        }
                    }
                    // Procesar normalmente si pasó el tiempo de gracia
                    elseif ($existingEntry->check_in && !$existingEntry->check_out) {
                        // Si hay un break abierto (iniciado por web), cerrarlo ahora
                        if ($existingEntry->break_start && !$existingEntry->break_end) {
                            $existingEntry->endBreak($punchTimeStr);
                        }

                        // Cerrando turno abierto (posible check_out o inicio de comida)
                        $punchHour = (int) $punchDateTime->format('H');
                        $punchMinute = (int) $punchDateTime->format('i');
                        $punchTotalMinutes = $punchHour * 60 + $punchMinute;

                        // Detectar si esta salida es para comida (entre 11:00 y 15:00)
                        $isLunchTime = ($punchTotalMinutes >= 660 && $punchTotalMinutes <= 900); // 11:00-15:00
                        
                        // Verificar que la entrada fue en la mañana (antes de las 12:00)
                        $checkInHour = (int) Carbon::parse($existingEntry->check_in)->format('H');
                        $isMorningEntry = ($checkInHour < 12);

                        if ($isLunchTime && $isMorningEntry) {
                            // Es salida para comida: registrar check_out Y break_start
                            $existingEntry->update(['check_out' => $punchTimeStr]);
                            $existingEntry->startBreak($punchTimeStr);
                            $employee->update(['paused' => null]);
                            
                            Log::info("BioTime Sync: Inicio de comida detectado para empleado {$emp_code} a las {$punchTimeStr}");
                        } else {
                            // Es salida normal (fin de turno)
                            $existingEntry->update(['check_out' => $punchTimeStr]);
                            $employee->update(['paused' => null]);
                        }
                    } else {
                        // Lógica especial de pausa (protegida para que no afecte a turnos nocturnos)
                        $shift = $employee->org_props['work_shift'] ?? 'Turno 3 (09:00 - 18:00)';
                        // Solo pausa automática si es turno de día y antes de las 17:39
                        $isDayShift = in_array($shift, ['Turno 1 (06:00 - 14:00)', 'Turno 3 (09:00 - 18:00)', 'Diurno']);
                        if ($isDayShift && strtotime($punchTimeStr) <= strtotime('17:39')) {
                            $employee->setPause();
                        } else {
                            $existingEntry->update([
                                'check_out' => $punchTimeStr,
                            ]);
                            $employee->update(['paused' => null]);
                        }
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

    /**
     * Aprobar tiempo extra con opción de ajuste de horas y guardar comentarios del proyecto.
     */
    public function approveExtraTime(Request $request)
    {
        $request->validate([
            'date' => 'required|date',
            'user_id' => 'required|exists:users,id',
            'payroll_id' => 'required|exists:payrolls,id',
            'approved_extra_hours' => 'required|numeric|min:0',
            'approved_extra_minutes' => 'required|numeric|min:0|max:59',
            'comments' => 'nullable|string'
        ]);

        $payrollUser = PayrollUser::where('user_id', $request->user_id)
            ->whereDate('date', clone \Carbon\Carbon::parse($request->date))
            ->first();

        if ($payrollUser) {
            $payrollUser->update([
                'approved_extra_hours' => $request->approved_extra_hours,
                'approved_extra_minutes' => $request->approved_extra_minutes,
                'approved_by' => auth()->id(),
                'approved_at' => now(),
            ]);

            // Guardar o actualizar el comentario (Nombre del proyecto o justificación)
            if ($request->filled('comments')) {
                \App\Models\PayrollComment::updateOrCreate(
                    [
                        'user_id' => $request->user_id,
                        'payroll_id' => $request->payroll_id,
                        'date' => clone \Carbon\Carbon::parse($request->date),
                    ],
                    ['comments' => $request->comments]
                );
            }
        }

        // Detectar si la petición viene de Inertia o de Axios
        if ($request->header('X-Inertia')) {
            return back();
        }

        return response()->json(['success' => true]);
    }

    /**
     * Rechazar tiempo extra (marcarlo como 0 horas aprobadas)
     */
    public function rejectExtraTime(Request $request)
    {
        $request->validate([
            'date' => 'required|date',
            'user_id' => 'required|exists:users,id',
            'payroll_id' => 'required|exists:payrolls,id',
            'comments' => 'nullable|string'
        ]);

        $payrollUser = PayrollUser::where('user_id', $request->user_id)
            ->whereDate('date', clone \Carbon\Carbon::parse($request->date))
            ->first();

        if ($payrollUser) {
            $payrollUser->update([
                'approved_extra_hours' => 0,
                'approved_extra_minutes' => 0,
                'approved_by' => auth()->id(),
                'approved_at' => now(),
            ]);

            if ($request->filled('comments')) {
                \App\Models\PayrollComment::updateOrCreate(
                    [
                        'user_id' => $request->user_id,
                        'payroll_id' => $request->payroll_id,
                        'date' => clone \Carbon\Carbon::parse($request->date),
                    ],
                    ['comments' => $request->comments]
                );
            }
        }

        // Detectar si la petición viene de Inertia o de Axios
        if ($request->header('X-Inertia')) {
            return back();
        }

        return response()->json(['success' => true]);
    }

    /**
     * Revertir una resolución para que vuelva a la lista de pendientes
     */
    public function revertExtraTime(Request $request)
    {
        $request->validate([
            'date' => 'required|date',
            'user_id' => 'required|exists:users,id',
        ]);

        $payrollUser = PayrollUser::where('user_id', $request->user_id)
            ->whereDate('date', clone \Carbon\Carbon::parse($request->date))
            ->first();

        if ($payrollUser) {
            $payrollUser->update([
                'approved_extra_hours' => null,
                'approved_extra_minutes' => null,
                'approved_by' => null,
                'approved_at' => null,
            ]);

            // Opcional: También podrías eliminar el PayrollComment aquí si lo deseas, 
            // pero mantenerlo suele ser útil para que el texto siga ahí al volver a evaluar.
        }

        // Detectar si la petición viene de Inertia o de Axios
        if ($request->header('X-Inertia')) {
            return back();
        }

        return response()->json(['success' => true]);
    }

    /**
     * Eliminar el tiempo extra de un día específico.
     * Borra horas/minutos extra y cualquier dato de aprobación asociado.
     */
    public function clearExtraTime(Request $request)
    {
        $request->validate([
            'date' => 'required|date',
            'user_id' => 'required|exists:users,id',
        ]);

        $payrollUser = PayrollUser::where('user_id', $request->user_id)
            ->whereDate('date', clone \Carbon\Carbon::parse($request->date))
            ->first();

        if ($payrollUser) {
            // Limpiar tiempo extra calculado y aprobación
            $payrollUser->update([
                'extra_hours' => null,
                'extra_minutes' => null,
                'approved_extra_hours' => null,
                'approved_extra_minutes' => null,
                'approved_by' => null,
                'approved_at' => null,
            ]);

            // Eliminar decisiones de aprobación asociadas
            \App\Models\ExtraHourApprovalDecision::where('payroll_user_id', $payrollUser->id)->delete();
        }

        // Detectar si la petición viene de Inertia o de Axios
        if ($request->header('X-Inertia')) {
            return back();
        }

        return response()->json(['success' => true]);
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

    /**
     * Vincula o desvincula un proyecto a un día específico de un usuario.
     */
    public function setProject(Request $request)
    {
        $request->validate([
            'date' => 'required|date',
            'user_id' => 'required|exists:users,id',
            'project_id' => 'nullable|exists:projects,id',
        ]);

        $payrollUser = PayrollUser::where('user_id', $request->user_id)
            ->whereDate('date', $request->date)
            ->first();

        if ($payrollUser) {
            $payrollUser->update([
                'project_id' => $request->project_id,
            ]);
        }

        if ($request->header('X-Inertia')) {
            return back();
        }

        return response()->json(['success' => true]);
    }
}
