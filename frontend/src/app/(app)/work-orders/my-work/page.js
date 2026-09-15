import { apiFetch } from "@/lib/api-server";
import { PageHeader } from "@/components/layout/page-header";
import { MyWorkTable } from "@/components/work-orders/my-work-table";

/** Mirrors `MyWorkController::index` — rendered on the mobile layout there; here it's the same query against `WorkOrderApiController`'s `assigned_to_me` filter. */
export default async function MyWorkPage({ searchParams }) {
  const params = await searchParams;
  const page = Number(params.page ?? 1);

  const workOrders = await apiFetch(`/work-orders?assigned_to_me=true&page=${page}`, { includeMeta: true });

  return (
    <>
      <PageHeader breadcrumb={[{ label: "My Work" }]} title="My Work" description="Your own queue — in-progress first, then by priority." />

      <MyWorkTable workOrders={workOrders.data} meta={workOrders.meta} />
    </>
  );
}
