"use client";

import { useState, useTransition } from "react";
import { useRouter } from "next/navigation";
import { Filter, Pencil, Plus, Save, Trash2, X } from "lucide-react";
import { cn } from "@/lib/utils";
import { Input } from "@/components/ui/input";
import { Textarea } from "@/components/ui/textarea";
import { Select } from "@/components/ui/select";
import { Checkbox } from "@/components/ui/checkbox";
import { Button } from "@/components/ui/button";
import { Alert } from "@/components/ui/alert";
import { useToastManager } from "@/components/ui/toast";
import { useT } from "@/lib/i18n";
import { ChipPicker, ImageBox } from "@/components/merchandise/style-item-desk";

let seq = 0;
const key = () => `n${++seq}`;
const PARTS = ["top", "binding", "back"];

const blankFabric = (items) => ({
  key: key(),
  item_ids: items.length === 1 ? [items[0].value] : [],
  garment_part_ids: [],
  fabric_code: "",
  fabric_category: "KNIT",
  source: "MANUFACTURED",
  fiber_composition_id: "",
  spin_type: "",
  is_slub: false,
  is_melange: false,
  is_main: false,
  fabric_type_id: "",
  weight_from: "0",
  weight_to: "0",
  weight_unit: "GSM",
  width: "",
  supplier: "",
  remarks: "",
  counts: { top: { count: "", pct: "50" }, binding: { count: "", pct: "0" }, back: { count: "", pct: "50" } },
  wash_types: [],
  wash_na: false,
  finish_types: [],
  finish_na: false,
  other_spec: "",
});

const asForm = (f) => ({
  ...f,
  key: f.id ?? f.key ?? key(),
  fabric_code: f.fabric_code ?? "",
  spin_type: f.spin_type ?? "",
  fiber_composition_id: f.fiber_composition_id ?? "",
  fabric_type_id: f.fabric_type_id ?? "",
  weight_from: f.weight_from === null || f.weight_from === undefined ? "" : String(f.weight_from),
  weight_to: f.weight_to === null || f.weight_to === undefined ? "" : String(f.weight_to),
  weight_unit: f.weight_unit ?? "",
  width: f.width ?? "",
  supplier: f.supplier ?? "",
  remarks: f.remarks ?? "",
  other_spec: f.other_spec ?? "",
  counts: Object.fromEntries(PARTS.map((p) => [p, { count: f.counts?.[p]?.count ?? "", pct: f.counts?.[p]?.pct === null || f.counts?.[p]?.pct === undefined ? "" : String(f.counts[p].pct) }])),
});

const blankInstruction = () => ({ key: key(), image_title: "", images: [], gmt_colour_ids: [], lines: [{ colour_id: "", value: "", unit: "" }] });
const asInstruction = (k) => ({ ...k, key: k.id ?? key(), image_title: k.image_title ?? "", images: k.images ?? [], lines: k.lines?.length ? k.lines.map((l) => ({ colour_id: l.colour_id ?? "", value: l.value ?? "", unit: l.unit ?? "" })) : [{ colour_id: "", value: "", unit: "" }] });

/**
 * Item/Combo/Model → Fabrication (AnnotechERP 06/05), Brickland's two halves:
 * on the left the Knitting/YD Instruction Entry (a title, pictures, the
 * garment colours and colour / value / unit lines) and the Fabrication List;
 * on the right the fabric form — items and parts it is for, category,
 * manufactured or purchased, composition, spin and fibre features, fabric
 * type, weight band, width, supplier, counts (Suggest reads the GSM-wise
 * yarn count chart), wash, finish and the description written from the
 * boxes. Add puts the fabric on the list; Save keeps the list and the
 * instructions together.
 *
 * @param {{ styleId: string, data: object, save: Function, canManage: boolean }} props
 */
