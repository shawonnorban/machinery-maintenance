"use client";

import { Fragment, useState, useTransition } from "react";
import { ChevronDown, ChevronRight, Lock, Search } from "lucide-react";
import { cn } from "@/lib/utils";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Select } from "@/components/ui/select";
import { Textarea } from "@/components/ui/textarea";
import { Modal } from "@/components/ui/modal";
import { Alert } from "@/components/ui/alert";
import { useToastManager } from "@/components/ui/toast";
import { useT } from "@/lib/i18n";
import { clockFormat } from "@/lib/clock";

/*
 * Material Procurement → Supplier Portal (AnnotechERP 08/04), as Brickland
 * draws it for a supplier's portal login: the Supplier Dashboard, Available
 * Requirements — each open line with Quote, or Revise once quoted — and
 * Quotation History, each quotation opening to every revision it sent. The
 * quote dialog shows the line's facts, the last price on a revision, and
 * submits without a reload.
 */

const card = "rounded-xl border border-border bg-surface";
const dateFmt = new Intl.DateTimeFormat("en-GB", { day: "2-digit", month: "short", year: "numeric", timeZone: "Asia/Dhaka" });
const dateTimeFmt = clockFormat({ day: "2-digit", month: "short", year: "numeric", hour: "2-digit", minute: "2-digit", timeZone: "Asia/Dhaka" });
const day = (iso) => (iso ? dateFmt.format(new Date(iso)) : "—");
const at = (iso) => (iso ? dateTimeFmt.format(new Date(iso)) : "—");
const qty = (n) => Number(n ?? 0).toLocaleString("en-US", { maximumFractionDigits: 3 });
const money = (r) => (r ? `${r.currency} ${Number(r.unit_price).toLocaleString("en-US", { minimumFractionDigits: 2, maximumFractionDigits: 4 })}` : "—");
const messagesOf = (result) => {
  const fields = [...new Set(Object.values(result?.errors ?? {}).flat())];
  return fields.length ? fields : [result?.message ?? ""];
};
const STATUS_TONE = {
  PENDING: "bg-warning-subtle text-warning",
  REVISED: "bg-info-subtle text-info",
  NEGOTIATION: "bg-warning-subtle text-warning",
  APPROVED: "bg-success-subtle text-success",
  REJECTED: "bg-danger-subtle text-danger",
  CLOSED: "bg-surface-muted text-foreground-muted",
};

/** What anyone without a portal login sees on these screens, as Brickland words it. */
function PortalNotice({ reason = "for_suppliers" }) {
  const t = useT("materialprocurement");
  return (
    <section className={cn(card, "flex flex-col items-center gap-2 px-6 py-12 text-center")}>
      <span className="flex size-12 items-center justify-center rounded-xl bg-surface-muted text-foreground-muted">
        <Lock className="size-5" aria-hidden />
      </span>
      <h2 className="text-base font-semibold text-foreground">{t("sp.not_available")}</h2>
      <p className="text-sm text-foreground">{t(`sp.${reason}`)}</p>
      <p className="max-w-xl text-[13px] text-foreground-muted">{t("sp.check_role")}</p>
    </section>
  );
}

function StatusChip({ status }) {
  const t = useT("materialprocurement");
  return <span className={cn("rounded-md px-1.5 py-0.5 text-xs font-medium", STATUS_TONE[status])}>{t(`sp.status.${status}`)}</span>;
}

function Fact({ label, value }) {
  return (
    <div className="flex flex-col gap-0.5">
      <span className="text-[11px] font-semibold uppercase tracking-wide text-foreground-muted">{label}</span>
      <span className="text-sm text-foreground">{value || "—"}</span>
    </div>
  );
}

const blankQuote = (line, last) => ({
  quoted_qty: String(last?.quoted_qty ?? line.req_qty ?? ""),
  unit_price: "",
  currency: last?.currency ?? "USD",
  lead_time_days: last ? String(last.lead_time_days) : "",
  expected_delivery: last?.expected_delivery ?? "",
  remarks: "",
});

