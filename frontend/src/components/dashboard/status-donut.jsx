"use client";

import { Cell, Pie, PieChart, ResponsiveContainer, Tooltip } from "recharts";

/**
 * A donut with its total in the centre — the same shape the reference
 * design's "Project Efficiency" widget uses, applied to a real status
 * breakdown (asset status, spare-part health) instead of a page-view count.
 * A Client Component for the same reason `TrendAreaChart` is.
 *
 * @param {{
 *   segments: { key: string, label: string, value: number, color: string }[],
 *   total: number,
 *   totalLabel: string,
 * }} props
 */
function StatusDonut({ segments, total, totalLabel }) {
  const nonZero = segments.filter((s) => s.value > 0);

  return (
    <div className="relative mx-auto h-44 w-44">
      <ResponsiveContainer width="100%" height="100%">
        <PieChart>
          <Pie
            data={nonZero.length > 0 ? nonZero : [{ key: "empty", value: 1, color: "var(--border)" }]}
            dataKey="value"
            nameKey="label"
            innerRadius="70%"
            outerRadius="100%"
            paddingAngle={nonZero.length > 1 ? 3 : 0}
            stroke="none"
            isAnimationActive={false}
          >
            {(nonZero.length > 0 ? nonZero : [{ key: "empty", color: "var(--border)" }]).map((segment) => (
              <Cell key={segment.key} fill={segment.color} />
            ))}
          </Pie>
          {nonZero.length > 0 ? (
            <Tooltip
              contentStyle={{
                background: "var(--surface)",
                border: "1px solid var(--border)",
                borderRadius: "var(--radius-sm, 6px)",
                fontSize: 12,
              }}
            />
          ) : null}
        </PieChart>
      </ResponsiveContainer>
      <div className="pointer-events-none absolute inset-0 flex flex-col items-center justify-center">
        <span className="tabular text-2xl font-semibold text-foreground">{total}</span>
        <span className="text-xs text-foreground-muted">{totalLabel}</span>
      </div>
    </div>
  );
}

export { StatusDonut };
