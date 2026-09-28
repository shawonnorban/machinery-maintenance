"use client";

import { DynamicField } from "@/components/settings/dynamic-field";

/**
 * Renders a `CustomFieldValueApiController::show()` schema array via the
 * same `DynamicField` master-data already uses for its own schema-driven
 * forms — `SELECT` is the only field_type name mismatch (`DynamicField`
 * expects `ENUM`), translated at the mapping boundary here rather than
 * inside `DynamicField` itself, which knows nothing about custom fields.
 *
 * @param {{ schema: object[], values: Record<string, unknown>, onChange: (code: string, value: unknown) => void, errors?: Record<string, string[]> }} props
 */
function CustomFieldsSection({ schema, values, onChange, errors }) {
  if (!schema || schema.length === 0) {
    return null;
  }

  return (
    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
      {schema.map((definition) => (
        <DynamicField
          key={definition.code}
          field={{
            name: definition.code,
            label: definition.label,
            type: definition.field_type === "SELECT" ? "ENUM" : definition.field_type,
            options: definition.options,
            required: definition.required,
          }}
          value={values[definition.code] ?? null}
          onChange={(value) => onChange(definition.code, value)}
          referenceOptions={{}}
          error={errors?.[`custom_fields.${definition.code}`]?.[0]}
        />
      ))}
    </div>
  );
}

export { CustomFieldsSection };
