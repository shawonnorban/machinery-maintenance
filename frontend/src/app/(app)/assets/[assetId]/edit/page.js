import { apiFetch } from "@/lib/api-server";
import { PageHeader } from "@/components/layout/page-header";
import { Card, CardBody } from "@/components/ui/card";
import { AssetForm } from "@/components/assets/asset-form";
import { getT } from "@/lib/i18n-server";
import { updateAsset } from "./actions";

export default async function EditAssetPage({ params }) {
  const { assetId } = await params;

  const [asset, options, customFields, t, tc] = await Promise.all([
    apiFetch(`/assets/${assetId}`),
    apiFetch("/assets/form-options"),
    apiFetch(`/custom-fields/values?fieldable_type=asset&fieldable_id=${assetId}`),
    getT("asset"),
    getT("common"),
  ]);

  return (
    <>
      <PageHeader
        breadcrumb={[{ label: t("assets"), href: "/assets" }, { label: asset.asset_code, href: `/assets/${assetId}` }, { label: tc("edit") }]}
        title={t("edit_asset_title", { code: asset.asset_code })}
      />

      <Card>
        <CardBody>
          <AssetForm
            asset={asset}
            options={options}
            customFieldSchema={customFields.schema}
            customFieldValues={customFields.values}
            action={updateAsset.bind(null, assetId)}
          />
        </CardBody>
      </Card>
    </>
  );
}
