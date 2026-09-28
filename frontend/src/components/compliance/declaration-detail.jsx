"use client";

import Link from "next/link";
import { Card, CardHeader, CardTitle, CardBody } from "@/components/ui/card";
import { Alert } from "@/components/ui/alert";
import { Badge } from "@/components/ui/badge";
import { StatusBadge } from "@/components/ui/status-badge";
import { FormattedDateTime } from "@/components/ui/formatted-date-time";
import { DocumentActions } from "@/components/procurement/document-actions";
import { Field, HistoryRow } from "@/components/procurement/requisition-detail";
import { formatNumber } from "@/lib/format";
import { useT } from "@/lib/i18n";

/**
 * One utilisation declaration, with its reconciliation (§3.2).
 *
 * The variance column is signed on purpose, and the page leans on that:
 * a red number means the factory has drawn more than customs allowed,
 * while a large negative one means bonded goods are sitting with no
 * export to justify them. Those are opposite problems, and an absolute
 * figure would make them look identical.
 */
function DeclarationDetail({ declaration, actions }) {
  const t = useT("compliance");
  const tc = useT("common");
  const d = declaration;

  const buttons = [
    d.can_issue && {
      key: "issue",
      label: t("issue_declaration"),
      run: () => actions.issue(),
      successTitle: t("declaration_issued", { number: d.ud_no }),
    },
    d.can_close && {
      key: "close",
      label: t("close_declaration"),
      variant: "outline",
      run: () => actions.close(),
      successTitle: t("declaration_closed", { number: d.ud_no }),
    },
    d.can_cancel && {
      key: "cancel",
      label: tc("cancel"),
      variant: "outline",
      run: (reason) => actions.cancel(reason),
      successTitle: t("declaration_cancelled", { number: d.ud_no }),
      reason: { label: t("reason"), required: false },
    },
  ];

  const rows = d.reconciliation ?? [];

  return (
    <div className="grid grid-cols-1 gap-4 lg:grid-cols-12">
      <div className="flex flex-col gap-4 lg:col-span-8">
        <Alert variant="info">{t("declaration_hint")}</Alert>

        <Card>
          <CardHeader>
            <CardTitle>{rows.length > 0 ? t("reconciliation") : t("declaration")}</CardTitle>
          </CardHeader>
          <CardBody className="overflow-x-auto">
            <table className="w-full text-sm">
              <thead>
                <tr className="text-left text-xs text-foreground-muted">
                  <th className="py-1">{t("item")}</th>
                  <th className="py-1 text-right">{t("declared_quantity")}</th>
                  <th className="py-1 text-right">{t("wastage_percent")}</th>
                  <th className="py-1 text-right">{t("allowed_quantity")}</th>
                  {rows.length > 0 ? <th className="py-1 text-right">{t("consumed_quantity")}</th> : null}
                  {rows.length > 0 ? <th className="py-1 text-right">{t("variance_quantity")}</th> : null}
                </tr>
              </thead>
              <tbody>
                {(rows.length > 0 ? rows : d.lines).map((line, index) => {
                  const reconciled = rows.length > 0;
                  const over = reconciled && line.exceeds_allowance;

                  return (
                    <tr key={line.line_id ?? line.id ?? index} className="border-t border-border">
                      <td className="py-1.5">
                        <span className="flex flex-wrap items-center gap-1">
                          {line.item_name}{" "}
                          <span className="font-mono text-xs text-foreground-muted">{line.item_code}</span>
                          {over ? <Badge variant="danger">{t("exceeds_allowance")}</Badge> : null}
                        </span>
                      </td>
                      <td className="py-1.5 text-right tabular-nums">{formatNumber(line.declared_quantity)}</td>
                      <td className="py-1.5 text-right tabular-nums text-foreground-muted">
                        {formatNumber(line.wastage_percent, { maximumFractionDigits: 2 })}%
                      </td>
                      <td className="py-1.5 text-right tabular-nums">{formatNumber(line.allowed_quantity)}</td>
                      {reconciled ? (
                        <td className="py-1.5 text-right tabular-nums">{formatNumber(line.consumed_quantity)}</td>
                      ) : null}
                      {reconciled ? (
                        <td
                          className={`py-1.5 text-right tabular-nums ${
                            over ? "font-medium text-danger" : "text-foreground-muted"
                          }`}
                        >
                          {formatNumber(line.variance_quantity)}
                        </td>
                      ) : null}
                    </tr>
                  );
                })}
              </tbody>
            </table>
            {rows.length > 0 ? (
              <p className="mt-2 text-xs text-foreground-muted">{t("reconciliation_hint")}</p>
            ) : null}
          </CardBody>
        </Card>

        {d.notes ? (
          <Card>
            <CardHeader>
              <CardTitle>{t("notes")}</CardTitle>
            </CardHeader>
            <CardBody className="text-sm whitespace-pre-line">{d.notes}</CardBody>
          </Card>
        ) : null}
      </div>

      <div className="flex flex-col gap-4 lg:col-span-4">
        <DocumentActions buttons={buttons} />

        <Card>
          <CardHeader>
            <CardTitle>{t("declaration")}</CardTitle>
          </CardHeader>
          <CardBody className="grid grid-cols-2 gap-x-4 gap-y-3 text-sm">
            <Field label={t("status")}>
              <StatusBadge status={d.status} label={t(`declaration_statuses.${d.status}`)} />
            </Field>
            <Field label={t("ud_type")}>{t(`ud_types.${d.ud_type}`)}</Field>
            <Field label={t("bond_licence")}>
              {d.licence ? (
                <Link href={`/compliance/bond-licences/${d.licence.id}`} className="text-brand hover:underline">
                  {d.licence.licence_no}
                </Link>
              ) : (
                "—"
              )}
            </Field>
            <Field label={t("factory")}>{d.factory?.name ?? "—"}</Field>
            <Field label={t("issued_date")}>
              <FormattedDateTime value={d.issue_date} mode="date" />
            </Field>
            <Field label={t("valid_to")}>
              <FormattedDateTime value={d.valid_to} mode="date" />
            </Field>
            {d.purchase_order ? (
              <Field label={t("purchase_order")}>
                <Link href={`/procurement/orders/${d.purchase_order.id}`} className="text-brand hover:underline">
                  {d.purchase_order.po_no}
                </Link>
              </Field>
            ) : null}
            {d.buyer_reference ? <Field label={t("buyer_reference")}>{d.buyer_reference}</Field> : null}
          </CardBody>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle>{t("history")}</CardTitle>
          </CardHeader>
          <CardBody className="flex flex-col gap-2 text-sm">
            <HistoryRow label={t("created")} at={d.created_at} />
            <HistoryRow label={t("declaration_statuses.ISSUED")} at={d.issued_at} />
            <HistoryRow label={t("declaration_statuses.CLOSED")} at={d.closed_at} />
            <HistoryRow label={t("declaration_statuses.CANCELLED")} at={d.cancelled_at} />
          </CardBody>
        </Card>
      </div>
    </div>
  );
}

export { DeclarationDetail };
