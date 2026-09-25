# JPC Portal — Entity-Relationship Diagram (Phase 3.1)

> Designed in **Phase 3.1-A** as the relational architecture reference for
> **Phase 3.1-B — Laravel Migrations**, and verified against the live schema in
> **Phase 3.1-E — Database Foundation Finalization**.
>
> Open with a Mermaid-capable renderer (VS Code Mermaid extension, GitHub, etc.).

## Major architecture (overview)

```mermaid
erDiagram
    USER ||--o{ EMPLOYEE : "profile"
    EMPLOYEE }o--|| DEPARTMENT : "belongs to"

    ACADEMIC_YEAR ||--o{ ANNUAL_PLAN : "plans for"
    ANNUAL_PLAN ||--o{ EMPLOYEE_TARGET : "sets targets"
    ANNUAL_PLAN ||--o{ TASK : "scopes"
    TASK ||--o{ TASK_ASSIGNMENT : "assigned to"
    TASK_ASSIGNMENT }o--|| EMPLOYEE : "responsible for"

    TASK ||--o{ REPORT : "produced"
    REPORT ||--o{ REPORT_VERSION : "versioned"
    REPORT_VERSION ||--o{ REPORT_FILE : "attached"
    REPORT_FILE ||--o{ OCR_PROCESSING : "OCR processed"

    REPORT ||--o{ AI_ANALYSIS : "analysed"
    AI_ANALYSIS ||--o{ AI_SUMMARY : "summarises"
    AI_ANALYSIS ||--o{ AI_VALIDATION_RESULT : "validates"
    AI_VALIDATION_RESULT ||--o{ AI_VALIDATION_ITEM : "details"
    REPORT ||--o{ AI_SIMILARITY_RESULT : "compared"

    REPORT ||--o{ REPORT_REVIEW : "reviewed (manual)"
    REPORT ||--o{ REPORT_APPROVAL : "approved (manual)"

    EMPLOYEE ||--o{ EMPLOYEE_PERFORMANCE_RECORD : "scored"
    EMPLOYEE ||--o{ MONTHLY_ACHIEVEMENT : "ranked"
    EMPLOYEE ||--o{ EMPLOYEE_BADGE : "receives"
    EMPLOYEE_BADGE }o--|| BADGE : "awarded"

    USER ||--o{ NOTIFICATION : "receives"
    USER ||--o{ AUDIT_LOG : "performs"
    USER ||--o{ ACTIVITY_LOG : "performs"
```

## Grouped ERD — Core Relationships

```mermaid
erDiagram
    USERS {
        bigint id PK
        varchar name
        varchar email UK
        varchar status
        timestamp last_login_at
    }
    ROLES {
        bigint id PK
        varchar name UK
        varchar display_name
    }
    PERMISSIONS {
        bigint id PK
        varchar name UK
        varchar display_name
    }
    ROLE_USER {
        bigint role_id FK
        bigint user_id FK
    }
    PERMISSION_ROLE {
        bigint permission_id FK
        bigint role_id FK
    }
    USERS ||--o{ ROLE_USER : "has roles"
    ROLES ||--o{ ROLE_USER : "assigned to"
    ROLES ||--o{ PERMISSION_ROLE : "grants"
    PERMISSIONS ||--o{ PERMISSION_ROLE : "assigned to"

    DEPARTMENTS {
        bigint id PK
        varchar name UK
        varchar code UK
        varchar status
        timestamp deleted_at
    }
    POSITIONS {
        bigint id PK
        varchar name UK
        varchar code UK
        varchar status
        timestamp deleted_at
    }
    EMPLOYEES {
        bigint id PK
        bigint user_id FK UK
        varchar employee_number UK
        varchar first_name
        varchar last_name
        bigint department_id FK
        bigint position_id FK
        varchar employment_status
        timestamp deleted_at
    }
    DEPARTMENT_HEADS {
        bigint id PK
        bigint department_id FK
        bigint employee_id FK
        date start_date
        date end_date
        tinyint is_current
        bigint current_department_id generated UK
    }
    DEPARTMENTS ||--o{ EMPLOYEES : "contains"
    POSITIONS ||--o{ EMPLOYEES : "holds"
    USERS ||--o{ EMPLOYEES : "has profile"
    DEPARTMENTS ||--o{ DEPARTMENT_HEADS : "led by"
    EMPLOYEES ||--o{ DEPARTMENT_HEADS : "head of"
```

