"use client";

import { useEffect, useMemo, useState, useTransition } from "react";
import { Download, FileText, RotateCcw, Route } from "lucide-react";
import { cn } from "@/lib/utils";
import { Select } from "@/components/ui/select";
import { Input } from "@/components/ui/input";
import { Button } from "@/components/ui/button";
import { Checkbox } from "@/components/ui/checkbox";
import { RecordsGrid } from "@/components/erp/records-grid";
import { day, firstError, money, qty, useSs, Filters, Tiles } from "@/components/sales-shipment/shared";
import { ampm } from "@/lib/clock";

/*
 * Sales & Shipment → Report (AnnotechERP 21/07), as Brickland lays the three
 * screens out:
 *
 * - Sales & Shipment Reports: Selection of Parameters — the report list on
 *   the left; on the right every box in its fixed place (Company, Store,
 *   Item, Status, Supplier/Customer, Style, SO #, From / To Date), the ones
 *   the picked report does not read disabled; Export to Excel? and Preview;
 *   the report drawn below with its total row. The first report runs as the
 *   screen opens.
 * - Order-wise Tracking: the Order card (Sales Order, Load, Reset), the
 *   order's balances, and its timeline (?so=SO-… in the address, no reload).
 * - Notifications & Alerts: a tile per alert kind, the Alert / Severity
 *   filters and the live alert list.
 */

const BOXES = ["company", "store", "item", "status", "buyer", "style", "order", "from", "to"];
const OPTIONS = { company: "companies", store: "stores", item: "items", buyer: "buyers", style: "styles", order: "orders" };

function csv(name, head, rows) {
  const text = [head, ...rows].map((l) => l.map((v) => `"${String(v ?? "").replaceAll('"', '""')}"`).join(",")).join("\n");
  const link = document.createElement("a");
  link.href = URL.createObjectURL(new Blob([`﻿${text}`], { type: "text/csv;charset=utf-8" }));
  link.download = `${name}.csv`;
  link.click();
}

const cellText = (c, v) => {
  if (v === null || v === undefined || v === "") return "—";
  if (c.type === "date") return day(v);
  if (c.type === "money") return money(v);
  if (c.type === "qty" || c.type === "num") return qty(v);
  return ampm(String(v));
};

/* ───────────────────────── Sales & Shipment Reports ───────────────────────── */

