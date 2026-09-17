"use client";

import { useActionState, useEffect } from "react";
import { FormField } from "@/components/ui/form-field";
import { Select } from "@/components/ui/select";
import { Input } from "@/components/ui/input";
import { Checkbox } from "@/components/ui/checkbox";
import { Button } from "@/components/ui/button";
import { useToastManager } from "@/components/ui/toast";
import { useT } from "@/lib/i18n";

const EVENT_TYPES = [
  "MAINTENANCE_DUE",
  "MAINTENANCE_OVERDUE",
  "BREAKDOWN_REPORTED",
  "BREAKDOWN_CRITICAL",
  "WORK_ORDER_ASSIGNED",
  "WORK_ORDER_COMPLETED",
  "APPROVAL_REQUESTED",
  "APPROVAL_DECIDED",
  "LOW_STOCK",
  "WARRANTY_EXPIRY",
  "AMC_EXPIRY",
  "WEBHOOK_DISABLED",
  "SUPPORT_ACCESS",
  "TICKET_REPLIED",
  "TICKET_RESOLVED",
];

const SEVERITIES = ["INFO", "WARNING", "CRITICAL"];

function formatEventType(type, t) {
  return t(`event_${type.toLowerCase()}`);
}

function formatSeverity(severity, t) {
  return t(`severity_${severity.toLowerCase()}`);
}

/** Mirrors `escalations/index.blade.php`'s "Add a rule" form. */
function EscalationRuleForm({ roles, factories, action }) {
  const t = useT("notification");
  const [state, dispatch, pending] = useActionState(action, null);
  const toastManager = useToastManager();

  useEffect(() => {
    if (state?.status === "success") {
      toastManager.add({ title: t("rule_added"), type: "success" });
    }
    // toastManager is not a stable reference across renders — including it
    // re-fires this effect every render once state first becomes
    // "success", stacking duplicate toasts.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [state]);

  return (
    <form action={dispatch} className="flex flex-col gap-3 rounded-sm border border-border p-4">
      <div className="grid grid-cols-1 gap-3 sm:grid-cols-3">
        <FormField label={t("event_label")} required>
          {(fieldProps) => (
            <Select {...fieldProps} name="event_type" options={EVENT_TYPES.map((type) => ({ value: type, label: formatEventType(type, t) }))} />
          )}
        </FormField>
        <FormField label={t("severity")}>
          {(fieldProps) => (
            <Select
              {...fieldProps}
              name="severity"
              placeholder={t("any_severity")}
              options={SEVERITIES.map((s) => ({ value: s, label: formatSeverity(s, t) }))}
            />
          )}
        </FormField>
        <FormField label={t("factory")}>
          {(fieldProps) => (
            <Select {...fieldProps} name="factory_id" placeholder={t("every_factory")} options={factories.map((f) => ({ value: f.id, label: f.name }))} />
          )}
        </FormField>
      </div>

      <div className="grid grid-cols-1 gap-3 sm:grid-cols-3">
        <FormField label={t("after_minutes")} required error={state?.errors?.delay_minutes?.[0]}>
          {(fieldProps) => <Input {...fieldProps} type="number" min="1" max="10080" name="delay_minutes" defaultValue={30} required />}
        </FormField>
        <FormField label={t("escalation_level")} required error={state?.errors?.escalation_level?.[0]} helperText={t("escalation_level_hint")}>
          {(fieldProps) => <Input {...fieldProps} type="number" min="1" max="5" name="escalation_level" defaultValue={1} required />}
        </FormField>
        <FormField label={t("tell")} required error={state?.errors?.escalation_role_id?.[0]}>
          {(fieldProps) => (
            <Select {...fieldProps} name="escalation_role_id" options={roles.map((r) => ({ value: String(r.id), label: r.name }))} />
          )}
        </FormField>
      </div>

      <label className="flex items-center gap-2 text-sm text-foreground">
        <Checkbox name="stop_on_acknowledge" value="1" defaultChecked />
        {t("stop_on_acknowledge_checkbox")}
      </label>

      {state?.status === "error" && !state.errors ? <p className="text-sm text-danger">{state.message}</p> : null}

      <div>
        <Button type="submit" loading={pending}>
          {t("add_rule")}
        </Button>
      </div>
    </form>
  );
}

export { EscalationRuleForm, formatEventType, formatSeverity };
