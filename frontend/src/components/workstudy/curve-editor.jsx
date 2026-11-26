"use client";

import { useState, useTransition } from "react";
import { useRouter } from "next/navigation";
import { ChevronDown, ChevronRight, ListChecks, RotateCcw, Save } from "lucide-react";
import { cn } from "@/lib/utils";
import { Input } from "@/components/ui/input";
import { Button } from "@/components/ui/button";
import { useToastManager } from "@/components/ui/toast";
import { useT } from "@/lib/i18n";

const MIN_DAYS = 10;

/** {band: {day: "efficiency"}} for one fabric class. */
const cellsOf = (points, fabricClass) => {
  const out = {};
  for (const p of points.filter((x) => x.fabric_class === fabricClass)) (out[p.prod_type] ??= {})[p.day] = String(Number(p.efficiency));
  return out;
};

/**
 * Work Study → Learning Curve Model → Planning Learning Curve (AnnotechERP
 * 14/01): one row per fabric class, opened like an accordion; inside, the
 * criticality bands are the rows and Day-1 … Day-10 (more when a curve runs
 * longer) the columns. Type over a cell, blank one to drop that day, Save
 * writes the fabric class's whole curve.
 *
 * @param {{ classes: {key: string, label: string}[], bands: {key: string, label: string}[], points: object[], save: Function, canManage: boolean }} props
 */
function CurveEditor({ classes, bands, points, save, canManage }) {
  const t = useT("workstudy");
  const router = useRouter();
  const toastManager = useToastManager();
  const [open, setOpen] = useState(null);
  const [cells, setCells] = useState({});
  const [dirty, setDirty] = useState(false);
  const [pending, startTransition] = useTransition();
  const count = (fc) => points.filter((p) => p.fabric_class === fc).length;

  function toggle(fc) {
    if (open === fc) return setOpen(null);
    setOpen(fc);
    setCells(cellsOf(points, fc));
    setDirty(false);
  }

  const days = Math.max(MIN_DAYS, ...Object.values(cells).flatMap((row) => Object.keys(row).map(Number)));
  const set = (band, day, value) => {
    setCells((current) => ({ ...current, [band]: { ...(current[band] ?? {}), [day]: value } }));
    setDirty(true);
  };

  function submit() {
    const list = [];
    for (const band of bands) for (const [day, value] of Object.entries(cells[band.key] ?? {})) if (value !== "" && value !== undefined) list.push({ prod_type: band.key, day: Number(day), efficiency: Number(value) });
    startTransition(async () => {
      const result = await save(open, list);
      if (result?.status === "success") {
        setDirty(false);
        toastManager.add({ title: t("master.saved"), type: "success" });
        router.refresh();
      } else {
        toastManager.add({ title: result?.errors ? Object.values(result.errors).flat()[0] : result?.message, type: "danger" });
      }
    });
  }

  return (
    <div className="rounded-lg border border-border bg-surface">
      <div className="flex items-start justify-between gap-3 px-5 pt-4 pb-3">
        <div className="flex gap-3">
          <ListChecks className="mt-0.5 size-5 text-foreground-muted" />
          <div>
            <h2 className="text-base font-semibold">{t("master.curve.tab")}</h2>
            <p className="mt-0.5 text-[13px] text-foreground-muted">{t("master.curve.hint")}</p>
          </div>
        </div>
        <span className="rounded-full bg-surface-muted px-2 py-0.5 text-xs font-semibold">{classes.length}</span>
      </div>
      {classes.map((fc) => (
        <div key={fc.key} className="border-t border-border">
          <button type="button" onClick={() => toggle(fc.key)} className={cn("flex w-full items-center gap-2 px-5 py-3 text-left text-sm hover:bg-surface-muted", open === fc.key && "bg-surface-muted font-semibold")}>
            {open === fc.key ? <ChevronDown className="size-4" /> : <ChevronRight className="size-4" />}
            <span>{fc.label}</span>
            <span className="ml-auto tabular-nums text-foreground-muted">{count(fc.key)}</span>
          </button>
          {open === fc.key ? (
            <>
              <div className="overflow-x-auto">
                <table className="w-full text-sm">
                  <thead>
                    <tr className="bg-brand text-left text-brand-foreground">
                      <th className="px-3 py-2.5 font-semibold">{t("master.curve.prod_type")}</th>
                      {Array.from({ length: days }, (_, i) => (
                        <th key={i} className="px-3 py-2.5 text-center font-semibold">
                          {t("master.curve.day_n", { day: i + 1 })}
                        </th>
                      ))}
                    </tr>
                  </thead>
                  <tbody>
                    {bands.map((band) => (
                      <tr key={band.key} className="border-b border-border last:border-0">
                        <td className="px-3 py-2 whitespace-nowrap">{band.label}</td>
                        {Array.from({ length: days }, (_, i) => (
                          <td key={i} className="px-2 py-1.5">
                            <Input type="number" min="0" max="100" step="0.01" value={cells[band.key]?.[i + 1] ?? ""} onChange={(e) => set(band.key, i + 1, e.target.value)} disabled={!canManage} className="w-full min-w-14 text-center" aria-label={`${band.label} ${t("master.curve.day_n", { day: i + 1 })}`} />
                          </td>
                        ))}
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
              <div className="flex flex-wrap items-center gap-3 border-t border-border p-4">
                <p className="mr-auto text-[13px] text-foreground-muted">{t("master.curve.footer", { count: bands.length })}</p>
                {canManage ? (
                  <>
                    <Button
                      variant="outline"
                      onClick={() => {
                        setCells(cellsOf(points, fc.key));
                        setDirty(false);
                      }}
                      disabled={pending || !dirty}
                    >
                      <RotateCcw /> {t("master.cancel")}
                    </Button>
                    <Button onClick={submit} loading={pending} disabled={!dirty}>
                      <Save /> {t("master.save")}
                    </Button>
                  </>
                ) : null}
              </div>
            </>
          ) : null}
        </div>
      ))}
    </div>
  );
}

export { CurveEditor };
