"use client";

import { Fragment, useMemo, useState, useTransition } from "react";
import Link from "next/link";
import { useRouter } from "next/navigation";
import { ArrowLeft, ChevronDown, ChevronRight, FilePlus2, FileSearch, Info, Pencil, Plus, Save, Send, Trash2 } from "lucide-react";
import { cn } from "@/lib/utils";
import { Button, buttonVariants } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Select } from "@/components/ui/select";
import { Textarea } from "@/components/ui/textarea";
import { Alert } from "@/components/ui/alert";
import { ConfirmDialog } from "@/components/ui/confirm-dialog";
import { useToastManager } from "@/components/ui/toast";
import { UomProvider, UomSelect } from "@/components/erp/uom-select";
import { useT } from "@/lib/i18n";
import { clockFormat } from "@/lib/clock";

/*
 * Material Procurement → Purchase Requisition (AnnotechERP 08/03), as Brickland
 * draws it. Create Requisition: the two ways in (by confirmed order, or
 * direct), then the requisition — who raises it, the order it is for, and
 * its fabric and accessories lines from the master data — saved as a draft
 * or submitted. My Requisitions: the pills, and each requisition opening in
 * place to its lines, with Edit draft, Submit to all suppliers and Delete
 * draft while it is a draft.
 */

const card = "rounded-xl border border-border bg-surface";
const KINDS = ["FABRIC", "TRIMS"];
const dateFmt = new Intl.DateTimeFormat("en-GB", { day: "2-digit", month: "short", year: "numeric", timeZone: "Asia/Dhaka" });
const dateTimeFmt = clockFormat({ day: "2-digit", month: "short", year: "numeric", hour: "2-digit", minute: "2-digit", timeZone: "Asia/Dhaka" });
const day = (iso) => (iso ? dateFmt.format(new Date(iso)) : "—");
const qty = (n) => Number(n ?? 0).toLocaleString("en-US", { maximumFractionDigits: 3 });
const dash = (v) => (v === null || v === undefined || v === "" ? "—" : v);
const messagesOf = (result) => {
  const fields = [...new Set(Object.values(result?.errors ?? {}).flat())];
  return fields.length ? fields : [result?.message ?? ""];
};
const LINE_TONE = { OPEN_FOR_QUOTATION: "bg-info-subtle text-info", APPROVED: "bg-success-subtle text-success", REJECTED: "bg-danger-subtle text-danger" };
const STATUS_TONE = { DRAFT: "bg-surface-muted text-foreground-muted", SUBMITTED: "bg-info-subtle text-info", REJECTED: "bg-danger-subtle text-danger", APPROVED: "bg-success-subtle text-success" };

/** A label over a value, as the requisition's header shows its facts. */
function Fact({ label, value }) {
  return (
    <div className="flex flex-col gap-1">
      <span className="text-[11px] font-semibold uppercase tracking-wide text-foreground-muted">{label}</span>
      <span className="text-sm text-foreground">{dash(value)}</span>
    </div>
  );
}

function FieldBox({ label, required, children, className }) {
  return (
    <label className={cn("flex min-w-0 flex-col gap-1.5", className)}>
      <span className="text-[13px] font-medium text-foreground">
        {label}
        {required ? <span className="text-danger"> *</span> : null}
      </span>
      {children}
    </label>
  );
}

/** What a picked order brings: its buyer, style, quantity, delivery date and people. */
function OrderFacts({ order }) {
  const t = useT("materialprocurement");
  const f = (k) => t(`pr.form.${k}`);
  return (
    <div className="grid gap-x-6 gap-y-4 sm:grid-cols-2 lg:grid-cols-3">
      <Fact label={f("buyer")} value={order.buyer} />
      <Fact label={f("style_no")} value={order.style_no} />
      <Fact label={f("order_qty")} value={`${qty(order.order_qty)} ${order.order_unit ? order.order_unit.charAt(0) + order.order_unit.slice(1).toLowerCase() : ""}`.trim()} />
      <Fact label={f("delivery_date")} value={day(order.delivery_date)} />
      <Fact label={f("merchandiser")} value={order.merchandiser} />
      {order.buyer_contact !== undefined ? <Fact label={f("buyer_contact")} value={order.buyer_contact} /> : null}
      {order.order_status ? <Fact label={f("order_status")} value={order.order_status.toLowerCase()} /> : null}
    </div>
  );
}

