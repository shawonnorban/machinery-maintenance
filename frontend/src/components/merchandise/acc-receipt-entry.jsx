"use client";

import { useState, useTransition } from "react";
import { CheckCheck, PackageCheck, Plus, RotateCcw, Save, Trash2 } from "lucide-react";
import { cn } from "@/lib/utils";
import { Input } from "@/components/ui/input";
import { Select } from "@/components/ui/select";
import { Textarea } from "@/components/ui/textarea";
import { Button } from "@/components/ui/button";
import { ConfirmDialog } from "@/components/ui/confirm-dialog";
import { Alert } from "@/components/ui/alert";
import { useToastManager } from "@/components/ui/toast";
import { useT } from "@/lib/i18n";
import { UomSelect } from "@/components/erp/uom-select";

// An error's field messages, or its message when it names no field.
const messagesOf = (result) => {
  const fields = [...new Set(Object.values(result?.errors ?? {}).flat())];
  return fields.length ? fields : [result?.message ?? ""];
};
const str = (v) => (v === null || v === undefined ? "" : String(v));
const todayIso = () => new Intl.DateTimeFormat("en-CA", { timeZone: "Asia/Dhaka" }).format(new Date());
// Brickland's defaults: a PO receipt, by LC, local, in taka at 1, no deductions.
const blankHead = (date, company) => ({
  unit_company_id: company, supplier_id: "", store_id: "", mrr_date: date, payment_method: "LC", source_type: "LOCAL", challan_no: "", challan_date: "", currency: "BDT", conv_rate: "1",
  po_id: "", requisition_id: "", requisition_type: "", remarks: "", discount_pct: "0", discount_amount: "0", vat_pct: "0", tds_pct: "0", vds_pct: "0", mrr_type: "PO",
});
const headOf = (data, date, company) => Object.fromEntries(Object.entries(blankHead(date, company)).map(([k, v]) => [k, data?.[k] === null || data?.[k] === undefined ? v : str(data[k])]));
let seq = 0;
const asLine = (l = {}) => ({ key: `r${++seq}`, item_id: str(l.item_id), po_line_id: l.po_line_id ?? null, po_no: l.po_no ?? null, description: str(l.description), batch: str(l.batch), uom: str(l.uom), qty: str(l.qty), rate: str(l.rate) });
const asCost = (c = {}) => ({ key: `c${++seq}`, cost_head_id: str(c.cost_head_id), amount: str(c.amount), remarks: str(c.remarks) });
const num = (v) => (v === "" || v === null || v === undefined ? 0 : Number(v));
const money = new Intl.NumberFormat("en-US", { minimumFractionDigits: 2, maximumFractionDigits: 2 });
const BILL = ["goods_value", "other_cost", "landed_cost", "vat_base", "vat", "tds", "vds", "net_payable"];

/**
 * Accessories Receipt Report → Receipt Entry (AnnotechERP 06/06), as Brickland
 * lays it out: the existing-receipt picker; Master Data; Discount, VAT &
 * Deductions at Source; the Bill Summary (recomputed by the server on save);
 * Received Items; and Other Cost, each head showing whether it is landed into
 * stock value and inside the VAT base. A purchase order taken from the
 * Receive List arrives with what is left of it. Save keeps a draft; Finalize
 * MRR locks it and puts its goods on stock. Nothing reloads.
 *
 * @param {{ options: object, initial: ?object, prefill: ?object, canManage: boolean, actions: object }} props
 */
