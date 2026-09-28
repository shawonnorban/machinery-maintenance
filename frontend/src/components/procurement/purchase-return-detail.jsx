"use client";

import Link from "next/link";
import { Card, CardHeader, CardTitle, CardBody } from "@/components/ui/card";
import { Alert } from "@/components/ui/alert";
import { StatusBadge } from "@/components/ui/status-badge";
import { FormattedDateTime } from "@/components/ui/formatted-date-time";
import { DocumentLinesTable, ItemCell, qty } from "@/components/procurement/document-lines-table";
import { DocumentActions } from "@/components/procurement/document-actions";
import { Field, HistoryRow } from "@/components/procurement/requisition-detail";
import { formatNumber } from "@/lib/format";
import { useT } from "@/lib/i18n";

/**
 * One purchase return (Procurement spec §12).
 *
 * The order of the buttons is the order of the events, and it matters:
 * approval, then the gate pass, then the stock comes down, then the
 * supplier is debited. Nothing leaves the shelf before it is approved, so
 * the page says in as many words whether the stock has moved yet.
 */
function PurchaseReturnDetail({ purchaseReturn, actions }) {
  const t = useT("procurement");
  const r = purchaseReturn;

  const money = (value) => `${formatNumber(value, { maximumFractionDigits: 2 })} ${r.currency}`;

  const buttons = [
    r.can_submit && {
      key: "submit",
      label: t("submit_for_approval"),
      run: () => actions.submit(),
      successTitle: t("return_submitted", { number: r.return_no }),
    },
    r.can_dispatch && {
      key: "dispatch",
      label: t("dispatch_return"),
      hint: t("dispatch_hint"),
      run: (gatePass) => actions.dispatch(gatePass),
      successTitle: t("return_dispatched", { number: r.return_no, debit_note: "" }),
      reason: { label: t("gate_pass_no"), required: false },
    },
    r.can_cancel && {
      key: "cancel",
      label: t("cancel_document"),
      variant: "outline",
      run: (reason) => actions.cancel(reason),
      successTitle: t("return_cancelled", { number: r.return_no }),
      reason: { label: t("cancel_reason"), required: false },
    },
  ];

  return (
    <div className="grid grid-cols-1 gap-4 lg:grid-cols-12">
      <div className="flex flex-col gap-4 lg:col-span-8">
        {r.stock_moved ? (
          <Alert variant="info">{t("return_stock_moved", { debit_note: r.debit_note_no ?? "" })}</Alert>
        ) : (
          <Alert variant="warning">{t("return_hint")}</Alert>
        )}

        <DocumentLinesTable
          title={t("lines")}
          lines={r.lines}
          columns={[
            { key: "item", header: t("item"), render: (line) => <ItemCell line={line} /> },
            { key: "quantity", header: t("quantity"), align: "right", render: (line) => qty(line.quantity, line.uom?.code) },
            {
              key: "unit_cost",
              header: t("rate"),
              align: "right",
              render: (line) => formatNumber(line.unit_cost, { maximumFractionDigits: 4 }),
            },
            { key: "line_total", header: t("amount"), align: "right", render: (line) => money(line.line_total) },
            {
              key: "where",
              header: t("bin"),
              render: (line) => line.roll_no ?? line.bin?.code ?? (line.lot_no || "—"),
            },
          ]}
        />

        {r.notes ? (
          <Card>
            <CardHeader>
              <CardTitle>{t("notes")}</CardTitle>
            </CardHeader>
            <CardBody className="text-sm whitespace-pre-line">{r.notes}</CardBody>
          </Card>
        ) : null}
      </div>

      <div className="flex flex-col gap-4 lg:col-span-4">
        <DocumentActions buttons={buttons} />

        <Card>
          <CardHeader>
            <CardTitle>{t("purchase_return")}</CardTitle>
          </CardHeader>
          <CardBody className="grid grid-cols-2 gap-x-4 gap-y-3 text-sm">
            <Field label={t("status")}>
              <StatusBadge status={r.status} label={t(`return_statuses.${r.status}`)} />
            </Field>
            <Field label={t("reason")}>{t(`return_reasons.${r.reason}`)}</Field>
            <Field label={t("vendor")}>{r.vendor?.name ?? "—"}</Field>
            <Field label={t("goods_receipt")}>
              {r.goods_receipt ? (
                <Link href={`/procurement/receipts/${r.goods_receipt.id}`} className="text-brand hover:underline">
                  {r.goods_receipt.grn_no}
                </Link>
              ) : (
                "—"
              )}
            </Field>
            <Field label={t("return_date")}>
              <FormattedDateTime value={r.return_date} mode="date" />
            </Field>
            <Field label={t("debit_note_amount")}>{money(r.debit_note_amount)}</Field>
            {/* Allocated at dispatch, not at draft: a cancelled return must
                not leave a hole in the series. */}
            <Field label={t("debit_note_no")}>{r.debit_note_no ?? "—"}</Field>
            <Field label={t("gate_pass_no")}>{r.gate_pass_no ?? "—"}</Field>
          </CardBody>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle>{t("history")}</CardTitle>
          </CardHeader>
          <CardBody className="flex flex-col gap-2 text-sm">
            <HistoryRow label={t("created")} at={r.created_at} />
            <HistoryRow label={t("return_statuses.PENDING_APPROVAL")} at={r.submitted_at} />
            <HistoryRow label={t("return_statuses.APPROVED")} at={r.approved_at} />
            <HistoryRow label={t("dispatched_at")} at={r.dispatched_at} />
            <HistoryRow label={t("return_statuses.REJECTED")} at={r.rejected_at} />
            <HistoryRow label={t("return_statuses.CANCELLED")} at={r.cancelled_at} />
          </CardBody>
        </Card>
      </div>
    </div>
  );
}

export { PurchaseReturnDetail };
