import { apiFetch } from "@/lib/api-server";
import { ScreenHeader } from "@/components/layout/screen-header";
import { ContractForm } from "@/components/vendor/contract-form";
import { getT } from "@/lib/i18n-server";
import { createContract } from "./actions";

export default async function CreateServiceContractPage() {
  const [vendors, assets, factories, t] = await Promise.all([
    apiFetch("/vendors?per_page=100"),
    apiFetch("/assets?per_page=200"),
    apiFetch("/factories?per_page=100"),
    getT("vendor"),
  ]);

  return (
    <>
      <ScreenHeader
        parentUrl="/vendors/service-contracts"
        title={t("new_contract")}
      />

      <ContractForm vendors={vendors} assets={assets} factories={factories} action={createContract} />
    </>
  );
}
