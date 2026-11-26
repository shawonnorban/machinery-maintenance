"use client";

import { useState, useTransition } from "react";
import { BadgeCheck, CircleX, History, Lock, MessagesSquare, Search } from "lucide-react";
import { cn } from "@/lib/utils";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Select } from "@/components/ui/select";
import { Textarea } from "@/components/ui/textarea";
import { Modal } from "@/components/ui/modal";
import { Alert } from "@/components/ui/alert";
import { useToastManager } from "@/components/ui/toast";
import { useT } from "@/lib/i18n";

/*
 * Material Procurement → Price Review & Approval (AnnotechERP 08/05), as
 * Brickland draws it: four counts, then Received quotations — Pending /
 * Negotiation / Awaiting quotes / Approved / Rejected / All lines and a
 * search — each line with every supplier's latest price, and Approve &
 * Handover, Negotiate and Reject on a quotation, Reject line on the line.
 * Every decision is a dialog and the list reads itself again in place.
 */

const card = "rounded-xl border border-border bg-surface";
const dateFmt = new Intl.DateTimeFormat("en-GB", { day: "2-digit", month: "short", year: "numeric", timeZone: "Asia/Dhaka" });
const day = (iso) => (iso ? dateFmt.format(new Date(iso)) : "—");
const qty = (n) => Number(n ?? 0).toLocaleString("en-US", { maximumFractionDigits: 3 });
const money = (q) => (q?.unit_price == null ? "—" : `${q.currency} ${Number(q.unit_price).toLocaleString("en-US", { minimumFractionDigits: 2, maximumFractionDigits: 4 })}`);
const messagesOf = (result) => {
  const fields = [...new Set(Object.values(result?.errors ?? {}).flat())];
  return fields.length ? fields : [result?.message ?? ""];
};
const TONE = {
  PENDING: "bg-warning-subtle text-warning",
  REVISED: "bg-info-subtle text-info",
  NEGOTIATION: "bg-warning-subtle text-warning",
  APPROVED: "bg-success-subtle text-success",
  REJECTED: "bg-danger-subtle text-danger",
  CLOSED: "bg-surface-muted text-foreground-muted",
};
const LINE_TONE = { OPEN_FOR_QUOTATION: "bg-info-subtle text-info", APPROVED: "bg-success-subtle text-success", REJECTED: "bg-danger-subtle text-danger" };
const TABS = [
  ["pending", (l) => l.open && l.quotations.some((q) => ["PENDING", "REVISED"].includes(q.status))],
  ["negotiation", (l) => l.open && l.quotations.some((q) => q.status === "NEGOTIATION")],
  ["awaiting", (l) => l.open && l.quotations.length === 0],
  ["approved", (l) => l.status === "APPROVED"],
  ["rejected", (l) => l.status === "REJECTED"],
  ["all", () => true],
];

function Fact({ label, value }) {
  return (
    <div className="flex flex-col gap-0.5">
      <span className="text-[11px] font-semibold uppercase tracking-wide text-foreground-muted">{label}</span>
      <span className="text-sm text-foreground">{value || "—"}</span>
    </div>
  );
}

