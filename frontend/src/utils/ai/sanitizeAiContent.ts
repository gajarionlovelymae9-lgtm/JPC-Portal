/**
 * Sanitizes text by stripping out harmful tags like script, iframe, object, embed,
 * and event handlers (onclick, onerror, etc.)
 */
export function sanitize(text: string | null | undefined): string {
  if (!text) return ''

  let sanitized = text
  // Remove dangerous tags and their contents
  sanitized = sanitized.replace(/<script\b[^<]*(?:(?!<\/script>)<[^<]*)*<\/script>/gi, '')
  sanitized = sanitized.replace(/<iframe\b[^<]*(?:(?!<\/iframe>)<[^<]*)*<\/iframe>/gi, '')
  sanitized = sanitized.replace(/<object\b[^<]*(?:(?!<\/object>)<[^<]*)*<\/object>/gi, '')
  sanitized = sanitized.replace(/<embed\b[^<]*(?:(?!<\/embed>)<[^<]*)*<\/embed>/gi, '')

  // Remove dangerous tags without closing tags if any
  sanitized = sanitized.replace(/<(script|iframe|object|embed)[^>]*>/gi, '')

  // Remove inline event handlers (on...)
  sanitized = sanitized.replace(/\bon[a-z]+(\s*=\s*(['"])[^'"]*\2|\s*=\s*[^>\s]+)/gi, '')

  return sanitized
}
