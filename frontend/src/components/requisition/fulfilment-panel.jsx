"use client";

import { useState, useTransition } from "react";
import { useRouter } from "next/navigation";
import { Card, CardHeader, CardTitle, CardBody } from "@/components/ui/card";
import { StatusBadge } from "@/components/ui/status-badge";
import { FormField } from "@/components/ui/form-field";
import { Select } from "@/components/ui/select";
import { Input } from "@/components/ui/input";
import { Button } from "@/components/ui/button";
import { Alert } from "@/components/ui/alert";
import { useToastManager } from "@/components/ui/toast";
import { formatNumber } from "@/lib/format";
import { useT } from "@/lib/i18n";

/**
 * The store counter (Floor/Store Requisition spec §4–§6).
 *
 * One row per line: what the floor asked for, what the store approved, what
 * is genuinely free right now, and where it would come from. "Available
 * now" is the number that matters — it excludes stock held for somebody
 * else, which is the difference between the shelf and what can be given
 * away, and the reason a storekeeper otherwise promises stock twice.
 *
 * Nothing needs to be typed for a full issue: each row defaults to what is
 * still outstanding, drawn from the oldest source. A line left short is not
 * the storekeeper's problem to remember — issuing raises the purchase
 * requisition and the claim by itself.
 */
function FulfilmentPanel({ requisition, availability, actions }) {
  const t = useT("requisition");
  const tc = useT("common");
  const router = useRouter();
  const toastManager = useToastManager();
  const [pending, startTransition] = useTransition();

  const [draft, setDraft] = useState(() => initialDraft(availability));
  const [errors, setErrors] = useState(null);
  const [reason, setReason] = useState("");
  const [rejecting, setRejecting] = useState(false);

  const issuable = availability.filter((line) => Number(line.outstanding_qty) > 0 && line.status !== "REJECTED");

  function set(lineId, patch) {
    setDraft((prev) => ({ ...prev, [lineId]: { ...prev[lineId], ...patch } }));
  }

  function run(work, successTitle) {
    startTransition(async () => {
      const result = await work();

      if (result?.status === "success") {
        toastManager.add({ title: successTitle, type: "success" });
        setErrors(null);
        setRejecting(false);
        router.refresh();
      } else if (result?.status === "error") {
        setErrors(result.errors ?? null);
        toastManager.add({ title: result.message, type: "danger" });
      }
    });
  }

  function issue() {
    const lines = issuable
      .filter((line) => Number(draft[line.line_id]?.quantity) > 0)
      .map((line) => {
        const entry = draft[line.line_id] ?? {};
        const source = sourceOf(line, entry.sourceKey);

        return {
          line_id: line.line_id,
          quantity: line.tracking_mode === "ROLL" ? undefined : entry.quantity,
          bin_id: source?.bin_id,
          lot_no: source?.lot_no ?? undefined,
          fabric_roll_id: source?.fabric_roll_id ?? undefined,
        };
      });

    if (lines.length === 0) {
      toastManager.add({ title: t("no_stock_available"), type: "danger" });
      return;
    }

    run(() => actions.issue(lines), t("requisition_issued", { number: requisition.fr_no }));
  }

  return (
    <Card>
      <CardHeader>
        <CardTitle>{t("fulfilment")}</CardTitle>
      </CardHeader>
      <CardBody className="flex flex-col gap-4">
        <p className="text-xs text-foreground-muted">{t("fulfilment_hint")}</p>

        {errors?.status?.[0] ? <Alert variant="danger">{errors.status[0]}</Alert> : null}

        <div className="overflow-x-auto">
          <table className="w-full min-w-[46rem] text-sm">
            <thead>
              <tr className="border-b border-border text-left text-xs text-foreground-muted">
                <th className="py-2 pr-3 font-medium">{t("item")}</th>
                <th className="py-2 pr-3 text-right font-medium">{t("requested_qty")}</th>
                <th className="py-2 pr-3 text-right font-medium">{t("approved_qty")}</th>
                <th className="py-2 pr-3 text-right font-medium">{t("available_now")}</th>
                <th className="py-2 pr-3 font-medium">{t("source")}</th>
                <th className="py-2 text-right font-medium">{t("issue_qty")}</th>
              </tr>
            </thead>
            <tbody>
              {availability.map((line) => {
                const entry = draft[line.line_id] ?? {};
                const open = Number(line.outstanding_qty) > 0 && line.status !== "REJECTED";

                return (
                  <tr key={line.line_id} className="border-b border-border/60 align-top">
                    <td className="py-2 pr-3">
                      <div className="font-medium text-foreground">{line.item_name}</div>
                      <div className="text-xs text-foreground-muted">{line.item_code}</div>
                      <div className="mt-1 flex flex-wrap items-center gap-1">
                        <StatusBadge status={line.status} label={t(`line_statuses.${line.status}`)} />
                        {Number(line.earmarked_qty) > 0 ? (
                          <span className="text-xs text-foreground-muted">
                            {t("earmarked")}: {formatNumber(line.earmarked_qty, { maximumFractionDigits: 2 })}
                          </span>
                        ) : null}
                      </div>
                    </td>
                    <td className="py-2 pr-3 text-right">{formatNumber(line.requested_qty, { maximumFractionDigits: 2 })}</td>
                    <td className="py-2 pr-3 text-right">
                      {line.approved_qty === null ? "—" : formatNumber(line.approved_qty, { maximumFractionDigits: 2 })}
                    </td>
                    <td className="py-2 pr-3 text-right">{formatNumber(line.available_qty, { maximumFractionDigits: 2 })}</td>
                    <td className="py-2 pr-3">
                      {!open ? (
                        <span className="text-xs text-foreground-muted">—</span>
                      ) : line.sources.length === 0 ? (
                        <span className="text-xs text-danger">{t("no_stock_available")}</span>
                      ) : (
                        <Select
                          value={entry.sourceKey ?? ""}
                          onValueChange={(value) => set(line.line_id, { sourceKey: value })}
                          options={line.sources.map((source) => ({ value: sourceKey(source), label: sourceLabel(source, t) }))}
                        />
                      )}
                    </td>
                    <td className="py-2 text-right">
                      {!open || line.sources.length === 0 ? (
                        <span className="text-xs text-foreground-muted">—</span>
                      ) : line.tracking_mode === "ROLL" ? (
                        <span className="text-xs text-foreground-muted">
                          {formatNumber(sourceOf(line, entry.sourceKey)?.available ?? "0", { maximumFractionDigits: 2 })}
                        </span>
                      ) : (
                        <Input
                          type="number"
                          step="0.01"
                          min="0"
                          className="ml-auto w-28 text-right"
                          value={entry.quantity ?? ""}
                          onChange={(event) => set(line.line_id, { quantity: event.target.value })}
                        />
                      )}
                    </td>
                  </tr>
                );
              })}
            </tbody>
          </table>
        </div>

        {rejecting ? (
          <FormField label={t("reject_reason")} required error={errors?.reason?.[0]}>
            {(fp) => <Input {...fp} value={reason} onChange={(event) => setReason(event.target.value)} maxLength={500} />}
          </FormField>
        ) : null}

        <div className="flex flex-wrap justify-end gap-2">
          {requisition.can_review ? (
            <Button
              type="button"
              variant="outline"
              loading={pending}
              onClick={() => run(() => actions.review(), t("requisition_reviewed", { number: requisition.fr_no }))}
            >
              {t("start_review")}
            </Button>
          ) : null}

          {requisition.can_close ? (
            <Button
              type="button"
              variant="outline"
              loading={pending}
              onClick={() => run(() => actions.close(), t("requisition_closed", { number: requisition.fr_no }))}
            >
              {t("close_document")}
            </Button>
          ) : null}

          {rejecting ? (
            <>
              <Button type="button" variant="outline" onClick={() => setRejecting(false)}>
                {tc("cancel")}
              </Button>
              <Button
                type="button"
                variant="danger"
                loading={pending}
                disabled={reason.trim() === ""}
                onClick={() => run(() => actions.reject(reason), t("requisition_rejected", { number: requisition.fr_no }))}
              >
                {t("reject_document")}
              </Button>
            </>
          ) : (
            <Button type="button" variant="outline" onClick={() => setRejecting(true)}>
              {t("reject_document")}
            </Button>
          )}

          <Button type="button" loading={pending} disabled={issuable.length === 0} onClick={issue}>
            {t("issue")}
          </Button>
        </div>

        {requisition.can_close ? <p className="text-xs text-foreground-muted">{t("close_hint")}</p> : null}
      </CardBody>
    </Card>
  );
}

