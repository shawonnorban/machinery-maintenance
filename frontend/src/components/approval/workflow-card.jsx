"use client";

import { useActionState, useEffect, useState, useTransition } from "react";
import { Card, CardHeader, CardTitle, CardBody } from "@/components/ui/card";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { FormField } from "@/components/ui/form-field";
import { Input } from "@/components/ui/input";
import { Select } from "@/components/ui/select";
import { ConfirmDialog } from "@/components/ui/confirm-dialog";
import { useToastManager } from "@/components/ui/toast";
import { formatEntityType } from "./new-workflow-form";

/** Mirrors `workflows/index.blade.php`'s per-chain card — steps, in the order signatures are collected, then the form that appends the next one. */
function WorkflowCard({ workflow, roles, toggleAction, addRuleAction, removeRuleAction }) {
  const [, startTransition] = useTransition();
  const [togglePending, setTogglePending] = useState(false);
  const toastManager = useToastManager();

  function handleToggle() {
    setTogglePending(true);
    startTransition(async () => {
      const result = await toggleAction();
      setTogglePending(false);
      if (result?.status === "success") {
        toastManager.add({ title: workflow.active ? "Chain paused" : "Chain resumed", type: "success" });
      }
    });
  }

  return (
    <Card>
      <CardHeader className="flex items-center justify-between">
        <div className="flex items-center gap-2">
          <CardTitle>{workflow.name}</CardTitle>
          <Badge variant="neutral">{formatEntityType(workflow.entity_type)}</Badge>
          {!workflow.active ? <Badge variant="warning">Inactive</Badge> : null}
        </div>
        <div className="flex items-center gap-3">
          <span className="text-xs text-foreground-muted">
            Used {workflow.request_count} time{workflow.request_count === 1 ? "" : "s"}
          </span>
          <Button size="sm" variant="outline" loading={togglePending} onClick={handleToggle}>
            {workflow.active ? "Pause" : "Resume"}
          </Button>
        </div>
      </CardHeader>
      <CardBody className="flex flex-col gap-4">
        {workflow.rules.length === 0 ? (
          <p className="text-sm text-foreground-muted">No steps yet — nothing will be routed for approval until one is added.</p>
        ) : (
          <div className="flex flex-col gap-2">
            {workflow.rules.map((rule) => (
              <RuleRow key={rule.id} rule={rule} removeAction={removeRuleAction.bind(null, rule.id)} />
            ))}
          </div>
        )}

        <AddRuleForm roles={roles} action={addRuleAction} />
      </CardBody>
    </Card>
  );
}

function RuleRow({ rule, removeAction }) {
  const [open, setOpen] = useState(false);
  const [pending, startTransition] = useTransition();
  const toastManager = useToastManager();

  function confirm() {
    startTransition(async () => {
      const result = await removeAction();
      setOpen(false);
      if (result?.status === "success") {
        toastManager.add({ title: "Step removed", type: "success" });
      }
    });
  }

  return (
    <div className="flex items-center justify-between gap-3 border-b border-border py-2 last:border-b-0">
      <div className="text-sm">
        <span className="mr-2 text-xs font-medium text-foreground-muted">Step {rule.sequence}</span>
        <span className="font-medium text-foreground">{rule.name}</span>
        <div className="text-xs text-foreground-muted">
          {conditionsLabel(rule.conditions)} — signed by {rule.role?.description ?? "—"}
        </div>
      </div>
      <Button size="sm" variant="ghost" onClick={() => setOpen(true)}>
        Remove
      </Button>
      <ConfirmDialog
        open={open}
        onOpenChange={setOpen}
        title={`Remove step "${rule.name}"?`}
        description="The chain resequences — a change removed cleanly, not a gap."
        confirmLabel="Remove"
        loading={pending}
        onConfirm={confirm}
      />
    </div>
  );
}

function conditionsLabel(conditions) {
  const parts = [];
  if (conditions.min_cost) parts.push(`from ${conditions.min_cost}`);
  if (conditions.max_cost) parts.push(`up to ${conditions.max_cost}`);
  if (conditions.criticality) parts.push(`criticality: ${conditions.criticality.join(", ")}`);
  if (conditions.priority) parts.push(`priority: ${conditions.priority.join(", ")}`);
  if (conditions.factory_id) parts.push("one factory");
  return parts.length ? parts.join(", ") : "always";
}

function AddRuleForm({ roles, action }) {
  const [state, dispatch, pending] = useActionState(action, null);
  const toastManager = useToastManager();

  useEffect(() => {
    if (state?.status === "success") {
      toastManager.add({ title: "Step added", type: "success" });
    }
    // toastManager is not a stable reference across renders — see
    // NewWorkflowForm's own comment.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [state]);

  return (
    <form action={dispatch} className="flex flex-wrap items-end gap-2 rounded-sm border border-border p-3">
      <div className="w-48">
        <FormField label="Step name" required error={state?.errors?.name?.[0]}>
          {(fieldProps) => <Input {...fieldProps} name="name" required />}
        </FormField>
      </div>
      <div className="w-32">
        <FormField label="From">
          {(fieldProps) => <Input {...fieldProps} type="number" step="0.01" min="0" name="min_cost" />}
        </FormField>
      </div>
      <div className="w-32">
        <FormField label="Up to">
          {(fieldProps) => <Input {...fieldProps} type="number" step="0.01" min="0" name="max_cost" />}
        </FormField>
      </div>
      <div className="w-48">
        <FormField label="Signed by" required error={state?.errors?.role_id?.[0]}>
          {(fieldProps) => (
            <Select {...fieldProps} name="role_id" options={roles.map((r) => ({ value: String(r.id), label: r.name }))} />
          )}
        </FormField>
      </div>
      <Button type="submit" size="sm" variant="outline" loading={pending}>
        Add step
      </Button>
      {state?.status === "error" && !state.errors ? <p className="w-full text-sm text-danger">{state.message}</p> : null}
    </form>
  );
}

export { WorkflowCard };
