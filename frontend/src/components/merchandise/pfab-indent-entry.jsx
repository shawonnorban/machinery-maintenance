"use client";

import { useEffect, useMemo, useRef, useState, useTransition } from "react";
import { printDocument } from "@/lib/print-document";
import Link from "next/link";
import { ArrowLeft, Check, Plus, Printer, RotateCcw, Save, Search, Send, Trash2, Undo2, X } from "lucide-react";
import { cn } from "@/lib/utils";
import { Input } from "@/components/ui/input";
import { Select } from "@/components/ui/select";
import { Textarea } from "@/components/ui/textarea";
import { Button, buttonVariants } from "@/components/ui/button";
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
const blankHead = (date) => ({ req_date: date, order_id: "", buyer_office_id: "", supplier_id: "", ship_date: "", gsm: "", dia: "", remarks: "" });
const KEYS = ["style_fabric_id", "fabric_type_id", "description", "construction", "composition_id", "colour_id", "qty", "uom", "gsm", "dia", "supplier_id", "required_date", "remarks"];
let seq = 0;
const asLine = (l = {}) => ({ key: `i${++seq}`, ...Object.fromEntries(KEYS.map((k) => [k, k === "style_fabric_id" ? (l[k] ?? null) : k === "uom" ? str(l[k] ?? "KG") : str(l[k])])) });
const num = (v) => (v === "" || v === null || v === undefined ? 0 : Number(v));
const qtyFmt = new Intl.NumberFormat("en-US", { maximumFractionDigits: 4 });
const PAGE = 10;

/**
 * Purchased Fabric Indent → Fabric Import Requisition Entry (AnnotechERP
 * 06/06), as Brickland lays it out: the order list on the left (buyer
 * account, search, ten to a page, each with its ship date); picking one fills
 * the buyer, order, ship date and the style's purchased fabrics; Add item adds
 * a blank fabric. Saved whole, then Submitted, Approved or Rejected (a
 * rejected one Reopened); only a draft or rejected indent is changed.
 * Nothing reloads.
 *
 * @param {{ options: object, initial: ?object, canManage: boolean, canApprove: boolean, autoPrint: boolean, actions: object, recordsHref: string }} props
 */
