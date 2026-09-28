"use client";

import { useActionState, useEffect } from "react";
import { useRouter } from "next/navigation";
import { FormField } from "@/components/ui/form-field";
import { Input } from "@/components/ui/input";
import { Button } from "@/components/ui/button";
import { useToastManager } from "@/components/ui/toast";
import { useT } from "@/lib/i18n";

/** Mirrors `contracts/show.blade.php`'s renew card — a new contract, never an edit of this one. */
function RenewContractForm({ contract, action }) {
  const t = useT("vendor");
  const [state, dispatch, pending] = useActionState(action, null);
  const router = useRouter();
  const toastManager = useToastManager();

  useEffect(() => {
    if (state?.status === "success") {
      toastManager.add({ title: t("contract_renewed"), type: "success" });
      router.push(`/vendors/service-contracts/${state.renewalId}`);
    }
    // See ChangeStatusModal's own comment (breakdowns/breakdown-actions.jsx)
    // on why toastManager/router are left out of the dependency array —
    // including them re-fires this effect every render once state first
    // becomes "success", stacking duplicate toasts at minimum.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [state]);

  const nextStart = addDays(contract.end_date, 1);
  const nextEnd = addYears(contract.end_date, 1);

  return (
    <form action={dispatch} className="flex flex-col gap-3">
      <p className="text-xs text-foreground-muted">{t("renew_hint")}</p>
      <div className="grid grid-cols-1 gap-3 sm:grid-cols-3">
        <FormField label={t("start_date")} required>
          {(fieldProps) => <Input {...fieldProps} type="date" name="start_date" defaultValue={nextStart} required />}
        </FormField>
        <FormField label={t("end_date")} required>
          {(fieldProps) => <Input {...fieldProps} type="date" name="end_date" defaultValue={nextEnd} required />}
        </FormField>
        <FormField label={t("value")}>
          {(fieldProps) => <Input {...fieldProps} type="number" step="0.01" min="0" name="value" defaultValue={contract.value ?? ""} />}
        </FormField>
      </div>

      {state?.status === "error" ? <p className="text-sm text-danger">{state.message}</p> : null}

      <div>
        <Button type="submit" size="sm" loading={pending}>
          {t("renew")}
        </Button>
      </div>
    </form>
  );
}

function addDays(dateString, days) {
  const date = new Date(`${dateString}T00:00:00Z`);
  date.setUTCDate(date.getUTCDate() + days);
  return date.toISOString().slice(0, 10);
}

function addYears(dateString, years) {
  const date = new Date(`${dateString}T00:00:00Z`);
  date.setUTCFullYear(date.getUTCFullYear() + years);
  return date.toISOString().slice(0, 10);
}

export { RenewContractForm };
