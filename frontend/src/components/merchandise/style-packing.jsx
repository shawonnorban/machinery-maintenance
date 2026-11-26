"use client";

import { useState, useTransition } from "react";
import { Pencil, Save, Trash2 } from "lucide-react";
import { cn } from "@/lib/utils";
import { Input } from "@/components/ui/input";
import { Select } from "@/components/ui/select";
import { Checkbox } from "@/components/ui/checkbox";
import { Button } from "@/components/ui/button";
import { Alert } from "@/components/ui/alert";
import { useToastManager } from "@/components/ui/toast";
import { useT } from "@/lib/i18n";

let seq = 0;
const blankForm = (o) => ({ key: null, sku_ref: "N/A", pack_type: "SINGLE", pcs_per_pack: "1", order_id: o.orders.length === 1 ? o.orders[0].value : "", item_id: o.items.length === 1 ? o.items[0].value : "", all_colours: false, colour_id: "" });
const asRows = (rows) => rows.map((r) => ({ ...r, key: r.id, colour_id: r.colour_id ?? "", pcs_per_pack: String(r.pcs_per_pack) }));

function Row({ label, required = false, children }) {
  return (
    <div className="grid items-center gap-3 sm:grid-cols-[7rem_1fr]">
      <span className="text-[13px] font-semibold text-foreground sm:text-right">
        {label}
        {required ? <span className="text-danger">*</span> : null}
      </span>
      <div className="min-w-0">{children}</div>
    </div>
  );
}

/**
 * Item/Combo/Model → Packing Instruction (AnnotechERP 06/05): Brickland's
 * list on the left — SKU, order, item, colour, pieces per pack and the packs
 * the order needs, with their total — filtered by work style / job, and the
 * entry on the right: SKU, a single-piece or multi-piece (uniform or random
 * ratio) pack and its pieces, the order, garment item and colour (or all).
 * Add puts a line on the list; Save keeps the list.
 *
 * @param {{ styleId: string, data: object, save: Function, canManage: boolean }} props
 */
