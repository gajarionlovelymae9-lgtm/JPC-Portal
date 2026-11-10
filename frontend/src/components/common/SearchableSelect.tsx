import {
  useCallback,
  useEffect,
  useId,
  useMemo,
  useRef,
  useState,
  type KeyboardEvent,
} from 'react'

/**
 * Type-to-filter pickers used wherever a list is too long for a native
 * `<select>` — departments, positions, employee records.
 *
 * Both controls keep a text input in front of the list so the operator can
 * type part of a name and pick from the matches that survive the filter,
 * instead of scrolling the whole list. `SearchableSelect` picks one value;
 * `SearchableMultiSelect` picks many and shows the choices as removable chips.
 */

export interface SearchableOption {
  value: string | number
  label: string
  /** Optional secondary text; also matched by the search. */
  hint?: string
}

const controlClass =
  'w-full text-sm rounded-lg border border-slate-300 bg-white px-3 py-2 text-slate-800 placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-jpc-500 focus:border-jpc-500 disabled:bg-slate-50 disabled:text-slate-400'

const listClass =
  'absolute z-30 mt-1 max-h-60 w-full overflow-y-auto rounded-lg border border-slate-200 bg-white py-1 shadow-xl'

function optionClass(active: boolean): string {
  return `flex w-full flex-col items-start px-3 py-2 text-left text-sm transition-colors ${
    active ? 'bg-jpc-50 text-jpc-800' : 'text-slate-700 hover:bg-slate-50'
  }`
}

function matches(option: SearchableOption, needle: string): boolean {
  return `${option.label} ${option.hint ?? ''}`.toLowerCase().includes(needle)
}

/** Closes the list when the pointer lands anywhere outside the control. */
function useDismissable(open: boolean, close: () => void) {
  const ref = useRef<HTMLDivElement | null>(null)

  useEffect(() => {
    if (!open) return
    const onMouseDown = (event: MouseEvent) => {
      if (!ref.current?.contains(event.target as Node)) close()
    }
    document.addEventListener('mousedown', onMouseDown)
    return () => document.removeEventListener('mousedown', onMouseDown)
  }, [open, close])

  return ref
}

function OptionList({
  filtered,
  valueOf,
  highlight,
  emptyMessage,
  onHighlight,
  onChoose,
}: {
  filtered: SearchableOption[]
  valueOf: (option: SearchableOption) => boolean
  highlight: number
  emptyMessage: string
  onHighlight: (index: number) => void
  onChoose: (option: SearchableOption) => void
}) {
  if (filtered.length === 0) {
    return (
      <ul role="listbox" className={listClass}>
        <li className="px-3 py-2 text-xs text-slate-400">{emptyMessage}</li>
      </ul>
    )
  }

  return (
    <ul role="listbox" className={listClass}>
      {filtered.map((option, index) => (
        <li key={String(option.value)}>
          <button
            type="button"
            role="option"
            aria-selected={valueOf(option)}
            className={optionClass(index === highlight)}
            onMouseEnter={() => onHighlight(index)}
            // Keep focus on the input: the control is often nested inside a
            // <label>, and a focus jump there would re-open the list.
            onMouseDown={(event) => event.preventDefault()}
            onClick={() => onChoose(option)}
          >
            <span className="font-medium">{option.label}</span>
            {option.hint ? <span className="text-[11px] text-slate-400">{option.hint}</span> : null}
          </button>
        </li>
      ))}
    </ul>
  )
}

/** Type-to-filter single select. `value` is `''` when nothing is chosen. */
export function SearchableSelect({
  value,
  onChange,
  options,
  placeholder = 'Select…',
  disabled = false,
  clearable = true,
  className = '',
  id,
  emptyMessage = 'No matches found',
}: {
  value: string
  onChange: (value: string) => void
  options: SearchableOption[]
  placeholder?: string
  disabled?: boolean
  /** Shows a clear button once a value is chosen. */
  clearable?: boolean
  className?: string
  id?: string
  emptyMessage?: string
}) {
  const autoId = useId()
  const inputId = id ?? autoId
  const [open, setOpen] = useState(false)
  const [query, setQuery] = useState('')
  const [highlight, setHighlight] = useState(0)

  const selected = useMemo(
    () => options.find((option) => String(option.value) === value) ?? null,
    [options, value],
  )

  const filtered = useMemo(() => {
    const needle = query.trim().toLowerCase()
    return needle ? options.filter((option) => matches(option, needle)) : options
  }, [options, query])

  const close = useCallback(() => {
    setOpen(false)
    setQuery('')
  }, [])

  const containerRef = useDismissable(open, close)

  const choose = (option: SearchableOption) => {
    onChange(String(option.value))
    close()
  }

  const onKeyDown = (event: KeyboardEvent<HTMLInputElement>) => {
    if (event.key === 'ArrowDown') {
      event.preventDefault()
      setOpen(true)
      setHighlight((index) => Math.min(index + 1, Math.max(filtered.length - 1, 0)))
    } else if (event.key === 'ArrowUp') {
      event.preventDefault()
      setHighlight((index) => Math.max(index - 1, 0))
    } else if (event.key === 'Enter') {
      if (open && filtered[highlight]) {
        event.preventDefault()
        choose(filtered[highlight])
      }
    } else if (event.key === 'Escape') {
      close()
    }
  }

  return (
    <div ref={containerRef} className={`relative ${className}`}>
      <input
        id={inputId}
        role="combobox"
        aria-expanded={open}
        aria-autocomplete="list"
        autoComplete="off"
        disabled={disabled}
        className={`${controlClass} ${clearable && value !== '' ? 'pr-14' : ''}`}
        placeholder={placeholder}
        value={open ? query : (selected?.label ?? '')}
        onFocus={() => {
          setOpen(true)
          setQuery('')
          setHighlight(0)
        }}
        onChange={(event) => {
          setOpen(true)
          setQuery(event.target.value)
          setHighlight(0)
        }}
        onKeyDown={onKeyDown}
      />

      {clearable && value !== '' && !disabled ? (
        <button
          type="button"
          aria-label="Clear selection"
          className="absolute right-2 top-1/2 -translate-y-1/2 rounded px-1.5 py-0.5 text-xs font-bold text-slate-400 hover:bg-slate-100 hover:text-slate-600"
          onClick={() => {
            onChange('')
            close()
          }}
        >
          ×
        </button>
      ) : null}

      {open ? (
        <OptionList
          filtered={filtered}
          valueOf={(option) => String(option.value) === value}
          highlight={highlight}
          emptyMessage={emptyMessage}
          onHighlight={setHighlight}
          onChoose={choose}
        />
      ) : null}
    </div>
  )
}

