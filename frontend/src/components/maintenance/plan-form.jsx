"use client";

import { startTransition, useActionState, useState } from "react";
import { useRouter } from "next/navigation";
import { FormField } from "@/components/ui/form-field";
import { Input } from "@/components/ui/input";
import { Select } from "@/components/ui/select";
import { DatePicker } from "@/components/ui/date-picker";
import { Checkbox } from "@/components/ui/checkbox";
import { Button } from "@/components/ui/button";
import { Alert } from "@/components/ui/alert";
import { formatStatus } from "@/components/ui/status-badge";
import { PlanPreviewPanel } from "@/components/maintenance/plan-preview-panel";

const TRIGGER_OPTIONS = ["TIME", "METER", "COMBINED"].map((value) => ({ value, label: formatStatus(value) }));
const MODE_OPTIONS = [
  { value: "ROLLING", label: "Rolling — from when it was last done" },
  { value: "FIXED", label: "Fixed — from the calendar, regardless of when it was last done" },
];
const LOGIC_OPTIONS = [
  { value: "OR", label: "Whichever comes first" },
  { value: "AND", label: "Both required" },
];
const INTERVAL_UNITS = ["DAY", "WEEK", "MONTH", "QUARTER", "YEAR", "HOUR"].map((value) => ({ value, label: value }));
const PRIORITY_OPTIONS = ["CRITICAL", "HIGH", "MEDIUM", "LOW"].map((value) => ({ value, label: formatStatus(value) }));
const NON_WORKING_DAY_OPTIONS = [
  { value: "NEXT_WORKING_DAY", label: "Move to the next working day" },
  { value: "PREVIOUS_WORKING_DAY", label: "Move to the previous working day" },
  { value: "NONE", label: "Leave it on the calendar date" },
];

