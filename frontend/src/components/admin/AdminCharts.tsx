import type { ReactNode } from 'react'

/**
 * Dependency-free SVG charts for the admin dashboard.
 *
 * These follow the same hand-rolled approach as the existing
 * `PerformanceTrendChart` / `DataQualityCategoryChart` components: no charting
 * library is added to the bundle.
 */

export const CHART_COLOURS = [
  '#1d4ed8', // jpc-600
  '#0ea5e9', // sky-500
  '#16a34a', // green-600
  '#d97706', // amber-600
  '#dc2626', // red-600
  '#7c3aed', // violet-600
  '#0f766e', // teal-700
  '#64748b', // slate-500
]

export interface ChartDatum {
  key: string
  label: string
  value: number
  percent?: number
}

/* ------------------------------------------------------------------ */
/* Donut                                                               */
/* ------------------------------------------------------------------ */

function polar(cx: number, cy: number, r: number, angle: number) {
  const rad = ((angle - 90) * Math.PI) / 180
  return { x: cx + r * Math.cos(rad), y: cy + r * Math.sin(rad) }
}

function arcPath(cx: number, cy: number, rOuter: number, rInner: number, start: number, end: number) {
  const large = end - start > 180 ? 1 : 0
  const o1 = polar(cx, cy, rOuter, start)
  const o2 = polar(cx, cy, rOuter, end)
  const i1 = polar(cx, cy, rInner, end)
  const i2 = polar(cx, cy, rInner, start)
  return [
    `M ${o1.x} ${o1.y}`,
    `A ${rOuter} ${rOuter} 0 ${large} 1 ${o2.x} ${o2.y}`,
    `L ${i1.x} ${i1.y}`,
    `A ${rInner} ${rInner} 0 ${large} 0 ${i2.x} ${i2.y}`,
    'Z',
  ].join(' ')
}

/**
 * Category donut. `mutuallyExclusive` must be false when the source buckets
 * overlap (for example a "submitted" bucket that already contains "approved"),
 * because the slices would then not add up to the total.
 */
export function DonutChart({
  data,
  centreLabel,
  centreValue,
  mutuallyExclusive = true,
  emptyMessage = 'No data for this period.',
}: {
  data: ChartDatum[]
  centreLabel?: string
  centreValue?: string | number
  mutuallyExclusive?: boolean
  emptyMessage?: string
}) {
  const total = data.reduce((sum, d) => sum + d.value, 0)

  if (total <= 0) {
    return <ChartEmpty>{emptyMessage}</ChartEmpty>
  }

  const cx = 110
  const cy = 110
  const rOuter = 92
  const rInner = 58

  const slices = data
    .filter((d) => d.value > 0)
    .reduce<{
      cursor: number
      items: Array<(typeof data)[number] & { path: string; colour: string }>
    }>(
      (acc, d, index) => {
        const sweep = (d.value / total) * 360
        acc.items.push({
          ...d,
          path: arcPath(cx, cy, rOuter, rInner, acc.cursor, acc.cursor + sweep),
          colour: CHART_COLOURS[index % CHART_COLOURS.length],
        })

        return { cursor: acc.cursor + sweep, items: acc.items }
      },
      { cursor: 0, items: [] },
    ).items

  return (
    <div className="flex flex-col sm:flex-row items-center gap-6">
      <svg viewBox="0 0 220 220" className="w-44 h-44 shrink-0" role="img" aria-label="Category distribution">
        {slices.map((slice) => (
          <path key={slice.key} d={slice.path} fill={slice.colour} />
        ))}
        <text x={cx} y={cy - 4} textAnchor="middle" className="fill-slate-900" style={{ fontSize: 26, fontWeight: 800 }}>
          {centreValue ?? total}
        </text>
        <text x={cx} y={cy + 16} textAnchor="middle" className="fill-slate-500" style={{ fontSize: 11 }}>
          {centreLabel ?? 'total'}
        </text>
      </svg>
      <ul className="space-y-1.5 w-full">
        {slices.map((slice) => (
          <li key={slice.key} className="flex items-center justify-between gap-3 text-xs">
            <span className="flex items-center gap-2 min-w-0">
              <span className="w-2.5 h-2.5 rounded-sm shrink-0" style={{ background: slice.colour }} />
              <span className="truncate text-slate-700">{slice.label}</span>
            </span>
            <span className="font-bold tabular-nums text-slate-900 shrink-0">
              {slice.value}
              <span className="text-slate-400 font-normal">
                {' '}
                ({((slice.value / total) * 100).toFixed(1)}%)
              </span>
            </span>
          </li>
        ))}
      </ul>
      {!mutuallyExclusive ? (
        <p className="text-[11px] text-amber-800 bg-amber-50 border border-amber-200 rounded-lg px-3 py-2 w-full">
          These categories overlap, so the slices do not represent a share of a single total.
        </p>
      ) : null}
    </div>
  )
}