/**
 * What is still out on the floor (spec §7). A returnable item is lent, not
 * consumed, so the store needs somewhere to take it back — and the
 * requisition cannot close while anything is still listed here.
 */
function BorrowedPanel({ lines, actions }) {
  const t = useT("requisition");
  const router = useRouter();
  const toastManager = useToastManager();
  const [pending, startTransition] = useTransition();

  const outstanding = lines.flatMap((line) =>
    (line.issues ?? [])
      .filter((issue) => Number(issue.outstanding) > 0)
      .map((issue) => ({ ...issue, item_name: line.item_name, uom: line.uom })),
  );

  if (outstanding.length === 0) return null;

  function giveBack(issue) {
    startTransition(async () => {
      const result = await actions.returnStock(issue.id, issue.outstanding);

      if (result?.status === "success") {
        toastManager.add({ title: t("stock_returned"), type: "success" });
        router.refresh();
      } else if (result?.status === "error") {
        toastManager.add({ title: result.message, type: "danger" });
      }
    });
  }

  return (
    <Card>
      <CardHeader>
        <CardTitle>{t("return_to_store")}</CardTitle>
      </CardHeader>
      <CardBody className="flex flex-col gap-3">
        <p className="text-xs text-foreground-muted">{t("borrowed_hint")}</p>

        {outstanding.map((issue) => (
          <div key={issue.id} className="flex flex-wrap items-center justify-between gap-2 border-b border-border/60 pb-2 text-sm last:border-0">
            <div>
              <div className="font-medium text-foreground">{issue.item_name}</div>
              <div className="text-xs text-foreground-muted">
                {issue.bin?.code ?? "—"}
                {issue.roll_no ? ` · ${issue.roll_no}` : ""}
                {issue.lot_no ? ` · ${t("lot")} ${issue.lot_no}` : ""}
              </div>
            </div>
            <div className="flex items-center gap-3">
              <span>{formatNumber(issue.outstanding, { maximumFractionDigits: 2 })} {issue.uom?.code ?? ""}</span>
              <Button type="button" size="sm" variant="outline" loading={pending} onClick={() => giveBack(issue)}>
                {t("return_to_store")}
              </Button>
            </div>
          </div>
        ))}
      </CardBody>
    </Card>
  );
}

/** Each row starts on the oldest source with what is still outstanding, capped at what is there. */
function initialDraft(availability) {
  const draft = {};

  for (const line of availability) {
    const source = line.sources[0];
    const wanted = Number(line.outstanding_qty);
    const there = Number(source?.available ?? 0);

    draft[line.line_id] = {
      sourceKey: source ? sourceKey(source) : "",
      quantity: source ? String(Math.min(wanted, there)) : "",
    };
  }

  return draft;
}

function sourceKey(source) {
  return source.fabric_roll_id ?? `${source.bin_id}|${source.lot_no ?? ""}`;
}

function sourceOf(line, key) {
  return line.sources.find((source) => sourceKey(source) === key) ?? line.sources[0];
}

function sourceLabel(source, t) {
  const bin = source.bin?.code ?? source.bin?.name ?? "—";

  if (source.roll_no) return `${source.roll_no} · ${bin}`;
  if (source.lot_no) return `${bin} · ${t("lot")} ${source.lot_no}`;

  return bin;
}

export { FulfilmentPanel, BorrowedPanel };
