import { redirect } from "next/navigation";
import { apiFetch } from "@/lib/api-server";
import { PageHeader } from "@/components/layout/page-header";
import { Alert } from "@/components/ui/alert";
import { OrderForm } from "@/components/procurement/order-form";
import { searchItems } from "@/components/procurement/search-items-action";
import { getT } from "@/lib/i18n-server";
import { reviseOrder } from "./actions";

/** Mirrors `ManagePurchaseOrder::revise` — the order as it stands is snapshotted before the change lands. */
export default async function RevisePurchaseOrderPage({ params }) {
  const { orderId } = await params;

  const [order, options, t] = await Promise.all([
    apiFetch(`/purchase-orders/${orderId}`),
    apiFetch("/purchase-orders/form-options"),
    getT("procurement"),
  ]);

  if (!order.can_revise) {
    redirect(`/procurement/orders/${orderId}`);
  }

  return (
    <>
      <PageHeader
        breadcrumb={[
          { label: t("procurement") },
          { label: t("purchase_orders"), href: "/procurement/orders" },
          { label: order.po_no, href: `/procurement/orders/${orderId}` },
          { label: t("revise") },
        ]}
        title={`${t("revise_order")} — ${order.po_no}`}
        description={t("revision_no", { number: order.revision_no + 1 })}
      />

      <div className="mb-4">
        <Alert variant="info">{t("revise_hint")}</Alert>
      </div>

      <OrderForm order={order} options={options} searchItems={searchItems} action={reviseOrder.bind(null, orderId)} revision />
    </>
  );
}
