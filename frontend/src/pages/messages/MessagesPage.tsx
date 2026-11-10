import { useCallback, useEffect, useMemo, useRef, useState } from 'react'
import { useSearchParams } from 'react-router-dom'
import {
  Archive,
  ArchiveRestore,
  Check,
  CheckCheck,
  ChevronLeft,
  FileText,
  Info,
  LogOut,
  MessageSquarePlus,
  Paperclip,
  Plus,
  Reply,
  Search,
  Send,
  UserPlus,
  Users,
  X,
} from 'lucide-react'
import {
  addGroupParticipants,
  archiveConversation,
  createGroupConversation,
  getConversation,
  getConversations,
  getRecipients,
  getUnreadMessageSummary,
  leaveConversation,
  removeGroupParticipant,
  sendMessage,
  type ConversationDetail,
  type ConversationSummary,
  type DirectoryUser,
  type MessageItem,
  type UnreadSummary,
} from '../../services/notificationCenterService'
import { downloadFile } from '../../services/api'
import { useAuth } from '../../auth/useAuth'

// ─── Constants ──────────────────────────────────────────────────
type FilterKey = 'all' | 'unread' | 'archived' | 'private' | 'group'

const FILTERS: Array<{ key: FilterKey; label: string }> = [
  { key: 'all', label: 'All' },
  { key: 'unread', label: 'Unread' },
  { key: 'private', label: 'Private' },
  { key: 'group', label: 'Groups' },
  { key: 'archived', label: 'Archived' },
]

const ROLE_FILTERS: Array<{ key: string; label: string }> = [
  { key: '', label: 'All roles' },
  { key: 'admin', label: 'Admin' },
  { key: 'dean', label: 'Dean' },
  { key: 'hr', label: 'HR' },
  { key: 'department_head', label: 'Department Head' },
  { key: 'employee', label: 'Employee' },
]

const ROLE_LABELS: Record<string, string> = {
  admin: 'Admin',
  hr: 'HR',
  department_head: 'Department Head',
  employee: 'Employee',
}

const ATTACHMENT_ACCEPT =
  '.pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png,application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document,application/vnd.ms-excel,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,image/jpeg,image/png'

const ATTACHMENT_MAX_BYTES = 10 * 1024 * 1024
const POLL_INTERVAL_MS = 8000

// ─── Helpers ────────────────────────────────────────────────────
function timeAgo(dateStr: string | null): string {
  if (!dateStr) return ''
  const diff = Date.now() - new Date(dateStr).getTime()
  const mins = Math.floor(diff / 60000)
  if (mins < 1) return 'just now'
  if (mins < 60) return `${mins}m`
  const hrs = Math.floor(mins / 60)
  if (hrs < 24) return `${hrs}h`
  const days = Math.floor(hrs / 24)
  if (days < 7) return `${days}d`
  return new Date(dateStr).toLocaleDateString(undefined, { day: 'numeric', month: 'short' })
}

function formatTime(dateStr: string): string {
  return new Date(dateStr).toLocaleTimeString(undefined, { hour: '2-digit', minute: '2-digit' })
}

function dayKey(dateStr: string): string {
  const d = new Date(dateStr)
  return `${d.getFullYear()}-${d.getMonth() + 1}-${d.getDate()}`
}

function formatDayLabel(dateStr: string): string {
  const date = new Date(dateStr)
  const today = new Date()
  const yesterday = new Date()
  yesterday.setDate(today.getDate() - 1)
  if (dayKey(dateStr) === dayKey(today.toISOString())) return 'Today'
  if (dayKey(dateStr) === dayKey(yesterday.toISOString())) return 'Yesterday'
  return date.toLocaleDateString(undefined, { day: 'numeric', month: 'long', year: 'numeric' })
}

function initialsOf(name: string | null | undefined): string {
  if (!name) return 'U'
  const parts = name.trim().split(/\s+/).slice(0, 2)
  return parts.map((p) => p.charAt(0).toUpperCase()).join('') || 'U'
}

function makeClientToken(): string {
  if (typeof crypto !== 'undefined' && typeof crypto.randomUUID === 'function') return crypto.randomUUID()
  return `msg-${Date.now()}-${Math.random().toString(36).slice(2, 10)}`
}

