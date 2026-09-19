interface StatusDotProps {
  kind: 'ok' | 'error' | 'loading' | 'muted'
  label: string
}

const KIND_CLASSES: Record<StatusDotProps['kind'], string> = {
  ok: 'status-dot status-dot--ok',
  error: 'status-dot status-dot--error',
  loading: 'status-dot status-dot--loading',
  muted: 'status-dot status-dot--muted',
}

export function StatusDot({ kind, label }: StatusDotProps) {
  return (
    <span className="status-dot" data-kind={kind}>
      <span className={KIND_CLASSES[kind]} aria-hidden="true" />
      <span>{label}</span>
    </span>
  )
}