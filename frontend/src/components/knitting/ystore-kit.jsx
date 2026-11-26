"use client";

import { useMemo, useState } from "react";
import Link from "next/link";
import {
  Download,
  Filter,
  FilterX,
  Plus,
  RotateCcw,
  Search,
} from "lucide-react";
import { cn } from "@/lib/utils";
import { Input } from "@/components/ui/input";
import { Button, buttonVariants } from "@/components/ui/button";
import { Alert } from "@/components/ui/alert";
import { PSelect, useYarn, useYs, card, th, Field } from "./yarn-common";
import { ampm } from "@/lib/clock";

/*
 * Knit Production → Yarn Store (AnnotechERP 15/03): the register Brickland's
 * challan screens share — count chips, a date range and status (and any
 * further selects) above, a search box, a filter box under every column,
 * Export, and pages of 10 / 25 / 50 / 100. It filters what it was given;
 * `onRefresh` loads it again.
 */

function KitList({
  rows,
  columns,
  dateKey,
  statusOptions,
  selects = [],
  chips,
  actions,
  addHref,
  empty,
  exportName,
  error,
  onRefresh,
  pending,
}) {
  const y = useYarn();
  const s = useYs();
  const [from, setFrom] = useState("");
  const [to, setTo] = useState("");
  const [status, setStatus] = useState("");
  const [picked, setPicked] = useState({});
  const [q, setQ] = useState("");
  const [col, setCol] = useState({});
  const [per, setPer] = useState(10);
  const [page, setPage] = useState(0);
  const text = (c, r) => String(c.text ? c.text(r) : (r[c.key] ?? ""));
  const shown = useMemo(
    () =>
      rows.filter(
        (r) =>
          (!from || r[dateKey] >= from) &&
          (!to || r[dateKey] <= to) &&
          (!status || r.status === status) &&
          selects.every((x) => !picked[x.key] || r[x.key] === picked[x.key]) &&
          (!q ||
            columns.some((c) =>
              text(c, r).toLowerCase().includes(q.toLowerCase()),
            )) &&
          columns.every(
            (c) =>
              !col[c.key] ||
              text(c, r).toLowerCase().includes(col[c.key].toLowerCase()),
          ),
      ),
    [rows, from, to, status, picked, q, col, columns, dateKey, selects],
  );
  const pages = Math.max(1, Math.ceil(shown.length / per));
  const clear = () => {
    setFrom("");
    setTo("");
    setStatus("");
    setPicked({});
    setQ("");
    setCol({});
    setPage(0);
  };
  const at =
    (fn) =>
    (...a) => (fn(...a), setPage(0));

  function exportCsv() {
    const esc = (v) => `"${String(v ?? "").replaceAll('"', '""')}"`;
    const lines = [
      columns.map((c) => esc(c.label)).join(","),
      ...shown.map((r) => columns.map((c) => esc(text(c, r))).join(",")),
    ];
    const url = URL.createObjectURL(
      new Blob([lines.join("\n")], { type: "text/csv" }),
    );
    Object.assign(document.createElement("a"), {
      href: url,
      download: `${exportName}.csv`,
    }).click();
    URL.revokeObjectURL(url);
  }
  const addButton = addHref ? (
    <Link href={addHref} className={buttonVariants({ size: "sm" })}>
      <Plus /> {y("add_new")}
    </Link>
  ) : null;

  return (
    <section className={card}>
      <div className="flex flex-wrap gap-2 px-5 pt-5">
        {chips.map(([label, v]) => (
          <span
            key={label}
            className="inline-flex items-center gap-2 rounded-lg border border-border px-3 py-1.5 text-[13px]"
          >
            {label}
            <span className="rounded-full bg-brand-subtle px-2 text-xs font-semibold text-brand tabular-nums">
              {v}
            </span>
          </span>
        ))}
      </div>
      <div className="flex flex-wrap items-end gap-4 border-b border-border px-5 py-4">
        {selects.map((x) => (
          <Field key={x.key} label={x.label} className="w-52">
            <PSelect
              value={picked[x.key] ?? ""}
              onValueChange={at((v) =>
                setPicked((p) => ({ ...p, [x.key]: v ?? "" })),
              )}
              options={[{ value: "", label: s("all_plain") }, ...x.options]}
              aria-label={x.label}
            />
          </Field>
        ))}
        <Field label={s("kit.date")}>
          <div className="flex items-center gap-2">
            <Input
              type="date"
              value={from}
              onChange={(e) => at(setFrom)(e.target.value)}
              aria-label={s("from")}
            />
            <span className="text-sm text-foreground-muted">
              {s("to_word")}
            </span>
            <Input
              type="date"
              value={to}
              onChange={(e) => at(setTo)(e.target.value)}
              aria-label={s("to")}
            />
          </div>
        </Field>
        <Field label={s("kit.status")} className="w-44">
          <PSelect
            value={status}
            onValueChange={(v) => at(setStatus)(v ?? "")}
            options={[{ value: "", label: y("any") }, ...statusOptions]}
            aria-label={s("kit.status")}
          />
        </Field>
        <div className="ml-auto flex items-center gap-3 text-[13px] text-foreground-muted">
          {s("adj.shown", { shown: shown.length, total: rows.length })}
          <Button variant="outline" size="sm" onClick={clear}>
            <FilterX /> {y("clear")}
          </Button>
          {addButton}
        </div>
      </div>
      <div className="flex flex-wrap items-center justify-between gap-3 px-5 py-3">
        <div className="relative w-full max-w-xs">
          <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-foreground-muted" />
          <Input
            value={q}
            onChange={(e) => at(setQ)(e.target.value)}
            placeholder={s("adj.search_ph", { count: rows.length })}
            className="pl-9"
            aria-label={s("search")}
          />
        </div>
        <div className="flex gap-2">
          {addButton}
          <Button variant="outline" size="sm" onClick={exportCsv}>
            <Download /> {s("export")}
          </Button>
        </div>
      </div>
      {error ? (
        <div className="px-5 pb-3">
          <Alert variant="danger">{error.join(" · ")}</Alert>
        </div>
      ) : null}
      <div className="overflow-x-auto">
        <table className="w-full min-w-[1200px] text-sm">
          <thead className="bg-brand text-brand-foreground">
            <tr>
              {columns.map((c) => (
                <th key={c.key} className={cn(th, c.num && "text-right")}>
                  {c.label}
                </th>
              ))}
              <th className={cn(th, "text-right")}>{s("action_col")}</th>
            </tr>
            <tr className="bg-surface">
              {columns.map((c) => (
                <th key={c.key} className="px-2 py-1.5">
                  <div className="relative">
                    <Input
                      value={col[c.key] ?? ""}
                      onChange={(e) =>
                        at(setCol)((x) => ({ ...x, [c.key]: e.target.value }))
                      }
                      className="h-8 pr-7 text-foreground"
                      aria-label={s("kit.filter_by", { col: c.label })}
                    />
                    <Filter className="pointer-events-none absolute top-1/2 right-2 size-3.5 -translate-y-1/2 text-foreground-muted" />
                  </div>
                </th>
              ))}
              <th />
            </tr>
          </thead>
          <tbody>
            {shown.length === 0 ? (
              <tr>
                <td
                  colSpan={columns.length + 1}
                  className="px-3 py-10 text-center text-foreground-muted"
                >
                  {empty}
                </td>
              </tr>
            ) : (
              shown.slice(page * per, page * per + per).map((r, i) => (
                <tr key={i} className="border-t border-border">
                  {columns.map((c) => (
                    <td
                      key={c.key}
                      className={cn(
                        "px-2 py-2",
                        c.num && "text-right tabular-nums",
                        c.nowrap && "whitespace-nowrap",
                      )}
                    >
                      {c.render ? c.render(r) : (ampm(r[c.key]) ?? "—")}
                    </td>
                  ))}
                  <td className="px-2 py-1.5">
                    <div className="flex justify-end gap-1">{actions(r)}</div>
                  </td>
                </tr>
              ))
            )}
          </tbody>
        </table>
      </div>
      <div className="flex flex-wrap items-center justify-between gap-3 border-t border-border px-5 py-3 text-[13px] text-foreground-muted">
        <div className="flex items-center gap-2">
          <Button
            variant="outline"
            size="sm"
            onClick={() => setPage((p) => Math.max(0, p - 1))}
            disabled={page === 0}
            aria-label={y("prev")}
          >
            ‹
          </Button>
          {page + 1} / {pages}
          <Button
            variant="outline"
            size="sm"
            onClick={() => setPage((p) => Math.min(pages - 1, p + 1))}
            disabled={page >= pages - 1}
            aria-label={y("next")}
          >
            ›
          </Button>
          <div className="w-20">
            <PSelect
              value={String(per)}
              onValueChange={(v) => (setPer(Number(v ?? 10)), setPage(0))}
              options={[10, 25, 50, 100].map((n) => ({
                value: String(n),
                label: String(n),
              }))}
              aria-label={s("per_page")}
            />
          </div>
          {s("per_page")}
        </div>
        <div className="flex items-center gap-2">
          {s("items", { count: shown.length })}
          <Button
            variant="outline"
            size="icon"
            className="size-8"
            onClick={onRefresh}
            loading={pending}
            aria-label={s("refresh")}
          >
            <RotateCcw />
          </Button>
        </div>
      </div>
    </section>
  );
}

/** A store's lots as options: "100% Cotton 30/1 Natural · Lot SQ-3011 · 480.000 kg". */
const lotOptions = (lots, fmt) =>
  lots.map((x) => ({
    value: x.lot_id,
    label: `${x.label || x.yarn} · ${fmt.format(x.balance)} kg`,
  }));

export { KitList, lotOptions };
