<?php

namespace App\Console\Commands;

use App\Models\Payroll;
use App\Models\PayrollUser;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * Recupera checadas históricas de BioTime y las registra en payroll_user.
 *
 * REGLA DE ORO: solo se escriben los días que NO tienen ningún registro previo
 * en payroll_user para ese empleado. Cualquier día que ya tenga registro se
 * omite por completo (nunca se sobreescribe).
 *
 * Es idempotente: se puede volver a ejecutar sin duplicar ni alterar nada.
 *
 * Uso:
 *   php artisan biotime:backfill --emp=63,64,65 --from=2026-09-01 --to=2026-09-21
 *   php artisan biotime:backfill --emp=63,64,65 --from=2026-09-01 --to=2026-09-21 --apply
 */
class BackfillBioTime extends Command
{
    protected $signature = 'biotime:backfill
                            {--emp= : Códigos de empleado separados por coma (ej: 63,64,65)}
                            {--from= : Fecha inicial en formato Y-m-d (ej: 2026-09-01)}
                            {--to= : Fecha final en formato Y-m-d (ej: 2026-09-21)}
                            {--apply : Escribe en la base de datos. Sin esta bandera es solo un reporte (dry-run)}
                            {--exclude= : Fechas a omitir, separadas por coma (ej: 2026-09-16,2026-09-17)}
                            {--biotime-url= : URL base de BioTime (ej: http://192.168.1.50:81)}
                            {--probe : Solo diagnostica la conexión con BioTime (no consulta checadas ni escribe nada)}
                            {--source=biotime : Origen de las checadas: biotime (API del reloj) o file (JSON que envía el plugin local)}
                            {--file= : Ruta del JSON de checadas cuando --source=file}
                            {--page-size=200 : Registros por página al consultar BioTime}
                            {--max-pages=50 : Máximo de páginas a recorrer por empleado (seguridad)}';

    protected $description = 'Registra en payroll_user las checadas históricas de BioTime SOLO de los días sin ningún registro previo';

    private const TAG = 'BioTimeBackfill';

    /** Ventana de anti-ráfaga, igual que PayrollUserController::processBioTimeTransaction() */
    private const ANTI_BURST_MINUTES = 3;

    /** Una checada anterior a esta hora se considera "de madrugada" (posible cierre de turno nocturno) */
    private const NIGHT_EXIT_BEFORE_MINUTES = 720; // 12:00

    /** Ventana máxima aceptada entre la entrada y su cierre nocturno (18 horas, igual que el endpoint) */
    private const NIGHT_WINDOW_MINUTES = 1080;

    // --- Estado del proceso ---
    private array $summaries = [];   // Resumen por empleado
    private array $plan = [];        // Días que se van a crear
    private array $skipped = [];     // Días omitidos porque ya tienen registro
    private array $issues = [];      // Situaciones que requieren decisión manual
    private array $applied = [];     // Días efectivamente escritos
    private array $errors = [];      // Errores durante la ejecución
    private array $triedUrls = [];   // Direcciones de BioTime probadas

    public function handle(): int
    {
        // El comando puede invocarse más de una vez en el mismo proceso (por ejemplo
        // desde el endpoint /api/biotime-punches): se limpia el estado acumulado para
        // que cada ejecución sea independiente e idempotente.
        $this->resetState();

        // El proceso hace varias consultas HTTP: evita que PHP lo corte a medio camino
        @set_time_limit(300);

        $apply = (bool) $this->option('apply');
        $excluded = $this->parseExcluded();

        // Diagnóstico de conectividad: no requiere --emp / --from / --to
        if ((bool) $this->option('probe')) {
            return $this->probe();
        }

        $codes = $this->parseCodes();
        $from = $this->parseDate((string) $this->option('from'));
        $to = $this->parseDate((string) $this->option('to'));

        if (empty($codes) || ! $from || ! $to || $from->greaterThan($to)) {
            $this->newLine();
            $this->error('Parámetros inválidos. --emp, --from y --to son obligatorios y --from debe ser menor o igual a --to.');
            $this->line('Ejemplo: php artisan biotime:backfill --emp=63,64,65 --from=2026-09-01 --to=2026-09-21');
            $this->newLine();

            return self::FAILURE;
        }

        $fromStr = $from->toDateString();
        $toStr = $to->toDateString();

        $this->newLine();
        $this->line('<options=bold>=== Backfill de checadas BioTime -> ERP ===</>');
        $this->line('Empleados: <fg=cyan>' . implode(', ', $codes) . '</>');
        $this->line('Rango: <fg=cyan>' . $fromStr . '</> a <fg=cyan>' . $toStr . '</>');
        $this->line('Modo: ' . ($apply
            ? '<fg=red;options=bold>APLICAR (escribe en la base de datos)</>'
            : '<fg=green>REPORTE (dry-run, no escribe nada)</>'));
        $this->newLine();

        // 1. Resolver empleados por users.code
        $employees = User::whereIn('code', $codes)
            ->get()
            ->keyBy(fn ($user) => (string) $user->code);

        foreach ($codes as $code) {
            if (! $employees->has($code)) {
                $this->issues[] = [
                    'emp_code' => $code,
                    'date' => '-',
                    'motivo' => 'No existe un usuario con ese code en la tabla users',
                ];
            }
        }

        if ($employees->isEmpty()) {
            $this->error('No se encontró ningún empleado con los códigos indicados. Se cancela el proceso.');
            $this->writeAuditReport($apply, $codes, $fromStr, $toStr, null);

            return self::FAILURE;
        }

        // 2. Origen de las checadas: API del reloj o archivo JSON enviado por el plugin local
        $source = strtolower(trim((string) $this->option('source')));

        if (! in_array($source, ['biotime', 'file'], true)) {
            $this->error('Valor inválido en --source. Usa "biotime" (API del reloj) o "file" (JSON enviado por el plugin local).');

            return self::FAILURE;
        }

        $preloadedPunches = [];
        $baseUrl = null;
        $token = null;

        if ($source === 'file') {
            $file = trim((string) $this->option('file'));

            if ($file === '') {
                $this->error('Con --source=file hay que indicar la ruta con --file=ruta/del/json.');
                $this->writeAuditReport($apply, $codes, $fromStr, $toStr, null);

                return self::FAILURE;
            }

            try {
                $preloadedPunches = $this->loadPunchesFromFile($file);
            } catch (Throwable $e) {
                $this->error($e->getMessage());
                $this->writeAuditReport($apply, $codes, $fromStr, $toStr, null);

                return self::FAILURE;
            }

            $baseUrl = 'archivo:' . $file;
        } else {
            $connection = $this->connectToBioTime($this->resolveCandidateUrls());

            if (! $connection) {
                $this->error('No fue posible autenticarse contra BioTime. Se cancela el proceso.');
                $this->line('Indica la dirección correcta con --biotime-url=http://IP:81 o con BIOTIME_URL en el .env.');
                $this->line('Para ver el diagnóstico completo: php artisan biotime:backfill --probe');
                $this->line('Si el ERP está en un VPS y el reloj en la red local, usa --source=file con el plugin local.');
                $this->writeAuditReport($apply, $codes, $fromStr, $toStr, null);

                return self::FAILURE;
            }

            $baseUrl = $connection['url'];
            $token = $connection['token'];
        }

        $this->newLine();

        // 3. Analizar a cada empleado
        foreach ($employees as $employee) {
            $code = (string) $employee->code;

            if (! $employee->is_active) {
                $this->summaries[] = [
                    'emp_code' => $code,
                    'name' => $employee->name,
                    'punches' => 0,
                    'days_with_punches' => 0,
                    'to_create' => 0,
                    'skipped' => 0,
                    'without_payroll' => 0,
                    'without_punches' => 0,
                ];
                $this->issues[] = [
                    'emp_code' => $code,
                    'date' => '-',
                    'motivo' => 'El empleado está inactivo (is_active = false); el endpoint de producción también descarta sus checadas',
                ];

                continue;
            }

            $punches = $source === 'file'
                ? ($preloadedPunches[$code] ?? [])
                : $this->fetchPunches($baseUrl, $token, $code, $from, $to);

            $this->analyseEmployee($employee, $code, $punches, $from, $to, $excluded);

            usleep(200000); // Cortesía para no saturar el reloj
        }

        // 4. Reporte
        $this->printReport();

        // 5. Aplicar cambios
        if ($apply && ! empty($this->plan)) {
            $this->applyPlan();
        }

        // 6. Bitácora en disco
        $reportPath = $this->writeAuditReport($apply, $codes, $fromStr, $toStr, $baseUrl);

        $this->newLine();
        $this->line('Bitácora del proceso: <fg=cyan>' . $reportPath . '</>');
        $this->newLine();

        if ($apply) {
            $this->line('<fg=green;options=bold>Proceso terminado. Días creados: ' . count($this->applied)
                . '. Días omitidos: ' . count($this->skipped) . '. Errores: ' . count($this->errors) . '.</>');
            $this->newLine();
            $this->line('Los movimientos quedaron registrados en storage/logs/laravel.log con la etiqueta "' . self::TAG . '".');
        } else {
            $this->line('<fg=yellow;options=bold>Esto fue un reporte (dry-run): NO se escribió nada en la base de datos.</>');
            $this->line('Para aplicar los cambios vuelve a ejecutarlo agregando la bandera --apply.');
        }
        $this->newLine();

        return empty($this->errors) ? self::SUCCESS : self::FAILURE;
    }

    // ------------------------------------------------------------------
    //  Entrada de datos
    // ------------------------------------------------------------------

    /** Limpia el estado de la ejecución (el objeto del comando se reutiliza en el proceso) */
    private function resetState(): void
    {
        $this->summaries = [];
        $this->plan = [];
        $this->skipped = [];
        $this->issues = [];
        $this->applied = [];
        $this->errors = [];
        $this->triedUrls = [];
    }

    /** @return array<int, string> */
    private function parseCodes(): array
    {
        $raw = (string) $this->option('emp');

        return collect(explode(',', $raw))
            ->map(fn ($code) => trim($code))
            ->filter(fn ($code) => $code !== '')
            ->unique()
            ->values()
            ->all();
    }

    /** @return array<string, true> */
    private function parseExcluded(): array
    {
        $excluded = [];

        foreach (explode(',', (string) $this->option('exclude')) as $date) {
            $date = trim($date);
            if ($date !== '') {
                $excluded[$date] = true;
            }
        }

        return $excluded;
    }

    private function parseDate(string $value): ?Carbon
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        try {
            return Carbon::createFromFormat('Y-m-d', $value)->startOfDay();
        } catch (Throwable $e) {
            return null;
        }
    }

    /**
     * Lee las checadas desde un JSON enviado por el plugin local (--source=file).
     *
     * Formato esperado:
     *   {
     *     "emp_codes": ["63","64","65"],
     *     "from": "2026-09-01",
     *     "to": "2026-09-21",
     *     "punches": [{"emp_code": "63", "punch_time": "2026-09-01 08:55:12"}]
     *   }
     * También acepta un arreglo simple de checadas.
     *
     * @return array<string, array<int, array{date: string, time: string, punch_time: string}>>
     */
    private function loadPunchesFromFile(string $file): array
    {
        $path = $file;

        if (! File::exists($path)) {
            $path = storage_path('app/' . ltrim($file, '/\\'));
        }

        if (! File::exists($path)) {
            throw new RuntimeException('No se encontró el archivo de checadas: ' . $file);
        }

        $decoded = json_decode((string) File::get($path), true);

        if (! is_array($decoded)) {
            throw new RuntimeException('El archivo ' . $path . ' no contiene JSON válido.');
        }

        $records = (isset($decoded['punches']) && is_array($decoded['punches'])) ? $decoded['punches'] : $decoded;

        $byEmployee = [];
        $ignored = 0;

        foreach ($records as $record) {
            if (! is_array($record)) {
                $ignored++;
                continue;
            }

            $code = trim((string) ($record['emp_code'] ?? ''));
            $punchTime = str_replace('T', ' ', trim((string) ($record['punch_time'] ?? '')));

            if ($code === '' || strlen($punchTime) < 16) {
                $ignored++;
                continue;
            }

            $byEmployee[$code][] = [
                'date' => substr($punchTime, 0, 10),
                'time' => substr($punchTime, 11, 5),
                'punch_time' => $punchTime,
            ];
        }

        $total = 0;

        foreach ($byEmployee as $code => $list) {
            usort($list, fn ($a, $b) => strcmp($a['punch_time'], $b['punch_time']));
            $byEmployee[$code] = $list;
            $total += count($list);
        }

        $this->line('Archivo: <fg=cyan>' . $path . '</>');
        $this->line('Checadas válidas: <fg=cyan>' . $total . '</> de ' . count($byEmployee) . ' empleado(s): '
            . implode(', ', array_keys($byEmployee)));

        if ($ignored > 0) {
            $this->issues[] = [
                'emp_code' => '-',
                'date' => '-',
                'motivo' => $ignored . ' registro(s) del archivo se ignoraron por no traer emp_code o punch_time válidos',
            ];
        }

        Log::info(self::TAG . ': checadas cargadas desde archivo', [
            'archivo' => $path,
            'empleados' => array_keys($byEmployee),
            'total' => $total,
        ]);

        return $byEmployee;
    }

    // ------------------------------------------------------------------
    //  Cliente de BioTime
    // ------------------------------------------------------------------

    /** @return array<int, string> */
    private function resolveCandidateUrls(): array
    {
        $candidates = [];

        // 1) Direcciones explícitas (--biotime-url / &biotime_url=), admite varias separadas por coma
        foreach (explode(',', (string) $this->option('biotime-url')) as $raw) {
            $url = $this->normalizeUrl($raw);

            if ($url !== '') {
                $candidates[] = $url;
            }
        }

        // 2) BIOTIME_URL del .env
        $configured = $this->normalizeUrl((string) config('services.biotime.url'));

        if ($configured !== '') {
            $candidates[] = $configured;
        }

        // 3) Respaldo: direcciones locales de ESTE servidor (127.0.0.1 primero).
        //    Sirve por si el ERP corre en la misma máquina o LAN del reloj.
        $port = (int) config('services.biotime.port', 81);

        foreach ($this->detectLocalIps() as $ip) {
            $candidates[] = 'http://' . $ip . ':' . $port;
        }

        return array_values(array_unique($candidates));
    }

    /**
     * Normaliza una dirección escrita a mano: tolera "http:host", "http:/host",
     * URLs sin esquema y sin puerto.
     */
    private function normalizeUrl(string $url): string
    {
        $url = trim($url);

        if ($url === '') {
            return '';
        }

        if (preg_match('#^(https?):/*#i', $url, $matches)) {
            // Corrige "http:host", "http:/host" y "http://host" al formato canónico
            $url = strtolower($matches[1]) . '://' . ltrim(substr($url, strlen($matches[0])), '/');
        } elseif (! preg_match('#^[a-z][a-z0-9+.-]*://#i', $url)) {
            // Sin esquema: se asume http
            $url = 'http://' . $url;
        }

        $url = rtrim($url, '/');
        $path = (string) parse_url($url, PHP_URL_PATH);

        // Agregar el puerto solo si no lo trae y no hay ruta en la URL
        if (empty(parse_url($url, PHP_URL_PORT)) && ($path === '' || $path === '/')) {
            $url .= ':' . (int) config('services.biotime.port', 81);
        }

        return $url;
    }

    /**
     * Direcciones IPv4 locales de ESTE servidor, empezando por loopback.
     * Se usan solo cuando no hay BIOTIME_URL configurada.
     *
     * @return array<int, string>
     */
    private function detectLocalIps(): array
    {
        $ips = ['127.0.0.1'];

        try {
            if (stripos(PHP_OS_FAMILY, 'Windows') !== false) {
                $output = @shell_exec('ipconfig');
                if ($output && preg_match_all('/IPv4[^:]*:\s*([0-9.]+)/', $output, $matches)) {
                    $ips = array_merge($ips, $matches[1]);
                }
            } else {
                $output = @shell_exec('hostname -I');
                if ($output && preg_match_all('/([0-9]+\.[0-9]+\.[0-9]+\.[0-9]+)/', $output, $matches)) {
                    $ips = array_merge($ips, $matches[1]);
                }
            }
        } catch (Throwable $e) {
            // Sin acceso a shell: seguimos con lo que tengamos
        }

        $hostname = gethostname();
        $resolved = $hostname ? gethostbyname($hostname) : null;

        if ($resolved && $resolved !== $hostname) {
            $ips[] = $resolved;
        }

        $ips = array_filter($ips, fn ($ip) => ! str_starts_with($ip, '169.254.'));

        return array_values(array_unique($ips));
    }

    /**
     * Prueba las direcciones candidatas en orden y devuelve la primera con la que
     * BioTime entregue un token.
     *
     * @param  array<int, string>  $candidates
     * @return array{url: string, token: string}|null
     */
    private function connectToBioTime(array $candidates): ?array
    {
        $this->line('Probando conexión con BioTime (' . count($candidates) . ' dirección/es)...');

        foreach ($candidates as $url) {
            [$ok, $detail] = $this->testBaseUrl($url);
            $this->triedUrls[$url] = $detail;

            if ($ok === true) {
                $this->line('  <fg=green>' . $url . '</> -> autenticación OK');
                $this->line('BioTime API: <fg=green>' . $url . '</>');

                return ['url' => $url, 'token' => (string) $detail];
            }

            $this->line('  <fg=yellow>' . $url . '</> -> ' . $detail);
        }

        Log::error(self::TAG . ': no se pudo conectar con BioTime', ['candidatas' => $this->triedUrls]);

        return null;
    }

    /**
     * Diagnóstico de conectividad: muestra desde ESTE servidor qué direcciones de
     * BioTime responden y cuál funciona. No consulta checadas ni escribe nada.
     */
    private function probe(): int
    {
        @set_time_limit(240);

        $candidates = $this->resolveCandidateUrls();

        $this->newLine();
        $this->line('<options=bold>=== Diagnóstico de conexión con BioTime ===</>');
        $this->line('Usuario configurado: <fg=cyan>' . config('services.biotime.username') . '</>');

        $password = (string) config('services.biotime.password');
        $defaultPassword = $password === 'adti1234';

        $this->line('Contraseña: <fg=cyan>' . ($defaultPassword ? 'la del código (por defecto)' : 'definida en BIOTIME_PASS') . '</>');
        $this->line('Origen de las direcciones: <fg=cyan>' . ($this->option('biotime-url')
            ? '--biotime-url'
            : (config('services.biotime.url') ? 'BIOTIME_URL del .env' : 'autodetección de la IP de este servidor')) . '</>');

        if ($defaultPassword) {
            $this->line('<fg=yellow>Si el reloj tiene otra contraseña, define BIOTIME_PASS en el .env (está en el config.ini del plugin).</>');
        }
        $this->newLine();

        $rows = [];
        $working = null;

        foreach ($candidates as $url) {
            [$ok, $detail] = $this->testBaseUrl($url, 6);
            $this->triedUrls[$url] = $detail;

            $rows[] = [$url, $ok ? 'CONECTA' : 'FALLA', $this->shorten($detail, 55)];

            if ($ok && $working === null) {
                $working = $url;
            }
        }

        $this->table(['Dirección de BioTime', 'Resultado', 'Detalle'], $rows);
        $this->newLine();

        if ($working !== null) {
            $this->line('<fg=green;options=bold>Dirección que funciona: ' . $working . '</>');
            $this->line('Configúrala en el .env de este servidor:  BIOTIME_URL=' . $working);
            $this->line('O pásala en el enlace del backfill:  &biotime_url=' . $working);
        } else {
            $this->error('Ninguna dirección de BioTime respondió desde este servidor.');
            $this->line('Si el reloj está en otra red, necesitas una de estas opciones:');
            $this->line('  1) Que este servidor esté en la misma LAN del reloj y usar su IP local (ej. http://192.168.1.50:81).');
            $this->line('  2) Publicar el puerto 81 del reloj (IP pública + reenvío de puerto en el router + firewall).');
            $this->line('  3) Una VPN o túnel (Tailscale / Cloudflare Tunnel / WireGuard) entre el servidor y la red del reloj.');
            $this->line('Revisa también que BIOTIME_USER y BIOTIME_PASS sean los correctos.');
        }
        $this->newLine();

        return $working !== null ? self::SUCCESS : self::FAILURE;
    }

    /**
     * Prueba una dirección de BioTime solicitando el token.
     *
     * @return array{0: bool, 1: string}  [éxito, token o descripción del problema]
     */
    private function testBaseUrl(string $baseUrl, ?int $timeout = null): array
    {
        $timeout = $timeout ?? max(3, min(10, (int) config('services.biotime.timeout', 30)));

        try {
            $response = Http::timeout($timeout)
                ->acceptJson()
                ->asJson()
                ->post($baseUrl . '/api-token-auth/', [
                    'username' => config('services.biotime.username'),
                    'password' => config('services.biotime.password'),
                ]);
        } catch (Throwable $e) {
            return [false, 'error de conexión: ' . $e->getMessage()];
        }

        if (! $response->successful()) {
            return [false, 'HTTP ' . $response->status() . ' (' . $this->plainBody($response->body()) . ')'];
        }

        $token = $response->json('token');

        if (empty($token)) {
            return [false, 'respondió sin token, revisar usuario/contraseña (' . $this->plainBody($response->body()) . ')'];
        }

        return [true, (string) $token];
    }

    /** Cuerpo de la respuesta en texto plano y corto, para mensajes de error */
    private function plainBody(string $body): string
    {
        $plain = trim((string) preg_replace('/\s+/', ' ', strip_tags($body)));

        return $this->shorten($plain, 120) ?: 'sin contenido';
    }

    /**
     * Obtiene las checadas de un empleado en el rango, paginando la respuesta
     * (el plugin de Python no pagina: eso provoca que se pierdan registros).
     *
     * @return array<int, array{date: string, time: string, punch_time: string}>
     */
    private function fetchPunches(string $baseUrl, string $token, string $empCode, Carbon $from, Carbon $to): array
    {
        $pageSize = max(20, (int) $this->option('page-size'));
        $maxPages = max(1, (int) $this->option('max-pages'));
        $timeout = (int) config('services.biotime.timeout', 30);

        $startTime = $from->copy()->startOfDay()->format('Y-m-d H:i:s');
        $endTime = $to->copy()->endOfDay()->format('Y-m-d H:i:s');

        $punches = [];
        $rawSeen = 0;
        $total = null;

        for ($page = 1; $page <= $maxPages; $page++) {
            try {
                $response = Http::timeout($timeout)
                    ->withToken($token)
                    ->acceptJson()
                    ->get($baseUrl . '/iclock/api/transactions/', [
                        'start_time' => $startTime,
                        'end_time' => $endTime,
                        'emp_code' => $empCode,
                        'page' => $page,
                        'page_size' => $pageSize,
                    ]);
            } catch (Throwable $e) {
                $this->errors[] = [
                    'emp_code' => $empCode,
                    'date' => '-',
                    'motivo' => 'Error de conexión al consultar checadas: ' . $e->getMessage(),
                ];
                Log::error(self::TAG . ': error al consultar transacciones', [
                    'emp_code' => $empCode,
                    'error' => $e->getMessage(),
                ]);

                break;
            }

            if (! $response->successful()) {
                $this->errors[] = [
                    'emp_code' => $empCode,
                    'date' => '-',
                    'motivo' => 'BioTime respondió HTTP ' . $response->status() . ' al consultar checadas',
                ];

                break;
            }

            $json = $response->json();
            $data = is_array($json['data'] ?? null) ? $json['data'] : [];
            $returned = count($data);
            $rawSeen += $returned;
            $total = $json['count'] ?? $total;

            foreach ($data as $record) {
                $punchTime = $record['punch_time'] ?? null;
                if (empty($punchTime)) {
                    continue;
                }

                // BioTime entrega 'Y-m-d H:i:s'; toleramos también formato ISO.
                $punchTime = str_replace('T', ' ', (string) $punchTime);

                // Filtro de seguridad por si el reloj ignora el parámetro emp_code
                $code = (string) ($record['emp_code'] ?? '');
                if ($code !== '' && $code !== $empCode) {
                    continue;
                }

                $punches[] = [
                    'date' => substr($punchTime, 0, 10),
                    'time' => substr($punchTime, 11, 5),
                    'punch_time' => $punchTime,
                ];
            }

            if ($returned === 0) {
                break; // No hay más registros
            }

            if (! empty($json['next'])) {
                continue; // La propia API indica que hay más páginas
            }

            if ($total !== null && $rawSeen < (int) $total) {
                continue; // La API no manda 'next' pero aún faltan registros
            }

            break;
        }

        if ($total !== null && $rawSeen < (int) $total) {
            $this->issues[] = [
                'emp_code' => $empCode,
                'date' => '-',
                'motivo' => 'Se alcanzó el límite de páginas (--max-pages) con ' . $rawSeen . ' de ' . $total
                    . ' registros. Revisa el paginado con --page-size / --max-pages',
            ];
        }

        usort($punches, fn ($a, $b) => strcmp($a['punch_time'], $b['punch_time']));

        return $punches;
    }

    // ------------------------------------------------------------------
    //  Análisis de días
    // ------------------------------------------------------------------

    /**
     * Agrupa las checadas por día y decide qué hacer con cada fecha.
     *
     * @param  array<int, array{date: string, time: string, punch_time: string}>  $punches
     * @param  array<string, true>  $excluded
     */
    private function analyseEmployee(User $employee, string $code, array $punches, Carbon $from, Carbon $to, array $excluded): void
    {
        $fromStr = $from->toDateString();
        $toStr = $to->toDateString();

        $byDate = [];

        foreach ($punches as $punch) {
            $date = $punch['date'];

            if ($date < $fromStr || $date > $toStr) {
                continue;
            }

            if (isset($excluded[$date])) {
                continue;
            }

            $byDate[$date][] = $punch['time'];
        }

        // Días del rango sin ninguna checada (solo informativo)
        $daysInRange = 0;
        $withoutPunches = 0;

        for ($day = $from->copy(); $day->lessThanOrEqualTo($to); $day->addDay()) {
            $daysInRange++;
            if (! isset($byDate[$day->toDateString()])) {
                $withoutPunches++;
            }
        }

        ksort($byDate);

        $toCreate = 0;
        $skipped = 0;
        $withoutPayroll = 0;
        $totalPunches = 0;

        // --- Fase 1: clasificar cada día ---
        $days = [];

        foreach ($byDate as $date => $times) {
            $times = $this->dedupeTimes($times);

            if (empty($times)) {
                continue;
            }

            $totalPunches += count($times);

            // REGLA DE ORO: si el día ya tiene CUALQUIER registro, no se toca.
            $existing = PayrollUser::where('user_id', $employee->id)
                ->whereDate('date', $date)
                ->first();

            if ($existing) {
                $skipped++;
                $days[$date] = ['state' => 'skip', 'times' => $times];
                $this->skipped[] = [
                    'emp_code' => $code,
                    'date' => $date,
                    'punches' => count($times),
                    'check_in' => $this->shortTime($existing->check_in),
                    'check_out' => $this->shortTime($existing->check_out),
                    'complete' => (bool) ($existing->check_in && $existing->check_out),
                ];

                continue;
            }

            $payroll = $this->resolvePayroll($date);

            if (! $payroll) {
                $withoutPayroll++;
                $days[$date] = ['state' => 'nopayroll', 'times' => $times];
                $this->issues[] = [
                    'emp_code' => $code,
                    'date' => $date,
                    'motivo' => 'No hay ninguna catorcena (payrolls) que cubra esta fecha: requiere decisión manual',
                ];

                continue;
            }

            $days[$date] = [
                'state' => 'create',
                'times' => $times,
                'payroll_id' => $payroll->id,
                'payroll_start' => Carbon::parse($payroll->start_date)->toDateString(),
            ];
        }

        foreach ($this->buildPlans($days, $employee, $code) as $entry) {
            $toCreate++;
            $this->plan[] = $entry;
        }

        $this->summaries[] = [
            'emp_code' => $code,
            'name' => $employee->name,
            'punches' => $totalPunches,
            'days_with_punches' => count($byDate),
            'to_create' => $toCreate,
            'skipped' => $skipped,
            'without_payroll' => $withoutPayroll,
            'without_punches' => $withoutPunches,
        ];
    }

    /**
     * Busca la catorcena que contiene la fecha.
     * Misma regla que PayrollUserController::processBioTimeTransaction().
     * No se usa la nómina activa como respaldo: escribir una checada vieja en la
     * catorcena equivocada es peor que no escribirla.
     */
    private function resolvePayroll(string $date): ?Payroll
    {
        return Payroll::where('start_date', '<=', $date)
            ->whereRaw('? <= DATE_ADD(start_date, INTERVAL 13 DAY)', [$date])
            ->orderBy('start_date')
            ->first();
    }

    private function shortTime($value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return substr(trim((string) $value), 0, 5);
    }

    // ------------------------------------------------------------------
    //  Emparejamiento de checadas
    // ------------------------------------------------------------------

    /**
     * Recorre los días en orden cronológico y arma el registro final de cada día.
     *
     * Reglas (deterministas, sin depender de la hora en que se ejecute):
     *   - La primera checada del día es la entrada y la última es la salida.
     *   - Las checadas intermedias delimitan la comida (primera = inicio, última = fin).
     *   - Si TODAS las checadas de un día son de madrugada (antes de las 12:00) y el día
     *     anterior quedó abierto dentro de la ventana de 18 horas, se consideran el cierre
     *     de ese turno nocturno (misma idea que $openEntry del endpoint).
     *   - NUNCA se usa User::setPause(): eso contamina el día de hoy.
     *
     * @param  array<string, array<string, mixed>>  $days
     * @return array<int, array<string, mixed>>
     */
    private function buildPlans(array $days, User $employee, string $code): array
    {
        $planned = [];
        $openDate = null;

        foreach ($days as $date => $info) {
            if ($info['state'] !== 'create') {
                // Un día que ya existe rompe la cadena: no se puede cerrar porque no se toca.
                if ($openDate !== null && $info['state'] === 'skip' && $this->looksLikeNightExit($info['times'])) {
                    $this->issues[] = [
                        'emp_code' => $code,
                        'date' => $openDate,
                        'motivo' => 'Quedó sin salida y las checadas de la madrugada del ' . $date
                            . ' podrían cerrarlo, pero ese día ya tiene registro en el ERP: por la regla no se toca. Revisar manualmente',
                    ];
                }

                $openDate = null;

                continue;
            }

            $times = $this->dedupeTimes($info['times']);

            if (empty($times)) {
                $openDate = null;

                continue;
            }

            // ¿Estas checadas de madrugada cierran el turno que quedó abierto el día anterior?
            if ($openDate !== null && isset($planned[$openDate]) && $this->looksLikeNightExit($times)) {
                $previous = $planned[$openDate];
                $gap = $this->nightGapMinutes($openDate, (string) $previous['check_in'], $date, $times[0]);

                if ($gap > 0 && $gap < self::NIGHT_WINDOW_MINUTES) {
                    $planned[$openDate]['check_out'] = $times[count($times) - 1];
                    $planned[$openDate]['night_from'] = $date;
                    $planned[$openDate]['punches'] += count($times);
                    $planned[$openDate]['notes'][] = 'turno nocturno: las checadas del ' . $date . ' cierran este día';
                    $this->applyBreak($planned[$openDate], array_slice($times, 0, -1));
                    $openDate = null;

                    continue;
                }
            }

            $count = count($times);
            $checkOut = $count >= 2 ? $times[$count - 1] : null;
            $middles = $count >= 2 ? array_slice($times, 1, -1) : [];

            $entry = [
                'emp_code' => $code,
                'employee_name' => $employee->name,
                'employee_id' => $employee->id,
                'date' => $date,
                'day_name' => Carbon::parse($date)->isoFormat('dddd'),
                'is_weekend' => Carbon::parse($date)->isWeekend(),
                'punches' => $count,
                'check_in' => $times[0],
                'check_out' => $checkOut,
                'break_start' => null,
                'break_end' => null,
                'night_from' => null,
                'payroll_id' => $info['payroll_id'],
                'payroll_start' => $info['payroll_start'],
                'notes' => [],
            ];

            $this->applyBreak($entry, $middles);

            if ($entry['is_weekend']) {
                $entry['notes'][] = 'fin de semana';
            }

            if ($checkOut === null) {
                $entry['notes'][] = 'una sola checada: quedaría sin salida';
            }

            $planned[$date] = $entry;
            $openDate = $checkOut === null ? $date : null;
        }

        return array_values($planned);
    }

    /** Quita duplicados consecutivos del reloj (anti-ráfaga de 3 minutos) */
    private function dedupeTimes(array $times): array
    {
        $times = array_values(array_unique($times));
        sort($times);

        $accepted = [];

        foreach ($times as $time) {
            $last = empty($accepted) ? null : $accepted[count($accepted) - 1];

            if ($last !== null && abs($this->rawMinutesBetween($last, $time)) <= self::ANTI_BURST_MINUTES) {
                continue;
            }

            $accepted[] = $time;
        }

        return $accepted;
    }

    /** ¿Todas las checadas son de madrugada? (candidatas a cerrar un turno nocturno) */
    private function looksLikeNightExit(array $times): bool
    {
        foreach ($times as $time) {
            if ($this->minutesOf($time) >= self::NIGHT_EXIT_BEFORE_MINUTES) {
                return false;
            }
        }

        return ! empty($times);
    }

    /** Minutos entre la entrada del día anterior y el cierre de madrugada */
    private function nightGapMinutes(string $previousDate, string $previousFirst, string $date, string $first): int
    {
        $dayGap = (int) round(Carbon::parse($previousDate)->diffInDays(Carbon::parse($date)));

        return ($dayGap * 1440) + $this->minutesOf($first) - $this->minutesOf($previousFirst);
    }

    /** Define la comida a partir de las checadas intermedias del día */
    private function applyBreak(array &$entry, array $middles): void
    {
        if (empty($middles)) {
            $entry['break_start'] = null;
            $entry['break_end'] = null;

            return;
        }

        $entry['break_start'] = $middles[0];
        $entry['break_end'] = $middles[count($middles) - 1];
    }

    /** Minutos transcurridos desde medianoche */
    private function minutesOf(string $time): int
    {
        $parts = explode(':', substr(trim($time), 0, 5));
        $hour = (int) ($parts[0] ?? 0);
        $minute = (int) ($parts[1] ?? 0);

        return ($hour * 60) + $minute;
    }

    /** Diferencia firmada en minutos entre dos horas (sin ajuste de medianoche) */
    private function rawMinutesBetween(string $from, string $to): int
    {
        return $this->minutesOf($to) - $this->minutesOf($from);
    }

    // ------------------------------------------------------------------
    //  Reporte
    // ------------------------------------------------------------------

    private function printReport(): void
    {
        $this->line('<options=bold>RESUMEN POR EMPLEADO</>');
        $this->table(
            ['Código', 'Empleado', 'Checadas', 'Días c/checadas', 'Días a crear', 'Días omitidos', 'Sin catorcena', 'Días sin checadas'],
            array_map(fn ($s) => [
                $s['emp_code'],
                $this->shorten((string) $s['name'], 28),
                $s['punches'],
                $s['days_with_punches'],
                $s['to_create'],
                $s['skipped'],
                $s['without_payroll'],
                $s['without_punches'],
            ], $this->summaries)
        );

        if (empty($this->plan)) {
            $this->line('<fg=yellow>No hay días nuevos que crear: todas las fechas del rango que tienen checadas ya tienen registro en el ERP.</>');
            $this->newLine();
        } else {
            $this->line('<options=bold>DÍAS QUE SE VAN A CREAR (' . count($this->plan) . ')</>');

            $rows = array_map(function ($day) {
                return [
                    $day['emp_code'],
                    $day['date'],
                    $this->shorten((string) $day['day_name'], 12),
                    $day['punches'],
                    $day['check_in'],
                    $day['check_out'] ?? '(sin salida)',
                    ($day['break_start'] ?? '-') . ' / ' . ($day['break_end'] ?? '-'),
                    $day['payroll_id'],
                    implode('; ', $day['notes']) ?: '-',
                ];
            }, $this->plan);

            $this->table(
                ['Código', 'Fecha', 'Día', 'Chec.', 'Entrada', 'Salida', 'Break ini/fin', 'Catorcena', 'Notas'],
                $rows
            );
        }

        $this->printDetails();
    }

    private function printDetails(): void
    {
        if (! empty($this->skipped)) {
            $incomplete = array_filter($this->skipped, fn ($s) => ! $s['complete']);

            $this->line('<options=bold>DÍAS OMITIDOS PORQUE YA TIENEN REGISTRO (' . count($this->skipped) . ')</>');
            $this->table(
                ['Código', 'Fecha', 'Checadas', 'Entrada ERP', 'Salida ERP', 'Estado'],
                array_map(fn ($s) => [
                    $s['emp_code'],
                    $s['date'],
                    $s['punches'],
                    $s['check_in'] ?? '-',
                    $s['check_out'] ?? '-',
                    $s['complete'] ? 'Completo (no se toca)' : 'INCOMPLETO: revisar manualmente',
                ], $this->skipped)
            );

            if (! empty($incomplete)) {
                $this->newLine();
                $this->line('<fg=yellow;options=bold>ATENCIÓN: ' . count($incomplete)
                    . ' día(s) ya tienen registro pero les falta la salida. Por la regla acordada NO se tocan; si HR los necesita completos, hay que decidirlo aparte.</>');
            }
        }

        if (! empty($this->issues)) {
            $this->newLine();
            $this->line('<fg=yellow;options=bold>PUNTOS QUE REQUIEREN DECISIÓN MANUAL (' . count($this->issues) . ')</>');
            $this->table(
                ['Código', 'Fecha', 'Motivo'],
                array_map(fn ($i) => [$i['emp_code'], $i['date'], $this->shorten($i['motivo'], 90)], $this->issues)
            );
        }

        if (! empty($this->errors)) {
            $this->newLine();
            $this->line('<fg=red;options=bold>ERRORES (' . count($this->errors) . ')</>');
            $this->table(
                ['Código', 'Fecha', 'Error'],
                array_map(fn ($e) => [$e['emp_code'], $e['date'], $this->shorten($e['motivo'], 90)], $this->errors)
            );
        }

        $this->newLine();
    }

    private function shorten(?string $value, int $limit): string
    {
        $value = trim((string) $value);

        return mb_strlen($value) > $limit ? mb_substr($value, 0, $limit - 3) . '...' : $value;
    }

    // ------------------------------------------------------------------
    //  Aplicación de los cambios
    // ------------------------------------------------------------------

    private function applyPlan(): void
    {
        $this->line('<options=bold>ESCRIBIENDO EN LA BASE DE DATOS (' . count($this->plan) . ' día(s))</>');

        foreach ($this->plan as $day) {
            try {
                $entry = DB::transaction(fn () => $this->writeDay($day));

                $this->applied[] = [
                    'emp_code' => $day['emp_code'],
                    'date' => $day['date'],
                    'payroll_user_id' => $entry->id,
                    'check_in' => $this->shortTime($entry->check_in),
                    'check_out' => $this->shortTime($entry->check_out),
                    'break_start' => $this->shortTime($entry->break_start),
                    'break_end' => $this->shortTime($entry->break_end),
                    'late' => $entry->late,
                    'extra_hours' => $entry->extra_hours,
                    'extra_minutes' => $entry->extra_minutes,
                ];

                $this->line(sprintf(
                    '  <fg=green>OK</> Empleado %s | %s | entrada %s | salida %s | retardo %s min | extra %sh %smin',
                    $day['emp_code'],
                    $day['date'],
                    $this->shortTime($entry->check_in) ?? '-',
                    $this->shortTime($entry->check_out) ?? '(sin salida)',
                    $entry->late ?? 0,
                    $entry->extra_hours ?? 0,
                    $entry->extra_minutes ?? 0
                ));

                Log::info(self::TAG . ': día creado | Empleado ' . $day['emp_code'] . ' | Fecha ' . $day['date'], [
                    'payroll_user_id' => $entry->id,
                    'payroll_id' => $day['payroll_id'],
                    'check_in' => $this->shortTime($entry->check_in),
                    'check_out' => $this->shortTime($entry->check_out),
                    'nota' => 'backfill histórico (sin pausa automática)',
                ]);
            } catch (Throwable $e) {
                $this->errors[] = [
                    'emp_code' => $day['emp_code'],
                    'date' => $day['date'],
                    'motivo' => 'No se pudo crear el día: ' . $e->getMessage(),
                ];
                $this->line('  <fg=red>ERROR</> Empleado ' . $day['emp_code'] . ' | ' . $day['date'] . ' | ' . $e->getMessage());

                Log::error(self::TAG . ': error al crear el día', [
                    'emp_code' => $day['emp_code'],
                    'date' => $day['date'],
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->newLine();
    }

    /**
     * Escribe el día en payroll_user con los valores ya calculados.
     * Usa endBreak() del modelo para que break_minutes se calcule igual que en el
     * flujo normal (soporta comida que cruza la medianoche).
     */
    private function writeDay(array $day): PayrollUser
    {
        $entry = PayrollUser::create([
            'date' => $day['date'],
            'user_id' => $day['employee_id'],
            'payroll_id' => $day['payroll_id'],
            'check_in' => $day['check_in'],
            'check_out' => $day['check_out'],
            'break_start' => $day['break_start'],
        ]);

        if ($day['break_end']) {
            $entry->endBreak($day['break_end']);
        }

        // Recalcula retardo y horas extra con el turno ACTUAL (ya corregido)
        $entry->calculateLate();
        $entry->calculateExtraTime();

        return $entry->refresh();
    }

    // ------------------------------------------------------------------
    //  Bitácora
    // ------------------------------------------------------------------

    private function writeAuditReport(bool $apply, array $codes, string $from, string $to, ?string $baseUrl): string
    {
        $directory = storage_path('app/biotime-backfills');
        File::ensureDirectoryExists($directory);

        $path = $directory . DIRECTORY_SEPARATOR . 'backfill_' . now()->format('Ymd_His') . '.json';

        $payload = [
            'generado_en' => now()->toDateTimeString(),
            'modo' => $apply ? 'apply' : 'dry-run',
            'emp_codes' => $codes,
            'rango' => ['from' => $from, 'to' => $to],
            'biotime_url' => $baseUrl ?? '(no resuelta)',
            'biotime_url_configurada' => (string) config('services.biotime.url'),
            'biotime_urls_probadas' => $this->triedUrls,
            'resumen_por_empleado' => $this->summaries,
            'dias_a_crear' => $this->plan,
            'dias_omitidos' => $this->skipped,
            'puntos_manuales' => $this->issues,
            'dias_creados' => $this->applied,
            'errores' => $this->errors,
        ];

        File::put($path, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        return $path;
    }
}
