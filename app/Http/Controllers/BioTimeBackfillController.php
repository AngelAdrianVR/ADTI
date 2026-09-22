<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

/**
 * Ejecuta el backfill histórico de checadas de BioTime desde el navegador,
 * para que RH no necesite acceso a terminal.
 *
 * Autorización: la llave BACKFILL_KEY del .env, o un usuario con sesión abierta
 * en el ERP. En producción sin llave y sin sesión responde 403.
 */
class BioTimeBackfillController extends Controller
{
    private const TAG = 'BioTimeBackfill';

    public function run(Request $request)
    {
        if (! $this->isAuthorized($request)) {
            return $this->page(
                'Acceso denegado',
                null,
                "No tienes permiso para ejecutar este proceso.\n\n"
                . "Opciones:\n"
                . "  1. Inicia sesión en el ERP en este mismo navegador y vuelve a abrir el enlace.\n"
                . "  2. Agrega ?key=TU_LLAVE al enlace (la llave se configura en BACKFILL_KEY del archivo .env).",
                [],
                false,
                403
            );
        }

        $emp = trim((string) $request->query('emp', ''));
        $from = trim((string) $request->query('from', ''));
        $to = trim((string) $request->query('to', ''));
        $exclude = trim((string) $request->query('exclude', ''));
        $biotimeUrl = trim((string) $request->query('biotime_url', ''));
        $probe = $request->boolean('probe') || trim((string) $request->query('probe', '')) === '1';

        if (! $probe && ($emp === '' || $from === '' || $to === '')) {
            return $this->page(
                'Faltan parámetros',
                null,
                "El enlace debe incluir emp, from y to.\n\n"
                . "Ejemplo:\n"
                . url('/backfill-biotime') . "?emp=63,64,65&from=2026-09-01&to=2026-09-21"
                . (! empty(config('services.biotime.backfill_key')) ? '&key=LA_LLAVE' : ''),
                [],
                false,
                400
            );
        }

        $wantsApply = $request->boolean('apply');
        $confirmed = strtoupper(trim((string) $request->query('confirm', ''))) === 'SI';
        $apply = $wantsApply && $confirmed;

        if ($wantsApply && ! $confirmed) {
            return $this->page(
                'Confirmación requerida',
                'Se solicitó aplicar los cambios, pero falta la confirmación explícita.',
                "Para escribir en la base de datos el enlace debe incluir confirm=SI.\n\n"
                . "Enlace para aplicar:\n"
                . $this->link('/backfill-biotime', $this->queryParams($request) + ['confirm' => 'SI']),
                [],
                true
            );
        }

        $params = [];

        foreach ([
            '--emp' => $emp,
            '--from' => $from,
            '--to' => $to,
            '--exclude' => $exclude,
            '--biotime-url' => $biotimeUrl,
        ] as $option => $value) {
            if ($value !== '') {
                $params[$option] = $value;
            }
        }

        if ($probe) {
            $params['--probe'] = true;
        }

        if ($apply) {
            $params['--apply'] = true;
        }

        Log::info(self::TAG . ' (web): ejecución solicitada', [
            'apply' => $apply,
            'emp' => $emp,
            'from' => $from,
            'to' => $to,
            'exclude' => $exclude,
            'biotime_url' => $biotimeUrl ?: '(default)',
            'ip' => $request->ip(),
            'user_id' => Auth::id(),
        ]);

        $exitCode = Artisan::call('biotime:backfill', $params);
        $output = Artisan::output();

        $commandLine = 'php artisan biotime:backfill'
            . ($emp !== '' ? ' --emp=' . $emp : '')
            . ($from !== '' ? ' --from=' . $from : '')
            . ($to !== '' ? ' --to=' . $to : '')
            . ($exclude !== '' ? ' --exclude=' . $exclude : '')
            . ($biotimeUrl !== '' ? ' --biotime-url=' . $biotimeUrl : '')
            . ($probe ? ' --probe' : '')
            . ($apply ? ' --apply' : '');

        $links = [];

        if (! $apply) {
            $links[] = [
                'label' => 'Diagnóstico: probar conexión con BioTime',
                'href' => $this->link('/backfill-biotime', ['probe' => '1'] + $this->queryParams($request)),
            ];

            if (! $probe) {
                $links[] = [
                    'label' => 'Aplicar los cambios (escribe en la base de datos)',
                    'href' => $this->link('/backfill-biotime', $this->queryParams($request) + ['apply' => '1', 'confirm' => 'SI']),
                    'warning' => true,
                ];
            }
        }

        $subtitle = $probe
            ? 'Diagnóstico de conexión' . ($biotimeUrl !== '' ? ' · URL indicada: ' . $biotimeUrl : '')
            : 'Empleados: ' . $emp . ' · Rango: ' . $from . ' a ' . $to
                . ($exclude !== '' ? ' · Excluidas: ' . $exclude : '');

        return $this->page(
            $probe ? 'Diagnóstico de conexión con BioTime' : ($apply ? 'Backfill aplicado' : 'Backfill: reporte previo (dry-run)'),
            $subtitle . ($exitCode === 0 ? '' : ' · El proceso terminó con incidencias: revisa el resultado'),
            $output,
            $links,
            $apply,
            200,
            $commandLine
        );
    }

