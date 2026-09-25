# JPC Portal — OCR Maintenance

Operational guidance for OCR document processing (report file uploads →
`ocr_processings`).

## Core rules

1. **OCR failure must never invalidate a report.** A report flagged for OCR
   issues stays submittable; the OCR status is informational for reviewers.
   This is enforced by design and covered in tests/QA.
2. **System must stay usable when OCR is unavailable.** `OCR_PROVIDER=fake`
   (offline engine) is the default; provider outages degrade health status but
   never block report submission.
3. Files are stored on the private disk (`storage/app/private/reports/`) and
   never exposed outside authorized download routes.

## Monitoring

- `GET /api/v1/admin/system-health/ocr` → queued/processing/completed/failed
  counts, low-confidence count (< `ai_reports.ocr.min_confidence`, default
  0.55), average duration, failure-rate threshold (20% warning).
- Deep-dive: `/admin/ai/ocr` (per-file OCR jobs, retry action).
- Failed OCR jobs appear in queue health and `failed_jobs`.

## Handling OCR failures

```bash
cd backend
php artisan queue:failed            # inspect failed OCR jobs
php artisan queue:retry <id>        # retry after provider recovery
```

In-app: use the retry action under `/admin/ai/ocr`.

If recurring:
- Check provider quota/keys (never log them).
- If provider is down, set `OCR_PROVIDER=fake` — users keep working.
- Record an incident: `php artisan system:record-maintenance "Incident Response" ...`
  and file a `OCR QUALITY` backlog entry if accuracy is the issue.

## Low-confidence handling

`ocr_processings.confidence_score < min_confidence` counts as low-confidence
(shown by the OCR health check). Reviewers see these signaled so a human can
verify the extracted text — never auto-accepted as ground truth.

## Config reference

`backend/config/ai_reports.php`:
- `ocr.provider` (env `OCR_PROVIDER`, default `fake`)
- `ocr.min_confidence` (default 0.55)
- `jobs.ocr.tries=3`, `backoff=[30,120,300]`, `timeout=300`

## Related

- Monitoring: [`monitoring.md`](monitoring.md)
- AI: [`ai-maintenance.md`](ai-maintenance.md)
- Troubleshooting: [`troubleshooting.md`](troubleshooting.md)