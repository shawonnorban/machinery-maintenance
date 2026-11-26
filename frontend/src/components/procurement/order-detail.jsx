"use client";

import { useState } from "react";
import Link from "next/link";
import { Card, CardHeader, CardTitle, CardBody } from "@/components/ui/card";
import { StatusBadge } from "@/components/ui/status-badge";
import { Button } from "@/components/ui/button";
import { FormattedDateTime } from "@/components/ui/formatted-date-time";
import { DocumentLinesTable, ItemCell, qty } from "@/components/procurement/document-lines-table";
import { DocumentActions } from "@/components/procurement/document-actions";
import { Field, HistoryRow } from "@/components/procurement/requisition-detail";
import { formatNumber } from "@/lib/format";
import { useT } from "@/lib/i18n";
import { refOf } from "@/lib/ref-path";

/** The body of a purchase order's detail page (Procurement spec §7): lines, revisions, receipts, actions. */
function OrderDetail({ order, actions }) {
  const t = useT("procurement");
  const o = order;
  const approval = o.approval_request;
  const [openRevision, setOpenRevision] = useState(null);

  const money = (value) => `${formatNumber(value, { maximumFractionDigits: 2 })} ${o.currency}`;

  const buttons = [
    o.can_submit && { key: "submit", label: t("submit_for_approval"), run: () => actions.submit(), successTitle: t("order_submitted", { number: o.po_no }) },
    approval?.can_act && {
      key: "approve", label: t("approve"), variant: "success",
      run: (comment) => actions.approve(approval.id, comment), successTitle: t("order_approved", { number: o.po_no }),
      reason: { label: t("comment"), required: false },
    },
    approval?.can_act && {
      key: "reject", label: t("reject"), variant: "danger",
      run: (comment) => actions.reject(approval.id, comment), successTitle: t("order_rejected", { number: o.po_no }),
      reason: { label: t("reject_reason"), required: true },
    },
    o.can_receive && {
      key: "receive", label: t("receive"),
      run: async () => ({ status: "success", redirect: `/procurement/orders/${o.id}/receive` }), successTitle: t("receive"),
    },
    o.can_revise && {
      key: "revise", label: t("revise"), variant: "outline",
      run: async () => ({ status: "success", redirect: `/procurement/orders/${o.id}/revise` }), successTitle: t("revise"),
    },
    // Only on an import: a local order has no papers to keep.
    o.purchase_type === "IMPORT" && {
      key: "import-documents", label: t("import_documents"), variant: "outline", hint: t("import_documents_hint"),
      run: async () => ({ status: "success", redirect: `/procurement/orders/${o.id}/import-documents` }),
      successTitle: t("import_documents"),
    },
    o.can_close && {
      key: "close", label: t("close_order"), variant: "outline", hint: t("close_order_hint"),
      run: () => actions.close(), successTitle: t("order_closed", { number: o.po_no }),
    },
    o.can_cancel && {
      key: "cancel", label: t("cancel_document"), variant: "outline",
      run: (reason) => actions.cancel(reason), successTitle: t("order_cancelled", { number: o.po_no }),
      reason: { label: t("cancel_reason"), required: false },
    },
  ];

  const lineColumns = [
    { key: "item", header: t("item"), render: (line) => <ItemCell line={line} /> },
    { key: "ordered_qty", header: t("ordered_qty"), align: "right", render: (line) => qty(line.ordered_qty, line.uom?.code) },
    { key: "received_qty", header: t("received_qty"), align: "right", render: (line) => qty(line.received_qty, line.uom?.code) },
    { key: "pending_qty", header: t("pending_qty"), align: "right", render: (line) => qty(line.pending_qty, line.uom?.code) },
    { key: "rate", header: t("rate"), align: "right", render: (line) => formatNumber(line.rate, { maximumFractionDigits: 2 }) },
    { key: "amount", header: t("amount"), align: "right", render: (line) => formatNumber(line.amount, { maximumFractionDigits: 2 }) },
    { key: "required_date", header: t("required_date"), render: (line) => (line.required_date ? <FormattedDateTime value={line.required_date} mode="date" /> : "—") },
  ];

  return (
    <div className="grid grid-cols-1 gap-4 lg:grid-cols-12">
      <div className="flex flex-col gap-4 lg:col-span-8">
        <DocumentLinesTable
          title={t("lines")}
          lines={o.lines}
          columns={lineColumns}
          footer={{ item: t("subtotal"), amount: formatNumber(o.subtotal, { maximumFractionDigits: 2 }) }}
        />

        {o.revisions?.length ? (
          <Card>
            <CardHeader>
              <CardTitle>{t("revisions")}</CardTitle>
            </CardHeader>
            <CardBody className="flex flex-col gap-3 text-sm">
              {o.revisions.map((rev) => (
                <div key={rev.id} className="flex flex-col gap-2 rounded-sm border border-border p-3">
                  <div className="flex flex-wrap items-center justify-between gap-2">
                    <span>
                      <span className="font-medium">{t("revision_no", { number: rev.revision_no })}</span>
                      <span className="ml-2 text-foreground-muted">{rev.reason}</span>
                    </span>
                    <span className="flex items-center gap-2 text-xs text-foreground-muted">
                      <FormattedDateTime value={rev.revised_at} />
                      <Button variant="ghost" size="sm" onClick={() => setOpenRevision(openRevision === rev.id ? null : rev.id)}>
                        {t("revision_snapshot", { number: rev.revision_no })}
                      </Button>
                    </span>
                  </div>
                  {openRevision === rev.id ? (
                    <table className="w-full text-xs">
                      <thead>
                        <tr className="text-left text-foreground-muted">
                          <th className="py-1">{t("item")}</th>
                          <th className="py-1 text-right">{t("ordered_qty")}</th>
                          <th className="py-1 text-right">{t("rate")}</th>
                          <th className="py-1 text-right">{t("amount")}</th>
                        </tr>
                      </thead>
                      <tbody>
                        {rev.snapshot?.lines?.map((line, index) => (
                          <tr key={index} className="border-t border-border">
                            <td className="py-1">{line.item_name} <span className="font-mono text-foreground-muted">{line.item_code}</span></td>
                            <td className="py-1 text-right tabular-nums">{formatNumber(line.ordered_qty)} {line.uom_code ?? ""}</td>
                            <td className="py-1 text-right tabular-nums">{formatNumber(line.rate, { maximumFractionDigits: 2 })}</td>
                            <td className="py-1 text-right tabular-nums">{formatNumber(line.amount, { maximumFractionDigits: 2 })}</td>
                          </tr>
                        ))}
                        <tr className="border-t border-border font-medium">
                          <td className="py-1" colSpan={3}>{t("subtotal")}</td>
                          <td className="py-1 text-right tabular-nums">{formatNumber(rev.snapshot?.subtotal, { maximumFractionDigits: 2 })}</td>
                        </tr>
                      </tbody>
                    </table>
                  ) : null}
                </div>
              ))}
            </CardBody>
          </Card>
        ) : null}

        {o.receipts?.length ? (
          <Card>
            <CardHeader>
              <CardTitle>{t("receipts")}</CardTitle>
            </CardHeader>
            <CardBody className="flex flex-col gap-2 text-sm">
              {o.receipts.map((grn) => (
                <div key={grn.id} className="flex items-center justify-between">
                  <Link href={`/procurement/receipts/${grn.id}`} className="font-medium text-brand hover:underline">{grn.grn_no}</Link>
                  <span className="flex items-center gap-2">
                    <FormattedDateTime value={grn.grn_date} mode="date" />
                    <StatusBadge status={grn.status} label={t(`statuses.${grn.status}`)} />
                  </span>
                </div>
              ))}
            </CardBody>
          </Card>
        ) : null}

        {o.notes ? (
          <Card>
            <CardHeader>
              <CardTitle>{t("notes")}</CardTitle>
            </CardHeader>
            <CardBody className="text-sm whitespace-pre-line">{o.notes}</CardBody>
          </Card>
        ) : null}
      </div>

      <div className="flex flex-col gap-4 lg:col-span-4">
        <DocumentActions buttons={buttons} />

        <Card>
          <CardHeader>
            <CardTitle>{t("purchase_order")}</CardTitle>
          </CardHeader>
          <CardBody className="grid grid-cols-2 gap-x-4 gap-y-3 text-sm">
            <Field label={t("status")}><StatusBadge status={o.status} label={t(`statuses.${o.status}`)} /></Field>
            <Field label={t("approval")}>
              <StatusBadge status={o.approval_status} label={t(`approval_statuses.${o.approval_status}`)} />
              {approval ? <span className="ml-1 text-xs text-foreground-muted">{approval.current_step}/{approval.total_steps}</span> : null}
            </Field>
            <Field label={t("vendor")}>{o.vendor ? <Link href={`/vendors/${refOf(o.vendor, "code")}`} className="text-brand hover:underline">{o.vendor.name}</Link> : "—"}</Field>
            <Field label={t("factory")}>{o.factory?.name ?? "—"}</Field>
            <Field label={t("date")}><FormattedDateTime value={o.po_date} mode="date" /></Field>
            <Field label={t("purchase_type")}>{t(`purchase_types.${o.purchase_type}`)}</Field>
            <Field label={t("subtotal")}>{money(o.subtotal)}</Field>
            <Field label={t("revision")}>{o.revision_no}</Field>
            <Field label={t("payment_type")}>{o.payment_type?.name ?? "—"}</Field>
            <Field label={t("payment_terms")}>{o.payment_terms ?? "—"}</Field>
            <Field label={t("delivery_date")}>{o.delivery_date ? <FormattedDateTime value={o.delivery_date} mode="date" /> : "—"}</Field>
            <Field label={t("delivery_store")}>{o.delivery_store?.name ?? "—"}</Field>
            <Field label={t("created_by")}>{o.creator?.name ?? "—"}</Field>
            {o.requisitions?.length ? (
              <Field label={t("from_requisition")}>
                {o.requisitions.map((r) => (
                  <Link key={r.id} href={`/procurement/requisitions/${r.id}`} className="block text-brand hover:underline">{t("purchase_requisition")}</Link>
                ))}
              </Field>
            ) : null}
          </CardBody>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle>{t("history")}</CardTitle>
          </CardHeader>
          <CardBody className="flex flex-col gap-2 text-sm">
            <HistoryRow label={t("created")} at={o.created_at} />
            <HistoryRow label={t("submitted_at")} at={o.submitted_at} />
            <HistoryRow label={t("approved_at")} at={o.approved_at} />
            <HistoryRow label={t("statuses.CLOSED")} at={o.closed_at} />
            <HistoryRow label={t("statuses.CANCELLED")} at={o.cancelled_at} />
          </CardBody>
        </Card>
      </div>
    </div>
  );
}

export { OrderDetail };