function formatBytes(bytes: number): string {
  if (bytes < 1024) return `${bytes} B`
  if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(0)} KB`
  return `${(bytes / (1024 * 1024)).toFixed(1)} MB`
}

// ─── Small presentational pieces ────────────────────────────────
function Avatar({
  name,
  url,
  size = 40,
  group = false,
}: {
  name: string | null | undefined
  url?: string | null
  size?: number
  group?: boolean
}) {
  if (url) {
    return (
      <img
        src={url}
        alt={name ?? 'Avatar'}
        width={size}
        height={size}
        style={{ width: size, height: size }}
        className="rounded-full object-cover flex-shrink-0"
      />
    )
  }
  return (
    <span
      style={{ width: size, height: size }}
      className={`rounded-full flex items-center justify-center font-bold flex-shrink-0 ${
        group ? 'bg-[#EAF6EF] text-[#1E3A8A]' : 'bg-[#1E3A8A] text-white'
      }`}
    >
      {group ? <Users size={Math.round(size / 2)} /> : initialsOf(name)}
    </span>
  )
}

// ─── Page ───────────────────────────────────────────────────────
export default function MessagesPage() {
  const { user } = useAuth()
  const viewerId = user?.id ?? 0
  const [searchParams, setSearchParams] = useSearchParams()

  const [conversations, setConversations] = useState<ConversationSummary[]>([])
  const [unread, setUnread] = useState<UnreadSummary>({ unread_conversations: 0, unread_messages: 0 })
  const [filter, setFilter] = useState<FilterKey>('all')
  const [search, setSearch] = useState('')

  const [activeId, setActiveId] = useState<number | null>(null)
  const [detail, setDetail] = useState<ConversationDetail | null>(null)

  const [loadingList, setLoadingList] = useState(true)
  const [loadingThread, setLoadingThread] = useState(false)
  const [sending, setSending] = useState(false)
  const [error, setError] = useState<string | null>(null)

  const [replyText, setReplyText] = useState('')
  const [replyTo, setReplyTo] = useState<MessageItem | null>(null)
  const [attachment, setAttachment] = useState<File | null>(null)
  const [showMembers, setShowMembers] = useState(false)

  // People Directory / compose
  const [showDirectory, setShowDirectory] = useState(false)
  const [directory, setDirectory] = useState<DirectoryUser[]>([])
  const [directoryLoading, setDirectoryLoading] = useState(false)
  const [directorySearch, setDirectorySearch] = useState('')
  const [directoryRole, setDirectoryRole] = useState('')

  // New direct message
  const [directTarget, setDirectTarget] = useState<DirectoryUser | null>(null)
  const [directBody, setDirectBody] = useState('')

  // New group
  const [showGroupModal, setShowGroupModal] = useState(false)
  const [groupName, setGroupName] = useState('')
  const [groupDescription, setGroupDescription] = useState('')
  const [groupSelected, setGroupSelected] = useState<number[]>([])

  // Add members
  const [showAddMembers, setShowAddMembers] = useState(false)
  const [addSelected, setAddSelected] = useState<number[]>([])

  const threadEndRef = useRef<HTMLDivElement>(null)
  const fileInputRef = useRef<HTMLInputElement>(null)
  const draftTokenRef = useRef<string>(makeClientToken())
  const searchTimer = useRef<ReturnType<typeof setTimeout> | null>(null)

  // ── Data loading ──────────────────────────────────────────────
  const refreshList = useCallback(
    async (opts?: { silent?: boolean }) => {
      if (!opts?.silent) setLoadingList(true)
      try {
        const [list, summary] = await Promise.all([
          getConversations({ filter, search }),
          getUnreadMessageSummary(),
        ])
        setConversations(Array.isArray(list) ? list : [])
        setUnread(summary)
      } catch {
        if (!opts?.silent) {
          setConversations([])
          setError('Unable to load conversations. Please retry.')
        }
      } finally {
        if (!opts?.silent) setLoadingList(false)
      }
    },
    [filter, search],
  )

  const loadThread = useCallback(
    async (id: number, opts?: { silent?: boolean }) => {
      if (!opts?.silent) setLoadingThread(true)
      try {
        const fresh = await getConversation(id)
        setDetail(fresh)
        setError(null)
      } catch (err) {
        if (!opts?.silent) {
          setDetail(null)
          setError((err as Error).message || 'Unable to open this conversation.')
        }
      } finally {
        if (!opts?.silent) setLoadingThread(false)
      }
    },
    [],
  )

  const loadDirectory = useCallback(async (term: string, role: string) => {
    setDirectoryLoading(true)
    try {
      const rows = await getRecipients({ search: term || undefined, role: role || undefined })
      setDirectory(Array.isArray(rows) ? rows : [])
    } catch {
      setDirectory([])
    } finally {
      setDirectoryLoading(false)
    }
  }, [])

  useEffect(() => {
    refreshList()
  }, [refreshList])

  useEffect(() => {
    const timer = setInterval(() => {
      refreshList({ silent: true })
      if (activeId) {
        const lastId = detail?.messages.length ? detail.messages[detail.messages.length - 1].id : undefined
        getConversation(activeId, lastId)
          .then((fresh) => {
            setDetail((prev) => {
              if (!prev || prev.id !== fresh.id) return fresh
              const known = new Set(prev.messages.map((m) => m.id))
              const incoming = fresh.messages.filter((m) => !known.has(m.id))
              if (incoming.length === 0) return { ...prev, ...fresh, messages: prev.messages }
              return { ...prev, ...fresh, messages: [...prev.messages, ...incoming] }
            })
          })
          .catch(() => { /* keep the current thread on transient errors */ })
      }
    }, POLL_INTERVAL_MS)
    return () => clearInterval(timer)
  }, [activeId, detail, refreshList])

  useEffect(() => {
    threadEndRef.current?.scrollIntoView({ behavior: 'smooth' })
  }, [detail?.messages, detail?.id])

  // Debounced server-side conversation search.
  useEffect(() => {
    if (searchTimer.current) clearTimeout(searchTimer.current)
    searchTimer.current = setTimeout(() => refreshList(), 300)
    return () => {
      if (searchTimer.current) clearTimeout(searchTimer.current)
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [search])

  const openConversation = async (id: number) => {
    setActiveId(id)
    setReplyTo(null)
    setReplyText('')
    setAttachment(null)
    setShowMembers(false)
    await loadThread(id)
    refreshList({ silent: true })
  }

  // Deep link: /messages?conversation=<id> opens that thread (e.g. from the
  // notification bell's "open_conversation" action).
  useEffect(() => {
    const raw = searchParams.get('conversation')
    if (!raw) return
    const id = Number(raw)
    if (!Number.isFinite(id) || id <= 0) return
    if (id === activeId) return
    void openConversation(id)
    // Clear the param so polling / navigation does not re-trigger the open.
    const next = new URLSearchParams(searchParams)
    next.delete('conversation')
    setSearchParams(next, { replace: true })
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [searchParams])

  // ── Directory filtering ───────────────────────────────────────
  const visibleDirectory = useMemo(() => {
    if (directoryRole === 'dean') {
      return directory.filter((u) => (u.position ?? '').toLowerCase().includes('dean'))
    }
    return directory
  }, [directory, directoryRole])

  useEffect(() => {
    if (showDirectory || showGroupModal || showAddMembers) {
      loadDirectory(directorySearch, directoryRole === 'dean' ? '' : directoryRole)
    }
  }, [showDirectory, showGroupModal, showAddMembers, directorySearch, directoryRole, loadDirectory])

  // ── Sending ───────────────────────────────────────────────────
  const handleSend = async (e?: React.FormEvent) => {
    e?.preventDefault()
    if (sending) return
    const body = replyText.trim()
    if (!body && !attachment) return

    setSending(true)
    setError(null)
    try {
      if (activeId) {
        await sendMessage(
          {
            conversation_id: activeId,
            body: body || undefined,
            reply_to_message_id: replyTo?.id,
            client_token: draftTokenRef.current,
          },
          attachment,
        )
      }
      // Reset composer and reload the authoritative thread.
      setReplyText('')
      setReplyTo(null)
      setAttachment(null)
      draftTokenRef.current = makeClientToken()
      if (activeId) await loadThread(activeId, { silent: true })
      refreshList({ silent: true })
    } catch (err) {
      setError((err as Error).message || 'Message could not be sent.')
    } finally {
      setSending(false)
    }
  }

  const handleStartDirect = async (e: React.FormEvent) => {
    e.preventDefault()
    if (!directTarget || sending) return
    if (!directBody.trim()) {
      setError('Enter a message to send.')
      return
    }
    setSending(true)
    setError(null)
    try {
      const result = await sendMessage({
        recipient_id: directTarget.id,
        body: directBody.trim(),
        client_token: makeClientToken(),
      })
      setShowDirectory(false)
      setDirectTarget(null)
      setDirectBody('')
      await refreshList({ silent: true })
      await openConversation(result.conversation_id)
    } catch (err) {
      setError((err as Error).message || 'Message could not be sent.')
    } finally {
      setSending(false)
    }
  }

  const handleCreateGroup = async (e: React.FormEvent) => {
    e.preventDefault()
    if (!groupName.trim() || groupSelected.length === 0 || sending) {
      setError('Provide a group name and select at least one participant.')
      return
    }
    setSending(true)
    setError(null)
    try {
      const created = await createGroupConversation({
        name: groupName.trim(),
        description: groupDescription.trim() || undefined,
        participant_ids: groupSelected,
      })
      setShowGroupModal(false)
      setGroupName('')
      setGroupDescription('')
      setGroupSelected([])
      await refreshList({ silent: true })
      await openConversation(created.id)
    } catch (err) {
      setError((err as Error).message || 'Group could not be created.')
    } finally {
      setSending(false)
    }
  }

  const handleAddMembers = async (e: React.FormEvent) => {
    e.preventDefault()
    if (!detail || addSelected.length === 0 || sending) return
    setSending(true)
    setError(null)
    try {
      await addGroupParticipants(detail.id, addSelected)
      setShowAddMembers(false)
      setAddSelected([])
      await loadThread(detail.id, { silent: true })
      refreshList({ silent: true })
    } catch (err) {
      setError((err as Error).message || 'Participants could not be added.')
    } finally {
      setSending(false)
    }
  }

  const handleRemoveParticipant = async (userId: number) => {
    if (!detail) return
    setError(null)
    try {
      await removeGroupParticipant(detail.id, userId)
      await loadThread(detail.id, { silent: true })
    } catch (err) {
      setError((err as Error).message || 'Participant could not be removed.')
    }
  }

  const handleLeave = async () => {
    if (!detail) return
    setError(null)
    try {
      await leaveConversation(detail.id)
      setDetail(null)
      setActiveId(null)
      await refreshList({ silent: true })
    } catch (err) {
      setError((err as Error).message || 'Unable to leave this group.')
    }
  }

  const handleArchiveToggle = async (id: number) => {
    const isArchived = filter === 'archived'
    setError(null)
    try {
      await archiveConversation(id, !isArchived)
      if (activeId === id && !isArchived) {
        setDetail(null)
        setActiveId(null)
      }
      await refreshList()
    } catch (err) {
      setError((err as Error).message || 'Unable to update the conversation.')
    }
  }

  const handleDownload = async (msg: MessageItem) => {
    if (!msg.attachment) return
    try {
      await downloadFile(msg.attachment.url, msg.attachment.name)
    } catch (err) {
      setError((err as Error).message || 'Attachment could not be downloaded.')
    }
  }

  // ── Derived ───────────────────────────────────────────────────
  const isGroupAdmin = useMemo(() => {
    if (!detail?.is_group) return false
    if (detail.created_by === viewerId) return true
    return detail.participants.some((p) => p.user.id === viewerId && p.is_admin)
  }, [detail, viewerId])

  const directCounterpart = useCallback(
    (conv: ConversationSummary | ConversationDetail) =>
      conv.participants.find((p) => p.user.id !== viewerId)?.user,
    [viewerId],
  )

  const conversationTitle = useCallback(
    (conv: ConversationSummary | ConversationDetail): string => {
      if (conv.is_group) return conv.subject || 'Group conversation'
      return directCounterpart(conv)?.name ?? 'Conversation'
    },
    [directCounterpart],
  )

  const groupedMessages = useMemo(() => {
    const groups: Array<{ day: string; items: MessageItem[] }> = []
    ;(detail?.messages ?? []).forEach((msg) => {
      const key = dayKey(msg.created_at)
      const last = groups[groups.length - 1]
      if (last && last.day === key) last.items.push(msg)
      else groups.push({ day: key, items: [msg] })
    })
    return groups
  }, [detail?.messages])

  const unreadTotal = unread.unread_messages

  // ── Render ────────────────────────────────────────────────────
  return (
    <div className="min-h-screen bg-[#F4FBF7] p-3 sm:p-5 md:p-8">
      <div className="mx-auto max-w-7xl">
        {/* Header */}
        <div className="mb-4 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
          <div>
            <h1 className="flex items-center gap-2 text-xl font-extrabold tracking-tight text-[#1E3A8A] md:text-2xl">
              <MessageSquarePlus size={26} /> Internal Messaging
            </h1>
            <p className="mt-1 text-sm text-[#64748B]">
              Secure conversations between Admin, Dean, HR, Department Heads and Employees.
            </p>
          </div>
          <div className="flex flex-wrap items-center gap-2">
            <button
              type="button"
              onClick={() => {
                setShowGroupModal(true)
                setGroupName('')
                setGroupDescription('')
                setGroupSelected([])
                setDirectoryRole('')
                setDirectorySearch('')
                setError(null)
              }}
              className="inline-flex items-center justify-center gap-2 rounded-2xl border border-[#1E4ED8] bg-white px-5 py-2.5 text-sm font-bold text-[#1E4ED8] shadow-sm transition hover:bg-[#EAF6EF]"
            >
              <Users size={18} /> New Group
            </button>
            <button
              type="button"
              onClick={() => {
                setShowDirectory(true)
                setDirectTarget(null)
                setDirectBody('')
                setDirectoryRole('')
                setDirectorySearch('')
                setError(null)
              }}
              className="inline-flex items-center justify-center gap-2 rounded-2xl bg-[#1E4ED8] px-5 py-2.5 text-sm font-bold text-white shadow-sm transition hover:bg-[#1E3A8A]"
            >
              <Plus size={18} /> New Message
            </button>
          </div>
        </div>

        {error && (
          <div className="mb-3 flex items-start justify-between gap-3 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
            <span>{error}</span>
            <button type="button" onClick={() => setError(null)} aria-label="Dismiss error">
              <X size={16} />
            </button>
          </div>
        )}

        {/* Two panel layout */}
        <div className="grid min-h-[640px] grid-cols-1 overflow-hidden rounded-3xl border border-[#EAF6EF] bg-white shadow-sm md:grid-cols-12">
          {/* LEFT PANEL */}
          <aside
            className={`${activeId ? 'hidden md:flex' : 'flex'} flex-col border-b border-[#EAF6EF] md:col-span-4 md:border-b-0 md:border-r lg:col-span-4`}
          >
            <div className="border-b border-[#EAF6EF] p-3">
              <div className="relative">
                <Search size={16} className="absolute left-3 top-1/2 -translate-y-1/2 text-[#64748B]" />
                <input
                  type="text"
                  value={search}
                  onChange={(e) => setSearch(e.target.value)}
                  placeholder="Search conversations"
                  className="w-full rounded-xl border border-[#EAF6EF] bg-[#F4FBF7] py-2 pl-9 pr-3 text-sm text-[#111827] outline-none focus:ring-2 focus:ring-[#1E4ED8]"
                />
              </div>

              <div className="mt-3 flex flex-wrap gap-1.5">
                {FILTERS.map((f) => (
                  <button
                    key={f.key}
                    type="button"
                    onClick={() => setFilter(f.key)}
                    className={`rounded-full px-3 py-1 text-xs font-semibold transition ${
                      filter === f.key
                        ? 'bg-[#1E4ED8] text-white'
                        : 'bg-[#EAF6EF] text-[#1E3A8A] hover:bg-[#d9efe3]'
                    }`}
                  >
                    {f.label}
                    {f.key === 'unread' && unread.unread_conversations > 0 && (
                      <span className="ml-1 rounded-full bg-white/25 px-1.5 text-[10px]">
                        {unread.unread_conversations}
                      </span>
                    )}
                  </button>
                ))}
              </div>
            </div>

            <div className="flex-1 divide-y divide-[#F4FBF7] overflow-y-auto">
              {loadingList ? (
                <div className="p-8 text-center text-sm text-[#64748B]">Loading conversations…</div>
              ) : conversations.length === 0 ? (
                <div className="flex flex-col items-center gap-2 p-8 text-center">
                  <Archive size={28} className="text-[#64748B]" />
                  <p className="text-sm font-semibold text-[#111827]">
                    {filter === 'archived' ? 'No archived conversations' : 'No conversations yet'}
                  </p>
                  <p className="text-xs text-[#64748B]">
                    {filter === 'archived'
                      ? 'Conversations you archive will appear here.'
                      : 'Start a new message to begin a conversation.'}
                  </p>
                </div>
              ) : (
                conversations.map((conv) => {
                  const isActive = conv.id === activeId
                  const title = conversationTitle(conv)
                  const counterpart = conv.is_group ? null : directCounterpart(conv)
                  return (
                    <button
                      key={conv.id}
                      type="button"
                      onClick={() => openConversation(conv.id)}
                      className={`flex w-full items-start gap-3 p-3 text-left transition ${
                        isActive ? 'bg-[#EAF6EF]' : 'hover:bg-[#F4FBF7]'
                      }`}
                    >
                      <Avatar name={title} url={counterpart?.avatar_url} group={conv.is_group} />
                      <div className="min-w-0 flex-1">
                        <div className="flex items-center justify-between gap-2">
                          <h4 className="truncate text-sm font-bold text-[#111827]">{title}</h4>
                          <span className="flex-shrink-0 text-[10px] font-medium text-[#64748B]">
                            {timeAgo(conv.last_message_at)}
                          </span>
                        </div>
                        <p className="truncate text-xs text-[#64748B]">
                          {conv.latest_message?.body || 'No messages yet'}
                        </p>
                      </div>
                      {conv.unread_count > 0 && (
                        <span className="flex-shrink-0 rounded-full bg-[#1E4ED8] px-2 py-0.5 text-[10px] font-extrabold text-white">
                          {conv.unread_count}
                        </span>
                      )}
                    </button>
                  )
                })
              )}
            </div>

            {unreadTotal > 0 && (
              <div className="border-t border-[#EAF6EF] px-3 py-2 text-[11px] font-semibold text-[#64748B]">
                {unreadTotal} unread message{unreadTotal === 1 ? '' : 's'}
              </div>
            )}
          </aside>

          {/* RIGHT PANEL */}
          <section
            className={`${activeId ? 'flex' : 'hidden md:flex'} flex-col bg-[#F4FBF7] md:col-span-8 lg:col-span-8`}
          >
            {detail ? (
              <>
                {/* Thread header */}
                <header className="flex items-center justify-between gap-3 border-b border-[#EAF6EF] bg-white p-3">
                  <div className="flex min-w-0 items-center gap-3">
                    <button
                      type="button"
                      onClick={() => setActiveId(null)}
                      className="rounded-lg p-1 text-[#1E3A8A] hover:bg-[#EAF6EF] md:hidden"
                      aria-label="Back to conversations"
                    >
                      <ChevronLeft size={20} />
                    </button>
                    <Avatar
                      name={conversationTitle(detail)}
                      url={detail.is_group ? null : directCounterpart(detail)?.avatar_url}
                      group={detail.is_group}
                    />
                    <div className="min-w-0">
                      <h3 className="truncate text-sm font-extrabold text-[#111827]">
                        {conversationTitle(detail)}
                      </h3>
                      <p className="truncate text-xs text-[#64748B]">
                        {detail.is_group
                          ? `${detail.participants.length} members`
                          : directCounterpart(detail)?.role
                            ? ROLE_LABELS[directCounterpart(detail)?.role ?? ''] ??
                              directCounterpart(detail)?.role
                            : 'Direct message'}
                      </p>
                    </div>
                  </div>
                  <div className="flex items-center gap-1">
                    {detail.is_group && (
                      <button
                        type="button"
                        onClick={() => setShowMembers((v) => !v)}
                        className="rounded-lg p-2 text-[#1E3A8A] hover:bg-[#EAF6EF]"
                        aria-label="Group information"
                      >
                        <Info size={18} />
                      </button>
                    )}
                    {filter === 'archived' ? (
                      <button
                        type="button"
                        onClick={() => handleArchiveToggle(detail.id)}
                        className="rounded-lg p-2 text-[#1E3A8A] hover:bg-[#EAF6EF]"
                        aria-label="Restore conversation"
                      >
                        <ArchiveRestore size={18} />
                      </button>
                    ) : (
                      <button
                        type="button"
                        onClick={() => handleArchiveToggle(detail.id)}
                        className="rounded-lg p-2 text-[#1E3A8A] hover:bg-[#EAF6EF]"
                        aria-label="Archive conversation"
                      >
                        <Archive size={18} />
                      </button>
                    )}
                  </div>
                </header>

                {showMembers && detail.is_group && (
                  <div className="border-b border-[#EAF6EF] bg-white px-3 py-3">
                    <div className="mb-2 flex items-center justify-between">
                      <h4 className="text-xs font-bold uppercase tracking-wide text-[#64748B]">
                        Members ({detail.participants.length})
                      </h4>
                      <div className="flex items-center gap-1">
                        {isGroupAdmin && (
                          <button
                            type="button"
                            onClick={() => {
                              setShowAddMembers(true)
                              setAddSelected([])
                            }}
                            className="inline-flex items-center gap-1 rounded-lg bg-[#EAF6EF] px-2 py-1 text-[11px] font-bold text-[#1E3A8A]"
                          >
                            <UserPlus size={14} /> Add
                          </button>
                        )}
                        <button
                          type="button"
                          onClick={handleLeave}
                          className="inline-flex items-center gap-1 rounded-lg bg-red-50 px-2 py-1 text-[11px] font-bold text-red-600"
                        >
                          <LogOut size={14} /> Leave
                        </button>
                      </div>
                    </div>
                    <ul className="max-h-44 space-y-1 overflow-y-auto">
                      {detail.participants.map((p) => (
                        <li
                          key={p.user.id}
                          className="flex items-center justify-between gap-2 rounded-lg px-1 py-1"
                        >
                          <div className="flex min-w-0 items-center gap-2">
                            <Avatar name={p.user.name} url={p.user.avatar_url} size={28} />
                            <div className="min-w-0">
                              <p className="truncate text-xs font-semibold text-[#111827]">
                                {p.user.name}
                                {p.user.id === viewerId && ' (you)'}
                              </p>
                              <p className="truncate text-[10px] text-[#64748B]">
                                {p.is_admin ? 'Group admin' : ROLE_LABELS[p.user.role ?? ''] ?? 'Member'}
                              </p>
                            </div>
                          </div>
                          {isGroupAdmin && p.user.id !== viewerId && p.user.id !== detail.created_by && (
                            <button
                              type="button"
                              onClick={() => handleRemoveParticipant(p.user.id)}
                              className="rounded-md p-1 text-[#64748B] hover:bg-red-50 hover:text-red-600"
                              aria-label={`Remove ${p.user.name}`}
                            >
                              <X size={14} />
                            </button>
                          )}
                        </li>
                      ))}
                    </ul>
                  </div>
                )}

                {/* Messages */}
                <div className="flex-1 space-y-3 overflow-y-auto p-3 sm:p-5" style={{ maxHeight: '52vh' }}>
                  {loadingThread && detail.messages.length === 0 ? (
                    <div className="py-12 text-center text-sm text-[#64748B]">Loading messages…</div>
                  ) : detail.messages.length === 0 ? (
                    <div className="flex flex-col items-center gap-2 py-12 text-center">
                      <MessageSquarePlus size={28} className="text-[#64748B]" />
                      <p className="text-sm font-semibold text-[#111827]">No messages yet</p>
                      <p className="text-xs text-[#64748B]">Send the first message to start the conversation.</p>
                    </div>
                  ) : (
                    groupedMessages.map((group) => (
                      <div key={group.day} className="space-y-3">
                        <div className="flex items-center justify-center">
                          <span className="rounded-full bg-white px-3 py-1 text-[10px] font-bold uppercase tracking-wide text-[#64748B] shadow-sm">
                            {formatDayLabel(group.items[0].created_at)}
                          </span>
                        </div>
                        {group.items.map((msg) => {
                          const mine = msg.is_mine ?? msg.sender.id === viewerId
                          return (
                            <div
                              key={msg.id}
                              className={`flex flex-col ${mine ? 'items-end' : 'items-start'}`}
                            >
                              {!mine && detail.is_group && (
                                <span className="mb-0.5 px-1 text-[10px] font-semibold text-[#64748B]">
                                  {msg.sender.name}
                                </span>
                              )}
                              <div
                                className={`group relative max-w-[80%] rounded-2xl px-3.5 py-2.5 text-sm shadow-sm ${
                                  mine
                                    ? 'rounded-br-sm bg-[#1E4ED8] text-white'
                                    : 'rounded-bl-sm border border-[#EAF6EF] bg-white text-[#111827]'
                                }`}
                              >
                                {msg.reply_to && (
                                  <div
                                    className={`mb-1.5 rounded-lg border-l-2 px-2 py-1 text-[11px] ${
                                      mine
                                        ? 'border-white/60 bg-white/10 text-white/90'
                                        : 'border-[#1E4ED8] bg-[#F4FBF7] text-[#64748B]'
                                    }`}
                                  >
                                    <span className="font-semibold">
                                      {msg.reply_to.sender_name ?? 'Reply'}
                                    </span>
                                    <p className="truncate">{msg.reply_to.body}</p>
                                  </div>
                                )}

                                {msg.body && <p className="whitespace-pre-line leading-relaxed">{msg.body}</p>}

                                {msg.attachment && (
                                  <button
                                    type="button"
                                    onClick={() => handleDownload(msg)}
                                    className={`mt-1.5 flex items-center gap-2 rounded-lg px-2 py-1.5 text-left text-xs font-semibold ${
                                      mine
                                        ? 'bg-white/15 text-white hover:bg-white/25'
                                        : 'bg-[#F4FBF7] text-[#1E3A8A] hover:bg-[#EAF6EF]'
                                    }`}
                                  >
                                    <FileText size={14} />
                                    <span className="truncate">{msg.attachment.name}</span>
                                  </button>
                                )}

                                <div
                                  className={`mt-1 flex items-center justify-end gap-1 text-[10px] ${
                                    mine ? 'text-white/80' : 'text-[#64748B]'
                                  }`}
                                >
                                  <span>{formatTime(msg.created_at)}</span>
                                  {mine && (
                                    <span title={msg.is_read ? 'Read' : 'Sent'}>
                                      {msg.is_read ? <CheckCheck size={13} /> : <Check size={13} />}
                                    </span>
                                  )}
                                </div>

                                <button
                                  type="button"
                                  onClick={() => setReplyTo(msg)}
                                  className="absolute -top-3 right-2 hidden rounded-full border border-[#EAF6EF] bg-white p-1 text-[#1E3A8A] shadow-sm group-hover:block"
                                  aria-label="Reply"
                                >
                                  <Reply size={13} />
                                </button>
                              </div>
                            </div>
                          )
                        })}
                      </div>
                    ))
                  )}
                  <div ref={threadEndRef} />
                </div>

                {/* Composer */}
                <form
                  onSubmit={handleSend}
                  className="border-t border-[#EAF6EF] bg-white p-3"
                >
                  {replyTo && (
                    <div className="mb-2 flex items-start justify-between gap-2 rounded-lg border-l-2 border-[#1E4ED8] bg-[#F4FBF7] px-3 py-2 text-xs">
                      <div className="min-w-0">
                        <p className="font-semibold text-[#1E3A8A]">Replying to {replyTo.sender.name}</p>
                        <p className="truncate text-[#64748B]">{replyTo.body || 'Attachment'}</p>
                      </div>
                      <button
                        type="button"
                        onClick={() => setReplyTo(null)}
                        className="text-[#64748B] hover:text-[#111827]"
                        aria-label="Cancel reply"
                      >
                        <X size={14} />
                      </button>
                    </div>
                  )}

                  {attachment && (
                    <div className="mb-2 flex items-center justify-between gap-2 rounded-lg bg-[#EAF6EF] px-3 py-2 text-xs text-[#1E3A8A]">
                      <span className="flex min-w-0 items-center gap-2">
                        <Paperclip size={14} />
                        <span className="truncate">
                          {attachment.name} · {formatBytes(attachment.size)}
                        </span>
                      </span>
                      <button
                        type="button"
                        onClick={() => {
                          setAttachment(null)
                          if (fileInputRef.current) fileInputRef.current.value = ''
                        }}
                        aria-label="Remove attachment"
                      >
                        <X size={14} />
                      </button>
                    </div>
                  )}

                  <div className="flex items-end gap-2">
                    <input
                      ref={fileInputRef}
                      type="file"
                      accept={ATTACHMENT_ACCEPT}
                      className="hidden"
                      onChange={(e) => {
                        const file = e.target.files?.[0] ?? null
                        if (file && file.size > ATTACHMENT_MAX_BYTES) {
                          setError('Attachments are limited to 10 MB.')
                          setAttachment(null)
                          e.target.value = ''
                          return
                        }
                        setAttachment(file)
                      }}
                    />
                    <button
                      type="button"
                      onClick={() => fileInputRef.current?.click()}
                      className="rounded-xl border border-[#EAF6EF] p-2.5 text-[#1E3A8A] hover:bg-[#EAF6EF]"
                      aria-label="Attach a file"
                    >
                      <Paperclip size={18} />
                    </button>
                    <textarea
                      rows={1}
                      value={replyText}
                      onChange={(e) => setReplyText(e.target.value)}
                      onKeyDown={(e) => {
                        if (e.key === 'Enter' && !e.shiftKey) {
                          e.preventDefault()
                          handleSend()
                        }
                      }}
                      placeholder="Type your message…"
                      className="max-h-32 min-h-[44px] flex-1 resize-y rounded-xl border border-[#EAF6EF] bg-[#F4FBF7] px-3 py-2.5 text-sm text-[#111827] outline-none focus:ring-2 focus:ring-[#1E4ED8]"
                    />
                    <button
                      type="submit"
                      disabled={sending || (!replyText.trim() && !attachment)}
                      className="inline-flex items-center gap-2 rounded-xl bg-[#1E4ED8] px-4 py-2.5 text-sm font-bold text-white shadow-sm transition hover:bg-[#1E3A8A] disabled:opacity-50"
                    >
                      <Send size={16} /> {sending ? 'Sending…' : 'Send'}
                    </button>
                  </div>
                </form>
              </>
            ) : (
              <div className="flex flex-1 flex-col items-center justify-center p-12 text-center">
                <span className="mb-3 flex h-14 w-14 items-center justify-center rounded-full bg-[#EAF6EF] text-[#1E3A8A]">
                  <MessageSquarePlus size={26} />
                </span>
                <p className="text-base font-bold text-[#111827]">No conversation selected</p>
                <p className="mt-1 max-w-sm text-xs text-[#64748B]">
                  Select a conversation from the list, or click “New Message” to start one with an
                  authorized colleague.
                </p>
              </div>
            )}
          </section>
        </div>
      </div>

      {/* ── People Directory / New Message ── */}
      {showDirectory && (
        <ModalShell onClose={() => setShowDirectory(false)} title="New Message" icon={<MessageSquarePlus size={18} />}>
          {directTarget ? (
            <form onSubmit={handleStartDirect} className="space-y-4">
              <div className="flex items-center gap-3 rounded-xl bg-[#F4FBF7] p-3">
                <Avatar name={directTarget.name} url={directTarget.avatar_url} />
                <div className="min-w-0">
                  <p className="truncate text-sm font-bold text-[#111827]">{directTarget.name}</p>
                  <p className="truncate text-xs text-[#64748B]">
                    {directTarget.role_label || ROLE_LABELS[directTarget.role] || directTarget.role}
                    {directTarget.department ? ` · ${directTarget.department}` : ''}
                  </p>
                </div>
                <button
                  type="button"
                  onClick={() => setDirectTarget(null)}
                  className="ml-auto text-xs font-semibold text-[#1E4ED8]"
                >
                  Change
                </button>
              </div>
              <textarea
                rows={4}
                value={directBody}
                onChange={(e) => setDirectBody(e.target.value)}
                placeholder="Write your message…"
                className="w-full rounded-xl border border-[#EAF6EF] bg-[#F4FBF7] px-3 py-2.5 text-sm outline-none focus:ring-2 focus:ring-[#1E4ED8]"
              />
              <div className="flex justify-end gap-2">
                <SecondaryButton onClick={() => setDirectTarget(null)}>Back</SecondaryButton>
                <PrimaryButton type="submit" disabled={sending}>
                  {sending ? 'Sending…' : 'Send Message'}
                </PrimaryButton>
              </div>
            </form>
          ) : (
            <div className="space-y-3">
              <DirectorySearch
                value={directorySearch}
                onChange={setDirectorySearch}
                roleValue={directoryRole}
                onRoleChange={setDirectoryRole}
              />
              <DirectoryList
                users={visibleDirectory}
                loading={directoryLoading}
                actionLabel="Message"
                onSelect={(u) => {
                  setDirectTarget(u)
                  setDirectBody('')
                }}
              />
            </div>
          )}
        </ModalShell>
      )}

      {/* ── Create Group ── */}
      {showGroupModal && (
        <ModalShell onClose={() => setShowGroupModal(false)} title="Create Group Chat" icon={<Users size={18} />}>
          <form onSubmit={handleCreateGroup} className="space-y-4">
            <Input label="Group name" required value={groupName} onChange={setGroupName} placeholder="e.g. Curriculum Committee" />
            <Input
              label="Description (optional)"
              value={groupDescription}
              onChange={setGroupDescription}
              placeholder="What is this group for?"
            />
            <div>
              <label className="mb-1 block text-xs font-bold text-[#111827]">
                Participants <span className="text-red-500">*</span>
              </label>
              <DirectorySearch
                value={directorySearch}
                onChange={setDirectorySearch}
                roleValue={directoryRole}
                onRoleChange={setDirectoryRole}
              />
              <div className="mt-2">
                <DirectoryList
                  users={visibleDirectory}
                  loading={directoryLoading}
                  multi
                  selectedIds={groupSelected}
                  onToggle={(id) =>
                    setGroupSelected((prev) =>
                      prev.includes(id) ? prev.filter((x) => x !== id) : [...prev, id],
                    )
                  }
                />
              </div>
              <p className="mt-1 text-[11px] text-[#64748B]">{groupSelected.length} selected</p>
            </div>
            <div className="flex justify-end gap-2">
              <SecondaryButton onClick={() => setShowGroupModal(false)}>Cancel</SecondaryButton>
              <PrimaryButton type="submit" disabled={sending}>
                {sending ? 'Creating…' : 'Create Group'}
              </PrimaryButton>
            </div>
          </form>
        </ModalShell>
      )}

      {/* ── Add Members ── */}
      {showAddMembers && detail && (
        <ModalShell onClose={() => setShowAddMembers(false)} title="Add Participants" icon={<UserPlus size={18} />}>
          <form onSubmit={handleAddMembers} className="space-y-3">
            <DirectorySearch
              value={directorySearch}
              onChange={setDirectorySearch}
              roleValue={directoryRole}
              onRoleChange={setDirectoryRole}
            />
            <DirectoryList
              users={visibleDirectory.filter(
                (u) => !detail.participants.some((p) => p.user.id === u.id),
              )}
              loading={directoryLoading}
              multi
              selectedIds={addSelected}
              onToggle={(id) =>
                setAddSelected((prev) => (prev.includes(id) ? prev.filter((x) => x !== id) : [...prev, id]))
              }
            />
            <p className="text-[11px] text-[#64748B]">{addSelected.length} selected</p>
            <div className="flex justify-end gap-2">
              <SecondaryButton onClick={() => setShowAddMembers(false)}>Cancel</SecondaryButton>
              <PrimaryButton type="submit" disabled={sending || addSelected.length === 0}>
                {sending ? 'Adding…' : 'Add Participants'}
              </PrimaryButton>
            </div>
          </form>
        </ModalShell>
      )}
    </div>
  )
}

