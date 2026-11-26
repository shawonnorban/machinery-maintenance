"use client";

import { useMemo, useState } from "react";
import { Calculator, Search } from "lucide-react";
import { cn } from "@/lib/utils";
import { Modal } from "@/components/ui/modal";
import { Select } from "@/components/ui/select";
import { Input } from "@/components/ui/input";
import { useT } from "@/lib/i18n";
import { display, evaluate, num, refs, round } from "@/lib/formula";

/*
 * Accessories Budget → Unit Guide (user, 2026-11-24): how each unit's
 * Cost/Pc is worked out (the company's Unit Cost Rules, Acc Budget Column
 * Setup), so a line is budgeted in the unit its Rate is quoted in. A small
 * calculator on top tries a unit with a Rate, B.Cons and I.Cons — the same
 * rule the worksheet uses — showing the sum with the numbers put in; below,
 * every unit with its name, rule and note (a click tries it).
 */

const pretty = (s) => s.replace(/\s\*\s/g, " × ").replace(/\s\/\s/g, " ÷ ");
const fmt = (v) => (v === null || v === undefined ? "—" : Number(v).toLocaleString("en-US", { maximumFractionDigits: 6 }));

function UnitGuide({ open, onOpenChange, unitRules, rules, unitNames = {}, unitNotes = {}, columns, today, initialUnit = null }) {
  const t = useT("prebudget");
  const units = useMemo(() => Object.keys(unitRules ?? {}).sort(), [unitRules]);
  const labelOf = useMemo(() => {
    const m = Object.fromEntries(columns.map((c) => [c.key, c.label]));
    return (k) => m[k] ?? k;
  }, [columns]);
  const [unit, setUnit] = useState(() => (initialUnit && unitRules?.[initialUnit] ? initialUnit : units.includes("DZN") ? "DZN" : (units[0] ?? "")));
  const [values, setValues] = useState({ rate: "", body_cons: "1", item_cons: "" });
  const [search, setSearch] = useState("");

  const tree = rules[unit] ?? null;
  const reads = useMemo(() => (tree ? [...new Set(refs(tree))] : []), [tree]);
  const numbers = Object.fromEntries(reads.map((k) => [k, values[k] === "" || values[k] === undefined ? null : Number(values[k])]));
  let result = null;
  if (tree) {
    const v = num(evaluate(tree, numbers, today));
    result = v === null || !Number.isFinite(v) ? null : round(v, 6);
  }
  const shown = units.filter((u) => {
    const q = search.trim().toLowerCase();
    return !q || u.toLowerCase().includes(q) || (unitNames[u] ?? "").toLowerCase().includes(q);
  });

  return (
    <Modal open={open} onOpenChange={onOpenChange} title={t("bom.guide.title")} description={t("bom.guide.lead")} className="max-w-3xl">
      {/* The calculator: one unit tried with the numbers of a line. */}
      <section className="rounded-md border border-brand/30 bg-brand/5 p-3 text-[13px]">
        <div className="grid gap-2 sm:grid-cols-[180px_repeat(3,minmax(0,1fr))]">
          <label className="flex flex-col gap-1">
            <span className="text-[11px] font-semibold text-foreground-muted">{t("bom.guide.unit")}</span>
            <Select size="sm" options={units.map((u) => ({ value: u, label: unitNames[u] ? `${u} — ${unitNames[u]}` : u }))} value={unit || null} onValueChange={(v) => setUnit(v ?? "")} aria-label={t("bom.guide.unit")} />
          </label>
          {["rate", "body_cons", "item_cons"].map((k) => (
            <label key={k} className={cn("flex flex-col gap-1", !reads.includes(k) && "opacity-40")}>
              <span className="text-[11px] font-semibold text-foreground-muted">{labelOf(k)}</span>
              <Input type="number" inputMode="decimal" step="any" className="h-7 text-[12px]" value={values[k]} onChange={(e) => setValues((x) => ({ ...x, [k]: e.target.value }))} aria-label={labelOf(k)} disabled={!reads.includes(k)} />
            </label>
          ))}
        </div>
        {tree ? (
          <div className="mt-3 space-y-1">
            <p>
              <span className="font-semibold">{t("bom.guide.cost_pc")} = </span>
              <span className="font-mono">{pretty(display(unitRules[unit], labelOf))}</span>
            </p>
            <p className="flex flex-wrap items-baseline gap-x-2">
              <span className="font-mono text-foreground-muted">= {pretty(display(unitRules[unit], (k) => fmt(numbers[k])).replace(/[[\]]/g, ""))}</span>
              <span className="text-base font-bold text-brand">= {result === null ? "—" : fmt(result)}</span>
            </p>
            {unitNotes[unit] ? <p className="text-[12px] text-foreground-muted">{unitNotes[unit]}</p> : null}
          </div>
        ) : (
          <p className="mt-3 text-danger">{t("bom.guide.no_rule")}</p>
        )}
      </section>

      {/* Every unit and its rule; a click tries it above. */}
      <div className="mt-4 flex items-center gap-2">
        <Search className="size-4 text-foreground-muted" />
        <Input className="h-8 text-[12px]" placeholder={t("bom.guide.search")} value={search} onChange={(e) => setSearch(e.target.value)} aria-label={t("bom.guide.search")} />
        <span className="shrink-0 text-[11px] text-foreground-muted">{t("bom.guide.count", { n: units.length })}</span>
      </div>
      <div className="mt-2 max-h-[45vh] overflow-auto rounded-md border border-border">
        <table className="w-full text-left text-[12px]">
          <thead className="sticky top-0 bg-surface-muted text-[11px] uppercase">
            <tr>
              <th className="px-2 py-1.5">{t("bom.guide.unit")}</th>
              <th className="px-2 py-1.5">{t("bom.guide.name")}</th>
              <th className="px-2 py-1.5">{t("bom.guide.rule")}</th>
              <th className="px-2 py-1.5">{t("bom.guide.note")}</th>
            </tr>
          </thead>
          <tbody>
            {shown.map((u) => (
              <tr key={u} onClick={() => setUnit(u)} className={cn("cursor-pointer border-t border-border hover:bg-brand/5", u === unit && "bg-brand/10 font-semibold")}>
                <td className="px-2 py-1 font-mono">{u}</td>
                <td className="px-2 py-1">{unitNames[u] ?? "—"}</td>
                <td className="px-2 py-1 font-mono">{pretty(display(unitRules[u], labelOf))}</td>
                <td className="px-2 py-1 text-foreground-muted">{unitNotes[u] ?? ""}</td>
              </tr>
            ))}
            {shown.length === 0 ? (
              <tr><td colSpan={4} className="px-2 py-4 text-center text-foreground-muted">{t("bom.guide.none")}</td></tr>
            ) : null}
          </tbody>
        </table>
      </div>
      <p className="mt-2 flex items-center gap-1.5 text-[11px] text-foreground-muted"><Calculator className="size-3.5" /> {t("bom.guide.where")}</p>
    </Modal>
  );
}

export { UnitGuide };
