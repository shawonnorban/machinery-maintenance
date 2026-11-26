"use client";

import { useState, useTransition } from "react";
import { useRouter } from "next/navigation";
import { BadgeCheck, RotateCcw, Save } from "lucide-react";
import { cn } from "@/lib/utils";
import { Input } from "@/components/ui/input";
import { Select } from "@/components/ui/select";
import { Textarea } from "@/components/ui/textarea";
import { Button } from "@/components/ui/button";
import { Alert } from "@/components/ui/alert";
import { useToastManager } from "@/components/ui/toast";
import { useT } from "@/lib/i18n";
import { FormattedDateTime } from "@/components/ui/formatted-date-time";
import { Field, ReadOnly, Section, blank, card, messagesOf, str, todayIso } from "@/components/prototype/proto-kit";

/*
 * Prototype Desk → Sample Revision (AnnotechERP 07/03): why a sample changes
 * and what changes, each revision a new version that never replaces the
 * last. Saving a new one moves the sample to its next version; edited only
 * while Open; Approve Revision signs it off. An existing one loads back in place.
 */

const FIELDS = ["sample_id", "revision_date", "due_date", "reason", "source_approval_id", "source_feedback_id", "buyer_comment", "internal_comment", "changed_measurement", "changed_fabric", "changed_color", "changed_accessories", "changed_construction", "requested_by", "attachment"];
const CHANGES = ["changed_measurement", "changed_fabric", "changed_color", "changed_accessories", "changed_construction"];
const blankForm = () => ({ ...Object.fromEntries(FIELDS.map((k) => [k, ""])), revision_date: todayIso() });
const formOf = (row) => (row ? Object.fromEntries(FIELDS.map((k) => [k, str(row[k])])) : blankForm());

/**
 * A new revision raised from an approval or feedback (Create Revision): the
 * sample and the source picked by their numbers, the comment and the changes
 * asked for carried over.
 */
function presetForm(options, preset) {
  const form = blankForm();
  const sample = options.samples.find((s) => s.sample_no === preset?.sample);
  if (!sample) return form;
  const approval = options.approvals.find((a) => a.no === preset.approval && a.sample_id === sample.value);
  const feedback = options.feedback.find((f) => f.no === preset.feedback && f.sample_id === sample.value);
  return {
    ...form,
    sample_id: sample.value,
    source_approval_id: approval?.value ?? "",
    source_feedback_id: feedback?.value ?? "",
    buyer_comment: str(approval?.comment ?? feedback?.comment),
    ...(feedback ? { changed_measurement: str(feedback.measurement), changed_fabric: str(feedback.fabric), changed_color: str(feedback.color), changed_construction: str(feedback.construction) } : {}),
  };
}

