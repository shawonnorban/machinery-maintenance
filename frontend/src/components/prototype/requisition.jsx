"use client";

import { useState, useTransition } from "react";
import { useRouter } from "next/navigation";
import { Ban, Gavel, RotateCcw, Save, Send, UserCheck } from "lucide-react";
import { cn } from "@/lib/utils";
import { Input } from "@/components/ui/input";
import { Select } from "@/components/ui/select";
import { Textarea } from "@/components/ui/textarea";
import { Button } from "@/components/ui/button";
import { ConfirmDialog } from "@/components/ui/confirm-dialog";
import { Alert } from "@/components/ui/alert";
import { useToastManager } from "@/components/ui/toast";
import { useT } from "@/lib/i18n";
import { FormattedDateTime } from "@/components/ui/formatted-date-time";
import { Field, ProtoList, ReadOnly, Section, blank, card, messagesOf, str, todayIso } from "@/components/prototype/proto-kit";

/*
 * Prototype Desk → Sample Requisition (AnnotechERP 07/03), Brickland's three
 * tabs: the Requisition List (filters, status pills, Open · Decide · Submit ·
 * Assign · Cancel on each row), the Sample Requisition form (an existing one
 * loaded back in place, or a new one raised) and Requisition Approval (the
 * level it is pending at records Approve or Reject). Nothing reloads.
 */

const dayFmt = new Intl.DateTimeFormat("en-GB", { day: "2-digit", month: "short", year: "numeric", timeZone: "UTC" });
const FIELDS = ["req_date", "buyer_id", "buyer_office_id", "order_id", "style_id", "season", "product_category", "sample_type_id", "sample_stage", "qty", "required_date", "priority", "requested_by", "department_id", "assigned_to", "remarks", "attachment"];
const blankForm = () => ({ ...Object.fromEntries(FIELDS.map((k) => [k, ""])), req_date: todayIso() });
const formOf = (row) => (row ? Object.fromEntries(FIELDS.map((k) => [k, str(row[k])])) : blankForm());

/** The decisions taken on a requisition, level by level; never edited or removed. */
function History({ decisions }) {
  const t = useT("prototype");
  return (
    <section className={cn(card, "p-4")}>
      <h3 className="text-sm font-semibold text-foreground">{t("ui.req.history")}</h3>
      <p className="mt-0.5 text-xs text-foreground-muted">{t("ui.req.history_hint")}</p>
      <div className="mt-3 overflow-x-auto">
        <table className="w-full text-[13px]">
          <thead>
            <tr className="border-b border-border text-left text-foreground-muted">
              {["level", "decision", "decided_by", "decided_at", "comments", "rejection_reason"].map((k) => (
                <th key={k} className="px-2 py-2 font-medium">
                  {t(`ui.req.col.${k}`)}
                </th>
              ))}
            </tr>
          </thead>
          <tbody>
            {(decisions ?? []).length === 0 ? (
              <tr>
                <td colSpan={6} className="px-2 py-6 text-center text-foreground-muted">
                  <p className="font-medium text-foreground">{t("ui.no_lines")}</p>
                  <p className="text-xs">{t("ui.no_lines_hint")}</p>
                </td>
              </tr>
            ) : (
              decisions.map((d, i) => (
                <tr key={i} className="border-b border-border last:border-0">
                  <td className="px-2 py-2">{t(`level.${d.level}`)}</td>
                  <td className="px-2 py-2">{t(`decision.${d.decision}`)}</td>
                  <td className="px-2 py-2">{d.decided_by ?? "—"}</td>
                  <td className="px-2 py-2">
                    <FormattedDateTime value={d.decided_at} />
                  </td>
                  <td className="px-2 py-2">{d.comments ?? "—"}</td>
                  <td className="px-2 py-2">{d.rejection_reason ?? "—"}</td>
                </tr>
              ))
            )}
          </tbody>
        </table>
      </div>
    </section>
  );
}

