import Link from "next/link";
import { Plus } from "lucide-react";
import { apiFetch } from "@/lib/api-server";
import { PageHeader } from "@/components/layout/page-header";
import { buttonVariants } from "@/components/ui/button";
import { cn } from "@/lib/utils";
import { StockCountsTable } from "@/components/item-master/stock-counts-table";
import { getT } from "@/lib/i18n-server";

/** Mirrors `StockCountApiController::index` (Inventory spec §12). */
export default async function StockCountsPage({ searchParams }) {
  const params = await searchParams;
  const page = Number(params.page ?? 1);
  const search = params.q ?? "";
  const status = params.status ?? "";

  const query = new URLSearchParams({ page: String(page) });
  if (search) query.set("search", search);
  if (status) query.set("status", status);

  const [counts, permissions, t] = await Promise.all([
    apiFetch(`/stock-counts?${query.toString()}`, { includeMeta: true }),
    apiFetch("/auth/permissions"),
    getT("itemmaster"),
  ]);

  const canCount = new Set(permissions.permissions).has("itemmaster.count.create");

  return (
    <>
      <PageHeader
        breadcrumb={[{ label: t("item_master") }, { label: t("stock_counts") }]}
        title={t("stock_counts")}
        description={t("count_hint")}
        actions={
          canCount ? (
            <Link href="/item-master/stock-counts/create" className={cn(buttonVariants({ size: "sm" }))}>
              <Plus /> {t("new_count")}
            </Link>
          ) : null
        }
      />

      <StockCountsTable rows={counts.data} meta={counts.meta} page={page} search={search} status={status} />
    </>
  );
}
