"use client";

import { useState } from "react";
import { FileText } from "lucide-react";
import { Button } from "@/components/ui/button";
import { printDocument } from "@/lib/print-document";
import { day, Filters, has, money, Register, within } from "@/components/sales-shipment/shared";
import { opts, useW } from "@/components/finance/vouchers";

/*
 * Finance & Ledger → Voucher Audit (AnnotechERP 23/06): Voucher Activity
 * Trail, Deleted Voucher History and Voucher Amendment Log — one read-only
 * register each over the voucher log, searched with Brickland's eleven boxes;
 * the activity trail also prints (PDF) and exports.
 */

const TYPES = ["BPV", "BRV", "CPV", "CRV", "CV", "BV", "JV"];
const SOURCES = ["FINANCE", "COMMERCIAL", "STORE", "HR", "SYSTEM"];
const POSTING = ["DRAFT", "UNPOSTED", "POSTED", "CANCELLED"];
const FOR_TYPES = ["GENERAL", "SUPPLIER", "BUYER", "EMPLOYEE", "PARTY"];

function VoucherAuditRegister({ kind, initial, opt }) {
  const w = useW();
  const [filter, setFilter] = useState({});
  const sel = (name, label, options, field) => ({ name, label, type: "select", options, test: (r, v) => !v || field(r) === v });
  const txt = (name, label, field) => ({ name, label, test: (r, v) => has(field(r), v) });
  const filters = [
    txt("no", w("f.voucher_no_f"), (r) => r.voucher_no),
    txt("ref", w("f.voucher_ref_f"), (r) => r.voucher_ref_no),
    txt("bill", w("f.bill_no_f"), (r) => r.bill_no),
    sel("type", w("f.voucher_type"), opts(w, "vtype", TYPES), (r) => r.voucher_type),
    sel("company", w("f.company"), opt.companies, () => opt.companies[0]?.value),
    sel("source", w("f.trx_source"), opts(w, "source", SOURCES), (r) => r.trx_source),
    sel("for_type", w("f.voucher_for_type"), opts(w, "for_type", FOR_TYPES), (r) => r.voucher_for_type),
    txt("for", w("f.voucher_for"), (r) => r.voucher_for),
    sel("status", w("f.posting_status"), opts(w, "posting", POSTING), (r) => r.posting_status),
    { name: "from", label: w("f.from"), type: "date", test: (r, v) => within(r.voucher_date, v, null) },
    { name: "to", label: w("f.to"), type: "date", test: (r, v) => within(r.voucher_date, null, v) },
  ];
  const shown = initial
    .map((r) => ({
      ...r,
      action_label: w(`au.action.${r.action}`),
      post_label: w(`posting.${r.posting_status}`),
      voucher_ref: [r.voucher_no, r.voucher_ref_no].filter(Boolean).join(" · "),
      date_company: [r.voucher_date ? day(r.voucher_date) : null, r.company].filter(Boolean).join(" · "),
      type_source: [r.voucher_type ? w(`vtype.${r.voucher_type}`) : null, r.trx_source ? w(`source.${r.trx_source}`) : null].filter(Boolean).join(" · "),
      status_label: r.action_status ? w(`action.${r.action_status}`) : "—",
    }))
    .filter((r) => filters.every((f) => f.test(r, filter[f.name])));
  const columns = [
    { key: "action_label", label: w("au.col.action"), type: "badge" },
    { key: "post_label", label: w("col.post_status") },
    { key: "voucher_ref", label: w("col.voucher_ref"), type: "mono" },
    { key: "date_company", label: w("au.col.date_company") },
    { key: "type_source", label: w("col.type_source") },
    { key: "created_at", label: w("col.created_at") },
    { key: "created_by", label: w("au.col.created_by") },
    { key: "status_label", label: w("au.col.status") },
    { key: "at", label: w("col.updated_at") },
    { key: "by", label: w("col.updated_by") },
    { key: "bill_no", label: w("col.bill_no_cap") },
    { key: "note", label: w("au.col.note") },
  ];
  const pdf = () =>
    printDocument({ title: w(`screens.${kind}`), orientation: "landscape", sections: [{ columns: columns.map((c) => ({ label: c.label })), rows: shown.map((r) => columns.map((c) => r[c.key] ?? "—")) }] });
  return (
    <div className="flex flex-col gap-4">
      <Filters
        w={w}
        value={filter}
        onChange={setFilter}
        fields={filters}
        note={w("filter_note")}
        extra={
          kind === "voucher-activity" ? (
            <Button type="button" variant="outline" onClick={pdf} disabled={!shown.length}>
              <FileText /> {w("pdf")}
            </Button>
          ) : null
        }
      />
      <Register w={w} rows={shown} refKey="id" exportName={kind} emptyTitle={w("empty")} emptyText="" actions={[]} columns={columns} />
      <p className="text-xs text-foreground-muted">{w(`au.note.${kind}`)}</p>
      {kind === "voucher-delete-history" && shown.length ? <p className="text-xs text-foreground-muted">{w("au.deleted_total", { n: shown.length, amount: money(shown.reduce((a, r) => a + r.amount, 0)) })}</p> : null}
    </div>
  );
}

export { VoucherAuditRegister };
