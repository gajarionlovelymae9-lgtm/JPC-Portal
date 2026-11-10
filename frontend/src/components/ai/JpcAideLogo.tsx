import { useId, type SVGProps } from 'react'

export const JPC_AIDE_NAME = 'JPC AIDE'
export const JPC_AIDE_FULL_NAME = 'AI for Assistance, Insights, Development, and Efficiency'
export const JPC_AIDE_TAGLINE = 'Your Intelligent Partner in Productivity'

interface JpcAideLogoProps extends SVGProps<SVGSVGElement> {
  size?: number
  title?: string
}

/**
 * JPC AIDE brand mark: a clean, modern intelligent-assistant symbol
 * using the system's blue and purple palette.
 */
export function JpcAideLogo({ size = 32, title = JPC_AIDE_NAME, className, ...props }: JpcAideLogoProps) {
  const gradientId = `jpc-aide-gradient-${useId().replace(/:/g, '')}`

  return (
    <svg
      width={size}
      height={size}
      viewBox="0 0 64 64"
      fill="none"
      xmlns="http://www.w3.org/2000/svg"
      role="img"
      aria-label={title}
      className={className}
      {...props}
    >
      <title>{title}</title>
      <defs>
        <linearGradient id={gradientId} x1="8" y1="8" x2="56" y2="56" gradientUnits="userSpaceOnUse">
          <stop stopColor="#2563EB" />
          <stop offset="1" stopColor="#7C3AED" />
        </linearGradient>
      </defs>
      <rect x="6" y="10" width="52" height="40" rx="14" fill={`url(#${gradientId})`} />
      <rect x="18" y="23" width="8" height="8" rx="4" fill="white" opacity="0.98" />
      <rect x="38" y="23" width="8" height="8" rx="4" fill="white" opacity="0.98" />
      <path d="M22 39C25 43 39 43 42 39" stroke="white" strokeWidth="3.2" strokeLinecap="round" />
      <path d="M32 4V10" stroke="#7C3AED" strokeWidth="3" strokeLinecap="round" />
      <circle cx="32" cy="4" r="2.5" fill="#2563EB" />
      <path d="M14 30H6" stroke="#2563EB" strokeWidth="3" strokeLinecap="round" opacity="0.9" />
      <path d="M58 30H50" stroke="#7C3AED" strokeWidth="3" strokeLinecap="round" opacity="0.9" />
      <path d="M24 50L20 58" stroke="#2563EB" strokeWidth="2.6" strokeLinecap="round" opacity="0.55" />
      <path d="M40 50L44 58" stroke="#7C3AED" strokeWidth="2.6" strokeLinecap="round" opacity="0.55" />
    </svg>
  )
}

export function JpcAideMark({ className = '' }: { className?: string }) {
  return (
    <span className={`inline-flex items-center gap-2 ${className}`.trim()}>
      <JpcAideLogo size={22} />
      <span className="font-semibold text-slate-900">{JPC_AIDE_NAME}</span>
    </span>
  )
}
