"use client";

import { useState, useTransition } from "react";
import { Pencil, Plus, Save, Trash2 } from "lucide-react";
import { Input } from "@/components/ui/input";
import { Select } from "@/components/ui/select";
import { Checkbox } from "@/components/ui/checkbox";
import { Button } from "@/components/ui/button";
import { Alert } from "@/components/ui/alert";
import { useToastManager } from "@/components/ui/toast";
import { useT } from "@/lib/i18n";
import { ChipPicker } from "@/components/merchandise/style-item-desk";

let seq = 0;
const blankForm = (o) => ({ key: null, prod_categories: [...o.prod_categories], item_id: o.items.length === 1 ? o.items[0].value : "", garment_part_id: "", fabric_id: "", all_colours: false, gmt_colour_id: "", fab_colour_id: "", ratio: "100" });
const asRows = (rows) => rows.map((r) => ({ ...r, key: r.id, gmt_colour_id: r.gmt_colour_id ?? "", fab_colour_id: r.fab_colour_id ?? "", ratio: String(r.ratio) }));

function Row({ label, required = false, children }) {
  return (
    <div className="grid items-center gap-3 sm:grid-cols-[8rem_1fr]">
      <span className="text-[13px] font-semibold text-foreground sm:text-right">
        {label}
        {required ? <span className="text-danger">*</span> : null}
      </span>
      <div className="min-w-0">{children}</div>
    </div>
  );
}

/**
 * Item/Combo/Model → Yarn/Fabric Mapping (AnnotechERP 06/05): Brickland's
 * Garment Part vs Fabric list on the left and the Fabric Mapping Form on the
 * right. A line names the item, the garment part, the style fabric it is cut
 * from, the garment colour (or all), the fabric colour and its ratio, for the
 * chosen production categories. Add puts a line on the list; Save keeps it.
 *
 * @param {{ styleId: string, data: object, save: Function, canManage: boolean }} props
 */
