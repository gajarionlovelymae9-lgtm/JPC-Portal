import React from 'react'
import type { DataQualityStatus } from '../../types/dataQuality'
import { STATUS_COLORS } from '../../utils/dataQuality/dataQualityConstants'

interface Props {
  status: DataQualityStatus
  className?: string
}

export const DataQualityStatusBadge: React.FC<Props> = ({ status, className = '' }) => {
  const style = STATUS_COLORS[status] || { bg: 'bg-gray-100 text-gray-700', text: '', border: '' }

  const formatted = status.replace(/_/g, ' ')

  return (
    <span
      className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium border ${style.bg} ${className}`}
    >
      {formatted}
    </span>
  )
}
