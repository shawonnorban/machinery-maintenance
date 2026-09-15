import { PageHeader } from "@/components/layout/page-header";
import { Card, CardBody } from "@/components/ui/card";
import { VendorForm } from "@/components/vendors/vendor-form";
import { createVendor } from "./actions";

export default function CreateVendorPage() {
  return (
    <>
      <PageHeader breadcrumb={[{ label: "Vendors", href: "/vendors" }, { label: "New" }]} title="New vendor" />

      <Card>
        <CardBody>
          <VendorForm action={createVendor} />
        </CardBody>
      </Card>
    </>
  );
}
