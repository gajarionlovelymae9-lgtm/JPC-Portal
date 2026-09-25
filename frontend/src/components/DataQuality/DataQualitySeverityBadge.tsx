import React from 'react'
import type { DataQualitySeverity } from '../../types/dataQuality'
import { SEVERITY_COLORS } from '../../utils/dataQuality/dataQualityConstants'

interface Props {
  severity: DataQualitySeverity
  className?: string
}

export const DataQualitySeverityBadge: React.FC<Props> = ({ severity, className = '' }) => {
  const style = SEVERITY_COLORS[severity] || { bg: 'bg-gray-100', text: 'text-gray-700', border: 'border-gray-200' }

  return (
    <span
      className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold border ${style.bg} ${style.text} ${style.border} ${className}`}
    >
      {severity}
    </span>
  )
}
