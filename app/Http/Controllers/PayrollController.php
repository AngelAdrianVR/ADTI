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

        // Buscar nóminas adyacentes para la navegación
        $prevPayroll = Payroll::where('id', '<', $payroll->id)->orderBy('id', 'desc')->first();
        $nextPayroll = Payroll::where('id', '>', $payroll->id)->orderBy('id', 'asc')->first();

        // Agregar datos de navegación al array de respuesta
        $processedData['adjacentPayrolls'] = [
            'prev' => $prevPayroll ? $prevPayroll->id : null,
            'next' => $nextPayroll ? $nextPayroll->id : null,
        ];

        return inertia('Payroll/Show', $processedData);
    }

    public function prePayrollTemplate(Request $request, Payroll $payroll)
    {
        $userIds = $request->query('user_ids');
        
        if (is_string($userIds)) {
            $userIds = explode(',', $userIds);
        }

        $processedData = $this->getUserProcessedInfo($payroll, $userIds);

        return inertia('Payroll/PrePayrollTemplate',  $processedData);
    }

     private function getUserProcessedInfo(Payroll $payroll, $userIds = null)
    {
        $currentUser = auth()->user();
        
        // 1. Determinar qué usuarios mostrar basado en permisos y jerarquía
        // Si tiene permiso global, ve todo. Si no, verificamos si tiene empleados a cargo.
        if ($currentUser->can('Ver incidencias')) {
            // Cargar todos los usuarios de la nómina
            $payroll->load('users');
            $usersCollection = $payroll->users;
        } elseif (!empty($currentUser->employees_in_charge)) {
            // Solo cargar los usuarios que están a su cargo Y que están en esta nómina
            $employeesIds = $currentUser->employees_in_charge;
            
            $usersCollection = $payroll->users()
                ->whereIn('users.id', $employeesIds)
                ->get();
        } else {
            // No tiene permisos ni empleados a cargo -> colección vacía
            $usersCollection = collect([]);
        }

        // Si se pasaron IDs específicos para filtrar (ej. desde el buscador o para imprimir), aplicarlo sobre la colección permitida
        if (!empty($userIds)) {
            $usersCollection = $usersCollection->whereIn('id', $userIds);
        }

        // Obtener los IDs finales a procesar
        $finalUserIds = $usersCollection->pluck('id');

        // 2. Cargar datos SOLO para los usuarios filtrados (Optimización)
        $allAttendances = PayrollUser::where('payroll_id', $payroll->id)
            ->whereIn('user_id', $finalUserIds)
            ->get()
            ->groupBy('user_id');

        $allComments = PayrollComment::where('payroll_id', $payroll->id)
            ->whereIn('user_id', $finalUserIds)
            ->get()
            ->groupBy('user_id');

        $endDate = $payroll->start_date->copy()->addDays(14);
        $holidays = Holiday::whereBetween('date', [$payroll->start_date, $endDate])->get();

        $formattedUsers = $usersCollection->groupBy('id')->map(function ($userGroup) use ($payroll, $allAttendances, $allComments, $holidays) {
            $user = $userGroup->first();
            $userAttendances = $allAttendances->get($user->id);
            
            // Obtener todos los comentarios del usuario
            $userComments = $allComments->get($user->id) ?? collect([]);
            
            // Separar el comentario general (donde date es null)
            $generalComment = $userComments->whereNull('date')->first();

            // Mapear comentarios por fecha para acceso rápido O(1)
            $commentsByDate = $userComments->whereNotNull('date')->keyBy(function($item) {
                return $item->date->toDateString();
            });

            // Procesar incidencias
            $incidences = $payroll->getProcessedAttendances($user->id, $userAttendances, $holidays);

            // Inyectar comentarios dentro de las incidencias correspondientes
            foreach ($incidences as $incidence) {
                $dateKey = $incidence->date->toDateString();
                if ($commentsByDate->has($dateKey)) {
                    $incidence->comment = $commentsByDate->get($dateKey);
                }
            }

            return [
                'user' => [
                    'id' => $user->id,
                    'code' => $user->code,
                    'name' => $user->name,
                    'org_props' => $user->org_props,
                    'paused' => $user->paused,
                    'profile_photo_url' => $user->profile_photo_url,
                ],
                'incidences' => $incidences,
                'comments' => $generalComment,
            ];
        })->values()->all();

        $payrollData = [
            'id' => $payroll->id,
            'start_date' => $payroll->start_date,
            'biweekly' => $payroll->biweekly,
            'is_active' => $payroll->is_active,
        ];

        return [
            'payroll' => $payrollData,
            'payrollUsers' => $formattedUsers,
            'noAttendances' => $this->getUsersWithNoAttendance($payroll->id, $currentUser),
        ];
    }

    private function getUsersWithNoAttendance($payroll_id, $currentUser)
    {
        $query = User::whereDoesntHave('payrolls', function ($query) use ($payroll_id) {
            $query->where('payroll_id', $payroll_id);
        })
        ->where('is_active', true)
        ->whereNotIn('org_props->position', ['Dirección', 'Soporte DTW']);

        // Aplicar el mismo filtro de permisos para la lista de "Sin Asistencia"
        if (!$currentUser->can('Ver incidencias')) {
            if (!empty($currentUser->employees_in_charge)) {
                // Solo ver empleados a su cargo
                $query->whereIn('id', $currentUser->employees_in_charge);
            } else {
                // Si no tiene permisos ni empleados, no ve a nadie (retorna vacío)
                $query->whereRaw('1 = 0');
            }
        }

        return $query->get();
    }
}