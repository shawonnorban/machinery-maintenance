"use client";

import { useState, useTransition } from "react";
import { printDocument } from "@/lib/print-document";
import { CheckCircle2, FileSpreadsheet, FileText, Plus, Printer, Save, X } from "lucide-react";
import { cn } from "@/lib/utils";
import { Input } from "@/components/ui/input";
import { Select } from "@/components/ui/select";
import { Checkbox } from "@/components/ui/checkbox";
import { Button } from "@/components/ui/button";
import { Badge } from "@/components/ui/badge";
import { Alert } from "@/components/ui/alert";
import { useToastManager } from "@/components/ui/toast";
import { useT } from "@/lib/i18n";

let seq = 0;
const NUMBERS = ["consumption", "per_gmt", "conversion", "rate", "allow_pct"];
const TEXTS = ["item_name", "cons_type", "cons_unit", "purchase_unit", "remarks", "category", "item_code", "supplier"];
const blank = (category = "") => ({ key: `b${++seq}`, item_name: "", cons_type: "", consumption: "1", cons_unit: "", per_gmt: "1", purchase_unit: "", conversion: "1", apply_addl: false, rate: "", remarks: "", category, item_code: "", allow_pct: "", supplier: "" });
const asForm = (lines) =>
  lines.map((l) => ({
    ...blank(),
    ...Object.fromEntries([...TEXTS, ...NUMBERS].map((f) => [f, l[f] == null ? "" : String(l[f])])),
    apply_addl: Boolean(l.apply_addl),
    key: l.id,
  }));
const num = (v) => (v === "" || v == null || Number.isNaN(Number(v)) ? null : Number(v));
/** Brickland's amount: what the consumption costs at the line's rate. */
const amountOf = (l) => (num(l.consumption) ?? 0) * (num(l.rate) ?? 0);
/** What the order needs of a line, in its purchase unit: per garment, with allowance (and the BOM's addl % when ticked), over the conversion. */
function requirementOf(l, orderQty, addlPct) {
  const perGarment = (num(l.consumption) ?? 0) / (num(l.per_gmt) || 1);
  const extra = 1 + (num(l.allow_pct) ?? 0) / 100 + (l.apply_addl ? (num(addlPct) ?? 0) / 100 : 0);
  return ((num(orderQty) ?? 0) * perGarment * extra) / (num(l.conversion) || 1);
}

/**
 * Item/Combo/Model → BOM (AnnotechERP 06/05): Brickland's bill of materials —
 * the BOM's number, version and approval on top; materials filtered by
 * category on the left; a line per material with its consumption, units,
 * conversion, rate, allowance and supplier. Save keeps a draft (or opens the
 * next version once the last is approved); Approve BOM puts it in use.
 *
 * @param {{ styleId: string, styleNo: string, data: object, save: Function, approve: Function, canManage: boolean }} props
 */
