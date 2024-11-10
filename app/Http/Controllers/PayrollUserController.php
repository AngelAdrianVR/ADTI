<?php

namespace App\Http\Controllers;

use App\Models\PayrollUser;
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

        $payrollUser->incidence = $request->incidence;
        $payrollUser->save();
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
                'checked_in_platform' => $request->checked_in_platform,
                'payroll_id' => $request->payroll_id,
                // 'additionals' => ,
            ]
        );

        $payrollUser->check_in = $request->check_in;
        $payrollUser->check_out = $request->check_out;
        $payrollUser->checked_in_platform = $request->checked_in_platform;
        $payrollUser->incidence = null;
        $payrollUser->save();
    }
}
