import { Ticket } from "lucide-react";
import { platformApiFetch } from "@/lib/platform-api-server";
import { PlatformHeader } from "@/components/platform/ui";
import { TicketsInboxTable } from "@/components/platform/tickets-inbox-table";

/** Every customer's tickets, from the platform side. */
export default async function PlatformTicketsPage({ searchParams }) {
  const params = await searchParams;
  const page = Number(params.page ?? 1);
  const status = params.status ?? "";
  const priority = params.priority ?? "";

  const query = new URLSearchParams({ page: String(page) });
  if (status) query.set("status", status);
  if (priority) query.set("priority", priority);

  const tickets = await platformApiFetch(`/tickets?${query.toString()}`, { includeMeta: true });

  return (
    <>
      <PlatformHeader breadcrumb={[{ label: "Tickets" }]} title="Tickets" description="Every customer's own support tickets, in one inbox." icon={<Ticket />} tone="warning" />
      <TicketsInboxTable tickets={tickets.data} meta={tickets.meta} page={page} status={status} priority={priority} />
    </>
  );
}
