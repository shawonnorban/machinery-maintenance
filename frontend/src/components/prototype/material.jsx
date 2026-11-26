"use client";

import { useMemo, useState } from "react";
import { useRouter } from "next/navigation";
import { Ban, CheckCheck, ClipboardCheck, Plus, Search, Send, ShieldCheck, Trash2, X, XCircle } from "lucide-react";
import { cn } from "@/lib/utils";
import { Input } from "@/components/ui/input";
import { Select } from "@/components/ui/select";
import { Textarea } from "@/components/ui/textarea";
import { Button } from "@/components/ui/button";
import { ConfirmDialog } from "@/components/ui/confirm-dialog";
import { FormattedDateTime } from "@/components/ui/formatted-date-time";
import { useToastManager } from "@/components/ui/toast";
import { useT } from "@/lib/i18n";
import { DocFoot, DocHead, Field, ReadOnly, Section, blank, card, messagesOf, str, todayIso, useDocForm } from "@/components/prototype/proto-kit";

/*
 * Prototype Desk → Prototype Material Requisition (AnnotechERP 07/03): the
 * requisition (header, the sample programs it covers, its material lines)
 * walked through its approvals by the buttons beside Save; the sample
 * programs and what has been raised on each; and the material issued
 * against an approved requisition.
 */

const HEAD = ["sample_id", "req_date", "required_date", "department_id", "requested_by", "requisition_type", "requisition_to_id", "delivery_to_id", "remarks", "rejection_reason"];
const LINE = ["item_id", "material_group", "fabric_type_id", "color", "size", "description", "specification", "req_qty", "available_qty", "warehouse_id", "remarks"];
const blankLine = () => Object.fromEntries(LINE.map((k) => [k, ""]));
const num = (v) => Number(v) || 0;
const qty = (v) => (v === null || v === undefined || v === "" ? "" : String(Math.round(Number(v) * 10000) / 10000));
/** Which step each button takes, the state it starts from, and who may press it. */
const STEPS = [
  { key: "submit", from: ["DRAFT"], can: "manage", icon: Send, confirm: true },
  { key: "dept-approve", from: ["SUBMITTED"], can: "approve", icon: ShieldCheck, confirm: false },
  { key: "verify", from: ["DEPT_APPROVED"], can: "approve", icon: ClipboardCheck, confirm: true },
  { key: "approve", from: ["STORE_VERIFIED"], can: "approve", icon: CheckCheck, confirm: true },
  { key: "reject", from: ["SUBMITTED", "DEPT_APPROVED", "STORE_VERIFIED"], can: "approve", icon: XCircle, confirm: true, destructive: true },
  { key: "cancel", from: ["DRAFT", "SUBMITTED", "DEPT_APPROVED", "STORE_VERIFIED", "APPROVED", "REJECTED"], can: "manage", icon: Ban, confirm: true, destructive: true },
];
const stepKey = (key) => key.replace("-", "_");

