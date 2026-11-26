"use client";

import { useMemo, useState, useTransition } from "react";
import { Layers, Plus, RotateCcw, Save, Search, Trash2 } from "lucide-react";
import { cn } from "@/lib/utils";
import { Input } from "@/components/ui/input";
import { Select } from "@/components/ui/select";
import { Textarea } from "@/components/ui/textarea";
import { Checkbox } from "@/components/ui/checkbox";
import { Button } from "@/components/ui/button";
import { ConfirmDialog } from "@/components/ui/confirm-dialog";
import { Alert } from "@/components/ui/alert";
import { useToastManager } from "@/components/ui/toast";
import { useT } from "@/lib/i18n";

// An error's field messages, or its message when it names no field.
const messagesOf = (result) => {
  const fields = [...new Set(Object.values(result?.errors ?? {}).flat())];
  return fields.length ? fields : [result?.message ?? ""];
};
const str = (v) => (v === null || v === undefined ? "" : String(v));
const todayIso = () => new Intl.DateTimeFormat("en-CA", { timeZone: "Asia/Dhaka" }).format(new Date());
const dateFmt = new Intl.DateTimeFormat("en-GB", { day: "numeric", month: "short", year: "numeric", timeZone: "UTC" });
const day = (v) => (v ? dateFmt.format(new Date(`${v}T00:00:00Z`)) : "—");
const num = (v) => (v === "" || v === null || v === undefined ? 0 : Number(v));
const money = new Intl.NumberFormat("en-US", { minimumFractionDigits: 2, maximumFractionDigits: 2 });
const qtyFmt = new Intl.NumberFormat("en-US", { maximumFractionDigits: 4 });
const PAGE = 10;
// Brickland's defaults: a local order, FOB, by LC, in taka at 1, no deductions.
const blankHead = (date) => ({
  supplier_id: "", po_date: date, source: "LOCAL", indent_id: "", beneficiary_company_id: "", billed_to_company_id: "", delivery_store_id: "", is_online: false, same_as_company: false, po_group: "",
  inco_term: "FOB", place_of_delivery: "", payment_method: "LC", currency: "BDT", conv_rate: "1", payment_term: "", mode_of_shipment: "", delivery_date: "", cash_supplier: "", other_term: "", remarks: "",
  discount_pct: "0", discount_amount: "0", vat_pct: "0", tds_pct: "0", vds_pct: "0", adjustment: "0", adjustment_note: "",
});
const headOf = (data, date) => Object.fromEntries(Object.entries(blankHead(date)).map(([k, v]) => [k, typeof v === "boolean" ? Boolean(data?.[k]) : data?.[k] === null || data?.[k] === undefined ? v : str(data[k])]));
let seq = 0;
const asLine = (l = {}) => ({ key: `q${++seq}`, ...l, qty: str(l.qty), rate: str(l.rate), remarks: str(l.remarks) });
const asCost = (c = {}) => ({ key: `k${++seq}`, cost_head_id: str(c.cost_head_id), amount: str(c.amount), remarks: str(c.remarks) });
const BILL = ["po_value", "other_cost", "vat", "tds", "vds", "net_amount"];

/**
 * Purchased Fabric Purchase Order → Fabric Purchase Order Entry (AnnotechERP
 * 06/06), as Brickland lays it out: the Approved PR List on the left
 * (supplier, search, ten to a page) — picking one adds what it has left open
 * to the details — and on the right Master Data, Terms & Condition, Cost, Tax
 * & Adjustment and the PO Amount (recomputed by the server on save), then
 * Details Data (each line ordering an approved indent line, "Add to PO" a
 * blank line picking one) and Other Cost. Each change is a new revision; an
 * order received against is read only. Nothing reloads.
 *
 * @param {{ options: object, initial: ?object, prefill: ?object, canManage: boolean, actions: object }} props
 */
