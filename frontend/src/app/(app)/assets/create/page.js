import { apiFetch } from "@/lib/api-server";
import { PageHeader } from "@/components/layout/page-header";
import { Card, CardBody } from "@/components/ui/card";
import { AssetForm } from "@/components/assets/asset-form";
import { createAsset } from "./actions";

export default async function CreateAssetPage() {
  const options = await apiFetch("/assets/form-options");

  return (
    <>
      <PageHeader breadcrumb={[{ label: "Assets", href: "/assets" }, { label: "New" }]} title="New asset" />

      <Card>
        <CardBody>
          <AssetForm options={options} action={createAsset} />
        </CardBody>
      </Card>
    </>
  );
}
