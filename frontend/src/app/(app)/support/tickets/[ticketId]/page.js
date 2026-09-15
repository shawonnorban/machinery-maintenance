import { apiFetch } from "@/lib/api-server";
import { PageHeader } from "@/components/layout/page-header";
import { Card, CardBody } from "@/components/ui/card";
import { StatusBadge } from "@/components/ui/status-badge";
import { FormattedDateTime } from "@/components/ui/formatted-date-time";
import { ReplyForm } from "@/components/support/reply-form";
import { cn } from "@/lib/utils";
import { replyTicket } from "../actions";

/** Mirrors `tickets/show.blade.php` — the conversation itself. */
export default async function SupportTicketDetailPage({ params }) {
  const { ticketId } = await params;
  const ticket = await apiFetch(`/support/tickets/${ticketId}`);

  return (
    <>
      <PageHeader
        breadcrumb={[{ label: "Support" }, { label: "Tickets", href: "/support/tickets" }, { label: ticket.subject }]}
        title={ticket.subject}
        actions={<StatusBadge status={ticket.status} />}
      />

      <div className="flex flex-col gap-4">
        <div className="flex flex-col gap-3">
          {ticket.messages.map((message) => (
            <Card key={message.id} className={cn(message.author_is_platform ? "bg-surface-muted" : undefined)}>
              <CardBody>
                <div className="mb-2 flex items-center justify-between text-xs text-foreground-muted">
                  <span className="font-medium text-foreground">
                    {message.author?.name ?? "—"}
                    {message.author_is_platform ? " · Platform support" : null}
                  </span>
                  <FormattedDateTime value={message.created_at} mode="datetime" />
                </div>
                <p className="whitespace-pre-wrap text-sm text-foreground">{message.body}</p>
              </CardBody>
            </Card>
          ))}
        </div>

        {ticket.is_open ? (
          <Card>
            <CardBody>
              <ReplyForm action={replyTicket.bind(null, ticketId)} />
            </CardBody>
          </Card>
        ) : (
          <p className="text-sm text-foreground-muted">This ticket is closed.</p>
        )}
      </div>
    </>
  );
}
