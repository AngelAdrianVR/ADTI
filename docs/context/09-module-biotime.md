# 09 — BioTime Integration Module

---

## Business Purpose

Integrates with an external biometric time-clock system (BioTime) via HTTP API endpoints. An external Python script pushes attendance transactions to Laravel, which processes them into `payroll_user` records. The module also tracks the volume of processed transactions for monitoring.

---

## Key Files

| File | Role |
|------|------|
| `app/Http/Controllers/PayrollUserController.php` | `processBioTimeTransaction()` — main processing endpoint |
| `app/Http/Controllers/BioTimeTransactionsController.php` | `getTotalProcessedCount()` — transaction counter |
| `app/Models/BioTimeTransactions.php` | Transaction log: `date`, `quantity` |
| `app/Console/Commands/` | `payrolls:close`, `extra-hours:backfill-status` Artisan commands |

---

## API Endpoints (Public — no auth required)

### `GET /api/process-transaction/{time}/{emp_code}`

**Called by:** External Python script (BioTime connector)

**Parameters:**
- `time` — DateTime string in URL path (format: `Y-m-d H:i:s`)
- `emp_code` — Employee code matching `users.code`
- Query params (optional): `location_in`, `location_out`

## Processing Logic (Updated Jul 2026)

1. Looks up user by `users.code = emp_code` **AND** `is_active = true`. Returns silently if not found.
   - Logs: `"No se encontró al empleado con código {code}"`
2. Parses the `time` parameter (replaces `+` with space first).
3. Determines payroll by finding one whose 14-day window contains the punch date.
4. Determines if this is a **check-in** or **check-out**:
   - Open entry (check-in exists, no check-out, < 18 hrs) → possibly closing a night shift.
   - No existing record for this user+date → creates new record with `check_in`.
   - Existing record with `break_start` and no `break_end` → detects lunch return (20-180 min window).
   - Existing record with `check_in`, no `check_out` → check-out or lunch break detection.
   - **Weekend re-entry** (NEW): On Sat/Sun, if both `check_in` and `check_out` exist → reopens shift 
     (`check_out = null`) instead of calling `setPause()`. Logs: `"Reapertura de turno en fin de semana"`.
5. After each punch: calls `calculateLate()` and `calculateExtraTime()`.
6. Increments `bio_time_transactions` counter for the **punch date**.
7. **Diagnostic log** (NEW): Every punch now logs:
   - `Log::info("BioTime Sync: {action} | Empleado {code} | Fecha {date} ({dayOfWeek}) | Hora {time}")`
   - Includes `payroll_user_id`, `check_in`, `check_out`, and `is_weekend` in context.

### Break Duration Calculation (Fixed Jul 2026)
- MySQL `TIME` columns return `HH:MM:SS` format. The code normalizes to `HH:MM` via `substr($time, 0, 5)` 
  before parsing with `Carbon::createFromFormat('H:i', ...)`. This fixes the "Trailing data" Carbon error 
  that caused `break_minutes` to always be `0`.
- Affected methods: `PayrollUser::endBreak()` and `PayrollUserController::processBreakUpdate()`.

### `GET /api/get-total-processed-count/`

**Called by:** Monitoring / Python script

**Query params:**
- `start_date`, `end_date` (optional) — filter by date range.

**Returns:** `{ "transactions": <sum of quantity> }`

---

## Artisan Commands

### `payrolls:close`
Route: `GET /payrolls-close` (also callable via Artisan)  
Sets the active payroll's `is_active = false`.

### `extra-hours:backfill-status`
Route: `GET /backfill-status`  
Backfills `extra_hour_status` and `current_approval_level_id` for all `payroll_user` records that have extra time but no status set. Useful after schema migrations or data fixes.

### `biotime:backfill`
Route: `GET /backfill-biotime` (ver sección *Backfill histórico*)

