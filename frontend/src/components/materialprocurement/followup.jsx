"use client";

import { Fragment, useState, useTransition } from "react";
import { Check, Search } from "lucide-react";
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
 * Material Procurement → Approved Material & Follow-Up (AnnotechERP 08/06), as
 * Brickland draws it. Assigned Materials: the lines handed to you (or all of
 * them, read-only, for a monitor); a line opens below the list to its
 * quantities, the nine delivery steps, its order, requisition, approval,
 * supplier and material details and its follow-ups — Add follow-up and
 * Raise GRN for the merchandiser. Delivery Tracking: every approved line,
 * grouped by where its delivery stands. Nothing reloads.
 */

const card = "rounded-xl border border-border bg-surface";
const STEPS = [
  "APPROVED",
  "SUPPLIER_CONFIRMED",
  "PRODUCTION",
  "READY_FOR_DISPATCH",
  "DISPATCHED",
  "IN_TRANSIT",
  "PARTIALLY_RECEIVED",
  "FULLY_RECEIVED",
  "IN_HOUSE",
];
const dateFmt = new Intl.DateTimeFormat("en-GB", {
  day: "2-digit",
  month: "short",
  year: "numeric",
  timeZone: "Asia/Dhaka",
});
const dateTimeFmt = clockFormat({
  day: "2-digit",
  month: "short",
  year: "numeric",
  hour: "2-digit",
  minute: "2-digit",
  timeZone: "Asia/Dhaka",
});
const day = (iso) => (iso ? dateFmt.format(new Date(iso)) : "—");
const at = (iso) => (iso ? dateTimeFmt.format(new Date(iso)) : "—");
const qty = (n) =>
  Number(n ?? 0).toLocaleString("en-US", { maximumFractionDigits: 3 });
const money = (p) =>
  p
    ? `${p.currency} ${Number(p.unit_price).toLocaleString("en-US", { minimumFractionDigits: 2, maximumFractionDigits: 4 })}`
    : "—";
const dash = (v) => (v === null || v === undefined || v === "" ? "—" : v);
const messagesOf = (result) => {
  const fields = [...new Set(Object.values(result?.errors ?? {}).flat())];
  return fields.length ? fields : [result?.message ?? ""];
};
const statusTone = (s) =>
  s === "APPROVED"
    ? "bg-success-subtle text-success"
    : ["FULLY_RECEIVED", "IN_HOUSE"].includes(s)
      ? "bg-success-subtle text-success"
      : s === "PARTIALLY_RECEIVED"
        ? "bg-warning-subtle text-warning"
        : "bg-info-subtle text-info";

function Fact({ label, value }) {
  return (
    <div className="flex flex-col gap-0.5">
      <span className="text-[11px] font-semibold uppercase tracking-wide text-foreground-muted">
        {label}
      </span>
      <span className="text-sm text-foreground">{dash(value)}</span>
    </div>
  );
}

function StatusChip({ status }) {
  const t = useT("materialprocurement");
  return (
    <span
      className={cn(
        "rounded-md px-1.5 py-0.5 text-xs font-medium whitespace-nowrap",
        statusTone(status),
      )}
    >
      {t(`pr.line_status.${status}`)}
    </span>
  );
}

function FieldBox({ label, required, children, className }) {
  return (
    <label className={cn("flex flex-col gap-1.5", className)}>
      <span className="text-[13px] font-medium text-foreground">
        {label}
        {required ? <span className="text-danger"> *</span> : null}
      </span>
      {children}
    </label>
  );
}

