"use client";

import { useState, useTransition } from "react";
import Link from "next/link";
import { Box, ClipboardList, Eye, Hourglass, Lock, Search } from "lucide-react";
import { cn } from "@/lib/utils";
import { buttonVariants, Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Modal } from "@/components/ui/modal";
import { useT } from "@/lib/i18n";
import { card, dmy, num, Pill } from "./plan-common";

/*
 * Capacity Planning → Projections (AnnotechERP 12/02), as Brickland lays it
 * out: the year and its months with how many confirmed orders ship in each;
 * the month's tiles — orders shipping, quantity, pending master plan, plan
 * locked; and the orders, each with where its plan stands, Open Plan or
 * Create Plan, and a quick look at the order. Nothing reloads.
 */

const YEARS = [2025, 2026, 2027, 2028];

/** @param {{ data: object, act: Function }} props */
function Projections({ data: initial, act }) {
  const t = useT("planning");
  const p = (k, x) => t(`plan.${k}`, x);
  const [data, setData] = useState(initial);
  const [q, setQ] = useState("");
  const [viewing, setViewing] = useState(null);
  const [pending, startTransition] = useTransition();

  const load = (year, month) =>
    startTransition(async () => {
      const res = await act("projections", { year, month });
      if (res?.status === "success") setData(res.data);
    });
  const view = (no) =>
    startTransition(async () => {
      const res = await act("order", no);
      if (res?.status === "success") setViewing(res.data.order);
    });
  const rows = data.rows.filter(
    (r) =>
      !q ||
      [r.order_no, r.buyer, r.po, r.style]
        .join(" ")
        .toLowerCase()
        .includes(q.toLowerCase()),
  );
  const tile = (label, value, Icon, tone) => (
    <div className={cn(card, "flex items-center gap-3 px-4 py-3")}>
      <span
        className={cn(
          "flex size-10 items-center justify-center rounded-lg",
          tone,
        )}
      >
        <Icon className="size-5" aria-hidden />
      </span>
      <div>
        <p className="text-xs font-semibold text-foreground-muted">{label}</p>
        <p className="text-lg font-semibold tabular-nums text-foreground">
          {value}
        </p>
      </div>
    </div>
  );

  return (
    <div className="grid gap-4 lg:grid-cols-[13rem_1fr]">
      <aside className={cn(card, "h-fit p-4")}>
        <label className="flex flex-col gap-1.5 text-[13px] font-semibold text-foreground">
          {p("proj.year")}
          <select
            value={data.year}
            onChange={(ev) => load(Number(ev.target.value), data.month)}
            className="h-10 rounded-lg border border-border-strong bg-surface px-3 text-sm"
            aria-label={p("proj.year")}
          >
            {YEARS.map((y) => (
              <option key={y} value={y}>
                {y}
              </option>
            ))}
          </select>
        </label>
        <ul className="mt-3 flex flex-col gap-0.5">
          {Array.from({ length: 12 }, (_, i) => i + 1).map((m) => (
            <li key={m}>
              <button
                type="button"
                onClick={() => load(data.year, m)}
                className={cn(
                  "flex w-full items-center justify-between rounded-md px-3 py-2 text-left text-sm hover:bg-surface-muted",
                  data.month === m &&
                    "bg-brand-subtle font-semibold text-brand",
                )}
              >
                {p(`months.${m}`)}
                {data.counts[m] ? (
                  <span className="rounded bg-warning-subtle px-1.5 text-xs text-warning">
                    {data.counts[m]}
                  </span>
                ) : null}
              </button>
            </li>
          ))}
        </ul>
      </aside>

      <div className="flex min-w-0 flex-col gap-4">
        <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
          {tile(
            p("proj.orders"),
            num(data.tiles.orders),
            Box,
            "bg-info-subtle text-info",
          )}
          {tile(
            p("proj.qty"),
            `${num(data.tiles.qty)} PCS`,
            ClipboardList,
            "bg-success-subtle text-success",
          )}
          {tile(
            p("proj.pending"),
            num(data.tiles.pending),
            Hourglass,
            "bg-warning-subtle text-warning",
          )}
          {tile(
            p("proj.locked"),
            num(data.tiles.locked),
            Lock,
            "bg-success-subtle text-success",
          )}
        </div>

        <section
          className={cn(
            card,
            "min-w-0 overflow-hidden",
            pending && "opacity-70",
          )}
        >
          <div className="flex flex-wrap items-start justify-between gap-3 p-5">
            <div>
              <h2 className="text-base font-semibold text-foreground">
                {p("proj.title", {
                  month: p(`months.${data.month}`),
                  year: data.year,
                })}
              </h2>
              <p className="text-[13px] text-foreground-muted">
                {p("proj.hint")}
              </p>
            </div>
            <div className="relative w-64">
              <Search
                className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-foreground-muted"
                aria-hidden
              />
              <Input
                value={q}
                onChange={(ev) => setQ(ev.target.value)}
                placeholder={p("proj.search")}
                className="pl-9"
                aria-label={p("proj.search")}
              />
            </div>
          </div>
          <div className="overflow-x-auto">
            <table className="w-full min-w-[900px] text-sm">
              <thead className="bg-brand text-brand-foreground">
                <tr>
                  {[
                    "order_no",
                    "buyer",
                    "po",
                    "style",
                    "qty",
                    "shipment_date",
                    "status",
                    "action",
                  ].map((k) => (
                    <th
                      key={k}
                      className={cn(
                        "px-3 py-2.5 text-left text-xs font-semibold uppercase",
                        k === "qty" && "text-right",
                        k === "action" && "text-right",
                      )}
                    >
                      {p(`col.${k}`)}
                    </th>
                  ))}
                </tr>
              </thead>
              <tbody>
                {rows.length === 0 ? (
                  <tr>
                    <td
                      colSpan={8}
                      className="px-3 py-10 text-center text-foreground-muted"
                    >
                      {p("proj.empty")}
                    </td>
                  </tr>
                ) : null}
                {rows.map((r) => (
                  <tr key={r.order_no} className="border-t border-border">
                    <td className="px-3 py-2.5 font-semibold whitespace-nowrap text-brand">
                      {r.order_no}
                    </td>
                    <td className="px-3 py-2.5">{r.buyer ?? "—"}</td>
                    <td className="px-3 py-2.5">{r.po ?? "—"}</td>
                    <td className="px-3 py-2.5">{r.style ?? "—"}</td>
                    <td className="px-3 py-2.5 text-right tabular-nums">
                      {num(r.qty)}
                    </td>
                    <td className="px-3 py-2.5 whitespace-nowrap">
                      {dmy(r.ship_date)}
                    </td>
                    <td className="px-3 py-2.5">
                      <Pill tone={r.status}>
                        {p(`status_long.${r.status}`)}
                      </Pill>
                      {r.plan_no ? (
                        <p className="mt-0.5 text-xs text-foreground-muted">
                          {r.plan_no} V{r.version}
                        </p>
                      ) : null}
                    </td>
                    <td className="px-3 py-2.5">
                      <div className="flex justify-end gap-2">
                        <Button
                          size="sm"
                          variant="outline"
                          onClick={() => view(r.order_no)}
                          aria-label={`${p("proj.view")} ${r.order_no}`}
                        >
                          <Eye className="size-4" aria-hidden />
                        </Button>
                        <Link
                          href={`/capacity-planning/master-plan?order=${encodeURIComponent(r.order_no)}`}
                          className={buttonVariants({
                            size: "sm",
                            variant:
                              r.status === "PENDING" ? "primary" : "outline",
                          })}
                        >
                          {p(
                            r.status === "PENDING"
                              ? "proj.create"
                              : "proj.open",
                          )}
                        </Link>
                      </div>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </section>
      </div>

      {viewing ? (
        <Modal
          open
          onOpenChange={(o) => !o && setViewing(null)}
          title={viewing.order_no}
          className="max-w-2xl"
        >
          <dl className="grid grid-cols-2 gap-x-6 gap-y-3 text-sm sm:grid-cols-3">
            {[
              ["buyer", viewing.buyer],
              ["po", viewing.po],
              ["style", viewing.style],
              ["order_qty", `${num(viewing.qty)} ${viewing.unit ?? ""}`],
              ["ship_date", dmy(viewing.ship_date)],
              [
                "smv",
                viewing.smv
                  ? `${num(viewing.smv)} ${p(`mp.src.${viewing.smv_source}`)}`
                  : "—",
              ],
              ["color", viewing.colors.join(", ") || "—"],
              ["size", viewing.sizes.join(", ") || "—"],
              [
                "tna",
                viewing.tna
                  ? p("mp.tna_some", { count: viewing.tna })
                  : p("mp.tna_none"),
              ],
            ].map(([k, v]) => (
              <div key={k}>
                <dt className="text-xs text-foreground-muted">
                  {p(`mp.${k}`)}
                </dt>
                <dd className="font-semibold text-foreground">{v ?? "—"}</dd>
              </div>
            ))}
          </dl>
        </Modal>
      ) : null}
    </div>
  );
}

export { Projections };