/** Requisition List: Brickland's filters and pills, and each row's own buttons. */
function RequisitionList({ rows, options, canManage, canApprove, actions }) {
  const t = useT("prototype");
  const router = useRouter();
  const toastManager = useToastManager();
  const [confirm, setConfirm] = useState(null);
  const [pending, startTransition] = useTransition();
  const label = (list, value) => list.find((o) => o.value === value)?.label;
  const data = rows.map((r) => ({ ...r, buyer_label: r.buyer, pending_label: r.pending_level ? t(`level.${r.pending_level}`) : null, required_label: `${dayFmt.format(new Date(`${r.required_date}T00:00:00Z`))}${r.overdue ? ` (${t("ui.overdue").toLowerCase()})` : ""}` }));
  function run() {
    const { action, row } = confirm;
    startTransition(async () => {
      const result = await actions.act("requisitions", row.req_no, action);
      if (result?.status !== "success") return toastManager.add({ title: messagesOf(result).join(" "), type: "danger" });
      toastManager.add({ title: t(`ui.req.done.${action}`, { no: row.req_no }), type: "success" });
      setConfirm(null);
      router.refresh();
    });
  }
  return (
    <>
      <ProtoList
        rows={data}
        rowKey="req_no"
        exportName="sample-requisitions"
        title={t("ui.req.list")}
        subtitle={t("ui.req.list_hint")}
        emptyText={t("ui.req.empty")}
        addHref={canManage ? "?tab=requisition" : undefined}
        addLabel={t("ui.req.new")}
        openHref="?tab=requisition&req="
        filters={[
          { key: "date", label: t("ui.req.f.req_date"), field: "req_date", range: true },
          { key: "buyer", label: t("ui.req.f.buyer"), field: "buyer_id", options: options.buyers.map((b) => ({ value: b.value, label: b.label })) },
          { key: "type", label: t("ui.req.f.sample_type"), field: "sample_type_id", options: options.sample_types.map((s) => ({ value: s.value, label: s.label })) },
          { key: "priority", label: t("ui.req.f.priority"), field: "priority", options: options.priorities.map((p) => ({ value: p, label: t(`priority.${p}`) })) },
          { key: "status", label: t("ui.req.f.status"), field: "status", options: ["DRAFT", "SUBMITTED", "PENDING_APPROVAL", "APPROVED", "REJECTED", "ASSIGNED", "IN_DEVELOPMENT", "COMPLETED", "CANCELLED"].map((s) => ({ value: s, label: t(`status.${s}`) })) },
        ]}
        pills={[
          { key: "draft", label: t("status.DRAFT"), field: "status", value: "DRAFT" },
          { key: "pending", label: t("status.PENDING_APPROVAL"), field: "status", value: "PENDING_APPROVAL" },
          { key: "approved", label: t("status.APPROVED"), field: "status", value: "APPROVED" },
          { key: "rejected", label: t("status.REJECTED"), field: "status", value: "REJECTED" },
          { key: "overdue", label: t("ui.overdue"), field: "overdue", value: true },
        ]}
        columns={[
          { key: "req_no", label: t("ui.req.col.req_no"), type: "mono" },
          { key: "req_date", label: t("ui.req.col.date"), type: "date" },
          { key: "buyer_label", label: t("ui.req.col.buyer") },
          { key: "style_name", label: t("ui.req.col.style") },
          { key: "sample_type", label: t("ui.req.col.sample_type") },
          { key: "sample_stage", label: t("ui.req.col.stage"), type: "badge", labels: Object.fromEntries(options.stages.map((s) => [s, t(`stage.${s}`)])) },
          { key: "qty", label: t("ui.req.col.qty"), type: "number" },
          { key: "required_label", label: t("ui.req.col.required") },
          { key: "priority", label: t("ui.req.col.priority"), type: "badge", labels: Object.fromEntries(options.priorities.map((p) => [p, t(`priority.${p}`)])) },
          { key: "pending_label", label: t("ui.req.col.pending_at") },
          { key: "sample_no", label: t("ui.req.col.sample_no"), type: "mono" },
          { key: "status", label: t("ui.req.col.status"), type: "badge", labels: Object.fromEntries(["DRAFT", "SUBMITTED", "PENDING_APPROVAL", "APPROVED", "REJECTED", "ASSIGNED", "IN_DEVELOPMENT", "COMPLETED", "CANCELLED"].map((s) => [s, t(`status.${s}`)])) },
        ]}
        rowActions={[
          ...(canApprove ? [{ key: "decide", label: t("ui.req.decide"), icon: <Gavel />, onClick: (row) => router.push(`?tab=approval&req=${encodeURIComponent(row.req_no)}`), disabled: (row) => row.status !== "PENDING_APPROVAL" }] : []),
          ...(canManage ? [{ key: "submit", label: t("ui.req.submit"), icon: <Send />, onClick: (row) => setConfirm({ action: "submit", row }), disabled: (row) => row.status !== "DRAFT" }] : []),
          ...(canApprove ? [{ key: "assign", label: t("ui.req.assign"), icon: <UserCheck />, onClick: (row) => setConfirm({ action: "assign", row }), disabled: (row) => row.status !== "APPROVED" }] : []),
          ...(canManage ? [{ key: "cancel", label: t("ui.req.cancel"), icon: <Ban />, onClick: (row) => setConfirm({ action: "cancel", row }), disabled: (row) => ["IN_DEVELOPMENT", "COMPLETED", "CANCELLED"].includes(row.status) }] : []),
        ]}
      />
      <ConfirmDialog
        open={confirm !== null}
        onOpenChange={(o) => !o && setConfirm(null)}
        title={confirm ? t(`ui.req.confirm.${confirm.action}_title`) : ""}
        description={confirm ? t(`ui.req.confirm.${confirm.action}_hint`, { no: confirm.row.req_no, who: label(options.users, confirm.row.assigned_to) ?? t("ui.none") }) : ""}
        confirmLabel={confirm ? t(`ui.req.${confirm.action}`) : ""}
        cancelLabel={t("ui.req.cancel_dialog")}
        destructive={confirm?.action === "cancel"}
        loading={pending}
        onConfirm={run}
      />
    </>
  );
}

