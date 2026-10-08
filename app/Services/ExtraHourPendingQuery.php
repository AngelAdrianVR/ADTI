<?php

namespace App\Services;

use App\Models\Payroll;
use App\Models\PayrollUser;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Fuente única de verdad de "tiempo extra pendiente de autorizar".
 *
 * TODOS los indicadores (badge de la barra superior, tarjetas KPI de la nómina,
 * encabezado y acciones del modal de tiempo extra) deben calcularse con este
 * servicio. Regla canónica:
 *
 *   Día con (extra_hours > 0 OR extra_minutes > 0)
 *     AND extra_hour_status = 'pending'
 *     AND current_approval_level_id NO es nulo
 *     AND ese nivel me tiene como aprobador
 *     AND el empleado pertenece AL GRUPO de ese nivel
 *     AND TODOS los niveles previos de ese grupo ya están aprobados
 *     AND no existe decisión previa en ese nivel (ni mía ni de otro aprobador)
 *
 * Un registro con current_approval_level_id = NULL es un día SIN FLUJO DE
 * AUTORIZACIÓN ("huérfano"): no cuenta en ningún indicador y no es accionable
 * desde el modal. Se reporta aparte (ver orphanQuery()).
 */
class ExtraHourPendingQuery
{
    /** Solo días con tiempo extra registrado. */
    private function scopeWithExtraTime(Builder $query): Builder
    {
        return $query->where(function ($w) {
            $w->where('extra_hours', '>', 0)->orWhere('extra_minutes', '>', 0);
        });
    }

    /**
     * Subconsulta: el nivel actual del registro pertenece a un grupo donde el
     * usuario es aprobador y el empleado del registro está en ESE grupo.
     */
    private function scopeLevelIsMineForThisEmployee(Builder $query, User $approver): Builder
    {
        return $query->whereExists(function ($sub) use ($approver) {
            $sub->selectRaw('1')
                ->from('extra_hour_approval_levels as l')
                ->join('extra_hour_approval_level_user as lu', 'lu.approval_level_id', '=', 'l.id')
                ->join('extra_hour_approval_group_user as gu', 'gu.approval_group_id', '=', 'l.approval_group_id')
                ->whereColumn('l.id', 'payroll_user.current_approval_level_id')
                ->whereColumn('gu.user_id', 'payroll_user.user_id')
                ->where('lu.user_id', $approver->id);
        });
    }

    /**
     * Subconsulta: el empleado del registro está en algún grupo de su catorcena
     * donde el usuario es aprobador de algún nivel (scope de "mis colaboradores").
     */
    private function scopeEmployeeInMyGroups(Builder $query, User $approver): Builder
    {
        return $query->whereExists(function ($sub) use ($approver) {
            $sub->selectRaw('1')
                ->from('extra_hour_approval_levels as l')
                ->join('extra_hour_approval_level_user as lu', 'lu.approval_level_id', '=', 'l.id')
                ->join('extra_hour_approval_group_user as gu', 'gu.approval_group_id', '=', 'l.approval_group_id')
                ->whereColumn('l.payroll_id', 'payroll_user.payroll_id')
                ->whereColumn('gu.user_id', 'payroll_user.user_id')
                ->where('lu.user_id', $approver->id);
        });
    }

    /**
     * Subconsulta: no queda ningún nivel previo del grupo sin aprobación.
     *
     * El flujo avanza nivel a nivel y `ExtraHourApprovalService::decide()` rechaza
     * la decisión con "El nivel anterior aún no ha sido aprobado". Sin este filtro,
     * el badge / los KPI / el modal contaban como "en tu turno" días de nivel 2 o 3
     * que en realidad fallaban al intentar decidirlos.
     */
    private function scopePreviousLevelsApproved(Builder $query): Builder
    {
        return $query->whereNotExists(function ($sub) {
            $sub->selectRaw('1')
                ->from('extra_hour_approval_levels as cur')
                ->join('extra_hour_approval_levels as prev', function ($join) {
                    $join->on('prev.approval_group_id', '=', 'cur.approval_group_id')
                        ->whereColumn('prev.level', '<', 'cur.level');
                })
                ->whereColumn('cur.id', 'payroll_user.current_approval_level_id')
                ->whereNotExists(function ($dec) {
                    $dec->selectRaw('1')
                        ->from('extra_hour_approval_decisions as pdec')
                        ->whereColumn('pdec.payroll_user_id', 'payroll_user.id')
                        ->whereColumn('pdec.approval_level_id', 'prev.id')
                        ->where('pdec.status', 'approved');
                });
        });
    }