## Academic Year & Planning

```mermaid
erDiagram
    ACADEMIC_YEARS {
        bigint id PK
        varchar name UK
        date start_date
        date end_date
        varchar status
    }
    ANNUAL_PLANS {
        bigint id PK
        bigint academic_year_id FK
        bigint department_id FK
        varchar title
        varchar status
        bigint created_by FK
        bigint approved_by FK
        timestamp deleted_at
    }
    EMPLOYEE_TARGETS {
        bigint id PK
        bigint annual_plan_id FK
        bigint employee_id FK
        varchar title
        decimal target_value
        varchar target_unit
        varchar status
        timestamp deleted_at
    }
    ACADEMIC_YEARS ||--o{ ANNUAL_PLANS : "plans for"
    DEPARTMENTS ||--o{ ANNUAL_PLANS : "department plan"
    EMPLOYEES ||--o{ EMPLOYEE_TARGETS : "targets"
    ANNUAL_PLANS ||--o{ EMPLOYEE_TARGETS : "contains"
```

## Task Management

```mermaid
erDiagram
    TASKS {
        bigint id PK
        bigint annual_plan_id FK
        bigint department_id FK
        bigint created_by FK
        varchar title
        varchar priority
        date due_date
        varchar status
        timestamp deleted_at
    }
    TASK_ASSIGNMENTS {
        bigint id PK
        bigint task_id FK
        bigint employee_id FK
        bigint assigned_by FK
        decimal progress_percentage
        varchar status
        date due_date
    }
    TASK_REFERENCES {
        bigint id PK
        bigint task_id FK
        varchar title
        varchar reference_type
        varchar reference_url
        varchar file_path
        timestamp deleted_at
    }
    ANNUAL_PLANS ||--o{ TASKS : "scope"
    EMPLOYEES ||--o{ TASK_ASSIGNMENTS : "responsible for"
    TASKS ||--o{ TASK_ASSIGNMENTS : "assigned to"
    TASKS ||--o{ TASK_REFERENCES : "cites"
```

## Report Management

```mermaid
erDiagram
    REPORTS {
        bigint id PK
        bigint task_id FK
        bigint employee_id FK
        varchar title
        date deadline
        varchar status
        tinyint is_late
        int version
        timestamp deleted_at
    }
    REPORT_VERSIONS {
        bigint id PK
        bigint report_id FK
        int version_number
        bigint submitted_by FK
        varchar file_path
        timestamp submitted_at
    }
    REPORT_FILES {
        bigint id PK
        bigint report_id FK
        bigint report_version_id FK
        varchar original_filename
        varchar stored_filename
        varchar file_path
        varchar mime_type
        bigint file_size
        char checksum
        bigint uploaded_by FK
        timestamp deleted_at
    }
    REPORT_REFERENCES {
        bigint id PK
        bigint report_id FK
        bigint task_reference_id FK
        text reference_text
        varchar reference_url
    }
    TASKS ||--o{ REPORTS : "produced"
    EMPLOYEES ||--o{ REPORTS : "submits"
    REPORTS ||--o{ REPORT_VERSIONS : "versioned"
    REPORTS ||--o{ REPORT_FILES : "attached"
    REPORT_VERSIONS ||--o{ REPORT_FILES : "contains"
    TASK_REFERENCES ||--o{ REPORT_REFERENCES : "anchors"
    REPORTS ||--o{ REPORT_REFERENCES : "references"
```

## OCR & AI Processing

