<?php

namespace App\Http\Controllers;

use App\Http\Requests\BulkDecideExtraHourRequest;
use App\Http\Requests\DecideExtraHourRequest;
use App\Models\ExtraHourCost;
use App\Models\Payroll;
use App\Models\PayrollUser;
use App\Models\User;
use App\Services\ExtraHourApprovalService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class PayrollExtraHoursController extends Controller
{
    public function __construct(
        private ExtraHourApprovalService $approvals
    ) {}
    /**
     * Muestra la vista de configuraciรณn de costos y niveles de autorizaciรณn
     * para una nรณmina especรญfica.
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

        // Cargar grupos de aprobaciรณn con sus niveles y aprobadores
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
            ->whereNotIn('org_props->position', ['Direcciรณn', 'Soporte DTW'])
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

        // Usuarios que tienen tiempo extra en esta nรณmina (para asignarles niveles)
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

        // Verificar si hay una nรณmina anterior para copiar
        $hasPreviousPayroll = Payroll::where('id', '<', $payroll->id)->exists();

        // Verificar si hay una nรณmina siguiente para copiar
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
     * Guarda o actualiza los costos de hora extra para la nรณmina.
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
            // Eliminar costos existentes de esta nรณmina
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
     * Guarda los grupos de aprobaciรณn con sus niveles para la nรณmina.
     */
    public function saveApprovalGroups(Request $request, Payroll $payroll)
    {
        $request->validate([
            'groups' => 'required|array',
            'groups.*.id' => 'nullable|integer',
            'groups.*.name' => 'nullable|string|max:100',
            'groups.*.employee_ids' => 'required|array|min:1',
            'groups.*.employee_ids.*' => 'required|integer|exists:users,id',
            'groups.*.levels' => 'required|array|min:1',
            'groups.*.levels.*.id' => 'nullable|integer',
            'groups.*.levels.*.name' => 'nullable|string|max:100',
            'groups.*.levels.*.approver_ids' => 'required|array|min:1',
            'groups.*.levels.*.approver_ids.*' => 'required|integer|exists:users,id',
        ]);

        // Un colaborador solo puede pertenecer a UN grupo de aprobacion. Si por
        // alguna via llega repetido en varios grupos (p. ej. un cliente que aun no
        // resuelve el "mover" en pantalla), se CONSERVA unicamente en el ultimo
        // grupo que lo incluye (el destino del cambio) y se retira de los
        // anteriores. Antes se devolvia withErrors, pero la pantalla no mostraba
        // el mensaje y parecia que si se habia guardado; ahora la operacion se
        // resuelve sola, igual que "agregar al nuevo = quitar del antiguo".
        $groupIndexByEmployee = [];
        foreach ($request->groups as $gi => $groupData) {
            foreach ($groupData['employee_ids'] as $empId) {
                $groupIndexByEmployee[(int) $empId] = $gi;
            }
        }

        $groups = collect($request->groups)
            ->map(function ($groupData, $gi) use ($groupIndexByEmployee) {
                $groupData['employee_ids'] = array_values(array_filter(
                    $groupData['employee_ids'],
                    fn ($empId) => $groupIndexByEmployee[(int) $empId] === $gi
                ));

                return $groupData;
            })
            ->all();

        // Upsert por identidad: actualizar los grupos/niveles que siguen
        // existiendo (por id, o por nombre/número de nivel cuando la petición no
        // trae ids) y eliminar solo los que desaparecen de la configuración. Las
        // decisiones ya registradas se conservan; solo se borran las de los
        // colaboradores que salen de un grupo, las de los niveles eliminados y
        // las de los grupos eliminados.
        $stats = DB::transaction(function () use ($groups, $payroll) {
            return $this->approvals->applyApprovalGroups($payroll, $groups);
        });

        // Reubicar SÓLO los registros que quedaron inconsistentes. Los días en
        // vuelo de los grupos que no cambiaron conservan su nivel y sus
        // decisiones (antes se reiniciaban TODOS al primer nivel en cada guardado,
        // como si la configuración se acabara de crear).
        $normalize = $this->approvals->normalizeWorkflowForPayroll($payroll);

        return back()->with('success', sprintf(
            'Grupos y niveles de autorización guardados. Grupos: %d nuevos, %d actualizados, %d eliminados · Niveles: %d nuevos, %d eliminados · Autorizaciones en curso conservadas: %d%s.',
            $stats['groups_created'],
            $stats['groups_updated'],
            $stats['groups_removed'],
            $stats['levels_created'],
            $stats['levels_removed'],
            $normalize['kept'],
            ($stats['decisions_removed'] > 0 || $normalize['without_group'] > 0)
                ? ' · ' . $stats['decisions_removed'] . ' decisiones invalidadas, ' . $normalize['without_group'] . ' días sin grupo (sin flujo)'
                : ''
        ));
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
            return back()->withErrors(['error' => 'No hay una nรณmina anterior para copiar.']);
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

            // 2. Copiar grupos de aprobación con sus niveles y aprobadores.
            //    Upsert por nombre: los grupos que ya existen con el mismo nombre
            //    conservan sus niveles, sus decisiones y el avance en vuelo.
            //    (Antes se borraban todos los grupos y, con ellos por cascade, las
            //    decisiones de toda la catorcena.)
            $this->copyApprovalGroupsFrom($previous, $payroll);

            // Sólo se reubican los registros que quedaron inconsistentes (grupo o
            // nivel que ya no existe); el avance de los grupos intactos se respeta.
            $this->approvals->normalizeWorkflowForPayroll($payroll);
        });

        return back()->with('success', 'Configuraciรณn copiada de la nรณmina anterior correctamente.');
    }

    /**
     * Copia la configuraciรณn (costos + grupos) de la nรณmina siguiente a la actual.
     */
    public function copyFromNext(Payroll $payroll)
    {
        $next = Payroll::where('id', '>', $payroll->id)
            ->orderBy('id', 'asc')
            ->first();

        if (!$next) {
            return back()->withErrors(['error' => 'No hay una nรณmina siguiente para copiar.']);
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

            // 2. Copiar grupos de aprobación con sus niveles y aprobadores.
            //    Upsert por nombre: los grupos que ya existen con el mismo nombre
            //    conservan sus niveles, sus decisiones y el avance en vuelo.
            //    (Antes se borraban todos los grupos y, con ellos por cascade, las
            //    decisiones de toda la catorcena.)
            $this->copyApprovalGroupsFrom($next, $payroll);

            // Sólo se reubican los registros que quedaron inconsistentes (grupo o
            // nivel que ya no existe); el avance de los grupos intactos se respeta.
            $this->approvals->normalizeWorkflowForPayroll($payroll);
        });

        return back()->with('success', 'Configuraciรณn copiada de la nรณmina siguiente correctamente.');
    }

    /**
     * Copia los grupos/niveles de una catorcena a otra mediante el upsert por
     * identidad: los grupos que ya existan en el destino con el mismo nombre
     * conservan sus niveles, sus decisiones y el avance en vuelo. Antes, copiar
     * borraba todos los grupos del destino y, por cascade, todas las decisiones
     * de la catorcena.
     */
    private function copyApprovalGroupsFrom(Payroll $source, Payroll $target): void
    {
        $payload = $source->approvalGroups()
            ->with(['employees', 'levels.approvers'])
            ->orderBy('id')
            ->get()
            ->map(fn ($group) => [
                // Sin ids: pertenecen a la otra catorcena. El emparejamiento se
                // hace por nombre de grupo y por número de nivel.
                'id' => null,
                'name' => $group->name,
                'employee_ids' => $group->employees->pluck('id')->all(),
                'levels' => $group->levels
                    ->sortBy('level')
                    ->values()
                    ->map(fn ($level) => [
                        'id' => null,
                        'name' => $level->name,
                        'approver_ids' => $level->approvers->pluck('id')->all(),
                    ])
                    ->all(),
            ])
            ->values()
            ->all();

        $this->approvals->applyApprovalGroups($target, $payload);
    }

    /**
     * Procesa la decisiรณn de un aprobador sobre una entrada de tiempo extra.
     * Se llama desde el modal de gestiรณn de tiempo extra.
     */
    public function decide(DecideExtraHourRequest $request)
    {
        $payrollUser = PayrollUser::findOrFail($request->payroll_user_id);

        try {
            $this->approvals->decide($payrollUser, $request->user(), $request->status, $request->validated());
        } catch (\RuntimeException $e) {
            // Para peticiones AJAX (no Inertia), devolver JSON en lugar de redirect
            if (!$request->header('X-Inertia')) {
                return response()->json(['error' => $e->getMessage()], 422);
            }

            return back()->withErrors(['error' => $e->getMessage()]);
        }

        if ($request->header('X-Inertia')) {
            return back()->with('success', 'Decisiรณn registrada correctamente.');
        }

        return response()->json(['success' => true, 'message' => 'Decisiรณn registrada correctamente.']);
    }

    /**
     * Aprueba o rechaza mรบltiples registros en lote.
     */
    public function decideBulk(BulkDecideExtraHourRequest $request)
    {
        $result = $this->approvals->bulkDecide(
            $request->payroll_user_ids,
            $request->user(),
            $request->status,
            $request->validated()
        );

        $okCount = count($result['ok']);
        $errCount = count($result['errors']);
        $msg = "{$okCount} registros procesados." . ($errCount ? " {$errCount} errores." : '');

        if ($request->header('X-Inertia')) {
            return back()->with($errCount ? 'warning' : 'success', $msg);
        }

        return response()->json(['success' => true, 'message' => $msg, 'result' => $result]);
    }

    /**
     * Revierte una decisiรณn de aprobaciรณn (para correcciones).
     */
    public function revertDecision(Request $request)
    {
        $request->validate([
            'payroll_user_id' => 'required|integer|exists:payroll_user,id',
        ]);

        try {
            $payrollUser = PayrollUser::findOrFail($request->payroll_user_id);
            // No se exige permiso especial: la protección la da ExtraHourApprovalService::revert(),
            // que valida que el actor sea aprobador del grupo del empleado.
            $this->approvals->revert($payrollUser, $request->user());
        } catch (\RuntimeException $e) {
            if (!$request->header('X-Inertia')) {
                return response()->json(['error' => $e->getMessage()], 422);
            }
            return back()->withErrors(['error' => $e->getMessage()]);
        }

        if ($request->header('X-Inertia')) {
            return back()->with('success', 'Decisiรณn revocada correctamente.');
        }

        return response()->json(['success' => true, 'message' => 'Decisiรณn revocada correctamente.']);
    }
}
