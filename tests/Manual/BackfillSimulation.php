<?php

/**
 * Prueba de simulación del backfill histórico de checadas de BioTime.
 *
 * Valida la lógica de emparejamiento del comando `biotime:backfill` sin dejar
 * residuos: todo se ejecuta dentro de una transacción que se revierte al final.
 *
 * Ejecutar: php tests/Manual/BackfillSimulation.php
 */

require __DIR__ . '/../../vendor/autoload.php';

$app = require_once __DIR__ . '/../../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Console\Commands\BackfillBioTime;
use App\Models\Payroll;
use App\Models\PayrollUser;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

$command = new BackfillBioTime();
$failures = 0;
$checks = 0;

function line(string $text = ''): void
{
    echo $text . PHP_EOL;
}

function check(bool $condition, string $label): void
{
    global $failures, $checks;

    $checks++;

    if ($condition) {
        line('      OK   ' . $label);
    } else {
        $failures++;
        line('      FALLA ' . $label);
    }
}

function callPrivate(object $object, string $method, array $args = [])
{
    $reflection = new ReflectionMethod($object, $method);
    $reflection->setAccessible(true);

    return $reflection->invokeArgs($object, $args);
}

function runScenario(object $command, User $employee, string $name, array $days, array $expect): void
{
    line('-- ' . $name);

    $result = callPrivate($command, 'buildPlans', [$days, $employee, (string) $employee->code]);

    check(
        count($result) === count($expect),
        'días generados: esperado ' . count($expect) . ' / obtenido ' . count($result)
    );

    foreach ($expect as $index => $expected) {
        $actual = $result[$index] ?? null;

        if ($actual === null) {
            line('      FALLA falta el día ' . $expected['date']);
            continue;
        }

        check($actual['date'] === $expected['date'], 'fecha ' . $expected['date']);
        check(
            $actual['check_in'] === $expected['check_in'],
            'entrada ' . $expected['check_in'] . ' (obtenido ' . $actual['check_in'] . ')'
        );
        check(
            $actual['check_out'] === $expected['check_out'],
            'salida ' . ($expected['check_out'] ?? 'NULL') . ' (obtenido ' . ($actual['check_out'] ?? 'NULL') . ')'
        );
        check(
            $actual['break_start'] === $expected['break_start'],
            'comida inicio ' . ($expected['break_start'] ?? 'NULL') . ' (obtenido ' . ($actual['break_start'] ?? 'NULL') . ')'
        );
        check(
            $actual['break_end'] === $expected['break_end'],
            'comida fin ' . ($expected['break_end'] ?? 'NULL') . ' (obtenido ' . ($actual['break_end'] ?? 'NULL') . ')'
        );
    }

    line();
}

line('======================================================');
line('  SIMULACION: backfill de checadas BioTime');
line('======================================================');
line();

$weekday = Carbon::parse('2026-09-07');
while ($weekday->isWeekend()) {
    $weekday->addDay();
}

$nextDay = $weekday->copy()->addDay();
$weekendDay = $weekday->copy()->next(Carbon::SATURDAY);

line('Día hábil:      ' . $weekday->toDateString() . ' (' . $weekday->isoFormat('dddd') . ')');
line('Día siguiente:  ' . $nextDay->toDateString() . ' (' . $nextDay->isoFormat('dddd') . ')');
line('Fin de semana:  ' . $weekendDay->toDateString() . ' (' . $weekendDay->isoFormat('dddd') . ')');
line();

DB::beginTransaction();

