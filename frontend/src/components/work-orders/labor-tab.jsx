"use client";

import { useActionState, useEffect, useMemo, useTransition } from "react";
import { useRouter } from "next/navigation";
import { FormField } from "@/components/ui/form-field";
import { Select } from "@/components/ui/select";
import { Input } from "@/components/ui/input";
import { DateTimeField } from "@/components/ui/date-time-field";
import { Button } from "@/components/ui/button";
import { useToastManager } from "@/components/ui/toast";
import { FormattedDateTime } from "@/components/ui/formatted-date-time";
import { Trash2 } from "lucide-react";
import { useT } from "@/lib/i18n";

/**
 * Mirrors `work_order::work-orders._labor.blade.php` — time, and nothing
 * but time (ADR-050). Technicians are salaried, so an hour of theirs
 * carries no cost of its own; a contractor's charge is money that leaves
 * the business and is recorded as a cost entry against the machine
 * instead, not here.
 */
function LaborTab({ entries, technicians, canManage, isTerminal, recordAction, deleteAction }) {
  const t = useT("work_order");
  const totalMinutes = entries.reduce((sum, entry) => sum + (entry.minutes ?? 0), 0);
  // See parts-tab.jsx's PartsTab for why this is memoized rather than built
  // inline in RecordLaborForm's render.
  const technicianOptions = useMemo(() => technicians.map((tech) => ({ value: tech.id, label: tech.name })), [technicians]);

  return (
    <div className="flex flex-col gap-4">
      <div className="flex items-center justify-between text-sm">
        <span className="text-foreground-muted">{t("time_on_the_job")}</span>
        <span className="font-medium text-foreground">{t("total_minutes", { count: totalMinutes.toLocaleString() })}</span>
      </div>

      <div className="overflow-x-auto rounded-sm border border-border">
        <table className="w-full text-sm">
          <thead>
            <tr className="border-b border-border text-left text-xs text-foreground-muted">
              <th className="px-4 py-3">{t("technician")}</th>
              <th className="px-4 py-3">{t("started_at")}</th>
              <th className="px-4 py-3">{t("ended_at")}</th>
              <th className="px-4 py-3 text-right">{t("minutes")}</th>
              <th className="px-4 py-3">{t("notes")}</th>
              {canManage && !isTerminal ? <th className="px-4 py-3" /> : null}
            </tr>
          </thead>
          <tbody>
            {entries.length === 0 ? (
              <tr>
                <td colSpan={6} className="px-4 py-6 text-center text-foreground-muted">
                  {t("no_labor")}
                </td>
              </tr>
            ) : (
              entries.map((entry) => (
                <LaborRow key={entry.id} entry={entry} canManage={canManage} isTerminal={isTerminal} deleteAction={deleteAction} />
              ))
            )}
          </tbody>
        </table>
      </div>

      {canManage && !isTerminal ? <RecordLaborForm technicianOptions={technicianOptions} action={recordAction} /> : null}
    </div>
  );
}

function LaborRow({ entry, canManage, isTerminal, deleteAction }) {
  const t = useT("work_order");
  const tc = useT("common");
  const router = useRouter();
  const [pending, startTransition] = useTransition();
  const toastManager = useToastManager();

  function runDelete() {
    startTransition(async () => {
      const result = await deleteAction(entry.id);
      if (result?.status === "success") {
        toastManager.add({ title: t("removed"), type: "success" });
        router.refresh();
      } else if (result?.status === "error") {
        toastManager.add({ title: result.message, type: "danger" });
      }
    });
  }

  return (
    <tr className="border-t border-border">
      <td className="px-4 py-3">{entry.technician?.name ?? "—"}</td>
      <td className="px-4 py-3"><FormattedDateTime value={entry.started_at} /></td>
      <td className="px-4 py-3"><FormattedDateTime value={entry.ended_at} /></td>
      <td className="px-4 py-3 text-right">{entry.minutes}</td>
      <td className="px-4 py-3 text-xs text-foreground-muted">{entry.notes ?? "—"}</td>
      {canManage && !isTerminal ? (
        <td className="px-4 py-3 text-right">
          <Button variant="ghost" size="icon" aria-label={tc("delete")} loading={pending} onClick={runDelete}>
            <Trash2 className="text-danger" />
          </Button>
        </td>
      ) : null}
    </tr>
  );
}

function RecordLaborForm({ technicianOptions, action }) {
  const t = useT("work_order");
  const [state, dispatch, pending] = useActionState(action, null);
  const toastManager = useToastManager();

  useEffect(() => {
    if (state?.status === "success") {
      toastManager.add({ title: t("labor_recorded"), type: "success" });
    }
    // toastManager is not a stable reference across renders — including it
    // re-fires this effect every render once state first becomes
    // "success", stacking duplicate toasts.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [state]);

  return (
    <form action={dispatch} className="grid grid-cols-1 gap-3 rounded-sm border border-border p-4 sm:grid-cols-5 sm:items-end">
      <FormField label={t("technician")} required error={state?.errors?.technician_id?.[0]}>
        {(fieldProps) => <Select {...fieldProps} name="technician_id" options={technicianOptions} placeholder={t("select_technician")} />}
      </FormField>
      <FormField label={t("started_at")} required error={state?.errors?.started_at?.[0]}>
        {(fieldProps) => <DateTimeField {...fieldProps} name="started_at" />}
      </FormField>
      <FormField label={t("ended_at")} required error={state?.errors?.ended_at?.[0]}>
        {(fieldProps) => <DateTimeField {...fieldProps} name="ended_at" />}
      </FormField>
      <FormField label={t("notes")} error={state?.errors?.notes?.[0]}>
        {(fieldProps) => <Input {...fieldProps} name="notes" maxLength={500} />}
      </FormField>
      <Button type="submit" loading={pending}>
        {t("record_time")}
      </Button>
      {state?.status === "error" && !state.errors ? <p className="col-span-full text-sm text-danger">{state.message}</p> : null}
    </form>
  );
}

export { LaborTab };