/** One decision dialog: negotiate, approve, reject a quotation, or reject a line. */
function DecisionDialog({ action, merchandisers, onClose, onDone, act }) {
  const t = useT("materialprocurement");
  const r = (k, p) => t(`review.${k}`, p);
  const toastManager = useToastManager();
  const { kind, line, quote } = action;
  const [text, setText] = useState(kind === "negotiate" ? r("default_message") : "");
  const [handover, setHandover] = useState("");
  const [errors, setErrors] = useState(null);
  const [pending, startTransition] = useTransition();
  const titles = {
    negotiate: r("negotiate_title", { supplier: quote?.supplier }),
    approve: r("approve_title"),
    reject: r("reject_title", { no: quote?.qt_no }),
    rejectLine: r("reject_line_title", { item: line.item_name }),
  };
  const hints = { negotiate: r("negotiate_hint"), approve: r("approve_hint"), reject: r("reject_hint"), rejectLine: r("reject_line_hint") };
  const confirm = { negotiate: r("send_request"), approve: r("confirm_approve"), reject: r("reject"), rejectLine: r("reject") };

  function submit() {
    setErrors(null);
    startTransition(async () => {
      const payload = kind === "negotiate" ? { message: text } : kind === "approve" ? { handover_to: handover } : { reason: text };
      const result = await act(kind, kind === "rejectLine" ? line.line_id : quote.id, payload);
      if (result?.status !== "success") return setErrors(messagesOf(result));
      const done = {
        negotiate: r("negotiated", { supplier: quote?.supplier }),
        approve: r("approved", { name: result.data.handover_to }),
        reject: r("rejected", { no: quote?.qt_no }),
        rejectLine: r("line_rejected", { item: line.item_name }),
      };
      toastManager.add({ title: done[kind], type: "success" });
      onDone();
    });
  }

  return (
    <Modal
      open
      onOpenChange={(v) => !v && onClose()}
      title={titles[kind]}
      description={hints[kind]}
      className={kind === "approve" ? "max-w-2xl" : undefined}
      footer={
        <>
          <Button variant="outline" onClick={onClose} disabled={pending}>
            {r("cancel")}
          </Button>
          <Button variant={kind.startsWith("reject") ? "danger" : "primary"} onClick={submit} loading={pending}>
            {confirm[kind]}
          </Button>
        </>
      }
    >
      <div className="flex flex-col gap-4">
        {errors ? (
          <Alert variant="danger">
            {errors.map((e) => (
              <p key={e}>{e}</p>
            ))}
          </Alert>
        ) : null}
        {kind === "approve" ? (
          <>
            <div className="grid gap-3 rounded-lg bg-surface-muted p-3 sm:grid-cols-3">
              <Fact label={r("f.qt_no")} value={quote.qt_no} />
              <Fact label={r("f.pr_no")} value={line.pr_no} />
              <Fact label={r("f.order_no")} value={line.order?.order_no ?? t(`pr.type.${line.req_type}`)} />
              <Fact label={r("f.supplier")} value={quote.supplier} />
              <Fact label={r("f.material")} value={line.item_name} />
              <Fact label={r("f.price")} value={money(quote)} />
              <Fact label={r("f.qty")} value={`${qty(quote.quoted_qty)} ${line.uom}`} />
              <Fact label={r("f.lead")} value={r("days", { n: quote.lead_time_days })} />
              <Fact label={r("f.requested_by")} value={line.requested_by} />
            </div>
            <label className="flex flex-col gap-1.5">
              <span className="text-[13px] font-medium text-foreground">
                {r("f.handover")}
                <span className="text-danger"> *</span>
              </span>
              <Select value={handover} onValueChange={(v) => setHandover(v ?? "")} options={merchandisers} placeholder={r("select_merchandiser")} aria-label={r("f.handover")} />
            </label>
          </>
        ) : (
          <label className="flex flex-col gap-1.5">
            <span className="text-[13px] font-medium text-foreground">
              {kind === "negotiate" ? r("f.message") : r("f.reason")}
              <span className="text-danger"> *</span>
            </span>
            <Textarea rows={3} value={text} onChange={(e) => setText(e.target.value)} aria-label={kind === "negotiate" ? r("f.message") : r("f.reason")} />
          </label>
        )}
      </div>
    </Modal>
  );
}

/**
 * @param {{ data: { counts: object, lines: object[], merchandisers: object[] }, act: Function, reload: Function }} props
 */
