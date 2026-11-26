"use client";

import { useState, useTransition } from "react";
import { useRouter } from "next/navigation";
import { Download } from "lucide-react";
import { Button } from "@/components/ui/button";
import { ConfirmDialog } from "@/components/ui/confirm-dialog";
import { FormattedDateTime } from "@/components/ui/formatted-date-time";
import { useToastManager } from "@/components/ui/toast";
import { StatusPill } from "@/components/erp/status-pill";
import { useT } from "@/lib/i18n";

const money = (currency, value) => (value === null || value === undefined ? "—" : `${currency ?? ""} ${Number(value).toFixed(2)}`.trim());

/**
 * Price Quotation Entry → Quotation (AnnotechERP 06/05): one buyer's inquiries
 * and moving each along — Revise (asked first: the revision number is bumped),
 * Send Quotation, Confirm, and Cancel (asked first; it stays as history). The
 * server refuses a move a status does not allow and says why.
 *
 * @param {{ rows: object[], move: Function, canManage: boolean, exportName: string }} props
 */
function InquiryQuotation({ rows, move, canManage, exportName }) {
  const t = useT("merchandise");
  const m = (k, p) => t(`master.inq.${k}`, p);
  const router = useRouter();
  const toastManager = useToastManager();
  const [asking, setAsking] = useState(null);
  const [pending, startTransition] = useTransition();

  function run(row, verb) {
    startTransition(async () => {
      const result = await move(row.id, verb);
      if (result?.status === "success") {
        toastManager.add({ title: result.message, type: "success" });
        setAsking(null);
        router.refresh();
      } else {
        toastManager.add({ title: result?.errors ? Object.values(result.errors).flat()[0] : result?.message, type: "danger" });
        setAsking(null);
      }
    });
  }

  function exportCsv() {
    const head = ["inquiry_no", "style_no", "style_desc", "inquiry_dt", "rev", "quoted_fob", "status"].map((k) => m(`f.${k}`));
    const lines = rows.map((r) => [r.inquiry_no, r.style_no, r.style_desc, r.inquiry_date, r.revision, money(r.currency, r.quoted_fob), m(`status.${r.status}`)]);
    const escape = (v) => `"${String(v ?? "").replaceAll('"', '""')}"`;
    const blob = new Blob([`﻿${[head, ...lines].map((l) => l.map(escape).join(",")).join("\n")}`], { type: "text/csv;charset=utf-8" });
    const link = document.createElement("a");
    link.href = URL.createObjectURL(blob);
    link.download = `${exportName}.csv`;
    link.click();
    URL.revokeObjectURL(link.href);
  }

  return (
    <>
      <div className="flex justify-end px-5 pb-3">
        <Button variant="outline" size="sm" onClick={exportCsv} disabled={rows.length === 0}>
          <Download /> {m("export")}
        </Button>
      </div>
      <div className="overflow-x-auto">
        <table className="w-full min-w-max text-sm">
          <thead>
            <tr className="bg-brand text-left text-brand-foreground">
              {["inquiry_no", "style_no", "style_desc", "inquiry_dt", "rev", "quoted_fob", "status", "action"].map((k) => (
                <th key={k} className="px-3 py-2.5 text-[13px] font-semibold whitespace-nowrap">
                  {m(`f.${k}`)}
                </th>
              ))}
            </tr>
          </thead>
          <tbody>
            {rows.map((r) => (
              <tr key={r.id} className="border-b border-border last:border-0">
                <td className="px-3 py-2.5 font-mono text-[13px]">{r.inquiry_no}</td>
                <td className="px-3 py-2.5">{r.style_no}</td>
                <td className="px-3 py-2.5">{r.style_desc}</td>
                <td className="px-3 py-2.5 whitespace-nowrap">
                  <FormattedDateTime value={r.inquiry_date} mode="date" />
                </td>
                <td className="px-3 py-2.5 text-right tabular-nums">{r.revision}</td>
                <td className="px-3 py-2.5 text-right tabular-nums">{money(r.currency, r.quoted_fob)}</td>
                <td className="px-3 py-2.5">
                  <StatusPill active={r.status !== "CANCELLED"} label={m(`status.${r.status}`)} />
                </td>
                <td className="px-3 py-2 whitespace-nowrap">
                  {canManage ? (
                    <span className="inline-flex gap-1.5">
                      <Button variant="outline" size="sm" disabled={pending} onClick={() => setAsking({ row: r, verb: "revise" })}>
                        {m("revise")}
                      </Button>
                      <Button variant="outline" size="sm" disabled={pending} onClick={() => run(r, "send")}>
                        {m("send")}
                      </Button>
                      <Button variant="outline" size="sm" disabled={pending} onClick={() => run(r, "confirm")}>
                        {m("confirm")}
                      </Button>
                      <Button variant="outline" size="sm" disabled={pending} onClick={() => setAsking({ row: r, verb: "cancel" })}>
                        {m("cancel")}
                      </Button>
                    </span>
                  ) : null}
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
      <p className="border-t border-border px-5 py-3 text-[13px] text-foreground-muted">{rows.length === 1 ? m("count_one") : m("count_many", { count: rows.length })}</p>

      <ConfirmDialog
        open={asking !== null}
        onOpenChange={(open) => !open && setAsking(null)}
        title={asking?.verb === "cancel" ? m("cancel_title") : m("revise_title")}
        description={asking?.verb === "cancel" ? m("cancel_text") : m("revise_text")}
        confirmLabel={asking?.verb === "cancel" ? m("cancel_inquiry") : m("revise")}
        cancelLabel={m("close")}
        loading={pending}
        onConfirm={() => asking && run(asking.row, asking.verb)}
      />
    </>
  );
}

export { InquiryQuotation };
