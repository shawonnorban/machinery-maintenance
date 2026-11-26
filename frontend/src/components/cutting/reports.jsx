"use client";

import { useState, useTransition } from "react";
import { Printer, Search } from "lucide-react";
import { cn } from "@/lib/utils";
import { Input } from "@/components/ui/input";
import { Button } from "@/components/ui/button";
import { useToastManager } from "@/components/ui/toast";
import { RecordsGrid } from "@/components/erp/records-grid";
import { useT } from "@/lib/i18n";
import { printDocument } from "@/lib/print-document";

/*
 * Cutting Floor → Reports (AnnotechERP 19/04), as Brickland lays them out:
 * 1 Filter (dates, PO, style, buyer, colour, size, serial, status) and Show;
 * 2 the report — every bundle, or the bundles counted per PO, style or size —
 * with Total Bundle and Total Qty under it.
 */

const firstError = (res) => (res?.errors ? Object.values(res.errors).flat()[0] : res?.message);
const dmy = (d) => (d ? d.split("-").reverse().join("-") : "—");
const STATUSES = ["CREATED", "PRINTED", "REPRINTED", "CANCELLED"];
const nativeSelect = "h-9 w-full rounded-md border border-border bg-surface px-3 text-sm outline-none focus:border-brand";

/** @param {{ kind: "bundle"|"po"|"style"|"size", initial: object, load: Function, title: string }} props */
function CutReport({ kind, initial, load, title }) {
  const t = useT("cutting");
  const toastManager = useToastManager();
  const [f, setF] = useState({ from: "", to: "", po: "", style: "", buyer: "", colour: "", size: "", serial: "", status: kind === "bundle" ? "ALL" : "ALL_BUT_CANCELLED" });
  const [data, setData] = useState(initial);
  const [pending, startTransition] = useTransition();
  const set = (k) => (e) => setF((x) => ({ ...x, [k]: e.target.value }));

  function show() {
    startTransition(async () => {
      const res = await load(kind, f);
      if (res?.status === "success") setData(res.data);
      else toastManager.add({ title: firstError(res), type: "danger" });
    });
  }

  const columns = {
    bundle: [
      { key: "sl", label: t("col.sl"), type: "number" },
      { key: "bundle_no", label: t("col.bundle_no"), type: "mono" },
      { key: "po_no", label: t("rpt.po") },
      { key: "style", label: t("rpt.style") },
      { key: "buyer", label: t("rpt.buyer") },
      { key: "colour", label: t("rpt.colour") },
      { key: "size", label: t("rpt.size") },
      { key: "serial_from", label: t("col.serial_from"), type: "number", align: "right" },
      { key: "serial_to", label: t("col.serial_to"), type: "number", align: "right" },
      { key: "qty", label: t("col.qty"), type: "number", align: "right" },
      { key: "status_label", label: t("col.status"), type: "badge" },
      { key: "print_date", label: t("col.print_date") },
    ],
    po: [
      { key: "po_no", label: t("rpt.po") },
      { key: "style", label: t("rpt.style") },
      { key: "bundles", label: t("rpt.total_bundle"), type: "number", align: "right" },
      { key: "qty", label: t("rpt.total_qty"), type: "number", align: "right" },
    ],
    style: [
      { key: "style", label: t("rpt.style") },
      { key: "bundles", label: t("rpt.total_bundle"), type: "number", align: "right" },
      { key: "qty", label: t("rpt.total_qty"), type: "number", align: "right" },
    ],
    size: [
      { key: "size", label: t("rpt.size") },
      { key: "bundles", label: t("rpt.total_bundle"), type: "number", align: "right" },
      { key: "qty", label: t("rpt.total_qty"), type: "number", align: "right" },
    ],
  }[kind];
  const rows = data.rows.map((r, i) => ({
    ...r,
    _k: i,
    sl: i + 1,
    status_label: r.status ? t(`status.${r.status}`) : undefined,
    print_date: r.printed_at ? new Date(r.printed_at).toLocaleString("en-GB", { timeZone: "Asia/Dhaka", day: "2-digit", month: "2-digit", year: "numeric", hour: "2-digit", minute: "2-digit", hourCycle: "h12" }).replace(/\b[ap]m\b/, (x) => x.toUpperCase()).replaceAll("/", "-").replace(",", "") : r.cut_date ? "—" : undefined,
    cut_date: dmy(r.cut_date),
  }));
  const head = (n, label) => (
    <header className="flex items-center gap-2.5 bg-brand px-4 py-2.5 text-white">
      <span className="flex size-6 items-center justify-center rounded-full bg-white text-xs font-bold text-foreground">{n}</span>
      <h2 className="text-[15px] font-semibold">{label}</h2>
    </header>
  );
  const box = (k, type = "text") => (
    <label key={k} className="flex flex-col gap-1.5">
      <span className="text-[13px] font-semibold">{t(`rpt.${k}`)}</span>
      <Input type={type} value={f[k]} onChange={set(k)} onKeyDown={(e) => e.key === "Enter" && show()} aria-label={t(`rpt.${k}`)} />
    </label>
  );

  return (
    <div className="flex flex-col gap-4">
      <section className="overflow-hidden rounded-lg border border-border bg-surface">
        {head(1, t("rpt.filter"))}
        <div className="grid gap-3 p-4 sm:grid-cols-2 lg:grid-cols-5">
          {box("from", "date")}
          {box("to", "date")}
          {box("po")}
          {box("style")}
          {box("buyer")}
          {box("colour")}
          {box("size")}
          {box("serial", "number")}
          <label className="flex flex-col gap-1.5">
            <span className="text-[13px] font-semibold">{t("rpt.status")}</span>
            <select value={f.status} onChange={set("status")} className={nativeSelect} aria-label={t("rpt.status")}>
              <option value={kind === "bundle" ? "ALL" : "ALL_BUT_CANCELLED"}>{kind === "bundle" ? t("rpt.all") : t("rpt.all_but_cancelled")}</option>
              {STATUSES.map((s) => (
                <option key={s} value={s}>
                  {t(`status.${s}`)}
                </option>
              ))}
            </select>
          </label>
          <div className="flex items-end">
            <Button onClick={show} loading={pending} className="w-full">
              <Search /> {t("rpt.show")}
            </Button>
          </div>
        </div>
      </section>
      <section className="overflow-hidden rounded-lg border border-border bg-surface">
        {head(2, title)}
        <div className="flex flex-col gap-3 p-4">
          <RecordsGrid exportName={`cutting-${kind}-report`} rows={rows} rowKey="_k" columns={columns} emptyState={{ title: t("rpt.empty"), text: t("rpt.empty_hint") }} />
          <p className={cn("text-right text-sm font-semibold tabular-nums")}>{t("rpt.totals", { bundles: data.total_bundles.toLocaleString("en-US"), qty: data.total_qty.toLocaleString("en-US") })}</p>
        </div>
      </section>
    </div>
  );
}

