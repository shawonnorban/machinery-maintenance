import { apiFetch } from "@/lib/api-server";
import { PageHeader } from "@/components/layout/page-header";
import { Card, CardBody } from "@/components/ui/card";
import { WarrantyForm } from "@/components/vendor/warranty-form";
import { createWarranty } from "./actions";

export default async function CreateWarrantyPage({ searchParams }) {
  const params = await searchParams;

  const [assets, vendors] = await Promise.all([
    apiFetch("/assets?per_page=200"),
    apiFetch("/vendors?per_page=100"),
  ]);

  return (
    <>
      <PageHeader
        breadcrumb={[{ label: "Vendors" }, { label: "Warranties", href: "/vendors/warranties" }, { label: "Record warranty" }]}
        title="Record warranty"
      />

      <Card>
        <CardBody>
          <WarrantyForm assets={assets} vendors={vendors} assetId={params.asset_id} action={createWarranty} />
        </CardBody>
      </Card>
    </>
  );
}