function MaterialRequisitionForm({ options, initial, existing, canManage, canApprove, actions }) {
  const t = useT("prototype");
  const router = useRouter();
  const toastManager = useToastManager();
  const [confirm, setConfirm] = useState(null);
  const formOf = (row) => ({
    ...Object.fromEntries(HEAD.map((k) => [k, str(row?.[k])])),
    req_date: row?.req_date ?? todayIso(),
    programs: (row?.programs ?? []).map((p) => p.program_id),
    lines: row?.lines?.length ? row.lines.map((l) => ({ ...Object.fromEntries(LINE.map((k) => [k, str(l[k])])), issued_qty: l.issued_qty })) : [blankLine()],
  });
  const payloadOf = (form) => ({
    ...Object.fromEntries(HEAD.map((k) => [k, blank(form[k])])),
    programs: form.programs.filter(Boolean),
    lines: form.lines.map((l) => Object.fromEntries(LINE.map((k) => [k, blank(l[k])]))),
  });
  const doc = useDocForm({ kind: "material-requisitions", tab: "requisition", param: "mr", noKey: "mr_no", initial, formOf, payloadOf, actions, savedText: (no) => t("ui.mr.saved", { no }) });
  const { row, form, set, setForm, errors, setErrors, pending, startTransition, load, save, take, address } = doc;
  const editable = canManage && (!row || row.editable);
  const linesOpen = editable && (!row || row.lines_open);
  const sample = row ?? options.samples.find((s) => s.value === form.sample_id);
  const opt = (list) => [{ value: "", label: t("ui.select") }, ...list];
  const dash = (list) => [{ value: "", label: "—" }, ...list];
  const unitOf = (item) => options.items.find((i) => i.value === item)?.uom ?? "";
  const setLine = (i, key) => (v) => setForm((f) => ({ ...f, lines: f.lines.map((l, j) => (j === i ? { ...l, [key]: v ?? "" } : l)) }));
  const programsById = useMemo(() => Object.fromEntries(options.programs.map((p) => [p.value, p])), [options.programs]);
  const allowed = { manage: canManage, approve: canApprove };
  const steps = row ? STEPS.filter((s) => s.from.includes(row.state) && allowed[s.can]) : [];
  const removable = row && canManage && (row.editable || row.state === "CANCELLED");

  function run(step) {
    setConfirm(null);
    setErrors(null);
    startTransition(async () => {
      if (step === "delete") {
        const result = await actions.remove("material-requisitions", row.mr_no);
        if (result?.status !== "success") return setErrors(messagesOf(result));
        toastManager.add({ title: t("ui.mr.done.delete", { no: row.mr_no }), type: "success" });
        load("");
        return router.refresh();
      }
      const result = await actions.act("material-requisitions", row.mr_no, step);
      if (result?.status !== "success") return setErrors(messagesOf(result));
      take(result.data);
      address(result.data.mr_no);
      toastManager.add({ title: t(`ui.mr.done.${stepKey(step)}`, { no: result.data.mr_no }), type: "success" });
      router.refresh();
    });
  }
  const press = (step) => (STEPS.find((s) => s.key === step)?.confirm === false ? run(step) : setConfirm(step));

  return (
    <div className="flex flex-col gap-4">
      <section className={cn(card, "flex flex-col gap-4 p-5")}>
        <DocHead
          title={t("ui.mr.form")}
          hint={t("ui.mr.form_hint")}
          badge={row ? t(`mr_state.${row.state}`) : t("ui.new")}
          existingLabel={t("ui.mr.existing")}
          newOption={t("ui.mr.new_option")}
          existing={existing}
          value={row?.mr_no ?? ""}
          onPick={load}
          errors={errors}
        />
        <Section title={t("ui.mr.s.requisition")}>
          <Field label={t("ui.mr.f.mr_no")}>
            <ReadOnly value={row?.mr_no} placeholder={t("ui.issued_on_save")} />
          </Field>
          <Field label={t("ui.mr.f.sample_no")} required={!row} hint={t("ui.mr.h.sample_no")}>
            <Select value={form.sample_id} onValueChange={set("sample_id")} options={opt(row ? [{ value: row.sample_id, label: row.sample_no }] : options.samples)} disabled={!editable || Boolean(row)} aria-label={t("ui.mr.f.sample_no")} />
          </Field>
          <Field label={t("ui.mr.f.requisition_no")}>
            <ReadOnly value={sample?.requisition_no} />
          </Field>
          <Field label={t("ui.mr.f.buyer")}>
            <ReadOnly value={sample?.buyer} />
          </Field>
          <Field label={t("ui.mr.f.style")}>
            <ReadOnly value={[sample?.style_name, sample?.style_ref].filter(Boolean).join(" · ")} />
          </Field>
          <Field label={t("ui.mr.f.req_date")} required>
            <Input type="date" value={form.req_date} onChange={(e) => set("req_date")(e.target.value)} disabled={!editable} />
          </Field>
          <Field label={t("ui.mr.f.required_date")}>
            <Input type="date" value={form.required_date} onChange={(e) => set("required_date")(e.target.value)} disabled={!editable} />
          </Field>
          <Field label={t("ui.mr.f.department")}>
            <Select value={form.department_id} onValueChange={set("department_id")} options={opt(options.departments)} disabled={!editable} aria-label={t("ui.mr.f.department")} />
          </Field>
          <Field label={t("ui.mr.f.requested_by")} required hint={t("ui.mr.h.requested_by")}>
            <Select value={form.requested_by} onValueChange={set("requested_by")} options={opt(options.users)} disabled={!editable} aria-label={t("ui.mr.f.requested_by")} />
          </Field>
          <Field label={t("ui.mr.f.requisition_type")} hint={t("ui.mr.h.requisition_type")}>
            <Select value={form.requisition_type} onValueChange={set("requisition_type")} options={opt(options.requisition_types.map((v) => ({ value: v, label: v })))} disabled={!editable} aria-label={t("ui.mr.f.requisition_type")} />
          </Field>
          <Field label={t("ui.mr.f.requisition_to")} required>
            <Select value={form.requisition_to_id} onValueChange={set("requisition_to_id")} options={opt(options.stores)} disabled={!editable} aria-label={t("ui.mr.f.requisition_to")} />
          </Field>
          <Field label={t("ui.mr.f.delivery_to")} required hint={t("ui.mr.h.delivery_to")}>
            <Select value={form.delivery_to_id} onValueChange={set("delivery_to_id")} options={opt(options.stores)} disabled={!editable} aria-label={t("ui.mr.f.delivery_to")} />
          </Field>
          <Field label={t("ui.mr.f.remarks")} wide>
            <Textarea rows={2} value={form.remarks} onChange={(e) => set("remarks")(e.target.value)} disabled={!editable} />
          </Field>
        </Section>
        <Section title={t("ui.mr.s.approval")}>
          <Field label={t("ui.mr.f.state")}>
            <ReadOnly value={row ? t(`mr_state.${row.state}`) : t("mr_state.DRAFT")} />
          </Field>
          <Field label={t("ui.mr.f.dept_approved")}>
            <ReadOnly value={row ? (row.dept_approved ?? "—") : ""} />
          </Field>
          <Field label={t("ui.mr.f.store_verified")}>
            <ReadOnly value={row ? (row.store_verified ?? "—") : ""} />
          </Field>
          <Field label={t("ui.mr.f.approved")}>
            <ReadOnly value={row ? (row.approved ?? "—") : ""} />
          </Field>
          <Field label={t("ui.mr.f.rejection_reason")} hint={t("ui.mr.h.rejection_reason")} wide>
            <Textarea rows={2} value={form.rejection_reason} onChange={(e) => set("rejection_reason")(e.target.value)} disabled={!editable} />
          </Field>
        </Section>
      </section>
      <section className={cn(card, "p-4")}>
        <div className="flex items-start justify-between gap-3">
          <div>
            <h3 className="text-sm font-semibold text-foreground">{t("ui.mr.programs")}</h3>
            <p className="mt-0.5 text-xs text-foreground-muted">{t("ui.mr.programs_hint")}</p>
          </div>
          {editable ? (
            <Button type="button" variant="outline" size="sm" onClick={() => setForm((f) => ({ ...f, programs: [...f.programs, ""] }))}>
              <Plus /> {t("ui.mr.add_program")}
            </Button>
          ) : null}
        </div>
        <table className="mt-3 w-full text-[13px]">
          <thead>
            <tr className="border-b border-border text-left text-foreground-muted">
              {["program_no", "company", "program_date"].map((k) => (
                <th key={k} className="px-2 py-2 font-medium">
                  {t(`ui.mr.pc.${k}`)}
                </th>
              ))}
              <th />
            </tr>
          </thead>
          <tbody>
            {form.programs.length === 0 ? (
              <tr>
                <td colSpan={4} className="px-2 py-6 text-center text-foreground-muted">
                  <p className="font-medium text-foreground">{t("ui.no_lines")}</p>
                  <p className="text-xs">{t("ui.mr.programs_empty")}</p>
                </td>
              </tr>
            ) : (
              form.programs.map((p, i) => (
                <tr key={i} className="border-b border-border last:border-0">
                  <td className="w-64 px-2 py-1.5">
                    <Select
                      value={p}
                      onValueChange={(v) => setForm((f) => ({ ...f, programs: f.programs.map((x, j) => (j === i ? (v ?? "") : x)) }))}
                      options={dash(options.programs.map((g) => ({ value: g.value, label: g.program_no })))}
                      placeholder="—"
                      disabled={!editable}
                      aria-label={`${t("ui.mr.pc.program_no")} ${i + 1}`}
                    />
                  </td>
                  <td className="px-2 py-1.5">{programsById[p]?.company ?? "—"}</td>
                  <td className="px-2 py-1.5">{programsById[p]?.program_date ? <FormattedDateTime value={programsById[p].program_date} mode="date" /> : "—"}</td>
                  <td className="px-2 py-1.5 text-right">
                    {editable ? (
                      <Button type="button" variant="ghost" size="sm" onClick={() => setForm((f) => ({ ...f, programs: f.programs.filter((_, j) => j !== i) }))} aria-label={`${t("ui.dt.remove_line")} ${i + 1}`}>
                        <X />
                      </Button>
                    ) : null}
                  </td>
                </tr>
              ))
            )}
          </tbody>
        </table>
      </section>
      <section className={cn(card, "p-4")}>
        <div className="flex items-start justify-between gap-3">
          <div>
            <h3 className="text-sm font-semibold text-foreground">{t("ui.mr.lines")}</h3>
            <p className="mt-0.5 text-xs text-foreground-muted">{t("ui.mr.lines_hint")}</p>
          </div>
          {linesOpen ? (
            <Button type="button" variant="outline" size="sm" onClick={() => setForm((f) => ({ ...f, lines: [...f.lines, blankLine()] }))}>
              <Plus /> {t("ui.mr.add_line")}
            </Button>
          ) : null}
        </div>
        <div className="mt-3 overflow-x-auto">
          <table className="w-full min-w-[1900px] text-[13px]">
            <thead>
              <tr className="border-b border-border text-left text-foreground-muted">
                {["item", "material_group", "fabric_type", "color", "size", "description", "specification", "req_qty", "unit", "available_qty", "short_qty", "issued", "warehouse", "remarks"].map((k) => (
                  <th key={k} className={cn("px-1.5 py-2 font-medium", ["req_qty", "available_qty", "short_qty", "issued"].includes(k) && "text-right")}>
                    {t(`ui.mr.lc.${k}`)}
                  </th>
                ))}
                <th />
              </tr>
            </thead>
            <tbody>
              {form.lines.length === 0 ? (
                <tr>
                  <td colSpan={15} className="px-2 py-6 text-center text-foreground-muted">
                    <p className="font-medium text-foreground">{t("ui.no_lines")}</p>
                    <p className="text-xs">{t("ui.mr.lines_empty")}</p>
                  </td>
                </tr>
              ) : (
                form.lines.map((l, i) => {
                  const short = l.available_qty === "" ? "" : Math.max(0, num(l.req_qty) - num(l.available_qty));
                  const text = (k, w = "w-32", max = 255) => (
                    <td className={cn("px-1.5 py-1.5", w)}>
                      <Input value={l[k]} onChange={(e) => setLine(i, k)(e.target.value)} disabled={!linesOpen} maxLength={max} aria-label={`${t(`ui.mr.lc.${k}`)} ${i + 1}`} />
                    </td>
                  );
                  return (
                    <tr key={i} className="border-b border-border last:border-0">
                      <td className="w-56 px-1.5 py-1.5">
                        <Select value={l.item_id} onValueChange={setLine(i, "item_id")} options={dash(options.items)} placeholder="—" disabled={!linesOpen} aria-label={`${t("ui.mr.lc.item")} ${i + 1}`} />
                      </td>
                      <td className="w-40 px-1.5 py-1.5">
                        <Select value={l.material_group} onValueChange={setLine(i, "material_group")} options={dash(options.material_groups.map((g) => ({ value: g, label: t(`material_group.${g}`) })))} placeholder="—" disabled={!linesOpen} aria-label={`${t("ui.mr.lc.material_group")} ${i + 1}`} />
                      </td>
                      <td className="w-44 px-1.5 py-1.5">
                        <Select value={l.fabric_type_id} onValueChange={setLine(i, "fabric_type_id")} options={dash(options.fabric_types)} placeholder="—" disabled={!linesOpen} aria-label={`${t("ui.mr.lc.fabric_type")} ${i + 1}`} />
                      </td>
                      {text("color", "w-28", 96)}
                      {text("size", "w-20", 32)}
                      {text("description", "w-40")}
                      {text("specification", "w-40")}
                      <td className="w-24 px-1.5 py-1.5">
                        <Input type="number" min={0} step="0.01" value={l.req_qty} onChange={(e) => setLine(i, "req_qty")(e.target.value)} disabled={!linesOpen} aria-label={`${t("ui.mr.lc.req_qty")} ${i + 1}`} />
                      </td>
                      <td className="w-16 px-1.5 py-1.5 text-foreground-muted">{unitOf(l.item_id) || "—"}</td>
                      <td className="w-24 px-1.5 py-1.5">
                        <Input type="number" min={0} step="0.01" value={l.available_qty} onChange={(e) => setLine(i, "available_qty")(e.target.value)} disabled={!linesOpen} aria-label={`${t("ui.mr.lc.available_qty")} ${i + 1}`} />
                      </td>
                      <td className="w-20 px-1.5 py-1.5 text-right tabular-nums">{short === "" ? "—" : qty(short)}</td>
                      <td className="w-20 px-1.5 py-1.5 text-right tabular-nums">{qty(l.issued_qty ?? 0) || "0"}</td>
                      <td className="w-52 px-1.5 py-1.5">
                        <Select value={l.warehouse_id} onValueChange={setLine(i, "warehouse_id")} options={dash(options.stores)} placeholder="—" disabled={!linesOpen} aria-label={`${t("ui.mr.lc.warehouse")} ${i + 1}`} />
                      </td>
                      {text("remarks", "w-40", 500)}
                      <td className="px-1.5 py-1.5">
                        {linesOpen ? (
                          <Button type="button" variant="ghost" size="sm" onClick={() => setForm((f) => ({ ...f, lines: f.lines.filter((_, j) => j !== i) }))} aria-label={`${t("ui.mr.remove")} ${i + 1}`}>
                            <X />
                          </Button>
                        ) : null}
                      </td>
                    </tr>
                  );
                })
              )}
            </tbody>
          </table>
        </div>
        <div className="mt-3 flex items-center justify-between text-[13px] text-foreground-muted">
          <span>{t("ui.mr.total", { n: form.lines.length })}</span>
          <span className="font-medium tabular-nums text-foreground">{qty(form.lines.reduce((a, l) => a + num(l.req_qty), 0)) || "0"}</span>
        </div>
      </section>
      <DocFoot note={t("ui.mr.note")} onClear={() => load("")} onSave={editable ? save : undefined} saveLabel={row ? t("ui.mr.save_changes") : t("ui.mr.save")} pending={pending}>
        {removable ? (
          <Button type="button" variant="outline" onClick={() => setConfirm("delete")} disabled={pending}>
            <Trash2 /> {t("ui.mr.act.delete")}
          </Button>
        ) : null}
        {steps.map((s) => (
          <Button key={s.key} type="button" variant="outline" onClick={() => press(s.key)} disabled={pending}>
            <s.icon /> {t(`ui.mr.act.${stepKey(s.key)}`)}
          </Button>
        ))}
      </DocFoot>
      <ConfirmDialog
        open={confirm !== null}
        onOpenChange={(o) => !o && setConfirm(null)}
        title={confirm ? t(`ui.mr.confirm.${stepKey(confirm)}_title`) : ""}
        description={confirm ? t(`ui.mr.confirm.${stepKey(confirm)}_hint`, { no: row?.mr_no ?? "" }) : ""}
        confirmLabel={confirm ? t(`ui.mr.act.${stepKey(confirm)}`) : ""}
        cancelLabel={t("ui.mr.confirm.keep")}
        destructive={["reject", "cancel", "delete"].includes(confirm)}
        loading={pending}
        onConfirm={() => run(confirm)}
      />
    </div>
  );
}

