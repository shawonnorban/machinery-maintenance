import Link from "next/link";
import { ArrowLeft } from "lucide-react";
import { apiFetch } from "@/lib/api-server";
import { PageHeader } from "@/components/layout/page-header";
import { buttonVariants } from "@/components/ui/button";
import { StatusBadge } from "@/components/ui/status-badge";
import { cn } from "@/lib/utils";
import { OrderDetail } from "@/components/production/order-detail";
import { searchItems } from "@/components/procurement/search-items-action";
import { getT } from "@/lib/i18n-server";
import { addStyle, saveStyleBom, addCuttingProgram, setOrderStatus } from "./actions";

/** Mirrors `ProductionOrderApiController::show` (Master Data spec §6). */
export default async function ProductionOrderPage({ params }) {
  const { orderId } = await params;

  const [order, options, t] = await Promise.all([
    apiFetch(`/production-orders/${orderId}`),
    // The bill of materials picks from every item department — a style
    // consumes fabric, trims and chemicals alike.
    apiFetch("/floor-requisitions/form-options"),
    getT("production"),
  ]);

  const tc = await getT("common");

  return (
    <>
      <PageHeader
        breadcrumb={[
          { label: t("production") },
          { label: t("orders"), href: "/production/orders" },
          { label: order.order_no },
        ]}
        title={
          <span className="flex flex-wrap items-center gap-2">
            {order.order_no}
            <StatusBadge status={order.status} label={t(`statuses.${order.status}`)} />
          </span>
        }
        description={`${order.buyer} · ${order.style_count ?? 0} ${t("styles")}`}
        actions={
          <Link href="/production/orders" className={cn(buttonVariants({ variant: "outline", size: "sm" }))}>
            <ArrowLeft /> {tc("back")}
          </Link>
        }
      />

      <OrderDetail
        order={order}
        departments={options.item_departments}
        searchItems={searchItems}
        actions={{
          addStyle: addStyle.bind(null, orderId),
          saveBom: saveStyleBom.bind(null, orderId),
          addProgram: addCuttingProgram.bind(null, orderId),
          setStatus: setOrderStatus.bind(null, orderId),
        }}
      />
    </>
  );
}
