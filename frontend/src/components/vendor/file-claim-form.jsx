"use client";

import { useActionState, useEffect } from "react";
import { FormField } from "@/components/ui/form-field";
import { Input } from "@/components/ui/input";
import { Textarea } from "@/components/ui/textarea";
import { Button } from "@/components/ui/button";
import { useToastManager } from "@/components/ui/toast";

const today = () => new Date().toISOString().slice(0, 10);

/** Mirrors `warranties/show.blade.php`'s file-claim card. */
function FileClaimForm({ action }) {
  const [state, dispatch, pending] = useActionState(action, null);
  const toastManager = useToastManager();

  useEffect(() => {
    if (state?.status === "success") {
      toastManager.add({ title: "Claim filed", type: "success" });
    }
    // toastManager is not a stable reference across renders — including it
    // re-fires this effect every render once state first becomes
    // "success", stacking duplicate toasts.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [state]);

  return (
    <form action={dispatch} className="flex flex-col gap-4">
      <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <FormField label="Claim date" required>
          {(fieldProps) => <Input {...fieldProps} type="date" name="claim_date" defaultValue={today()} required />}
        </FormField>
        <FormField label="Incident date" helperText="Cover is judged on the day the machine failed, not the day it's claimed.">
          {(fieldProps) => <Input {...fieldProps} type="date" name="incident_date" />}
        </FormField>
      </div>

      <FormField label="Description" required error={state?.errors?.description?.[0]}>
        {(fieldProps) => <Textarea {...fieldProps} name="description" rows={3} required />}
      </FormField>

      <FormField label="Claimed amount">
        {(fieldProps) => <Input {...fieldProps} type="number" step="0.01" min="0" name="claimed_amount" />}
      </FormField>

      {state?.status === "error" && !state.errors ? <p className="text-sm text-danger">{state.message}</p> : null}

      <div>
        <Button type="submit" loading={pending}>
          File claim
        </Button>
      </div>
    </form>
  );
}

export { FileClaimForm };
