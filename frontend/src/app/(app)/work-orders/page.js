import Link from "next/link";
import { Plus, ListTodo, Play, PauseCircle, BadgeCheck } from "lucide-react";
import { apiFetch } from "@/lib/api-server";
import { PageHeader } from "@/components/layout/page-header";
import { buttonVariants } from "@/components/ui/button";
import { StatCard } from "@/components/ui/stat-card";
import { cn } from "@/lib/utils";
import { WorkOrdersTable } from "@/components/work-orders/work-orders-table";
import { getT } from "@/lib/i18n-server";

/** Mirrors `WorkOrderApiController::index` — `open=true` reads `WorkOrder::TERMINAL_STATUSES`, not a client-guessed list. */
export default async function WorkOrdersPage({ searchParams }) {
  const params = await searchParams;
  const page = Number(params.page ?? 1);
  const open = params.open ?? "true";
  const priority = params.priority ?? "";

  const query = new URLSearchParams({ page: String(page), open });
  if (priority) query.set("priority", priority);

  const [workOrders, counts, t] = await Promise.all([
    apiFetch(`/work-orders?${query.toString()}`, { includeMeta: true }),
    // Mirrors `WorkOrderController::index()`'s own KPI tile row — dropped
    // when this list was first ported, restored via its own endpoint since
    // it isn't scoped to the current filter/page.
    apiFetch("/work-orders/counts"),
    getT("work_order"),
  ]);

  return (
    <>
      <PageHeader
        breadcrumb={[{ label: t("work_orders") }]}
        title={t("work_orders")}
        description={t("page_description")}
        actions={
          <Link href="/work-orders/create" className={cn(buttonVariants({ size: "sm" }))}>
            <Plus /> {t("new_work_order")}
          </Link>
        }
      />

      <div className="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <StatCard label={t("filter_open")} value={counts.open} icon={<ListTodo />} tone="brand" />
        <StatCard label={t("filter_in_progress")} value={counts.in_progress} icon={<Play />} tone="info" />
        <StatCard label={t("filter_on_hold")} value={counts.on_hold} icon={<PauseCircle />} tone="warning" />
        <StatCard label={t("filter_awaiting_verification")} value={counts.awaiting_verification} icon={<BadgeCheck />} tone="success" />
      </div>

      <WorkOrdersTable workOrders={workOrders.data} meta={workOrders.meta} page={page} open={open} priority={priority} />
    </>
  );
}
