import { apiFetch } from "@/lib/api-server";
import { PageHeader } from "@/components/layout/page-header";
import { BondLicenceForm } from "@/components/compliance/bond-licence-form";
import { searchItems } from "@/components/procurement/search-items-action";
import { getT } from "@/lib/i18n-server";
import { createBondLicence } from "./actions";

/** Mirrors `BondComplianceApiController::formOptions` (Shared Systems spec §3.1). */
export default async function CreateBondLicencePage() {
  // The item departments come from the requisition form's options, which
  // is where the shared item picker is already fed from.
  const [options, itemOptions, t] = await Promise.all([
    apiFetch("/bond-licences/form-options"),
    apiFetch("/purchase-requisitions/form-options"),
    getT("compliance"),
  ]);

  return (
    <>
      <PageHeader
        breadcrumb={[
          { label: t("compliance") },
          { label: t("bond_licences"), href: "/compliance/bond-licences" },
          { label: t("new_licence") },
        ]}
        title={t("new_licence")}
        description={t("licence_hint")}
      />

      <BondLicenceForm
        factories={options.factories ?? []}
        bases={options.bases ?? []}
        departments={itemOptions.item_departments ?? []}
        searchItems={searchItems}
        onSubmit={createBondLicence}
      />
    </>
  );
}
