<?php

namespace App\Http\Controllers;

use App\Models\Holiday;
use App\Models\Payroll;
use App\Models\PayrollComment;
use App\Models\PayrollUser;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PayrollController extends Controller
{
    public function index()
    {
        $payrolls = Payroll::latest()->get();

        return inertia('Payroll/Index', compact('payrolls'));
    }

    public function show(Payroll $payroll)
    {
        $processedData = $this->getUserProcessedInfo($payroll);

        return inertia('Payroll/Show', $processedData);
    }

    public function prePayrollTemplate(Payroll $payroll)
    {
        $processedData = $this->getUserProcessedInfo($payroll);

        return inertia('Payroll/PrePayrollTemplate',  $processedData);
    }

    private function getUserProcessedInfo(Payroll $payroll)
    {
        // 1. Cargar usuarios básicos de la nómina
        $payroll->load('users');

        // 2. OPTIMIZACIÓN: Cargar TODOS los registros de asistencia de esta nómina en una sola consulta
        $allAttendances = PayrollUser::where('payroll_id', $payroll->id)
            ->get()
            ->groupBy('user_id');

        // 3. OPTIMIZACIÓN: Cargar TODOS los comentarios de esta nómina
        $allComments = PayrollComment::where('payroll_id', $payroll->id)
            ->get()
            ->keyBy('user_id');

        // 4. OPTIMIZACIÓN: Cargar días festivos del rango una sola vez
        $endDate = $payroll->start_date->copy()->addDays(14);
        $holidays = Holiday::whereBetween('date', [$payroll->start_date, $endDate])->get();

        // Formatea los datos de los usuarios y sus incidencias
        $formattedUsers = $payroll->users->groupBy('id')->map(function ($userGroup) use ($payroll, $allAttendances, $allComments, $holidays) {
            $user = $userGroup->first();
            
            // Obtener asistencias de memoria (evita query por usuario)
            $userAttendances = $allAttendances->get($user->id);

            return [
                'user' => [
                    'id' => $user->id,
                    'code' => $user->code,
                    'name' => $user->name,
                    'org_props' => $user->org_props,
                    'paused' => $user->paused,
                    'profile_photo_url' => $user->profile_photo_url, // Necesario para el diseño moderno
                ],
                // Pasar colecciones optimizadas al modelo
                'incidences' => $payroll->getProcessedAttendances($user->id, $userAttendances, $holidays),
                'comments' => $allComments->get($user->id),
            ];
        })->values()->all();

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
        return User::whereDoesntHave('payrolls', function ($query) use ($payroll_id) {
            $query->where('payroll_id', $payroll_id);
        })->where('is_active', true)->whereNotIn('org_props->position', ['Dirección', 'Soporte DTW'])->get();
    }
}