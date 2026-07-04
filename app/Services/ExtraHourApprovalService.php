<?php

namespace App\Services;

use App\Models\ExtraHourApprovalDecision;
use App\Models\ExtraHourApprovalGroup;
use App\Models\ExtraHourApprovalLevel;
use App\Models\PayrollUser;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ExtraHourApprovalService
{
    /**
     * Inicializa el flujo de aprobación cuando se detectan horas extra.
     * Se llama desde PayrollUser::calculateExtraTime() y desde importación BioTime.
     */
    public function initializeWorkflow(PayrollUser $payrollUser): void
    {
        if (!$payrollUser->extra_hours && !$payrollUser->extra_minutes) {
            $payrollUser->updateQuietly([
                'extra_hour_status' => 'none',
                'current_approval_level_id' => null,
            ]);
            return;
        }

        // Si ya tiene decisión final, no reiniciar
        if (in_array($payrollUser->extra_hour_status, ['approved', 'rejected'])) {
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

            // Validar que el flujo esté activo para este registro
            if (!in_array($payrollUser->extra_hour_status, ['pending', 'none'])) {
                throw new \RuntimeException('Este tiempo extra ya fue resuelto.');
            }

            $currentLevelId = $payrollUser->current_approval_level_id;
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

            // Registrar decisión
            ExtraHourApprovalDecision::updateOrCreate(
                [
                    'payroll_user_id' => $payrollUser->id,
                    'approval_level_id' => $currentLevelId,
                    'approver_id' => $approver->id,
                ],
                [
                    'status' => $status,
                    'comments' => $data['comments'] ?? null,
                    'decided_at' => now(),
                ]
            );

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

            if (!$decision) {
                throw new \RuntimeException('No hay decisión que revertir.');
            }

            $decision->delete();

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
        if ($status === 'rejected') {
            // Rechazo → cierre global
            $payrollUser->update([
                'extra_hour_status' => 'rejected',
                'current_approval_level_id' => null,
                'approved_extra_hours' => 0,
                'approved_extra_minutes' => 0,
                'approved_by' => $approver->id,
                'approved_at' => now(),
            ]);
            return;
        }

        // Aprobación
        if (!$currentLevel) {
            // Modo directo: aprobación final
            $payrollUser->update([
                'extra_hour_status' => 'approved',
                'current_approval_level_id' => null,
                'approved_extra_hours' => $data['approved_extra_hours'] ?? $payrollUser->extra_hours,
                'approved_extra_minutes' => $data['approved_extra_minutes'] ?? $payrollUser->extra_minutes,
                'approved_by' => $approver->id,
                'approved_at' => now(),
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
            // Avanzar al siguiente nivel
            $payrollUser->update([
                'extra_hour_status' => 'pending',
                'current_approval_level_id' => $nextLevel->id,
            ]);
        } else {
            // Último nivel → cierre final
            $payrollUser->update([
                'extra_hour_status' => 'approved',
                'current_approval_level_id' => null,
                'approved_extra_hours' => $data['approved_extra_hours'] ?? $payrollUser->extra_hours,
                'approved_extra_minutes' => $data['approved_extra_minutes'] ?? $payrollUser->extra_minutes,
                'approved_by' => $approver->id,
                'approved_at' => now(),
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
            ]);
            return;
        }

        $levels = $group->levels()->orderBy('level')->get();
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

        $payrollUser->update([
            'extra_hour_status' => $finalStatus,
            'current_approval_level_id' => $finalStatus === 'approved' ? null : $currentLevelId,
        ]);
    }
}
