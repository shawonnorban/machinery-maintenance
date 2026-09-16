"use client";

import { useActionState, useEffect, useState, useTransition } from "react";
import { useRouter } from "next/navigation";
import { MoreVertical } from "lucide-react";
import { Dropdown } from "@/components/ui/dropdown";
import { ConfirmDialog } from "@/components/ui/confirm-dialog";
import { Modal } from "@/components/ui/modal";
import { FormField } from "@/components/ui/form-field";
import { Textarea } from "@/components/ui/textarea";
import { Button } from "@/components/ui/button";
import { useToastManager } from "@/components/ui/toast";

/**
 * Which action(s) a transfer accepts depends on its own status, mirroring
 * `TransferAsset::approve/reject/receive`'s own `assertStatus()` calls
 * exactly — offering a button the API would refuse is confusing, not
 * dangerous (the API still enforces it), but there's no reason to show it.
 */
const APPROVABLE = ["REQUESTED"];
const REJECTABLE = ["REQUESTED", "APPROVED"];
const RECEIVABLE = ["REQUESTED", "APPROVED", "IN_TRANSIT"];

function TransferActionsMenu({ transfer, actions, onMutated }) {
  const [confirmApprove, setConfirmApprove] = useState(false);
  const [confirmReceive, setConfirmReceive] = useState(false);
  const [rejectOpen, setRejectOpen] = useState(false);
  const [pending, startTransition] = useTransition();
  const router = useRouter();
  const toastManager = useToastManager();

  const items = [];
  if (APPROVABLE.includes(transfer.status)) {
    items.push({ label: "Approve", onSelect: () => setConfirmApprove(true) });
  }
  if (RECEIVABLE.includes(transfer.status)) {
    items.push({ label: "Receive", onSelect: () => setConfirmReceive(true) });
  }
  if (REJECTABLE.includes(transfer.status)) {
    items.push({ label: "Reject", destructive: true, onSelect: () => setRejectOpen(true) });
  }

  if (items.length === 0) {
    return null;
  }

  function run(action, successMessage) {
    startTransition(async () => {
      const result = await action(transfer.id);
      if (result?.status === "success") {
        toastManager.add({ title: successMessage, type: "success" });
        router.refresh();
        onMutated?.();
      } else if (result?.status === "error") {
        toastManager.add({ title: result.message, type: "danger" });
      }
    });
  }

  return (
    <>
      <Dropdown
        trigger={
          <span className="inline-flex size-8 items-center justify-center rounded-sm text-foreground-muted hover:bg-surface-muted hover:text-foreground">
            <MoreVertical className="size-4" />
          </span>
        }
        items={items}
      />

      <ConfirmDialog
        open={confirmApprove}
        onOpenChange={setConfirmApprove}
        title="Approve this transfer?"
        description="The destination factory will still need to receive it before the machine actually moves."
        confirmLabel="Approve"
        destructive={false}
        loading={pending}
        onConfirm={() => run(actions.approve, "Transfer approved")}
      />

      <ConfirmDialog
        open={confirmReceive}
        onOpenChange={setConfirmReceive}
        title="Receive this transfer?"
        description="This is the point the machine actually moves — its factory and location update immediately."
        confirmLabel="Receive"
        destructive={false}
        loading={pending}
        onConfirm={() => run(actions.receive, "Transfer received")}
      />

      <RejectModal
        open={rejectOpen}
        onOpenChange={setRejectOpen}
        action={actions.reject.bind(null, transfer.id)}
        onMutated={onMutated}
      />
    </>
  );
}

function RejectModal({ open, onOpenChange, action, onMutated }) {
  const [state, formAction] = useActionState(action, null);
  const router = useRouter();
  const toastManager = useToastManager();

  useEffect(() => {
    if (state?.status === "success") {
      toastManager.add({ title: "Transfer rejected", type: "success" });
      queueMicrotask(() => onOpenChange(false));
      router.refresh();
      onMutated?.();
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [state]);

  return (
    <Modal open={open} onOpenChange={onOpenChange} title="Reject this transfer?">
      <form action={formAction} className="flex flex-col gap-4">
        <FormField label="Reason" required error={state?.errors?.rejection_reason?.[0]}>
          {(fieldProps) => <Textarea {...fieldProps} name="rejection_reason" rows={2} maxLength={255} required />}
        </FormField>

        {state?.status === "error" && !state.errors ? <p className="text-sm text-danger">{state.message}</p> : null}

        <div className="flex justify-end gap-2">
          <Button type="button" variant="outline" onClick={() => onOpenChange(false)}>
            Cancel
          </Button>
          <Button type="submit" variant="danger">
            Reject
          </Button>
        </div>
      </form>
    </Modal>
  );
}

export { TransferActionsMenu };
