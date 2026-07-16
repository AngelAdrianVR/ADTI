# ADTI — Project Overview

> **Document version:** July 2026  
> **Purpose:** Provide architectural context for AI assistants without direct codebase access.  
> **Convention:** `{project-root}` refers to `c:\Users\Miguel\Desktop\Sitios web\ADTI`

---

## 1. Tech Stack

### Backend
| Layer | Technology | Version |
|-------|-----------|---------|
| Language | PHP | ^8.2 |
| Framework | Laravel | ^11.9 |
| ORM | Eloquent (Laravel built-in) | — |
| Auth scaffolding | Laravel Jetstream (Inertia stack) | ^5.1 |
| API tokens / SPA auth | Laravel Sanctum | ^4.0 |
| Roles & Permissions | spatie/laravel-permission | ^6.9 |
| File attachments | spatie/laravel-medialibrary | ^11.8 |
| Excel import/export | PhpOffice/PhpSpreadsheet | ^2.2 |
| Frontend ↔ Backend bridge | inertiajs/inertia-laravel + tightenco/ziggy | ^1.0 / ^2.0 |

### Frontend
| Layer | Technology | Version |
|-------|-----------|---------|
| JS framework | Vue 3 (Composition API, SFC) | ^3.3 |
| Build tool | Vite | ^5.0 |
| CSS framework | Tailwind CSS | ^3.4 |
| UI component library | Element Plus | ^2.8 |
| Date utilities | date-fns | ^3.6 |
| Barcode generation | jsbarcode | ^3.11 |
| Event bus (cross-component) | mitt | ^3.0 |

### Database
- **MySQL / MariaDB** (configured via `config/database.php`, connection details in `.env`)

---

## 2. Project Structure

```
{project-root}/
├── app/
│   ├── Actions/Fortify/       # Fortify auth actions (password reset, etc.)
│   ├── Actions/Jetstream/     # Jetstream actions (delete user, etc.)
│   ├── Console/Commands/      # Artisan commands (payrolls:close, extra-hours:backfill-status)
│   ├── Http/
│   │   ├── Controllers/       # 19 controllers (see module index below)
│   │   ├── Middleware/        # Custom middleware
│   │   └── Requests/         # Form request validation
│   ├── Models/               # 23 Eloquent models
│   ├── Providers/            # App, Fortify, Jetstream service providers
│   └── Services/             # ExtraHourApprovalService
├── config/                   # Laravel config (app, auth, database, jetstream, permission, etc.)
├── database/
│   ├── migrations/           # ~35 migration files
│   ├── seeders/              # DatabaseSeeder & domain seeders
│   └── factories/            # UserFactory
├── resources/
│   ├── css/                  # Tailwind entry
│   ├── js/
│   │   ├── Pages/            # Inertia page components (one folder per feature)
│   │   ├── Components/       # Shared Vue components + MyComponents/ custom widgets
│   │   ├── Composables/      # payroll/ composable for reusable logic
│   │   ├── Layouts/          # App layout (sidebar nav, header)
│   │   └── eventBus.js       # mitt event bus
│   └── views/                # Blade fallback / mail templates
├── routes/
│   ├── web.php               # All web routes (auth + public landing)
│   ├── api.php                # Sanctum-protected /api/user
│   └── console.php            # Artisan command scheduling
├── lang/es/ & lang/en/       # Localization
├── tests/                    # PHPUnit tests (Feature + Manual)
├── composer.json             # PHP dependencies
├── package.json              # JS dependencies
├── vite.config.js            # Vite + Laravel plugin
└── tailwind.config.js        # Tailwind configuration
```

### Naming conventions
- **Controllers:** PascalCase singular resource name + `Controller` (e.g. `ProductController`)
- **Models:** PascalCase singular (e.g. `PayrollUser`, `ExtraHourApprovalGroup`)
- **Vue pages:** PascalCase folders under `resources/js/Pages/`
- **Routes:** Laravel resource routing (`Route::resource('products', ...)`) with custom prefixes as needed
- **Database:** Snake_case, Laravel conventions (`user_id` foreign keys, timestamps)

---

## 3. Architectural Decisions

