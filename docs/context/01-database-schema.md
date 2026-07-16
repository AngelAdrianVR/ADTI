# 01 — Database Schema

> Covers all ~35 migration files, grouped by domain.  
> Tables prefixed with `*` are the most important for understanding business logic.

---

## 1. Core Laravel Tables

| Table | Purpose |
|-------|---------|
| `*users` | Central user/employee table (see §2) |
| `sessions` | Session storage |
| `cache` / `cache_locks` | Application cache |
| `jobs` / `job_batches` / `failed_jobs` | Queue system |
| `password_reset_tokens` | Password resets |
| `personal_access_tokens` | Sanctum API tokens |
| `media` | spatie/laravel-medialibrary (polymorphic file attachments) |

---

## 2. Users Domain

### `*users`
| Column | Type | Notes |
|--------|------|-------|
| `id` | PK | |
| `code` | string, nullable | Employee code |
| `name` | string, UNIQUE | Display name / full name |
| `email` | string, UNIQUE | Login identifier |
| `password` | string | Hashed |
| `phone` | string(20), nullable | |
| `birthdate` | date, nullable | |
| `civil_state` | string, nullable | e.g. "Soltero", "Casado" |
| `address` | text, nullable | |
| `rfc` | string, nullable | Mexican tax ID |
| `curp` | string, nullable | Mexican personal ID |
| `ssn` | string, nullable | Social security number |
| `org_props` | JSON, nullable | **Critical:** work_shift, position, department, vacations balance, IMSS, salary, etc. |
| `is_active` | boolean, default true | Soft-active flag |
| `home_office` | boolean, default false | Remote work toggle |
| `paused` | string, nullable | Pause status for time tracking |
| `employees_in_charge` | JSON, nullable | Array of user IDs this user supervises |
| `inactivate_date` | date, nullable | |
| `inactivate_reason` | text, nullable | |
| `profile_photo_path` | string, nullable | |
| `current_team_id` | FK, nullable | Jetstream (not actively used) |
| `email_verified_at` | timestamp, nullable | |
| `two_factor_*` | various | Fortify 2FA columns |

### `user_vacation_adjustments`
| Column | Type | Notes |
|--------|------|-------|
| `id` | PK | |
| `user_id` | FK → users | Cascade on delete |
| `days` | decimal(8,2) | Positive = add, negative = subtract |
| `notes` | string, nullable | |
| `date` | date | |
| `timestamps` | | |

### Roles & Permissions (Spatie)
| Table | Purpose |
|-------|---------|
| `roles` | Role definitions (guard=web) |
| `permissions` | Permission definitions with `category` column for grouping |
| `model_has_roles` | User ↔ Role pivot |
| `model_has_permissions` | User ↔ Permission pivot (direct assignments) |
| `role_has_permissions` | Role ↔ Permission pivot |

---

## 3. Product Catalog Domain

### `*categories`
| Column | Type | Notes |
|--------|------|-------|
| `id` | PK | |
| `name` | string | |
| `key` | string | short identifier |
| `timestamps` | | |

**Relationships:** `hasMany → subcategories`  
**Media:** Spatie media library (images)

### `*subcategories`
| Column | Type | Notes |
|--------|------|-------|
| `id` | PK | |
| `name` | string | |
| `key` | string | |
| `level` | unsignedSmallInteger | Depth in hierarchy |
| `features` | JSON, nullable | Feature definitions for products in this subcategory |
| `category_id` | FK → categories | Cascade on delete |
| `prev_subcategory_id` | unsignedSmallInteger, nullable | Parent subcategory (null = direct child of category) |
| `timestamps` | | |

**Relationships:** `belongsTo → category`, `hasMany → products`, `hasMany → subcategories` (self-referencing via prev_subcategory_id)  
**Media:** Spatie media library

### `*products`
| Column | Type | Notes |
|--------|------|-------|
| `id` | PK | |
| `name` | string, nullable | |
| `description` | string, nullable | |
| `consecutivo` | unsignedSmallInteger, nullable | Sequential number per subcategory (max 999) |
| `part_number` | string, **UNIQUE** | Internal part number (combined from breadcrumbs + consecutivo) |
| `part_number_supplier` | string, nullable | Manufacturer's part number |
| `location` | string, nullable | Physical location / warehouse |
| `line_cost` | float, nullable | |
| `currency` | string, default "$MXN" | |
| `features` | JSON, nullable | Custom feature key-value pairs |
| `features_keys` | JSON, nullable | Ordered keys used to build part_number |
| `bread_crumbles` | JSON, nullable | Hierarchy path (category → subcategory → ...) |
| `subcategory_id` | FK → subcategories | Cascade on delete |
| `timestamps` | | |

**Relationships:** `belongsTo → subcategory`  
**Media:** Spatie media library (product images, files, barcodes)

