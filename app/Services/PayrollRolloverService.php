<?php

namespace App\Services;

use App\Models\ExtraHourApprovalGroup;
use App\Models\ExtraHourApprovalLevel;
use App\Models\Payroll;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Cierre de catorcena y apertura de la siguiente, en un solo lugar.
 *
 * Lo usan:
 *  - `payrolls:close` (cron de los martes), y
 *  - `PayrollUserController::processBioTimeTransaction()` para el AUTO-ROLL:
 *    si llega una checada posterior al último día de la catorcena activa (porque
 *    el cron no corrió), se abre automáticamente la catorcena siguiente con la
 *    misma lógica del cierre, en vez de estampar el día en la catorcena vencida.
 *
 * El cierre arrastra la jerarquía de autorización de tiempo extra y re-liga las
 * asistencias que ya hubieran llegado a la ventana del período nuevo.
 */
class PayrollRolloverService
{
    /**
     * Tope de catorcenas a abrir de golpe por una checada muy adelantada
     * (26 catorcenas ≈ un año). Evita bucles si el reloj manda una fecha absurda.
     */
    private const MAX_PERIODS_PER_ROLL = 26;

    public function __construct(private PayrollAttendanceReassignment $reassignment)
    {
    }

    /**
     * Catorcena cuya ventana de 14 días contiene la fecha indicada.
     *
     * Se compara con whereDate (no con `<=`/`>=` sobre el valor crudo) para que
     * funcione igual con columnas DATE de MySQL y con los valores con hora que
     * guarda SQLite en pruebas.
     */
    public function findByDate($date): ?Payroll
    {
        $day = Carbon::parse($date)->startOfDay();
        $earliestStart = $day->copy()->subDays(PayrollAttendanceReassignment::PERIOD_DAYS - 1);

        return Payroll::whereDate('start_date', '<=', $day->toDateString())
            ->whereDate('start_date', '>=', $earliestStart->toDateString())
            ->orderByDesc('start_date')
            ->first();
    }

    /**
     * Último día de la ventana de la catorcena.
     */
    public function windowEnd(Payroll $payroll): Carbon
    {
        return $this->reassignment->windowEnd($payroll);
    }

    /**
     * Cierra la catorcena indicada (por defecto la activa) y abre la siguiente.
     *
     * @return array{closed: Payroll, opened: Payroll, groups: int, moved: int, levels_remapped: int, levels_lost: int}
     *
     * @throws RuntimeException cuando no hay catorcena activa que cerrar.
     */
    public function closeAndOpenNext(?Payroll $current = null): array
    {
        return DB::transaction(function () use ($current) {
            $current = $current
                ? Payroll::whereKey($current->getKey())->lockForUpdate()->first()
                : Payroll::where('is_active', true)->orderByDesc('start_date')->lockForUpdate()->first();

            if (!$current) {
                throw new RuntimeException('No hay una catorcena activa para cerrar. Revisa la tabla payrolls antes de volver a intentarlo.');
            }

            return $this->doCloseAndOpen($current);
        });
    }

    /**
     * Resuelve la catorcena destino de una checada.
     *
     * 1. Si alguna catorcena contiene la fecha, esa manda.
     * 2. Si la fecha ya pasó del último día de la catorcena activa, se abre(n)
     *    automáticamente la(s) siguiente(s) (AUTO-ROLL) reutilizando el cierre.
     * 3. Si no hay catorcena activa, o la fecha es anterior a su inicio (checada
     *    nocturna que cierra un turno del día previo), se conserva el respaldo
     *    histórico a la catorcena activa.
     */
    public function ensurePeriodForDate($date): ?Payroll
    {
        $punchDate = Carbon::parse($date)->startOfDay();

        if ($found = $this->findByDate($punchDate)) {
            return $found;
        }

        $active = Payroll::where('is_active', true)->orderByDesc('start_date')->first();

        if (!$active) {
            return null;
        }

        if (!$punchDate->greaterThan($this->windowEnd($active))) {
            // La fecha es anterior al inicio de la catorcena activa: no hay nada
            // que abrir. Se mantiene el respaldo previo (la catorcena activa).
            Log::warning('Checada fuera de la ventana de la catorcena activa; se usa la catorcena activa como respaldo', [
                'fecha_checada' => $punchDate->toDateString(),
                'payroll_id' => $active->id,
                'ventana_inicio' => Carbon::parse($active->start_date)->toDateString(),
                'ventana_fin' => $this->windowEnd($active)->toDateString(),
            ]);

            return $active;
        }

        return $this->openPeriodsUntil($punchDate);
    }

