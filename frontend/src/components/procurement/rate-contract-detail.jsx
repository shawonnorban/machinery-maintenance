"use client";

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
 * One rate contract (Procurement spec §14).
 *
 * The banner is the useful part of this page. "Active" and "applies today"
 * are different things — a contract keeps its status after its dates run
 * out — and a buyer looking at the screen needs the second, not the first.
 */
function RateContractDetail({ contract, actions }) {
  const t = useT("procurement");
  const c = contract;

  const buttons = [
    c.can_activate && {
      key: "activate",
      label: t("activate_contract"),
      run: () => actions.activate(),
      successTitle: t("contract_activated", { number: c.contract_no }),
    },
    c.can_cancel && {
      key: "cancel",
      label: t("cancel_document"),
      variant: "outline",
      run: (reason) => actions.cancel(reason),
      successTitle: t("contract_cancelled", { number: c.contract_no }),
      reason: { label: t("cancel_reason"), required: false },
    },
  ];

  const notStarted = c.status === "ACTIVE" && !c.is_live && !c.has_expired;

  return (
    <div className="grid grid-cols-1 gap-4 lg:grid-cols-12">
      <div className="flex flex-col gap-4 lg:col-span-8">
        {c.is_live ? (
          <Alert variant="success">{t("contract_hint")}</Alert>
        ) : c.has_expired ? (
          <Alert variant="warning">{t("contract_expired")}</Alert>
        ) : notStarted ? (
          <Alert variant="info">{t("contract_not_started")}</Alert>
        ) : null}

        <Card>
          <CardHeader>
            <CardTitle>{t("agreed_rate")}</CardTitle>
          </CardHeader>
          <CardBody className="overflow-x-auto">
            <table className="w-full text-sm">
              <thead>
                <tr className="text-left text-xs text-foreground-muted">
                  <th className="py-1">{t("item")}</th>
                  <th className="py-1 text-right">{t("min_quantity")}</th>
                  <th className="py-1 text-right">{t("agreed_rate")}</th>
                  <th className="py-1 text-right">{t("lead_time_days")}</th>
                  <th className="py-1">{t("notes")}</th>
                </tr>
              </thead>
              <tbody>
                {c.lines.map((line) => (
                  <tr key={line.id} className="border-t border-border">
                    <td className="py-1.5">
                      {line.item_name}{" "}
                      <span className="font-mono text-xs text-foreground-muted">{line.item_code}</span>
                    </td>
                    <td className="py-1.5 text-right tabular-nums">
                      {Number(line.min_quantity) === 0 ? (
                        <span className="text-foreground-muted">—</span>
                      ) : (
                        <>
                          {formatNumber(line.min_quantity)} {line.uom?.code ?? ""}
                        </>
                      )}
                    </td>
                    <td className="py-1.5 text-right font-medium tabular-nums">
                      {formatNumber(line.rate, { maximumFractionDigits: 4 })} {c.currency}
                    </td>
                    <td className="py-1.5 text-right tabular-nums text-foreground-muted">
                      {line.lead_time_days ?? "—"}
                    </td>
                    <td className="py-1.5 text-foreground-muted">{line.notes ?? "—"}</td>
                  </tr>
                ))}
              </tbody>
            </table>
            <p className="mt-2 text-xs text-foreground-muted">{t("min_quantity_hint")}</p>
          </CardBody>
        </Card>

        {c.notes ? (
          <Card>
            <CardHeader>
              <CardTitle>{t("notes")}</CardTitle>
            </CardHeader>
            <CardBody className="text-sm whitespace-pre-line">{c.notes}</CardBody>
          </Card>
        ) : null}
      </div>

      <div className="flex flex-col gap-4 lg:col-span-4">
        <DocumentActions buttons={buttons} />

        <Card>
          <CardHeader>
            <CardTitle>{t("rate_contract")}</CardTitle>
          </CardHeader>
          <CardBody className="grid grid-cols-2 gap-x-4 gap-y-3 text-sm">
            <Field label={t("status")}>
              <span className="flex flex-wrap items-center gap-1">
                <StatusBadge status={c.status} label={t(`contract_statuses.${c.status}`)} />
                {c.is_live ? <Badge variant="success">{t("contract_live")}</Badge> : null}
              </span>
            </Field>
            <Field label={t("vendor")}>{c.vendor?.name ?? "—"}</Field>
            <Field label={t("factory")}>{c.company_wide ? t("company_wide") : (c.factory?.name ?? "—")}</Field>
            <Field label={t("currency")}>{c.currency}</Field>
            <Field label={t("valid_from")}>
              <FormattedDateTime value={c.valid_from} mode="date" />
            </Field>
            <Field label={t("valid_to")}>
              <FormattedDateTime value={c.valid_to} mode="date" />
            </Field>
            {c.is_live ? <Field label={t("days_to_expiry")}>{c.days_to_expiry}</Field> : null}
            {c.supplier_reference ? (
              <Field label={t("supplier_reference")}>{c.supplier_reference}</Field>
            ) : null}
          </CardBody>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle>{t("history")}</CardTitle>
          </CardHeader>
          <CardBody className="flex flex-col gap-2 text-sm">
            <HistoryRow label={t("created")} at={c.created_at} />
            <HistoryRow label={t("contract_statuses.ACTIVE")} at={c.activated_at} />
            <HistoryRow label={t("contract_statuses.CANCELLED")} at={c.cancelled_at} />
          </CardBody>
        </Card>
      </div>
    </div>
  );
}

export { RateContractDetail };
