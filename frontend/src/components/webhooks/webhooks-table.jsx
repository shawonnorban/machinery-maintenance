"use client";

import { useState, useTransition } from "react";
import { useRouter } from "next/navigation";
import { DataTable } from "@/components/ui/data-table";
import { StatusBadge } from "@/components/ui/status-badge";
import { ConfirmDialog } from "@/components/ui/confirm-dialog";
import { FormattedDateTime } from "@/components/ui/formatted-date-time";
import { useToastManager } from "@/components/ui/toast";
import { useT } from "@/lib/i18n";

/** Mirrors `WebhookController::index` — outgoing integrations and how many events each is subscribed to. */
function WebhooksTable({ endpoints, actions }) {
  const t = useT("webhook");
  const tc = useT("common");
  const router = useRouter();
  const [pending, startTransition] = useTransition();
  const [deleting, setDeleting] = useState(null);
  const toastManager = useToastManager();

  function runToggle(endpoint) {
    startTransition(async () => {
      const result = endpoint.status === "ACTIVE" ? await actions.pauseEndpoint(endpoint.id) : await actions.enableEndpoint(endpoint.id);
      if (result?.status === "success") router.refresh();
      else if (result?.status === "error") toastManager.add({ title: result.message, type: "danger" });
    });
  }

  function runDelete() {
    startTransition(async () => {
      const result = await actions.deleteEndpoint(deleting.id);
      if (result?.status === "success") {
        toastManager.add({ title: t("endpoint_paused_toast"), type: "success" });
        setDeleting(null);
        router.refresh();
      } else if (result?.status === "error") {
        toastManager.add({ title: result.message, type: "danger" });
        setDeleting(null);
      }
    });
  }

  return (
    <div className="flex flex-col gap-4">
      <DataTable
        columns={[
          {
            key: "url",
            header: t("endpoint"),
            render: (e) => (
              <div>
                <button type="button" onClick={() => router.push(`/settings/webhooks/${e.id}`)} className="font-medium text-brand hover:underline">
                  {e.url}
                </button>
                <div className="text-xs text-foreground-muted">{e.description ?? "—"}</div>
              </div>
            ),
          },
          { key: "subscriptions_count", header: t("events"), align: "right", render: (e) => e.subscriptions_count ?? 0 },
          { key: "consecutive_failure_count", header: t("failures"), align: "right", render: (e) => e.consecutive_failure_count },
          { key: "created_at", header: t("created_at_header"), render: (e) => <FormattedDateTime value={e.created_at} mode="date" /> },
          { key: "status", header: t("status"), render: (e) => <StatusBadge status={e.status} label={t(`statuses.${e.status}`)} /> },
        ]}
        rows={endpoints}
        rowKey={(e) => e.id}
        emptyTitle={t("no_endpoints_found")}
        rowActions={(e) => [
          { label: tc("view"), onSelect: () => router.push(`/settings/webhooks/${e.id}`) },
          { label: tc("edit"), onSelect: () => router.push(`/settings/webhooks/${e.id}/edit`) },
          { label: e.status === "ACTIVE" ? t("pause") : t("enable"), onSelect: () => runToggle(e) },
          { label: tc("delete"), destructive: true, onSelect: () => setDeleting(e) },
        ]}
      />

      <ConfirmDialog
        open={Boolean(deleting)}
        onOpenChange={() => setDeleting(null)}
        title={deleting ? t("delete_endpoint_title", { url: deleting.url }) : ""}
        description={t("delete_endpoint_description")}
        confirmLabel={tc("delete")}
        loading={pending}
        onConfirm={runDelete}
      />
    </div>
  );
}

export { WebhooksTable };
