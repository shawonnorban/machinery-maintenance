import { PageHeader } from "@/components/layout/page-header";
import { TenantCreateForm } from "@/components/platform/tenant-create-form";
import { createTenant } from "./actions";

export default function NewTenantPage() {
  return (
    <>
      <PageHeader
        breadcrumb={[{ label: "Customers", href: "/platform" }, { label: "New" }]}
        title="New customer"
        description="Onboard a company, its first factory, and an owner account."
      />
      <TenantCreateForm action={createTenant} />
    </>
  );
}
