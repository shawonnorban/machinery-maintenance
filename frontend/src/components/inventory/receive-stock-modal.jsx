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

const TRANSACTION_TYPES = [
  { value: "RECEIPT", label: "Receipt (purchase order)" },
  { value: "OPENING_BALANCE", label: "Opening balance" },
  { value: "ADJUSTMENT_IN", label: "Adjustment in (physical count)" },
];

function SubmitButton() {
  const { pending } = useFormStatus();
  return (
    <Button type="submit" loading={pending}>
      Receive stock
    </Button>
  );
}

/** Mirrors `StockController::store` — the received price sets the weighted average, so it's required, not defaulted. */
function ReceiveStockModal({ bins, action }) {
  const [open, setOpen] = useState(false);
  const [state, formAction] = useActionState(action, null);
  const router = useRouter();
  const toastManager = useToastManager();

  useEffect(() => {
    if (state?.status === "success") {
      toastManager.add({ title: "Stock received", type: "success" });
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
      <Button size="sm" onClick={() => setOpen(true)}>
        Receive stock
      </Button>

      <Modal open={open} onOpenChange={setOpen} title="Receive stock" description="Stock arriving into a bin.">
        <form action={formAction} className="flex flex-col gap-4">
          <FormField label="Bin" required error={state?.errors?.bin_id?.[0]}>
            {(fieldProps) => (
              <Select {...fieldProps} name="bin_id" placeholder="Select a bin" options={bins.map((bin) => ({ value: bin.id, label: bin.full_path }))} />
            )}
          </FormField>

          <div className="grid grid-cols-2 gap-3">
            <FormField label="Quantity" required error={state?.errors?.quantity?.[0]}>
              {(fieldProps) => <Input {...fieldProps} name="quantity" type="number" step="0.0001" min="0.0001" required />}
            </FormField>
            <FormField label="Unit cost" required error={state?.errors?.unit_cost?.[0]}>
              {(fieldProps) => <Input {...fieldProps} name="unit_cost" type="number" step="0.0001" min="0" required />}
            </FormField>
          </div>

          <FormField label="Reason">
            {(fieldProps) => <Select {...fieldProps} name="transaction_type" options={TRANSACTION_TYPES} />}
          </FormField>

          <FormField label="Notes" error={state?.errors?.notes?.[0]}>
            {(fieldProps) => <Textarea {...fieldProps} name="notes" rows={2} maxLength={2000} />}
          </FormField>

          {state?.status === "error" && !state.errors ? <p className="text-sm text-danger">{state.message}</p> : null}

          <div className="mt-2 flex justify-end gap-2">
            <Button type="button" variant="outline" onClick={() => setOpen(false)}>
              Cancel
            </Button>
            <SubmitButton />
          </div>
        </form>
      </Modal>
    </>
  );
}

export { ReceiveStockModal };
