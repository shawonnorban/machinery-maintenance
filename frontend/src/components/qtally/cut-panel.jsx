"use client";

import { useEffect, useState } from "react";
import { ArrowLeft, Eye, Layers, Printer, Scissors } from "lucide-react";
import { useLocale } from "next-intl";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { useToastManager } from "@/components/ui/toast";
import { printDocument } from "@/lib/print-document";
import { useQt, useFilters, num, pct, day, Panel, Table, Field, FilterCard, Kpi, Facts } from "./common";

/*
 * Quality Tally → Tally → Cut Panel Inspection (user, 2026-11-25): the
 * report of the cutting tally — QCs tap every panel (OK or Reject with its
 * codes) on the tablet, and THOSE taps are the inspection. One report per
 * lot (day, cutting table, job, cutting no, batch); opened in place it is the
 * "100% Cut Panel Inspection Report": bundle by bundle, panel by panel, each
 * code's count and the reject; printed on A4 landscape like the paper sheet.
 */

function CutPanelInspection({ data, form, query, act }) {
  const p = useQt();
  const { apply, pending } = useFilters(query);
  const [from, setFrom] = useState(query.from ?? data.from);
  const [to, setTo] = useState(query.to ?? data.to);
  const [search, setSearch] = useState(query.search ?? "");
  const [open, setOpen] = useState(null);
  const toast = useToastManager();
  const t = data.rows.reduce((a, r) => ({ checked: a.checked + r.checked, reject: a.reject + r.reject }), { checked: 0, reject: 0 });

  const print = async (key) => {
    const res = await act("panelLot", key);
    if (res?.status !== "success") return toast.add({ title: res?.message ?? p("error"), type: "danger" });
    printReport(res.data, form.defects, p);
  };

  if (open) return <LotSheet lotKey={open} defects={form.defects} act={act} onClose={() => setOpen(null)} onPrint={print} />;

  return (
    <>
      <FilterCard onApply={() => apply({ from, to, search })} pending={pending} applyLabel={p("apply")}>
        <Field label={p("f.from")}>
          <Input type="date" value={from} onChange={(e) => setFrom(e.target.value)} className="w-40" />
        </Field>
        <Field label={p("f.to")}>
          <Input type="date" value={to} onChange={(e) => setTo(e.target.value)} className="w-40" />
        </Field>
        <Field label={p("f.search")} className="min-w-56 flex-1">
          <Input value={search} onChange={(e) => setSearch(e.target.value)} placeholder={p("cpi.search_hint")} />
        </Field>
      </FilterCard>
      <div className="mb-4 grid grid-cols-2 gap-3 md:grid-cols-4">
        <Kpi label={p("cpi.reports")} value={num(data.rows.length)} tone="brand" />
        <Kpi label={p("cpi.checked_panels")} value={num(t.checked)} tone="info" />
        <Kpi label={p("cpi.reject_panels")} value={num(t.reject)} tone="danger" />
        <Kpi label={p("cpi.reject_pct")} value={pct(t.checked ? Math.round((t.reject / t.checked) * 10000) / 100 : null)} tone="warning" />
      </div>
      <Panel title={p("cpi.title")} subtitle={p("cpi.from_taps", { n: data.rows.length, from: day(data.from), to: day(data.to) })} icon={Scissors}>
        <Table
          rows={data.rows}
          rowKey={(r) => Object.values(r.key).join("|")}
          empty={p("empty.cpi")}
          columns={[
            { key: "date", label: p("f.date"), render: (r) => day(r.date) },
            { key: "line", label: p("col.cutting_table") },
            { key: "job", label: p("col.job"), render: (r) => (<><b>{r.job_no}</b><div className="text-xs text-foreground-muted">{[r.buyer, r.style, r.color].filter(Boolean).join(" · ")}</div></>) },
            { key: "garment_type", label: p("col.garment_type"), render: (r) => r.garment_type || "—" },
            { key: "cutting_no", label: p("col.cutting_no"), render: (r) => r.cutting_no || "—" },
            { key: "batch_no", label: p("col.batch_no"), render: (r) => r.batch_no || "—" },
            { key: "cut_qty", label: p("f.cut_qty"), align: "right", render: (r) => num(r.cut_qty) },
            { key: "bundles", label: p("col.bundles"), align: "right", render: (r) => num(r.bundles) },
            { key: "checked", label: p("col.panel_check"), align: "right", render: (r) => num(r.checked) },
            { key: "reject", label: p("col.reject_panel"), align: "right", render: (r) => <b className="text-danger">{num(r.reject)}</b> },
            { key: "reject_pct", label: p("cpi.reject_pct"), align: "right", render: (r) => pct(r.reject_pct) },
            {
              key: "act",
              label: "",
              render: (r) => (
                <div className="flex gap-1.5">
                  <Button size="sm" variant="outline" onClick={() => setOpen(r.key)}>
                    <Eye /> {p("btn.open")}
                  </Button>
                  <Button size="sm" variant="outline" onClick={() => print(r.key)} aria-label={p("btn.print")} title={p("btn.print")}>
                    <Printer />
                  </Button>
                </div>
              ),
            },
          ]}
        />
      </Panel>
    </>
  );
}

