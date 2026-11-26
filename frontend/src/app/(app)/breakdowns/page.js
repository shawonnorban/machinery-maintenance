import { AlertTriangle, BellRing, Wrench, CheckCircle2 } from "lucide-react";
import { apiFetch } from "@/lib/api-server";
import { ScreenHeader } from "@/components/layout/screen-header";
import { StatCard } from "@/components/ui/stat-card";
import { BreakdownsTable } from "@/components/breakdowns/breakdowns-table";
import { BreakdownsLiveUpdates } from "@/components/breakdowns/breakdowns-live-updates";
import { getT } from "@/lib/i18n-server";
import { applyPerPage } from "@/lib/per-page";

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
  await applyPerPage(query, "/breakdowns");
  if (priority) query.set("priority", priority);

  const [breakdowns, counts, me, permissions, t] = await Promise.all([
    apiFetch(`/breakdowns?${query.toString()}`, { includeMeta: true }),
    // Mirrors `BreakdownController::index()`'s own KPI tile row — dropped
    // when this list was first ported, restored via its own endpoint since
    // it isn't scoped to the current filter/page.
    apiFetch("/breakdowns/counts"),
    apiFetch("/auth/me"),
    // Reporting is Line Chief/Engineer/Manager's job now, not a Technician's
    // (`breakdown.breakdown.create` — RoleSeeder's matrix) — checked here so
    // a Technician never sees a "Report breakdown" button the API would 403.
    apiFetch("/auth/permissions"),
    getT("breakdown"),
  ]);

  const canReport = permissions.permissions.includes("breakdown.breakdown.create");

  return (
    <>
      <BreakdownsLiveUpdates companyId={me.company_id} />

      <ScreenHeader
        url="/breakdowns"
        title={t("breakdowns")}
        description={t("page_description")}
      />

      <div className="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <StatCard label={t("filter_open")} value={counts.open} icon={<AlertTriangle />} tone="danger" />
        <StatCard label={t("unacknowledged")} value={counts.unacknowledged} icon={<BellRing />} tone="warning" />
        <StatCard label={t("filter_in_repair")} value={counts.in_repair} icon={<Wrench />} tone="brand" />
        <StatCard label={t("filter_awaiting_closure")} value={counts.awaiting_closure} icon={<CheckCircle2 />} tone="info" />
      </div>

      <BreakdownsTable addHref={canReport ? "/breakdowns/create" : null} breakdowns={breakdowns.data} meta={breakdowns.meta} page={page} open={open} priority={priority} />
    </>
  );
}
