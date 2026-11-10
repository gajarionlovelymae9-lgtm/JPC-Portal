import { render, screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { afterEach, describe, expect, it, vi } from 'vitest'
import AdminAiAssistantPage from './AdminAiAssistantPage'

function json(payload: unknown, status = 200) {
  return {
    ok: status >= 200 && status < 300,
    status,
    json: async () => payload,
  }
}

const USER_MESSAGE = {
  id: 1,
  conversation_id: 1,
  role: 'user',
  content: 'Summarize this month.',
  created_at: '2026-11-01T00:00:00Z',
}

const ASSISTANT_MESSAGE = {
  id: 2,
  conversation_id: 1,
  role: 'assistant',
  content: 'Here is the answer from authorized records.',
  created_at: '2026-11-01T00:00:05Z',
}

function stubApi(options: { conversations?: Array<Record<string, unknown>> } = {}) {
  const fetchMock = vi.fn(async (input: RequestInfo | URL, init?: RequestInit) => {
    const url = String(input)
    const method = init?.method ?? 'GET'

    if (url.includes('/admin/ai-assistant/usage')) {
      return json({
        success: true,
        message: 'ok',
        data: { allowed: true, role: 'admin', limit: 50, used: 1, remaining: 49, resets_at: '2026-11-02T00:00:00Z' },
      })
    }

    if (url.includes('/admin/ai-assistant/conversations/1/messages')) {
      return json({ success: true, message: 'ok', data: [USER_MESSAGE, ASSISTANT_MESSAGE] })
    }

    if (url.includes('/admin/ai-assistant/conversations') && method === 'GET') {
      return json({
        success: true,
        message: 'ok',
        data: options.conversations ?? [],
        meta: { current_page: 1, last_page: 1, per_page: 50, total: (options.conversations ?? []).length },
      })
    }

    if (url.includes('/admin/ai-assistant/chat') && method === 'POST') {
      return json({
        success: true,
        message: 'Message processed successfully',
        data: {
          conversation_id: 1,
          conversation: { id: 1, title: 'Summarize this month.', created_at: '2026-11-01T00:00:00Z' },
          message: ASSISTANT_MESSAGE,
          user_message: USER_MESSAGE,
          ok: true,
        },
      })
    }

    if (url.includes('/ai/language-preference')) {
      return json({ success: true, message: 'ok', data: { ai_language_preference: 'auto' } })
    }

    return json({ success: true, message: 'ok', data: [] })
  })

  vi.stubGlobal('fetch', fetchMock)
  return fetchMock
}

afterEach(() => {
  vi.unstubAllGlobals()
})

describe('AdminAiAssistantPage safety notice', () => {
  it('renders the compact JPC AIDE AI Safety Notice with the new wording', async () => {
    stubApi()
    render(<AdminAiAssistantPage />)

    const notice = await screen.findByRole('note', { name: 'JPC AIDE AI Safety Notice' })

    expect(notice).toHaveTextContent('JPC AIDE – AI Safety Notice')
    expect(notice).toHaveTextContent(
      'AI-generated responses are based on available authorized JPC Portal records. Please verify important information before taking administrative action. Final decisions remain with authorized personnel.',
    )
    expect(notice).toHaveTextContent('Human review is required for important administrative decisions.')

    // The old warning copy must no longer be shown.
    expect(screen.queryByText('Human verification required')).not.toBeInTheDocument()
    expect(
      screen.queryByText(
        'This assistant uses authorized records and a configured AI provider. It does not make employment decisions, predict future performance, or replace Admin review.',
      ),
    ).not.toBeInTheDocument()

    // Compact, neutral design: light background and reduced padding rather
    // than the previous full amber alert box.
    expect(notice.className).toContain('bg-slate-50')
    expect(notice.className).not.toContain('bg-amber-50')
    expect(notice.className).toContain('px-3.5')
    expect(notice.className).not.toContain('p-4')
  })

  it('still sends a message and renders the assistant response', async () => {
    const fetchMock = stubApi()
    const user = userEvent.setup()
    render(<AdminAiAssistantPage />)

    const input = await screen.findByLabelText('Message')
    await user.type(input, 'Summarize this month.')
    await user.click(screen.getByRole('button', { name: 'Send' }))

    expect(await screen.findByText('Here is the answer from authorized records.')).toBeInTheDocument()

    const chatCall = fetchMock.mock.calls.find(([input]) => String(input).includes('/admin/ai-assistant/chat'))
    expect(chatCall).toBeTruthy()
    expect(String(chatCall?.[0])).toContain('/admin/ai-assistant/chat')
  })

  it('keeps conversation history rendering', async () => {
    stubApi({
      conversations: [
        {
          id: 7,
          title: 'Department performance review',
          message_count: 4,
          created_at: '2026-11-01T00:00:00Z',
          updated_at: '2026-11-02T00:00:00Z',
        },
      ],
    })
    render(<AdminAiAssistantPage />)

    expect(await screen.findByText('Department performance review')).toBeInTheDocument()
    expect(screen.getByText(/4 messages/)).toBeInTheDocument()
  })

  it('keeps the safety notice visible after loading the page state', async () => {
    stubApi()
    render(<AdminAiAssistantPage />)

    await waitFor(() => expect(screen.getByRole('note', { name: 'JPC AIDE AI Safety Notice' })).toBeVisible())
  })
})
