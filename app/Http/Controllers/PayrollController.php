<?php

namespace App\Http\Controllers;

use App\Models\ExtraHourApprovalDecision;
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
        $payrolls = Payroll::latest()
            ->withCount(['extraHourCosts', 'approvalGroups'])
            ->get();

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

    public function receiptsTemplate(Request $request, Payroll $payroll)
    {
        $userIds = $request->query('user_ids');
        
        if (is_string($userIds)) {
            $userIds = explode(',', $userIds);
        }

        $processedData = $this->getUserProcessedInfo($payroll, $userIds);

        return inertia('Payroll/PayrollReceiptTemplate',  $processedData);
    }

     private function getUserProcessedInfo(Payroll $payroll, $userIds = null)
    {
        $currentUser = auth()->user();
        
        // 1. Determinar qué usuarios mostrar basado en permisos y jerarquía
        $query = User::whereNotIn('org_props->position', ['Dirección', 'Soporte DTW'])
            ->where(function ($q) use ($payroll) {
                $q->where('is_active', true)
                  ->orWhereHas('payrolls', function ($sub) use ($payroll) {
                      $sub->where('payroll_id', $payroll->id);
                  });
            });

        // Aplicamos la jerarquía y permisos
        if (!$currentUser->can('Ver incidencias')) {
            if (!empty($currentUser->employees_in_charge)) {
                // Solo cargar los usuarios a su cargo + a sí mismo
                $employeesIds = $currentUser->employees_in_charge;
                if (!in_array($currentUser->id, $employeesIds)) {
                    $employeesIds[] = $currentUser->id;
                }
                $query->whereIn('id', $employeesIds);
            } else {
                // No tiene permisos ni empleados a cargo -> no ve a nadie
                $query->whereRaw('1 = 0');
            }
        }

        // Si se pasaron IDs específicos para filtrar (ej. desde el buscador o para imprimir), aplicarlo
        if (!empty($userIds)) {
            $query->whereIn('id', $userIds);
        }

        // Obtener los IDs finales a procesar
        $usersCollection = $query->get();
        $finalUserIds = $usersCollection->pluck('id');

        // 2. Cargar datos SOLO para los usuarios filtrados (Optimización)
        $allAttendances = PayrollUser::with(['approver', 'project'])
            ->where('payroll_id', $payroll->id)
            ->whereIn('user_id', $finalUserIds)
            ->get()
            ->groupBy('user_id');

        // Cargar decisiones de aprobación por separado (más fiable que eager-load en Pivot)
        $payrollUserIds = $allAttendances->flatten(1)->pluck('id');
        $allDecisions = ExtraHourApprovalDecision::whereIn('payroll_user_id', $payrollUserIds)
            ->with(['approver', 'approvalLevel'])
            ->get()
            ->groupBy('payroll_user_id');

        $allComments = PayrollComment::where('payroll_id', $payroll->id)
            ->whereIn('user_id', $finalUserIds)
            ->get()
            ->groupBy('user_id');

        $endDate = $payroll->start_date->copy()->addDays(14);
        $holidays = Holiday::whereBetween('date', [$payroll->start_date, $endDate])->get();

        // 2.5 Cargar proyectos activos para vinculación por día
        $projects = \App\Models\Project::where('status', 'active')
            ->select('id', 'name', 'client')
            ->orderBy('name')
            ->get();

        // 3. Cargar costos de hora extra configurados para esta nómina
        $extraHourCosts = $payroll->extraHourCosts()->get();

        // 4. Cargar grupos de autorización con sus aprobadores y empleados (formato anidado eficiente)
        $approvalGroups = $payroll->approvalGroups()
            ->with(['employees', 'levels.approvers'])
            ->get()
            ->map(function ($group) {
                return [
                    'id' => $group->id,
                    'name' => $group->name,
                    'employee_ids' => $group->employees->pluck('id')->values()->toArray(),
                    'levels' => $group->levels->map(function ($level) {
                        return [
                            'id' => $level->id,
                            'level' => $level->level,
                            'name' => $level->name,
                            'approvers' => $level->approvers->map(function ($approver) {
                                return [
                                    'id' => $approver->id,
                                    'name' => $approver->name,
                                    'profile_photo_url' => $approver->profile_photo_url,
                                ];
                            })->values()->toArray(),
                        ];
                    })->values()->toArray(),
                ];
            })->values()->toArray();

        $formattedUsers = $usersCollection->groupBy('id')->map(function ($userGroup) use ($payroll, $allAttendances, $allComments, $holidays, $extraHourCosts, $approvalGroups, $allDecisions) {
            $user = $userGroup->first();
            
            // Pasamos collect([]) si está nulo para evitar llamadas extras a BD
            $userAttendances = $allAttendances->get($user->id) ?? collect([]);
            
            // MARCA: Determinar si el usuario tiene al menos un registro real en la BD para esta catorcena
            $hasAttendances = $userAttendances->isNotEmpty();
            
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

            // Inyectar comentarios, costos y datos de aprobación dentro de las incidencias
            foreach ($incidences as $incidence) {
                $dateKey = $incidence->date->toDateString();
                if ($commentsByDate->has($dateKey)) {
                    $incidence->comment = $commentsByDate->get($dateKey);
                }

                // Calcular costo de hora extra para este día si tiene tiempo extra
                if ($incidence->extra_hours || $incidence->extra_minutes) {
                    $dayOfWeek = $incidence->date->dayOfWeek; // 0=Dom, 6=Sáb
                    
                    // 1. Buscar costo específico para ESTE usuario (tiene prioridad)
                    $cost = $extraHourCosts->first(function ($c) use ($dayOfWeek, $user) {
                        return $c->user_id === $user->id
                            && $c->range_type === 'specific'
                            && $c->day_of_week === $dayOfWeek;
                    });
                    
                    // 2. Buscar costo por rango para ESTE usuario
                    if (!$cost) {
                        $isWeekend = ($dayOfWeek === 0 || $dayOfWeek === 6);
                        $rangeType = $isWeekend ? 'weekend' : 'weekday';
                        $cost = $extraHourCosts->first(function ($c) use ($rangeType, $user) {
                            return $c->user_id === $user->id
                                && $c->range_type === $rangeType;
                        });
                    }
                    
                    // 3. Fallback a costo general específico (user_id = null)
                    if (!$cost) {
                        $cost = $extraHourCosts->first(function ($c) use ($dayOfWeek) {
                            return $c->user_id === null
                                && $c->range_type === 'specific'
                                && $c->day_of_week === $dayOfWeek;
                        });
                    }
                    
                    // 4. Fallback a costo general por rango
                    if (!$cost) {
                        $isWeekend = ($dayOfWeek === 0 || $dayOfWeek === 6);
                        $rangeType = $isWeekend ? 'weekend' : 'weekday';
                        $cost = $extraHourCosts->first(function ($c) use ($rangeType) {
                            return $c->user_id === null
                                && $c->range_type === $rangeType;
                        });
                    }
                    
                    // Adjuntar costo por hora al objeto incidencia
                    $incidence->cost_per_hour = $cost ? (float) $cost->cost_per_hour : 0;
                    
                    // Calcular monto total de tiempo extra para este día
                    $totalHours = ($incidence->extra_hours ?? 0) + (($incidence->extra_minutes ?? 0) / 60);
                    $incidence->extra_amount = $cost ? round($totalHours * $cost->cost_per_hour, 2) : 0;
                }

                // Adjuntar decisiones de aprobación (consulta directa, más fiable)
                $incidenceDecisions = $allDecisions->get($incidence->id) ?? collect([]);
                $incidence->approval_decisions = $incidenceDecisions->map(function ($dec) {
                    return [
                        'id' => $dec->id,
                        'level_id' => $dec->approval_level_id,
                        'level_name' => $dec->approvalLevel->name ?? null,
                        'approver' => [
                            'id' => $dec->approver->id,
                            'name' => $dec->approver->name,
                            'profile_photo_url' => $dec->approver->profile_photo_url,
                        ],
                        'status' => $dec->status,
                        'comments' => $dec->comments,
                        'decided_at' => $dec->decided_at,
                    ];
                })->values();

                // Columnas desnormalizadas del flujo (vienen directo de payroll_user)
                $incidence->extra_hour_status = $incidence->extra_hour_status ?? 'none';
                $incidence->current_approval_level_id = $incidence->current_approval_level_id ?? null;
            }

            return [
                'user' => [
                    'id' => $user->id,
                    'code' => $user->code,
                    'name' => $user->name,
                    'org_props' => $user->org_props,
                    'paused' => $user->paused,
                    'profile_photo_url' => $user->profile_photo_url,
                    'has_attendances' => $hasAttendances, // Pasamos la nueva marca a la vista
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
            'noAttendances' => [],
            'extraHourCosts' => $extraHourCosts,
            'approvalGroups' => $approvalGroups,
            'projects' => $projects,
        ];
    }
}