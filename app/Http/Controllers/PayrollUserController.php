<?php

namespace App\Http\Controllers;

use App\Models\PayrollUser;
use App\Models\User;
use Illuminate\Http\Request;

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
}
