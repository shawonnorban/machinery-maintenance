"use client";

import { useEffect, useMemo, useRef, useState, useTransition } from "react";
import { useRouter } from "next/navigation";
import { Calculator, CheckCircle2, ChevronsLeft, ChevronsRight, ImageIcon, Layers, Package, Plus, Save, Trash2, Wallet, X } from "lucide-react";
import { cn } from "@/lib/utils";
import { Select } from "@/components/ui/select";
import { Button } from "@/components/ui/button";
import { useToastManager } from "@/components/ui/toast";
import { useT } from "@/lib/i18n";
import { FormulaSet, evaluate, parse, num, round, text, blank } from "@/lib/formula";
import { toneOf, TONE, TEXT_TONE, GROUP_TONE } from "@/components/ordersummary/common";
import { BomHead, Pill, STATUS_TONE, SCREEN } from "./accessories";
import { UnitGuide } from "./unit-guide";

/*
 * Pre-Budget → the accessories BOM worksheet (Norban's Accessories Pre-Budget),
 * drawn from the company's columns (Acc Budget Column Setup):
 *
 *  - The budget's styles as chips — tick one to put it on this BOM (one in
 *    another BOM is greyed out). Each ticked style has its cells (the Style
 *    group: SL, Brand, Job No, Style No, Artwork, Order Qty, FOB, Style
 *    Value, spanning its rows), its own accessory rows and a TOTAL row
 *    adding up the columns marked Total in Column Setup.
 *  - A row works out as the server does (BomColumns::work): the style's
 *    cells and what is typed, Unit Rule Cost from the row's unit's rule
 *    (Unit Cost Rules), then every formula — as typed.
 *  - "Copy rows from…" copies another style's rows (Item Order Qty left
 *    blank, so this style's Order Qty is used). Picking an item fills its
 *    unit. Unticking a style keeps its rows until the page is left.
 *  - The bottom bar: Total Order Qty, Order Value, Accessories Cost (Total
 *    Value added up), Avg per Pcs; Save Draft, Save Final Costing.
 */

const VALUE = "value";
const UNIT_COST = "unit_cost";
const NUMBER_TYPES = ["number", "money", "percent", "days"];
const ACTION_WIDTH = 32;
/** A column's width in px, as Column Setup has it. */
const colWidth = (c) => Math.max(Number(c.width) || 130, 24);
const cell = "border border-[#bfbfbf] px-1.5 py-1";
const input = "h-7 w-full rounded border border-border-strong bg-white px-1 disabled:bg-surface-muted";
const errorOf = (res, t) => Object.values(res?.errors ?? {})[0]?.[0] ?? res?.message ?? t("budget.error");
const grouped = (n, max, min) => Number(n).toLocaleString("en-US", { minimumFractionDigits: min, maximumFractionDigits: max });
const money = (v, d = 2) => `${Number(v) < 0 ? "-" : ""}$${grouped(Math.abs(Number(v) || 0), d, d)}`;

/** A worked-out value as its column shows it (places up to the column's, at least 2 for a fraction). */
function fmt(c, v) {
  if (blank(v)) return "";
  const n = num(v);
  if ((NUMBER_TYPES.includes(c.type) || (c.type === "system" && c.decimals !== null && c.decimals !== undefined)) && n !== null && typeof v !== "boolean") {
    const d = c.type === "days" ? 0 : (c.decimals ?? 2);
    const s = grouped(n, d, Math.min(d, 2));
    const suffix = c.suffix ?? (c.type === "percent" ? "%" : "");
    return `${c.type === "money" ? (c.prefix ?? "$") : (c.prefix ?? "")}${s}${suffix === "%" ? "%" : suffix ? ` ${suffix}` : ""}`;
  }
  return Array.isArray(v) ? v.join(", ") : text(v);
}

/** A style's cells (BomSheet::cells). */
const cellsOf = (info, sl) => ({
  sl, brand: info.brand || null, job_no: info.job_no || null, style: info.style || null, artwork: info.artwork_url,
  order_qty: info.order_qty, fob: info.fob, style_value: info.style_value,
});

let seq = 0;
const nextKey = () => `l${++seq}`;

