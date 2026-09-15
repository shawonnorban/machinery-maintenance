import { apiFetch } from "@/lib/api-server";
import { PageHeader } from "@/components/layout/page-header";
import { LowStockTable } from "@/components/inventory/low-stock-table";

/** Mirrors `StockController::lowStock` — below the reorder level, the actionable signal; by the time stock is out the lead time has already been lost. */
export default async function LowStockPage() {
  const parts = await apiFetch("/spare-parts/low-stock");

  return (
    <>
      <PageHeader
        breadcrumb={[{ label: "Inventory" }, { label: "Low Stock" }]}
        title="Low stock"
        description="At or below the reorder level. Critical spares — an absence that stops a critical machine — lead the list."
      />

      <LowStockTable parts={parts} />
    </>
  );
}
