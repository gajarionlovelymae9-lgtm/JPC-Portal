# JPC Portal — Continuous-Improvement Backlog

Open/candidate improvements collected from UAT, incidents, and feedback.
Each item carries: **ID · Title · Description · Priority · Source · Affected
Module · Status · Requested By · Created · Resolution**.

## Active

| ID | Category | Title | Priority | Source | Module | Status | Requested By | Created | Resolution |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| JPC-001 | FEATURE REQUEST | Real AI/OCR provider integration guide | HIGH | UAT | AI/OCR | OPEN | Admin (capstone) | 2026-09-23 | — |
| JPC-002 | PERFORMANCE | Code-split frontend bundle (single ~869 kB chunk) | MEDIUM | Release review | Frontend | OPEN | Admin | 2026-09-23 | Use dynamic `import()` / vite code-splitting |
| JPC-003 | DOCUMENTATION | Email delivery runbook (SMTP/dev) | LOW | Docs audit | Notifications | OPEN | Admin | 2026-09-23 | — |
| JPC-004 | AI QUALITY | Prompt v2 for report summaries after N "Incorrect" verdicts | MEDIUM | AI feedback loop | AI | OPEN | Monitor | 2026-09-23 | Add `ai_prompt_versions` row on go-live of v2 |
| JPC-005 | OCR QUALITY | Improve low-confidence handling UX (already flagged; add reviewer filter) | LOW | Feedback | OCR | OPEN | Users | 2026-09-23 | — |

## Resolved / closed during Phase 13

| ID | Category | Title | Priority | Status | Resolution |
| --- | --- | --- | --- | --- | --- |
| JPC-000 | FEATURE REQUEST | Phase 13 monitoring & maintenance baseline | HIGH | RESOLVED | Health services, dashboard, commands, tests, docs shipped in v1.0.0 |

## How items get added

1. User feedback (`/feedback`) — admin triages in `/admin/feedback`.
2. Incidents (`incident-response.md` Post-incident step).
3. Monthly checklist review (new issues surfaced by health trends).
4. AI quality feedback (`ai_feedback` verdicts, per `ai-maintenance.md`).

## Status vocabulary

`OPEN` → `BACKLOGGED` → `IN_PROGRESS` → `RESOLVED` (or `DROPPED`). Each item is
closed in a release note in [`CHANGELOG.md`](../../CHANGELOG.md).

## Categories

`BUG` · `SECURITY` · `PERFORMANCE` · `USABILITY` · `AI QUALITY` · `OCR QUALITY` ·
`DATA QUALITY` · `DOCUMENTATION` · `FEATURE REQUEST`.