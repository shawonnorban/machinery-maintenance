"use client";

import { useMemo, useRef, useState } from "react";
import { useRouter } from "next/navigation";
import { FileUp, Loader2 } from "lucide-react";
import { OrderSheet } from "@/components/ordersummary/sheet";
import { Button } from "@/components/ui/button";
import { useToastManager } from "@/components/ui/toast";
import { useT } from "@/lib/i18n";

/*
 * Export Statement → View All / Add Export Statement (AnnotechERP 35): Order
 * Summary's sheet on the export invoices — every column as Column Setup makes
 * it, a filter on each header, tick rows and Bulk Edit them (the edit waits
 * for approval unless one may approve), copy and paste like Excel, Excel out
 * (Norban's book) and in. No Copy Row: one Company + Invoice No. is one entry.
 */

/** The columns View All starts with: Norban's default view. */
const DEFAULT_COLUMNS = [
  "serial_no", "company_name", "job_no", "buyer_name", "ex_factory_date", "invoice_no", "invoice_date", "po_no", "export_qty", "invoice_value_gross",
  "invoice_value_net", "bl_no", "bl_date", "max_docs_submission_date", "docs_submission_date", "docs_submission_delay_days", "realized_value",
  "shortage_over_payment", "due_payment", "shippers_bank_name", "sc_lc_no", "status",
];

/** Import Excel: Norban's book or this page's export, through /api/export-statement/import. */
function ImportButton() {
  const t = useT("exportstatement");
  const toast = useToastManager();
  const router = useRouter();
  const input = useRef(null);
  const [busy, setBusy] = useState(false);
  const upload = async (file) => {
    if (!file) return;
    setBusy(true);
    try {
      const body = new FormData();
      body.append("file", file);
      const res = await fetch("/api/export-statement/import", { method: "POST", body });
      const json = await res.json().catch(() => null);
      if (!res.ok) {
        const first = json?.errors ? Object.values(json.errors)[0]?.[0] : null;
        throw new Error(first ?? json?.message ?? res.statusText);
      }
      const d = json.data;
      const notes = [d.skipped.length ? `${t("imp.skipped", { n: d.skipped.length })}: ${d.skipped.slice(0, 4).join(" · ")}` : null, d.unknown_columns.length ? t("imp.unknown", { list: d.unknown_columns.slice(0, 6).join(", ") }) : null].filter(Boolean);
      toast.add({ title: t("imp.done", { created: d.created, updated: d.updated, sent: d.sent_for_approval, unchanged: d.unchanged }), description: notes.join(" — ") || undefined, type: d.skipped.length ? "warning" : "success" });
      router.refresh();
    } catch (e) {
      toast.add({ title: t("imp.failed"), description: String(e?.message ?? e), type: "danger" });
    } finally {
      setBusy(false);
      if (input.current) input.current.value = "";
    }
  };

  return (
    <>
      <input ref={input} type="file" accept=".xlsx,.xls" className="hidden" onChange={(e) => upload(e.target.files?.[0])} />
      <Button type="button" size="sm" variant="outline" disabled={busy} onClick={() => input.current?.click()} title={t("imp.hint")}>
        {busy ? <Loader2 className="animate-spin" /> : <FileUp />} {busy ? t("imp.busy") : t("imp.button")}
      </Button>
    </>
  );
}

function StatementSheet({ mode, meta, data, queryString, act }) {
  const config = useMemo(
    () => ({
      viewKey: "exportstatement",
      listPath: "/export-statement/list",
      artworkUrl: null,
      titleKeys: ["invoice_no", "company_name"],
      defaultColumns: DEFAULT_COLUMNS,
      rowHeight: { new: 40, list: 40 },
      fillHeight: { list: true },
      companions: null,
      copy: false,
      // Job No in the company's form (26NCL00001): the year and the row's Company Name (its factory) fill themselves.
      jobNo: { column: "job_no", factory: "company_name", url: "/api/job-numbers?module=es" },
      excelUrl: meta.can.export ? "/api/export-statement/export" : null,
      printUrl: null,
      toolbarExtra: meta.can.import ? <ImportButton /> : null,
    }),
    [meta.can.export, meta.can.import],
  );

  return <OrderSheet mode={mode} meta={meta} data={data} queryString={queryString} act={act} config={config} />;
}

export { StatementSheet };