/** Mirrors `plans::_form.blade.php`, including its live due-date preview panel. */
function PlanForm({ plan = null, options, action, previewAction }) {
  const isEdit = plan !== null;
  const router = useRouter();
  const [state, dispatch, pending] = useActionState(action, null);

  const timeRule = plan?.rules?.find((r) => r.rule_type === "TIME");
  const meterRule = plan?.rules?.find((r) => r.rule_type === "METER");

  const [name, setName] = useState(plan?.name ?? "");
  const [assetId, setAssetId] = useState(plan?.asset_id ?? "");
  const [assetTypeId, setAssetTypeId] = useState(plan?.asset_type_id ?? "");
  const [maintenanceTypeId, setMaintenanceTypeId] = useState(plan?.maintenance_type_id ?? "");
  const [templateVersionId, setTemplateVersionId] = useState(plan?.template_version_id ?? "");
  const [triggerType, setTriggerType] = useState(plan?.trigger_type ?? "TIME");
  const [scheduleMode, setScheduleMode] = useState(plan?.schedule_mode ?? "ROLLING");
  const [ruleLogic, setRuleLogic] = useState(plan?.rule_logic ?? "OR");
  const [intervalValue, setIntervalValue] = useState(timeRule ? String(Math.trunc(Number(timeRule.value))) : "30");
  const [intervalUnit, setIntervalUnit] = useState(timeRule?.unit ?? "DAY");
  const [meterThreshold, setMeterThreshold] = useState(meterRule?.value ?? "");
  const [meterTypeId, setMeterTypeId] = useState(meterRule?.meter_type_id ?? "");
  const [startDate, setStartDate] = useState(plan?.start_date ?? new Date().toISOString().slice(0, 10));
  const [endDate, setEndDate] = useState(plan?.end_date ?? null);
  const [priority, setPriority] = useState(plan?.priority ?? "MEDIUM");
  const [nonWorkingDayPolicy, setNonWorkingDayPolicy] = useState(plan?.non_working_day_policy ?? "NEXT_WORKING_DAY");
  const [gracePeriodMinutes, setGracePeriodMinutes] = useState(plan?.grace_period_minutes ?? 2880);
  const [leadTimeDays, setLeadTimeDays] = useState(plan?.lead_time_days ?? 30);
  const [assignedTeamId, setAssignedTeamId] = useState(plan?.assigned_team_id ?? "");
  const [estimatedDurationMinutes, setEstimatedDurationMinutes] = useState(plan?.estimated_duration_minutes ?? "");
  const [requiresShutdown, setRequiresShutdown] = useState(plan?.requires_shutdown ?? false);

  function handleSubmit(event) {
    event.preventDefault();

    const formData = new FormData();
    formData.set("name", name);
    if (assetId) formData.set("asset_id", assetId);
    if (assetTypeId) formData.set("asset_type_id", assetTypeId);
    formData.set("maintenance_type_id", maintenanceTypeId);
    if (templateVersionId) formData.set("template_version_id", templateVersionId);
    formData.set("trigger_type", triggerType);
    formData.set("schedule_mode", scheduleMode);
    if (triggerType === "COMBINED") formData.set("rule_logic", ruleLogic);
    if (triggerType === "TIME" || triggerType === "COMBINED") {
      formData.set("interval_value", intervalValue);
      formData.set("interval_unit", intervalUnit);
    }
    if (triggerType === "METER" || triggerType === "COMBINED") {
      formData.set("meter_threshold", meterThreshold);
      if (meterTypeId) formData.set("meter_type_id", meterTypeId);
    }
    formData.set("start_date", startDate);
    if (endDate) formData.set("end_date", endDate);
    formData.set("priority", priority);
    formData.set("non_working_day_policy", nonWorkingDayPolicy);
    formData.set("grace_period_minutes", String(gracePeriodMinutes));
    formData.set("lead_time_days", String(leadTimeDays));
    if (assignedTeamId) formData.set("assigned_team_id", assignedTeamId);
    if (estimatedDurationMinutes) formData.set("estimated_duration_minutes", String(estimatedDurationMinutes));
    formData.set("requires_shutdown", requiresShutdown ? "1" : "0");

    startTransition(() => dispatch(formData));
  }

  return (
    <div className="grid grid-cols-1 gap-6 lg:grid-cols-[1fr_280px]">
      <form onSubmit={handleSubmit} className="flex flex-col gap-5">
      {state?.status === "error" && !state.errors ? <Alert variant="danger">{state.message}</Alert> : null}

      <FormField label="Name" required error={state?.errors?.name?.[0]}>
        {(fieldProps) => <Input {...fieldProps} value={name} onChange={(e) => setName(e.target.value)} maxLength={255} required />}
      </FormField>

      <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <FormField label="Target asset" error={state?.errors?.asset_id?.[0]}>
          {(fieldProps) => (
            <Select
              {...fieldProps}
              value={assetId}
              onValueChange={(value) => {
                setAssetId(value);
                if (value) setAssetTypeId("");
              }}
              placeholder="—"
              options={options.assets.map((a) => ({ value: a.id, label: `${a.asset_code} — ${a.name}` }))}
            />
          )}
        </FormField>
        <FormField label="Target asset type" helperText="Exactly one of asset or asset type — never both, never neither.">
          {(fieldProps) => (
            <Select
              {...fieldProps}
              value={assetTypeId}
              onValueChange={(value) => {
                setAssetTypeId(value);
                if (value) setAssetId("");
              }}
              placeholder="—"
              options={options.asset_types.map((t) => ({ value: t.id, label: t.name }))}
            />
          )}
        </FormField>
      </div>

      <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <FormField label="Maintenance type" required error={state?.errors?.maintenance_type_id?.[0]}>
          {(fieldProps) => (
            <Select
              {...fieldProps}
              value={maintenanceTypeId}
              onValueChange={setMaintenanceTypeId}
              options={options.maintenance_types.map((t) => ({ value: t.id, label: t.name }))}
            />
          )}
        </FormField>
        <FormField label="Template" helperText="Only published versions are listed.">
          {(fieldProps) => (
            <Select
              {...fieldProps}
              value={templateVersionId}
              onValueChange={setTemplateVersionId}
              placeholder="—"
              options={options.templates.map((t) => ({ value: t.current_version_id, label: t.name }))}
            />
          )}
        </FormField>
      </div>

      <hr className="border-border" />

      <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
        <FormField label="Trigger" required>
          {(fieldProps) => <Select {...fieldProps} value={triggerType} onValueChange={setTriggerType} options={TRIGGER_OPTIONS} />}
        </FormField>
        <FormField label="Schedule mode" required>
          {(fieldProps) => <Select {...fieldProps} value={scheduleMode} onValueChange={setScheduleMode} options={MODE_OPTIONS} />}
        </FormField>
        {triggerType === "COMBINED" ? (
          <FormField label="Rule logic" required error={state?.errors?.rule_logic?.[0]}>
            {(fieldProps) => <Select {...fieldProps} value={ruleLogic} onValueChange={setRuleLogic} options={LOGIC_OPTIONS} />}
          </FormField>
        ) : null}
      </div>

      {triggerType === "TIME" || triggerType === "COMBINED" ? (
        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
          <FormField label="Every" required error={state?.errors?.interval_value?.[0]}>
            {(fieldProps) => (
              <Input {...fieldProps} type="number" min="1" max="9999" value={intervalValue} onChange={(e) => setIntervalValue(e.target.value)} required />
            )}
          </FormField>
          <FormField label="Interval">
            {(fieldProps) => <Select {...fieldProps} value={intervalUnit} onValueChange={setIntervalUnit} options={INTERVAL_UNITS} />}
          </FormField>
        </div>
      ) : null}

      {triggerType === "METER" || triggerType === "COMBINED" ? (
        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
          <FormField label="Meter threshold" required error={state?.errors?.meter_threshold?.[0]}>
            {(fieldProps) => (
              <Input {...fieldProps} type="number" step="0.0001" min="0" value={meterThreshold} onChange={(e) => setMeterThreshold(e.target.value)} required />
            )}
          </FormField>
          <FormField label="Meter type">
            {(fieldProps) => (
              <Select
                {...fieldProps}
                value={meterTypeId}
                onValueChange={setMeterTypeId}
                placeholder="—"
                options={options.meter_types.map((t) => ({ value: t.id, label: t.name }))}
              />
            )}
          </FormField>
        </div>
      ) : null}

      <hr className="border-border" />

      <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
        <FormField label="Start date" required error={state?.errors?.start_date?.[0]}>
          {() => <DatePicker value={startDate} onChange={setStartDate} />}
        </FormField>
        <FormField label="End date" error={state?.errors?.end_date?.[0]}>
          {() => <DatePicker value={endDate} onChange={setEndDate} />}
        </FormField>
        <FormField label="Priority" required>
          {(fieldProps) => <Select {...fieldProps} value={priority} onValueChange={setPriority} options={PRIORITY_OPTIONS} />}
        </FormField>
        <FormField label="Non-working day">
          {(fieldProps) => <Select {...fieldProps} value={nonWorkingDayPolicy} onValueChange={setNonWorkingDayPolicy} options={NON_WORKING_DAY_OPTIONS} />}
        </FormField>
        <FormField label="Grace (minutes)">
          {(fieldProps) => (
            <Input {...fieldProps} type="number" min="0" max="43200" value={gracePeriodMinutes} onChange={(e) => setGracePeriodMinutes(e.target.value)} />
          )}
        </FormField>
        <FormField label="Lead time (days)">
          {(fieldProps) => <Input {...fieldProps} type="number" min="1" max="730" value={leadTimeDays} onChange={(e) => setLeadTimeDays(e.target.value)} />}
        </FormField>
        <FormField label="Team">
          {(fieldProps) => (
            <Select
              {...fieldProps}
              value={assignedTeamId}
              onValueChange={setAssignedTeamId}
              placeholder="Unassigned"
              options={options.teams.map((t) => ({ value: t.id, label: t.name }))}
            />
          )}
        </FormField>
        <FormField label="Estimated duration (minutes)">
          {(fieldProps) => (
            <Input {...fieldProps} type="number" min="1" max="10080" value={estimatedDurationMinutes} onChange={(e) => setEstimatedDurationMinutes(e.target.value)} />
          )}
        </FormField>
      </div>

      <label className="flex items-center gap-2 text-sm text-foreground">
        <Checkbox checked={requiresShutdown} onCheckedChange={setRequiresShutdown} /> Requires the machine to be shut down
      </label>

      <div className="flex justify-end gap-2">
        <Button type="button" variant="outline" onClick={() => router.back()}>
          Cancel
        </Button>
        <Button type="submit" loading={pending}>
          {isEdit ? "Save changes" : "Create plan"}
        </Button>
      </div>
      </form>

      <PlanPreviewPanel
        scheduleMode={scheduleMode}
        startDate={startDate}
        intervalValue={intervalValue}
        intervalUnit={intervalUnit}
        nonWorkingDayPolicy={nonWorkingDayPolicy}
        previewAction={previewAction}
      />
    </div>
  );
}

export { PlanForm };
