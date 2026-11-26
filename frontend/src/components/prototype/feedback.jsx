"use client";

import { cn } from "@/lib/utils";
import { Input } from "@/components/ui/input";
import { Select } from "@/components/ui/select";
import { Textarea } from "@/components/ui/textarea";
import { Checkbox } from "@/components/ui/checkbox";
import { useT } from "@/lib/i18n";
import { DocFoot, DocHead, Field, ReadOnly, Section, blank, card, str, todayIso, useDocForm } from "@/components/prototype/proto-kit";

/*
 * Prototype Desk → Buyer Feedback (AnnotechERP 07/03): what the buyer said
 * about a sample, the changes asked for and who has to act. Action Required
 * needs an assignee and a due date, alerting the assignee on save; Resolved
 * or Closed clears the alert; a closed feedback is locked.
 */

const FIELDS = ["sample_id", "approval_id", "feedback_date", "feedback_type", "status", "comment", "measurement_change", "fabric_change", "color_change", "construction_change", "assigned_to", "due_date", "attachment"];
const CHANGES = ["measurement_change", "fabric_change", "color_change", "construction_change"];

function FeedbackForm({ options, initial, existing, canManage, actions }) {
  const t = useT("prototype");
  const formOf = (row) => ({ ...Object.fromEntries(FIELDS.map((k) => [k, str(row?.[k])])), feedback_date: row?.feedback_date ?? todayIso(), status: row?.status ?? "OPEN", action_required: Boolean(row?.action_required) });
  const payloadOf = (form) => ({ ...Object.fromEntries(FIELDS.map((k) => [k, blank(form[k])])), action_required: form.action_required });
  const { row, form, set, errors, pending, load, save } = useDocForm({ kind: "feedback", tab: "feedback", param: "fb", noKey: "feedback_no", initial, formOf, payloadOf, actions, savedText: (no) => t("ui.fb.saved", { no }) });
  const locked = !canManage || row?.status === "CLOSED";
  const sample = row ?? options.samples.find((s) => s.value === form.sample_id);
  const approvals = options.approvals.filter((a) => a.sample_id === (row?.sample_id ?? form.sample_id));
  const opt = (list) => [{ value: "", label: t("ui.select") }, ...list];
  const labelled = (list, ns) => list.map((v) => ({ value: v, label: t(`${ns}.${v}`) }));

  return (
    <div className="flex flex-col gap-4">
      <section className={cn(card, "flex flex-col gap-4 p-5")}>
        <DocHead
          title={t("ui.fb.form")}
          hint={t("ui.fb.form_hint")}
          badge={row ? t(`fb_status.${row.status}`) : t("ui.new")}
          existingLabel={t("ui.fb.existing")}
          newOption={t("ui.fb.new_option")}
          existing={existing}
          value={row?.feedback_no ?? ""}
          onPick={load}
          errors={errors}
        />
        <Section title={t("ui.fb.s.sample")}>
          <Field label={t("ui.fb.f.feedback_no")}>
            <ReadOnly value={row?.feedback_no} placeholder={t("ui.issued_on_save")} />
          </Field>
          <Field label={t("ui.fb.f.sample_no")} required>
            <Select value={form.sample_id} onValueChange={(v) => (set("sample_id")(v), set("approval_id")(""))} options={opt(row ? [{ value: row.sample_id, label: row.sample_no }] : options.samples)} disabled={locked || Boolean(row)} aria-label={t("ui.fb.f.sample_no")} />
          </Field>
          <Field label={t("ui.fb.f.buyer")}>
            <ReadOnly value={sample?.buyer} />
          </Field>
          <Field label={t("ui.fb.f.style_no")}>
            <ReadOnly value={sample?.style_ref} />
          </Field>
          <Field label={t("ui.fb.f.sample_type")}>
            <ReadOnly value={sample?.sample_type} />
          </Field>
          <Field label={t("ui.fb.f.version")}>
            <ReadOnly value={row ? `V${row.version}` : sample ? `V${sample.sample_version}` : ""} />
          </Field>
          <Field label={t("ui.fb.f.related_approval")} hint={approvals.length === 0 ? t("ui.no_choices") : undefined}>
            <Select value={form.approval_id} onValueChange={set("approval_id")} options={opt(approvals)} disabled={locked || approvals.length === 0} aria-label={t("ui.fb.f.related_approval")} />
          </Field>
        </Section>
        <Section title={t("ui.fb.s.feedback")}>
          <Field label={t("ui.fb.f.feedback_date")} required>
            <Input type="date" value={form.feedback_date} onChange={(e) => set("feedback_date")(e.target.value)} disabled={locked} />
          </Field>
          <Field label={t("ui.fb.f.feedback_type")} required>
            <Select value={form.feedback_type} onValueChange={set("feedback_type")} options={opt(labelled(options.types, "fb_type"))} disabled={locked} aria-label={t("ui.fb.f.feedback_type")} />
          </Field>
          <Field label={t("ui.fb.f.status")}>
            <Select value={form.status} onValueChange={set("status")} options={opt(labelled(options.statuses, "fb_status"))} disabled={locked} aria-label={t("ui.fb.f.status")} />
          </Field>
        </Section>
        <Section title={t("ui.fb.s.comment")}>
          <Field label={t("ui.fb.f.comment")} wide>
            <Textarea rows={3} value={form.comment} onChange={(e) => set("comment")(e.target.value)} disabled={locked} />
          </Field>
        </Section>
        <Section title={t("ui.fb.s.changes")}>
          {CHANGES.map((k) => (
            <Field key={k} label={t(`ui.fb.f.${k}`)}>
              <Textarea rows={2} value={form[k]} onChange={(e) => set(k)(e.target.value)} disabled={locked} />
            </Field>
          ))}
        </Section>
        <Section title={t("ui.fb.s.followup")}>
          <div className="flex items-start gap-2 sm:col-span-2 lg:col-span-3">
            <Checkbox checked={form.action_required} onCheckedChange={(c) => set("action_required")(c === true)} disabled={locked} aria-label={t("ui.fb.f.action_required")} className="mt-0.5" />
            <div>
              <p className="text-[13px] font-medium text-foreground">{t("ui.fb.f.action_required")}</p>
              <p className="text-xs text-foreground-muted">{t("ui.fb.h.action_required")}</p>
            </div>
          </div>
          <Field label={t("ui.fb.f.assigned_to")} required={form.action_required}>
            <Select value={form.assigned_to} onValueChange={set("assigned_to")} options={opt(options.users)} disabled={locked} aria-label={t("ui.fb.f.assigned_to")} />
          </Field>
          <Field label={t("ui.fb.f.due_date")} required={form.action_required}>
            <Input type="date" value={form.due_date} onChange={(e) => set("due_date")(e.target.value)} disabled={locked} />
          </Field>
          <Field label={t("ui.fb.f.attachment")}>
            <Input value={form.attachment} onChange={(e) => set("attachment")(e.target.value)} placeholder={t("ui.attachment_placeholder")} disabled={locked} maxLength={500} />
          </Field>
        </Section>
      </section>
      <DocFoot note={t("ui.fb.note")} onClear={() => load("")} onSave={locked ? undefined : save} saveLabel={t("ui.fb.save")} pending={pending} />
    </div>
  );
}

export { FeedbackForm };
