"use client";

import { useActionState, useEffect } from "react";
import { FormField } from "@/components/ui/form-field";
import { Input } from "@/components/ui/input";
import { Textarea } from "@/components/ui/textarea";
import { Button } from "@/components/ui/button";
import { useToastManager } from "@/components/ui/toast";
import { useT } from "@/lib/i18n";

const today = () => new Date().toISOString().slice(0, 10);

/** Mirrors `warranties/show.blade.php`'s file-claim card. */
function FileClaimForm({ action }) {
  const t = useT("vendor");
  const [state, dispatch, pending] = useActionState(action, null);
  const toastManager = useToastManager();

  useEffect(() => {
    if (state?.status === "success") {
      toastManager.add({ title: t("claim_filed"), type: "success" });
    }
    // toastManager is not a stable reference across renders — including it
    // re-fires this effect every render once state first becomes
    // "success", stacking duplicate toasts.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [state]);

  return (
    <form action={dispatch} className="flex flex-col gap-4">
      <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <FormField label={t("claim_date")} required>
          {(fieldProps) => <Input {...fieldProps} type="date" name="claim_date" defaultValue={today()} required />}
        </FormField>
        <FormField label={t("incident_date")} helperText={t("incident_date_hint")}>
          {(fieldProps) => <Input {...fieldProps} type="date" name="incident_date" />}
        </FormField>
      </div>

      <FormField label={t("description")} required error={state?.errors?.description?.[0]}>
        {(fieldProps) => <Textarea {...fieldProps} name="description" rows={3} required />}
      </FormField>

      <FormField label={t("claimed_amount")}>
        {(fieldProps) => <Input {...fieldProps} type="number" step="0.01" min="0" name="claimed_amount" />}
      </FormField>

      {state?.status === "error" && !state.errors ? <p className="text-sm text-danger">{state.message}</p> : null}

      <div>
        <Button type="submit" loading={pending}>
          {t("file_claim")}
        </Button>
      </div>
    </form>
  );
}

export { FileClaimForm };
