import { apiFetch } from "@/lib/api-server";
import { PageHeader } from "@/components/layout/page-header";
import { LowStockTable } from "@/components/inventory/low-stock-table";
import { getT } from "@/lib/i18n-server";

/** Mirrors `StockController::lowStock` — below the reorder level, the actionable signal; by the time stock is out the lead time has already been lost. */
export default async function LowStockPage() {
  const [parts, t] = await Promise.all([
    apiFetch("/spare-parts/low-stock"),
    getT("inventory"),
  ]);

  return (
    <>
      <PageHeader
        breadcrumb={[{ label: t("inventory") }, { label: t("low_stock") }]}
        title={t("low_stock")}
        description={t("low_stock_page_description")}
      />

      <LowStockTable parts={parts} />
    </>
  );
}