Recupera checadas históricas de BioTime y las escribe en `payroll_user`. Solo escribe
los días que **no tienen ningún registro previo** para ese empleado.

```bash
# Reporte (dry-run, NO escribe nada)
php artisan biotime:backfill --emp=63,64,65 --from=2026-09-01 --to=2026-09-21

# Aplicar
php artisan biotime:backfill --emp=63,64,65 --from=2026-09-01 --to=2026-09-21 --apply
```

Opciones: `--exclude=YYYY-MM-DD,...`, `--biotime-url=http://IP:81`, `--probe`, `--page-size=200`, `--max-pages=50`.

### `biotime:backfill --probe`
Diagnóstico de conectividad. Prueba todas las direcciones candidatas de BioTime desde el
servidor donde corre el ERP y dice cuál funciona. **No** consulta checadas ni escribe nada.

```bash
php artisan biotime:backfill --probe
php artisan biotime:backfill --probe --biotime-url=http://192.168.1.50:81
```

El mismo diagnóstico está disponible en el navegador: `/backfill-biotime?probe=1`
(y con dirección puntual: `/backfill-biotime?probe=1&biotime_url=http://192.168.1.50:81`).

### Resolución de la dirección de BioTime
Orden de prioridad:
1. `--biotime-url=...` (o `&biotime_url=...` en el enlace) — no requiere desplegar nada.
2. `BIOTIME_URL` del `.env`.
3. **Autodetección**: se prueban TODAS las IPv4 locales del servidor empezando por
   `127.0.0.1` (el caso normal es que el ERP corra en la misma máquina que BioTime) y se
   usa la primera con la que BioTime entregue un token. Todas las direcciones probadas y su
   resultado quedan en el reporte (`biotime_urls_probadas`) y en el log.

⚠️ Los errores de conexión (`curl error 7`) significan que **el servidor del ERP no alcanza**
esa dirección en el puerto 81. Ver la sección de solución de problemas.


---

## Backfill histórico (recuperación de días perdidos)

**Archivos:** `app/Console/Commands/BackfillBioTime.php`, `app/Http/Controllers/BioTimeBackfillController.php`

### Por qué existe
El endpoint `/api/process-transaction/*` es *fire-and-forget*: responde 200 aunque
interno no haya escrito nada, y no guarda ninguna cola de checadas rechazadas. Por eso
una checada que el ERP descartó (empleado inactivo, sin catorcena, día ya cerrado, etc.)
**no se recupera sola**: hay que volver a empujarla. Además, el plugin de Python
(`sync_script.py`) mantiene una "aguja" en `last_sync.txt` que nunca retrocede, así que
todo lo anterior a esa marca es invisible para él.

### Reglas del backfill
1. **Nunca sobreescribe.** Si el día ya tiene CUALQUIER registro en `payroll_user` para
   ese empleado, se omite y solo se reporta.
2. **Sin `setPause()`.** En modo histórico nunca se llama `User::setPause()`; ese método
   usa `now()` y contamina el día actual (es el bug que provoca que una checada de un día
   ya cerrado pause al empleado en el día de hoy).
3. **Catorcena correcta.** Se escribe en la catorcena que contiene la fecha. Si ninguna
   la cubre, el día se reporta como "requiere decisión manual" y NO se escribe (no se usa
   la nómina activa como respaldo).
4. **Idempotente.** Se puede volver a ejecutar: los días ya creados quedan como omitidos.

### Emparejamiento
- La primera checada del día es la entrada y la última la salida.
- Las checadas intermedias delimitan la comida (primera = inicio, última = fin).
- **Turno nocturno:** si todas las checadas de un día son de madrugada (antes de las
  12:00) y el día anterior quedó abierto dentro de la ventana de 18 horas, se consideran
  el cierre de ese turno (misma idea que `$openEntry` del endpoint).
- Anti-ráfaga: se descartan checadas duplicadas a 3 minutos o menos de la anterior.
- `late` y `extra_hours`/`extra_minutes` se recalculan con el turno ACTUAL del empleado.

