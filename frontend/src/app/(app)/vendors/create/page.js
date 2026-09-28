import { apiFetch } from "@/lib/api-server";
import { PageHeader } from "@/components/layout/page-header";
import { Card, CardBody } from "@/components/ui/card";
import { VendorForm } from "@/components/vendors/vendor-form";
import { getT } from "@/lib/i18n-server";
import { createVendor } from "./actions";

export default async function CreateVendorPage() {
  const [options, t, tc] = await Promise.all([apiFetch("/vendors/form-options"), getT("vendor"), getT("common")]);

  return (
    <>
      <PageHeader breadcrumb={[{ label: t("vendors"), href: "/vendors" }, { label: tc("new") }]} title={t("new_vendor")} />

      <Card>
        <CardBody>
          <VendorForm options={options} action={createVendor} />
        </CardBody>
      </Card>
    </>
  );
}
