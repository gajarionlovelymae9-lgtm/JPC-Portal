import type { DataQualitySeverity, DataQualityStatus, DataQualityScanStatus } from '../../types/dataQuality'

export const SEVERITY_COLORS: Record<DataQualitySeverity, { bg: string; text: string; border: string }> = {
  CRITICAL: { bg: 'bg-red-50 dark:bg-red-950/40', text: 'text-red-700 dark:text-red-300', border: 'border-red-200 dark:border-red-800' },
  HIGH: { bg: 'bg-orange-50 dark:bg-orange-950/40', text: 'text-orange-700 dark:text-orange-300', border: 'border-orange-200 dark:border-orange-800' },
  MEDIUM: { bg: 'bg-amber-50 dark:bg-amber-950/40', text: 'text-amber-700 dark:text-amber-300', border: 'border-amber-200 dark:border-amber-800' },
  LOW: { bg: 'bg-blue-50 dark:bg-blue-950/40', text: 'text-blue-700 dark:text-blue-300', border: 'border-blue-200 dark:border-blue-800' },
}

export const STATUS_COLORS: Record<DataQualityStatus, { bg: string; text: string; border: string }> = {
  OPEN: { bg: 'bg-red-100 text-red-800 dark:bg-red-900/50 dark:text-red-300', text: 'text-red-800', border: 'border-red-200' },
  UNDER_REVIEW: { bg: 'bg-amber-100 text-amber-800 dark:bg-amber-900/50 dark:text-amber-300', text: 'text-amber-800', border: 'border-amber-200' },
  CONFIRMED: { bg: 'bg-purple-100 text-purple-800 dark:bg-purple-900/50 dark:text-purple-300', text: 'text-purple-800', border: 'border-purple-200' },
  DISMISSED: { bg: 'bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-300', text: 'text-gray-800', border: 'border-gray-200' },
  RESOLVED: { bg: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/50 dark:text-emerald-300', text: 'text-emerald-800', border: 'border-emerald-200' },
  IGNORED: { bg: 'bg-slate-100 text-slate-800 dark:bg-slate-800 dark:text-slate-300', text: 'text-slate-800', border: 'border-slate-200' },
}

export const SCAN_STATUS_COLORS: Record<DataQualityScanStatus, string> = {
  QUEUED: 'bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-300',
  RUNNING: 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300 animate-pulse',
  COMPLETED: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300',
  FAILED: 'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-300',
  CANCELLED: 'bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-300',
}
