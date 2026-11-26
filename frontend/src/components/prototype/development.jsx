"use client";

import { useState, useTransition } from "react";
import { useRouter } from "next/navigation";
import { RotateCcw, Save } from "lucide-react";
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
 * Prototype Desk → Sample Development (AnnotechERP 07/03): one development per
 * version of an approved, open sample — schedule, team and the fourteen-point
 * checklist. Progress is completed points over applicable ones; when every
 * applicable point is done, or the status is set to Completed, it closes and
 * locks. An existing one loads back in place.
 */

const FIELDS = ["sample_id", "status", "dev_date", "start_date", "expected_completion", "actual_completion", "pattern_master", "sample_maker", "technician", "supervisor", "delay_reason", "remarks"];
const blankForm = () => ({ ...Object.fromEntries(FIELDS.map((k) => [k, ""])), status: "PENDING", dev_date: todayIso() });
const formOf = (row) => (row ? Object.fromEntries(FIELDS.map((k) => [k, str(row[k])])) : blankForm());
const tasksOf = (row, all) => all.map((task) => {
  const t = row?.tasks?.find((x) => x.task === task);
  return { task, status: str(t?.status), remarks: str(t?.remarks), completed_at: t?.completed_at ?? null };
});

function DevelopmentForm({ options, initial, existing, canManage, actions }) {
  const t = useT("prototype");
  const router = useRouter();
  const toastManager = useToastManager();
  const [row, setRow] = useState(initial);
  const [form, setForm] = useState(() => formOf(initial));
  const [tasks, setTasks] = useState(() => tasksOf(initial, options.tasks));
  const [errors, setErrors] = useState(null);
  const [pending, startTransition] = useTransition();
  const set = (key) => (v) => setForm((f) => ({ ...f, [key]: v ?? "" }));
  const locked = !canManage || Boolean(row?.locked);
  // A loaded development keeps its own sample; a new one offers the samples that can be developed.
  const samples = row ? [{ value: row.sample_id, label: `${row.sample_no} — ${[row.style_name ?? row.style_ref, row.sample_type, row.buyer].filter(Boolean).join(" · ")}` }] : options.samples;
  const card_ = row ?? options.samples.find((s) => s.value === form.sample_id) ?? {};
  const opt = (list) => [{ value: "", label: t("ui.select") }, ...list];
  const users = opt(options.users);

  function take(data) {
    setRow(data);
    setForm(formOf(data));
    setTasks(tasksOf(data, options.tasks));
  }

  function load(no) {
    window.history.replaceState(null, "", no ? `?tab=development&dev=${encodeURIComponent(no)}` : "?tab=development");
    setErrors(null);
    if (!no) return take(null);
    startTransition(async () => {
      const result = await actions.load("developments", no);
      if (result?.status === "success") take(result.data);
      else setErrors(messagesOf(result));
    });
  }

  function save() {
    setErrors(null);
    const payload = { ...Object.fromEntries(FIELDS.map((k) => [k, blank(form[k])])), tasks: tasks.map((x) => ({ task: x.task, status: blank(x.status), remarks: blank(x.remarks) })) };
    startTransition(async () => {
      const result = await actions.save("developments", row?.dev_no ?? null, payload);
      if (result?.status !== "success") return setErrors(messagesOf(result));
      take(result.data);
      window.history.replaceState(null, "", `?tab=development&dev=${encodeURIComponent(result.data.dev_no)}`);
      toastManager.add({ title: t("ui.dev.saved", { no: result.data.dev_no }), type: "success" });
      router.refresh();
    });
  }

  return (
    <div className="flex flex-col gap-4">
      <section className={cn(card, "flex flex-col gap-4 p-5")}>
        <div className="flex items-start justify-between gap-3">
          <div>
            <h2 className="text-base font-semibold text-foreground">{t("ui.dev.form")}</h2>
            <p className="mt-0.5 text-[13px] text-foreground-muted">{t("ui.dev.form_hint")}</p>
          </div>
          <span className="rounded-md bg-brand-subtle px-2 py-0.5 text-xs font-medium text-brand">{row ? t(`dev_status.${row.status}`) : t("ui.new")}</span>
        </div>
        <Field label={t("ui.dev.existing")} hint={t("ui.existing_hint")} wide>
          <Select value={row?.dev_no ?? ""} onValueChange={(v) => load(v ?? "")} options={[{ value: "", label: t("ui.dev.new_option") }, ...existing]} aria-label={t("ui.dev.existing")} />
        </Field>
        {errors ? (
          <Alert variant="danger">
            {errors.map((e) => (
              <p key={e}>{e}</p>
            ))}
          </Alert>
        ) : null}
        <Section title={t("ui.dev.s.development")}>
          <Field label={t("ui.dev.f.dev_no")} hint={t("ui.dev.h.dev_no")}>
            <ReadOnly value={row?.dev_no} placeholder={t("ui.auto_gen")} />
          </Field>
          <Field label={t("ui.dev.f.sample")} required hint={t("ui.dev.h.sample")}>
            <Select value={form.sample_id} onValueChange={set("sample_id")} options={opt(samples)} disabled={locked || Boolean(row)} aria-label={t("ui.dev.f.sample")} />
          </Field>
          <Field label={t("ui.dev.f.version")}>
            <ReadOnly value={row ? `V${row.version}` : card_.sample_version ? `V${card_.sample_version}` : ""} />
          </Field>
          <Field label={t("ui.dev.f.status")}>
            <Select value={form.status} onValueChange={set("status")} options={opt(options.statuses.map((s) => ({ value: s, label: t(`dev_status.${s}`) })))} disabled={locked} aria-label={t("ui.dev.f.status")} />
          </Field>
          <Field label={t("ui.dev.f.progress")}>
            <ReadOnly value={row ? `${row.progress}%` : ""} />
          </Field>
        </Section>
        <Section title={t("ui.dev.s.sample")}>
          {[["requisition_no", card_.requisition_no], ["sample_no", card_.sample_no], ["buyer", card_.buyer], ["style", card_.style_name ?? card_.style_ref], ["order", card_.order_no], ["sample_type", card_.sample_type], ["sample_stage", card_.sample_stage ? t(`stage.${card_.sample_stage}`) : ""], ["revision", card_.revision_no]].map(([k, v]) => (
            <Field key={k} label={t(`ui.dev.f.${k}`)}>
              <ReadOnly value={v} />
            </Field>
          ))}
        </Section>
        <Section title={t("ui.dev.s.schedule")}>
          <Field label={t("ui.dev.f.dev_date")} required>
            <Input type="date" value={form.dev_date} onChange={(e) => set("dev_date")(e.target.value)} disabled={locked} />
          </Field>
          <Field label={t("ui.dev.f.start_date")}>
            <Input type="date" value={form.start_date} onChange={(e) => set("start_date")(e.target.value)} disabled={locked} />
          </Field>
          <Field label={t("ui.dev.f.expected_completion")}>
            <Input type="date" value={form.expected_completion} onChange={(e) => set("expected_completion")(e.target.value)} disabled={locked} />
          </Field>
          <Field label={t("ui.dev.f.actual_completion")} hint={t("ui.dev.h.actual_completion")}>
            <Input type="date" value={form.actual_completion} onChange={(e) => set("actual_completion")(e.target.value)} disabled={locked} />
          </Field>
        </Section>
        <Section title={t("ui.dev.s.team")}>
          {["pattern_master", "sample_maker", "technician", "supervisor"].map((k) => (
            <Field key={k} label={t(`ui.dev.f.${k}`)}>
              <Select value={form[k]} onValueChange={set(k)} options={users} disabled={locked} aria-label={t(`ui.dev.f.${k}`)} />
            </Field>
          ))}
          <Field label={t("ui.dev.f.delay_reason")}>
            <Input value={form.delay_reason} onChange={(e) => set("delay_reason")(e.target.value)} disabled={locked} maxLength={500} />
          </Field>
        </Section>
        <Section title={t("ui.dev.s.notes")}>
          <Field label={t("ui.dev.f.remarks")} wide>
            <Textarea rows={3} value={form.remarks} onChange={(e) => set("remarks")(e.target.value)} disabled={locked} />
          </Field>
        </Section>
      </section>
      <section className={cn(card, "p-4")}>
        <h3 className="text-sm font-semibold text-foreground">{t("ui.dev.checklist")}</h3>
        <p className="mt-0.5 text-xs text-foreground-muted">{t("ui.dev.checklist_hint")}</p>
        <div className="mt-3 overflow-x-auto">
          <table className="w-full text-[13px]">
            <thead>
              <tr className="bg-brand text-left text-brand-foreground">
                {["task", "task_status", "completed_at", "remarks"].map((k) => (
                  <th key={k} className="px-3 py-2 font-semibold">
                    {t(`ui.dev.col.${k}`)}
                  </th>
                ))}
              </tr>
            </thead>
            <tbody>
              {tasks.map((x, i) => (
                <tr key={x.task} className="border-b border-border last:border-0">
                  <td className="px-3 py-1.5 font-medium text-foreground">{t(`task.${x.task}`)}</td>
                  <td className="px-3 py-1.5">
                    <div className="w-44">
                      <Select value={x.status} onValueChange={(v) => setTasks((all) => all.map((y, j) => (j === i ? { ...y, status: v ?? "" } : y)))} options={[{ value: "", label: "—" }, ...["PENDING", "IN_PROGRESS", "COMPLETED", "NOT_REQUIRED"].map((s) => ({ value: s, label: t(`task_status.${s}`) }))]} placeholder="—" disabled={locked} aria-label={`${t("ui.dev.col.task_status")} ${t(`task.${x.task}`)}`} />
                    </div>
                  </td>
                  <td className="px-3 py-1.5 text-foreground-muted">{x.completed_at ? <FormattedDateTime value={x.completed_at} /> : "—"}</td>
                  <td className="px-3 py-1.5">
                    <Input value={x.remarks} onChange={(e) => setTasks((all) => all.map((y, j) => (j === i ? { ...y, remarks: e.target.value } : y)))} disabled={locked} maxLength={500} aria-label={`${t("ui.dev.col.remarks")} ${t(`task.${x.task}`)}`} />
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </section>
      <section className={cn(card, "flex flex-wrap items-center justify-between gap-3 px-5 py-3.5")}>
        <p className="max-w-3xl text-[13px] text-foreground-muted">{t("ui.dev.note")}</p>
        <div className="flex gap-2">
          <Button type="button" variant="outline" onClick={() => load("")} disabled={pending}>
            <RotateCcw /> {t("ui.clear")}
          </Button>
          {!locked ? (
            <Button type="button" onClick={save} disabled={pending}>
              <Save /> {t("ui.dev.save")}
            </Button>
          ) : null}
        </div>
      </section>
    </div>
  );
}

export { DevelopmentForm };
