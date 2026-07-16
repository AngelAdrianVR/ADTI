# 06 — Extra Hours Approval Module

---

## Business Purpose

A multi-level approval workflow for overtime (horas extra). When the payroll system detects extra hours, the workflow initializes and routes the record through configurable approval levels. Each level has designated approvers. Costs per extra hour are configurable by day of week, range type (weekday/weekend/specific), and optionally per specific user.

---

## Key Files

| File | Role |
|------|------|
| `app/Services/ExtraHourApprovalService.php` | **Core state machine**: initialize, decide, bulkDecide, revert, canAct |
| `app/Http/Controllers/PayrollExtraHoursController.php` | UI for cost config, approval groups/levels, decide actions |
| `app/Models/ExtraHourCost.php` | Cost configuration per payroll/day/user |
| `app/Models/ExtraHourApprovalGroup.php` | Group of employees sharing an approval chain |
| `app/Models/ExtraHourApprovalLevel.php` | Approval level within a group (1, 2, 3...) |
| `app/Models/ExtraHourApprovalDecision.php` | Individual approval/rejection record |
| `app/Models/PayrollUser.php` | Contains desnormalized `extra_hour_status` and `current_approval_level_id` |
| `resources/js/Pages/Payroll/` | Vue pages include extra hours configuration UI |

---

## Workflow State Machine

```
                    ┌──────────┐
                    │   none   │  (no extra hours detected)
                    └──────────┘
                         │
                         │ extra_hours > 0 OR extra_minutes > 0
                         ▼
              ┌──────────────────────┐
              │      pending         │
              │ current_approval_    │
              │ level_id = Level 1   │
              └──────┬───────────────┘
                     │
          ┌──────────┼──────────┐
          │          │          │
          ▼          ▼          ▼
    ┌──────────┐ ┌──────────┐ ┌──────────┐
    │ Level 1  │ │ Level 1  │ │ Level 1  │
    │ approve  │ │ reject   │ │ approve  │
    └────┬─────┘ └────┬─────┘ └────┬─────┘
         │            │            │
         │            ▼            │ (has Level 2?)
         │     ┌──────────┐        │
         │     │ rejected │        ├── Yes → Level 2 pending
         │     └──────────┘        │
         │                         ├── No  → ┌──────────┐
         │                         │         │ approved │
         │                         │         └──────────┘
         │                         │
         │              ┌──────────┘
         │              ▼
         │    (continues through all levels)
         │              │
         │    ┌─────────┴─────────┐
         │    │                   │
         │    ▼                   ▼
         │  Level N         Level N
         │  approve         reject
         │    │               │
         │    ▼               ▼
         │  ┌──────────┐   ┌──────────┐
         │  │ approved │   │ rejected │
         │  └──────────┘   └──────────┘
         │
         └── (any level reject → immediate global rejection)
```

**Key rules:**
- A rejection at ANY level immediately terminates the workflow as globally **rejected**.
- Approval at a level advances to the next level. Approval at the last level → globally **approved**.
- If no group/levels are configured for the user → **direct mode**: anyone with the right permission can approve without multi-level routing.

---

## Main Endpoints

### Configuration
| Route | Action |
|-------|--------|
| `GET /payrolls/{payroll}/extra-hours-config` | `PayrollExtraHoursController@config` — View/configure costs and approval groups |
| `POST /payrolls/{payroll}/extra-hours-costs` | Save cost configuration |
| `POST /payrolls/{payroll}/extra-hours-groups` | Save approval groups with levels and approvers |
| `POST /payrolls/{payroll}/extra-hours-copy` | Copy configuration from previous payroll |
| `POST /payrolls/{payroll}/extra-hours-copy-next` | Copy configuration from next payroll |

### Decision Actions
| Route | Action |
|-------|--------|
| `POST /payrolls/{payroll}/extra-hours-decide` | Single approval/rejection decision |
| `POST /payrolls/{payroll}/extra-hours-decide-bulk` | Bulk approve/reject multiple records |
| `DELETE /payrolls/extra-hours-revert` | Revert an approver's last decision |

