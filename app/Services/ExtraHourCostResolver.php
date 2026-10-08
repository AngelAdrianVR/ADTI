<?php

namespace App\Services;

use App\Models\PayrollUser;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Resuelve el costo por hora de tiempo extra para un registro de nómina
 * a partir de la configuración de ExtraHourCost.
 *
 * Prioridad de búsqueda:
 * 1. Costo específico del usuario + día de la semana
 * 2. Costo por rango (weekday/weekend) del usuario
 * 3. Costo general específico del día de la semana
 * 4. Costo general por rango (weekday/weekend)
 */
class ExtraHourCostResolver
{
    /**
     * @param  CarbonInterface|string  $dateObj  Fecha del registro
     * @param  int|null  $userId  ID del usuario (null = costo general)
     * @param  Collection  $extraHourCosts  Colección de modelos ExtraHourCost
     */
    public function resolve(CarbonInterface|string $dateObj, ?int $userId, Collection $extraHourCosts): float
    {
        if ($extraHourCosts->isEmpty()) {
            return 0;
        }

        $dayOfWeek = $dateObj instanceof CarbonInterface
            ? $dateObj->dayOfWeek
            : \Carbon\Carbon::parse($dateObj)->dayOfWeek; // 0=Dom, 6=Sáb

        $isWeekend = ($dayOfWeek === 0 || $dayOfWeek === 6);
        $rangeType = $isWeekend ? 'weekend' : 'weekday';

        // 1. Costo específico para ESTE usuario + día
        $cost = $extraHourCosts->first(fn ($c) =>
            $c->user_id === $userId && $c->range_type === 'specific' && $c->day_of_week === $dayOfWeek
        );

        // 2. Costo por rango para ESTE usuario
        if (!$cost) {
            $cost = $extraHourCosts->first(fn ($c) =>
                $c->user_id === $userId && $c->range_type === $rangeType
            );
        }

        // 3. Costo general específico del día
        if (!$cost) {
            $cost = $extraHourCosts->first(fn ($c) =>
                $c->user_id === null && $c->range_type === 'specific' && $c->day_of_week === $dayOfWeek
            );
        }

        // 4. Costo general por rango
        if (!$cost) {
            $cost = $extraHourCosts->first(fn ($c) =>
                $c->user_id === null && $c->range_type === $rangeType
            );
        }

        return $cost ? (float) $cost->cost_per_hour : 0;
    }

    /**
     * Conveniencia: resuelve el costo para un PayrollUser concreto.
     */
    public function resolveForPayrollUser(PayrollUser $payrollUser, Collection $extraHourCosts): float
    {
        return $this->resolve($payrollUser->date, (int) $payrollUser->user_id, $extraHourCosts);
    }
}