function RevisionForm({ options, initial, existing, canManage, canApprove, actions, preset }) {
  const t = useT("prototype");
  const router = useRouter();
  const toastManager = useToastManager();
  const [row, setRow] = useState(initial);
  const [form, setForm] = useState(() => (initial ? formOf(initial) : presetForm(options, preset)));
  const [errors, setErrors] = useState(null);
  const [pending, startTransition] = useTransition();
  const set = (key) => (v) => setForm((f) => ({ ...f, [key]: v ?? "" }));
  const locked = !canManage || (row && row.status !== "OPEN");
  const sample = options.samples.find((s) => s.value === form.sample_id);
  const opt = (list) => [{ value: "", label: t("ui.select") }, ...list];
  const previous = row ? row.previous_version : sample?.sample_version;
  const forSample = (list) => list.filter((o) => o.sample_id === (row?.sample_id ?? form.sample_id));
  const approvals = forSample(options.approvals);
  const feedback = forSample(options.feedback);

  function take(data) {
    setRow(data);
    setForm(formOf(data));
  }

  function load(no) {
    window.history.replaceState(null, "", no ? `?tab=revision&rev=${encodeURIComponent(no)}` : "?tab=revision");
    setErrors(null);
    if (!no) return take(null);
    startTransition(async () => {
      const result = await actions.load("revisions", no);
      if (result?.status === "success") take(result.data);
      else setErrors(messagesOf(result));
    });
  }

  function save() {
    setErrors(null);
    const payload = Object.fromEntries(FIELDS.map((k) => [k, blank(form[k])]));
    startTransition(async () => {
      const result = await actions.save("revisions", row?.rev_no ?? null, payload);
      if (result?.status !== "success") return setErrors(messagesOf(result));
      take(result.data);
      window.history.replaceState(null, "", `?tab=revision&rev=${encodeURIComponent(result.data.rev_no)}`);
      toastManager.add({ title: t("ui.rev.saved", { no: result.data.rev_no }), type: "success" });
      router.refresh();
    });
  }

  function approve() {
    setErrors(null);
    startTransition(async () => {
      const result = await actions.act("revisions", row.rev_no, "approve");
      if (result?.status !== "success") return setErrors(messagesOf(result));
      take(result.data);
      toastManager.add({ title: t("ui.rev.approved", { no: result.data.rev_no }), type: "success" });
      router.refresh();
    });
  }

  return (
    <div className="flex flex-col gap-4">
      <section className={cn(card, "flex flex-col gap-4 p-5")}>
        <div className="flex items-start justify-between gap-3">
          <div>
            <h2 className="text-base font-semibold text-foreground">{t("ui.rev.form")}</h2>
            <p className="mt-0.5 text-[13px] text-foreground-muted">{t("ui.rev.form_hint")}</p>
          </div>
          <span className="rounded-md bg-brand-subtle px-2 py-0.5 text-xs font-medium text-brand">{row ? t(`rev_status.${row.status}`) : t("ui.new")}</span>
        </div>
        <Field label={t("ui.rev.existing")} hint={t("ui.existing_hint")} wide>
          <Select value={row?.rev_no ?? ""} onValueChange={(v) => load(v ?? "")} options={[{ value: "", label: t("ui.rev.new_option") }, ...existing]} aria-label={t("ui.rev.existing")} />
        </Field>
        {errors ? (
          <Alert variant="danger">
            {errors.map((e) => (
              <p key={e}>{e}</p>
            ))}
          </Alert>
        ) : null}
        <Section title={t("ui.rev.s.revision")}>
          <Field label={t("ui.rev.f.rev_no")} hint={t("ui.rev.h.rev_no")}>
            <ReadOnly value={row?.rev_no} placeholder={t("ui.auto_gen")} />
          </Field>
          <Field label={t("ui.rev.f.sample_no")} required hint={t("ui.rev.h.sample_no")}>
            <Select value={form.sample_id} onValueChange={(v) => setForm((f) => ({ ...f, sample_id: v ?? "", source_approval_id: "", source_feedback_id: "" }))} options={opt(row ? [{ value: row.sample_id, label: row.sample_no }] : options.samples)} disabled={locked || Boolean(row)} aria-label={t("ui.rev.f.sample_no")} />
          </Field>
          <Field label={t("ui.rev.f.previous_version")}>
            <ReadOnly value={previous ? `V${previous}` : ""} />
          </Field>
          <Field label={t("ui.rev.f.new_version")}>
            <ReadOnly value={row ? `V${row.new_version}` : previous ? `V${previous + 1}` : ""} />
          </Field>
          <Field label={t("ui.rev.f.revision_date")} required>
            <Input type="date" value={form.revision_date} onChange={(e) => set("revision_date")(e.target.value)} disabled={locked} />
          </Field>
          <Field label={t("ui.rev.f.due_date")}>
            <Input type="date" value={form.due_date} onChange={(e) => set("due_date")(e.target.value)} disabled={locked} />
          </Field>
          <Field label={t("ui.rev.f.status")}>
            <ReadOnly value={row ? t(`rev_status.${row.status}`) : ""} placeholder={t("rev_status.OPEN")} />
          </Field>
        </Section>
        <Section title={t("ui.rev.s.why")}>
          <Field label={t("ui.rev.f.reason")} required wide>
            <Input value={form.reason} onChange={(e) => set("reason")(e.target.value)} disabled={locked} maxLength={500} />
          </Field>
          <Field label={t("ui.rev.f.source_approval")} hint={approvals.length === 0 ? t("ui.no_choices") : undefined}>
            <Select value={form.source_approval_id} onValueChange={set("source_approval_id")} options={opt(row?.source_approval_no ? [{ value: row.source_approval_id, label: row.source_approval_no }] : approvals)} disabled={locked || Boolean(row) || approvals.length === 0} aria-label={t("ui.rev.f.source_approval")} />
          </Field>
          <Field label={t("ui.rev.f.source_feedback")} hint={feedback.length === 0 ? t("ui.no_choices") : undefined}>
            <Select value={form.source_feedback_id} onValueChange={set("source_feedback_id")} options={opt(row?.source_feedback_no ? [{ value: row.source_feedback_id, label: row.source_feedback_no }] : feedback)} disabled={locked || Boolean(row) || feedback.length === 0} aria-label={t("ui.rev.f.source_feedback")} />
          </Field>
          <Field label={t("ui.rev.f.buyer_comment")} wide>
            <Textarea rows={2} value={form.buyer_comment} onChange={(e) => set("buyer_comment")(e.target.value)} disabled={locked} />
          </Field>
          <Field label={t("ui.rev.f.internal_comment")} wide>
            <Textarea rows={2} value={form.internal_comment} onChange={(e) => set("internal_comment")(e.target.value)} disabled={locked} />
          </Field>
        </Section>
        <Section title={t("ui.rev.s.changes")}>
          {CHANGES.map((k) => (
            <Field key={k} label={t(`ui.rev.f.${k}`)}>
              <Textarea rows={2} value={form[k]} onChange={(e) => set(k)(e.target.value)} disabled={locked} />
            </Field>
          ))}
        </Section>
        <Section title={t("ui.rev.s.signoff")}>
          <Field label={t("ui.rev.f.requested_by")} hint={t("ui.left_blank_me")}>
            <Select value={form.requested_by} onValueChange={set("requested_by")} options={opt(options.users)} disabled={locked} aria-label={t("ui.rev.f.requested_by")} />
          </Field>
          <Field label={t("ui.rev.f.approved_by")} hint={t("ui.rev.h.approved_by")}>
            <ReadOnly value={row?.approved_by_name} />
          </Field>
          <Field label={t("ui.rev.f.approved_at")}>
            <ReadOnly value={row?.approved_at ? new Date(row.approved_at).toLocaleString("en-GB", { timeZone: "Asia/Dhaka", dateStyle: "short", timeStyle: "short", hourCycle: "h12" }).replace(/\b[ap]m\b/, (x) => x.toUpperCase()) : ""} />
          </Field>
          <Field label={t("ui.rev.f.attachment")} wide>
            <Input value={form.attachment} onChange={(e) => set("attachment")(e.target.value)} placeholder={t("ui.attachment_placeholder")} disabled={locked} maxLength={500} />
          </Field>
        </Section>
      </section>
      <section className={cn(card, "p-4")}>
        <h3 className="text-sm font-semibold text-foreground">{t("ui.rev.history")}</h3>
        <p className="mt-0.5 text-xs text-foreground-muted">{t("ui.rev.history_hint")}</p>
        <div className="mt-3 overflow-x-auto">
          <table className="w-full text-[13px]">
            <thead>
              <tr className="border-b border-border text-left text-foreground-muted">
                {["rev_no", "date", "version", "reason", "status", "approved"].map((k) => (
                  <th key={k} className="px-2 py-2 font-medium">
                    {t(`ui.rev.col.${k}`)}
                  </th>
                ))}
              </tr>
            </thead>
            <tbody>
              {(row?.history ?? []).length === 0 ? (
                <tr>
                  <td colSpan={6} className="px-2 py-6 text-center text-foreground-muted">
                    <p className="font-medium text-foreground">{t("ui.no_lines")}</p>
                    <p className="text-xs">{t("ui.no_lines_hint")}</p>
                  </td>
                </tr>
              ) : (
                row.history.map((h) => (
                  <tr key={h.rev_no} className="border-b border-border last:border-0">
                    <td className="px-2 py-2 font-mono">{h.rev_no}</td>
                    <td className="px-2 py-2">
                      <FormattedDateTime value={h.revision_date} mode="date" />
                    </td>
                    <td className="px-2 py-2">{`V${h.previous_version} → V${h.new_version}`}</td>
                    <td className="px-2 py-2">{h.reason}</td>
                    <td className="px-2 py-2">{t(`rev_status.${h.status}`)}</td>
                    <td className="px-2 py-2">{h.approved ? t("ui.yes") : t("ui.no")}</td>
                  </tr>
                ))
              )}
            </tbody>
          </table>
        </div>
      </section>
      <section className={cn(card, "flex flex-wrap items-center justify-between gap-3 px-5 py-3.5")}>
        <p className="max-w-3xl text-[13px] text-foreground-muted">{t("ui.rev.note")}</p>
        <div className="flex gap-2">
          <Button type="button" variant="outline" onClick={() => load("")} disabled={pending}>
            <RotateCcw /> {t("ui.clear")}
          </Button>
          {row && canApprove && !row.approved_at && row.status !== "CANCELLED" ? (
            <Button type="button" variant="outline" onClick={approve} disabled={pending}>
              <BadgeCheck /> {t("ui.rev.approve")}
            </Button>
          ) : null}
          {!locked ? (
            <Button type="button" onClick={save} disabled={pending}>
              <Save /> {t("ui.rev.save")}
            </Button>
          ) : null}
        </div>
      </section>
    </div>
  );
}

export { RevisionForm };
