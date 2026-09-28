"use client";

import { useState } from "react";
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
 * One fabric booking (Procurement spec §3).
 *
 * Confirming can come back with over-booking warnings; they are shown, not
 * enforced. A merchandiser books against a forecast that moves, and a
 * system that refuses the booking is a system people work around — so the
 * warning stays on the page afterwards rather than flashing past in a toast.
 */
function FabricBookingDetail({ booking, actions }) {
  const t = useT("procurement");
  const b = booking;
  const [warnings, setWarnings] = useState([]);

  const buttons = [
    b.can_confirm && {
      key: "confirm",
      label: t("confirm_booking"),
      run: async () => {
        const result = await actions.confirm();
        setWarnings(result?.data?.warnings ?? []);
        return result;
      },
      successTitle: t("booking_confirmed", { number: b.booking_no }),
    },
    b.can_convert && {
      key: "convert",
      label: t("convert_booking"),
      variant: "outline",
      run: () => actions.convert(),
      successTitle: t("booking_converted", { number: b.booking_no, pr_no: "" }),
    },
    b.can_cancel && {
      key: "cancel",
      label: t("cancel_booking"),
      variant: "outline",
      run: (reason) => actions.cancel(reason),
      successTitle: t("booking_cancelled", { number: b.booking_no }),
      reason: { label: t("cancel_reason"), required: false },
    },
  ];

  return (
    <div className="grid grid-cols-1 gap-4 lg:grid-cols-12">
      <div className="flex flex-col gap-4 lg:col-span-8">
        {warnings.length > 0 ? (
          <Alert variant="warning">
            <div className="flex flex-col gap-1">
              <span className="font-medium">{t("over_booking_title")}</span>
              {warnings.map((warning, index) => (
                <span key={index} className="text-sm">{warning.message}</span>
              ))}
              <span className="text-xs">{t("over_booking_hint")}</span>
            </div>
          </Alert>
        ) : null}

        <DocumentLinesTable
          title={t("lines")}
          lines={b.lines}
          columns={[
            { key: "item", header: t("item"), render: (line) => <ItemCell line={line} /> },
            { key: "booked_qty", header: t("booked_qty"), align: "right", render: (line) => qty(line.booked_qty, line.uom?.code) },
            {
              key: "tentative_rate",
              header: t("tentative_rate"),
              align: "right",
              render: (line) => (line.tentative_rate === null ? "—" : formatNumber(line.tentative_rate, { maximumFractionDigits: 2 })),
            },
            {
              key: "line_total",
              header: t("amount"),
              align: "right",
              render: (line) => `${formatNumber(line.line_total, { maximumFractionDigits: 2 })} ${b.currency}`,
            },
            {
              key: "reservation_status",
              header: t("reservation_status"),
              render: (line) =>
                line.reservation_status
                  ? <StatusBadge status={line.reservation_status} label={line.reservation_status} />
                  : "—",
            },
          ]}
        />

        {b.notes ? (
          <Card>
            <CardHeader>
              <CardTitle>{t("notes")}</CardTitle>
            </CardHeader>
            <CardBody className="text-sm whitespace-pre-line">{b.notes}</CardBody>
          </Card>
        ) : null}
      </div>

      <div className="flex flex-col gap-4 lg:col-span-4">
        <DocumentActions buttons={buttons} />

        <Card>
          <CardHeader>
            <CardTitle>{t("fabric_booking")}</CardTitle>
          </CardHeader>
          <CardBody className="grid grid-cols-2 gap-x-4 gap-y-3 text-sm">
            <Field label={t("status")}>
              <StatusBadge status={b.status} label={t(`booking_statuses.${b.status}`)} />
            </Field>
            <Field label={t("vendor")}>{b.vendor?.name ?? "—"}</Field>
            <Field label={t("production_order")}>
              {b.production_order ? (
                <Link href={`/production/orders/${b.production_order.id}`} className="text-brand hover:underline">
                  {b.production_order.order_no}
                </Link>
              ) : "—"}
            </Field>
            <Field label={t("style")}>{b.style?.style_no ?? "—"}</Field>
            <Field label={t("booking_date")}><FormattedDateTime value={b.booking_date} mode="date" /></Field>
            <Field label={t("required_date")}><FormattedDateTime value={b.required_date} mode="date" /></Field>
            <Field label={t("factory")}>{b.factory?.name ?? "—"}</Field>
            {b.purchase_requisition ? (
              <Field label={t("purchase_requisitions")}>
                <Link href={`/procurement/requisitions/${b.purchase_requisition.id}`} className="text-brand hover:underline">
                  {b.purchase_requisition.pr_no}
                </Link>
              </Field>
            ) : null}
          </CardBody>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle>{t("history")}</CardTitle>
          </CardHeader>
          <CardBody className="flex flex-col gap-2 text-sm">
            <HistoryRow label={t("created")} at={b.created_at} />
            <HistoryRow label={t("booking_statuses.CONFIRMED")} at={b.confirmed_at} />
            <HistoryRow label={t("booking_statuses.CONVERTED")} at={b.converted_at} />
            <HistoryRow label={t("booking_statuses.CANCELLED")} at={b.cancelled_at} />
          </CardBody>
        </Card>
      </div>
    </div>
  );
}

export { FabricBookingDetail };
