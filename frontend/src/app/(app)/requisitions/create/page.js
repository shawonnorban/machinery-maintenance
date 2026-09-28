import { apiFetch } from "@/lib/api-server";
import { PageHeader } from "@/components/layout/page-header";
import { FloorRequisitionForm } from "@/components/requisition/floor-requisition-form";
import { searchItems } from "@/components/procurement/search-items-action";
import { getT } from "@/lib/i18n-server";
import { createFloorRequisition } from "./actions";

export default async function CreateFloorRequisitionPage() {
  const [options, t] = await Promise.all([apiFetch("/floor-requisitions/form-options"), getT("requisition")]);

  return (
    <>
      <PageHeader
        breadcrumb={[{ label: t("floor_requisitions"), href: "/requisitions" }, { label: t("new_requisition") }]}
        title={t("new_requisition")}
      />

      <FloorRequisitionForm options={options} searchItems={searchItems} action={createFloorRequisition} />
    </>
  );
}
