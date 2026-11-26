"use client";

import { cn } from "@/lib/utils";
import { Input } from "@/components/ui/input";
import { Select } from "@/components/ui/select";
import { Textarea } from "@/components/ui/textarea";
import { useT } from "@/lib/i18n";
import { DocFoot, DocHead, Field, ReadOnly, Section, blank, card, str, todayIso, useDocForm } from "@/components/prototype/proto-kit";

/*
 * Prototype Desk → Sample Quality Check (AnnotechERP 07/03): the verdict on a
 * finished sample and its thirteen-point checklist, saved together; the
 * verdict moves the sample. Only the latest check of the current version is
 * corrected, and not once an approval is raised on it.
 */

const FIELDS = ["sample_id", "checked_by", "check_date", "result", "severity", "defect_type", "defect", "correction", "remarks", "attachment"];
const POINT_FIELDS = ["result", "defect", "severity", "remarks"];
const pointsOf = (checkpoints, row) => checkpoints.map((c) => ({ checkpoint: c, ...Object.fromEntries(POINT_FIELDS.map((k) => [k, str(row?.checkpoints?.find((p) => p.checkpoint === c)?.[k])])) }));

function QualityCheckForm({ options, initial, existing, canManage, actions }) {
  const t = useT("prototype");
  const formOf = (row) => ({ ...Object.fromEntries(FIELDS.map((k) => [k, str(row?.[k])])), check_date: row?.check_date ?? todayIso(), points: pointsOf(options.checkpoints, row) });
  const payloadOf = (form) => ({
    ...Object.fromEntries(FIELDS.map((k) => [k, blank(form[k])])),
    checkpoints: form.points.map((p) => ({ checkpoint: p.checkpoint, ...Object.fromEntries(POINT_FIELDS.map((k) => [k, blank(p[k])])) })),
  });
  const { row, form, set, errors, pending, load, save } = useDocForm({ kind: "quality-checks", tab: "quality", param: "qc", noKey: "qc_no", initial, formOf, payloadOf, actions, savedText: (no) => t("ui.qc.saved", { no }) });
  const locked = !canManage || (row && !row.correctable);
  const sample = row ?? options.samples.find((s) => s.value === form.sample_id);
  const opt = (list) => [{ value: "", label: t("ui.select") }, ...list];
  const labelled = (list, ns) => list.map((v) => ({ value: v, label: t(`${ns}.${v}`) }));
  const setPoint = (i, key) => (v) => set("points")(form.points.map((p, j) => (j === i ? { ...p, [key]: v ?? "" } : p)));

  return (
    <div className="flex flex-col gap-4">
      <section className={cn(card, "flex flex-col gap-4 p-5")}>
        <DocHead
          title={t("ui.qc.form")}
          hint={t("ui.qc.form_hint")}
          badge={row ? t(`result.${row.result}`) : t("ui.new")}
          existingLabel={t("ui.qc.existing")}
          newOption={t("ui.qc.new_option")}
          existing={existing}
          value={row?.qc_no ?? ""}
          onPick={load}
          errors={errors}
        />
        <Section title={t("ui.qc.s.sample")}>
          <Field label={t("ui.qc.f.qc_no")}>
            <ReadOnly value={row?.qc_no} placeholder={t("ui.issued_on_save")} />
          </Field>
          <Field label={t("ui.qc.f.sample_no")} required hint={t("ui.qc.h.sample_no")}>
            <Select value={form.sample_id} onValueChange={set("sample_id")} options={opt(row ? [{ value: row.sample_id, label: row.sample_no }] : options.samples)} disabled={locked || Boolean(row)} aria-label={t("ui.qc.f.sample_no")} />
          </Field>
          <Field label={t("ui.qc.f.buyer")}>
            <ReadOnly value={sample?.buyer} />
          </Field>
          <Field label={t("ui.qc.f.style_no")}>
            <ReadOnly value={sample?.style_ref} />
          </Field>
          <Field label={t("ui.qc.f.sample_type")}>
            <ReadOnly value={sample?.sample_type} />
          </Field>
          <Field label={t("ui.qc.f.version")} hint={t("ui.qc.h.version")}>
            <ReadOnly value={row ? `V${row.version}` : sample ? `V${sample.sample_version}` : ""} />
          </Field>
        </Section>
        <Section title={t("ui.qc.s.check")}>
          <Field label={t("ui.qc.f.checked_by")} hint={t("ui.left_blank_me")}>
            <Select value={form.checked_by} onValueChange={set("checked_by")} options={opt(options.users)} disabled={locked} aria-label={t("ui.qc.f.checked_by")} />
          </Field>
          <Field label={t("ui.qc.f.check_date")} required>
            <Input type="date" value={form.check_date} onChange={(e) => set("check_date")(e.target.value)} disabled={locked} />
          </Field>
          <Field label={t("ui.qc.f.result")} required>
            <Select value={form.result} onValueChange={set("result")} options={opt(labelled(options.results, "result"))} disabled={locked} aria-label={t("ui.qc.f.result")} />
          </Field>
          <Field label={t("ui.qc.f.severity")}>
            <Select value={form.severity} onValueChange={set("severity")} options={opt(labelled(options.severities, "severity"))} disabled={locked} aria-label={t("ui.qc.f.severity")} />
          </Field>
          <Field label={t("ui.qc.f.defect_type")}>
            <Input value={form.defect_type} onChange={(e) => set("defect_type")(e.target.value)} placeholder={t("ui.qc.h.defect_type")} disabled={locked} maxLength={255} />
          </Field>
        </Section>
        <Section title={t("ui.qc.s.findings")}>
          <Field label={t("ui.qc.f.defect")} hint={t("ui.qc.h.defect")} wide>
            <Textarea rows={3} value={form.defect} onChange={(e) => set("defect")(e.target.value)} disabled={locked} />
          </Field>
          <Field label={t("ui.qc.f.correction")} wide>
            <Textarea rows={3} value={form.correction} onChange={(e) => set("correction")(e.target.value)} disabled={locked} />
          </Field>
          <Field label={t("ui.qc.f.remarks")} wide>
            <Textarea rows={3} value={form.remarks} onChange={(e) => set("remarks")(e.target.value)} disabled={locked} />
          </Field>
          <Field label={t("ui.qc.f.attachment")} wide>
            <Input value={form.attachment} onChange={(e) => set("attachment")(e.target.value)} placeholder={t("ui.attachment_placeholder")} disabled={locked} maxLength={500} />
          </Field>
        </Section>
      </section>
      <section className={cn(card, "p-4")}>
        <h3 className="text-sm font-semibold text-foreground">{t("ui.qc.checklist")}</h3>
        <p className="mt-0.5 text-xs text-foreground-muted">{t("ui.qc.checklist_hint")}</p>
        <div className="mt-3 overflow-x-auto">
          <table className="w-full min-w-[720px] text-[13px]">
            <thead>
              <tr className="border-b border-border text-left text-foreground-muted">
                {["checkpoint", "result", "defect", "severity", "remarks"].map((k) => (
                  <th key={k} className="px-2 py-2 font-medium">
                    {t(`ui.qc.cp.${k}`)}
                  </th>
                ))}
              </tr>
            </thead>
            <tbody>
              {form.points.map((p, i) => (
                <tr key={p.checkpoint} className="border-b border-border last:border-0">
                  <td className="px-2 py-1.5 font-medium text-foreground">{t(`checkpoint.${p.checkpoint}`)}</td>
                  <td className="w-36 px-2 py-1.5">
                    <Select value={p.result} onValueChange={setPoint(i, "result")} options={[{ value: "", label: "—" }, ...labelled(["OK", "NOT_OK", "NA"], "checkpoint_result")]} placeholder="—" disabled={locked} aria-label={`${t(`checkpoint.${p.checkpoint}`)} ${t("ui.qc.cp.result")}`} />
                  </td>
                  <td className="px-2 py-1.5">
                    <Input value={p.defect} onChange={(e) => setPoint(i, "defect")(e.target.value)} disabled={locked} maxLength={500} aria-label={`${t(`checkpoint.${p.checkpoint}`)} ${t("ui.qc.cp.defect")}`} />
                  </td>
                  <td className="w-36 px-2 py-1.5">
                    <Select value={p.severity} onValueChange={setPoint(i, "severity")} options={[{ value: "", label: "—" }, ...labelled(options.severities, "severity")]} placeholder="—" disabled={locked} aria-label={`${t(`checkpoint.${p.checkpoint}`)} ${t("ui.qc.cp.severity")}`} />
                  </td>
                  <td className="px-2 py-1.5">
                    <Input value={p.remarks} onChange={(e) => setPoint(i, "remarks")(e.target.value)} disabled={locked} maxLength={500} aria-label={`${t(`checkpoint.${p.checkpoint}`)} ${t("ui.qc.cp.remarks")}`} />
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </section>
      <DocFoot note={t("ui.qc.note")} onClear={() => load("")} onSave={locked ? undefined : save} saveLabel={t("ui.qc.save")} pending={pending} />
    </div>
  );
}

export { QualityCheckForm };