/**
 * Submit quotation (or Revise quotation — revision n): the line's facts, the
 * last price when revising, and the price, currency, lead time and delivery.
 */
function QuoteDialog({ target, currencies, onClose, onDone, quote }) {
  const t = useT("materialprocurement");
  const f = (k, p) => t(`sp.form.${k}`, p);
  const toastManager = useToastManager();
  const { line, last, revision } = target;
  const [form, setForm] = useState(() => blankQuote(line, last));
  const [errors, setErrors] = useState(null);
  const [pending, startTransition] = useTransition();
  const set = (k) => (v) => setForm((all) => ({ ...all, [k]: v ?? "" }));

  function submit() {
    setErrors(null);
    startTransition(async () => {
      const result = await quote(line.line_id, form);
      if (result?.status !== "success") return setErrors(messagesOf(result));
      const { qt_no, revision: n } = result.data;
      toastManager.add({ title: n > 1 ? f("revised", { n, no: qt_no }) : f("submitted", { no: qt_no }), type: "success" });
      onDone();
    });
  }

  return (
    <Modal
      open
      onOpenChange={(v) => !v && onClose()}
      title={last ? f("revise_title", { n: revision }) : f("submit_title")}
      description={`${line.pr_no} · ${line.item_name}`}
      className="max-w-2xl"
      footer={
        <>
          <Button variant="outline" onClick={onClose} disabled={pending}>
            {f("cancel")}
          </Button>
          <Button onClick={submit} loading={pending}>
            {f("submit")}
          </Button>
        </>
      }
    >
      <div className="flex flex-col gap-4">
        <div className="grid gap-3 rounded-lg bg-surface-muted p-3 sm:grid-cols-3">
          <Fact label={f("pr_no")} value={line.pr_no} />
          <Fact label={f("type")} value={line.req_type ? t(`pr.type.${line.req_type}`) : null} />
          <Fact label={f("item")} value={line.item_name} />
          <Fact label={f("specification")} value={line.specification} />
          <Fact label={f("colour_size")} value={line.colour_size} />
          <Fact label={f("req_qty")} value={`${qty(line.req_qty)} ${line.uom}`} />
          <Fact label={f("req_date")} value={day(line.req_date)} />
          {last ? <Fact label={f("last_price")} value={f("last_price_value", { price: money(last), n: last.revision })} /> : null}
        </div>
        {errors ? (
          <Alert variant="danger">
            {errors.map((e) => (
              <p key={e}>{e}</p>
            ))}
          </Alert>
        ) : null}
        <div className="grid gap-3 sm:grid-cols-2">
          {[
            ["quoted_qty", f("qty"), "number", line.uom],
            ["unit_price", f("price"), "number", null],
          ].map(([k, label, type, suffix]) => (
            <label key={k} className="flex flex-col gap-1.5">
              <span className="text-[13px] font-medium text-foreground">
                {label}
                <span className="text-danger"> *</span>
              </span>
              <div className="flex items-center gap-2">
                <Input type={type} min="0" step="any" value={form[k]} onChange={(e) => set(k)(e.target.value)} aria-label={label} />
                {suffix ? <span className="text-sm text-foreground-muted">{suffix}</span> : null}
              </div>
            </label>
          ))}
          <label className="flex flex-col gap-1.5">
            <span className="text-[13px] font-medium text-foreground">
              {f("currency")}
              <span className="text-danger"> *</span>
            </span>
            <Select value={form.currency} onValueChange={set("currency")} options={currencies.map((c) => ({ value: c, label: c }))} placeholder={f("select")} aria-label={f("currency")} />
          </label>
          <label className="flex flex-col gap-1.5">
            <span className="text-[13px] font-medium text-foreground">
              {f("lead")}
              <span className="text-danger"> *</span>
            </span>
            <div className="flex items-center gap-2">
              <Input type="number" min="0" step="1" value={form.lead_time_days} onChange={(e) => set("lead_time_days")(e.target.value)} aria-label={f("lead")} />
              <span className="text-sm text-foreground-muted">{f("days")}</span>
            </div>
          </label>
          <label className="flex flex-col gap-1.5">
            <span className="text-[13px] font-medium text-foreground">
              {f("delivery")}
              <span className="text-danger"> *</span>
            </span>
            <Input type="date" value={form.expected_delivery} onChange={(e) => set("expected_delivery")(e.target.value)} aria-label={f("delivery")} />
          </label>
          <label className="flex flex-col gap-1.5 sm:col-span-2">
            <span className="text-[13px] font-medium text-foreground">{f("remarks")}</span>
            <Textarea rows={2} value={form.remarks} onChange={(e) => set("remarks")(e.target.value)} aria-label={f("remarks")} />
          </label>
        </div>
        <p className="text-xs text-foreground-muted">{f("note")}</p>
      </div>
    </Modal>
  );
}

