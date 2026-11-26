"use client";

import { useState, useTransition } from "react";
import {
  ArrowDownToLine,
  ArrowUpFromLine,
  Boxes,
  ClipboardList,
  Hourglass,
  Undo2,
} from "lucide-react";
import { cn } from "@/lib/utils";
import { StatusDonut } from "@/components/dashboard/status-donut";
import { useT } from "@/lib/i18n";
import { card, day, qty, StoreFilters } from "./common";

/*
 * Store & Material → Dashboard (AnnotechERP 09/01), as Brickland draws it:
 * the filter row (buyer → PO → style → material, dates); six tiles — PR
 * quantity, in-house, pending, material out, return, current stock — each
 * saying "mixed units" until one material is picked; the material movement
 * with its running balance; and stock by material type.
 */

const TILES = [
  ["pr_qty", ClipboardList, "border-t-info", "bg-info-subtle text-info"],
  [
    "in_house",
    ArrowDownToLine,
    "border-t-success",
    "bg-success-subtle text-success",
  ],
  ["pending", Hourglass, "border-t-warning", "bg-warning-subtle text-warning"],
  ["out", ArrowUpFromLine, "border-t-danger", "bg-danger-subtle text-danger"],
  ["returned", Undo2, "border-t-brand", "bg-brand-subtle text-brand"],
  ["stock", Boxes, "border-t-info", "bg-info-subtle text-info"],
];

/**
 * Dashboard.
 *
 * @param {{ data: object, filters: object, act: (op: string, ...args: any[]) => Promise<object> }} props
 */
function StoreDashboard({ data, filters, act }) {
  const t = useT("storematerial");
  const d = (k, p) => t(`dash.${k}`, p);
  const [state, setState] = useState(data);
  const [pending, startTransition] = useTransition();
  const mixed = state.units.length !== 1;
  const unit = mixed ? "" : ` ${state.units[0]}`;
  const total = state.by_kind.FABRIC + state.by_kind.TRIMS;
  const pct = (v) => (total ? Math.round((v / total) * 100) : 0);

  function search(f) {
    startTransition(async () => {
      const result = await act("dashboard", f);
      if (result?.status === "success") setState(result.data);
    });
  }

  return (
    <div className="flex flex-col gap-4">
      <StoreFilters
        fields={["buyer", "po", "style", "material", "from", "to"]}
        options={filters}
        onSearch={search}
        pending={pending}
      />
      <section className="grid grid-cols-2 gap-3 md:grid-cols-3 xl:grid-cols-6">
        {TILES.map(([k, Icon, border, tone]) => (
          <div
            key={k}
            className={cn(card, "flex flex-col gap-2 border-t-4 p-4", border)}
          >
            <div className="flex items-start justify-between gap-2">
              <span className="text-[13px] font-medium text-foreground">
                {d(`tile.${k}`)}
              </span>
              <span
                className={cn(
                  "flex size-8 items-center justify-center rounded-md",
                  tone,
                )}
              >
                <Icon className="size-4" aria-hidden />
              </span>
            </div>
            <span className="text-2xl font-semibold tabular-nums text-foreground">
              {qty(state.tiles[k])}
              {unit}
            </span>
            {mixed ? (
              <span className="text-xs text-foreground-muted">
                {d("mixed")}
              </span>
            ) : null}
          </div>
        ))}
      </section>
      <div className="grid gap-4 xl:grid-cols-[minmax(0,2fr)_minmax(0,1fr)]">
        <section className={cn(card, "min-w-0 overflow-hidden")}>
          <h2 className="p-5 text-base font-semibold text-foreground">
            {d("movement")}
          </h2>
          <div className="overflow-x-auto">
            <table className="w-full min-w-[720px] text-sm">
              <thead className="bg-brand text-brand-foreground">
                <tr>
                  {[
                    "date",
                    "pr_no",
                    "grn_no",
                    "reference",
                    "in",
                    "out",
                    "returned",
                    "balance",
                  ].map((k) => (
                    <th
                      key={k}
                      className={cn(
                        "px-3 py-2.5 text-left text-xs font-semibold uppercase",
                        ["in", "out", "returned", "balance"].includes(k) &&
                          "text-right",
                      )}
                    >
                      {d(`col.${k}`)}
                    </th>
                  ))}
                </tr>
              </thead>
              <tbody>
                {state.movements.length === 0 ? (
                  <tr>
                    <td
                      colSpan={8}
                      className="px-3 py-10 text-center text-foreground-muted"
                    >
                      {d("no_movement")}
                    </td>
                  </tr>
                ) : null}
                {state.movements.map((m, i) => (
                  <tr
                    key={`${m.reference}-${i}`}
                    className="border-t border-border"
                  >
                    <td className="px-3 py-2.5 whitespace-nowrap">
                      {day(m.date)}
                    </td>
                    <td className="px-3 py-2.5 whitespace-nowrap">
                      {m.pr_no ?? "—"}
                    </td>
                    <td className="px-3 py-2.5 font-semibold whitespace-nowrap">
                      {m.reference}
                    </td>
                    <td className="px-3 py-2.5">{t(`type.${m.type}`)}</td>
                    <td className="px-3 py-2.5 text-right tabular-nums">
                      {qty(m.in)}
                    </td>
                    <td className="px-3 py-2.5 text-right tabular-nums">
                      {qty(m.out)}
                    </td>
                    <td className="px-3 py-2.5 text-right tabular-nums">
                      {qty(m.returned)}
                    </td>
                    <td className="px-3 py-2.5 text-right font-semibold tabular-nums">
                      {qty(m.balance)}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </section>
        <section className={cn(card, "flex flex-col gap-4 p-5")}>
          <h2 className="text-base font-semibold text-foreground">
            {d("by_kind")}
          </h2>
          <StatusDonut
            segments={[
              {
                key: "FABRIC",
                label: t("kind.FABRIC"),
                value: state.by_kind.FABRIC,
                color: "var(--series-1)",
              },
              {
                key: "TRIMS",
                label: t("kind.TRIMS"),
                value: state.by_kind.TRIMS,
                color: "var(--series-3)",
              },
            ]}
            total={qty(total)}
            totalLabel={d("total_stock")}
          />
          <ul className="flex flex-col gap-2 text-sm">
            {[
              ["FABRIC", "var(--series-1)"],
              ["TRIMS", "var(--series-3)"],
            ].map(([k, color]) => (
              <li key={k} className="flex items-center justify-between gap-2">
                <span className="flex items-center gap-2 text-foreground">
                  <span
                    className="size-2.5 rounded-full"
                    style={{ background: color }}
                    aria-hidden
                  />
                  {t(`kind.${k}`)}
                </span>
                <span className="font-semibold tabular-nums text-foreground">
                  {pct(state.by_kind[k])}%
                </span>
              </li>
            ))}
          </ul>
        </section>
      </div>
    </div>
  );
}

export { StoreDashboard };
