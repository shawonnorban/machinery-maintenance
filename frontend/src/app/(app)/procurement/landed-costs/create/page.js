import { apiFetch } from "@/lib/api-server";
import { PageHeader } from "@/components/layout/page-header";
import { LandedCostForm } from "@/components/procurement/landed-cost-form";
import { getT } from "@/lib/i18n-server";
import { createLandedCost } from "./actions";

/**
 * A new landed cost voucher (Procurement spec §11). The receipts come from
 * the goods-receipt list rather than a bespoke endpoint — a charge lands on
 * a completed receipt, which is exactly what that list already filters to.
 */
export default async function CreateLandedCostPage() {
  const [receipts, options, t] = await Promise.all([
    apiFetch("/goods-receipts?status=COMPLETED&per_page=50", { includeMeta: true }),
    apiFetch("/goods-receipts/form-options"),
    getT("procurement"),
  ]);

  return (
    <>
      <PageHeader
        breadcrumb={[
          { label: t("procurement") },
          { label: t("landed_costs"), href: "/procurement/landed-costs" },
          { label: t("new_landed_cost") },
        ]}
        title={t("new_landed_cost")}
        description={t("landed_cost_hint")}
      />

      <LandedCostForm
        receipts={receipts.data}
        factories={options.factories ?? []}
        vendors={options.vendors ?? []}
        onSubmit={createLandedCost}
      />
    </>
  );
}
