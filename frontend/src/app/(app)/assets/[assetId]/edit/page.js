import { apiFetch } from "@/lib/api-server";
import { PageHeader } from "@/components/layout/page-header";
import { Card, CardBody } from "@/components/ui/card";
import { AssetForm } from "@/components/assets/asset-form";
import { updateAsset } from "./actions";

export default async function EditAssetPage({ params }) {
  const { assetId } = await params;

  const [asset, options] = await Promise.all([
    apiFetch(`/assets/${assetId}`),
    apiFetch("/assets/form-options"),
  ]);

  return (
    <>
      <PageHeader breadcrumb={[{ label: "Assets", href: "/assets" }, { label: asset.asset_code, href: `/assets/${assetId}` }, { label: "Edit" }]} title={`Edit ${asset.asset_code}`} />

      <Card>
        <CardBody>
          <AssetForm asset={asset} options={options} action={updateAsset.bind(null, assetId)} />
        </CardBody>
      </Card>
    </>
  );
}
