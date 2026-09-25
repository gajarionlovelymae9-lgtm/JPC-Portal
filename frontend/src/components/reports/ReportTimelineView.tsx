import React from 'react'
import type { ReportTimelineEvent } from '../../types/reportWorkflow'

interface ReportTimelineViewProps {
  timeline: ReportTimelineEvent[]
  loading?: boolean
}

export const ReportTimelineView: React.FC<ReportTimelineViewProps> = ({ timeline, loading = false }) => {
  if (loading) {
    return (
      <div className="flex items-center justify-center p-8">
        <div className="w-8 h-8 border-4 border-indigo-600 border-t-transparent rounded-full animate-spin" />
      </div>
    )
  }

  if (!timeline || timeline.length === 0) {
    return (
      <div className="text-center py-8 text-gray-500">
        <div className="text-3xl mb-2">⏱️</div>
        <p className="text-sm">No timeline events recorded yet.</p>
      </div>
    )
  }

  const getEventIcon = (eventType: string) => {
    switch (eventType) {
      case 'approved':
        return <span className="text-emerald-600 text-base font-bold">✓</span>
      case 'rejected':
      case 'cancelled':
        return <span className="text-rose-600 text-base font-bold">✕</span>
      case 'revision_requested':
      case 'resubmitted':
        return <span className="text-amber-600 text-base font-bold">🔄</span>
      case 'comment_added':
      case 'comment_resolved':
        return <span className="text-blue-600 text-base font-bold">💬</span>
      default:
        return <span className="text-indigo-600 text-base font-bold">📄</span>
    }
  }

  const getBadgeColor = (eventType: string) => {
    switch (eventType) {
      case 'approved':
        return 'bg-emerald-50 text-emerald-700 border-emerald-200'
      case 'rejected':
      case 'cancelled':
        return 'bg-rose-50 text-rose-700 border-rose-200'
      case 'revision_requested':
        return 'bg-amber-50 text-amber-700 border-amber-200'
      case 'resubmitted':
        return 'bg-purple-50 text-purple-700 border-purple-200'
      case 'submitted':
        return 'bg-blue-50 text-blue-700 border-blue-200'
      default:
        return 'bg-gray-50 text-gray-700 border-gray-200'
    }
  }

  return (
    <div className="flow-root">
      <ul className="-mb-8">
        {timeline.map((event, eventIdx) => (
          <li key={event.id || eventIdx}>
            <div className="relative pb-8">
              {eventIdx !== timeline.length - 1 ? (
                <span
                  className="absolute left-4 top-4 -ml-px h-full w-0.5 bg-gray-200"
                  aria-hidden="true"
                />
              ) : null}
              <div className="relative flex space-x-3 items-start">
                <div className="flex h-8 w-8 items-center justify-center rounded-full bg-white ring-8 ring-white shadow-sm border border-gray-200">
                  {getEventIcon(event.event_type)}
                </div>
                <div className="flex-1 min-w-0">
                  <div className="flex items-center justify-between">
                    <p className="text-sm font-semibold text-gray-900">{event.title}</p>
                    <time className="text-xs text-gray-500" dateTime={event.created_at}>
                      {new Date(event.created_at).toLocaleString()}
                    </time>
                  </div>
                  <p className="text-sm text-gray-600 mt-1">{event.description}</p>
                  {event.actor && (
                    <div className="mt-1 flex items-center gap-2 text-xs text-gray-500">
                      <span>Actor: <strong className="text-gray-700">{event.actor.name}</strong></span>
                      <span className={`px-2 py-0.5 rounded-full text-[11px] font-medium border ${getBadgeColor(event.event_type)}`}>
                        {event.event_type.replace('_', ' ').toUpperCase()}
                      </span>
                    </div>
                  )}
                  {event.metadata && Object.keys(event.metadata).length > 0 && (
                    <div className="mt-2 text-xs bg-gray-50 rounded p-2 border border-gray-100 text-gray-600 space-y-0.5">
                      {event.metadata.reason && <div><strong>Reason:</strong> {event.metadata.reason}</div>}
                      {event.metadata.change_summary && <div><strong>Changes:</strong> {event.metadata.change_summary}</div>}
                      {event.metadata.comments && <div><strong>Comments:</strong> {event.metadata.comments}</div>}
                      {event.metadata.version && <div><strong>Version:</strong> v{event.metadata.version}</div>}
                    </div>
                  )}
                </div>
              </div>
            </div>
          </li>
        ))}
      </ul>
    </div>
  )
}
