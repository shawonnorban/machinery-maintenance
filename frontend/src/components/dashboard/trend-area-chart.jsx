"use client";

import { Area, AreaChart, CartesianGrid, ResponsiveContainer, Tooltip, XAxis } from "recharts";

/**
 * The gradient-filled daily trend behind every dashboard's headline stat
 * cards — how a period's breakdowns and completions actually moved day by
 * day, rather than one summed number that hides a bad week inside an
 * otherwise ordinary month. Backed by `GET /dashboard/trend`.
 *
 * A Client Component (Recharts renders via `ResizeObserver`/refs, which a
 * Server Component cannot do) — `series` and `lines` are plain data/config
 * objects, not functions, so they cross the Server→Client boundary as JSX
 * props the same way any other data does.
 *
 * @param {{
 *   series: { date: string, breakdowns: number, completed_work_orders: number }[],
 *   lines: { key: string, label: string, color: string }[],
 * }} props
 */
function TrendAreaChart({ series, lines }) {
  const data = series.map((point) => ({
    ...point,
    label: formatShortDate(point.date),
  }));

  return (
    <div className="h-64 w-full">
      <ResponsiveContainer width="100%" height="100%">
        <AreaChart data={data} margin={{ top: 8, right: 8, left: -16, bottom: 0 }}>
          <defs>
            {lines.map((line) => (
              <linearGradient key={line.key} id={`trend-fill-${line.key}`} x1="0" y1="0" x2="0" y2="1">
                <stop offset="5%" stopColor={line.color} stopOpacity={0.35} />
                <stop offset="95%" stopColor={line.color} stopOpacity={0.02} />
              </linearGradient>
            ))}
          </defs>
          <CartesianGrid vertical={false} stroke="var(--border)" strokeDasharray="3 3" />
          <XAxis
            dataKey="label"
            tick={{ fill: "var(--foreground-muted)", fontSize: 11 }}
            tickLine={false}
            axisLine={{ stroke: "var(--border)" }}
            interval="preserveStartEnd"
            minTickGap={24}
          />
          <Tooltip
            contentStyle={{
              background: "var(--surface)",
              border: "1px solid var(--border)",
              borderRadius: "var(--radius-sm, 6px)",
              fontSize: 12,
            }}
            labelStyle={{ color: "var(--foreground)", fontWeight: 600, marginBottom: 4 }}
          />
          {lines.map((line) => (
            <Area
              key={line.key}
              type="monotone"
              dataKey={line.key}
              name={line.label}
              stroke={line.color}
              strokeWidth={2}
              fill={`url(#trend-fill-${line.key})`}
            />
          ))}
        </AreaChart>
      </ResponsiveContainer>
    </div>
  );
}

function formatShortDate(isoDate) {
  const date = new Date(`${isoDate}T00:00:00`);
  return date.toLocaleDateString(undefined, { month: "short", day: "numeric" });
}

export { TrendAreaChart };