function StylePacking({ styleId, data, save, canManage }) {
  const t = useT("merchandise");
  const m = (k, p) => t(`master.pck.${k}`, p);
  const toastManager = useToastManager();
  const o = data.options;
  const [base, setBase] = useState(data.rows);
  const [rows, setRows] = useState(() => asRows(data.rows));
  const [form, setForm] = useState(() => blankForm(o));
  const [filter, setFilter] = useState("");
  const [errors, setErrors] = useState(null);
  const [pending, startTransition] = useTransition();
  const set = (field) => (value) => setForm((f) => ({ ...f, [field]: value }));
  const label = (list, id) => list.find((x) => x.value === id)?.label ?? "";
  const shown = filter ? rows.filter((r) => r.order_id === filter) : rows;
  const total = shown.reduce((sum, r) => sum + (Number(r.qty) || 0), 0);
  const pick = (list, placeholder) => [{ value: "", label: placeholder }, ...list.map(({ value, label: l }) => ({ value, label: l }))];

  function add() {
    if (!form.sku_ref.trim() || !form.order_id || !form.item_id || (!form.all_colours && !form.colour_id)) {
      setErrors([m("need")]);
      return;
    }
    const order = o.orders.find((x) => x.value === form.order_id);
    const row = {
      ...form,
      key: form.key ?? `p${++seq}`,
      sku_ref: form.sku_ref.trim(),
      pcs_per_pack: form.pack_type === "SINGLE" ? "1" : form.pcs_per_pack,
      order: order?.label,
      job: order?.job,
      item: label(o.items, form.item_id),
      colour: form.all_colours ? null : label(o.colours, form.colour_id),
      colour_id: form.all_colours ? "" : form.colour_id,
      // The packs are worked out on Save; until then the line shows none.
      qty: form.key ? (rows.find((r) => r.key === form.key)?.qty ?? 0) : 0,
    };
    setRows((all) => (form.key ? all.map((r) => (r.key === form.key ? row : r)) : [...all, row]));
    setForm(blankForm(o));
    setErrors(null);
  }

  function submit() {
    startTransition(async () => {
      const result = await save(styleId, {
        rows: rows.map((r) => ({ sku_ref: r.sku_ref, pack_type: r.pack_type, pcs_per_pack: Number(r.pcs_per_pack) || 1, order_id: r.order_id, item_id: r.item_id, all_colours: r.all_colours, colour_id: r.all_colours ? null : r.colour_id || null })),
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

  const th = "px-3 py-2.5 text-left text-[13px] font-semibold whitespace-nowrap";

  return (
    <div className="grid gap-5 p-5 xl:grid-cols-[1fr_1fr]">
      <section className="flex min-w-0 flex-col gap-3">
        <Select value={filter} onValueChange={(v) => setFilter(v ?? "")} options={[{ value: "", label: m("ph_work_style") }, ...o.orders.map((x) => ({ value: x.value, label: `${x.job} · ${x.label}` }))]} placeholder={m("ph_work_style")} aria-label={m("work_style")} />
        <div className="overflow-x-auto rounded-md border border-border">
          <table className="w-full min-w-max text-sm">
            <thead>
              <tr className="bg-brand text-brand-foreground">
                {["sku", "order", "item", "colour", "pcs_per_pack", "qty"].map((k) => (
                  <th key={k} className={cn(th, (k === "pcs_per_pack" || k === "qty") && "text-right")}>
                    {m(`col.${k}`)}
                  </th>
                ))}
                <th className={th} aria-label="-" />
              </tr>
            </thead>
            <tbody>
              {shown.length === 0 ? (
                <tr>
                  <td colSpan={7} className="px-4 py-14 text-center text-foreground-muted">
                    {m("empty")}
                  </td>
                </tr>
              ) : (
                shown.map((r) => (
                  <tr key={r.key} className={cn("border-b border-border align-middle last:border-0", form.key === r.key && "bg-brand-subtle")}>
                    <td className="px-3 py-2.5 font-medium text-foreground">{r.sku_ref}</td>
                    <td className="px-3 py-2.5">{r.order}</td>
                    <td className="px-3 py-2.5">{r.item}</td>
                    <td className="px-3 py-2.5">{r.all_colours ? m("all") : r.colour}</td>
                    <td className="px-3 py-2.5 text-right tabular-nums">{r.pcs_per_pack}</td>
                    <td className="px-3 py-2.5 text-right tabular-nums">{Number(r.qty).toLocaleString("en-US")}</td>
                    <td className="px-3 py-2.5 text-right">
                      {canManage ? (
                        <span className="flex justify-end gap-1">
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
        <div className="flex items-center justify-end gap-6 rounded-md border border-border px-4 py-2.5 text-sm font-semibold">
          <span>{m("total")}</span>
          <span className="tabular-nums">{total.toLocaleString("en-US")}</span>
        </div>
        <p className="text-xs text-foreground-muted">{m("qty_hint")}</p>
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
          <legend className="px-1 text-[13px] font-semibold text-brand italic">{m("work_style")}</legend>
          {o.orders.length === 0 ? <Alert variant="warning">{m("no_orders")}</Alert> : null}
          <div className="grid gap-3 sm:grid-cols-[1fr_9rem]">
            <Row label={m("f.sku")} required>
              <Input value={form.sku_ref} onChange={(e) => set("sku_ref")(e.target.value)} />
            </Row>
            <label className="flex flex-col gap-1.5 text-[13px] font-semibold text-foreground">
              {m("f.pcs_per_pack")}
              <Input type="number" min="1" step="1" value={form.pack_type === "SINGLE" ? "1" : form.pcs_per_pack} onChange={(e) => set("pcs_per_pack")(e.target.value)} disabled={form.pack_type === "SINGLE"} />
            </label>
          </div>
          <div className="flex flex-col gap-1.5 sm:pl-[7.75rem]" role="radiogroup">
            {o.pack_types.map((type) => (
              <label key={type} className="flex items-center gap-2 text-[13px] font-semibold text-foreground">
                <input type="radio" name={`pack-${styleId}`} value={type} checked={form.pack_type === type} onChange={() => set("pack_type")(type)} className="size-4 accent-brand" />
                {m(`type.${type}`)}
              </label>
            ))}
          </div>
          <Row label={m("f.order")} required>
            <Select value={form.order_id} onValueChange={(v) => set("order_id")(v ?? "")} options={pick(o.orders, m("ph_search"))} placeholder={m("ph_search")} aria-label={m("f.order")} />
          </Row>
          <Row label={m("f.item")} required>
            <Select value={form.item_id} onValueChange={(v) => set("item_id")(v ?? "")} options={pick(o.items, m("ph_search"))} placeholder={m("ph_search")} aria-label={m("f.item")} />
          </Row>
          <Row label={m("f.colour")} required>
            <div className="flex items-center gap-3">
              <div className="min-w-0 flex-1">
                <Select value={form.colour_id} onValueChange={(v) => set("colour_id")(v ?? "")} options={pick(o.colours, m("ph_search"))} placeholder={m("ph_search")} disabled={form.all_colours} aria-label={m("f.colour")} />
              </div>
              <label className="flex items-center gap-2 text-[13px] font-semibold whitespace-nowrap text-foreground">
                <Checkbox checked={form.all_colours} onCheckedChange={(c) => setForm((f) => ({ ...f, all_colours: c === true, colour_id: c === true ? "" : f.colour_id }))} />
                {m("f.all_col")}
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

export { StylePacking };
