"use client";

import { useState, useTransition } from "react";
import { useRouter } from "next/navigation";
import { Download } from "lucide-react";
import { Button } from "@/components/ui/button";
import { Badge } from "@/components/ui/badge";
import { ConfirmDialog } from "@/components/ui/confirm-dialog";
import { useToastManager } from "@/components/ui/toast";
import { useT } from "@/lib/i18n";

/**
 * Print & Embroidery Rate Setup → Pendings (AnnotechERP 06/09): one buyer's
 * unconfirmed rates. Confirm makes a rate costable and closes it (it leaves
 * this list for Records, where it is no longer rewritten); Drop removes it.
 * Both ask first, as Brickland does. Export downloads the list.
 *
 * @param {{ buyer: string, rows: object[], confirm: Function, drop: Function, canManage: boolean }} props
 */
function PendingEmbellishmentRates({ buyer, rows, confirm, drop, canManage }) {
  const t = useT("merchandise");
  const m = (k, p) => t(`master.emb.${k}`, p);
  const router = useRouter();
  const toastManager = useToastManager();
  const [left, setLeft] = useState(rows);
  const [asking, setAsking] = useState(null);
  const [pending, startTransition] = useTransition();
  const rate = (r) => m("per_unit", { rate: r.rate, ccy: r.currency, per: r.per ?? "" });

  function decide() {
    const { move, row } = asking;
    startTransition(async () => {
      const result = move === "confirm" ? await confirm(row.id) : await drop(row.id);
      if (result?.status === "success") {
        setLeft((all) => all.filter((r) => r.id !== row.id));
        setAsking(null);
        toastManager.add({ title: m(move === "confirm" ? "confirmed_toast" : "dropped_toast"), type: "success" });
        router.refresh();
      } else {
        setAsking(null);
        toastManager.add({ title: result?.message, type: "danger" });
      }
    });
  }

  function exportCsv() {
    const quote = (v) => `"${String(v ?? "").replace(/"/g, '""')}"`;
    const head = ["type", "order_style", "description", "rate", "supplier", "status"].map((k) => quote(m(`f.${k}`))).join(",");
    const lines = left.map((r) => [m(`type.${r.type}`), r.order_style, r.description, rate(r), r.supplier, m("pending")].map(quote).join(","));
    const link = document.createElement("a");
    link.href = URL.createObjectURL(new Blob([`﻿${[head, ...lines].join("\r\n")}`], { type: "text/csv;charset=utf-8" }));
    link.download = `unconfirmed-rates-${buyer}.csv`;
    link.click();
    URL.revokeObjectURL(link.href);
  }

  const th = "px-3 py-2.5 text-left text-[13px] font-semibold whitespace-nowrap";
  return (
    <div className="flex flex-col">
      <div className="flex justify-end border-b border-border px-5 py-3">
        <Button variant="outline" size="sm" onClick={exportCsv} disabled={left.length === 0}>
          <Download /> {m("export")}
        </Button>
      </div>
      <div className="overflow-x-auto">
        <table className="w-full min-w-max text-sm">
          <thead>
            <tr className="bg-brand text-brand-foreground">
              {["type", "order_style", "description", "rate", "supplier", "status"].map((k) => (
                <th key={k} className={th}>
                  {m(`f.${k}`)}
                </th>
              ))}
              <th className={th}>{t("master.dsc.col.action")}</th>
            </tr>
          </thead>
          <tbody>
            {left.length === 0 ? (
              <tr>
                <td colSpan={7} className="px-4 py-12 text-center text-foreground-muted">
                  {m("none_pending")}
                </td>
              </tr>
            ) : (
              left.map((r) => (
                <tr key={r.id} className="border-b border-border align-middle last:border-0">
                  <td className="px-3 py-2.5 font-medium text-foreground">{m(`type.${r.type}`)}</td>
                  <td className="px-3 py-2.5">{r.order_style ?? "—"}</td>
                  <td className="px-3 py-2.5">{r.description}</td>
                  <td className="px-3 py-2.5 whitespace-nowrap tabular-nums">{rate(r)}</td>
                  <td className="px-3 py-2.5">{r.supplier ?? "—"}</td>
                  <td className="px-3 py-2.5">
                    <Badge variant="warning">{m("pending")}</Badge>
                  </td>
                  <td className="px-3 py-2.5">
                    {canManage ? (
                      <div className="flex gap-1.5">
                        <Button size="sm" className="bg-success text-white hover:bg-success/90" onClick={() => setAsking({ move: "confirm", row: r })}>
                          {m("confirm")}
                        </Button>
                        <Button variant="outline" size="sm" className="text-danger" onClick={() => setAsking({ move: "drop", row: r })}>
                          {m("drop")}
                        </Button>
                      </div>
                    ) : null}
                  </td>
                </tr>
              ))
            )}
          </tbody>
        </table>
      </div>
      <p className="border-t border-border px-5 py-3 text-[13px] text-foreground-muted">{m("footer", { n: left.length })}</p>
      <ConfirmDialog
        open={Boolean(asking)}
        onOpenChange={(o) => !o && setAsking(null)}
        title={asking?.move === "confirm" ? m("confirm_title") : m("drop_title")}
        description={asking?.move === "confirm" ? m("confirm_text") : m("drop_text", { type: asking ? m(`type.${asking.row.type}`) : "" })}
        confirmLabel={asking?.move === "confirm" ? m("confirm") : m("drop")}
        cancelLabel={m("cancel")}
        destructive={asking?.move === "drop"}
        loading={pending}
        onConfirm={decide}
      />
    </div>
  );
}

export { PendingEmbellishmentRates };