function StyleBom({ styleId, styleNo, data, save, approve, canManage }) {
  const t = useT("merchandise");
  const m = (k, p) => t(`master.bom.${k}`, p);
  const toastManager = useToastManager();
  const [base, setBase] = useState(data);
  const [lines, setLines] = useState(() => asForm(data.lines));
  const [orderQty, setOrderQty] = useState(data.order_qty == null ? "" : String(data.order_qty));
  const [addlPct, setAddlPct] = useState(data.addl_pct == null ? "" : String(data.addl_pct));
  const [category, setCategory] = useState(null);
  const [errors, setErrors] = useState(null);
  const [pending, startTransition] = useTransition();
  const { options } = data;
  const pick = (list, prefix, placeholder = m("select")) => [{ value: "", label: placeholder }, ...list.map((v) => ({ value: v, label: m(`${prefix}.${v}`) }))];
  const consOptions = pick(options.cons_types, "cons");
  const unitOptions = pick(options.units, "unit");
  const categoryOptions = pick(options.categories, "cat", m("ph_category"));
  const inCategory = (l, c) => (c === "OTHERS" ? !l.category || l.category === "OTHERS" : l.category === c);
  const shown = category ? lines.filter((l) => inCategory(l, category)) : lines;

  const set = (key, patch) => setLines((all) => all.map((l) => (l.key === key ? { ...l, ...patch } : l)));

  function load(source) {
    setBase(source);
    setLines(asForm(source.lines));
    setOrderQty(source.order_qty == null ? "" : String(source.order_qty));
    setAddlPct(source.addl_pct == null ? "" : String(source.addl_pct));
  }

  function report(result, title) {
    if (result?.status === "success") {
      load({ ...result.data, options });
      setErrors(null);
      toastManager.add({ title, type: "success" });
    } else {
      setErrors(result?.errors ? [...new Set(Object.values(result.errors).flat())] : [result?.message]);
    }
  }

  function submit() {
    startTransition(async () => {
      const result = await save(styleId, {
        order_qty: num(orderQty),
        addl_pct: num(addlPct),
        lines: lines.map((l) => ({
          ...Object.fromEntries(TEXTS.map((f) => [f, l[f].trim() === "" ? null : l[f]])),
          ...Object.fromEntries(NUMBERS.map((f) => [f, num(l[f])])),
          apply_addl: l.apply_addl,
        })),
      });
      report(result, m("saved"));
    });
  }

  function approveBom() {
    startTransition(async () => report(await approve(styleId), m("approved")));
  }

  const columns = ["item_name", "cons_type", "consumption", "cons_unit", "per_gmt", "purchase_unit", "conversion", "apply_addl", "rate", "remarks", "category", "item_code", "allow_pct", "amount", "supplier"];
  const label = (l, f) => (f === "cons_type" ? (l[f] ? m(`cons.${l[f]}`) : "") : f === "cons_unit" || f === "purchase_unit" ? (l[f] ? m(`unit.${l[f]}`) : "") : f === "category" ? (l[f] ? m(`cat.${l[f]}`) : "") : f === "apply_addl" ? (l[f] ? "✓" : "") : l[f]);
  const printColumns = [...columns, "requirement"];
  const cell = (l, f) => (f === "amount" ? amountOf(l).toFixed(2) : f === "requirement" ? `${requirementOf(l, orderQty, addlPct).toFixed(2)} ${l.purchase_unit ? m(`unit.${l.purchase_unit}`) : ""}`.trim() : label(l, f));
  const head = (f) => (f === "requirement" ? m("requirement") : m(`f.${f}`));
  const total = lines.reduce((sum, l) => sum + amountOf(l), 0);

  // The BOM on paper: every line with its requirement for the order, the amount total under it.
  function printBom() {
    const right = new Set(["consumption", "per_gmt", "conversion", "rate", "allow_pct", "amount", "requirement"]);
    printDocument({
      title: m("print_title"),
      docNo: [base.bom_no, base.version ? `V${base.version}` : null].filter(Boolean).join(" · "),
      orientation: "landscape",
      meta: [
        [m("style"), styleNo],
        [m("order_qty"), String(orderQty || 0)],
        [m("addl_pct"), `${addlPct || 0}%`],
      ],
      sections: [
        {
          columns: printColumns.map((f) => ({ label: head(f), align: right.has(f) ? "right" : f === "apply_addl" ? "center" : undefined, width: f === "item_name" ? "11%" : undefined, nowrap: ["item_code", "requirement"].includes(f) })),
          rows: lines.map((l) => printColumns.map((f) => cell(l, f))),
          foot: [printColumns.map((f, i) => (i === 0 ? m("total") : f === "amount" ? total.toFixed(2) : ""))],
        },
      ],
      signatures: true,
    });
  }

  function excel() {
    const quote = (v) => `"${String(v ?? "").replace(/"/g, '""')}"`;
    const rows = [printColumns.map((f) => quote(head(f))).join(","), ...lines.map((l) => printColumns.map((f) => quote(cell(l, f))).join(","))];
    const blob = new Blob([`﻿${rows.join("\r\n")}`], { type: "text/csv;charset=utf-8" });
    const link = document.createElement("a");
    link.href = URL.createObjectURL(blob);
    link.download = `${base.bom_no ?? "BOM"}-${styleNo}${base.version ? `-V${base.version}` : ""}.csv`;
    link.click();
    URL.revokeObjectURL(link.href);
  }

  const status = base.status;
  const note = !base.in_use ? m("none_approved") : base.in_use === base.version ? m("in_use_self") : m("in_use_other", { in_use: base.in_use, version: base.version });
  const th = "px-2 py-2.5 text-[13px] font-semibold whitespace-nowrap";
  const numberInput = (l, f, w = "w-24") => <Input type="number" min="0" step="any" value={l[f]} onChange={(e) => set(l.key, { [f]: e.target.value })} className={w} disabled={!canManage} aria-label={m(`f.${f}`)} />;
  const selectInput = (l, f, list, w = "w-28") => (
    <div className={w}>
      <Select value={l[f]} onValueChange={(v) => set(l.key, { [f]: v ?? "" })} options={list} placeholder={m("select")} disabled={!canManage} aria-label={m(`f.${f}`)} />
    </div>
  );

  return (
    <div className="flex flex-col gap-4 p-5">
      <div className="flex flex-wrap items-center gap-x-4 gap-y-2 rounded-md border border-border bg-surface-muted px-4 py-3 text-sm">
        <span className="text-foreground-muted">
          {m("bom_id")} <b className="text-foreground">{base.bom_no ?? m("not_saved")}</b>
        </span>
        {base.version ? (
          <span className="text-foreground-muted">
            {m("version")} <b className="text-foreground">V{base.version}</b>
          </span>
        ) : null}
        {status ? <Badge variant={status === "APPROVED" ? "success" : "warning"}>{m(`status_${status}`)}</Badge> : null}
        <span className="text-foreground-muted">{note}</span>
        {canManage ? (
          <Button variant="outline" size="sm" className="ml-auto" onClick={approveBom} disabled={pending || status !== "DRAFT"}>
            <CheckCircle2 /> {m("approve")}
          </Button>
        ) : null}
      </div>
      {errors ? <Alert variant="danger">{errors.join(" · ")}</Alert> : null}

      <div className="flex overflow-hidden rounded-md border border-border">
        <nav className="flex w-52 shrink-0 flex-col gap-0.5 border-r border-border bg-surface-muted p-2">
          <h3 className="px-2 pb-1.5 text-[13px] font-semibold text-foreground">{m("category")}</h3>
          {[null, ...options.categories].map((c) => {
            const count = c ? lines.filter((l) => inCategory(l, c)).length : lines.length;
            return (
              <button
                key={c ?? "all"}
                type="button"
                onClick={() => setCategory(c)}
                className={cn("flex items-center justify-between rounded-md px-2 py-1.5 text-left text-[13px]", category === c ? "bg-brand font-semibold text-brand-foreground" : "text-foreground hover:bg-surface")}
              >
                {c ? m(`cat.${c}`) : m("all")}
                <span className={cn("rounded-full px-1.5 text-[11px]", category === c ? "bg-white/25" : "bg-surface text-foreground-muted")}>{count}</span>
              </button>
            );
          })}
          <span className="mt-2 px-2 text-[12px] text-foreground-muted">{styleNo}</span>
        </nav>
        <div className="min-w-0 flex-1 overflow-x-auto">
          <table className="w-full min-w-max text-sm">
            <thead>
              <tr className="bg-brand text-left text-brand-foreground">
                {columns.map((f) => (
                  <th key={f} className={th}>
                    {m(`f.${f}`)}
                  </th>
                ))}
                <th className={cn(th, "text-right")}>
                  {canManage ? (
                    <Button variant="outline" size="sm" onClick={() => setLines((all) => [...all, blank(category && category !== "OTHERS" ? category : "")])} aria-label={m("add_line")} title={m("add_line")}>
                      <Plus />
                    </Button>
                  ) : null}
                </th>
              </tr>
            </thead>
            <tbody>
              {shown.length === 0 ? (
                <tr>
                  <td colSpan={columns.length + 1} className="px-4 py-14 text-center text-foreground-muted">
                    {category ? m("empty_filter") : m("empty")}
                  </td>
                </tr>
              ) : (
                shown.map((l) => (
                  <tr key={l.key} className="border-b border-border align-middle last:border-0">
                    <td className="px-2 py-1.5">
                      <Input value={l.item_name} onChange={(e) => set(l.key, { item_name: e.target.value })} className="w-56" disabled={!canManage} aria-label={m("f.item_name")} />
                    </td>
                    <td className="px-2 py-1.5">{selectInput(l, "cons_type", consOptions, "w-44")}</td>
                    <td className="px-2 py-1.5">{numberInput(l, "consumption")}</td>
                    <td className="px-2 py-1.5">{selectInput(l, "cons_unit", unitOptions)}</td>
                    <td className="px-2 py-1.5">{numberInput(l, "per_gmt")}</td>
                    <td className="px-2 py-1.5">{selectInput(l, "purchase_unit", unitOptions)}</td>
                    <td className="px-2 py-1.5">{numberInput(l, "conversion")}</td>
                    <td className="px-2 py-1.5 text-center">
                      <Checkbox checked={l.apply_addl} onCheckedChange={(c) => set(l.key, { apply_addl: c === true })} disabled={!canManage} aria-label={m("f.apply_addl")} />
                    </td>
                    <td className="px-2 py-1.5">{numberInput(l, "rate")}</td>
                    <td className="px-2 py-1.5">
                      <Input value={l.remarks} onChange={(e) => set(l.key, { remarks: e.target.value })} className="w-36" disabled={!canManage} aria-label={m("f.remarks")} />
                    </td>
                    <td className="px-2 py-1.5">{selectInput(l, "category", categoryOptions, "w-44")}</td>
                    <td className="px-2 py-1.5">
                      <Input value={l.item_code} onChange={(e) => set(l.key, { item_code: e.target.value })} className="w-28" disabled={!canManage} aria-label={m("f.item_code")} />
                    </td>
                    <td className="px-2 py-1.5">{numberInput(l, "allow_pct", "w-20")}</td>
                    <td className="px-2 py-1.5 text-right font-medium whitespace-nowrap text-foreground tabular-nums">{amountOf(l).toFixed(2)}</td>
                    <td className="px-2 py-1.5">
                      <Input value={l.supplier} onChange={(e) => set(l.key, { supplier: e.target.value })} className="w-40" disabled={!canManage} aria-label={m("f.supplier")} />
                    </td>
                    <td className="px-2 py-1.5 text-right">
                      {canManage ? (
                        <Button variant="outline" size="sm" onClick={() => setLines((all) => all.filter((x) => x.key !== l.key))} aria-label={m("remove", { item: l.item_name })}>
                          <X className="text-danger" />
                        </Button>
                      ) : null}
                    </td>
                  </tr>
                ))
              )}
            </tbody>
          </table>
        </div>
      </div>

      <div className="flex flex-wrap items-center gap-3">
        {canManage ? (
          <Button onClick={submit} loading={pending}>
            <Save /> {t("master.itm.save")}
          </Button>
        ) : null}
        <label className="flex items-center gap-2 text-sm font-medium text-foreground">
          {m("order_qty")}
          <Input type="number" min="0" step="1" value={orderQty} onChange={(e) => setOrderQty(e.target.value)} className="w-28" disabled={!canManage} />
        </label>
        <label className="flex items-center gap-2 text-sm font-medium text-foreground">
          {m("addl_pct")}
          <Input type="number" min="0" max="100" step="any" value={addlPct} onChange={(e) => setAddlPct(e.target.value)} className="w-20" disabled={!canManage} />
        </label>
        <Button variant="outline" onClick={printBom} disabled={lines.length === 0}>
          <Printer /> {m("print")}
        </Button>
        <Button variant="outline" onClick={printBom} disabled={lines.length === 0}>
          <FileText className="text-danger" /> {m("pdf")}
        </Button>
        <Button variant="outline" onClick={excel} disabled={lines.length === 0}>
          <FileSpreadsheet className="text-success" /> {m("excel")}
        </Button>
        {canManage ? (
          <Button variant="outline" onClick={() => load(base)} disabled={pending}>
            {t("master.itm.cancel")}
          </Button>
        ) : null}
      </div>
    </div>
  );
}

export { StyleBom };
