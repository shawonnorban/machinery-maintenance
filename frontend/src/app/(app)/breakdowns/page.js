import Link from "next/link";
import { Plus, AlertTriangle, BellRing, Wrench, CheckCircle2 } from "lucide-react";
import { apiFetch } from "@/lib/api-server";
import { PageHeader } from "@/components/layout/page-header";
import { buttonVariants } from "@/components/ui/button";
import { StatCard } from "@/components/ui/stat-card";
import { cn } from "@/lib/utils";
import { BreakdownsTable } from "@/components/breakdowns/breakdowns-table";
import { BreakdownsLiveUpdates } from "@/components/breakdowns/breakdowns-live-updates";

/**
 * Mirrors `BreakdownApiController::index` — `open=true` is the question a
 * dashboard actually asks (Breakdown::OPEN_STATUSES), not a client-side
 * guess at which statuses count as open.
 */
export default async function BreakdownsPage({ searchParams }) {
  const params = await searchParams;
  const page = Number(params.page ?? 1);
  const open = params.open ?? "true";
  const priority = params.priority ?? "";

  const query = new URLSearchParams({ page: String(page), open });
  if (priority) query.set("priority", priority);

  const [breakdowns, counts, me] = await Promise.all([
    apiFetch(`/breakdowns?${query.toString()}`, { includeMeta: true }),
    // Mirrors `BreakdownController::index()`'s own KPI tile row — dropped
    // when this list was first ported, restored via its own endpoint since
    // it isn't scoped to the current filter/page.
    apiFetch("/breakdowns/counts"),
    apiFetch("/auth/me"),
  ]);

  return (
    <>
      <BreakdownsLiveUpdates companyId={me.company_id} />

      <PageHeader
        breadcrumb={[{ label: "Breakdowns" }]}
        title="Breakdowns"
        description="Every machine stoppage on the floors you can reach."
        actions={
          <Link href="/breakdowns/create" className={cn(buttonVariants({ size: "sm", variant: "danger" }))}>
            <Plus /> Report breakdown
          </Link>
        }
      />

      <div className="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <StatCard label="Open" value={counts.open} icon={<AlertTriangle />} tone="danger" />
        <StatCard label="Unacknowledged" value={counts.unacknowledged} icon={<BellRing />} tone="warning" />
        <StatCard label="In repair" value={counts.in_repair} icon={<Wrench />} tone="brand" />
        <StatCard label="Awaiting closure" value={counts.awaiting_closure} icon={<CheckCircle2 />} tone="info" />
      </div>

      <BreakdownsTable breakdowns={breakdowns.data} meta={breakdowns.meta} page={page} open={open} priority={priority} />
    </>
  );
}
