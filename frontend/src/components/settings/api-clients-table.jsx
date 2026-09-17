"use client";

import { useState, useTransition } from "react";
import { useRouter } from "next/navigation";
import Link from "next/link";
import { DataTable } from "@/components/ui/data-table";
import { StatusBadge } from "@/components/ui/status-badge";
import { Badge } from "@/components/ui/badge";
import { ConfirmDialog } from "@/components/ui/confirm-dialog";
import { Modal } from "@/components/ui/modal";
import { FormattedDateTime } from "@/components/ui/formatted-date-time";
import { useToastManager } from "@/components/ui/toast";
import { useT } from "@/lib/i18n";

/** Mirrors `ApiClientController::index` — the client id, the scopes, and when it was last used, which is what somebody deciding whether to revoke it actually needs. */
function ApiClientsTable({ clients, actions }) {
  const t = useT("api");
  const router = useRouter();
  const [pending, startTransition] = useTransition();
  const [revoking, setRevoking] = useState(null);
  const [newSecret, setNewSecret] = useState(null);
  const toastManager = useToastManager();

  function runRotate(client) {
    startTransition(async () => {
      const result = await actions.rotateSecret(client.id);
      if (result?.status === "success") setNewSecret(result.secret);
      else if (result?.status === "error") toastManager.add({ title: result.message, type: "danger" });
    });
  }

  function runRevoke() {
    startTransition(async () => {
      const result = await actions.revokeClient(revoking.id);
      if (result?.status === "success") {
        toastManager.add({ title: t("client_revoked", { name: revoking.name }), type: "success" });
        setRevoking(null);
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
            key: "name",
            header: t("client"),
            render: (c) => (
              <div>
                <span className="font-medium text-foreground">{c.name}</span>
                <div className="text-xs text-foreground-muted">{t("created_by", { name: c.creator?.name ?? "—" })}</div>
              </div>
            ),
          },
          { key: "client_id", header: t("client_id_header"), render: (c) => <code className="text-xs">{c.client_id}</code> },
          {
            key: "scopes",
            header: t("scopes"),
            render: (c) => (
              <div className="flex max-w-xs flex-wrap gap-1">
                {c.scopes.map((scope) => (
                  <Badge key={scope} variant="neutral">
                    {scope}
                  </Badge>
                ))}
              </div>
            ),
          },
          {
            key: "last_used_at",
            header: t("last_used"),
            render: (c) => <FormattedDateTime value={c.last_used_at} mode="datetime" fallback={t("never_used")} />,
          },
          {
            key: "status",
            header: t("status"),
            render: (c) => (
              <div>
                <StatusBadge status={c.is_usable ? "ACTIVE" : "INACTIVE"} label={c.is_usable ? t("active") : t("inactive")} />
                {c.active_tokens > 0 ? (
                  <div className="text-xs text-foreground-muted">{t("live_tokens_count", { count: c.active_tokens })}</div>
                ) : null}
              </div>
            ),
          },
        ]}
        rows={clients}
        rowKey={(c) => c.id}
        emptyTitle={t("no_clients")}
        emptyDescription={t("no_clients_hint")}
        rowActions={(c) =>
          c.is_usable
            ? [
                { label: t("edit_scopes"), onSelect: () => router.push(`/settings/api-clients/${c.id}/edit`) },
                { label: t("rotate_secret"), onSelect: () => runRotate(c) },
                { label: t("revoke"), destructive: true, onSelect: () => setRevoking(c) },
              ]
            : []
        }
      />

      <ConfirmDialog
        open={Boolean(revoking)}
        onOpenChange={() => setRevoking(null)}
        title={t("revoke_client_title", { name: revoking?.name })}
        description={t("revoke_client_description")}
        confirmLabel={t("revoke")}
        loading={pending}
        onConfirm={runRevoke}
      />

      <Modal open={Boolean(newSecret)} onOpenChange={() => setNewSecret(null)} title={t("new_secret")}>
        <p className="mb-3 text-sm text-foreground-muted">{t("secret_shown_once")}</p>
        <div className="rounded-sm border border-border bg-surface-muted px-4 py-3 font-mono text-sm break-all">{newSecret}</div>
      </Modal>
    </div>
  );
}

export { ApiClientsTable };
