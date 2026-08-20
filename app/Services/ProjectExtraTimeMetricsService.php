<?php

namespace App\Services;

use App\Models\Payroll;
use App\Models\PayrollUser;
use App\Models\Project;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Métricas de costo y horas extra para proyectos.
 *
 * Fuente de verdad: registros de PayrollUser con tiempo extra APROBADO
 * (extra_hour_status = 'approved'), usando approved_extra_hours/minutes
 * como horas efectivas y el costo por hora de la catorcena del registro.
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
     * }
     */
    public function forProject(Project $project, ?CarbonInterface $startDate = null, ?CarbonInterface $endDate = null): array
    {
        $records = $this->baseRecordsQuery()
            ->where('project_id', $project->id)
            ->when($startDate && $endDate, fn ($q) => $q->whereBetween('date', [
                $startDate->toDateString(),
                $endDate->toDateString(),
            ]))
            ->with('user:id,name,profile_photo_path')
            ->get();

        return $this->buildMetrics($records);
    }

    /**
     * Métricas globales: ranking de proyectos, ranking de empleados
     * (por horas y por monto) y totales globales.
     *
     * @return array{
     *     projects_ranking: Collection,
     *     employees_ranking: Collection,
     *     employees_ranking_by_cost: Collection,
     *     total_extra_hours: float,
     *     total_cost: float,
     * }
     */
    public function globalMetrics(?CarbonInterface $startDate = null, ?CarbonInterface $endDate = null): array
    {
        $records = $this->baseRecordsQuery()
            ->with(['project:id,name,client', 'user:id,name,profile_photo_path'])
            ->when($startDate && $endDate, fn ($q) => $q->whereBetween('date', [
                $startDate->toDateString(),
                $endDate->toDateString(),
            ]))
            ->get();

        $costsByPayroll = $this->loadCostsByPayroll($records->pluck('payroll_id'));

        $globalHours = 0.0;
        $globalCost = 0.0;
        $projectTotals = [];
        $employeeTotals = [];

        foreach ($records as $record) {
            $hours = $this->approvedHours($record);
            if ($hours <= 0) {
                continue;
            }

            $costPerHour = $this->costResolver->resolve(
                $record->date,
                (int) $record->user_id,
                $costsByPayroll->get($record->payroll_id, collect())
            );
            $cost = round($hours * $costPerHour, 2);

            $globalHours += $hours;
            $globalCost += $cost;

            // Proyectos (solo registros vinculados a un proyecto)
            if ($record->project_id && $record->project) {
                $projectTotals[$record->project_id] ??= [
                    'project' => [
                        'id' => $record->project->id,
                        'name' => $record->project->name,
                        'client' => $record->project->client,
                    ],
                    'total_extra_hours' => 0.0,
                    'total_cost' => 0.0,
                ];
                $projectTotals[$record->project_id]['total_extra_hours'] += $hours;
                $projectTotals[$record->project_id]['total_cost'] += $cost;
            }

            // Empleados (todo el sistema)
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

        $projectsRanking = collect($projectTotals)
            ->map(fn ($item) => [
                'project' => $item['project'],
                'total_extra_hours' => round($item['total_extra_hours'], 2),
                'total_cost' => round($item['total_cost'], 2),
            ])
            ->sortByDesc('total_cost')
            ->values();

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

        return [
            'projects_ranking' => $projectsRanking,
            'employees_ranking' => $employeesRanking,
            'employees_ranking_by_cost' => $employeesRankingByCost,
            'total_extra_hours' => round($globalHours, 2),
            'total_cost' => round($globalCost, 2),
        ];
    }

    // ─── Internos ──────────────────────────────────────────────

    private function baseRecordsQuery()
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

    private function buildMetrics(Collection $records): array
    {
        $costsByPayroll = $this->loadCostsByPayroll($records->pluck('payroll_id'));

        $daily = $records
            ->map(function ($record) use ($costsByPayroll) {
                $hours = $this->approvedHours($record);
                if ($hours <= 0) {
                    return null;
                }

                $costPerHour = $this->costResolver->resolve(
                    $record->date,
                    (int) $record->user_id,
                    $costsByPayroll->get($record->payroll_id, collect())
                );

                return [
                    'user' => [
                        'id' => $record->user->id,
                        'name' => $record->user->name,
                        'profile_photo_url' => $record->user->profile_photo_url,
                    ],
                    'date' => $record->date->toDateString(),
                    'cost_per_hour' => $costPerHour,
                    'hours' => $hours,
                    'amount' => round($hours * $costPerHour, 2),
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
        ];
    }
}