function StyleFabricMapping({ styleId, data, save, canManage }) {
  const t = useT("merchandise");
  const m = (k, p) => t(`master.yfm.${k}`, p);
  const toastManager = useToastManager();
  const o = data.options;
  const [base, setBase] = useState(data.rows);
  const [rows, setRows] = useState(() => asRows(data.rows));
  const [form, setForm] = useState(() => blankForm(o));
  const [errors, setErrors] = useState(null);
  const [pending, startTransition] = useTransition();
  const label = (list, id) => list.find((x) => x.value === id)?.label ?? "";
  const set = (field) => (value) => setForm((f) => ({ ...f, [field]: value }));
  const categories = o.prod_categories.map((v) => ({ value: v, label: m(`cat.${v}`) }));
  // Fabrics cut for the chosen item first; every fabric of the style when none is.
  const fabrics = o.fabrics.filter((f) => !form.item_id || !f.item_ids.length || f.item_ids.includes(form.item_id));
  const pick = (list, placeholder) => [{ value: "", label: placeholder }, ...list.map(({ value, label: l }) => ({ value, label: l }))];

  function add() {
    if (!form.prod_categories.length || !form.item_id || !form.garment_part_id || !form.fabric_id || (!form.all_colours && !form.gmt_colour_id)) {
      setErrors([m("need")]);
      return;
    }
    const row = {
      ...form,
      key: form.key ?? `m${++seq}`,
      item: label(o.items, form.item_id),
      part: label(o.parts, form.garment_part_id),
      fabric: label(o.fabrics, form.fabric_id),
      gmt_colour_id: form.all_colours ? "" : form.gmt_colour_id,
      gmt_colour: form.all_colours ? null : label(o.colours, form.gmt_colour_id),
      fab_colour: form.fab_colour_id ? label(o.colours, form.fab_colour_id) : null,
    };
    setRows((all) => (form.key ? all.map((r) => (r.key === form.key ? row : r)) : [...all, row]));
    setForm(blankForm(o));
    setErrors(null);
  }

  function submit() {
    startTransition(async () => {
      const result = await save(styleId, {
        rows: rows.map((r) => ({
          prod_categories: r.prod_categories,
          item_id: r.item_id,
          garment_part_id: r.garment_part_id,
          fabric_id: r.fabric_id,
          all_colours: r.all_colours,
          gmt_colour_id: r.all_colours ? null : r.gmt_colour_id || null,
          fab_colour_id: r.fab_colour_id || null,
          ratio: Number(r.ratio),
        })),
      });
      if (result?.status === "success") {
        setBase(result.data);
        setRows(asRows(result.data));
        setErrors(null);
        toastManager.add({ title: m("saved"), type: "success" });
      } else {
        setErrors(result?.errors ? [...new Set(Object.values(result.errors).flat())] : [result?.message]);
      }
    });
  }

  const head = ["part", "ratio", "fabric", "colour", "gmt_colour", "item", "action"];

  return (
    <div className="grid gap-5 p-5 xl:grid-cols-[1fr_1fr]">
      <section className="flex min-w-0 flex-col gap-3">
        <h3 className="text-sm font-semibold text-foreground">{m("list")}</h3>
        <div className="overflow-x-auto rounded-md border border-border">
          <table className="w-full min-w-max text-sm">
            <thead>
              <tr className="bg-brand text-left text-brand-foreground">
                {head.map((h) => (
                  <th key={h} className="px-3 py-2.5 text-[13px] font-semibold whitespace-nowrap">
                    {m(`col.${h}`)}
                  </th>
                ))}
              </tr>
            </thead>
            <tbody>
              {rows.length === 0 ? (
                <tr>
                  <td colSpan={head.length} className="px-4 py-14 text-center text-foreground-muted">
                    {m("empty")}
                  </td>
                </tr>
              ) : (
                rows.map((r) => (
                  <tr key={r.key} className={`border-b border-border align-middle last:border-0 ${form.key === r.key ? "bg-brand-subtle" : ""}`}>
                    <td className="px-3 py-2 font-medium text-foreground">{r.part}</td>
                    <td className="px-3 py-2 tabular-nums">{Number(r.ratio)}%</td>
                    <td className="max-w-64 px-3 py-2 text-foreground-muted">{r.fabric}</td>
                    <td className="px-3 py-2">{r.fab_colour ?? (r.all_colours ? m("same") : r.gmt_colour)}</td>
                    <td className="px-3 py-2">{r.all_colours ? m("all") : r.gmt_colour}</td>
                    <td className="px-3 py-2">{r.item}</td>
                    <td className="px-3 py-2">
                      {canManage ? (
                        <span className="flex gap-1">
                          <Button variant="outline" size="sm" onClick={() => setForm({ ...blankForm(o), ...r })} aria-label={m("edit")}>
                            <Pencil />
                          </Button>
                          <Button variant="outline" size="sm" onClick={() => setRows((all) => all.filter((x) => x.key !== r.key))} aria-label={m("remove")}>
                            <Trash2 />
                          </Button>
                        </span>
                      ) : null}
                    </td>
                  </tr>
                ))
              )}
            </tbody>
          </table>
        </div>
        {errors ? <Alert variant="danger">{errors.join(" · ")}</Alert> : null}
        {canManage ? (
          <div className="flex gap-2">
            <Button onClick={submit} loading={pending}>
              <Save /> {t("master.itm.save")}
            </Button>
            <Button variant="outline" onClick={() => { setRows(asRows(base)); setForm(blankForm(o)); setErrors(null); }} disabled={pending}>
              {t("master.itm.cancel")}
            </Button>
          </div>
        ) : null}
      </section>

      {canManage ? (
        <fieldset className="flex flex-col gap-3 rounded-lg border border-border px-4 pt-2 pb-4">
          <legend className="px-1 text-[13px] font-semibold text-brand italic">{m("form")}</legend>
          <div className="flex justify-end">
            <Button variant="outline" size="sm" onClick={() => { setForm(blankForm(o)); setErrors(null); }}>
              <Plus /> {m("new")}
            </Button>
          </div>
          <Row label={m("f.prod_category")} required>
            <ChipPicker styles={categories} value={form.prod_categories} onChange={set("prod_categories")} placeholder={m("f.prod_category")} />
          </Row>
          <Row label={m("f.item")} required>
            <Select value={form.item_id} onValueChange={(v) => setForm((f) => ({ ...f, item_id: v ?? "", fabric_id: "" }))} options={pick(o.items, m("ph_item"))} placeholder={m("ph_item")} aria-label={m("f.item")} />
          </Row>
          <Row label={m("f.part")} required>
            <Select value={form.garment_part_id} onValueChange={(v) => set("garment_part_id")(v ?? "")} options={pick(o.parts, m("ph_part"))} placeholder={m("ph_part")} aria-label={m("f.part")} />
          </Row>
          <Row label={m("f.fabric")} required>
            <Select value={form.fabric_id} onValueChange={(v) => set("fabric_id")(v ?? "")} options={pick(fabrics, m("ph_fabric"))} placeholder={m("ph_fabric")} aria-label={m("f.fabric")} />
            {o.fabrics.length === 0 ? <span className="mt-1 block text-xs text-foreground-muted">{m("no_fabric")}</span> : null}
          </Row>
          <Row label={m("f.gmt_colour")} required>
            <div className="flex items-center gap-3">
              <div className="min-w-0 flex-1">
                <Select value={form.gmt_colour_id} onValueChange={(v) => set("gmt_colour_id")(v ?? "")} options={pick(o.colours, m("ph_colour"))} placeholder={m("ph_colour")} disabled={form.all_colours} aria-label={m("f.gmt_colour")} />
              </div>
              <label className="flex items-center gap-2 text-[13px] font-semibold whitespace-nowrap text-foreground">
                <Checkbox checked={form.all_colours} onCheckedChange={(c) => setForm((f) => ({ ...f, all_colours: c === true, gmt_colour_id: c === true ? "" : f.gmt_colour_id }))} />
                {m("all_col")}
              </label>
            </div>
          </Row>
          <Row label={m("f.fab_colour")}>
            <div className="flex items-center gap-3">
              <div className="min-w-0 flex-1">
                <Select value={form.fab_colour_id} onValueChange={(v) => set("fab_colour_id")(v ?? "")} options={pick(o.colours, m("same"))} placeholder={m("same")} aria-label={m("f.fab_colour")} />
              </div>
              <label className="flex items-center gap-2 text-[13px] font-semibold whitespace-nowrap text-foreground">
                {m("f.ratio")}
                <Input type="number" min="0" max="100" step="any" value={form.ratio} onChange={(e) => set("ratio")(e.target.value)} className="w-24" />
              </label>
            </div>
          </Row>
          <div className="flex gap-2 pt-1">
            <Button onClick={add}>
              <Save /> {form.key ? m("update") : m("add")}
            </Button>
            <Button variant="outline" onClick={() => { setForm(blankForm(o)); setErrors(null); }}>
              {t("master.itm.cancel")}
            </Button>
          </div>
        </fieldset>
      ) : null}
    </div>
  );
}

export { StyleFabricMapping };
