"use client";

import { startTransition, useActionState, useEffect, useState, useTransition } from "react";
import { useRouter } from "next/navigation";
import { Card, CardHeader, CardTitle, CardBody } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Modal } from "@/components/ui/modal";
import { FormField } from "@/components/ui/form-field";
import { Input } from "@/components/ui/input";
import { useToastManager } from "@/components/ui/toast";
import { useT } from "@/lib/i18n";

/** Status-driven, the same as `TransfersTable`'s own row actions — only one status is ever current, so which buttons render is a pure function of it. */
function TransferActions({ transfer, actions }) {
  const t = useT("asset");
  const tc = useT("common");
  const [pending, startPending] = useTransition();
  const [rejecting, setRejecting] = useState(false);
  const router = useRouter();
  const toastManager = useToastManager();

  function runApprove() {
    startPending(async () => {
      const result = await actions.approve();
      if (result?.status === "success") {
        toastManager.add({ title: t("transfer_approved_toast"), type: "success" });
        router.refresh();
      } else if (result?.status === "error") {
        toastManager.add({ title: result.message, type: "danger" });
      }
    });
  }

  function runReceive() {
    startPending(async () => {
      const result = await actions.receive();
      if (result?.status === "success") {
        toastManager.add({ title: t("transfer_received_toast"), type: "success" });
        router.refresh();
      } else if (result?.status === "error") {
        toastManager.add({ title: result.message, type: "danger" });
      }
    });
  }

  const canApprove = transfer.status === "REQUESTED";
  const canReceive = ["REQUESTED", "APPROVED", "IN_TRANSIT"].includes(transfer.status);
  const canReject = transfer.status === "REQUESTED";

  if (!canApprove && !canReceive && !canReject) {
    return null;
  }

  return (
    <Card>
      <CardHeader>
        <CardTitle>{tc("actions")}</CardTitle>
      </CardHeader>
      <CardBody className="flex flex-wrap gap-2">
        {canApprove ? (
          <Button loading={pending} onClick={runApprove}>
            {t("approve")}
          </Button>
        ) : null}
        {canReceive ? (
          <Button variant="outline" loading={pending} onClick={runReceive}>
            {t("receive")}
          </Button>
        ) : null}
        {canReject ? (
          <Button variant="danger" onClick={() => setRejecting(true)}>
            {t("reject")}
          </Button>
        ) : null}
      </CardBody>

      {rejecting ? <RejectModal action={actions.reject} onClose={() => setRejecting(false)} /> : null}
    </Card>
  );
}

function RejectModal({ action, onClose }) {
  const t = useT("asset");
  const tc = useT("common");
  const [state, dispatch, pending] = useActionState(action, null);
  const router = useRouter();
  const toastManager = useToastManager();

  useEffect(() => {
    if (state?.status === "success") {
      toastManager.add({ title: t("transfer_rejected_toast"), type: "success" });
      onClose();
      router.refresh();
    }
    // toastManager/router/onClose are deliberately excluded — see
    // transfers-table.jsx's own comment on why an unstable reference in
    // this array re-fires the effect every render once state first
    // becomes "success", stacking duplicate toasts.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [state]);

  function handleSubmit(event) {
    event.preventDefault();
    const formData = new FormData(event.currentTarget);
    startTransition(() => dispatch(formData));
  }

  return (
    <Modal open onOpenChange={onClose} title={t("reject_transfer_confirm_title")}>
      <form onSubmit={handleSubmit} className="flex flex-col gap-4">
        <FormField label={t("reason")} required error={state?.errors?.rejection_reason?.[0]}>
          {(fieldProps) => <Input {...fieldProps} name="rejection_reason" required maxLength={255} />}
        </FormField>
        {state?.status === "error" && !state.errors ? <p className="text-sm text-danger">{state.message}</p> : null}
        <div className="flex justify-end gap-2">
          <Button type="button" variant="outline" onClick={onClose}>
            {tc("cancel")}
          </Button>
          <Button type="submit" variant="danger" loading={pending}>
            {t("reject")}
          </Button>
        </div>
      </form>
    </Modal>
  );
}

export { TransferActions };
