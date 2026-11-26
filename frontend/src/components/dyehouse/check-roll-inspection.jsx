"use client";

import { useState, useTransition } from "react";
import { useRouter } from "next/navigation";
import { ChevronLeft, ChevronRight, RotateCcw, Save, ScanLine, ShieldCheck } from "lucide-react";
import { cn } from "@/lib/utils";
import { Select } from "@/components/ui/select";
import { Input } from "@/components/ui/input";
import { Textarea } from "@/components/ui/textarea";
import { Checkbox } from "@/components/ui/checkbox";
import { Button } from "@/components/ui/button";
import { useToastManager } from "@/components/ui/toast";
import { Field, Group } from "@/components/dyehouse/chem-store";
import { useT } from "@/lib/i18n";

const PAGE = 15;
const today = () => new Date().toLocaleDateString("en-CA", { timeZone: "Asia/Dhaka" });
const blank = () => ({ inspection_no: "", inspection_date: today(), roll_no: "", defects: [], department_id: "", remarks: "" });
const num = (v) => Number(v || 0).toLocaleString("en-US", { maximumFractionDigits: 3 });

/**
 * Check Roll Quality Inspection (AnnotechERP 17/08), laid out as Brickland
 * lays it out: the dyed batches on the left with the scan box a barcode
 * reader types into; on the right the inspection (number given on save,
 * date, the batch and the roll scanned), the batch's details read off the
 * card and its latest production, the defects to tick, and the assessment —
 * the department answerable, required once anything is ticked. Receiving a
 * roll with nothing ticked passes it; anything ticked holds it. A roll of a
 * batch is inspected once; an existing inspection of the batch loads back.
 *
 * @param {{ batches: object[], options: object, canManage: boolean, load: Function, save: Function }} props
 */