### `features`
| Column | Type | Notes |
|--------|------|-------|
| `id` | PK | |
| `name` | string | Feature name used as key in product features JSON |

### `measure_units`
| Column | Type | Notes |
|--------|------|-------|
| `id` | PK | |
| `name` | string | |
| `abreviation` | string | Short form |

---

## 4. Organizational Domain

### `departments`
| Column | Type | Notes |
|--------|------|-------|
| `id` | PK | |
| `name` | string | |

**Relationships:** `hasMany → tasks`

### `job_positions`
| Column | Type | Notes |
|--------|------|-------|
| `id` | PK | |
| `name` | string | |

### `holidays`
| Column | Type | Notes |
|--------|------|-------|
| `id` | PK | |
| `name` | string | |
| `date` | date | Specific date |
| `ordinal` | string, nullable | e.g. "1er" |
| `week_day` | string, nullable | Day of week |
| `month` | string, nullable | Month name |
| `is_active` | boolean | |
| `is_custom_date` | boolean | Whether date can be customized |

---

## 5. Payroll Domain

### `*payrolls`
| Column | Type | Notes |
|--------|------|-------|
| `id` | PK | |
| `start_date` | date | First day of the 14-day period |
| `biweekly` | unsignedTinyInteger | Biweekly period number |
| `is_active` | boolean, default true | Whether payroll is open |

**Relationships:** `belongsToMany → users` via `payroll_user`, `hasMany → extraHourCosts`, `hasMany → approvalLevels`, `hasMany → approvalGroups`

### `*payroll_user` (pivot with extra fields)
| Column | Type | Notes |
|--------|------|-------|
| `id` | PK (incrementing pivot) | |
| `date` | date | Individual day within the payroll period |
| `check_in` | time, nullable | Clock-in time |
| `check_in_location` | string, nullable | GPS coordinates at check-in |
| `check_out` | time, nullable | Clock-out time |
| `check_out_location` | string, nullable | GPS coordinates at check-out |
| `break_start` | time, nullable | Meal/break start |
| `break_end` | time, nullable | Meal/break end |
| `break_minutes` | unsignedSmallInteger, nullable | Calculated break duration |
| `late` | unsignedSmallInteger, default 0 | Minutes late |
| `extra_hours` | smallInteger, nullable | Raw extra hours detected |
| `extra_minutes` | smallInteger, nullable | Raw extra minutes detected |
| `approved_extra_hours` | integer, nullable | Approved extra hours after workflow |
| `approved_extra_minutes` | integer, nullable | Approved extra minutes after workflow |
| `approved_by` | FK → users, nullable | Who gave final approval |
| `approved_at` | timestamp, nullable | When approved |
| `extra_hour_status` | enum: none/pending/approved/rejected | Desnormalized workflow state |
| `current_approval_level_id` | FK → extra_hour_approval_levels, nullable | Which level must decide next |
| `incidence` | string, nullable | e.g. "Día normal", "Falta", "Vacaciones", "Incapacidad" |
| `project_id` | FK → projects, nullable | Project linked to this day's work |
| `additionals` | JSON, nullable | Extra data (e.g. overtime justification) |
| `checked_in_platform` | boolean, default false | Whether attendance was manual (true) or from BioTime (false) |
| `user_id` | FK → users | Cascade on delete |
| `payroll_id` | FK → payrolls | Cascade on delete |

**Relationships:** `belongsTo → user`, `belongsTo → payroll`, `belongsTo → approver`, `belongsTo → project`, `hasMany → approvalDecisions`  
**Index:** Composite index on `(payroll_id, extra_hour_status, current_approval_level_id)`

### `payroll_comments`
| Column | Type | Notes |
|--------|------|-------|
| `id` | PK | |
| `payroll_id` | FK → payrolls | Cascade on delete |
| `user_id` | FK → users | |
| `comments` | text | |
| `date` | date | Which day the comment refers to |

---

## 6. Extra Hours Approval Domain

### `*extra_hour_costs`
| Column | Type | Notes |
|--------|------|-------|
| `id` | PK | |
| `payroll_id` | FK → payrolls | |
| `user_id` | FK → users, nullable | NULL = general cost; specific = override for that user |
| `day_of_week` | unsignedTinyInteger, nullable | 0=Sun…6=Sat; NULL depends on range_type |
| `range_type` | enum: weekday/weekend/specific | |
| `cost_per_hour` | decimal(10,2) | |

**Unique:** `(payroll_id, user_id, day_of_week, range_type)`

### `*extra_hour_approval_groups`
| Column | Type | Notes |
|--------|------|-------|
| `id` | PK | |
| `payroll_id` | FK → payrolls | |
| `name` | string, nullable | e.g. "Supervisores Planta" |

