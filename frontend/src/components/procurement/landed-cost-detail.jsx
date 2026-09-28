"use client";

import { useState } from "react";
import Link from "next/link";
import { Card, CardHeader, CardTitle, CardBody } from "@/components/ui/card";
import { Alert } from "@/components/ui/alert";
import { StatusBadge } from "@/components/ui/status-badge";
import { FormattedDateTime } from "@/components/ui/formatted-date-time";
import { DocumentActions } from "@/components/procurement/document-actions";
import { Field, HistoryRow } from "@/components/procurement/requisition-detail";
import { formatNumber } from "@/lib/format";
import { useT } from "@/lib/i18n";

/**
 * One landed cost voucher (Procurement spec §11).
 *
 * The preview is the whole point of this screen. Posting moves what stock
 * is worth and locks the receipts behind it, and neither can be put back —
 * so the distribution is shown as a table of numbers to check **before**
 * the button is pressed, not as a result afterwards.
 */
function LandedCostDetail({ voucher, preview, actions }) {
  const t = useT("procurement");
  const v = voucher;
  const [rows, setRows] = useState(preview?.lines ?? []);
  const [checked, setChecked] = useState(false);

  const money = (value) => `${formatNumber(value, { maximumFractionDigits: 2 })} ${v.currency}`;

  const buttons = [
    v.can_post && {
      key: "preview",
      label: t("preview_distribution"),
      variant: "outline",
      hint: t("post_warning"),
      run: async () => {
        const result = await actions.preview();
        if (result?.status === "success") {
          setRows(result.data?.lines ?? []);
          setChecked(true);
        }
        return result;
      },
      successTitle: t("preview_ready"),
    },
    v.can_post && {
      key: "post",
      label: t("post_landed_cost"),
      hint: t("post_warning"),
      run: () => actions.post(),
      successTitle: t("landed_cost_posted", { number: v.voucher_no }),
    },
    v.can_cancel && {
      key: "cancel",
      label: t("cancel_document"),
      variant: "outline",
      run: (reason) => actions.cancel(reason),
      successTitle: t("landed_cost_cancelled", { number: v.voucher_no }),
      reason: { label: t("cancel_reason"), required: false },
    },
  ];

  // Posted vouchers keep their allocations; a draft only has the preview.
  const allocations = v.allocations.length > 0 ? v.allocations : rows;
  const posted = v.status === "POSTED";

  return (
    <div className="grid grid-cols-1 gap-4 lg:grid-cols-12">
      <div className="flex flex-col gap-4 lg:col-span-8">
        {!posted && checked ? <Alert variant="info">{t("post_warning")}</Alert> : null}

        <Card>
          <CardHeader>
            <CardTitle>{t("charges")}</CardTitle>
          </CardHeader>
          <CardBody className="flex flex-col gap-2 text-sm">
            {v.charges.map((charge) => (
              <div key={charge.id} className="flex flex-wrap items-baseline justify-between gap-2">
                <span>
                  {t(`charge_types.${charge.charge_type}`)}
                  {charge.label ? ` · ${charge.label}` : ""}
                  {charge.vendor ? ` · ${charge.vendor.name}` : ""}
                </span>
                <span className="tabular-nums">{money(charge.amount)}</span>
              </div>
            ))}
            <div className="flex items-baseline justify-between border-t border-border pt-2 font-medium">
              <span>{t("total_charges")}</span>
              <span className="tabular-nums">{money(v.total_charges)}</span>
            </div>
          </CardBody>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle>{posted ? t("allocation") : t("preview_distribution")}</CardTitle>
          </CardHeader>
          <CardBody className="overflow-x-auto">
            {allocations.length === 0 ? (
              <p className="text-sm text-foreground-muted">{t("preview_empty")}</p>
            ) : (
              <table className="w-full text-sm">
                <thead>
                  <tr className="text-left text-xs text-foreground-muted">
                    <th className="py-1">{t("item")}</th>
                    <th className="py-1 text-right">{t("quantity")}</th>
                    <th className="py-1 text-right">{t("basis_value")}</th>
                    <th className="py-1 text-right">{t("share_amount")}</th>
                    <th className="py-1 text-right">{t("previous_unit_cost")}</th>
                    <th className="py-1 text-right">{t("new_unit_cost")}</th>
                  </tr>
                </thead>
                <tbody>
                  {allocations.map((row, index) => (
                    <tr key={row.id ?? index} className="border-t border-border">
                      <td className="py-1.5">
                        {row.item_name}{" "}
                        <span className="font-mono text-xs text-foreground-muted">{row.item_code}</span>
                      </td>
                      <td className="py-1.5 text-right tabular-nums">{formatNumber(row.quantity)}</td>
                      <td className="py-1.5 text-right tabular-nums">
                        {formatNumber(row.basis_value, { maximumFractionDigits: 2 })}
                      </td>
                      <td className="py-1.5 text-right tabular-nums">
                        {formatNumber(row.share_amount ?? row.share, { maximumFractionDigits: 2 })}
                      </td>
                      <td className="py-1.5 text-right tabular-nums text-foreground-muted">
                        {formatNumber(row.previous_unit_cost ?? row.previous, { maximumFractionDigits: 4 })}
                      </td>
                      <td className="py-1.5 text-right font-medium tabular-nums">
                        {formatNumber(row.new_unit_cost ?? row.new, { maximumFractionDigits: 4 })}
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            )}
          </CardBody>
        </Card>

        {v.notes ? (
          <Card>
            <CardHeader>
              <CardTitle>{t("notes")}</CardTitle>
            </CardHeader>
            <CardBody className="text-sm whitespace-pre-line">{v.notes}</CardBody>
          </Card>
        ) : null}
      </div>

      <div className="flex flex-col gap-4 lg:col-span-4">
        <DocumentActions buttons={buttons} />

        <Card>
          <CardHeader>
            <CardTitle>{t("landed_cost")}</CardTitle>
          </CardHeader>
          <CardBody className="grid grid-cols-2 gap-x-4 gap-y-3 text-sm">
            <Field label={t("status")}>
              <StatusBadge status={v.status} label={t(`landed_cost_statuses.${v.status}`)} />
            </Field>
            <Field label={t("basis")}>{t(`bases.${v.basis}`)}</Field>
            <Field label={t("voucher_date")}>
              <FormattedDateTime value={v.voucher_date} mode="date" />
            </Field>
            <Field label={t("factory")}>{v.factory?.name ?? "—"}</Field>
            <Field label={t("total_charges")}>{money(v.total_charges)}</Field>
          </CardBody>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle>{t("receipts")}</CardTitle>
          </CardHeader>
          <CardBody className="flex flex-col gap-2 text-sm">
            {v.receipts.map((grn) => (
              <div key={grn.id} className="flex items-center justify-between">
                <Link href={`/procurement/receipts/${grn.id}`} className="font-medium text-brand hover:underline">
                  {grn.grn_no}
                </Link>
                <FormattedDateTime value={grn.grn_date} mode="date" />
              </div>
            ))}
            {posted ? <p className="text-xs text-foreground-muted">{t("rate_locked_hint")}</p> : null}
          </CardBody>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle>{t("history")}</CardTitle>
          </CardHeader>
          <CardBody className="flex flex-col gap-2 text-sm">
            <HistoryRow label={t("created")} at={v.created_at} />
            <HistoryRow label={t("landed_cost_statuses.POSTED")} at={v.posted_at} />
            <HistoryRow label={t("landed_cost_statuses.CANCELLED")} at={v.cancelled_at} />
          </CardBody>
        </Card>
      </div>
    </div>
  );
}

export { LandedCostDetail };
