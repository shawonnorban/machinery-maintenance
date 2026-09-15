"use client";

import { useActionState, useEffect } from "react";
import { FormField } from "@/components/ui/form-field";
import { Input } from "@/components/ui/input";
import { Button } from "@/components/ui/button";
import { useToastManager } from "@/components/ui/toast";

function CancelContractForm({ action }) {
  const [state, dispatch, pending] = useActionState(action, null);
  const toastManager = useToastManager();

  useEffect(() => {
    if (state?.status === "success") {
      toastManager.add({ title: "Contract cancelled", type: "success" });
    }
    // toastManager is not a stable reference across renders — including it
    // re-fires this effect every render once state first becomes
    // "success", stacking duplicate toasts.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [state]);

  return (
    <form action={dispatch} className="flex items-end gap-2">
      <div className="flex-1">
        <FormField label="Cancel reason" required error={state?.errors?.reason?.[0]}>
          {(fieldProps) => <Input {...fieldProps} name="reason" required />}
        </FormField>
      </div>
      <Button type="submit" size="sm" variant="danger" loading={pending}>
        Cancel contract
      </Button>
    </form>
  );
}

export { CancelContractForm };
