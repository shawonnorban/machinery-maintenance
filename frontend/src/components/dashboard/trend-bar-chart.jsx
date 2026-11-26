"use client";

import { Bar, BarChart, CartesianGrid, ResponsiveContainer, Tooltip, XAxis, YAxis } from "recharts";

/**
 * Breakdowns reported against work orders completed, as paired bars.
 *
 * Bars, not a smoothed area: these are whole counts on mostly-quiet days,
 * and a monotone curve drew spikes between zeros that read as a wave of
 * failures. Longer than a month is summed per week so 90 days is ~13 pairs
 * of readable bars instead of 90 slivers.
 *
 * @param {{
 *   series: { date: string, breakdowns: number, completed_work_orders: number }[],
 *   lines: { key: string, label: string, color: string }[],
 * }} props
 */
function TrendBarChart({ series, lines }) {
  const data = series.length > 31 ? weekly(series) : series.map((point) => ({ ...point, label: shortDate(point.date) }));

  return (
    <div className="h-60 w-full">
      <ResponsiveContainer width="100%" height="100%">
        <BarChart data={data} margin={{ top: 8, right: 4, left: -24, bottom: 0 }} barGap={2} barCategoryGap="22%">
          <CartesianGrid vertical={false} stroke="var(--border)" strokeDasharray="3 3" />
          <XAxis
            dataKey="label"
            tick={{ fill: "var(--foreground-muted)", fontSize: 11 }}
            tickLine={false}
            axisLine={{ stroke: "var(--border)" }}
            interval="preserveStartEnd"
            minTickGap={16}
          />
          <YAxis allowDecimals={false} tick={{ fill: "var(--foreground-muted)", fontSize: 11 }} tickLine={false} axisLine={false} width={40} />
          <Tooltip
            cursor={{ fill: "var(--surface-muted)" }}
            contentStyle={{
              background: "var(--surface)",
              border: "1px solid var(--border)",
              borderRadius: "var(--radius-sm, 6px)",
              fontSize: 12,
            }}
            labelStyle={{ color: "var(--foreground)", fontWeight: 600, marginBottom: 4 }}
            labelFormatter={(label, payload) => payload?.[0]?.payload?.range ?? label}
            itemStyle={{ color: "var(--foreground)" }}
          />
          {lines.map((line) => (
            <Bar key={line.key} dataKey={line.key} name={line.label} fill={line.color} radius={[4, 4, 0, 0]} maxBarSize={18} />
          ))}
        </BarChart>
      </ResponsiveContainer>
    </div>
  );
}

function weekly(series) {
  const weeks = [];

  for (let i = 0; i < series.length; i += 7) {
    const chunk = series.slice(i, i + 7);
    weeks.push({
      label: shortDate(chunk[0].date),
      range: `${shortDate(chunk[0].date)} – ${shortDate(chunk[chunk.length - 1].date)}`,
      breakdowns: chunk.reduce((sum, p) => sum + p.breakdowns, 0),
      completed_work_orders: chunk.reduce((sum, p) => sum + p.completed_work_orders, 0),
    });
  }

  return weeks;
}

function shortDate(isoDate) {
  return new Date(`${isoDate}T00:00:00`).toLocaleDateString(undefined, { month: "short", day: "numeric" });
}

export { TrendBarChart };
