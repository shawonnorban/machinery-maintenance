"use client";

import { useEffect, useMemo, useRef, useState, useTransition } from "react";
import { printDocument } from "@/lib/print-document";
import Link from "next/link";
import { ArrowLeft, Check, FileText, Plus, Printer, RotateCcw, Save, Search, Send, ShoppingCart, Trash2, Undo2, X } from "lucide-react";
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
const blankHead = (date) => ({ req_date: date, order_id: "", buyer_office_id: "", supplier_id: "", addition_for: "", remarks: "" });
let seq = 0;
const asLine = (l) => ({ key: `l${++seq}`, bom_line_id: l.bom_line_id ?? null, item_id: str(l.item_id), item_code: str(l.item_code), description: str(l.description), uom: str(l.uom), required_qty: str(l.required_qty), stock_qty: Number(l.stock_qty ?? 0), required_date: str(l.required_date), supplier_id: str(l.supplier_id), remarks: str(l.remarks) });
const num = (v) => (v === "" || v === null || v === undefined ? 0 : Number(v));
const fmt = new Intl.NumberFormat("en-US", { minimumFractionDigits: 3, maximumFractionDigits: 3 });
const PAGE = 10;

/**
 * Accessories Purchase Indent → Requisition Entry (AnnotechERP 06/06), as
 * Brickland lays it out: the order list on the left (buyer account, search,
 * ten to a page, each with its approved BOM), and on the right the
 * requisition — picking an order fills the item list from its BOM
 * (required, in stock, short), Add item adds a blank line — saved whole,
 * then Submitted, Approved or Rejected, a rejected one Reopened. Only a
 * draft or rejected requisition is changed. Nothing reloads.
 *
 * `dummy` raises the Dummy Order Accessories Indent instead, under its own `title` and `subtitle`.
 *
 * @param {{ options: object, initial: ?object, prefill: ?object, canManage: boolean, canApprove: boolean, autoPrint: boolean, actions: object, recordsHref: string, poHref: string, dummy?: boolean, title?: string, subtitle?: string }} props
 */
