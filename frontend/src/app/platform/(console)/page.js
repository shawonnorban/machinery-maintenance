import Link from "next/link";
import { Plus, Building2, CheckCircle2, Ban, Archive } from "lucide-react";
import { platformApiFetch } from "@/lib/platform-api-server";
import { PageHeader } from "@/components/layout/page-header";
import { StatCard } from "@/components/ui/stat-card";
import { buttonVariants } from "@/components/ui/button";
import { cn } from "@/lib/utils";
import { TenantsTable } from "@/components/platform/tenants-table";
import { ClosedTenantsList } from "@/components/platform/closed-tenants-list";
import { restoreTenant } from "./actions";

/** Mirrors `TenantController::index` — every customer, seen from the platform (SRS 3.1, 5, 40). */
export default async function PlatformTenantsPage() {
  const [tenants, closed] = await Promise.all([
    platformApiFetch("/tenants"),
    platformApiFetch("/tenants?status=closed"),
  ]);

  const active = tenants.filter((t) => t.status === "ACTIVE").length;
  const suspended = tenants.filter((t) => t.status === "SUSPENDED").length;

  return (
    <>
      <PageHeader
        breadcrumb={[{ label: "Customers" }]}
        title="Customers"
        description="Every company on the platform, and the contract behind each one."
        actions={
          <Link href="/platform/tenants/new" className={cn(buttonVariants({ size: "sm" }))}>
            <Plus /> New customer
          </Link>
        }
      />

      <div className="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <StatCard label="Total customers" value={tenants.length} icon={<Building2 />} tone="brand" />
        <StatCard label="Active" value={active} icon={<CheckCircle2 />} tone="success" />
        <StatCard label="Suspended" value={suspended} icon={<Ban />} tone="danger" />
        <StatCard label="Closed" value={closed.length} icon={<Archive />} tone="warning" />
      </div>

      <TenantsTable tenants={tenants} />

      <ClosedTenantsList closed={closed} restoreAction={restoreTenant} />
    </>
  );
}
