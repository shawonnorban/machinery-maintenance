"use client";

import { useState, useTransition } from "react";
import { useRouter } from "next/navigation";
import { RotateCcw, Save } from "lucide-react";
import { Input } from "@/components/ui/input";
import { Button } from "@/components/ui/button";
import { useToastManager } from "@/components/ui/toast";
import { useT } from "@/lib/i18n";

const today = () => new Date().toISOString().slice(0, 10);

function editable(rows) {
  return rows.map((r) => ({ ...r, next_rate: r.rate ?? "", next_currency: r.currency ?? "USD", next_from: r.effective_from ?? today() }));
}

/**
 * Inquiry Rate Chart → Rate Chart (AnnotechERP 06/01): one buyer office's rate
 * for every line, grouped as Brickland groups them. A changed rate or a new
 * effective date is saved as a revision; the old rate stays in Rate History.
 *
 * @param {{ office: string, rows: object[], save: Function, canManage: boolean }} props
 */
function RateChartEditor({ office, rows: initial, save, canManage }) {
  const t = useT("merchandise");
  const router = useRouter();
  const toastManager = useToastManager();
  const [rows, setRows] = useState(() => editable(initial));
  const [pending, startTransition] = useTransition();
  const groups = [...new Set(rows.map((r) => r.rate_group))];

  const set = (id, field, value) => setRows((current) => current.map((r) => (r.rate_line_id === id ? { ...r, [field]: value } : r)));

  function submit() {
    startTransition(async () => {
      const rates = rows
        .filter((r) => r.next_rate !== "" && r.next_rate !== null)
        .map((r) => ({ rate_line_id: r.rate_line_id, rate: Number(r.next_rate), currency: r.next_currency, effective_from: r.next_from }));
      const result = await save(office, rates);
      if (result?.status === "success") {
        setRows(editable(result.rows));
        toastManager.add({ title: t("master.saved"), type: "success" });
        router.refresh();
      } else {
        toastManager.add({ title: result?.errors ? Object.values(result.errors).flat()[0] : result?.message, type: "danger" });
      }
    });
  }

  return (
    <div className="rounded-lg border border-border bg-surface">
      <div className="overflow-x-auto">
        <table className="w-full min-w-max text-sm">
          <thead>
            <tr className="bg-primary text-left text-primary-foreground">
              <th className="px-3 py-2 font-semibold">{t("master.f.rate_desc")}</th>
              <th className="px-3 py-2 font-semibold">{t("master.f.uom")}</th>
              <th className="px-3 py-2 text-right font-semibold">{t("master.rate.current")}</th>
              <th className="px-3 py-2 font-semibold">{t("master.rate.since")}</th>
              <th className="px-3 py-2 font-semibold">{t("master.rate.new_rate")}</th>
              <th className="px-3 py-2 font-semibold">{t("master.f.currency")}</th>
              <th className="px-3 py-2 font-semibold">{t("master.rate.effective_from")}</th>
            </tr>
          </thead>
          <tbody>
            {groups.map((group) => [
              <tr key={group} className="bg-surface-muted">
                <td colSpan={7} className="px-3 py-1.5 text-[13px] font-semibold text-foreground">
                  {t(`master.opt.rate_group.${group}`)}
                </td>
              </tr>,
              ...rows
                .filter((r) => r.rate_group === group)
                .map((r) => (
                  <tr key={r.rate_line_id} className="border-b border-border last:border-0">
                    <td className="max-w-64 px-3 py-1.5">{r.description}</td>
                    <td className="px-3 py-1.5 text-foreground-muted">{r.uom}</td>
                    <td className="px-3 py-1.5 text-right tabular-nums">{r.rate === null ? "—" : `${r.rate.toFixed(2)} ${r.currency}`}</td>
                    <td className="px-3 py-1.5 tabular-nums text-foreground-muted">{r.effective_from ?? "—"}</td>
                    <td className="px-3 py-1.5">
                      <Input type="number" min="0" step="0.0001" value={r.next_rate} onChange={(e) => set(r.rate_line_id, "next_rate", e.target.value)} disabled={!canManage} className="w-24" aria-label={r.description} />
                    </td>
                    <td className="px-3 py-1.5">
                      <Input value={r.next_currency} maxLength={3} onChange={(e) => set(r.rate_line_id, "next_currency", e.target.value.toUpperCase())} disabled={!canManage} className="w-16 font-mono" />
                    </td>
                    <td className="px-3 py-1.5">
                      <Input type="date" value={r.next_from} onChange={(e) => set(r.rate_line_id, "next_from", e.target.value)} disabled={!canManage} className="w-36" />
                    </td>
                  </tr>
                )),
            ])}
          </tbody>
        </table>
      </div>
      {canManage ? (
        <div className="flex flex-wrap items-center gap-2 border-t border-border p-4">
          <p className="mr-auto text-[13px] text-foreground-muted">{t("master.rate.hint")}</p>
          <Button variant="outline" onClick={() => setRows(editable(initial))} disabled={pending}>
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

export { RateChartEditor };
