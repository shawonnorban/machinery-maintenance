"use client";

import Link from "next/link";
import {
  Bar,
  BarChart,
  CartesianGrid,
  Legend,
  ResponsiveContainer,
  Tooltip,
  XAxis,
  YAxis,
} from "recharts";
import {
  AlarmClock,
  ArrowDownToLine,
  ArrowUpFromLine,
  CircleCheck,
  ClipboardList,
  Hourglass,
  PackageX,
  Plus,
  Split,
} from "lucide-react";
import { cn } from "@/lib/utils";
import { buttonVariants } from "@/components/ui/button";
import { StatusDonut } from "@/components/dashboard/status-donut";
import { card, day, useGp, PassTable } from "./common";

/*
 * Gate Pass → Dashboard (AnnotechERP 11/02), as Brickland draws it: Create
 * Gate Pass; the eight tiles — total, today's OUT and IN, pending, partial,
 * short, overdue and closed; OUT against IN over the last fourteen days; how
 * the returnable item passes have come back; passes by purpose and by
 * location; what is overdue; the latest passes.
 */

const TONE = {
  info: ["border-t-info", "bg-info-subtle text-info"],
  danger: ["border-t-danger", "bg-danger-subtle text-danger"],
  warning: ["border-t-warning", "bg-warning-subtle text-warning"],
  success: ["border-t-success", "bg-success-subtle text-success"],
};

function BigTile({ label, value, Icon, tone }) {
  return (
    <div className={cn(card, "border-t-4 p-4", TONE[tone][0])}>
      <div className="flex items-start justify-between gap-2">
        <p className="text-[13px] font-semibold text-foreground">{label}</p>
        <span className={cn("rounded-md p-1.5", TONE[tone][1])}>
          <Icon className="size-4" aria-hidden />
        </span>
      </div>
      <p className="mt-2 text-2xl font-semibold tabular-nums text-foreground">
        {value}
      </p>
    </div>
  );
}

function Bars({ title, rows, empty, color }) {
  const max = Math.max(1, ...rows.map((r) => r.count));
  return (
    <section className={cn(card, "min-w-0 p-5")}>
      <h2 className="text-base font-semibold text-foreground">{title}</h2>
      <ul className="mt-4 flex flex-col gap-3 text-sm">
        {rows.length === 0 ? (
          <li className="text-foreground-muted">{empty}</li>
        ) : null}
        {rows.map((r) => (
          <li
            key={r.label}
            className="grid grid-cols-[9rem_1fr_auto] items-center gap-3"
          >
            <span className="truncate text-foreground" title={r.label}>
              {r.label}
            </span>
            <span className="h-4 rounded-sm bg-surface-muted">
              <span
                className="block h-full rounded-sm"
                style={{
                  width: `${(r.count / max) * 100}%`,
                  background: color,
                }}
              />
            </span>
            <span className="text-right font-semibold tabular-nums text-foreground">
              {r.count}
            </span>
          </li>
        ))}
      </ul>
    </section>
  );
}

/**
 * Dashboard.
 *
 * @param {{ data: object, canCreate: boolean }} props
 */
