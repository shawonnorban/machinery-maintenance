"use client";

import { useMemo, useState, useTransition } from "react";
import Link from "next/link";
import { ArrowLeft, CheckCheck, Plus, RotateCcw, Save, Search, Trash2 } from "lucide-react";
import { cn } from "@/lib/utils";
import { Input } from "@/components/ui/input";
import { Select } from "@/components/ui/select";
import { Textarea } from "@/components/ui/textarea";
import { Button, buttonVariants } from "@/components/ui/button";
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
const dateFmt = new Intl.DateTimeFormat("en-GB", { day: "numeric", month: "short", year: "numeric", timeZone: "UTC" });
const day = (v) => (v ? dateFmt.format(new Date(`${v}T00:00:00Z`)) : "—");
// Brickland's default: a rejected-goods return.
const blankHead = (date) => ({ return_type: "REJECTED", unit_company_id: "", supplier_id: "", mrr_no: "", challan_date: date, store_id: "", return_by: "", gate_pass_no: "", vehicle_no: "", driver_name: "", remarks: "" });
const headOf = (data, date) => Object.fromEntries(Object.entries(blankHead(date)).map(([k, v]) => [k, data?.[k] === null || data?.[k] === undefined ? v : str(data[k])]));
let seq = 0;
const asLine = (l = {}) => ({ key: `t${++seq}`, mrr_line_id: l.mrr_line_id ?? null, description: str(l.description), uom: str(l.uom), qty: str(l.qty), reason: str(l.reason) });
const PAGE = 10;

/**
 * Accessories Supplier Return Challan → entry (AnnotechERP 06/06), as
 * Brickland lays it out: the MRR List on the left (finalised receipts, by
 * supplier, searchable, ten to a page); picking one fills the Master List
 * (company, supplier, store from the receipt) and Details Data with what it
 * brought in, the quantities then corrected to the return. Save keeps a
 * draft; Finalize locks it and takes the goods off stock. Nothing reloads.
 * The Fabric Supplier Return Challan is the same screen; `labels` swaps the
 * few lines that name what goes back (key → its own lang key).
 *
 * @param {{ options: object, initial: ?object, canManage: boolean, actions: object, recordsHref: string, labels?: Record<string, string> }} props
 */
