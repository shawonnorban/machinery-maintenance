"use client";

import { useState, useTransition } from "react";
import { useRouter } from "next/navigation";
import { RotateCcw, Save } from "lucide-react";
import { cn } from "@/lib/utils";
import { Input } from "@/components/ui/input";
import { Button } from "@/components/ui/button";
import { Alert } from "@/components/ui/alert";
import { useToastManager } from "@/components/ui/toast";
import { useT } from "@/lib/i18n";

const asForm = (rows) => Object.fromEntries(rows.map((r) => [r.head, String(r.actual)]));
const num = (v) => (v === "" || Number.isNaN(Number(v)) ? 0 : Number(v));

/**
 * Budget vs Actual Costing → one expense tab for one budget (AnnotechERP
 * 06/09): each cost head of the group with what was budgeted per piece, what
 * production actually spent (typed) and the difference, with the group's
 * totals. Save records the actuals; Revert goes back to what was saved.
 *
 * @param {{ budgetId: string, group: string, data: { currency: string, rows: object[] }, save: Function, canManage: boolean }} props
 */
function BudgetActuals({ budgetId, group, data, save, canManage }) {
  const t = useT("merchandise");
  const m = (k, p) => t(`master.bva.${k}`, p);
  const router = useRouter();
  const toastManager = useToastManager();
  const [base, setBase] = useState(data.rows);
  const [actual, setActual] = useState(() => asForm(data.rows));
  const [errors, setErrors] = useState(null);
  const [pending, startTransition] = useTransition();
  const totalBudget = base.reduce((a, r) => a + r.budget, 0);
  const totalActual = Object.values(actual).reduce((a, v) => a + num(v), 0);

  function submit() {
    startTransition(async () => {
      const result = await save(budgetId, group, { actual: Object.fromEntries(Object.entries(actual).map(([k, v]) => [k, num(v)])) });
      if (result?.status === "success") {
        setBase(result.data.rows);
        setActual(asForm(result.data.rows));
        setErrors(null);
        toastManager.add({ title: m("saved"), type: "success" });
        router.refresh();
      } else {
        setErrors(result?.errors ? [...new Set(Object.values(result.errors).flat())] : [result?.message]);
      }
    });
  }

  const th = "px-4 py-2.5 text-left text-[13px] font-semibold whitespace-nowrap";
  const diffTone = (d) => (d > 0.0001 ? "text-danger" : d < -0.0001 ? "text-success" : "text-foreground-muted");
  return (
    <div className="flex flex-col">
      {errors ? (
        <Alert variant="danger" className="m-4 mb-0">
          {errors.join(" · ")}
        </Alert>
      ) : null}
      <p className="px-5 pt-3 text-[13px] text-foreground-muted">{m("per_piece", { ccy: data.currency })}</p>
      <div className="overflow-x-auto pt-2">
        <table className="w-full text-sm">
          <thead>
            <tr className="bg-brand text-brand-foreground">
              <th className={th}>{m("head")}</th>
              <th className={cn(th, "text-right")}>{m("budget")}</th>
              <th className={th}>{m("actual")}</th>
              <th className={cn(th, "text-right")}>{m("diff")}</th>
            </tr>
          </thead>
          <tbody>
            {base.map((r) => {
              const d = num(actual[r.head]) - r.budget;
              return (
                <tr key={r.head} className="border-b border-border">
                  <td className="px-4 py-2 font-medium text-foreground">{m(`heads.${r.head}`)}</td>
                  <td className="px-4 py-2 text-right tabular-nums">{r.budget.toFixed(4)}</td>
                  <td className="px-4 py-2">
                    <Input type="number" min="0" step="any" value={actual[r.head]} onChange={(e) => setActual((a) => ({ ...a, [r.head]: e.target.value }))} className="w-32" disabled={!canManage} aria-label={`${m("actual")} ${m(`heads.${r.head}`)}`} />
                  </td>
                  <td className={cn("px-4 py-2 text-right tabular-nums", diffTone(d))}>{d.toFixed(4)}</td>
                </tr>
              );
            })}
            <tr className="bg-surface-muted font-semibold">
              <td className="px-4 py-2">{m("total")}</td>
              <td className="px-4 py-2 text-right tabular-nums">{totalBudget.toFixed(4)}</td>
              <td className="px-4 py-2 tabular-nums">{totalActual.toFixed(4)}</td>
              <td className={cn("px-4 py-2 text-right tabular-nums", diffTone(totalActual - totalBudget))}>{(totalActual - totalBudget).toFixed(4)}</td>
            </tr>
          </tbody>
        </table>
      </div>
      {canManage ? (
        <div className="flex justify-end gap-2 border-t border-border px-5 py-3">
          <Button variant="outline" onClick={() => { setActual(asForm(base)); setErrors(null); }} disabled={pending}>
            <RotateCcw /> {m("revert")}
          </Button>
          <Button onClick={submit} loading={pending}>
            <Save /> {m("save")}
          </Button>
        </div>
      ) : null}
    </div>
  );
}

export { BudgetActuals };
