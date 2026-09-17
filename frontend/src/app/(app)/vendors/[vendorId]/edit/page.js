import { apiFetch } from "@/lib/api-server";
import { PageHeader } from "@/components/layout/page-header";
import { Card, CardBody } from "@/components/ui/card";
import { VendorForm } from "@/components/vendors/vendor-form";
import { getT } from "@/lib/i18n-server";
import { updateVendor } from "./actions";

export default async function EditVendorPage({ params }) {
  const { vendorId } = await params;
  const [vendor, t] = await Promise.all([
    apiFetch(`/vendors/${vendorId}`),
    getT("vendor"),
  ]);

  return (
    <>
      <PageHeader breadcrumb={[{ label: t("vendors"), href: "/vendors" }, { label: vendor.name }]} title={t("edit_vendor_title", { name: vendor.name })} />

      <Card>
        <CardBody>
          <VendorForm vendor={vendor} action={updateVendor.bind(null, vendorId)} />
        </CardBody>
      </Card>
    </>
  );
}