```mermaid
erDiagram
    OCR_PROCESSINGS {
        bigint id PK
        bigint report_file_id FK
        varchar provider
        varchar status
        text raw_text
        decimal confidence_score
        timestamp completed_at
    }
    AI_ANALYSES {
        bigint id PK
        bigint report_id FK
        varchar analysis_type
        varchar provider
        varchar model
        varchar status
        char input_hash
    }
    AI_SUMMARIES {
        bigint id PK
        bigint report_id FK
        bigint ai_analysis_id FK
        longtext summary
        json key_accomplishments
        json recommendations
    }
    AI_VALIDATION_RESULTS {
        bigint id PK
        bigint report_id FK
        bigint ai_analysis_id FK
        decimal overall_score
        varchar result
    }
    AI_VALIDATION_ITEMS {
        bigint id PK
        bigint ai_validation_result_id FK
        varchar category
        varchar severity
        text finding
    }
    AI_SIMILARITY_RESULTS {
        bigint id PK
        bigint report_id FK
        bigint compared_report_id FK
        decimal similarity_score
        varchar status
    }
    REPORT_FILES ||--o{ OCR_PROCESSINGS : "OCR processed"
    REPORTS ||--o{ AI_ANALYSES : "analysed"
    AI_ANALYSES ||--o{ AI_SUMMARIES : "summarises"
    AI_ANALYSES ||--o{ AI_VALIDATION_RESULTS : "validates"
    AI_VALIDATION_RESULTS ||--o{ AI_VALIDATION_ITEMS : "details"
    REPORTS ||--o{ AI_SIMILARITY_RESULTS : "compared"
```

## Manual Review & Approval

```mermaid
erDiagram
    REPORT_REVIEWS {
        bigint id PK
        bigint report_id FK
        bigint reviewer_id FK
        varchar review_type
        varchar decision
        timestamp reviewed_at
    }
    REPORT_APPROVALS {
        bigint id PK
        bigint report_id FK
        bigint approver_id FK
        varchar decision
        varchar comments
        timestamp approved_at
    }
    REPORTS ||--o{ REPORT_REVIEWS : "reviewed"
    USERS ||--o{ REPORT_REVIEWS : "reviewer"
    REPORTS ||--o{ REPORT_APPROVALS : "approved"
    USERS ||--o{ REPORT_APPROVALS : "approver"
```

## Performance & Achievements

```mermaid
erDiagram
    EMPLOYEE_PROGRESS_SNAPSHOTS {
        bigint id PK
        bigint employee_id FK
        bigint academic_year_id FK
        date snapshot_date
        decimal completion_rate
        decimal overall_score
    }
    EMPLOYEE_PERFORMANCE_RECORDS {
        bigint id PK
        bigint employee_id FK
        bigint academic_year_id FK
        varchar period_type
        date period_start
        date period_end
        decimal overall_score
        varchar performance_status
    }
    BADGES {
        bigint id PK
        varchar name UK
        varchar code UK
        varchar criteria_type
        json criteria_value
        tinyint is_active
        timestamp deleted_at
    }
    EMPLOYEE_BADGES {
        bigint id PK
        bigint employee_id FK
        bigint badge_id FK
        bigint academic_year_id FK
        date period_start
        date period_end
        timestamp awarded_at
        bigint awarded_by FK
        json criteria_snapshot
    }
    MONTHLY_ACHIEVEMENTS {
        bigint id PK
        bigint employee_id FK
        bigint academic_year_id FK
        tinyint month
        smallint year
        decimal overall_score
        int rank
        tinyint is_employee_of_month
    }
    EMPLOYEES ||--o{ EMPLOYEE_PROGRESS_SNAPSHOTS : "tracked"
    ACADEMIC_YEARS ||--o{ EMPLOYEE_PROGRESS_SNAPSHOTS : "period"
    EMPLOYEES ||--o{ EMPLOYEE_PERFORMANCE_RECORDS : "scored"
    ACADEMIC_YEARS ||--o{ EMPLOYEE_PERFORMANCE_RECORDS : "period"
    EMPLOYEES ||--o{ EMPLOYEE_BADGES : "receives"
    BADGES ||--o{ EMPLOYEE_BADGES : "awarded"
    EMPLOYEES ||--o{ MONTHLY_ACHIEVEMENTS : "ranks"
    ACADEMIC_YEARS ||--o{ MONTHLY_ACHIEVEMENTS : "period"
```

