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

    // ─── Reconfiguración de grupos (upsert no destructivo) ───────────────

    /**
     * Aplica la configuración de grupos/niveles recibida SIN destruir las
     * autorizaciones ya registradas.
     *
     * Regla de negocio:
     *  - Un grupo/nivel que sigue existiendo (mismo id; o mismo nombre/número de
     *    nivel cuando la petición no trae ids, p.ej. al copiar de otra catorcena)
     *    conserva sus decisiones intactas.
     *  - Los colaboradores que se REMUEVEN de un grupo pierden sus decisiones en
     *    vuelo de ese grupo (su jerarquía cambió). Las decisiones ya resueltas
     *    (días aprobados o rechazados) se conservan: reabrirlas alteraría la
     *    nómina.
     *  - Los niveles y grupos que desaparecen de la configuración se borran con
     *    sus decisiones (eliminación deliberada del administrador).
     *
     * Antes, guardar/copiar grupos borraba TODOS los niveles y decisiones de la
     * catorcena (cascade) y reiniciaba todos los días en vuelo al primer nivel.
     *
     * @param  array<int, array{id?:int|null,name?:string|null,employee_ids?:array,levels?:array}>  $groups
     * @return array{groups_created:int,groups_updated:int,groups_removed:int,levels_created:int,levels_removed:int,employees_removed:int,decisions_removed:int}
     */
    public function applyApprovalGroups(Payroll $payroll, array $groups): array
    {
        $stats = [
            'groups_created' => 0,
            'groups_updated' => 0,
            'groups_removed' => 0,
            'levels_created' => 0,
            'levels_removed' => 0,
            'employees_removed' => 0,
            'decisions_removed' => 0,
        ];

        $existing = $payroll->approvalGroups()->with(['levels', 'employees'])->orderBy('id')->get();
        $byId = $existing->keyBy('id');

        // 1. Emparejar cada grupo entrante con uno existente: primero por id y,
        //    si la petición no lo trae, por nombre.
        $claimed = [];   // group_id => true
        $matched = [];   // índice del payload => ExtraHourApprovalGroup

        foreach ($groups as $index => $groupData) {
            $id = isset($groupData['id']) ? (int) $groupData['id'] : 0;
            if ($id && $byId->has($id)) {
                $claimed[$id] = true;
                $matched[$index] = $byId->get($id);
            }
        }

        foreach ($groups as $index => $groupData) {
            if (isset($matched[$index])) {
                continue;
            }

            $name = trim((string) ($groupData['name'] ?? ''));
            if ($name === '') {
                continue;
            }

            $candidate = $existing->first(function (ExtraHourApprovalGroup $group) use ($name, $claimed) {
                return !array_key_exists((int) $group->id, $claimed)
                    && trim((string) $group->name) === $name;
            });

            if ($candidate) {
                $claimed[(int) $candidate->id] = true;
                $matched[$index] = $candidate;
            }
        }

        // 2. Eliminar los grupos que la configuración ya no incluye.
        foreach ($existing as $group) {
            if (array_key_exists((int) $group->id, $claimed)) {
                continue;
            }

            $levelIds = $group->levels->pluck('id')->all();
            if ($levelIds) {
                $stats['decisions_removed'] += (int) ExtraHourApprovalDecision::whereIn('approval_level_id', $levelIds)->delete();
            }

            $group->delete(); // cascade: niveles, pivote de empleados y decisiones
            $stats['groups_removed']++;
        }

        // 3. Crear o actualizar cada grupo (con sus empleados y niveles).
        foreach ($groups as $index => $groupData) {
            $group = $matched[$index] ?? null;

            if ($group) {
                $group->update([
                    'name' => array_key_exists('name', $groupData) ? $groupData['name'] : $group->name,
                ]);
                $stats['groups_updated']++;
            } else {
                $group = ExtraHourApprovalGroup::create([
                    'payroll_id' => $payroll->id,
                    'name' => $groupData['name'] ?? null,
                ]);
                $stats['groups_created']++;
            }

            $employees = $this->syncGroupEmployees($payroll, $group, $groupData['employee_ids'] ?? []);
            $stats['employees_removed'] += $employees['removed'];
            $stats['decisions_removed'] += $employees['decisions_removed'];

            $levels = $this->syncGroupLevels($payroll, $group, $groupData['levels'] ?? []);
            $stats['levels_created'] += $levels['created'];
            $stats['levels_removed'] += $levels['removed'];
            $stats['decisions_removed'] += $levels['decisions_removed'];
        }

        return $stats;
    }

    /**
     * Reubica los registros de tiempo extra tras reconfigurar los grupos, sin
     * reiniciar los que siguen siendo válidos.
     *
     *  - Días ya resueltos (aprobados o rechazados) → intactos.
     *  - Días en vuelo cuyo nivel actual sigue existiendo en su grupo → intactos
     *    (éste es el caso que antes se reiniciaba en cada guardado, incluso
     *    cuando el grupo del colaborador no había cambiado).
     *  - El resto se recalcula a partir de las decisiones que sobrevivieron
     *    (primer nivel sin aprobar) o queda "sin flujo" si el colaborador ya no
     *    pertenece a ningún grupo.
     *
     * @return array{kept:int,recomputed:int,without_group:int,skipped_final:int}
     */
    public function normalizeWorkflowForPayroll(Payroll $payroll): array
    {
        $rows = PayrollUser::where('payroll_id', $payroll->id)
            ->where(function ($q) {
                $q->where('extra_hours', '>', 0)->orWhere('extra_minutes', '>', 0);
            })
            ->orderBy('id')
            ->get();

        $groupsByEmployee = $this->groupsByEmployee($payroll);
        $levelsByGroup = [];

        $kept = 0;
        $recomputed = 0;
        $withoutGroup = 0;
        $skippedFinal = 0;

        foreach ($rows as $row) {
            // Días ya resueltos: una aprobación o un rechazo registrado no se
            // reabre nunca (reabrirlo cambiaría el monto de la nómina).
            if (in_array($row->extra_hour_status, ['approved', 'rejected'], true)) {
                $skippedFinal++;
                continue;
            }

            $group = $groupsByEmployee[(int) $row->user_id] ?? null;

            if (!$group) {
                // Colaborador fuera de todo grupo: día sin flujo de autorización.
                $row->updateQuietly([
                    'extra_hour_status' => 'pending',
                    'current_approval_level_id' => null,
                    'approved_extra_hours' => null,
                    'approved_extra_minutes' => null,
                    'approved_by' => null,
                    'approved_at' => null,
                    'proposed_extra_hours' => null,
                    'proposed_extra_minutes' => null,
                ]);
                $withoutGroup++;
                continue;
            }

            $levels = $levelsByGroup[$group->id] ??= $group->levels()->orderBy('level')->get();
            $currentLevelId = (int) $row->current_approval_level_id;

            $isLevelStillValid = $currentLevelId > 0
                && $row->extra_hour_status === 'pending'
                && $levels->contains(fn ($level) => (int) $level->id === $currentLevelId);

            if ($isLevelStillValid) {
                $kept++;
                continue;
            }

            // El nivel actual desapareció (o el día quedó huérfano): se reubica a
            // partir de las decisiones vigentes.
            $this->recalculateState($row);
            $this->closeIfAllLevelsApproved($row);
            $recomputed++;
        }

        return [
            'kept' => $kept,
            'recomputed' => $recomputed,
            'without_group' => $withoutGroup,
            'skipped_final' => $skippedFinal,
        ];
    }

    /**
     * Si tras recalcular las decisiones vigentes el día quedó aprobado pero sin
     * los campos de cierre (p.ej. al eliminar el último nivel pendiente), los
     * completa con la última decisión aprobada. Sin esto, el día quedaría
     * "approved" con `approved_at` NULL (registro huérfano).
     */
    private function closeIfAllLevelsApproved(PayrollUser $payrollUser): void
    {
        $fresh = $payrollUser->fresh();

        if (!$fresh || $fresh->extra_hour_status !== 'approved' || $fresh->approved_at !== null) {
            return;
        }

        $lastApproved = ExtraHourApprovalDecision::where('payroll_user_id', $fresh->id)
            ->where('status', 'approved')
            ->orderByDesc('decided_at')
            ->first();

        if (!$lastApproved) {
            return;
        }

        $fresh->update([
            'approved_extra_hours' => $fresh->approved_extra_hours ?? $lastApproved->proposed_extra_hours ?? $fresh->extra_hours,
            'approved_extra_minutes' => $fresh->approved_extra_minutes ?? $lastApproved->proposed_extra_minutes ?? $fresh->extra_minutes,
            'approved_by' => $fresh->approved_by ?? $lastApproved->approver_id,
            'approved_at' => $lastApproved->decided_at ?? now(),
        ]);
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
     * Sincroniza los empleados de un grupo. Los colaboradores que salen pierden
     * sus decisiones EN VUELO de este grupo (nunca las ya cerradas: reabrirlas
     * alteraría la nómina).
     *
     * @param  array<int, int|string>  $employeeIds
     * @return array{added:int,removed:int,decisions_removed:int}
     */
    private function syncGroupEmployees(Payroll $payroll, ExtraHourApprovalGroup $group, array $employeeIds): array
    {
        $before = $group->employees()->pluck('users.id')->map(fn ($id) => (int) $id)->all();
        $after = array_values(array_unique(array_map('intval', $employeeIds)));
        $removed = array_values(array_diff($before, $after));

        $decisionsRemoved = 0;

        if ($removed) {
            $levelIds = $group->levels()->pluck('id')->all();

            if ($levelIds) {
                $decisionsRemoved = (int) ExtraHourApprovalDecision::query()
                    ->whereIn('approval_level_id', $levelIds)
                    ->whereHas('payrollUser', function ($q) use ($payroll, $removed) {
                        $q->where('payroll_id', $payroll->id)
                            ->whereIn('user_id', $removed)
                            // Sólo decisiones EN VUELO: un día ya aprobado o
                            // rechazado no se reabre.
                            ->whereIn('extra_hour_status', ['pending', 'none']);
                    })
                    ->delete();
            }
        }

        $group->employees()->sync($after);

        return [
            'added' => count(array_diff($after, $before)),
            'removed' => count($removed),
            'decisions_removed' => $decisionsRemoved,
        ];
    }

    /**
     * Sincroniza los niveles de un grupo conservando (por id o, si la petición
     * no trae ids, por número de nivel) los que siguen existiendo.
     *
     *  - Nivel conservado → se le actualiza nombre y aprobadores (sus
     *    decisiones se mantienen).
     *  - Nivel eliminado → se borra con sus decisiones (acción deliberada).
     *  - Nivel nuevo → se agrega al final (número libre más alto + 1).
     *
     * @param  array<int, array{id?:int|null,name?:string|null,approver_ids?:array}>  $levels
     * @return array{created:int,updated:int,removed:int,decisions_removed:int}
     */
    private function syncGroupLevels(Payroll $payroll, ExtraHourApprovalGroup $group, array $levels): array
    {
        $existing = $group->levels()->orderBy('level')->get();
        $byId = $existing->keyBy('id');

        $claimed = [];   // level_id => true
        $matched = [];   // índice del payload => ExtraHourApprovalLevel

        // a) Emparejar por id explícito.
        foreach ($levels as $index => $levelData) {
            $id = isset($levelData['id']) ? (int) $levelData['id'] : 0;
            if ($id && $byId->has($id)) {
                $claimed[$id] = true;
                $matched[$index] = $byId->get($id);
            }
        }

        // b) Emparejar por número de nivel (payloads sin ids: copias de otra
        //    catorcena o clientes antiguos).
        foreach ($levels as $index => $levelData) {
            if (isset($matched[$index])) {
                continue;
            }

            $number = $index + 1;
            $candidate = $existing->first(function (ExtraHourApprovalLevel $level) use ($number, $claimed) {
                return !array_key_exists((int) $level->id, $claimed) && (int) $level->level === $number;
            });

            if ($candidate) {
                $claimed[(int) $candidate->id] = true;
                $matched[$index] = $candidate;
            }
        }

        // c) Eliminar los niveles que la configuración ya no incluye.
        $removed = 0;
        $decisionsRemoved = 0;

        foreach ($existing as $level) {
            if (array_key_exists((int) $level->id, $claimed)) {
                continue;
            }

            $decisionsRemoved += (int) ExtraHourApprovalDecision::where('approval_level_id', $level->id)->delete();
            $level->delete();
            $removed++;
        }

        // d) Actualizar los conservados y crear los nuevos.
        $maxLevel = (int) ($existing->max('level') ?? 0);
        $created = 0;
        $updated = 0;

        foreach ($levels as $index => $levelData) {
            $approverIds = array_values(array_unique(array_map('intval', $levelData['approver_ids'] ?? [])));
            $level = $matched[$index] ?? null;

            if ($level) {
                $level->update([
                    'name' => $levelData['name'] ?? $level->name ?? ('Nivel ' . ($index + 1)),
                ]);
                $level->approvers()->sync($approverIds);
                $updated++;
                continue;
            }

            $level = ExtraHourApprovalLevel::create([
                'payroll_id' => $payroll->id,
                'approval_group_id' => $group->id,
                'level' => ++$maxLevel,
                'name' => $levelData['name'] ?? ('Nivel ' . ($index + 1)),
            ]);
            $level->approvers()->sync($approverIds);
            $created++;
        }

        // e) Compactar la numeración si quedó con huecos (p.ej. al borrar un nivel
        //    intermedio), sin arriesgar el índice único (approval_group_id, level).
        $this->compactGroupLevelNumbers($group);

        return [
            'created' => $created,
            'updated' => $updated,
            'removed' => $removed,
            'decisions_removed' => $decisionsRemoved,
        ];
    }

    /**
     * Renumera 1..N los niveles del grupo cuando la numeración quedó con huecos.
     *
     * Sólo se aplica si los números actuales ya están en orden ascendente (el
     * único escenario que produce la UI: alta al final y baja en cualquier
     * posición). En ese caso reasignar en orden ascendente nunca choca con el
     * índice único (approval_group_id, level). Si el orden no es ascendente (no
     * ocurre desde la UI) se respeta la numeración existente: el pipeline usa
     * `orderBy('level')` en todos lados, así que sigue siendo correcto.
     */
    private function compactGroupLevelNumbers(ExtraHourApprovalGroup $group): void
    {
        $levels = $group->levels()->orderBy('level')->get();
        $numbers = $levels->pluck('level')->map(fn ($level) => (int) $level)->all();
        $sorted = $numbers;
        sort($sorted);

        if ($numbers === $sorted && $numbers === range(1, count($numbers))) {
            return; // ya está 1..N
        }

        if ($numbers !== $sorted) {
            return; // numeración fuera de orden: no se toca
        }

        foreach ($levels as $index => $level) {
            $target = $index + 1;
            if ((int) $level->level !== $target) {
                $level->updateQuietly(['level' => $target]);
            }
        }
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
     * Reinicia (forzando) el flujo de los registros EN VUELO de una catorcena:
     * devuelve TODOS los días sin cierre al primer nivel de su grupo.
     *
     * Ya NO se usa al guardar o copiar grupos: esas operaciones aplican un upsert
     * por identidad y sólo reubican los registros que quedaron inconsistentes
     * (ver normalizeWorkflowForPayroll()). Se mantiene como herramienta de
     * reparación manual; no toca aprobaciones finales legítimas (estado final +
     * approved_at) ni inventa decisiones.
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