function AccReceiptEntry({ options, initial, prefill, canManage, actions }) {
  const t = useT("merchandise");
  const m = (k, p) => t(`master.mrr.${k}`, p);
  const toastManager = useToastManager();
  const [today] = useState(todayIso);
  const company = options.companies[0]?.value ?? "";
  const [mrr, setMrr] = useState(initial);
  const [head, setHead] = useState(() => (initial ? headOf(initial, today, company) : prefill ? headOf({ ...prefill, mrr_type: "PO" }, today, company) : blankHead(today, company)));
  const [lines, setLines] = useState(() => (initial?.lines ?? prefill?.lines ?? []).map(asLine));
  const [costs, setCosts] = useState(() => (initial?.costs ?? []).map(asCost));
  const [errors, setErrors] = useState(null);
  const [confirm, setConfirm] = useState(null);
  const [pending, startTransition] = useTransition();
  const editable = canManage && (!mrr || mrr.editable);
  const setH = (patch) => setHead((h) => ({ ...h, ...patch }));
  const setLine = (key, patch) => setLines((all) => all.map((l) => (l.key === key ? { ...l, ...patch } : l)));
  const setCost = (key, patch) => setCosts((all) => all.map((c) => (c.key === key ? { ...c, ...patch } : c)));
  const pick = (list) => [{ value: "", label: m("select") }, ...list];
  const plain = (values) => values.map((v) => ({ value: v, label: v }));
  const coded = (prefix, values) => values.map((v) => ({ value: v, label: t(`${prefix}.${v}`) }));
  const orders = options.orders.filter((o) => !head.supplier_id || o.supplier_id === head.supplier_id);

  function take(data) {
    setMrr(data);
    setHead(headOf(data, today, company));
    setLines(data.lines.map(asLine));
    setCosts(data.costs.map(asCost));
    setErrors(null);
    window.history.replaceState(null, "", `?tab=entry&ref=${encodeURIComponent(data.mrr_no)}`);
  }

  function clear() {
    setMrr(null);
    setHead(blankHead(today, company));
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
      const payload = {
        ...Object.fromEntries(Object.entries(head).map(([k, v]) => [k, blank(v)])),
        ...Object.fromEntries(["conv_rate", "discount_pct", "discount_amount", "vat_pct", "tds_pct", "vds_pct"].map((k) => [k, head[k] === "" ? null : Number(head[k])])),
        lines: lines.map((l) => ({ item_id: blank(l.item_id), po_line_id: l.po_line_id, description: blank(l.description), batch: blank(l.batch), uom: blank(l.uom), qty: l.qty === "" ? null : Number(l.qty), rate: l.rate === "" ? null : Number(l.rate) })),
        costs: costs.map((c) => ({ cost_head_id: blank(c.cost_head_id), amount: c.amount === "" ? null : Number(c.amount), remarks: blank(c.remarks) })),
      };
      const result = await actions.save(mrr?.mrr_no ?? null, payload);
      if (result?.status !== "success") return setErrors(messagesOf(result));
      take(result.data);
      toastManager.add({ title: m(mrr ? "updated" : "saved", { no: result.data.mrr_no }), type: "success" });
    });
  }

  function run(kind) {
    startTransition(async () => {
      const result = kind === "delete" ? await actions.remove(mrr.mrr_no) : await actions.finalize(mrr.mrr_no);
      setConfirm(null);
      if (result?.status !== "success") return setErrors(messagesOf(result));
      toastManager.add({ title: m(kind === "delete" ? "deleted" : "finalized_toast", { no: mrr.mrr_no }), type: "success" });
      if (kind === "delete") clear();
      else take(result.data);
    });
  }

  const card = "rounded-xl border border-border bg-surface";
  const group = "rounded-lg border border-border p-4";
  const field = "flex flex-col gap-1.5";
  const lab = "text-[13px] font-semibold text-foreground";
  const hint = "text-xs text-foreground-muted";
  const star = <span className="text-danger">*</span>;
  const pctInput = (key) => (
    <label className={field}>
      <span className={lab}>{m(`f.${key}`)}</span>
      <Input type="number" min="0" max="100" step="any" value={head[key]} onChange={(e) => setH({ [key]: e.target.value })} disabled={!editable} aria-label={m(`f.${key}`)} />
      {key === "discount_pct" ? <span className={hint}>{m("discount_hint")}</span> : key === "tds_pct" ? <span className={hint}>{m("tds_hint")}</span> : key === "vds_pct" ? <span className={hint}>{m("vds_hint")}</span> : null}
    </label>
  );
  return (
    <div className="flex flex-col gap-4">
      <section className={cn(card, "p-5")}>
        <div className="flex items-start justify-between gap-3">
          <div>
            <h2 className="flex items-center gap-2 text-base font-semibold text-foreground">
              <PackageCheck className="size-4" /> {m("entry")}
            </h2>
            <p className="mt-0.5 text-[13px] text-foreground-muted">{m("entry_hint")}</p>
          </div>
          <span className="rounded-md bg-brand-subtle px-2 py-0.5 text-xs font-medium text-brand">{mrr ? m(mrr.editable ? "draft" : "finalized") : m("new")}</span>
        </div>
        <div className={cn(field, "mt-4")}>
          <span className={lab}>{m("existing")}</span>
          <Select value={mrr?.mrr_no ?? ""} onValueChange={(v) => load(v ?? "")} options={[{ value: "", label: m("new_receipt") }, ...options.receipts]} aria-label={m("existing")} />
          <span className={hint}>{m("existing_hint")}</span>
        </div>

        <fieldset className={cn(group, "mt-4")}>
          <legend className="px-1 text-[13px] font-semibold text-foreground">{m("master_data")}</legend>
          <div className="grid gap-4 md:grid-cols-3">
            <div className={field}>
              <span className={lab}>
                {m("f.unit_company_id")}
                {star}
              </span>
              <Select value={head.unit_company_id} onValueChange={(v) => setH({ unit_company_id: v ?? "" })} options={pick(options.companies)} disabled={!editable} aria-label={m("f.unit_company_id")} />
            </div>
            <div className={field}>
              <span className={lab}>
                {m("f.supplier_id")}
                {star}
              </span>
              <Select value={head.supplier_id} onValueChange={(v) => setH({ supplier_id: v ?? "", po_id: "" })} options={pick(options.suppliers)} placeholder={m("select")} disabled={!editable} aria-label={m("f.supplier_id")} />
            </div>
            <div className={field}>
              <span className={lab}>{m("f.mrr_no")}</span>
              <Input value={mrr?.mrr_no ?? ""} placeholder={m("auto_gen")} readOnly className="bg-surface-muted" aria-label={m("f.mrr_no")} />
            </div>
            <div className={field}>
              <span className={lab}>
                {m("f.store_id")}
                {star}
              </span>
              <Select value={head.store_id} onValueChange={(v) => setH({ store_id: v ?? "" })} options={pick(options.stores)} placeholder={m("select")} disabled={!editable} aria-label={m("f.store_id")} />
            </div>
            <label className={field}>
              <span className={lab}>
                {m("f.mrr_date")}
                {star}
              </span>
              <Input type="date" value={head.mrr_date} onChange={(e) => setH({ mrr_date: e.target.value })} disabled={!editable} aria-label={m("f.mrr_date")} />
            </label>
            <div className={field}>
              <span className={lab}>
                {m("f.payment_method")}
                {star}
              </span>
              <Select value={head.payment_method} onValueChange={(v) => setH({ payment_method: v ?? "" })} options={pick(coded("master.apo.method", options.payment_methods))} disabled={!editable} aria-label={m("f.payment_method")} />
            </div>
            <div className={field}>
              <span className={lab}>
                {m("f.source_type")}
                {star}
              </span>
              <Select value={head.source_type} onValueChange={(v) => setH({ source_type: v ?? "" })} options={pick(coded("master.apo.source", options.sources))} disabled={!editable} aria-label={m("f.source_type")} />
            </div>
            <label className={field}>
              <span className={lab}>
                {m("f.challan_no")}
                {star}
              </span>
              <Input value={head.challan_no} onChange={(e) => setH({ challan_no: e.target.value })} maxLength={60} disabled={!editable} aria-label={m("f.challan_no")} />
            </label>
            <label className={field}>
              <span className={lab}>
                {m("f.challan_date")}
                {star}
              </span>
              <Input type="date" value={head.challan_date} onChange={(e) => setH({ challan_date: e.target.value })} disabled={!editable} aria-label={m("f.challan_date")} />
            </label>
            <div className={field}>
              <span className={lab}>
                {m("f.currency")}
                {star}
              </span>
              <Select value={head.currency} onValueChange={(v) => setH({ currency: v ?? "", ...(v === "BDT" ? { conv_rate: "1" } : {}) })} options={pick(plain(options.currencies))} disabled={!editable} aria-label={m("f.currency")} />
            </div>
            <label className={field}>
              <span className={lab}>{m("f.conv_rate")}</span>
              <Input type="number" min="0" step="any" value={head.conv_rate} onChange={(e) => setH({ conv_rate: e.target.value })} disabled={!editable || head.currency === "BDT"} aria-label={m("f.conv_rate")} />
              <span className={hint}>{m("conv_hint")}</span>
            </label>
            <div className={field}>
              <span className={lab}>{m("f.po_id")}</span>
              <Select value={head.po_id} onValueChange={(v) => setH({ po_id: v ?? "" })} options={pick(orders.map(({ value, label }) => ({ value, label })))} disabled={!editable} aria-label={m("f.po_id")} />
              {orders.length === 0 ? <span className={hint}>{m("no_records")}</span> : null}
            </div>
            <div className={field}>
              <span className={lab}>{m("f.requisition_id")}</span>
              <Select value={head.requisition_id} onValueChange={(v) => setH({ requisition_id: v ?? "" })} options={pick(options.requisitions)} disabled={!editable} aria-label={m("f.requisition_id")} />
            </div>
            <div className={field}>
              <span className={lab}>{m("f.requisition_type")}</span>
              <Select value={head.requisition_type} onValueChange={(v) => setH({ requisition_type: v ?? "" })} options={pick(plain(options.requisition_types))} placeholder={m("select")} disabled={!editable} aria-label={m("f.requisition_type")} />
            </div>
            <div className={field}>
              <span className={lab}>
                {m("f.mrr_type")}
                {star}
              </span>
              <Select value={head.mrr_type} onValueChange={(v) => setH({ mrr_type: v ?? "" })} options={pick(coded("master.mrr.type", options.types))} disabled={!editable} aria-label={m("f.mrr_type")} />
              <span className={hint}>{m("type_hint")}</span>
            </div>
            <label className={cn(field, "md:col-span-3")}>
              <span className={lab}>{m("f.remarks")}</span>
              <Textarea rows={3} value={head.remarks} onChange={(e) => setH({ remarks: e.target.value })} maxLength={2000} disabled={!editable} aria-label={m("f.remarks")} />
            </label>
          </div>
        </fieldset>

        <div className="mt-4 grid gap-4 lg:grid-cols-2">
          <fieldset className={group}>
            <legend className="px-1 text-[13px] font-semibold text-foreground">{m("deductions")}</legend>
            <div className="grid gap-4 sm:grid-cols-2">
              {pctInput("discount_pct")}
              <label className={field}>
                <span className={lab}>{m("f.discount_amount")}</span>
                <Input type="number" min="0" step="any" value={head.discount_amount} onChange={(e) => setH({ discount_amount: e.target.value })} disabled={!editable || num(head.discount_pct) > 0} aria-label={m("f.discount_amount")} />
              </label>
              {pctInput("vat_pct")}
              {pctInput("tds_pct")}
              {pctInput("vds_pct")}
            </div>
          </fieldset>
          <fieldset className={group}>
            <legend className="px-1 text-[13px] font-semibold text-foreground">{m("bill")}</legend>
            <div className="grid gap-4 sm:grid-cols-2">
              {BILL.map((k) => (
                <div key={k} className={field}>
                  <span className={lab}>{m(`f.${k}`)}</span>
                  <Input value={mrr ? money.format(mrr[k]) : ""} placeholder={m("on_save")} readOnly className={cn("bg-surface-muted text-right tabular-nums", k === "net_payable" && "font-semibold")} aria-label={m(`f.${k}`)} />
                </div>
              ))}
              <div className={cn(field, "sm:col-span-2")}>
                <span className={lab}>{m("f.voucher_no")}</span>
                <Input value={mrr?.voucher_no ?? ""} placeholder={m("on_finalise")} readOnly className="bg-surface-muted" aria-label={m("f.voucher_no")} />
              </div>
            </div>
          </fieldset>
        </div>
      </section>

      <section className={cn(card, "overflow-hidden")}>
        <div className="flex items-start justify-between gap-3 px-5 py-4">
          <div>
            <h2 className="text-base font-semibold text-foreground">{m("items")}</h2>
            <p className="mt-0.5 text-[13px] text-foreground-muted">{m("items_hint")}</p>
          </div>
          {editable ? (
            <Button variant="outline" size="sm" onClick={() => setLines((all) => [...all, asLine()])}>
              <Plus /> {m("add_line")}
            </Button>
          ) : null}
        </div>
        <div className="overflow-x-auto">
          <table className="w-full text-sm">
            <thead className="bg-brand text-brand-foreground">
              <tr>
                {["item", "description", "batch", "uom", "qty", "rate", "amount", "remove"].map((k) => (
                  <th key={k} className={cn("px-3 py-2.5 text-left text-[13px] font-semibold whitespace-nowrap", ["qty", "rate", "amount"].includes(k) && "text-right")}>
                    {k === "remove" ? <span className="sr-only">{m("col.remove")}</span> : m(`col.${k}`)}
                  </th>
                ))}
              </tr>
            </thead>
            <tbody>
              {lines.map((l, i) => (
                <tr key={l.key} className="border-t border-border">
                  <td className="px-3 py-1.5">
                    <div className="w-72">
                      <Select value={l.item_id} onValueChange={(v) => setLine(l.key, { item_id: v ?? "", uom: l.uom || options.items.find((x) => x.value === v)?.uom || "" })} options={[{ value: "", label: "—" }, ...options.items.map(({ value, label }) => ({ value, label }))]} disabled={!editable} aria-label={m("row_item", { n: i + 1 })} />
                    </div>
                    {l.po_no ? <span className="mt-0.5 block text-[11px] text-foreground-muted">{l.po_no}</span> : null}
                  </td>
                  <td className="px-3 py-1.5">
                    <Input value={l.description} onChange={(e) => setLine(l.key, { description: e.target.value })} maxLength={200} className="h-8 w-44" disabled={!editable} aria-label={m("row_description", { n: i + 1 })} />
                  </td>
                  <td className="px-3 py-1.5">
                    <Input value={l.batch} onChange={(e) => setLine(l.key, { batch: e.target.value })} maxLength={60} className="h-8 w-28" disabled={!editable} aria-label={m("row_batch", { n: i + 1 })} />
                  </td>
                  <td className="px-3 py-1.5">
                    <UomSelect value={l.uom} onChange={(v) => setLine(l.key, { uom: v })} size="sm" className="w-32" disabled={!editable} aria-label={m("row_uom", { n: i + 1 })} />
                  </td>
                  <td className="px-3 py-1.5">
                    <Input type="number" min="0" step="0.0001" value={l.qty} onChange={(e) => setLine(l.key, { qty: e.target.value })} className="ml-auto h-8 w-28 text-right" disabled={!editable} aria-label={m("row_qty", { n: i + 1 })} />
                  </td>
                  <td className="px-3 py-1.5">
                    <Input type="number" min="0" step="0.0001" value={l.rate} onChange={(e) => setLine(l.key, { rate: e.target.value })} className="ml-auto h-8 w-28 text-right" disabled={!editable} aria-label={m("row_rate", { n: i + 1 })} />
                  </td>
                  <td className="px-3 py-1.5 text-right tabular-nums">{money.format(num(l.qty) * num(l.rate))}</td>
                  <td className="px-3 py-1.5 text-right">
                    {editable ? (
                      <Button variant="ghost" size="icon" className="size-8" aria-label={m("row_remove")} onClick={() => setLines((all) => all.filter((x) => x.key !== l.key))}>
                        <Trash2 />
                      </Button>
                    ) : null}
                  </td>
                </tr>
              ))}
              {lines.length === 0 ? (
                <tr>
                  <td colSpan={8} className="px-5 py-8 text-center text-[13px] text-foreground-muted">
                    {m("no_lines")}
                  </td>
                </tr>
              ) : null}
            </tbody>
          </table>
        </div>
        {lines.length ? (
          <div className="flex flex-wrap items-center gap-6 border-t border-border px-5 py-3 text-sm">
            <span className="font-semibold text-foreground">{m("total", { n: lines.length })}</span>
            <span className="font-semibold text-foreground tabular-nums">
              {money.format(lines.reduce((n, l) => n + num(l.qty) * num(l.rate), 0))} {head.currency}
            </span>
          </div>
        ) : null}
      </section>

      <section className={cn(card, "overflow-hidden")}>
        <div className="flex items-start justify-between gap-3 px-5 py-4">
          <div>
            <h2 className="text-base font-semibold text-foreground">{m("costs")}</h2>
            <p className="mt-0.5 text-[13px] text-foreground-muted">{m("costs_hint")}</p>
          </div>
          {editable ? (
            <Button variant="outline" size="sm" onClick={() => setCosts((all) => [...all, asCost()])}>
              <Plus /> {m("add_cost")}
            </Button>
          ) : null}
        </div>
        <div className="overflow-x-auto">
          <table className="w-full text-sm">
            <thead className="bg-brand text-brand-foreground">
              <tr>
                {["cost_head", "landed", "vat", "amount", "remarks", "remove"].map((k) => (
                  <th key={k} className={cn("px-3 py-2.5 text-left text-[13px] font-semibold whitespace-nowrap", k === "amount" && "text-right")}>
                    {k === "remove" ? <span className="sr-only">{m("col.remove")}</span> : m(`col.${k}`)}
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
                        <Select value={c.cost_head_id} onValueChange={(v) => setCost(c.key, { cost_head_id: v ?? "" })} options={[{ value: "", label: "—" }, ...options.cost_heads.map(({ value, label }) => ({ value, label }))]} disabled={!editable} aria-label={m("cost_row_head", { n: i + 1 })} />
                      </div>
                    </td>
                    <td className="px-3 py-1.5">{h ? m(h.landed ? "yes" : "no") : "—"}</td>
                    <td className="px-3 py-1.5">{h ? m(h.vat_applicable ? "yes" : "no") : "—"}</td>
                    <td className="px-3 py-1.5">
                      <Input type="number" min="0" step="any" value={c.amount} onChange={(e) => setCost(c.key, { amount: e.target.value })} className="ml-auto h-8 w-32 text-right" disabled={!editable} aria-label={m("cost_row_amount", { n: i + 1 })} />
                    </td>
                    <td className="px-3 py-1.5">
                      <Input value={c.remarks} onChange={(e) => setCost(c.key, { remarks: e.target.value })} maxLength={200} className="h-8 w-56" disabled={!editable} aria-label={m("cost_row_remarks", { n: i + 1 })} />
                    </td>
                    <td className="px-3 py-1.5 text-right">
                      {editable ? (
                        <Button variant="ghost" size="icon" className="size-8" aria-label={m("row_remove")} onClick={() => setCosts((all) => all.filter((x) => x.key !== c.key))}>
                          <Trash2 />
                        </Button>
                      ) : null}
                    </td>
                  </tr>
                );
              })}
              {costs.length === 0 ? (
                <tr>
                  <td colSpan={6} className="px-5 py-6 text-center text-[13px] text-foreground-muted">
                    {m("no_costs")}
                  </td>
                </tr>
              ) : null}
            </tbody>
          </table>
        </div>
      </section>

      {errors ? <Alert variant="danger">{errors.join(" · ")}</Alert> : null}
      <section className={cn(card, "flex flex-wrap items-center justify-between gap-3 p-5")}>
        <p className="max-w-3xl text-[13px] text-foreground-muted">{m("rules")}</p>
        <div className="flex flex-wrap gap-2">
          {mrr && editable ? (
            <Button variant="outline" className="text-danger" onClick={() => setConfirm("delete")} disabled={pending}>
              <Trash2 /> {m("delete")}
            </Button>
          ) : null}
          <Button variant="outline" onClick={clear} disabled={pending}>
            <RotateCcw /> {m("clear")}
          </Button>
          {editable ? (
            <Button onClick={save} loading={pending}>
              <Save /> {mrr ? m("save_changes") : m("save")}
            </Button>
          ) : null}
          {mrr && editable ? (
            <Button variant="success" onClick={() => setConfirm("finalize")} disabled={pending}>
              <CheckCheck /> {m("finalize")}
            </Button>
          ) : null}
        </div>
      </section>

      <ConfirmDialog
        open={confirm !== null}
        onOpenChange={(o) => !o && setConfirm(null)}
        title={confirm ? m(`confirm_${confirm}_title`) : ""}
        description={confirm ? m(`confirm_${confirm}_hint`) : ""}
        confirmLabel={confirm ? m(confirm) : ""}
        cancelLabel={m("cancel")}
        destructive={confirm === "delete"}
        onConfirm={() => confirm && run(confirm)}
        loading={pending}
      />
    </div>
  );
}

export { AccReceiptEntry };