## Notifications & Audit

```mermaid
erDiagram
    NOTIFICATIONS {
        bigint id PK
        bigint user_id FK
        varchar type
        varchar title
        text message
        json data
        timestamp read_at
    }
    AUDIT_LOGS {
        bigint id PK
        bigint user_id FK
        varchar action
        varchar entity_type
        bigint entity_id
        json old_values
        json new_values
    }
    ACTIVITY_LOGS {
        bigint id PK
        bigint user_id FK
        varchar activity_type
        text description
        varchar subject_type
        bigint subject_id
        json metadata
    }
    USERS ||--o{ NOTIFICATIONS : "receives"
    USERS ||--o{ AUDIT_LOGS : "performs"
    USERS ||--o{ ACTIVITY_LOGS : "performs"
```

## Phase 4–9 Additions (Workflow, AI Chat, Data Quality, Reporting)

```mermaid
erDiagram
    REPORT ||--o{ REPORT_REVIEW_COMMENT : "comments"
    REPORT ||--o{ REPORT_TIMELINE : "timeline"
    REPORT_VERSION ||--o{ REPORT_FILE : "contains"
    REPORT ||--o{ AI_FINDING : "finds"
    REPORT ||--o{ AI_INSIGHT : "insights"

    USERS ||--o{ AI_CHAT_CONVERSATION : "opens"
    AI_CHAT_CONVERSATION ||--o{ AI_CHAT_MESSAGE : "contains"
    AI_CHAT_MESSAGE ||--o{ AI_CHAT_TOOL_CALL : "invokes"

    EMPLOYEE_PERFORMANCE_RECORD ||--o{ PERFORMANCE_SCORE_BREAKDOWN : "broken down"
    PERFORMANCE_PERIODS ||--o{ EMPLOYEE_PERFORMANCE_RECORD : "period"

    DATA_QUALITY_SCAN ||--o{ DATA_QUALITY_ISSUE : "finds"
    DATA_QUALITY_ISSUE ||--o{ DATA_QUALITY_ISSUE_HISTORY : "history"
    USER ||--o{ REPORT_EXPORT : "requests"
    DEPARTMENT ||--o{ DEPARTMENT_PLAN : "plans"
    ANNUAL_PLAN ||--o{ DEPARTMENT_PLAN : "scopes"
    DEPARTMENT ||--o{ DEPARTMENT_TARGET : "targets"
    DEPARTMENT_PLAN ||--o{ DEPARTMENT_TARGET : "contains"
    EMPLOYEE_TARGET }o--|| DEPARTMENT_TARGET : "may derive"

    TASK_ASSIGNMENT ||--o{ TASK_PROGRESS : "progress trail"
    TASK_ASSIGNMENT ||--o{ TASK_REVIEW : "reviewed"
    TASK ||--o{ TASK_COMMENT : "discussed"
    TASK ||--o{ TASK_ATTACHMENT : "attached"

    USERS ||--o{ NOTIFICATION_PREFERENCE : "routing"
    NOTIFICATIONS ||--o{ NOTIFICATION_LOG : "delivery trace"
    USER ||--o{ MESSAGE : "sends/receives"
    USER ||--o{ ANNOUNCEMENT : "author of"
    DEPARTMENT ||--o{ DEPARTMENT_ESCALATION : "escalates"
```

## Legend

| Symbol | Meaning |
| ------ | ------- |
| PK     | Primary key |
| UK     | Unique key |
| FK     | Foreign key |
| `\|\|--o{` | One-to-many |
| `generated` | Computed stored column |

Entity-type FKs (audit_logs.entity_type, activity_logs.subject_type) are polymorphic
logical references and intentionally have no formal foreign key constraint.

> The diagrams above document the design reference. The authoritative live schema is
> produced by `backend/database/migrations/*.php`; see also
> [`../DATABASE.md`](../DATABASE.md) for per-table documentation.
