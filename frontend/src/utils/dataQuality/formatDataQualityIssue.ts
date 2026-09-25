export function formatIssueType(type: string): string {
  return type
    .replace(/_/g, ' ')
    .toLowerCase()
    .replace(/\b\w/g, (char) => char.toUpperCase())
}

export function formatJsonValue(val: any): string {
  if (val === null || val === undefined) return 'None'
  if (typeof val === 'object') {
    return JSON.stringify(val, null, 2)
  }
  return String(val)
}
