# JPC EPMS — AI/OCR Processing Center (Phase 3.3-Q)

## Purpose
Central pipeline that turns uploaded reports into structured AI analysis:
secure upload → file validation → OCR (Google Cloud Vision) → text
normalization → classification → summarization → requirement matching →
reference extraction & anchoring → consistency analysis → confidence
scoring → human review.

**AI does not make administrative decisions.** It produces analysis,
summaries, recommendations and confidence indicators; the authorized
human reviewer makes the final decision.

## Architecture

```
┌─────────────┐     ┌──────────────┐     ┌─────────────┐
│  EMPLOYEE   │──→──│ FILE VALIDATE│──→──│  SECURE     │
│  UPLOAD     │     │  + STORAGE   │     │  STORAGE    │
└─────────────┘     └──────┬───────┘     └──────┬──────┘
                           ↓                    ↓
                    ┌──────────────┐     ┌─────────────┐
                    │  OCR ENGINE  │     │  DOCUMENT   │
                    │ (Google Viz) │     │  CLASSIFY   │
                    └──────┬───────┘     └──────┬──────┘
                           ↓                    ↓
                    ┌──────────────┐     ┌─────────────┐
                    │ TEXT         │     │  AI SUMMAR- │
                    │ NORMALIZE    │     │  IZATION    │
                    └──────┬───────┘     └──────┬──────┘
                           ↓                    ↓
              ┌────────────┴─────────────────────┴──────┐
              │        AI PROCESSING ORCHESTRATOR        │
              │  requirements · references · consistency │
              │  · confidence · issues                   │
              └────────────┬────────────────────────────┘
                           ↓
                    ┌──────────────┐
                    │  HUMAN       │
                    │  REVIEW      │
                    └──────────────┘
```

## Abstraction layers (§12, §91–§96)

- **OCR**: `OcrServiceInterface` (`Services\AiReports\Ocr\Contracts\OcrProvider`) with `GoogleVisionOcrService` (production) and `FakeOcrProvider` (offline/test).
- **AI**: `AiAnalysisServiceInterface` (`Services\AiReports\Ai\Contracts\AiProvider`) with `HttpAiProvider` (real) and `FakeAiProvider` (deterministic).
- **Validation**: `JsonSchemaValidator` + `PromptInjectionGuard` enforce structured, injection-safe output.
- **Orchestrator**: `ReportAnalysisService` (final) coordinates OCR → normalization → classification → summarization → validation → references → consistency → confidence.

This abstraction means neither Google Vision nor the AI provider can be swapped without touching the domain.

## OCR (Google Cloud Vision)
- Credentials come from `.env` (`GOOGLE_CLOUD_PROJECT_ID`, `GOOGLE_APPLICATION_CREDENTIALS`); never hard-coded or committed.
- Errors handled: invalid credentials, quota, network, unsupported document, timeout.
- Result stored: raw text, confidence, page/block/word structure, timestamp.
- Low-confidence OCR (< 70% configurable) flags `OCR_REVIEW_REQUIRED` with the standard warning.
- Original OCR is never overwritten; corrections go to `corrected_ocr_text` with an audit trail.

## AI summarization
- Short/Medium/Detailed levels; default Short.
- Structured output: executive summary, key activities, accomplishments, results, issues, recommendations.
- Hallucination control: grounded strictly in extracted text; every claim maps to source section/page.
- Disclaimer shown: "AI-generated summary. Verify against the original report."

## Reference analysis
- Extracts URLs, books, articles, policies, institutional/government documents.
- Anchoring language is **"Potentially supported by reference"** — never "proven true".
- Flags: claim without reference, disconnected reference, broken URL, incomplete citation.

## Validation & confidence
- Four interpretable indicators (no single "magical" score):
  - Requirement Match
  - Completeness
  - Reference Alignment
  - Consistency
- Each finding carries a confidence label (High / Medium / Low) with a warning when low.
- Severity: LOW / MEDIUM / HIGH.

## Security & privacy
- Only the minimum required report data is sent to the AI provider.
- Prompt injection guard treats uploaded content as untrusted data, never as system instructions.
- AI output validated against a JSON schema; malformed output retries or marks `AI_PROCESSING_FAILED`.
- Policies: `ReportPolicy`, `AiAnalysisPolicy`, `DocumentPolicy`.
- Downloads always authorize; uploads never trust extension alone (MIME + content validation).

## Queue, retry & versioning
- `ProcessReportDocumentJob` queued with `WithoutOverlapping` (idempotent).
- Stage failures record the error (stage, code, message, timestamp) and keep `FAILED` — never silently `AI_COMPLETED`.
- Partial results are stored but never presented as final analysis.
- Reprocess creates a new analysis **version**; old results preserved in history.
- Versioning tracks: OCR engine version, AI model/provider, prompt version, analysis version.
- Caching reuses completed analysis until the report or analysis version changes.

## Human review & feedback
- `NEEDS_HUMAN_REVIEW` flagged for low OCR confidence, AI uncertainty, major inconsistency, missing requirement, or reference issue.
- Reviewer marks findings Correct / Incorrect / Uncertain; feedback stored for future improvement only.
- **No automatic retraining** — feedback is stored unless a controlled ML pipeline is explicitly added later.

## Configuration
- `config/ai.php`: provider, model, timeouts, retry, confidence thresholds, summary settings.
- `config/ocr.php`: provider, language, timeout, max file size, confidence threshold.
- Example env keys in docs (no real credentials).

## Testing
- `tests/Feature\Api/V1/AiReportProcessingTest.php`: dashboard, process, idempotency, summary access, cross-user authorization denial.
- Existing domain tests cover OCR, AI provider, consistency, references, similarity.

## Documentation
See `docs/ai/phase-3-3-q-ai-ocr-processing-center.md` (this file) and `docs/notifications/phase-3-3-p-notification-system.md`.