/** Sample Requisition: an existing one loaded back into the form, or a new one raised. */
function RequisitionForm({ options, initial, existing, companyName, canManage, actions }) {
  const t = useT("prototype");
  const router = useRouter();
  const toastManager = useToastManager();
  const [row, setRow] = useState(initial);
  const [form, setForm] = useState(() => formOf(initial));
  const [errors, setErrors] = useState(null);
  const [pending, startTransition] = useTransition();
  const set = (key) => (v) => setForm((f) => ({ ...f, [key]: v ?? "" }));
  const approved = row && ["APPROVED", "ASSIGNED", "IN_DEVELOPMENT", "COMPLETED"].includes(row.status);
  const editable = canManage && (!row || ["DRAFT", "REJECTED"].includes(row.status) || approved);
  const locked = !canManage || (row && !["DRAFT", "REJECTED"].includes(row.status));
  const buyer = options.buyers.find((b) => b.value === form.buyer_id);
  const styles = options.styles.filter((s) => s.buyer_id === form.buyer_id);
  const style = styles.find((s) => s.value === form.style_id);
  const orders = options.orders.filter((o) => o.buyer_id === form.buyer_id);
  const opt = (list) => [{ value: "", label: t("ui.select") }, ...list];

  function load(no) {
    window.history.replaceState(null, "", no ? `?tab=requisition&req=${encodeURIComponent(no)}` : "?tab=requisition");
    setErrors(null);
    if (!no) return (setRow(null), setForm(blankForm()));
    startTransition(async () => {
      const result = await actions.load("requisitions", no);
      if (result?.status !== "success") return setErrors(messagesOf(result));
      setRow(result.data);
      setForm(formOf(result.data));
    });
  }

  function save() {
    setErrors(null);
    const payload = Object.fromEntries(FIELDS.map((k) => [k, blank(form[k])]));
    startTransition(async () => {
      const result = await actions.save("requisitions", row?.req_no ?? null, payload);
      if (result?.status !== "success") return setErrors(messagesOf(result));
      setRow(result.data);
      setForm(formOf(result.data));
      window.history.replaceState(null, "", `?tab=requisition&req=${encodeURIComponent(result.data.req_no)}`);
      toastManager.add({ title: t("ui.req.saved", { no: result.data.req_no }), type: "success" });
      router.refresh();
    });
  }

  return (
    <div className="flex flex-col gap-4">
      <section className={cn(card, "flex flex-col gap-4 p-5")}>
        <div className="flex items-start justify-between gap-3">
          <div>
            <h2 className="text-base font-semibold text-foreground">{t("ui.req.form")}</h2>
            <p className="mt-0.5 text-[13px] text-foreground-muted">{t("ui.req.form_hint")}</p>
          </div>
          <span className="rounded-md bg-brand-subtle px-2 py-0.5 text-xs font-medium text-brand">{row ? t(`status.${row.status}`) : t("ui.new")}</span>
        </div>
        <Field label={t("ui.req.existing")} hint={t("ui.existing_hint")} wide>
          <Select value={row?.req_no ?? ""} onValueChange={(v) => load(v ?? "")} options={[{ value: "", label: t("ui.req.new_option") }, ...existing]} aria-label={t("ui.req.existing")} />
        </Field>
        {errors ? (
          <Alert variant="danger">
            {errors.map((e) => (
              <p key={e}>{e}</p>
            ))}
          </Alert>
        ) : null}
        <Section title={t("ui.req.s.requisition")}>
          <Field label={t("ui.req.f.req_no")} hint={t("ui.req.h.req_no")}>
            <ReadOnly value={row?.req_no} placeholder={t("ui.auto_gen")} />
          </Field>
          <Field label={t("ui.req.f.req_date")} required>
            <Input type="date" value={form.req_date} onChange={(e) => set("req_date")(e.target.value)} disabled={locked} />
          </Field>
          <Field label={t("ui.req.f.company")} required>
            <ReadOnly value={companyName} />
          </Field>
          <Field label={t("ui.req.f.status")}>
            <ReadOnly value={row ? t(`status.${row.status}`) : ""} placeholder={t("status.DRAFT")} />
          </Field>
          <Field label={t("ui.req.f.pending_at")}>
            <ReadOnly value={row?.pending_level ? t(`level.${row.pending_level}`) : ""} />
          </Field>
          <Field label={t("ui.req.f.sample_no")}>
            <ReadOnly value={row?.sample_no} />
          </Field>
        </Section>
        <Section title={t("ui.req.s.buyer_style")}>
          <Field label={t("ui.req.f.buyer")} required>
            <Select value={form.buyer_id} onValueChange={(v) => setForm((f) => ({ ...f, buyer_id: v ?? "", buyer_office_id: "", order_id: "", style_id: "" }))} options={opt(options.buyers)} disabled={locked} aria-label={t("ui.req.f.buyer")} />
          </Field>
          <Field label={t("ui.req.f.buying_house")} hint={buyer && buyer.houses.length === 0 ? t("ui.no_choices") : undefined}>
            <Select value={form.buyer_office_id} onValueChange={set("buyer_office_id")} options={opt(buyer?.houses ?? [])} disabled={locked || !buyer || buyer.houses.length === 0} aria-label={t("ui.req.f.buying_house")} />
          </Field>
          <Field label={t("ui.req.f.order_no")} hint={t("ui.req.h.order_no")}>
            <Select value={form.order_id} onValueChange={set("order_id")} options={opt(orders)} disabled={locked} aria-label={t("ui.req.f.order_no")} />
          </Field>
          <Field label={t("ui.req.f.style_no")} required hint={t("ui.req.h.style_no")}>
            <Select value={form.style_id} onValueChange={(v) => setForm((f) => ({ ...f, style_id: v ?? "", season: f.season || (options.styles.find((s) => s.value === v)?.season ?? "") }))} options={opt(styles)} disabled={locked} aria-label={t("ui.req.f.style_no")} />
          </Field>
          <Field label={t("ui.req.f.style_name")}>
            <ReadOnly value={style?.name ?? row?.style_name} />
          </Field>
          <Field label={t("ui.req.f.season")}>
            <Select value={form.season} onValueChange={set("season")} options={opt(options.seasons.map((s) => ({ value: s, label: s })))} disabled={locked} aria-label={t("ui.req.f.season")} />
          </Field>
          <Field label={t("ui.req.f.product_category")} hint={t("ui.req.h.product_category")}>
            <Input value={form.product_category} onChange={(e) => set("product_category")(e.target.value)} disabled={locked} maxLength={120} />
          </Field>
        </Section>
        <Section title={t("ui.req.s.sample")}>
          <Field label={t("ui.req.f.sample_type")} required>
            <Select value={form.sample_type_id} onValueChange={set("sample_type_id")} options={opt(options.sample_types)} disabled={locked} aria-label={t("ui.req.f.sample_type")} />
          </Field>
          <Field label={t("ui.req.f.sample_stage")} hint={t("ui.req.h.sample_stage")}>
            <Select value={form.sample_stage} onValueChange={set("sample_stage")} options={opt(options.stages.map((s) => ({ value: s, label: t(`stage.${s}`) })))} disabled={locked} aria-label={t("ui.req.f.sample_stage")} />
          </Field>
          <Field label={t("ui.req.f.qty")} required>
            <Input type="number" min="1" value={form.qty} onChange={(e) => set("qty")(e.target.value)} disabled={locked} />
          </Field>
          <Field label={t("ui.req.f.required_date")} required>
            <Input type="date" value={form.required_date} onChange={(e) => set("required_date")(e.target.value)} disabled={locked} />
          </Field>
          <Field label={t("ui.req.f.priority")}>
            <Select value={form.priority} onValueChange={set("priority")} options={opt(options.priorities.map((p) => ({ value: p, label: t(`priority.${p}`) })))} disabled={locked} aria-label={t("ui.req.f.priority")} />
          </Field>
        </Section>
        <Section title={t("ui.req.s.people")}>
          <Field label={t("ui.req.f.requested_by")} hint={t("ui.left_blank_me")}>
            <Select value={form.requested_by} onValueChange={set("requested_by")} options={opt(options.users)} disabled={locked} aria-label={t("ui.req.f.requested_by")} />
          </Field>
          <Field label={t("ui.req.f.department")}>
            <Select value={form.department_id} onValueChange={set("department_id")} options={opt(options.departments)} disabled={locked} aria-label={t("ui.req.f.department")} />
          </Field>
          <Field label={t("ui.req.f.assigned_to")} hint={t("ui.req.h.assigned_to")}>
            <Select value={form.assigned_to} onValueChange={set("assigned_to")} options={opt(options.users)} disabled={!editable} aria-label={t("ui.req.f.assigned_to")} />
          </Field>
        </Section>
        <Section title={t("ui.req.s.notes")}>
          <Field label={t("ui.req.f.remarks")} wide>
            <Textarea rows={3} value={form.remarks} onChange={(e) => set("remarks")(e.target.value)} disabled={locked} />
          </Field>
          <Field label={t("ui.req.f.attachment")} wide>
            <Input value={form.attachment} onChange={(e) => set("attachment")(e.target.value)} placeholder={t("ui.attachment_placeholder")} disabled={locked} maxLength={500} />
          </Field>
        </Section>
      </section>
      <History decisions={row?.decisions} />
      <section className={cn(card, "flex flex-wrap items-center justify-between gap-3 px-5 py-3.5")}>
        <p className="max-w-3xl text-[13px] text-foreground-muted">{t("ui.req.form_note")}</p>
        <div className="flex gap-2">
          <Button type="button" variant="outline" onClick={() => load("")} disabled={pending}>
            <RotateCcw /> {t("ui.clear")}
          </Button>
          {editable ? (
            <Button type="button" onClick={save} disabled={pending}>
              <Save /> {t("ui.req.save")}
            </Button>
          ) : null}
        </div>
      </section>
    </div>
  );
}

