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

const PRIORITY_OPTIONS = [
  { value: "CRITICAL", label: "Critical" },
  { value: "HIGH", label: "High" },
  { value: "MEDIUM", label: "Medium" },
  { value: "LOW", label: "Low" },
];

/** Mirrors `work_order::work-orders.create` — a template's published version auto-populates the checklist (`CreateWorkOrder`'s own `template_version_id` handling), same as the web form. */
function WorkOrderForm({ options, action }) {
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

      <FormField label="Title" required error={state?.errors?.title?.[0]}>
        {(fieldProps) => <Input {...fieldProps} value={title} onChange={(e) => setTitle(e.target.value)} maxLength={255} required />}
      </FormField>

      <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <FormField label="Asset" required error={state?.errors?.asset_id?.[0]}>
          {(fieldProps) => (
            <Select
              {...fieldProps}
              value={assetId}
              onValueChange={setAssetId}
              placeholder="Select a machine"
              options={options.assets.map((a) => ({ value: a.id, label: `${a.asset_code} — ${a.name}` }))}
            />
          )}
        </FormField>

        <FormField label="Maintenance type" required error={state?.errors?.maintenance_type_id?.[0]}>
          {(fieldProps) => (
            <Select
              {...fieldProps}
              value={maintenanceTypeId}
              onValueChange={setMaintenanceTypeId}
              placeholder="Select a type"
              options={options.maintenance_types.map((t) => ({ value: t.id, label: t.name }))}
            />
          )}
        </FormField>

        <FormField label="Priority" required>
          {(fieldProps) => <Select {...fieldProps} value={priority} onValueChange={setPriority} options={PRIORITY_OPTIONS} />}
        </FormField>

        <FormField label="Team">
          {(fieldProps) => (
            <Select
              {...fieldProps}
              value={teamId}
              onValueChange={setTeamId}
              placeholder="Unassigned"
              options={options.teams.map((t) => ({ value: t.id, label: t.name }))}
            />
          )}
        </FormField>

        <FormField label="Template" helperText="Pre-fills the checklist from a published maintenance template.">
          {(fieldProps) => (
            <Select
              {...fieldProps}
              value={templateVersionId}
              onValueChange={setTemplateVersionId}
              placeholder="None"
              options={options.templates.map((t) => ({ value: t.current_version_id, label: `${t.name} (v${t.version_number})` }))}
            />
          )}
        </FormField>

        <FormField label="Scheduled start">{() => <DatePicker value={scheduledStart} onChange={setScheduledStart} />}</FormField>
        <FormField label="Scheduled end" error={state?.errors?.scheduled_end?.[0]}>
          {() => <DatePicker value={scheduledEnd} onChange={setScheduledEnd} />}
        </FormField>

        <FormField label="Estimated parts cost" error={state?.errors?.estimated_parts_cost?.[0]}>
          {(fieldProps) => <Input {...fieldProps} type="number" step="0.0001" min="0" value={estimatedPartsCost} onChange={(e) => setEstimatedPartsCost(e.target.value)} />}
        </FormField>
      </div>

      <FormField label="Description">
        {(fieldProps) => <Textarea {...fieldProps} value={description} onChange={(e) => setDescription(e.target.value)} rows={3} maxLength={5000} />}
      </FormField>

      <label className="flex items-center gap-2 text-sm text-foreground">
        <Checkbox checked={requiresShutdown} onCheckedChange={setRequiresShutdown} /> Requires the machine to be shut down
      </label>

      <div className="flex justify-end gap-2">
        <Button type="button" variant="outline" onClick={() => router.back()}>
          Cancel
        </Button>
        <Button type="submit" loading={pending}>
          Create work order
        </Button>
      </div>
    </form>
  );
}

export { WorkOrderForm };
