"use client";

import { useState, useTransition } from "react";
import { useRouter } from "next/navigation";
import { Plus, Save, Trash2 } from "lucide-react";
import { ColumnSetup } from "@/components/ordersummary/column-setup";
import { Button } from "@/components/ui/button";
import { useToastManager } from "@/components/ui/toast";
import { useT } from "@/lib/i18n";

/*
 * Pre-Budget → Master Data → the two Column Setups (AnnotechERP 33/34):
 *
 *  - Requisition Column Setup: Order Summary's Column Setup on the
 *    requisition sheet, where each column also says whether it is a Knit
 *    column, a Woven column or both.
 *  - Acc Budget Column Setup (user, 2026-11-19): the same Column Setup on the
 *    accessories BOM worksheet — every column, the calculation ones too —
 *    and below it the Unit Cost Rules: for each unit the formula a row's
 *    Unit Rule Cost (what Cost/Pc reads) is worked out with, the company's
 *    own rule (DZN = [Rate] / 12 * [B.Cons] …).
 */
function RequisitionColumnSetup({ data, act }) {
  const t = useT("prebudget");
  const appliesTo = { label: t("req.applies"), both: t("req.applies_both"), KNIT: t("req.applies_knit"), WOVEN: t("req.applies_woven") };

  return <ColumnSetup data={data} act={act} appliesTo={appliesTo} />;
}

function AccBudgetColumnSetup({ data, act }) {
  // The same Column Setup calls, sent to the BOM sheet (pbAction's bom_* calls).
  const bomAct = (name, ...args) => act(`bom_${name}`, ...args);

  return (
    <div className="flex flex-col gap-4">
      <ColumnSetup data={data} act={bomAct} />
      {/* Saved rules come back from the server: drawn afresh with them. */}
      <UnitRules key={data.unit_rules.map((r) => `${r.uom}=${r.formula_text}|${r.note ?? ""}`).join(";")} data={data} act={act} />
    </div>
  );
}

const errorOf = (res, t) => Object.values(res?.errors ?? {})[0]?.[0] ?? res?.message ?? t("budget.error");