function AccReturnEntry({ options, initial, canManage, actions, recordsHref, labels = {} }) {
  const t = useT("merchandise");
  const m = (k, p) => t(labels[k] ?? `master.rtn.${k}`, p);
  const toastManager = useToastManager();
  const [today] = useState(todayIso);
  const [rtn, setRtn] = useState(initial);
  const [head, setHead] = useState(() => (initial ? headOf(initial, today) : blankHead(today)));
  const [lines, setLines] = useState(() => (initial?.lines ?? []).map(asLine));
  const [customer, setCustomer] = useState("");
  const [query, setQuery] = useState("");
  const [page, setPage] = useState(0);
  const [errors, setErrors] = useState(null);
  const [confirm, setConfirm] = useState(null);
  const [pending, startTransition] = useTransition();
  const editable = canManage && (!rtn || rtn.editable);
  const setH = (patch) => setHead((h) => ({ ...h, ...patch }));
  const setLine = (key, patch) => setLines((all) => all.map((l) => (l.key === key ? { ...l, ...patch } : l)));
  const pick = (list) => [{ value: "", label: m("select") }, ...list];
  const shown = useMemo(() => {
    const needle = query.trim().toLowerCase();
    return options.receipts.filter((r) => (!customer || r.supplier_id === customer) && (!needle || `${r.mrr_no} ${r.challan_no}`.toLowerCase().includes(needle)));
  }, [options.receipts, customer, query]);
  const pages = Math.max(1, Math.ceil(shown.length / PAGE));

  function fillFrom(no) {
    startTransition(async () => {
      const result = await actions.fromReceipt(no);
      if (result?.status !== "success") return setErrors(messagesOf(result));
      const d = result.data;
      setH({ mrr_no: d.mrr_no, unit_company_id: str(d.unit_company_id), supplier_id: str(d.supplier_id), store_id: str(d.store_id) });
      setLines(d.lines.map(asLine));
      setErrors(null);
    });
  }

  function take(data) {
    setRtn(data);
    setHead(headOf(data, today));
    setLines(data.lines.map(asLine));
    setErrors(null);
    window.history.replaceState(null, "", `?tab=entry&ref=${encodeURIComponent(data.challan_no)}`);
  }

  function clear() {
    setRtn(null);
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
      const blank = (v) => (v === "" ? null : v);
      const payload = {
        ...Object.fromEntries(Object.entries(head).map(([k, v]) => [k, blank(v)])),
        lines: lines.map((l) => ({ mrr_line_id: l.mrr_line_id, description: blank(l.description), uom: blank(l.uom), qty: l.qty === "" ? null : Number(l.qty), reason: blank(l.reason) })),
      };
      const result = await actions.save(rtn?.challan_no ?? null, payload);
      if (result?.status !== "success") return setErrors(messagesOf(result));
      take(result.data);
      toastManager.add({ title: m("saved", { no: result.data.challan_no }), type: "success" });
    });
  }

  function run(kind) {
    startTransition(async () => {
      const result = kind === "delete" ? await actions.remove(rtn.challan_no) : await actions.finalize(rtn.challan_no);
      setConfirm(null);
      if (result?.status !== "success") return setErrors(messagesOf(result));
      toastManager.add({ title: m(kind === "delete" ? "deleted" : "finalized_toast", { no: rtn.challan_no }), type: "success" });
      if (kind === "delete") clear();
      else take(result.data);
    });
  }

  const card = "rounded-xl border border-border bg-surface";
  const field = "flex flex-col gap-1.5";
  const lab = "text-[13px] font-semibold text-foreground";
  const star = <span className="text-danger">*</span>;
  const text = (key, ph, max) => (
    <label className={field}>
      <span className={lab}>{m(`f.${key}`)}</span>
      <Input value={head[key]} onChange={(e) => setH({ [key]: e.target.value })} placeholder={ph ? m(ph) : undefined} maxLength={max} disabled={!editable} aria-label={m(`f.${key}`)} />
    </label>
  );
  return (
    <div className="grid gap-4 xl:grid-cols-[22rem_1fr]">
      <section className={cn(card, "self-start p-4")}>
        <h2 className="text-base font-semibold text-foreground">{m("mrr_list")}</h2>
        <div className="mt-3 flex flex-col gap-1.5">
          <span className={lab}>{m("f.customer")}</span>
          <Select value={customer} onValueChange={(v) => (setCustomer(v ?? ""), setPage(0))} options={[{ value: "", label: m("pick_customer") }, ...options.suppliers]} aria-label={m("f.customer")} />
        </div>
        <div className="relative mt-3">
          <Search className="pointer-events-none absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-foreground-muted" />
          <Input value={query} onChange={(e) => (setQuery(e.target.value), setPage(0))} placeholder={m("search_mrr")} className="pl-8" aria-label={m("mrr_search")} />
        </div>
        <div className="mt-3 overflow-hidden rounded-lg border border-border">
          <table className="w-full text-sm">
            <thead className="bg-brand text-brand-foreground">
              <tr>
                {["mrr_no", "mrr_date", "chalan"].map((k) => (
                  <th key={k} className="px-3 py-2 text-left text-[13px] font-semibold">
                    {m(`col.${k}`)}
                  </th>
                ))}
              </tr>
            </thead>
            <tbody>
              {shown.slice(page * PAGE, page * PAGE + PAGE).map((r) => (
                <tr key={r.mrr_no} className={cn("border-t border-border", head.mrr_no === r.mrr_no && "bg-brand-subtle")}>
                  <td className="px-3 py-2">
                    <button type="button" className="text-left font-medium text-brand hover:underline disabled:text-foreground-muted" onClick={() => fillFrom(r.mrr_no)} disabled={!editable}>
                      {r.mrr_no}
                    </button>
                  </td>
                  <td className="px-3 py-2 whitespace-nowrap">{day(r.mrr_date)}</td>
                  <td className="px-3 py-2">{r.challan_no}</td>
                </tr>
              ))}
              {shown.length === 0 ? (
                <tr>
                  <td colSpan={3} className="px-3 py-6 text-center text-foreground-muted">
                    {m("nothing_left")}
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
              <h2 className="text-base font-semibold text-foreground">{m("entry")}</h2>
              <p className="mt-0.5 text-[13px] text-foreground-muted">{m("entry_hint")}</p>
            </div>
            <span className="rounded-md bg-brand-subtle px-2 py-0.5 text-xs font-medium text-brand">{rtn ? m(rtn.editable ? "draft" : "finalized") : m("new")}</span>
          </div>
          <div className={cn(field, "mt-4")}>
            <span className={lab}>{m("existing")}</span>
            <Select value={rtn?.challan_no ?? ""} onValueChange={(v) => load(v ?? "")} options={[{ value: "", label: m("new_challan") }, ...options.returns]} aria-label={m("existing")} />
          </div>
          <h3 className="mt-5 text-[13px] font-semibold text-foreground">{m("master_list")}</h3>
          <div className="mt-2 grid gap-4 md:grid-cols-3">
            <div className={field}>
              <span className={lab}>
                {m("f.return_type")}
                {star}
              </span>
              <Select value={head.return_type} onValueChange={(v) => setH({ return_type: v ?? "" })} options={pick(options.types.map((v) => ({ value: v, label: m(`type.${v}`) })))} disabled={!editable} aria-label={m("f.return_type")} />
            </div>
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
              <Select value={head.supplier_id} onValueChange={(v) => setH({ supplier_id: v ?? "" })} options={pick(options.suppliers)} placeholder={m("select")} disabled={!editable} aria-label={m("f.supplier_id")} />
            </div>
            <div className={field}>
              <span className={lab}>{m("f.challan_no")}</span>
              <Input value={rtn?.challan_no ?? ""} placeholder={m("auto_gen")} readOnly className="bg-surface-muted" aria-label={m("f.challan_no")} />
            </div>
            <label className={field}>
              <span className={lab}>
                {m("f.challan_date")}
                {star}
              </span>
              <Input type="date" value={head.challan_date} onChange={(e) => setH({ challan_date: e.target.value })} disabled={!editable} aria-label={m("f.challan_date")} />
            </label>
            <div className={field}>
              <span className={lab}>{m("f.mrr_no")}</span>
              <Input value={head.mrr_no} placeholder={m("mrr_list")} readOnly className="bg-surface-muted" aria-label={m("f.mrr_no")} />
            </div>
            <div className={field}>
              <span className={lab}>
                {m("f.store_id")}
                {star}
              </span>
              <Select value={head.store_id} onValueChange={(v) => setH({ store_id: v ?? "" })} options={pick(options.stores)} placeholder={m("select")} disabled={!editable} aria-label={m("f.store_id")} />
            </div>
            <div className={field}>
              <span className={lab}>
                {m("f.return_by")}
                {star}
              </span>
              <Select value={head.return_by} onValueChange={(v) => setH({ return_by: v ?? "" })} options={pick(options.users)} placeholder={m("select")} disabled={!editable} aria-label={m("f.return_by")} />
            </div>
            {text("gate_pass_no", "gate_pass_ph", 40)}
            {text("vehicle_no", "vehicle_ph", 40)}
            {text("driver_name", "driver_ph", 80)}
            <label className={cn(field, "md:col-span-3")}>
              <span className={lab}>{m("f.remarks")}</span>
              <Textarea rows={3} value={head.remarks} onChange={(e) => setH({ remarks: e.target.value })} maxLength={2000} disabled={!editable} aria-label={m("f.remarks")} />
            </label>
          </div>
        </section>

        <section className={cn(card, "overflow-hidden")}>
          <div className="flex items-start justify-between gap-3 px-5 py-4">
            <div>
              <h2 className="text-base font-semibold text-foreground">{m("details")}</h2>
              <p className="mt-0.5 text-[13px] text-foreground-muted">{m("details_hint")}</p>
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
                  {["description", "uom", "qty", "reason", "remove"].map((k) => (
                    <th key={k} className={cn("px-3 py-2.5 text-left text-[13px] font-semibold", k === "qty" && "text-right")}>
                      {k === "remove" ? <span className="sr-only">{m("col.remove")}</span> : m(`col.${k}`)}
                    </th>
                  ))}
                </tr>
              </thead>
              <tbody>
                {lines.map((l, i) => (
                  <tr key={l.key} className="border-t border-border">
                    <td className="px-3 py-1.5">
                      <Input value={l.description} onChange={(e) => setLine(l.key, { description: e.target.value })} maxLength={200} className="h-8 min-w-56" disabled={!editable} aria-label={m("row_description", { n: i + 1 })} />
                    </td>
                    <td className="px-3 py-1.5">
                      <UomSelect value={l.uom} onChange={(v) => setLine(l.key, { uom: v })} size="sm" className="w-32" disabled={!editable} aria-label={m("row_uom", { n: i + 1 })} />
                    </td>
                    <td className="px-3 py-1.5">
                      <Input type="number" min="0" step="0.0001" value={l.qty} onChange={(e) => setLine(l.key, { qty: e.target.value })} className="ml-auto h-8 w-28 text-right" disabled={!editable} aria-label={m("row_qty", { n: i + 1 })} />
                    </td>
                    <td className="px-3 py-1.5">
                      <Input value={l.reason} onChange={(e) => setLine(l.key, { reason: e.target.value })} maxLength={200} className="h-8 min-w-48" disabled={!editable} aria-label={m("row_reason", { n: i + 1 })} />
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
                    <td colSpan={5} className="px-5 py-8 text-center text-[13px] text-foreground-muted">
                      {m("no_lines")}
                    </td>
                  </tr>
                ) : null}
              </tbody>
            </table>
          </div>
          {lines.length ? <div className="border-t border-border px-5 py-3 text-sm font-semibold text-foreground">{m("total", { n: lines.length })}</div> : null}
        </section>

        {errors ? <Alert variant="danger">{errors.join(" · ")}</Alert> : null}
        <section className={cn(card, "flex flex-col gap-3 p-5")}>
          <div>
            <Link href={recordsHref} className={buttonVariants({ variant: "outline", size: "sm" })}>
              <ArrowLeft /> {m("back")}
            </Link>
          </div>
          <p className="text-[13px] text-foreground-muted">{m("action_status")}</p>
          <div className="flex flex-wrap gap-2">
            {rtn && editable ? (
              <Button variant="outline" className="text-danger" onClick={() => setConfirm("delete")} disabled={pending}>
                <Trash2 /> {m("delete")}
              </Button>
            ) : null}
            <Button variant="outline" onClick={clear} disabled={pending}>
              <RotateCcw /> {m("clear")}
            </Button>
            {editable ? (
              <Button onClick={save} loading={pending}>
                <Save /> {rtn ? m("save_changes") : m("save")}
              </Button>
            ) : null}
            {rtn && editable ? (
              <Button variant="success" onClick={() => setConfirm("finalize")} disabled={pending}>
                <CheckCheck /> {m("finalize")}
              </Button>
            ) : null}
          </div>
        </section>
      </div>

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

export { AccReturnEntry };
