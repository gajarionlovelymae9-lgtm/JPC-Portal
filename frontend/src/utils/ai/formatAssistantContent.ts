import { sanitize } from './sanitizeAiContent'

/**
 * Legacy advisory footer that older admin assistant messages may contain.
 *
 * New responses keep this information in `tool_results` instead of the
 * user-facing content. This only strips the exact legacy footer at render
 * time so existing chat history stays readable without mutating stored rows.
 */
const LEGACY_ADVISORY_FOOTER =
  /\s*---\s*\n\s*_AI-generated advisory response \(Phase B\)\.[\s\S]*$/i

export function stripLegacyAdvisoryMetadata(text: string | null | undefined): string {
  if (!text) return ''
  return text.replace(LEGACY_ADVISORY_FOOTER, '').replace(/\s+$/, '')
}

/**
 * Prepares an assistant message for display: removes legacy internal
 * metadata footers, then sanitizes the remaining user-facing text.
 */
export function formatAssistantContent(text: string | null | undefined): string {
  return sanitize(stripLegacyAdvisoryMetadata(text))
}
