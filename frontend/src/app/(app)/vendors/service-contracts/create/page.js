import { apiFetch } from "@/lib/api-server";
import { PageHeader } from "@/components/layout/page-header";
import { Card, CardBody } from "@/components/ui/card";
import { ContractForm } from "@/components/vendor/contract-form";
import { createContract } from "./actions";

export default async function CreateServiceContractPage() {
  const [vendors, assets, factories] = await Promise.all([
    apiFetch("/vendors?per_page=100"),
    apiFetch("/assets?per_page=200"),
    apiFetch("/factories?per_page=100"),
  ]);

  return (
    <>
      <PageHeader
        breadcrumb={[
          { label: "Vendors" },
          { label: "Service contracts", href: "/vendors/service-contracts" },
          { label: "New contract" },
        ]}
        title="New contract"
      />

      <Card>
        <CardBody>
          <ContractForm vendors={vendors} assets={assets} factories={factories} action={createContract} />
        </CardBody>
      </Card>
    </>
  );
}
