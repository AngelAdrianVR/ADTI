<?php

namespace App\Http\Controllers;

use App\Models\Payroll;
use Illuminate\Http\Request;

class PayrollController extends Controller
{
    public function index()
    {
        $payrolls = Payroll::latest()->get();

        return inertia('Payroll/Index', compact('payrolls'));
    }

    public function create()
    {
        //
    }

    public function store(Request $request)
    {
        //
    }

    public function show(Payroll $payroll)
    {
        // Carga los usuarios junto con su información en el pivote
        $payroll->load('users');

        // Formatea los datos de los usuarios y sus incidencias
        $formattedUsers = $payroll->users->groupBy('id')->map(function ($userGroup) use ($payroll) {
            $user = $userGroup->first();

            return [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'org_props' => $user->org_props,
                    // Otros datos relevantes del usuario
                ],
                'incidences' => $payroll->getProcessedAttendances($user->id)
            ];
        })->values()->all(); // Reinicia índices principales

        // Selecciona solo las propiedades específicas del objeto payroll
        $payrollData = [
            'id' => $payroll->id,
            'start_date' => $payroll->start_date,
            'biweekly' => $payroll->biweekly,
            'is_active' => $payroll->is_active,
        ];
    //    return [
    //     'payroll' => $payrollData,
    //     'users' => $formattedUsers,
    //    ];
        return inertia('Payroll/Show', [
            'payroll' => $payrollData,
            'users' => $formattedUsers,
        ]);
    }

    public function edit(Payroll $payroll)
    {
        //
    }

    public function update(Request $request, Payroll $payroll)
    {
        //
    }

    public function destroy(Payroll $payroll)
    {
        //
    }

    public function prePayrollTemplate()
    {
        return inertia('Payroll/PrePayrollTemplate');
    }
}
