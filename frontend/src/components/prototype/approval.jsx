"use client";

import Link from "next/link";
import { GitBranch } from "lucide-react";
import { cn } from "@/lib/utils";
import { Input } from "@/components/ui/input";
import { Select } from "@/components/ui/select";
import { Textarea } from "@/components/ui/textarea";
import { buttonVariants } from "@/components/ui/button";
import { useT } from "@/lib/i18n";
import { DocFoot, DocHead, Field, ReadOnly, Section, blank, card, str, todayIso, useDocForm } from "@/components/prototype/proto-kit";

/*
 * Prototype Desk → Sample Approval (AnnotechERP 07/03): a QC-passed sample
 * put up for merchandising review or buyer approval, and the decision on it.
 * A decided approval is locked; Rejected and Revision Required are answered
 * with Create Revision, which opens the revision form on this sample.
 */

const FIELDS = ["sample_id", "step", "submitted_date", "decision", "approval_date", "approval_by", "buyer_comment", "internal_comment", "attachment"];

function ApprovalForm({ options, initial, existing, canManage, canRevise, actions }) {
  const t = useT("prototype");
  const formOf = (row) => ({ ...Object.fromEntries(FIELDS.map((k) => [k, str(row?.[k])])), step: row?.step ?? "MERCHANDISING_REVIEW", submitted_date: row?.submitted_date ?? todayIso(), decision: row?.decision ?? "PENDING" });
  const payloadOf = (form) => Object.fromEntries(FIELDS.map((k) => [k, blank(form[k])]));
  const { row, form, set, errors, pending, load, save } = useDocForm({ kind: "approvals", tab: "approval", param: "apr", noKey: "approval_no", initial, formOf, payloadOf, actions, savedText: (no) => t("ui.appr.saved", { no }) });
  const locked = !canManage || Boolean(row?.decided);
  const sample = row ?? options.samples.find((s) => s.value === form.sample_id);
  const opt = (list) => [{ value: "", label: t("ui.select") }, ...list];
  const labelled = (list, ns) => list.map((v) => ({ value: v, label: t(`${ns}.${v}`) }));
  const revise = row ? `/prototype-desk/sample-revision?tab=revision&sample=${encodeURIComponent(row.sample_no)}&approval=${encodeURIComponent(row.approval_no)}` : null;

  return (
    <div className="flex flex-col gap-4">
      <section className={cn(card, "flex flex-col gap-4 p-5")}>
        <DocHead
          title={t("ui.appr.form")}
          hint={t("ui.appr.form_hint")}
          badge={row ? t(`decision_sa.${row.decision}`) : t("ui.new")}
          existingLabel={t("ui.appr.existing")}
          newOption={t("ui.appr.new_option")}
          existing={existing}
          value={row?.approval_no ?? ""}
          onPick={load}
          errors={errors}
        />
        <Section title={t("ui.appr.s.sample")}>
          <Field label={t("ui.appr.f.approval_no")}>
            <ReadOnly value={row?.approval_no} placeholder={t("ui.issued_on_save")} />
          </Field>
          <Field label={t("ui.appr.f.sample_no")} required hint={t("ui.appr.h.sample_no")}>
            <Select value={form.sample_id} onValueChange={set("sample_id")} options={opt(row ? [{ value: row.sample_id, label: row.sample_no }] : options.samples)} disabled={locked || Boolean(row)} aria-label={t("ui.appr.f.sample_no")} />
          </Field>
          <Field label={t("ui.appr.f.buyer")}>
            <ReadOnly value={sample?.buyer} />
          </Field>
          <Field label={t("ui.appr.f.style_no")}>
            <ReadOnly value={sample?.style_ref} />
          </Field>
          <Field label={t("ui.appr.f.sample_type")}>
            <ReadOnly value={sample?.sample_type} />
          </Field>
          <Field label={t("ui.appr.f.version")}>
            <ReadOnly value={row ? `V${row.version}` : sample ? `V${sample.sample_version}` : ""} />
          </Field>
          <Field label={t("ui.appr.f.qc_ref")}>
            <ReadOnly value={row?.qc_no} placeholder={t("ui.appr.h.qc_ref")} />
          </Field>
        </Section>
        <Section title={t("ui.appr.s.approval")}>
          <Field label={t("ui.appr.f.step")} required>
            <Select value={form.step} onValueChange={set("step")} options={opt(labelled(options.steps, "step"))} disabled={locked || Boolean(row)} aria-label={t("ui.appr.f.step")} />
          </Field>
          <Field label={t("ui.appr.f.submitted_date")} required>
            <Input type="date" value={form.submitted_date} onChange={(e) => set("submitted_date")(e.target.value)} disabled={locked} />
          </Field>
          <Field label={t("ui.appr.f.decision")}>
            <Select value={form.decision} onValueChange={set("decision")} options={opt(labelled(options.decisions, "decision_sa"))} disabled={locked} aria-label={t("ui.appr.f.decision")} />
          </Field>
          <Field label={t("ui.appr.f.approval_date")} hint={t("ui.appr.h.approval_date")}>
            <Input type="date" value={form.approval_date} onChange={(e) => set("approval_date")(e.target.value)} disabled={locked} />
          </Field>
          <Field label={t("ui.appr.f.approval_by")}>
            <Input value={form.approval_by} onChange={(e) => set("approval_by")(e.target.value)} placeholder={t("ui.appr.h.approval_by")} disabled={locked} maxLength={255} />
          </Field>
        </Section>
        <Section title={t("ui.appr.s.comments")}>
          <Field label={t("ui.appr.f.buyer_comment")} hint={t("ui.appr.h.buyer_comment")} wide>
            <Textarea rows={3} value={form.buyer_comment} onChange={(e) => set("buyer_comment")(e.target.value)} disabled={locked} />
          </Field>
          <Field label={t("ui.appr.f.internal_comment")} wide>
            <Textarea rows={3} value={form.internal_comment} onChange={(e) => set("internal_comment")(e.target.value)} disabled={locked} />
          </Field>
          <Field label={t("ui.appr.f.attachment")} wide>
            <Input value={form.attachment} onChange={(e) => set("attachment")(e.target.value)} placeholder={t("ui.attachment_placeholder")} disabled={locked} maxLength={500} />
          </Field>
        </Section>
      </section>
      <DocFoot note={t("ui.appr.note")} onClear={() => load("")} onSave={locked ? undefined : save} saveLabel={t("ui.appr.save")} pending={pending}>
        {row?.answer_with_revision && canRevise ? (
          <Link href={revise} className={cn(buttonVariants({ variant: "outline" }))}>
            <GitBranch /> {t("ui.appr.create_revision")}
          </Link>
        ) : null}
      </DocFoot>
    </div>
  );
}

export { ApprovalForm };
