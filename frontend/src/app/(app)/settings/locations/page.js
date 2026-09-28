import { apiFetch } from "@/lib/api-server";
import { PageHeader } from "@/components/layout/page-header";
import { LocationsTable } from "@/components/locations/locations-table";
import { getT } from "@/lib/i18n-server";
import { createLocation, updateLocation, toggleLocation, deleteLocation } from "./actions";

/**
 * Mirrors `AssetLocationController::index` (ADR-052) — under Settings
 * because a location is configuration, not day-to-day work. Create/edit
 * happen in a modal over this list rather than a separate page.
 *
 * Only two calls up front — the location list and factories (needed by
 * the always-visible filter dropdown) — rather than the eight this page
 * used to fire in one `Promise.all`. The six master-data lists the
 * create/edit modal's own dropdowns need are fetched once, lazily, the
 * first time that modal is actually opened (`LocationsTable`'s own note
 * has why).
 */
export default async function LocationsPage({ searchParams }) {
  const params = await searchParams;
  const page = Number(params.page ?? 1);
  const search = params.search ?? "";
  const factoryId = params.factory_id ?? "";

  const query = new URLSearchParams({ page: String(page) });
  if (search) query.set("search", search);
  if (factoryId) query.set("factory_id", factoryId);

  const [locations, factories, t, tn] = await Promise.all([
    apiFetch(`/locations?${query.toString()}`, { includeMeta: true }),
    apiFetch("/factories?per_page=100"),
    getT("asset"),
    getT("nav"),
  ]);

  return (
    <>
      <PageHeader
        breadcrumb={[{ label: tn("settings") }, { label: t("locations") }]}
        title={t("locations")}
        description={t("locations_intro")}
      />

      <LocationsTable
        locations={locations.data}
        meta={locations.meta}
        page={page}
        search={search}
        factoryId={factoryId}
        factories={factories}
        actions={{ createLocation, updateLocation, toggleLocation, deleteLocation }}
      />
    </>
  );
}