/** Sample Programs: every program, and on picking one the requisitions raised on it. */
function SampleProgramsBrowser({ programs }) {
  const t = useT("prototype");
  const [query, setQuery] = useState("");
  const [picked, setPicked] = useState(null);
  const shown = programs.filter((p) => p.program_no.toLowerCase().includes(query.trim().toLowerCase()));
  const program = programs.find((p) => p.value === picked);
  return (
    <div className="grid gap-4 lg:grid-cols-[320px_1fr]">
      <section className={cn(card, "flex flex-col gap-3 p-4")}>
        <h3 className="text-sm font-semibold text-foreground">{t("ui.sp.title")}</h3>
        <div className="relative">
          <Search className="pointer-events-none absolute left-2.5 top-1/2 size-4 -translate-y-1/2 text-foreground-muted" />
          <Input value={query} onChange={(e) => setQuery(e.target.value)} placeholder={t("ui.sp.search")} className="pl-8" aria-label={t("ui.sp.search")} />
        </div>
        {shown.length === 0 ? (
          <p className="py-6 text-center text-[13px] text-foreground-muted">{t("ui.sp.no_match")}</p>
        ) : (
          <ul className="flex flex-col gap-1">
            {shown.map((p) => (
              <li key={p.value}>
                <button
                  type="button"
                  onClick={() => setPicked(p.value)}
                  className={cn("flex w-full items-center justify-between rounded-md px-3 py-2 text-left text-[13px]", picked === p.value ? "bg-brand-subtle text-brand" : "hover:bg-surface-muted")}
                >
                  <span className="font-mono">{p.program_no}</span>
                  <span className="rounded-full bg-surface-muted px-1.5 text-xs tabular-nums">{p.requisitions.length}</span>
                </button>
              </li>
            ))}
          </ul>
        )}
      </section>
      <section className={cn(card, "min-w-0 p-4")}>
        <h3 className="text-sm font-semibold text-foreground">{t("ui.sp.raised")}</h3>
        <p className="mt-0.5 text-xs text-foreground-muted">{t("ui.sp.raised_hint")}</p>
        {!program ? (
          <div className="py-10 text-center text-foreground-muted">
            <p className="font-medium text-foreground">{t("ui.sp.pick")}</p>
            <p className="text-xs">{t("ui.sp.pick_hint")}</p>
          </div>
        ) : (
          <table className="mt-3 w-full text-[13px]">
            <thead>
              <tr className="border-b border-border text-left text-foreground-muted">
                {["mr_no", "req_date", "state"].map((k) => (
                  <th key={k} className="px-2 py-2 font-medium">
                    {t(`ui.sp.col.${k}`)}
                  </th>
                ))}
              </tr>
            </thead>
            <tbody>
              {program.requisitions.length === 0 ? (
                <tr>
                  <td colSpan={3} className="px-2 py-6 text-center text-foreground-muted">
                    {t("ui.sp.none")}
                  </td>
                </tr>
              ) : (
                program.requisitions.map((m) => (
                  <tr key={m.mr_no} className="border-b border-border last:border-0">
                    <td className="px-2 py-2 font-mono">
                      <a className="text-brand hover:underline" href={`?tab=requisition&mr=${encodeURIComponent(m.mr_no)}`}>
                        {m.mr_no}
                      </a>
                    </td>
                    <td className="px-2 py-2">
                      <FormattedDateTime value={m.req_date} mode="date" />
                    </td>
                    <td className="px-2 py-2">{t(`mr_state.${m.state}`)}</td>
                  </tr>
                ))
              )}
            </tbody>
          </table>
        )}
      </section>
    </div>
  );
}

