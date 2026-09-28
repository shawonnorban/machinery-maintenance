import Link from "next/link";
import { Plus } from "lucide-react";
import { apiFetch } from "@/lib/api-server";
import { PageHeader } from "@/components/layout/page-header";
import { buttonVariants } from "@/components/ui/button";
import { cn } from "@/lib/utils";
import { OrdersTable } from "@/components/procurement/orders-table";
import { getT } from "@/lib/i18n-server";

/** Mirrors `PurchaseOrderApiController::index` (Procurement spec §7). */
export default async function PurchaseOrdersPage({ searchParams }) {
  const params = await searchParams;
  const page = Number(params.page ?? 1);
  const search = params.q ?? "";
  const status = params.status ?? "";
  const vendorId = params.vendor_id ?? "";

  const query = new URLSearchParams({ page: String(page) });
  if (search) query.set("search", search);
  if (status) query.set("status", status);
  // A vendor's page links here to see everything raised against them.
  if (vendorId) query.set("vendor_id", vendorId);

  const [orders, permissions, t] = await Promise.all([
    apiFetch(`/purchase-orders?${query.toString()}`, { includeMeta: true }),
    apiFetch("/auth/permissions"),
    getT("procurement"),
  ]);

  const canCreate = new Set(permissions.permissions).has("procurement.order.create");

  return (
    <>
      <PageHeader
        breadcrumb={[{ label: t("procurement") }, { label: t("purchase_orders") }]}
        title={t("purchase_orders")}
        actions={
          canCreate ? (
            <Link href="/procurement/orders/create" className={cn(buttonVariants({ size: "sm" }))}>
              <Plus /> {t("new_order")}
            </Link>
          ) : null
        }
      />

      <OrdersTable rows={orders.data} meta={orders.meta} page={page} search={search} status={status} />
    </>
  );
}