/**
 * Type-to-filter multi select. `values` holds the chosen option values as
 * strings; the selection is shown as chips inside the control.
 */
export function SearchableMultiSelect({
  values,
  onChange,
  options,
  placeholder = 'Type a name to search…',
  disabled = false,
  className = '',
  id,
  emptyMessage = 'No matches found',
}: {
  values: string[]
  onChange: (values: string[]) => void
  options: SearchableOption[]
  placeholder?: string
  disabled?: boolean
  className?: string
  id?: string
  emptyMessage?: string
}) {
  const autoId = useId()
  const inputId = id ?? autoId
  const [open, setOpen] = useState(false)
  const [query, setQuery] = useState('')
  const [highlight, setHighlight] = useState(0)

  const chosen = useMemo(
    () =>
      values
        .map((value) => options.find((option) => String(option.value) === value))
        .filter((option): option is SearchableOption => Boolean(option)),
    [options, values],
  )

  const filtered = useMemo(() => {
    const needle = query.trim().toLowerCase()
    return needle ? options.filter((option) => matches(option, needle)) : options
  }, [options, query])

  const close = useCallback(() => {
    setOpen(false)
    setQuery('')
  }, [])

  const containerRef = useDismissable(open, close)

  const toggle = (option: SearchableOption) => {
    const key = String(option.value)
    onChange(values.includes(key) ? values.filter((value) => value !== key) : [...values, key])
    // Keep the control open: assigning several teachers in one pass is the
    // normal case, and the chips already confirm each pick.
    setQuery('')
    setOpen(true)
  }

  const onKeyDown = (event: KeyboardEvent<HTMLInputElement>) => {
    if (event.key === 'ArrowDown') {
      event.preventDefault()
      setOpen(true)
      setHighlight((index) => Math.min(index + 1, Math.max(filtered.length - 1, 0)))
    } else if (event.key === 'ArrowUp') {
      event.preventDefault()
      setHighlight((index) => Math.max(index - 1, 0))
    } else if (event.key === 'Enter') {
      if (open && filtered[highlight]) {
        event.preventDefault()
        toggle(filtered[highlight])
      }
    } else if (event.key === 'Escape') {
      close()
    } else if (event.key === 'Backspace' && query === '' && values.length > 0) {
      onChange(values.slice(0, -1))
    }
  }

  return (
    <div ref={containerRef} className={`relative ${className}`}>
      <div
        className="flex flex-wrap items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-2 py-1.5 focus-within:ring-2 focus-within:ring-jpc-500 focus-within:border-jpc-500"
        onClick={() => setOpen(true)}
      >
        {chosen.map((option) => (
          <span
            key={String(option.value)}
            className="inline-flex items-center gap-1 rounded-full bg-jpc-50 px-2 py-0.5 text-xs font-semibold text-jpc-800 ring-1 ring-inset ring-jpc-200"
          >
            {option.label}
            {!disabled ? (
              <button
                type="button"
                aria-label={`Remove ${option.label}`}
                className="text-jpc-600 hover:text-jpc-900"
                onClick={(event) => {
                  event.stopPropagation()
                  toggle(option)
                }}
              >
                ×
              </button>
            ) : null}
          </span>
        ))}

        <input
          id={inputId}
          role="combobox"
          aria-expanded={open}
          aria-autocomplete="list"
          autoComplete="off"
          disabled={disabled}
          className="min-w-[8rem] flex-1 border-0 bg-transparent px-1 py-1 text-sm text-slate-800 placeholder:text-slate-400 focus:outline-none focus:ring-0"
          placeholder={chosen.length === 0 ? placeholder : ''}
          value={query}
          onFocus={() => {
            setOpen(true)
            setHighlight(0)
          }}
          onChange={(event) => {
            setOpen(true)
            setQuery(event.target.value)
            setHighlight(0)
          }}
          onKeyDown={onKeyDown}
        />
      </div>

      {open ? (
        <OptionList
          filtered={filtered}
          valueOf={(option) => values.includes(String(option.value))}
          highlight={highlight}
          emptyMessage={emptyMessage}
          onHighlight={setHighlight}
          onChoose={toggle}
        />
      ) : null}
    </div>
  )
}