// ─── Shared sub-components ──────────────────────────────────────
function ModalShell({
  title,
  icon,
  children,
  onClose,
}: {
  title: string
  icon: React.ReactNode
  children: React.ReactNode
  onClose: () => void
}) {
  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-[#111827]/50 p-3 backdrop-blur-sm">
      <div className="w-full max-w-lg rounded-3xl border border-[#EAF6EF] bg-white shadow-2xl">
        <div className="flex items-center justify-between border-b border-[#EAF6EF] p-4">
          <h3 className="flex items-center gap-2 text-base font-extrabold text-[#1E3A8A]">
            {icon} {title}
          </h3>
          <button type="button" onClick={onClose} className="text-[#64748B] hover:text-[#111827]" aria-label="Close">
            <X size={18} />
          </button>
        </div>
        <div className="max-h-[70vh] overflow-y-auto p-4">{children}</div>
      </div>
    </div>
  )
}

function PrimaryButton({
  children,
  disabled,
  type = 'button',
  onClick,
}: {
  children: React.ReactNode
  disabled?: boolean
  type?: 'button' | 'submit'
  onClick?: () => void
}) {
  return (
    <button
      type={type}
      disabled={disabled}
      onClick={onClick}
      className="rounded-xl bg-[#1E4ED8] px-5 py-2 text-sm font-bold text-white shadow-sm transition hover:bg-[#1E3A8A] disabled:opacity-50"
    >
      {children}
    </button>
  )
}