/** `embedded` (Budget Finalized): the BOM alone — no budget head and chips, no save bar. */
function BomWorksheet({ data, act, embedded = false }) {
  const t = useT("prebudget");
  const router = useRouter();
  const toast = useToastManager();
  const [pending, start] = useTransition();
  const bom = data.bom;
  const editable = data.editable;
  const today = data.today;
  const columns = data.columns;
  const groups = data.groups;
  const byStyle = useMemo(() => Object.fromEntries(data.budget_styles.map((s) => [s.style, s])), [data.budget_styles]);
  const elsewhere = useMemo(() => Object.fromEntries(data.summary.styles.filter((x) => x.bom_no && x.bom_no !== bom?.bom_no).map((x) => [x.style, x.bom_no])), [data.summary.styles, bom]);

  // The worksheet: its formulas and unit rules, worked out as the server does.
  const formulas = useMemo(() => new FormulaSet(Object.fromEntries(columns.map((c) => [c.key, { formula: c.formula, type: c.type, decimals: c.decimals }]))), [columns]);
  const rules = useMemo(() => {
    const out = {};
    for (const [u, f] of Object.entries(data.unit_rules ?? {})) {
      try {
        out[u] = parse(f, () => null);
      } catch {
        out[u] = null;
      }
    }
    return out;
  }, [data.unit_rules]);
  const isInput = (c) => !c.style && c.type !== "system" && !formulas.isFormula(c.key);
  const inputKeys = useMemo(() => columns.filter((c) => !c.style && c.type !== "system" && !c.formula).map((c) => c.key), [columns]);
  const shown = columns.filter((c) => !c.hidden && c.access !== "none");
  const styleCols = shown.filter((c) => c.style);
  const lineCols = shown.filter((c) => !c.style);
  const kpiCols = lineCols.filter((c) => c.kpi);
  const firstKpi = kpiCols.length ? lineCols.indexOf(kpiCols[0]) : lineCols.length;
  const unitKey = data.unit_key;
  const work = (typed, cells) => {
    const values = { ...cells, ...typed };
    const u = String(values[unitKey] ?? "").trim().toUpperCase();
    let uc = null;
    if (u && rules[u]) {
      const v = num(evaluate(rules[u], values, today));
      uc = v === null || !Number.isFinite(v) ? null : round(v, 9);
    }
    values[UNIT_COST] = uc;
    return formulas.apply(values, today, true);
  };

  const typedOf = (values) => Object.fromEntries(inputKeys.filter((k) => !blank(values?.[k])).map((k) => [k, values[k]]));
  const blankLine = () => ({ key: nextKey(), values: { nominated: "Nominated", extra_percent: 2 } });

  // The styles on this BOM, in order, and every style's rows (an unticked style keeps its rows).
  const [open, setOpen] = useState(() => (bom ? bom.styles.map((s) => s.style) : []));
  const [lines, setLines] = useState(() => Object.fromEntries((bom?.styles ?? []).map((s) => [s.style, s.lines.length ? s.lines.map((l) => ({ key: nextKey(), values: typedOf(l) })) : [blankLine()]])));
  const [dirty, setDirty] = useState(false);
  const [collapsed, setCollapsed] = useState(false);
  // The Unit Guide: how each unit's Cost/Pc is worked out, to budget a line in the right unit.
  const [guide, setGuide] = useState(false);
  const saving = useRef(false);

  useEffect(() => {
    if (!dirty) return undefined;
    const warn = (e) => {
      if (saving.current) return;
      e.preventDefault();
      e.returnValue = "";
    };
    window.addEventListener("beforeunload", warn);
    return () => window.removeEventListener("beforeunload", warn);
  }, [dirty]);

  const change = (fn) => {
    setDirty(true);
    setLines(fn);
  };
  const toggle = (style, on) => {
    setDirty(true);
    if (on) {
      setOpen((o) => (o.includes(style) ? o : [...o, style]));
      setLines((all) => (all[style] ? all : { ...all, [style]: [blankLine()] }));
    } else setOpen((o) => o.filter((x) => x !== style));
  };
  const setValue = (style, key, col, v) =>
    change((all) => ({
      ...all,
      [style]: all[style].map((l) => {
        if (l.key !== key) return l;
        const values = { ...l.values, [col]: v };
        // An item brings its unit when the row has none.
        if (col === "item" && unitKey && blank(values[unitKey]) && data.item_units?.[v]) values[unitKey] = data.item_units[v];
        return { ...l, values };
      }),
    }));
  const addLine = (style) => change((all) => ({ ...all, [style]: [...all[style], blankLine()] }));
  const removeLine = (style, key) => change((all) => ({ ...all, [style]: all[style].length > 1 ? all[style].filter((l) => l.key !== key) : [blankLine()] }));
  const copyFrom = (style, from) => {
    if (!from || !lines[from]) return;
    change((all) => ({ ...all, [style]: all[from].map((l) => ({ key: nextKey(), values: { ...l.values, item_order_qty: null } })) }));
    toast.add({ title: t("bom.copied", { style: from || "—" }), type: "success" });
  };

  const live = useMemo(() => {
    const styles = open.map((name, i) => {
      const info = byStyle[name] ?? { style: name, order_qty: 0, style_value: 0, fob: null };
      const cells = cellsOf(info, i + 1);
      const rows = (lines[name] ?? []).map((l) => ({ l, w: work(l.values, cells) }));
      const totals = {};
      for (const c of kpiCols) {
        const nums = rows.map((r) => num(r.w[c.key])).filter((x) => x !== null);
        totals[c.key] = nums.length ? (c.kpi === "AVG" ? nums.reduce((a, b) => a + b, 0) / nums.length : nums.reduce((a, b) => a + b, 0)) : null;
      }
      const value = rows.reduce((a, r) => a + (num(r.w[VALUE]) ?? 0), 0);
      return { info, cells, rows, totals, value };
    });
    const qty = styles.reduce((a, x) => a + (x.info.order_qty ?? 0), 0);
    const val = styles.reduce((a, x) => a + (x.info.style_value ?? 0), 0);
    const acc = styles.reduce((a, x) => a + x.value, 0);
    return { styles, qty, val, acc, avg: qty > 0 ? acc / qty : 0 };
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [open, lines, byStyle, formulas, rules]);

  const save = (final) =>
    start(async () => {
      if (open.length === 0) {
        toast.add({ title: t("bom.v.no_style"), type: "danger" });
        return;
      }
      const body = { version: bom?.version ?? null, final, styles: open.map((style) => ({ style, lines: (lines[style] ?? []).map((l) => typedOf(l.values)) })) };
      const res = bom ? await act("saveBom", bom.bom_no, body) : await act("createBom", data.budget_no, body);
      if (res?.status === "success") {
        const no = bom?.bom_no ?? res.data.bom_no;
        toast.add({ title: t(final ? "bom.saved_final" : "bom.saved", { no }), type: "success" });
        saving.current = true;
        setDirty(false);
        if (bom) router.refresh();
        else router.replace(`${SCREEN}?bom=${encodeURIComponent(no)}`);
      } else toast.add({ title: errorOf(res, t), type: "danger" });
    });

  const cancel = () => {
    if (dirty && !window.confirm(t("bom.leave"))) return;
    saving.current = true;
    router.push(`${SCREEN}?budget=${encodeURIComponent(data.budget_no)}`);
  };

  const styleShown = collapsed ? [] : styleCols;
  const tableWidth = [...styleShown, ...lineCols].reduce((a, c) => a + colWidth(c), 0) + (editable ? ACTION_WIDTH : 0);
  const groupSpans = [];
  for (const c of [...styleShown, ...lineCols]) {
    const last = groupSpans[groupSpans.length - 1];
    if (last && last.id === c.group_id) last.span += 1;
    else groupSpans.push({ id: c.group_id, span: 1, group: groups.find((g) => g.id === c.group_id) });
  }

  /** A row's cell: an input for a column this user types in, the worked-out value otherwise. */
  const lineCell = (c, style, l, w, ri) => {
    const v = isInput(c) ? l.values[c.key] : w[c.key];
    const tone = toneOf(c, v);
    const label = `${c.label} ${style} ${ri + 1}`;
    const can = editable && c.access === "edit" && isInput(c);
    const unknownUnit = c.key === unitKey && !blank(v) && !rules[String(v).toUpperCase()];
    let body;
    if (can && c.type === "dropdown") {
      const opts = data.options?.[c.source] ?? [];
      // A list cell with a search box once the list is long (Accessories Item, Supplier — user 2026-11-22).
      const choices = [
        { value: "", label: "—" },
        ...(!blank(v) && !opts.some((o) => o.value === v) ? [{ value: String(v), label: String(v) }] : []),
        ...opts.map((o) => ({ value: String(o.value), label: String(o.label) })),
      ];
      body = (
        <span className="block" title={unknownUnit ? t("bom.no_formula", { unit: v }) : undefined}>
          <Select size="sm" className={unknownUnit ? "border-danger" : undefined} value={v ?? ""} onValueChange={(x) => setValue(style, l.key, c.key, x || null)} options={choices} aria-label={label} />
        </span>
      );
    } else if (can) {
      const numeric = NUMBER_TYPES.includes(c.type);
      body = <input className={cn(input, numeric && "text-right tabular-nums")} type={c.type === "date" ? "date" : "text"} value={Array.isArray(v) ? v.join(", ") : (v ?? "")} placeholder={c.key === "item_order_qty" ? fmt({ type: "number", decimals: 0 }, w.order_qty) : undefined} onChange={(e) => setValue(style, l.key, c.key, e.target.value)} aria-label={label} />;
    } else {
      body = <span className={cn("block whitespace-nowrap", NUMBER_TYPES.includes(c.type) && "text-right tabular-nums", c.key === VALUE && "font-semibold")}>{fmt(c, v)}</span>;
    }
    return <td key={c.key} className={cn(cell, "overflow-hidden", tone && TONE[tone], c.text_tone && TEXT_TONE[c.text_tone], c.bold && "font-bold")}>{body}</td>;
  };

  /** A style's cell, spanning its rows. */
  const styleCell = (c, x, span) => {
    const v = x.cells[c.key];
    if (c.key === "artwork") {
      return (
        <td key={c.key} rowSpan={span} className={cn(cell, "text-center")}>
          {v ? (
            <a href={v} target="_blank" rel="noreferrer" className="mx-auto block size-11">
              {/* eslint-disable-next-line @next/next/no-img-element */}
              <img src={v} alt="" className="size-full rounded border border-border object-cover" />
            </a>
          ) : <span className="mx-auto flex size-11 items-center justify-center rounded border border-dashed border-border-strong bg-surface-muted text-foreground-muted"><ImageIcon className="size-4" /></span>}
        </td>
      );
    }
    const tone = toneOf(c, v);
    return (
      <td key={c.key} rowSpan={span} className={cn(cell, "text-center", c.key === "style" && "font-bold", c.key === "style_value" && "text-brand", tone && TONE[tone], c.text_tone && TEXT_TONE[c.text_tone], c.bold && "font-bold")}>
        {c.vertical ? <span className="inline-block rotate-180 whitespace-nowrap [writing-mode:vertical-rl]">{fmt(c, v) || "—"}</span> : <span className="tabular-nums">{fmt(c, v)}</span>}
      </td>
    );
  };

  return (
    <div className="flex flex-col gap-3 text-[12px]">
      {embedded ? null : (
      <BomHead data={data} t={t} back={`${SCREEN}?budget=${encodeURIComponent(data.budget_no)}`} print={bom ? { kind: "bom", extra: { bom: bom.bom_no } } : null}>
          <div className="text-[11px] text-foreground-muted">
            {bom ? <span className="mr-2 font-mono font-semibold text-foreground">{bom.bom_no}</span> : null}
            {bom ? <Pill tone={STATUS_TONE[bom.status]}>{t(`bom.status.${bom.status}`)}</Pill> : null} {t("bom.styles_head", { done: data.summary.budgeted, n: data.summary.count })}
          </div>
          <div className="mt-1 flex flex-wrap gap-1.5">
            {data.budget_styles.map((s) => {
              const other = elsewhere[s.style];
              const on = open.includes(s.style);
              return (
                <label key={s.style} title={other ? t("bom.in_other", { no: other }) : ""} className={cn("inline-flex items-center gap-1.5 rounded-full border px-2.5 py-0.5 text-[12px]", other ? "cursor-not-allowed border-border bg-surface-muted text-foreground-muted" : on ? "cursor-pointer border-success bg-success-subtle font-semibold text-success" : "cursor-pointer border-border")}>
                  <input type="checkbox" checked={on || !!other} disabled={!!other || !editable} onChange={(e) => toggle(s.style, e.target.checked)} aria-label={s.style || "—"} />
                  {s.style || "—"}
                  {other ? <span className="text-[10px]">({other.split("-").pop()})</span> : null}
                </label>
              );
            })}
          </div>
        </BomHead>
      )}
      {!editable && data.status !== "DRAFT" ? <p className="rounded border border-warning bg-warning-subtle px-3 py-2 text-warning">{t("bom.locked")}</p> : null}

      <div className="flex items-center justify-end gap-2">
        <span className="text-[11px] text-foreground-muted">{t("bom.guide.hint")}</span>
        <Button type="button" size="sm" variant="outline" onClick={() => setGuide(true)}>
          <Calculator /> {t("bom.guide.button")}
        </Button>
      </div>
      {guide ? (
        <UnitGuide open={guide} onOpenChange={setGuide} unitRules={data.unit_rules ?? {}} rules={rules} unitNames={data.unit_names ?? {}} unitNotes={data.unit_notes ?? {}} columns={columns} today={today} />
      ) : null}

      <section className="overflow-x-auto rounded-lg border border-border bg-surface">
        {/* Fixed layout: every column exactly its Column Setup width (the table does not stretch to fill the page). */}
        <table className="border-separate border-spacing-0" style={{ tableLayout: "fixed", width: tableWidth }}>
          <colgroup>
            {[...styleShown, ...lineCols].map((c) => <col key={c.key} style={{ width: colWidth(c) }} />)}
            {editable ? <col style={{ width: ACTION_WIDTH }} /> : null}
          </colgroup>
          <thead className="text-[10px] font-bold uppercase">
            <tr>
              {groupSpans.map((g, i) => (
                <th key={`${g.id}${i}`} colSpan={g.span} className={cn(cell, "text-left", g.group?.tone ? GROUP_TONE[g.group.tone] : "bg-[#d9d9d9]")}>
                  {i === 0 ? (
                    <span className="flex items-center gap-1">
                      <button type="button" onClick={() => setCollapsed((v) => !v)} className="rounded border border-border-strong bg-white p-0.5" title={t(collapsed ? "bom.show_style_cols" : "bom.hide_style_cols")} aria-label={t(collapsed ? "bom.show_style_cols" : "bom.hide_style_cols")}>
                        {collapsed ? <ChevronsRight className="size-3" /> : <ChevronsLeft className="size-3" />}
                      </button>
                      {g.group?.name}
                    </span>
                  ) : g.group?.name}
                </th>
              ))}
              {editable ? <th className={cn(cell, "w-8 bg-[#d9d9d9]")} rowSpan={2} /> : null}
            </tr>
            <tr className="bg-[#f2f2f2]">
              {[...styleShown, ...lineCols].map((c) => (
                <th key={c.key} className={cn(cell, "break-words", c.vertical ? "h-20" : "")} title={c.formula ? t("bom.formula_col") : undefined}>
                  {c.vertical ? <span className="inline-block rotate-180 whitespace-nowrap [writing-mode:vertical-rl]">{c.label}</span> : c.label}
                </th>
              ))}
            </tr>
          </thead>
          {live.styles.length === 0 ? (
            <tbody>
              <tr><td colSpan={styleShown.length + lineCols.length + 1} className="px-4 py-10 text-center text-foreground-muted">{t("bom.no_styles")}</td></tr>
            </tbody>
          ) : live.styles.map((x) => {
            const name = x.info.style;
            const span = x.rows.length + 1;
            return (
              <tbody key={name}>
                {x.rows.map(({ l, w }, ri) => (
                  <tr key={l.key} className="h-9">
                    {ri === 0 ? styleShown.map((c) => styleCell(c, x, span)) : null}
                    {lineCols.map((c) => lineCell(c, name, l, w, ri))}
                    {editable ? (
                      <td className={cn(cell, "text-center")}>
                        <button type="button" onClick={() => removeLine(name, l.key)} className="text-danger" aria-label={`${t("bom.remove_row")} ${name} ${ri + 1}`}><X className="size-4" /></button>
                      </td>
                    ) : null}
                  </tr>
                ))}
                <tr className="bg-[#fff2cc] font-bold">
                  {firstKpi > 0 ? (
                    <td className={cell} colSpan={firstKpi}>
                      <span className="flex flex-wrap items-center gap-2">
                        {editable ? (
                          <>
                            <Button type="button" size="sm" variant="outline" className="h-6 px-2 text-[11px]" onClick={() => addLine(name)}><Plus /> {t("bom.add_row")}</Button>
                            {open.length > 1 ? (
                              <select className="h-6 rounded border border-border-strong bg-white px-1 text-[11px] font-normal" value="" onChange={(e) => copyFrom(name, e.target.value)} aria-label={`${t("bom.copy_from")} ${name}`}>
                                <option value="">{t("bom.copy_from")}</option>
                                {open.filter((o) => o !== name).map((o) => <option key={o} value={o}>{o || "—"}</option>)}
                              </select>
                            ) : null}
                          </>
                        ) : null}
                        <span className="ml-auto">{t("bom.style_total", { style: collapsed ? name : "" })}</span>
                      </span>
                    </td>
                  ) : null}
                  {lineCols.slice(firstKpi).map((c, i) => (
                    <td key={c.key} className={cn(cell, "text-right tabular-nums")}>
                      {c.kpi ? fmt(c, x.totals[c.key]) : i === lineCols.length - firstKpi - 1 && editable ? (
                        <Button type="button" size="sm" variant="outline" className="h-6 px-2 text-[11px] text-danger" onClick={() => toggle(name, false)}><Trash2 /> {t("bom.remove_style")}</Button>
                      ) : null}
                    </td>
                  ))}
                  {editable ? <td className={cell} /> : null}
                </tr>
              </tbody>
            );
          })}
        </table>
      </section>
      <p className="text-[11px] text-foreground-muted">{t("bom.rules_hint")}</p>

      {embedded ? null : (
      // The BOM's totals and its save buttons, kept in view at the foot of the screen (redesigned, user 2026-11-20):
      // the app's own light card — four figure tiles, the accessories' share of the order value under its cost, then
      // the unsaved note and Cancel / Save Draft / Save Final Costing.
      <div className="sticky bottom-3 z-30 rounded-xl border border-border bg-surface/95 shadow-[0_-4px_16px_rgba(15,23,42,0.08)] backdrop-blur">
        <div className="flex flex-wrap items-stretch gap-3 p-3">
          <div className="grid flex-1 grid-cols-2 gap-2 sm:grid-cols-4">
            {[
              ["total_order_qty", grouped(live.qty, 0, 0), Package, "text-brand bg-brand/10"],
              ["total_order_value", money(live.val), Wallet, "text-info bg-info-subtle"],
              ["total_acc", money(live.acc), Layers, "text-success bg-success-subtle", live.val > 0 ? `${grouped((live.acc / live.val) * 100, 2, 2)}% ${t("bom.of_value")}` : null],
              ["avg_cost", money(live.avg, 4), Calculator, "text-warning bg-warning-subtle"],
            ].map(([k, v, Icon, tone, sub]) => (
              <div key={k} className="flex items-center gap-3 rounded-lg border border-border bg-surface-muted/50 px-3 py-2">
                <span className={cn("flex size-9 shrink-0 items-center justify-center rounded-lg", tone)}><Icon className="size-4.5" /></span>
                <div className="min-w-0">
                  <div className="truncate text-[11px] font-medium text-foreground-muted">{t(`bom.${k}`)}</div>
                  <div className="text-[15px] font-semibold text-foreground tabular-nums">{v}</div>
                  {sub ? <div className="text-[10px] text-foreground-muted tabular-nums">{sub}</div> : null}
                </div>
              </div>
            ))}
          </div>
          <div className="flex flex-wrap items-center gap-2 border-border sm:border-l sm:pl-3">
            {dirty ? (
              <span className="inline-flex items-center gap-1.5 rounded-full bg-warning-subtle px-2.5 py-1 text-[11px] font-semibold text-warning">
                <span className="size-1.5 rounded-full bg-warning" /> {t("bom.unsaved")}
              </span>
            ) : null}
            <Button type="button" variant="ghost" onClick={cancel}>{t("bom.cancel")}</Button>
            {editable ? (
              <>
                <Button type="button" variant="outline" onClick={() => save(false)} disabled={pending}><Save /> {t("bom.save_draft")}</Button>
                <Button type="button" onClick={() => save(true)} disabled={pending}><CheckCircle2 /> {t("bom.save_final")}</Button>
              </>
            ) : null}
          </div>
        </div>
      </div>
      )}
    </div>
  );
}

export { BomWorksheet };
