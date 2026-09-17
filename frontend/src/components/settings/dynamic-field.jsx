"use client";

import { FormField } from "@/components/ui/form-field";
import { Input } from "@/components/ui/input";
import { Select } from "@/components/ui/select";
import { Checkbox } from "@/components/ui/checkbox";
import { useT } from "@/lib/i18n";

/**
 * Renders the one control a `Field` (app/Modules/Settings/MasterData/
 * Field.php) type maps to — the client-side half of the schema-driven
 * form the API's `meta.schema` (MasterDataApiController::show()) exists
 * to describe. One component for all ~21 master-data types, the same
 * reason the backend is one controller for all of them.
 */
function DynamicField({ field, value, onChange, referenceOptions, error }) {
  const t = useT("masterdata");

  if (field.type === "BOOLEAN") {
    return (
      <label className="flex items-center gap-2 text-sm text-foreground">
        <Checkbox checked={Boolean(value)} onCheckedChange={onChange} />
        {field.label}
      </label>
    );
  }

  if (field.type === "ENUM") {
    return (
      <FormField label={field.label} required={field.required} error={error}>
        {(fieldProps) => (
          <Select
            {...fieldProps}
            value={value ?? ""}
            onValueChange={onChange}
            options={field.options.map((option) => ({ value: option, label: option }))}
          />
        )}
      </FormField>
    );
  }

  if (field.type === "REFERENCE" || field.type === "BELONGS_TO") {
    const options = referenceOptions[field.name] ?? [];

    return (
      <FormField label={field.label} required={field.required} error={error}>
        {(fieldProps) => (
          <Select
            {...fieldProps}
            value={value ?? ""}
            onValueChange={onChange}
            placeholder={t("select_placeholder")}
            options={options.map((option) => ({ value: option.id, label: option.label }))}
          />
        )}
      </FormField>
    );
  }

  // TEXT and CODE both end up here — a code is still typed as plain text,
  // just uppercased server-side (SaveMasterDataRow) and shown that way
  // back, so this needs no special-casing on its own.
  return (
    <FormField label={field.label} required={field.required} error={error}>
      {(fieldProps) => <Input {...fieldProps} value={value ?? ""} onChange={(e) => onChange(e.target.value)} maxLength={255} />}
    </FormField>
  );
}

export { DynamicField };
