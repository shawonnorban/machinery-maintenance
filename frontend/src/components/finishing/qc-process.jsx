"use client";

import { useRouter } from "next/navigation";
import { ScanSearch } from "lucide-react";
import { Select } from "@/components/ui/select";
import { Input } from "@/components/ui/input";
import { Textarea } from "@/components/ui/textarea";
import { Field, Group, EditorFoot, LinesCard, usePaper } from "@/components/dyehouse/chem-store";
import { useT } from "@/lib/i18n";

/*
 * Finishing Line → QC Process → Four-Point Fabric Inspection (AnnotechERP
 * 18/04): Brickland's entry tab — load a batch (its buyer, style, colour,
 * lot and weights come with it), the lab report, who inspected and the
 * verdict, then the rolls, each weighed and graded.
 */

const today = () => new Date().toLocaleDateString("en-CA", { timeZone: "Asia/Dhaka" });
const kg = (v) => Number(v || 0).toLocaleString("en-US", { maximumFractionDigits: 3 });
const blank = (v) => (v === "" || v === undefined ? null : v);
const num = (v) => (v === "" || v === null || v === undefined ? null : Number(v));
const str = (v) => (v === null || v === undefined ? "" : String(v));
const firstError = (res) => (res?.errors ? Object.values(res.errors).flat()[0] : res?.message);
const SHIFTS = ["DAY", "NIGHT"];
const VERDICTS = ["PASS", "HOLD", "REJECT"];

/**
 * @param {{ inspections: object[], options: object, canManage: boolean, load: Function, save: Function, backHref: string, initialNo?: string }} props
 */
