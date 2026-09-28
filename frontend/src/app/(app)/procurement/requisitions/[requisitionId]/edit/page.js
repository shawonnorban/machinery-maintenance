import { redirect } from "next/navigation";
import { apiFetch } from "@/lib/api-server";
import { PageHeader } from "@/components/layout/page-header";
import { RequisitionForm } from "@/components/procurement/requisition-form";
import { searchItems } from "@/components/procurement/search-items-action";
import { getT } from "@/lib/i18n-server";
import { updateRequisition } from "./actions";

export default async function EditPurchaseRequisitionPage({ params }) {
  const { requisitionId } = await params;

  const [requisition, options, t] = await Promise.all([
    apiFetch(`/purchase-requisitions/${requisitionId}`),
    apiFetch("/purchase-requisitions/form-options"),
    getT("procurement"),
  ]);

  // Only a draft is editable (ManagePurchaseRequisition::update).
  if (!requisition.can_edit) {
    redirect(`/procurement/requisitions/${requisitionId}`);
  }

  return (
    <>
      <PageHeader
        breadcrumb={[
          { label: t("procurement") },
          { label: t("purchase_requisitions"), href: "/procurement/requisitions" },
          { label: requisition.pr_no, href: `/procurement/requisitions/${requisitionId}` },
          { label: t("edit_requisition") },
        ]}
        title={`${t("edit_requisition")} — ${requisition.pr_no}`}
      />

      <RequisitionForm requisition={requisition} options={options} searchItems={searchItems} action={updateRequisition.bind(null, requisitionId)} />
    </>
  );
}
