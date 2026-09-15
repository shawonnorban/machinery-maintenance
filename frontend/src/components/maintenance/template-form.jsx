"use client";

import { startTransition, useActionState, useState } from "react";
import { useRouter } from "next/navigation";
import { FormField } from "@/components/ui/form-field";
import { Input } from "@/components/ui/input";
import { Textarea } from "@/components/ui/textarea";
import { Select } from "@/components/ui/select";
import { Button } from "@/components/ui/button";
import { Alert } from "@/components/ui/alert";

/** Mirrors `templates::form.blade.php`. The code ties every version of a checklist together, so it's set once on create and never edited. */
function TemplateForm({ template = null, options, action }) {
  const isEdit = template !== null;
  const router = useRouter();
  const [state, dispatch, pending] = useActionState(action, null);
  const [name, setName] = useState(template?.name ?? "");
  const [code, setCode] = useState(template?.code ?? "");
  const [assetTypeId, setAssetTypeId] = useState(template?.asset_type_id ?? "");
  const [maintenanceTypeId, setMaintenanceTypeId] = useState(template?.maintenance_type_id ?? "");
  const [description, setDescription] = useState(template?.description ?? "");
  const [estimatedDurationMinutes, setEstimatedDurationMinutes] = useState(template?.version?.estimated_duration_minutes ?? "");

  function handleSubmit(event) {
    event.preventDefault();

    const formData = new FormData();
    formData.set("name", name);
    if (!isEdit) formData.set("code", code);
    if (assetTypeId) formData.set("asset_type_id", assetTypeId);
    if (maintenanceTypeId) formData.set("maintenance_type_id", maintenanceTypeId);
    if (description) formData.set("description", description);
    if (!isEdit && estimatedDurationMinutes) formData.set("estimated_duration_minutes", estimatedDurationMinutes);

    startTransition(() => dispatch(formData));
  }

  return (
    <form onSubmit={handleSubmit} className="flex flex-col gap-5">
      {state?.status === "error" && !state.errors ? <Alert variant="danger">{state.message}</Alert> : null}

      <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <FormField label="Name" required error={state?.errors?.name?.[0]}>
          {(fieldProps) => <Input {...fieldProps} value={name} onChange={(e) => setName(e.target.value)} maxLength={255} required />}
        </FormField>
        <FormField label="Code" required={!isEdit} error={state?.errors?.code?.[0]} helperText={isEdit ? "Ties every version together — never editable." : undefined}>
          {(fieldProps) => <Input {...fieldProps} value={code} onChange={(e) => setCode(e.target.value)} maxLength={64} required={!isEdit} disabled={isEdit} />}
        </FormField>
        <FormField label="Asset type">
          {(fieldProps) => (
            <Select
              {...fieldProps}
              value={assetTypeId}
              onValueChange={setAssetTypeId}
              placeholder="—"
              options={options.asset_types.map((t) => ({ value: t.id, label: t.name }))}
            />
          )}
        </FormField>
        <FormField label="Maintenance type">
          {(fieldProps) => (
            <Select
              {...fieldProps}
              value={maintenanceTypeId}
              onValueChange={setMaintenanceTypeId}
              placeholder="—"
              options={options.maintenance_types.map((t) => ({ value: t.id, label: t.name }))}
            />
          )}
        </FormField>
        {!isEdit ? (
          <FormField label="Estimated duration (minutes)">
            {(fieldProps) => (
              <Input {...fieldProps} type="number" min="1" max="10080" value={estimatedDurationMinutes} onChange={(e) => setEstimatedDurationMinutes(e.target.value)} />
            )}
          </FormField>
        ) : null}
      </div>

      <FormField label="Description">
        {(fieldProps) => <Textarea {...fieldProps} value={description} onChange={(e) => setDescription(e.target.value)} rows={3} maxLength={2000} />}
      </FormField>

      <div className="flex justify-end gap-2">
        <Button type="button" variant="outline" onClick={() => router.back()}>
          Cancel
        </Button>
        <Button type="submit" loading={pending}>
          {isEdit ? "Save changes" : "Create checklist"}
        </Button>
      </div>
    </form>
  );
}

export { TemplateForm };
