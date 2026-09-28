"use client";

import { useState } from "react";
import Link from "next/link";
import { Card, CardHeader, CardTitle, CardBody } from "@/components/ui/card";
import { StatusBadge } from "@/components/ui/status-badge";
import { Select } from "@/components/ui/select";
import { FormattedDateTime } from "@/components/ui/formatted-date-time";
import { DocumentLinesTable, ItemCell, qty } from "@/components/procurement/document-lines-table";
import { DocumentActions } from "@/components/procurement/document-actions";
import { RollEntryPanel, newRoll, serialiseRolls } from "@/components/procurement/roll-entry-panel";
import { Field, HistoryRow } from "@/components/procurement/requisition-detail";
import { formatNumber } from "@/lib/format";
import { useT } from "@/lib/i18n";

/**
 * The body of a goods receipt's detail page (Procurement spec §9). A draft
 * QUANTITY/LOT line without a bin can be given one right here before
 * completing; a ROLL-tracked (fabric) line instead gets its own roll-entry
 * panel below the table, one row per physical roll (Inventory spec §3.1) —
 * bin is per-roll, not per-line, since rolls of one line can land on
 * different shelves.
 */
function ReceiptDetail({ receipt, bins, actions }) {
  const t = useT("procurement");
  const ti = useT("inspection");
  const g = receipt;
  const fabricLines = g.lines.filter((line) => line.tracking_mode === "ROLL");
  const otherLines = g.lines.filter((line) => line.tracking_mode !== "ROLL");

  const [lineBins, setLineBins] = useState(() => Object.fromEntries(otherLines.map((line) => [line.id, line.bin?.id ?? ""])));
  const [lineRolls, setLineRolls] = useState(() =>
    Object.fromEntries(fabricLines.map((line) => [line.id, line.rolls?.length ? line.rolls.map(() => newRoll()) : [newRoll()]])),
  );

  const missingBin = otherLines.some((line) => !lineBins[line.id]);
  const binOptions = bins.map((b) => ({ value: b.id, label: b.name ? `${b.code} — ${b.name}` : b.code }));

  const buttons = [
    g.can_complete && {
      key: "complete", label: t("complete"), variant: "success", hint: t("complete_hint"),
      run: () => actions.complete(lineBins, Object.fromEntries(Object.entries(lineRolls).map(([lineId, rolls]) => [lineId, serialiseRolls(rolls)]))),
      successTitle: t("receipt_completed", { number: g.grn_no }),
    },
    // Only off a completed receipt: nothing is on the shelf before that,
    // so there is nothing to take back off it (spec §12).
    g.status === "COMPLETED" && {
      key: "return", label: t("new_return"), variant: "outline", hint: t("return_hint"),
      run: async () => ({ status: "success", redirect: `/procurement/receipts/${g.id}/return` }),
      successTitle: t("new_return"),
    },
    g.can_cancel && {
      key: "cancel", label: t("cancel_document"), variant: "outline",
      run: (reason) => actions.cancel(reason), successTitle: t("receipt_cancelled", { number: g.grn_no }),
      reason: { label: t("cancel_reason"), required: false },
    },
  ];

  const total = g.lines.reduce((sum, line) => sum + Number(line.amount), 0);

  return (
    <div className="grid grid-cols-1 gap-4 lg:grid-cols-12">
      <div className="flex flex-col gap-4 lg:col-span-8">
        <DocumentLinesTable
          title={t("lines")}
          lines={g.lines}
          columns={[
            { key: "item", header: t("item"), render: (line) => <ItemCell line={line} /> },
            { key: "received_qty", header: t("received_qty"), align: "right", render: (line) => qty(line.received_qty, line.uom?.code) },
            {
              key: "bin",
              header: t("bin"),
              render: (line) => {
                if (line.tracking_mode === "ROLL") {
                  return line.rolls?.length
                    ? `${line.rolls.length} ${ti("rolls").toLowerCase()}`
                    : <span className="text-xs text-foreground-muted">{ti("roll_entry_hint")}</span>;
                }

                return g.can_complete ? (
                  <div className="min-w-[180px]">
                    <Select value={lineBins[line.id] ?? ""} onValueChange={(value) => setLineBins((prev) => ({ ...prev, [line.id]: value }))} options={binOptions} placeholder={t("pick_bin")} />
                  </div>
                ) : (
                  line.bin?.code ?? "—"
                );
              },
            },
            { key: "lot_no", header: t("lot_no"), render: (line) => line.lot_no ?? (line.tracking_mode === "LOT" ? <span className="text-danger">{t("lot_no")}!</span> : "—") },
            { key: "rate", header: t("rate"), align: "right", render: (line) => (line.rate === null ? "—" : formatNumber(line.rate, { maximumFractionDigits: 2 })) },
            { key: "amount", header: t("amount"), align: "right", render: (line) => formatNumber(line.amount, { maximumFractionDigits: 2 }) },
          ]}
          footer={{ item: t("subtotal"), amount: formatNumber(total, { maximumFractionDigits: 2 }) }}
        />

        {g.can_complete
          ? fabricLines.map((line) => (
              <RollEntryPanel
                key={line.id}
                line={line}
                rolls={lineRolls[line.id] ?? []}
                onChange={(rolls) => setLineRolls((prev) => ({ ...prev, [line.id]: rolls }))}
                bins={bins}
              />
            ))
          : null}

        {g.notes ? (
          <Card>
            <CardHeader>
              <CardTitle>{t("notes")}</CardTitle>
            </CardHeader>
            <CardBody className="text-sm whitespace-pre-line">{g.notes}</CardBody>
          </Card>
        ) : null}
      </div>

      <div className="flex flex-col gap-4 lg:col-span-4">
        {g.can_complete && missingBin ? <p className="text-xs text-warning">{t("pick_bin")}</p> : null}
        <DocumentActions buttons={buttons} />

        <Card>
          <CardHeader>
            <CardTitle>{t("goods_receipt")}</CardTitle>
          </CardHeader>
          <CardBody className="grid grid-cols-2 gap-x-4 gap-y-3 text-sm">
            <Field label={t("status")}><StatusBadge status={g.status} label={t(`statuses.${g.status}`)} /></Field>
            <Field label={t("grn_type")}>{t(`grn_types.${g.grn_type}`)}</Field>
            <Field label={t("date")}><FormattedDateTime value={g.grn_date} mode="date" /></Field>
            <Field label={t("gate_pass_no")}>{g.gate_pass_no ?? "—"}</Field>
            <Field label={t("factory")}>{g.factory?.name ?? "—"}</Field>
            <Field label={t("store")}>{g.store?.name ?? "—"}</Field>
            <Field label={t("vendor")}>{g.vendor?.name ?? "—"}</Field>
            <Field label={t("purchase_order")}>
              {g.order ? <Link href={`/procurement/orders/${g.order.id}`} className="text-brand hover:underline">{g.order.po_no}</Link> : t("direct_receipt")}
            </Field>
            <Field label={t("created_by")}>{g.creator?.name ?? "—"}</Field>
          </CardBody>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle>{t("history")}</CardTitle>
          </CardHeader>
          <CardBody className="flex flex-col gap-2 text-sm">
            <HistoryRow label={t("created")} at={g.created_at} />
            <HistoryRow label={t("statuses.COMPLETED")} at={g.completed_at} />
            <HistoryRow label={t("statuses.CANCELLED")} at={g.cancelled_at} />
          </CardBody>
        </Card>
      </div>
    </div>
  );
}

export { ReceiptDetail };
