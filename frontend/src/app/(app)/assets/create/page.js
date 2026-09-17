import { apiFetch } from "@/lib/api-server";
import { PageHeader } from "@/components/layout/page-header";
import { Card, CardBody } from "@/components/ui/card";
import { AssetForm } from "@/components/assets/asset-form";
import { getT } from "@/lib/i18n-server";
import { createAsset } from "./actions";

export default async function CreateAssetPage() {
  const [options, t, tc] = await Promise.all([
    apiFetch("/assets/form-options"),
    getT("asset"),
    getT("common"),
  ]);

  return (
    <>
      <PageHeader breadcrumb={[{ label: t("assets"), href: "/assets" }, { label: tc("new") }]} title={t("new_asset")} />

      <Card>
        <CardBody>
          <AssetForm options={options} action={createAsset} />
        </CardBody>
      </Card>
    </>
  );
}
