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
                // Lógica simple: si ya hay check_in, este es el check_out.
                // (Se puede mejorar esta lógica si hay comidas, etc., pero seguimos la original)
                if ($existingEntry->check_in && !$existingEntry->check_out) {
                     $existingEntry->update([
                        'check_out' => $punchTimeStr,
                    ]);
                    $employee->update(['paused' => null]);
                }
                // Si ya hay check_in y check_out, podríamos loggear que es un punch extra
                // O si es antes de las 17:49 (lógica original), registrar pausa.
                else if (strtotime($punchTimeStr) <= strtotime('17:49')) {
                    $employee->setPause();
                } else {
                    // Si ya hay check_out, esto sobreescribirá el último.
                     $existingEntry->update([
                        'check_out' => $punchTimeStr,
                    ]);
                    $employee->update(['paused' => null]);
                }
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
}
