"use client";

import { useActionState, useEffect, useState } from "react";
import { FormField } from "@/components/ui/form-field";
import { Select } from "@/components/ui/select";
import { Input } from "@/components/ui/input";
import { Textarea } from "@/components/ui/textarea";
import { Button } from "@/components/ui/button";
import { Tabs, TabsList, TabsTab, TabsIndicator, TabsPanel } from "@/components/ui/tabs";
import { useToastManager } from "@/components/ui/toast";
import { useT } from "@/lib/i18n";

/** Mirrors `StockController::issue`/`returnStock` — a consumable moving with no work order behind it, so a reason is required. */
function IssueReturnForm({ parts, bins, issueAction, returnAction }) {
  const t = useT("inventory");
  const [mode, setMode] = useState("issue");

  return (
    <Tabs value={mode} onValueChange={setMode}>
      <TabsList>
        <TabsTab value="issue">{t("issue")}</TabsTab>
        <TabsTab value="return">{t("return")}</TabsTab>
        <TabsIndicator />
      </TabsList>

      <TabsPanel value="issue">
        <MovementForm parts={parts} bins={bins} action={issueAction} verb={t("issue")} successLabel={t("issued")} />
      </TabsPanel>
      <TabsPanel value="return">
        <MovementForm parts={parts} bins={bins} action={returnAction} verb={t("return")} successLabel={t("returned")} />
      </TabsPanel>
    </Tabs>
  );
}

function MovementForm({ parts, bins, action, verb, successLabel }) {
  const t = useT("inventory");
  const [state, dispatch, pending] = useActionState(action, null);
  const toastManager = useToastManager();

  useEffect(() => {
    if (state?.status === "success") {
      toastManager.add({ title: successLabel, type: "success" });
    }
    // toastManager is not a stable reference across renders — including it
    // re-fires this effect every render once state first becomes
    // "success", stacking duplicate toasts.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [state, successLabel]);

  return (
    <form action={dispatch} className="mt-4 flex flex-col gap-4">
      <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <FormField label={t("spare_part")} required error={state?.errors?.spare_part_id?.[0]}>
          {(fieldProps) => (
            <Select
              {...fieldProps}
              name="spare_part_id"
              options={parts.map((p) => ({ value: p.id, label: `${p.part_number} — ${p.name}` }))}
            />
          )}
        </FormField>
        <FormField label={t("bin")} required error={state?.errors?.bin_id?.[0]}>
          {(fieldProps) => (
            <Select {...fieldProps} name="bin_id" options={bins.map((b) => ({ value: b.id, label: `${b.code} — ${b.name}` }))} />
          )}
        </FormField>
      </div>

      <FormField label={t("quantity")} required error={state?.errors?.quantity?.[0]}>
        {(fieldProps) => <Input {...fieldProps} type="number" step="0.0001" min="0.0001" name="quantity" required />}
      </FormField>

      <FormField label={t("notes")} required helperText={t("notes_no_work_order_hint")} error={state?.errors?.notes?.[0]}>
        {(fieldProps) => <Textarea {...fieldProps} name="notes" rows={3} required />}
      </FormField>

      {state?.status === "error" && !state.errors ? <p className="text-sm text-danger">{state.message}</p> : null}

      <div>
        <Button type="submit" loading={pending}>
          {verb}
        </Button>
      </div>
    </form>
  );
}

export { IssueReturnForm };
