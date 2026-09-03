<?php

namespace App\Services;

use App\Models\Payroll;
use App\Models\PayrollUser;
use App\Models\PayrollUserProject;
use App\Models\Project;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Métricas de costo y horas extra para proyectos.
 *
 * Fuente de verdad para la atribución POR PROYECTO: la tabla pivote
 * `payroll_user_project` (un día laborado puede vincular varios proyectos,
 * cada uno con tipo interno/externo, departamento y tiempo extra propio).
 *
 * Solo se consideran vínculos cuyo día tiene tiempo extra APROBADO
 * (extra_hour_status = 'approved') con horas > 0.
 */
class ProjectExtraTimeMetricsService
{
    public function __construct(private ExtraHourCostResolver $costResolver)
    {
    }

    /**
     * Métricas de un proyecto en un rango de fechas.
     *
     * @return array{
     *     total_extra_hours: float,
     *     total_cost: float,
     *     employees: Collection,
     *     daily: Collection,
     *     monthly_series: array,
     *     work_type_breakdown: array,
     * }
     */
    public function forProject(Project $project, ?CarbonInterface $startDate = null, ?CarbonInterface $endDate = null): array
    {
        $links = $this->linksQuery($project->id, $startDate, $endDate)
            ->with(['payrollUser.user:id,name,profile_photo_path'])
            ->get();

        $costsByPayroll = $this->loadCostsByPayroll($links->pluck('payrollUser.payroll_id'));

        $monthly = [];
        $workTypeBreakdown = [
            'internal_hours' => 0.0,
            'internal_cost' => 0.0,
            'external_hours' => 0.0,
            'external_cost' => 0.0,
        ];

        $daily = $links->map(function ($link) use ($costsByPayroll, &$monthly, &$workTypeBreakdown) {
            $pu = $link->payrollUser;
            $hours = $link->hours;
            if ($hours <= 0 || !$pu) {
                return null;
            }

            $costPerHour = $this->costResolver->resolve(
                $pu->date,
                (int) $pu->user_id,
                $costsByPayroll->get($pu->payroll_id, collect())
            );
            $amount = round($hours * $costPerHour, 2);

            // Serie mensual y desglose interno/externo (solo este proyecto)
            $monthKey = $pu->date->format('Y-m');
            $monthly[$monthKey] ??= [
                'month' => $monthKey,
                'internal_hours' => 0.0,
                'internal_cost' => 0.0,
                'external_hours' => 0.0,
                'external_cost' => 0.0,
            ];

            if ($link->work_type === 'external') {
                $monthly[$monthKey]['external_hours'] += $hours;
                $monthly[$monthKey]['external_cost'] += $amount;
                $workTypeBreakdown['external_hours'] += $hours;
                $workTypeBreakdown['external_cost'] += $amount;
            } else {
                $monthly[$monthKey]['internal_hours'] += $hours;
                $monthly[$monthKey]['internal_cost'] += $amount;
                $workTypeBreakdown['internal_hours'] += $hours;
                $workTypeBreakdown['internal_cost'] += $amount;
            }

            return [
                'user' => [
                    'id' => $pu->user->id,
                    'name' => $pu->user->name,
                    'profile_photo_url' => $pu->user->profile_photo_url,
                ],
                'date' => $pu->date->toDateString(),
                'cost_per_hour' => $costPerHour,
                'hours' => $hours,
                'amount' => $amount,
            ];
        })
            ->filter()
            ->values();

        $employees = $daily
            ->groupBy('user.id')
            ->map(function ($rows) {
                return [
                    'user' => $rows->first()['user'],
                    'total_extra_hours' => round($rows->sum('hours'), 2),
                    'total_cost' => round($rows->sum('amount'), 2),
                    'days' => $rows->count(),
                ];
            })
            ->sortByDesc('total_cost')
            ->values();

        return [
            'total_extra_hours' => round($daily->sum('hours'), 2),
            'total_cost' => round($daily->sum('amount'), 2),
            'employees' => $employees,
            'daily' => $daily->groupBy('user.id'),
            'monthly_series' => $this->buildMonthlySeries($monthly, $startDate, $endDate),
            'work_type_breakdown' => [
                'internal_hours' => round($workTypeBreakdown['internal_hours'], 2),
                'internal_cost' => round($workTypeBreakdown['internal_cost'], 2),
                'external_hours' => round($workTypeBreakdown['external_hours'], 2),
                'external_cost' => round($workTypeBreakdown['external_cost'], 2),
                'total_hours' => round(
                    $workTypeBreakdown['internal_hours'] + $workTypeBreakdown['external_hours'],
                    2
                ),
                'total_cost' => round(
                    $workTypeBreakdown['internal_cost'] + $workTypeBreakdown['external_cost'],
                    2
                ),
            ],
        ];
    }

