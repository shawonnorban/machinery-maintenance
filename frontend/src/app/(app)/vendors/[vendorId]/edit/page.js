import { apiFetch } from "@/lib/api-server";
import { PageHeader } from "@/components/layout/page-header";
import { Card, CardBody } from "@/components/ui/card";
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
      <PageHeader breadcrumb={[{ label: t("vendors"), href: "/vendors" }, { label: vendor.name, href: `/vendors/${vendorId}` }, { label: t("edit_vendor") }]} title={t("edit_vendor_title", { name: vendor.name })} />

      <Card>
        <CardBody>
          <VendorForm vendor={vendor} options={options} action={updateVendor.bind(null, vendorId)} />
        </CardBody>
      </Card>
    </>
  );
}
