import { describe, expect, it } from 'vitest'

import {
  buildCriteriaValue,
  criteriaToGuided,
  describeCriteriaSummary,
  isGuidedCriteriaShape,
  parseCriteriaJson,
} from './badgeCriteria'

const humanise = (value: string) =>
  value.toLowerCase().replace(/_/g, ' ').replace(/\b\w/g, (char) => char.toUpperCase())

describe('badgeCriteria', () => {
  it('recognises the stored monthly criteria shape', () => {
    expect(isGuidedCriteriaShape({ period: 'monthly', threshold: 85 })).toBe(true)
    expect(isGuidedCriteriaShape({ period: 'monthly', threshold: 85, min_score: 80 })).toBe(true)
    expect(isGuidedCriteriaShape({})).toBe(true)
  })

  it('refuses to treat custom keys or invalid types as guided', () => {
    expect(isGuidedCriteriaShape({ threshold: 85, lookback_months: 3 })).toBe(false)
    expect(isGuidedCriteriaShape({ threshold: '85' })).toBe(false)
    expect(isGuidedCriteriaShape({ period: 3 })).toBe(false)
    expect(isGuidedCriteriaShape(null)).toBe(false)
    expect(isGuidedCriteriaShape([1, 2])).toBe(false)
  })

  it('maps a recognised object to form strings and back without loss', () => {
    const fields = criteriaToGuided({ period: 'monthly', threshold: 85, min_score: 80 })
    expect(fields).toEqual({ period: 'monthly', threshold: '85', minScore: '80' })

    expect(buildCriteriaValue(fields!)).toEqual({
      ok: true,
      value: { period: 'monthly', threshold: 85, min_score: 80 },
    })
  })

  it('omits blank fields and returns null when nothing is configured', () => {
    expect(buildCriteriaValue({ period: '', threshold: '', minScore: '' })).toEqual({
      ok: true,
      value: null,
    })
    expect(buildCriteriaValue({ period: 'monthly', threshold: '', minScore: '' })).toEqual({
      ok: true,
      value: { period: 'monthly' },
    })
  })

  it('rejects invalid period and non-numeric or negative values', () => {
    expect(buildCriteriaValue({ period: 'month ly', threshold: '', minScore: '' }).ok).toBe(false)
    expect(buildCriteriaValue({ period: '', threshold: 'abc', minScore: '' }).ok).toBe(false)
    expect(buildCriteriaValue({ period: '', threshold: '-5', minScore: '' }).ok).toBe(false)
    expect(buildCriteriaValue({ period: '', threshold: '', minScore: '1e3' }).ok).toBe(false)
  })

  it('parses the raw JSON editor and rejects non-objects or malformed input', () => {
    expect(parseCriteriaJson('')).toEqual({ ok: true, value: null })
    expect(parseCriteriaJson('{"threshold": 85}')).toEqual({ ok: true, value: { threshold: 85 } })
    expect(parseCriteriaJson('[1,2]').ok).toBe(false)
    expect(parseCriteriaJson('null').ok).toBe(false)
    expect(parseCriteriaJson('{ bad').ok).toBe(false)
  })

  it('summarises criteria readably and keeps custom keys visible', () => {
    expect(describeCriteriaSummary('overall_score', { threshold: 95, period: 'monthly' }, humanise))
      .toBe('Overall Score ≥ 95 per monthly')
    expect(
      describeCriteriaSummary(
        'consistency',
        { threshold: 3, min_score: 85, period: 'monthly' },
        humanise,
      ),
    ).toBe('Consistency ≥ 3 (min score 85) per monthly')
    expect(describeCriteriaSummary('custom', { lookback_months: 3 }, humanise)).toBe(
      'Custom (lookback_months)',
    )
    expect(describeCriteriaSummary(null, null, humanise)).toBe('—')
  })
})
