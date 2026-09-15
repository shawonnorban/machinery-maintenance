import { apiFetch } from "@/lib/api-server";
import { PageHeader } from "@/components/layout/page-header";
import { PartRequestsTable } from "@/components/inventory/part-requests-table";

/** Mirrors `PartRequestController::index` (SRS 22). */
export default async function PartRequestsPage() {
  const lines = await apiFetch("/part-requests");

  return (
    <>
      <PageHeader
        breadcrumb={[{ label: "Inventory" }, { label: "Part requests" }]}
        title="Part requests"
        description="Every part asked for and not yet handed over, with what's on the shelf beside it."
      />

      <PartRequestsTable lines={lines} />
    </>
  );
}