try {
    // --- Datos de prueba (se revierten al final) ---
    $suffix = random_int(100000, 999999);

    $employee = User::create([
        'code' => 'TEST-BF-' . $suffix,
        'name' => 'Empleado Backfill ' . $suffix,
        'email' => 'backfill' . $suffix . '@adti.test',
        'password' => bcrypt('test'),
        'is_active' => true,
        'org_props' => ['work_shift' => 'Turno 3 (09:00 - 18:00)'],
    ]);

    $payroll = Payroll::create([
        'start_date' => $weekday->copy()->subDays(3)->toDateString(),
        'biweekly' => 1,
        'is_active' => false,
    ]);

    line('Empleado de prueba:   ' . $employee->name . ' (code ' . $employee->code . ')');
    line('Catorcena de prueba:  #' . $payroll->id . ' desde ' . $payroll->start_date->toDateString());
    line();

    $create = fn (array $times) => [
        'state' => 'create',
        'times' => $times,
        'payroll_id' => $payroll->id,
        'payroll_start' => $payroll->start_date->toDateString(),
    ];

    $day1 = $weekday->toDateString();
    $day2 = $nextDay->toDateString();
    $weekend = $weekendDay->toDateString();

    runScenario($command, $employee, 'Entrada y salida normales', [
        $day1 => $create(['08:55', '18:05']),
    ], [
        ['date' => $day1, 'check_in' => '08:55', 'check_out' => '18:05', 'break_start' => null, 'break_end' => null],
    ]);

    runScenario($command, $employee, 'Con comida (4 checadas)', [
        $day1 => $create(['08:55', '14:00', '14:45', '18:05']),
    ], [
        ['date' => $day1, 'check_in' => '08:55', 'check_out' => '18:05', 'break_start' => '14:00', 'break_end' => '14:45'],
    ]);

    runScenario($command, $employee, '3 checadas (olvidó checar el regreso de comida)', [
        $day1 => $create(['08:55', '14:00', '18:05']),
    ], [
        ['date' => $day1, 'check_in' => '08:55', 'check_out' => '18:05', 'break_start' => '14:00', 'break_end' => '14:00'],
    ]);

    runScenario($command, $employee, 'Una sola checada (queda sin salida)', [
        $day1 => $create(['08:55']),
    ], [
        ['date' => $day1, 'check_in' => '08:55', 'check_out' => null, 'break_start' => null, 'break_end' => null],
    ]);

    runScenario($command, $employee, 'Fin de semana', [
        $weekend => $create(['08:00', '12:00', '13:00', '16:00']),
    ], [
        ['date' => $weekend, 'check_in' => '08:00', 'check_out' => '16:00', 'break_start' => '12:00', 'break_end' => '13:00'],
    ]);

    runScenario($command, $employee, 'Turno nocturno: la madrugada cierra el día anterior', [
        $day1 => $create(['19:05']),
        $day2 => $create(['00:30', '01:00', '06:10']),
    ], [
        ['date' => $day1, 'check_in' => '19:05', 'check_out' => '06:10', 'break_start' => '00:30', 'break_end' => '01:00'],
    ]);

    runScenario($command, $employee, 'Día que ya existe en el ERP (no se toca)', [
        $day1 => ['state' => 'skip', 'times' => ['08:55', '18:05']],
    ], []);

    runScenario($command, $employee, 'Anti-ráfaga: duplicados del reloj', [
        $day1 => $create(['08:55', '08:56', '14:00', '14:02', '18:05']),
    ], [
        ['date' => $day1, 'check_in' => '08:55', 'check_out' => '18:05', 'break_start' => '14:00', 'break_end' => '14:00'],
    ]);

    line('-- Anti-ráfaga: función dedupeTimes');
    $deduped = callPrivate($command, 'dedupeTimes', [['08:55', '08:56', '08:55', '14:00', '14:02', '18:05']]);
    check($deduped === ['08:55', '14:00', '18:05'], 'duplicados eliminados: ' . implode(', ', $deduped));
    line();

    line('-- Resolución de catorcena');
    $resolved = callPrivate($command, 'resolvePayroll', [$day1]);
    check($resolved !== null && $resolved->id === $payroll->id, 'encontró la catorcena #' . $payroll->id);

    $outside = callPrivate($command, 'resolvePayroll', [$weekday->copy()->addMonths(3)->toDateString()]);
    check($outside === null, 'devuelve NULL cuando ninguna catorcena cubre la fecha');
    line();

    line('-- Escritura real en payroll_user (se revierte al final)');
    $entry = callPrivate($command, 'writeDay', [[
        'emp_code' => (string) $employee->code,
        'employee_id' => $employee->id,
        'date' => $day1,
        'payroll_id' => $payroll->id,
        'check_in' => '08:55',
        'check_out' => '18:05',
        'break_start' => '14:00',
        'break_end' => '14:45',
    ]]);

    check(substr((string) $entry->check_in, 0, 5) === '08:55', 'check_in persistido: ' . $entry->check_in);
    check(substr((string) $entry->check_out, 0, 5) === '18:05', 'check_out persistido: ' . $entry->check_out);
    check(substr((string) $entry->break_start, 0, 5) === '14:00', 'break_start persistido: ' . $entry->break_start);
    check(substr((string) $entry->break_end, 0, 5) === '14:45', 'break_end persistido: ' . $entry->break_end);
    check((int) $entry->break_minutes === 45, 'break_minutes = 45 (obtenido ' . $entry->break_minutes . ')');
    check((int) $entry->late === 0, 'retardo 0 min con entrada 08:55 y turno 09:00 (obtenido ' . $entry->late . ')');
    check(
        (int) $entry->extra_minutes === 10,
        'tiempo extra 10 min (5 por entrar 08:55 antes de las 09:00 + 5 por salir 18:05 después de las 18:00), obtenido ' . $entry->extra_minutes
    );

    $persisted = PayrollUser::where('user_id', $employee->id)->whereDate('date', $day1)->first();
    check($persisted !== null && $persisted->id === $entry->id, 'el registro quedó guardado en payroll_user');
    line();
} catch (Throwable $e) {
    line('EXCEPCION: ' . $e->getMessage() . ' (' . basename($e->getFile()) . ':' . $e->getLine() . ')');
    $failures++;
} finally {
    DB::rollBack();
    line('Transaccion revertida: no quedaron datos de prueba.');
}

line();
line('======================================================');
line('  Comprobaciones correctas: ' . ($checks - $failures) . '/' . $checks);

if ($failures === 0) {
    line('  RESULTADO: TODO OK');
} else {
    line('  RESULTADO: ' . $failures . ' FALLA(S)');
}
line('======================================================');

exit($failures === 0 ? 0 : 1);