### Quick Actions (on PayrollUserController)
| Route | Action |
|-------|--------|
| `PUT /payroll-users/approve-extra-time` | Direct approve (bypasses multi-level if in direct mode) |
| `PUT /payroll-users/revert-extra-time` | Revert approval |
| `PUT /payroll-users/reject-extra-time` | Reject extra time |
| `GET /payroll-users/recalculate-extra-time` | Trigger recalculation + workflow re-initialization |

---

## Core Service: `ExtraHourApprovalService`

### `initializeWorkflow(PayrollUser)`
Called automatically from `PayrollUser::calculateExtraTime()` and BioTime import. Sets `extra_hour_status`:
- `'none'` if no extra time
- `'pending'` + `current_approval_level_id` = first level of user's group
- `'pending'` + `current_approval_level_id` = null if no group (direct mode)

Skips re-initialization if status is already `'approved'` or `'rejected'`.

### `decide(PayrollUser, User approver, string status, array data)`
- Uses `DB::transaction()` with `lockForUpdate()` to prevent race conditions.
- Validates the approver belongs to the current level.
- Validates previous levels are approved (can't skip levels).
- Creates/updates `ExtraHourApprovalDecision`.
- Calls `advanceOrClose()` to move to next level or finalize.

### `bulkDecide(array ids, User approver, string status, array data)`
Iterates through multiple IDs, calling `decide()` for each. Returns `['ok' => [...], 'errors' => [id => message]]` — partial success is allowed.

### `revert(PayrollUser, User actor)`
- Finds the actor's most recent decision and deletes it.
- Recalculates the entire workflow state from remaining decisions.
- If no decisions remain, resets to `'pending'` with first level.

### `canAct(PayrollUser, User user): bool`
Lightweight check: is this user authorized to decide on this record right now? Checks current level's approvers list for the user.

---

## Data Model Relationships

```
Payroll
  ├── ExtraHourCost (cost_per_hour by day_of_week, range_type, user_id)
  └── ExtraHourApprovalGroup
        ├── employees (BelongsToMany → User)
        └── ExtraHourApprovalLevel (ordered by level)
              ├── approvers (BelongsToMany → User)
              └── ExtraHourApprovalDecision
                    ├── payrollUser (FK)
                    ├── approvalLevel (FK)
                    └── approver (FK → User)

PayrollUser (desnormalized)
  ├── extra_hour_status: 'none'|'pending'|'approved'|'rejected'
  └── current_approval_level_id: FK → extra_hour_approval_levels
```

**Why desnormalization?** The composite index `(payroll_id, extra_hour_status, current_approval_level_id)` allows fast queries like "show me all pending approvals for my level in payroll X" without joining through the decisions table.

---

## Cost Configuration

Costs are stored in `extra_hour_costs` with a composite unique key:
- `payroll_id` — which payroll period
- `user_id` — NULL = general cost; specific = override for that employee
- `day_of_week` — 0=Sun…6=Sat, NULL depends on range_type
- `range_type` — `weekday` (Mon-Fri), `weekend` (Sat-Sun), `specific` (exact day)

This allows flexible cost models: one rate for all weekdays, a different rate for weekends, and per-employee overrides.

---

## Dependencies

- **Payroll module** — `PayrollUser::calculateExtraTime()` triggers workflow initialization
- **Users module** — approvals reference users as approvers and as employees in groups
- **Auth module** — action gated by the `canAct()` check and general permissions

---

## Known Limitations & Technical Debt

1. **Desnormalization sync risk**: `extra_hour_status` and `current_approval_level_id` on `payroll_user` must stay in sync with `extra_hour_approval_decisions`. The service handles this, but any direct DB manipulation will break it.
2. **No notification system**: When a record moves to a new level, there's no automated notification to the next-level approvers. They must manually check the UI.
3. **No timeouts/auto-escalation**: If an approver never acts, the record stays `pending` indefinitely. No SLA or auto-advance mechanism.
4. **Costs are informational only**: The `extra_hour_costs` table stores rates but there's no payroll calculation that uses them automatically. The cost data is for the pre-payroll report.
5. **Direct mode ambiguity**: When `current_approval_level_id` is NULL, any user with the right permission can approve. This could lead to unauthorized approvals if permissions are too broad.
6. **Race condition on `decide()`**: The `lockForUpdate()` protects within a single transaction, but two approvers at the same level could theoretically both try to decide. The second will hit the status validation and fail — this is handled gracefully but the UX for the second approver could be confusing.
