import { apiFetch } from "@/lib/api-server";
import { PageHeader } from "@/components/layout/page-header";
import { LowStockTable } from "@/components/item-master/low-stock-table";
import { getT } from "@/lib/i18n-server";

/** Mirrors `ItemApiController::lowStock` — items at or below their reorder level, emptiest first (Phase 1 reorder alert). */
export default async function LowStockItemsPage({ searchParams }) {
  const params = await searchParams;
  const page = Number(params.page ?? 1);
  const departmentId = params.item_department_id ?? "";

  const query = new URLSearchParams({ page: String(page) });
  if (departmentId) query.set("item_department_id", departmentId);

  const [items, options, t] = await Promise.all([
    apiFetch(`/items/low-stock?${query.toString()}`, { includeMeta: true }),
    apiFetch("/items/form-options"),
    getT("itemmaster"),
  ]);

  return (
    <>
      <PageHeader
        breadcrumb={[{ label: t("item_library"), href: "/item-master" }, { label: t("below_reorder") }]}
        title={t("below_reorder")}
        description={t("low_stock_intro")}
      />

      <LowStockTable items={items.data} meta={items.meta} page={page} departmentId={departmentId} departments={options.item_departments} />
    </>
  );
}
