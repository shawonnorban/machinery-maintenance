import { redirect } from "next/navigation";
import { apiFetch } from "@/lib/api-server";
import { PageHeader } from "@/components/layout/page-header";
import { ReceiptForm } from "@/components/procurement/receipt-form";
import { searchItems } from "@/components/procurement/search-items-action";
import { getT } from "@/lib/i18n-server";
import { createReceipt } from "@/app/(app)/procurement/receipts/create/actions";

/** Mirrors `ManageGoodsReceipt::createFromOrder` — the order's outstanding lines, pre-filled. */
export default async function ReceiveAgainstOrderPage({ params }) {
  const { orderId } = await params;

  const [receivable, options, t] = await Promise.all([
    apiFetch(`/purchase-orders/${orderId}/receivable`),
    apiFetch("/goods-receipts/form-options"),
    getT("procurement"),
  ]);

  if (!receivable.receivable) {
    redirect(`/procurement/orders/${orderId}`);
  }

  // The receivable payload's `order` is a summary; the form wants the
  // delivery store id to default the receiving store.
  const openOrder = options.open_orders.find((o) => o.id === orderId);
  receivable.order.delivery_store_id = openOrder?.delivery_store_id ?? null;

  return (
    <>
      <PageHeader
        breadcrumb={[
          { label: t("procurement") },
          { label: t("purchase_orders"), href: "/procurement/orders" },
          { label: receivable.order.po_no, href: `/procurement/orders/${orderId}` },
          { label: t("receive") },
        ]}
        title={t("receive_against", { number: receivable.order.po_no })}
      />

      <ReceiptForm receivable={receivable} options={options} searchItems={searchItems} action={createReceipt} />
    </>
  );
}
