import { apiFetch } from "@/lib/api-server";
import { PageHeader } from "@/components/layout/page-header";
import { OrderForm } from "@/components/procurement/order-form";
import { searchItems } from "@/components/procurement/search-items-action";
import { getT } from "@/lib/i18n-server";
import { createOrder } from "./actions";

export default async function CreatePurchaseOrderPage() {
  const [options, t] = await Promise.all([apiFetch("/purchase-orders/form-options"), getT("procurement")]);

  return (
    <>
      <PageHeader
        breadcrumb={[{ label: t("procurement") }, { label: t("purchase_orders"), href: "/procurement/orders" }, { label: t("new_order") }]}
        title={t("new_order")}
      />

      <OrderForm options={options} searchItems={searchItems} action={createOrder} />
    </>
  );
}