**Pivot:** `extra_hour_approval_group_user` — links groups to employees (`approval_group_id`, `user_id`)

### `*extra_hour_approval_levels`
| Column | Type | Notes |
|--------|------|-------|
| `id` | PK | |
| `payroll_id` | FK → payrolls | (denormalized for compat) |
| `approval_group_id` | FK → extra_hour_approval_groups | |
| `level` | unsignedTinyInteger | 1, 2, 3... |
| `name` | string, nullable | e.g. "Supervisor Directo" |

**Unique:** `(approval_group_id, level)`  
**Pivot:** `extra_hour_approval_level_user` — links levels to approvers (`approval_level_id`, `user_id`)

### `*extra_hour_approval_decisions`
| Column | Type | Notes |
|--------|------|-------|
| `id` | PK | |
| `payroll_user_id` | FK → payroll_user | The attendance record being decided |
| `approval_level_id` | FK → extra_hour_approval_levels | Who decided |
| `approver_id` | FK → users | User who clicked approve/reject |
| `status` | enum: pending/approved/rejected | |
| `comments` | text, nullable | |
| `decided_at` | timestamp, nullable | When the decision was made |

---

## 7. Vacation Domain

### `*vacation_requests`
| Column | Type | Notes |
|--------|------|-------|
| `id` | PK | |
| `user_id` | FK → users | Cascade on delete |
| `start_date` | date | |
| `end_date` | date | |
| `days_requested` | integer | |
| `status` | string, default "Pendiente" | Pendiente / Aprobada / Rechazada / Cancelada |
| `resolved_by` | FK → users, nullable | Who approved/rejected |
| `resolved_at` | timestamp, nullable | |
| `employee_notes` | text, nullable | |
| `reviewer_notes` | text, nullable | |

---

## 8. Projects & Time Tracking Domain

### `*projects`
| Column | Type | Notes |
|--------|------|-------|
| `id` | PK | |
| `name` | string | |
| `client` | string | |
| `start_date` | date, nullable | |
| `estimated_end_date` | date, nullable | |
| `budgeted_hours` | decimal(10,2) | Sum of all task hours |
| `status` | enum: active/finished | |
| `description` | text, nullable | |
| `softDeletes` | | |
| `timestamps` | | |

**Relationships:** `hasMany → tasks`, `hasMany → timeEntries`, `belongsToMany → users` via time_entries

### `*tasks`
| Column | Type | Notes |
|--------|------|-------|
| `id` | PK | |
| `project_id` | FK → projects | Cascade on delete |
| `department_id` | FK → departments | |
| `description` | string | |
| `budgeted_hours` | decimal(8,2) | |
| `completed_at` | timestamp, nullable | Marks task as finished |

**Relationships:** `belongsTo → project`, `belongsTo → department`, `hasMany → timeEntries`

### `*time_entries`
| Column | Type | Notes |
|--------|------|-------|
| `id` | PK | |
| `user_id` | FK → users | Cascade on delete |
| `project_id` | FK → projects | Cascade on delete |
| `task_id` | FK → tasks, nullable | Null on delete |
| `start_time` | timestamp, nullable | |
| `end_time` | timestamp, nullable | |
| `is_paused` | boolean | |
| `last_pause_start` | timestamp, nullable | |
| `total_pause_seconds` | integer | |
| `total_duration_seconds` | integer | |

**Relationships:** `belongsTo → user`, `belongsTo → project`, `belongsTo → task`

### `default_tasks`
| Column | Type | Notes |
|--------|------|-------|
| `id` | PK | |
| `name` | string | Template task |
| `department_id` | FK → departments | |

---

## 9. BioTime Domain

### `bio_time_transactions`
| Column | Type | Notes |
|--------|------|-------|
| `id` | PK | |
| `date` | date | |
| `quantity` | integer | Count of transactions processed that day |

---

## 10. Entity Relationship Summary

```
users ──┬── payroll_user (pivot) ──── payrolls
        │       ├── extra_hour_approval_decisions
        │       └── extra_hour_approval_levels
        │
        ├── vacation_requests
        ├── user_vacation_adjustments
        ├── time_entries ──── projects ──── tasks ──── departments
        │
        ├── model_has_roles ──── roles ──── role_has_permissions ──── permissions
        └── payroll_comments

categories ──── subcategories (self-referencing via prev_subcategory_id) ──── products
```

**Key points:**
- `payroll_user` is the central attendance entity — it links users to payroll periods on a per-day basis.
- The extra hours approval system sits on top of `payroll_user` via `extra_hour_approval_decisions`, with the desnormalized `extra_hour_status` field for quick queries.
- Projects have their own independent time-tracking system (`time_entries`) separate from payroll attendance.
- Users' organizational properties are stored in a single JSON column (`org_props`) rather than normalized tables — this is flexible but means many queries filter via `->org_props['key']`.
