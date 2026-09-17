"use client";

import { startTransition, useActionState, useEffect, useState } from "react";
import { useRouter } from "next/navigation";
import { Button } from "@/components/ui/button";
import { Modal } from "@/components/ui/modal";
import { useToastManager } from "@/components/ui/toast";
import { DynamicField } from "@/components/settings/dynamic-field";
import { useT } from "@/lib/i18n";

/**
 * One form for every master-data type's create and edit — driven entirely
 * by `schema.fields` (see MasterDataApiController::show()'s `meta.schema`).
 * `row` is null for create, the row being edited otherwise; a platform row
 * (no `company_id`) is never passed here since SaveMasterDataRow refuses
 * to edit one regardless — the row list itself doesn't offer Edit for it.
 *
 * The caller must pass a `key` that changes with the target row (e.g.
 * `key={row?.id ?? "create"}`) so a new target remounts this component
 * fresh — `values`'s lazy initial state depends on `row`, and resetting it
 * via a `useEffect` on prop change would need to call `setState` directly
 * in the effect body, which React's own lint flags as a smell.
 */
function RowFormModal({ open, onOpenChange, title, schema, referenceOptions, row, action }) {
  const t = useT("masterdata");
  const tc = useT("common");
  const [state, dispatch, pending] = useActionState(action, null);
  const [values, setValues] = useState(() => initialValues(schema, row));
  const router = useRouter();
  const toastManager = useToastManager();

  useEffect(() => {
    if (state?.status === "success") {
      toastManager.add({ title: row ? t("updated") : t("created"), type: "success" });
      queueMicrotask(() => onOpenChange(false));
      router.refresh();
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [state]);

  function handleSubmit(event) {
    event.preventDefault();

    const formData = new FormData();
    for (const field of schema.fields) {
      const value = values[field.name];
      if (field.type === "BOOLEAN") {
        formData.set(field.name, value ? "1" : "0");
      } else if (value !== null && value !== undefined && value !== "") {
        formData.set(field.name, value);
      }
    }

    // useActionState's own dispatch must run inside a transition when it
    // isn't reached via a native <form action> — called bare, React warns
    // "called outside of a transition" and `pending` stops updating.
    startTransition(() => dispatch(formData));
  }

  return (
    <Modal open={open} onOpenChange={onOpenChange} title={title}>
      <form onSubmit={handleSubmit} className="flex flex-col gap-4">
        {state?.status === "error" && !state.errors ? <p className="text-sm text-danger">{state.message}</p> : null}

        {schema.fields.map((field) => (
          <DynamicField
            key={field.name}
            field={field}
            value={values[field.name]}
            onChange={(value) => setValues((current) => ({ ...current, [field.name]: value }))}
            referenceOptions={referenceOptions}
            error={state?.errors?.[field.name]?.[0]}
          />
        ))}

        <div className="mt-2 flex justify-end gap-2">
          <Button type="button" variant="outline" onClick={() => onOpenChange(false)}>
            {tc("cancel")}
          </Button>
          <Button type="submit" loading={pending}>
            {row ? tc("save") : t("create")}
          </Button>
        </div>
      </form>
    </Modal>
  );
}

function initialValues(schema, row) {
  const values = {};
  for (const field of schema.fields) {
    values[field.name] = row ? (row[field.name] ?? (field.type === "BOOLEAN" ? false : "")) : field.type === "BOOLEAN" ? true : "";
  }
  return values;
}

export { RowFormModal };
