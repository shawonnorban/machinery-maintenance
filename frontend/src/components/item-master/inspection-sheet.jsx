"use client";

import { useState, useTransition } from "react";
import Link from "next/link";
import { Card, CardHeader, CardTitle, CardBody } from "@/components/ui/card";
import { Alert } from "@/components/ui/alert";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { StatusBadge } from "@/components/ui/status-badge";
import { FormattedDateTime } from "@/components/ui/formatted-date-time";
import { useToastManager } from "@/components/ui/toast";
import { DocumentActions } from "@/components/procurement/document-actions";
import { Field } from "@/components/procurement/requisition-detail";
import { RollTallyDialog } from "@/components/item-master/roll-tally-dialog";
import { formatNumber } from "@/lib/format";
import { useT } from "@/lib/i18n";

/**
 * One four-point inspection sheet (Inventory spec §4.3).
 *
 * Laid out the way the mill's own form reads: the lot's verdict at the
 * top, then a row per roll. Rolls that have not been opened yet stay in
 * the table rather than being filtered out — a sample looks finished when
 * the untouched half is hidden, which is exactly the mistake the escalation
 * rule exists to catch.
 */
function InspectionSheet({ inspection, options, actions }) {
  const t = useT("inspection");
  const tc = useT("common");
  const toastManager = useToastManager();
  const [pending, startTransition] = useTransition();
  const [tallyRoll, setTallyRoll] = useState(null);

  const i = inspection;
  const failed = i.result === "FAIL";
  const recorded = i.rolls.filter((roll) => roll.recorded).length;

  const buttons = [
    i.can_submit && {
      key: "submit",
      label: t("submit_sheet"),
      hint: recorded === 0 ? t("nothing_inspected") : undefined,
      disabled: recorded === 0,
      run: () => actions.submit(),
      successTitle: t("inspection_submitted", { number: i.inspection_no }),
    },
    i.can_approve && {
      key: "approve",
      label: t("approve_lot"),
      run: () => actions.approve(),
      successTitle: t("inspection_approved", { number: i.inspection_no }),
    },
    i.can_reject && {
      key: "reject",
      label: t("reject_lot"),
      variant: "outline",
      run: (reason) => actions.reject(reason),
      successTitle: t("inspection_rejected", { number: i.inspection_no }),
      reason: { label: t("reason"), required: false },
    },
    i.can_escalate && {
      key: "escalate",
      label: t("escalate"),
      hint: t("escalation_hint"),
      run: () => actions.escalate(),
      successTitle: t("inspection_escalated", {
        number: i.inspection_no,
        percent: i.next_stage === "FULL" ? "100" : "15",
      }),
    },
    i.can_cancel && {
      key: "cancel",
      label: tc("cancel"),
      variant: "outline",
      run: (reason) => actions.cancel(reason),
      successTitle: t("inspection_statuses.CANCELLED"),
      reason: { label: t("reason"), required: false },
    },
  ];

  function saveRoll(payload) {
    startTransition(async () => {
      const result = await actions.recordRoll(payload);

      if (result?.status === "error") {
        toastManager.add({ type: "danger", title: result.message ?? tc("something_went_wrong") });
        return;
      }

      toastManager.add({ type: "success", title: t("record_roll") });
      setTallyRoll(null);
    });
  }

  return (
    <div className="flex flex-col gap-4">
      {/* The verdict, before anything else — it is what the sheet is for. */}
      <Card>
        <CardBody className="grid grid-cols-2 gap-4 sm:grid-cols-5">
          <Score
            label={t("penalty_percent")}
            value={i.penalty_percent}
            suffix=""
            tone={i.result === null ? "muted" : failed ? "danger" : "success"}
            big
          />
          <Score label={t("acceptance_threshold")} value={i.acceptance_threshold} tone="muted" />
          <Score label={t("total_points")} value={i.total_points} tone="muted" />
          <Score
            label={t("yards_inspected")}
            value={i.yards_inspected}
            hint={`${t("yards_in_lot")}: ${formatNumber(i.yards_in_lot)}`}
            tone="muted"
          />
          <Score
            label={t("rolls_inspected")}
            value={String(recorded)}
            hint={`${t("rolls_in_lot")}: ${i.rolls_in_lot}`}
            tone="muted"
          />
        </CardBody>
      </Card>

      {i.result === null ? (
        <Alert variant="info">{t("four_point_hint")}</Alert>
      ) : failed ? (
        <Alert variant="danger">{t("escalation_hint")}</Alert>
      ) : (
        <Alert variant="success">{t("four_point_hint")}</Alert>
      )}

      <div className="grid grid-cols-1 gap-4 lg:grid-cols-12">
        <div className="flex flex-col gap-4 lg:col-span-8">
          <Card>
            <CardHeader>
              <CardTitle>{t("rolls")}</CardTitle>
            </CardHeader>
            <CardBody className="overflow-x-auto">
              <table className="w-full text-sm">
                <thead>
                  <tr className="text-left text-xs text-foreground-muted">
                    <th className="py-1">{t("roll_no")}</th>
                    <th className="py-1 text-right">{t("actual_width")}</th>
                    <th className="py-1 text-right">{t("marked_length")}</th>
                    <th className="py-1 text-right">{t("inspected_length")}</th>
                    <th className="py-1 text-right">{t("total_points")}</th>
                    <th className="py-1 text-right">{t("penalty_percent")}</th>
                    <th className="py-1">{t("shade_assessment")}</th>
                    {i.can_record ? <th className="py-1" /> : null}
                  </tr>
                </thead>
                <tbody>
                  {i.rolls.map((roll) => (
                    <tr key={roll.id} className="border-t border-border">
                      <td className="py-1.5">
                        <span className="flex flex-wrap items-center gap-1">
                          <span className="font-medium">{roll.roll_no}</span>
                          {roll.inspection_grade ? (
                            <Badge variant="neutral">{roll.inspection_grade}</Badge>
                          ) : null}
                          {/* Untouched rolls stay visible on purpose. */}
                          {roll.recorded ? null : (
                            <Badge variant="warning">{t("not_recorded")}</Badge>
                          )}
                        </span>
                      </td>
                      <td className="py-1.5 text-right tabular-nums">
                        {roll.actual_width === null ? "—" : formatNumber(roll.actual_width, { maximumFractionDigits: 2 })}
                      </td>
                      <td className="py-1.5 text-right tabular-nums text-foreground-muted">
                        {roll.marked_length === null ? "—" : formatNumber(roll.marked_length)}
                      </td>
                      <td className="py-1.5 text-right tabular-nums">
                        <span className="flex items-center justify-end gap-1">
                          {roll.inspected_length === null ? "—" : formatNumber(roll.inspected_length)}
                          {/* A short delivery is paid for twice when nobody
                              writes both numbers down. */}
                          {roll.length_shortfall !== null && Number(roll.length_shortfall) > 0 ? (
                            <Badge variant="danger">−{formatNumber(roll.length_shortfall)}</Badge>
                          ) : null}
                        </span>
                      </td>
                      <td className="py-1.5 text-right tabular-nums">
                        {roll.inspection_points === null ? "—" : formatNumber(roll.inspection_points, { maximumFractionDigits: 0 })}
                      </td>
                      <td
                        className={`py-1.5 text-right tabular-nums ${
                          roll.penalty_percent !== null && Number(roll.penalty_percent) > Number(i.acceptance_threshold)
                            ? "font-medium text-danger"
                            : ""
                        }`}
                      >
                        {roll.penalty_percent === null ? "—" : formatNumber(roll.penalty_percent, { maximumFractionDigits: 2 })}
                      </td>
                      <td className="py-1.5">
                        {roll.shade_assessment === null ? (
                          <span className="text-foreground-muted">—</span>
                        ) : roll.shade_clean ? (
                          <Badge variant="success">{t("results.PASS")}</Badge>
                        ) : (
                          <Badge variant="danger">{t("shade_assessment")}</Badge>
                        )}
                      </td>
                      {i.can_record ? (
                        <td className="py-1.5 text-right">
                          <Button type="button" size="sm" variant="outline" onClick={() => setTallyRoll(roll)}>
                            {t("record_roll")}
                          </Button>
                        </td>
                      ) : null}
                    </tr>
                  ))}
                </tbody>
              </table>
              <p className="mt-2 text-xs text-foreground-muted">{t("defect_tally_hint")}</p>
            </CardBody>
          </Card>

          {i.rolls.some((roll) => roll.defects.length > 0) ? (
            <Card>
              <CardHeader>
                <CardTitle>{t("defect_tally")}</CardTitle>
              </CardHeader>
              <CardBody className="overflow-x-auto">
                <table className="w-full text-sm">
                  <thead>
                    <tr className="text-left text-xs text-foreground-muted">
                      <th className="py-1">{t("roll_no")}</th>
                      <th className="py-1">{t("defects")}</th>
                      <th className="py-1">{t("point_class")}</th>
                      <th className="py-1 text-right">{t("occurrences")}</th>
                      <th className="py-1 text-right">{t("total_points")}</th>
                    </tr>
                  </thead>
                  <tbody>
                    {i.rolls.flatMap((roll) =>
                      roll.defects.map((defect) => (
                        <tr key={`${roll.id}-${defect.defect_id}-${defect.point_class}`} className="border-t border-border">
                          <td className="py-1.5">{roll.roll_no}</td>
                          <td className="py-1.5">{defect.name}</td>
                          <td className="py-1.5 text-foreground-muted">{defect.point_class}</td>
                          <td className="py-1.5 text-right tabular-nums">{defect.occurrences}</td>
                          <td className="py-1.5 text-right tabular-nums">{defect.points}</td>
                        </tr>
                      )),
                    )}
                  </tbody>
                </table>
              </CardBody>
            </Card>
          ) : null}

          {i.remarks ? (
            <Card>
              <CardHeader>
                <CardTitle>{t("inspection_notes")}</CardTitle>
              </CardHeader>
              <CardBody className="text-sm whitespace-pre-line">{i.remarks}</CardBody>
            </Card>
          ) : null}
        </div>

        <div className="flex flex-col gap-4 lg:col-span-4">
          <DocumentActions buttons={buttons} />

          <Card>
            <CardHeader>
              <CardTitle>{t("inspection")}</CardTitle>
            </CardHeader>
            <CardBody className="grid grid-cols-2 gap-x-4 gap-y-3 text-sm">
              <Field label={t("status")}>
                <span className="flex flex-wrap items-center gap-1">
                  <StatusBadge status={i.status} label={t(`inspection_statuses.${i.status}`)} />
                  {i.result ? (
                    <Badge variant={failed ? "danger" : "success"}>{t(`results.${i.result}`)}</Badge>
                  ) : null}
                </span>
              </Field>
              <Field label={t("stage")}>
                {t(`stages.${i.stage}`)} · {formatNumber(i.sampling_percent, { maximumFractionDigits: 0 })}%
              </Field>
              <Field label={t("item")}>{i.item?.name ?? "—"}</Field>
              <Field label={t("supplier")}>{i.vendor?.name ?? "—"}</Field>
              <Field label={t("goods_receipt")}>
                {i.goods_receipt ? (
                  <Link href={`/procurement/receipts/${i.goods_receipt.id}`} className="text-brand hover:underline">
                    {i.goods_receipt.grn_no}
                  </Link>
                ) : (
                  "—"
                )}
              </Field>
              <Field label={t("inspection_date")}>
                <FormattedDateTime value={i.inspection_date} mode="date" />
              </Field>
              {i.buyer_reference ? <Field label={t("buyer_reference")}>{i.buyer_reference}</Field> : null}
              {i.style_reference ? <Field label={t("style_reference")}>{i.style_reference}</Field> : null}
              {i.colour ? <Field label={t("colour")}>{i.colour}</Field> : null}
              {i.lot_no ? <Field label={t("lot_no")}>{i.lot_no}</Field> : null}
              {i.supersedes ? (
                <Field label={t("supersedes")}>
                  <Link href={`/item-master/inspections/${i.supersedes.id}`} className="text-brand hover:underline">
                    {i.supersedes.inspection_no}
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
              <SignOff label={t("inspected_by")} by={i.inspected_by} at={i.inspected_at} />
              <SignOff label={t("verified_by")} by={i.verified_by} at={i.verified_at} />
              <SignOff label={t("approved_by")} by={i.approved_by} at={i.approved_at} />
            </CardBody>
          </Card>
        </div>
      </div>

      {tallyRoll ? (
        <RollTallyDialog
          roll={tallyRoll}
          options={options}
          pending={pending}
          onClose={() => setTallyRoll(null)}
          onSubmit={saveRoll}
        />
      ) : null}
    </div>
  );
}

/**
 * One signature off the paper form. The name matters as much as the time:
 * the five boxes on the mill's sheet exist so that somebody can be asked
 * about a decision later, which a bare timestamp cannot support.
 */
function SignOff({ label, by, at }) {
  if (!at) return null;

  return (
    <div className="flex items-center justify-between gap-2">
      <span className="text-foreground-muted">{label}</span>
      <span className="text-right">
        {by ? <span className="mr-2">{by}</span> : null}
        <span className="text-xs text-foreground-subtle">
          <FormattedDateTime value={at} />
        </span>
      </span>
    </div>
  );
}

/** One figure from the sheet's summary strip. */
function Score({ label, value, hint, tone, big }) {
  const colour = tone === "danger" ? "text-danger" : tone === "success" ? "text-success" : "text-foreground";

  return (
    <div className="flex flex-col gap-0.5">
      <span className="text-xs text-foreground-muted">{label}</span>
      <span className={`tabular-nums ${big ? "text-2xl font-semibold" : "text-lg"} ${colour}`}>
        {value === null ? "—" : formatNumber(value, { maximumFractionDigits: 2 })}
      </span>
      {hint ? <span className="text-xs text-foreground-subtle">{hint}</span> : null}
    </div>
  );
}

export { InspectionSheet };
