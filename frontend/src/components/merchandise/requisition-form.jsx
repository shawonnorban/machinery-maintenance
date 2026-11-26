"use client";

import { useState, useTransition } from "react";
import Link from "next/link";
import { Pencil, RotateCcw, Save, X } from "lucide-react";
import { Input } from "@/components/ui/input";
import { Select } from "@/components/ui/select";
import { Checkbox } from "@/components/ui/checkbox";
import { Textarea } from "@/components/ui/textarea";
import { Button, buttonVariants } from "@/components/ui/button";
import { Alert } from "@/components/ui/alert";
import { useToastManager } from "@/components/ui/toast";
import { useT } from "@/lib/i18n";

// An error's field messages, or its message when it names no field (an empty `errors` is no message at all).
const messagesOf = (result) => {
  const fields = [...new Set(Object.values(result?.errors ?? {}).flat())];
  return fields.length ? fields : [result?.message ?? ""];
};

const str = (v) => (v === null || v === undefined ? "" : String(v));
const blank = () => ({ booking_date: "", style_id: "", order_id: "", buyer_office_id: "", work_style_no: "", ref_no: "", sp_no: "", ship_date: "", status: "DRAFT", send_mail: false, remarks: "" });
const fromRow = (r) => ({ ...blank(), ...Object.fromEntries(Object.keys(blank()).map((k) => [k, typeof r[k] === "boolean" ? r[k] : str(r[k])])) });
// Brickland's "16 Oct 2026".
const day = (v) => new Intl.DateTimeFormat("en-GB", { day: "numeric", month: "short", year: "numeric", timeZone: "UTC" }).format(new Date(`${v}T00:00:00Z`));

/**
 * Additional Fabric Booking Entry → New requisition / Edit requisition
 * (AnnotechERP 06/11), as Brickland lays it out: the requisition's header in
 * three columns — the fabric lines themselves are added on Booking Items.
 * A new requisition is saved and the form cleared for the next; an edited
 * one is updated (a new revision) or left as it was with Cancel.
 *
 * @param {{ prefix: string, options: object, initial: ?object, save: Function, recordsHref: string }} props
 */
