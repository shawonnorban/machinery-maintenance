import Link from "next/link";
import { Plus, ListTodo, Play, PauseCircle, BadgeCheck } from "lucide-react";
import { apiFetch } from "@/lib/api-server";
import { PageHeader } from "@/components/layout/page-header";
import { buttonVariants } from "@/components/ui/button";
import { StatCard } from "@/components/ui/stat-card";
import { cn } from "@/lib/utils";
import { WorkOrdersTable } from "@/components/work-orders/work-orders-table";

/** Mirrors `WorkOrderApiController::index` — `open=true` reads `WorkOrder::TERMINAL_STATUSES`, not a client-guessed list. */
export default async function WorkOrdersPage({ searchParams }) {
  const params = await searchParams;
  const page = Number(params.page ?? 1);
  const open = params.open ?? "true";
  const priority = params.priority ?? "";

  const query = new URLSearchParams({ page: String(page), open });
  if (priority) query.set("priority", priority);

  const [workOrders, counts] = await Promise.all([
    apiFetch(`/work-orders?${query.toString()}`, { includeMeta: true }),
    // Mirrors `WorkOrderController::index()`'s own KPI tile row — dropped
    // when this list was first ported, restored via its own endpoint since
    // it isn't scoped to the current filter/page.
    apiFetch("/work-orders/counts"),
  ]);

  return (
    <>
      <PageHeader
        breadcrumb={[{ label: "Work Orders" }]}
        title="Work Orders"
        description="Every scheduled and in-progress job on the floors you can reach."
        actions={
          <Link href="/work-orders/create" className={cn(buttonVariants({ size: "sm" }))}>
            <Plus /> New work order
          </Link>
        }
      />

      <div className="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <StatCard label="Open" value={counts.open} icon={<ListTodo />} tone="brand" />
        <StatCard label="In progress" value={counts.in_progress} icon={<Play />} tone="info" />
        <StatCard label="On hold" value={counts.on_hold} icon={<PauseCircle />} tone="warning" />
        <StatCard label="Awaiting verification" value={counts.awaiting_verification} icon={<BadgeCheck />} tone="success" />
      </div>

      <WorkOrdersTable workOrders={workOrders.data} meta={workOrders.meta} page={page} open={open} priority={priority} />
    </>
  );
}
