"use client";

import { Card, CardHeader, CardTitle, CardBody } from "@/components/ui/card";
import { Alert } from "@/components/ui/alert";
import { StatusBadge } from "@/components/ui/status-badge";
import { FormattedDateTime } from "@/components/ui/formatted-date-time";
import { DocumentLinesTable, ItemCell, qty } from "@/components/procurement/document-lines-table";
import { DocumentActions } from "@/components/procurement/document-actions";
import { Field, HistoryRow } from "@/components/procurement/requisition-detail";
import { FulfilmentPanel, BorrowedPanel } from "@/components/requisition/fulfilment-panel";
import Link from "next/link";
import { useT } from "@/lib/i18n";

/**
 * The body of a floor requisition's detail page (Floor/Store Requisition
 * spec §3–§7). The floor sees what it asked for and what came back; the
 * store additionally gets the counter — the fulfilment panel — which is
 * only rendered for somebody who may actually issue.
 */
function FloorRequisitionDetail({ requisition, availability, actions }) {
  const t = useT("requisition");
  const r = requisition;

  const buttons = [
    r.can_submit && { key: "submit", label: t("submit"), run: () => actions.submit(), successTitle: t("requisition_submitted", { number: r.fr_no }) },
    r.can_cancel && {
      key: "cancel", label: t("cancel_document"), variant: "outline",
      run: (reason) => actions.cancel(reason), successTitle: t("requisition_cancelled", { number: r.fr_no }),
      reason: { label: t("cancel_reason"), required: false },
    },
  ];

  return (
    <div className="grid grid-cols-1 gap-4 lg:grid-cols-12">
      <div className="flex flex-col gap-4 lg:col-span-8">
        <DocumentLinesTable
          title={t("lines")}
          lines={r.lines}
          columns={[
            { key: "item", header: t("item"), render: (line) => <ItemCell line={line} /> },
            { key: "requested_qty", header: t("requested_qty"), align: "right", render: (line) => qty(line.requested_qty, line.uom?.code) },
            { key: "approved_qty", header: t("approved_qty"), align: "right", render: (line) => qty(line.approved_qty, line.uom?.code) },
            { key: "issued_qty", header: t("issued_qty"), align: "right", render: (line) => qty(line.issued_qty, line.uom?.code) },
            { key: "shortage_qty", header: t("shortage_qty"), align: "right", render: (line) => qty(line.shortage_qty, line.uom?.code) },
            {
              key: "status",
              header: t("status"),
              render: (line) => <StatusBadge status={line.status} label={t(`line_statuses.${line.status}`)} />,
            },
            { key: "purpose", header: t("line_purpose"), render: (line) => line.purpose ?? "—" },
          ]}
        />

        {r.can_issue && availability ? (
          <FulfilmentPanel requisition={r} availability={availability} actions={actions} />
        ) : null}

        {r.can_issue ? <BorrowedPanel lines={r.lines} actions={actions} /> : null}

        {r.rejection_reason ? <Alert variant="danger">{r.rejection_reason}</Alert> : null}

        {r.purpose ? (
          <Card>
            <CardHeader>
              <CardTitle>{t("purpose")}</CardTitle>
            </CardHeader>
            <CardBody className="text-sm whitespace-pre-line">{r.purpose}</CardBody>
          </Card>
        ) : null}
      </div>

      <div className="flex flex-col gap-4 lg:col-span-4">
        <DocumentActions buttons={buttons} />

        <Card>
          <CardHeader>
            <CardTitle>{t("floor_requisition")}</CardTitle>
          </CardHeader>
          <CardBody className="grid grid-cols-2 gap-x-4 gap-y-3 text-sm">
            <Field label={t("status")}><StatusBadge status={r.status} label={t(`statuses.${r.status}`)} /></Field>
            <Field label={t("priority")}>{t(`priorities.${r.priority}`)}</Field>
            <Field label={t("item_department")}>{r.target_item_department?.name ?? "—"}</Field>
            <Field label={t("your_department")}>{r.requester_department?.name ?? "—"}</Field>
            <Field label={t("factory")}>{r.factory?.name ?? "—"}</Field>
            <Field label={t("date")}><FormattedDateTime value={r.fr_date} mode="date" /></Field>
            <Field label={t("requested_by")}>{r.requester?.name ?? "—"}</Field>
            <Field label={t("reviewed_by")}>{r.reviewed_by?.name ?? "—"}</Field>
            {r.shortage_pr ? (
              <Field label={t("shortage_pr")}>
                <Link href={`/purchase-requisitions/${r.shortage_pr.id}`} className="text-brand hover:underline">
                  {r.shortage_pr.pr_no}
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
            <HistoryRow label={t("created")} at={r.created_at} />
            <HistoryRow label={t("submitted_at")} at={r.submitted_at} />
            <HistoryRow label={t("reviewed_at")} at={r.reviewed_at} />
            <HistoryRow label={t("closed_at")} at={r.closed_at} />
            <HistoryRow label={t("statuses.CANCELLED")} at={r.cancelled_at} />
          </CardBody>
        </Card>
      </div>
    </div>
  );
}

export { FloorRequisitionDetail };
