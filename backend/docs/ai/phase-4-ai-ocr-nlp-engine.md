# Phase 4 — AI / OCR / NLP Document Analysis Engine

## Overview
Phase 4 delivers the core AI, OCR, and NLP document analysis pipeline for the **John Paul College (JPC) EPMS**.
It establishes an automated, grounded, and human-supervised document evaluation workflow for faculty accomplishment reports.

---

## Core Pipeline Architecture

```
DOCUMENT UPLOAD
      │
      ▼
FILE VALIDATION & SHA-256 HASH
      │
      ▼
SECURE PRIVATE STORAGE
      │
      ▼
GOOGLE CLOUD VISION / PDF OCR
      │
      ▼
TEXT CLEANING & SECTION SEGMENTATION
      │
      ▼
GROUNDED NLP SUMMARIZATION
      ├───────────────────────┐
      ▼                       ▼
REQUIREMENT VALIDATION    REFERENCE ANCHORING
(Met / Partial / Not)     (Referenced / Partial / Not)
      │                       │
      └───────────┬───────────┘
                  ▼
      CROSS-SUBMISSION SIMILARITY
                  ▼
         STRUCTURED AI FINDINGS
                  ▼
       HUMAN REVIEW WORKSPACE
        ┌─────────┼─────────┐
        ▼         ▼         ▼
     ACCEPT     MODIFY    DISMISS
        │         │         │
        └─────────┼─────────┘
                  ▼
         FINAL VALIDATION DECISION
          ┌───────┴────────┐
          ▼                ▼
       APPROVED        REVISION REQUIRED
```

---

## 1. Document Pipeline & Security
- **Private Storage:** Files stored in `storage/app/private/reports/{id}/...`. Direct public access is strictly prohibited.
- **MIME / Type Validation:** Enforces whitelist (`PDF`, `DOCX`, `DOC`, `JPG`, `JPEG`, `PNG`) with magic-byte checks.
- **SHA-256 Cryptographic Hashing:** Every uploaded report file is hashed upon upload. Identical file uploads across different reports are immediately flagged as `Potential Duplicate File`.

---

## 2. OCR Engine & Provider Abstraction
- **`OcrProviderInterface`:** Contract for text extraction engines.
- **`GoogleVisionOcrService`:** Production integration for Google Cloud Vision OCR with orientation/quality metrics.
- **`EmbeddedTextExtractor`:** Fallback parser for digital PDFs and plain text extractions.
- **Confidence Scoring:** Outputs extraction confidence (0.00 – 1.00). Scans below 0.70 are tagged as `Needs Manual Review` and generate an `ocr_concern` finding.

---

## 3. Grounded NLP Summarization
- **Strict Grounding:** Summaries are strictly derived from report content and task specifications. No hallucination of dates, metrics, or achievements.
- **Configurable Lengths:** `short`, `medium`, `detailed`.
- **Mandatory Disclaimer:**
  > *"AI-generated summary. Verify against the original report before making official decisions."*
- **Structured Fields:** Executive Overview, Key Accomplishments, Activities Completed, Results, Issues/Challenges, and AI Recommendations.

---

## 4. Task Requirement Validation
- Compares report body against task deliverables, criteria, and expected outputs.
- **Status Classification:** `Met`, `Partially Met`, `Not Evident`.
- **Verbatim Evidence Anchoring:** Extracts verbatim text quotes from the report supporting each requirement evaluation.

---

## 5. Reference Anchoring & Citations
- Evaluates whether reports reference required institutional policies, guidelines, and reference URLs.
- **Anchor Status:** `Referenced`, `Partially Referenced`, `Not Evident`, `Unable to Determine`.
- Differentiates between explicit citations and semantic topic mentions.

---

## 6. Similarity & Plagiarism Indicators
- Computes internal cross-submission similarity scores against authorized institutional reports.
- **Risk Tiers:** `Potential Duplicate` (≥85%), `High Similarity` (≥60%), `Moderate Similarity` (≥35%), `Unique` (<35%).
- **Non-Accusatory Rule:** Labeled as an *advisory comparative indicator*, never as automatic proof of plagiarism.

---

## 7. Structured AI Findings & Human Override
- **Finding Types:** `missing_requirement`, `weak_evidence`, `reference_concern`, `potential_duplicate`, `ocr_concern`, `incomplete_report`, `data_inconsistency`.
- **Severity Levels:** `low`, `medium`, `high`, `critical`.
- **Human Actions:**
  - `Accept`: Confirms the AI finding.
  - `Dismiss`: Dismisses the finding with reviewer justification.
  - `Modify`: Edits finding details and audit notes.
  - `Add Manual Finding`: Reviewer adds an independent manual observation.

---

## 8. Multi-Version Diffing & History
- Version N vs Version N-1 comparison displays added/removed text and automatically tracks **Resolved Findings** between report iterations.

---

## 9. API Endpoints Summary

| Method | Endpoint | Description |
|---|---|---|
| `POST` | `/api/v1/ai-processing/{report}/documents` | Secure document upload with SHA-256 hash |
| `POST` | `/api/v1/ai-processing/{report}/ocr` | Trigger OCR text extraction |
| `POST` | `/api/v1/ai-processing/{report}/analyze` | Execute complete AI analysis pipeline |
| `GET` | `/api/v1/ai-processing/{report}/processing-status` | Report processing & OCR status |
| `GET` | `/api/v1/ai-processing/{report}/ai-summary` | Grounded summary with advisory disclaimer |
| `GET` | `/api/v1/ai-processing/{report}/ai-validation` | Requirement checks & verbatim evidence quotes |
| `GET` | `/api/v1/ai-processing/{report}/references` | Reference anchoring matrix |
| `GET` | `/api/v1/ai-processing/{report}/similarity` | Similarity score & cross-report comparisons |
| `GET` | `/api/v1/ai-processing/{report}/findings` | Structured AI findings list |
| `POST` | `/api/v1/ai-processing/{report}/findings/{finding}/review` | Reviewer action (Accept / Dismiss / Modify) |
| `POST` | `/api/v1/ai-processing/{report}/review` | Final official review decision (`validated`, `revision_required`, `rejected`) |
| `POST` | `/api/v1/ai-processing/{report}/retry` | Safe retry of failed AI/OCR processing |
| `GET` | `/api/v1/ai-processing/{report}/versions/compare` | Version comparison & resolved findings diff |
| `GET` | `/api/v1/ai-processing/dashboard` | AI / OCR processing telemetry counts |

---

## Non-Authority Principle
AI outputs are strictly **advisory recommendations**. All official validations, approvals, revisions, and rejections remain exclusively in the hands of authorized human reviewers.
