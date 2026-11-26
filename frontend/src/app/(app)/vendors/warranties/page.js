import { AlertTriangle } from "lucide-react";
import { apiFetch } from "@/lib/api-server";
import { ScreenHeader } from "@/components/layout/screen-header";
import { StatCard } from "@/components/ui/stat-card";
import { WarrantiesTable } from "@/components/vendor/warranties-table";
import { getT } from "@/lib/i18n-server";
import { applyPerPage } from "@/lib/per-page";

/** Mirrors `WarrantyApiController::index` — reading is deliberately as wide as asset visibility (SRS 18), since a technician at the machine needs to see cover is already paid for. */
export default async function WarrantiesPage({ searchParams }) {
  const params = await searchParams;
  const page = Number(params.page ?? 1);
  const expiring = params.expiring === "1";

  const query = new URLSearchParams({ page: String(page) });
  await applyPerPage(query, "/vendors/warranties");
  if (expiring) query.set("expiring", "1");

  const [warranties, expiringCount, t] = await Promise.all([
    apiFetch(`/warranties?${query.toString()}`, { includeMeta: true }),
    // Mirrors `CoverageController::warranties()`'s own KPI tile — the same
    // `expiringWithin(60)` scope the `expiring=1` filter already applies,
    // just read here as a count rather than a filtered page.
    apiFetch("/warranties?expiring=1&per_page=1", { includeMeta: true }).then((r) => r.meta.total),
    getT("vendor"),
  ]);

  return (
    <>
      <ScreenHeader
        url="/vendors/warranties"
        title={t("warranties")}
        description={t("warranties_description")}
      />

      <div className="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <StatCard label={t("expiring_soon")} value={expiringCount} icon={<AlertTriangle />} tone={expiringCount > 0 ? "warning" : "success"} />
      </div>

      <WarrantiesTable addHref="/vendors/warranties/create" warranties={warranties.data} meta={warranties.meta} page={page} expiring={expiring} />
    </>
  );
}
