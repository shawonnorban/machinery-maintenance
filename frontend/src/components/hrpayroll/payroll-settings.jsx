"use client";

import { useState, useTransition } from "react";
import { useRouter } from "next/navigation";
import { RotateCcw, Save } from "lucide-react";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { useToastManager } from "@/components/ui/toast";
import { useT } from "@/lib/i18n";

/**
 * Payroll Settings → Payroll Settings (AnnotechERP 24/01): the salary day
 * basis, late deduction rule and rounding every salary screen is computed
 * with. Type over a value and Save; Revert puts back what is stored.
 *
 * @param {{ rows: {key: string, value: string, allowed: string, updated_at: ?string}[], save: Function, canManage: boolean, locale: string }} props
 */
function PayrollSettings({ rows, save, canManage, locale }) {
  const t = useT("hrpayroll");
  const m = (k, p) => t(`master.${k}`, p);
  const router = useRouter();
  const toastManager = useToastManager();
  const stored = Object.fromEntries(rows.map((r) => [r.key, r.value]));
  const [values, setValues] = useState(stored);
  const [list, setList] = useState(rows);
  const [pending, startTransition] = useTransition();
  const dirty = list.some((r) => values[r.key] !== r.value);
  const date = (d) => (d ? new Intl.DateTimeFormat(locale === "bn" ? "bn-BD" : "en-GB", { day: "2-digit", month: "short", year: "numeric" }).format(new Date(d)) : "—");

  function submit() {
    startTransition(async () => {
      const result = await save(values);
      if (result?.status === "success") {
        setList(result.rows);
        setValues(Object.fromEntries(result.rows.map((r) => [r.key, r.value])));
        toastManager.add({ title: m("saved"), type: "success" });
        router.refresh();
      } else toastManager.add({ title: result?.errors ? Object.values(result.errors).flat()[0] : result?.message, type: "danger" });
    });
  }

  return (
    <section className="rounded-lg border border-border bg-surface">
      <header className="px-5 pt-4 pb-3">
        <h2 className="text-base font-semibold">{m("ps.settings_title")}</h2>
        <p className="mt-0.5 text-[13px] text-foreground-muted">{m("ps.settings_hint")}</p>
      </header>
      <div className="overflow-x-auto">
        <table className="w-full text-sm">
          <thead className="bg-brand text-left text-[13px] text-white">
            <tr>
              <th className="px-3 py-2">{m("ps.col.setting")}</th>
              <th className="px-3 py-2">{m("ps.col.value")}</th>
              <th className="px-3 py-2">{m("ps.col.allowed")}</th>
              <th className="px-3 py-2">{m("ps.col.what")}</th>
              <th className="px-3 py-2">{m("ps.col.changed")}</th>
            </tr>
          </thead>
          <tbody>
            {list.map((r) => (
              <tr key={r.key} className="border-t border-border">
                <td className="px-3 py-2">{m(`ps.settings.${r.key}.label`)}</td>
                <td className="px-3 py-2">
                  <Input value={values[r.key] ?? ""} onChange={(e) => setValues((v) => ({ ...v, [r.key]: e.target.value }))} disabled={!canManage} className="w-44" aria-label={m(`ps.settings.${r.key}.label`)} />
                </td>
                <td className="px-3 py-2 whitespace-nowrap">{m(`ps.settings.${r.key}.allowed`, { allowed: r.allowed })}</td>
                <td className="max-w-md px-3 py-2 text-[13px] text-foreground-muted">{m(`ps.settings.${r.key}.what`)}</td>
                <td className="px-3 py-2 whitespace-nowrap">{date(r.updated_at)}</td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
      <div className="flex flex-col gap-3 border-t border-border p-4">
        <p className="text-[13px] text-foreground-muted">{m("ps.settings_footer", { count: list.length })}</p>
        {canManage ? (
          <div className="flex gap-2">
            <Button variant="outline" onClick={() => setValues(Object.fromEntries(list.map((r) => [r.key, r.value])))} disabled={pending || !dirty}>
              <RotateCcw /> {m("grid.revert")}
            </Button>
            <Button onClick={submit} loading={pending} disabled={!dirty}>
              <Save /> {m("ps.save_settings")}
            </Button>
          </div>
        ) : null}
      </div>
    </section>
  );
}

export { PayrollSettings };
