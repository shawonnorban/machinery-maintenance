"use client";

import { useActionState, useEffect } from "react";
import { FormField } from "@/components/ui/form-field";
import { Input } from "@/components/ui/input";
import { Select } from "@/components/ui/select";
import { Button } from "@/components/ui/button";
import { useToastManager } from "@/components/ui/toast";

/** Mirrors `workflows/index.blade.php`'s "Add a chain" form. */
function NewWorkflowForm({ entityTypes, action }) {
  const [state, dispatch, pending] = useActionState(action, null);
  const toastManager = useToastManager();

  useEffect(() => {
    if (state?.status === "success") {
      toastManager.add({ title: "Chain created", type: "success" });
    }
    // toastManager is not a stable reference across renders, so including
    // it re-fires this effect (adding another toast) every render once
    // state first becomes "success" — an infinite toast-stacking loop.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [state]);

  return (
    <form action={dispatch} className="flex flex-wrap items-end gap-3">
      <div className="w-64">
        <FormField label="Chain name" required error={state?.errors?.name?.[0]}>
          {(fieldProps) => <Input {...fieldProps} name="name" required />}
        </FormField>
      </div>
      <div className="w-48">
        <FormField label="Applies to" required>
          {(fieldProps) => (
            <Select
              {...fieldProps}
              name="entity_type"
              defaultValue={entityTypes[0]}
              options={entityTypes.map((t) => ({ value: t, label: formatEntityType(t) }))}
            />
          )}
        </FormField>
      </div>
      <Button type="submit" loading={pending}>
        Add chain
      </Button>
    </form>
  );
}

function formatEntityType(type) {
  return type
    .toLowerCase()
    .split("_")
    .map((w) => w[0].toUpperCase() + w.slice(1))
    .join(" ");
}

export { NewWorkflowForm, formatEntityType };