| Decision | Choice | Rationale |
|----------|--------|-----------|
| Monolith SPA | Laravel + Inertia + Vue 3 | Single codebase; no separate API needed for the UI; Inertia avoids building a full REST API |
| Auth | Laravel Jetstream (Inertia stack) + Sanctum + Spatie Permissions | Session-based auth for the SPA; Sanctum for API tokens; Spatie for RBAC roles/permissions |
| State management | Inertia props (server-driven) + Vue 3 reactive refs | No Vuex/Pinia; each page receives its data as Inertia props; complex in-page state uses Composition API |
| UI library | Element Plus | Provides ready-made tables, forms, modals, notifications across all CRUD UIs |
| File uploads | spatie/laravel-medialibrary | Multi-file media with conversions; used for user photos, product images, category images |
| Excel | PhpSpreadsheet directly | Manual import/export logic in controllers (no Laravel Excel wrapper) |
| Role authorization | Spatie permissions + `employees_in_charge` hierarchy | Global permissions (e.g. "Ver incidencias") plus per-user subordinate lists stored in JSON column |
| Payroll attendance | `payroll_user` pivot table | Many-to-many between User and Payroll with extra date-specific columns |
| BioTime integration | HTTP endpoint `/api/process-transaction/{time}/{emp_code}` + `bio_time_transactions` table | External Python script pushes attendance data; processed synchronously in controller |
| Extra hours workflow | Multi-level approval with groups | `ExtraHourApprovalService` manages state machine; desnormalized status column for fast queries |
| Product part numbers | `part_number` column with UNIQUE constraint | Race-condition fix documented in repo memory |

---

## 4. Module Index

| # | Module | Description | Context file |
|---|--------|-------------|--------------|
| 1 | Auth & Settings | Authentication (Fortify/Jetstream), RBAC roles/permissions, departments, job positions, holidays, features | `02-module-auth-and-settings.md` |
| 2 | Users | Employee CRUD, profiles, org_props JSON, attendance self-service, performance tracking | `03-module-users.md` |
| 3 | Catalog | Category → Subcategory (hierarchical) → Products with part numbers, images, Excel import/export, barcodes | `04-module-catalog.md` |
| 4 | Payroll | Biweekly payroll periods, attendance processing (check-in/out, late, incidences), break tracking, GPS locations | `05-module-payroll.md` |
| 5 | Extra Hours | Configurable costs (by day/range/user), multi-level approval groups with state machine, bulk decisions | `06-module-extra-hours.md` |
| 6 | Vacations | Vacation requests with balance validation, 15-day advance rule, approval workflow, manual adjustments | `07-module-vacations.md` |
| 7 | Projects & Time Tracking | Project CRUD with budgeted tasks, real-time time tracking with pause/resume, consumption analysis | `08-module-projects.md` |
| 8 | BioTime Integration | External biometric system integration via API endpoints, transaction counting | `09-module-biotime.md` |
| 9 | Landing Page | Public product catalog: browse categories → subcategories → product detail, search | `10-module-landing-and-kiosk.md` |
| 10 | Kiosk | Attendance check-in/out terminal interface | `10-module-landing-and-kiosk.md` |
| 11 | Frontend | Vue 3 + Inertia architecture, layout, shared components, composables, Element Plus integration | `11-module-frontend.md` |

---

## 5. Key Configuration Files

| File | What it controls |
|------|-----------------|
| `config/auth.php` | Guard = `web` (session), provider = `users` (Eloquent) |
| `config/jetstream.php` | Features: profile photos, API tokens, teams disabled |
| `config/fortify.php` | 2FA, password confirmation, registration settings |
| `config/permission.php` | Spatie permissions table names, default guard = `web` |
| `config/media-library.php` | Media disk, conversions, model configuration |
| `config/database.php` | MySQL connection (default), like `utf8mb4_unicode_ci` |

---

## 6. Important Notes for Contributors

- **Race condition on `part_number`**: The `Product` model's `part_number` generation has been hardened with DB row locks and a UNIQUE constraint. Any new product creation logic MUST follow the same serialization pattern (see repo memory `race-condition-fix.md`).
- **`org_props` JSON column on `users`**: Stores flexible employee metadata (position, department, work_shift, vacations balance, etc.). Cast to array. Many queries filter/access via `->org_props['key']`.
- **Extra hours approval uses desnormalized columns**: `payroll_user.extra_hour_status` and `current_approval_level_id` are duplicated from the full `extra_hour_approval_decisions` table for fast index lookups. The `ExtraHourApprovalService` keeps them in sync — never update them directly.
- **No separate REST API**: All UI interactions go through Inertia (server-rendered props). The only true API endpoints are `/api/user` (Sanctum) and the BioTime webhooks.
- **Turno nocturno support**: Attendance logic handles shifts that cross midnight (up to 18-hour window for open check-ins).
