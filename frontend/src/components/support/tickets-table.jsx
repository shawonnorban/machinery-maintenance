"use client";

import { useState, useTransition } from "react";
import { useRouter } from "next/navigation";
import Link from "next/link";
import { AlertTriangle } from "lucide-react";
import { DataTable } from "@/components/ui/data-table";
import { StatusBadge } from "@/components/ui/status-badge";
import { Badge } from "@/components/ui/badge";
import { ConfirmDialog } from "@/components/ui/confirm-dialog";
import { useToastManager } from "@/components/ui/toast";
import { FormattedDateTime } from "@/components/ui/formatted-date-time";
import { useT } from "@/lib/i18n";

const PRIORITY_TONE = { URGENT: "danger", HIGH: "warning", MEDIUM: "info", LOW: "neutral" };

/**
 * `DataTable`'s column `render` functions are plain closures, which can't
 * cross the Server→Client boundary as props (see `AssetDetailTabs`'s own
 * note on the same constraint) — this stays its own small Client Component
 * so the list page itself can stay a plain server-rendered fetch.
 *
 * Edit/Delete only ever appear for a ticket the API's own `can_edit` flag
 * allows (opener, still OPEN, nobody has replied yet) — the button is
 * withheld rather than shown and left to fail, since `deleteTicket` would
 * refuse it anyway.
 */
function TicketsTable({ tickets, deleteTicket }) {
  const t = useT("support");
  const tc = useT("common");
  const router = useRouter();
  const toastManager = useToastManager();
  const [deleting, setDeleting] = useState(null);
  const [pending, startTransition] = useTransition();

  function runDelete() {
    startTransition(async () => {
      const result = await deleteTicket(deleting.id);
      if (result?.status === "success") {
        toastManager.add({ title: t("ticket_deleted_toast"), type: "success" });
        setDeleting(null);
        router.refresh();
      } else if (result?.status === "error") {
        toastManager.add({ title: result.message, type: "danger" });
      }
    });
  }

  return (
    <>
      <DataTable
        columns={[
          {
            key: "subject",
            header: t("subject"),
            render: (ticket) => (
              <Link href={`/support/tickets/${ticket.id}`} className="font-medium text-brand hover:underline">
                {ticket.subject}
              </Link>
            ),
          },
          {
            key: "priority",
            header: t("priority"),
            render: (ticket) => (
              <span className="flex items-center gap-1.5">
                <Badge variant={PRIORITY_TONE[ticket.priority] ?? "neutral"}>{t(`priority_${ticket.priority?.toLowerCase()}`)}</Badge>
                {ticket.sla?.breached ? <AlertTriangle className="size-4 text-danger" aria-label={t("sla_breached")} /> : null}
              </span>
            ),
          },
          { key: "opened_by", header: t("opened_by"), render: (ticket) => ticket.opener?.name ?? "—" },
          {
            key: "last_activity",
            header: t("last_activity"),
            render: (ticket) => <FormattedDateTime value={ticket.last_message_at} mode="datetime" fallback="—" />,
          },
          {
            key: "status",
            header: t("status"),
            render: (ticket) => <StatusBadge status={ticket.status} label={t(`status_${ticket.status?.toLowerCase()}`)} />,
          },
        ]}
        rows={tickets}
        rowKey={(ticket) => ticket.id}
        emptyTitle={t("no_tickets")}
        emptyDescription={t("no_tickets_hint")}
        rowActions={(ticket) => [
          { label: tc("view"), onSelect: () => router.push(`/support/tickets/${ticket.id}`) },
          ...(ticket.can_edit
            ? [
                { label: tc("edit"), onSelect: () => router.push(`/support/tickets/${ticket.id}/edit`) },
                { label: tc("delete"), destructive: true, onSelect: () => setDeleting(ticket) },
              ]
            : []),
        ]}
      />

      <ConfirmDialog
        open={Boolean(deleting)}
        onOpenChange={() => setDeleting(null)}
        title={t("delete_ticket_title")}
        description={t("delete_ticket_description")}
        confirmLabel={tc("delete")}
        loading={pending}
        onConfirm={runDelete}
      />
    </>
  );
}

export { TicketsTable };