/** The list's rows: material, supplier, price, quantities with a received bar, expected and status. */
function MaterialTable({ rows, selected, onPick, tracking }) {
  const t = useT("materialprocurement");
  const c = (k) => t(`fu.col.${k}`);
  const cols = [
    "material",
    "supplier",
    "price",
    "required",
    "received",
    "balance",
    "expected",
    "status",
    ...(tracking ? ["merchandisers", "next"] : []),
  ];
  return (
    <div className="overflow-x-auto">
      <table className="w-full min-w-[960px] text-sm">
        <thead className="bg-brand text-brand-foreground">
          <tr>
            {cols.map((k) => (
              <th
                key={k}
                className={cn(
                  "px-3 py-2.5 text-left text-xs font-semibold uppercase",
                  ["price", "required", "received", "balance"].includes(k) &&
                    "text-right",
                )}
              >
                {c(k)}
              </th>
            ))}
          </tr>
        </thead>
        <tbody>
          {rows.map((r) => {
            const pct =
              r.req_qty > 0 ? Math.min(100, (r.received / r.req_qty) * 100) : 0;
            return (
              <tr
                key={r.line_id}
                onClick={onPick ? () => onPick(r.line_id) : undefined}
                className={cn(
                  "border-t border-border align-top",
                  onPick && "cursor-pointer hover:bg-surface-muted",
                  selected === r.line_id && "bg-brand-subtle",
                )}
              >
                <td className="px-3 py-2.5">
                  <p className="font-medium text-foreground">{r.item_name}</p>
                  <p className="text-xs text-foreground-muted">
                    {r.pr_no} · {r.order_no ?? t(`pr.type.${r.req_type}`)}
                  </p>
                </td>
                <td className="px-3 py-2.5 text-foreground">{r.supplier}</td>
                <td className="px-3 py-2.5 text-right tabular-nums text-foreground">
                  {money(r.price)}
                </td>
                <td className="px-3 py-2.5 text-right tabular-nums text-foreground">
                  {qty(r.req_qty)} {r.uom}
                </td>
                <td className="px-3 py-2.5 text-right tabular-nums text-foreground">
                  {qty(r.received)}
                  <div className="mt-1 h-1.5 overflow-hidden rounded-full bg-surface-muted">
                    <div
                      className="h-full rounded-full bg-success"
                      style={{ width: `${pct}%` }}
                    />
                  </div>
                </td>
                <td className="px-3 py-2.5 text-right font-semibold tabular-nums text-foreground">
                  {qty(r.balance)}
                </td>
                <td className="px-3 py-2.5 text-foreground">
                  {day(r.expected)}
                </td>
                <td className="px-3 py-2.5">
                  <StatusChip status={r.status} />
                </td>
                {tracking ? (
                  <>
                    <td className="px-3 py-2.5 text-xs text-foreground-muted">
                      <p>{t("fu.req_by", { name: dash(r.requested_by) })}</p>
                      <p>{t("fu.fu_by", { name: dash(r.followup) })}</p>
                    </td>
                    <td
                      className={cn(
                        "px-3 py-2.5",
                        r.next_overdue
                          ? "font-medium text-danger"
                          : "text-foreground",
                      )}
                    >
                      {day(r.next_followup)}
                    </td>
                  </>
                ) : null}
              </tr>
            );
          })}
        </tbody>
      </table>
    </div>
  );
}