function SalesReports({ options, act }) {
  const w = useSs();
  const [kind, setKind] = useState(options.reports[0]?.kind ?? "");
  const [q, setQ] = useState({ company: options.companies[0]?.value ?? "", from: `${options.today.slice(0, 8)}01`, to: options.today });
  const [excel, setExcel] = useState(false);
  const [data, setData] = useState(null);
  const [error, setError] = useState(null);
  const [pending, startTransition] = useTransition();
  const report = options.reports.find((r) => r.kind === kind);
  const reads = (p) => p === "company" || (report?.params ?? []).includes(p);
  const statusOpts = useMemo(() => options.statuses.map((s) => ({ value: s.value, label: `${w(`sr.skind.${s.kind}`)}: ${w(`sr.status.${s.value.replace(":", ".")}`)}` })), [options.statuses, w]);
  const orders = options.orders.filter((o) => !q.buyer || o.customer_id === q.buyer);
  const opts = (p) => (p === "status" ? statusOpts : p === "order" ? orders : options[OPTIONS[p]]);
  const label = (c) => w(`sr.col.${c.key}`);

  const run = (k = kind, download = excel) =>
    startTransition(async () => {
      const r = options.reports.find((x) => x.kind === k);
      const body = { kind: k, ...Object.fromEntries((r?.params ?? []).map((p) => [p, q[p] || null])) };
      const res = await act.write("POST", "/sales-shipment/reports/run", body);
      if (res?.status !== "success") {
        setData(null);
        return setError(firstError(res));
      }
      setError(null);
      setData(res.data);
      if (download) {
        const d = res.data;
        csv(
          `sales-shipment-${d.kind}`,
          d.columns.map(label),
          [...d.rows.map((row) => d.columns.map((c) => cellText(c, row[c.key]))), ...(Object.keys(d.totals).length ? [d.columns.map((c, i) => (c.key in d.totals ? cellText(c, d.totals[c.key]) : i === 0 ? w("sr.total") : ""))] : [])],
        );
      }
    });

  // Brickland runs the first report as the screen opens.
  useEffect(() => {
    if (kind) run(kind, false);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  return (
    <div className="flex flex-col gap-4">
      <section className="rounded-lg border border-border bg-surface p-5">
        <h2 className="text-[15px] font-semibold">{w("sr.title")}</h2>
        <p className="mb-4 text-[13px] text-foreground-muted">{w("sr.hint")}</p>
        <fieldset className="rounded-lg border border-border p-4">
          <legend className="px-1 text-[13px] font-semibold text-brand">{w("sr.legend")}</legend>
          <div className="grid gap-6 lg:grid-cols-[minmax(0,1.2fr)_minmax(0,1fr)]">
            <div className="max-h-[36rem] overflow-auto rounded-lg border border-border p-2" role="radiogroup" aria-label={w("sr.legend")}>
              {options.reports.map((r) => (
                <label key={r.kind} className={cn("flex cursor-pointer items-center gap-3 rounded-md px-3 py-1.5 text-sm hover:bg-surface-muted", r.kind === kind && "bg-brand-subtle font-semibold")}>
                  <input
                    type="radio"
                    name="sales-report"
                    className="size-4 accent-[var(--color-brand)]"
                    checked={r.kind === kind}
                    onChange={() => {
                      setKind(r.kind);
                      setData(null);
                      setError(null);
                    }}
                  />
                  {w(`sr.kind.${r.kind}`)}
                </label>
              ))}
            </div>
            <div className="flex flex-col gap-3">
              {BOXES.map((p) => (
                <label key={p} className="grid items-center gap-2 sm:grid-cols-[9rem_1fr]">
                  <span className={cn("text-[13px] font-semibold sm:text-right", !reads(p) && "text-foreground-muted")}>{w(`sr.p.${p}`)}</span>
                  {p === "from" || p === "to" ? (
                    <Input type="date" value={q[p] ?? ""} disabled={!reads(p)} onChange={(e) => setQ({ ...q, [p]: e.target.value })} aria-label={w(`sr.p.${p}`)} />
                  ) : (
                    <Select
                      value={q[p] ?? ""}
                      disabled={!reads(p)}
                      onValueChange={(v) => setQ({ ...q, [p]: v ?? "", ...(p === "buyer" ? { order: "" } : {}) })}
                      options={[...(p === "company" ? [] : [{ value: "", label: w("sr.select") }]), ...(opts(p) ?? [])]}
                      placeholder={w("sr.select")}
                      aria-label={w(`sr.p.${p}`)}
                    />
                  )}
                </label>
              ))}
              <label className="flex items-center gap-2 text-[13px] font-semibold sm:pl-[9.5rem]">
                <Checkbox checked={excel} onCheckedChange={(v) => setExcel(v === true)} aria-label={w("sr.excel")} /> {w("sr.excel")}
              </label>
              <div className="sm:pl-[9.5rem]">
                <Button className="w-full justify-center" onClick={() => run()} loading={pending} disabled={!kind}>
                  <FileText /> {w("sr.preview")}
                </Button>
              </div>
              {error ? (
                <p role="alert" className="text-sm text-danger sm:pl-[9.5rem]">
                  {error}
                </p>
              ) : null}
            </div>
          </div>
        </fieldset>
        <p className="mt-3 text-[13px] text-foreground-muted">{w("sr.note")}</p>
      </section>

      {data ? (
        <section className="rounded-lg border border-border bg-surface">
          <header className="flex flex-wrap items-center justify-between gap-2 border-b border-border px-5 py-3">
            <div>
              <h2 className="text-[15px] font-semibold">{w(`sr.kind.${data.kind}`)}</h2>
              <p className="text-[13px] text-foreground-muted">
                {(report?.params ?? [])
                  .filter((p) => q[p])
                  .map((p) => `${w(`sr.p.${p}`)}: ${p === "from" || p === "to" ? day(q[p]) : (opts(p)?.find((o) => o.value === q[p])?.label ?? q[p])}`)
                  .join(" · ") || w("sr.all")}
                {" · "}
                {w("sr.rows", { n: data.rows.length })}
              </p>
            </div>
            <Button size="sm" variant="outline" disabled={!data.rows.length} onClick={() => run(data.kind, true)}>
              <Download /> {w("sr.export")}
            </Button>
          </header>
          {data.rows.length === 0 ? (
            <p className="px-5 py-8 text-center text-sm text-foreground-muted">{w("sr.empty")}</p>
          ) : (
            <div className="max-h-[42rem] overflow-auto px-5 py-4">
              <table className="w-full border border-border text-sm">
                <thead className="sticky top-0 bg-brand text-xs text-brand-foreground">
                  <tr>
                    {data.columns.map((c) => (
                      <th key={c.key} scope="col" className={cn("border border-border px-2 py-2 text-left font-semibold whitespace-nowrap", c.numeric && "text-right")}>
                        {label(c)}
                      </th>
                    ))}
                  </tr>
                </thead>
                <tbody>
                  {data.rows.map((r, i) => (
                    <tr key={i} className="hover:bg-surface-muted">
                      {data.columns.map((c) => (
                        <td key={c.key} className={cn("border border-border px-2 py-1.5 whitespace-nowrap", c.numeric && "text-right tabular-nums", /_no$/.test(c.key) && "font-mono text-xs")}>
                          {cellText(c, r[c.key])}
                        </td>
                      ))}
                    </tr>
                  ))}
                </tbody>
                {Object.keys(data.totals).length ? (
                  <tfoot className="bg-surface-muted text-xs font-semibold">
                    <tr>
                      {data.columns.map((c, i) => (
                        <td key={c.key} className={cn("border border-border px-2 py-1.5", c.numeric && "text-right tabular-nums")}>
                          {c.key in data.totals ? cellText(c, data.totals[c.key]) : i === 0 ? w("sr.total") : ""}
                        </td>
                      ))}
                    </tr>
                  </tfoot>
                ) : null}
              </table>
            </div>
          )}
        </section>
      ) : null}
    </div>
  );
}

/* ───────────────────────── Order-wise Tracking ───────────────────────── */

function OrderTracking({ orders, initial, act }) {
  const w = useSs();
  const [so, setSo] = useState(initial?.order.so_no ?? "");
  const [data, setData] = useState(initial);
  const [error, setError] = useState(null);
  const [pending, startTransition] = useTransition();
  const address = (no) => {
    const url = new URL(window.location.href);
    if (no) url.searchParams.set("so", no);
    else url.searchParams.delete("so");
    window.history.replaceState(null, "", url);
  };
  const load = () => {
    if (!so) return setError(w("ot.pick_first"));
    startTransition(async () => {
      const res = await act.read(`/sales-shipment/order-tracking/${encodeURIComponent(so)}`);
      if (res?.status !== "success") return setError(firstError(res));
      setError(null);
      setData(res.data);
      address(so);
    });
  };
  const f = data?.figures;
  const rows = (data?.events ?? []).map((e, i) => ({ ...e, key: `${i}` }));

  return (
    <div className="flex flex-col gap-4">
      <section className="rounded-lg border border-border bg-surface p-5">
        <h3 className="text-[15px] font-semibold">{w("ot.filter")}</h3>
        <p className="mt-0.5 mb-3 text-[13px] text-foreground-muted">{w("ot.note")}</p>
        <form
          className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4"
          onSubmit={(e) => {
            e.preventDefault();
            load();
          }}
        >
          <label className="flex flex-col gap-1.5 sm:col-span-2">
            <span className="text-[13px] font-semibold">
              {w("ot.order")} <span className="text-danger">*</span>
            </span>
            <Select value={so} onValueChange={(v) => setSo(v ?? "")} options={orders} placeholder={w("od.ed.select")} aria-label={w("ot.order")} />
            <span className="text-xs text-foreground-muted">{w("ot.order_hint")}</span>
          </label>
          <div className="flex flex-wrap items-end gap-2 sm:col-span-2 lg:col-span-4">
            <Button type="submit" loading={pending}>
              <Route /> {w("ot.load")}
            </Button>
            <Button
              type="button"
              variant="outline"
              onClick={() => {
                setSo("");
                setData(null);
                setError(null);
                address(null);
              }}
            >
              <RotateCcw /> {w("ot.reset")}
            </Button>
          </div>
          {error ? (
            <p role="alert" className="text-sm text-danger sm:col-span-2 lg:col-span-4">
              {error}
            </p>
          ) : null}
        </form>
      </section>

      {data ? (
        <>
          <section className="rounded-lg border border-border bg-surface px-5 py-3">
            <h2 className="text-[15px] font-semibold">{w("ot.head", { no: data.order.so_no, customer: data.order.customer })}</h2>
            <p className="text-[13px] text-foreground-muted">
              {w("ot.dates", { receive: day(data.order.receive_date), target: day(data.order.target_date), ship: day(data.order.shipment_date) })}
              {" · "}
              {w(f.against_delivered ? "ot.against.on" : "ot.against.off")}
            </p>
          </section>
          <Tiles
            items={[
              [w("ot.fig.order_qty"), qty(f.order_qty)],
              [w("ot.fig.net_delivered"), qty(f.net_delivered), "accent"],
              [w("ot.fig.returned"), qty(f.returned), f.returned > 0 ? "warning" : "neutral"],
              [w("ot.fig.shipped"), qty(f.shipped), "good"],
              [w("ot.fig.delivery_balance"), qty(f.delivery_balance), f.delivery_balance > 0 ? "warning" : "good"],
              [w("ot.fig.shipment_balance"), qty(f.shipment_balance), f.shipment_balance > 0 ? "warning" : "good"],
              [w("ot.fig.available"), qty(f.available), "info"],
              ...(f.against_delivered ? [[w("ot.fig.balance"), qty(f.balance), f.balance > 0 ? "warning" : "good"]] : []),
            ]}
          />
          <RecordsGrid
            exportName={`order-timeline-${data.order.so_no}`}
            rows={rows}
            rowKey="key"
            emptyState={{ title: w("ot.empty"), text: "" }}
            columns={[
              { key: "date", label: w("ot.col.date"), type: "date" },
              { key: "stage", label: w("ot.col.stage"), type: "badge" },
              { key: "reference", label: w("ot.col.reference"), type: "mono" },
              { key: "qty", label: w("ot.col.qty"), type: "number", align: "right" },
              { key: "value", label: w("ot.col.value"), type: "number", align: "right" },
              { key: "status", label: w("ot.col.status") },
              { key: "detail", label: w("ot.col.detail") },
            ]}
          />
        </>
      ) : (
        <p className="rounded-lg border border-dashed border-border bg-surface px-5 py-8 text-center text-sm text-foreground-muted">{w("ot.pick")}</p>
      )}
    </div>
  );
}

/* ───────────────────────── Notifications & Alerts ───────────────────────── */

function SalesAlerts({ initial }) {
  const w = useSs();
  const [filter, setFilter] = useState({});
  const kinds = initial.summary.map((s) => s.kind);
  const daysText = (r) => (r.days === null ? "—" : r.kind === "CLOSING_PENDING" ? w("al.days.since", { n: Math.abs(r.days) }) : r.days === 0 ? w("al.days.today") : r.days > 0 ? w("al.days.left", { n: r.days }) : w("al.days.past", { n: -r.days }));
  const rows = initial.rows
    .filter((r) => (!filter.kind || r.kind === filter.kind) && (!filter.severity || r.severity === filter.severity))
    .map((r, i) => ({ ...r, key: `${r.kind}-${r.reference}-${i}`, alert: w(`al.kind.${r.kind}`), days_text: daysText(r), screen_label: w(`screens.${r.screen}`) }));

  return (
    <>
      <Tiles items={initial.summary.map((s) => [w(`al.kind.${s.kind}`), s.count, s.count === 0 ? "good" : s.critical > 0 ? "critical" : "warning"])} />
      <Filters
        w={w}
        note={w("al.note", { days: initial.windows.due, cert: initial.windows.cert })}
        value={filter}
        onChange={setFilter}
        fields={[
          { name: "kind", label: w("al.f.kind"), type: "select", options: kinds.map((k) => ({ value: k, label: w(`al.kind.${k}`) })) },
          { name: "severity", label: w("al.f.severity"), type: "select", options: ["critical", "warning"].map((s) => ({ value: s, label: w(`al.severity.${s}`) })) },
        ]}
      />
      <RecordsGrid
        exportName="sales-shipment-alerts"
        rows={rows}
        rowKey="key"
        emptyState={{ title: w("al.empty_title"), text: "" }}
        columns={[
          { key: "alert", label: w("al.col.alert"), type: "badge" },
          { key: "reference", label: w("al.col.reference"), type: "mono" },
          { key: "party", label: w("al.col.party") },
          { key: "due_date", label: w("al.col.due"), type: "date" },
          { key: "days_text", label: w("al.col.days"), align: "right" },
          { key: "detail", label: w("al.col.detail") },
          { key: "screen_label", label: w("al.col.screen") },
        ]}
      />
    </>
  );
}

export { SalesReports, OrderTracking, SalesAlerts };
