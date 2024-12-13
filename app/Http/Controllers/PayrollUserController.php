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
            $currentPayroll = Payroll::firstWhere('is_active', true);

            $existingEntry = PayrollUser::where('user_id', $employee->id)
                ->where('payroll_id', $currentPayroll->id)
                ->whereDate('date', today()->toDateString())
                ->first();

            $time = str_replace('+', ' ', $time);
            $punchTime = Carbon::parse($time)->format('H:i');
            if (!$existingEntry) { //No existe registro de asistencia del empleado en cuestion
                $existingEntry = PayrollUser::create([
                    'emp_code' => $emp_code,
                    'date' => today()->toDateString(),
                    'check_in' => $punchTime,
                    'user_id' => $employee->id,
                    'payroll_id' => $currentPayroll->id,
                ]);
                $employee->update(['paused' => null]);
            } else { //Ya existe registro de asistencia
                if (strtotime($punchTime) <= strtotime('17:49')) {
                    $employee->setPause();
                } else {
                    $existingEntry->update([
                        'check_out' => $punchTime,
                    ]);
                    $employee->update(['paused' => null]);
                }
            }

            // sumar la transaccion a las procesadas del dia actual
            $todaysTransactions = BioTimeTransactions::firstOrCreate(
                ['date' => today()->toDateString()],
            );
            $todaysTransactions->increment('quantity');

            // Calcular tiempo extra y retardo
            $existingEntry->calculateLate();
            $existingEntry->calculateExtraTime();
        } else {
            Log::info("No se encontró al empleado con código {$emp_code}");
        }
    }
}