function FinInspectionEditor({ inspections, options, canManage, load, save, backHref, initialNo = null }) {
  const t = useT("finishing");
  const router = useRouter();
  const c = (k, p) => (k === "ed.add_item" ? t("qc.insp.add") : t(`qc.${k}`, p));
  const fresh = () => ({ ref: null, status: "DRAFT", ref_no: "", receipt_id: "", batch: null, insp_date: today(), shift: "", actual_dia: "", shrinkage_l: "", shrinkage_w: "", spirality: "", inspector_id: "", shift_incharge_id: "", qc_incharge_id: "", verdict: "", shade_note: "", qc_comment: "", rolls: [] });
  const toForm = (x) => ({
    ...Object.fromEntries(Object.entries(x).map(([k, v]) => [k, k === "rolls" ? v : str(v)])),
    ref: x.ref_no,
    status: x.status,
    batch: { buyer: x.buyer, style: x.style, colour: x.colour, lot_no: x.lot_no, weights: x.weights, label: x.batch },
    rolls: x.rolls.map((r) => ({ ...r, weight_kg: str(r.weight_kg), points: str(r.points), grade: str(r.grade), defect: str(r.defect) })),
  });
  const { form, setForm, entry, setEntry, pending, startTransition, warn, open, toastManager } = usePaper({ blank: fresh, toForm, load, initialNo, docKey: "doc" });
  const set = (k, v) => setForm((f) => ({ ...f, [k]: v }));
  const editable = canManage && form.status === "DRAFT";
  const none = { roll_no: "", weight_kg: "", points: "", grade: "", result: "", defect: "" };
  const e = entry ?? none;
  const verdicts = VERDICTS.map((v) => ({ value: v, label: c(`opt.verdict.${v}`) }));
  const users = [{ value: "", label: c("f.select") }, ...options.users];
  // A batch being edited stays among the choices once it has moved on.
  const cards = form.receipt_id && !options.cards.some((x) => x.value === form.receipt_id) ? [{ value: form.receipt_id, label: form.batch?.label ?? form.receipt_id }, ...options.cards] : options.cards;

  function pickBatch(v) {
    const k = options.cards.find((x) => x.value === v);
    setForm((f) => ({ ...f, receipt_id: v ?? "", batch: k ? { buyer: k.buyer, style: k.style, colour: k.colour, lot_no: k.lot_no, weights: `${kg(k.qty)} kg / ${k.rolls}`, label: k.label } : null }));
  }

  function add() {
    if (!e.roll_no.trim() || !(Number(e.weight_kg) > 0) || !e.result) return warn(c("insp.line_incomplete"));
    if (form.rolls.some((r) => r.roll_no.trim().toUpperCase() === e.roll_no.trim().toUpperCase())) return warn(c("insp.roll_twice", { roll: e.roll_no.trim().toUpperCase() }));
    setForm((f) => ({ ...f, rolls: [...f.rolls, { ...e }] }));
    setEntry(null);
  }

  function submit() {
    startTransition(async () => {
      const res = await save(form.ref, {
        ref_no: blank(form.ref_no),
        receipt_id: blank(form.receipt_id),
        insp_date: blank(form.insp_date),
        shift: blank(form.shift),
        actual_dia: num(form.actual_dia),
        shrinkage_l: num(form.shrinkage_l),
        shrinkage_w: num(form.shrinkage_w),
        spirality: num(form.spirality),
        inspector_id: blank(form.inspector_id),
        shift_incharge_id: blank(form.shift_incharge_id),
        qc_incharge_id: blank(form.qc_incharge_id),
        verdict: blank(form.verdict),
        shade_note: blank(form.shade_note),
        qc_comment: blank(form.qc_comment),
        rolls: form.rolls.map((r) => ({ roll_no: r.roll_no, weight_kg: num(r.weight_kg), points: num(r.points) ?? 0, grade: blank(r.grade), result: blank(r.result), defect: blank(r.defect) })),
      });
      if (res?.status === "success") {
        setForm(toForm(res.doc));
        toastManager.add({ title: c("insp.saved", { no: res.doc.ref_no }), type: "success" });
        router.refresh();
      } else warn(firstError(res));
    });
  }

  const totalKg = form.rolls.reduce((s, r) => s + (Number(r.weight_kg) || 0), 0);
  const totalPoints = form.rolls.reduce((s, r) => s + (Number(r.points) || 0), 0);
  const b = form.batch ?? {};

  return (
    <div className="flex flex-col gap-4">
      <div className="flex flex-col gap-4 rounded-lg border border-border bg-surface p-5">
        <div className="flex items-start justify-between gap-3">
          <div>
            <h2 className="flex items-center gap-2 text-base font-semibold">
              <ScanSearch className="size-4" /> {c("insp.title")}
            </h2>
            <p className="mt-0.5 text-[13px] text-foreground-muted">{c("insp.hint")}</p>
          </div>
          <span className="shrink-0 rounded-full bg-brand-subtle px-2.5 py-0.5 text-xs font-semibold text-brand">{form.status === "POSTED" ? c("opt.status.POSTED") : form.ref ? c("ed.editing") : c("ed.new_badge")}</span>
        </div>
        <Field label={c("insp.existing")} hint={c("ed.existing_hint")}>
          <Select value={form.ref ?? ""} onValueChange={(v) => (v ? open(v) : (setForm(fresh()), setEntry(null)))} options={[{ value: "", label: c("insp.new_option") }, ...inspections.map((x) => ({ value: x.ref_no, label: `${x.ref_no} · ${x.batch_no ?? ""} · ${c(`opt.verdict.${x.verdict}`)}` }))]} placeholder={c("insp.new_option")} />
        </Field>
        <Group legend={c("insp.batch_details")}>
          <Field label={c("f.batch_no")} required hint={c("insp.batch_hint")} className="sm:col-span-3">
            <Select value={form.receipt_id} onValueChange={pickBatch} options={cards} placeholder={c("insp.load_batch")} disabled={!editable} />
          </Field>
          {[
            ["f.buyer", b.buyer],
            ["f.style", b.style],
            ["f.colour", b.colour],
            ["f.lot_no", b.lot_no],
            ["insp.weights", b.weights],
          ].map(([k, v]) => (
            <Field key={k} label={c(k)}>
              <Input value={v ?? ""} readOnly disabled />
            </Field>
          ))}
        </Group>
        <Group legend={c("insp.lab_report")}>
          <Field label={c("insp.date")} required>
            <Input type="date" value={form.insp_date} onChange={(ev) => set("insp_date", ev.target.value)} disabled={!editable} />
          </Field>
          <Field label={c("f.shift")} required>
            <Select value={form.shift} onValueChange={(v) => set("shift", v ?? "")} options={SHIFTS.map((s) => ({ value: s, label: c(`opt.shift.${s}`) }))} placeholder={c("f.select_shift")} disabled={!editable} />
          </Field>
          {["actual_dia", "shrinkage_l", "shrinkage_w", "spirality"].map((k) => (
            <Field key={k} label={c(`insp.${k}`)}>
              <Input type="number" step="0.01" value={form[k]} onChange={(ev) => set(k, ev.target.value)} disabled={!editable} />
            </Field>
          ))}
        </Group>
        <Group legend={c("insp.inspection")}>
          <Field label={c("insp.ref_no")} hint={c("insp.ref_hint")}>
            <Input value={form.ref_no} onChange={(ev) => set("ref_no", ev.target.value)} maxLength={40} disabled={!editable} />
          </Field>
          {["inspector_id", "shift_incharge_id", "qc_incharge_id"].map((k) => (
            <Field key={k} label={c(`insp.${k}`)}>
              <Select value={form[k]} onValueChange={(v) => set(k, v ?? "")} options={users} placeholder={c("f.select")} disabled={!editable} />
            </Field>
          ))}
          <Field label={c("insp.verdict")} required hint={c("insp.verdict_hint")}>
            <Select value={form.verdict} onValueChange={(v) => set("verdict", v ?? "")} options={verdicts} placeholder={c("f.select_shift")} disabled={!editable} />
          </Field>
          <Field label={c("insp.shade_note")} className="sm:col-span-3">
            <Textarea value={form.shade_note} onChange={(ev) => set("shade_note", ev.target.value)} rows={2} disabled={!editable} />
          </Field>
          <Field label={c("insp.qc_comment")} className="sm:col-span-3">
            <Textarea value={form.qc_comment} onChange={(ev) => set("qc_comment", ev.target.value)} rows={2} disabled={!editable} />
          </Field>
        </Group>
      </div>
      <LinesCard
        c={c}
        title={c("insp.rolls")}
        hint={c("insp.rolls_hint")}
        cols={[
          { key: "roll_no", label: c("insp.roll_no"), type: "text", max: 40 },
          { key: "weight_kg", label: c("insp.weight"), type: "number" },
          { key: "points", label: c("insp.points"), type: "number" },
          { key: "grade", label: c("insp.grade"), type: "text", max: 8 },
          { key: "result", label: c("insp.result"), type: "item", options: verdicts, placeholder: c("f.select_shift"), width: "w-36" },
          { key: "defect", label: c("insp.defect"), type: "text" },
        ]}
        entry={e}
        setEntry={setEntry}
        lines={form.rolls}
        setLines={(rolls) => setForm((f) => ({ ...f, rolls }))}
        editable={editable}
        onAdd={add}
        emptyHint={c("insp.no_rolls_hint")}
      />
      {form.rolls.length ? <p className="px-1 text-[13px] text-foreground-muted tabular-nums">{c("insp.totals", { rolls: form.rolls.length, kg: kg(totalKg), points: totalPoints })}</p> : null}
      <EditorFoot c={c} backHref={backHref} note={editable || !form.ref ? c("insp.footer") : c("insp.footer_posted")} editable={editable} pending={pending} onClear={() => (setForm(fresh()), setEntry(null))} onSave={submit} saveLabel={c("insp.save")} />
    </div>
  );
}

export { FinInspectionEditor };
