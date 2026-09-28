import { redirect } from "next/navigation";
import { apiFetch } from "@/lib/api-server";
import { PageHeader } from "@/components/layout/page-header";
import { OrderForm } from "@/components/procurement/order-form";
import { searchItems } from "@/components/procurement/search-items-action";
import { getT } from "@/lib/i18n-server";
import { createOrderFromRequisition } from "./actions";

/** Mirrors `PurchaseOrderApiController::storeFromRequisition` (Procurement spec §7). */
export default async function CreateOrderFromRequisitionPage({ params }) {
  const { requisitionId } = await params;

  const [requisition, options, t] = await Promise.all([
    apiFetch(`/purchase-requisitions/${requisitionId}`),
    apiFetch("/purchase-orders/form-options"),
    getT("procurement"),
  ]);

  if (!requisition.can_create_order) {
    redirect(`/procurement/requisitions/${requisitionId}`);
  }

  return (
    <>
      <PageHeader
        breadcrumb={[
          { label: t("procurement") },
          { label: t("purchase_requisitions"), href: "/procurement/requisitions" },
          { label: requisition.pr_no, href: `/procurement/requisitions/${requisitionId}` },
          { label: t("create_po") },
        ]}
        title={t("create_po_from_pr", { number: requisition.pr_no })}
      />

      <OrderForm requisition={requisition} options={options} searchItems={searchItems} action={createOrderFromRequisition.bind(null, requisitionId)} />
    </>
  );
}