const blankLine = (kind) => ({ key: `${kind}-${Math.random().toString(36).slice(2)}`, kind, item_id: "", req_qty: "", uom: "", req_date: "", specification: "", remarks: "" });
const lineOf = (l) => ({ key: l.id, kind: l.kind, item_id: l.item_id ?? "", req_qty: String(l.req_qty ?? ""), uom: l.uom ?? "", req_date: l.req_date ?? "", specification: l.specification ?? "", remarks: l.remarks ?? "" });

/**
 * Create Requisition: the choice, then the form. A draft opened from My
 * Requisitions (`?draft=PR-2026-00007`) goes straight to its form.
 *
 * @param {{ options: object, draft: ?object, canManage: boolean, save: (no: ?string, payload: object) => Promise<object> }} props
 */
function CreateRequisition({ options, draft, canManage, save }) {
  const t = useT("materialprocurement");
  const f = (k, p) => t(`pr.form.${k}`, p);
  const router = useRouter();
  const toastManager = useToastManager();
  const [type, setType] = useState(draft?.req_type ?? null);
  const [orderId, setOrderId] = useState(draft?.order_id ?? "");
  const [lines, setLines] = useState(() => (draft ? draft.lines.map(lineOf) : [blankLine("FABRIC")]));
  const [tab, setTab] = useState("FABRIC");
  const [remarks, setRemarks] = useState(draft?.remarks ?? "");
  const [errors, setErrors] = useState(null);
  const [pending, startTransition] = useTransition();
  const order = options.orders.find((o) => o.value === orderId);
  const items = useMemo(() => Object.fromEntries(KINDS.flatMap((k) => (options.items[k] ?? []).map((i) => [i.value, i]))), [options.items]);
  const raiser = draft ? { name: draft.created_by_name, employee_code: draft.employee_code, department: draft.department, designation: draft.designation, contact: draft.contact } : options.raiser;

  if (!type) {
    const ways = [
      ["BY_ORDER", FileSearch, "bg-brand-subtle text-brand", "by_order"],
      ["DIRECT", FilePlus2, "bg-success-subtle text-success", "direct"],
    ];
    return (
      <div className="grid gap-4 md:grid-cols-2">
        {ways.map(([key, Icon, tone, k]) => (
          <section key={key} className={cn(card, "flex flex-col items-center gap-3 px-6 py-8 text-center")}>
            <span className={cn("flex size-14 items-center justify-center rounded-xl", tone)}>
              <Icon className="size-6" aria-hidden />
            </span>
            <h2 className="text-base font-semibold text-foreground">{t(`pr.choose.${k}_title`)}</h2>
            <div className="text-sm text-foreground-muted">
              <p>{t(`pr.choose.${k}_line1`)}</p>
              <p>{t(`pr.choose.${k}_line2`)}</p>
            </div>
            <Button onClick={() => setType(key)} disabled={!canManage}>
              {t(`pr.choose.${k}_btn`)}
            </Button>
          </section>
        ))}
      </div>
    );
  }

  const setLine = (key, patch) => setLines((all) => all.map((l) => (l.key === key ? { ...l, ...patch } : l)));
  const pickItem = (key, id) => {
    const item = items[id];
    setLines((all) => all.map((l) => (l.key === key ? { ...l, item_id: id ?? "", uom: item?.uom ?? l.uom, specification: l.specification || item?.specification || "" } : l)));
  };
  const shown = lines.filter((l) => l.kind === tab);

  function send(submit) {
    setErrors(null);
    const payload = {
      req_type: type,
      order_id: type === "BY_ORDER" ? orderId || null : null,
      remarks: remarks || null,
      submit,
      lines: lines.map(({ kind, item_id, req_qty, uom, req_date, specification, remarks: r }) => ({
        kind,
        item_id: item_id || null,
        req_qty: req_qty === "" ? null : req_qty,
        uom: uom || null,
        req_date: req_date || null,
        specification: specification || null,
        remarks: r || null,
      })),
    };
    startTransition(async () => {
      const result = await save(draft?.pr_no ?? null, payload);
      if (result?.status !== "success") return setErrors(messagesOf(result));
      toastManager.add({ title: f(submit ? "submitted" : "saved", { no: result.data.pr_no }), type: "success" });
      router.push(`/material-procurement/my-requisitions?open=${encodeURIComponent(result.data.pr_no)}`);
    });
  }

  return (
    <UomProvider uoms={options.uoms}>
      <div className="flex flex-col gap-4">
        <section className={cn(card, "flex flex-col gap-5 p-5")}>
          <div className="flex flex-wrap items-start justify-between gap-3">
            <div>
              <h2 className="text-base font-semibold text-foreground">{f(`title_${type}`)}</h2>
              <p className="mt-0.5 text-[13px] text-foreground-muted">{f(`hint_${type}`)}</p>
            </div>
            {draft ? null : (
              <Button variant="outline" size="sm" onClick={() => (setType(null), setErrors(null))}>
                <ArrowLeft /> {f("change_option")}
              </Button>
            )}
          </div>
          <div className="grid gap-x-6 gap-y-4 sm:grid-cols-2 lg:grid-cols-3">
            <Fact label={f("pr_no")} value={draft ? draft.pr_no : f("on_save", { no: options.pr_no })} />
            <Fact label={f("req_date")} value={day(draft?.req_date ?? options.today)} />
            <Fact label={f("created_by")} value={raiser.name} />
            <Fact label={f("employee_id")} value={raiser.employee_code} />
            <Fact label={f("department")} value={raiser.department} />
            <Fact label={f("designation")} value={raiser.designation} />
            <Fact label={f("contact")} value={raiser.contact} />
          </div>
          {type === "BY_ORDER" ? (
            <div className="flex flex-col gap-4 rounded-lg border border-border p-4">
              <FieldBox label={f("order_no")} required>
                <Select
                  value={orderId}
                  onValueChange={(v) => setOrderId(v ?? "")}
                  options={options.orders.map((o) => ({ value: o.value, label: o.label }))}
                  placeholder={f("order_placeholder")}
                  disabled={!canManage || pending}
                  aria-label={f("order_no")}
                />
              </FieldBox>
              {order ? <OrderFacts order={order} /> : <p className="text-sm text-foreground-muted">{f("order_hint")}</p>}
            </div>
          ) : null}
        </section>

        <section className={cn(card, "flex flex-col gap-4 p-5")}>
          <div className="flex flex-wrap items-start justify-between gap-3">
            <div>
              <h2 className="text-base font-semibold text-foreground">{f("materials")}</h2>
              <p className="mt-0.5 text-[13px] text-foreground-muted">{f("materials_hint")}</p>
            </div>
            <div role="tablist" className="flex gap-2">
              {KINDS.map((k) => (
                <button
                  key={k}
                  type="button"
                  role="tab"
                  aria-selected={tab === k}
                  onClick={() => setTab(k)}
                  className={cn("inline-flex items-center gap-2 rounded-full border px-3 py-1 text-[13px] font-medium", tab === k ? "border-brand bg-brand text-brand-foreground" : "border-border text-foreground hover:bg-surface-muted")}
                >
                  {t(`pr.kind.${k}`)}
                  <span className={cn("rounded-full px-1.5 text-xs tabular-nums", tab === k ? "bg-brand-foreground/20" : "bg-surface-muted")}>{lines.filter((l) => l.kind === k && l.item_id).length}</span>
                </button>
              ))}
            </div>
          </div>
          {shown.length === 0 ? <p className="text-sm text-foreground-muted">{f(`none_${tab}`)}</p> : null}
          {shown.map((l, i) => {
            const item = items[l.item_id];
            const facts = tab === "FABRIC" ? ["category", "colour", "gsm", "width"] : ["category", "colour", "size", "uom"];
            return (
              <div key={l.key} className="flex flex-col gap-4 rounded-lg border border-border p-4">
                <div className="flex items-center justify-between">
                  <span className="text-sm font-semibold text-foreground">{f("line", { n: i + 1 })}</span>
                  <Button variant="outline" size="sm" onClick={() => setLines((all) => all.filter((x) => x.key !== l.key))} disabled={!canManage || pending} aria-label={f("remove_line")} title={f("remove_line")}>
                    <Trash2 />
                  </Button>
                </div>
                <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-6">
                  <FieldBox label={f("item")} required className="lg:col-span-2">
                    <Select
                      value={l.item_id}
                      onValueChange={(v) => pickItem(l.key, v)}
                      options={(options.items[tab] ?? []).map((o) => ({ value: o.value, label: o.label }))}
                      placeholder={f("select")}
                      disabled={!canManage || pending}
                      aria-label={f("item")}
                    />
                  </FieldBox>
                  {facts.map((k) => (
                    <FieldBox key={k} label={f(k)}>
                      <Input value={dash(item?.[k])} readOnly disabled aria-label={f(k)} />
                    </FieldBox>
                  ))}
                  <FieldBox label={f("req_qty")} required>
                    <Input type="number" min="0" step="any" value={l.req_qty} onChange={(e) => setLine(l.key, { req_qty: e.target.value })} disabled={!canManage || pending} aria-label={f("req_qty")} />
                  </FieldBox>
                  <FieldBox label={f("uom")} required>
                    <UomSelect value={l.uom} onChange={(v) => setLine(l.key, { uom: v })} disabled={!canManage || pending} aria-label={f("uom")} />
                  </FieldBox>
                  <FieldBox label={f("line_date")} required>
                    <Input type="date" value={l.req_date} onChange={(e) => setLine(l.key, { req_date: e.target.value })} disabled={!canManage || pending} aria-label={f("line_date")} />
                  </FieldBox>
                  <FieldBox label={f("specification")} className="lg:col-span-3">
                    <Input value={l.specification} onChange={(e) => setLine(l.key, { specification: e.target.value })} disabled={!canManage || pending} aria-label={f("specification")} />
                  </FieldBox>
                  <FieldBox label={f("remarks")} className="sm:col-span-2 lg:col-span-6">
                    <Input value={l.remarks} onChange={(e) => setLine(l.key, { remarks: e.target.value })} disabled={!canManage || pending} aria-label={f("remarks")} />
                  </FieldBox>
                </div>
              </div>
            );
          })}
          <div>
            <Button variant="outline" size="sm" onClick={() => setLines((all) => [...all, blankLine(tab)])} disabled={!canManage || pending}>
              <Plus /> {f(`add_${tab}`)}
            </Button>
          </div>
          <FieldBox label={f("req_remarks")}>
            <Textarea value={remarks} onChange={(e) => setRemarks(e.target.value)} rows={3} disabled={!canManage || pending} aria-label={f("req_remarks")} />
          </FieldBox>
          {errors ? (
            <Alert variant="danger">
              {errors.map((e) => (
                <p key={e}>{e}</p>
              ))}
            </Alert>
          ) : null}
          <div className="-mx-5 -mb-5 flex flex-wrap items-center justify-between gap-3 border-t border-border px-5 py-3.5">
            <p className="flex items-center gap-1.5 text-[13px] text-foreground-muted">
              <Info className="size-4" aria-hidden /> {f(`note_${type}`)}
            </p>
            {canManage ? (
              <div className="flex gap-2">
                <Button variant="outline" onClick={() => send(false)} disabled={pending}>
                  <Save /> {f("save_draft")}
                </Button>
                <Button onClick={() => send(true)} disabled={pending}>
                  <Send /> {f("submit")}
                </Button>
              </div>
            ) : null}
          </div>
        </section>
      </div>
    </UomProvider>
  );
}

