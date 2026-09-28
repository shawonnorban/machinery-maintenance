import { apiFetch } from "@/lib/api-server";
import { PageHeader } from "@/components/layout/page-header";
import { PartRequestsTable } from "@/components/inventory/part-requests-table";
import { getT } from "@/lib/i18n-server";

/** Mirrors `PartRequestController::index` (SRS 22). */
export default async function PartRequestsPage() {
  const [lines, t] = await Promise.all([
    apiFetch("/part-requests"),
    getT("inventory"),
  ]);

  return (
    <>
      <PageHeader
        breadcrumb={[{ label: t("inventory") }, { label: t("part_requests") }]}
        title={t("part_requests")}
        description={t("part_requests_intro")}
      />

      <PartRequestsTable lines={lines} />
    </>
  );
}
