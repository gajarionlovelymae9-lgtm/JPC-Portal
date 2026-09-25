# JPC Portal — Future Enhancements

Possible improvements considered outside the current scope. **These are future
features only — they are not implemented and should not be started unless explicitly
required.**

## Applications & clients

- **Mobile application** (React Native / PWA) for employees to submit reports and
  check status on the go. The API is already REST; mobile auth would use token-based
  Sanctum tokens (`personal_access_tokens` table already exists).
- **Offline support** — offline-first queueing for report drafts/uploads on mobile
  (server-side OCR/AI would re-run on reconnection).

## Analytics & intelligence

- **Advanced predictive analytics** — forecast completion rates, deadlines and
  performance using historical snapshots (`employee_progress_snapshots`).
- **Advanced anomaly detection** — detect unusual submission patterns, score
  outliers, or data-quality anomalies beyond the current rule scans.
- **Institution-wide benchmarking** — compare departments/years/months using the
  existing analytics/trend tables.
- **Advanced recommendation engine** — suggest task prioritization or target
  adjustments from performance data.

## AI / OCR

- **More AI providers** — plug in additional OpenAI-compatible or local (e.g.
  Ollama) providers; the provider abstraction (`HttpAiProvider`, config) is ready.
- **Advanced OCR** — multi-page/document layout analysis, handwriting support, and
  confidence-gated post-processing.
- **Document classification** — auto-classify uploaded documents into categories/
  types before analysis.
- **Prompt/tool versioning** — versioned system prompts and tool schemas with an
  admin-visible changelog (customers can already set `AI_ANALYSIS_VERSION`).

## HR / operations

- **Biometric / time-attendance integration** — only if approved institutionally;
  would connect to a separate attendance source and feed performance data.
- **Enterprise deployment** — SSO (SAML/OIDC), multi-institution tenants, LDAP sync.
- **Cloud scaling** — managed MySQL/Redis, S3 for private files, horizontal queue
  workers, CDN for the SPA.
- **Advanced permissions** — granular per-department role scoping and delegation.

## Engineering

- **Frontend code-splitting / lazy loading** — reduce the ~846 kB initial bundle
  (dynamic `import()` per route).
- **Better performance monitoring** — query profiling, request tracing, queue
  latency dashboards.
- **Localization** — multi-language UI and report templates.

## Process notes

- Each item needs a requirements pass before implementation.
- Any change reusing existing tables/services must extend, not duplicate, the
  current modules.
- New features must keep the AI governance boundary (human decision-making) intact.