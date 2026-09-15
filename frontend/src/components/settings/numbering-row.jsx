"use client";

import { startTransition, useActionState, useEffect, useState } from "react";
import { FormField } from "@/components/ui/form-field";
import { Input } from "@/components/ui/input";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { useToastManager } from "@/components/ui/toast";

const TYPE_LABELS = {
  WORK_ORDER: "Work order",
  BREAKDOWN: "Breakdown",
  ASSET_TRANSFER: "Machine transfer",
  INVENTORY_TRANSFER: "Stock transfer",
  INVOICE: "Invoice",
  WARRANTY_CLAIM: "Warranty claim",
  SERVICE_CONTRACT: "Service contract",
  GOODS_RECEIPT: "Goods receipt",
};

const RESET_LABELS = { MONTHLY: "Restarts each month", YEARLY: "Restarts each year", NEVER: "Never restarts" };

/**
 * One document type from `NumberingApiController::index` (SRS 52). A format
 * is easy to get subtly wrong and impossible to correct for numbers already
 * issued, so this always shows a live sample and how many are issued
 * already before anyone changes anything.
 */
function NumberingRow({ row, updateAction, resetAction }) {
  const [state, dispatch, pending] = useActionState(updateAction, null);
  const [resetting, setResetting] = useState(false);
  const [format, setFormat] = useState(row.format);
  const [padding, setPadding] = useState(String(row.padding));
  const toastManager = useToastManager();

  useEffect(() => {
    if (state?.status === "success") {
      toastManager.add({ title: "Saved. Takes effect from the next period.", type: "success" });
    } else if (state?.status === "error" && !state.errors) {
      toastManager.add({ title: state.message ?? "Could not save", type: "danger" });
    }
    // toastManager is not a stable reference across renders — including it
    // re-fires this effect every render once state first becomes
    // "success"/"error", stacking duplicate toasts.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [state]);

  function handleSubmit(event) {
    event.preventDefault();
    const formData = new FormData();
    formData.set("format", format);
    formData.set("padding", padding);
    startTransition(() => dispatch(formData));
  }

  async function handleReset() {
    setResetting(true);
    const result = await resetAction();
    setResetting(false);
    if (result?.status === "success") {
      toastManager.add({ title: "Back to the platform default", type: "success" });
    } else if (result?.status === "error") {
      toastManager.add({ title: result.message ?? "Could not reset", type: "danger" });
    }
  }

  return (
    <div className="flex flex-col gap-3 border-b border-border py-4 last:border-b-0">
      <div className="flex items-start justify-between gap-3">
        <div>
          <p className="text-sm font-medium text-foreground">{TYPE_LABELS[row.document_type] ?? row.document_type}</p>
          <p className="text-xs text-foreground-muted">
            {RESET_LABELS[row.reset] ?? row.reset} · {row.issued} number{row.issued === 1 ? "" : "s"} already issued
          </p>
        </div>
        {row.is_default ? <Badge variant="neutral">Default</Badge> : <Badge variant="brand">Customised</Badge>}
      </div>

      <form onSubmit={handleSubmit} className="flex flex-wrap items-end gap-2">
        <div className="w-64">
          <FormField label="Format" error={state?.errors?.format?.[0]}>
            {(fieldProps) => <Input {...fieldProps} value={format} onChange={(e) => setFormat(e.target.value)} />}
          </FormField>
        </div>
        <div className="w-24">
          <FormField label="Digits" error={state?.errors?.padding?.[0]}>
            {(fieldProps) => (
              <Input {...fieldProps} type="number" min={1} max={10} value={padding} onChange={(e) => setPadding(e.target.value)} />
            )}
          </FormField>
        </div>
        <Button type="submit" size="sm" variant="outline" loading={pending}>
          Save
        </Button>
        {!row.is_default ? (
          <Button type="button" size="sm" variant="ghost" loading={resetting} onClick={handleReset}>
            Back to default
          </Button>
        ) : null}
      </form>

      <p className="text-xs text-foreground-muted">
        Next number would be <span className="font-mono text-foreground">{row.sample}</span>. Use {"{FACTORY}"},{" "}
        {"{YYYY}"}, {"{MM}"} and {"{SEQ}"}.
      </p>
    </div>
  );
}

export { NumberingRow };