function RequisitionForm({ prefix, options, initial, save, recordsHref }) {
  const t = useT("merchandise");
  const m = (k, p) => t(`master.${prefix}.${k}`, p);
  const toastManager = useToastManager();
  const [form, setForm] = useState(() => (initial ? fromRow(initial) : blank()));
  const [errors, setErrors] = useState(null);
  const [pending, startTransition] = useTransition();
  const set = (k) => (v) => setForm((f) => ({ ...f, [k]: v ?? "" }));
  const orders = options.orders.filter((o) => !form.style_id || o.style_id === form.style_id);
  const pick = (list) => [{ value: "", label: m("select") }, ...list.map(({ value, label }) => ({ value, label }))];
  const style = options.styles.find((s) => s.value === form.style_id);
  const office = options.offices.find((o) => o.value === (form.buyer_office_id || style?.buyer_office_id));

  function pickOrder(id) {
    const order = options.orders.find((o) => o.value === id);
    setForm((f) => ({ ...f, order_id: id, style_id: f.style_id || order?.style_id || "", ship_date: f.ship_date || order?.ship_date || "" }));
  }

  function submit() {
    startTransition(async () => {
      const payload = Object.fromEntries(Object.entries(form).map(([k, v]) => [k, v === "" ? null : v]));
      const result = await save(initial?.booking_no ?? null, payload);
      if (result?.status === "success") {
        setErrors(null);
        toastManager.add({ title: m(initial ? "updated" : "saved", { no: result.data.booking_no, rev: result.data.revision }), type: "success" });
        if (!initial) setForm(blank());
      } else {
        setErrors(messagesOf(result));
      }
    });
  }

  const field = "flex flex-col gap-1.5";
  const lab = "text-[13px] font-semibold text-foreground";
  const star = <span className="text-danger">*</span>;
  return (
    <div className="flex flex-col gap-4">
      <section className="rounded-xl border border-border bg-surface p-5">
        <h2 className="flex items-center gap-2 text-base font-semibold text-foreground">
          {initial ? <Pencil className="size-4" /> : null}
          {initial ? m("edit_title") : m("new_title")}
        </h2>
        <p className="mt-0.5 text-[13px] text-foreground-muted">{initial ? m("edit_hint", { date: day(initial.booking_date) }) : m("new_hint")}</p>
        <div className="mt-4 grid gap-4 md:grid-cols-3">
          <label className={field}>
            <span className={lab}>
              {m("f.booking_date")}
              {star}
            </span>
            <Input type="date" value={form.booking_date} onChange={(e) => set("booking_date")(e.target.value)} aria-label={m("f.booking_date")} />
          </label>
          <div className={field}>
            <span className={lab}>{m("f.style")}</span>
            <Select value={form.style_id} onValueChange={(v) => setForm((f) => ({ ...f, style_id: v ?? "", order_id: "" }))} options={pick(options.styles)} placeholder={m("select")} aria-label={m("f.style")} />
          </div>
          <div className={field}>
            <span className={lab}>{m("f.order")}</span>
            <Select value={form.order_id} onValueChange={(v) => pickOrder(v ?? "")} options={pick(orders)} placeholder={m("select")} aria-label={m("f.order")} />
          </div>
          <div className={field}>
            <span className={lab}>{m("f.buyer_ac")}</span>
            <Select value={form.buyer_office_id} onValueChange={set("buyer_office_id")} options={pick(options.offices)} placeholder={office?.label ?? m("select")} aria-label={m("f.buyer_ac")} />
            <span className="text-xs text-foreground-muted">{m("buyer_hint")}</span>
          </div>
          <div className={field}>
            <span className={lab}>{m("f.company")}</span>
            <Select value="current" options={[{ value: "current", label: options.company }]} aria-label={m("f.company")} />
          </div>
          <label className={field}>
            <span className={lab}>{m("f.work_style")}</span>
            <Input value={form.work_style_no} onChange={(e) => set("work_style_no")(e.target.value)} maxLength={64} aria-label={m("f.work_style")} />
          </label>
          <label className={field}>
            <span className={lab}>{m("f.ref")}</span>
            <Input value={form.ref_no} onChange={(e) => set("ref_no")(e.target.value)} maxLength={64} aria-label={m("f.ref")} />
          </label>
          <label className={field}>
            <span className={lab}>{m("f.sp")}</span>
            <Input value={form.sp_no} onChange={(e) => set("sp_no")(e.target.value)} maxLength={32} aria-label={m("f.sp")} />
          </label>
          <label className={field}>
            <span className={lab}>{m("f.ship_date")}</span>
            <Input type="date" value={form.ship_date} onChange={(e) => set("ship_date")(e.target.value)} aria-label={m("f.ship_date")} />
          </label>
          <div className={field}>
            <span className={lab}>{m("f.status")}</span>
            <Select value={form.status} onValueChange={(v) => v && set("status")(v)} options={options.statuses.map((s) => ({ value: s, label: m(`status.${s}`) }))} aria-label={m("f.status")} />
          </div>
          <div className="flex items-end pb-2">
            <label className="flex items-center gap-2 text-sm font-semibold text-foreground">
              <Checkbox checked={form.send_mail} onCheckedChange={(c) => set("send_mail")(c === true)} />
              {m("f.send_mail")}
            </label>
          </div>
          <label className={`${field} md:col-span-3`}>
            <span className={lab}>{m("f.remarks")}</span>
            <Textarea rows={3} value={form.remarks} onChange={(e) => set("remarks")(e.target.value)} maxLength={2000} aria-label={m("f.remarks")} />
          </label>
        </div>
      </section>

      {errors ? <Alert variant="danger">{errors.join(" · ")}</Alert> : null}
      <div className="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-border bg-surface px-5 py-3">
        <span className="text-[13px] text-foreground-muted">{m("audit")}</span>
        <div className="flex gap-2">
          {initial ? (
            <Link href={recordsHref} className={buttonVariants({ variant: "outline" })}>
              <X /> {m("cancel")}
            </Link>
          ) : (
            <Button
              variant="outline"
              onClick={() => {
                setForm(blank());
                setErrors(null);
              }}
              disabled={pending}
            >
              <RotateCcw /> {m("reset")}
            </Button>
          )}
          <Button onClick={submit} loading={pending}>
            <Save /> {initial ? m("update") : m("save")}
          </Button>
        </div>
      </div>
    </div>
  );
}

export { RequisitionForm };
