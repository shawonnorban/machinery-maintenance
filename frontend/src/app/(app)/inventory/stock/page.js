import { DollarSign, Truck, Boxes } from "lucide-react";
import { apiFetch } from "@/lib/api-server";
import { PageHeader } from "@/components/layout/page-header";
import { StatCard } from "@/components/ui/stat-card";
import { StockBalancesTable } from "@/components/inventory/stock-balances-table";
import { StockFilters } from "@/components/inventory/stock-filters";
import { getT } from "@/lib/i18n-server";

/**
 * Mirrors `StockController::index` — what's on the shelf right now, bin by
 * bin, across every part, as opposed to `/inventory/parts`'s catalogue of
 * what a part is.
 */
export default async function InventoryStockPage({ searchParams }) {
  const params = await searchParams;
  const page = Number(params.page ?? 1);
  const search = params.search ?? "";
  const binId = params.bin_id ?? "";

  const query = new URLSearchParams({ page: String(page) });
  if (search) query.set("search", search);
  if (binId) query.set("bin_id", binId);

  const [balances, bins, t] = await Promise.all([
    apiFetch(`/inventory-balances?${query.toString()}`, { includeMeta: true }),
    apiFetch("/spare-parts/bins"),
    getT("inventory"),
  ]);

  const { totals } = balances.meta;

  return (
    <>
      <PageHeader
        breadcrumb={[{ label: t("spare_parts"), href: "/inventory/parts" }, { label: t("stock") }]}
        title={t("stock")}
        description={t("stock_page_description")}
      />

      <div className="mb-4 grid grid-cols-1 gap-4 sm:grid-cols-3">
        <StatCard
          label={t("stock_value")}
          value={Number(totals.value).toLocaleString(undefined, { maximumFractionDigits: 0 })}
          icon={<DollarSign />}
          tone="brand"
        />
        <StatCard
          label={t("in_transit")}
          value={Number(totals.in_transit).toLocaleString(undefined, { maximumFractionDigits: 2 })}
          icon={<Truck />}
          tone="info"
        />
        <StatCard label={t("balance_lines")} value={totals.lines} icon={<Boxes />} tone="success" />
      </div>

      <div className="mb-4">
        <StockFilters bins={bins} search={search} binId={binId} />
      </div>

      <StockBalancesTable balances={balances.data} meta={balances.meta} search={search} binId={binId} />
    </>
  );
}
