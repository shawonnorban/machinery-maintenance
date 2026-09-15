"use client";

import { startTransition, useActionState, useEffect, useState, useTransition } from "react";
import { useRouter } from "next/navigation";
import Link from "next/link";
import { DataTable } from "@/components/ui/data-table";
import { StatusBadge } from "@/components/ui/status-badge";
import { Modal } from "@/components/ui/modal";
import { FormField } from "@/components/ui/form-field";
import { Input } from "@/components/ui/input";
import { Button } from "@/components/ui/button";
import { FormattedDateTime } from "@/components/ui/formatted-date-time";
import { useToastManager } from "@/components/ui/toast";

/** Mirrors `AssetTransferController::index` — the pending queue: what is waiting on somebody, company-wide. */
function TransfersTable({ transfers, meta, actions }) {
  const router = useRouter();
  const [pending, startPending] = useTransition();
  const [rejecting, setRejecting] = useState(null);
  const toastManager = useToastManager();

  function runApprove(transfer) {
    startPending(async () => {
      const result = await actions.approveTransfer(transfer.id);
      if (result?.status === "success") {
        toastManager.add({ title: "Transfer approved", type: "success" });
        router.refresh();
      } else if (result?.status === "error") {
        toastManager.add({ title: result.message, type: "danger" });
      }
    });
  }

  function runReceive(transfer) {
    startPending(async () => {
      const result = await actions.receiveTransfer(transfer.id);
      if (result?.status === "success") {
        toastManager.add({ title: "Transfer received", type: "success" });
        router.refresh();
      } else if (result?.status === "error") {
        toastManager.add({ title: result.message, type: "danger" });
      }
    });
  }

  return (
    <div className="flex flex-col gap-4">
      <DataTable
        columns={[
          {
            key: "transfer_number",
            header: "Transfer",
            render: (t) => (
              <div>
                <Link href={`/assets/transfers/${t.id}`} className="font-medium text-brand hover:underline">
                  {t.transfer_number}
                </Link>
                <div className="text-xs text-foreground-muted">
                  <Link href={`/assets/${t.asset?.id}`} className="text-brand hover:underline">
                    {t.asset?.asset_code}
                  </Link>
                  {" — "}
                  {t.asset?.name}
                </div>
              </div>
            ),
          },
          {
            key: "route",
            header: "Route",
            render: (t) => (
              <span>
                {t.from_factory?.name ?? "—"} → {t.to_factory?.name ?? "—"}
                <div className="text-xs text-foreground-muted">{t.to_location?.name}</div>
              </span>
            ),
          },
          { key: "reason", header: "Reason", render: (t) => t.reason ?? "—" },
          { key: "status", header: "Status", render: (t) => <StatusBadge status={t.status} /> },
          {
            key: "requested_at",
            header: "Requested",
            render: (t) => <FormattedDateTime value={t.requested_at} mode="date" />,
          },
        ]}
        rows={transfers}
        rowKey={(t) => t.id}
        emptyTitle="Nothing pending."
        emptyDescription="No transfer is currently waiting on approval or receipt."
        rowActions={(t) =>
          [
            { label: "View", onSelect: () => router.push(`/assets/transfers/${t.id}`) },
            t.status === "REQUESTED" ? { label: "Approve", onSelect: () => runApprove(t) } : null,
            ["REQUESTED", "APPROVED", "IN_TRANSIT"].includes(t.status) ? { label: "Receive", onSelect: () => runReceive(t) } : null,
            t.status === "REQUESTED" ? { label: "Reject", destructive: true, onSelect: () => setRejecting(t) } : null,
          ].filter(Boolean)
        }
        pagination={{ page: meta.current_page, perPage: meta.per_page, total: meta.total }}
        onPageChange={(nextPage) => router.push(`/assets/transfers?page=${nextPage}`)}
      />

      {rejecting ? (
        <RejectModal transfer={rejecting} action={actions.rejectTransfer} onClose={() => setRejecting(null)} />
      ) : null}
    </div>
  );
}

function RejectModal({ transfer, action, onClose }) {
  const [state, dispatch, pending] = useActionState(action.bind(null, transfer.id), null);
  const router = useRouter();
  const toastManager = useToastManager();

  function handleSubmit(event) {
    event.preventDefault();
    const formData = new FormData(event.currentTarget);
    startTransition(() => dispatch(formData));
  }

  useEffect(() => {
    if (state?.status === "success") {
      toastManager.add({ title: "Transfer rejected", type: "success" });
      onClose();
      router.refresh();
    }
    // toastManager/router/onClose are deliberately excluded — see
    // breakdown-actions.jsx's own comment on why an unstable reference in
    // this array re-fires the effect every render once state first
    // becomes "success", stacking duplicate toasts.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [state]);

  return (
    <Modal open onOpenChange={onClose} title={`Reject ${transfer.transfer_number}?`}>
      <form onSubmit={handleSubmit} className="flex flex-col gap-4">
        <FormField label="Reason" required error={state?.errors?.rejection_reason?.[0]}>
          {(fieldProps) => <Input {...fieldProps} name="rejection_reason" required maxLength={255} />}
        </FormField>
        {state?.status === "error" && !state.errors ? <p className="text-sm text-danger">{state.message}</p> : null}
        <div className="flex justify-end gap-2">
          <Button type="button" variant="outline" onClick={onClose}>
            Cancel
          </Button>
          <Button type="submit" variant="danger" loading={pending}>
            Reject
          </Button>
        </div>
      </form>
    </Modal>
  );
}

export { TransfersTable };
