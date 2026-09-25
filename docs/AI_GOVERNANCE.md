# JPC Portal — AI Governance

This document defines what the AI subsystem is **allowed** to do and what it is
**never** allowed to do, so that human oversight stays in the loop for every
consequential decision.

## Design principle

> AI **assists**. Humans **decide**.
>
> Every report approval, rejection, revision request, performance score, and any
> personnel decision is made by an authorized human. AI output is advisory input to
> that decision — never the decision itself.

## 1. What AI CAN do

- **Summarize** submitted report documents (key accomplishments, recommendations).
- **Classify** content (report type, category, topic).
- **Identify possible issues** in a report (gaps, inconsistencies, missing sections).
- **Validate against configured requirements** (validation items with
  severity/category — the requirements are configured by the institution).
- **Compare against references** (reference anchors, similarity to other reports).
- **Detect similarity indicators** — a numeric score and status that a reviewer may
  investigate.
- **Identify possible data inconsistencies** (data-quality scan findings).
- **Generate management insights** (AI insights) from authorized institutional data.
- **Answer authorized analytical questions** via AI Chat (e.g. "show late reports
  this month", "who has the highest completion rate") using the **AI tool registry**
  with role-scoped authorization.

All of these run **server-side** (`app/Services/AI/*`, `app/Services/AiReports/*`)
and are constrained by `AiAuthorizationService`, `AiResponseValidator`, and RBAC.

## 2. What AI CANNOT do (independently)

AI must **not**, under any circumstance:

- **Approve employee reports** — approval is a human action recorded in
  `report_approvals` by an authorized reviewer.
- **Reject employees** — no employment decision is made by AI.
- **Accuse employees of plagiarism** — similarity detection is an **indicator, not
  proof**; it never produces a verdict, only a score for human review.
- **Modify official performance scores** — scores are computed by the deterministic
  Performance service (`app/Services/Performance/*`), not authored by the model.
- **Modify employee data without authorization** — AI has no write path to
  authoritative employee/HR data.
- **Bypass RBAC** — AI tool calls are authorized per user/role/scope before
  execution; the model cannot request data the caller is not permitted to read.
- **Access unauthorized information** — context is built from the caller's
  authorized scope only (`AiContextBuilder`).

## 3. Human review is mandatory for

- Report approval / rejection / revision requests
- Performance evaluation and scoring outcomes
- Achievement/badge decisions surfaced from data
- Any consequence affecting an employee's record or standing
- Acting on AI findings (including similarity findings) — verify before acting

## 4. Transparency & traceability

- Every AI analysis run is recorded (`ai_analyses` with provider/model/status),
  and summaries/validation/similarity results are stored with their report.
- AI chat tool calls are logged (`ai_chat_tool_calls` — tool, intent, params,
  result, timings) for auditability.
- AI findings (`ai_findings`) carry a status so reviewers can track validation.

## 5. Prompt-injection defense

- Model input treats user/document text as **data**, with a fixed server-defined
  system prompt.
- Tool authorization happens **before** any tool executes, independent of the
  model's request.
- Output is validated and rendered as text, never executed as commands.
- See [`SECURITY.md`](SECURITY.md) §10.

## 6. Provider & credential policy

- Default provider is the deterministic **offline** engine (`AI_PROVIDER=fake`) so
  the pipeline works with no external accounts and no data leaves the server.
- Real providers (`AI_PROVIDER=openai` or an OpenAI-compatible `AI_ENDPOINT`) are
  opt-in via server `.env`.
- **AI keys never appear in:** React source, browser network responses, the git
  repository, or logs.
- All AI requests originate from the backend.

## 7. Cost / rate / reliability controls

- `AI_TIMEOUT`, `AI_MAX_TOKENS`, `AI_TEMPERATURE`, `AI_REPORTS_MAX_INPUT_CHARS`
  bound request size, latency and cost.
- Failures are logged and the report pipeline degrades gracefully (offline engine
  or a recorded failure) — the app does not crash.
- Provider availability, internet access, and quotas can affect real-provider
  results (see [`README` limitations](../README.md) /
  [`CAPSTONE_SYSTEM_OVERVIEW.md`](CAPSTONE_SYSTEM_OVERVIEW.md)).

## 8. Summary

| AI may | AI may not |
| --- | --- |
| summarize, classify, flag issues | approve/reject reports |
| validate vs configured requirements | modify official scores |
| compare to references, show similarity indicators | accuse of plagiarism |
| detect data inconsistencies | modify employee data |
| generate insights, answer authorized questions | bypass RBAC / access unauthorized data |
| run server-side, authorized, logged | run client-side or expose credentials |

**Human review remains required for all consequential decisions.**