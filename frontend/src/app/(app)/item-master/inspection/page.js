import { apiFetch } from "@/lib/api-server";
import { PageHeader } from "@/components/layout/page-header";
import { InspectionTable } from "@/components/item-master/inspection-table";
import { getT } from "@/lib/i18n-server";

/** Mirrors `FabricRollApiController::index` (Inventory spec §3, §4) — defaults to the inspection queue. */
export default async function FabricInspectionPage({ searchParams }) {
  const params = await searchParams;
  const page = Number(params.page ?? 1);
  const status = params.status ?? "UNDER_INSPECTION";
  const itemId = params.item_id ?? "";

  const query = new URLSearchParams({ page: String(page), status });
  if (itemId) query.set("item_id", itemId);

  const [rolls, t] = await Promise.all([
    apiFetch(`/fabric-rolls?${query.toString()}`, { includeMeta: true }),
    getT("inspection"),
  ]);

  return (
    <>
      <PageHeader
        breadcrumb={[{ label: t("fabric_inspection") }]}
        title={t("fabric_inspection")}
        description={t("inspection_queue")}
      />

      <InspectionTable rows={rolls.data} meta={rolls.meta} page={page} status={status} />
    </>
  );
}
