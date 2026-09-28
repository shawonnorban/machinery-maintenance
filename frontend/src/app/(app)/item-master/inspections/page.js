import { apiFetch } from "@/lib/api-server";
import { PageHeader } from "@/components/layout/page-header";
import { InspectionsTable } from "@/components/item-master/inspections-table";
import { getT } from "@/lib/i18n-server";

/** Mirrors `FabricInspectionApiController::index` (Inventory spec §4.3). */
export default async function InspectionsPage({ searchParams }) {
  const params = await searchParams;
  const page = Number(params.page ?? 1);
  const search = params.q ?? "";
  const status = params.status ?? "";

  const query = new URLSearchParams({ page: String(page) });
  if (search) query.set("search", search);
  if (status) query.set("status", status);

  const [inspections, t] = await Promise.all([
    apiFetch(`/fabric-inspections?${query.toString()}`, { includeMeta: true }),
    getT("inspection"),
  ]);

  return (
    <>
      <PageHeader
        breadcrumb={[{ label: t("fabric_inspection") }, { label: t("inspections") }]}
        title={t("inspections")}
        description={t("four_point_hint")}
      />

      <InspectionsTable
        rows={inspections.data}
        meta={inspections.meta}
        page={page}
        search={search}
        status={status}
      />
    </>
  );
}