function GpDashboard({ data, canCreate }) {
  const { g } = useGp();
  const d = (k, p) => g(`dash.${k}`, p);
  const t = data.tiles;
  const segments = [
    ["FULL", "return_full", "var(--series-3)"],
    ["PARTIAL", "return_partial", "var(--series-2)"],
    ["SHORT", "return_short", "var(--danger)"],
    ["PENDING", "return_pending", "var(--series-1)"],
  ].map(([key, label, color]) => ({
    key,
    label: d(label),
    value: data.returns[key],
    color,
  }));

  return (
    <div className="flex flex-col gap-4">
      {canCreate ? (
        <div className="flex justify-end">
          <Link
            href="/gate-pass/gp-create"
            className={buttonVariants({ size: "sm" })}
          >
            <Plus className="size-4" aria-hidden />
            {g("btn.create")}
          </Link>
        </div>
      ) : null}

      <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <BigTile
          label={d("total")}
          value={t.total}
          Icon={ClipboardList}
          tone="info"
        />
        <BigTile
          label={d("today_out")}
          value={t.today_out}
          Icon={ArrowUpFromLine}
          tone="info"
        />
        <BigTile
          label={d("today_in")}
          value={t.today_in}
          Icon={ArrowDownToLine}
          tone="success"
        />
        <BigTile
          label={d("pending_return")}
          value={t.pending_return}
          Icon={Hourglass}
          tone="warning"
        />
        <BigTile
          label={d("partial_return")}
          value={t.partial_return}
          Icon={Split}
          tone="danger"
        />
        <BigTile
          label={d("short")}
          value={t.short}
          Icon={PackageX}
          tone="danger"
        />
        <BigTile
          label={d("overdue")}
          value={t.overdue}
          Icon={AlarmClock}
          tone="danger"
        />
        <BigTile
          label={d("closed")}
          value={t.closed}
          Icon={CircleCheck}
          tone="success"
        />
      </div>

      <div className="grid gap-4 lg:grid-cols-[1.8fr_1fr]">
        <section className={cn(card, "min-w-0 p-5")}>
          <h2 className="text-base font-semibold text-foreground">
            {d("movement")}
          </h2>
          <p className="text-[13px] text-foreground-muted">
            {d("movement_hint")}
          </p>
          <div className="mt-3 h-64">
            <ResponsiveContainer width="100%" height="100%">
              <BarChart data={data.movement} barGap={2}>
                <CartesianGrid
                  strokeDasharray="3 3"
                  stroke="var(--border)"
                  vertical={false}
                />
                <XAxis
                  dataKey="date"
                  tickFormatter={(x) => day(x).slice(0, 6)}
                  tick={{ fontSize: 11, fill: "var(--foreground-muted)" }}
                  axisLine={false}
                  tickLine={false}
                />
                <YAxis
                  allowDecimals={false}
                  tick={{ fontSize: 11, fill: "var(--foreground-muted)" }}
                  axisLine={false}
                  tickLine={false}
                  width={32}
                />
                <Tooltip
                  cursor={{ fill: "var(--surface-muted)" }}
                  labelFormatter={(x) => day(x)}
                  contentStyle={{
                    borderRadius: 8,
                    borderColor: "var(--border)",
                    fontSize: 12,
                  }}
                />
                <Legend iconType="circle" wrapperStyle={{ fontSize: 12 }} />
                <Bar
                  dataKey="out"
                  name={d("out")}
                  fill="var(--series-1)"
                  radius={[4, 4, 0, 0]}
                  isAnimationActive={false}
                />
                <Bar
                  dataKey="in"
                  name={d("in")}
                  fill="var(--series-3)"
                  radius={[4, 4, 0, 0]}
                  isAnimationActive={false}
                />
              </BarChart>
            </ResponsiveContainer>
          </div>
        </section>

        <section className={cn(card, "min-w-0 p-5")}>
          <h2 className="text-base font-semibold text-foreground">
            {d("returns")}
          </h2>
          <p className="text-[13px] text-foreground-muted">
            {d("returns_hint")}
          </p>
          {data.returns.total === 0 ? (
            <p className="mt-6 text-sm text-foreground-muted">
              {d("returns_empty")}
            </p>
          ) : (
            <>
              <div className="mt-3">
                <StatusDonut
                  segments={segments}
                  total={data.returns.total}
                  totalLabel={d("returnable")}
                />
              </div>
              <ul className="mt-4 flex flex-col gap-1.5 text-sm">
                {segments.map((s) => (
                  <li
                    key={s.key}
                    className="flex items-center justify-between gap-2"
                  >
                    <span className="flex items-center gap-2 text-foreground">
                      <span
                        className="size-2.5 rounded-full"
                        style={{ background: s.color }}
                        aria-hidden
                      />
                      {s.label}
                    </span>
                    <b className="tabular-nums text-foreground">{s.value}</b>
                  </li>
                ))}
              </ul>
            </>
          )}
        </section>
      </div>

      <div className="grid gap-4 lg:grid-cols-2">
        <Bars
          title={d("purposes")}
          rows={data.purposes}
          empty={d("purposes_empty")}
          color="var(--series-1)"
        />
        <Bars
          title={d("locations")}
          rows={data.locations}
          empty={d("locations_empty")}
          color="var(--series-3)"
        />
      </div>

      <PassTable
        title={d("overdue_title", { count: data.overdue.length })}
        hint={d("overdue_hint")}
        rows={data.overdue}
        cols={[
          "gp_no",
          "out_time",
          "expected_return_day",
          "destination",
          "taken_by",
          "balance",
        ]}
        empty={d("overdue_empty")}
      />
      <PassTable
        title={d("latest")}
        rows={data.latest}
        cols={[
          "gp_no",
          "date",
          "category",
          "type",
          "purpose",
          "destination",
          "taken_by",
          "items",
          "qty",
          "status",
        ]}
        empty={d("latest_empty")}
      />
    </div>
  );
}

export { GpDashboard };