function StyleFabrication({ styleId, data, save, canManage }) {
  const t = useT("merchandise");
  const m = (k, p) => t(`master.fab.${k}`, p);
  const mi = (k, p) => t(`master.itm.${k}`, p);
  const router = useRouter();
  const toastManager = useToastManager();
  const o = data.options;
  const [fabrics, setFabrics] = useState(() => data.fabrics.map(asForm));
  const [instructions, setInstructions] = useState(() => data.instructions.map(asInstruction));
  const [form, setForm] = useState(() => blankFabric(o.items));
  const [editingKey, setEditingKey] = useState(null);
  const [instruction, setInstruction] = useState(null);
  const [errors, setErrors] = useState(null);
  const [pending, startTransition] = useTransition();
  const set = (field) => (value) => setForm((f) => ({ ...f, [field]: value }));
  const label = (list, id) => list.find((x) => x.value === id)?.label ?? "";
  const opt = (group, values) => values.map((v) => ({ value: v, label: m(`${group}.${v}`) }));
  const ph = (text, list) => [{ value: "", label: text }, ...list];

  /** "Knit Fabric — 100% Cotton Single Jersey · Combed · 180 GSM · Manufactured": written from the boxes. */
  function describe(f) {
    const head = [label(o.compositions, f.fiber_composition_id), label(o.fabric_types, f.fabric_type_id)].filter(Boolean).join(" ");
    const weight = Number(f.weight_from) > 0 ? `${Number(f.weight_from)}${Number(f.weight_to) > Number(f.weight_from) ? `-${Number(f.weight_to)}` : ""} ${f.weight_unit ? m(`unit.${f.weight_unit}`) : ""}`.trim() : "";
    const tail = [f.spin_type && m(`spin.${f.spin_type}`), f.is_slub && m("slub"), f.is_melange && m("melange"), weight, m(`source.${f.source}`)].filter(Boolean).join(" · ");
    return `${m(`category.${f.fabric_category}`)}${head ? ` — ${head}` : ""}${tail ? ` · ${tail}` : ""}`;
  }

  /** Suggest: the chart's counts for this fabric type and weight. */
  function suggest() {
    const gsm = Number(form.weight_from) || 0;
    const hit = o.count_chart.find((c) => c.fabric_type_id === form.fabric_type_id && gsm >= c.gsm_min && gsm <= c.gsm_max);
    if (!hit) {
      toastManager.add({ title: m("no_suggestion"), type: "danger" });
      return;
    }
    setForm((f) => ({ ...f, counts: Object.fromEntries(PARTS.map((p) => [p, { ...f.counts[p], count: hit[p] ?? "" }])) }));
  }

  function add() {
    const problems = [!form.item_ids.length && m("need_items"), !form.fiber_composition_id && m("need_composition"), !form.fabric_type_id && m("need_type")].filter(Boolean);
    if (problems.length) {
      setErrors(problems);
      return;
    }
    const row = { ...form, description: describe(form) };
    setFabrics((all) => (editingKey ? all.map((f) => (f.key === editingKey ? row : f)) : [...all, row]));
    setForm(blankFabric(o.items));
    setEditingKey(null);
    setErrors(null);
  }

  function submit() {
    startTransition(async () => {
      const payload = {
        fabrics: fabrics.map((f) => ({
          ...f,
          fabric_code: f.fabric_code || null,
          spin_type: f.spin_type || null,
          weight_from: f.weight_from === "" ? null : Number(f.weight_from),
          weight_to: f.weight_to === "" ? null : Number(f.weight_to),
          weight_unit: f.weight_unit || null,
          counts: Object.fromEntries(PARTS.map((p) => [p, { count: f.counts[p].count || null, pct: f.counts[p].pct === "" ? null : Number(f.counts[p].pct) }])),
        })),
        instructions: instructions.map((k) => ({ image_title: k.image_title || null, image_ids: k.images.map((i) => i.id), gmt_colour_ids: k.gmt_colour_ids, lines: k.lines.map((l) => ({ colour_id: l.colour_id || null, value: l.value || null, unit: l.unit || null })) })),
      };
      const result = await save(styleId, payload);
      if (result?.status === "success") {
        setFabrics(result.data.fabrics.map(asForm));
        setInstructions(result.data.instructions.map(asInstruction));
        setErrors(null);
        toastManager.add({ title: m("saved"), type: "success" });
        router.refresh();
      } else {
        setErrors(result?.errors ? [...new Set(Object.values(result.errors).flat())] : [result?.message]);
      }
    });
  }

  function keepInstruction() {
    setInstructions((all) => (all.some((k) => k.key === instruction.key) ? all.map((k) => (k.key === instruction.key ? instruction : k)) : [...all, instruction]));
    setInstruction(null);
  }

  return (
    <div className="flex flex-col gap-4 p-5">
      {errors ? <Alert variant="danger">{errors.join(" · ")}</Alert> : null}
      <div className="grid gap-6 xl:grid-cols-[minmax(0,1fr)_minmax(0,1.15fr)]">
        <div className="flex flex-col gap-4">
          <fieldset className="rounded-lg border border-border px-4 pt-2 pb-4">
            <legend className="px-1 text-[13px] font-semibold text-brand italic">{m("knit_title")}</legend>
            {canManage && !instruction ? (
              <div className="flex justify-end">
                <Button variant="outline" size="sm" onClick={() => setInstruction(blankInstruction())}>
                  <Plus /> {mi("add_new")}
                </Button>
              </div>
            ) : null}
            {instruction ? (
              <InstructionEditor value={instruction} onChange={setInstruction} colours={o.colours} units={o.units} m={m} mi={mi} onKeep={keepInstruction} onDrop={() => setInstruction(null)} />
            ) : instructions.length === 0 ? (
              <p className="py-8 text-center text-[13px] text-foreground-muted">{m("no_knit")}</p>
            ) : (
              <ul className="mt-2 flex flex-col gap-2">
                {instructions.map((k) => (
                  <li key={k.key} className="flex items-center justify-between gap-3 rounded-md border border-border px-3 py-2 text-sm">
                    <span className="min-w-0">
                      <span className="block truncate font-semibold">{k.image_title || m("untitled")}</span>
                      <span className="block truncate text-xs text-foreground-muted">
                        {k.gmt_colour_ids.map((id) => label(o.colours, id)).join(", ") || "—"} · {m("lines", { count: k.lines.filter((l) => l.colour_id || l.value).length })}
                      </span>
                    </span>
                    {canManage ? (
                      <span className="flex gap-1">
                        <Button variant="outline" size="sm" onClick={() => setInstruction(k)} aria-label={m("edit")}>
                          <Pencil />
                        </Button>
                        <Button variant="outline" size="sm" onClick={() => setInstructions((all) => all.filter((x) => x.key !== k.key))} aria-label={m("remove")}>
                          <Trash2 />
                        </Button>
                      </span>
                    ) : null}
                  </li>
                ))}
              </ul>
            )}
          </fieldset>

          <fieldset className="rounded-lg border border-border px-4 pt-2 pb-4">
            <legend className="px-1 text-[13px] font-semibold text-brand italic">{m("list_title")}</legend>
            {fabrics.length === 0 ? (
              <p className="py-8 text-center text-[13px] text-foreground-muted">{m("no_fabric")}</p>
            ) : (
              <ul className="mt-2 flex flex-col gap-2">
                {fabrics.map((f) => (
                  <li key={f.key} className={cn("flex items-start justify-between gap-3 rounded-md border px-3 py-2 text-sm", f.key === editingKey ? "border-brand bg-brand-subtle/40" : "border-border")}>
                    <span className="min-w-0">
                      <span className="block font-semibold">
                        {f.fabric_code ? `${f.fabric_code} · ` : ""}
                        {f.description}
                        {f.is_main ? <span className="ml-2 rounded bg-brand-subtle px-1.5 text-xs text-brand">{m("main")}</span> : null}
                      </span>
                      <span className="block text-xs text-foreground-muted">{f.item_ids.map((id) => label(o.items, id)).join(", ")}{f.garment_part_ids.length ? ` · ${f.garment_part_ids.map((id) => label(o.parts, id)).join(", ")}` : ""}</span>
                    </span>
                    {canManage ? (
                      <span className="flex shrink-0 gap-1">
                        <Button variant="outline" size="sm" onClick={() => { setForm(asForm(f)); setEditingKey(f.key); }} aria-label={m("edit")}>
                          <Pencil />
                        </Button>
                        <Button variant="outline" size="sm" onClick={() => setFabrics((all) => all.filter((x) => x.key !== f.key))} aria-label={m("remove")}>
                          <Trash2 />
                        </Button>
                      </span>
                    ) : null}
                  </li>
                ))}
              </ul>
            )}
          </fieldset>

          {canManage ? (
            <div className="flex gap-2">
              <Button onClick={submit} loading={pending}>
                <Save /> {mi("save")}
              </Button>
              <Button variant="outline" onClick={() => { setFabrics(data.fabrics.map(asForm)); setInstructions(data.instructions.map(asInstruction)); setInstruction(null); }} disabled={pending}>
                {mi("cancel")}
              </Button>
            </div>
          ) : null}
        </div>

        <div className="flex flex-col gap-3">
          <div className="flex justify-end">
            <button type="button" onClick={() => { setForm(blankFabric(o.items)); setEditingKey(null); }} className="inline-flex items-center gap-1 text-[13px] font-semibold text-warning">
              <Filter className="size-3.5" /> {m("clear_all")}
            </button>
          </div>
          <Row label={m("f.items")}>
            <ChipPicker styles={o.items} value={form.item_ids} onChange={set("item_ids")} placeholder={m("search")} />
          </Row>
          <Row label={m("f.parts")}>
            <ChipPicker styles={o.parts} value={form.garment_part_ids} onChange={set("garment_part_ids")} placeholder={m("search")} />
          </Row>
          <Row label={m("f.code")}>
            <Input value={form.fabric_code} onChange={(e) => set("fabric_code")(e.target.value)} aria-label={m("f.code")} />
          </Row>
          <Row label={m("f.category")}>
            <Select value={form.fabric_category} onValueChange={(v) => v && set("fabric_category")(v)} options={opt("category", o.categories)} />
          </Row>
          <Row label="">
            <div className="flex gap-5">
              {["MANUFACTURED", "PURCHASED"].map((s) => (
                <label key={s} className="flex items-center gap-2 text-[13px] font-semibold">
                  <input type="radio" name="fabric-source" checked={form.source === s} onChange={() => set("source")(s)} className="size-4 accent-brand" /> {m(`source_q.${s}`)}
                </label>
              ))}
            </div>
          </Row>
          <Row label={m("f.composition")}>
            <div className="flex gap-2">
              <div className="flex-1">
                <Select value={form.fiber_composition_id} onValueChange={(v) => set("fiber_composition_id")(v ?? "")} options={ph(m("ph.composition"), o.compositions)} placeholder={m("ph.composition")} />
              </div>
              <a href="/merchandise-desk/fiber-composition?tab=new" target="_blank" rel="noreferrer" className="inline-flex items-center gap-1 rounded-md border border-border-strong px-3 text-[13px] font-semibold hover:bg-surface-muted">
                <Plus className="size-3.5" /> {m("new_composition")}
              </a>
            </div>
          </Row>
          <Row label={m("f.fiber_feature")}>
            <div className="flex flex-wrap items-center gap-3">
              <span className="text-[13px]">{m("f.spin_type")}</span>
              <div className="w-40">
                <Select value={form.spin_type} onValueChange={(v) => set("spin_type")(v ?? "")} options={ph(m("ph.spin"), opt("spin", o.spin_types))} placeholder={m("ph.spin")} />
              </div>
              {["is_slub", "is_melange", "is_main"].map((f) => (
                <label key={f} className="flex items-center gap-1.5 text-[13px] font-semibold">
                  <Checkbox checked={form[f]} onCheckedChange={(c) => set(f)(c === true)} /> {m(`f.${f}`)}
                </label>
              ))}
            </div>
          </Row>
          <Row label={m("f.fabric_type")}>
            <Select value={form.fabric_type_id} onValueChange={(v) => set("fabric_type_id")(v ?? "")} options={ph(m("ph.fabric_type"), o.fabric_types)} placeholder={m("ph.fabric_type")} />
          </Row>
          <Row label={m("f.weight")}>
            <div className="grid grid-cols-[1fr_1fr_8rem] gap-2">
              <Input type="number" min="0" value={form.weight_from} onChange={(e) => set("weight_from")(e.target.value)} aria-label={m("f.weight_from")} />
              <Input type="number" min="0" value={form.weight_to} onChange={(e) => set("weight_to")(e.target.value)} aria-label={m("f.weight_to")} />
              <Select value={form.weight_unit} onValueChange={(v) => set("weight_unit")(v ?? "")} options={ph(m("select"), opt("unit", o.weight_units))} />
            </div>
          </Row>
          <Row label={m("f.width")}>
            <Input value={form.width} onChange={(e) => set("width")(e.target.value)} placeholder={'60" open / 72" tubular'} aria-label={m("f.width")} />
          </Row>
          <Row label={m("f.supplier")}>
            <Input value={form.supplier} onChange={(e) => set("supplier")(e.target.value)} aria-label={m("f.supplier")} />
          </Row>
          <Row label={m("f.remarks")}>
            <Input value={form.remarks} onChange={(e) => set("remarks")(e.target.value)} aria-label={m("f.remarks")} />
          </Row>
          <Row label={m("f.counts")}>
            <div className="flex flex-col gap-2">
              <div className="grid grid-cols-3 gap-2">
                {PARTS.map((p) => (
                  <div key={p} className="flex flex-col gap-1.5">
                    <span className="text-[13px] font-semibold">{m(`count.${p}`)}</span>
                    <Select value={form.counts[p].count} onValueChange={(v) => setForm((f) => ({ ...f, counts: { ...f.counts, [p]: { ...f.counts[p], count: v ?? "" } } }))} options={ph(m("ph.count"), [...new Set([...o.counts, form.counts[p].count].filter(Boolean))].map((c) => ({ value: c, label: c })))} placeholder={m("ph.count")} />
                    <Input type="number" min="0" max="100" value={form.counts[p].pct} onChange={(e) => setForm((f) => ({ ...f, counts: { ...f.counts, [p]: { ...f.counts[p], pct: e.target.value } } }))} aria-label={`${m(`count.${p}`)} %`} />
                  </div>
                ))}
              </div>
              <button type="button" onClick={suggest} className="inline-flex items-center gap-1 self-end text-[13px] font-semibold text-brand">
                <Filter className="size-3.5" /> {m("suggest")}
              </button>
            </div>
          </Row>
          {[
            ["wash_types", "wash_na", "f.wash"],
            ["finish_types", "finish_na", "f.finish"],
          ].map(([field, na, name]) => (
            <Row key={field} label={m(name)}>
              <div className="flex items-center gap-3">
                <div className="flex-1">{form[na] ? <Input disabled value="N/A" aria-label={m(name)} /> : <ChipPicker styles={o.washes.map((w) => ({ value: w, label: w }))} value={form[field]} onChange={set(field)} placeholder={m("search")} />}</div>
                <label className="flex items-center gap-1.5 text-[13px] font-semibold">
                  <Checkbox checked={form[na]} onCheckedChange={(c) => set(na)(c === true)} /> {m("na")}
                </label>
              </div>
            </Row>
          ))}
          <Row label={m("f.other_spec")}>
            <Textarea value={form.other_spec} onChange={(e) => set("other_spec")(e.target.value)} rows={2} aria-label={m("f.other_spec")} />
          </Row>
          <Row label={m("f.description")} hint={m("written")}>
            <Textarea value={describe(form)} readOnly rows={2} aria-label={m("f.description")} />
          </Row>
          {canManage ? (
            <div className="flex justify-end gap-2">
              {editingKey ? (
                <Button variant="outline" onClick={() => { setForm(blankFabric(o.items)); setEditingKey(null); }}>
                  <X /> {mi("cancel")}
                </Button>
              ) : null}
              <Button onClick={add}>
                <Plus /> {editingKey ? m("update") : m("add")}
              </Button>
            </div>
          ) : null}
        </div>
      </div>
    </div>
  );
}

