<?php

namespace App\Services;

use App\Models\ExtraHourApprovalDecision;
use App\Models\ExtraHourApprovalGroup;
use App\Models\ExtraHourApprovalLevel;
use App\Models\PayrollUser;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ExtraHourApprovalService
{
    /**
     * Inicializa el flujo de aprobación cuando se detectan horas extra.
     * Se llama desde PayrollUser::calculateExtraTime() y desde importación BioTime.
     */
    public function initializeWorkflow(PayrollUser $payrollUser, bool $force = false): void
    {
        if (!$payrollUser->extra_hours && !$payrollUser->extra_minutes) {
            $payrollUser->updateQuietly([
                'extra_hour_status' => 'none',
                'current_approval_level_id' => null,
            ]);
            return;
        }

        // Si ya tiene decisión final, no reiniciar (a menos que se fuerce
        // para reparar estados huérfanos tras reconfiguración de grupos)
        if (!$force && in_array($payrollUser->extra_hour_status, ['approved', 'rejected'])) {
            return;
        }

        $group = $this->findGroupForUser($payrollUser);
        if (!$group) {
            // Modo directo: sin grupo, cualquiera con permiso puede aprobar
            $payrollUser->updateQuietly([
                'extra_hour_status' => 'pending',
                'current_approval_level_id' => null,
            ]);
            return;
        }

        $firstLevel = $group->levels()->orderBy('level')->first();
        $payrollUser->updateQuietly([
            'extra_hour_status' => 'pending',
            'current_approval_level_id' => $firstLevel?->id,
        ]);
    }

    /**
     * Registra una decisión (aprobar/rechazar) con bloqueo de fila para evitar condición de carrera.
     */
    public function decide(PayrollUser $payrollUser, User $approver, string $status, array $data = []): void
    {
        DB::transaction(function () use ($payrollUser, $approver, $status, $data) {
            // Bloquear fila para evitar condición de carrera
            $payrollUser = PayrollUser::whereKey($payrollUser->id)->lockForUpdate()->firstOrFail();

            // Validar que el flujo esté activo para este registro.
            // Aceptamos null como equivalente a 'none' (registros creados antes de la migración de estados).
            $effectiveStatus = $payrollUser->extra_hour_status ?? 'none';
            if (!in_array($effectiveStatus, ['pending', 'none'])) {
                throw new \RuntimeException('Este tiempo extra ya fue resuelto.');
            }

            $currentLevelId = $payrollUser->current_approval_level_id;

            // Si está pendiente pero sin nivel asignado (modo directo), intentar
            // re-inicializar el flujo por si se configuraron grupos después.
            if (!$currentLevelId && $payrollUser->extra_hour_status === 'pending') {
                // Re-ejecutar initializeWorkflow para encontrar el grupo ahora
                $this->initializeWorkflow($payrollUser);
                // Refrescar desde BD para obtener el nuevo current_approval_level_id
                $payrollUser->refresh();
                $currentLevelId = $payrollUser->current_approval_level_id;
            }

            $currentLevel = $currentLevelId ? ExtraHourApprovalLevel::find($currentLevelId) : null;

            if ($currentLevel) {
                // Verificar que el aprobador pertenece al nivel actual
                if (!$currentLevel->approvers()->where('user_id', $approver->id)->exists()) {
                    throw new \RuntimeException('No eres aprobador del nivel actual.');
                }

                // Verificar niveles anteriores (si estamos en nivel > 1)
                $group = $currentLevel->group;
                if ($currentLevel->level > 1) {
                    $prevLevel = $group->levels()
                        ->where('level', '<', $currentLevel->level)
                        ->orderBy('level', 'desc')
                        ->first();
                    if ($prevLevel && !$this->isLevelApproved($payrollUser->id, $prevLevel)) {
                        throw new \RuntimeException('El nivel anterior aún no ha sido aprobado.');
                    }
                }
            }

            // Valor efectivo del acuerdo hasta este punto: el ajuste que envía el
            // aprobador, o el que ya venía perseguido de niveles anteriores.
            $proposedHours = $data['approved_extra_hours'] ?? $payrollUser->proposed_extra_hours ?? $payrollUser->extra_hours;
            $proposedMinutes = $data['approved_extra_minutes'] ?? $payrollUser->proposed_extra_minutes ?? $payrollUser->extra_minutes;

            // Registrar decisión solo si hay un nivel formal asignado.
            // En modo directo (current_approval_level_id = NULL), no se inserta
            // en extra_hour_approval_decisions porque la columna approval_level_id es NOT NULL.
            if ($currentLevelId) {
                ExtraHourApprovalDecision::updateOrCreate(
                    [
                        'payroll_user_id' => $payrollUser->id,
                        'approval_level_id' => $currentLevelId,
                        'approver_id' => $approver->id,
                    ],
                    [
                        'status' => $status,
                        'proposed_extra_hours' => $proposedHours,
                        'proposed_extra_minutes' => $proposedMinutes,
                        'comments' => $data['comments'] ?? null,
                        'decided_at' => now(),
                    ]
                );
            }

            // Avanzar o cerrar el flujo
            $this->advanceOrClose($payrollUser, $currentLevel, $approver, $status, $data);
        });
    }

    /**
     * Aprueba o rechaza múltiples registros en una sola operación.
     * Retorna array con ['ok' => [...ids], 'errors' => [id => mensaje]].
     */
    public function bulkDecide(array $payrollUserIds, User $approver, string $status, array $data = []): array
    {
        $ok = [];
        $errors = [];

        foreach ($payrollUserIds as $id) {
            try {
                $payrollUser = PayrollUser::findOrFail($id);
                $this->decide($payrollUser, $approver, $status, $data);
                $ok[] = $id;
            } catch (\Exception $e) {
                $errors[$id] = $e->getMessage();
            }
        }

        return ['ok' => $ok, 'errors' => $errors];
    }

    /**
     * Revierte la última decisión del aprobador y recalcula el estado.
     */
    public function revert(PayrollUser $payrollUser, User $actor): void
    {
        DB::transaction(function () use ($payrollUser, $actor) {
            $payrollUser = PayrollUser::whereKey($payrollUser->id)->lockForUpdate()->firstOrFail();

            // Encontrar la decisión del actor para este registro
            $decision = ExtraHourApprovalDecision::where('payroll_user_id', $payrollUser->id)
                ->where('approver_id', $actor->id)
                ->latest('decided_at')
                ->first();

            if ($decision) {
                $decision->delete();
            } else {
                // Sin decisión del actor: permitir revertir estados finales huérfanos
                // (legacy o dañados por reconfiguración de grupos) si el actor es
                // aprobador del grupo del empleado (o modo directo sin grupos).
                $status = $payrollUser->extra_hour_status ?? 'none';
                $isFinal = in_array($status, ['approved', 'rejected']) || $payrollUser->approved_at !== null;
                if (!$isFinal) {
                    throw new \RuntimeException('No hay decisión que revertir.');
                }

                if (!$this->isApproverForUser($payrollUser, $actor)) {
                    throw new \RuntimeException('No eres aprobador del grupo de este empleado.');
                }
            }

            // Recalcular el estado desde cero
            if ($payrollUser->extra_hours || $payrollUser->extra_minutes) {
                $this->recalculateState($payrollUser);
            } else {
                $payrollUser->update([
                    'extra_hour_status' => 'none',
                    'current_approval_level_id' => null,
                    'approved_extra_hours' => null,
                    'approved_extra_minutes' => null,
                    'approved_by' => null,
                    'approved_at' => null,
                    'proposed_extra_hours' => null,
                    'proposed_extra_minutes' => null,
                ]);
            }
        });
    }

    /**
     * Chequeo ligero: ¿el usuario puede actuar sobre este registro?
     */
    public function canAct(PayrollUser $payrollUser, User $user): bool
    {
        if (!in_array($payrollUser->extra_hour_status, ['pending', 'none'])) {
            return false;
        }

        $currentLevelId = $payrollUser->current_approval_level_id;
        if (!$currentLevelId) {
            // Modo directo: cualquiera con permiso puede
            return true;
        }

        return ExtraHourApprovalLevel::whereKey($currentLevelId)
            ->whereHas('approvers', fn ($q) => $q->where('user_id', $user->id))
            ->exists();
    }

    // ─── Private helpers ────────────────────────────────────────────

    private function advanceOrClose(PayrollUser $payrollUser, ?ExtraHourApprovalLevel $currentLevel, User $approver, string $status, array $data): void
    {
        // Valor efectivo del acuerdo hasta este punto: el ajuste enviado por el
        // aprobador actual, o el que ya venía perseguido de niveles anteriores.
        $proposedHours = $data['approved_extra_hours'] ?? $payrollUser->proposed_extra_hours ?? $payrollUser->extra_hours;
        $proposedMinutes = $data['approved_extra_minutes'] ?? $payrollUser->proposed_extra_minutes ?? $payrollUser->extra_minutes;

        if ($status === 'rejected') {
            // Rechazo → cierre global
            $payrollUser->update([
                'extra_hour_status' => 'rejected',
                'current_approval_level_id' => null,
                'approved_extra_hours' => 0,
                'approved_extra_minutes' => 0,
                'approved_by' => $approver->id,
                'approved_at' => now(),
                'proposed_extra_hours' => null,
                'proposed_extra_minutes' => null,
            ]);
            return;
        }

        // Aprobación
        if (!$currentLevel) {
            // Modo directo: aprobación final
            $payrollUser->update([
                'extra_hour_status' => 'approved',
                'current_approval_level_id' => null,
                'approved_extra_hours' => $proposedHours,
                'approved_extra_minutes' => $proposedMinutes,
                'approved_by' => $approver->id,
                'approved_at' => now(),
                'proposed_extra_hours' => null,
                'proposed_extra_minutes' => null,
            ]);
            return;
        }

        // Buscar siguiente nivel
        $group = $currentLevel->group;
        $nextLevel = $group->levels()
            ->where('level', '>', $currentLevel->level)
            ->orderBy('level')
            ->first();

        if ($nextLevel) {
            // Avanzar al siguiente nivel persistiendo el acuerdo ajustado
            // para que los niveles siguientes lo vean pre-cargado.
            $payrollUser->update([
                'extra_hour_status' => 'pending',
                'current_approval_level_id' => $nextLevel->id,
                'proposed_extra_hours' => $proposedHours,
                'proposed_extra_minutes' => $proposedMinutes,
            ]);
        } else {
            // Último nivel → cierre final (hereda el ajuste de niveles anteriores)
            $payrollUser->update([
                'extra_hour_status' => 'approved',
                'current_approval_level_id' => null,
                'approved_extra_hours' => $proposedHours,
                'approved_extra_minutes' => $proposedMinutes,
                'approved_by' => $approver->id,
                'approved_at' => now(),
                'proposed_extra_hours' => null,
                'proposed_extra_minutes' => null,
            ]);
        }
    }

    private function isLevelApproved(int $payrollUserId, ExtraHourApprovalLevel $level): bool
    {
        return ExtraHourApprovalDecision::where([
            'payroll_user_id' => $payrollUserId,
            'approval_level_id' => $level->id,
            'status' => 'approved',
        ])->exists();
    }

    private function findGroupForUser(PayrollUser $payrollUser): ?ExtraHourApprovalGroup
    {
        return $payrollUser->payroll->approvalGroups()
            ->whereHas('employees', fn ($q) => $q->where('user_id', $payrollUser->user_id))
            ->first();
    }

    private function recalculateState(PayrollUser $payrollUser): void
    {
        $group = $this->findGroupForUser($payrollUser);
        if (!$group) {
            $payrollUser->update([
                'extra_hour_status' => 'pending',
                'current_approval_level_id' => null,
                'approved_extra_hours' => null,
                'approved_extra_minutes' => null,
                'approved_by' => null,
                'approved_at' => null,
                'proposed_extra_hours' => null,
                'proposed_extra_minutes' => null,
            ]);
            return;
        }

        $levels = $group->levels()->orderBy('level')->get();
        $hasAnyDecision = ExtraHourApprovalDecision::where('payroll_user_id', $payrollUser->id)->exists();

        // Sin decisiones → reiniciar flujo desde el primer nivel.
        // Se usa $force=true para que el reinicio funcione también cuando el
        // estado desnormalizado quedó como 'approved'/'rejected' (tras revertir
        // la última decisión) y evitar registros huérfanos.
        if (!$hasAnyDecision) {
            $payrollUser->update([
                'approved_extra_hours' => null,
                'approved_extra_minutes' => null,
                'approved_by' => null,
                'approved_at' => null,
                'proposed_extra_hours' => null,
                'proposed_extra_minutes' => null,
            ]);
            $this->initializeWorkflow($payrollUser, true);
            return;
        }

        $finalStatus = 'approved';
        $currentLevelId = null;

        foreach ($levels as $level) {
            $hasRejection = ExtraHourApprovalDecision::where([
                'payroll_user_id' => $payrollUser->id,
                'approval_level_id' => $level->id,
                'status' => 'rejected',
            ])->exists();

            if ($hasRejection) {
                $payrollUser->update([
                    'extra_hour_status' => 'rejected',
                    'current_approval_level_id' => null,
                    'approved_extra_hours' => 0,
                    'approved_extra_minutes' => 0,
                    'approved_by' => null,
                    'approved_at' => null,
                    'proposed_extra_hours' => null,
                    'proposed_extra_minutes' => null,
                ]);
                return;
            }

            $isApproved = $this->isLevelApproved($payrollUser->id, $level);
            if (!$isApproved) {
                $currentLevelId = $level->id;
                $finalStatus = 'pending';
                break;
            }
        }

        $isFullyApproved = $finalStatus === 'approved';

        // Reconstruir el valor propuesto a partir de la última decisión aprobada
        // (para que el nivel que retoma el flujo tras un revert vea el acuerdo
        // de los niveles anteriores, no el valor original).
        $lastApprovedDecision = null;
        if (!$isFullyApproved) {
            $lastApprovedDecision = ExtraHourApprovalDecision::where('payroll_user_id', $payrollUser->id)
                ->where('status', 'approved')
                ->orderByDesc('decided_at')
                ->first();
        }

        $payrollUser->update([
            'extra_hour_status' => $finalStatus,
            'current_approval_level_id' => $isFullyApproved ? null : $currentLevelId,
            // Al no estar totalmente aprobado, limpiar los campos legacy para evitar
            // estados inconsistentes (p.ej. pending con approved_at viejo).
            'approved_extra_hours' => $isFullyApproved ? $payrollUser->approved_extra_hours : null,
            'approved_extra_minutes' => $isFullyApproved ? $payrollUser->approved_extra_minutes : null,
            'approved_by' => $isFullyApproved ? $payrollUser->approved_by : null,
            'approved_at' => $isFullyApproved ? $payrollUser->approved_at : null,
            // Propuesto: se limpia al aprobar todo; si queda pendiente, se hereda
            // el último acuerdo aprobado (o null si nadie ha aprobado aún).
            'proposed_extra_hours' => $isFullyApproved
                ? null
                : ($lastApprovedDecision?->proposed_extra_hours ?? $payrollUser->extra_hours),
            'proposed_extra_minutes' => $isFullyApproved
                ? null
                : ($lastApprovedDecision?->proposed_extra_minutes ?? $payrollUser->extra_minutes),
        ]);
    }

    /**
     * Determina si el actor es aprobador de algún nivel del grupo del empleado.
     * En modo directo (sin grupos configurados), cualquier aprobador puede.
     */
    private function isApproverForUser(PayrollUser $payrollUser, User $user): bool
    {
        $group = $this->findGroupForUser($payrollUser);
        if (!$group) {
            return true; // Modo directo
        }

        return ExtraHourApprovalLevel::where('approval_group_id', $group->id)
            ->whereHas('approvers', fn ($q) => $q->where('user_id', $user->id))
            ->exists();
    }
}
