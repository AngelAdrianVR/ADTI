# 03 — Users Module

---

## Business Purpose

Employee lifecycle management: CRUD, profile data (including the flexible `org_props` JSON for department, position, shift, vacation balance, salary, IMSS, etc.), self-service attendance/payroll views, performance tracking, and manual attendance recording.

---

## Key Files

| File | Role |
|------|------|
| `app/Http/Controllers/UserController.php` | Main controller (~500+ lines) |
| `app/Models/User.php` | Central model with multiple relationships |
| `app/Models/UserVacationAdjustment.php` | Manual vacation balance adjustments |
| `resources/js/Pages/User/Index.vue` | Employee list with weekly time summary |
| `resources/js/Pages/User/MyPayrolls.vue` | Employee self-service payroll view |
| `resources/js/Pages/Profile/*` | Jetstream profile pages |

---

## Main Endpoints

### User CRUD
| Route | Action |
|-------|--------|
| `GET /users` | `UserController@index` — List with weekly time totals |
| `GET /users/create` | Create form |
| `POST /users` | Store new user |
| `GET /users/{user}` | Show user detail |
| `GET /users/{user}/edit` | Edit form |
| `PUT /users/{user}` | Update user |
| `DELETE /users/{user}` | Delete user |

### User Management Actions
| Route | Action |
|-------|--------|
| `POST /users/update-with-media/{user}` | Update user with file attachments (Spatie Media) |
| `POST /users/massive-delete` | Bulk delete users |
| `POST /users/massive-delete-media` | Bulk delete media files |
| `POST /users/{user}/store-media` | Add media files to user |
| `PUT /users/{user}/reset-password` | Admin password reset |
| `POST /users/inactivate/{user}` | Soft-deactivate (sets `is_active=false`, reason, date) |
| `GET /users/reactivatation/{user}` | Reactivate user |
| `PUT /users/{user}/update-vacations` | Update vacation balance in `org_props` |
| `PUT /users/{user}/toggle-home-office` | Toggle `home_office` boolean |
| `PUT /users/media/{media}/update-name` | Rename a media file |

### Attendance Self-Service
| Route | Action |
|-------|--------|
| `GET /users-get-next-attendance` | Determines if user should check IN or OUT next |
| `GET /users-get-pause-status` | Gets pause/break status |
| `GET /users-set-pause` | Toggles pause/break state |
| `POST /users-set-attendance` | Records check-in or check-out (manual from platform) |

### Vacation Adjustments
| Route | Action |
|-------|--------|
| `POST /users/{user}/vacation-adjustments` | Add manual adjustment (positive or negative days) |
| `DELETE /users/{user}/vacation-adjustments/{adjustment}` | Remove an adjustment |

### Performance & Payroll Self-Service
| Route | Action |
|-------|--------|
| `GET /users/{user}/performance` | Performance metrics for a user |
| `GET /my-payrolls` | `UserController@myPayrolls` — Employee's own payroll history |

---

## The `org_props` JSON Column

This is the most important flexible data structure. The `org_props` JSON column on `users` stores:

```json
{
  "position": "Dirección" | "Soporte DTW" | ...,
  "department": "Engineering",
  "work_shift": "Turno 1 (06:00 - 14:00)" | "Turno 2 (14:00 - 22:00)" | "Turno 3 (09:00 - 18:00)" | ...,
  "vacations": 12.5,
  "salary": 15000.00,
  "imss": "...",
  ...
}
```

**Critical:** Queries frequently filter by `org_props->position` (e.g. excluding "Soporte DTW" and "Dirección" from employee lists). The `work_shift` value is parsed in `PayrollUser::getShiftBoundaries()` to determine shift start/end times.

---

## Attendance Logic (`getNextAttendance`)

The method on `User` model determines the next action for self-service check-in:

1. **Find open shift**: Look for a `payroll_user` record with `check_in` but no `check_out`, ordered by date desc.
2. **Validate recency**: If the check-in was less than 18 hours ago → suggest "Registrar salida" (check out).
3. **Today's completed shift**: If already checked out today → suggest "Registrar entrada" (check in for next day).
4. **No record today**: Check if today is a rest day (weekend/holiday) → if not, suggest "Registrar entrada".

The 18-hour window handles night shifts that cross midnight.

---

## Performance Tracking

`getPerformance` sums `time_entries.total_duration_seconds` for the user, grouped by project/client, providing consumed hours. It also aggregates weekly time in `UserController@index` via `timeEntries` relationship.

---

## Dependencies

- **Payroll module** — attendance records are stored in `payroll_user`
- **Projects module** — `timeEntries` for performance metrics
- **Vacations module** — `vacationRequests`, `userVacationAdjustments`
- **Auth module** — roles, permissions
- **BioTime module** — external attendance data feeds into `payroll_user`

---

## Known Limitations & Technical Debt

1. **`getNextAttendance` is complex**: The 18-hour heuristic for night shifts can fail for unusual shift patterns. It's tightly coupled to the `PayrollUser` model and the `org_props.work_shift` format.
2. **No dedicated attendance service**: All attendance logic lives in `UserController` and `User` model methods. Consider extracting to a service if complexity grows.
3. **`org_props` has no schema validation**: Any key can be set; typos in `work_shift` values break the `getShiftBoundaries()` match expression (falls back to "Turno 3").
4. **Massive delete/media operations**: These are simple loops in the controller — no queue/job usage for large datasets.
5. **Inactivation is not a true soft delete**: Users are flagged `is_active=false` but remain in the database. Queries must remember to filter by `is_active`.
