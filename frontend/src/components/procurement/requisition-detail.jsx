"use client";

import Link from "next/link";
import { Card, CardHeader, CardTitle, CardBody } from "@/components/ui/card";
import { Alert } from "@/components/ui/alert";
import { StatusBadge } from "@/components/ui/status-badge";
import { FormattedDateTime } from "@/components/ui/formatted-date-time";
import { DocumentLinesTable, ItemCell, qty } from "@/components/procurement/document-lines-table";
import { DocumentActions } from "@/components/procurement/document-actions";
import { formatNumber } from "@/lib/format";
import { useT } from "@/lib/i18n";

/** The body of a requisition's detail page (Procurement spec §5): lines, warnings, actions, history. */
function RequisitionDetail({ requisition, actions }) {
  const t = useT("procurement");
  const r = requisition;
  const approval = r.approval_request;

  const money = (value) => `${formatNumber(value, { maximumFractionDigits: 2 })} ${r.currency}`;

  const buttons = [
    r.can_submit && { key: "submit", label: t("submit_for_approval"), run: () => actions.submit(), successTitle: t("requisition_submitted", { number: r.pr_no }) },
    approval?.can_act && {
      key: "approve",
      label: t("approve"),
      variant: "success",
      run: (comment) => actions.approve(approval.id, comment),
      successTitle: t("requisition_approved", { number: r.pr_no }),
      reason: { label: t("comment"), required: false },
    },
    approval?.can_act && {
      key: "reject",
      label: t("reject"),
      variant: "danger",
      run: (comment) => actions.reject(approval.id, comment),
      successTitle: t("requisition_rejected", { number: r.pr_no }),
      reason: { label: t("reject_reason"), required: true },
    },
    r.can_create_order && {
      key: "order",
      label: t("create_po"),
      run: async () => ({ status: "success", redirect: `/procurement/requisitions/${r.id}/order` }),
      successTitle: t("create_po"),
    },
    r.can_cancel && {
      key: "cancel",
      label: t("cancel_document"),
      variant: "outline",
      run: (reason) => actions.cancel(reason),
      successTitle: t("requisition_cancelled", { number: r.pr_no }),
      reason: { label: t("cancel_reason"), required: false },
    },
  ];

  return (
    <div className="grid grid-cols-1 gap-4 lg:grid-cols-12">
      <div className="flex flex-col gap-4 lg:col-span-8">
        {r.open_duplicates?.length ? (
          <Alert variant="warning" title={t("open_duplicates")}>
            <p className="text-xs">{t("open_duplicates_hint")}</p>
            <ul className="mt-1 text-sm">
              {r.open_duplicates.map((d) => (
                <li key={d.id}>
                  <Link href={`/procurement/requisitions/${d.id}`} className="text-brand hover:underline">{d.pr_no}</Link>
                  <span className="text-foreground-muted"> · {t(`statuses.${d.status}`)} · {d.items.join(", ")}</span>
                </li>
              ))}
            </ul>
          </Alert>
        ) : null}

        <DocumentLinesTable
          title={t("lines")}
          lines={r.lines}
          columns={[
            { key: "item", header: t("item"), render: (line) => <ItemCell line={line} /> },
            { key: "requested_qty", header: t("requested_qty"), align: "right", render: (line) => qty(line.requested_qty, line.uom?.code) },
            { key: "ordered_qty", header: t("ordered_qty"), align: "right", render: (line) => qty(line.ordered_qty, line.uom?.code) },
            { key: "current_stock_qty", header: t("current_stock"), align: "right", render: (line) => qty(line.current_stock_qty, line.uom?.code) },
            { key: "already_ordered_qty", header: t("already_ordered"), align: "right", render: (line) => qty(line.already_ordered_qty, line.uom?.code) },
            { key: "estimated_rate", header: t("estimated_rate"), align: "right", render: (line) => (line.estimated_rate === null ? "—" : formatNumber(line.estimated_rate, { maximumFractionDigits: 2 })) },
            { key: "estimated_amount", header: t("amount"), align: "right", render: (line) => formatNumber(line.estimated_amount, { maximumFractionDigits: 2 }) },
            { key: "required_date", header: t("required_date"), render: (line) => (line.required_date ? <FormattedDateTime value={line.required_date} mode="date" /> : "—") },
          ]}
          footer={{ item: t("estimated_total"), estimated_amount: formatNumber(r.lines.reduce((s, l) => s + Number(l.estimated_amount), 0), { maximumFractionDigits: 2 }) }}
        />

        {r.notes || r.rejection_reason ? (
          <Card>
            <CardHeader>
              <CardTitle>{t("notes")}</CardTitle>
            </CardHeader>
            <CardBody className="flex flex-col gap-2 text-sm">
              {r.notes ? <p className="whitespace-pre-line">{r.notes}</p> : null}
              {r.rejection_reason ? <p className="text-danger">{r.rejection_reason}</p> : null}
            </CardBody>
          </Card>
        ) : null}
      </div>

      <div className="flex flex-col gap-4 lg:col-span-4">
        <DocumentActions buttons={buttons} />

        <Card>
          <CardHeader>
            <CardTitle>{t("purchase_requisition")}</CardTitle>
          </CardHeader>
          <CardBody className="grid grid-cols-2 gap-x-4 gap-y-3 text-sm">
            <Field label={t("status")}><StatusBadge status={r.status} label={t(`statuses.${r.status}`)} /></Field>
            <Field label={t("approval")}>
              <StatusBadge status={r.approval_status} label={t(`approval_statuses.${r.approval_status}`)} />
              {approval ? <span className="ml-1 text-xs text-foreground-muted">{approval.current_step}/{approval.total_steps}</span> : null}
            </Field>
            <Field label={t("factory")}>{r.factory?.name ?? "—"}</Field>
            <Field label={t("store")}>{r.store?.name ?? "—"}</Field>
            <Field label={t("date")}><FormattedDateTime value={r.pr_date} mode="date" /></Field>
            <Field label={t("pr_type")}>{t(`pr_types.${r.pr_type}`)}</Field>
            <Field label={t("priority")}>{t(`priorities.${r.priority}`)}</Field>
            <Field label={t("estimated_total")}>{money(r.estimated_total)}</Field>
            <Field label={t("requested_by")}>{r.requester?.name ?? "—"}</Field>
          </CardBody>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle>{t("history")}</CardTitle>
          </CardHeader>
          <CardBody className="flex flex-col gap-2 text-sm">
            <HistoryRow label={t("created")} at={r.created_at} />
            <HistoryRow label={t("submitted_at")} at={r.submitted_at} />
            <HistoryRow label={t("approved_at")} at={r.approved_at} />
            <HistoryRow label={t("statuses.REJECTED")} at={r.rejected_at} />
            <HistoryRow label={t("statuses.CANCELLED")} at={r.cancelled_at} />
          </CardBody>
        </Card>
      </div>
    </div>
  );
}

function Field({ label, children }) {
  return (
    <div className="flex flex-col gap-1">
      <span className="text-xs text-foreground-muted">{label}</span>
      <span>{children}</span>
    </div>
  );
}

function HistoryRow({ label, at }) {
  if (!at) return null;
  return (
    <div className="flex items-center justify-between">
      <span className="text-foreground-muted">{label}</span>
      <span><FormattedDateTime value={at} /></span>
    </div>
  );
}

export { RequisitionDetail, Field, HistoryRow };
