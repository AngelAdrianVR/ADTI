<?php

namespace App\Http\Controllers;

use App\Models\Payroll;
use App\Models\PayrollComment;
use App\Models\User;
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
        $processedData = $this->getUserProcessedInfo($payroll);

        return inertia('Payroll/Show', $processedData);
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

    public function prePayrollTemplate(Payroll $payroll)
    {
        $processedData = $this->getUserProcessedInfo($payroll);

        return inertia('Payroll/PrePayrollTemplate',  $processedData);
    }

    private function getUserProcessedInfo(Payroll $payroll)
    {
        // Carga los usuarios junto con su información en el pivote
        $payroll->load('users');

        // Formatea los datos de los usuarios y sus incidencias
        $formattedUsers = $payroll->users->groupBy('id')->map(function ($userGroup) use ($payroll) {
            $user = $userGroup->first();

            return [
                'user' => [
                    'id' => $user->id,
                    'code' => $user->code,
                    'name' => $user->name,
                    'org_props' => $user->org_props,
                    'paused' => $user->paused,
                ],
                'incidences' => $payroll->getProcessedAttendances($user->id),
                'comments' => PayrollComment::firstWhere(['user_id' => $user->id, 'payroll_id' => $payroll->id]),
            ];
        })->values()->all(); // Reinicia índices principales

        // Selecciona solo las propiedades específicas del objeto payroll
        $payrollData = [
            'id' => $payroll->id,
            'start_date' => $payroll->start_date,
            'biweekly' => $payroll->biweekly,
            'is_active' => $payroll->is_active,
        ];

        return [
            'payroll' => $payrollData,
            'payrollUsers' => $formattedUsers,
            'noAttendances' => $this->getUsersWithNoAttendance($payroll->id),
        ];
    }

    private function getUsersWithNoAttendance($payroll_id)
    {
        $usersWithNoAttendance = [];

        $usersWithNoAttendance = User::whereDoesntHave('payrolls', function ($query) use ($payroll_id) {
            $query->where('payroll_id', $payroll_id);
        })->where('is_active', true)->whereNotIn('org_props->position', ['Dirección', 'Desarrollador'])->get();

        return $usersWithNoAttendance;
    }
}