/** The requirement rows: requisition, order, item, quantity, date, your quotation and Quote / Revise. */
function RequirementTable({ lines, onQuote }) {
  const t = useT("materialprocurement");
  const r = (k, p) => t(`sp.req.${k}`, p);
  return (
    <div className="overflow-x-auto">
      <table className="w-full min-w-[860px] text-sm">
        <thead className="bg-surface-muted">
          <tr>
            {["requisition", "order_no", "item", "req_qty", "required", "your_quotation"].map((k) => (
              <th key={k} className={cn("px-4 py-2.5 text-left text-xs font-semibold uppercase text-foreground-muted", k === "req_qty" && "text-right")}>
                {r(`col.${k}`)}
              </th>
            ))}
            <th className="w-28" />
          </tr>
        </thead>
        <tbody>
          {lines.map((l) => (
            <tr key={l.line_id} className="border-t border-border align-top">
              <td className="px-4 py-2.5">
                <p className="font-medium text-foreground">{l.pr_no}</p>
                <p className="text-xs text-foreground-muted">{t(`pr.type.${l.req_type}`)}</p>
              </td>
              <td className="px-4 py-2.5 text-foreground">{l.order_no ?? "—"}</td>
              <td className="px-4 py-2.5">
                <p className="font-medium text-foreground">{l.item_name}</p>
                <p className="text-xs text-foreground-muted">{l.item_detail}</p>
              </td>
              <td className="px-4 py-2.5 text-right tabular-nums text-foreground">
                {qty(l.req_qty)} {l.uom}
              </td>
              <td className="px-4 py-2.5 text-foreground">{day(l.req_date)}</td>
              <td className="px-4 py-2.5">
                {l.quotation ? (
                  <div className="flex flex-col gap-0.5">
                    <StatusChip status={l.quotation.status} />
                    <span className="text-xs text-foreground-muted">
                      {money(l.quotation.latest)} · {r("rev", { n: l.quotation.revisions })}
                    </span>
                    {l.quotation.status === "NEGOTIATION" && l.quotation.negotiation_message ? <span className="text-xs text-warning">{t("sp.hist.negotiation_note", { message: l.quotation.negotiation_message })}</span> : null}
                  </div>
                ) : (
                  <span className="text-xs text-foreground-muted">{r("not_quoted")}</span>
                )}
              </td>
              <td className="px-4 py-2.5 text-right">
                {!l.quotation || l.quotation.revisable ? (
                  <Button size="sm" variant={l.quotation ? "outline" : "primary"} onClick={() => onQuote({ line: l, last: l.quotation?.latest ?? null, revision: (l.quotation?.revisions ?? 0) + 1 })}>
                    {!l.quotation ? r("quote") : l.quotation.status === "NEGOTIATION" ? r("send_revised") : r("revise")}
                  </Button>
                ) : null}
              </td>
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  );
}

/**
 * Available Requirements: All / Fabric / Accessories, a search, and the lines.
 *
 * @param {{ data: object, quote: Function, reload: Function }} props
 */
function AvailableRequirements({ data, quote, reload }) {
  const t = useT("materialprocurement");
  const r = (k, p) => t(`sp.req.${k}`, p);
  const [state, setState] = useState(data);
  const [kind, setKind] = useState("ALL");
  const [needle, setNeedle] = useState("");
  const [target, setTarget] = useState(null);
  if (!state.supplier) return <PortalNotice />;
  const q = needle.trim().toLowerCase();
  const shown = state.lines.filter((l) => (kind === "ALL" || l.kind === kind) && (!q || [l.pr_no, l.order_no, l.item_name, l.item_detail].some((v) => (v ?? "").toLowerCase().includes(q))));
  const refresh = async () => {
    setTarget(null);
    const fresh = await reload();
    if (fresh?.status === "success") setState(fresh.data);
  };

  return (
    <section className={cn(card, "min-w-0 overflow-hidden")}>
      <div className="flex flex-col gap-4 p-5">
        <div>
          <h2 className="text-base font-semibold text-foreground">{r("title")}</h2>
          <p className="mt-0.5 text-[13px] text-foreground-muted">{r("hint")}</p>
        </div>
        <div className="flex flex-wrap items-center justify-between gap-3">
          <div role="tablist" className="flex flex-wrap gap-2">
            {["ALL", "FABRIC", "TRIMS"].map((k) => (
              <button
                key={k}
                type="button"
                role="tab"
                aria-selected={kind === k}
                onClick={() => setKind(k)}
                className={cn("inline-flex items-center gap-2 rounded-full border px-3 py-1 text-[13px] font-medium", kind === k ? "border-brand bg-brand text-brand-foreground" : "border-border text-foreground hover:bg-surface-muted")}
              >
                {k === "ALL" ? r("all") : t(`pr.kind.${k}`)}
                <span className={cn("rounded-full px-1.5 text-xs tabular-nums", kind === k ? "bg-brand-foreground/20" : "bg-surface-muted")}>{k === "ALL" ? state.lines.length : state.lines.filter((l) => l.kind === k).length}</span>
              </button>
            ))}
          </div>
          <div className="relative w-72">
            <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-foreground-muted" aria-hidden />
            <Input value={needle} onChange={(e) => setNeedle(e.target.value)} placeholder={r("search")} aria-label={r("search")} className="pl-9" />
          </div>
        </div>
      </div>
      {state.lines.length === 0 ? (
        <p className="border-t border-border px-5 py-10 text-center text-sm text-foreground-muted">{r("empty")}</p>
      ) : shown.length === 0 ? (
        <p className="border-t border-border px-5 py-10 text-center text-sm text-foreground-muted">{r("no_match")}</p>
      ) : (
        <RequirementTable lines={shown} onQuote={setTarget} />
      )}
      {target ? <QuoteDialog target={target} currencies={state.currencies} onClose={() => setTarget(null)} onDone={refresh} quote={quote} /> : null}
    </section>
  );
}

/**
 * Supplier Dashboard: welcome, the four counts, the newest open requirements
 * and the supplier's notifications.
 *
 * @param {{ data: object, requirements: object, quote: Function, reload: Function, reloadDashboard: Function }} props
 */
function SupplierDashboard({ data, requirements, quote, reloadDashboard }) {
  const t = useT("materialprocurement");
  const d = (k) => t(`sp.dash.${k}`);
  const [state, setState] = useState(data);
  const [target, setTarget] = useState(null);
  if (!state.supplier) return <PortalNotice />;
  const refresh = async () => {
    setTarget(null);
    const fresh = await reloadDashboard();
    if (fresh?.status === "success") setState(fresh.data);
  };
  const tiles = [
    ["pending", "bg-warning-subtle text-warning"],
    ["submitted", "bg-info-subtle text-info"],
    ["approved", "bg-success-subtle text-success"],
    ["ended", "bg-danger-subtle text-danger"],
  ];

  return (
    <div className="flex flex-col gap-4">
      <section className={cn(card, "p-5")}>
        <p className="text-[13px] text-foreground-muted">{d("welcome")}</p>
        <h2 className="text-lg font-semibold text-foreground">{state.supplier}</h2>
      </section>
      <section className="grid grid-cols-2 gap-3 md:grid-cols-4">
        {tiles.map(([k, tone]) => (
          <div key={k} className={cn(card, "flex flex-col gap-1 p-4")}>
            <span className={cn("w-fit rounded-md px-1.5 py-0.5 text-xs font-medium", tone)}>{d(k)}</span>
            <span className="text-2xl font-semibold tabular-nums text-foreground">{state.counts[k]}</span>
          </div>
        ))}
      </section>
      <div className="grid gap-4 lg:grid-cols-3">
        <section className={cn(card, "min-w-0 overflow-hidden lg:col-span-2")}>
          <div className="p-5">
            <h2 className="text-base font-semibold text-foreground">{d("recent")}</h2>
            <p className="mt-0.5 text-[13px] text-foreground-muted">{d("recent_hint")}</p>
          </div>
          {state.recent.length ? <RequirementTable lines={state.recent} onQuote={setTarget} /> : <p className="border-t border-border px-5 py-8 text-center text-sm text-foreground-muted">{t("sp.req.empty")}</p>}
        </section>
        <section className={cn(card, "flex flex-col gap-3 p-5")}>
          <h2 className="text-base font-semibold text-foreground">{d("notifications")}</h2>
          {state.notifications.length ? (
            <ul className="flex flex-col divide-y divide-border">
              {state.notifications.map((n, i) => (
                <li key={`${n.at}-${i}`} className="py-2.5">
                  <p className="text-sm font-medium text-foreground">{n.title}</p>
                  <p className="text-[13px] text-foreground-muted">{n.body}</p>
                  <p className="text-xs text-foreground-subtle">{at(n.at)}</p>
                </li>
              ))}
            </ul>
          ) : (
            <p className="text-sm text-foreground-muted">{d("no_notifications")}</p>
          )}
        </section>
      </div>
      {target ? <QuoteDialog target={target} currencies={requirements.currencies} onClose={() => setTarget(null)} onDone={refresh} quote={quote} /> : null}
    </div>
  );
}

/**
 * Quotation History: All / Revision requested / Approved / Rejected-closed,
 * each quotation opening to its revisions, with Revise price while it is
 * still under review.
 *
 * @param {{ data: object, requirements: object, quote: Function, reload: Function }} props
 */
function QuotationHistory({ data, requirements, quote, reload }) {
  const t = useT("materialprocurement");
  const h = (k, p) => t(`sp.hist.${k}`, p);
  const [state, setState] = useState(data);
  const [pill, setPill] = useState("ALL");
  const [open, setOpen] = useState(null);
  const [target, setTarget] = useState(null);
  if (!state.supplier) return <PortalNotice />;
  const pills = [
    ["ALL", h("all"), () => true],
    ["NEGOTIATION", h("revision_requested"), (q) => q.status === "NEGOTIATION"],
    ["APPROVED", h("approved"), (q) => q.status === "APPROVED"],
    ["ENDED", h("ended"), (q) => ["REJECTED", "CLOSED"].includes(q.status)],
  ];
  const test = pills.find((p) => p[0] === pill)[2];
  const shown = state.quotations.filter(test);
  const refresh = async () => {
    setTarget(null);
    const fresh = await reload();
    if (fresh?.status === "success") setState(fresh.data);
  };

  return (
    <section className={cn(card, "min-w-0 overflow-hidden")}>
      <div className="flex flex-col gap-4 p-5">
        <div>
          <h2 className="text-base font-semibold text-foreground">{h("title")}</h2>
          <p className="mt-0.5 text-[13px] text-foreground-muted">{h("hint")}</p>
        </div>
        <div role="tablist" className="flex flex-wrap gap-2">
          {pills.map(([k, label, fn]) => (
            <button
              key={k}
              type="button"
              role="tab"
              aria-selected={pill === k}
              onClick={() => setPill(k)}
              className={cn("inline-flex items-center gap-2 rounded-full border px-3 py-1 text-[13px] font-medium", pill === k ? "border-brand bg-brand text-brand-foreground" : "border-border text-foreground hover:bg-surface-muted")}
            >
              {label}
              <span className={cn("rounded-full px-1.5 text-xs tabular-nums", pill === k ? "bg-brand-foreground/20" : "bg-surface-muted")}>{state.quotations.filter(fn).length}</span>
            </button>
          ))}
        </div>
      </div>
      {shown.length === 0 ? <p className="border-t border-border px-5 py-10 text-center text-sm text-foreground-muted">{h("empty")}</p> : null}
      <ul>
        {shown.map((q) => {
          const isOpen = open === q.qt_no;
          return (
            <li key={q.qt_no} className="border-t border-border">
              <div className={cn("flex flex-wrap items-center gap-x-4 gap-y-1 px-5 py-3", isOpen && "bg-surface-muted")}>
                <button type="button" onClick={() => setOpen(isOpen ? null : q.qt_no)} aria-expanded={isOpen} className="flex flex-1 flex-wrap items-center gap-x-4 gap-y-1 text-left">
                  {isOpen ? <ChevronDown className="size-4 text-foreground-muted" aria-hidden /> : <ChevronRight className="size-4 text-foreground-muted" aria-hidden />}
                  <span className="font-semibold text-foreground">{q.qt_no}</span>
                  <span className="text-sm text-foreground">
                    {q.line.pr_no} · {q.line.item_name} · {qty(q.line.req_qty)} {q.line.uom}
                  </span>
                  <StatusChip status={q.status} />
                  <span className="text-[13px] text-foreground-muted">{h("latest", { price: money(q.latest), n: q.revisions.length })}</span>
                </button>
                {q.revisable ? (
                  <Button size="sm" variant={q.status === "NEGOTIATION" ? "primary" : "outline"} onClick={() => setTarget({ line: q.line, last: q.latest, revision: q.revisions.length + 1 })}>
                    {q.status === "NEGOTIATION" ? t("sp.req.send_revised") : h("revise")}
                  </Button>
                ) : null}
              </div>
              {q.status === "NEGOTIATION" && q.negotiation_message ? <p className="px-5 pb-2 text-[13px] text-warning">{h("negotiation_note", { message: q.negotiation_message })}</p> : null}
              {q.reject_reason ? <p className="px-5 pb-2 text-[13px] text-danger">{h("reject_note", { reason: q.reject_reason })}</p> : null}
              {isOpen ? (
                <ol className="flex flex-col gap-2 px-5 pt-1 pb-4">
                  {q.revisions.map((rv) => (
                    <Fragment key={rv.revision}>
                      <li className="rounded-lg border border-border px-4 py-2.5">
                        <div className="flex items-baseline justify-between gap-3">
                          <span className="text-sm font-medium text-foreground">{rv.revision === 1 ? h("initial") : h("revision", { n: rv.revision })}</span>
                          <span className="text-sm font-semibold tabular-nums text-foreground">{money(rv)}</span>
                        </div>
                        <p className="text-xs text-foreground-muted">
                          {h("detail", { qty: qty(rv.quoted_qty), lead: rv.lead_time_days, date: day(rv.expected_delivery), at: at(rv.submitted_at) })}
                          {rv.remarks ? ` · ${rv.remarks}` : ""}
                        </p>
                      </li>
                    </Fragment>
                  ))}
                </ol>
              ) : null}
            </li>
          );
        })}
      </ul>
      {target ? <QuoteDialog target={target} currencies={requirements?.currencies ?? state.currencies} onClose={() => setTarget(null)} onDone={refresh} quote={quote} /> : null}
    </section>
  );
}

export { AvailableRequirements, PortalNotice, QuotationHistory, SupplierDashboard };
