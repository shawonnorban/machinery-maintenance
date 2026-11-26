import { apiFetch } from "@/lib/api-server";
import { ScreenHeader } from "@/components/layout/screen-header";
import { VendorForm } from "@/components/vendors/vendor-form";
import { getT } from "@/lib/i18n-server";
import { createVendor } from "./actions";

export default async function CreateVendorPage() {
  const [options, t, tc] = await Promise.all([apiFetch("/vendors/form-options"), getT("vendor"), getT("common")]);

  return (
    <>
      <ScreenHeader parentUrl="/vendors" title={t("new_vendor")} />

      <VendorForm options={options} action={createVendor} />
    </>
  );
}
