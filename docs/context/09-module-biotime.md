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

**Processing logic:**
1. Looks up user by `users.code = emp_code`. Returns 404 if not found.
2. Parses the `time` parameter.
3. Determines if this is a **check-in** or **check-out**:
   - If no existing record for this user+date with a check-in → it's a check-in.
   - If there's an open record (check-in exists, no check-out) → it's a check-out.
4. Creates or updates a `payroll_user` record:
   - On check-in: creates record with `date`, `user_id`, `payroll_id` (current payroll), `check_in`, optional `check_in_location`.
   - On check-out: updates the open record with `check_out`, optional `check_out_location`.
5. After update: calls `calculateLate()` and `calculateExtraTime()` (which triggers extra hours workflow initialization).
6. Logs the transaction in `bio_time_transactions` (increments count for the date).

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
4. **Employee code lookup**: Uses `users.code` which is nullable. Employees without a code will never match, silently dropping their BioTime data.
5. **Current payroll assumption**: The code gets the "current" payroll via `Payroll::getCurrent()` (latest by ID). If no payroll exists, this will fail.
6. **No error reporting back to Python**: Errors in processing are logged to Laravel's log but the HTTP response may still be 200. The Python script has no way to detect partial failures.
7. **Timezone handling**: The `time` parameter is parsed without explicit timezone. Relies on server/PHP default timezone configuration.