function CheckRollInspection({ batches, options, canManage, load, save }) {
  const t = useT("dyehouse");
  const router = useRouter();
  const toastManager = useToastManager();
  const [sheet, setSheet] = useState(null);
  const [form, setForm] = useState(blank);
  const [query, setQuery] = useState("");
  const [page, setPage] = useState(0);
  const [pending, startTransition] = useTransition();
  const q = (k, p) => t(`qc.${k}`, p);
  const warn = (title) => toastManager.add({ title, type: "danger" });
  const set = (k, v) => setForm((f) => ({ ...f, [k]: v }));

  function open(no) {
    startTransition(async () => {
      const r = await load(no);
      if (r?.status === "success") {
        setSheet(r.sheet);
        setForm(blank());
      } else warn(r?.message);
    });
  }

  function pickExisting(no) {
    const found = sheet?.inspections.find((x) => x.inspection_no === no);
    setForm(found ? { inspection_no: found.inspection_no, inspection_date: found.inspection_date, roll_no: found.roll_no, defects: found.defects, department_id: found.department_id ?? "", remarks: found.remarks ?? "" } : blank());
  }

  const toggle = (code, on) => setForm((f) => ({ ...f, defects: on ? [...f.defects, code] : f.defects.filter((d) => d !== code) }));

  function submit() {
    if (!sheet) return warn(q("ins.pick_first"));
    startTransition(async () => {
      const payload = {
        inspection_date: form.inspection_date || null,
        ...(form.inspection_no ? {} : { batch_id: sheet.batch_id }),
        roll_no: form.roll_no || null,
        defects: form.defects,
        department_id: form.department_id || null,
        remarks: form.remarks || null,
      };
      const r = await save(form.inspection_no || null, payload);
      if (r?.status === "success") {
        toastManager.add({ title: q(r.inspection.result === "PASSED" ? "ins.passed" : "ins.held", { roll: r.inspection.roll_no, no: r.inspection.inspection_no }), type: r.inspection.result === "PASSED" ? "success" : "warning" });
        const again = await load(sheet.batch_no);
        if (again?.status === "success") setSheet(again.sheet);
        setForm(blank());
        router.refresh();
      } else warn(r?.errors ? Object.values(r.errors).flat()[0] : r?.message);
    });
  }

  const needle = query.trim().toLowerCase();
  const shown = batches.filter((b) => !needle || b.batch_no.toLowerCase().includes(needle) || (b.colour ?? "").toLowerCase().includes(needle));
  const pages = Math.max(1, Math.ceil(shown.length / PAGE));
  const at = Math.min(page, pages - 1);
  const slice = shown.slice(at * PAGE, at * PAGE + PAGE);
  const shownText = (v) => <Input value={v ?? ""} readOnly />;
  const held = form.defects.length > 0;

  return (
    <div className="grid items-start gap-4 xl:grid-cols-[22rem_1fr]">
      <div className="rounded-lg border border-border bg-surface p-4">
        <h2 className="mb-3 text-base font-semibold">{q("ins.list_title")}</h2>
        <div className="relative mb-3">
          <ScanLine className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-foreground-subtle" />
          <Input
            value={query}
            onChange={(e) => {
              setQuery(e.target.value);
              setPage(0);
            }}
            onKeyDown={(e) => {
              // A barcode reader types the number and Enter: an exact match opens.
              if (e.key === "Enter") {
                const hit = batches.find((b) => b.batch_no.toLowerCase() === needle);
                if (hit) open(hit.batch_no);
              }
            }}
            placeholder={q("ins.search")}
            className="pl-9"
          />
        </div>
        <table className="w-full overflow-hidden rounded-md border border-border text-sm">
          <thead>
            <tr className="bg-brand text-left text-brand-foreground">
              <th className="px-3 py-2 font-semibold">{q("ins.batch_no")}</th>
              <th className="px-3 py-2 font-semibold">{q("ins.lot")}</th>
              <th className="px-3 py-2 font-semibold">{q("ins.colour")}</th>
            </tr>
          </thead>
          <tbody>
            {slice.length === 0 ? (
              <tr>
                <td colSpan={3} className="px-3 py-6 text-center text-foreground-muted">
                  {q("ins.none")}
                </td>
              </tr>
            ) : null}
            {slice.map((b) => (
              <tr key={b.batch_no} className={cn("border-t border-border", sheet?.batch_no === b.batch_no ? "bg-brand-subtle" : "hover:bg-surface-muted")}>
                <td className="px-3 py-1.5">
                  <button type="button" onClick={() => open(b.batch_no)} disabled={pending} className="font-mono font-medium text-brand hover:underline">
                    {b.batch_no}
                  </button>
                </td>
                <td className="px-3 py-1.5">{b.lot_no ?? "—"}</td>
                <td className="px-3 py-1.5">{b.colour ?? "—"}</td>
              </tr>
            ))}
          </tbody>
        </table>
        <div className="mt-3 flex items-center justify-between gap-2 text-[13px] text-foreground-muted">
          <span>{q("ins.showing", { from: shown.length ? at * PAGE + 1 : 0, to: at * PAGE + slice.length, total: shown.length })}</span>
          <span className="flex items-center gap-1">
            <Button variant="outline" size="sm" onClick={() => setPage(at - 1)} disabled={at === 0} aria-label="‹">
              <ChevronLeft />
            </Button>
            <span className="tabular-nums">
              {at + 1} / {pages}
            </span>
            <Button variant="outline" size="sm" onClick={() => setPage(at + 1)} disabled={at >= pages - 1} aria-label="›">
              <ChevronRight />
            </Button>
          </span>
        </div>
      </div>

      <div className="flex flex-col gap-4">
        <div className="flex flex-col gap-4 rounded-lg border border-border bg-surface p-5">
          <div className="flex items-start justify-between gap-3">
            <div>
              <h2 className="flex items-center gap-2 text-base font-semibold">
                <ShieldCheck className="size-4" /> {q("ins.title")}
              </h2>
              <p className="mt-0.5 text-[13px] text-foreground-muted">{q("ins.hint")}</p>
            </div>
            <span className="shrink-0 rounded-full bg-brand-subtle px-2.5 py-0.5 text-xs font-semibold text-brand">{form.inspection_no ? q("ins.editing") : q("ins.new_badge")}</span>
          </div>
          <Field label={q("ins.existing")} hint={q("ins.existing_hint")}>
            <Select
              value={form.inspection_no}
              onValueChange={(v) => pickExisting(v ?? "")}
              options={[{ value: "", label: q("ins.new_option") }, ...(sheet?.inspections ?? []).map((x) => ({ value: x.inspection_no, label: `${x.inspection_no} · ${x.roll_no} · ${q(`opt.result.${x.result}`)}` }))]}
              placeholder={q("ins.new_option")}
              disabled={!sheet}
            />
          </Field>
          <Group legend={q("ins.section_search")}>
            <Field label={q("ins.req_no")}>
              <Input value={form.inspection_no} placeholder={q("ins.auto")} readOnly />
            </Field>
            <Field label={q("ins.date")} required>
              <Input type="date" value={form.inspection_date} onChange={(e) => set("inspection_date", e.target.value)} disabled={!canManage} />
            </Field>
            <Field label={q("ins.batch_no")}>
              <Input value={sheet?.batch_no ?? ""} placeholder={q("ins.batch_ph")} readOnly />
            </Field>
            <Field label={q("ins.roll_no")} required hint={q("ins.roll_hint")}>
              <Input value={form.roll_no} onChange={(e) => set("roll_no", e.target.value)} placeholder={q("ins.roll_ph")} maxLength={40} disabled={!canManage || !sheet} />
            </Field>
          </Group>
          <Group legend={q("ins.section_batch")}>
            <Field label={q("ins.weights")}>{shownText(sheet ? num(sheet.weight_kg) : "")}</Field>
            <Field label={q("ins.machine")}>{shownText(sheet?.machine)}</Field>
            <Field label={q("ins.model")}>{shownText(sheet?.model_combo)}</Field>
            <Field label={q("ins.colour")}>{shownText(sheet?.colour)}</Field>
            <Field label={q("ins.group")}>{shownText(sheet?.colour_group)}</Field>
            <Field label={q("ins.buyer")}>{shownText(sheet?.buyer)}</Field>
            <Field label={q("ins.style")}>{shownText(sheet?.style)}</Field>
            <Field label={q("ins.order")}>{shownText(sheet?.order_no)}</Field>
            <Field label={q("ins.labdip")}>{shownText(sheet?.lab_dip_ref)}</Field>
            <Field label={q("ins.method")}>{shownText(sheet?.dyeing_method)}</Field>
            <Field label={q("ins.ratio")}>{shownText(sheet?.liquor_ratio)}</Field>
          </Group>
          {sheet?.bypass_check_roll ? <p className="rounded-md bg-warning-subtle px-3 py-2 text-sm text-warning">{q("ins.bypass_note", { no: sheet.batch_no })}</p> : null}
          <fieldset className="rounded-lg border border-border p-4">
            <legend className="px-1 text-[13px] font-semibold">{q("ins.section_defects")}</legend>
            <div className="grid gap-x-4 gap-y-2.5 sm:grid-cols-2 lg:grid-cols-4">
              {options.defects.map((code) => (
                <label key={code} className="flex items-center gap-2 text-sm">
                  <Checkbox checked={form.defects.includes(code)} onCheckedChange={(v) => toggle(code, v === true)} disabled={!canManage || !sheet} aria-label={q(`defect.${code}`)} />
                  <span>{q(`defect.${code}`)}</span>
                </label>
              ))}
            </div>
          </fieldset>
          <Group legend={q("ins.section_assessment")}>
            <Field label={q("ins.department")} required={held} hint={q("ins.department_hint")}>
              <Select value={form.department_id} onValueChange={(v) => set("department_id", v ?? "")} options={[{ value: "", label: "—" }, ...options.departments]} placeholder={q("ins.select_department")} disabled={!canManage || !held} />
            </Field>
            <Field label={q("ins.remarks")} className="sm:col-span-2">
              <Textarea value={form.remarks} onChange={(e) => set("remarks", e.target.value)} rows={2} disabled={!canManage || !sheet} />
            </Field>
          </Group>
          <p className={cn("rounded-md px-3 py-2 text-sm font-medium", held ? "bg-warning-subtle text-warning" : "bg-success-subtle text-success")}>{held ? q("ins.will_hold", { n: form.defects.length }) : q("ins.will_pass")}</p>
        </div>

        {canManage ? (
          <div className="flex flex-wrap items-center gap-3 rounded-lg border border-border bg-surface p-5">
            <p className="min-w-0 flex-1 text-[13px] text-foreground-muted">{q("ins.footer")}</p>
            <Button
              variant="outline"
              onClick={() => {
                setSheet(null);
                setForm(blank());
              }}
              disabled={pending}
            >
              <RotateCcw /> {q("ins.clear")}
            </Button>
            <Button onClick={submit} loading={pending}>
              <Save /> {q("ins.save")}
            </Button>
          </div>
        ) : null}
      </div>
    </div>
  );
}

export { CheckRollInspection };
