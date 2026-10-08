# 02 — Auth & Settings Module

---

## Business Purpose

Handles user authentication (login, registration, password reset, 2FA), role-based access control, and administrative configuration (departments, job positions, holidays, features, measure units). This module governs who can access the system and what they can do.

---

## Key Files

| File | Role |
|------|------|
| `config/auth.php` | Guard = `web`, provider = `users` |
| `config/fortify.php` | 2FA settings, password rules |
| `config/jetstream.php` | Features toggle (teams = disabled) |
| `config/permission.php` | Spatie permission config |
| `app/Actions/Fortify/*` | Password reset, password update actions |
| `app/Actions/Jetstream/*` | Delete user action |
| `app/Http/Controllers/SettingController.php` | Settings page hub + CRUD for roles/permissions |
| `app/Http/Controllers/DepartmentController.php` | CRUD + reassign-and-delete |
| `app/Http/Controllers/JobPositionController.php` | Standard resource CRUD |
| `app/Http/Controllers/HolidayController.php` | CRUD + massiveDelete |
| `app/Http/Controllers/FeatureController.php` | Standard resource CRUD |
| `app/Http/Controllers/MeasureUnitController.php` | Standard resource CRUD |
| `app/Models/Department.php` | `id, name`, hasMany → tasks |
| `app/Models/JobPosition.php` | `id, name` |
| `app/Models/Holiday.php` | `id, name, date, ordinal, week_day, month, is_active, is_custom_date` |
| `app/Models/Feature.php` | `id, name` |
| `app/Models/MeasureUnit.php` | `id, name, abreviation` |
| `resources/js/Pages/Auth/*` | Login, Register, ForgotPassword, ResetPassword, VerifyEmail, ConfirmPassword |
| `resources/js/Pages/Setting/Index.vue` | Tabbed settings UI (categories, permissions, general) |

---

## Main Endpoints (all behind `auth:sanctum` middleware)

### Auth (Jetstream/Fortify — auto-registered)
- `GET /login`, `POST /login`
- `GET /register`, `POST /register`
- `GET /forgot-password`, `POST /forgot-password`
- `GET /reset-password/{token}`, `POST /reset-password`
- `GET /verify-email`, `POST /verify-email/{id}/{hash}`
- `POST /user/confirm-password`
- `GET /user/profile` — profile management
- `POST /user/logout`

### Settings Hub
| Route | Action |
|-------|--------|
| `GET /settings/catalogos` | `SettingController@index` — Tab: categories |
| `GET /settings/permisos` | `SettingController@permissions` — Roles & permissions table |
| `GET /settings/general` | `SettingController@general` — Departments, positions, features tabs |

### Role & Permission CRUD
| Route | Action |
|-------|--------|
| `POST /settings/store-role` | Create role with permissions |
| `PUT /settings/update-role/{role_id}` | Update role name & sync permissions |
| `DELETE /settings/delete-role/{role_id}` | Delete role |
| `POST /settings/store-permission` | Create permission (has `category` field for grouping) |
| `PUT /settings/update-permission/{permission_id}` | Update permission |
| `DELETE /settings/delete-permission/{permission_id}` | Delete permission |

### Resource CRUD (standard Laravel resources)
- `Route::resource('departments', DepartmentController::class)` + `POST departments/{department}/reassign-and-delete`
- `Route::resource('job-positions', JobPositionController::class)`
- `Route::resource('features', FeatureController::class)`
- `Route::resource('holidays', HolidayController::class)` + `POST holidays/massive-delete`
- `Route::resource('measure-units', MeasureUnitController::class)`

---

## Authorization Model

### How it works
- **Spatie Permissions** with guard `web`.
- Permissions have a `category` string for grouping in the UI.
- Roles are assigned permissions. Users are assigned roles.
- Direct user-to-permission assignments are also possible.

### Known permission names (examples inferred from code)
| Permission | Category | Purpose |
|------------|----------|---------|
| `Ver incidencias` | Payroll | View all attendance incidences globally |
| `Gestionar cualquier solicitud de vacaciones` | Vacations | Approve/reject any vacation request |

### Hierarchical visibility
The `User.employees_in_charge` JSON column stores an array of subordinate user IDs. Controllers check this when the user lacks a global permission like "Ver incidencias" — they only see data for employees in that list (plus themselves).

---

## Dependencies

- **Users module** — roles/permissions are assigned to users
- **Projects module** — tasks reference departments
- **Payroll module** — holidays affect attendance calculations

---

## Known Limitations & Technical Debt

1. **Role guard hardcoded**: The `SettingController` forces `guard_name = 'web'` on role creation to avoid Sanctum conflicts. Any new role-creation logic must do the same.
2. **Department reassign**: `reassignAndDelete` allows moving tasks/users from one department to another before deletion. This logic lives entirely in the controller — no service layer.
3. **Holidays are simple**: The `Holiday` model has no relationship beyond its own table. It's referenced manually in `PayrollUser` calculations by fetching all holidays within a date range.
4. **No permission seeding**: Permissions appear to be created manually through the UI rather than via seeders.
5. **Settings tabs are Vue-driven**: The same `Setting/Index.vue` renders different tabs based on the `section` prop from Inertia. CSS classes toggle visibility of the panels.
