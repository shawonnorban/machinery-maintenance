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
import { useT } from "@/lib/i18n";

/** Mirrors `AssetTransferController::index` — the pending queue: what is waiting on somebody, company-wide. */
function TransfersTable({ transfers, meta, actions }) {
  const t = useT("asset");
  const tc = useT("common");
  const router = useRouter();
  const [pending, startPending] = useTransition();
  const [rejecting, setRejecting] = useState(null);
  const toastManager = useToastManager();

  function runApprove(transfer) {
    startPending(async () => {
      const result = await actions.approveTransfer(transfer.id);
      if (result?.status === "success") {
        toastManager.add({ title: t("transfer_approved_toast"), type: "success" });
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
        toastManager.add({ title: t("transfer_received_toast"), type: "success" });
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
            header: t("transfer"),
            render: (row) => (
              <div>
                <Link href={`/assets/transfers/${row.id}`} className="font-medium text-brand hover:underline">
                  {row.transfer_number}
                </Link>
                <div className="text-xs text-foreground-muted">
                  <Link href={`/assets/${row.asset?.id}`} className="text-brand hover:underline">
                    {row.asset?.asset_code}
                  </Link>
                  {" — "}
                  {row.asset?.name}
                </div>
              </div>
            ),
          },
          {
            key: "route",
            header: t("route"),
            render: (row) => (
              <span>
                {row.from_factory?.name ?? "—"} → {row.to_factory?.name ?? "—"}
                <div className="text-xs text-foreground-muted">{row.to_location?.name}</div>
              </span>
            ),
          },
          { key: "reason", header: t("reason"), render: (row) => row.reason ?? "—" },
          {
            key: "status",
            header: t("status"),
            render: (row) => <StatusBadge status={row.status} label={t(`transfer_status_${row.status?.toLowerCase()}`)} />,
          },
          {
            key: "requested_at",
            header: t("requested"),
            render: (row) => <FormattedDateTime value={row.requested_at} mode="date" />,
          },
        ]}
        rows={transfers}
        rowKey={(row) => row.id}
        emptyTitle={t("no_pending_transfers")}
        emptyDescription={t("no_pending_transfers_hint")}
        rowActions={(row) =>
          [
            { label: tc("view"), onSelect: () => router.push(`/assets/transfers/${row.id}`) },
            row.status === "REQUESTED" ? { label: t("approve"), onSelect: () => runApprove(row) } : null,
            ["REQUESTED", "APPROVED", "IN_TRANSIT"].includes(row.status) ? { label: t("receive"), onSelect: () => runReceive(row) } : null,
            row.status === "REQUESTED" ? { label: t("reject"), destructive: true, onSelect: () => setRejecting(row) } : null,
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
  const t = useT("asset");
  const tc = useT("common");
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
      toastManager.add({ title: t("transfer_rejected_toast"), type: "success" });
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
    <Modal open onOpenChange={onClose} title={t("reject_transfer_named_title", { number: transfer.transfer_number })}>
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

export { TransfersTable };
