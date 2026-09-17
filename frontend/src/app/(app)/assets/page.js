import Link from "next/link";
import { Plus, Boxes, Play, TriangleAlert, Flame } from "lucide-react";
import { apiFetch } from "@/lib/api-server";
import { PageHeader } from "@/components/layout/page-header";
import { AssetsTable } from "@/components/assets/assets-table";
import { StatCard } from "@/components/ui/stat-card";
import { buttonVariants } from "@/components/ui/button";
import { cn } from "@/lib/utils";
import { getT } from "@/lib/i18n-server";

const STATUSES = [
  "DRAFT", "PURCHASED", "INSTALLED", "COMMISSIONED", "RUNNING", "IDLE",
  "UNDER_MAINTENANCE", "BREAKDOWN", "UNDER_REPAIR", "RETIRED", "SCRAPPED", "LOST",
];
const CRITICALITIES = ["CRITICAL", "HIGH", "MEDIUM", "LOW"];

/**
 * Machines (docs/03-API-Specification.md §6; behaviour mirrors the Blade
 * `asset::assets.index` view — same search-by-prefix on code/name/serial,
 * same status/criticality filters, same sortable columns).
 */
export default async function AssetsPage({ searchParams }) {
  const params = await searchParams;
  const page = Number(params.page ?? 1);
  const search = params.search ?? "";
  const status = params.status ?? "";
  const criticality = params.criticality ?? "";
  const sort = params.sort ?? "asset_code";
  const direction = params.direction ?? "asc";

  const query = new URLSearchParams({ page: String(page), sort, direction });
  if (search) query.set("search", search);
  if (status) query.set("status", status);
  if (criticality) query.set("criticality", criticality);

  const [assets, counts, t] = await Promise.all([
    apiFetch(`/assets?${query.toString()}`, { includeMeta: true }),
    // Not scoped to whatever status/criticality filter or page the list
    // below happens to be on — same reasoning as Work Orders' own
    // `/work-orders/counts`.
    apiFetch("/assets/counts"),
    getT("asset"),
  ]);

  return (
    <>
      <PageHeader
        breadcrumb={[{ label: t("assets") }]}
        title={t("assets")}
        description={t("page_description")}
        actions={
          <Link href="/assets/create" className={cn(buttonVariants({ size: "sm" }))}>
            <Plus /> {t("new_asset")}
          </Link>
        }
      />

      <div className="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <StatCard label={t("total_assets")} value={counts.total} icon={<Boxes />} tone="brand" />
        <StatCard label={t("status_running")} value={counts.running} icon={<Play />} tone="success" />
        <StatCard label={t("needs_attention")} value={counts.needs_attention} icon={<TriangleAlert />} tone="danger" />
        <StatCard label={t("criticality_critical")} value={counts.critical} icon={<Flame />} tone="warning" />
      </div>

      <AssetsTable
        assets={assets.data}
        meta={assets.meta}
        page={page}
        search={search}
        status={status}
        criticality={criticality}
        sort={sort}
        direction={direction}
        statusOptions={STATUSES}
        criticalityOptions={CRITICALITIES}
      />
    </>
  );
}
