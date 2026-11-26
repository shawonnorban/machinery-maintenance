import { apiFetch } from "@/lib/api-server";
import { ScreenHeader } from "@/components/layout/screen-header";
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
      <ScreenHeader
        parentUrl="/vendors/warranties"
        title={t("new_warranty")}
      />

      <WarrantyForm assets={assets} vendors={vendors} assetId={params.asset_id} action={createWarranty} />
    </>
  );
}
