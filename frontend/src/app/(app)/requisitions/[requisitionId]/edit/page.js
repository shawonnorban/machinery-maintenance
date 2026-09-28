import { redirect } from "next/navigation";
import { apiFetch } from "@/lib/api-server";
import { PageHeader } from "@/components/layout/page-header";
import { FloorRequisitionForm } from "@/components/requisition/floor-requisition-form";
import { searchItems } from "@/components/procurement/search-items-action";
import { getT } from "@/lib/i18n-server";
import { updateFloorRequisition } from "./actions";

export default async function EditFloorRequisitionPage({ params }) {
  const { requisitionId } = await params;

  const [requisition, options, t] = await Promise.all([
    apiFetch(`/floor-requisitions/${requisitionId}`),
    apiFetch("/floor-requisitions/form-options"),
    getT("requisition"),
  ]);

  if (!requisition.can_edit) {
    redirect(`/requisitions/${requisitionId}`);
  }

  return (
    <>
      <PageHeader
        breadcrumb={[
          { label: t("floor_requisitions"), href: "/requisitions" },
          { label: requisition.fr_no, href: `/requisitions/${requisitionId}` },
          { label: t("edit_requisition") },
        ]}
        title={`${t("edit_requisition")} — ${requisition.fr_no}`}
      />

      <FloorRequisitionForm requisition={requisition} options={options} searchItems={searchItems} action={updateFloorRequisition.bind(null, requisitionId)} />
    </>
  );
}
