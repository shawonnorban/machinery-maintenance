import Link from "next/link";
import { Plus } from "lucide-react";
import { apiFetch } from "@/lib/api-server";
import { PageHeader } from "@/components/layout/page-header";
import { buttonVariants } from "@/components/ui/button";
import { cn } from "@/lib/utils";
import { ReceiptsTable } from "@/components/procurement/receipts-table";
import { getT } from "@/lib/i18n-server";

/** Mirrors `GoodsReceiptApiController::index` (Procurement spec §9). */
export default async function GoodsReceiptsPage({ searchParams }) {
  const params = await searchParams;
  const page = Number(params.page ?? 1);
  const search = params.q ?? "";
  const status = params.status ?? "";

  const query = new URLSearchParams({ page: String(page) });
  if (search) query.set("search", search);
  if (status) query.set("status", status);

  const [receipts, permissions, t] = await Promise.all([
    apiFetch(`/goods-receipts?${query.toString()}`, { includeMeta: true }),
    apiFetch("/auth/permissions"),
    getT("procurement"),
  ]);

  const canCreate = new Set(permissions.permissions).has("procurement.receipt.create");

  return (
    <>
      <PageHeader
        breadcrumb={[{ label: t("procurement") }, { label: t("goods_receipts") }]}
        title={t("goods_receipts")}
        actions={
          canCreate ? (
            <Link href="/procurement/receipts/create" className={cn(buttonVariants({ size: "sm" }))}>
              <Plus /> {t("new_receipt")}
            </Link>
          ) : null
        }
      />

      <ReceiptsTable rows={receipts.data} meta={receipts.meta} page={page} search={search} status={status} />
    </>
  );
}