/* ------------------------------------------------------------------ */
/* Grouped bars                                                        */
/* ------------------------------------------------------------------ */

export interface GroupedBarSeries {
  key: string
  label: string
  colour: string
  values: number[]
}

/**
 * Grouped vertical bar chart. A `null`-safe wrapper (`GroupedBarChartNullable`)
 * accepts optional values so missing metrics are skipped rather than drawn
 * as zero.
 */
export function GroupedBarChart({
  labels,
  series,
  height = 220,
  suffix = '',
  emptyMessage = 'No data for this period.',
}: {
  labels: string[]
  series: GroupedBarSeries[]
  height?: number
  suffix?: string
  emptyMessage?: string
}) {
  const values = series.flatMap((s) => s.values).filter((v) => v !== null && v !== undefined)
  if (labels.length === 0 || series.length === 0 || values.length === 0) {
    return <ChartEmpty>{emptyMessage}</ChartEmpty>
  }

  const max = Math.max(...values, 1)
  const padTop = 16
  const axisHeight = 34
  const width = 640
  const plotWidth = width - 24
  const plotHeight = height - padTop - axisHeight
  const groupWidth = plotWidth / labels.length
  const barGap = 4
  const barWidth = Math.max(
    6,
    Math.min(38, (groupWidth - 18 - barGap * (series.length - 1)) / series.length),
  )

  const ticks = 4
  const tickValues = Array.from({ length: ticks + 1 }, (_, i) => (max / ticks) * i)

  return (
    <div className="w-full overflow-x-auto">
      <svg viewBox={`0 0 ${width} ${height}`} className="w-full min-w-[520px]" role="img" aria-label="Comparison by category">
        {tickValues.map((tick, i) => {
          const y = padTop + plotHeight - (tick / max) * plotHeight
          return (
            <g key={i}>
              <line x1={12} y1={y} x2={width - 12} y2={y} stroke="#e2e8f0" strokeWidth={1} />
              <text x={14} y={y - 3} className="fill-slate-400" style={{ fontSize: 9 }}>
                {Math.round(tick)}
                {suffix}
              </text>
            </g>
          )
        })}

        {labels.map((label, labelIndex) => {
          const groupX = 12 + groupWidth * labelIndex
          const totalBarWidth = barWidth * series.length + barGap * (series.length - 1)
          const startX = groupX + (groupWidth - totalBarWidth) / 2

          return (
            <g key={label}>
              {series.map((serie, serieIndex) => {
                const value = serie.values[labelIndex]
                if (value === null || value === undefined) return null
                const barHeight = (value / max) * plotHeight
                return (
                  <rect
                    key={serie.key}
                    x={startX + serieIndex * (barWidth + barGap)}
                    y={padTop + plotHeight - barHeight}
                    width={barWidth}
                    height={Math.max(barHeight, value > 0 ? 2 : 0)}
                    rx={3}
                    fill={serie.colour}
                  >
                    <title>{`${serie.label} · ${label}: ${value}${suffix}`}</title>
                  </rect>
                )
              })}
              <text
                x={groupX + groupWidth / 2}
                y={height - axisHeight + 16}
                textAnchor="middle"
                className="fill-slate-500"
                style={{ fontSize: 9.5 }}
              >
                {label.length > 16 ? `${label.slice(0, 15)}…` : label}
              </text>
            </g>
          )
        })}
      </svg>
      <div className="flex flex-wrap gap-4 mt-2">
        {series.map((serie) => (
          <span key={serie.key} className="flex items-center gap-1.5 text-[11px] text-slate-600">
            <span className="w-2.5 h-2.5 rounded-sm" style={{ background: serie.colour }} />
            {serie.label}
          </span>
        ))}
      </div>
    </div>
  )
}

