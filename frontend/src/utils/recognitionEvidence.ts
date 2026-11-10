/**
 * recognitionEvidence.ts
 *
 * Shared presentation-layer formatting for award evidence / criteria
 * snapshots (used by both the HR and Admin Achievement modals).
 *
 * Raw JSON is never rendered: every leaf becomes a {label, value} row.
 * NULL/missing/blank fields are omitted, and internal development metadata
 * (seeder names, "development.*" sources, DB implementation notes) is hidden
 * from normal users. No evidence is fabricated — fields the database does
 * not have simply do not appear.
 */

/** Friendlier names for snapshot keys that humanise() cannot improve. */
const EVIDENCE_LABELS: Record<string, string> = {
  overall_score: 'Overall performance score',
  note: 'Notes',
  reason: 'Reason',
  criteria: 'Criteria',
  evidence: 'Evidence',
  threshold: 'Required value',
  min_score: 'Minimum score',
  monthly_rank: 'Monthly rank',
  measured_period: 'Measured period',
}

/** Rate-like fields whose stored value is a percentage (e.g. "99.10" = 99.10%). */
const PERCENT_KEYS = new Set([
  'completion_rate',
  'on_time_rate',
  'approval_rate',
  'quality_score',
  'target_achievement_rate',
  'overall_score',
])

/** Top-level payload sections — they carry context, not label words. */
const EVIDENCE_SECTION_KEYS = new Set(['snapshot', 'measured', 'badge_criteria'])

/** Internal DB/seeder metadata that never belongs in the user-facing modal. */
const INTERNAL_SNAPSHOT_KEYS = new Set(['source', 'development', 'seeder'])

export const isPercentEvidenceKey = (key: string): boolean => PERCENT_KEYS.has(key)

export function humanise(value: string | null | undefined) {
  if (!value) return '—'
  // Lowercase first so UPPERCASE codes (TASK_CHAMPION) still become
  // "Task Champion" rather than staying all-caps.
  return value.toLowerCase().replace(/_/g, ' ').replace(/\b\w/g, (char) => char.toUpperCase())
}

function scoreLabel(value: unknown): string {
  const parsed = typeof value === 'number' ? value : Number(value)
  if (typeof value !== 'number' && typeof value !== 'string') return 'Not available'
  if (value === '' || Number.isNaN(parsed)) return 'Not available'
  return Number.isInteger(parsed) ? String(parsed) : parsed.toFixed(2)
}

/** Seeders/development identifiers (e.g. "development.seeder") must never show. */
export function isDevMetadata(value: unknown): boolean {
  return typeof value === 'string' && (
    /DEVELOPMENT SAMPLE DATA/i.test(value) ||
    /^development\./i.test(value) ||
    /seeder/i.test(value)
  )
}

function formatEvidenceValue(value: unknown): string | null {
  if (value === null || value === undefined) return null
  if (typeof value === 'boolean') return value ? 'Yes' : 'No'
  if (typeof value === 'number') return scoreLabel(value)
  if (typeof value === 'string') {
    return value.trim() === '' || isDevMetadata(value) ? null : value
  }
  return null // nested structures are handled by the recursive collector
}

export interface RecognitionEvidenceRow {
  label: string
  value: string
}

function labelFor(chain: string[]): string {
  const last = chain[chain.length - 1] ?? ''
  // Section containers ("measured", "snapshot", "badge_criteria") add context
  // that the labels already imply — "Overall performance score" beats
  // "Measured – Overall score".
  const chainWithoutSections = chain.filter((key) => !EVIDENCE_SECTION_KEYS.has(key))
  if (chainWithoutSections.length >= 2) {
    const parent = chainWithoutSections[chainWithoutSections.length - 2]
    return `${humanise(parent)} – ${humanise(last)}`
  }
  if (chainWithoutSections.length > 0) {
    const key = chainWithoutSections[chainWithoutSections.length - 1]
    return EVIDENCE_LABELS[key] ?? humanise(key)
  }
  return EVIDENCE_LABELS[last] ?? humanise(last)
}

/**
 * Turn the stored evidence / criteria snapshot into readable rows.
 *
 * - Raw JSON is never rendered: every leaf becomes {label, value}.
 * - `null`, missing and blank fields are omitted (they add no meaning).
 * - Internal metadata (seeder names, "development.*" sources) is hidden from
 *   normal users.
 * - If nothing meaningful survives, `hasEvidence` comes back false so the
 *   panel can show "No evidence details available."
 */
export function toRecognitionEvidence(snapshot: unknown): {
  rows: RecognitionEvidenceRow[]
  criteria: string[]
  hasEvidence: boolean
} {
  const rows: RecognitionEvidenceRow[] = []
  const criteria: string[] = []

  const collect = (chain: string[], value: unknown): void => {
    if (value === null || value === undefined) return
    if (isDevMetadata(value)) return
    if (Array.isArray(value)) {
      // Criteria lists: strings stay readable; nested objects are flattened.
      const items = value.filter((item) =>
        (typeof item === 'string' && item.trim() !== '' && !isDevMetadata(item)) ||
        (item !== null && typeof item === 'object' && Object.keys(item as object).length > 0),
      )
      items.forEach((item, index) => {
        if (typeof item === 'object') {
          collect([...chain, String(index + 1)], item)
        } else {
          const text = String(item).trim()
          if (text !== '' && !isDevMetadata(text)) criteria.push(text)
        }
      })
      return
    }
    if (typeof value === 'object') {
      Object.entries(value as Record<string, unknown>).forEach(([key, nested]) => {
        if (key === 'criteria' && Array.isArray(nested)) {
          collect([...chain, key], nested)
          return
        }
        if (INTERNAL_SNAPSHOT_KEYS.has(key) || key.toLowerCase().startsWith('dev')) return
        collect([...chain, key], nested)
      })
      return
    }
    const key = [...chain].reverse().find((k) => !EVIDENCE_SECTION_KEYS.has(k)) ?? ''
    const formatted = formatEvidenceValue(value)
    if (formatted === null) return
    if (PERCENT_KEYS.has(key) && /^-?\d+(\.\d+)?$/.test(formatted)) {
      rows.push({ label: labelFor(chain), value: `${formatted}%` })
      return
    }
    if (key === 'metric' || key === 'period') {
      rows.push({ label: labelFor(chain), value: humanise(formatted) })
      return
    }
    rows.push({ label: labelFor(chain), value: formatted })
  }

  if (snapshot === null || typeof snapshot !== 'object') {
    if (typeof snapshot === 'string' && snapshot.trim() !== '' && !isDevMetadata(snapshot)) {
      rows.push({ label: 'Notes', value: snapshot })
    }
    return { rows, criteria, hasEvidence: rows.length > 0 || criteria.length > 0 }
  }

  collect([], snapshot)
  return { rows, criteria, hasEvidence: rows.length > 0 || criteria.length > 0 }
}