    /**
     * Métricas globales: ranking de proyectos, ranking de empleados (por horas
     * y por monto), totales globales, serie mensual de tiempo extra y desglose
     * de trabajo interno vs externo.
     *
     * @return array{
     *     projects_ranking: Collection,
     *     employees_ranking: Collection,
     *     employees_ranking_by_cost: Collection,
     *     total_extra_hours: float,
     *     total_cost: float,
     *     monthly_series: array,
     *     work_type_breakdown: array,
     * }
     */
    public function globalMetrics(?CarbonInterface $startDate = null, ?CarbonInterface $endDate = null): array
    {
        // ── 1. Empleados y totales globales: TODO tiempo extra aprobado ──
        $records = $this->approvedRecordsQuery()
            ->with(['user:id,name,profile_photo_path'])
            ->when($startDate && $endDate, fn ($q) => $q->whereBetween('date', [
                $startDate->toDateString(),
                $endDate->toDateString(),
            ]))
            ->get();

        $globalCostsByPayroll = $this->loadCostsByPayroll($records->pluck('payroll_id'));

        $globalHours = 0.0;
        $globalCost = 0.0;
        $employeeTotals = [];

        foreach ($records as $record) {
            $hours = $this->approvedHours($record);
            if ($hours <= 0) {
                continue;
            }

            $costPerHour = $this->costResolver->resolve(
                $record->date,
                (int) $record->user_id,
                $globalCostsByPayroll->get($record->payroll_id, collect())
            );
            $cost = round($hours * $costPerHour, 2);

            $globalHours += $hours;
            $globalCost += $cost;

            $employeeTotals[$record->user_id] ??= [
                'user' => [
                    'id' => $record->user->id,
                    'name' => $record->user->name,
                    'profile_photo_url' => $record->user->profile_photo_url,
                ],
                'total_extra_hours' => 0.0,
                'total_cost' => 0.0,
            ];
            $employeeTotals[$record->user_id]['total_extra_hours'] += $hours;
            $employeeTotals[$record->user_id]['total_cost'] += $cost;
        }

        $employeesRanking = collect($employeeTotals)
            ->map(fn ($item) => [
                'user' => $item['user'],
                'total_extra_hours' => round($item['total_extra_hours'], 2),
                'total_cost' => round($item['total_cost'], 2),
            ])
            ->sortByDesc('total_extra_hours')
            ->values();

        $employeesRankingByCost = $employeesRanking
            ->sortByDesc('total_cost')
            ->values();

        // ── 2. Proyectos + serie mensual + interno/externo: pivote ──
        $links = $this->linksQuery(null, $startDate, $endDate)
            ->with(['project:id,name,client', 'payrollUser.user:id,name,profile_photo_path'])
            ->get();

        $linksCostsByPayroll = $this->loadCostsByPayroll($links->pluck('payrollUser.payroll_id'));

        $projectTotals = [];
        $monthly = [];
        $workTypeBreakdown = [
            'internal_hours' => 0.0,
            'internal_cost' => 0.0,
            'external_hours' => 0.0,
            'external_cost' => 0.0,
        ];

        foreach ($links as $link) {
            $hours = $link->hours;
            $pu = $link->payrollUser;
            if ($hours <= 0 || !$pu) {
                continue;
            }

            $costPerHour = $this->costResolver->resolve(
                $pu->date,
                (int) $pu->user_id,
                $linksCostsByPayroll->get($pu->payroll_id, collect())
            );
            $cost = round($hours * $costPerHour, 2);

            // Ranking de proyectos
            if ($link->project_id && $link->project) {
                $projectTotals[$link->project_id] ??= [
                    'project' => [
                        'id' => $link->project->id,
                        'name' => $link->project->name,
                        'client' => $link->project->client,
                    ],
                    'total_extra_hours' => 0.0,
                    'total_cost' => 0.0,
                ];
                $projectTotals[$link->project_id]['total_extra_hours'] += $hours;
                $projectTotals[$link->project_id]['total_cost'] += $cost;
            }

            // Serie mensual
            $monthKey = $pu->date->format('Y-m');
            $monthly[$monthKey] ??= [
                'month' => $monthKey,
                'internal_hours' => 0.0,
                'internal_cost' => 0.0,
                'external_hours' => 0.0,
                'external_cost' => 0.0,
            ];

            if ($link->work_type === 'external') {
                $monthly[$monthKey]['external_hours'] += $hours;
                $monthly[$monthKey]['external_cost'] += $cost;
                $workTypeBreakdown['external_hours'] += $hours;
                $workTypeBreakdown['external_cost'] += $cost;
            } else {
                $monthly[$monthKey]['internal_hours'] += $hours;
                $monthly[$monthKey]['internal_cost'] += $cost;
                $workTypeBreakdown['internal_hours'] += $hours;
                $workTypeBreakdown['internal_cost'] += $cost;
            }
        }

        $projectsRanking = collect($projectTotals)
            ->map(fn ($item) => [
                'project' => $item['project'],
                'total_extra_hours' => round($item['total_extra_hours'], 2),
                'total_cost' => round($item['total_cost'], 2),
            ])
            ->sortByDesc('total_cost')
            ->values();

        // Completar la serie con todos los meses del rango (ceros para huecos)
        $monthlySeries = $this->buildMonthlySeries($monthly, $startDate, $endDate);

        return [
            'projects_ranking' => $projectsRanking,
            'employees_ranking' => $employeesRanking,
            'employees_ranking_by_cost' => $employeesRankingByCost,
            'total_extra_hours' => round($globalHours, 2),
            'total_cost' => round($globalCost, 2),
            'monthly_series' => $monthlySeries,
            'work_type_breakdown' => [
                'internal_hours' => round($workTypeBreakdown['internal_hours'], 2),
                'internal_cost' => round($workTypeBreakdown['internal_cost'], 2),
                'external_hours' => round($workTypeBreakdown['external_hours'], 2),
                'external_cost' => round($workTypeBreakdown['external_cost'], 2),
                'total_hours' => round($workTypeBreakdown['internal_hours'] + $workTypeBreakdown['external_hours'], 2),
                'total_cost' => round($workTypeBreakdown['internal_cost'] + $workTypeBreakdown['external_cost'], 2),
            ],
        ];
    }