    // ------------------------------------------------------------------
    //  Recepción de checadas desde el plugin local (PC del reloj)
    // ------------------------------------------------------------------

    /**
     * Recibe las checadas que envía el plugin local y ejecuta el backfill en este
     * servidor. Es el camino cuando el ERP vive en un VPS y no tiene ruta de red
     * a la IP privada del reloj.
     *
     * POST /api/biotime-punches
     *   Encabezado: X-Backfill-Key: <BACKFILL_KEY>
     *   Cuerpo JSON:
     *     {
     *       "emp_codes": ["63","64","65"],
     *       "from": "2026-09-01",
     *       "to": "2026-09-21",
     *       "apply": false,
     *       "punches": [{"emp_code":"63","punch_time":"2026-09-01 08:55:12"}]
     *     }
     *
     * Responde el reporte en texto plano; el código de salida del comando viaja en
     * el encabezado X-Backfill-Exit (0 = sin incidencias).
     */
    public function store(Request $request)
    {
        if (! $this->isAuthorized($request)) {
            Log::warning(self::TAG . ' (api): petición rechazada', ['ip' => $request->ip()]);

            return response()->json([
                'ok' => false,
                'error' => 'Llave inválida. Define BACKFILL_KEY en el .env del ERP y envíala en el encabezado X-Backfill-Key.',
            ], 403);
        }

        $payload = $request->json()->all() ?: $request->all();

        $empCodes = $payload['emp_codes'] ?? [];

        // Tolerar que llegue un solo código o una cadena "63,64,65"
        if (! is_array($empCodes)) {
            $empCodes = explode(',', (string) $empCodes);
        }

        $empCodes = array_values(array_filter(array_unique(array_map(fn ($code) => trim((string) $code), $empCodes))));

        $punches = $payload['punches'] ?? [];

        // Tolerar que llegue una sola checada en lugar de un arreglo
        if (is_array($punches) && isset($punches['emp_code'])) {
            $punches = [$punches];
        }

        $from = trim((string) ($payload['from'] ?? ''));
        $to = trim((string) ($payload['to'] ?? ''));

        $applyRaw = $payload['apply'] ?? false;
        $apply = is_bool($applyRaw)
            ? $applyRaw
            : in_array(strtolower(trim((string) $applyRaw)), ['1', 'true', 'si', 'sí', 's', 'yes', 'on'], true);

        $validationErrors = [];

        if (empty($empCodes)) {
            $validationErrors[] = 'emp_codes: arreglo de códigos de empleado (obligatorio)';
        }
        if (! is_array($punches) || empty($punches)) {
            $validationErrors[] = 'punches: arreglo de checadas (obligatorio)';
        }
        if ($from === '') {
            $validationErrors[] = 'from: fecha inicial Y-m-d (obligatorio)';
        }
        if ($to === '') {
            $validationErrors[] = 'to: fecha final Y-m-d (obligatorio)';
        }

        if (! empty($validationErrors)) {
            return response()->json(['ok' => false, 'errores' => $validationErrors], 422);
        }

        $directory = storage_path('app/biotime-punches');
        File::ensureDirectoryExists($directory);

        $path = $directory . DIRECTORY_SEPARATOR . 'incoming_' . now()->format('Ymd_His') . '.json';

        File::put($path, json_encode([
            'recibido_en' => now()->toDateTimeString(),
            'origen_ip' => $request->ip(),
            'emp_codes' => $empCodes,
            'from' => $from,
            'to' => $to,
            'apply' => $apply,
            'punches' => array_values($punches),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        Log::info(self::TAG . ' (api): checadas recibidas del plugin local', [
            'ip' => $request->ip(),
            'emp_codes' => $empCodes,
            'from' => $from,
            'to' => $to,
            'apply' => $apply,
            'punches' => count($punches),
            'archivo' => $path,
        ]);

        $exitCode = Artisan::call('biotime:backfill', [
            '--emp' => implode(',', $empCodes),
            '--from' => $from,
            '--to' => $to,
            '--source' => 'file',
            '--file' => $path,
            '--apply' => $apply,
        ]);

        return response(Artisan::output(), 200)
            ->header('Content-Type', 'text/plain; charset=utf-8')
            ->header('X-Backfill-Exit', (string) $exitCode);
    }

    // ------------------------------------------------------------------
    //  Autorización
    // ------------------------------------------------------------------

    private function isAuthorized(Request $request): bool
    {
        if ($this->hasValidKey($request)) {
            return true;
        }

        // Un usuario con sesión abierta en el ERP puede ejecutarlo
        if (Auth::check()) {
            return true;
        }

        // Comodidad únicamente para desarrollo (APP_ENV=local)
        return app()->isLocal();
    }

    /** ¿La petición trae la llave correcta? (encabezado X-Backfill-Key, query o cuerpo) */
    private function hasValidKey(Request $request): bool
    {
        $expected = (string) config('services.biotime.backfill_key');
        $provided = (string) ($request->header('X-Backfill-Key') ?: $request->query('key', '') ?: $request->input('key', ''));

        return $expected !== '' && $provided !== '' && hash_equals($expected, $provided);
    }

    /** @return array<string, string> */
    private function queryParams(Request $request): array
    {
        $params = [];

        foreach (['emp', 'from', 'to', 'exclude', 'biotime_url', 'key'] as $key) {
            $value = $request->query($key);

            if (is_string($value) && $value !== '') {
                $params[$key] = $value;
            }
        }

        return $params;
    }

    /** @param  array<string, string>  $params */
    private function link(string $path, array $params): string
    {
        return url($path) . '?' . http_build_query($params);
    }

    /**
     * Página HTML autocontenida (no depende del build de Vite), pensada para que
     * RH pueda leer el reporte desde el navegador sin terminal.
     *
     * @param  array<int, array{label: string, href: string, warning?: bool}>  $links
     */
    private function page(
        string $title,
        ?string $subtitle,
        string $output,
        array $links = [],
        bool $isApply = false,
        int $status = 200,
        ?string $commandLine = null
    ) {
        $accent = $isApply ? '#b91c1c' : '#1d4ed8';

        $badge = $isApply
            ? '<span style="background:#fee2e2;color:#991b1b;padding:2px 10px;border-radius:9999px;font-size:12px;font-weight:700">ESCRIBE EN LA BASE DE DATOS</span>'
            : '<span style="background:#dcfce7;color:#166534;padding:2px 10px;border-radius:9999px;font-size:12px;font-weight:700">SOLO REPORTE (no escribió nada)</span>';

        $buttons = '';

        foreach ($links as $link) {
            $warning = ! empty($link['warning']);
            $background = $warning ? '#b91c1c' : '#1d4ed8';
            $confirm = $warning
                ? ' onclick="return confirm(\'Se escribirán los días faltantes en la base de datos. ¿Continuar?\');"'
                : '';

            $buttons .= '<a href="' . e($link['href']) . '"' . $confirm
                . ' style="display:inline-block;margin:14px 8px 0 0;padding:10px 16px;background:' . $background
                . ';color:#fff;border-radius:6px;font-weight:600;text-decoration:none">'
                . e($link['label']) . '</a>';
        }

        $commandHtml = '';

        if ($commandLine) {
            $commandHtml = '<h2 style="font-size:13px;text-transform:uppercase;color:#6b7280;margin:26px 0 6px">Comando equivalente (terminal)</h2>'
                . '<code style="display:block;background:#f3f4f6;border:1px solid #e5e7eb;border-radius:6px;padding:10px;font-size:13px;word-break:break-all">'
                . e($commandLine) . '</code>';
        }

        $subtitleHtml = $subtitle
            ? '<p style="color:#4b5563;margin:6px 0 0;font-size:14px">' . e($subtitle) . '</p>'
            : '';

        $html = '<!DOCTYPE html><html lang="es"><head><meta charset="utf-8">'
            . '<meta name="viewport" content="width=device-width, initial-scale=1">'
            . '<title>' . e($title) . '</title></head>'
            . '<body style="margin:0;padding:24px;background:#f9fafb;font-family:system-ui,-apple-system,Segoe UI,Roboto,sans-serif;color:#111827">'
            . '<div style="max-width:1100px;margin:0 auto;background:#fff;border:1px solid #e5e7eb;border-radius:10px;padding:24px">'
            . '<div style="border-left:5px solid ' . $accent . ';padding-left:14px">'
            . '<h1 style="margin:0;font-size:20px">' . e($title) . '</h1>'
            . $subtitleHtml
            . '<div style="margin-top:10px">' . $badge . '</div>'
            . '</div>'
            . $commandHtml
            . '<h2 style="font-size:13px;text-transform:uppercase;color:#6b7280;margin:26px 0 6px">Resultado</h2>'
            . '<pre style="background:#0b1020;color:#e5e7eb;padding:16px;border-radius:8px;overflow:auto;font-size:12.5px;line-height:1.5;white-space:pre-wrap">'
            . e($output) . '</pre>'
            . $buttons
            . '<p style="margin:22px 0 0;font-size:12.5px;color:#6b7280;border-top:1px solid #e5e7eb;padding-top:14px">'
            . 'Regla aplicada: los días que ya tienen cualquier registro en el ERP se omiten por completo (no se sobreescriben). '
            . 'El detalle completo queda en <code>storage/app/biotime-backfills/</code> y en <code>storage/logs/laravel.log</code>.'
            . '</p>'
            . '</div></body></html>';

        return response($html, $status)->header('Cache-Control', 'no-store, max-age=0');
    }
}
