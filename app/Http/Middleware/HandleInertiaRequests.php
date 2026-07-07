<?php

namespace App\Http\Middleware;

use App\Models\ExtraHourApprovalLevel;
use App\Models\Payroll;
use App\Models\PayrollUser;
use App\Models\VacationRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return array_merge(parent::share($request), [
            // Compartir datos flash de reasignación (para modal de eliminación de departamentos)
            'reassignData' => fn () => $request->session()->get('reassignData'),

            'auth.user.permissions' => function () use ($request) {
                if ($request->user()) {
                    return $request->user()->getAllPermissions()->pluck('name');
                }
                return [];
            },
            'auth.user.roles' => function () use ($request) {
                if ($request->user()) {
                    return $request->user()->roles()->pluck('name');
                }
                return [];
            },
            'auth.user.nextAttendance' => function () use ($request) {
                if ($request->user()) {
                    return $request->user()->getNextAttendance();
                }

                return null;
            },
            // Compartir la entrada de tiempo activa (si existe)
            'auth.user.active_entry' => function () use ($request) {
                if ($request->user()) {
                    return $request->user()->activeTimeEntry()
                        ->with('project:id,name') // Solo traemos id y nombre del proyecto
                        ->first();
                }
                return null;
            },
            // NUEVO: Contador de solicitudes de vacaciones pendientes
            'auth.user.pendingVacationRequests' => function () use ($request) {
                $user = $request->user();
                if (!$user) return 0;

                // 1. Si tiene el permiso global, cuenta absolutamente todas las pendientes
                if ($user->can('Gestionar cualquier solicitud de vacaciones')) {
                    return VacationRequest::where('status', 'Pendiente')->count();
                }

                // 2. Si no tiene el permiso global, verificamos si tiene empleados a cargo
                $employeesInCharge = $user->employees_in_charge ?? [];
                if (!empty($employeesInCharge)) {
                    return VacationRequest::where('status', 'Pendiente')
                        ->whereIn('user_id', $employeesInCharge)
                        ->count();
                }

                // 3. Si no cumple ninguna de las dos, devuelve 0
                return 0;
            },

            // Contador de tiempo extra pendiente por aprobar (por catorcena)
            // Solo muestra entradas donde es el TURNO del usuario (nivel actual = su nivel)
            'auth.user.pendingExtraTimePayrolls' => function () use ($request) {
                $user = $request->user();
                if (!$user) return [];

                $userId = $user->id;

                // ¿Es aprobador en algún nivel?
                $isApprover = ExtraHourApprovalLevel::whereHas('approvers', fn ($q) => $q->where('user_id', $userId))->exists();
                if (!$isApprover) {
                    return [];
                }

                // Solo pendientes cuyo nivel ACTUAL tiene a este usuario como aprobador
                $results = PayrollUser::where('extra_hour_status', 'pending')
                    ->whereNotNull('current_approval_level_id')
                    ->whereHas('currentApprovalLevel', fn ($q) => $q->whereHas('approvers', fn ($q2) => $q2->where('user_id', $userId)))
                    ->select('payroll_id', DB::raw('COUNT(*) as pending_count'))
                    ->groupBy('payroll_id')
                    ->orderBy('payroll_id', 'desc')
                    ->get();

                if ($results->isEmpty()) {
                    return [];
                }

                // Cargar datos de las catorcenas
                $payrollIds = $results->pluck('payroll_id');
                $payrolls = Payroll::whereIn('id', $payrollIds)
                    ->orderBy('id', 'desc')
                    ->get()
                    ->keyBy('id');

                return $results->map(function ($row) use ($payrolls) {
                    $payroll = $payrolls->get($row->payroll_id);
                    return [
                        'id' => $row->payroll_id,
                        'label' => $payroll ? $payroll->biweekly : 'Catorcena #' . $row->payroll_id,
                        'pending_count' => (int) $row->pending_count,
                    ];
                })->values()->toArray();
            },
        ]);
    }
}