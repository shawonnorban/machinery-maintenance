import Link from "next/link";
import { Plus } from "lucide-react";
import { apiFetch } from "@/lib/api-server";
import { PageHeader } from "@/components/layout/page-header";
import { buttonVariants } from "@/components/ui/button";
import { cn } from "@/lib/utils";
import { OrdersTable } from "@/components/production/orders-table";
import { getT } from "@/lib/i18n-server";

/** Mirrors `ProductionOrderApiController::index` (Master Data spec §6). */
export default async function ProductionOrdersPage({ searchParams }) {
  const params = await searchParams;
  const page = Number(params.page ?? 1);
  const search = params.q ?? "";
  const status = params.status ?? "";

  const query = new URLSearchParams({ page: String(page) });
  if (search) query.set("search", search);
  if (status) query.set("status", status);

  const [orders, permissions, t] = await Promise.all([
    apiFetch(`/production-orders?${query.toString()}`, { includeMeta: true }),
    apiFetch("/auth/permissions"),
    getT("production"),
  ]);

  const canManage = new Set(permissions.permissions).has("production.order.manage");

  return (
    <>
      <PageHeader
        breadcrumb={[{ label: t("production") }, { label: t("orders") }]}
        title={t("orders")}
        description={t("minimal_master_hint")}
        actions={
          canManage ? (
            <Link href="/production/orders/create" className={cn(buttonVariants({ size: "sm" }))}>
              <Plus /> {t("new_order")}
            </Link>
          ) : null
        }
      />

      <OrdersTable rows={orders.data} meta={orders.meta} page={page} search={search} status={status} />
    </>
  );
}
