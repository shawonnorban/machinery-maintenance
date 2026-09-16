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
import { saveDraft, flush } from "@/lib/offline/queue";

const HOLD_REASON_OPTIONS = HOLD_REASONS.map((code) => ({ value: code, label: formatStatus(code) }));

/**
 * The URL segment each transition posts to (`WorkOrderApiController`'s own
 * route names) — needed only for the offline fallback below, which talks to
 * `/api/offline-relay` directly rather than through the Server Action, so it
 * has to name the endpoint itself.
 */
const ENDPOINT_SEGMENT = {
  submitForApproval: "submit-for-approval",
  start: "start",
  resume: "resume",
  complete: "complete",
  verify: "verify",
  close: "close",
  hold: "hold",
  cancel: "cancel",
  reopen: "reopen",
};

/**
 * Shared by both the no-reason transitions (`run()` below) and the
 * reason-requiring ones (`ReasonModal`): saves the transition as an offline
 * draft and starts sending it in the background, the same mechanism
 * `ReportBreakdownForm` uses. Only reached when the Server Action's own
 * request never made it to the app at all (see the call sites' comments).
 */
async function queueOfflineTransition({ workOrderId, step, payload, toastManager }) {
  await saveDraft({
    endpoint: `/work-orders/${workOrderId}/${ENDPOINT_SEGMENT[step.key]}`,
    payload,
    label: `${step.label} — work order ${workOrderId}`,
  });
  flush();
  toastManager.add({
    title: `${step.label} saved on this device`,
    description: "Sending now — check the sync icon if you're offline.",
    type: "success",
  });
}

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
      try {
        const result = await actions[step.key](workOrderId);
        if (result?.status === "success") {
          toastManager.add({ title: `${step.label} recorded`, type: "success" });
          router.refresh();
        } else if (result?.status === "error") {
          toastManager.add({ title: result.message, type: "danger" });
        }
      } catch {
        // The Server Action's own request never reached the app at all —
        // the one failure mode that means "no signal right now," as
        // opposed to a validation/permission error the action already
        // caught and returned as `result.status === "error"` above.
        await queueOfflineTransition({ workOrderId, step, payload: {}, toastManager });
      }

      setConfirming(null);
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
          workOrderId={workOrderId}
          onOpenChange={() => setReasonStep(null)}
          action={actions[reasonStep.key].bind(null, workOrderId)}
        />
      ) : null}
    </div>
  );
}

function ReasonModal({ step, workOrderId, onOpenChange, action }) {
  const router = useRouter();
  const toastManager = useToastManager();

  // Wrapped so `useActionState`'s action always settles into a state object
  // rather than rejecting — a transport-level failure (no signal to the app
  // at all) is turned into an offline draft here instead of surfacing as an
  // uncaught error from within the transition.
  async function wrappedAction(previousState, formData) {
    try {
      return await action(previousState, formData);
    } catch {
      await queueOfflineTransition({
        workOrderId,
        step,
        payload: Object.fromEntries(formData.entries()),
        toastManager,
      });

      return { status: "queued" };
    }
  }

  const [state, formAction] = useActionState(wrappedAction, null);

  useEffect(() => {
    if (state?.status === "success") {
      toastManager.add({ title: `${step.label} recorded`, type: "success" });
      queueMicrotask(() => onOpenChange(false));
      router.refresh();
    } else if (state?.status === "queued") {
      // queueOfflineTransition already toasted; this only needs to close
      // the dialog, not refresh — nothing on the server has changed yet.
      queueMicrotask(() => onOpenChange(false));
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