/** Nullable-value variant: gaps mean "no data", not zero. */
export function GroupedBarChartNullable(props: {
  labels: string[]
  series: Array<Omit<GroupedBarSeries, 'values'> & { values: Array<number | null> }>
  height?: number
  suffix?: string
  emptyMessage?: string
}) {
  const { series, ...rest } = props
  const complete = series.every((s) => s.values.every((v) => v !== null && v !== undefined))
  if (complete) {
    return <GroupedBarChart {...rest} series={series as GroupedBarSeries[]} />
  }
  return (
    <p className="text-[11px] text-slate-500 bg-slate-50 border border-slate-200 rounded-lg px-3 py-2">
      Some categories have no recorded data for these metrics, so they are omitted from the comparison
      rather than plotted as zero.
    </p>
  )
}

/* ------------------------------------------------------------------ */
/* Line / area trend                                                   */
/* ------------------------------------------------------------------ */

export function TrendChart({
  labels,
  series,
  height = 240,
  suffix = '',
  emptyMessage = 'No data for this period.',
}: {
  labels: string[]
  series: Array<{ key: string; label: string; colour: string; values: Array<number | null> }>
  height?: number
  suffix?: string
  emptyMessage?: string
}) {
  const values = series.flatMap((s) => s.values).filter((v): v is number => v !== null && v !== undefined)
  if (labels.length === 0 || series.length === 0 || values.length === 0) {
    return <ChartEmpty>{emptyMessage}</ChartEmpty>
  }

  const max = Math.max(...values, 1)
  const padTop = 16
  const padBottom = 30
  const padLeft = 40
  const width = 680
  const plotWidth = width - padLeft - 12
  const plotHeight = height - padTop - padBottom
  const step = labels.length > 1 ? plotWidth / (labels.length - 1) : 0
  const ticks = 4

  return (
    <div className="w-full overflow-x-auto">
      <svg viewBox={`0 0 ${width} ${height}`} className="w-full min-w-[540px]" role="img" aria-label="Trend over time">
        {Array.from({ length: ticks + 1 }, (_, i) => {
          const tick = (max / ticks) * i
          const y = padTop + plotHeight - (tick / max) * plotHeight
          return (
            <g key={i}>
              <line x1={padLeft} y1={y} x2={width - 12} y2={y} stroke="#e2e8f0" />
              <text x={padLeft - 6} y={y + 3} textAnchor="end" className="fill-slate-400" style={{ fontSize: 9 }}>
                {Math.round(tick)}
                {suffix}
              </text>
            </g>
          )
        })}

        {series.map((serie) => {
          const points = serie.values
            .map((value, index) =>
              value === null || value === undefined
                ? null
                : { x: padLeft + step * index, y: padTop + plotHeight - (value / max) * plotHeight },
            )
            .filter((p): p is { x: number; y: number } => p !== null)

          if (points.length === 0) return null

          const line = points.map((p, i) => `${i === 0 ? 'M' : 'L'} ${p.x} ${p.y}`).join(' ')
          const area = `${line} L ${points[points.length - 1].x} ${padTop + plotHeight} L ${points[0].x} ${
            padTop + plotHeight
          } Z`

          return (
            <g key={serie.key}>
              <path d={area} fill={serie.colour} opacity={0.08} />
              <path d={line} fill="none" stroke={serie.colour} strokeWidth={2} strokeLinejoin="round" />
              {points.map((p, i) => (
                <circle key={i} cx={p.x} cy={p.y} r={2.6} fill={serie.colour}>
                  <title>{`${serie.label} · ${labels[i]}: ${serie.values[i]}${suffix}`}</title>
                </circle>
              ))}
            </g>
          )
        })}

        {labels.map((label, index) => (
          <text
            key={label + index}
            x={padLeft + step * index}
            y={height - 10}
            textAnchor="middle"
            className="fill-slate-500"
            style={{ fontSize: 9.5 }}
          >
            {label}
          </text>
        ))}
      </svg>
      <div className="flex flex-wrap gap-4 mt-2">
        {series.map((serie) => (
          <span key={serie.key} className="flex items-center gap-1.5 text-[11px] text-slate-600">
            <span className="w-2.5 h-2.5 rounded-sm" style={{ background: serie.colour }} />
            {serie.label}
          </span>
        ))}
      </div>
    </div>
  )
}

