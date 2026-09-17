import { PageHeader } from "@/components/layout/page-header";
import { Card, CardBody } from "@/components/ui/card";
import { NewTicketForm } from "@/components/support/new-ticket-form";
import { getT } from "@/lib/i18n-server";
import { createTicket } from "../actions";

export default async function CreateSupportTicketPage() {
  const t = await getT("support");

  return (
    <>
      <PageHeader
        breadcrumb={[{ label: t("support") }, { label: t("tickets"), href: "/support/tickets" }, { label: t("new_ticket") }]}
        title={t("new_ticket_title")}
      />

      <Card>
        <CardBody>
          <NewTicketForm action={createTicket} />
        </CardBody>
      </Card>
    </>
  );
}
