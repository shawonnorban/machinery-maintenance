import { PageHeader } from "@/components/layout/page-header";
import { Card, CardBody } from "@/components/ui/card";
import { NewTicketForm } from "@/components/support/new-ticket-form";
import { createTicket } from "../actions";

export default function CreateSupportTicketPage() {
  return (
    <>
      <PageHeader breadcrumb={[{ label: "Support" }, { label: "Tickets", href: "/support/tickets" }, { label: "New ticket" }]} title="New support ticket" />

      <Card>
        <CardBody>
          <NewTicketForm action={createTicket} />
        </CardBody>
      </Card>
    </>
  );
}