function PriceReview({ data, act, reload }) {
  const t = useT("materialprocurement");
  const r = (k, p) => t(`review.${k}`, p);
  const [state, setState] = useState(data);
  const [tab, setTab] = useState("pending");
  const [needle, setNeedle] = useState("");
  const [action, setAction] = useState(null);
  const test = TABS.find(([k]) => k === tab)[1];
  const q = needle.trim().toLowerCase();
  const shown = state.lines.filter(test).filter((l) => !q || [l.pr_no, l.order?.order_no, l.item_name, ...l.quotations.map((x) => x.supplier)].some((v) => (v ?? "").toLowerCase().includes(q)));
  const refresh = async () => {
    setAction(null);
    const fresh = await reload();
    if (fresh?.status === "success") setState(fresh.data);
  };
  const tiles = [
    ["pending", History, "bg-info-subtle text-info"],
    ["negotiation", MessagesSquare, "bg-warning-subtle text-warning"],
    ["approved", BadgeCheck, "bg-success-subtle text-success"],
    ["rejected", CircleX, "bg-danger-subtle text-danger"],
  ];

  return (
    <div className="flex flex-col gap-4">
      <section className="grid grid-cols-2 gap-3 md:grid-cols-4">
        {tiles.map(([k, Icon, tone]) => (
          <div key={k} className={cn(card, "flex items-center gap-3 p-4")}>
            <span className={cn("flex size-10 items-center justify-center rounded-lg", tone)}>
              <Icon className="size-5" aria-hidden />
            </span>
            <div>
              <p className="text-[13px] text-foreground-muted">{r(`tile.${k}`)}</p>
              <p className="text-xl font-semibold tabular-nums text-foreground">{state.counts[k]}</p>
            </div>
          </div>
        ))}
      </section>
      <section className={cn(card, "min-w-0 overflow-hidden")}>
        <div className="flex flex-col gap-4 p-5">
          <div className="flex items-start gap-2">
            <Lock className="mt-0.5 size-4 text-foreground-muted" aria-hidden />
            <div>
              <h2 className="text-base font-semibold text-foreground">{r("title")}</h2>
              <p className="mt-0.5 text-[13px] text-foreground-muted">{r("confidential")}</p>
            </div>
          </div>
          <div className="flex flex-wrap items-center justify-between gap-3">
            <div role="tablist" className="flex flex-wrap gap-2">
              {TABS.map(([k, fn]) => (
                <button
                  key={k}
                  type="button"
                  role="tab"
                  aria-selected={tab === k}
                  onClick={() => setTab(k)}
                  className={cn("inline-flex items-center gap-2 rounded-full border px-3 py-1 text-[13px] font-medium", tab === k ? "border-brand bg-brand text-brand-foreground" : "border-border text-foreground hover:bg-surface-muted")}
                >
                  {r(`tab.${k}`)}
                  <span className={cn("rounded-full px-1.5 text-xs tabular-nums", tab === k ? "bg-brand-foreground/20" : "bg-surface-muted")}>{state.lines.filter(fn).length}</span>
                </button>
              ))}
            </div>
            <div className="relative w-72">
              <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-foreground-muted" aria-hidden />
              <Input value={needle} onChange={(e) => setNeedle(e.target.value)} placeholder={r("search")} aria-label={r("search")} className="pl-9" />
            </div>
          </div>
        </div>
        {shown.length === 0 ? <p className="border-t border-border px-5 py-10 text-center text-sm text-foreground-muted">{r("empty")}</p> : null}
        {shown.map((l) => (
          <div key={l.line_id} className="flex flex-col gap-3 border-t border-border p-5">
            <div className="flex flex-wrap items-center gap-x-3 gap-y-1">
              <span className="font-semibold text-foreground">{l.pr_no}</span>
              <span className={cn("rounded-md px-1.5 py-0.5 text-xs font-medium", l.req_type === "BY_ORDER" ? "bg-brand-subtle text-brand" : "bg-surface-muted text-foreground")}>{t(`pr.type.${l.req_type}`)}</span>
              <span className="font-medium text-foreground">{l.item_name}</span>
              <span className="text-[13px] text-foreground-muted">{l.item_detail}</span>
              <span className="ml-auto flex items-center gap-2">
                <span className={cn("rounded-md px-1.5 py-0.5 text-xs font-medium", LINE_TONE[l.status])}>{t(`pr.line_status.${l.status}`)}</span>
                {l.open ? (
                  <Button size="sm" variant="outline" onClick={() => setAction({ kind: "rejectLine", line: l })}>
                    {r("reject_line")}
                  </Button>
                ) : null}
              </span>
            </div>
            <p className="text-[13px] text-foreground-muted">
              {r("required", { qty: qty(l.req_qty), uom: l.uom, date: day(l.req_date) })} · {r("requested_by", { name: l.requested_by ?? "—" })} ·{" "}
              {l.order ? r("order_line", { no: l.order.order_no, buyer: l.order.buyer ?? "—", style: l.order.style ?? "—" }) : r("direct")}
              {l.followup ? ` · ${r("followup", { name: l.followup })}` : ""}
              {l.reject_reason ? ` · ${r("line_reason", { reason: l.reject_reason })}` : ""}
            </p>
            {l.quotations.length ? (
              <div className="overflow-x-auto rounded-lg border border-border">
                <table className="w-full min-w-[920px] text-sm">
                  <thead className="bg-brand text-brand-foreground">
                    <tr>
                      {["supplier", "price", "qty", "lead", "delivery", "revisions", "status", "actions"].map((k) => (
                        <th key={k} className={cn("px-3 py-2.5 text-left text-xs font-semibold uppercase", ["price", "qty", "lead", "revisions"].includes(k) && "text-right", k === "actions" && "text-right")}>
                          {r(`col.${k}`)}
                        </th>
                      ))}
                    </tr>
                  </thead>
                  <tbody>
                    {l.quotations.map((x) => (
                      <tr key={x.id} className="border-t border-border align-top">
                        <td className="px-3 py-2.5">
                          <p className="font-medium text-foreground">{x.supplier}</p>
                          <p className="text-xs text-foreground-muted">
                            {x.qt_no} · {x.supplier_code}
                          </p>
                          {x.status === "NEGOTIATION" && x.negotiation_message ? <p className="text-xs text-warning">{x.negotiation_message}</p> : null}
                          {x.reject_reason ? <p className="text-xs text-danger">{x.reject_reason}</p> : null}
                        </td>
                        <td className="px-3 py-2.5 text-right font-semibold tabular-nums text-foreground">{money(x)}</td>
                        <td className="px-3 py-2.5 text-right tabular-nums text-foreground">
                          {qty(x.quoted_qty)} {l.uom}
                        </td>
                        <td className="px-3 py-2.5 text-right tabular-nums text-foreground">{r("days", { n: x.lead_time_days })}</td>
                        <td className="px-3 py-2.5 text-foreground">{day(x.expected_delivery)}</td>
                        <td className="px-3 py-2.5 text-right tabular-nums text-foreground">{x.revisions}</td>
                        <td className="px-3 py-2.5">
                          <span className={cn("rounded-md px-1.5 py-0.5 text-xs font-medium", TONE[x.status])}>{r(`status.${x.status}`)}</span>
                        </td>
                        <td className="px-3 py-2.5">
                          <div className="flex justify-end gap-1.5">
                            {x.can_approve ? (
                              <Button size="sm" onClick={() => setAction({ kind: "approve", line: l, quote: x })}>
                                {r("approve")}
                              </Button>
                            ) : null}
                            {x.can_negotiate ? (
                              <Button size="sm" variant="outline" onClick={() => setAction({ kind: "negotiate", line: l, quote: x })}>
                                {r("negotiate")}
                              </Button>
                            ) : null}
                            {x.can_approve ? (
                              <Button size="sm" variant="danger" onClick={() => setAction({ kind: "reject", line: l, quote: x })}>
                                {r("reject")}
                              </Button>
                            ) : null}
                          </div>
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            ) : (
              <p className="text-[13px] text-foreground-muted">{r("no_quotes")}</p>
            )}
          </div>
        ))}
      </section>
      {action ? <DecisionDialog action={action} merchandisers={state.merchandisers} onClose={() => setAction(null)} onDone={refresh} act={act} /> : null}
    </div>
  );
}

export { PriceReview };