const CUT_STATUSES = ["ANY_ADDITIONAL", "ADDITIONAL", "PARTIAL_ADDITIONAL"];
const pctLabel = (v, na) => (v === null || v === undefined ? na : `${Number(v).toFixed(2)}%`);
const int = (v) => Number(v ?? 0).toLocaleString("en-US");

/**
 * Cutting Floor → Reports → Additional Cutting List (AnnotechERP 19/04), as
 * Brickland lays it out: 1 Filter (dates — the last 30 days at first — PO,
 * style, colour, cutting, bundle, size, batch and Additional + Partial /
 * Additional / Partial Additional) with Show and Print; 2 per PO / style /
 * colour the buyer's order, the total cut, the additional and its percentage;
 * 3 every additional card, with "Bundles · Additional Cutting" under it.
 * @param {{ initial: object, load: Function }} props
 */
function AdditionalCuttingList({ initial, load }) {
  const t = useT("cutting");
  const toastManager = useToastManager();
  const [f, setF] = useState({ from: initial.from, to: initial.to, po: "", style: "", colour: "", cutting_no: "", bundle_no: "", size: "", batch_no: "", status: "ANY_ADDITIONAL" });
  const [data, setData] = useState(initial);
  const [pending, startTransition] = useTransition();
  const set = (k) => (e) => setF((x) => ({ ...x, [k]: e.target.value }));
  const na = t("acl.na");

  function show() {
    startTransition(async () => {
      const res = await load(f);
      if (res?.status === "success") setData(res.data);
      else toastManager.add({ title: firstError(res), type: "danger" });
    });
  }

  const statusTone = { ADDITIONAL: "text-danger", PARTIAL_ADDITIONAL: "text-warning" };
  const groups = data.groups.map((r, i) => ({ ...r, _k: i, sl: i + 1, pct: pctLabel(r.additional_pct, na) }));
  const bundles = data.bundles.map((r, i) => ({ ...r, sl: i + 1, date: dmy(r.cut_date), status_label: t(`cut_status.${r.cutting_status}`), pct: pctLabel(r.additional_pct, na) }));
  const summary = t("acl.summary", { bundles: int(data.total_bundles), qty: int(data.total_additional) });

  function printReport() {
    const cols = [
      ["sl", t("col.sl")], ["date", t("col.date")], ["po_no", t("col.po")], ["style", t("col.style")], ["colour", t("col.colour")], ["cutting_no", t("col.cutting_no"), true],
      ["bundle_no", t("col.bundle_no")], ["size", t("col.size")], ["batch_no", t("col.batch")], ["qty", t("col.qty"), true], ["status_label", t("col.cutting_status")],
      ["additional_qty", t("acl.additional_qty"), true], ["order_qty", t("col.order_qty"), true], ["total_cut", t("acl.total_cut"), true], ["total_additional", t("acl.total_additional"), true], ["pct", t("acl.additional_pct"), true],
    ];
    printDocument({
      title: t("screens.additional-cutting-list"),
      subtitle: t("acl.print_sub", { from: data.from, to: data.to, bundles: int(data.total_bundles), qty: int(data.total_additional) }),
      orientation: "landscape",
      sections: [{ columns: cols.map(([, label, right]) => ({ label, align: right ? "right" : "left" })), rows: bundles.map((r) => cols.map(([k, , right]) => (right && typeof r[k] === "number" ? int(r[k]) : r[k] ?? ""))), empty: t("acl.empty") }],
    });
  }

  const head = (n, label, action) => (
    <header className="flex items-center gap-2.5 bg-brand px-4 py-2.5 text-white">
      <span className="flex size-6 items-center justify-center rounded-full bg-white text-xs font-bold text-foreground">{n}</span>
      <h2 className="flex-1 text-[15px] font-semibold">{label}</h2>
      {action}
    </header>
  );
  const box = (k, label, type = "text") => (
    <label key={k} className="flex flex-col gap-1.5">
      <span className="text-[13px] font-semibold">{label}</span>
      <Input type={type} value={f[k]} onChange={set(k)} onKeyDown={(e) => e.key === "Enter" && show()} aria-label={label} />
    </label>
  );

  return (
    <div className="flex flex-col gap-4">
      <section className="overflow-hidden rounded-lg border border-border bg-surface">
        {head(1, t("rpt.filter"))}
        <div className="grid gap-3 p-4 sm:grid-cols-2 lg:grid-cols-6">
          {box("from", t("rpt.from"), "date")}
          {box("to", t("rpt.to"), "date")}
          {box("po", t("col.po"))}
          {box("style", t("rpt.style"))}
          {box("colour", t("rpt.colour"))}
          {box("cutting_no", t("acl.cutting_no"), "number")}
          {box("bundle_no", t("acl.bundle_no"))}
          {box("size", t("rpt.size"))}
          {box("batch_no", t("acl.batch_no"))}
          <label className="flex flex-col gap-1.5">
            <span className="text-[13px] font-semibold">{t("acl.cutting_status")}</span>
            <select value={f.status} onChange={set("status")} className={nativeSelect} aria-label={t("acl.cutting_status")}>
              {CUT_STATUSES.map((s) => (
                <option key={s} value={s}>
                  {s === "ANY_ADDITIONAL" ? t("acl.any") : t(`cut_status.${s}`)}
                </option>
              ))}
            </select>
          </label>
          <div className="flex items-end gap-2">
            <Button onClick={show} loading={pending} className="flex-1">
              <Search /> {t("rpt.show")}
            </Button>
            <Button variant="outline" onClick={printReport} disabled={pending || bundles.length === 0} aria-label={t("acl.print")} title={t("acl.print")}>
              <Printer />
            </Button>
          </div>
        </div>
      </section>
      <section className="overflow-hidden rounded-lg border border-border bg-surface">
        {head(2, t("acl.by_group"))}
        <div className="p-4">
          <RecordsGrid
            exportName="additional-cutting-by-po"
            rows={groups}
            rowKey="_k"
            emptyState={{ title: t("acl.empty"), text: t("acl.empty_hint") }}
            columns={[
              { key: "sl", label: t("col.sl"), type: "number" },
              { key: "po_no", label: t("col.po"), render: (r) => <b>{r.po_no}</b>, read: (r) => r.po_no },
              { key: "style", label: t("col.style") },
              { key: "buyer", label: t("col.buyer") },
              { key: "colour", label: t("col.colour") },
              { key: "order_qty", label: t("acl.order_qty"), type: "number", align: "right" },
              { key: "cut", label: t("acl.total_cut"), type: "number", align: "right" },
              { key: "additional", label: t("acl.additional_qty"), align: "right", render: (r) => <b className="text-warning tabular-nums">{int(r.additional)}</b>, read: (r) => r.additional },
              { key: "pct", label: t("acl.additional_pct"), align: "right" },
            ]}
          />
        </div>
      </section>
      <section className="overflow-hidden rounded-lg border border-border bg-surface">
        {head(3, t("acl.bundles"))}
        <div className="flex flex-col gap-3 p-4">
          <RecordsGrid
            exportName="additional-cutting-bundles"
            rows={bundles}
            rowKey="bundle_no"
            emptyState={{ title: t("acl.empty"), text: t("acl.empty_hint") }}
            columns={[
              { key: "sl", label: t("col.sl"), type: "number" },
              { key: "date", label: t("col.date") },
              { key: "po_no", label: t("col.po") },
              { key: "style", label: t("col.style") },
              { key: "colour", label: t("col.colour") },
              { key: "cutting_no", label: t("col.cutting_no"), type: "number", align: "right" },
              { key: "bundle_no", label: t("col.bundle_no"), type: "mono" },
              { key: "size", label: t("col.size") },
              { key: "parts_name", label: t("col.parts") },
              { key: "batch_no", label: t("col.batch") },
              { key: "qty", label: t("col.qty"), type: "number", align: "right" },
              { key: "status_label", label: t("col.cutting_status"), render: (r) => <span className={cn("font-semibold", statusTone[r.cutting_status])}>{r.status_label}</span>, read: (r) => r.status_label },
              { key: "additional_qty", label: t("acl.additional_qty"), type: "number", align: "right" },
              { key: "order_qty", label: t("acl.order_qty"), type: "number", align: "right" },
              { key: "total_cut", label: t("acl.total_cut"), type: "number", align: "right" },
              { key: "pct", label: t("acl.additional_pct"), align: "right" },
            ]}
          />
          <p className="text-right text-sm font-semibold tabular-nums">{summary}</p>
        </div>
      </section>
    </div>
  );
}

export { CutReport, AdditionalCuttingList };
