<?php

namespace App\Http\Controllers;

use App\Models\ExtraHourApprovalDecision;
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
        // Cargar costos configurados
        $costs = $payroll->extraHourCosts()->get();

        // Cargar niveles de autorización con sus aprobadores
        $approvalLevels = $payroll->approvalLevels()
            ->with('approvers')
            ->orderBy('level')
            ->get();

        // Usuarios elegibles como aprobadores (todos los activos con rol apropiado)
        $eligibleApprovers = User::where('is_active', true)
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

        return Inertia::render('Payroll/ExtraHoursConfig', [
            'payroll' => [
                'id' => $payroll->id,
                'start_date' => $payroll->start_date,
                'biweekly' => $payroll->biweekly,
            ],
            'costs' => $costs,
            'approvalLevels' => $approvalLevels,
            'eligibleApprovers' => $eligibleApprovers,
            'usersWithExtraTime' => $usersWithExtraTime,
        ]);
    }

    /**
     * Guarda o actualiza los costos de hora extra para la nómina.
     */
    public function saveCosts(Request $request, Payroll $payroll)
    {
        $request->validate([
            'costs' => 'required|array',
            'costs.*.range_type' => 'required|in:weekday,weekend,specific',
            'costs.*.day_of_week' => 'nullable|integer|min:0|max:6',
            'costs.*.cost_per_hour' => 'required|numeric|min:0',
        ]);

        DB::transaction(function () use ($request, $payroll) {
            // Eliminar costos existentes
            $payroll->extraHourCosts()->delete();

            // Insertar nuevos costos
            foreach ($request->costs as $cost) {
                ExtraHourCost::create([
                    'payroll_id' => $payroll->id,
                    'range_type' => $cost['range_type'],
                    'day_of_week' => $cost['day_of_week'] ?? null,
                    'cost_per_hour' => $cost['cost_per_hour'],
                ]);
            }
        });

        return back()->with('success', 'Costos de hora extra actualizados correctamente.');
    }

    /**
     * Guarda o actualiza los niveles de autorización para la nómina.
     */
    public function saveApprovalLevels(Request $request, Payroll $payroll)
    {
        $request->validate([
            'levels' => 'required|array',
            'levels.*.name' => 'nullable|string|max:100',
            'levels.*.approver_ids' => 'required|array|min:1',
            'levels.*.approver_ids.*' => 'required|integer|exists:users,id',
        ]);

        DB::transaction(function () use ($request, $payroll) {
            // Eliminar niveles existentes y sus relaciones
            $payroll->approvalLevels()->each(function ($level) {
                $level->approvers()->detach();
                $level->delete();
            });

            // Crear nuevos niveles
            foreach ($request->levels as $index => $levelData) {
                $level = ExtraHourApprovalLevel::create([
                    'payroll_id' => $payroll->id,
                    'level' => $index + 1,
                    'name' => $levelData['name'] ?? ('Nivel ' . ($index + 1)),
                ]);

                // Asignar aprobadores
                $level->approvers()->sync($levelData['approver_ids']);
            }
        });

        return back()->with('success', 'Niveles de autorización actualizados correctamente.');
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
            'comments' => 'nullable|string|max:500',
        ]);

        $payrollUser = PayrollUser::findOrFail($request->payroll_user_id);
        $payroll = $payrollUser->payroll;
        $currentUser = auth()->user();

        // Obtener los niveles de autorización configurados para esta nómina
        $levels = $payroll->approvalLevels()->with('approvers')->orderBy('level')->get();

        if ($levels->isEmpty()) {
            // Sin niveles configurados: comportamiento anterior (aprobación directa)
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
        $this->processLevelCompletion($payrollUser, $currentLevel, $levels);

        return back()->with('success', 'Decisión registrada correctamente.');
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

    // --- Métodos privados de ayuda ---

    /**
     * Verifica si un nivel de aprobación está completamente aprobado por todos sus miembros.
     */
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
    private function processLevelCompletion(PayrollUser $payrollUser, ExtraHourApprovalLevel $currentLevel, $allLevels): void
    {
        if ($this->checkLevelHasRejection($payrollUser->id, $currentLevel)) {
            // Rechazo en este nivel: marcar como rechazado globalmente (approved_at pero con 0 horas)
            $payrollUser->update([
                'approved_extra_hours' => 0,
                'approved_extra_minutes' => 0,
                'approved_by' => auth()->id(),
                'approved_at' => now(),
            ]);
            return;
        }

        if (!$this->checkLevelFullyApproved($payrollUser->id, $currentLevel)) {
            // Aún faltan aprobadores en este nivel, no hacer nada
            return;
        }

        // Este nivel está completamente aprobado. ¿Es el último nivel?
        $isLastLevel = $currentLevel->level >= $allLevels->max('level');

        if ($isLastLevel) {
            // Todos los niveles aprobados: aprobar el tiempo extra
            $payrollUser->update([
                'approved_extra_hours' => $payrollUser->extra_hours,
                'approved_extra_minutes' => $payrollUser->extra_minutes,
                'approved_by' => auth()->id(),
                'approved_at' => now(),
            ]);
        }
        // Si no es el último nivel, el siguiente nivel queda pendiente
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