function UnitRules({ data, act }) {
  const t = useT("prebudget");
  const router = useRouter();
  const toast = useToastManager();
  const [pending, start] = useTransition();
  const can = data.can_manage;
  const [rows, setRows] = useState(() => Object.fromEntries(data.unit_rules.map((r) => [r.uom, { formula: r.formula_text ?? "", note: r.note ?? "" }])));
  const [adding, setAdding] = useState({ uom: "", formula: "", note: "" });
  const used = new Set(data.unit_rules.map((r) => r.uom));
  // The columns a rule may read: typed and style columns (not a formula, not Unit Rule Cost).
  const readable = data.columns.filter((c) => !c.formula && c.key !== "unit_cost" && !["image"].includes(c.type) && c.key !== "artwork").map((c) => c.label);

  const run = (promise, done) =>
    start(async () => {
      const res = await promise;
      if (res?.status === "success") {
        toast.add({ title: done, type: "success" });
        router.refresh();
      } else toast.add({ title: errorOf(res, t), type: "danger" });
    });

  const input = "h-8 w-full rounded border border-border-strong bg-white px-2 font-mono text-[12px] disabled:bg-surface-muted";
  return (
    <section className="rounded-lg border border-border bg-surface text-[13px]">
      <div className="border-b border-border px-4 py-3">
        <h2 className="font-semibold">{t("bom.rules.title")}</h2>
        <p className="mt-0.5 text-[12px] text-foreground-muted">{t("bom.rules.hint")}</p>
        <p className="mt-1 flex flex-wrap gap-1 text-[11px]">
          {readable.map((l) => <code key={l} className="rounded bg-surface-muted px-1.5 py-0.5">[{l}]</code>)}
        </p>
      </div>
      <div className="overflow-x-auto">
        <table className="w-full">
          <thead className="bg-surface-muted text-left text-[11px] font-semibold tracking-wide text-foreground-muted uppercase">
            <tr>
              <th className="w-28 px-3 py-2">{t("bom.rules.unit")}</th>
              <th className="px-3 py-2">{t("bom.rules.formula")}</th>
              <th className="w-56 px-3 py-2">{t("bom.rules.note")}</th>
              <th className="w-48 px-3 py-2">{t("bom.rules.reads")}</th>
              {can ? <th className="w-28 px-3 py-2" /> : null}
            </tr>
          </thead>
          <tbody>
            {data.unit_rules.map((r) => {
              const row = rows[r.uom] ?? { formula: "", note: "" };
              const changed = row.formula !== (r.formula_text ?? "") || row.note !== (r.note ?? "");
              return (
                <tr key={r.uom} className="border-t border-border">
                  <td className="px-3 py-1.5 font-mono font-semibold">{r.uom}</td>
                  <td className="px-3 py-1.5"><input className={input} disabled={!can} value={row.formula} onChange={(e) => setRows((all) => ({ ...all, [r.uom]: { ...row, formula: e.target.value } }))} aria-label={`${t("bom.rules.formula")} ${r.uom}`} /></td>
                  <td className="px-3 py-1.5"><input className={input.replace("font-mono ", "")} disabled={!can} value={row.note} onChange={(e) => setRows((all) => ({ ...all, [r.uom]: { ...row, note: e.target.value } }))} aria-label={`${t("bom.rules.note")} ${r.uom}`} /></td>
                  <td className="px-3 py-1.5 text-[12px] text-foreground-muted">{r.reads.join(", ")}</td>
                  {can ? (
                    <td className="px-3 py-1.5 text-right whitespace-nowrap">
                      <Button type="button" size="sm" variant={changed ? "default" : "outline"} disabled={pending || !changed} onClick={() => run(act("saveUnitRule", r.uom, { formula: row.formula, note: row.note || null }), t("bom.rules.saved", { unit: r.uom }))} aria-label={`${t("bom.rules.save")} ${r.uom}`}><Save /></Button>
                      <Button type="button" size="sm" variant="ghost" className="ml-1 text-danger" disabled={pending} onClick={() => window.confirm(t("bom.rules.delete_confirm", { unit: r.uom })) && run(act("deleteUnitRule", r.uom), t("bom.rules.deleted", { unit: r.uom }))} aria-label={`${t("bom.rules.delete")} ${r.uom}`}><Trash2 /></Button>
                    </td>
                  ) : null}
                </tr>
              );
            })}
            {can ? (
              <tr className="border-t border-border bg-surface-muted/50">
                <td className="px-3 py-1.5">
                  <select className="h-8 w-full rounded border border-border-strong bg-white px-1 text-[12px]" value={adding.uom} onChange={(e) => setAdding({ ...adding, uom: e.target.value })} aria-label={t("bom.rules.new_unit")}>
                    <option value="">{t("bom.rules.new_unit")}</option>
                    {data.units.filter((u) => !used.has(u)).map((u) => <option key={u} value={u}>{u}</option>)}
                  </select>
                </td>
                <td className="px-3 py-1.5"><input className={input} value={adding.formula} placeholder="[Rate] / [I.Cons] * [B.Cons]" onChange={(e) => setAdding({ ...adding, formula: e.target.value })} aria-label={t("bom.rules.new_formula")} /></td>
                <td className="px-3 py-1.5"><input className={input.replace("font-mono ", "")} value={adding.note} onChange={(e) => setAdding({ ...adding, note: e.target.value })} aria-label={t("bom.rules.new_note")} /></td>
                <td />
                <td className="px-3 py-1.5 text-right">
                  <Button type="button" size="sm" disabled={pending || !adding.uom || !adding.formula.trim()} onClick={() => { run(act("saveUnitRule", adding.uom, { formula: adding.formula, note: adding.note || null }), t("bom.rules.saved", { unit: adding.uom })); setAdding({ uom: "", formula: "", note: "" }); }}><Plus /> {t("bom.rules.add")}</Button>
                </td>
              </tr>
            ) : null}
          </tbody>
        </table>
      </div>
    </section>
  );
}

export { RequisitionColumnSetup, AccBudgetColumnSetup };
