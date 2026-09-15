"use client";

import { useActionState, useEffect } from "react";
import { FormField } from "@/components/ui/form-field";
import { Select } from "@/components/ui/select";
import { Input } from "@/components/ui/input";
import { Checkbox } from "@/components/ui/checkbox";
import { Button } from "@/components/ui/button";
import { useToastManager } from "@/components/ui/toast";

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

function formatEventType(type) {
  return type
    .toLowerCase()
    .split("_")
    .map((w) => w[0].toUpperCase() + w.slice(1))
    .join(" ");
}

/** Mirrors `escalations/index.blade.php`'s "Add a rule" form. */
function EscalationRuleForm({ roles, factories, action }) {
  const [state, dispatch, pending] = useActionState(action, null);
  const toastManager = useToastManager();

  useEffect(() => {
    if (state?.status === "success") {
      toastManager.add({ title: "Rule added", type: "success" });
    }
    // toastManager is not a stable reference across renders — including it
    // re-fires this effect every render once state first becomes
    // "success", stacking duplicate toasts.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [state]);

  return (
    <form action={dispatch} className="flex flex-col gap-3 rounded-sm border border-border p-4">
      <div className="grid grid-cols-1 gap-3 sm:grid-cols-3">
        <FormField label="Event" required>
          {(fieldProps) => (
            <Select {...fieldProps} name="event_type" options={EVENT_TYPES.map((t) => ({ value: t, label: formatEventType(t) }))} />
          )}
        </FormField>
        <FormField label="Severity">
          {(fieldProps) => (
            <Select
              {...fieldProps}
              name="severity"
              placeholder="Any severity"
              options={SEVERITIES.map((s) => ({ value: s, label: formatEventType(s) }))}
            />
          )}
        </FormField>
        <FormField label="Factory">
          {(fieldProps) => (
            <Select {...fieldProps} name="factory_id" placeholder="Every factory" options={factories.map((f) => ({ value: f.id, label: f.name }))} />
          )}
        </FormField>
      </div>

      <div className="grid grid-cols-1 gap-3 sm:grid-cols-3">
        <FormField label="And nobody answers for (minutes)" required error={state?.errors?.delay_minutes?.[0]}>
          {(fieldProps) => <Input {...fieldProps} type="number" min="1" max="10080" name="delay_minutes" defaultValue={30} required />}
        </FormField>
        <FormField label="Escalation level" required error={state?.errors?.escalation_level?.[0]} helperText="1–5; two rules can't cover the same level for one event.">
          {(fieldProps) => <Input {...fieldProps} type="number" min="1" max="5" name="escalation_level" defaultValue={1} required />}
        </FormField>
        <FormField label="Tell" required error={state?.errors?.escalation_role_id?.[0]}>
          {(fieldProps) => (
            <Select {...fieldProps} name="escalation_role_id" options={roles.map((r) => ({ value: String(r.id), label: r.name }))} />
          )}
        </FormField>
      </div>

      <label className="flex items-center gap-2 text-sm text-foreground">
        <Checkbox name="stop_on_acknowledge" value="1" defaultChecked />
        Stop once somebody acknowledges it
      </label>

      {state?.status === "error" && !state.errors ? <p className="text-sm text-danger">{state.message}</p> : null}

      <div>
        <Button type="submit" loading={pending}>
          Add rule
        </Button>
      </div>
    </form>
  );
}

export { EscalationRuleForm, formatEventType };
