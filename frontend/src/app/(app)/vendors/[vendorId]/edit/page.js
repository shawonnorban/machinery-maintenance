import { apiFetch } from "@/lib/api-server";
import { PageHeader } from "@/components/layout/page-header";
import { Card, CardBody } from "@/components/ui/card";
import { VendorForm } from "@/components/vendors/vendor-form";
import { updateVendor } from "./actions";

export default async function EditVendorPage({ params }) {
  const { vendorId } = await params;
  const vendor = await apiFetch(`/vendors/${vendorId}`);

  return (
    <>
      <PageHeader breadcrumb={[{ label: "Vendors", href: "/vendors" }, { label: vendor.name }]} title={`Edit ${vendor.name}`} />

      <Card>
        <CardBody>
          <VendorForm vendor={vendor} action={updateVendor.bind(null, vendorId)} />
        </CardBody>
      </Card>
    </>
  );
}