### Uso desde el navegador (para RH)
```
/backfill-biotime?emp=63,64,65&from=2026-09-01&to=2026-09-21          → reporte
/backfill-biotime?emp=63,64,65&from=2026-09-01&to=2026-09-21&apply=1&confirm=SI  → aplica
```
Autorización: la llave `BACKFILL_KEY` del `.env` (`?key=...`) **o** una sesión abierta en
el ERP. En `APP_ENV=local` no se pide nada.

### Cuando el ERP no puede alcanzar el reloj (ERP en VPS + reloj en red local)

Si el ERP vive en un VPS, es imposible que llegue a la IP privada del reloj
(ej. `192.168.0.55`). En ese escenario el flujo es:

```
PC del reloj (LAN)                              VPS (ERP)
┌──────────────────────────────┐               ┌────────────────────────────────┐
│ backfill_biotime.bat         │  HTTPS POST   │ POST /api/biotime-punches      │
│  └ backfill_biotime.ps1      │ ────────────► │  BioTimeBackfillController     │
│     lee la API del reloj     │               │   └ biotime:backfill --source=file
└──────────────────────────────┘               └────────────────────────────────┘
```

**Script de consola (sin Python, sin compilar nada):** carpeta del sincronizador de BioTime.

| Archivo | Rol |
|---|---|
| `backfill_biotime.bat` | Se ejecuta con **doble clic**; lanza el `.ps1` con la política de ejecución correcta |
| `backfill_biotime.ps1` | Obtiene las checadas del reloj (con paginado) y las envía al ERP |
| `backfill.ini` | Configuración: URL del ERP, llave, reloj, credenciales y valores por defecto |

Uso interactivo (doble clic): pregunta empleados, rango de fechas y si se aplican los cambios.
Uso por consola:

```powershell
# Reporte (no escribe nada)
.\backfill_biotime.ps1 -From 2026-09-01 -To 2026-09-21 -Emp 63,64,65

# Aplicar
.\backfill_biotime.ps1 -From 2026-09-01 -To 2026-09-21 -Emp 63,64,65 -Apply

# Solo descargar las checadas a un archivo (sin tocar el ERP)
.\backfill_biotime.ps1 -From 2026-09-01 -To 2026-09-21 -Out checadas.json

# Reenviar un archivo ya descargado
.\backfill_biotime.ps1 -PunchesFile checadas.json -Apply
```

Prioridad de los datos: parámetros > contenido del archivo JSON > valores de `backfill.ini`.

**Endpoint receptor** (en `routes/api.php`, sin CSRF por ser ruta de API):

```
POST /api/biotime-punches
Encabezado: X-Backfill-Key: <BACKFILL_KEY>
Cuerpo: {"emp_codes":["63"],"from":"2026-09-01","to":"2026-09-21","apply":false,
         "punches":[{"emp_code":"63","punch_time":"2026-09-01 08:55:12"}]}
Respuesta: el reporte en texto plano + encabezado X-Backfill-Exit (0 = sin incidencias)
```

Guardado de cada envío: `storage/app/biotime-punches/incoming_YYYYmmdd_His.json`.
La llave es obligatoria en producción (si `BACKFILL_KEY` está vacío, el endpoint responde 403).

### ⚠️ Al desplegar
Además del comando y el controlador, hay que desplegar **`routes/api.php`**; si no, el
endpoint responde `404 The route api/biotime-punches could not be found`.
Y confirmar que el `.env` de producción tenga **`APP_DEBUG=false`** (un 404 estaba
devolviendo el stack trace completo con rutas del servidor).


### Bitácora
Cada corrida deja `storage/app/biotime-backfills/backfill_YYYYmmdd_His.json` con el plan
completo, y registra cada día creado en `storage/logs/laravel.log` con la etiqueta
`BioTimeBackfill`.

