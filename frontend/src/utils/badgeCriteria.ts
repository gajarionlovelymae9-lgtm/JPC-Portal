/**
 * badgeCriteria.ts
 *
 * Model for the badge "criteria value" JSON object (stored as a JSON column on
 * `badges.criteria_value`). Known fields get a guided form; anything the form
 * cannot represent safely falls back to a raw (validated) JSON editor so
 * custom properties are never discarded.
 *
 * Known shape (all optional):
 *   { "period": "monthly", "threshold": 85, "min_score": 80 }
 */

export interface GuidedCriteriaFields {
  period: string
  threshold: string
  minScore: string
}

export const CRITERIA_PERIOD_OPTIONS = [
  { value: 'monthly', label: 'Monthly' },
  { value: 'quarterly', label: 'Quarterly' },
  { value: 'yearly', label: 'Yearly' },
]

const KNOWN_CRITERIA_KEYS = new Set(['period', 'threshold', 'min_score'])

const isPlainObject = (value: unknown): value is Record<string, unknown> =>
  typeof value === 'object' && value !== null && !Array.isArray(value)

/**
 * True when the object contains only recognised fields with valid types, so
 * the guided form can round-trip it without losing information.
 */
export function isGuidedCriteriaShape(value: unknown): value is Record<string, unknown> {
  if (!isPlainObject(value)) return false

  return Object.entries(value).every(([key, entry]) => {
    if (!KNOWN_CRITERIA_KEYS.has(key)) return false
    if (key === 'period') return typeof entry === 'string'
    return typeof entry === 'number' && Number.isFinite(entry)
  })
}

/** Convert a recognised criteria object into form strings (null = fallback). */
export function criteriaToGuided(value: unknown): GuidedCriteriaFields | null {
  if (value === null || value === undefined) {
    return { period: '', threshold: '', minScore: '' }
  }
  if (!isGuidedCriteriaShape(value)) return null

  return {
    period: typeof value.period === 'string' ? value.period : '',
    threshold: typeof value.threshold === 'number' ? String(value.threshold) : '',
    minScore: typeof value.min_score === 'number' ? String(value.min_score) : '',
  }
}

/** Serialise the form back into the stored criteria object shape. */
export function buildCriteriaValue(
  fields: GuidedCriteriaFields,
): { ok: true; value: Record<string, unknown> | null } | { ok: false; error: string } {
  const value: Record<string, unknown> = {}

  const period = fields.period.trim()
  if (period !== '') {
    if (!/^[a-z][a-z0-9_-]*$/i.test(period)) {
      return { ok: false, error: 'Period may only contain letters, numbers, hyphens and underscores.' }
    }
    value.period = period
  }

  for (const [key, label] of [
    ['threshold', 'Threshold'],
    ['min_score', 'Minimum score'],
  ] as const) {
    const raw = key === 'threshold' ? fields.threshold.trim() : fields.minScore.trim()
    if (raw === '') continue
    if (!/^\d+(\.\d+)?$/.test(raw)) {
      return { ok: false, error: `${label} must be a non-negative number.` }
    }
    value[key] = Number(raw)
  }

  return { ok: true, value: Object.keys(value).length > 0 ? value : null }
}

/** Parse the raw JSON editor content; the result must be an object. */
export function parseCriteriaJson(
  raw: string,
): { ok: true; value: Record<string, unknown> | null } | { ok: false; error: string } {
  if (raw.trim() === '') return { ok: true, value: null }

  try {
    const parsed: unknown = JSON.parse(raw)
    if (!isPlainObject(parsed)) {
      return { ok: false, error: 'Criteria value must be a JSON object.' }
    }
    return { ok: true, value: parsed }
  } catch {
    return { ok: false, error: 'Criteria value must be valid JSON.' }
  }
}

/** Readable summary for the badge-definition table. */
export function describeCriteriaSummary(
  type: string | null | undefined,
  value: Record<string, unknown> | null | undefined,
  humanise: (value: string) => string,
): string {
  if (!type) return '—'

  if (!isPlainObject(value)) return humanise(type)

  const threshold = typeof value.threshold === 'number' ? value.threshold : null
  const minScore = typeof value.min_score === 'number' ? value.min_score : null
  const period = typeof value.period === 'string' ? value.period : null

  const parts: string[] = [humanise(type)]
  if (threshold !== null) parts.push(`≥ ${threshold}`)
  if (minScore !== null) parts.push(`(min score ${minScore})`)
  if (period) parts.push(`per ${period}`)
  if (threshold === null && minScore === null && !period) {
    const extraKeys = Object.keys(value)
    if (extraKeys.length > 0) parts.push(`(${extraKeys.join(', ')})`)
  }

  return parts.join(' ')
}