function PfabIndentEntry({ options, initial, canManage, canApprove, autoPrint, actions, recordsHref }) {
  const t = useT("merchandise");
  const m = (k, p) => t(`master.apr.${k}`, p);
  const f = (k, p) => t(`master.pfi.${k}`, p);
  const toastManager = useToastManager();
  const [today] = useState(todayIso);
  const [req, setReq] = useState(initial);
  const [head, setHead] = useState(() => (initial ? Object.fromEntries(Object.keys(blankHead(today)).map((k) => [k, str(initial[k])])) : blankHead(today)));
  const [lines, setLines] = useState(() => (initial?.lines ?? []).map(asLine));
  const [errors, setErrors] = useState(null);
  const [notice, setNotice] = useState(null);
  const [buyerFilter, setBuyerFilter] = useState("");
  const [query, setQuery] = useState("");
  const [page, setPage] = useState(0);
  const [confirm, setConfirm] = useState(null);
  const [pending, startTransition] = useTransition();
  const printed = useRef(false);
  const editable = canManage && (!req || req.editable);
  const shown = useMemo(() => {
    const needle = query.trim().toLowerCase();
    return options.orders.filter((o) => (!buyerFilter || o.buyer_office_id === buyerFilter) && (!needle || `${o.order_no} ${o.job} ${o.style}`.toLowerCase().includes(needle)));
  }, [options.orders, buyerFilter, query]);
  const pages = Math.max(1, Math.ceil(shown.length / PAGE));
  const pick = (list) => [{ value: "", label: m("select") }, ...list];
  const setH = (patch) => setHead((h) => ({ ...h, ...patch }));
  const setLine = (key, patch) => setLines((all) => all.map((l) => (l.key === key ? { ...l, ...patch } : l)));
  const uoms = options.uoms.map((v) => ({ value: v, label: f(`uom.${v}`) }));
  const label = (list, id) => list.find((x) => x.value === id)?.label ?? "";

  function fillFrom(orderId) {
    setH({ order_id: orderId });
    startTransition(async () => {
      const result = await actions.fill(orderId);
      if (result?.status !== "success") return setErrors(messagesOf(result));
      setH({ order_id: orderId, buyer_office_id: str(result.data.buyer_office_id), ship_date: str(result.data.ship_date) });
      setLines(result.data.lines.map(asLine));
      setErrors(null);
      setNotice(result.data.lines.length ? null : f("no_fabrics"));
    });
  }

  function take(data) {
    setReq(data);
    setHead(Object.fromEntries(Object.keys(blankHead(today)).map((k) => [k, str(data[k])])));
    setLines(data.lines.map(asLine));
    setErrors(null);
    setNotice(null);
    window.history.replaceState(null, "", `?tab=entry&ref=${encodeURIComponent(data.req_no)}`);
  }

  function clear() {
    setReq(null);
    setHead(blankHead(today));
    setLines([]);
    setErrors(null);
    setNotice(null);
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
        lines: lines.map(({ key, ...l }) => ({ ...Object.fromEntries(Object.entries(l).map(([k, v]) => [k, typeof v === "string" ? blank(v) : v])), qty: l.qty === "" ? null : Number(l.qty) })),
      };
      const result = await actions.save(req?.req_no ?? null, payload);
      if (result?.status !== "success") return setErrors(messagesOf(result));
      take(result.data);
      toastManager.add({ title: m(req ? "updated" : "saved", { no: result.data.req_no }), type: "success" });
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

  // The indent on paper: its order and fabric spec, each fabric line, and who signs it.
  function print() {
    const order = options.orders.find((o) => o.value === head.order_id);
    printDocument({
      title: t("master.screens.purchase-fabric-pr"),
      docNo: req?.req_no,
      orientation: "landscape",
      metaColumns: 6,
      meta: [
        [m("f.req_date"), head.req_date],
        [m("f.order"), order?.order_no],
        [f("ship"), head.ship_date],
        ["GSM", head.gsm],
        ["DIA", head.dia],
        [m("f.status"), { v: m(`status.${req?.status ?? "DRAFT"}`), tone: "info" }],
      ],
      sections: [
        {
          columns: [{ label: "#", align: "center", width: "4%" }, ...["type", "description", "construction", "composition", "colour"].map((k) => ({ label: f(`col.${k}`) })), { label: f("col.qty"), align: "right" }, ...["uom", "gsm", "dia", "supplier"].map((k) => ({ label: f(`col.${k}`) })), { label: f("col.required_date"), nowrap: true }],
          rows: lines.map((l, i) => [i + 1, label(options.fabric_types, l.fabric_type_id), l.description, l.construction, label(options.compositions, l.composition_id), label(options.colours, l.colour_id), qtyFmt.format(num(l.qty)), f(`uom.${l.uom}`), l.gsm, l.dia, label(options.suppliers, l.supplier_id), l.required_date]),
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
  const cellSelect = (l, i, key, list, aria, width = "w-40") => (
    <div className={width}>
      <Select value={l[key]} onValueChange={(v) => setLine(l.key, { [key]: v ?? "" })} options={[{ value: "", label: "—" }, ...list]} disabled={!editable} aria-label={f(aria, { n: i + 1 })} />
    </div>
  );
  const cellInput = (l, i, key, aria, cls = "w-28", type = "text") => (
    <Input type={type} min={type === "number" ? "0" : undefined} step={type === "number" ? "any" : undefined} value={l[key]} onChange={(e) => setLine(l.key, { [key]: e.target.value })} className={cn("h-8", cls)} disabled={!editable} aria-label={f(aria, { n: i + 1 })} />
  );
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
                <th className="px-3 py-2 text-left text-[13px] font-semibold">{m("ol.order")}</th>
                <th className="px-3 py-2 text-left text-[13px] font-semibold">{m("ol.job_style")}</th>
                <th className="px-3 py-2 text-left text-[13px] font-semibold">{f("col.ship_date")}</th>
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
                  <td className="px-3 py-2 whitespace-nowrap">{day(o.ship_date)}</td>
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
              <h2 className="text-base font-semibold text-foreground">{f("entry_tab")}</h2>
              <p className="mt-0.5 text-[13px] text-foreground-muted">{f("entry_hint")}</p>
            </div>
            {req ? <span className="rounded-md bg-brand-subtle px-2 py-0.5 text-xs font-medium text-brand">{m(req.editable ? "editing" : "read_only")}</span> : null}
          </div>
          <div className={cn(field, "mt-4")}>
            <span className={lab}>{m("existing")}</span>
            <Select value={req?.req_no ?? ""} onValueChange={(v) => load(v ?? "")} options={[{ value: "", label: m("new_req") }, ...options.indents]} aria-label={m("existing")} />
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
              <Select value={head.order_id} onValueChange={(v) => v && fillFrom(v)} options={pick(options.orders.map(({ value, label: l }) => ({ value, label: l })))} placeholder={m("select")} disabled={!editable} aria-label={m("f.order")} />
              <span className={hint}>{m("order_hint")}</span>
            </div>
            <div className={field}>
              <span className={lab}>{m("f.supplier")}</span>
              <Select value={head.supplier_id} onValueChange={(v) => setH({ supplier_id: v ?? "" })} options={pick(options.suppliers)} placeholder={m("select")} disabled={!editable} aria-label={m("f.supplier")} />
              <span className={hint}>{m("supplier_hint")}</span>
            </div>
            <label className={field}>
              <span className={lab}>{f("ship")}</span>
              <Input type="date" value={head.ship_date} onChange={(e) => setH({ ship_date: e.target.value })} disabled={!editable} aria-label={f("ship")} />
            </label>
            <label className={field}>
              <span className={lab}>{f("gsm")}</span>
              <Input value={head.gsm} onChange={(e) => setH({ gsm: e.target.value })} placeholder={f("gsm_ph")} maxLength={40} disabled={!editable} aria-label={f("gsm")} />
            </label>
            <label className={field}>
              <span className={lab}>{f("dia")}</span>
              <Input value={head.dia} onChange={(e) => setH({ dia: e.target.value })} placeholder={f("dia_ph")} maxLength={40} disabled={!editable} aria-label={f("dia")} />
            </label>
            <label className={cn(field, "md:col-span-3")}>
              <span className={lab}>{m("f.remarks")}</span>
              <Textarea rows={3} value={head.remarks} onChange={(e) => setH({ remarks: e.target.value })} maxLength={2000} disabled={!editable} aria-label={m("f.remarks")} />
            </label>
          </div>
        </section>

        <section className={cn(card, "overflow-hidden")}>
          <div className="flex items-start justify-between gap-3 px-5 py-4">
            <div>
              <h2 className="text-base font-semibold text-foreground">{f("items_title")}</h2>
              <p className="mt-0.5 text-[13px] text-foreground-muted">{f("items_hint")}</p>
            </div>
            {editable ? (
              <Button variant="outline" size="sm" onClick={() => setLines((all) => [...all, asLine()])}>
                <Plus /> {m("add_item")}
              </Button>
            ) : null}
          </div>
          {notice ? <p className="px-5 pb-3 text-xs text-foreground-muted">{notice}</p> : null}
          <div className="overflow-x-auto">
            <table className="w-full text-sm">
              <thead className="bg-brand text-brand-foreground">
                <tr>
                  {["type", "description", "construction", "composition", "colour", "qty", "uom", "gsm", "dia", "supplier", "required_date", "remarks", "remove"].map((k) => (
                    <th key={k} className={cn("px-2 py-2.5 text-left text-[13px] font-semibold whitespace-nowrap", k === "qty" && "text-right")}>
                      {k === "remove" ? <span className="sr-only">{f("col.remove")}</span> : f(`col.${k}`)}
                    </th>
                  ))}
                </tr>
              </thead>
              <tbody>
                {lines.map((l, i) => (
                  <tr key={l.key} className="border-t border-border">
                    <td className="px-2 py-1.5">{cellSelect(l, i, "fabric_type_id", options.fabric_types, "row_type", "w-36")}</td>
                    <td className="px-2 py-1.5">{cellInput(l, i, "description", "row_description", "w-40")}</td>
                    <td className="px-2 py-1.5">{cellInput(l, i, "construction", "row_construction", "w-32")}</td>
                    <td className="px-2 py-1.5">{cellSelect(l, i, "composition_id", options.compositions, "row_composition", "w-44")}</td>
                    <td className="px-2 py-1.5">{cellSelect(l, i, "colour_id", options.colours, "row_colour", "w-36")}</td>
                    <td className="px-2 py-1.5">{cellInput(l, i, "qty", "row_qty", "w-24 text-right", "number")}</td>
                    <td className="px-2 py-1.5">{cellSelect(l, i, "uom", uoms, "row_uom", "w-24")}</td>
                    <td className="px-2 py-1.5">{cellInput(l, i, "gsm", "row_gsm", "w-20")}</td>
                    <td className="px-2 py-1.5">{cellInput(l, i, "dia", "row_dia", "w-24")}</td>
                    <td className="px-2 py-1.5">{cellSelect(l, i, "supplier_id", options.suppliers, "row_supplier", "w-56")}</td>
                    <td className="px-2 py-1.5">{cellInput(l, i, "required_date", "row_date", "w-36", "date")}</td>
                    <td className="px-2 py-1.5">{cellInput(l, i, "remarks", "row_remarks", "w-36")}</td>
                    <td className="px-2 py-1.5 text-right">
                      {editable ? (
                        <Button variant="outline" size="icon" className="size-8 text-danger" aria-label={m("row_remove", { n: i + 1 })} onClick={() => setLines((all) => all.filter((x) => x.key !== l.key))}>
                          <Trash2 />
                        </Button>
                      ) : null}
                    </td>
                  </tr>
                ))}
                {lines.length === 0 ? (
                  <tr>
                    <td colSpan={13} className="px-5 py-8 text-center">
                      <p className="text-sm font-semibold text-foreground">{m("no_lines")}</p>
                      <p className="mt-1 text-xs text-foreground-muted">{f("no_lines_hint")}</p>
                    </td>
                  </tr>
                ) : null}
              </tbody>
            </table>
          </div>
          {lines.length ? <div className="border-t border-border px-5 py-3 text-sm font-semibold text-foreground">{f("total", { n: lines.length, qty: qtyFmt.format(lines.reduce((n, l) => n + num(l.qty), 0)) })}</div> : null}
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

export { PfabIndentEntry };
