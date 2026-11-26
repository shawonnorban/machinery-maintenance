import Link from "next/link";
import { Plus, Building2, CheckCircle2, Ban, Archive } from "lucide-react";
import { platformApiFetch } from "@/lib/platform-api-server";
import { buttonVariants } from "@/components/ui/button";
import { cn } from "@/lib/utils";
import { PlatformHeader, Kpi } from "@/components/platform/ui";
import { TenantsTable } from "@/components/platform/tenants-table";
import { ClosedTenantsList } from "@/components/platform/closed-tenants-list";
import { restoreTenant } from "./actions";

/** Every customer, seen from the platform (SRS 3.1, 5, 40). */
export default async function PlatformTenantsPage() {
  const [tenants, closed] = await Promise.all([platformApiFetch("/tenants"), platformApiFetch("/tenants?status=closed")]);

  const active = tenants.filter((t) => t.status === "ACTIVE").length;
  const suspended = tenants.filter((t) => t.status === "SUSPENDED").length;
  const users = tenants.reduce((a, t) => a + (t.users ?? 0), 0);

  return (
    <>
      <PlatformHeader
        breadcrumb={[{ label: "Customers" }]}
        title="Customers"
        description="Every company on the platform, the contract behind each one, and what it uses."
        icon={<Building2 />}
        actions={
          <Link href="/platform/tenants/new" className={cn(buttonVariants())}>
            <Plus /> New customer
          </Link>
        }
      />

      <div className="mb-4 grid grid-cols-2 gap-3 xl:grid-cols-5">
        <Kpi label="Customers" value={tenants.length} icon={<Building2 />} tone="brand" />
        <Kpi label="Active" value={active} icon={<CheckCircle2 />} tone="success" />
        <Kpi label="Suspended" value={suspended} icon={<Ban />} tone="danger" />
        <Kpi label="Closed" value={closed.length} icon={<Archive />} tone="warning" />
        <Kpi label="Users" value={users.toLocaleString("en-US")} tone="info" sub="across every customer" />
      </div>

      <TenantsTable tenants={tenants} />

      <ClosedTenantsList closed={closed} restoreAction={restoreTenant} />
    </>
  );
}
