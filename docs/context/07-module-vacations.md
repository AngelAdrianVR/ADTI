# 07 — Vacations Module

---

## Business Purpose

Employee vacation request system with balance validation, a 15-day advance notice rule, and a supervisor approval workflow. Also supports manual vacation balance adjustments (positive or negative) for corrections.

---

## Key Files

| File | Role |
|------|------|
| `app/Http/Controllers/VacationRequestController.php` | Request CRUD, approve/reject/cancel |
| `app/Models/VacationRequest.php` | `user_id, start_date, end_date, days_requested, status, resolved_by, notes` |
| `app/Models/UserVacationAdjustment.php` | Manual balance adjustments |
| `app/Models/User.php` | Vacation balance stored in `org_props.vacations` |
| `resources/js/Pages/VacationRequest/Index.vue` | Vacation request management UI |
| `resources/js/Pages/User/MyPayrolls.vue` | Employee self-service: view payrolls + submit vacation requests |

---

## Vacation Balance Model

The vacation balance is stored as a float in `users.org_props.vacations` (e.g., `12.5` days).

**Available balance = `org_props.vacations` − locked days**

Where **locked days** = sum of `days_requested` for this user's requests where:
- `status IN ('Pendiente', 'Aprobada')`
- `start_date >= today`

Manual adjustments via `UserVacationAdjustment` directly modify `org_props.vacations` (`UserController@updateVacations` applies the adjustment).

---

## Main Endpoints

| Route | Action |
|-------|--------|
| `GET /vacation-requests` | List requests (filtered by `employees_in_charge` hierarchy) |
| `POST /vacation-requests` | Employee submits a new request |
| `PUT /vacation-requests/{id}/cancel` | Employee cancels own pending request |
| `PUT /vacation-requests/{id}/approve` | Supervisor approves (with optional reviewer_notes) |
| `PUT /vacation-requests/{id}/reject` | Supervisor rejects (with optional reviewer_notes) |
| `GET /vacation-requests/pending-count` | AJAX: count of pending requests (for badge/notification) |

### Manual Adjustments (via UserController)
| Route | Action |
|-------|--------|
| `POST /users/{user}/vacation-adjustments` | Add manual adjustment record |
| `DELETE /users/{user}/vacation-adjustments/{adjustment}` | Delete adjustment record |

---

## Business Rules

### 1. Advance Notice (15 days)
```php
if (now()->diffInDays($startDate) < 14 && $startDate->isAfter(now())) {
    // Error: must request at least 15 days in advance
}
```
Note: The code checks `< 14` days (not `<= 14`), meaning exactly 14 days of difference is rejected — the effective minimum is 15 days.

### 2. Balance Validation
```php
$realAvailable = $currentBalance - $lockedDays;
if ($request->days_requested > $realAvailable) {
    // Error: insufficient balance
}
```

### 3. Cancellation
- Only the **request owner** can cancel
- Only requests with `status = 'Pendiente'` can be cancelled
- Cancel sets `status = 'Cancelada'`

### 4. Approval/Rejection
- Sets `status = 'Aprobada'` or `'Rechazada'`
- Records `resolved_by` (reviewer user ID) and `resolved_at` timestamp
- Optionally stores `reviewer_notes`
- **No automatic balance deduction**: Approval does NOT automatically subtract from `org_props.vacations`. This must be handled separately (likely a manual process or a future feature).

---

## Visibility Rules

- **Global permission `Gestionar cualquier solicitud de vacaciones`** → See ALL requests.
- **Without global permission** → See only requests from users in the supervisor's `employees_in_charge` list.

---

## Self-Service Flow (in `MyPayrolls.vue`)

Employees see:
1. Their vacation balance summary (total, locked, available).
2. A form to submit new requests.
3. Their request history with status and reviewer info.

The vacation request form validates advance notice and balance on the server side.

---

## Dependencies

- **Users module** — `org_props.vacations` balance, `employees_in_charge` hierarchy, reviewer assignment
- **Auth module** — `Gestionar cualquier solicitud de vacaciones` permission
- **Payroll module** — vacation days reflected in attendance incidences

---

## Known Limitations & Technical Debt

1. **No automatic balance deduction on approval**: When a vacation is approved, `org_props.vacations` is NOT automatically reduced. This means the same balance can be "double-counted" until a manual adjustment is made.
2. **No weekend/holiday awareness in `days_requested`**: The employee manually enters the number of days. There's no server-side validation that `days_requested` matches the actual business days between `start_date` and `end_date`.
3. **No overlap detection**: An employee could submit multiple requests for the same date range. There's no server-side check for overlapping approved/pending requests.
4. **Single-level approval**: Unlike extra hours (which has multi-level groups), vacation approval is a single-step process. Any supervisor can approve or reject.
5. **No notification on new request**: Supervisors must manually check the vacation requests page. The `pending-count` endpoint exists for badges but there's no push notification.
6. **Adjustment audit trail is fragile**: `UserVacationAdjustment` records can be deleted, but there's no snapshot of the balance before/after. If adjustments are deleted out of order, the balance can become inconsistent.
