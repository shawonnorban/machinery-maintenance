import { apiFetch } from "@/lib/api-server";
import { ScreenHeader } from "@/components/layout/screen-header";
import { VendorForm } from "@/components/vendors/vendor-form";
import { getT } from "@/lib/i18n-server";
import { updateVendor } from "./actions";

export default async function EditVendorPage({ params }) {
  const { vendorId } = await params;
  const [vendor, options, t] = await Promise.all([
    apiFetch(`/vendors/${vendorId}`),
    apiFetch("/vendors/form-options"),
    getT("vendor"),
  ]);

  return (
    <>
      <ScreenHeader parentUrl="/vendors" trail={[{ label: vendor.name, href: `/vendors/${vendorId}` }]} title={t("edit_vendor_title", { name: vendor.name })} />

      <VendorForm vendor={vendor} options={options} action={updateVendor.bind(null, vendorId)} />
    </>
  );
}
