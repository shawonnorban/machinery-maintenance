"use client";

import { useState, useTransition } from "react";
import { useRouter } from "next/navigation";
import { Check } from "lucide-react";
import { Select } from "@/components/ui/select";
import { Button } from "@/components/ui/button";
import { useToastManager } from "@/components/ui/toast";
import { useT } from "@/lib/i18n";

/**
 * Fabric Process Rate → Pending Fiber Combination (AnnotechERP 06/09): the
 * rates of one chart category that have no fibre combination template yet,
 * each assigned its template in place; an assigned rate leaves the list.
 *
 * @param {{ rows: object[], templates: {value: string, label: string}[], assign: Function, canManage: boolean }} props
 */
function PendingFiberTemplates({ rows, templates, assign, canManage }) {
  const t = useT("merchandise");
  const m = (k, p) => t(`master.fpr.${k}`, p);
  const router = useRouter();
  const toastManager = useToastManager();
  const [left, setLeft] = useState(rows);
  const [picked, setPicked] = useState({});
  const [pending, startTransition] = useTransition();

  function save(row) {
    startTransition(async () => {
      const result = await assign(row.id, picked[row.id]);
      if (result?.status === "success") {
        setLeft((all) => all.filter((r) => r.id !== row.id));
        toastManager.add({ title: m("assigned", { no: row.rate_no }), type: "success" });
        router.refresh();
      } else {
        toastManager.add({ title: result?.message, type: "danger" });
      }
    });
  }

  if (left.length === 0) return <p className="px-5 py-14 text-center text-sm text-foreground-muted">{m("none_pending")}</p>;

  const th = "px-3 py-2.5 text-left text-[13px] font-semibold whitespace-nowrap";
  return (
    <div className="overflow-x-auto">
      <table className="w-full min-w-max text-sm">
        <thead>
          <tr className="bg-brand text-brand-foreground">
            <th className={th}>{m("f.id")}</th>
            <th className={th}>{m("col.fab_type")}</th>
            <th className={th}>{m("col.cnt")}</th>
            <th className={th}>{m("col.desc")}</th>
            <th className={th}>{m("col.rate")}</th>
            <th className={th}>{m("f.template")}</th>
          </tr>
        </thead>
        <tbody>
          {left.map((r) => (
            <tr key={r.id} className="border-b border-border align-middle last:border-0">
              <td className="px-3 py-2 tabular-nums">{r.rate_no}</td>
              <td className="px-3 py-2">{r.fabric_type ?? "—"}</td>
              <td className="px-3 py-2">{r.count_range ?? "—"}</td>
              <td className="px-3 py-2">{r.description}</td>
              <td className="px-3 py-2 whitespace-nowrap tabular-nums">{m("per_unit", { rate: r.rate, ccy: r.currency, per: r.per })}</td>
              <td className="px-3 py-2">
                {canManage ? (
                  <div className="flex items-center gap-2">
                    <div className="w-56">
                      <Select value={picked[r.id] ?? ""} onValueChange={(v) => setPicked((all) => ({ ...all, [r.id]: v ?? "" }))} options={[{ value: "", label: t("master.select") }, ...templates]} placeholder={t("master.select")} aria-label={m("f.template")} />
                    </div>
                    <Button size="sm" onClick={() => save(r)} disabled={!picked[r.id] || pending}>
                      <Check /> {m("assign")}
                    </Button>
                  </div>
                ) : null}
              </td>
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  );
}

export { PendingFiberTemplates };
