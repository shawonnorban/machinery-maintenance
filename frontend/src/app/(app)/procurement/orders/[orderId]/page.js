import Link from "next/link";
import { ArrowLeft, Pencil } from "lucide-react";
import { apiFetch } from "@/lib/api-server";
import { PageHeader } from "@/components/layout/page-header";
import { buttonVariants } from "@/components/ui/button";
import { StatusBadge } from "@/components/ui/status-badge";
import { cn } from "@/lib/utils";
import { OrderDetail } from "@/components/procurement/order-detail";
import { getT } from "@/lib/i18n-server";
import { submitOrder, cancelOrder, closeOrder, approveOrder, rejectOrder } from "./actions";

/** Mirrors `PurchaseOrderApiController::show` (Procurement spec §7). */
export default async function PurchaseOrderPage({ params }) {
  const { orderId } = await params;

  const [order, t, tc] = await Promise.all([apiFetch(`/purchase-orders/${orderId}`), getT("procurement"), getT("common")]);

  return (
    <>
      <PageHeader
        breadcrumb={[{ label: t("procurement") }, { label: t("purchase_orders"), href: "/procurement/orders" }, { label: order.po_no }]}
        title={
          <span className="flex flex-wrap items-center gap-2">
            {order.po_no}
            {order.revision_no > 0 ? <span className="text-base font-normal text-foreground-muted">{t("revision_no", { number: order.revision_no })}</span> : null}
            <StatusBadge status={order.status} label={t(`statuses.${order.status}`)} />
          </span>
        }
        description={`${order.vendor?.name ?? ""} · ${order.factory?.name ?? ""}`}
        actions={
          <div className="flex flex-wrap items-center gap-2">
            {order.can_edit ? (
              <Link href={`/procurement/orders/${orderId}/edit`} className={cn(buttonVariants({ variant: "outline", size: "sm" }))}>
                <Pencil /> {tc("edit")}
              </Link>
            ) : null}
            <Link href="/procurement/orders" className={cn(buttonVariants({ variant: "outline", size: "sm" }))}>
              <ArrowLeft /> {tc("back")}
            </Link>
          </div>
        }
      />

      <OrderDetail
        order={order}
        actions={{
          submit: submitOrder.bind(null, orderId),
          cancel: cancelOrder.bind(null, orderId),
          close: closeOrder.bind(null, orderId),
          approve: approveOrder.bind(null, orderId),
          reject: rejectOrder.bind(null, orderId),
        }}
      />
    </>
  );
}
