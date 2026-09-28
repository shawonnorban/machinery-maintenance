import { apiFetch } from "@/lib/api-server";
import { PageHeader } from "@/components/layout/page-header";
import { Card, CardBody } from "@/components/ui/card";
import { AssetForm } from "@/components/assets/asset-form";
import { getT } from "@/lib/i18n-server";
import { createAsset } from "./actions";

export default async function CreateAssetPage() {
  const [options, customFields, t, tc] = await Promise.all([
    apiFetch("/assets/form-options"),
    // No asset exists yet — `fieldable_id` is only used to look up already-
    // saved values, so a placeholder is fine, it just finds none.
    apiFetch("/custom-fields/values?fieldable_type=asset&fieldable_id=new"),
    getT("asset"),
    getT("common"),
  ]);

  return (
    <>
      <PageHeader breadcrumb={[{ label: t("assets"), href: "/assets" }, { label: tc("new") }]} title={t("new_asset")} />

      <Card>
        <CardBody>
          <AssetForm options={options} customFieldSchema={customFields.schema} action={createAsset} />
        </CardBody>
      </Card>
    </>
  );
}
