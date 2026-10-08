<?php

/**
 * Prueba del endpoint POST /api/biotime-punches (checadas que envía el script
 * de consola desde la PC del reloj).
 *
 * Simula APP_ENV=production para validar la seguridad y usa una transacción que
 * se revierte al final (no deja datos de prueba).
 *
 * Ejecutar: php tests/Manual/BackfillApiCheck.php
 */

require __DIR__ . '/../../vendor/autoload.php';

$app = require_once __DIR__ . '/../../bootstrap/app.php';
$kernel = $app->make(\Illuminate\Contracts\Http\Kernel::class);
$kernel->bootstrap();

use App\Models\Payroll;
use App\Models\PayrollUser;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

$failures = 0;
$checks = [];

echo '======================================================' . PHP_EOL;
echo '  ENDPOINT API  POST /api/biotime-punches' . PHP_EOL;
echo '======================================================' . PHP_EOL . PHP_EOL;

// Simular produccion y una llave conocida
$app->detectEnvironment(fn () => 'production');
config()->set('services.biotime.backfill_key', 'llave-de-prueba');

DB::beginTransaction();

try {
    $employee = User::whereNotNull('code')->where('is_active', true)->first();

    if (! $employee) {
        echo 'ERROR: no hay empleados activos con codigo en la base de datos.' . PHP_EOL;
        exit(1);
    }

    $monday = Carbon::parse('2026-09-07');
    while ($monday->isWeekend()) {
        $monday->addDay();
    }

    $date = $monday->toDateString();

    $payroll = Payroll::create([
        'start_date' => $monday->copy()->subDays(3)->toDateString(),
        'biweekly' => 1,
        'is_active' => false,
    ]);

    echo 'Empleado de prueba: ' . $employee->name . ' (code ' . $employee->code . ')' . PHP_EOL;
    echo 'Dia simulado:       ' . $date . ' (' . $monday->isoFormat('dddd') . ')' . PHP_EOL;
    echo 'Catorcena de prueba: #' . $payroll->id . PHP_EOL . PHP_EOL;

    $payload = [
        'emp_codes' => [(string) $employee->code],
        'from' => $date,
        'to' => $date,
        'apply' => false,
        'punches' => [
            ['emp_code' => (string) $employee->code, 'punch_time' => $date . ' 08:55:12'],
            ['emp_code' => (string) $employee->code, 'punch_time' => $date . ' 18:05:40'],
        ],
    ];

    $post = function (array $body, ?string $key) use ($kernel): array {
        $server = ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'];

        if ($key !== null) {
            $server['HTTP_X_BACKFILL_KEY'] = $key;
        }

        $request = Request::create('/api/biotime-punches', 'POST', [], [], [], $server, json_encode($body));
        $response = $kernel->handle($request);
        $result = [
            $response->getStatusCode(),
            (string) $response->getContent(),
            (string) $response->headers->get('X-Backfill-Exit'),
        ];
        $kernel->terminate($request, $response);

        return $result;
    };

    $rowsOfDay = fn () => PayrollUser::where('user_id', $employee->id)->whereDate('date', $date)->count();

    $runStep = function (string $label, array $body, ?string $key = 'llave-de-prueba') use ($post, $rowsOfDay): array {
        $before = $rowsOfDay();
        [$status, $text] = $post($body, $key);
        $after = $rowsOfDay();

        $summary = '';

        foreach (explode(PHP_EOL, (string) $text) as $line) {
            foreach (['días nuevos que crear', 'DÍAS OMITIDOS', 'Días creados', 'días creados:', 'Error', 'ERROR'] as $needle) {
                if (str_contains($line, $needle)) {
                    $summary .= trim($line) . ' || ';
                    break;
                }
            }
        }

        echo sprintf('  [%s] HTTP %s | filas %d -> %d', $label, $status, $before, $after) . PHP_EOL;
        echo '        ' . ($summary !== '' ? $summary : '(sin resumen)') . PHP_EOL;

        return [$status, $text];
    };

    // 1. Sin llave -> 403
    [$status] = $post($payload, null);
    $checks['sin llave responde 403'] = $status === 403;

    // 2. Llave incorrecta -> 403
    [$status] = $post($payload, 'llave-mala');
    $checks['llave incorrecta responde 403'] = $status === 403;

    // 3. Payload incompleto -> 422
    [$status] = $post(['emp_codes' => ['1'], 'from' => '2026-09-01'], 'llave-de-prueba');
    $checks['payload incompleto responde 422'] = $status === 422;

    // 4. Llave correcta, dry-run
    [$status, $body] = $runStep('dry-run', $payload);
    $checks['con llave correcta responde 200'] = $status === 200;
    $checks['dry-run no escribe en payroll_user'] = $rowsOfDay() === 0;
    $checks['el reporte muestra el modo reporte'] = str_contains($body, 'REPORTE (dry-run');
    $checks['el reporte lista el día a crear'] = str_contains($body, $date);
    $checks['el reporte muestra la entrada 08:55'] = str_contains($body, '08:55');

    // 5. Aplicar
    $payloadApply = $payload;
    $payloadApply['apply'] = true;
    [$status] = $runStep('apply=true', $payloadApply);

    $row = PayrollUser::where('user_id', $employee->id)->whereDate('date', $date)->first();

    $checks['apply=true responde 200'] = $status === 200;
    $checks['se creó el registro del día'] = $row !== null;
    $checks['check_in = 08:55'] = $row && substr((string) $row->check_in, 0, 5) === '08:55';
    $checks['check_out = 18:05'] = $row && substr((string) $row->check_out, 0, 5) === '18:05';
    $checks['quedó en la catorcena correcta'] = $row && $row->payroll_id === $payroll->id;

    // 6. Reenviar el mismo payload: el día ya existe, NO se toca
    [, $body2] = $runStep('reenvío', $payloadApply);
    $checks['reenviar no duplica el registro'] = $rowsOfDay() === 1;
    $checks['reporta el día como omitido'] = str_contains($body2, 'YA TIENEN REGISTRO');

    // 7. Tolerancia: emp_codes como texto y una sola checada sin arreglo
    $loose = [
        'emp_codes' => (string) $employee->code,
        'from' => $date,
        'to' => $date,
        'punches' => ['emp_code' => (string) $employee->code, 'punch_time' => $date . ' 09:00:00'],
    ];
    [$status] = $runStep('formato suelto (dry-run)', $loose);
    $checks['acepta emp_codes como texto y checada suelta'] = $status === 200;
    $checks['el formato suelto no duplica'] = $rowsOfDay() === 1;
} catch (Throwable $e) {
    echo 'EXCEPCION: ' . $e->getMessage() . ' (' . basename($e->getFile()) . ':' . $e->getLine() . ')' . PHP_EOL;
    $failures++;
} finally {
    DB::rollBack();
    echo PHP_EOL . 'Transaccion revertida: no quedaron datos de prueba.' . PHP_EOL;
}

foreach ($checks as $label => $ok) {
    echo ($ok ? '  OK    ' : '  FALLA ') . $label . PHP_EOL;

    if (! $ok) {
        $failures++;
    }
}

echo PHP_EOL . '======================================================' . PHP_EOL;
echo '  Resultado: ' . ($failures === 0 ? 'TODO OK' : $failures . ' FALLA(S)') . PHP_EOL;
echo '======================================================' . PHP_EOL;

exit($failures === 0 ? 0 : 1);
