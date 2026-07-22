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