    /**
     * Días que el usuario PUEDE decidir ahora mismo (regla canónica).
     */
    public function pendingQuery(User $approver, ?int $payrollId = null): Builder
    {
        $query = PayrollUser::query();
        $this->scopeWithExtraTime($query);
        $query->where('extra_hour_status', 'pending')
            ->whereNotNull('current_approval_level_id');

        $this->scopeLevelIsMineForThisEmployee($query, $approver);

        // Sin decisión previa en el nivel actual (ni mía ni de otro aprobador del mismo nivel).
        $query->whereNotExists(function ($sub) {
            $sub->selectRaw('1')
                ->from('extra_hour_approval_decisions as d')
                ->whereColumn('d.payroll_user_id', 'payroll_user.id')
                ->whereColumn('d.approval_level_id', 'payroll_user.current_approval_level_id')
                ->where('d.status', '!=', 'pending');
        });

        // Los niveles previos del grupo ya deben estar aprobados (misma regla que decide()).
        $this->scopePreviousLevelsApproved($query);

        return $payrollId ? $query->where('payroll_id', $payrollId) : $query;
    }

    /**
     * Días "sin flujo de autorización" (nivel NULL) pero visibles para el usuario
     * porque el empleado pertenece a alguno de sus grupos.
     *
     * @param  bool|null  $onlyCovered  true = el empleado SÍ tiene grupo (rescatable),
     *                                 false = el empleado no está en ningún grupo,
     *                                 null = ambos.
     */
    public function orphanQuery(User $approver, ?int $payrollId = null, ?bool $onlyCovered = null): Builder
    {
        $query = PayrollUser::query();
        $this->scopeWithExtraTime($query);
        $query->where('extra_hour_status', 'pending')
            ->whereNull('current_approval_level_id');

        $this->scopeEmployeeInMyGroups($query, $approver);

        $covered = function ($sub) {
            $sub->selectRaw('1')
                ->from('extra_hour_approval_groups as g')
                ->join('extra_hour_approval_group_user as gu', 'gu.approval_group_id', '=', 'g.id')
                ->whereColumn('g.payroll_id', 'payroll_user.payroll_id')
                ->whereColumn('gu.user_id', 'payroll_user.user_id');
        };

        if ($onlyCovered === true) {
            $query->whereExists($covered);
        } elseif ($onlyCovered === false) {
            $query->whereNotExists($covered);
        }

        return $payrollId ? $query->where('payroll_id', $payrollId) : $query;
    }

