import { apiFetch } from "@/lib/api-server";
import { PageHeader } from "@/components/layout/page-header";
import { StockCountForm } from "@/components/item-master/stock-count-form";
import { getT } from "@/lib/i18n-server";
import { createStockCount } from "./actions";

/** Mirrors `StockCountApiController::formOptions` (Inventory spec §12). */
export default async function CreateStockCountPage() {
  const [options, t] = await Promise.all([apiFetch("/stock-counts/form-options"), getT("itemmaster")]);

  return (
    <>
      <PageHeader
        breadcrumb={[
          { label: t("item_master") },
          { label: t("stock_counts"), href: "/item-master/stock-counts" },
          { label: t("new_count") },
        ]}
        title={t("new_count")}
        description={t("count_hint")}
      />

      <StockCountForm
        factories={options.factories ?? []}
        stores={options.stores ?? []}
        departments={options.item_departments ?? []}
        categories={options.item_categories ?? []}
        onSubmit={createStockCount}
      />
    </>
  );
}
