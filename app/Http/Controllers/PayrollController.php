<?php

namespace App\Http\Controllers;

use App\Models\ExtraHourApprovalDecision;
use App\Models\Holiday;
use App\Models\Payroll;
use App\Models\PayrollComment;
use App\Models\PayrollUser;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PayrollController extends Controller
{
    public function index()
    {
        $payrolls = Payroll::latest()
            ->withCount(['extraHourCosts', 'approvalGroups'])
            ->get();

        // Usuarios elegibles para el selector de recibos por rango
        $users = $this->getEligibleUsersForSelector();

        return inertia('Payroll/Index', compact('payrolls', 'users'));
    }

    /**
     * Genera recibos con un rango de fechas personalizado.
     * Puede combinar días de 2 catorcenas diferentes.
     */
    public function receiptsByRange(Request $request)
    {
        $userIds = $request->input('user_ids');
        if (is_string($userIds)) {
            $userIds = explode(',', $userIds);
        }
        $request->merge(['user_ids' => $userIds]);

        $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'user_ids' => 'required|array|min:1',
            'user_ids.*' => 'integer',
        ]);

        $startDate = Carbon::parse($request->start_date)->startOfDay();
        $endDate = Carbon::parse($request->end_date)->endOfDay();

        // Límite razonable de días para un recibo (evita rangos absurdos)
        if ($startDate->diffInDays($endDate) + 1 > 31) {
            return back()->withErrors(['error' => 'El rango máximo permitido es de 31 días.']);
        }

        // 1. Determinar qué usuarios mostrar basado en permisos y jerarquía
        $currentUser = auth()->user();
        $query = User::whereNotIn('org_props->position', ['Dirección', 'Soporte DTW'])
            ->where('is_active', true);

        // Aplicamos la jerarquía y permisos
        if (!$currentUser->can('Ver incidencias')) {
            if (!empty($currentUser->employees_in_charge)) {
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

        $query->whereIn('id', $request->user_ids);
        $usersCollection = $query->get();
        $finalUserIds = $usersCollection->pluck('id');

        // 2. Payrolls que se solapan con el rango (una catorcena cubre [start_date, start_date + 13])
        $payrolls = Payroll::where('start_date', '<=', $endDate->toDateString())
            ->where('start_date', '>=', $startDate->copy()->subDays(13)->toDateString())
            ->orderBy('start_date')
            ->get();

        if ($payrolls->isEmpty()) {
            return back()->withErrors(['error' => 'No hay nóminas que cubran el rango de fechas seleccionado.']);
        }

        // Payroll principal = el que aporta más días al rango (para costos y grupos)
        $payroll = $payrolls->sortByDesc(function ($p) use ($startDate, $endDate) {
            $pStart = $p->start_date;
            $pEnd = $p->start_date->copy()->addDays(13);
            $overlapStart = $pStart->greaterThan($startDate) ? $pStart : $startDate;
            $overlapEnd = $pEnd->lessThan($endDate) ? $pEnd : $endDate;
            return $overlapStart->diffInDays($overlapEnd);
        })->first();

        // 3. Cargar asistencias SOLO para los usuarios filtrados y DENTRO del rango
        $allAttendances = PayrollUser::with(['approver', 'project'])
            ->whereIn('payroll_id', $payrolls->pluck('id'))
            ->whereIn('user_id', $finalUserIds)
            ->whereBetween('date', [$startDate->toDateString(), $endDate->toDateString()])
            ->get()
            ->groupBy('user_id');

        // 4. Cargar decisiones de aprobación por separado
        $payrollUserIds = $allAttendances->flatten(1)->pluck('id');
        $allDecisions = ExtraHourApprovalDecision::whereIn('payroll_user_id', $payrollUserIds)
            ->with(['approver', 'approvalLevel'])
            ->get()
            ->groupBy('payroll_user_id');

        // 5. Cargar comentarios dentro del rango
        $allComments = PayrollComment::whereIn('payroll_id', $payrolls->pluck('id'))
            ->whereIn('user_id', $finalUserIds)
            ->whereBetween('date', [$startDate->toDateString(), $endDate->toDateString()])
            ->get()
            ->groupBy('user_id');

        // 6. Festivos dentro del rango
        $holidays = Holiday::whereBetween('date', [$startDate->toDateString(), $endDate->toDateString()])->get();

        // 7. Cargar costos de hora extra del payroll principal
        $extraHourCosts = $payroll->extraHourCosts()->get();

        // 8. Cargar grupos de autorización del payroll principal
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

        // 9. Procesar cada usuario: construir los días del rango (día a día)
        $formattedUsers = $usersCollection->groupBy('id')->map(function ($userGroup) use ($startDate, $endDate, $allAttendances, $allComments, $holidays, $extraHourCosts, $approvalGroups, $allDecisions) {
            $user = $userGroup->first();

            $userAttendances = $allAttendances->get($user->id) ?? collect([]);
            $attendancesMap = $userAttendances->keyBy(function ($item) {
                return $item->date->toDateString();
            });

            // Comentarios del usuario
            $userComments = $allComments->get($user->id) ?? collect([]);
            $generalComment = $userComments->whereNull('date')->first();
            $commentsByDate = $userComments->whereNotNull('date')->keyBy(function ($item) {
                return $item->date->toDateString();
            });

            // Construir los días del rango
            $incidences = [];
            for ($date = $startDate->copy(); $date->lte($endDate); $date->addDay()) {
                $dateString = $date->toDateString();

                // Verificar festivo usando la colección optimizada
                $isHoliday = $holidays->contains(fn($h) => $h->date->isSameDay($date));
                $dayOfWeek = $date->dayOfWeek;

                $payrollUser = $attendancesMap->get($dateString);

                if ($payrollUser) {
                    // Si existe registro real, usarlo
                    $incidences[] = $payrollUser;
                } else {
                    // Crear objeto "dummy" para días sin registro (misma lógica que getProcessedAttendances)
                    $dummy = new PayrollUser();
                    $dummy->date = $date;
                    $dummy->user_id = $user->id;

                    if ($isHoliday) {
                        $dummy->incidence = "Día festivo";
                    } else {
                        if ($dayOfWeek == 0) { // Domingo
                            $dummy->incidence = "Domingo";
                        } else {
                            if ($date->lt(now()->startOfDay())) {
                                $dummy->incidence = 'Falta injustificada';
                            } else {
                                $dummy->incidence = 'Día normal';
                            }
                        }
                    }
                    $incidences[] = $dummy;
                }
            }

            // Inyectar comentarios, costos y datos de aprobación dentro de las incidencias
            foreach ($incidences as $incidence) {
                $dateKey = $incidence->date->toDateString();
                if ($commentsByDate->has($dateKey)) {
                    $incidence->comment = $commentsByDate->get($dateKey);
                }

                // Calcular costo de hora extra para este día si tiene tiempo extra
                if ($incidence->extra_hours || $incidence->extra_minutes) {
                    $costPerHour = $this->resolveCostPerHour($incidence->date, $user->id, $extraHourCosts);

                    $incidence->cost_per_hour = $costPerHour;

                    $totalHours = ($incidence->extra_hours ?? 0) + (($incidence->extra_minutes ?? 0) / 60);
                    $incidence->extra_amount = $costPerHour ? round($totalHours * $costPerHour, 2) : 0;
                }

                // Adjuntar decisiones de aprobación
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

                // Columnas desnormalizadas del flujo
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
                    'has_attendances' => $attendancesMap->isNotEmpty(),
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

        return inertia('Payroll/PayrollReceiptTemplate', [
            'payroll' => $payrollData,
            'payrollUsers' => $formattedUsers,
            'noAttendances' => [],
            'extraHourCosts' => $extraHourCosts,
            'approvalGroups' => $approvalGroups,
            'range' => [
                'start_date' => $startDate->toDateString(),
                'end_date' => $endDate->toDateString(),
            ],
        ]);
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

    private function getEligibleUsersForSelector()
    {
        $currentUser = auth()->user();

        $query = User::whereNotIn('org_props->position', ['Dirección', 'Soporte DTW'])
            ->where('is_active', true)
            ->orderBy('name');

        if (!$currentUser->can('Ver incidencias')) {
            if (!empty($currentUser->employees_in_charge)) {
                $employeesIds = $currentUser->employees_in_charge;
                if (!in_array($currentUser->id, $employeesIds)) {
                    $employeesIds[] = $currentUser->id;
                }
                $query->whereIn('id', $employeesIds);
            } else {
                $query->whereRaw('1 = 0');
            }
        }

        return $query->get(['id', 'name', 'code', 'org_props'])
            ->map(function ($user) {
                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'code' => $user->code,
                    'department' => $user->org_props['department'] ?? 'General',
                ];
            })
            ->values();
    }

    private function resolveCostPerHour($dateObj, $userId, $extraHourCosts)
    {
        if ($extraHourCosts->isEmpty()) return 0;

        $dayOfWeek = $dateObj->dayOfWeek; // 0=Dom, 6=Sáb
        $isWeekend = ($dayOfWeek === 0 || $dayOfWeek === 6);
        $rangeType = $isWeekend ? 'weekend' : 'weekday';

        // 1. Costo específico para ESTE usuario + día
        $cost = $extraHourCosts->first(fn($c) =>
            $c->user_id === $userId && $c->range_type === 'specific' && $c->day_of_week === $dayOfWeek
        );
        // 2. Costo por rango para ESTE usuario
        if (!$cost) {
            $cost = $extraHourCosts->first(fn($c) =>
                $c->user_id === $userId && $c->range_type === $rangeType
            );
        }
        // 3. Costo general específico del día
        if (!$cost) {
            $cost = $extraHourCosts->first(fn($c) =>
                $c->user_id === null && $c->range_type === 'specific' && $c->day_of_week === $dayOfWeek
            );
        }
        // 4. Costo general por rango
        if (!$cost) {
            $cost = $extraHourCosts->first(fn($c) =>
                $c->user_id === null && $c->range_type === $rangeType
            );
        }

        return $cost ? (float) $cost->cost_per_hour : 0;
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