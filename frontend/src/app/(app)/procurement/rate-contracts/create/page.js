import { apiFetch } from "@/lib/api-server";
import { PageHeader } from "@/components/layout/page-header";
import { RateContractForm } from "@/components/procurement/rate-contract-form";
import { searchItems } from "@/components/procurement/search-items-action";
import { getT } from "@/lib/i18n-server";
import { createRateContract } from "./actions";

/** Mirrors `RateContractApiController::formOptions` (Procurement spec §14). */
export default async function CreateRateContractPage() {
  // The item departments come from the requisition form's own options,
  // which is where the picker's department filter is already fed from.
  const [options, itemOptions, t] = await Promise.all([
    apiFetch("/rate-contracts/form-options"),
    apiFetch("/purchase-requisitions/form-options"),
    getT("procurement"),
  ]);

  return (
    <>
      <PageHeader
        breadcrumb={[
          { label: t("procurement") },
          { label: t("rate_contracts"), href: "/procurement/rate-contracts" },
          { label: t("new_contract") },
        ]}
        title={t("new_contract")}
        description={t("contract_hint")}
      />

      <RateContractForm
        factories={options.factories ?? []}
        vendors={options.vendors ?? []}
        departments={itemOptions.item_departments ?? []}
        searchItems={searchItems}
        onSubmit={createRateContract}
      />
    </>
  );
}
