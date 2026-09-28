"use client";

import { useState, useTransition } from "react";
import { Plus } from "lucide-react";
import { Card, CardHeader, CardTitle, CardBody } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Alert } from "@/components/ui/alert";
import { Badge } from "@/components/ui/badge";
import { StatusBadge } from "@/components/ui/status-badge";
import { FormattedDateTime } from "@/components/ui/formatted-date-time";
import { useToastManager } from "@/components/ui/toast";
import { DocumentActions } from "@/components/procurement/document-actions";
import { FormDialog } from "@/components/procurement/form-dialog";
import { Field, HistoryRow } from "@/components/procurement/requisition-detail";
import { formatNumber } from "@/lib/format";
import { useT } from "@/lib/i18n";

/**
 * One count sheet (Inventory spec §12).
 *
 * While the sheet is being walked it is a data-entry screen and nothing
 * else: one box per shelf, and on a blind count the system figure is not
 * merely hidden — the API never sent it. Once counting closes the same
 * table turns into the variance report, because at that point the gap
 * between the two numbers is the entire subject.
 */
function StockCountDetail({ count, items, actions }) {
  const t = useT("itemmaster");
  const tc = useT("common");
  const toastManager = useToastManager();
  const [pending, startTransition] = useTransition();
  const [dialog, setDialog] = useState(null);
  const [entries, setEntries] = useState({});

  const c = count;
  const reveal = c.reveals_system_qty;
  const dirty = Object.keys(entries).length > 0;

  async function run(fn, successTitle) {
    const result = await fn();

    if (result?.status === "error") {
      toastManager.add({ type: "danger", title: result.message ?? tc("something_went_wrong") });
      return false;
    }

    toastManager.add({ type: "success", title: successTitle });
    setDialog(null);
    return true;
  }

  function saveCounts() {
    startTransition(async () => {
      const payload = Object.entries(entries)
        .filter(([, value]) => value !== "")
        .map(([lineId, value]) => ({ line_id: lineId, counted_qty: value }));

      if (payload.length === 0) return;

      const ok = await run(() => actions.record(payload), t("count_recorded"));
      if (ok) setEntries({});
    });
  }

  const buttons = [
    c.can_submit && {
      key: "submit",
      label: t("submit_count"),
      hint: c.uncounted_count > 0 ? t("uncounted_lines", { count: c.uncounted_count }) : undefined,
      run: () => actions.submit(),
      successTitle: t("count_submitted", { number: c.count_no }),
    },
    c.can_post && {
      key: "post",
      label: t("post_count"),
      hint: t("post_count_hint"),
      run: () => actions.post(),
      successTitle: t("count_posted", { number: c.count_no }),
    },
    c.can_cancel && {
      key: "cancel",
      label: t("cancel_document"),
      variant: "outline",
      run: (reason) => actions.cancel(reason),
      successTitle: t("count_cancelled", { number: c.count_no }),
      reason: { label: t("cancel_reason"), required: false },
    },
  ];

  return (
    <div className="grid grid-cols-1 gap-4 lg:grid-cols-12">
      <div className="flex flex-col gap-4 lg:col-span-8">
        {c.can_record && c.is_blind ? <Alert variant="info">{t("blind_count_hint")}</Alert> : null}
        {!c.can_record && c.status === "COUNTING" ? <Alert variant="warning">{t("count_hint")}</Alert> : null}

        <Card>
          <CardHeader>
            <CardTitle>{t("lines")}</CardTitle>
            <div className="flex flex-wrap items-center gap-2">
              {c.uncounted_count > 0 ? (
                <Badge variant="warning">{t("uncounted_lines", { count: c.uncounted_count })}</Badge>
              ) : null}
              {reveal && c.variance_count > 0 ? (
                <Badge variant="danger">{t("variance_lines", { count: c.variance_count })}</Badge>
              ) : null}
              {c.can_record ? (
                <Button size="sm" variant="outline" onClick={() => setDialog({ kind: "found" })}>
                  <Plus /> {t("record_found")}
                </Button>
              ) : null}
            </div>
          </CardHeader>
          <CardBody className="overflow-x-auto">
            <table className="w-full text-sm">
              <thead>
                <tr className="text-left text-xs text-foreground-muted">
                  <th className="py-1">{t("item")}</th>
                  <th className="py-1">{t("bin")}</th>
                  {reveal ? <th className="py-1 text-right">{t("system_qty")}</th> : null}
                  <th className="py-1 text-right">{t("counted_qty")}</th>
                  {reveal ? <th className="py-1 text-right">{t("variance_qty")}</th> : null}
                  {reveal ? <th className="py-1 text-right">{t("variance_value")}</th> : null}
                  <th className="py-1">{t("status")}</th>
                </tr>
              </thead>
              <tbody>
                {c.lines.map((line) => (
                  <tr key={line.id} className="border-t border-border">
                    <td className="py-1.5">
                      <span className="flex flex-wrap items-center gap-1">
                        {line.item_name}{" "}
                        <span className="font-mono text-xs text-foreground-muted">{line.item_code}</span>
                        {line.is_unexpected ? <Badge variant="info">{t("unexpected_stock")}</Badge> : null}
                      </span>
                      {line.lot_no ? <span className="text-xs text-foreground-muted">{line.lot_no}</span> : null}
                    </td>
                    <td className="py-1.5">{line.bin?.code ?? "—"}</td>
                    {reveal ? (
                      <td className="py-1.5 text-right tabular-nums text-foreground-muted">
                        {formatNumber(line.system_qty)}
                      </td>
                    ) : null}
                    <td className="py-1.5 text-right">
                      {c.can_record ? (
                        <Input
                          type="number"
                          step="any"
                          min="0"
                          className="ml-auto max-w-[120px] text-right"
                          placeholder={t("counted_qty")}
                          value={entries[line.id] ?? (line.counted_qty ?? "")}
                          onChange={(event) =>
                            setEntries((current) => ({ ...current, [line.id]: event.target.value }))
                          }
                        />
                      ) : (
                        <span className="tabular-nums">
                          {line.counted_qty === null ? (
                            <span className="text-foreground-muted">{t("uncounted")}</span>
                          ) : (
                            formatNumber(line.counted_qty)
                          )}
                        </span>
                      )}
                    </td>
                    {reveal ? (
                      <td
                        className={`py-1.5 text-right tabular-nums ${
                          Number(line.variance_qty) === 0 ? "text-foreground-muted" : "font-medium text-danger"
                        }`}
                      >
                        {formatNumber(line.variance_qty)}
                      </td>
                    ) : null}
                    {reveal ? (
                      <td className="py-1.5 text-right tabular-nums">
                        {formatNumber(line.variance_value, { maximumFractionDigits: 2 })}
                      </td>
                    ) : null}
                    <td className="py-1.5">
                      <span className="flex flex-wrap items-center gap-1">
                        <StatusBadge status={line.status} label={t(`count_line_statuses.${line.status}`)} />
                        {c.can_record && line.counted_qty !== null ? (
                          <Button
                            size="sm"
                            variant="outline"
                            onClick={() =>
                              run(() => actions.recount(line.id), t("flag_recount"))
                            }
                          >
                            {t("flag_recount")}
                          </Button>
                        ) : null}
                      </span>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>

            {c.can_record ? (
              <div className="mt-3 flex justify-end">
                <Button onClick={saveCounts} disabled={pending || !dirty}>
                  {t("record_counts")}
                </Button>
              </div>
            ) : null}
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
            <CardTitle>{t("stock_count")}</CardTitle>
          </CardHeader>
          <CardBody className="grid grid-cols-2 gap-x-4 gap-y-3 text-sm">
            <Field label={t("status")}>
              <StatusBadge status={c.status} label={t(`count_statuses.${c.status}`)} />
            </Field>
            <Field label={t("count_type")}>{t(`count_types.${c.count_type}`)}</Field>
            <Field label={t("store")}>{c.store?.name ?? "—"}</Field>
            <Field label={t("count_date")}>
              <FormattedDateTime value={c.count_date} mode="date" />
            </Field>
            <Field label={t("blind_count")}>{c.is_blind ? tc("yes") : tc("no")}</Field>
            <Field label={t("count_scope")}>
              {c.scope ? Object.keys(c.scope).length : 0 ? t(`count_types.${c.count_type}`) : t("count_whole_store")}
            </Field>
            {reveal ? <Field label={t("variance_value")}>{formatNumber(c.variance_value, { maximumFractionDigits: 2 })}</Field> : null}
          </CardBody>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle>{t("history")}</CardTitle>
          </CardHeader>
          <CardBody className="flex flex-col gap-2 text-sm">
            <HistoryRow label={t("created")} at={c.created_at} />
            <HistoryRow label={t("count_statuses.UNDER_REVIEW")} at={c.submitted_at} />
            <HistoryRow label={t("count_statuses.APPROVED")} at={c.approved_at} />
            <HistoryRow label={t("count_statuses.POSTED")} at={c.posted_at} />
            <HistoryRow label={t("count_statuses.REJECTED")} at={c.rejected_at} />
            <HistoryRow label={t("count_statuses.CANCELLED")} at={c.cancelled_at} />
          </CardBody>
        </Card>
      </div>

      {dialog?.kind === "found" ? (
        <FormDialog
          title={t("record_found")}
          description={t("record_found_hint")}
          fields={[
            {
              name: "item_id",
              label: t("item"),
              type: "select",
              required: true,
              options: [{ value: "", label: "—" }, ...items.map((item) => ({ value: item.id, label: `${item.item_code} · ${item.name}` }))],
            },
            {
              name: "bin_id",
              label: t("bin"),
              type: "select",
              required: true,
              options: [
                { value: "", label: "—" },
                // Only bins already on the sheet: the count covers one store.
                ...dedupeBins(c.lines).map((bin) => ({ value: bin.id, label: bin.code })),
              ],
            },
            { name: "counted_qty", label: t("counted_qty"), type: "number", required: true },
            { name: "lot_no", label: t("lot_no") },
          ]}
          onClose={() => setDialog(null)}
          onSubmit={(values) => run(() => actions.recordFound(values), t("count_recorded"))}
          submitLabel={tc("save")}
        />
      ) : null}
    </div>
  );
}

/** The bins the sheet already covers, once each. */
function dedupeBins(lines) {
  const seen = new Map();

  for (const line of lines) {
    if (line.bin && !seen.has(line.bin.id)) seen.set(line.bin.id, line.bin);
  }

  return [...seen.values()];
}

export { StockCountDetail };
