"use client";

import { startTransition, useActionState, useEffect, useState } from "react";
import { useRouter } from "next/navigation";
import { Plus, Trash2 } from "lucide-react";
import { Button } from "@/components/ui/button";
import { Modal } from "@/components/ui/modal";
import { FormField } from "@/components/ui/form-field";
import { Input } from "@/components/ui/input";
import { Select } from "@/components/ui/select";
import { Checkbox } from "@/components/ui/checkbox";
import { useToastManager } from "@/components/ui/toast";
import { useT } from "@/lib/i18n";

const FIELD_TYPES = ["TEXT", "NUMBER", "DATE", "BOOLEAN", "SELECT"];

/**
 * Create/edit a single `CustomFieldDefinition` — a fixed set of fields
 * (unlike `RowFormModal`, which is schema-driven), so this stays its own
 * component rather than a `DynamicField` consumer.
 *
 * `code` is only editable on create — `SaveCustomFieldDefinition::update()`
 * never accepts it, matching this modal disabling the input on edit.
 */
function CustomFieldDefinitionModal({ open, onOpenChange, title, definition, action }) {
  const t = useT("custom_field");
  const tc = useT("common");
  const [state, dispatch, pending] = useActionState(action, null);
  const [code, setCode] = useState(definition?.code ?? "");
  const [label, setLabel] = useState(definition?.label ?? "");
  const [fieldType, setFieldType] = useState(definition?.field_type ?? "TEXT");
  const [options, setOptions] = useState(definition?.options?.length ? definition.options : [""]);
  const [required, setRequired] = useState(definition?.required ?? false);
  const [sortOrder, setSortOrder] = useState(definition?.sort_order ?? 0);
  const router = useRouter();
  const toastManager = useToastManager();

  useEffect(() => {
    if (state?.status === "success") {
      toastManager.add({ title: t("field_saved_toast"), type: "success" });
      queueMicrotask(() => onOpenChange(false));
      router.refresh();
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [state]);

  function handleSubmit(event) {
    event.preventDefault();

    const formData = new FormData();
    if (!definition) formData.set("code", code);
    formData.set("label", label);
    formData.set("field_type", fieldType);
    formData.set("required", required ? "1" : "0");
    formData.set("sort_order", String(sortOrder));
    if (fieldType === "SELECT") {
      for (const option of options) {
        if (option.trim() !== "") formData.append("options", option);
      }
    }

    startTransition(() => dispatch(formData));
  }

  return (
    <Modal open={open} onOpenChange={onOpenChange} title={title}>
      <form onSubmit={handleSubmit} className="flex flex-col gap-4">
        {state?.status === "error" && !state.errors ? <p className="text-sm text-danger">{state.message}</p> : null}

        <FormField label={t("code")} required helperText={definition ? undefined : t("code_hint")} error={state?.errors?.code?.[0]}>
          {(fieldProps) => (
            <Input {...fieldProps} value={code} onChange={(e) => setCode(e.target.value)} disabled={Boolean(definition)} maxLength={64} required />
          )}
        </FormField>

        <FormField label={t("label")} required error={state?.errors?.label?.[0]}>
          {(fieldProps) => <Input {...fieldProps} value={label} onChange={(e) => setLabel(e.target.value)} maxLength={255} required />}
        </FormField>

        <FormField label={t("field_type")} required error={state?.errors?.field_type?.[0]}>
          {(fieldProps) => (
            <Select
              {...fieldProps}
              value={fieldType}
              onValueChange={setFieldType}
              options={FIELD_TYPES.map((type) => ({ value: type, label: t(`field_type_${type.toLowerCase()}`) }))}
            />
          )}
        </FormField>

        {fieldType === "SELECT" ? (
          <div className="flex flex-col gap-2">
            <span className="text-sm font-medium text-foreground">{t("options")}</span>
            {state?.errors?.options?.[0] ? <p className="text-sm text-danger">{state.errors.options[0]}</p> : null}
            {options.map((option, index) => (
              <div key={index} className="flex items-center gap-2">
                <Input
                  value={option}
                  onChange={(e) => setOptions((current) => current.map((o, i) => (i === index ? e.target.value : o)))}
                  maxLength={255}
                />
                <Button
                  type="button"
                  variant="ghost"
                  size="icon"
                  aria-label={tc("delete")}
                  onClick={() => setOptions((current) => current.filter((_, i) => i !== index))}
                >
                  <Trash2 className="text-danger" />
                </Button>
              </div>
            ))}
            <div>
              <Button type="button" size="sm" variant="outline" onClick={() => setOptions((current) => [...current, ""])}>
                <Plus /> {t("add_option")}
              </Button>
            </div>
          </div>
        ) : null}

        <label className="flex items-center gap-2 text-sm text-foreground">
          <Checkbox checked={required} onCheckedChange={setRequired} />
          {t("required")}
        </label>

        <FormField label={t("sort_order")}>
          {(fieldProps) => <Input {...fieldProps} type="number" value={sortOrder} onChange={(e) => setSortOrder(e.target.value)} />}
        </FormField>

        <div className="mt-2 flex justify-end gap-2">
          <Button type="button" variant="outline" onClick={() => onOpenChange(false)}>
            {tc("cancel")}
          </Button>
          <Button type="submit" loading={pending}>
            {definition ? tc("save") : t("new_field")}
          </Button>
        </div>
      </form>
    </Modal>
  );
}

export { CustomFieldDefinitionModal };
