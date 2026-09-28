import { redirect } from "next/navigation";
import { apiFetch } from "@/lib/api-server";
import { PageHeader } from "@/components/layout/page-header";
import { OrderForm } from "@/components/procurement/order-form";
import { searchItems } from "@/components/procurement/search-items-action";
import { getT } from "@/lib/i18n-server";
import { updateOrder } from "./actions";

export default async function EditPurchaseOrderPage({ params }) {
  const { orderId } = await params;

  const [order, options, t] = await Promise.all([
    apiFetch(`/purchase-orders/${orderId}`),
    apiFetch("/purchase-orders/form-options"),
    getT("procurement"),
  ]);

  if (!order.can_edit) {
    redirect(`/procurement/orders/${orderId}`);
  }

  return (
    <>
      <PageHeader
        breadcrumb={[
          { label: t("procurement") },
          { label: t("purchase_orders"), href: "/procurement/orders" },
          { label: order.po_no, href: `/procurement/orders/${orderId}` },
          { label: t("edit_order") },
        ]}
        title={`${t("edit_order")} — ${order.po_no}`}
      />

      <OrderForm order={order} options={options} searchItems={searchItems} action={updateOrder.bind(null, orderId)} />
    </>
  );
}
