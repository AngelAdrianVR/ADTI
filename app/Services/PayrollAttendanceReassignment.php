<?php

namespace App\Services;

use App\Models\ExtraHourApprovalDecision;
use App\Models\ExtraHourApprovalLevel;
use App\Models\Payroll;
use App\Models\PayrollUser;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Re-liga los registros de asistencia (payroll_user) a la catorcena que
 * realmente contiene su fecha.
 *
 * Contexto del problema que resuelve: cuando el cron `payrolls:close` no corre
 * a tiempo, la sincronización de BioTime no encuentra catorcena para la fecha
 * de la checada y cae al respaldo `Payroll::firstWhere('is_active', true)`, así
 * que los días del período nuevo se estampan en la catorcena vencida. Esos
 * registros quedan "huérfanos": la pantalla de la catorcena nueva los ignora
 * porque filtra por `payroll_id` y el día se ve como falta injustificada.
 *
 * Como los períodos son de 14 días y NO se traslapan, comparar la fecha contra
 * la ventana de cada catorcena basta para decidir a dónde pertenece cada uno.
 */
class PayrollAttendanceReassignment
{
    /** Días que abarca una catorcena (inclusive el día de inicio). */
    public const PERIOD_DAYS = 14;

    /**
     * Primer día de la catorcena.
     */
    public function windowStart(Payroll $payroll): Carbon
    {
        return Carbon::parse($payroll->start_date)->startOfDay();
    }

    /**
     * Último día de la catorcena (14 días contando el de inicio).
     */
    public function windowEnd(Payroll $payroll): Carbon
    {
        return $this->windowStart($payroll)->copy()->addDays(self::PERIOD_DAYS - 1);
    }

    /**
     * Registros cuya fecha cae dentro de la ventana de $payroll pero quedaron
     * guardados en otra catorcena.
     */
    public function orphansFor(Payroll $payroll): Builder
    {
        return PayrollUser::query()
            ->whereDate('date', '>=', $this->windowStart($payroll)->toDateString())
            ->whereDate('date', '<=', $this->windowEnd($payroll)->toDateString())
            ->where('payroll_id', '!=', $payroll->id)
            ->orderBy('date')
            ->orderBy('user_id');
    }

    /**
     * Mueve a $payroll los registros que le corresponden por fecha y remapea su
     * nivel de aprobación al equivalente de la catorcena destino.
     *
     * @param  array<int, int>  $levelMap  [nivel_viejo => nivel_nuevo] ya conocido
     *                                     (lo devuelve el copiado de jerarquía del cierre).
     * @return array{moved: int, levels_remapped: int, levels_lost: int}
     */
    public function reassignTo(Payroll $payroll, array $levelMap = []): array
    {
        $rows = $this->orphansFor($payroll)->get(['id', 'payroll_id', 'date', 'current_approval_level_id']);

        if ($rows->isEmpty()) {
            return ['moved' => 0, 'levels_remapped' => 0, 'levels_lost' => 0];
        }

        $oldLevelIds = $rows->pluck('current_approval_level_id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        // El mapa explícito (cierre) gana; lo demás se resuelve por grupo + nivel.
        $map = $levelMap + $this->resolveLevelMap($payroll, $oldLevelIds);

        $ids = $rows->pluck('id')->all();
        $remapped = 0;
        $lost = 0;

        DB::transaction(function () use ($rows, $ids, $map, $payroll, &$remapped, &$lost) {
            // 1. Decisiones de tiempo extra: siguen al mismo nivel lógico del
            //    período nuevo (si había aprobaciones en curso, no se pierden).
            foreach ($map as $oldLevelId => $newLevelId) {
                if ((int) $oldLevelId === (int) $newLevelId) {
                    continue;
                }

                ExtraHourApprovalDecision::whereIn('payroll_user_id', $ids)
                    ->where('approval_level_id', $oldLevelId)
                    ->update([
                        'approval_level_id' => $newLevelId,
                        'updated_at' => now(),
                    ]);
            }

            // 2. Asistencias: un UPDATE por nivel de origen en vez de uno por día.
            $grouped = $rows->groupBy(
                fn ($row) => $row->current_approval_level_id === null
                    ? 'null'
                    : (string) $row->current_approval_level_id
            );

            foreach ($grouped as $oldLevel => $group) {
                if ($oldLevel === 'null') {
                    $newLevelId = null;
                } elseif (array_key_exists((int) $oldLevel, $map)) {
                    $newLevelId = $map[(int) $oldLevel];
                    $remapped += $group->count();
                } else {
                    // Sin equivalente (la catorcena destino no tiene ese grupo/nivel):
                    // el día se mueve igual, pero queda "sin flujo de autorización".
                    $newLevelId = null;
                    $lost += $group->count();
                }

                PayrollUser::whereIn('id', $group->pluck('id')->all())->update([
                    'payroll_id' => $payroll->id,
                    'current_approval_level_id' => $newLevelId,
                    'updated_at' => now(),
                ]);
            }
        });

        Log::info('Asistencias re-ligadas a su catorcena', [
            'payroll_id' => $payroll->id,
            'biweekly' => $payroll->biweekly,
            'moved' => $rows->count(),
            'levels_remapped' => $remapped,
            'levels_lost' => $lost,
            'payrolls_origen' => $rows->pluck('payroll_id')->unique()->values()->all(),
        ]);

        if ($lost > 0) {
            Log::warning('Asistencias re-ligadas sin nivel de aprobación equivalente en la catorcena destino', [
                'payroll_id' => $payroll->id,
                'registros' => $lost,
            ]);
        }

        return [
            'moved' => $rows->count(),
            'levels_remapped' => $remapped,
            'levels_lost' => $lost,
        ];
    }

    /**
     * Empareja los niveles de la catorcena de origen con los de la destino
     * usando nombre de grupo + número de nivel (así los copia el cierre).
     *
     * @param  array<int, int>  $oldLevelIds
     * @return array<int, int>
     */
    public function resolveLevelMap(Payroll $payroll, array $oldLevelIds): array
    {
        if (empty($oldLevelIds)) {
            return [];
        }

        $oldLevels = ExtraHourApprovalLevel::with('group:id,payroll_id,name')
            ->whereIn('id', $oldLevelIds)
            ->where('payroll_id', '!=', $payroll->id)
            ->get();

        if ($oldLevels->isEmpty()) {
            return [];
        }

        $targetLevels = ExtraHourApprovalLevel::with('group:id,payroll_id,name')
            ->where('payroll_id', $payroll->id)
            ->get()
            ->keyBy(fn (ExtraHourApprovalLevel $level) => $this->levelKey($level->group?->name, $level->level));

        $map = [];

        foreach ($oldLevels as $oldLevel) {
            $target = $targetLevels->get($this->levelKey($oldLevel->group?->name, $oldLevel->level));

            if ($target) {
                $map[$oldLevel->id] = $target->id;
            }
        }

        return $map;
    }

    private function levelKey(?string $groupName, ?int $level): string
    {
        return mb_strtoupper(trim((string) $groupName)) . '|' . (int) $level;
    }
}