/* ------------------------------------------------------------------ */
/* Horizontal ranking bars                                             */
/* ------------------------------------------------------------------ */

export function RankingBars({
  rows,
  suffix = '',
  emptyMessage = 'No data to rank yet.',
}: {
  rows: Array<{ key: string | number; label: string; value: number | null; colour?: string }>
  suffix?: string
  emptyMessage?: string
}) {
  const usable = rows.filter((r) => r.value !== null && r.value !== undefined)
  if (usable.length === 0) {
    return <ChartEmpty>{emptyMessage}</ChartEmpty>
  }
  const max = Math.max(...usable.map((r) => r.value as number), 1)

  return (
    <ul className="space-y-3">
      {rows.map((row, index) => (
        <li key={row.key}>
          <div className="flex items-center justify-between gap-3 text-xs mb-1">
            <span className="font-semibold text-slate-700 truncate">
              {index + 1}. {row.label}
            </span>
            <span className="font-bold tabular-nums text-slate-900 shrink-0">
              {row.value === null || row.value === undefined
                ? '—'
                : `${Number(row.value).toFixed(1)}${suffix}`}
            </span>
          </div>
          <div className="h-2 rounded-full bg-slate-100 overflow-hidden">
            <div
              className="h-full rounded-full"
              style={{
                width: `${row.value === null || row.value === undefined ? 0 : (row.value / max) * 100}%`,
                background: row.colour ?? CHART_COLOURS[index % CHART_COLOURS.length],
              }}
            />
          </div>
        </li>
      ))}
    </ul>
  )
}

/* ------------------------------------------------------------------ */
/* Stacked proportion bar                                              */
/* ------------------------------------------------------------------ */

export function StackedProportionBar({
  segments,
}: {
  segments: Array<{ key: string; label: string; value: number; colour: string }>
}) {
  const total = segments.reduce((sum, s) => sum + s.value, 0)
  if (total <= 0) {
    return <ChartEmpty>No recorded items yet.</ChartEmpty>
  }
  return (
    <div>
      <div className="flex h-4 rounded-full overflow-hidden bg-slate-100">
        {segments
          .filter((s) => s.value > 0)
          .map((s) => (
            <div
              key={s.key}
              style={{ width: `${(s.value / total) * 100}%`, background: s.colour }}
              title={`${s.label}: ${s.value}`}
            />
          ))}
      </div>
      <div className="flex flex-wrap gap-3 mt-2">
        {segments.map((s) => (
          <span key={s.key} className="flex items-center gap-1.5 text-[11px] text-slate-600">
            <span className="w-2.5 h-2.5 rounded-sm" style={{ background: s.colour }} />
            {s.label}
            <span className="font-bold text-slate-900">{s.value}</span>
          </span>
        ))}
      </div>
    </div>
  )
}

function ChartEmpty({ children }: { children: ReactNode }) {
  return (
    <div className="h-40 grid place-items-center text-xs text-slate-400 border border-dashed border-slate-200 rounded-xl">
      {children}
    </div>
  )
}