    /**
     * Vínculos de la tabla pivote para un proyecto/rango, restringidos a días
     * con tiempo extra aprobado y con horas asignadas a ese proyecto.
     */
    private function linksQuery(?int $projectId, ?CarbonInterface $startDate, ?CarbonInterface $endDate)
    {
        return PayrollUserProject::query()
            ->when($projectId, fn ($q) => $q->where('project_id', $projectId))
            ->whereHas('payrollUser', function ($q) use ($startDate, $endDate) {
                $q->where('extra_hour_status', 'approved')
                    ->where(function ($qq) {
                        $qq->where('approved_extra_hours', '>', 0)
                            ->orWhere('approved_extra_minutes', '>', 0);
                    })
                    ->when($startDate && $endDate, fn ($qq) => $qq->whereBetween('date', [
                        $startDate->toDateString(),
                        $endDate->toDateString(),
                    ]));
            });
    }

    /**
     * Registros de payroll_user con tiempo extra aprobado (base histórica).
     */
    private function approvedRecordsQuery()
    {
        return PayrollUser::query()
            ->where('extra_hour_status', 'approved')
            ->where(function ($q) {
                $q->where('approved_extra_hours', '>', 0)
                    ->orWhere('approved_extra_minutes', '>', 0);
            });
    }

    private function approvedHours(PayrollUser $record): float
    {
        $hours = (float) ($record->approved_extra_hours ?? 0);
        $minutes = (float) ($record->approved_extra_minutes ?? 0);

        return round($hours + ($minutes / 60), 2);
    }

    private function loadCostsByPayroll(Collection $payrollIds): Collection
    {
        $uniqueIds = $payrollIds->filter()->unique()->values();

        if ($uniqueIds->isEmpty()) {
            return collect();
        }

        return Payroll::whereIn('id', $uniqueIds)
            ->with('extraHourCosts')
            ->get()
            ->mapWithKeys(fn ($p) => [$p->id => $p->extraHourCosts]);
    }

    /**
     * Expande la serie mensual agregada para incluir TODOS los meses del rango
     * consultado (o del rango cubierto por los datos), rellenando ceros.
     */
    private function buildMonthlySeries(array $monthly, ?CarbonInterface $startDate, ?CarbonInterface $endDate): array
    {
        ksort($monthly);

        if (empty($monthly)) {
            return [];
        }

        $keys = array_keys($monthly);
        $first = $startDate?->copy() ?? Carbon::createFromFormat('Y-m-d', $keys[0].'-01');
        $last = $endDate?->copy() ?? Carbon::createFromFormat('Y-m-d', end($keys).'-01');

        $series = [];
        $cursor = $first->copy()->startOfMonth();
        $stop = $last->copy()->startOfMonth();

        while ($cursor->lte($stop)) {
            $key = $cursor->format('Y-m');
            $base = $monthly[$key] ?? [
                'month' => $key,
                'internal_hours' => 0.0,
                'internal_cost' => 0.0,
                'external_hours' => 0.0,
                'external_cost' => 0.0,
            ];

            $series[] = [
                'month' => $key,
                'internal_hours' => round($base['internal_hours'] ?? 0.0, 2),
                'internal_cost' => round($base['internal_cost'] ?? 0.0, 2),
                'external_hours' => round($base['external_hours'] ?? 0.0, 2),
                'external_cost' => round($base['external_cost'] ?? 0.0, 2),
            ];
            $cursor->addMonth();
        }

        return $series;
    }
}
