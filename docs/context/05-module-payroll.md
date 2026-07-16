# 05 — Payroll Module

---

## Business Purpose

Manages biweekly payroll periods and daily attendance records. Each payroll has a 14-day window. For each day in that window, every active employee gets a `payroll_user` record tracking check-in/out times, lateness, extra time, incidences (absences, vacations, sick leave), break durations, and GPS locations. Supports manual attendance editing, incidence assignment, and integration with the BioTime biometric system.

---

## Key Files

| File | Role |
|------|------|
| `app/Http/Controllers/PayrollController.php` | Payroll list, detail view, pre-payroll template, receipts |
| `app/Http/Controllers/PayrollUserController.php` | Attendance CRUD, manual check-in/out, incidence management, extra time approval actions |
| `app/Models/Payroll.php` | Payroll period model with `getProcessedAttendances()` |
| `app/Models/PayrollUser.php` | Pivot model with `getShiftBoundaries()`, `calculateLate()`, `calculateExtraTime()` |
| `app/Models/PayrollComment.php` | Per-user, per-day comments |
| `resources/js/Pages/Payroll/Index.vue` | Payroll list |
| `resources/js/Pages/Payroll/Show.vue` | Payroll detail with attendance grid |
| `resources/js/Pages/Payroll/PrePayrollTemplate.vue` | Printable pre-payroll report |
| `resources/js/Pages/Payroll/PayrollReceiptTemplate.vue` | Printable receipt report |
| `resources/js/Composables/payroll/` | Reusable Vue composables for payroll logic |

---

## Payroll Period Lifecycle

1. **Creation** — A new `Payroll` record is created with `start_date`, `biweekly` number, `is_active=true`.
2. **Attendance collection** — Over 14 days, attendance data comes from:
   - BioTime API (`/api/process-transaction/{time}/{emp_code}`)
   - Manual platform input (`POST /payroll-users/set-attendance`)
   - Manual editing (`PUT /payroll-users/update-attendance`)
3. **Closing** — `php artisan payrolls:close` (Artisan command) sets `is_active=false`. Only one payroll is active at a time.
4. **Reporting** — Pre-payroll template and receipt views for printing/PDF.

---

## Main Endpoints

### Payroll Views
| Route | Action |
|-------|--------|
| `GET /payrolls` | `PayrollController@index` — List with extra hour cost/group counts |
| `GET /payrolls/{payroll}` | `PayrollController@show` — Detail grid with prev/next navigation |
| `GET /payrolls/{payroll}/pre-payroll` | Filterable pre-payroll printable template |
| `GET /payrolls/{payroll}/receipts` | Printable receipt template |

### Attendance Management
| Route | Action |
|-------|--------|
| `POST /payroll-users/set-attendance` | Manual check-in/out (from NoAttendanceCard) |
| `PUT /payroll-users/update-attendance` | Update times for an existing record |
| `PUT /payroll-users/set-incidence` | Set incidence type (Falta, Vacaciones, Incapacidad, etc.) |
| `PUT /payroll-users/remove-late` | Clear lateness flag |

### Comments
| Route | Action |
|-------|--------|
| `GET|POST|PUT|DELETE /payroll-comments` | CRUD for per-user, per-day comments |

### Extra Time Actions (quick actions — see also 06-extra-hours)
| Route | Action |
|-------|--------|
| `PUT /payroll-users/approve-extra-time` | Direct approval (single-level) |
| `PUT /payroll-users/revert-extra-time` | Revert approval |
| `PUT /payroll-users/reject-extra-time` | Reject extra time |
| `GET /payroll-users/recalculate-extra-time` | Recalculate extra hours for a record |
| `PUT /payroll-users/clear-extra-time` | Clear extra time values |
| `PUT /payroll-users/set-project` | Assign payroll day to a project |

---

## Core Business Logic

### Shift Boundaries (`PayrollUser::getShiftBoundaries()`)
Reads `user.org_props.work_shift` and returns `[start_of_shift, end_of_shift]` as Carbon objects. For night shifts crossing midnight, `end_of_shift` gets `addDay()`.

Known shifts:
- `Turno 1 (06:00 - 14:00)`
- `Turno 2 (14:00 - 22:00)`
- `Turno 3 (09:00 - 18:00)` (default)

### Late Calculation (`calculateLate()`)
Compares `check_in` against shift start. If check-in is after start, sets `late` to the difference in minutes. If no check-in, late = 0.

### Extra Time Calculation (`calculateExtraTime()`)
Compares `check_out` against shift end. If check-out is after end, computes `extra_hours` and `extra_minutes`. **Also triggers `ExtraHourApprovalService::initializeWorkflow()`** to set the `extra_hour_status` and `current_approval_level_id` if extra time is detected.

### Attendance Processing (`Payroll::getProcessedAttendances()`)
For a given user within a payroll period:
1. Fetches all `payroll_user` records for that user + payroll.
2. Iterates through all 14 days of the period.
3. For each day: determines if it's a rest day (weekend, holiday), vacation day, or work day.
4. Computes whether the user was absent, late, or present.
5. Accepts pre-loaded attendances and holidays collections to avoid N+1 queries.

### Break/Pause Tracking
- `break_start` / `break_end` / `break_minutes` on `payroll_user`
- Processed via `processBreakUpdate()` in `PayrollUserController`
- Break minutes are subtracted from total work time for accurate extra hour calculation

---

## Visibility Rules (in `getUserProcessedInfo()`)

1. **Global "Ver incidencias" permission** → See all employees.
2. **No global permission but has `employees_in_charge`** → See only subordinates + self.
3. **Neither** → See nothing (empty result).

Users with `org_props->position = 'Dirección'` or `'Soporte DTW'` are always excluded from attendance lists.

---

## Dependencies

- **Users module** — employee data, `org_props.work_shift`, `employees_in_charge`
- **Extra Hours module** — `ExtraHourApprovalService::initializeWorkflow()` called from `calculateExtraTime()`
- **Vacations module** — vacation days are reflected as incidences
- **Holidays** — affect rest day determination
- **BioTime module** — primary source of check-in/out data
- **Projects module** — optional project assignment per day

---

## Known Limitations & Technical Debt

1. **Single active payroll**: The system assumes only one payroll is active at a time (`Payroll::getCurrent()` returns latest by ID). Multiple concurrent payrolls are not supported.
2. **`getProcessedAttendances()` is heavy**: Processes all 14 days for each user, with nested Carbon date comparisons. Pre-loading strategy helps but the method is monolithic.
3. **Manual attendance vs BioTime**: `checked_in_platform=true` marks platform-entered records. These are treated identically to BioTime records for calculation purposes, but admins need to know the source for auditing.
4. **No bulk incidence assignment**: Incidences are set one user at a time via `set-incidence`. For company-wide holidays or events, this could be tedious.
5. **GPS locations are raw strings**: `check_in_location` and `check_out_location` store whatever the client sends. No validation, geocoding, or reverse-geocoding.
6. **The 18-hour night shift window**: The `getNextAttendance()` method's 18-hour heuristic is arbitrary and not configurable per shift type.
