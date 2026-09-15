import { platformApiFetch } from "@/lib/platform-api-server";
import { PageHeader } from "@/components/layout/page-header";
import { TicketsInboxTable } from "@/components/platform/tickets-inbox-table";

/** Every customer's tickets, from the platform side (mirrors `desk/tickets.blade.php`). */
export default async function PlatformTicketsPage({ searchParams }) {
  const params = await searchParams;
  const page = Number(params.page ?? 1);
  const status = params.status ?? "";

  const query = new URLSearchParams({ page: String(page) });
  if (status) query.set("status", status);

  const tickets = await platformApiFetch(`/tickets?${query.toString()}`, { includeMeta: true });

  return (
    <>
      <PageHeader breadcrumb={[{ label: "Tickets" }]} title="Tickets" description="Every customer's own support tickets, in one inbox." />
      <TicketsInboxTable tickets={tickets.data} meta={tickets.meta} page={page} status={status} />
    </>
  );
}
