"use client";

import { useActionState, useEffect } from "react";
import { useFormStatus } from "react-dom";
import { FormField } from "@/components/ui/form-field";
import { Textarea } from "@/components/ui/textarea";
import { Button } from "@/components/ui/button";
import { useToastManager } from "@/components/ui/toast";

function ApproveButton() {
  const { pending } = useFormStatus();
  return (
    <Button type="submit" variant="success" loading={pending}>
      Approve
    </Button>
  );
}

function RejectButton() {
  const { pending } = useFormStatus();
  return (
    <Button type="submit" variant="danger" loading={pending}>
      Reject
    </Button>
  );
}

/**
 * Mirrors `approvals.show.blade.php`'s two decision forms exactly: approve
 * takes an optional comment, reject requires one — a refusal with no reason
 * gives the requester nothing to act on.
 *
 * @param {{ step: number, totalSteps: number, approveAction: Function, rejectAction: Function }} props
 *   Both actions are pre-bound to this approval's id by the page.
 */
function DecisionForms({ step, totalSteps, approveAction, rejectAction }) {
  const [approveState, approveFormAction] = useActionState(approveAction, null);
  const [rejectState, rejectFormAction] = useActionState(rejectAction, null);
  const toastManager = useToastManager();

  useEffect(() => {
    if (approveState?.status === "success") {
      toastManager.add({ title: "Approved", type: "success" });
    }
    // toastManager is not a stable reference across renders — including it
    // re-fires this effect every render once state first becomes
    // "success", stacking duplicate toasts.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [approveState]);

  useEffect(() => {
    if (rejectState?.status === "success") {
      toastManager.add({ title: "Rejected", type: "success" });
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [rejectState]);

  return (
    <div className="flex flex-col gap-4">
      <p className="text-sm font-semibold text-foreground">
        Step {step} of {totalSteps}
      </p>

      <form action={approveFormAction} className="flex flex-col gap-2">
        <FormField label="Comment" error={approveState?.errors?.comment?.[0]}>
          {(fieldProps) => <Textarea {...fieldProps} name="comment" rows={2} maxLength={2000} />}
        </FormField>
        {approveState?.status === "error" && !approveState.errors ? (
          <p className="text-sm text-danger">{approveState.message}</p>
        ) : null}
        <div>
          <ApproveButton />
        </div>
      </form>

      <form action={rejectFormAction} className="flex flex-col gap-2 border-t border-border pt-4">
        <FormField label="Comment" required error={rejectState?.errors?.comment?.[0]} helperText="A refusal with no reason gives the requester nothing to act on.">
          {(fieldProps) => <Textarea {...fieldProps} name="comment" rows={2} maxLength={2000} required />}
        </FormField>
        {rejectState?.status === "error" && !rejectState.errors ? (
          <p className="text-sm text-danger">{rejectState.message}</p>
        ) : null}
        <div>
          <RejectButton />
        </div>
      </form>
    </div>
  );
}

export { DecisionForms };
