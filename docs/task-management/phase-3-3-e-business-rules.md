# Phase 3.3-E — Business Rules & Service Layer

## Overview

The service layer enforces canonical task and assignment lifecycles, validates all state transitions, prevents invalid operations, and protects data integrity through explicit business rules, policies, and exceptions.

---

## 1. Task Lifecycle

Canonical states and allowed transitions:

```
draft → assigned | cancelled
assigned → in_progress | cancelled
in_progress → submitted | cancelled
submitted → under_review | rejected
under_review → completed | rejected
rejected → in_progress
```

- `TaskService::enforceTransition()` validates every status change.
- `TaskService::createTask()` starts tasks in `draft` status.
- `TaskService::cancelTask()` allows cancellation from `draft`, `assigned`, `in_progress`, `submitted`, `under_review`.
- `TaskService::updateTask()` blocks updates on `completed` and `cancelled`.

### Exception
- `InvalidTaskStatusTransitionException` thrown on any disallowed transition.

---

## 2. Assignment Lifecycle

```
assigned → accepted → in_progress → submitted → completed | rejected
```

- `TaskAssignmentService::assignEmployee()` requires active employee, non-duplicate assignment, and task status `draft` or `assigned`.
- `TaskAssignmentService::acceptAssignment()` transitions `assigned` → `accepted`.
- `TaskAssignmentService::startAssignment()` transitions `assigned`/`accepted` → `in_progress`.
- `TaskAssignmentService::submitAssignment()` transitions `in_progress`/`accepted` → `submitted`.
- `TaskAssignmentService::resubmitAssignment()` transitions `rejected` → `submitted`.

### Exception
- `TaskNotAssignableException` for inactive employees, duplicate assignments, or non-assignable task statuses.
- `InvalidTaskStatusTransitionException` for invalid assignment status changes.

---

## 3. Progress Rules

- Progress is **append-only** (monotonic): `new_percentage` must be ≥ previous `new_percentage`.
- Percentage bounds: **0–100** inclusive.
- `TaskProgressService::updateProgress()` creates a `TaskProgress` record and updates `TaskAssignment.progress_percentage`.
- At 100%, assignment status remains as-is; `completed_at` is set if not already.

### Exception
- `InvalidProgressException::outOfBounds()` for < 0 or > 100.
- `InvalidProgressException::backwardProgress()` for any decrease.

---

## 4. Review Rules

### Authorized Reviewers
Only users with role `admin` or `department_head` can perform reviews.
- `TaskReviewService::isAuthorizedReviewer()` checks `User::roleName()` against `AUTHORIZED_ROLES`.
- `TaskReviewPolicy::update()` enforces this at the gate level.

### Self-Approval Guard
An employee cannot approve their own submission.
- `TaskReviewService::guardNotSelfApproval()` queries `Employee::where('id', assignment.employee_id)->value('user_id')` and compares with reviewer `id`.
- Throws `InvalidTaskReviewException::selfApproval()`.

### State Guard
Only `submitted` or `under_review` assignments can be reviewed.
- `TaskReviewService::guardReviewableState()` validates assignment status.
- Throws `InvalidTaskReviewException::invalidState()`.

### Review Decisions
| Decision | Assignment Status |
|---|---|
| `approved` | `completed` |
| `rejected` | `rejected` |
| `needs_revision` | `rejected` |

### Exception
- `InvalidTaskReviewException::unauthorized()` for non-authorized reviewers.
- `InvalidTaskReviewException::selfApproval()` for self-approval attempts.
- `InvalidTaskReviewException::invalidState()` for non-submittable assignments.

---

## 5. Authorization (Policies)

Three policies registered in `AppServiceProvider::boot()`:

| Policy | Model | Methods |
|---|---|---|
| `TaskPolicy` | `Task` | `update`, `delete` |
| `TaskAssignmentPolicy` | `TaskAssignment` | `update`, `delete` |
| `TaskReviewPolicy` | `TaskReview` | `update` |

Policies check role-based access using `User::roleName()`.

---

## 6. Overdue / Late / Completion Calculation

- **Overdue**: `Task::scopeOverdue()` returns tasks where `due_date < now()` and status is not in final states (`completed`, `cancelled`).
- **Late**: `TaskAssignment::scopeOverdue()` uses the same logic on assignments.
- **Completion**: `TaskProgressService::getCompletionRate()` computes the arithmetic average of all assignment `progress_percentage` values.

---

## 7. Exception Handling Strategy

All exceptions extend `RuntimeException` and use static factory methods for consistent, descriptive error messages:

| Exception Class | Factory Methods |
|---|---|
| `TaskNotAssignableException` | `employeeNotActive()`, `assignmentAlreadyExists()`, `taskNotAssignable(string)` |
| `InvalidTaskStatusTransitionException` | `from(string $from, string $to)` |
| `InvalidProgressException` | `outOfBounds(float)`, `backwardProgress(float, float)` |
| `InvalidTaskReviewException` | `unauthorized()`, `selfApproval()`, `invalidState(string)`, `remarksRequired()` |

---

## 8. Transaction Strategy

All service methods that modify multiple records use `DB::transaction()` to ensure atomicity:

- `TaskService::createTask()` — code generation + task creation
- `TaskAssignmentService::assignEmployee()` — assignment creation + activity log
- `TaskProgressService::updateProgress()` — progress record + assignment update
- `TaskReviewService::approve/reject/requestRevision()` — review creation + assignment status update

Concurrent-safe code generation uses `lockForUpdate()` on the task record.

---

## 9. Files Created/Modified in Phase 3.3-E

### Services
- `app/Services/TaskManagement/TaskService.php`
- `app/Services/TaskManagement/TaskAssignmentService.php`
- `app/Services/TaskManagement/TaskProgressService.php`
- `app/Services/TaskManagement/TaskReviewService.php`

### Policies
- `app/Policies/TaskPolicy.php`
- `app/Policies/TaskAssignmentPolicy.php`
- `app/Policies/TaskReviewPolicy.php`

### Form Requests
- `app/Http/Requests/TaskManagement/StoreTaskRequest.php`
- `app/Http/Requests/TaskManagement/UpdateTaskRequest.php`
- `app/Http/Requests/TaskManagement/AssignTaskRequest.php`
- `app/Http/Requests/TaskManagement/UpdateTaskProgressRequest.php`
- `app/Http/Requests/TaskManagement/SubmitTaskRequest.php`
- `app/Http/Requests/TaskManagement/ReviewTaskRequest.php`

### Exceptions
- `app/Exceptions/TaskManagement/TaskNotAssignableException.php`
- `app/Exceptions/TaskManagement/InvalidTaskStatusTransitionException.php`
- `app/Exceptions/TaskManagement/InvalidProgressException.php`
- `app/Exceptions/TaskManagement/InvalidTaskReviewException.php`

### Tests
- `tests/Feature/Services/TaskServiceTest.php` (6 tests)
- `tests/Feature/Services/TaskAssignmentServiceTest.php` (4 tests)
- `tests/Feature/Services/TaskProgressServiceTest.php` (4 tests)
- `tests/Feature/Services/TaskReviewServiceTest.php` (6 tests)

### Modified
- `app/Providers/AppServiceProvider.php` — policy registration
- `backend/database/migrations/` — existing migrations unchanged

---

## 10. Test Results

**Full test suite: 163 passed / 869 assertions**
- All 20 service tests pass
- All 143 existing tests pass (no regressions)
- Pint passes on all modified files
