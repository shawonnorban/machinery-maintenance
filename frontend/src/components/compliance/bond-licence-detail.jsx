"use client";

import Link from "next/link";
import { Plus } from "lucide-react";
import { Card, CardHeader, CardTitle, CardBody } from "@/components/ui/card";
import { buttonVariants } from "@/components/ui/button";
import { Alert } from "@/components/ui/alert";
import { Badge } from "@/components/ui/badge";
import { StatusBadge } from "@/components/ui/status-badge";
import { EmptyState } from "@/components/ui/empty-state";
import { FormattedDateTime } from "@/components/ui/formatted-date-time";
import { DocumentActions } from "@/components/procurement/document-actions";
import { Field, HistoryRow } from "@/components/procurement/requisition-detail";
import { formatNumber } from "@/lib/format";
import { cn } from "@/lib/utils";
import { useT } from "@/lib/i18n";

/**
 * One bond licence (Shared Systems spec §3).
 *
 * The utilisation table is the working part: entitlement beside what has
 * actually arrived, computed from goods receipts rather than from a
 * running total somebody maintained. Importing past an entitlement is
 * flagged rather than left as a minus sign to notice.
 */
function BondLicenceDetail({ licence, actions }) {
  const t = useT("compliance");
  const tc = useT("common");
  const l = licence;

  const buttons = [
    l.can_suspend && {
      key: "suspend",
      label: t("suspend_licence"),
      variant: "outline",
      run: (reason) => actions.setStatus("SUSPENDED", reason),
      successTitle: t("licence_status_changed", { number: l.licence_no, status: t("licence_statuses.SUSPENDED") }),
      reason: { label: t("reason"), required: false },
    },
    l.can_restore && {
      key: "restore",
      label: t("restore_licence"),
      run: () => actions.setStatus("ACTIVE"),
      successTitle: t("licence_status_changed", { number: l.licence_no, status: t("licence_statuses.ACTIVE") }),
    },
    l.can_cancel && {
      key: "cancel",
      label: tc("cancel"),
      variant: "outline",
      run: (reason) => actions.setStatus("CANCELLED", reason),
      successTitle: t("licence_status_changed", { number: l.licence_no, status: t("licence_statuses.CANCELLED") }),
      reason: { label: t("reason"), required: false },
    },
  ];

  return (
    <div className="grid grid-cols-1 gap-4 lg:grid-cols-12">
      <div className="flex flex-col gap-4 lg:col-span-8">
        {l.is_live ? (
          <Alert variant="success">{t("licence_hint")}</Alert>
        ) : l.has_expired ? (
          <Alert variant="warning">{t("licence_expired")}</Alert>
        ) : (
          <Alert variant="info">{t("licence_hint")}</Alert>
        )}

        <Card>
          <CardHeader>
            <CardTitle>{t("entitlements")}</CardTitle>
          </CardHeader>
          <CardBody className="overflow-x-auto">
            {l.utilisation.length === 0 ? (
              <EmptyState title={t("entitlements")} description={t("entitlement_hint")} />
            ) : (
              <>
                <table className="w-full text-sm">
                  <thead>
                    <tr className="text-left text-xs text-foreground-muted">
                      <th className="py-1">{t("item")}</th>
                      <th className="py-1">{t("basis")}</th>
                      <th className="py-1 text-right">{t("entitled_quantity")}</th>
                      <th className="py-1 text-right">{t("imported_quantity")}</th>
                      <th className="py-1 text-right">{t("remaining_quantity")}</th>
                      <th className="py-1 text-right">{t("used_percent")}</th>
                    </tr>
                  </thead>
                  <tbody>
                    {l.utilisation.map((row) => (
                      <tr key={row.entitlement_id} className="border-t border-border">
                        <td className="py-1.5">
                          <span className="flex flex-wrap items-center gap-1">
                            {row.item_name}{" "}
                            <span className="font-mono text-xs text-foreground-muted">{row.item_code}</span>
                            {/* The one thing a bond audit looks for. */}
                            {row.exceeded ? <Badge variant="danger">{t("entitlement_exceeded")}</Badge> : null}
                          </span>
                        </td>
                        <td className="py-1.5">{t(`bases.${row.basis}`)}</td>
                        <td className="py-1.5 text-right tabular-nums">{formatNumber(row.entitled_quantity)}</td>
                        <td className="py-1.5 text-right tabular-nums">{formatNumber(row.imported_quantity)}</td>
                        <td
                          className={`py-1.5 text-right tabular-nums ${row.exceeded ? "font-medium text-danger" : ""}`}
                        >
                          {formatNumber(row.remaining_quantity)}
                        </td>
                        <td className="py-1.5 text-right tabular-nums text-foreground-muted">
                          {row.used_percent === null ? "—" : `${formatNumber(row.used_percent, { maximumFractionDigits: 1 })}%`}
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
                <p className="mt-2 text-xs text-foreground-muted">{t("entitlement_hint")}</p>
              </>
            )}
          </CardBody>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle>{t("declarations")}</CardTitle>
            {/* Raising one against a lapsed licence is refused, so the
                button only appears while the licence still stands. */}
            {l.can_edit ? (
              <Link
                href={`/compliance/bond-licences/${l.id}/declarations/create`}
                className={cn(buttonVariants({ variant: "outline", size: "sm" }))}
              >
                <Plus /> {t("new_declaration")}
              </Link>
            ) : null}
          </CardHeader>
          <CardBody className="flex flex-col gap-2 text-sm">
            {l.declarations.length === 0 ? (
              <EmptyState title={t("no_declarations")} description={t("declaration_hint")} />
            ) : (
              l.declarations.map((row) => (
                <div key={row.id} className="flex flex-wrap items-center justify-between gap-2 border-b border-border pb-2 last:border-0">
                  <span className="flex flex-wrap items-center gap-2">
                    <Link
                      href={`/compliance/declarations/${row.id}`}
                      className="font-medium text-brand hover:underline"
                    >
                      {row.ud_no}
                    </Link>
                    <Badge variant="neutral">{t(`ud_types.${row.ud_type}`)}</Badge>
                    <StatusBadge status={row.status} label={t(`declaration_statuses.${row.status}`)} />
                  </span>
                  <span className="flex items-center gap-2 text-foreground-muted">
                    <FormattedDateTime value={row.issue_date} mode="date" />
                  </span>
                </div>
              ))
            )}
          </CardBody>
        </Card>

        {l.notes ? (
          <Card>
            <CardHeader>
              <CardTitle>{t("notes")}</CardTitle>
            </CardHeader>
            <CardBody className="text-sm whitespace-pre-line">{l.notes}</CardBody>
          </Card>
        ) : null}
      </div>

      <div className="flex flex-col gap-4 lg:col-span-4">
        <DocumentActions buttons={buttons} />

        <Card>
          <CardHeader>
            <CardTitle>{t("bond_licence")}</CardTitle>
          </CardHeader>
          <CardBody className="grid grid-cols-2 gap-x-4 gap-y-3 text-sm">
            <Field label={t("status")}>
              <span className="flex flex-wrap items-center gap-1">
                <StatusBadge status={l.status} label={t(`licence_statuses.${l.status}`)} />
                {l.is_live ? <Badge variant="success">{t("licence_live")}</Badge> : null}
              </span>
            </Field>
            <Field label={t("factory")}>{l.factory?.name ?? "—"}</Field>
            <Field label={t("issuing_office")}>{l.issuing_office}</Field>
            <Field label={t("issued_date")}>
              <FormattedDateTime value={l.issued_date} mode="date" />
            </Field>
            <Field label={t("valid_from")}>
              <FormattedDateTime value={l.valid_from} mode="date" />
            </Field>
            <Field label={t("valid_to")}>
              <FormattedDateTime value={l.valid_to} mode="date" />
            </Field>
            {l.is_live ? <Field label={t("days_to_expiry")}>{l.days_to_expiry}</Field> : null}
          </CardBody>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle>{t("history")}</CardTitle>
          </CardHeader>
          <CardBody className="flex flex-col gap-2 text-sm">
            <HistoryRow label={t("created")} at={l.created_at} />
          </CardBody>
        </Card>
      </div>
    </div>
  );
}

export { BondLicenceDetail };
