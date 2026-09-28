import { redirect } from "next/navigation";
import { apiFetch } from "@/lib/api-server";
import { PageHeader } from "@/components/layout/page-header";
import { Card, CardBody } from "@/components/ui/card";
import { EditTicketForm } from "@/components/support/edit-ticket-form";
import { getT } from "@/lib/i18n-server";
import { updateTicket } from "../../actions";

export default async function EditSupportTicketPage({ params }) {
  const { ticketId } = await params;

  const [ticket, t] = await Promise.all([apiFetch(`/support/tickets/${ticketId}`), getT("support")]);

  // The API itself refuses an edit once it's no longer eligible (403/409),
  // but there is no reason to show the form at all if the answer is
  // already known before the customer even starts typing.
  if (!ticket.can_edit) {
    redirect(`/support/tickets/${ticketId}`);
  }

  return (
    <>
      <PageHeader
        breadcrumb={[
          { label: t("support") },
          { label: t("tickets"), href: "/support/tickets" },
          { label: ticket.subject, href: `/support/tickets/${ticketId}` },
          { label: t("edit_ticket") },
        ]}
        title={t("edit_ticket")}
      />

      <Card>
        <CardBody>
          <EditTicketForm ticket={ticket} action={updateTicket.bind(null, ticketId)} />
        </CardBody>
      </Card>
    </>
  );
}
