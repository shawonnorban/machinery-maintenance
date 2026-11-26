"use client";

import { useState, useTransition } from "react";
import { useRouter } from "next/navigation";
import { Plus, Save, Trash2 } from "lucide-react";
import { Input } from "@/components/ui/input";
import { Select } from "@/components/ui/select";
import { Checkbox } from "@/components/ui/checkbox";
import { Button } from "@/components/ui/button";
import { Alert } from "@/components/ui/alert";
import { useToastManager } from "@/components/ui/toast";
import { useT } from "@/lib/i18n";
import { ImageBox } from "@/components/merchandise/style-item-desk";

let seq = 0;
const blank = () => ({ key: `n${++seq}`, colour_id: "", colour_type: "", is_gmt_colour: true, colour_group_id: "", dye_method_id: "", fiber_composition_id: "", colour_ref: "", shade_no: "", swatch: false, pantone_no: "", buyer_colour_name: "", remarks: "", image_id: null, image: null });
const editable = (rows) => rows.map((r) => ({ ...blank(), ...Object.fromEntries(Object.entries(r).map(([k, v]) => [k, v ?? (typeof blank()[k] === "string" ? "" : v)])), key: r.id }));

/**
 * Item/Combo/Model → Colour Mapping (AnnotechERP 06/05): Brickland's grid of
 * the colours a style is made in. Add New adds a line; picking a colour fills
 * its type, Pantone, colour group and swatch from Colour Master; Save keeps
 * the whole grid. Labdip and Sample Program arrive with their own modules.
 *
 * @param {{ styleId: string, data: object, save: Function, canManage: boolean }} props
 */