const ISSUE_HEAD = ["requisition_id", "issue_date", "store_id", "received_by", "remarks"];

/** Material Issue: an approved requisition's lines released; a saved issue is only viewed. */
function MaterialIssueForm({ options, initial, existing, canIssue, actions }) {
  const t = useT("prototype");
  const formOf = (row) => ({
    ...Object.fromEntries(ISSUE_HEAD.map((k) => [k, str(row?.[k])])),
    issue_date: row?.issue_date ?? todayIso(),
    lines: row?.lines?.map((l) => ({ line_id: l.line_id, qty: str(l.qty), remarks: str(l.remarks), label: l.label, unit: l.unit })) ?? [],
  });
  const payloadOf = (form) => ({ ...Object.fromEntries(ISSUE_HEAD.map((k) => [k, blank(form[k])])), lines: form.lines.map((l) => ({ line_id: blank(l.line_id), qty: blank(l.qty), remarks: blank(l.remarks) })) });
  const { row, form, set, setForm, errors, pending, load, save } = useDocForm({ kind: "material-issues", tab: "issue", param: "mi", noKey: "issue_no", initial, formOf, payloadOf, actions, savedText: (no) => t("ui.mi.saved", { no }) });
  const locked = !canIssue || Boolean(row);
  const requisition = options.requisitions.find((r) => r.value === form.requisition_id);
  const opt = (list) => [{ value: "", label: t("ui.select") }, ...list];
  const setLine = (i, key) => (v) => setForm((f) => ({ ...f, lines: f.lines.map((l, j) => (j === i ? { ...l, [key]: v ?? "" } : l)) }));
  const pickRequisition = (v) => setForm((f) => ({ ...f, requisition_id: v ?? "", store_id: options.requisitions.find((r) => r.value === v)?.store_id ?? f.store_id, lines: [] }));
  const lineOptions = row ? form.lines.map((l) => ({ value: l.line_id, label: l.label })) : (requisition?.lines ?? []);

  return (
    <div className="flex flex-col gap-4">
      <section className={cn(card, "flex flex-col gap-4 p-5")}>
        <DocHead title={t("ui.mi.form")} hint={t("ui.mi.form_hint")} badge={row ? row.issue_no : t("ui.new")} existingLabel={t("ui.mi.existing")} newOption={t("ui.mi.new_option")} existing={existing} value={row?.issue_no ?? ""} onPick={load} errors={errors} />
        <Section title={t("ui.mi.form")}>
          <Field label={t("ui.mi.f.issue_no")}>
            <ReadOnly value={row?.issue_no} placeholder={t("ui.issued_on_save")} />
          </Field>
          <Field label={t("ui.mi.f.requisition")} required hint={t("ui.mi.h.requisition")}>
            <Select value={form.requisition_id} onValueChange={pickRequisition} options={opt(row ? [{ value: row.requisition_id, label: row.mr_no }] : options.requisitions)} disabled={locked} aria-label={t("ui.mi.f.requisition")} />
          </Field>
          <Field label={t("ui.mi.f.issue_date")} required>
            <Input type="date" value={form.issue_date} onChange={(e) => set("issue_date")(e.target.value)} disabled={locked} />
          </Field>
          <Field label={t("ui.mi.f.store")} hint={t("ui.mi.h.store")}>
            <Select value={form.store_id} onValueChange={set("store_id")} options={opt(options.stores)} disabled={locked} aria-label={t("ui.mi.f.store")} />
          </Field>
          <Field label={t("ui.mi.f.received_by")}>
            <Input value={form.received_by} onChange={(e) => set("received_by")(e.target.value)} disabled={locked} maxLength={255} />
          </Field>
          <Field label={t("ui.mi.f.remarks")} wide>
            <Textarea rows={2} value={form.remarks} onChange={(e) => set("remarks")(e.target.value)} disabled={locked} />
          </Field>
        </Section>
      </section>
      <section className={cn(card, "p-4")}>
        <div className="flex items-start justify-between gap-3">
          <div>
            <h3 className="text-sm font-semibold text-foreground">{t("ui.mi.lines")}</h3>
            <p className="mt-0.5 text-xs text-foreground-muted">{t("ui.mi.lines_hint")}</p>
          </div>
          {!locked ? (
            <Button type="button" variant="outline" size="sm" onClick={() => setForm((f) => ({ ...f, lines: [...f.lines, { line_id: "", qty: "", remarks: "" }] }))} disabled={!requisition}>
              <Plus /> {t("ui.mi.add_line")}
            </Button>
          ) : null}
        </div>
        <table className="mt-3 w-full text-[13px]">
          <thead>
            <tr className="border-b border-border text-left text-foreground-muted">
              <th className="px-2 py-2 font-medium">{t("ui.mi.lc.line")}</th>
              <th className="px-2 py-2 text-right font-medium">{t("ui.mi.lc.qty")}</th>
              <th className="px-2 py-2 font-medium">{t("ui.mi.lc.remarks")}</th>
              <th />
            </tr>
          </thead>
          <tbody>
            {form.lines.length === 0 ? (
              <tr>
                <td colSpan={4} className="px-2 py-6 text-center text-foreground-muted">
                  <p className="font-medium text-foreground">{t("ui.no_lines")}</p>
                  <p className="text-xs">{t("ui.mi.lines_empty")}</p>
                </td>
              </tr>
            ) : (
              form.lines.map((l, i) => (
                <tr key={i} className="border-b border-border last:border-0">
                  <td className="px-2 py-1.5">
                    <Select value={l.line_id} onValueChange={setLine(i, "line_id")} options={[{ value: "", label: "—" }, ...lineOptions]} placeholder="—" disabled={locked} aria-label={`${t("ui.mi.lc.line")} ${i + 1}`} />
                  </td>
                  <td className="w-32 px-2 py-1.5">
                    <Input type="number" min={0} step="0.01" value={l.qty} onChange={(e) => setLine(i, "qty")(e.target.value)} disabled={locked} aria-label={`${t("ui.mi.lc.qty")} ${i + 1}`} />
                  </td>
                  <td className="w-64 px-2 py-1.5">
                    <Input value={l.remarks} onChange={(e) => setLine(i, "remarks")(e.target.value)} disabled={locked} maxLength={500} aria-label={`${t("ui.mi.lc.remarks")} ${i + 1}`} />
                  </td>
                  <td className="px-2 py-1.5 text-right">
                    {!locked ? (
                      <Button type="button" variant="ghost" size="sm" onClick={() => setForm((f) => ({ ...f, lines: f.lines.filter((_, j) => j !== i) }))} aria-label={`${t("ui.mr.remove")} ${i + 1}`}>
                        <X />
                      </Button>
                    ) : null}
                  </td>
                </tr>
              ))
            )}
          </tbody>
        </table>
        <div className="mt-3 flex items-center justify-between text-[13px] text-foreground-muted">
          <span>{t("ui.mr.total", { n: form.lines.length })}</span>
          <span className="font-medium tabular-nums text-foreground">{qty(form.lines.reduce((a, l) => a + num(l.qty), 0)) || "0"}</span>
        </div>
      </section>
      <DocFoot note={t("ui.mi.note")} onClear={() => load("")} onSave={locked ? undefined : save} saveLabel={t("ui.mi.save")} pending={pending} />
    </div>
  );
}

export { MaterialIssueForm, MaterialRequisitionForm, SampleProgramsBrowser };