/**
 * My Requisitions: All / Draft / Submitted / Rejected / Approved, and each
 * requisition opening in place (`?open=PR-2026-00006` opens one).
 *
 * @param {{ initial: object[], initialOpen?: string, canManage: boolean, act: (op: string, no: string) => Promise<object> }} props
 */
function MyRequisitions({ initial, initialOpen, canManage, act }) {
  const t = useT("materialprocurement");
  const l = (k, p) => t(`pr.list.${k}`, p);
  const f = (k) => t(`pr.form.${k}`);
  const toastManager = useToastManager();
  const [rows, setRows] = useState(initial);
  const [pill, setPill] = useState("ALL");
  const [open, setOpen] = useState(initialOpen ?? null);
  const [confirm, setConfirm] = useState(null);
  const [errors, setErrors] = useState(null);
  const [pending, startTransition] = useTransition();
  const shown = pill === "ALL" ? rows : rows.filter((r) => r.status === pill);

  function toggle(no) {
    const next = open === no ? null : no;
    setOpen(next);
    window.history.replaceState(null, "", next ? `?open=${encodeURIComponent(next)}` : "?");
  }

  function run(op, row) {
    setErrors(null);
    startTransition(async () => {
      const result = await act(op, row.pr_no);
      setConfirm(null);
      if (result?.status !== "success") return setErrors(messagesOf(result));
      if (op === "remove") {
        setRows((all) => all.filter((r) => r.pr_no !== row.pr_no));
        toastManager.add({ title: l("deleted", { no: row.pr_no }), type: "success" });
      } else {
        setRows((all) => all.map((r) => (r.pr_no === row.pr_no ? result.data : r)));
        toastManager.add({ title: l("submitted", { no: row.pr_no }), type: "success" });
      }
    });
  }

  return (
    <section className={cn(card, "min-w-0 overflow-hidden")}>
      <div className="flex flex-col gap-4 p-5">
        <div className="flex flex-wrap items-start justify-between gap-3">
          <div>
            <h2 className="text-base font-semibold text-foreground">{l("title")}</h2>
            <p className="mt-0.5 text-[13px] text-foreground-muted">{l("hint")}</p>
          </div>
          {canManage ? (
            <Link href="/material-procurement/create-requisition" className={cn(buttonVariants({ size: "sm" }))}>
              {l("new")}
            </Link>
          ) : null}
        </div>
        <div role="tablist" className="flex flex-wrap gap-2">
          {["ALL", "DRAFT", "SUBMITTED", "REJECTED", "APPROVED"].map((k) => (
            <button
              key={k}
              type="button"
              role="tab"
              aria-selected={pill === k}
              onClick={() => setPill(k)}
              className={cn("inline-flex items-center gap-2 rounded-full border px-3 py-1 text-[13px] font-medium", pill === k ? "border-brand bg-brand text-brand-foreground" : "border-border text-foreground hover:bg-surface-muted")}
            >
              {k === "ALL" ? l("all") : t(`pr.status.${k}`)}
              <span className={cn("rounded-full px-1.5 text-xs tabular-nums", pill === k ? "bg-brand-foreground/20" : "bg-surface-muted")}>{k === "ALL" ? rows.length : rows.filter((r) => r.status === k).length}</span>
            </button>
          ))}
        </div>
        {errors ? (
          <Alert variant="danger">
            {errors.map((e) => (
              <p key={e}>{e}</p>
            ))}
          </Alert>
        ) : null}
      </div>
      {shown.length === 0 ? <p className="border-t border-border px-5 py-10 text-center text-sm text-foreground-muted">{l("empty")}</p> : null}
      <ul>
        {shown.map((r) => {
          const isOpen = open === r.pr_no;
          return (
            <li key={r.pr_no} className="border-t border-border">
              <button type="button" onClick={() => toggle(r.pr_no)} aria-expanded={isOpen} className={cn("flex w-full flex-wrap items-center gap-x-4 gap-y-1 px-5 py-3 text-left hover:bg-surface-muted", isOpen && "bg-surface-muted")}>
                {isOpen ? <ChevronDown className="size-4 text-foreground-muted" aria-hidden /> : <ChevronRight className="size-4 text-foreground-muted" aria-hidden />}
                <span className="font-semibold text-foreground">{r.pr_no}</span>
                <span className={cn("rounded-md px-1.5 py-0.5 text-xs font-medium", r.req_type === "BY_ORDER" ? "bg-brand-subtle text-brand" : "bg-surface-muted text-foreground")}>{t(`pr.type.${r.req_type}`)}</span>
                <span className="text-sm text-foreground">{r.order ? `${r.order.order_no} · ${dash(r.order.buyer)}` : l("no_order")}</span>
                <span className="text-[13px] text-foreground-muted">
                  {l("lines", { n: r.lines_count })} · {day(r.req_date)} · {dash(r.created_by_name)}
                </span>
                <span className={cn("ml-auto rounded-md px-1.5 py-0.5 text-xs font-medium", STATUS_TONE[r.status])}>{t(`pr.status.${r.status}`)}</span>
              </button>
              {isOpen ? (
                <div className="flex flex-col gap-4 px-5 pb-5 pt-2">
                  <div className="grid gap-x-6 gap-y-4 sm:grid-cols-2 lg:grid-cols-3">
                    <Fact label={l("req_type")} value={t(`pr.type.${r.req_type}`)} />
                    <Fact label={f("created_by")} value={r.created_by_name} />
                    <Fact label={f("department")} value={r.department} />
                    <Fact label={f("designation")} value={r.designation} />
                    <Fact label={f("contact")} value={r.contact} />
                    <Fact label={l("submitted_at")} value={r.submitted_at ? dateTimeFmt.format(new Date(r.submitted_at)) : null} />
                    {r.order ? (
                      <>
                        <Fact label={f("order_no")} value={r.order.order_no} />
                        <Fact label={f("buyer")} value={r.order.buyer} />
                        <Fact label={f("style_no")} value={r.order.style_no} />
                        <Fact label={f("order_qty")} value={`${qty(r.order.order_qty)} ${r.order.order_unit ? r.order.order_unit.charAt(0) + r.order.order_unit.slice(1).toLowerCase() : ""}`.trim()} />
                        <Fact label={f("delivery_date")} value={day(r.order.delivery_date)} />
                        <Fact label={f("merchandiser")} value={r.order.merchandiser} />
                      </>
                    ) : (
                      <Fact label={l("order_info")} value={l("not_applicable")} />
                    )}
                  </div>
                  <div className="overflow-x-auto rounded-lg border border-border">
                    <table className="w-full min-w-[760px] text-sm">
                      <thead className="bg-brand text-brand-foreground">
                        <tr>
                          {["no", "item", "req_qty", "req_date", "quotations", "supplier", "status"].map((k) => (
                            <th key={k} className={cn("px-3 py-2.5 text-left text-xs font-semibold uppercase", ["req_qty", "quotations"].includes(k) && "text-right")}>
                              {l(`col.${k}`)}
                            </th>
                          ))}
                        </tr>
                      </thead>
                      <tbody>
                        {r.lines.map((ln, i) => (
                          <Fragment key={ln.id}>
                            <tr className="border-t border-border align-top">
                              <td className="px-3 py-2.5 text-foreground">{i + 1}</td>
                              <td className="px-3 py-2.5">
                                <p className="font-medium text-foreground">{ln.item_name}</p>
                                <p className="text-xs text-foreground-muted">{ln.item_detail}</p>
                              </td>
                              <td className="px-3 py-2.5 text-right tabular-nums text-foreground">
                                {qty(ln.req_qty)} {ln.uom}
                              </td>
                              <td className="px-3 py-2.5 text-foreground">{day(ln.req_date)}</td>
                              <td className="px-3 py-2.5 text-right tabular-nums text-foreground">{ln.quotations}</td>
                              <td className="px-3 py-2.5">
                                {ln.approved_supplier ? (
                                  <>
                                    <p className="text-foreground">{ln.approved_supplier}</p>
                                    <p className="text-xs text-foreground-muted">{[ln.approved_price, ln.followup ? l("followup", { name: ln.followup }) : null].filter(Boolean).join(" · ")}</p>
                                  </>
                                ) : (
                                  "—"
                                )}
                              </td>
                              <td className="px-3 py-2.5">
                                <span className={cn("rounded-md px-1.5 py-0.5 text-xs font-medium", LINE_TONE[ln.status] ?? "bg-info-subtle text-info")}>{t(`pr.line_status.${ln.status}`)}</span>
                              </td>
                            </tr>
                          </Fragment>
                        ))}
                      </tbody>
                    </table>
                  </div>
                  {r.editable && canManage ? (
                    <div className="flex flex-wrap gap-2">
                      <Link href={`/material-procurement/create-requisition?draft=${encodeURIComponent(r.pr_no)}`} className={cn(buttonVariants({ variant: "outline", size: "sm" }))}>
                        <Pencil /> {l("edit")}
                      </Link>
                      <Button size="sm" onClick={() => run("submit", r)} disabled={pending}>
                        <Send /> {l("submit")}
                      </Button>
                      <Button size="sm" variant="danger" onClick={() => setConfirm(r)} disabled={pending}>
                        <Trash2 /> {l("delete")}
                      </Button>
                    </div>
                  ) : null}
                </div>
              ) : null}
            </li>
          );
        })}
      </ul>
      <ConfirmDialog
        open={Boolean(confirm)}
        onOpenChange={(v) => !v && setConfirm(null)}
        title={confirm ? l("delete_title", { no: confirm.pr_no }) : ""}
        description={l("delete_text")}
        confirmLabel={l("delete")}
        cancelLabel={l("cancel")}
        loading={pending}
        onConfirm={() => confirm && run("remove", confirm)}
      />
    </section>
  );
}

export { CreateRequisition, MyRequisitions };
