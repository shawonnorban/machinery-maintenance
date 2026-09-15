"use client";

import { useActionState, useEffect, useState } from "react";
import { useRouter } from "next/navigation";
import { Button } from "@/components/ui/button";
import { Modal } from "@/components/ui/modal";
import { FormField } from "@/components/ui/form-field";
import { Textarea } from "@/components/ui/textarea";
import { useToastManager } from "@/components/ui/toast";

/** Mirrors `StockController::reverse` — an opposing row, never an edit of the original. */
function ReverseTransactionButton({ action }) {
  const [open, setOpen] = useState(false);
  const [state, formAction] = useActionState(action, null);
  const router = useRouter();
  const toastManager = useToastManager();

  useEffect(() => {
    if (state?.status === "success") {
      toastManager.add({ title: "Transaction reversed", type: "success" });
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
      <button type="button" onClick={() => setOpen(true)} className="text-xs font-medium text-brand hover:underline">
        Reverse
      </button>

      <Modal open={open} onOpenChange={setOpen} title="Reverse this transaction?" description="Posts an opposing row — the original stays exactly as it was.">
        <form action={formAction} className="flex flex-col gap-4">
          <FormField label="Reason" required error={state?.errors?.reason?.[0]}>
            {(fieldProps) => <Textarea {...fieldProps} name="reason" rows={2} maxLength={2000} required />}
          </FormField>

          {state?.status === "error" && !state.errors ? <p className="text-sm text-danger">{state.message}</p> : null}

          <div className="flex justify-end gap-2">
            <Button type="button" variant="outline" onClick={() => setOpen(false)}>
              Cancel
            </Button>
            <Button type="submit" variant="danger">
              Reverse
            </Button>
          </div>
        </form>
      </Modal>
    </>
  );
}

export { ReverseTransactionButton };