function AccRequisitionEntry({ options, initial, prefill, canManage, canApprove, autoPrint, actions, recordsHref, poHref, dummy = false, title, subtitle }) {
  const t = useT("merchandise");
  const m = (k, p) => t(`master.apr.${k}`, p);
  const toastManager = useToastManager();
  const [today] = useState(todayIso);
  const [req, setReq] = useState(initial);
  const [head, setHead] = useState(() => (initial ? { ...blankHead(today), ...Object.fromEntries(Object.keys(blankHead(today)).map((k) => [k, str(initial[k])])) } : { ...blankHead(today), order_id: prefill?.order_id ?? "", buyer_office_id: prefill?.buyer_office_id ?? "" }));
  // An order named in the address (Add PR on the BOM tab) arrives with its BOM lines.
  const [bom, setBom] = useState(initial?.bom ?? prefill?.bom ?? null);
  const [lines, setLines] = useState(() => (initial?.lines ?? prefill?.lines ?? []).map(asLine));
  const [errors, setErrors] = useState(null);
  const [buyerFilter, setBuyerFilter] = useState("");
  const [query, setQuery] = useState("");
  const [page, setPage] = useState(0);
  const [confirm, setConfirm] = useState(null);
  const [pending, startTransition] = useTransition();
  const printed = useRef(false);
  const editable = canManage && (!req || req.editable);
  const order = options.orders.find((o) => o.value === head.order_id);
  const shown = useMemo(() => {
    const needle = query.trim().toLowerCase();
    return options.orders.filter((o) => (!buyerFilter || o.buyer_office_id === buyerFilter) && (!needle || `${o.order_no} ${o.job} ${o.style}`.toLowerCase().includes(needle)));
  }, [options.orders, buyerFilter, query]);
  const pages = Math.max(1, Math.ceil(shown.length / PAGE));
  const pick = (list) => [{ value: "", label: m("select") }, ...list.map(({ value, label }) => ({ value, label }))];
  const setH = (patch) => setHead((h) => ({ ...h, ...patch }));
  const setLine = (key, patch) => setLines((all) => all.map((l) => (l.key === key ? { ...l, ...patch } : l)));
  const totalRequired = lines.reduce((n, l) => n + num(l.required_qty), 0);
  const totalShort = lines.reduce((n, l) => n + Math.max(0, num(l.required_qty) - l.stock_qty), 0);

  function fillFrom(orderId) {
    setH({ order_id: orderId, buyer_office_id: options.orders.find((o) => o.value === orderId)?.buyer_office_id ?? "" });
    startTransition(async () => {
      const result = await actions.bom(orderId);
      if (result?.status !== "success") return setErrors(messagesOf(result));
      setBom(result.data.bom);
      setLines(result.data.lines.map(asLine));
      setErrors(result.data.bom ? null : [m("no_bom")]);
    });
  }

  function load(no) {
    if (!no) return clear();
    startTransition(async () => {
      const result = await actions.load(no);
      if (result?.status !== "success") return setErrors(messagesOf(result));
      take(result.data);
    });
  }

  function take(data) {
    setReq(data);
    setHead(Object.fromEntries(Object.keys(blankHead(today)).map((k) => [k, str(data[k])])));
    setBom(data.bom);
    setLines(data.lines.map(asLine));
    setErrors(null);
    window.history.replaceState(null, "", `?tab=entry&ref=${encodeURIComponent(data.req_no)}`);
  }

  function clear() {
    setReq(null);
    setHead(blankHead(today));
    setBom(null);
    setLines([]);
    setErrors(null);
    window.history.replaceState(null, "", "?tab=entry");
  }

  function save() {
    startTransition(async () => {
      const payload = {
        dummy,
        ...Object.fromEntries(Object.entries(head).map(([k, v]) => [k, v === "" ? null : v])),
        lines: lines.map((l) => ({ bom_line_id: l.bom_line_id, item_id: l.item_id || null, description: l.description || null, uom: l.uom || null, required_qty: l.required_qty === "" ? null : Number(l.required_qty), required_date: l.required_date || null, supplier_id: l.supplier_id || null, remarks: l.remarks || null })),
      };
      const result = await actions.save(req?.req_no ?? null, payload);
      if (result?.status === "success") {
        take(result.data);
        toastManager.add({ title: m(req ? "updated" : "saved", { no: result.data.req_no }), type: "success" });
      } else setErrors(messagesOf(result));
    });
  }

  function act(action) {
    startTransition(async () => {
      const result = action === "delete" ? await actions.remove(req.req_no) : await actions.transition(req.req_no, action);
      setConfirm(null);
      if (result?.status !== "success") return setErrors(messagesOf(result));
      toastManager.add({ title: m(`done.${action}`, { no: req.req_no }), type: "success" });
      if (action === "delete") clear();
      else take(result.data);
    });
  }

  // The requisition on paper: its order and BOM, each accessory's need against stock, and who signs it.
  function print() {
    const supplier = (id) => options.suppliers.find((s) => s.value === id)?.label ?? null;
    const right = { align: "right" };
    printDocument({
      title: m("title"),
      docNo: req?.req_no,
      meta: [
        [m("f.req_date"), head.req_date],
        [m("f.order"), order?.order_no],
        ["BOM", bom],
        [m("f.status"), { v: m(`status.${req?.status ?? "DRAFT"}`), tone: "info" }],
      ],
      metaColumns: 4,
      orientation: "landscape",
      sections: [
        {
          columns: [{ label: "#", align: "center", width: "4%" }, { label: m("col.item") }, { label: m("col.code"), nowrap: true }, { label: "UOM" }, { label: m("col.required"), ...right }, { label: m("col.stock"), ...right }, { label: m("col.short"), ...right }, { label: m("col.required_date"), nowrap: true }, { label: m("col.supplier") }],
          rows: lines.map((l, i) => [i + 1, l.description, l.item_code, l.uom, fmt.format(num(l.required_qty)), fmt.format(l.stock_qty), fmt.format(Math.max(0, num(l.required_qty) - l.stock_qty)), l.required_date, supplier(l.supplier_id)]),
        },
      ],
      signatures: true,
    });
  }

  useEffect(() => {
    if (autoPrint && req && !printed.current) {
      printed.current = true;
      print();
    }
  });

  const card = "rounded-xl border border-border bg-surface";
  const field = "flex flex-col gap-1.5";
  const lab = "text-[13px] font-semibold text-foreground";
  const hint = "text-xs text-foreground-muted";
  const status = req?.status ?? "DRAFT";
  return (
    <div className="grid gap-4 xl:grid-cols-[22rem_1fr]">
      <section className={cn(card, "self-start p-4")}>
        <h2 className="text-base font-semibold text-foreground">{m("order_list")}</h2>
        <div className="mt-3 flex flex-col gap-1.5">
          <span className={lab}>{m("f.buyer_ac")}</span>
          <Select value={buyerFilter} onValueChange={(v) => (setBuyerFilter(v ?? ""), setPage(0))} options={[{ value: "", label: m("any_buyer") }, ...options.offices]} aria-label={m("filter_buyer")} />
        </div>
        <div className="relative mt-3">
          <Search className="pointer-events-none absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-foreground-muted" />
          <Input value={query} onChange={(e) => (setQuery(e.target.value), setPage(0))} placeholder={m("search_orders")} className="pl-8" aria-label={m("search_orders")} />
        </div>
        <div className="mt-3 overflow-hidden rounded-lg border border-border">
          <table className="w-full text-sm">
            <thead className="bg-brand text-brand-foreground">
              <tr>
                {["order", "job_style", "bom"].map((k) => (
                  <th key={k} className="px-3 py-2 text-left text-[13px] font-semibold">
                    {m(`ol.${k}`)}
                  </th>
                ))}
              </tr>
            </thead>
            <tbody>
              {shown.slice(page * PAGE, page * PAGE + PAGE).map((o) => (
                <tr key={o.value} className={cn("border-t border-border", head.order_id === o.value && "bg-brand-subtle")}>
                  <td className="px-3 py-2">
                    <button type="button" className="text-left font-medium text-brand hover:underline disabled:text-foreground-muted" onClick={() => fillFrom(o.value)} disabled={!editable}>
                      {o.order_no}
                    </button>
                  </td>
                  <td className="px-3 py-2">
                    {o.job} · {o.style}
                  </td>
                  <td className="px-3 py-2">{o.bom ?? <span className="text-foreground-muted">{m("no_approved_bom")}</span>}</td>
                </tr>
              ))}
              {shown.length === 0 ? (
                <tr>
                  <td colSpan={3} className="px-3 py-6 text-center text-foreground-muted">
                    {m("no_orders")}
                  </td>
                </tr>
              ) : null}
            </tbody>
          </table>
        </div>
        <div className="mt-2 flex items-center justify-between text-xs text-foreground-muted">
          <span>{m("showing", { from: shown.length ? page * PAGE + 1 : 0, to: Math.min(shown.length, page * PAGE + PAGE), total: shown.length })}</span>
          <span className="flex items-center gap-1">
            <Button variant="outline" size="sm" onClick={() => setPage((p) => Math.max(0, p - 1))} disabled={page === 0}>
              ‹
            </Button>
            {page + 1} / {pages}
            <Button variant="outline" size="sm" onClick={() => setPage((p) => Math.min(pages - 1, p + 1))} disabled={page >= pages - 1}>
              ›
            </Button>
          </span>
        </div>
      </section>

      <div className="flex flex-col gap-4">
        <section className={cn(card, "p-5")}>
          <div className="flex items-start justify-between gap-3">
            <div>
              <h2 className="text-base font-semibold text-foreground">{title ?? m("entry")}</h2>
              <p className="mt-0.5 text-[13px] text-foreground-muted">{subtitle ?? m("entry_hint")}</p>
            </div>
            {req ? <span className="rounded-md bg-brand-subtle px-2 py-0.5 text-xs font-medium text-brand">{m(req.editable ? "editing" : "read_only")}</span> : null}
          </div>
          <div className={cn(field, "mt-4")}>
            <span className={lab}>{m("existing")}</span>
            <Select value={req?.req_no ?? ""} onValueChange={(v) => load(v ?? "")} options={[{ value: "", label: m("new_req") }, ...options.requisitions]} aria-label={m("existing")} />
            <span className={hint}>{m("existing_hint")}</span>
          </div>
          <div className="mt-4 grid gap-4 md:grid-cols-3">
            <div className={field}>
              <span className={lab}>{m("f.req_no")}</span>
              <Input value={req?.req_no ?? ""} placeholder={m("auto_gen")} readOnly className="bg-surface-muted" aria-label={m("f.req_no")} />
            </div>
            <label className={field}>
              <span className={lab}>
                {m("f.req_date")}
                <span className="text-danger">*</span>
              </span>
              <Input type="date" value={head.req_date} onChange={(e) => setH({ req_date: e.target.value })} disabled={!editable} aria-label={m("f.req_date")} />
            </label>
            <div className={field}>
              <span className={lab}>{m("f.status")}</span>
              <Input value={m(`status.${status}`)} readOnly className="bg-surface-muted" aria-label={m("f.status")} />
            </div>
            <div className={field}>
              <span className={lab}>{m("f.buyer_ac")}</span>
              <Select value={head.buyer_office_id} onValueChange={(v) => setH({ buyer_office_id: v ?? "" })} options={pick(options.offices)} placeholder={m("select")} disabled aria-label={m("f.buyer_ac")} />
              <span className={hint}>{m("buyer_hint")}</span>
            </div>
            <div className={field}>
              <span className={lab}>{m("f.order")}</span>
              <Select value={head.order_id} onValueChange={(v) => v && fillFrom(v)} options={pick(options.orders)} placeholder={m("select")} disabled={!editable} aria-label={m("f.order")} />
              <span className={hint}>{m("order_hint")}</span>
            </div>
            <div className={field}>
              <span className={lab}>{m("f.supplier")}</span>
              <Select value={head.supplier_id} onValueChange={(v) => setH({ supplier_id: v ?? "" })} options={pick(options.suppliers)} placeholder={m("select")} disabled={!editable} aria-label={m("f.supplier")} />
              <span className={hint}>{m("supplier_hint")}</span>
            </div>
            <label className={field}>
              <span className={lab}>{m("f.addition_for")}</span>
              <Input value={head.addition_for} onChange={(e) => setH({ addition_for: e.target.value })} placeholder={m("addition_placeholder")} maxLength={120} disabled={!editable} aria-label={m("f.addition_for")} />
              <span className={hint}>{m("addition_hint")}</span>
            </label>
            <div className={field}>
              <span className={lab}>BOM</span>
              <Input value={bom ?? ""} placeholder={m("bom_placeholder")} readOnly className="bg-surface-muted" aria-label="BOM" />
            </div>
            <label className={cn(field, "md:col-span-3")}>
              <span className={lab}>{m("f.remarks")}</span>
              <Textarea rows={3} value={head.remarks} onChange={(e) => setH({ remarks: e.target.value })} maxLength={2000} disabled={!editable} aria-label={m("f.remarks")} />
            </label>
          </div>
        </section>

        <section className={cn(card, "overflow-hidden")}>
          <div className="flex items-start justify-between gap-3 px-5 py-4">
            <div>
              <h2 className="text-base font-semibold text-foreground">{m("item_list")}</h2>
              <p className="mt-0.5 text-[13px] text-foreground-muted">{m("item_hint")}</p>
            </div>
            {editable ? (
              <Button variant="outline" size="sm" onClick={() => setLines((all) => [...all, asLine({})])}>
                <Plus /> {m("add_item")}
              </Button>
            ) : null}
          </div>
          {lines.length === 0 ? (
            <div className="px-5 py-10 text-center">
              <p className="text-sm font-semibold text-foreground">{m("no_lines")}</p>
              <p className="mt-1 text-xs text-foreground-muted">{m("no_lines_hint")}</p>
            </div>
          ) : (
            <div className="overflow-x-auto">
              <table className="w-full text-sm">
                <thead className="bg-brand text-brand-foreground">
                  <tr>
                    {["item", "code", "description", "uom", "required", "stock", "short", "required_date", "supplier", "remarks", "remove"].map((k) => (
                      <th key={k} className={cn("px-2 py-2.5 text-left text-[13px] font-semibold whitespace-nowrap", ["required", "stock", "short"].includes(k) && "text-right")}>
                        {m(`col.${k}`)}
                      </th>
                    ))}
                  </tr>
                </thead>
                <tbody>
                  {lines.map((l, i) => {
                    const item = options.items.find((x) => x.value === l.item_id);
                    return (
                      <tr key={l.key} className="border-t border-border">
                        <td className="px-2 py-1.5">
                          <div className="w-60">
                            <Select value={l.item_id} onValueChange={(v) => { const it = options.items.find((x) => x.value === v); setLine(l.key, { item_id: v ?? "", uom: l.uom || it?.uom || "", description: l.description || it?.name || "" }); }} options={[{ value: "", label: "—" }, ...options.items.map(({ value, label }) => ({ value, label }))]} disabled={!editable} aria-label={m("row_item", { n: i + 1 })} />
                          </div>
                        </td>
                        <td className="px-2 py-1.5 whitespace-nowrap">{item?.code ?? (l.item_code || "—")}</td>
                        <td className="px-2 py-1.5">
                          <Input value={l.description} onChange={(e) => setLine(l.key, { description: e.target.value })} maxLength={200} className="h-8 w-44" disabled={!editable} aria-label={m("row_description", { n: i + 1 })} />
                        </td>
                        <td className="px-2 py-1.5">
                          <UomSelect value={l.uom} onChange={(v) => setLine(l.key, { uom: v })} size="sm" className="w-32" disabled={!editable} aria-label={m("row_uom", { n: i + 1 })} />
                        </td>
                        <td className="px-2 py-1.5">
                          <Input type="number" min="0" step="any" value={l.required_qty} onChange={(e) => setLine(l.key, { required_qty: e.target.value })} className="h-8 w-28 text-right" disabled={!editable} aria-label={m("row_required", { n: i + 1 })} />
                        </td>
                        <td className="px-2 py-1.5 text-right tabular-nums">{fmt.format(l.stock_qty)}</td>
                        <td className="px-2 py-1.5 text-right tabular-nums">{fmt.format(Math.max(0, num(l.required_qty) - l.stock_qty))}</td>
                        <td className="px-2 py-1.5">
                          <Input type="date" value={l.required_date} onChange={(e) => setLine(l.key, { required_date: e.target.value })} className="h-8 w-36" disabled={!editable} aria-label={m("row_date", { n: i + 1 })} />
                        </td>
                        <td className="px-2 py-1.5">
                          <div className="w-64">
                            <Select value={l.supplier_id} onValueChange={(v) => setLine(l.key, { supplier_id: v ?? "" })} options={[{ value: "", label: "—" }, ...options.suppliers]} disabled={!editable} aria-label={m("row_supplier", { n: i + 1 })} />
                          </div>
                        </td>
                        <td className="px-2 py-1.5">
                          <Input value={l.remarks} onChange={(e) => setLine(l.key, { remarks: e.target.value })} maxLength={200} className="h-8 w-36" disabled={!editable} aria-label={m("row_remarks", { n: i + 1 })} />
                        </td>
                        <td className="px-2 py-1.5">
                          {editable ? (
                            <Button variant="outline" size="icon" className="size-8 text-danger" aria-label={m("row_remove", { n: i + 1 })} onClick={() => setLines((all) => all.filter((x) => x.key !== l.key))}>
                              <Trash2 />
                            </Button>
                          ) : null}
                        </td>
                      </tr>
                    );
                  })}
                </tbody>
              </table>
            </div>
          )}
          <div className="flex flex-wrap items-center gap-6 border-t border-border px-5 py-3 text-sm">
            <span className="font-semibold text-foreground">{m("total", { n: lines.length })}</span>
            <span className="text-foreground-muted">
              {m("col.required")} <span className="font-semibold text-foreground tabular-nums">{fmt.format(totalRequired)}</span>
            </span>
            <span className="text-foreground-muted">
              {m("col.short")} <span className="font-semibold text-foreground tabular-nums">{fmt.format(totalShort)}</span>
            </span>
          </div>
        </section>

        {errors ? <Alert variant="danger">{errors.join(" · ")}</Alert> : null}
        <section className={cn(card, "flex flex-col gap-3 p-5")}>
          <div>
            <Link href={recordsHref} className={buttonVariants({ variant: "outline", size: "sm" })}>
              <ArrowLeft /> {m("back")}
            </Link>
          </div>
          <p className="text-[13px] text-foreground-muted">{m("rules")}</p>
          <div className="flex flex-wrap gap-2">
            {req && editable ? (
              <Button variant="outline" className="text-danger" onClick={() => setConfirm("delete")} disabled={pending}>
                <Trash2 /> {m("delete")}
              </Button>
            ) : null}
            <Button variant="outline" onClick={clear} disabled={pending}>
              <RotateCcw /> {m("clear")}
            </Button>
            {editable ? (
              <Button onClick={save} loading={pending}>
                <Save /> {req ? m("save_changes") : m("save")}
              </Button>
            ) : null}
            {canManage && req?.status === "DRAFT" ? (
              <Button variant="outline" onClick={() => act("submit")} disabled={pending}>
                <Send /> {m("submit")}
              </Button>
            ) : null}
            {canApprove && req?.status === "SUBMITTED" ? (
              <>
                <Button variant="success" onClick={() => setConfirm("approve")} disabled={pending}>
                  <Check /> {m("approve")}
                </Button>
                <Button variant="outline" className="text-danger" onClick={() => setConfirm("reject")} disabled={pending}>
                  <X /> {m("reject")}
                </Button>
              </>
            ) : null}
            {canManage && req?.status === "REJECTED" ? (
              <Button variant="outline" onClick={() => act("reopen")} disabled={pending}>
                <Undo2 /> {m("reopen")}
              </Button>
            ) : null}
            {req ? (
              <Button variant="outline" onClick={print}>
                <Printer /> {m("print")}
              </Button>
            ) : null}
            {req ? (
              req.status === "APPROVED" && poHref ? (
                <Link href={`${poHref}${encodeURIComponent(req.req_no)}`} className={buttonVariants({ variant: "outline" })}>
                  <ShoppingCart /> {m("purchase_order")}
                </Link>
              ) : (
                <Button variant="outline" disabled title={m("po_hint")}>
                  <FileText /> {m("purchase_order")}
                </Button>
              )
            ) : null}
          </div>
        </section>
      </div>

      <ConfirmDialog
        open={confirm !== null}
        onOpenChange={(o) => !o && setConfirm(null)}
        title={confirm ? m(`confirm.${confirm}_title`) : ""}
        description={confirm ? m(`confirm.${confirm}_hint`) : ""}
        confirmLabel={confirm ? m(confirm) : ""}
        cancelLabel={m("cancel")}
        destructive={confirm !== "approve"}
        onConfirm={() => confirm && act(confirm)}
        loading={pending}
      />
    </div>
  );
}

export { AccRequisitionEntry };
