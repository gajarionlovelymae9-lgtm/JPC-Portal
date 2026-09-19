# Phase 3.3-X — Department Head Portal

## Overview
The **Department Head Portal** serves as the operational bridge between individual employees, their respective academic/administrative department, and John Paul College's central administration. It equips Department Heads to oversee department faculty, dispatch deliverables with individual employee tracking, review submitted reports against AI validation and OCR extraction, maintain department target milestones, and escalate risks to Administration.

---

## 1. Key Features & Workflows

### 1.1 Department Dashboard (`/department-head/dashboard`)
- **Department Scope Identification:** Header shows Department Name, Code, Department Head, and Academic Year.
- **Real-Time KPIs:** Total/Active Faculty Members, Total Tasks, Completed Tasks, Pending Tasks, Overdue Tasks, Submitted Reports, Reports Pending Review, Approved Reports, Revision Required, Completion Rate, On-Time Submission Rate, and Approval Rate.
- **Annual Target Tracker:** Visual progress bar tracking completed deliverables against the department's annual target with automatic "At Risk" detection.
- **Actionable Alerts:** Highlights overdue task assignments and reports awaiting review with direct action links.
- **AI Performance Summary:** Synthesizes department metrics into an executive operational overview with mandatory advisory disclaimer.

### 1.2 Faculty Directory & Individual Tracking (`/department-head/employees`, `/department-head/employees/:id`)
- Displays faculty members belonging strictly to the Department Head's department.
- Detailed profile view shows assigned tasks, reports submitted, individual completion rate, and employment status.

### 1.3 Task Creation & Multi-Employee Assignment (`/department-head/tasks`, `/department-head/tasks/create`, `/department-head/tasks/:id`)
- **Individual Progress Architecture:** When a task is assigned to multiple faculty members (e.g. Teacher 1 and Teacher 2), each member receives an individual `TaskAssignment` record. Teacher 1 can be at 100% (Completed) while Teacher 2 is at 50% (In Progress).
- Tasks support priority levels (Low, Medium, High, Urgent), start dates, deadlines, AI validation criteria/requirements, and instructional guidelines.
- Department Heads can cancel tasks with a recorded reason that is preserved in the audit log.

### 1.4 Report Review Queue & Detailed Review View (`/department-head/reports`, `/department-head/reports/:id`)
- Queue of submitted faculty reports with AI status tags and review status badges.
- **Comprehensive Review Workspace:**
  - **Original Report & Attachments:** Full body text and downloadable files.
  - **AI Summary & Validation:** Executive summary, key accomplishments, requirement adherence checklist, reference checks, and similarity detection.
  - **OCR Extracted Text:** Extracted text from uploaded documents with OCR confidence score and manual verification warnings.
  - **Review History:** Complete log of previous review rounds and revision comments.
  - **Human Review Decision Panel:** Department Heads execute human decisions: **Approve**, **Request Revision** (with mandatory feedback comments), or **Reject** (with mandatory reason).

### 1.5 Department Targets & Planning (`/department-head/targets`)
- Annual and milestone goals calculated against real task completions and report approvals.

### 1.6 Department Performance & Intra-Department Comparison (`/department-head/performance`)
- Benchmark completion rate, punctuality (on-time rate), and approval rate across department faculty members.
- Monthly performance trends (January – Present).

### 1.7 Achievements & Badges (`/department-head/achievements`)
- Objective, rule-based rankings for monthly top faculty performers (Highest Completion, On-Time Champions, Target Achievers).

### 1.8 Department Reports Center (`/department-head/reports-center`)
- Formal report generator with date and report type filters, CSV export, and print-ready layout for Dean/Administration meetings.

### 1.9 Issue Escalation (`/department-head/escalations`)
- Tool to flag critical delays, repeated report revisions, or target risks directly to Administration with notifications dispatched to Admin accounts.

### 1.10 Management Summary (`/department-head/management-summary`)
- High-level executive summary view of department health and operational status.

---

## 2. Security & Department Scope Isolation

1. **Strict Department Scope & IDOR Protection:**
   - Enforced by `DepartmentHeadContextService.php`.
   - Every request resolves the authenticated Department Head's department.
   - Any attempt to view or update employees, tasks, or reports outside the head's department returns `403 Forbidden`.
2. **AI as Assistant:**
   - AI validation and summary outputs are explicitly marked as advisory.
   - All report approvals and revision instructions require human submission.
3. **No Administrative Privilege Escalation:**
   - Department Heads are restricted from system configuration, user role management, and global audit logs.

---

## 3. Verification & Build Status
- **Backend API Routes:** 17 `/api/v1/department-head/*` endpoints active.
- **Frontend Build:** TypeScript compilation and production bundle passed with 0 errors (139 modules).