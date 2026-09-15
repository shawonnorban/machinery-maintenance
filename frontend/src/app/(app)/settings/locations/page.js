import { apiFetch } from "@/lib/api-server";
import { PageHeader } from "@/components/layout/page-header";
import { LocationsTable } from "@/components/locations/locations-table";
import { createLocation, updateLocation, toggleLocation, deleteLocation } from "./actions";

/** Mirrors `AssetLocationController::index` (ADR-052) — under Settings because a location is configuration, not day-to-day work. Create/edit happen in a modal over this list rather than a separate page. */
export default async function LocationsPage({ searchParams }) {
  const params = await searchParams;
  const page = Number(params.page ?? 1);
  const search = params.search ?? "";
  const factoryId = params.factory_id ?? "";

  const query = new URLSearchParams({ page: String(page) });
  if (search) query.set("search", search);
  if (factoryId) query.set("factory_id", factoryId);

  const [locations, factories, buildings, floors, departments, sections, productionLines, workstations] = await Promise.all([
    apiFetch(`/locations?${query.toString()}`, { includeMeta: true }),
    apiFetch("/factories?per_page=100"),
    apiFetch("/master-data/buildings?per_page=200"),
    apiFetch("/master-data/floors?per_page=200"),
    apiFetch("/master-data/departments?per_page=200"),
    apiFetch("/master-data/sections?per_page=200"),
    apiFetch("/master-data/production-lines?per_page=200"),
    apiFetch("/master-data/workstations?per_page=200"),
  ]);

  return (
    <>
      <PageHeader
        breadcrumb={[{ label: "Settings" }, { label: "Locations" }]}
        title="Locations"
        description="Where machines actually live — a specific building, department and line named as one place."
      />

      <LocationsTable
        locations={locations.data}
        meta={locations.meta}
        page={page}
        search={search}
        factoryId={factoryId}
        factories={factories}
        buildings={buildings}
        floors={floors}
        departments={departments}
        sections={sections}
        productionLines={productionLines}
        workstations={workstations}
        actions={{ createLocation, updateLocation, toggleLocation, deleteLocation }}
      />
    </>
  );
}
