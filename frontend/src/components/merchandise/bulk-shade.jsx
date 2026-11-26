"use client";

import { useState, useTransition } from "react";
import { useRouter } from "next/navigation";
import { Lock, Unlock } from "lucide-react";
import { Button } from "@/components/ui/button";
import { Badge } from "@/components/ui/badge";
import { Alert } from "@/components/ui/alert";
import { useToastManager } from "@/components/ui/toast";
import { useT } from "@/lib/i18n";

const TONE = { PENDING: "neutral", SUBMITTED: "info", APPROVED: "success", REJECTED: "danger", RESUBMIT: "warning" };

/**
 * Buyer Sample Feedback → Bulk Shade Approval (AnnotechERP 06/10): the
 * samples one buyer account has given feedback on, and for each the bulk
 * shade released or held — a decision of its own, apart from the sample
 * being accepted. Release and Hold take effect at once.
 *
 * @param {{ rows: object[], setShade: Function, canManage: boolean }} props
 */
function BulkShade({ rows, setShade, canManage }) {
  const t = useT("merchandise");
  const m = (k, p) => t(`master.bsf.${k}`, p);
  const router = useRouter();
  const toastManager = useToastManager();
  const [list, setList] = useState(rows);
  const [busy, setBusy] = useState(null);
  const [error, setError] = useState(null);
  const [, startTransition] = useTransition();

  function decide(row, approved) {
    setBusy(row.id);
    startTransition(async () => {
      const result = await setShade(row.id, approved);
      setBusy(null);
      if (result?.status === "success") {
        setList((all) => all.map((r) => (r.id === row.id ? { ...r, bulk_shade_approved: approved } : r)));
        setError(null);
        toastManager.add({ title: m(approved ? "released" : "held", { what: [row.job, row.type].filter(Boolean).join(" · ") }), type: "success" });
        router.refresh();
      } else {
        setError(result?.errors ? Object.values(result.errors).flat().join(" · ") : result?.message);
      }
    });
  }

  const th = "px-4 py-2.5 text-left text-[13px] font-semibold whitespace-nowrap";
  return (
    <div className="flex flex-col">
      {error ? (
        <Alert variant="danger" className="m-4">
          {error}
        </Alert>
      ) : null}
      <div className="overflow-x-auto">
        <table className="w-full text-sm">
          <thead>
            <tr className="bg-brand text-brand-foreground">
              {["batch", "submit_date", "job", "order", "type", "status", "comments", "bulk_shade"].map((k) => (
                <th key={k} className={th}>
                  {m(`col.${k}`)}
                </th>
              ))}
              {canManage ? <th className={th}>{m("col.action")}</th> : null}
            </tr>
          </thead>
          <tbody>
            {list.map((r) => (
              <tr key={r.id} className="border-b border-border last:border-0">
                <td className="px-4 py-2 font-mono text-[13px]">{r.batch_no ?? "—"}</td>
                <td className="px-4 py-2 whitespace-nowrap">{r.submit_date}</td>
                <td className="px-4 py-2">{r.job ?? "—"}</td>
                <td className="px-4 py-2 whitespace-nowrap">{r.order_no ?? "—"}</td>
                <td className="px-4 py-2">{r.type ?? "—"}</td>
                <td className="px-4 py-2">
                  <Badge variant={TONE[r.status] ?? "neutral"}>{m(`status.${r.status}`)}</Badge>
                </td>
                <td className="px-4 py-2 text-foreground-muted">{r.comments ?? "—"}</td>
                <td className="px-4 py-2">
                  <Badge variant={r.bulk_shade_approved ? "success" : "warning"}>{m(r.bulk_shade_approved ? "approved" : "not_approved")}</Badge>
                </td>
                {canManage ? (
                  <td className="px-4 py-2">
                    {r.bulk_shade_approved ? (
                      <Button variant="outline" size="sm" onClick={() => decide(r, false)} loading={busy === r.id}>
                        <Lock /> {m("hold")}
                      </Button>
                    ) : (
                      <Button size="sm" onClick={() => decide(r, true)} loading={busy === r.id}>
                        <Unlock /> {m("release")}
                      </Button>
                    )}
                  </td>
                ) : null}
              </tr>
            ))}
          </tbody>
        </table>
      </div>
      <p className="border-t border-border px-5 py-3 text-[13px] text-foreground-muted">{m("rule")}</p>
    </div>
  );
}

export { BulkShade };
