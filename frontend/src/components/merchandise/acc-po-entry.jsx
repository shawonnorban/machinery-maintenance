"use client";

import { useState, useTransition } from "react";
import { Layers, Plus, RotateCcw, Save, Trash2 } from "lucide-react";
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
import { UomSelect } from "@/components/erp/uom-select";

// An error's field messages, or its message when it names no field.
const messagesOf = (result) => {
  const fields = [...new Set(Object.values(result?.errors ?? {}).flat())];
  return fields.length ? fields : [result?.message ?? ""];
};
const str = (v) => (v === null || v === undefined ? "" : String(v));
const todayIso = () => new Intl.DateTimeFormat("en-CA", { timeZone: "Asia/Dhaka" }).format(new Date());
// Brickland's defaults: a local order, FOB, by LC, in taka at 1.
const blankHead = (date) => ({
  supplier_id: "", po_date: date, source: "LOCAL", requisition_id: "", beneficiary_company_id: "", billed_to_company_id: "", delivery_store_id: "", is_online: false, same_as_company: false,
  inco_term: "FOB", place_of_delivery: "", payment_method: "LC", currency: "BDT", conv_rate: "1", payment_term: "", mode_of_shipment: "", delivery_date: "", cash_supplier: "", other_term: "", remarks: "",
});
const headOf = (data, date) => Object.fromEntries(Object.entries(blankHead(date)).map(([k, v]) => [k, typeof v === "boolean" ? Boolean(data?.[k]) : data?.[k] === null || data?.[k] === undefined ? v : str(data[k])]));
let seq = 0;
const asLine = (l = {}) => ({ key: `p${++seq}`, item_id: str(l.item_id), requisition_line_id: l.requisition_line_id ?? null, req_no: l.req_no ?? null, specification: str(l.specification), uom: str(l.uom), qty: str(l.qty), unit_price: str(l.unit_price), note: str(l.note) });
const num = (v) => (v === "" || v === null || v === undefined ? 0 : Number(v));
const money = new Intl.NumberFormat("en-US", { minimumFractionDigits: 2, maximumFractionDigits: 2 });

/**
 * Accessories Purchase Order → Purchase Order Entry (AnnotechERP 06/06), as
 * Brickland lays it out: the existing-order picker, Master Data (supplier,
 * PO no and date, source, the requisition it is against, beneficiary,
 * billed-to, the store it is delivered to, online and same-as-company),
 * Terms & Condition, and Details Data — the priced lines, "Add to PO" adding
 * one. A requisition taken from the Requisition List arrives with what is
 * left of it. Header and lines are saved together; each change is a new
 * revision; an order received against is read only. Nothing reloads.
 *
 * @param {{ options: object, initial: ?object, prefill: ?object, canManage: boolean, actions: object }} props
 */
