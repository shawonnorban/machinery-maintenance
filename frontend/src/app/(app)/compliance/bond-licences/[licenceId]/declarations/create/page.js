import { apiFetch } from "@/lib/api-server";
import { PageHeader } from "@/components/layout/page-header";
import { DeclarationForm } from "@/components/compliance/declaration-form";
import { searchItems } from "@/components/procurement/search-items-action";
import { getT } from "@/lib/i18n-server";
import { createDeclaration } from "./actions";

/** Mirrors `BondComplianceApiController::storeDeclaration` (Shared Systems spec §3.2). */
export default async function CreateDeclarationPage({ params }) {
  const { licenceId } = await params;

  const [licence, options, itemOptions, t] = await Promise.all([
    apiFetch(`/bond-licences/${licenceId}`),
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
          { label: licence.licence_no, href: `/compliance/bond-licences/${licenceId}` },
          { label: t("new_declaration") },
        ]}
        title={t("new_declaration")}
        description={t("declaration_hint")}
      />

      <DeclarationForm
        licence={licence}
        udTypes={options.ud_types ?? []}
        departments={itemOptions.item_departments ?? []}
        searchItems={searchItems}
        onSubmit={createDeclaration.bind(null, licenceId)}
      />
    </>
  );
}
