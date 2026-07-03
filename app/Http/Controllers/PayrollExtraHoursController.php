<?php

namespace App\Http\Controllers;

use App\Models\ExtraHourApprovalDecision;
use App\Models\ExtraHourApprovalGroup;
use App\Models\ExtraHourApprovalLevel;
use App\Models\ExtraHourCost;
use App\Models\Payroll;
use App\Models\PayrollUser;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class PayrollExtraHoursController extends Controller
{
    /**
     * Muestra la vista de configuración de costos y niveles de autorización
     * para una nómina específica.
     */
    public function config(Payroll $payroll)
    {
        // Cargar costos configurados (generales + por usuario)
        $costs = $payroll->extraHourCosts()
            ->with('user')
            ->get()
            ->map(function ($cost) {
                return [
                    'id' => $cost->id,
                    'payroll_id' => $cost->payroll_id,
                    'user_id' => $cost->user_id,
                    'user_name' => $cost->user?->name,
                    'day_of_week' => $cost->day_of_week,
                    'range_type' => $cost->range_type,
                    'cost_per_hour' => (float) $cost->cost_per_hour,
                ];
            })
            ->values();

        // Cargar grupos de aprobación con sus niveles y aprobadores
        $approvalGroups = $payroll->approvalGroups()
            ->with(['employees', 'levels.approvers'])
            ->get()
            ->map(function ($group) {
                return [
                    'id' => $group->id,
                    'name' => $group->name,
                    'employee_ids' => $group->employees->pluck('id'),
                    'employees' => $group->employees->map(function ($emp) {
                        return [
                            'id' => $emp->id,
                            'name' => $emp->name,
                            'code' => $emp->code,
                            'profile_photo_url' => $emp->profile_photo_url,
                            'department' => $emp->org_props['department'] ?? null,
                        ];
                    })->values(),
                    'levels' => $group->levels->map(function ($level) {
                        return [
                            'id' => $level->id,
                            'level' => $level->level,
                            'name' => $level->name,
                            'approver_ids' => $level->approvers->pluck('id'),
                            'approvers' => $level->approvers->map(function ($a) {
                                return [
                                    'id' => $a->id,
                                    'name' => $a->name,
                                    'profile_photo_url' => $a->profile_photo_url,
                                ];
                            })->values(),
                        ];
                    })->values(),
                ];
            })->values();

        // Usuarios elegibles como aprobadores (todos los activos, incluyendo directivos)
        $eligibleApprovers = User::where('is_active', true)
            ->select('id', 'name', 'code', 'org_props', 'profile_photo_path')
            ->get()
            ->map(function ($user) {
                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'code' => $user->code,
                    'profile_photo_url' => $user->profile_photo_url,
                    'department' => $user->org_props['department'] ?? null,
                ];
            });

        // Usuarios elegibles como empleados de un grupo
        $eligibleEmployees = User::where('is_active', true)
            ->whereNotIn('org_props->position', ['Dirección', 'Soporte DTW'])
            ->select('id', 'name', 'code', 'org_props', 'profile_photo_path')
            ->get()
            ->map(function ($user) {
                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'code' => $user->code,
                    'profile_photo_url' => $user->profile_photo_url,
                    'department' => $user->org_props['department'] ?? null,
                ];
            });

        // Usuarios que tienen tiempo extra en esta nómina (para asignarles niveles)
        $usersWithExtraTime = PayrollUser::where('payroll_id', $payroll->id)
            ->where(function ($q) {
                $q->where('extra_hours', '>', 0)
                  ->orWhere('extra_minutes', '>', 0);
            })
            ->with('user')
            ->get()
            ->groupBy('user_id')
            ->map(function ($entries) {
                $user = $entries->first()->user;
                $totalMinutes = $entries->sum(function ($e) {
                    return ($e->extra_hours ?? 0) * 60 + ($e->extra_minutes ?? 0);
                });
                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'code' => $user->code,
                    'profile_photo_url' => $user->profile_photo_url,
                    'department' => $user->org_props['department'] ?? null,
                    'total_extra_minutes' => $totalMinutes,
                    'total_extra_formatted' => intdiv($totalMinutes, 60) . 'h ' . ($totalMinutes % 60) . 'm',
                ];
            })
            ->values();

        // Verificar si hay una nómina anterior para copiar
        $hasPreviousPayroll = Payroll::where('id', '<', $payroll->id)->exists();

        // Verificar si hay una nómina siguiente para copiar
        $hasNextPayroll = Payroll::where('id', '>', $payroll->id)->exists();

        return Inertia::render('Payroll/ExtraHoursConfig', [
            'payroll' => [
                'id' => $payroll->id,
                'start_date' => $payroll->start_date,
                'biweekly' => $payroll->biweekly,
            ],
            'costs' => $costs,
            'approvalGroups' => $approvalGroups,
            'eligibleApprovers' => $eligibleApprovers,
            'eligibleEmployees' => $eligibleEmployees,
            'usersWithExtraTime' => $usersWithExtraTime,
            'hasPreviousPayroll' => $hasPreviousPayroll,
            'hasNextPayroll' => $hasNextPayroll,
        ]);
    }

    /**
     * Guarda o actualiza los costos de hora extra para la nómina.
     */
    public function saveCosts(Request $request, Payroll $payroll)
    {
        $request->validate([
            'costs' => 'required|array',
            'costs.*.user_id' => 'nullable|integer|exists:users,id',
            'costs.*.range_type' => 'required|in:weekday,weekend,specific',
            'costs.*.day_of_week' => 'nullable|integer|min:0|max:6',
            'costs.*.cost_per_hour' => 'required|numeric|min:0',
        ]);

        DB::transaction(function () use ($request, $payroll) {
            // Eliminar costos existentes de esta nómina
            $payroll->extraHourCosts()->delete();

            // Insertar nuevos costos (generales y por usuario)
            foreach ($request->costs as $cost) {
                ExtraHourCost::create([
                    'payroll_id' => $payroll->id,
                    'user_id' => $cost['user_id'] ?? null,
                    'range_type' => $cost['range_type'],
                    'day_of_week' => $cost['day_of_week'] ?? null,
                    'cost_per_hour' => $cost['cost_per_hour'],
                ]);
            }
        });

        return back()->with('success', 'Costos de hora extra actualizados correctamente.');
    }

    /**
     * Guarda los grupos de aprobación con sus niveles para la nómina.
     */
    public function saveApprovalGroups(Request $request, Payroll $payroll)
    {
        $request->validate([
            'groups' => 'required|array',
            'groups.*.name' => 'nullable|string|max:100',
            'groups.*.employee_ids' => 'required|array|min:1',
            'groups.*.employee_ids.*' => 'required|integer|exists:users,id',
            'groups.*.levels' => 'required|array|min:1',
            'groups.*.levels.*.name' => 'nullable|string|max:100',
            'groups.*.levels.*.approver_ids' => 'required|array|min:1',
            'groups.*.levels.*.approver_ids.*' => 'required|integer|exists:users,id',
        ]);

        DB::transaction(function () use ($request, $payroll) {
            // Eliminar grupos existentes (cascade elimina niveles, pivotes y decisiones)
            $payroll->approvalGroups()->each(function ($group) {
                $group->delete();
            });

            // Crear nuevos grupos
            foreach ($request->groups as $groupData) {
                $group = ExtraHourApprovalGroup::create([
                    'payroll_id' => $payroll->id,
                    'name' => $groupData['name'] ?? ('Grupo ' . (count($payroll->approvalGroups) + 1)),
                ]);

                // Asignar empleados al grupo
                $group->employees()->sync($groupData['employee_ids']);

                // Crear niveles para este grupo
                foreach ($groupData['levels'] as $index => $levelData) {
                    $level = ExtraHourApprovalLevel::create([
                        'payroll_id' => $payroll->id,
                        'approval_group_id' => $group->id,
                        'level' => $index + 1,
                        'name' => $levelData['name'] ?? ('Nivel ' . ($index + 1)),
                    ]);

                    $level->approvers()->sync($levelData['approver_ids']);
                }
            }
        });

        return back()->with('success', 'Grupos y niveles de autorización guardados correctamente.');
    }

    /**
     * Copia la configuración (costos + grupos) de la nómina anterior a la actual.
     */
    public function copyFromPrevious(Payroll $payroll)
    {
        $previous = Payroll::where('id', '<', $payroll->id)
            ->orderBy('id', 'desc')
            ->first();

        if (!$previous) {
            return back()->withErrors(['error' => 'No hay una nómina anterior para copiar.']);
        }

        DB::transaction(function () use ($payroll, $previous) {
            // 1. Copiar costos
            $payroll->extraHourCosts()->delete();
            foreach ($previous->extraHourCosts as $cost) {
                ExtraHourCost::create([
                    'payroll_id' => $payroll->id,
                    'user_id' => $cost->user_id,
                    'range_type' => $cost->range_type,
                    'day_of_week' => $cost->day_of_week,
                    'cost_per_hour' => $cost->cost_per_hour,
                ]);
            }

            // 2. Copiar grupos de aprobación con sus niveles y aprobadores
            $payroll->approvalGroups()->each(function ($g) { $g->delete(); });

            foreach ($previous->approvalGroups()->with(['employees', 'levels.approvers'])->get() as $prevGroup) {
                $newGroup = ExtraHourApprovalGroup::create([
                    'payroll_id' => $payroll->id,
                    'name' => $prevGroup->name,
                ]);

                // Copiar empleados
                $newGroup->employees()->sync($prevGroup->employees->pluck('id'));

                // Copiar niveles con aprobadores
                foreach ($prevGroup->levels as $prevLevel) {
                    $newLevel = ExtraHourApprovalLevel::create([
                        'payroll_id' => $payroll->id,
                        'approval_group_id' => $newGroup->id,
                        'level' => $prevLevel->level,
                        'name' => $prevLevel->name,
                    ]);

                    $newLevel->approvers()->sync($prevLevel->approvers->pluck('id'));
                }
            }
        });

        return back()->with('success', 'Configuración copiada de la nómina anterior correctamente.');
    }

    /**
     * Copia la configuración (costos + grupos) de la nómina siguiente a la actual.
     */
    public function copyFromNext(Payroll $payroll)
    {
        $next = Payroll::where('id', '>', $payroll->id)
            ->orderBy('id', 'asc')
            ->first();

        if (!$next) {
            return back()->withErrors(['error' => 'No hay una nómina siguiente para copiar.']);
        }

        DB::transaction(function () use ($payroll, $next) {
            // 1. Copiar costos
            $payroll->extraHourCosts()->delete();
            foreach ($next->extraHourCosts as $cost) {
                ExtraHourCost::create([
                    'payroll_id' => $payroll->id,
                    'user_id' => $cost->user_id,
                    'range_type' => $cost->range_type,
                    'day_of_week' => $cost->day_of_week,
                    'cost_per_hour' => $cost->cost_per_hour,
                ]);
            }

            // 2. Copiar grupos de aprobación con sus niveles y aprobadores
            $payroll->approvalGroups()->each(function ($g) { $g->delete(); });

            foreach ($next->approvalGroups()->with(['employees', 'levels.approvers'])->get() as $nextGroup) {
                $newGroup = ExtraHourApprovalGroup::create([
                    'payroll_id' => $payroll->id,
                    'name' => $nextGroup->name,
                ]);

                // Copiar empleados
                $newGroup->employees()->sync($nextGroup->employees->pluck('id'));

                // Copiar niveles con aprobadores
                foreach ($nextGroup->levels as $nextLevel) {
                    $newLevel = ExtraHourApprovalLevel::create([
                        'payroll_id' => $payroll->id,
                        'approval_group_id' => $newGroup->id,
                        'level' => $nextLevel->level,
                        'name' => $nextLevel->name,
                    ]);

                    $newLevel->approvers()->sync($nextLevel->approvers->pluck('id'));
                }
            }
        });

        return back()->with('success', 'Configuración copiada de la nómina siguiente correctamente.');
    }

    /**
     * Procesa la decisión de un aprobador sobre una entrada de tiempo extra.
     * Se llama desde el modal de gestión de tiempo extra.
     */
    public function decide(Request $request)
    {
        $request->validate([
            'payroll_user_id' => 'required|integer|exists:payroll_user,id',
            'status' => 'required|in:approved,rejected',
            'approved_extra_hours' => 'nullable|integer|min:0',
            'approved_extra_minutes' => 'nullable|integer|min:0|max:59',
            'comments' => 'nullable|string|max:500',
        ]);

        $payrollUser = PayrollUser::findOrFail($request->payroll_user_id);
        $payroll = $payrollUser->payroll;
        $currentUser = auth()->user();

        // Buscar el grupo al que pertenece este empleado
        $group = $this->findGroupForUser($payroll, $payrollUser->user_id);

        if (!$group) {
            return $this->handleDirectApproval($payrollUser, $currentUser, $request);
        }

        $levels = $group->levels()->with('approvers')->orderBy('level')->get();

        if ($levels->isEmpty()) {
            return $this->handleDirectApproval($payrollUser, $currentUser, $request);
        }

        // Encontrar el nivel donde el usuario actual es aprobador
        $currentLevel = null;
        foreach ($levels as $level) {
            if ($level->approvers->contains('id', $currentUser->id)) {
                $currentLevel = $level;
                break;
            }
        }

        if (!$currentLevel) {
            return back()->withErrors(['error' => 'No tienes permisos para aprobar/rechazar en esta nómina.']);
        }

        // Verificar que los niveles anteriores estén todos aprobados
        for ($i = 0; $i < $currentLevel->level - 1; $i++) {
            $prevLevel = $levels->where('level', $i + 1)->first();
            if ($prevLevel) {
                $allApproved = $this->checkLevelFullyApproved($payrollUser->id, $prevLevel);
                if (!$allApproved) {
                    return back()->withErrors(['error' => 'El nivel anterior (' . $prevLevel->name . ') aún no ha sido completamente aprobado.']);
                }
            }
        }

        // Registrar la decisión del aprobador actual
        ExtraHourApprovalDecision::updateOrCreate(
            [
                'payroll_user_id' => $payrollUser->id,
                'approval_level_id' => $currentLevel->id,
                'approver_id' => $currentUser->id,
            ],
            [
                'status' => $request->status,
                'comments' => $request->comments,
                'decided_at' => now(),
            ]
        );

        // Verificar si este nivel ya está completamente decidido y proceder
        $this->processLevelCompletion($payrollUser, $currentLevel, $levels, $request);

        // Detectar si la petición viene de Inertia o de Axios
        if ($request->header('X-Inertia')) {
            return back()->with('success', 'Decisión registrada correctamente.');
        }

        return response()->json(['success' => true, 'message' => 'Decisión registrada correctamente.']);
    }

    /**
     * Revierte una decisión de aprobación (para correcciones).
     */
    public function revertDecision(Request $request)
    {
        $request->validate([
            'payroll_user_id' => 'required|integer|exists:payroll_user,id',
            'approval_level_id' => 'required|integer|exists:extra_hour_approval_levels,id',
        ]);

        $decision = ExtraHourApprovalDecision::where([
            'payroll_user_id' => $request->payroll_user_id,
            'approval_level_id' => $request->approval_level_id,
            'approver_id' => auth()->id(),
        ])->first();

        if ($decision) {
            $decision->delete();
        }

        // Revertir también la aprobación final en payroll_user si existía
        $payrollUser = PayrollUser::find($request->payroll_user_id);
        if ($payrollUser && $payrollUser->approved_at) {
            $payrollUser->update([
                'approved_extra_hours' => null,
                'approved_extra_minutes' => null,
                'approved_by' => null,
                'approved_at' => null,
            ]);
        }

        return back()->with('success', 'Decisión revocada correctamente.');
    }

    // ─── Métodos privados ────────────────────────────────────────────

    /**
     * Encuentra el grupo de aprobación al que pertenece un usuario.
     */
    private function findGroupForUser(Payroll $payroll, int $userId): ?ExtraHourApprovalGroup
    {
        return $payroll->approvalGroups()
            ->whereHas('employees', function ($q) use ($userId) {
                $q->where('user_id', $userId);
            })
            ->first();
    }

    private function checkLevelFullyApproved(int $payrollUserId, ExtraHourApprovalLevel $level): bool
    {
        $approverIds = $level->approvers->pluck('id');
        $approvedCount = ExtraHourApprovalDecision::where([
            'payroll_user_id' => $payrollUserId,
            'approval_level_id' => $level->id,
            'status' => 'approved',
        ])->whereIn('approver_id', $approverIds)->count();

        return $approvedCount >= $approverIds->count();
    }

    /**
     * Verifica si algún aprobador del nivel rechazó.
     */
    private function checkLevelHasRejection(int $payrollUserId, ExtraHourApprovalLevel $level): bool
    {
        return ExtraHourApprovalDecision::where([
            'payroll_user_id' => $payrollUserId,
            'approval_level_id' => $level->id,
            'status' => 'rejected',
        ])->exists();
    }

    /**
     * Procesa la finalización de un nivel: si todos aprobaron, pasa al siguiente.
     * Si hay rechazo, se detiene el flujo.
     */
    private function processLevelCompletion(PayrollUser $payrollUser, ExtraHourApprovalLevel $currentLevel, $allLevels, Request $request): void
    {
        if ($this->checkLevelHasRejection($payrollUser->id, $currentLevel)) {
            // Rechazo en este nivel: marcar como rechazado globalmente
            $payrollUser->update([
                'approved_extra_hours' => 0,
                'approved_extra_minutes' => 0,
                'approved_by' => auth()->id(),
                'approved_at' => now(),
            ]);
            return;
        }

        if (!$this->checkLevelFullyApproved($payrollUser->id, $currentLevel)) {
            // Aún faltan aprobadores en este nivel
            return;
        }

        // Este nivel está completamente aprobado.
        // Siempre marcar como resuelto (pasa a Historial) aunque queden más niveles.
        $isLastLevel = $currentLevel->level >= $allLevels->max('level');

        if ($isLastLevel) {
            // Último nivel: guardar horas finales y marcar como resuelto
            $approvedHours = $request->has('approved_extra_hours') 
                ? $request->integer('approved_extra_hours') 
                : $payrollUser->extra_hours;
            $approvedMinutes = $request->has('approved_extra_minutes') 
                ? $request->integer('approved_extra_minutes') 
                : $payrollUser->extra_minutes;

            $payrollUser->update([
                'approved_extra_hours' => $approvedHours,
                'approved_extra_minutes' => $approvedMinutes,
                'approved_by' => auth()->id(),
                'approved_at' => now(),
            ]);
        }
        // Niveles intermedios: solo se registró la decisión, NO se marca approved_at.
        // El siguiente nivel lo verá como pendiente.
    }

    /**
     * Maneja la aprobación directa cuando no hay niveles configurados (retrocompatibilidad).
     */
    private function handleDirectApproval(PayrollUser $payrollUser, User $currentUser, Request $request)
    {
        if ($request->status === 'approved') {
            $payrollUser->update([
                'approved_extra_hours' => $request->approved_extra_hours ?? $payrollUser->extra_hours,
                'approved_extra_minutes' => $request->approved_extra_minutes ?? $payrollUser->extra_minutes,
                'approved_by' => $currentUser->id,
                'approved_at' => now(),
            ]);
        } else {
            $payrollUser->update([
                'approved_extra_hours' => 0,
                'approved_extra_minutes' => 0,
                'approved_by' => $currentUser->id,
                'approved_at' => now(),
            ]);
        }

        return back()->with('success', 'Decisión registrada correctamente.');
    }
}