    /**
     * Abre las catorcenas necesarias para que $targetDate quede dentro de una
     * ventana. Se ejecuta en transacción con bloqueo para que dos checadas
     * simultáneas no creen catorcenas duplicadas.
     */
    private function openPeriodsUntil(Carbon $targetDate): ?Payroll
    {
        return DB::transaction(function () use ($targetDate) {
            $active = Payroll::where('is_active', true)->orderByDesc('start_date')->lockForUpdate()->first();

            if (!$active) {
                return null;
            }

            // Otra checada concurrente pudo haber abierto ya la catorcena correcta.
            if ($found = $this->findByDate($targetDate)) {
                return $found;
            }

            $opened = 0;
            $last = null;

            while ($targetDate->greaterThan($this->windowEnd($active)) && $opened < self::MAX_PERIODS_PER_ROLL) {
                $last = $this->doCloseAndOpen($active);
                $active = $last['opened'];
                $opened++;
            }

            $owner = $this->findByDate($targetDate);

            Log::warning('Auto-roll de catorcena: checada recibida después del fin de la catorcena activa', [
                'fecha_checada' => $targetDate->toDateString(),
                'catorcenas_abiertas' => $opened,
                'payroll_nueva' => $last['opened']->id ?? null,
                'payroll_destino' => $owner?->id,
            ]);

            if (!$owner) {
                Log::error('Auto-roll sin catorcena destino: no se pudo ubicar la checada en ninguna ventana', [
                    'fecha_checada' => $targetDate->toDateString(),
                    'catorcenas_abiertas' => $opened,
                ]);
            }

            return $owner;
        });
    }

    /**
     * Crea la catorcena siguiente, copia la jerarquía, desactiva la anterior y
     * re-liga las asistencias que ya llegaron a la ventana nueva.
     *
     * @return array{closed: Payroll, opened: Payroll, groups: int, moved: int, levels_remapped: int, levels_lost: int}
     */
    private function doCloseAndOpen(Payroll $current): array
    {
        $newPayroll = Payroll::create([
            'start_date' => $this->windowEnd($current)->copy()->addDay()->toDateString(),
            'biweekly' => $current->biweekly + 1,
        ]);

        $copied = $this->copyApprovalGroups($current, $newPayroll);

        $current->update(['is_active' => 0]);

        $reassigned = $this->reassignment->reassignTo($newPayroll, $copied['levels']);

        Log::info('Catorcena cerrada y creada una nueva', [
            'payroll_cerrada' => $current->id,
            'payroll_nueva' => $newPayroll->id,
            'grupos_copiados' => $copied['groups'],
            'registros_reasignados' => $reassigned['moved'],
            'niveles_remapados' => $reassigned['levels_remapped'],
            'niveles_sin_equivalente' => $reassigned['levels_lost'],
        ]);

        return [
            'closed' => $current,
            'opened' => $newPayroll,
            'groups' => $copied['groups'],
            'moved' => $reassigned['moved'],
            'levels_remapped' => $reassigned['levels_remapped'],
            'levels_lost' => $reassigned['levels_lost'],
        ];
    }

    /**
     * Copia grupos + niveles + aprobadores de una catorcena a otra.
     *
     * @return array{groups: int, levels: array<int, int>} Grupos copiados y mapa
     *                                                     [nivel_viejo => nivel_nuevo].
     */
    private function copyApprovalGroups(Payroll $from, Payroll $to): array
    {
        $groups = $from->approvalGroups()->with(['employees', 'levels.approvers'])->orderBy('id')->get();

        $levelMap = [];

        foreach ($groups as $group) {
            $newGroup = ExtraHourApprovalGroup::create([
                'payroll_id' => $to->id,
                'name' => $group->name,
            ]);

            $newGroup->employees()->sync($group->employees->pluck('id'));

            foreach ($group->levels as $level) {
                $newLevel = ExtraHourApprovalLevel::create([
                    'payroll_id' => $to->id,
                    'approval_group_id' => $newGroup->id,
                    'level' => $level->level,
                    'name' => $level->name,
                ]);

                $newLevel->approvers()->sync($level->approvers->pluck('id'));

                $levelMap[$level->id] = $newLevel->id;
            }
        }

        return ['groups' => $groups->count(), 'levels' => $levelMap];
    }
}
