"use client";

import { useState, useTransition } from "react";
import { useRouter } from "next/navigation";
import { RotateCcw, Save } from "lucide-react";
import { Input } from "@/components/ui/input";
import { Checkbox } from "@/components/ui/checkbox";
import { Button } from "@/components/ui/button";
import { useToastManager } from "@/components/ui/toast";
import { useT } from "@/lib/i18n";

const edit = (rows) => rows.map((r) => ({ ...r, water_cost: String(Number(r.water_cost)), wash_cost: String(Number(r.wash_cost)), display_order: String(r.display_order) }));

/**
 * Dye House → Colour Group Rate Update (AnnotechERP 17/01): every shade band in
 * one editable grid — code, name, lab dip, water and wash cost per kilo,
 * order, active — saved as a whole. `action` is the Add colour group button.
 *
 * @param {{ rows: object[], save: Function, canManage: boolean, action?: React.ReactNode }} props
 */
function ColourGroupGrid({ rows: initial, save, canManage, action = null }) {
  const t = useT("dyehouse");
  const router = useRouter();
  const toastManager = useToastManager();
  const [rows, setRows] = useState(() => edit(initial));
  const [pending, startTransition] = useTransition();
  const label = (k, p) => t(`master.colour.${k}`, p);
  const set = (id, field, value) => setRows((current) => current.map((r) => (r.id === id ? { ...r, [field]: value } : r)));

  function submit() {
    startTransition(async () => {
      const groups = rows.map((r) => ({
        id: r.id, code: r.code, name: r.name, labdip_required: r.labdip_required, active: r.active,
        water_cost: r.water_cost === "" ? null : Number(r.water_cost), wash_cost: r.wash_cost === "" ? null : Number(r.wash_cost), display_order: r.display_order === "" ? null : Number(r.display_order),
      }));
      const result = await save(groups);
      if (result?.status === "success") {
        toastManager.add({ title: t("master.saved"), type: "success" });
        router.refresh();
      } else {
        toastManager.add({ title: result?.errors ? Object.values(result.errors).flat()[0] : result?.message, type: "danger" });
      }
    });
  }

  return (
    <div className="rounded-lg border border-border bg-surface">
      <div className="flex flex-wrap items-start justify-between gap-3 px-5 pt-4 pb-3">
        <div>
          <h2 className="text-base font-semibold">{label("title")}</h2>
          <p className="mt-0.5 text-[13px] text-foreground-muted">{label("hint")}</p>
        </div>
        {action}
      </div>
      <div className="overflow-x-auto">
        <table className="w-full min-w-max text-sm">
          <thead>
            <tr className="bg-brand text-left text-brand-foreground">
              <th className="px-3 py-2.5 font-semibold">{t("master.f.color_code")}</th>
              <th className="px-3 py-2.5 font-semibold">{t("master.f.color_name")}</th>
              <th className="px-3 py-2.5 text-center font-semibold">{t("master.f.labdip")}</th>
              <th className="px-3 py-2.5 text-right font-semibold">{t("master.f.water_cost")}</th>
              <th className="px-3 py-2.5 text-right font-semibold">{t("master.f.wash_cost")}</th>
              <th className="px-3 py-2.5 text-right font-semibold">{t("master.f.display_order")}</th>
              <th className="px-3 py-2.5 text-center font-semibold">{t("master.f.is_active")}</th>
            </tr>
          </thead>
          <tbody>
            {rows.map((r) => (
              <tr key={r.id} className="border-b border-border last:border-0">
                <td className="px-3 py-1.5">
                  <Input value={r.code} onChange={(e) => set(r.id, "code", e.target.value)} disabled={!canManage} className="w-32" aria-label={t("master.f.color_code")} />
                </td>
                <td className="px-3 py-1.5">
                  <Input value={r.name} onChange={(e) => set(r.id, "name", e.target.value)} disabled={!canManage} className="w-52" aria-label={t("master.f.color_name")} />
                </td>
                <td className="px-3 py-1.5 text-center">
                  <Checkbox checked={r.labdip_required} onCheckedChange={(c) => set(r.id, "labdip_required", c === true)} disabled={!canManage} aria-label={`${r.name} ${t("master.f.labdip")}`} />
                </td>
                {["water_cost", "wash_cost", "display_order"].map((field) => (
                  <td key={field} className="px-3 py-1.5">
                    <Input type="number" min="0" step={field === "display_order" ? "1" : "0.0001"} value={r[field]} onChange={(e) => set(r.id, field, e.target.value)} disabled={!canManage} className="ml-auto w-24 text-right" aria-label={t(`master.f.${field}`)} />
                  </td>
                ))}
                <td className="px-3 py-1.5 text-center">
                  <Checkbox checked={r.active} onCheckedChange={(c) => set(r.id, "active", c === true)} disabled={!canManage} aria-label={`${r.name} ${t("master.f.is_active")}`} />
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
      <div className="flex flex-wrap items-center gap-2 border-t border-border p-4">
        <p className="mr-auto text-[13px] text-foreground-muted">{label("footer", { count: rows.length })}</p>
        {canManage ? (
          <>
            <Button variant="outline" onClick={() => setRows(edit(initial))} disabled={pending}>
              <RotateCcw /> {label("revert")}
            </Button>
            <Button onClick={submit} loading={pending}>
              <Save /> {t("master.save")}
            </Button>
          </>
        ) : null}
      </div>
    </div>
  );
}

export { ColourGroupGrid };
