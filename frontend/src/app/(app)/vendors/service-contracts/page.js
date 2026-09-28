import Link from "next/link";
import { Plus, AlertTriangle } from "lucide-react";
import { apiFetch } from "@/lib/api-server";
import { PageHeader } from "@/components/layout/page-header";
import { buttonVariants } from "@/components/ui/button";
import { StatCard } from "@/components/ui/stat-card";
import { cn } from "@/lib/utils";
import { ServiceContractsTable } from "@/components/vendor/service-contracts-table";
import { getT } from "@/lib/i18n-server";

/** Mirrors `ServiceContractApiController::index` — commercial info, gated on `vendor.vendor.view_any` rather than the wider asset-view permission warranties use. */
export default async function ServiceContractsPage({ searchParams }) {
  const params = await searchParams;
  const page = Number(params.page ?? 1);
  const expiring = params.expiring === "1";

  const query = new URLSearchParams({ page: String(page) });
  if (expiring) query.set("expiring", "1");

  const [contracts, expiringCount, t] = await Promise.all([
    apiFetch(`/service-contracts?${query.toString()}`, { includeMeta: true }),
    // Mirrors `CoverageController::contracts()`'s own KPI tile — the same
    // `expiringWithin(60)` scope the `expiring=1` filter already applies,
    // just read here as a count rather than a filtered page.
    apiFetch("/service-contracts?expiring=1&per_page=1", { includeMeta: true }).then((r) => r.meta.total),
    getT("vendor"),
  ]);

  return (
    <>
      <PageHeader
        breadcrumb={[{ label: t("vendors") }, { label: t("contracts") }]}
        title={t("contracts")}
        actions={
          <Link href="/vendors/service-contracts/create" className={cn(buttonVariants({ size: "sm" }))}>
            <Plus /> {t("new_contract")}
          </Link>
        }
      />

      <div className="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <StatCard label={t("expiring_soon")} value={expiringCount} icon={<AlertTriangle />} tone={expiringCount > 0 ? "warning" : "success"} />
      </div>

      <ServiceContractsTable contracts={contracts.data} meta={contracts.meta} page={page} expiring={expiring} />
    </>
  );
}
