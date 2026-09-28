import { apiFetch } from "@/lib/api-server";
import { PageHeader } from "@/components/layout/page-header";
import { MetersTable } from "@/components/metering/meters-table";
import { getT } from "@/lib/i18n-server";

/**
 * Meters (docs/03-API-Specification.md §10; behaviour mirrors the Blade
 * `metering::meters.index` view exactly per UI-DESIGN-SYSTEM.md rule 1 —
 * same factory scoping, same ACTIVE-by-default status filter, same
 * oldest-reading-first order that surfaces a stale meter first).
 *
 * Server Component: `page`/`status` live in the URL (searchParams) rather
 * than client state, so the list is fetched here and handed to the client
 * table only for its interactive bits (status filter, pagination, row
 * click) — those navigate via the URL rather than calling the API
 * themselves (docs/12-Stack-Migration-Implementation-Plan.md Phase C §5:
 * every Laravel API call happens server-side).
 */
export default async function MetersPage({ searchParams }) {
  const params = await searchParams;
  const page = Number(params.page ?? 1);
  const status = params.status ?? "ACTIVE";
  const assetId = params.asset_id ?? "";

  const query = new URLSearchParams({ page: String(page), status });
  if (assetId) query.set("asset_id", assetId);
  const [meters, t] = await Promise.all([apiFetch(`/meters?${query.toString()}`, { includeMeta: true }), getT("metering")]);

  return (
    <>
      <PageHeader breadcrumb={[{ label: t("meters") }]} title={t("meters")} description={t("intro")} />

      <MetersTable meters={meters.data} meta={meters.meta} page={page} status={status} assetId={assetId} />
    </>
  );
}