/** Add follow-up: date, contact, the step reached, what was received in this delivery, dates and notes. */
function FollowupDialog({ line, onClose, onDone, add }) {
  const t = useT("materialprocurement");
  const f = (k, p) => t(`fu.form.${k}`, p);
  const toastManager = useToastManager();
  const today = new Intl.DateTimeFormat("en-CA", {
    timeZone: "Asia/Dhaka",
  }).format(new Date());
  const [form, setForm] = useState({
    followup_date: today,
    contact_person: "",
    status: line.status,
    received_qty: "",
    expected_delivery: line.expected ?? "",
    actual_delivery: "",
    supplier_response: "",
    remarks: "",
    next_followup_date: "",
  });
  const [errors, setErrors] = useState(null);
  const [pending, startTransition] = useTransition();
  const set = (k) => (v) => setForm((all) => ({ ...all, [k]: v ?? "" }));

  function save() {
    setErrors(null);
    const payload = Object.fromEntries(
      Object.entries(form).map(([k, v]) => [k, v === "" ? null : v]),
    );
    startTransition(async () => {
      const result = await add(line.line_id, payload);
      if (result?.status !== "success") return setErrors(messagesOf(result));
      toastManager.add({ title: f("saved"), type: "success" });
      onDone(result.data);
    });
  }

  return (
    <Modal
      open
      onOpenChange={(v) => !v && onClose()}
      title={f("title")}
      description={f("balance", {
        balance: `${qty(line.balance)} ${line.uom}`,
        required: `${qty(line.req_qty)} ${line.uom}`,
      })}
      className="max-w-2xl"
      footer={
        <>
          <Button variant="outline" onClick={onClose} disabled={pending}>
            {f("cancel")}
          </Button>
          <Button onClick={save} loading={pending}>
            {f("save")}
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
        <div className="grid gap-3 sm:grid-cols-2">
          <FieldBox label={f("date")} required>
            <Input
              type="date"
              value={form.followup_date}
              onChange={(e) => set("followup_date")(e.target.value)}
              aria-label={f("date")}
            />
          </FieldBox>
          <FieldBox label={f("contact")}>
            <Input
              value={form.contact_person}
              onChange={(e) => set("contact_person")(e.target.value)}
              aria-label={f("contact")}
            />
          </FieldBox>
          <FieldBox label={f("status")} required>
            <Select
              value={form.status}
              onValueChange={set("status")}
              options={STEPS.map((s) => ({
                value: s,
                label: t(`pr.line_status.${s}`),
              }))}
              placeholder={f("select")}
              aria-label={f("status")}
            />
          </FieldBox>
          <FieldBox label={f("received")}>
            <div className="flex items-center gap-2">
              <Input
                type="number"
                min="0"
                step="any"
                value={form.received_qty}
                onChange={(e) => set("received_qty")(e.target.value)}
                aria-label={f("received")}
              />
              <span className="text-sm text-foreground-muted">{line.uom}</span>
            </div>
          </FieldBox>
          <FieldBox label={f("expected")}>
            <Input
              type="date"
              value={form.expected_delivery}
              onChange={(e) => set("expected_delivery")(e.target.value)}
              aria-label={f("expected")}
            />
          </FieldBox>
          <FieldBox label={f("actual")}>
            <Input
              type="date"
              value={form.actual_delivery}
              onChange={(e) => set("actual_delivery")(e.target.value)}
              aria-label={f("actual")}
            />
          </FieldBox>
          <FieldBox label={f("response")} className="sm:col-span-2">
            <Textarea
              rows={2}
              value={form.supplier_response}
              onChange={(e) => set("supplier_response")(e.target.value)}
              aria-label={f("response")}
            />
          </FieldBox>
          <FieldBox label={f("remarks")} className="sm:col-span-2">
            <Textarea
              rows={2}
              value={form.remarks}
              onChange={(e) => set("remarks")(e.target.value)}
              aria-label={f("remarks")}
            />
          </FieldBox>
          <FieldBox label={f("next")}>
            <Input
              type="date"
              value={form.next_followup_date}
              onChange={(e) => set("next_followup_date")(e.target.value)}
              aria-label={f("next")}
            />
          </FieldBox>
        </div>
      </div>
    </Modal>
  );
}

/** Raise GRN: the company, the receiving store and the supplier's challan. */
function GrnDialog({ line, followup, options, onClose, onDone, raise }) {
  const t = useT("materialprocurement");
  const g = (k, p) => t(`fu.grn.${k}`, p);
  const toastManager = useToastManager();
  const [form, setForm] = useState({
    unit_company_id:
      options.companies.length === 1 ? options.companies[0].value : "",
    store_id: "",
    challan_no: "",
  });
  const [errors, setErrors] = useState(null);
  const [pending, startTransition] = useTransition();
  const set = (k) => (v) => setForm((all) => ({ ...all, [k]: v ?? "" }));

  function save() {
    setErrors(null);
    startTransition(async () => {
      const result = await raise(followup.id, {
        ...form,
        unit_company_id: form.unit_company_id || null,
        store_id: form.store_id || null,
        challan_no: form.challan_no || null,
      });
      if (result?.status !== "success") return setErrors(messagesOf(result));
      toastManager.add({
        title: g("raised", { no: result.data.grn_no }),
        type: "success",
      });
      onDone(result.data.line);
    });
  }

  return (
    <Modal
      open
      onOpenChange={(v) => !v && onClose()}
      title={g("title")}
      description={g("hint", {
        qty: qty(followup.received_qty),
        uom: line.uom,
        item: line.item_name,
        supplier: line.supplier,
      })}
      footer={
        <>
          <Button variant="outline" onClick={onClose} disabled={pending}>
            {g("cancel")}
          </Button>
          <Button onClick={save} loading={pending}>
            {g("confirm")}
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
        <FieldBox label={g("company")} required>
          <Select
            value={form.unit_company_id}
            onValueChange={set("unit_company_id")}
            options={options.companies}
            placeholder={g("select")}
            aria-label={g("company")}
          />
        </FieldBox>
        <FieldBox label={g("store")} required>
          <Select
            value={form.store_id}
            onValueChange={set("store_id")}
            options={options.stores}
            placeholder={g("select")}
            aria-label={g("store")}
          />
        </FieldBox>
        <FieldBox label={g("challan")}>
          <Input
            value={form.challan_no}
            onChange={(e) => set("challan_no")(e.target.value)}
            aria-label={g("challan")}
          />
        </FieldBox>
      </div>
    </Modal>
  );
}

/** One line in full, below the list. */
function LineDetail({ line, onAdd, onRaise }) {
  const t = useT("materialprocurement");
  const f = (k) => t(`fu.f.${k}`);
  const s = (k) => t(`fu.sec.${k}`);
  const current = STEPS.indexOf(line.status);
  const pct =
    line.req_qty > 0 ? Math.min(100, (line.received / line.req_qty) * 100) : 0;
  const editable = line.mine && !line.closed;
  return (
    <div className="flex flex-col gap-4">
      <section className={cn(card, "flex flex-col gap-4 p-5")}>
        <div className="flex flex-wrap items-start justify-between gap-3">
          <div>
            <h2 className="text-base font-semibold text-foreground">
              {line.item_name} — {line.pr_no}
            </h2>
            <p className="text-[13px] text-foreground-muted">
              {t("fu.supplier_line", {
                supplier: line.supplier,
                price: money(line.price),
                uom: line.uom,
              })}
            </p>
          </div>
          {line.closed ? (
            <span className="rounded-full bg-success-subtle px-2 py-0.5 text-xs font-medium text-success">
              {t("fu.closed_badge")}
            </span>
          ) : !line.mine ? (
            <span className="rounded-full bg-surface-muted px-2 py-0.5 text-xs font-medium text-foreground-muted">
              {t("fu.monitoring")}
            </span>
          ) : null}
        </div>
        <div className="grid gap-3 sm:grid-cols-3">
          {[
            ["required", line.req_qty],
            ["received", line.received],
            ["balance", line.balance],
          ].map(([k, v]) => (
            <div key={k} className="rounded-lg border border-border px-4 py-3">
              <p className="text-[11px] font-semibold uppercase tracking-wide text-foreground-muted">
                {t(`fu.box.${k}`)}
              </p>
              <p className="text-lg font-semibold tabular-nums text-foreground">
                {qty(v)} {line.uom}
              </p>
            </div>
          ))}
        </div>
        <div className="h-1.5 overflow-hidden rounded-full bg-surface-muted">
          <div
            className="h-full rounded-full bg-success"
            style={{ width: `${pct}%` }}
          />
        </div>
        <ol className="grid grid-cols-3 gap-y-3 sm:grid-cols-9">
          {STEPS.map((step, i) => (
            <li
              key={step}
              className="flex flex-col items-center gap-1 text-center"
            >
              <span
                className={cn(
                  "flex size-7 items-center justify-center rounded-full border text-xs font-medium",
                  i <= current
                    ? "border-success bg-success text-white"
                    : "border-border text-foreground-muted",
                )}
              >
                {i <= current ? (
                  <Check className="size-4" aria-hidden />
                ) : (
                  i + 1
                )}
              </span>
              <span
                className={cn(
                  "text-[11px]",
                  i <= current ? "text-foreground" : "text-foreground-muted",
                )}
              >
                {t(`pr.line_status.${step}`)}
              </span>
            </li>
          ))}
        </ol>
      </section>
      <div className="grid gap-4 lg:grid-cols-2">
        <section className={cn(card, "flex flex-col gap-3 p-5")}>
          <h3 className="text-sm font-semibold text-foreground">
            {s("order")}
          </h3>
          {line.order ? (
            <div className="grid gap-3 sm:grid-cols-3">
              <Fact label={f("order_no")} value={line.order.order_no} />
              <Fact label={f("buyer")} value={line.order.buyer} />
              <Fact label={f("style")} value={line.order.style} />
              <Fact
                label={f("order_qty")}
                value={`${qty(line.order.order_qty)} ${line.order.order_unit ?? ""}`}
              />
              <Fact
                label={f("delivery_date")}
                value={day(line.order.delivery_date)}
              />
            </div>
          ) : (
            <p className="text-sm text-foreground-muted">{t("fu.no_order")}</p>
          )}
        </section>
        <section className={cn(card, "flex flex-col gap-3 p-5")}>
          <h3 className="text-sm font-semibold text-foreground">
            {s("requisition")}
          </h3>
          <div className="grid gap-3 sm:grid-cols-3">
            <Fact label={f("pr_no")} value={line.requisition.pr_no} />
            <Fact
              label={f("req_type")}
              value={t(`pr.type.${line.requisition.req_type}`)}
            />
            <Fact
              label={f("req_date")}
              value={day(line.requisition.req_date)}
            />
            <Fact label={f("created_by")} value={line.requisition.created_by} />
            <Fact
              label={f("merchandiser")}
              value={line.requisition.merchandiser}
            />
            <Fact label={f("department")} value={line.requisition.department} />
          </div>
        </section>
        <section className={cn(card, "flex flex-col gap-3 p-5")}>
          <h3 className="text-sm font-semibold text-foreground">
            {s("approval")}
          </h3>
          <div className="grid gap-3 sm:grid-cols-3">
            <Fact label={f("qt_no")} value={line.approval.qt_no} />
            <Fact
              label={f("approved_supplier")}
              value={line.approval.supplier}
            />
            <Fact
              label={f("approved_price")}
              value={money(
                line.approval.price != null
                  ? {
                      currency: line.approval.currency,
                      unit_price: line.approval.price,
                    }
                  : null,
              )}
            />
            <Fact label={f("currency")} value={line.approval.currency} />
            <Fact
              label={f("lead_time")}
              value={
                line.approval.lead_time_days != null
                  ? t("fu.days", { n: line.approval.lead_time_days })
                  : null
              }
            />
            <Fact label={f("approved_by")} value={line.approval.approved_by} />
            <Fact
              label={f("approved_date")}
              value={at(line.approval.approved_at)}
            />
            <Fact label={f("assigned")} value={line.approval.merchandiser} />
          </div>
        </section>
        <section className={cn(card, "flex flex-col gap-3 p-5")}>
          <h3 className="text-sm font-semibold text-foreground">
            {s("supplier")}
          </h3>
          <div className="grid gap-3 sm:grid-cols-3">
            <Fact label={f("code")} value={line.supplier_info.code} />
            <Fact label={f("company")} value={line.supplier_info.name} />
            <Fact
              label={f("contact")}
              value={line.supplier_info.contact_person}
            />
            <Fact label={f("goods")} value={line.supplier_info.goods_service} />
            <Fact label={f("vat")} value={line.supplier_info.vat_reg_no} />
            <Fact label={f("tin")} value={line.supplier_info.tin_no} />
            <Fact label={f("address")} value={line.supplier_info.address} />
            <Fact label={f("payment_terms")} value={f("payment_terms_value")} />
          </div>
        </section>
      </div>
      <section className={cn(card, "flex flex-col gap-3 p-5")}>
        <h3 className="text-sm font-semibold text-foreground">
          {s("material")}
        </h3>
        <div className="grid gap-3 sm:grid-cols-3">
          <Fact label={f("item_code")} value={line.material.item_code} />
          <Fact label={f("item")} value={line.material.item} />
          <Fact label={f("category")} value={line.material.category} />
          <Fact label={f("colour")} value={line.material.colour} />
          <Fact label={f("size")} value={line.material.size} />
          <Fact label={f("gsm_width")} value={line.material.gsm_width} />
          <Fact
            label={f("specification")}
            value={line.material.specification}
          />
          <Fact
            label={f("required_date")}
            value={day(line.material.req_date)}
          />
          <Fact
            label={f("expected_delivery")}
            value={day(line.material.expected_delivery)}
          />
        </div>
      </section>
      <section className={cn(card, "min-w-0 overflow-hidden")}>
        <div className="flex flex-wrap items-start justify-between gap-3 p-5">
          <div>
            <h3 className="text-sm font-semibold text-foreground">
              {s("followups")}
            </h3>
            <p className="text-[13px] text-foreground-muted">
              {t("fu.fu_hint")}
            </p>
          </div>
          {editable ? (
            <Button size="sm" onClick={onAdd}>
              {t("fu.add")}
            </Button>
          ) : null}
        </div>
        <div className="overflow-x-auto">
          <table className="w-full min-w-[960px] text-sm">
            <thead className="bg-brand text-brand-foreground">
              <tr>
                {[
                  "date",
                  "contact",
                  "response",
                  "status",
                  "expected",
                  "actual",
                  "received",
                  "next",
                  "grn",
                  "by",
                ].map((k) => (
                  <th
                    key={k}
                    className={cn(
                      "px-3 py-2.5 text-left text-xs font-semibold uppercase",
                      k === "received" && "text-right",
                    )}
                  >
                    {t(`fu.fu_col.${k}`)}
                  </th>
                ))}
              </tr>
            </thead>
            <tbody>
              {line.followups.length === 0 ? (
                <tr>
                  <td
                    colSpan={10}
                    className="px-3 py-8 text-center text-foreground-muted"
                  >
                    {t("fu.no_followups")}
                  </td>
                </tr>
              ) : null}
              {line.followups.map((x) => (
                <Fragment key={x.id}>
                  <tr className="border-t border-border align-top">
                    <td className="px-3 py-2.5 whitespace-nowrap text-foreground">
                      {day(x.date)}
                    </td>
                    <td className="px-3 py-2.5 text-foreground">
                      {dash(x.contact_person)}
                    </td>
                    <td className="px-3 py-2.5 text-foreground">
                      {dash(x.supplier_response)}
                      {x.remarks ? (
                        <p className="text-xs text-foreground-muted">
                          {x.remarks}
                        </p>
                      ) : null}
                    </td>
                    <td className="px-3 py-2.5">
                      <StatusChip status={x.status} />
                    </td>
                    <td className="px-3 py-2.5 whitespace-nowrap text-foreground">
                      {day(x.expected_delivery)}
                    </td>
                    <td className="px-3 py-2.5 whitespace-nowrap text-foreground">
                      {day(x.actual_delivery)}
                    </td>
                    <td className="px-3 py-2.5 text-right tabular-nums text-foreground">
                      {x.received_qty ? qty(x.received_qty) : "—"}
                    </td>
                    <td className="px-3 py-2.5 whitespace-nowrap text-foreground">
                      {day(x.next_followup_date)}
                    </td>
                    <td className="px-3 py-2.5">
                      {x.grn_no ? (
                        <span className="font-mono text-xs text-foreground">
                          {x.grn_no}
                        </span>
                      ) : x.can_raise_grn && line.mine ? (
                        <Button
                          size="sm"
                          variant="outline"
                          onClick={() => onRaise(x)}
                        >
                          {t("fu.grn.raise")}
                        </Button>
                      ) : (
                        "—"
                      )}
                    </td>
                    <td className="px-3 py-2.5 text-foreground">
                      {dash(x.by)}
                    </td>
                  </tr>
                </Fragment>
              ))}
            </tbody>
          </table>
        </div>
      </section>
    </div>
  );
}

/**
 * Assigned Materials.
 *
 * @param {{ data: object, act: (op: string, ...args: any[]) => Promise<object> }} props
 */
function AssignedMaterials({ data, act }) {
  const t = useT("materialprocurement");
  const [scope, setScope] = useState("mine");
  const [rows, setRows] = useState(data.rows);
  const [line, setLine] = useState(null);
  const [dialog, setDialog] = useState(null);
  const [grnOptions, setGrnOptions] = useState(null);
  const [errors, setErrors] = useState(null);
  const [pending, startTransition] = useTransition();

  function switchTo(next) {
    setScope(next);
    setLine(null);
    startTransition(async () => {
      const result = await act("list", next);
      if (result?.status === "success") setRows(result.data.rows);
    });
  }

  function pick(id) {
    setErrors(null);
    startTransition(async () => {
      const result = await act("show", id);
      if (result?.status !== "success") return setErrors(messagesOf(result));
      setLine(result.data);
    });
  }

  function refreshWith(detail) {
    setDialog(null);
    setLine(detail);
    setRows((all) =>
      all.map((r) => (r.line_id === detail.line_id ? { ...r, ...detail } : r)),
    );
  }

  function openGrn(followup) {
    startTransition(async () => {
      const options = grnOptions ?? (await act("grnOptions"))?.data;
      if (options) {
        setGrnOptions(options);
        setDialog({ kind: "grn", followup });
      }
    });
  }

  return (
    <div className="flex flex-col gap-4">
      <section className={cn(card, "min-w-0 overflow-hidden")}>
        <div className="flex flex-wrap items-start justify-between gap-3 p-5">
          <div>
            <h2 className="text-base font-semibold text-foreground">
              {scope === "mine" ? t("fu.mine_title") : t("fu.all_title")}
            </h2>
            <p className="mt-0.5 text-[13px] text-foreground-muted">
              {t("fu.hint")}
            </p>
          </div>
          {data.can_monitor ? (
            <div role="tablist" className="flex gap-2">
              {["mine", "all"].map((k) => (
                <button
                  key={k}
                  type="button"
                  role="tab"
                  aria-selected={scope === k}
                  onClick={() => switchTo(k)}
                  disabled={pending}
                  className={cn(
                    "rounded-full border px-3 py-1 text-[13px] font-medium",
                    scope === k
                      ? "border-brand bg-brand text-brand-foreground"
                      : "border-border text-foreground hover:bg-surface-muted",
                  )}
                >
                  {k === "mine" ? t("fu.tab_mine") : t("fu.tab_all")}
                </button>
              ))}
            </div>
          ) : null}
        </div>
        {rows.length ? (
          <MaterialTable rows={rows} selected={line?.line_id} onPick={pick} />
        ) : (
          <p className="border-t border-border px-5 py-10 text-center text-sm text-foreground-muted">
            {scope === "mine" ? t("fu.empty_mine") : t("fu.empty_all")}
          </p>
        )}
      </section>
      {errors ? (
        <Alert variant="danger">
          {errors.map((e) => (
            <p key={e}>{e}</p>
          ))}
        </Alert>
      ) : null}
      {line ? (
        <LineDetail
          line={line}
          onAdd={() => setDialog({ kind: "followup" })}
          onRaise={openGrn}
        />
      ) : null}
      {dialog?.kind === "followup" ? (
        <FollowupDialog
          line={line}
          onClose={() => setDialog(null)}
          onDone={refreshWith}
          add={(id, payload) => act("followup", id, payload)}
        />
      ) : null}
      {dialog?.kind === "grn" ? (
        <GrnDialog
          line={line}
          followup={dialog.followup}
          options={grnOptions}
          onClose={() => setDialog(null)}
          onDone={refreshWith}
          raise={(id, payload) => act("grn", id, payload)}
        />
      ) : null}
    </div>
  );
}

/** Delivery Tracking: every approved line, grouped by where its delivery stands. */
function DeliveryTracking({ data }) {
  const t = useT("materialprocurement");
  const [pill, setPill] = useState("all");
  const [needle, setNeedle] = useState("");
  const groups = {
    all: () => true,
    before: (r) =>
      [
        "APPROVED",
        "SUPPLIER_CONFIRMED",
        "PRODUCTION",
        "READY_FOR_DISPATCH",
      ].includes(r.status),
    moving: (r) => ["DISPATCHED", "IN_TRANSIT"].includes(r.status),
    partial: (r) => r.status === "PARTIALLY_RECEIVED",
    received: (r) => ["FULLY_RECEIVED", "IN_HOUSE"].includes(r.status),
  };
  const q = needle.trim().toLowerCase();
  const shown = data.rows
    .filter(groups[pill])
    .filter(
      (r) =>
        !q ||
        [r.item_name, r.pr_no, r.order_no, r.supplier, r.followup].some((v) =>
          (v ?? "").toLowerCase().includes(q),
        ),
    );

  return (
    <section className={cn(card, "min-w-0 overflow-hidden")}>
      <div className="flex flex-col gap-4 p-5">
        <div>
          <h2 className="text-base font-semibold text-foreground">
            {t("fu.tracking_title")}
          </h2>
          <p className="mt-0.5 text-[13px] text-foreground-muted">
            {t("fu.tracking_hint")}
          </p>
        </div>
        <div className="flex flex-wrap items-center justify-between gap-3">
          <div role="tablist" className="flex flex-wrap gap-2">
            {Object.entries(groups).map(([k, fn]) => (
              <button
                key={k}
                type="button"
                role="tab"
                aria-selected={pill === k}
                onClick={() => setPill(k)}
                className={cn(
                  "inline-flex items-center gap-2 rounded-full border px-3 py-1 text-[13px] font-medium",
                  pill === k
                    ? "border-brand bg-brand text-brand-foreground"
                    : "border-border text-foreground hover:bg-surface-muted",
                )}
              >
                {t(`fu.pill.${k}`)}
                <span
                  className={cn(
                    "rounded-full px-1.5 text-xs tabular-nums",
                    pill === k ? "bg-brand-foreground/20" : "bg-surface-muted",
                  )}
                >
                  {data.rows.filter(fn).length}
                </span>
              </button>
            ))}
          </div>
          <div className="relative w-72">
            <Search
              className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-foreground-muted"
              aria-hidden
            />
            <Input
              value={needle}
              onChange={(e) => setNeedle(e.target.value)}
              placeholder={t("fu.search")}
              aria-label={t("fu.search")}
              className="pl-9"
            />
          </div>
        </div>
      </div>
      {shown.length ? (
        <MaterialTable rows={shown} tracking />
      ) : (
        <p className="border-t border-border px-5 py-10 text-center text-sm text-foreground-muted">
          {t("fu.empty_all")}
        </p>
      )}
    </section>
  );
}

export { AssignedMaterials, DeliveryTracking };