function InstructionEditor({ value, onChange, colours, units, m, mi, onKeep, onDrop }) {
  const set = (field, v) => onChange({ ...value, [field]: v });
  const setLine = (i, field, v) => onChange({ ...value, lines: value.lines.map((l, j) => (j === i ? { ...l, [field]: v } : l)) });
  return (
    <div className="mt-2 flex flex-col gap-3">
      <label className="flex flex-col gap-1.5">
        <span className="text-[13px] font-semibold">{m("f.image_title")}</span>
        <Input value={value.image_title} onChange={(e) => set("image_title", e.target.value)} placeholder={m("ph.image_title")} />
      </label>
      <div className="flex flex-col gap-1.5">
        <span className="text-[13px] font-semibold">{m("f.images")}</span>
        <div className="flex flex-wrap items-center gap-2">
          {value.images.map((img, i) => (
            <span key={img.id} className="relative">
              {/* eslint-disable-next-line @next/next/no-img-element */}
              <img src={img.path} alt={`${m("f.images")} ${i + 1}`} className="size-14 rounded-md border border-border object-cover" />
              <button type="button" onClick={() => set("images", value.images.filter((x) => x.id !== img.id))} aria-label={m("clear_image", { n: i + 1 })} className="absolute -top-1.5 -right-1.5 rounded-full bg-surface p-0.5 shadow">
                <X className="size-3" />
              </button>
            </span>
          ))}
          <ImageBox compact label={m("add_image")} path={null} onUploaded={(f) => set("images", [...value.images, f])} onClear={() => {}} m={mi} />
        </div>
      </div>
      <div className="flex flex-col gap-1.5">
        <span className="text-[13px] font-semibold">{m("f.gmt_colour")}</span>
        <ChipPicker styles={colours} value={value.gmt_colour_ids} onChange={(v) => set("gmt_colour_ids", v)} placeholder={m("ph.colours")} />
      </div>
      <div className="overflow-x-auto rounded-md border border-border">
        <table className="w-full text-sm">
          <thead>
            <tr className="bg-surface-muted text-left text-[13px]">
              <th className="px-2 py-2 font-semibold">{m("f.colour")}</th>
              <th className="px-2 py-2 font-semibold">{m("f.value")}</th>
              <th className="px-2 py-2 font-semibold">{m("f.mou")}</th>
              <th className="w-20 px-2 py-2" />
            </tr>
          </thead>
          <tbody>
            {value.lines.map((l, i) => (
              <tr key={i} className="border-t border-border">
                <td className="px-2 py-1.5">
                  <Select value={l.colour_id} onValueChange={(v) => setLine(i, "colour_id", v ?? "")} options={[{ value: "", label: m("ph.colour") }, ...colours]} placeholder={m("ph.colour")} />
                </td>
                <td className="px-2 py-1.5">
                  <Input value={l.value} onChange={(e) => setLine(i, "value", e.target.value)} placeholder={m("f.value")} aria-label={m("f.value")} />
                </td>
                <td className="w-32 px-2 py-1.5">
                  <Select value={l.unit} onValueChange={(v) => setLine(i, "unit", v ?? "")} options={[{ value: "", label: m("ph.unit") }, ...units.map((u) => ({ value: u, label: m(`mou.${u}`) }))]} placeholder={m("ph.unit")} />
                </td>
                <td className="px-2 py-1.5">
                  <span className="flex gap-1">
                    <Button variant="outline" size="sm" onClick={() => onChange({ ...value, lines: [...value.lines.slice(0, i + 1), { colour_id: "", value: "", unit: "" }, ...value.lines.slice(i + 1)] })} aria-label={m("add_line", { n: i + 1 })}>
                      <Plus />
                    </Button>
                    <Button variant="outline" size="sm" onClick={() => onChange({ ...value, lines: value.lines.length > 1 ? value.lines.filter((_, j) => j !== i) : [{ colour_id: "", value: "", unit: "" }] })} aria-label={m("remove_line", { n: i + 1 })}>
                      <X />
                    </Button>
                  </span>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
      <div className="flex justify-end gap-2">
        <Button variant="outline" size="sm" onClick={onDrop}>
          {mi("cancel")}
        </Button>
        <Button size="sm" onClick={onKeep}>
          <Plus /> {m("keep_instruction")}
        </Button>
      </div>
    </div>
  );
}

function Row({ label, hint = null, children }) {
  return (
    <div className="grid items-start gap-3 sm:grid-cols-[8rem_1fr]">
      <span className="pt-2.5 text-[13px] font-semibold text-foreground sm:text-right">{label}</span>
      <div className="flex min-w-0 flex-col gap-1">
        {children}
        {hint ? <span className="text-xs text-foreground-muted">{hint}</span> : null}
      </div>
    </div>
  );
}

export { StyleFabrication };
