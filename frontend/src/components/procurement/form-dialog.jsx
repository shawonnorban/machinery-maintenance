"use client";

import { useId, useState } from "react";
import { Modal } from "@/components/ui/modal";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Select } from "@/components/ui/select";
import { Textarea } from "@/components/ui/textarea";
import { FormField } from "@/components/ui/form-field";
import { useT } from "@/lib/i18n";

/**
 * A modal of plain fields.
 *
 * Nearly every action across import documents, landed cost, returns,
 * claims and advances is "type a few values and post them". One component
 * carries all of them rather than a couple of dozen near-identical forms,
 * and each caller describes its fields instead of laying one out.
 */
function FormDialog({ title, description, fields, initial = {}, onClose, onSubmit, submitLabel }) {
  const tc = useT("common");
  const [values, setValues] = useState(() =>
    Object.fromEntries(fields.map((field) => [field.name, initial[field.name] ?? ""])),
  );
  const [busy, setBusy] = useState(false);
  const formId = useId();

  const set = (name, value) => setValues((current) => ({ ...current, [name]: value }));

  async function submit(event) {
    event.preventDefault();
    setBusy(true);

    try {
      // Empty strings are "not said", not "set to nothing" — the API's
      // nullable rules would take them as a value.
      const payload = Object.fromEntries(Object.entries(values).filter(([, value]) => value !== ""));
      await onSubmit(payload);
    } finally {
      setBusy(false);
    }
  }

  return (
    <Modal
      open
      onOpenChange={(open) => (open ? null : onClose())}
      title={title}
      description={description}
      footer={
        <>
          <Button type="button" variant="outline" onClick={onClose} disabled={busy}>
            {tc("cancel")}
          </Button>
          <Button type="submit" form={formId} disabled={busy}>
            {submitLabel}
          </Button>
        </>
      }
    >
      <form id={formId} onSubmit={submit} className="grid grid-cols-1 gap-3 sm:grid-cols-2">
        {fields.map((field) => (
          <FormField
            key={field.name}
            label={field.label}
            required={field.required}
            helperText={field.helperText}
            className={field.type === "textarea" ? "sm:col-span-2" : undefined}
          >
            {(props) =>
              field.type === "textarea" ? (
                <Textarea
                  {...props}
                  rows={3}
                  value={values[field.name]}
                  onChange={(event) => set(field.name, event.target.value)}
                />
              ) : field.type === "select" ? (
                <Select
                  options={field.options}
                  value={values[field.name]}
                  onValueChange={(value) => set(field.name, value)}
                />
              ) : (
                <Input
                  {...props}
                  type={field.type ?? "text"}
                  step={field.type === "number" ? "any" : undefined}
                  value={values[field.name]}
                  onChange={(event) => set(field.name, event.target.value)}
                />
              )
            }
          </FormField>
        ))}
      </form>
    </Modal>
  );
}

export { FormDialog };