/** Requisition Approval: the level it is pending at records its decision. */
function RequisitionApproval({ options, initial, existing, canApprove, actions }) {
  const t = useT("prototype");
  const router = useRouter();
  const toastManager = useToastManager();
  const [row, setRow] = useState(initial);
  const [form, setForm] = useState({ decision: "", comments: "", rejection_reason: "", assigned_to: "", attachment: "" });
  const [errors, setErrors] = useState(null);
  const [pending, startTransition] = useTransition();
  const set = (key) => (v) => setForm((f) => ({ ...f, [key]: v ?? "" }));
  const deciding = canApprove && row?.status === "PENDING_APPROVAL";
  const lastLevel = row?.pending_level === "SAMPLE_MANAGER";
  const users = [{ value: "", label: t("ui.select") }, ...options.users];
  const reset = () => setForm({ decision: "", comments: "", rejection_reason: "", assigned_to: "", attachment: "" });

  function load(no) {
    window.history.replaceState(null, "", no ? `?tab=approval&req=${encodeURIComponent(no)}` : "?tab=approval");
    setErrors(null);
    reset();
    if (!no) return setRow(null);
    startTransition(async () => {
      const result = await actions.load("requisitions", no);
      if (result?.status === "success") setRow(result.data);
      else setErrors(messagesOf(result));
    });
  }

  function record() {
    setErrors(null);
    startTransition(async () => {
      const result = await actions.act("requisitions", row.req_no, "decide", { decision: blank(form.decision), comments: blank(form.comments), rejection_reason: blank(form.rejection_reason), assigned_to: blank(form.assigned_to), attachment: blank(form.attachment) });
      if (result?.status !== "success") return setErrors(messagesOf(result));
      setRow(result.data);
      reset();
      toastManager.add({ title: t("ui.req.decided", { no: result.data.req_no }), type: "success" });
      router.refresh();
    });
  }

  const read = row
    ? [
        ["req_no", row.req_no], ["req_date", row.req_date], ["buyer", row.buyer], ["style", row.style_name ?? row.style_ref], ["sample_type", row.sample_type],
        ["sample_stage", row.sample_stage ? t(`stage.${row.sample_stage}`) : ""], ["qty", row.qty], ["required_date", row.required_date], ["priority", t(`priority.${row.priority}`)],
        ["requested_by", row.requested_by_name], ["status", t(`status.${row.status}`)], ["pending_at", row.pending_level ? t(`level.${row.pending_level}`) : ""],
      ]
    : [];
  return (
    <div className="flex flex-col gap-4">
      <section className={cn(card, "flex flex-col gap-4 p-5")}>
        <div>
          <h2 className="text-base font-semibold text-foreground">{t("ui.req.approval")}</h2>
          <p className="mt-0.5 text-[13px] text-foreground-muted">{t("ui.req.approval_hint")}</p>
        </div>
        <Field label={t("ui.req.existing_decision")} hint={t("ui.existing_hint")} wide>
          <Select value={row?.req_no ?? ""} onValueChange={(v) => load(v ?? "")} options={[{ value: "", label: t("ui.select") }, ...existing]} aria-label={t("ui.req.existing_decision")} />
        </Field>
        {errors ? (
          <Alert variant="danger">
            {errors.map((e) => (
              <p key={e}>{e}</p>
            ))}
          </Alert>
        ) : null}
        <Section title={t("ui.req.s.requisition")}>
          {(read.length ? read : ["req_no", "req_date", "buyer", "style", "sample_type", "sample_stage", "qty", "required_date", "priority", "requested_by", "status", "pending_at"].map((k) => [k, ""])).map(([k, v]) => (
            <Field key={k} label={t(`ui.req.f.${k}`)}>
              <ReadOnly value={v} />
            </Field>
          ))}
        </Section>
        <Section title={t("ui.req.s.decision")}>
          <Field label={t("ui.req.f.decision")} required>
            <Select value={form.decision} onValueChange={set("decision")} options={[{ value: "", label: t("ui.select") }, { value: "APPROVE", label: t("decision.APPROVE") }, { value: "REJECT", label: t("decision.REJECT") }]} disabled={!deciding} aria-label={t("ui.req.f.decision")} />
          </Field>
          <Field label={t("ui.req.f.comments")} wide>
            <Textarea rows={2} value={form.comments} onChange={(e) => set("comments")(e.target.value)} disabled={!deciding} />
          </Field>
          <Field label={t("ui.req.f.rejection_reason")} hint={t("ui.req.h.rejection_reason")} wide>
            <Textarea rows={2} value={form.rejection_reason} onChange={(e) => set("rejection_reason")(e.target.value)} disabled={!deciding} />
          </Field>
          <Field label={t("ui.req.f.assigned_to")} hint={t("ui.req.h.assigned_level")}>
            <Select value={form.assigned_to} onValueChange={set("assigned_to")} options={users} disabled={!deciding || !lastLevel} aria-label={t("ui.req.f.assigned_to")} />
          </Field>
          <Field label={t("ui.req.f.attachment")} wide>
            <Input value={form.attachment} onChange={(e) => set("attachment")(e.target.value)} placeholder={t("ui.attachment_placeholder")} disabled={!deciding} maxLength={500} />
          </Field>
        </Section>
      </section>
      <History decisions={row?.decisions} />
      <section className={cn(card, "flex flex-wrap items-center justify-between gap-3 px-5 py-3.5")}>
        <p className="max-w-3xl text-[13px] text-foreground-muted">{t("ui.req.approval_note")}</p>
        <div className="flex gap-2">
          <Button type="button" variant="outline" onClick={() => load("")} disabled={pending}>
            <RotateCcw /> {t("ui.clear")}
          </Button>
          <Button type="button" onClick={record} disabled={pending || !deciding}>
            <Gavel /> {t("ui.req.record")}
          </Button>
        </div>
      </section>
    </div>
  );
}

export { RequisitionApproval, RequisitionForm, RequisitionList };