    /**
     * Resumen por catorcena para los indicadores y el badge.
     *
     * @return array<int, array{id:int,label:string|int,pending_days:int,pending_employees:int,pending_count:int,orphan_days:int,orphan_uncovered_days:int,is_active:bool,start_date:?string}>
     */
    public function summaryFor(User $approver, ?int $payrollId = null): array
    {
        $pending = $this->pendingQuery($approver, $payrollId)
            ->selectRaw('payroll_id, COUNT(*) as pending_days, COUNT(DISTINCT user_id) as pending_employees')
            ->groupBy('payroll_id')
            ->get()
            ->keyBy('payroll_id');

        $covered = $this->orphanQuery($approver, $payrollId, true)
            ->selectRaw('payroll_id, COUNT(*) as n')
            ->groupBy('payroll_id')
            ->pluck('n', 'payroll_id');

        $uncovered = $this->orphanQuery($approver, $payrollId, false)
            ->selectRaw('payroll_id, COUNT(*) as n')
            ->groupBy('payroll_id')
            ->pluck('n', 'payroll_id');

        $payrollIds = $pending->keys()
            ->merge($covered->keys())
            ->merge($uncovered->keys())
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->sortDesc()
            ->values();

        if ($payrollIds->isEmpty()) {
            return [];
        }

        $payrolls = Payroll::whereIn('id', $payrollIds)->get()->keyBy('id');

        return $payrollIds->map(function ($id) use ($pending, $covered, $uncovered, $payrolls) {
            /** @var Payroll|null $payroll */
            $payroll = $payrolls->get($id);
            $row = $pending->get($id);

            return [
                'id' => (int) $id,
                'label' => $payroll?->biweekly !== null ? $payroll->biweekly : 'Catorcena #' . $id,
                'pending_days' => (int) ($row->pending_days ?? 0),
                'pending_employees' => (int) ($row->pending_employees ?? 0),
                // Compatibilidad con el prop previo del badge
                'pending_count' => (int) ($row->pending_days ?? 0),
                'orphan_days' => (int) $covered->get($id, 0),
                'orphan_uncovered_days' => (int) $uncovered->get($id, 0),
                'is_active' => (bool) ($payroll?->is_active ?? false),
                'start_date' => $payroll?->start_date?->toDateString(),
            ];
        })->all();
    }

    /**
     * Resumen de UNA catorcena (para la página de nómina / modal).
     */
    public function summaryForPayroll(User $approver, Payroll $payroll): array
    {
        $rows = $this->summaryFor($approver, (int) $payroll->id);

        return $rows[0] ?? [
            'id' => (int) $payroll->id,
            'label' => $payroll->biweekly,
            'pending_days' => 0,
            'pending_employees' => 0,
            'pending_count' => 0,
            'orphan_days' => 0,
            'orphan_uncovered_days' => 0,
            'is_active' => (bool) $payroll->is_active,
            'start_date' => $payroll->start_date?->toDateString(),
        ];
    }

    /**
     * Evalúa UNA incidencia en memoria (misma regla que las consultas SQL).
     *
     * Se usa para etiquetar cada día del payload de la nómina, de modo que el
     * frontend no tenga que re-derivar permisos (y nunca pueda discrepar del badge).
     *
     * @param  array  $groups  Grupos de la catorcena: [{id, employee_ids, levels:[{id, level, approvers:[{id}]}]}]
     * @return array{can_act:bool,reason:string,orphan:bool,is_my_employee:bool,already_decided:bool,level_id:?int}
     */
    /**
     * ¿Queda algún nivel previo del grupo sin aprobación para el nivel actual?
     *
     * Se evalúa con las decisiones que ya trae la incidencia (misma regla que
     * `ExtraHourApprovalService::decide()`).
     */
    private function hasPendingPreviousLevel(?array $employeeGroup, ?int $currentLevelNumber, $decisions): bool
    {
        if (!$employeeGroup || !$currentLevelNumber || $currentLevelNumber <= 1) {
            return false;
        }

        foreach ($employeeGroup['levels'] ?? [] as $level) {
            if ((int) ($level['level'] ?? 0) >= $currentLevelNumber) {
                continue;
            }

            $approved = false;
            foreach ($decisions as $decision) {
                if ((int) ($decision['level_id'] ?? 0) === (int) ($level['id'] ?? 0)
                    && ($decision['status'] ?? '') === 'approved') {
                    $approved = true;
                    break;
                }
            }

            if (!$approved) {
                return true;
            }
        }

        return false;
    }

