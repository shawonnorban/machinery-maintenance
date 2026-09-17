import { apiFetch } from "@/lib/api-server";
import { PageHeader } from "@/components/layout/page-header";
import { Card, CardBody } from "@/components/ui/card";
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
      <PageHeader
        breadcrumb={[
          { label: t("vendors") },
          { label: t("contracts"), href: "/vendors/service-contracts" },
          { label: t("new_contract") },
        ]}
        title={t("new_contract")}
      />

      <Card>
        <CardBody>
          <ContractForm vendors={vendors} assets={assets} factories={factories} action={createContract} />
        </CardBody>
      </Card>
    </>
  );
}
