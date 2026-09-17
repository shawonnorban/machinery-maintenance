import Link from "next/link";
import { Plus, AlertTriangle, BellRing, Wrench, CheckCircle2 } from "lucide-react";
import { apiFetch } from "@/lib/api-server";
import { PageHeader } from "@/components/layout/page-header";
import { buttonVariants } from "@/components/ui/button";
import { StatCard } from "@/components/ui/stat-card";
import { cn } from "@/lib/utils";
import { BreakdownsTable } from "@/components/breakdowns/breakdowns-table";
import { BreakdownsLiveUpdates } from "@/components/breakdowns/breakdowns-live-updates";
import { getT } from "@/lib/i18n-server";

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

  const [breakdowns, counts, me, t] = await Promise.all([
    apiFetch(`/breakdowns?${query.toString()}`, { includeMeta: true }),
    // Mirrors `BreakdownController::index()`'s own KPI tile row — dropped
    // when this list was first ported, restored via its own endpoint since
    // it isn't scoped to the current filter/page.
    apiFetch("/breakdowns/counts"),
    apiFetch("/auth/me"),
    getT("breakdown"),
  ]);

  return (
    <>
      <BreakdownsLiveUpdates companyId={me.company_id} />

      <PageHeader
        breadcrumb={[{ label: t("breakdowns") }]}
        title={t("breakdowns")}
        description={t("page_description")}
        actions={
          <Link href="/breakdowns/create" className={cn(buttonVariants({ size: "sm", variant: "danger" }))}>
            <Plus /> {t("report_breakdown")}
          </Link>
        }
      />

      <div className="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <StatCard label={t("filter_open")} value={counts.open} icon={<AlertTriangle />} tone="danger" />
        <StatCard label={t("unacknowledged")} value={counts.unacknowledged} icon={<BellRing />} tone="warning" />
        <StatCard label={t("filter_in_repair")} value={counts.in_repair} icon={<Wrench />} tone="brand" />
        <StatCard label={t("filter_awaiting_closure")} value={counts.awaiting_closure} icon={<CheckCircle2 />} tone="info" />
      </div>

      <BreakdownsTable breakdowns={breakdowns.data} meta={breakdowns.meta} page={page} open={open} priority={priority} />
    </>
  );
}
