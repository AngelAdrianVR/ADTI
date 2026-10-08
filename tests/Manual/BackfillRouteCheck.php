<?php

/**
 * Verifica el endpoint web /backfill-biotime (reporte dry-run) sin levantar servidor.
 *
 * Ejecutar: php tests/Manual/BackfillRouteCheck.php
 */

require __DIR__ . '/../../vendor/autoload.php';

$app = require_once __DIR__ . '/../../bootstrap/app.php';
$kernel = $app->make(\Illuminate\Contracts\Http\Kernel::class);
$kernel->bootstrap();

use App\Models\PayrollUser;
use Illuminate\Http\Request;

$failures = 0;
$checks = [];

echo '======================================================' . PHP_EOL;
echo '  ENDPOINT WEB  /backfill-biotime' . PHP_EOL;
echo '======================================================' . PHP_EOL . PHP_EOL;

// --- 1. Reporte (dry-run) ---
$before = PayrollUser::count();

$request = Request::create('/backfill-biotime?emp=63,64,65&from=2026-09-01&to=2026-09-21', 'GET');
$response = $kernel->handle($request);
$html = (string) $response->getContent();

$after = PayrollUser::count();

echo 'HTTP ' . $response->getStatusCode() . ' (reporte dry-run)' . PHP_EOL . PHP_EOL;

$checks['responde 200'] = $response->getStatusCode() === 200;
$checks['marca "SOLO REPORTE (no escribió nada)"'] = str_contains($html, 'SOLO REPORTE');
$checks['incluye el enlace para aplicar (confirm=SI)'] = str_contains($html, 'confirm=SI');
$checks['incluye la salida del comando'] = str_contains($html, 'Backfill de checadas BioTime');
$checks['incluye el comando equivalente para terminal'] = str_contains($html, 'php artisan biotime:backfill --emp=63,64,65');
$checks['no modificó payroll_user (' . $before . ' registros)'] = $before === $after;

$kernel->terminate($request, $response);

// --- 2. apply=1 sin confirm=SI ---
$request2 = Request::create('/backfill-biotime?emp=63,64,65&from=2026-09-01&to=2026-09-21&apply=1', 'GET');
$response2 = $kernel->handle($request2);

$checks['apply=1 sin confirm=SI pide confirmación explícita'] = str_contains((string) $response2->getContent(), 'Confirmación requerida');

$kernel->terminate($request2, $response2);

// --- 3. Diagnóstico de conexión (probe=1) ---
$request3 = Request::create('/backfill-biotime?probe=1', 'GET');
$response3 = $kernel->handle($request3);
$html3 = (string) $response3->getContent();

$checks['probe responde 200'] = $response3->getStatusCode() === 200;
$checks['probe muestra el diagnóstico de conexión'] = str_contains($html3, 'Diagnóstico de conexión con BioTime');
$checks['probe lista las direcciones probadas'] = str_contains($html3, 'Dirección de BioTime');
$checks['probe no escribió en payroll_user'] = PayrollUser::count() === $after;

$kernel->terminate($request3, $response3);

// --- 4. El backfill sigue funcionando sin emp/from/to cuando es probe ---
$request4 = Request::create('/backfill-biotime?probe=1&biotime_url=http://127.0.0.1:9', 'GET');
$response4 = $kernel->handle($request4);

$checks['probe acepta biotime_url puntual'] = str_contains((string) $response4->getContent(), '127.0.0.1:9');

$kernel->terminate($request4, $response4);

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
