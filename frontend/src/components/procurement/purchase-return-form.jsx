"use client";

import { useState, useTransition } from "react";
import { useRouter } from "next/navigation";
import { Card, CardHeader, CardTitle, CardBody } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Select } from "@/components/ui/select";
import { Textarea } from "@/components/ui/textarea";
import { Alert } from "@/components/ui/alert";
import { FormField } from "@/components/ui/form-field";
import { useToastManager } from "@/components/ui/toast";
import { formatNumber } from "@/lib/format";
import { useT } from "@/lib/i18n";

const REASONS = ["QUALITY_FAIL", "EXCESS", "WRONG_ITEM", "DAMAGE", "OTHER"];

/**
 * Sending part of a receipt back (Procurement spec §12).
 *
 * Built from the receipt's own lines rather than an item picker: what can
 * go back is what came in, and typing an item code here is how a return
 * ends up against the wrong delivery. A line left blank is simply not
 * returned.
 */
function PurchaseReturnForm({ receipt, onSubmit }) {
  const t = useT("procurement");
  const tc = useT("common");
  const router = useRouter();
  const toastManager = useToastManager();
  const [pending, startTransition] = useTransition();

  const [reason, setReason] = useState("QUALITY_FAIL");
  const [returnDate, setReturnDate] = useState("");
  const [notes, setNotes] = useState("");
  const [quantities, setQuantities] = useState({});
  const [errors, setErrors] = useState({});

  const lines = receipt.lines.filter((line) => Number(line.received_qty) > 0);
  const chosen = lines.filter((line) => quantities[line.id] && Number(quantities[line.id]) > 0);
  const total = chosen.reduce((sum, line) => sum + Number(quantities[line.id]) * Number(line.rate ?? 0), 0);

  function submit(event) {
    event.preventDefault();
    setErrors({});

    startTransition(async () => {
      const result = await onSubmit({
        reason,
        return_date: returnDate || undefined,
        notes: notes || undefined,
        lines: chosen.map((line) => ({
          goods_receipt_line_id: line.id,
          quantity: quantities[line.id],
        })),
      });

      if (result?.status === "error") {
        setErrors(result.errors ?? {});
        toastManager.add({ type: "danger", title: result.message ?? tc("something_went_wrong") });
        return;
      }

      toastManager.add({ type: "success", title: t("return_created", { number: result.data?.return_no ?? "" }) });
      router.push(`/procurement/returns/${result.data.id}`);
    });
  }

  return (
    <form onSubmit={submit} className="flex flex-col gap-4">
      <Alert variant="warning">{t("return_hint")}</Alert>

      <Card>
        <CardHeader>
          <CardTitle>{t("purchase_return")}</CardTitle>
        </CardHeader>
        <CardBody className="grid grid-cols-1 gap-4 sm:grid-cols-3">
          <FormField label={t("reason")} required error={errors.reason?.[0]}>
            {(props) => (
              <Select
                {...props}
                options={REASONS.map((value) => ({ value, label: t(`return_reasons.${value}`) }))}
                value={reason}
                onValueChange={setReason}
              />
            )}
          </FormField>
          <FormField label={t("return_date")} error={errors.return_date?.[0]}>
            {(props) => (
              <Input {...props} type="date" value={returnDate} onChange={(event) => setReturnDate(event.target.value)} />
            )}
          </FormField>
          <FormField label={t("goods_receipt")}>{() => <Input value={receipt.grn_no} readOnly />}</FormField>
        </CardBody>
      </Card>

      <Card>
        <CardHeader>
          <CardTitle>{t("lines")}</CardTitle>
        </CardHeader>
        <CardBody className="flex flex-col gap-2">
          {errors.lines ? <p className="text-xs text-danger">{errors.lines[0]}</p> : null}
          {lines.map((line) => (
            <div key={line.id} className="grid grid-cols-1 items-center gap-2 rounded-sm border border-border p-2 sm:grid-cols-12">
              <div className="sm:col-span-6">
                <span className="text-sm font-medium">{line.item_name}</span>{" "}
                <span className="font-mono text-xs text-foreground-muted">{line.item_code}</span>
              </div>
              <div className="text-sm text-foreground-muted sm:col-span-3">
                {t("received_qty")}: {formatNumber(line.received_qty)} {line.uom?.code ?? ""}
              </div>
              <div className="sm:col-span-3">
                <Input
                  type="number"
                  step="any"
                  min="0"
                  max={line.received_qty}
                  placeholder={t("returnable_qty")}
                  value={quantities[line.id] ?? ""}
                  onChange={(event) =>
                    setQuantities((current) => ({ ...current, [line.id]: event.target.value }))
                  }
                />
              </div>
            </div>
          ))}

          <div className="flex items-baseline justify-between border-t border-border pt-2 text-sm font-medium">
            <span>{t("debit_note_amount")}</span>
            <span className="tabular-nums">{formatNumber(total, { maximumFractionDigits: 2 })}</span>
          </div>
        </CardBody>
      </Card>

      <Card>
        <CardBody>
          <FormField label={t("notes")}>
            {(props) => <Textarea {...props} rows={3} value={notes} onChange={(event) => setNotes(event.target.value)} />}
          </FormField>
        </CardBody>
      </Card>

      <div className="flex justify-end gap-2">
        <Button type="button" variant="outline" onClick={() => router.back()} disabled={pending}>
          {tc("cancel")}
        </Button>
        <Button type="submit" disabled={pending || chosen.length === 0}>
          {tc("save")}
        </Button>
      </div>
    </form>
  );
}

export { PurchaseReturnForm };