function SecondaryButton({ children, onClick }: { children: React.ReactNode; onClick: () => void }) {
  return (
    <button
      type="button"
      onClick={onClick}
      className="rounded-xl px-4 py-2 text-sm font-bold text-[#64748B] transition hover:bg-[#F4FBF7]"
    >
      {children}
    </button>
  )
}

function Input({
  label,
  value,
  onChange,
  placeholder,
  required,
}: {
  label: string
  value: string
  onChange: (v: string) => void
  placeholder?: string
  required?: boolean
}) {
  return (
    <div>
      <label className="mb-1 block text-xs font-bold text-[#111827]">
        {label} {required && <span className="text-red-500">*</span>}
      </label>
      <input
        type="text"
        value={value}
        onChange={(e) => onChange(e.target.value)}
        placeholder={placeholder}
        className="w-full rounded-xl border border-[#EAF6EF] bg-[#F4FBF7] px-3 py-2.5 text-sm text-[#111827] outline-none focus:ring-2 focus:ring-[#1E4ED8]"
      />
    </div>
  )
}

function DirectorySearch({
  value,
  onChange,
  roleValue,
  onRoleChange,
}: {
  value: string
  onChange: (v: string) => void
  roleValue: string
  onRoleChange: (v: string) => void
}) {
  return (
    <div className="space-y-2">
      <div className="relative">
        <Search size={16} className="absolute left-3 top-1/2 -translate-y-1/2 text-[#64748B]" />
        <input
          type="text"
          value={value}
          onChange={(e) => onChange(e.target.value)}
          placeholder="Search by name, department or position"
          className="w-full rounded-xl border border-[#EAF6EF] bg-[#F4FBF7] py-2 pl-9 pr-3 text-sm text-[#111827] outline-none focus:ring-2 focus:ring-[#1E4ED8]"
        />
      </div>
      <div className="flex flex-wrap gap-1.5">
        {ROLE_FILTERS.map((r) => (
          <button
            key={r.key}
            type="button"
            onClick={() => onRoleChange(r.key)}
            className={`rounded-full px-2.5 py-1 text-[11px] font-semibold transition ${
              roleValue === r.key
                ? 'bg-[#1E4ED8] text-white'
                : 'bg-[#EAF6EF] text-[#1E3A8A] hover:bg-[#d9efe3]'
            }`}
          >
            {r.label}
          </button>
        ))}
      </div>
    </div>
  )
}