function LotSheet({ lotKey, defects, act, onClose, onPrint }) {
  const p = useQt();
  const bn = useLocale() === "bn";
  const [d, setD] = useState(null);

  useEffect(() => {
    let live = true;
    act("panelLot", lotKey).then((res) => live && setD(res?.status === "success" ? res.data : { error: res?.message }));
    return () => {
      live = false;
    };
  }, [act, lotKey]);

  if (!d) return <p className="py-10 text-center text-sm text-foreground-muted">{p("loading")}</p>;
  if (d.error) return <p className="py-10 text-center text-sm text-danger">{d.error}</p>;
  // Only the codes this lot uses get a column on screen; the print keeps all of them, as the paper does.
  const used = defects.filter((x) => d.totals.by_defect[x.id]);
  const cols = used.length ? used : defects.slice(0, 0);

  return (
    <div className="flex flex-col gap-4">
      <div className="flex flex-wrap items-center gap-2">
        <Button variant="outline" onClick={onClose}>
          <ArrowLeft /> {p("btn.back_to_list")}
        </Button>
        <Button variant="outline" onClick={() => onPrint(lotKey)}>
          <Printer /> {p("btn.print")}
        </Button>
      </div>
      <Facts
        items={[
          [p("col.buyer"), d.buyer], [p("f.job"), d.job_no], [p("col.style"), d.style], [p("col.color"), d.color],
          [p("col.cutting_no"), d.cutting_no || "—"], [p("col.batch_no"), d.batch_no || "—"], [p("f.cut_qty"), num(d.cut_qty)], [p("f.date"), day(d.date)],
          [p("col.cutting_table"), d.line], [p("col.garment_type"), d.garment_type || "—"], [p("cpi.checked_panels"), num(d.totals.checked)],
          [p("cpi.reject_panels"), `${num(d.totals.reject)} (${pct(d.totals.reject_pct)})`],
        ]}
      />
      <Panel title={p("cpi.bundles")} subtitle={p("cpi.sheet_hint", { qcs: d.qcs.map((q) => `${q.name} (${q.taps})`).join(", ") || "—" })} icon={Layers}>
        <Table
          dense
          rowKey={(r) => r.sl}
          rows={d.rows}
          columns={[
            { key: "sl", label: p("col.sl"), align: "center" },
            { key: "bundle_no", label: p("col.bundle_no"), render: (r) => <b>{r.bundle_no}</b> },
            { key: "bundle_serial", label: p("col.bundle_serial"), render: (r) => r.bundle_serial || "—" },
            { key: "bundle_qty", label: p("col.bundle_qty"), align: "right", render: (r) => num(r.bundle_qty) },
            { key: "size", label: p("col.size"), render: (r) => r.size || "—" },
            { key: "panel", label: p("col.panel") },
            { key: "checked", label: p("col.panel_check"), align: "right", render: (r) => num(r.checked) },
            ...cols.map((x) => ({ key: x.id, label: <span title={bn && x.name_bn ? x.name_bn : x.name}>{x.code}</span>, align: "center", render: (r) => r.defects[x.id] ?? "" })),
            { key: "rectify", label: p("action.RECTIFY"), align: "right", render: (r) => num(r.rectify) },
            { key: "reject", label: p("col.reject_panel"), align: "right", render: (r) => <b className={r.reject ? "text-danger" : ""}>{num(r.reject)}</b> },
          ]}
          foot={{ sl: p("total"), checked: num(d.totals.checked), rectify: num(d.totals.rectify), reject: num(d.totals.reject), ...Object.fromEntries(cols.map((x) => [x.id, d.totals.by_defect[x.id] ?? ""])) }}
        />
        {cols.length ? <p className="border-t border-border px-5 py-2.5 text-xs text-foreground-muted">{cols.map((x) => `${x.code} = ${bn && x.name_bn ? x.name_bn : x.name}`).join(" · ")}</p> : null}
      </Panel>
    </div>
  );
}