function StyleColourGrid({ styleId, data, save, canManage }) {
  const t = useT("merchandise");
  const m = (k, p) => t(`master.clr.${k}`, p);
  const mi = (k, p) => t(`master.itm.${k}`, p);
  const router = useRouter();
  const toastManager = useToastManager();
  const [rows, setRows] = useState(() => editable(data.rows));
  const [errors, setErrors] = useState(null);
  const [pending, startTransition] = useTransition();
  const colourById = Object.fromEntries(data.colours.map((c) => [c.value, c]));
  const types = data.types.map((v) => ({ value: v, label: m(`type.${v}`) }));
  const pick = (list) => [{ value: "", label: m("select") }, ...list];

  const set = (key, field, value) => setRows((all) => all.map((r) => (r.key === key ? { ...r, [field]: value } : r)));

  function pickColour(key, id) {
    const c = colourById[id];
    setRows((all) =>
      all.map((r) =>
        r.key === key
          ? {
              ...r,
              colour_id: id,
              colour_type: data.types.includes(c?.type) ? c.type : r.colour_type,
              pantone_no: r.pantone_no || c?.pantone || "",
              colour_group_id: r.colour_group_id || c?.group_id || "",
              swatch: r.swatch || Boolean(c?.swatch),
            }
          : r,
      ),
    );
  }

  function submit() {
    startTransition(async () => {
      const result = await save(styleId, {
        rows: rows.map((r) => ({
          colour_id: r.colour_id || null,
          colour_type: r.colour_type || null,
          is_gmt_colour: r.is_gmt_colour,
          colour_group_id: r.colour_group_id || null,
          dye_method_id: r.dye_method_id || null,
          fiber_composition_id: r.fiber_composition_id || null,
          colour_ref: r.colour_ref || null,
          shade_no: r.shade_no || null,
          swatch: r.swatch,
          pantone_no: r.pantone_no || null,
          buyer_colour_name: r.buyer_colour_name || null,
          remarks: r.remarks || null,
          image_id: r.image_id,
        })),
      });
      if (result?.status === "success") {
        setRows(editable(result.rows));
        setErrors(null);
        toastManager.add({ title: m("saved"), type: "success" });
        router.refresh();
      } else {
        setErrors(result?.errors ? [...new Set(Object.values(result.errors).flat())] : [result?.message]);
      }
    });
  }

  const head = ["image", "colour", "type", "gmt", "group", "method", "composition", "ref", "shade", "swatch", "pantone", "buyer_name", "remarks", "action"];

  return (
    <div className="flex flex-col gap-4 p-5">
      {canManage ? (
        <div>
          <Button variant="outline" size="sm" onClick={() => setRows((all) => [...all, blank()])}>
            <Plus /> {mi("add_new")}
          </Button>
        </div>
      ) : null}
      {errors ? <Alert variant="danger">{errors.join(" · ")}</Alert> : null}
      <div className="overflow-x-auto rounded-md border border-border">
        <table className="w-full min-w-max text-sm">
          <thead>
            <tr className="bg-brand text-left text-brand-foreground">
              {head.map((h) => (
                <th key={h} className="px-2 py-2.5 text-[13px] font-semibold whitespace-nowrap">
                  {m(`f.${h}`)}
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
                <tr key={r.key} className="border-b border-border align-middle last:border-0">
                  <td className="px-2 py-1.5">
                    <div className="flex items-center gap-1">
                      <ImageBox compact label={m("f.image")} path={r.image} onUploaded={(f) => setRows((all) => all.map((x) => (x.key === r.key ? { ...x, image_id: f.id, image: f.path } : x)))} onClear={() => setRows((all) => all.map((x) => (x.key === r.key ? { ...x, image_id: null, image: null } : x)))} m={mi} disabled={!canManage} />
                    </div>
                  </td>
                  <td className="w-48 px-2 py-1.5">
                    <Select value={r.colour_id} onValueChange={(v) => v && pickColour(r.key, v)} options={pick(data.colours.map(({ value, label }) => ({ value, label })))} placeholder={m("ph_colour")} />
                  </td>
                  <td className="w-32 px-2 py-1.5">
                    <Select value={r.colour_type} onValueChange={(v) => set(r.key, "colour_type", v ?? "")} options={pick(types)} placeholder={m("select")} />
                  </td>
                  <td className="px-2 py-1.5 text-center">
                    <Checkbox checked={r.is_gmt_colour} onCheckedChange={(c) => set(r.key, "is_gmt_colour", c === true)} aria-label={m("f.gmt")} />
                  </td>
                  <td className="w-40 px-2 py-1.5">
                    <Select value={r.colour_group_id} onValueChange={(v) => set(r.key, "colour_group_id", v ?? "")} options={pick(data.groups)} placeholder={m("select")} />
                  </td>
                  <td className="w-36 px-2 py-1.5">
                    <Select value={r.dye_method_id} onValueChange={(v) => set(r.key, "dye_method_id", v ?? "")} options={pick(data.methods)} placeholder={m("select")} />
                  </td>
                  <td className="w-52 px-2 py-1.5">
                    <Select value={r.fiber_composition_id} onValueChange={(v) => set(r.key, "fiber_composition_id", v ?? "")} options={pick(data.compositions)} placeholder={m("select")} />
                  </td>
                  {["colour_ref", "shade_no"].map((f) => (
                    <td key={f} className="px-2 py-1.5">
                      <Input value={r[f]} onChange={(e) => set(r.key, f, e.target.value)} className="w-28" aria-label={m(`f.${f === "colour_ref" ? "ref" : "shade"}`)} />
                    </td>
                  ))}
                  <td className="px-2 py-1.5 text-center">
                    <Checkbox checked={r.swatch} onCheckedChange={(c) => set(r.key, "swatch", c === true)} aria-label={m("f.swatch")} />
                  </td>
                  {[
                    ["pantone_no", "pantone", "w-28"],
                    ["buyer_colour_name", "buyer_name", "w-40"],
                    ["remarks", "remarks", "w-40"],
                  ].map(([f, label, w]) => (
                    <td key={f} className="px-2 py-1.5">
                      <Input value={r[f]} onChange={(e) => set(r.key, f, e.target.value)} className={w} aria-label={m(`f.${label}`)} />
                    </td>
                  ))}
                  <td className="px-2 py-1.5">
                    {canManage ? (
                      <Button variant="outline" size="sm" onClick={() => setRows((all) => all.filter((x) => x.key !== r.key))} aria-label={m("remove")}>
                        <Trash2 />
                      </Button>
                    ) : null}
                  </td>
                </tr>
              ))
            )}
          </tbody>
        </table>
      </div>
      {canManage ? (
        <div className="flex gap-2">
          <Button onClick={submit} loading={pending}>
            <Save /> {mi("save")}
          </Button>
          <Button variant="outline" onClick={() => setRows(editable(data.rows))} disabled={pending}>
            {mi("cancel")}
          </Button>
        </div>
      ) : null}
    </div>
  );
}

export { StyleColourGrid };
