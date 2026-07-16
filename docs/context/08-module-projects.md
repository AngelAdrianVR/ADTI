# 08 — Projects & Time Tracking Module

---

## Business Purpose

Project management with integrated real-time time tracking. Projects have budgeted hours broken down into department-assigned tasks. Users can start/pause/stop work on tasks, and the system tracks elapsed time with pause support. Provides consumption analysis (budgeted vs. actual hours) and supports assigning time entries to other users (admin mode).

---

## Key Files

| File | Role |
|------|------|
| `app/Http/Controllers/ProjectController.php` | Project CRUD, task management, time tracking actions, default tasks |
| `app/Models/Project.php` | Project with `consumed_hours` accessor, active time entries, scopes |
| `app/Models/Task.php` | Task within a project, `is_finished` + `consumed_hours` accessors |
| `app/Models/TimeEntry.php` | Individual time tracking record with pause support |
| `app/Models/DefaultTask.php` | Template tasks for quick project creation |
| `resources/js/Pages/Project/Index.vue` | Project list with summary cards and active user indicators |
| `resources/js/Pages/Project/Create.vue` | Project creation with task builder |
| `resources/js/Pages/Project/Show.vue` | Project detail with task list and time logs |

---

## Data Model

```
Project (name, client, budgeted_hours, status: active|finished, softDeletes)
  ├── Task (department_id, description, budgeted_hours, completed_at)
  │     └── TimeEntry (user_id, start_time, end_time, is_paused, pause data, total_duration_seconds)
  └── TimeEntry (direct project entries, task_id nullable)
```

**Key design decisions:**
- `TimeEntry.task_id` is nullable — allows time tracked directly against a project without a specific task.
- `total_duration_seconds` is the final computed duration (start→end minus pauses). Always use this for reporting.
- The `consumed_hours` accessor on Project sums ALL time entries' `total_duration_seconds` and converts to hours.

---

## Main Endpoints

### Projects
| Route | Action |
|-------|--------|
| `GET /projects` | `ProjectController@index` — List with active time entries, consumed hours |
| `GET /projects/create` | Create form with department dropdown + default tasks |
| `POST /projects` | Store project + nested tasks in one transaction |
| `GET /projects/{project}` | Show detail |
| `GET /projects/{project}/edit` | Edit form |
| `PUT /projects/{project}` | Update project |
| `DELETE /projects/{project}` | Soft delete |

### Time Tracking Actions
| Route | Action |
|-------|--------|
| `POST /projects/{project}/start` | Start working on a task (creates TimeEntry with `start_time=now`) |
| `POST /projects/{project}/pause` | Toggle pause/resume on active TimeEntry |
| `POST /projects/{project}/stop` | Stop working (sets `end_time=now`, computes final duration) |
| `POST /projects/add-time-entry` | Add a manual time entry (admin recording time for any user) |

### Task Management
| Route | Action |
|-------|--------|
| `PUT /tasks/{task}/toggle-status` | Mark task as finished (sets `completed_at`) or re-open (sets null) |

### Default Tasks
| Route | Action |
|-------|--------|
| `POST /default-tasks` | Create a reusable task template |
| `DELETE /default-tasks/{default_task}` | Delete template |

---

## Time Tracking State Machine

```
Idle (no active TimeEntry for user)
  │
  │ POST /projects/{project}/start  (creates TimeEntry, start_time=now)
  ▼
Active (is_paused=false)
  │
  │ POST /projects/{project}/pause  (sets is_paused=true, records last_pause_start)
  ▼
Paused (is_paused=true)
  │
  │ POST /projects/{project}/pause  (resume: is_paused=false, accumulates total_pause_seconds)
  ▼
Active (is_paused=false)
  │
  │ POST /projects/{project}/stop  (sets end_time=now, computes total_duration_seconds)
  ▼
Completed (end_time != null)
```

**Pause tracking:**
- `last_pause_start` — timestamp when the most recent pause began.
- `total_pause_seconds` — accumulated pause time across all pause/resume cycles.
- Final duration = `(end_time - start_time) - total_pause_seconds`.

**Active entry constraint:** A user can have at most ONE active TimeEntry at a time (`end_time IS NULL`). The `activeTimeEntry` relationship on User uses this constraint.

---

## Project Status Management

- **active** — Work in progress. Default on creation.
- **finished** — Project complete. Use `scopeActive()` and `scopeFinished()` for filtering.

There's no explicit "finish project" endpoint; status is likely set via the update form.

---

## Admin Time Entry (`add-time-entry`)

Allows managers to record time for any user on any project/task. This bypasses the active-entry constraint — it creates a completed TimeEntry directly (both `start_time` and `end_time` set).

---

## Dashboard Integration

`DashboardController` shows:
- `active_projects_count` — count of projects with status=active.
- `total_hours_consumed` — sum of ALL `time_entries.total_duration_seconds` across the system.
- Top 5 active projects with `consumed_hours` appended.

---

## Dependencies

- **Users module** — Time entries reference users; active entry lookup; performance metrics
- **Auth module** — All routes behind `auth:sanctum`
- **Departments** — Tasks are assigned to departments
- **Dashboard** — Aggregates project stats

---

## Known Limitations & Technical Debt

1. **Single active entry**: The constraint that a user can only have one active TimeEntry is enforced at the application level, not the database level. Two rapid "start" clicks could theoretically create two active entries.
2. **No task-specific pause**: Pause is per-TimeEntry (which is per-project). If a user needs to pause work on a specific task but continue on another, they must stop the first task entirely.
3. **Manual time entries bypass validation**: `add-time-entry` doesn't validate that start < end, or that the user exists. It trusts the admin input.
4. **No time rounding**: All time is stored in exact seconds. No configurable rounding (e.g., round to nearest 15 minutes).
5. **Project budget vs. task budget**: `Project.budgeted_hours` is recomputed as the sum of all task `budgeted_hours` on creation. If tasks are added/removed later, the project-level budget may become outdated. There's no automatic recalculation.
6. **Default tasks are simple**: Just `name` and `department_id`. No estimated hours or description. Used as a convenience for project creation only.
7. **No invoicing or billing**: The module tracks time but has no integration with billing rates, client invoices, or cost reporting.