/** The paper sheet: header facts, one row per bundle and panel with every defect code as a column, totals, signatures. */
function printReport(d, defects, p) {
  printDocument({
    title: p("cpi.print_title"),
    docNo: [d.cutting_no ? `${p("col.cutting_no")} ${d.cutting_no}` : null, day(d.date)].filter(Boolean).join(" · "),
    orientation: "landscape",
    meta: [
      [p("col.buyer"), d.buyer], [p("f.job"), d.job_no], [p("col.style"), d.style], [p("col.color"), d.color],
      [p("col.cutting_no"), d.cutting_no || "—"], [p("col.batch_no"), d.batch_no || "—"], [p("f.cut_qty"), d.cut_qty ?? "—"], [p("f.date"), d.date],
      [p("col.cutting_table"), d.line], [p("col.garment_type"), d.garment_type || "—"], [p("cpi.checked_panels"), d.totals.checked], [p("cpi.reject_panels"), `${d.totals.reject} (${d.totals.reject_pct ?? 0}%)`],
    ],
    metaColumns: 4,
    sections: [
      {
        dense: true,
        columns: [
          { label: p("col.sl"), align: "center" }, { label: p("col.bundle_no"), nowrap: true }, { label: p("col.bundle_serial"), nowrap: true }, { label: p("col.bundle_qty"), align: "right" },
          { label: p("col.size") }, { label: p("col.panel"), nowrap: true }, { label: p("col.panel_check"), align: "right" },
          ...defects.map((x) => ({ label: x.code, align: "center" })), { label: p("action.RECTIFY"), align: "right" }, { label: p("col.reject_panel"), align: "right" },
        ],
        rows: d.rows.map((r) => [r.sl, r.bundle_no, r.bundle_serial ?? "", r.bundle_qty ?? "", r.size ?? "", r.panel, r.checked, ...defects.map((x) => r.defects[x.id] ?? " "), r.rectify || " ", { v: r.reject, bold: true }]),
        foot: [[p("total"), "", "", "", "", "", d.totals.checked, ...defects.map((x) => d.totals.by_defect[x.id] ?? ""), d.totals.rectify || "", d.totals.reject]],
      },
    ],
    notes: [defects.map((x) => `${x.code} = ${x.name}`).join(" · "), `${p("sheet.inspectors")}: ${d.qcs.map((q) => `${q.name} (${q.taps})`).join(", ")}`],
    signatures: [p("sheet.sign_qc"), p("sheet.sign_qc_incharge"), p("sheet.sign_manager")],
  });
}

export { CutPanelInspection };
