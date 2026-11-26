import { UserPlus } from "lucide-react";
import { PlatformHeader } from "@/components/platform/ui";
import { TenantCreateForm } from "@/components/platform/tenant-create-form";
import { createTenant } from "./actions";

export default function NewTenantPage() {
  return (
    <>
      <PlatformHeader
        breadcrumb={[{ label: "Customers", href: "/platform" }, { label: "New customer" }]}
        title="New customer"
        description="Onboard a company, its first factory and an owner account."
        icon={<UserPlus />}
        tone="success"
      />
      <TenantCreateForm action={createTenant} />
    </>
  );
}