function PfabPoEntry({ options, initial, prefill, canManage, actions }) {
  const t = useT("merchandise");
  const a = (k, p) => t(`master.apo.${k}`, p);
  const f = (k, p) => t(`master.fpo.${k}`, p);
  const r = (k, p) => t(`master.mrr.${k}`, p);
  const toastManager = useToastManager();
  const [today] = useState(todayIso);
  const [po, setPo] = useState(initial);
  const [head, setHead] = useState(() => (initial ? headOf(initial, today) : { ...blankHead(today), ...(prefill ? { indent_id: prefill.indent_id, supplier_id: str(prefill.supplier_id), delivery_date: str(prefill.delivery_date) } : {}) }));
  const [lines, setLines] = useState(() => (initial?.lines ?? prefill?.lines ?? []).map(asLine));
  const [costs, setCosts] = useState(() => (initial?.costs ?? []).map(asCost));
  const [errors, setErrors] = useState(null);
  const [supplierFilter, setSupplierFilter] = useState("");
  const [query, setQuery] = useState("");
  const [page, setPage] = useState(0);
  const [confirm, setConfirm] = useState(false);
  const [pending, startTransition] = useTransition();
  const editable = canManage && (!po || po.editable);
  const setH = (patch) => setHead((h) => ({ ...h, ...patch }));
  const setLine = (key, patch) => setLines((all) => all.map((l) => (l.key === key ? { ...l, ...patch } : l)));
  const setCost = (key, patch) => setCosts((all) => all.map((c) => (c.key === key ? { ...c, ...patch } : c)));
  const pick = (list) => [{ value: "", label: a("select") }, ...list];
  const coded = (group, values) => values.map((v) => ({ value: v, label: a(`${group}.${v}`) }));
  const plain = (values) => values.map((v) => ({ value: v, label: v }));
  const nameOf = (list, id) => list.find((x) => x.value === id)?.label ?? "—";
  const shown = useMemo(() => {
    const needle = query.trim().toLowerCase();
    return options.approved.filter((p) => (!supplierFilter || p.supplier_id === supplierFilter) && (!needle || `${p.req_no} ${p.order_no ?? ""}`.toLowerCase().includes(needle)));
  }, [options.approved, supplierFilter, query]);
  const pages = Math.max(1, Math.ceil(shown.length / PAGE));
  const openLines = options.open_lines.filter((o) => !lines.some((l) => l.indent_line_id === o.indent_line_id));
  const total = lines.reduce((n, l) => n + num(l.qty) * num(l.rate), 0);

  function addFrom(reqNo) {
    startTransition(async () => {
      const result = await actions.fromIndent(reqNo);
      if (result?.status !== "success") return setErrors(messagesOf(result));
      const d = result.data;
      setLines((all) => [...all, ...d.lines.filter((x) => !all.some((l) => l.indent_line_id === x.indent_line_id)).map(asLine)]);
      setHead((h) => ({ ...h, indent_id: h.indent_id || d.indent_id, supplier_id: h.supplier_id || str(d.supplier_id), delivery_date: h.delivery_date || str(d.delivery_date) }));
      setErrors(null);
    });
  }

  function take(data) {
    setPo(data);
    setHead(headOf(data, today));
    setLines(data.lines.map(asLine));
    setCosts(data.costs.map(asCost));
    setErrors(null);
    window.history.replaceState(null, "", `?tab=entry&ref=${encodeURIComponent(data.po_no)}`);
  }

  function clear() {
    setPo(null);
    setHead(blankHead(today));
    setLines([]);
    setCosts([]);
    setErrors(null);
    window.history.replaceState(null, "", "?tab=entry");
  }

  function load(no) {
    if (!no) return clear();
    startTransition(async () => {
      const result = await actions.load(no);
      if (result?.status !== "success") return setErrors(messagesOf(result));
      take(result.data);
    });
  }

  function save() {
    startTransition(async () => {
      const blank = (v) => (v === "" ? null : v);
      const number = (v) => (v === "" ? null : Number(v));
      const payload = {
        ...Object.fromEntries(Object.entries(head).map(([k, v]) => [k, typeof v === "boolean" ? v : blank(v)])),
        ...Object.fromEntries(["conv_rate", "discount_pct", "discount_amount", "vat_pct", "tds_pct", "vds_pct", "adjustment"].map((k) => [k, number(head[k])])),
        lines: lines.map((l) => ({ indent_line_id: l.indent_line_id ?? null, qty: number(l.qty), rate: number(l.rate), remarks: blank(l.remarks) })),
        costs: costs.map((c) => ({ cost_head_id: blank(c.cost_head_id), amount: number(c.amount), remarks: blank(c.remarks) })),
      };
      const result = await actions.save(po?.po_no ?? null, payload);
      if (result?.status !== "success") return setErrors(messagesOf(result));
      take(result.data);
      toastManager.add({ title: po ? a("updated", { no: result.data.po_no, rev: result.data.revision }) : a("saved", { no: result.data.po_no }), type: "success" });
    });
  }

  function remove() {
    startTransition(async () => {
      const result = await actions.remove(po.po_no);
      setConfirm(false);
      if (result?.status !== "success") return setErrors(messagesOf(result));
      toastManager.add({ title: a("deleted", { no: po.po_no }), type: "success" });
      clear();
    });
  }

  const card = "rounded-xl border border-border bg-surface";
  const group = "rounded-lg border border-border p-4";
  const field = "flex flex-col gap-1.5";
  const lab = "text-[13px] font-semibold text-foreground";
  const hint = "text-xs text-foreground-muted";
  const star = <span className="text-danger">*</span>;
  const sel = (key, label, list, required, extra = {}) => (
    <div className={field}>
      <span className={lab}>
        {label}
        {required ? star : null}
      </span>
      <Select value={head[key]} onValueChange={(v) => setH({ [key]: v ?? "", ...(extra.onPick ? extra.onPick(v) : {}) })} options={pick(list)} placeholder={a("select")} disabled={!editable || extra.disabled} aria-label={label} />
      {extra.hint ? <span className={hint}>{extra.hint}</span> : null}
    </div>
  );
  const txt = (key, label, extra = {}) => (
    <label className={cn(field, extra.span)}>
      <span className={lab}>
        {label}
        {extra.required ? star : null}
      </span>
      {extra.area ? (
        <Textarea rows={3} value={head[key]} onChange={(e) => setH({ [key]: e.target.value })} maxLength={2000} disabled={!editable} aria-label={label} />
      ) : (
        <Input type={extra.type ?? "text"} step={extra.type === "number" ? "any" : undefined} value={head[key]} onChange={(e) => setH({ [key]: e.target.value })} disabled={!editable || extra.disabled} aria-label={label} />
      )}
      {extra.hint ? <span className={hint}>{extra.hint}</span> : null}
    </label>
  );
  return (
    <div className="grid gap-4 xl:grid-cols-[22rem_1fr]">
      <section className={cn(card, "self-start p-4")}>
        <h2 className="text-base font-semibold text-foreground">{f("approved_pr")}</h2>
        <div className="mt-3 flex flex-col gap-1.5">
          <span className={lab}>{f("f.supplier_filter")}</span>
          <Select value={supplierFilter} onValueChange={(v) => (setSupplierFilter(v ?? ""), setPage(0))} options={[{ value: "", label: f("any_supplier") }, ...options.suppliers]} aria-label={f("f.supplier_filter")} />
        </div>
        <div className="relative mt-3">
          <Search className="pointer-events-none absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-foreground-muted" />
          <Input value={query} onChange={(e) => (setQuery(e.target.value), setPage(0))} placeholder={f("search_pr")} className="pl-8" aria-label={f("search_pr")} />
        </div>
        <div className="mt-3 overflow-hidden rounded-lg border border-border">
          <table className="w-full text-sm">
            <thead className="bg-brand text-brand-foreground">
              <tr>
                {["pr_no", "req_date", "order"].map((k) => (
                  <th key={k} className="px-3 py-2 text-left text-[13px] font-semibold">
                    {f(`col.${k}`)}
                  </th>
                ))}
              </tr>
            </thead>
            <tbody>
              {shown.slice(page * PAGE, page * PAGE + PAGE).map((p) => (
                <tr key={p.req_no} className="border-t border-border">
                  <td className="px-3 py-2">
                    <button type="button" className="text-left font-medium text-brand hover:underline disabled:text-foreground-muted" onClick={() => addFrom(p.req_no)} disabled={!editable}>
                      {p.req_no}
                    </button>
                  </td>
                  <td className="px-3 py-2 whitespace-nowrap">{day(p.req_date)}</td>
                  <td className="px-3 py-2">{p.order_no ?? "—"}</td>
                </tr>
              ))}
              {shown.length === 0 ? (
                <tr>
                  <td colSpan={3} className="px-3 py-6 text-center text-foreground-muted">
                    {f("nothing_left")}
                  </td>
                </tr>
              ) : null}
            </tbody>
          </table>
        </div>
        <div className="mt-2 flex items-center justify-end gap-1 text-xs text-foreground-muted">
          <Button variant="outline" size="sm" onClick={() => setPage((p) => Math.max(0, p - 1))} disabled={page === 0} aria-label="Previous page">
            ‹
          </Button>
          {page + 1} / {pages}
          <Button variant="outline" size="sm" onClick={() => setPage((p) => Math.min(pages - 1, p + 1))} disabled={page >= pages - 1} aria-label="Next page">
            ›
          </Button>
        </div>
      </section>

      <div className="flex flex-col gap-4">
        <section className={cn(card, "p-5")}>
          <div className="flex items-start justify-between gap-3">
            <div>
              <h2 className="flex items-center gap-2 text-base font-semibold text-foreground">
                <Layers className="size-4" /> {f("entry_tab")}
              </h2>
              <p className="mt-0.5 text-[13px] text-foreground-muted">{f("entry_hint")}</p>
            </div>
            <span className="rounded-md bg-brand-subtle px-2 py-0.5 text-xs font-medium text-brand">{po ? (po.editable ? a("editing", { n: po.revision }) : a("read_only")) : a("new")}</span>
          </div>
          <div className={cn(field, "mt-4")}>
            <span className={lab}>{a("existing")}</span>
            <Select value={po?.po_no ?? ""} onValueChange={(v) => load(v ?? "")} options={[{ value: "", label: a("new_po") }, ...options.orders]} aria-label={a("existing")} />
            <span className={hint}>{a("existing_hint")}</span>
          </div>

          <fieldset className={cn(group, "mt-4")}>
            <legend className="px-1 text-[13px] font-semibold text-foreground">{a("master_data")}</legend>
            <div className="grid gap-4 md:grid-cols-3">
              {sel("supplier_id", a("f.supplier_id"), options.suppliers, true)}
              <div className={field}>
                <span className={lab}>{a("f.po_no")}</span>
                <Input value={po?.po_no ?? ""} placeholder={a("auto_gen")} readOnly className="bg-surface-muted" aria-label={a("f.po_no")} />
              </div>
              {txt("po_date", a("f.po_date"), { type: "date", required: true })}
              {sel("source", a("f.source"), coded("source", options.sources), true)}
              {sel("indent_id", a("f.requisition_id"), options.indents, false, { hint: options.indents.length ? undefined : r("no_records") })}
              {sel("beneficiary_company_id", a("f.beneficiary"), options.companies, false)}
              {sel("billed_to_company_id", a("f.billed_to"), options.companies, false, { disabled: head.same_as_company })}
              {sel("delivery_store_id", a("f.delivery_to"), options.stores, false)}
              <label className="flex items-center gap-2 self-end pb-2 text-[13px] font-semibold text-foreground">
                <Checkbox checked={head.is_online} onCheckedChange={(c) => setH({ is_online: c === true })} disabled={!editable} aria-label={a("f.is_online")} /> {a("f.is_online")}
              </label>
              <label className="flex items-start gap-2 md:col-span-2">
                <Checkbox checked={head.same_as_company} onCheckedChange={(c) => setH({ same_as_company: c === true })} disabled={!editable} aria-label={a("f.same_as_company")} className="mt-0.5" />
                <span>
                  <span className="text-[13px] font-semibold text-foreground">{a("f.same_as_company")}</span>
                  <span className={cn(hint, "block")}>{a("same_hint")}</span>
                </span>
              </label>
              {txt("po_group", f("f.po_group"))}
            </div>
          </fieldset>

          <fieldset className={cn(group, "mt-4")}>
            <legend className="px-1 text-[13px] font-semibold text-foreground">{a("terms")}</legend>
            <div className="grid gap-4 md:grid-cols-3">
              {sel("inco_term", a("f.inco_term"), plain(options.inco_terms), false)}
              {txt("place_of_delivery", a("f.place_of_delivery"))}
              {sel("payment_method", a("f.payment_method"), coded("method", options.payment_methods), true)}
              {sel("currency", a("f.currency"), plain(options.currencies), true, { onPick: (v) => (v === "BDT" ? { conv_rate: "1" } : {}) })}
              {txt("conv_rate", a("f.conv_rate"), { type: "number", disabled: head.currency === "BDT", hint: a("conv_hint") })}
              {sel("payment_term", a("f.payment_term"), coded("term", options.payment_terms), true)}
              {sel("mode_of_shipment", a("f.mode_of_shipment"), coded("ship", options.shipment_modes), true)}
              {txt("delivery_date", a("f.delivery_date"), { type: "date", required: true })}
              {txt("cash_supplier", a("f.cash_supplier"), { hint: a("cash_hint") })}
              {txt("other_term", a("f.other_term"), { area: true, span: "md:col-span-3" })}
              {txt("remarks", a("f.remarks"), { area: true, span: "md:col-span-3" })}
            </div>
          </fieldset>

          <div className="mt-4 grid gap-4 lg:grid-cols-2">
            <fieldset className={group}>
              <legend className="px-1 text-[13px] font-semibold text-foreground">{f("cost_tax")}</legend>
              <div className="grid gap-4 sm:grid-cols-2">
                {txt("discount_pct", r("f.discount_pct"), { type: "number", hint: r("discount_hint") })}
                {txt("discount_amount", r("f.discount_amount"), { type: "number", disabled: num(head.discount_pct) > 0 })}
                {txt("vat_pct", r("f.vat_pct"), { type: "number" })}
                {txt("tds_pct", r("f.tds_pct"), { type: "number", hint: r("tds_hint") })}
                {txt("vds_pct", r("f.vds_pct"), { type: "number", hint: r("vds_hint") })}
                {txt("adjustment", f("f.adjustment"), { type: "number", hint: f("adjustment_hint") })}
                {txt("adjustment_note", f("f.adjustment_note"), { span: "sm:col-span-2" })}
              </div>
            </fieldset>
            <fieldset className={group}>
              <legend className="px-1 text-[13px] font-semibold text-foreground">{f("po_amount")}</legend>
              <div className="grid gap-4 sm:grid-cols-2">
                {BILL.map((k) => (
                  <div key={k} className={field}>
                    <span className={lab}>{k === "po_value" ? f("f.po_value") : k === "net_amount" ? f("f.net_amount") : r(`f.${k}`)}</span>
                    <Input value={po ? money.format(po[k]) : ""} placeholder={r("on_save")} readOnly className={cn("bg-surface-muted text-right tabular-nums", k === "net_amount" && "font-semibold")} aria-label={k === "po_value" ? f("f.po_value") : k === "net_amount" ? f("f.net_amount") : r(`f.${k}`)} />
                  </div>
                ))}
              </div>
            </fieldset>
          </div>
        </section>

        <section className={cn(card, "overflow-hidden")}>
          <div className="flex items-start justify-between gap-3 px-5 py-4">
            <div>
              <h2 className="text-base font-semibold text-foreground">{a("details")}</h2>
              <p className="mt-0.5 text-[13px] text-foreground-muted">{a("details_hint")}</p>
            </div>
            {editable ? (
              <Button variant="outline" size="sm" onClick={() => setLines((all) => [...all, asLine({ indent_line_id: null })])}>
                <Plus /> {a("add_to_po")}
              </Button>
            ) : null}
          </div>
          <div className="overflow-x-auto">
            <table className="w-full text-sm">
              <thead className="bg-brand text-brand-foreground">
                <tr>
                  {["pr_no", "fabric_type", "description", "construction", "composition", "colour", "gsm", "dia", "uom", "pr_qty", "po_qty", "rate", "amount", "remarks", "remove"].map((k) => (
                    <th key={k} className={cn("px-2 py-2.5 text-left text-[13px] font-semibold whitespace-nowrap", ["pr_qty", "po_qty", "rate", "amount"].includes(k) && "text-right")}>
                      {k === "remove" ? <span className="sr-only">{f("col.remove")}</span> : f(`col.${k}`)}
                    </th>
                  ))}
                </tr>
              </thead>
              <tbody>
                {lines.map((l, i) => (
                  <tr key={l.key} className="border-t border-border">
                    <td className="px-2 py-1.5">
                      {l.indent_line_id ? (
                        <span className="font-mono text-xs">{l.req_no}</span>
                      ) : (
                        <div className="w-64">
                          <Select
                            value=""
                            onValueChange={(v) => {
                              const o = options.open_lines.find((x) => x.indent_line_id === v);
                              if (o) setLine(l.key, { ...o, qty: str(o.qty), rate: "", remarks: str(o.remarks) });
                            }}
                            options={openLines.map((o) => ({ value: o.indent_line_id, label: `${o.req_no} · ${o.description ?? nameOf(options.fabric_types, o.fabric_type_id)}` }))}
                            placeholder={f("pick_line")}
                            disabled={!editable}
                            aria-label={f("row_line", { n: i + 1 })}
                          />
                        </div>
                      )}
                    </td>
                    <td className="px-2 py-1.5 whitespace-nowrap">{l.fabric_type_id ? nameOf(options.fabric_types, l.fabric_type_id) : "—"}</td>
                    <td className="px-2 py-1.5">{l.description ?? "—"}</td>
                    <td className="px-2 py-1.5">{l.construction ?? "—"}</td>
                    <td className="px-2 py-1.5 whitespace-nowrap">{l.composition_id ? nameOf(options.compositions, l.composition_id) : "—"}</td>
                    <td className="px-2 py-1.5">{l.colour_id ? nameOf(options.colours, l.colour_id) : "—"}</td>
                    <td className="px-2 py-1.5">{l.gsm ?? "—"}</td>
                    <td className="px-2 py-1.5">{l.dia ?? "—"}</td>
                    <td className="px-2 py-1.5">{l.uom ? t(`master.pfi.uom.${l.uom}`) : "—"}</td>
                    <td className="px-2 py-1.5 text-right tabular-nums">{l.indent_line_id ? qtyFmt.format(l.pr_qty ?? 0) : "—"}</td>
                    <td className="px-2 py-1.5">
                      <Input type="number" min="0" step="any" value={l.qty} onChange={(e) => setLine(l.key, { qty: e.target.value })} className="ml-auto h-8 w-24 text-right" disabled={!editable} aria-label={f("row_qty", { n: i + 1 })} />
                    </td>
                    <td className="px-2 py-1.5">
                      <Input type="number" min="0" step="any" value={l.rate} onChange={(e) => setLine(l.key, { rate: e.target.value })} className="ml-auto h-8 w-24 text-right" disabled={!editable} aria-label={f("row_rate", { n: i + 1 })} />
                    </td>
                    <td className="px-2 py-1.5 text-right tabular-nums">{money.format(num(l.qty) * num(l.rate))}</td>
                    <td className="px-2 py-1.5">
                      <Input value={l.remarks} onChange={(e) => setLine(l.key, { remarks: e.target.value })} maxLength={200} className="h-8 w-36" disabled={!editable} aria-label={f("row_remarks", { n: i + 1 })} />
                    </td>
                    <td className="px-2 py-1.5 text-right">
                      {editable ? (
                        <Button variant="ghost" size="icon" className="size-8" aria-label={a("row_remove")} onClick={() => setLines((all) => all.filter((x) => x.key !== l.key))}>
                          <Trash2 />
                        </Button>
                      ) : null}
                    </td>
                  </tr>
                ))}
                {lines.length === 0 ? (
                  <tr>
                    <td colSpan={15} className="px-5 py-8 text-center text-[13px] text-foreground-muted">
                      {a("no_lines")}
                    </td>
                  </tr>
                ) : null}
              </tbody>
            </table>
          </div>
          {lines.length ? (
            <div className="flex flex-wrap items-center gap-6 border-t border-border px-5 py-3 text-sm">
              <span className="font-semibold text-foreground">{f("total", { n: lines.length, qty: qtyFmt.format(lines.reduce((n, l) => n + num(l.qty), 0)) })}</span>
              <span className="font-semibold text-foreground tabular-nums">
                {money.format(total)} {head.currency}
              </span>
            </div>
          ) : null}
        </section>

        <section className={cn(card, "overflow-hidden")}>
          <div className="flex items-start justify-between gap-3 px-5 py-4">
            <div>
              <h2 className="text-base font-semibold text-foreground">{r("costs")}</h2>
              <p className="mt-0.5 text-[13px] text-foreground-muted">{f("costs_hint")}</p>
            </div>
            {editable ? (
              <Button variant="outline" size="sm" onClick={() => setCosts((all) => [...all, asCost()])}>
                <Plus /> {r("add_cost")}
              </Button>
            ) : null}
          </div>
          <div className="overflow-x-auto">
            <table className="w-full text-sm">
              <thead className="bg-brand text-brand-foreground">
                <tr>
                  {["cost_head", "vat", "amount", "remarks", "remove"].map((k) => (
                    <th key={k} className={cn("px-3 py-2.5 text-left text-[13px] font-semibold", k === "amount" && "text-right")}>
                      {k === "remove" ? <span className="sr-only">{f("col.remove")}</span> : f(`col.${k}`)}
                    </th>
                  ))}
                </tr>
              </thead>
              <tbody>
                {costs.map((c, i) => {
                  const h = options.cost_heads.find((x) => x.value === c.cost_head_id);
                  return (
                    <tr key={c.key} className="border-t border-border">
                      <td className="px-3 py-1.5">
                        <div className="w-64">
                          <Select value={c.cost_head_id} onValueChange={(v) => setCost(c.key, { cost_head_id: v ?? "" })} options={[{ value: "", label: "—" }, ...options.cost_heads.map(({ value, label }) => ({ value, label }))]} disabled={!editable} aria-label={r("cost_row_head", { n: i + 1 })} />
                        </div>
                      </td>
                      <td className="px-3 py-1.5">{h ? r(h.vat_applicable ? "yes" : "no") : "—"}</td>
                      <td className="px-3 py-1.5">
                        <Input type="number" min="0" step="any" value={c.amount} onChange={(e) => setCost(c.key, { amount: e.target.value })} className="ml-auto h-8 w-32 text-right" disabled={!editable} aria-label={r("cost_row_amount", { n: i + 1 })} />
                      </td>
                      <td className="px-3 py-1.5">
                        <Input value={c.remarks} onChange={(e) => setCost(c.key, { remarks: e.target.value })} maxLength={200} className="h-8 w-56" disabled={!editable} aria-label={r("cost_row_remarks", { n: i + 1 })} />
                      </td>
                      <td className="px-3 py-1.5 text-right">
                        {editable ? (
                          <Button variant="ghost" size="icon" className="size-8" aria-label={a("row_remove")} onClick={() => setCosts((all) => all.filter((x) => x.key !== c.key))}>
                            <Trash2 />
                          </Button>
                        ) : null}
                      </td>
                    </tr>
                  );
                })}
                {costs.length === 0 ? (
                  <tr>
                    <td colSpan={5} className="px-5 py-6 text-center text-[13px] text-foreground-muted">
                      {f("no_costs")}
                    </td>
                  </tr>
                ) : null}
              </tbody>
            </table>
          </div>
        </section>

        {errors ? <Alert variant="danger">{errors.join(" · ")}</Alert> : null}
        <section className={cn(card, "flex flex-wrap items-center justify-between gap-3 p-5")}>
          <p className="max-w-3xl text-[13px] text-foreground-muted">{f("rules")}</p>
          <div className="flex flex-wrap gap-2">
            {po && editable ? (
              <Button variant="outline" className="text-danger" onClick={() => setConfirm(true)} disabled={pending}>
                <Trash2 /> {a("delete")}
              </Button>
            ) : null}
            <Button variant="outline" onClick={clear} disabled={pending}>
              <RotateCcw /> {a("clear")}
            </Button>
            {editable ? (
              <Button onClick={save} loading={pending}>
                <Save /> {po ? a("save_changes") : a("save")}
              </Button>
            ) : null}
          </div>
        </section>
      </div>

      <ConfirmDialog open={confirm} onOpenChange={setConfirm} title={a("confirm_delete_title")} description={a("confirm_delete_hint")} confirmLabel={a("delete")} cancelLabel={a("cancel")} destructive onConfirm={remove} loading={pending} />
    </div>
  );
}

export { PfabPoEntry };
