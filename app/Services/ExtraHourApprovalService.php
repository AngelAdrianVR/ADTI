<?php

namespace App\Services;

use App\Models\ExtraHourApprovalDecision;
use App\Models\ExtraHourApprovalGroup;
use App\Models\ExtraHourApprovalLevel;
use App\Models\Payroll;
use App\Models\PayrollUser;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ExtraHourApprovalService
{
    /**
     * Inicializa el flujo de aprobación cuando se detectan horas extra.
     * Se llama desde PayrollUser::calculateExtraTime() y desde importación BioTime.
     *
     * Sólo avanza hacia adelante:
     *  - Si el registro ya está en un nivel VÁLIDO del grupo del empleado, NO se
     *    reescribe (antes, cada recálculo de checadas "rebobinaba" al nivel 1 un
     *    registro que ya estaba en el nivel 2, dejando al aprobador de nivel 1 un
     *    día que ya había aprobado y sin poder decidirlo).
     *  - Si el registro quedó sin nivel y el empleado SÍ tiene grupo, se asigna el
     *    primer nivel (rescate de estados viejos).
     *  - Si el empleado no tiene grupo, queda como "sin flujo de autorización"
     *    (pending + nivel NULL): no cuenta en los indicadores ni es accionable.
     *
     * @param  bool  $force  Re-inicia el flujo desde el primer nivel del grupo
     *                       (usar sólo desde reparaciones explícitas).
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
            // Sin grupo → día SIN FLUJO DE AUTORIZACIÓN (no es "modo directo accionable")
            $payrollUser->updateQuietly([
                'extra_hour_status' => 'pending',
                'current_approval_level_id' => null,
            ]);
            return;
        }

        if (!$force) {
            $currentLevelId = $payrollUser->current_approval_level_id;
            $isInFlight = $currentLevelId
                && $payrollUser->extra_hour_status === 'pending'
                && $group->levels()->whereKey($currentLevelId)->exists();

            if ($isInFlight) {
                // El flujo ya está en curso y el nivel es válido para este grupo:
                // no tocar (idempotente).
                return;
            }
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

            if (!$payrollUser->extra_hours && !$payrollUser->extra_minutes) {
                throw new \RuntimeException('Este día no tiene tiempo extra registrado.');
            }

            $currentLevelId = $payrollUser->current_approval_level_id;

            // Sin nivel = día SIN FLUJO DE AUTORIZACIÓN (el colaborador no está en
            // ningún grupo de esta catorcena). No se decide desde aquí: primero hay
            // que asignar al colaborador a un grupo. Sólo se re-inicializa cuando el
            // grupo YA existe (rescate explícito de estados viejos).
            if (!$currentLevelId) {
                if (!$this->findGroupForUser($payrollUser)) {
                    throw new \RuntimeException('Este día no tiene flujo de autorización: el colaborador no está en ningún grupo de esta catorcena.');
                }

                $this->initializeWorkflow($payrollUser, true);
                $payrollUser->refresh();
                $currentLevelId = $payrollUser->current_approval_level_id;
            }

            $currentLevel = $currentLevelId ? ExtraHourApprovalLevel::find($currentLevelId) : null;

            // El actor SIEMPRE debe ser aprobador del nivel actual: antes, con la
            // columna de nivel en NULL ("modo directo"), cualquier usuario
            // autenticado podía aprobar o rechazar el registro.
            if (!$currentLevel || !$currentLevel->approvers()->where('user_id', $approver->id)->exists()) {
                throw new \RuntimeException('No eres aprobador del nivel actual.');
            }

            $group = $currentLevel->group;

            // El colaborador debe seguir perteneciendo al grupo del nivel actual
            if (!$group || !$group->employees()->where('user_id', $payrollUser->user_id)->exists()) {
                throw new \RuntimeException('El colaborador ya no pertenece al grupo de este nivel de autorización.');
            }

            // Verificar niveles anteriores (si estamos en nivel > 1)
            if ($currentLevel->level > 1) {
                $prevLevel = $group->levels()
                    ->where('level', '<', $currentLevel->level)
                    ->orderBy('level', 'desc')
                    ->first();
                if ($prevLevel && !$this->isLevelApproved($payrollUser->id, $prevLevel)) {
                    throw new \RuntimeException('El nivel anterior aún no ha sido aprobado.');
                }
            }

            // Valor efectivo del acuerdo hasta este punto: el ajuste que envía el
            // aprobador, o el que ya venía perseguido de niveles anteriores.
            $proposedHours = $data['approved_extra_hours'] ?? $payrollUser->proposed_extra_hours ?? $payrollUser->extra_hours;
            $proposedMinutes = $data['approved_extra_minutes'] ?? $payrollUser->proposed_extra_minutes ?? $payrollUser->extra_minutes;

            // Registrar la decisión (siempre hay un nivel: los días sin flujo se
            // rechazan más arriba, así que queda auditoría completa de cada decisión).
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
     *
     * Misma regla canónica que ExtraHourPendingQuery::pendingQuery(): hace falta
     * tiempo extra, nivel asignado, ser aprobador de ESE nivel, que el colaborador
     * pertenezca al grupo del nivel y que nadie haya decidido todavía en el nivel.
     */
    public function canAct(PayrollUser $payrollUser, User $user): bool
    {
        if (!in_array($payrollUser->extra_hour_status, ['pending', 'none'])) {
            return false;
        }

        if (!$payrollUser->extra_hours && !$payrollUser->extra_minutes) {
            return false;
        }

        $currentLevelId = $payrollUser->current_approval_level_id;
        if (!$currentLevelId) {
            // Día sin flujo de autorización: nadie puede decidirlo desde el modal
            return false;
        }

        $level = ExtraHourApprovalLevel::with('group')->find($currentLevelId);
        if (!$level || !$level->group) {
            return false;
        }

        $belongsToGroup = $level->group->employees()
            ->where('user_id', $payrollUser->user_id)
            ->exists();
        if (!$belongsToGroup) {
            return false;
        }

        $isApprover = $level->approvers()->where('user_id', $user->id)->exists();
        if (!$isApprover) {
            return false;
        }

        return !ExtraHourApprovalDecision::where('payroll_user_id', $payrollUser->id)
            ->where('approval_level_id', $currentLevelId)
            ->where('status', '!=', 'pending')
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
            ->orderBy('id')
            ->first();
    }

    /**
     * Mapa [user_id => grupo] de una catorcena (gana el grupo de menor id).
     * Evita N+1 al reconciliar cientos de registros.
     *
     * @return array<int, ExtraHourApprovalGroup>
     */
    private function groupsByEmployee(Payroll $payroll): array
    {
        $map = [];
        foreach ($payroll->approvalGroups()->with('employees')->orderBy('id')->get() as $group) {
            foreach ($group->employees as $employee) {
                $map[(int) $employee->id] ??= $group;
            }
        }

        return $map;
    }

    /**
     * Rescata los días de tiempo extra que quedaron SIN FLUJO DE AUTORIZACIÓN
     * (current_approval_level_id = NULL) pero cuyo colaborador SÍ pertenece a un
     * grupo de esa catorcena: les asigna el primer nivel del grupo.
     *
     * Los días de colaboradores sin grupo se dejan intactos (no hay a quién
     * asignarlos): se reportan como "sin jerarquía" y no cuentan en los indicadores.
     *
     * @return array{rescued:int,without_group:int,groups_without_levels:int,details:array<int,string>}
     */
    public function reconcileOrphans(Payroll $payroll, bool $dryRun = false): array
    {
        $rows = PayrollUser::where('payroll_id', $payroll->id)
            ->where('extra_hour_status', 'pending')
            ->whereNull('current_approval_level_id')
            ->where(function ($q) {
                $q->where('extra_hours', '>', 0)->orWhere('extra_minutes', '>', 0);
            })
            ->orderBy('user_id')
            ->get();

        $groupsByEmployee = $this->groupsByEmployee($payroll);
        $rescued = 0;
        $withoutGroup = 0;
        $withoutLevels = 0;
        $details = [];

        foreach ($rows as $row) {
            $group = $groupsByEmployee[(int) $row->user_id] ?? null;
            if (!$group) {
                $withoutGroup++;
                continue;
            }

            $firstLevel = $group->levels()->orderBy('level')->first();
            if (!$firstLevel) {
                $withoutLevels++;
                continue;
            }

            $details[] = sprintf(
                'payroll_user_id=%d user_id=%d fecha=%s → nivel %d (%s)',
                $row->id,
                $row->user_id,
                $row->date?->toDateString(),
                $firstLevel->level,
                $firstLevel->name ?? 'sin nombre'
            );

            if (!$dryRun) {
                $row->updateQuietly([
                    'extra_hour_status' => 'pending',
                    'current_approval_level_id' => $firstLevel->id,
                ]);
            }

            $rescued++;
        }

        return [
            'rescued' => $rescued,
            'without_group' => $withoutGroup,
            'groups_without_levels' => $withoutLevels,
            'details' => $details,
        ];
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
     *
     * Si el colaborador no tiene grupo NO hay jerarquía que invocar: sólo se
     * permite intervenir a un usuario con el permiso global 'Ver incidencias'
     * (administración). Antes devolvía `true` para cualquier usuario autenticado,
     * lo que permitía a cualquiera revertir aprobaciones.
     */
    private function isApproverForUser(PayrollUser $payrollUser, User $user): bool
    {
        $group = $this->findGroupForUser($payrollUser);
        if (!$group) {
            return $user->can('Ver incidencias');
        }

        return ExtraHourApprovalLevel::where('approval_group_id', $group->id)
            ->whereHas('approvers', fn ($q) => $q->where('user_id', $user->id))
            ->exists();
    }

    /**
     * Reinicia el flujo de los registros EN VUELO de una catorcena.
     *
     * Se usa cada vez que se reconfiguran los grupos (guardar o copiar): al
     * recrearse los niveles, las decisiones previas se borran por cascade y el
     * nivel actual queda en NULL, así que los registros deben volver al primer
     * nivel de su grupo nuevo. No toca aprobaciones finales legítimas
     * (estado final + approved_at) ni inventa decisiones.
     *
     * @return array{reset:int,skipped_final:int,without_group:int}
     */
    public function resetInFlightForPayroll(Payroll $payroll): array
    {
        $rows = PayrollUser::where('payroll_id', $payroll->id)
            ->where(function ($q) {
                $q->where('extra_hours', '>', 0)->orWhere('extra_minutes', '>', 0);
            })
            ->get();

        $groupsByEmployee = $this->groupsByEmployee($payroll);
        $reset = 0;
        $skippedFinal = 0;
        $withoutGroup = 0;

        foreach ($rows as $row) {
            if (in_array($row->extra_hour_status, ['approved', 'rejected']) && $row->approved_at !== null) {
                $skippedFinal++;
                continue;
            }

            if (!isset($groupsByEmployee[(int) $row->user_id])) {
                // Colaborador sin grupo: queda como día sin flujo de autorización
                $row->updateQuietly([
                    'extra_hour_status' => 'pending',
                    'current_approval_level_id' => null,
                ]);
                $withoutGroup++;
                continue;
            }

            $row->updateQuietly([
                'approved_extra_hours' => null,
                'approved_extra_minutes' => null,
                'approved_by' => null,
                'approved_at' => null,
                'proposed_extra_hours' => null,
                'proposed_extra_minutes' => null,
            ]);

            $this->initializeWorkflow($row, true);
            $reset++;
        }

        return ['reset' => $reset, 'skipped_final' => $skippedFinal, 'without_group' => $withoutGroup];
    }

}