    public function evaluateIncidence(PayrollUser $incidence, array $groups, int $currentUserId): array
    {
        $levelId = $incidence->current_approval_level_id ? (int) $incidence->current_approval_level_id : null;

        $flag = static function (bool $canAct, string $reason, bool $orphan = false, bool $isMyEmployee = false, bool $alreadyDecided = false) use ($levelId) {
            return [
                'can_act' => $canAct,
                'reason' => $reason,
                'orphan' => $orphan,
                'is_my_employee' => $isMyEmployee,
                'already_decided' => $alreadyDecided,
                'level_id' => $levelId,
            ];
        };

        $hasExtra = ($incidence->extra_hours ?? 0) > 0 || ($incidence->extra_minutes ?? 0) > 0;
        if (!$hasExtra) {
            return $flag(false, 'Sin tiempo extra');
        }

        $status = $incidence->extra_hour_status ?? 'none';
        $employeeId = (int) $incidence->user_id;

        // Grupo del empleado (el de menor id, igual que ExtraHourApprovalService::findGroupForUser()).
        $employeeGroup = null;
        foreach ($groups as $group) {
            if (in_array($employeeId, array_map('intval', $group['employee_ids'] ?? []), true)) {
                $employeeGroup = $group;
                break;
            }
        }

        $isMyEmployee = false;
        if ($employeeGroup) {
            foreach ($employeeGroup['levels'] ?? [] as $level) {
                foreach ($level['approvers'] ?? [] as $approver) {
                    if ((int) $approver['id'] === $currentUserId) {
                        $isMyEmployee = true;
                        break 2;
                    }
                }
            }
        }

        $decisions = $incidence->approval_decisions ?? [];
        $decisionAtLevel = null;
        $myDecision = null;
        if ($levelId) {
            foreach ($decisions as $d) {
                if ((int) ($d['level_id'] ?? 0) !== $levelId) {
                    continue;
                }
                $decisionAtLevel ??= $d;
                if ((int) ($d['approver']['id'] ?? 0) === $currentUserId) {
                    $myDecision = $d;
                }
            }
        }

        // Ya resuelto globalmente
        if (in_array($status, ['approved', 'rejected'], true)) {
            return $flag(
                false,
                $status === 'approved' ? 'Ya fue aprobado' : 'Ya fue rechazado',
                false,
                $isMyEmployee,
                $myDecision !== null
            );
        }

        // SIN FLUJO: nunca es accionable (antes el frontend lo trataba como "modo directo")
        if ($levelId === null) {
            return $flag(false, 'Sin flujo de autorización (el colaborador no está en ningún grupo)', true, $isMyEmployee);
        }

        if (!$isMyEmployee) {
            return $flag(false, 'Fuera de tu grupo', false, false);
        }

        // El nivel actual debe pertenecer al grupo del empleado y yo ser aprobador de él
        $levelBelongsToEmployeeGroup = false;
        $levelIsMine = false;
        $currentLevelNumber = null;
        foreach ($employeeGroup['levels'] ?? [] as $level) {
            if ((int) $level['id'] !== $levelId) {
                continue;
            }
            $levelBelongsToEmployeeGroup = true;
            $currentLevelNumber = (int) ($level['level'] ?? 0);
            foreach ($level['approvers'] ?? [] as $approver) {
                if ((int) $approver['id'] === $currentUserId) {
                    $levelIsMine = true;
                    break;
                }
            }
            break;
        }

        if (!$levelBelongsToEmployeeGroup) {
            return $flag(false, 'El nivel actual ya no corresponde al grupo del colaborador', false, true);
        }

        if (!$levelIsMine) {
            return $flag(false, 'Esperando decisión de otro nivel', false, true);
        }

        // Los niveles previos del grupo deben estar aprobados: decide() rechaza la
        // decisión ("El nivel anterior aún no ha sido aprobado"), así que el día no
        // puede contarse como "en tu turno" mientras eso no ocurra.
        if ($this->hasPendingPreviousLevel($employeeGroup, $currentLevelNumber, $decisions)) {
            return $flag(false, 'Esperando aprobación del nivel previo', false, true);
        }

        if ($myDecision) {
            return $flag(false, ($myDecision['status'] ?? '') === 'approved' ? 'Has aprobado este tiempo extra' : 'Has rechazado este tiempo extra', false, true, true);
        }

        if ($decisionAtLevel) {
            return $flag(false, 'Otro aprobador de tu nivel ya decidió', false, true);
        }

        return $flag(true, 'Es tu turno de revisar', false, true);
    }

}

