import { platformApiFetch } from "@/lib/platform-api-server";
import { PageHeader } from "@/components/layout/page-header";
import { TicketThread } from "@/components/platform/ticket-thread";
import { replyToTicket, setTicketStatus, assignTicket, reclassifyTicket, uploadTicketAttachment } from "../actions";

export default async function PlatformTicketDetailPage({ params }) {
  const { ticketId } = await params;
  const [ticket, me, attachments] = await Promise.all([
    platformApiFetch(`/tickets/${ticketId}`),
    platformApiFetch("/auth/me"),
    platformApiFetch(`/tickets/${ticketId}/attachments`),
  ]);

  return (
    <>
      <PageHeader
        breadcrumb={[{ label: "Tickets", href: "/platform/tickets" }, { label: ticket.subject }]}
        title={ticket.subject}
        description={`${ticket.company?.name ?? "—"} · opened by ${ticket.opener?.name ?? "—"}`}
      />
      <TicketThread
        ticket={ticket}
        me={me}
        staff={ticket.staff}
        attachments={attachments}
        replyAction={replyToTicket.bind(null, ticketId)}
        statusAction={setTicketStatus.bind(null, ticketId)}
        assignAction={assignTicket.bind(null, ticketId)}
        reclassifyAction={reclassifyTicket.bind(null, ticketId)}
        uploadAction={uploadTicketAttachment.bind(null, ticketId)}
      />
    </>
  );
}
