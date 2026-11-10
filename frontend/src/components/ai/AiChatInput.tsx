import { useState } from 'react'
import type { KeyboardEvent } from 'react'

interface AiChatInputProps {
  onSend: (message: string) => void
  disabled?: boolean
}

export function AiChatInput({ onSend, disabled = false }: AiChatInputProps) {
  const [text, setText] = useState('')
  const MAX_CHARS = 2000

  const handleSend = () => {
    const trimmed = text.trim()
    if (trimmed && !disabled) {
      onSend(trimmed)
      setText('')
    }
  }

  const handleKeyDown = (e: KeyboardEvent<HTMLTextAreaElement>) => {
    if (e.key === 'Enter' && !e.shiftKey) {
      e.preventDefault()
      handleSend()
    }
  }

  return (
    <div className="p-4 border-t bg-white relative flex flex-col">
      <textarea
        className="w-full border rounded-lg p-3 resize-none focus:outline-none focus:ring-2 focus:ring-blue-500"
        rows={3}
        placeholder="Ask JPC AIDE..."
        value={text}
        onChange={(e) => setText(e.target.value.slice(0, MAX_CHARS))}
        onKeyDown={handleKeyDown}
        disabled={disabled}
      />
      <div className="flex justify-between items-center mt-2">
        <span className="text-xs text-gray-400">
          {text.length}/{MAX_CHARS}
        </span>
        <button
          className="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded disabled:opacity-50"
          onClick={handleSend}
          disabled={disabled || !text.trim()}
        >
          Send
        </button>
      </div>
    </div>
  )
}
