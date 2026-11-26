"use client";

import { useState, useTransition } from "react";
import { useRouter } from "next/navigation";
import { CalendarRange, RotateCcw, Save, Trash2 } from "lucide-react";
import { Input } from "@/components/ui/input";
import { Select } from "@/components/ui/select";
import { Checkbox } from "@/components/ui/checkbox";
import { Button } from "@/components/ui/button";
import { useToastManager } from "@/components/ui/toast";
import { useT } from "@/lib/i18n";

const asRows = (tasks) => tasks.map((r) => ({ ...r, offset_days: String(r.offset_days), duration_days: String(r.duration_days), sort_order: String(r.sort_order) }));

/**
 * TNA Template Setup → Template Tasks (AnnotechERP 06/01): a template's
 * milestone calendar. Each task falls a number of days from the shipment date
 * (negative before it) and runs for its duration; saved as a whole.
 *
 * @param {{ calendar: { template: object, tasks: object[], available: {value: string, label: string}[] }, save: Function, canManage: boolean }} props
 */
function TnaCalendarEditor({ calendar, save, canManage }) {
  const t = useT("merchandise");
  const router = useRouter();
  const toastManager = useToastManager();
  const [rows, setRows] = useState(() => asRows(calendar.tasks));
  const [pending, startTransition] = useTransition();
  const { template } = calendar;
  const free = calendar.available.filter((a) => !rows.some((r) => r.tna_task_id === a.value));

  const set = (id, field, value) => setRows((current) => current.map((r) => (r.tna_task_id === id ? { ...r, [field]: value } : r)));

  function add(id) {
    const task = calendar.available.find((a) => a.value === id);
    if (!task) return;
    const last = rows.reduce((max, r) => Math.max(max, Number(r.sort_order) || 0), 0);
    setRows((current) => [...current, { tna_task_id: id, name: task.label, group: null, offset_days: String(-template.lead_from), duration_days: "1", sort_order: String(last + 10), active: true }]);
  }

  function submit() {
    startTransition(async () => {
      const tasks = rows.map((r) => ({ tna_task_id: r.tna_task_id, offset_days: Number(r.offset_days), duration_days: Number(r.duration_days), sort_order: Number(r.sort_order), active: r.active }));
      const result = await save(template.id, tasks);
      if (result?.status === "success") {
        setRows(asRows(result.calendar.tasks));
        toastManager.add({ title: t("master.saved"), type: "success" });
        router.refresh();
      } else {
        toastManager.add({ title: result?.errors ? Object.values(result.errors).flat()[0] : result?.message, type: "danger" });
      }
    });
  }

  const sorted = [...rows].sort((a, b) => Number(a.sort_order) - Number(b.sort_order));

  return (
    <div className="rounded-lg border border-border bg-surface">
      <div className="flex flex-wrap items-center gap-3 border-b border-border px-4 py-3">
        <CalendarRange className="size-4 text-foreground-muted" />
        <span className="text-sm font-semibold">{t("master.tna.calendar")}</span>
        <span className="text-[13px] text-foreground-muted">{t("master.tna.calendar_hint", { from: template.lead_from, to: template.lead_to })}</span>
        {canManage && free.length ? (
          <div className="ml-auto w-72">
            <Select value="" onValueChange={(v) => v && add(v)} options={free} placeholder={t("master.tna.add_task")} />
          </div>
        ) : null}
      </div>
      {rows.length === 0 ? (
        <p className="px-4 py-10 text-center text-sm text-foreground-muted">{t("master.tna.calendar_empty")}</p>
      ) : (
        <div className="overflow-x-auto">
          <table className="w-full min-w-max text-sm">
            <thead>
              <tr className="bg-primary text-left text-primary-foreground">
                <th className="px-3 py-2 font-semibold">{t("master.f.task_order")}</th>
                <th className="px-3 py-2 font-semibold">{t("master.f.task_name")}</th>
                <th className="px-3 py-2 font-semibold">{t("master.tna.offset")}</th>
                <th className="px-3 py-2 font-semibold">{t("master.tna.duration")}</th>
                <th className="px-3 py-2 font-semibold">{t("master.tna.window")}</th>
                <th className="px-3 py-2 text-center font-semibold">{t("master.f.active")}</th>
                <th className="w-10 px-3 py-2" />
              </tr>
            </thead>
            <tbody>
              {sorted.map((r) => {
                const start = Number(r.offset_days);
                const end = start + Math.max(Number(r.duration_days) || 1, 1) - 1;
                return (
                  <tr key={r.tna_task_id} className="border-b border-border last:border-0">
                    <td className="px-3 py-1.5">
                      <Input type="number" min="0" value={r.sort_order} onChange={(e) => set(r.tna_task_id, "sort_order", e.target.value)} disabled={!canManage} className="w-20" />
                    </td>
                    <td className="px-3 py-1.5">
                      {r.name}
                      {r.group ? <span className="ml-2 text-xs text-foreground-muted">{r.group}</span> : null}
                    </td>
                    <td className="px-3 py-1.5">
                      <Input type="number" min={-template.lead_to} max="60" value={r.offset_days} onChange={(e) => set(r.tna_task_id, "offset_days", e.target.value)} disabled={!canManage} className="w-24" aria-label={t("master.tna.offset")} />
                    </td>
                    <td className="px-3 py-1.5">
                      <Input type="number" min="1" value={r.duration_days} onChange={(e) => set(r.tna_task_id, "duration_days", e.target.value)} disabled={!canManage} className="w-20" aria-label={t("master.tna.duration")} />
                    </td>
                    <td className="px-3 py-1.5 font-mono text-xs tabular-nums text-foreground-muted">
                      {Number.isFinite(start) ? `D${start >= 0 ? "+" : ""}${start} → D${end >= 0 ? "+" : ""}${end}` : "—"}
                    </td>
                    <td className="px-3 py-1.5 text-center">
                      <Checkbox checked={r.active} onCheckedChange={(c) => set(r.tna_task_id, "active", c === true)} disabled={!canManage} aria-label={r.name} />
                    </td>
                    <td className="px-3 py-1.5">
                      {canManage ? (
                        <Button variant="ghost" size="sm" onClick={() => setRows((current) => current.filter((x) => x.tna_task_id !== r.tna_task_id))} aria-label={t("master.remove")}>
                          <Trash2 />
                        </Button>
                      ) : null}
                    </td>
                  </tr>
                );
              })}
            </tbody>
          </table>
        </div>
      )}
      {canManage ? (
        <div className="flex justify-end gap-2 border-t border-border p-4">
          <Button variant="outline" onClick={() => setRows(asRows(calendar.tasks))} disabled={pending}>
            <RotateCcw /> {t("master.reset")}
          </Button>
          <Button onClick={submit} loading={pending}>
            <Save /> {t("master.save")}
          </Button>
        </div>
      ) : null}
    </div>
  );
}

export { TnaCalendarEditor };
