"use client";

import { useActionState, useEffect, useState } from "react";
import { useFormStatus } from "react-dom";
import { useRouter } from "next/navigation";
import { Button } from "@/components/ui/button";
import { Modal } from "@/components/ui/modal";
import { FormField } from "@/components/ui/form-field";
import { Select } from "@/components/ui/select";
import { Input } from "@/components/ui/input";
import { Textarea } from "@/components/ui/textarea";
import { useToastManager } from "@/components/ui/toast";
import { useT } from "@/lib/i18n";

function SubmitButton() {
  const t = useT("inventory");
  const { pending } = useFormStatus();
  return (
    <Button type="submit" variant="danger" loading={pending}>
      {t("record_adjustment")}
    </Button>
  );
}

/**
 * Mirrors `StockController::adjust` exactly, including its one-directional
 * shape: this only ever decreases stock. The increasing direction after a
 * physical count is a receipt (`ReceiveStockModal`, `ADJUSTMENT_IN`), which
 * has no separate "adjust up" endpoint on the web either.
 */
function AdjustStockModal({ bins, action }) {
  const t = useT("inventory");
  const tc = useT("common");
  const TRANSACTION_TYPES = [
    { value: "ADJUSTMENT_OUT", label: t("adjustment_out_physical_count") },
    { value: "SCRAP", label: t("scrap") },
  ];
  const [open, setOpen] = useState(false);
  const [state, formAction] = useActionState(action, null);
  const router = useRouter();
  const toastManager = useToastManager();

  useEffect(() => {
    if (state?.status === "success") {
      toastManager.add({ title: t("adjustment_recorded_toast"), type: "success" });
      queueMicrotask(() => setOpen(false));
      router.refresh();
    }
    // See ChangeStatusModal's own comment on why toastManager/router are
    // left out of the dependency array — including them causes an
    // infinite loop once state first becomes "success".
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [state]);

  return (
    <>
      <Button variant="outline" size="sm" onClick={() => setOpen(true)}>
        {t("adjust")}
      </Button>

      <Modal open={open} onOpenChange={setOpen} title={t("adjust_stock")} description={t("adjust_stock_hint")}>
        <form action={formAction} className="flex flex-col gap-4">
          <FormField label={t("bin")} required error={state?.errors?.bin_id?.[0]}>
            {(fieldProps) => (
              <Select {...fieldProps} name="bin_id" placeholder={t("select_a_bin")} options={bins.map((bin) => ({ value: bin.id, label: bin.full_path }))} />
            )}
          </FormField>

          <FormField label={t("transaction_type")} required>
            {(fieldProps) => <Select {...fieldProps} name="transaction_type" options={TRANSACTION_TYPES} />}
          </FormField>

          <FormField label={t("quantity")} required error={state?.errors?.quantity?.[0]}>
            {(fieldProps) => <Input {...fieldProps} name="quantity" type="number" step="0.0001" min="0.0001" required />}
          </FormField>

          <FormField label={t("reason")} required error={state?.errors?.notes?.[0]}>
            {(fieldProps) => <Textarea {...fieldProps} name="notes" rows={2} maxLength={2000} required />}
          </FormField>

          {state?.status === "error" && !state.errors ? <p className="text-sm text-danger">{state.message}</p> : null}

          <div className="mt-2 flex justify-end gap-2">
            <Button type="button" variant="outline" onClick={() => setOpen(false)}>
              {tc("cancel")}
            </Button>
            <SubmitButton />
          </div>
        </form>
      </Modal>
    </>
  );
}

export { AdjustStockModal };
