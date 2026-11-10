import { render, screen, waitFor, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { MemoryRouter } from 'react-router-dom'

import MessagesPage from './MessagesPage'
import { AuthContext, type AuthContextValue } from '../../auth/useAuth'
import type { UserProfile } from '../../types/auth'

/**
 * The page is a pure function of the `/api/v1/messages/*` payloads. These tests
 * stub `fetch` (matching the other page tests) and pin the behaviour the task
 * spec depends on:
 *
 *  - the two-panel layout renders the empty states from §11 (never sample data);
 *  - conversations, unread counters and filters come from the API;
 *  - a thread renders date separators, sent/received bubbles and read ticks;
 *  - sending is idempotent (client_token) and cannot double-submit;
 *  - the People Directory is the only route to a new private chat;
 *  - group creation posts the chosen participants.
 */

const VIEWER_ID = 7

function json(payload: unknown, status = 200) {
  return {
    ok: status >= 200 && status < 300,
    status,
    json: async () => payload,
  }
}

const ok = (data: unknown) => Promise.resolve(json({ success: true, message: 'ok', data }))

const dean = {
  id: 3,
  name: 'Dr. Dean Cruz',
  email: 'dean@jpc.local',
  role: 'employee',
  role_label: 'Employee',
  department: 'College of Education',
  position: 'Dean',
  status: 'active',
  avatar_url: null,
  initials: 'DD',
}

const hrUser = {
  id: 4,
  name: 'Bea HR Reyes',
  email: 'hr@jpc.local',
  role: 'hr',
  role_label: 'HR',
  department: 'Human Resources',
  position: 'HR Officer',
  status: 'active',
  avatar_url: null,
  initials: 'BR',
}

const directConversation = {
  id: 11,
  subject: null,
  description: null,
  icon: null,
  type: 'direct',
  is_group: false,
  created_by: VIEWER_ID,
  participants: [
    { user: { id: VIEWER_ID, name: 'Me Employee', email: 'me@jpc.local', role: 'employee' }, role: 'member', is_admin: false },
    { user: dean, role: 'member', is_admin: false },
  ],
  latest_message: { id: 100, body: 'Please send the report', sender: { id: 3, name: 'Dr. Dean Cruz' }, created_at: '2026-11-06T09:00:00Z' },
  unread_count: 2,
  last_message_at: '2026-11-06T09:00:00Z',
  viewer_id: VIEWER_ID,
}

const groupConversation = {
  id: 12,
  subject: 'Curriculum Committee',
  description: 'Term planning',
  icon: null,
  type: 'group',
  is_group: true,
  created_by: VIEWER_ID,
  participants: [
    { user: { id: VIEWER_ID, name: 'Me Employee', email: 'me@jpc.local', role: 'employee' }, role: 'admin', is_admin: true },
    { user: dean, role: 'member', is_admin: false },
  ],
  latest_message: { id: 101, body: 'Agenda attached', sender: { id: 3, name: 'Dr. Dean Cruz' }, created_at: '2026-11-05T09:00:00Z' },
  unread_count: 0,
  last_message_at: '2026-11-05T09:00:00Z',
  viewer_id: VIEWER_ID,
}

const todayIso = new Date().toISOString()

const directDetail = {
  id: 11,
  subject: null,
  description: null,
  icon: null,
  type: 'direct',
  is_group: false,
  created_by: VIEWER_ID,
  participants: directConversation.participants,
  messages: [
    {
      id: 200,
      sender: { id: 3, name: 'Dr. Dean Cruz' },
      body: 'Please review the guidelines before Friday',
      attachment: { name: 'guidelines.pdf', type: 'application/pdf', url: '/api/v1/messages/200/attachment' },
      reply_to: null,
      is_mine: false,
      is_read: false,
      created_at: todayIso,
    },
    {
      id: 201,
      sender: { id: VIEWER_ID, name: 'Me Employee' },
      body: 'Sure, sending it now',
      attachment: null,
      reply_to: null,
      is_mine: true,
      is_read: true,
      created_at: todayIso,
    },
  ],
  read_receipts: [],
  last_message_at: todayIso,
}

interface StubState {
  conversations?: unknown[]
  detail?: Record<number, unknown>
  recipients?: unknown[]
  postMessage?: (init?: RequestInit) => Promise<unknown>
}

function stubFetch(state: StubState = {}) {
  const fetchMock = vi.fn((input: RequestInfo | URL, init?: RequestInit) => {
    const url = String(input)
    const method = init?.method ?? 'GET'

    if (url.includes('/messages/recipients')) return ok(state.recipients ?? [dean, hrUser])
    if (url.includes('/messages/unread-summary')) return ok({ unread_conversations: 1, unread_messages: 2 })

    const detailMatch = url.match(/\/messages\/conversations\/(\d+)/)
    if (method === 'GET' && detailMatch) {
      const id = Number(detailMatch[1])
      return ok(state.detail?.[id] ?? directDetail)
    }

    if (method === 'GET' && url.includes('/messages/conversations')) {
      if (url.includes('filter=archived')) return ok([])
      return ok(state.conversations ?? [directConversation, groupConversation])
    }

    if (method === 'POST' && url.endsWith('/messages/groups')) {
      return ok({ ...groupConversation, id: 20 })
    }

    if (method === 'POST' && url.endsWith('/messages')) {
      return state.postMessage ? state.postMessage(init) : ok({ message_id: 300, conversation_id: 11 })
    }

    return ok(null)
  })

  vi.stubGlobal('fetch', fetchMock)
  return fetchMock
}

function authValue(): AuthContextValue {
  const user: UserProfile = {
    id: VIEWER_ID,
    name: 'Me Employee',
    email: 'me@jpc.local',
    status: 'active',
    role: 'employee',
    permissions: ['notifications.view'],
    last_login_at: null,
  }

  return { user, loading: false, error: null, login: vi.fn(), logout: vi.fn(), clearError: vi.fn() }
}

function renderPage() {
  return render(
    <MemoryRouter>
      <AuthContext.Provider value={authValue()}>
        <MessagesPage />
      </AuthContext.Provider>
    </MemoryRouter>,
  )
}

describe('MessagesPage (internal messaging)', () => {
  afterEach(() => {
    vi.unstubAllGlobals()
  })

  it('renders the two-panel empty states without inventing any conversations', async () => {
    stubFetch({ conversations: [] })
    renderPage()

    expect(await screen.findByRole('heading', { name: /Internal Messaging/ })).toBeInTheDocument()
    expect(await screen.findByText('No conversations yet')).toBeInTheDocument()
    expect(screen.getByText('No conversation selected')).toBeInTheDocument()
    expect(
      screen.getByText(/Select a conversation from the list, or click “New Message”/),
    ).toBeInTheDocument()
  })

  it('lists private and group conversations with unread counters and filter switching', async () => {
    const user = userEvent.setup()
    const fetchMock = stubFetch()
    renderPage()

    expect(await screen.findByText('Dr. Dean Cruz')).toBeInTheDocument()
    expect(screen.getByText('Curriculum Committee')).toBeInTheDocument()
    // private conversation shows its unread pill
    expect(within(screen.getByRole('button', { name: /Dr\. Dean Cruz/ })).getByText('2')).toBeInTheDocument()

    await user.click(screen.getByRole('button', { name: /^Archived/ }))

    await waitFor(() => {
      expect(
        fetchMock.mock.calls.some(([input]) => String(input).includes('filter=archived')),
      ).toBe(true)
    })
    expect(await screen.findByText('No archived conversations')).toBeInTheDocument()
  })

  it('opens a thread with date separators, received/sent bubbles, attachment and read ticks', async () => {
    const user = userEvent.setup()
    stubFetch({ detail: { 11: directDetail } })
    renderPage()

    await user.click(await screen.findByRole('button', { name: /Dr\. Dean Cruz/ }))

    expect(await screen.findByText('Please review the guidelines before Friday')).toBeInTheDocument()
    expect(screen.getByText('Sure, sending it now')).toBeInTheDocument()
    // date separator
    expect(await screen.findByText('Today')).toBeInTheDocument()
    // attachment chip
    expect(screen.getByText('guidelines.pdf')).toBeInTheDocument()
    // the viewer's own message carries a "Read" delivery tick
    expect(screen.getByTitle('Read')).toBeInTheDocument()
  })

  it('sends a message with a conversation id and an idempotency client token', async () => {
    const user = userEvent.setup()
    const fetchMock = stubFetch({ detail: { 11: directDetail } })
    renderPage()

    await user.click(await screen.findByRole('button', { name: /Dr\. Dean Cruz/ }))
    await screen.findByText('Please review the guidelines before Friday')

    await user.type(screen.getByPlaceholderText('Type your message…'), 'On my way')
    await user.click(screen.getByRole('button', { name: 'Send' }))

    await waitFor(() => {
      const post = fetchMock.mock.calls.find(
        ([input, init]) => String(input).endsWith('/messages') && (init as RequestInit | undefined)?.method === 'POST',
      )
      expect(post).toBeTruthy()
      const body = JSON.parse((post![1] as RequestInit).body as string)
      expect(body.conversation_id).toBe(11)
      expect(body.body).toBe('On my way')
      expect(typeof body.client_token).toBe('string')
      expect(body.client_token.length).toBeGreaterThan(0)
    })

    // exactly one POST — no duplicate submission
    const posts = fetchMock.mock.calls.filter(
      ([input, init]) => String(input).endsWith('/messages') && (init as RequestInit | undefined)?.method === 'POST',
    )
    expect(posts).toHaveLength(1)
  })

  it('blocks a second submission while the first is in flight', async () => {
    const user = userEvent.setup()
    let release: (value: unknown) => void = () => {}
    const pending = new Promise((resolve) => {
      release = resolve
    })
    const fetchMock = stubFetch({
      detail: { 11: directDetail },
      postMessage: () => pending as Promise<unknown>,
    })
    renderPage()

    await user.click(await screen.findByRole('button', { name: /Dr\. Dean Cruz/ }))
    await screen.findByText('Please review the guidelines before Friday')

    await user.type(screen.getByPlaceholderText('Type your message…'), 'Ping')
    await user.click(screen.getByRole('button', { name: 'Send' }))

    // while the request is unsettled the composer is disabled and shows progress
    const sendingButton = await screen.findByRole('button', { name: /Sending/ })
    expect(sendingButton).toBeDisabled()
    await user.click(sendingButton)

    const posts = fetchMock.mock.calls.filter(
      ([input, init]) => String(input).endsWith('/messages') && (init as RequestInit | undefined)?.method === 'POST',
    )
    expect(posts).toHaveLength(1)

    release(json({ success: true, message: 'ok', data: { message_id: 301, conversation_id: 11 } }))
    // the in-flight state ends and the composer returns to idle
    await waitFor(() => expect(screen.queryByRole('button', { name: /Sending/ })).not.toBeInTheDocument())
    expect(screen.getByRole('button', { name: 'Send' })).toBeInTheDocument()
  })

  it('opens the People Directory and starts a private chat with an authorized recipient', async () => {
    const user = userEvent.setup()
    const fetchMock = stubFetch({ conversations: [], detail: { 11: directDetail } })
    renderPage()

    await screen.findByText('No conversations yet')
    await user.click(screen.getByRole('button', { name: /New Message/ }))

    // directory lists only the API-provided authorized users, with role/dept/status
    const deanRow = await screen.findByRole('button', { name: /Dr\. Dean Cruz/ })
    expect(within(deanRow).getByText(/College of Education/)).toBeInTheDocument()
    expect(within(deanRow).getByText('active')).toBeInTheDocument()
    expect(screen.getByRole('button', { name: /Bea HR Reyes/ })).toBeInTheDocument()

    await user.click(deanRow)
    await user.type(screen.getByPlaceholderText('Write your message…'), 'Good morning')
    await user.click(screen.getByRole('button', { name: /Send Message/ }))

    await waitFor(() => {
      const post = fetchMock.mock.calls.find(
        ([input, init]) => String(input).endsWith('/messages') && (init as RequestInit | undefined)?.method === 'POST',
      )
      expect(post).toBeTruthy()
      const body = JSON.parse((post![1] as RequestInit).body as string)
      expect(body.recipient_id).toBe(3)
      expect(body.body).toBe('Good morning')
    })
  })

  it('creates a group chat with the selected participants', async () => {
    const user = userEvent.setup()
    const fetchMock = stubFetch({ conversations: [] })
    renderPage()

    await screen.findByText('No conversations yet')
    await user.click(screen.getByRole('button', { name: /New Group/ }))

    expect(await screen.findByRole('heading', { name: /Create Group Chat/ })).toBeInTheDocument()

    await user.type(screen.getByPlaceholderText('e.g. Curriculum Committee'), 'Budget Panel')
    await user.type(screen.getByPlaceholderText('What is this group for?'), 'Q4 review')

    const deanRow = await screen.findByRole('button', { name: /Dr\. Dean Cruz/ })
    await user.click(deanRow)

    await user.click(screen.getByRole('button', { name: 'Create Group' }))

    await waitFor(() => {
      const post = fetchMock.mock.calls.find(([input]) => String(input).endsWith('/messages/groups'))
      expect(post).toBeTruthy()
      const body = JSON.parse((post![1] as RequestInit).body as string)
      expect(body.name).toBe('Budget Panel')
      expect(body.description).toBe('Q4 review')
      expect(body.participant_ids).toEqual([3])
    })
  })

  it('shows an empty state when the directory has no authorized recipients', async () => {
    const user = userEvent.setup()
    stubFetch({ conversations: [], recipients: [] })
    renderPage()

    await screen.findByText('No conversations yet')
    await user.click(screen.getByRole('button', { name: /New Message/ }))

    expect(await screen.findByText('No authorized recipients found')).toBeInTheDocument()
  })
})