### Variables de entorno
`BIOTIME_URL` (ej. `http://127.0.0.1:81`), `BIOTIME_PORT`, `BIOTIME_USER`,
`BIOTIME_PASS`, `BIOTIME_TIMEOUT`, `BIOTIME_PAGE_SIZE`, `BACKFILL_KEY`.

### Prueba manual
```bash
php tests/Manual/BackfillSimulation.php
```
Valida el emparejamiento (día normal, comida, fin de semana, turno nocturno, anti-ráfaga,
día ya existente) y la escritura en `payroll_user`, dentro de una transacción que se revierte.

### Solución de problemas de conexión con BioTime

Síntoma: `curl error 7: Failed to connect to ... port 81` / `Connection refused`.
Significa que **el servidor donde corre el ERP no alcanza el reloj** en esa dirección.

1. **Obtener la IP del reloj** (en la PC donde está instalado BioTime Server):
   - `ipconfig` → "Dirección IPv4" del adaptador activo (ej. `192.168.1.50`).
   - Confirmar en esa misma PC que `http://localhost:81` abre BioTime.
   - Las terminales/relojes quedan configuradas con esa IP; también aparece en BioTime → Dispositivos.
2. **Obtener las IP del servidor del ERP**: `ipconfig /all` (Windows) o `ip a` (Linux).
3. **Probar**: `php artisan biotime:backfill --probe` o `/backfill-biotime?probe=1`.
   Equivalente manual: `curl -v http://IP:81/api-token-auth/`.
4. **Según la topología**:

   | Escenario | Solución |
   |---|---|
   | ERP y reloj en la misma máquina | `BIOTIME_URL=http://127.0.0.1:81` |
   | ERP y reloj en la misma LAN | `BIOTIME_URL=http://IP_LAN_DEL_RELOJ:81` + permitir TCP 81 en el firewall de esa PC |
   | ERP remoto y reloj en la oficina | VPN/túnel (recomendado) o publicar el puerto 81 con reenvío en el router |

   ⚠️ La autodetección usa las IP locales **del servidor del ERP**; por eso puede proponer una
   IP pública del propio servidor (ej. `37.60.250.80`) que no tiene ruta al reloj.
   ⚠️ Publicar el puerto 81 hacia internet manda credenciales de BioTime en HTTP sin cifrar:
   preferir VPN/túnel.
6. **Lectura de los errores de conexión**:

   | Error | Significado |
   |---|---|
   | `cURL error 7: Failed to connect ... Could not connect to server` | La máquina responde pero el puerto 81 está cerrado, o BioTime no está corriendo ahí |
   | `cURL error 28: Connection timed out` | Nadie contestó: la PC del reloj está apagada/dormida, un firewall descarta los paquetes, o **no hay ruta** (IP privada desde otra red) |
   | `HTTP 400 Unable to log in with provided credentials` | La conexión sí llegó: revisar `BIOTIME_USER` / `BIOTIME_PASS` |
   | `HTTP 404 / 200 sin token` | La conexión llegó pero a otro servicio, o BioTime no es de la versión Pro |

7. **Formato de `BIOTIME_URL`**: se normaliza automáticamente, así que todas estas son válidas:
   `http://192.168.0.55:81`, `192.168.0.55`, `192.168.0.55:81`, `http:192.168.0.55:81`.
   Si el puerto no se indica se usa `BIOTIME_PORT` (81). **Después de editar el `.env` hay que
   ejecutar `php artisan config:clear`** (o `optimize:clear`) para que Laravel tome el cambio.
8. **Prueba del firewall desde otra PC de la oficina**: abrir `http://192.168.0.55:81` en el
   navegador de cualquier otra computadora de la misma red. Si no carga, el puerto 81 no está
   abierto a la LAN (revisar el Firewall de Windows de la PC del reloj).

