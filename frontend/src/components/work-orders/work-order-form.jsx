"use client";

import { startTransition, useActionState, useState } from "react";
import { useRouter } from "next/navigation";
import { FormField } from "@/components/ui/form-field";
import { Input } from "@/components/ui/input";
import { Textarea } from "@/components/ui/textarea";
import { Select } from "@/components/ui/select";
import { DatePicker } from "@/components/ui/date-picker";
import { Checkbox } from "@/components/ui/checkbox";
import { Button } from "@/components/ui/button";
import { Alert } from "@/components/ui/alert";
import { useT } from "@/lib/i18n";

/** Mirrors `work_order::work-orders.create` — a template's published version auto-populates the checklist (`CreateWorkOrder`'s own `template_version_id` handling), same as the web form. */
function WorkOrderForm({ options, action }) {
  const t = useT("work_order");
  const tc = useT("common");
  const PRIORITY_OPTIONS = [
    { value: "CRITICAL", label: t("priority_critical") },
    { value: "HIGH", label: t("priority_high") },
    { value: "MEDIUM", label: t("priority_medium") },
    { value: "LOW", label: t("priority_low") },
  ];
  const router = useRouter();
  const [state, dispatch, pending] = useActionState(action, null);

  const [assetId, setAssetId] = useState("");
  const [maintenanceTypeId, setMaintenanceTypeId] = useState("");
  const [title, setTitle] = useState("");
  const [description, setDescription] = useState("");
  const [priority, setPriority] = useState("MEDIUM");
  const [teamId, setTeamId] = useState("");
  const [templateVersionId, setTemplateVersionId] = useState("");
  const [requiresShutdown, setRequiresShutdown] = useState(false);
  const [scheduledStart, setScheduledStart] = useState(null);
  const [scheduledEnd, setScheduledEnd] = useState(null);
  const [estimatedPartsCost, setEstimatedPartsCost] = useState("");

  function handleSubmit(event) {
    event.preventDefault();

    const formData = new FormData();
    formData.set("asset_id", assetId);
    formData.set("maintenance_type_id", maintenanceTypeId);
    formData.set("title", title);
    if (description) formData.set("description", description);
    formData.set("priority", priority);
    formData.set("requires_shutdown", requiresShutdown ? "1" : "0");
    if (teamId) formData.set("assigned_team_id", teamId);
    if (templateVersionId) formData.set("template_version_id", templateVersionId);
    if (scheduledStart) formData.set("scheduled_start", scheduledStart);
    if (scheduledEnd) formData.set("scheduled_end", scheduledEnd);
    if (estimatedPartsCost) formData.set("estimated_parts_cost", estimatedPartsCost);

    startTransition(() => dispatch(formData));
  }

  return (
    <form onSubmit={handleSubmit} className="flex flex-col gap-5">
      {state?.status === "error" && !state.errors ? <Alert variant="danger">{state.message}</Alert> : null}

      <FormField label={t("title")} required error={state?.errors?.title?.[0]}>
        {(fieldProps) => <Input {...fieldProps} value={title} onChange={(e) => setTitle(e.target.value)} maxLength={255} required />}
      </FormField>

      <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <FormField label={t("asset")} required error={state?.errors?.asset_id?.[0]}>
          {(fieldProps) => (
            <Select
              {...fieldProps}
              value={assetId}
              onValueChange={setAssetId}
              placeholder={t("select_a_machine")}
              options={options.assets.map((a) => ({ value: a.id, label: `${a.asset_code} — ${a.name}` }))}
            />
          )}
        </FormField>

        <FormField label={t("maintenance_type")} required error={state?.errors?.maintenance_type_id?.[0]}>
          {(fieldProps) => (
            <Select
              {...fieldProps}
              value={maintenanceTypeId}
              onValueChange={setMaintenanceTypeId}
              placeholder={t("select_a_type")}
              options={options.maintenance_types.map((mt) => ({ value: mt.id, label: mt.name }))}
            />
          )}
        </FormField>

        <FormField label={t("priority")} required>
          {(fieldProps) => <Select {...fieldProps} value={priority} onValueChange={setPriority} options={PRIORITY_OPTIONS} />}
        </FormField>

        <FormField label={t("team")}>
          {(fieldProps) => (
            <Select
              {...fieldProps}
              value={teamId}
              onValueChange={setTeamId}
              placeholder={t("unassigned_option")}
              options={options.teams.map((team) => ({ value: team.id, label: team.name }))}
            />
          )}
        </FormField>

        <FormField label={t("template")} helperText={t("template_hint")}>
          {(fieldProps) => (
            <Select
              {...fieldProps}
              value={templateVersionId}
              onValueChange={setTemplateVersionId}
              placeholder={t("none_option")}
              options={options.templates.map((tpl) => ({ value: tpl.current_version_id, label: `${tpl.name} (v${tpl.version_number})` }))}
            />
          )}
        </FormField>

        <FormField label={t("scheduled_start")}>{() => <DatePicker value={scheduledStart} onChange={setScheduledStart} />}</FormField>
        <FormField label={t("scheduled_end")} error={state?.errors?.scheduled_end?.[0]}>
          {() => <DatePicker value={scheduledEnd} onChange={setScheduledEnd} />}
        </FormField>

        <FormField label={t("estimated_parts_cost")} error={state?.errors?.estimated_parts_cost?.[0]}>
          {(fieldProps) => <Input {...fieldProps} type="number" step="0.0001" min="0" value={estimatedPartsCost} onChange={(e) => setEstimatedPartsCost(e.target.value)} />}
        </FormField>
      </div>

      <FormField label={t("description")}>
        {(fieldProps) => <Textarea {...fieldProps} value={description} onChange={(e) => setDescription(e.target.value)} rows={3} maxLength={5000} />}
      </FormField>

      <label className="flex items-center gap-2 text-sm text-foreground">
        <Checkbox checked={requiresShutdown} onCheckedChange={setRequiresShutdown} /> {t("requires_shutdown_checkbox")}
      </label>

      <div className="flex justify-end gap-2">
        <Button type="button" variant="outline" onClick={() => router.back()}>
          {tc("cancel")}
        </Button>
        <Button type="submit" loading={pending}>
          {t("create_work_order")}
        </Button>
      </div>
    </form>
  );
}

export { WorkOrderForm };
