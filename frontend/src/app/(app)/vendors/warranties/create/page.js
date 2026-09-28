import { apiFetch } from "@/lib/api-server";
import { PageHeader } from "@/components/layout/page-header";
import { Card, CardBody } from "@/components/ui/card";
import { WarrantyForm } from "@/components/vendor/warranty-form";
import { getT } from "@/lib/i18n-server";
import { createWarranty } from "./actions";

export default async function CreateWarrantyPage({ searchParams }) {
  const params = await searchParams;

  const [assets, vendors, t] = await Promise.all([
    apiFetch("/assets?per_page=200"),
    apiFetch("/vendors?per_page=100"),
    getT("vendor"),
  ]);

  return (
    <>
      <PageHeader
        breadcrumb={[{ label: t("vendors") }, { label: t("warranties"), href: "/vendors/warranties" }, { label: t("new_warranty") }]}
        title={t("new_warranty")}
      />

      <Card>
        <CardBody>
          <WarrantyForm assets={assets} vendors={vendors} assetId={params.asset_id} action={createWarranty} />
        </CardBody>
      </Card>
    </>
  );
}
