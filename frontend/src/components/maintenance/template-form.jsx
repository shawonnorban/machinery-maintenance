"use client";

import { startTransition, useActionState, useState } from "react";
import { useRouter } from "next/navigation";
import { FormField } from "@/components/ui/form-field";
import { Input } from "@/components/ui/input";
import { Textarea } from "@/components/ui/textarea";
import { Select } from "@/components/ui/select";
import { Button } from "@/components/ui/button";
import { Alert } from "@/components/ui/alert";
import { useT } from "@/lib/i18n";

/** Mirrors `templates::form.blade.php`. The code ties every version of a checklist together, so it's set once on create and never edited. */
function TemplateForm({ template = null, options, action }) {
  const t = useT("maintenance");
  const tc = useT("common");
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
        <FormField label={t("name")} required error={state?.errors?.name?.[0]}>
          {(fieldProps) => <Input {...fieldProps} value={name} onChange={(e) => setName(e.target.value)} maxLength={255} required />}
        </FormField>
        <FormField label={t("code")} required={!isEdit} error={state?.errors?.code?.[0]} helperText={isEdit ? t("code_readonly_hint") : undefined}>
          {(fieldProps) => <Input {...fieldProps} value={code} onChange={(e) => setCode(e.target.value)} maxLength={64} required={!isEdit} disabled={isEdit} />}
        </FormField>
        <FormField label={t("asset_type")}>
          {(fieldProps) => (
            <Select
              {...fieldProps}
              value={assetTypeId}
              onValueChange={setAssetTypeId}
              placeholder="—"
              options={options.asset_types.map((type) => ({ value: type.id, label: type.name }))}
            />
          )}
        </FormField>
        <FormField label={t("maintenance_type")}>
          {(fieldProps) => (
            <Select
              {...fieldProps}
              value={maintenanceTypeId}
              onValueChange={setMaintenanceTypeId}
              placeholder="—"
              options={options.maintenance_types.map((type) => ({ value: type.id, label: type.name }))}
            />
          )}
        </FormField>
        {!isEdit ? (
          <FormField label={t("estimated_minutes")}>
            {(fieldProps) => (
              <Input {...fieldProps} type="number" min="1" max="10080" value={estimatedDurationMinutes} onChange={(e) => setEstimatedDurationMinutes(e.target.value)} />
            )}
          </FormField>
        ) : null}
      </div>

      <FormField label={t("description")}>
        {(fieldProps) => <Textarea {...fieldProps} value={description} onChange={(e) => setDescription(e.target.value)} rows={3} maxLength={2000} />}
      </FormField>

      <div className="flex justify-end gap-2">
        <Button type="button" variant="outline" onClick={() => router.back()}>
          {tc("cancel")}
        </Button>
        <Button type="submit" loading={pending}>
          {isEdit ? t("save_changes") : t("create_checklist")}
        </Button>
      </div>
    </form>
  );
}

export { TemplateForm };