function DirectoryList({
  users,
  loading,
  multi = false,
  selectedIds = [],
  onSelect,
  onToggle,
  actionLabel,
}: {
  users: DirectoryUser[]
  loading: boolean
  multi?: boolean
  selectedIds?: number[]
  onSelect?: (u: DirectoryUser) => void
  onToggle?: (id: number) => void
  actionLabel?: string
}) {
  if (loading) {
    return <div className="py-8 text-center text-sm text-[#64748B]">Loading directory…</div>
  }
  if (users.length === 0) {
    return (
      <div className="flex flex-col items-center gap-1 py-8 text-center">
        <Users size={24} className="text-[#64748B]" />
        <p className="text-sm font-semibold text-[#111827]">No authorized recipients found</p>
        <p className="text-xs text-[#64748B]">Try a different name, role or department.</p>
      </div>
    )
  }
  return (
    <ul className="max-h-64 divide-y divide-[#F4FBF7] overflow-y-auto rounded-xl border border-[#EAF6EF]">
      {users.map((u) => {
        const selected = selectedIds.includes(u.id)
        return (
          <li key={u.id}>
            <button
              type="button"
              onClick={() => (multi ? onToggle?.(u.id) : onSelect?.(u))}
              className={`flex w-full items-center gap-3 p-2.5 text-left transition hover:bg-[#F4FBF7] ${
                selected ? 'bg-[#EAF6EF]' : ''
              }`}
            >
              <Avatar name={u.name} url={u.avatar_url} size={36} />
              <div className="min-w-0 flex-1">
                <p className="truncate text-sm font-bold text-[#111827]">{u.name}</p>
                <p className="truncate text-xs text-[#64748B]">
                  {u.role_label || ROLE_LABELS[u.role] || u.role}
                  {u.department ? ` · ${u.department}` : ''}
                  {u.position ? ` · ${u.position}` : ''}
                </p>
              </div>
              <span className="flex-shrink-0 rounded-full bg-[#EAF6EF] px-2 py-0.5 text-[10px] font-bold text-[#1E3A8A]">
                {u.status}
              </span>
              {multi ? (
                <span
                  className={`flex h-5 w-5 flex-shrink-0 items-center justify-center rounded-full border ${
                    selected ? 'border-[#1E4ED8] bg-[#1E4ED8] text-white' : 'border-[#64748B]'
                  }`}
                >
                  {selected && <Check size={12} />}
                </span>
              ) : (
                actionLabel && (
                  <span className="flex-shrink-0 text-xs font-bold text-[#1E4ED8]">{actionLabel}</span>
                )
              )}
            </button>
          </li>
        )
      })}
    </ul>
  )
}