5. **Credenciales**: `BIOTIME_USER` / `BIOTIME_PASS` deben coincidir con BioTime. Si BioTime
   responde `HTTP 400`, el usuario o la contraseña no son válidos.



---

## Transaction Tracking

The `bio_time_transactions` table stores:
- `date` — which day
- `quantity` — how many transactions were processed

The Python script uses the `getTotalProcessedCount` endpoint to track progress (e.g., "processed 1,500 of 2,000 records today").

---

## Dependencies

- **Payroll module** — `payroll_user` records are created/updated
- **Extra Hours module** — `calculateExtraTime()` triggers workflow initialization
- **Users module** — employee lookup by `code`

---

## Known Limitations & Technical Debt

1. **No authentication on BioTime endpoints**: `/api/process-transaction/*` and `/api/get-total-processed-count/*` are completely public. Anyone who knows the URL format can inject attendance data. This should be protected by an API token or IP whitelist.
2. **GET method for data mutation**: `processBioTimeTransaction` uses GET (not POST). This violates REST conventions and could cause issues with URL length limits, caching proxies, or browser pre-fetch.
3. **No idempotency**: If the Python script sends the same transaction twice, a duplicate `payroll_user` record could be created (check-in case) or a check-out could overwrite an already-closed record. There's no unique constraint on `(user_id, date)` for payroll_user.
4. **Employee code lookup**: Uses `users.code` which is nullable and NOT unique. Employees without a code will never match. If two active users share the same code, only one (by DB order) gets the attendance — the other is silently ignored.
5. **Current payroll assumption**: The code gets the payroll via a 14-day window match or fallback to `is_active = true`. If no payroll exists, the transaction is silently skipped.
6. **No error reporting back to Python**: Errors in processing are logged to Laravel's log but the HTTP response may still be 200. The Python script has no way to detect partial failures.
7. **Timezone handling**: The `time` parameter is parsed without explicit timezone. Relies on server/PHP default timezone configuration.
8. **Weekend batch sync**: The Python sync script runs on an admin PC that is OFF on weekends. On Monday, 
   all weekend punches arrive in rapid succession (seconds apart). The system must correctly handle 
   multiple punches for the same day arriving with no real-time delay. Fixed (Jul 2026) by adding 
   weekend re-entry detection in the `else` branch of `processBioTimeTransaction`.
9. **`setPause()` contamina el día actual**: cuando llega una checada de un día que ya tiene
   `check_in` y `check_out`, el endpoint entra al bloque final y llama `User::setPause()`, que
   usa `now()` — es decir, pausa al empleado HOY y crea/edita el registro de HOY, en lugar de
   guardar la checada del día que la generó. Ocurre con checadas fuera de orden (backlog,
   lunes por la mañana, reenvíos). *Pendiente de corrección.*
10. **El plugin no pagina la API de BioTime**: `get_biotime_transactions()` lee solo la primera
   página de `/iclock/api/transactions/`. Con muchas checadas en la ventana consultada se
   pierden registros silenciosamente. El comando `biotime:backfill` sí pagina.
11. **La "aguja" del plugin nunca retrocede**: `sync_script.py` guarda el último `punch_time`
   enviado en `last_sync.txt` y solo consulta lo posterior a esa marca. Cualquier día que el
   ERP haya descartado queda perdido para siempre y requiere backfill manual.
12. **La sincronización por conteo se puede quedar muda** (`v1`/`v2_sync_script.py`):
   `bio_time_transactions.quantity` se incrementa por CADA checada recibida, incluso si el
   registro fue ignorado o duplicado (`PayrollUserController.php`, línea del `increment` está
   fuera del bloque `$isDuplicate`). Si el contador se infla, el script deja de enviar todo.
   El comando `biotime:backfill` no toca ese contador.
13. **Reapertura de duplicados**: no existe índice único en `payroll_user (user_id, date)`.
   El backfill respeta esa regla verificando la existencia del día antes de escribir, pero la
   protección depende del código, no de la base de datos.

