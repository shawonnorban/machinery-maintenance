import Link from "next/link";
import { Plus } from "lucide-react";
import { apiFetch } from "@/lib/api-server";
import { PageHeader } from "@/components/layout/page-header";
import { buttonVariants } from "@/components/ui/button";
import { cn } from "@/lib/utils";
import { StatusBadge } from "@/components/ui/status-badge";
import { FormattedDateTime } from "@/components/ui/formatted-date-time";
import { EmptyState } from "@/components/ui/empty-state";

/** Mirrors `SupportTicketController::index` — no permission gate beyond being signed in to the company. */
export default async function SupportTicketsPage({ searchParams }) {
  const params = await searchParams;
  const page = Number(params.page ?? 1);

  const tickets = await apiFetch(`/support/tickets?page=${page}`, { includeMeta: true });

  return (
    <>
      <PageHeader
        breadcrumb={[{ label: "Support" }, { label: "Tickets" }]}
        title="Support tickets"
        description="A written conversation with the platform when something needs their attention."
        actions={
          <Link href="/support/tickets/create" className={cn(buttonVariants({ size: "sm" }))}>
            <Plus /> New ticket
          </Link>
        }
      />

      {tickets.data.length === 0 ? (
        <EmptyState title="No support tickets yet" description="Open one whenever something needs the platform's attention." />
      ) : (
        <div className="overflow-hidden rounded-sm border border-border">
          <table className="w-full text-sm">
            <thead className="bg-surface-muted text-xs font-medium text-foreground-muted">
              <tr>
                <th className="px-4 py-3 text-left">Subject</th>
                <th className="px-4 py-3 text-left">Opened by</th>
                <th className="px-4 py-3 text-left">Last activity</th>
                <th className="px-4 py-3 text-left">Status</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-border">
              {tickets.data.map((ticket) => (
                <tr key={ticket.id} className="hover:bg-surface-muted/60">
                  <td className="px-4 py-3">
                    <Link href={`/support/tickets/${ticket.id}`} className="font-medium text-brand hover:underline">
                      {ticket.subject}
                    </Link>
                  </td>
                  <td className="px-4 py-3 text-foreground-muted">{ticket.opener?.name ?? "—"}</td>
                  <td className="px-4 py-3 text-foreground-muted">
                    <FormattedDateTime value={ticket.last_message_at} mode="datetime" fallback="—" />
                  </td>
                  <td className="px-4 py-3">
                    <StatusBadge status={ticket.status} />
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
    </>
  );
}
