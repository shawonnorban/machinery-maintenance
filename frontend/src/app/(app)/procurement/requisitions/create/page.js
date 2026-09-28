import { apiFetch } from "@/lib/api-server";
import { PageHeader } from "@/components/layout/page-header";
import { RequisitionForm } from "@/components/procurement/requisition-form";
import { searchItems } from "@/components/procurement/search-items-action";
import { getT } from "@/lib/i18n-server";
import { createRequisition } from "./actions";

export default async function CreatePurchaseRequisitionPage() {
  const [options, t] = await Promise.all([apiFetch("/purchase-requisitions/form-options"), getT("procurement")]);

  return (
    <>
      <PageHeader
        breadcrumb={[{ label: t("procurement") }, { label: t("purchase_requisitions"), href: "/procurement/requisitions" }, { label: t("new_requisition") }]}
        title={t("new_requisition")}
      />

      <RequisitionForm options={options} searchItems={searchItems} action={createRequisition} />
    </>
  );
}