function AccPoEntry({ options, initial, prefill, canManage, actions }) {
  const t = useT("merchandise");
  const m = (k, p) => t(`master.apo.${k}`, p);
  const toastManager = useToastManager();
  const [today] = useState(todayIso);
  const [po, setPo] = useState(initial);
  const [head, setHead] = useState(() => (initial ? headOf(initial, today) : { ...blankHead(today), ...(prefill ? { requisition_id: prefill.requisition_id, supplier_id: str(prefill.supplier_id), delivery_date: str(prefill.delivery_date) } : {}) }));
  const [lines, setLines] = useState(() => (initial?.lines ?? prefill?.lines ?? []).map(asLine));
  const [errors, setErrors] = useState(null);
  const [confirm, setConfirm] = useState(false);
  const [pending, startTransition] = useTransition();
  const editable = canManage && (!po || po.editable);
  const setH = (patch) => setHead((h) => ({ ...h, ...patch }));
  const setLine = (key, patch) => setLines((all) => all.map((l) => (l.key === key ? { ...l, ...patch } : l)));
  const pick = (list) => [{ value: "", label: m("select") }, ...list];
  const coded = (group, values) => values.map((v) => ({ value: v, label: m(`${group}.${v}`) }));
  const plain = (values) => values.map((v) => ({ value: v, label: v }));
  const total = lines.reduce((n, l) => n + num(l.qty) * num(l.unit_price), 0);

  function take(data) {
    setPo(data);
    setHead(headOf(data, today));
    setLines(data.lines.map(asLine));
    setErrors(null);
    window.history.replaceState(null, "", `?tab=entry&ref=${encodeURIComponent(data.po_no)}`);
  }

  function clear() {
    setPo(null);
    setHead(blankHead(today));
    setLines([]);
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
      const payload = {
        ...Object.fromEntries(Object.entries(head).map(([k, v]) => [k, v === "" ? null : v])),
        conv_rate: head.conv_rate === "" ? null : Number(head.conv_rate),
        lines: lines.map((l) => ({ item_id: l.item_id || null, requisition_line_id: l.requisition_line_id, specification: l.specification || null, uom: l.uom || null, qty: l.qty === "" ? null : Number(l.qty), unit_price: l.unit_price === "" ? null : Number(l.unit_price), note: l.note || null })),
      };
      const result = await actions.save(po?.po_no ?? null, payload);
      if (result?.status !== "success") return setErrors(messagesOf(result));
      take(result.data);
      toastManager.add({ title: po ? m("updated", { no: result.data.po_no, rev: result.data.revision }) : m("saved", { no: result.data.po_no }), type: "success" });
    });
  }

  function remove() {
    startTransition(async () => {
      const result = await actions.remove(po.po_no);
      setConfirm(false);
      if (result?.status !== "success") return setErrors(messagesOf(result));
      toastManager.add({ title: m("deleted", { no: po.po_no }), type: "success" });
      clear();
    });
  }

  const card = "rounded-xl border border-border bg-surface";
  const group = "rounded-lg border border-border p-4";
  const field = "flex flex-col gap-1.5";
  const lab = "text-[13px] font-semibold text-foreground";
  const hint = "text-xs text-foreground-muted";
  const star = <span className="text-danger">*</span>;
  return (
    <div className="flex flex-col gap-4">
      <section className={cn(card, "p-5")}>
        <div className="flex items-start justify-between gap-3">
          <div>
            <h2 className="flex items-center gap-2 text-base font-semibold text-foreground">
              <Layers className="size-4" /> {m("entry")}
            </h2>
            <p className="mt-0.5 text-[13px] text-foreground-muted">{m("entry_hint")}</p>
          </div>
          <span className="rounded-md bg-brand-subtle px-2 py-0.5 text-xs font-medium text-brand">{po ? (po.editable ? m("editing", { n: po.revision }) : m("read_only")) : m("new")}</span>
        </div>
        <div className={cn(field, "mt-4")}>
          <span className={lab}>{m("existing")}</span>
          <Select value={po?.po_no ?? ""} onValueChange={(v) => load(v ?? "")} options={[{ value: "", label: m("new_po") }, ...options.orders]} aria-label={m("existing")} />
          <span className={hint}>{m("existing_hint")}</span>
        </div>

        <fieldset className={cn(group, "mt-4")}>
          <legend className="px-1 text-[13px] font-semibold text-foreground">{m("master_data")}</legend>
          <div className="grid gap-4 md:grid-cols-3">
            <div className={field}>
              <span className={lab}>
                {m("f.supplier_id")}
                {star}
              </span>
              <Select value={head.supplier_id} onValueChange={(v) => setH({ supplier_id: v ?? "" })} options={pick(options.suppliers)} placeholder={m("select")} disabled={!editable} aria-label={m("f.supplier_id")} />
            </div>
            <div className={field}>
              <span className={lab}>{m("f.po_no")}</span>
              <Input value={po?.po_no ?? ""} placeholder={m("auto_gen")} readOnly className="bg-surface-muted" aria-label={m("f.po_no")} />
            </div>
            <label className={field}>
              <span className={lab}>
                {m("f.po_date")}
                {star}
              </span>
              <Input type="date" value={head.po_date} onChange={(e) => setH({ po_date: e.target.value })} disabled={!editable} aria-label={m("f.po_date")} />
            </label>
            <div className={field}>
              <span className={lab}>
                {m("f.source")}
                {star}
              </span>
              <Select value={head.source} onValueChange={(v) => setH({ source: v ?? "" })} options={pick(coded("source", options.sources))} disabled={!editable} aria-label={m("f.source")} />
            </div>
            <div className={field}>
              <span className={lab}>{m("f.requisition_id")}</span>
              <Select value={head.requisition_id} onValueChange={(v) => setH({ requisition_id: v ?? "" })} options={pick(options.requisitions)} disabled={!editable} aria-label={m("f.requisition_id")} />
            </div>
            <div className={field}>
              <span className={lab}>{m("f.beneficiary")}</span>
              <Select value={head.beneficiary_company_id} onValueChange={(v) => setH({ beneficiary_company_id: v ?? "" })} options={pick(options.companies)} disabled={!editable} aria-label={m("f.beneficiary")} />
            </div>
            <div className={field}>
              <span className={lab}>{m("f.billed_to")}</span>
              <Select value={head.same_as_company ? head.beneficiary_company_id : head.billed_to_company_id} onValueChange={(v) => setH({ billed_to_company_id: v ?? "" })} options={pick(options.companies)} disabled={!editable || head.same_as_company} aria-label={m("f.billed_to")} />
            </div>
            <div className={field}>
              <span className={lab}>{m("f.delivery_to")}</span>
              <Select value={head.delivery_store_id} onValueChange={(v) => setH({ delivery_store_id: v ?? "" })} options={pick(options.stores)} placeholder={m("select")} disabled={!editable} aria-label={m("f.delivery_to")} />
            </div>
            <label className="flex items-center gap-2 self-end pb-2 text-[13px] font-semibold text-foreground">
              <Checkbox checked={head.is_online} onCheckedChange={(c) => setH({ is_online: c === true })} disabled={!editable} aria-label={m("f.is_online")} /> {m("f.is_online")}
            </label>
            <label className="flex items-start gap-2 md:col-span-3">
              <Checkbox checked={head.same_as_company} onCheckedChange={(c) => setH({ same_as_company: c === true })} disabled={!editable} aria-label={m("f.same_as_company")} className="mt-0.5" />
              <span>
                <span className="text-[13px] font-semibold text-foreground">{m("f.same_as_company")}</span>
                <span className={cn(hint, "block")}>{m("same_hint")}</span>
              </span>
            </label>
          </div>
        </fieldset>

        <fieldset className={cn(group, "mt-4")}>
          <legend className="px-1 text-[13px] font-semibold text-foreground">{m("terms")}</legend>
          <div className="grid gap-4 md:grid-cols-3">
            <div className={field}>
              <span className={lab}>{m("f.inco_term")}</span>
              <Select value={head.inco_term} onValueChange={(v) => setH({ inco_term: v ?? "" })} options={pick(plain(options.inco_terms))} disabled={!editable} aria-label={m("f.inco_term")} />
            </div>
            <label className={field}>
              <span className={lab}>{m("f.place_of_delivery")}</span>
              <Input value={head.place_of_delivery} onChange={(e) => setH({ place_of_delivery: e.target.value })} maxLength={160} disabled={!editable} aria-label={m("f.place_of_delivery")} />
            </label>
            <div className={field}>
              <span className={lab}>
                {m("f.payment_method")}
                {star}
              </span>
              <Select value={head.payment_method} onValueChange={(v) => setH({ payment_method: v ?? "" })} options={pick(coded("method", options.payment_methods))} disabled={!editable} aria-label={m("f.payment_method")} />
            </div>
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
              <span className={lab}>
                {m("f.payment_term")}
                {star}
              </span>
              <Select value={head.payment_term} onValueChange={(v) => setH({ payment_term: v ?? "" })} options={pick(coded("term", options.payment_terms))} placeholder={m("select")} disabled={!editable} aria-label={m("f.payment_term")} />
            </div>
            <div className={field}>
              <span className={lab}>
                {m("f.mode_of_shipment")}
                {star}
              </span>
              <Select value={head.mode_of_shipment} onValueChange={(v) => setH({ mode_of_shipment: v ?? "" })} options={pick(coded("ship", options.shipment_modes))} placeholder={m("select")} disabled={!editable} aria-label={m("f.mode_of_shipment")} />
            </div>
            <label className={field}>
              <span className={lab}>
                {m("f.delivery_date")}
                {star}
              </span>
              <Input type="date" value={head.delivery_date} onChange={(e) => setH({ delivery_date: e.target.value })} disabled={!editable} aria-label={m("f.delivery_date")} />
            </label>
            <label className={field}>
              <span className={lab}>{m("f.cash_supplier")}</span>
              <Input value={head.cash_supplier} onChange={(e) => setH({ cash_supplier: e.target.value })} maxLength={120} disabled={!editable} aria-label={m("f.cash_supplier")} />
              <span className={hint}>{m("cash_hint")}</span>
            </label>
            <label className={cn(field, "md:col-span-3")}>
              <span className={lab}>{m("f.other_term")}</span>
              <Textarea rows={3} value={head.other_term} onChange={(e) => setH({ other_term: e.target.value })} maxLength={2000} disabled={!editable} aria-label={m("f.other_term")} />
            </label>
            <label className={cn(field, "md:col-span-3")}>
              <span className={lab}>{m("f.remarks")}</span>
              <Textarea rows={3} value={head.remarks} onChange={(e) => setH({ remarks: e.target.value })} maxLength={2000} disabled={!editable} aria-label={m("f.remarks")} />
            </label>
          </div>
        </fieldset>
      </section>

      <section className={cn(card, "overflow-hidden")}>
        <div className="flex items-start justify-between gap-3 px-5 py-4">
          <div>
            <h2 className="text-base font-semibold text-foreground">{m("details")}</h2>
            <p className="mt-0.5 text-[13px] text-foreground-muted">{m("details_hint")}</p>
          </div>
          {editable ? (
            <Button variant="outline" size="sm" onClick={() => setLines((all) => [...all, asLine()])}>
              <Plus /> {m("add_to_po")}
            </Button>
          ) : null}
        </div>
        <div className="overflow-x-auto">
          <table className="w-full text-sm">
            <thead className="bg-brand text-brand-foreground">
              <tr>
                {["item", "spec", "uom", "qty", "price", "note", "remove"].map((k) => (
                  <th key={k} className={cn("px-3 py-2.5 text-left text-[13px] font-semibold whitespace-nowrap", ["qty", "price"].includes(k) && "text-right")}>
                    {k === "remove" ? <span className="sr-only">{m("col.remove")}</span> : m(`col.${k}`)}
                  </th>
                ))}
              </tr>
            </thead>
            <tbody>
              {lines.map((l, i) => (
                <tr key={l.key} className="border-t border-border">
                  <td className="px-3 py-1.5">
                    <div className="w-64">
                      <Select value={l.item_id} onValueChange={(v) => setLine(l.key, { item_id: v ?? "", uom: l.uom || options.items.find((x) => x.value === v)?.uom || "" })} options={[{ value: "", label: "—" }, ...options.items.map(({ value, label }) => ({ value, label }))]} disabled={!editable} aria-label={m("row_item", { n: i + 1 })} />
                    </div>
                    {l.req_no ? <span className="mt-0.5 block text-[11px] text-foreground-muted">{l.req_no}</span> : null}
                  </td>
                  <td className="px-3 py-1.5">
                    <Input value={l.specification} onChange={(e) => setLine(l.key, { specification: e.target.value })} maxLength={200} className="h-8 w-44" disabled={!editable} aria-label={m("row_spec", { n: i + 1 })} />
                  </td>
                  <td className="px-3 py-1.5">
                    <UomSelect value={l.uom} onChange={(v) => setLine(l.key, { uom: v })} size="sm" className="w-32" disabled={!editable} aria-label={m("row_uom", { n: i + 1 })} />
                  </td>
                  <td className="px-3 py-1.5">
                    <Input type="number" min="0" step="0.0001" value={l.qty} onChange={(e) => setLine(l.key, { qty: e.target.value })} className="ml-auto h-8 w-28 text-right" disabled={!editable} aria-label={m("row_qty", { n: i + 1 })} />
                  </td>
                  <td className="px-3 py-1.5">
                    <Input type="number" min="0" step="0.0001" value={l.unit_price} onChange={(e) => setLine(l.key, { unit_price: e.target.value })} className="ml-auto h-8 w-28 text-right" disabled={!editable} aria-label={m("row_price", { n: i + 1 })} />
                  </td>
                  <td className="px-3 py-1.5">
                    <Input value={l.note} onChange={(e) => setLine(l.key, { note: e.target.value })} maxLength={200} className="h-8 w-44" disabled={!editable} aria-label={m("row_note", { n: i + 1 })} />
                  </td>
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
                  <td colSpan={7} className="px-5 py-8 text-center text-[13px] text-foreground-muted">
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
            <span className="text-foreground-muted">
              {m("value_total")}{" "}
              <span className="font-semibold text-foreground tabular-nums">
                {money.format(total)} {head.currency}
              </span>
            </span>
          </div>
        ) : null}
      </section>

      {errors ? <Alert variant="danger">{errors.join(" · ")}</Alert> : null}
      <section className={cn(card, "flex flex-wrap items-center justify-between gap-3 p-5")}>
        <p className="text-[13px] text-foreground-muted">{m("rules")}</p>
        <div className="flex flex-wrap gap-2">
          {po && editable ? (
            <Button variant="outline" className="text-danger" onClick={() => setConfirm(true)} disabled={pending}>
              <Trash2 /> {m("delete")}
            </Button>
          ) : null}
          <Button variant="outline" onClick={clear} disabled={pending}>
            <RotateCcw /> {m("clear")}
          </Button>
          {editable ? (
            <Button onClick={save} loading={pending}>
              <Save /> {po ? m("save_changes") : m("save")}
            </Button>
          ) : null}
        </div>
      </section>

      <ConfirmDialog open={confirm} onOpenChange={setConfirm} title={m("confirm_delete_title")} description={m("confirm_delete_hint")} confirmLabel={m("delete")} cancelLabel={m("cancel")} destructive onConfirm={remove} loading={pending} />
    </div>
  );
}

export { AccPoEntry };
