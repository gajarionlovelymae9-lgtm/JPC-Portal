# JPC Portal — AI Maintenance

Operational guidance for the NLP/AI layer (summary, validation, reference &
similarity indicators, findings, insights, AI Chat).

## Rules that must never break

1. **AI output is not truth.** Similarity detection is an *indicator* —
   human review is authoritative.
2. **Feedback must not mutate history.** `ai_feedback` rows record verdicts
   (Correct / Partially Correct / Incorrect / Needs Human Review) WITHOUT
   changing the original AI result, `ai_analyses`, or official performance
   scores. Verified by tests.
3. **The system must stay usable when the AI provider is unavailable.**
   Offline fake engine (`AI_PROVIDER=fake`) is the default; provider failures
   degrade health status but never block the app.
4. **No secrets in logs.** Model/provider/version metadata is stored and shown;
   prompts and keys are not.

## Monitoring

- `GET /api/v1/admin/system-health/ai` → totals, failures, timeouts, average
  duration, distinct providers/models, failure-rate threshold (20% warning).
- Existing deep-dive: `/admin/ai` (job status) and `/admin/ai/ocr`.
- Failed AI jobs appear in the queue health (by payload type) and `failed_jobs`.

## Model & prompt versioning

- `ai_prompt_versions` registry (seeded by `AiPromptVersionSeeder`): purpose,
  provider, model, version, description, `versioned_at`, unique `purpose+version`.
- When a prompt or model changes, **add a new version row** — never edit history.
- Tie `ai_analyses.analysis_version` / prompts to the registered version so
  older AI output stays explainable.

## Retrying failed AI work

```bash
cd backend
php artisan queue:failed            # find failed job IDs
php artisan queue:retry <id>        # retry one
```

In-app: use the existing retry action under `/admin/ai`.

## Quality feedback loop

- Users rate AI answers (via `/feedback` page "Rate This AI Answer", and
  `POST /api/v1/feedback/ai`).
- Admins review verdicts in `/admin/feedback` (AI Quality Feedback section).
- Periodic review: `SELECT verdict, COUNT(*) FROM ai_feedback GROUP BY verdict;`
- A high "Incorrect" share for one purpose triggers a new
  `ai_prompt_versions` row + backlog entry, per the
  [`../BACKLOG.md`](../BACKLOG.md) `AI QUALITY` category.

## Config reference

`backend/config/ai_reports.php`:
- `ai.provider` (env `AI_PROVIDER`, default `fake`)
- `jobs.ai.tries=3`, `backoff=[60,240,600]`, `timeout=420`

## Related

- Monitoring: [`monitoring.md`](monitoring.md)
- OCR: [`ocr-maintenance.md`](ocr-maintenance.md)
- Governance: [`../AI_GOVERNANCE.md`](../AI_GOVERNANCE.md)