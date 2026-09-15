"use client";

import { useActionState, useEffect, useState, useTransition } from "react";
import { useRouter } from "next/navigation";
import { Button } from "@/components/ui/button";
import { ConfirmDialog } from "@/components/ui/confirm-dialog";
import { Modal } from "@/components/ui/modal";
import { FormField } from "@/components/ui/form-field";
import { Select } from "@/components/ui/select";
import { Textarea } from "@/components/ui/textarea";
import { useToastManager } from "@/components/ui/toast";
import { HOLD_REASONS } from "@/lib/work-order-transitions";
import { formatStatus } from "@/components/ui/status-badge";

const HOLD_REASON_OPTIONS = HOLD_REASONS.map((code) => ({ value: code, label: formatStatus(code) }));

/**
 * Which actions a status offers — a courtesy matching `WorkOrder::
 * TRANSITIONS`, not the enforcement (the API re-checks it). `assign`/
 * `unassign` are handled separately (AssignTechnicianControl) since
 * they're not part of the named lifecycle the way these are.
 */
const STATUS_ACTIONS = {
  DRAFT: [
    { key: "submitForApproval", label: "Submit for approval" },
    { key: "cancel", label: "Cancel", destructive: true, needsReason: "reason" },
  ],
  PENDING_APPROVAL: [{ key: "cancel", label: "Cancel", destructive: true, needsReason: "reason" }],
  SCHEDULED: [{ key: "cancel", label: "Cancel", destructive: true, needsReason: "reason" }],
  ASSIGNED: [
    { key: "start", label: "Start" },
    { key: "cancel", label: "Cancel", destructive: true, needsReason: "reason" },
  ],
  IN_PROGRESS: [
    { key: "hold", label: "Put on hold", needsReason: "reason_code", reasonIsCode: true },
    { key: "complete", label: "Complete" },
    { key: "cancel", label: "Cancel", destructive: true, needsReason: "reason" },
  ],
  ON_HOLD: [
    { key: "resume", label: "Resume" },
    { key: "cancel", label: "Cancel", destructive: true, needsReason: "reason" },
  ],
  COMPLETED: [
    { key: "verify", label: "Verify" },
    { key: "close", label: "Close" },
  ],
  VERIFIED: [{ key: "close", label: "Close" }],
  CLOSED: [{ key: "reopen", label: "Reopen", needsReason: "reason" }],
  CANCELLED: [{ key: "reopen", label: "Reopen", needsReason: "reason" }],
};

function WorkOrderActions({ status, workOrderId, actions }) {
  const steps = STATUS_ACTIONS[status] ?? [];
  const [confirming, setConfirming] = useState(null);
  const [reasonStep, setReasonStep] = useState(null);
  const [pending, startTransition] = useTransition();
  const router = useRouter();
  const toastManager = useToastManager();

  function run(step) {
    startTransition(async () => {
      const result = await actions[step.key](workOrderId);
      if (result?.status === "success") {
        toastManager.add({ title: `${step.label} recorded`, type: "success" });
        setConfirming(null);
        router.refresh();
      } else if (result?.status === "error") {
        toastManager.add({ title: result.message, type: "danger" });
      }
    });
  }

  if (steps.length === 0) {
    return null;
  }

  return (
    <div className="flex items-center gap-2">
      {steps.map((step) => (
        <Button
          key={step.key}
          size="sm"
          variant={step.destructive ? "danger" : "outline"}
          onClick={() => (step.needsReason ? setReasonStep(step) : setConfirming(step))}
        >
          {step.label}
        </Button>
      ))}

      {confirming ? (
        <ConfirmDialog
          open={Boolean(confirming)}
          onOpenChange={() => setConfirming(null)}
          title={`${confirming.label}?`}
          confirmLabel={confirming.label}
          destructive={Boolean(confirming.destructive)}
          loading={pending}
          onConfirm={() => run(confirming)}
        />
      ) : null}

      {reasonStep ? (
        <ReasonModal
          step={reasonStep}
          onOpenChange={() => setReasonStep(null)}
          action={actions[reasonStep.key].bind(null, workOrderId)}
        />
      ) : null}
    </div>
  );
}

function ReasonModal({ step, onOpenChange, action }) {
  const [state, formAction] = useActionState(action, null);
  const router = useRouter();
  const toastManager = useToastManager();

  useEffect(() => {
    if (state?.status === "success") {
      toastManager.add({ title: `${step.label} recorded`, type: "success" });
      queueMicrotask(() => onOpenChange(false));
      router.refresh();
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [state]);

  return (
    <Modal open onOpenChange={onOpenChange} title={`${step.label}?`}>
      <form action={formAction} className="flex flex-col gap-4">
        <FormField label={step.reasonIsCode ? "Reason" : "Why"} required error={state?.errors?.[step.needsReason]?.[0]}>
          {(fieldProps) =>
            step.reasonIsCode ? (
              <Select {...fieldProps} name={step.needsReason} options={HOLD_REASON_OPTIONS} />
            ) : (
              <Textarea {...fieldProps} name={step.needsReason} rows={2} maxLength={2000} required />
            )
          }
        </FormField>

        {state?.status === "error" && !state.errors ? <p className="text-sm text-danger">{state.message}</p> : null}

        <div className="flex justify-end gap-2">
          <Button type="button" variant="outline" onClick={() => onOpenChange(false)}>
            Cancel
          </Button>
          <Button type="submit" variant={step.destructive ? "danger" : "primary"}>
            {step.label}
          </Button>
        </div>
      </form>
    </Modal>
  );
}

export { WorkOrderActions };
