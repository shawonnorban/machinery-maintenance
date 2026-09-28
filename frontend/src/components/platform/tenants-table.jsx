"use client";

import { useMemo, useState } from "react";
import { useRouter } from "next/navigation";
import Link from "next/link";
import { Search, Factory, Boxes, Users } from "lucide-react";
import { DataTable } from "@/components/ui/data-table";
import { Select } from "@/components/ui/select";
import { Input } from "@/components/ui/input";
import { StatusBadge } from "@/components/ui/status-badge";
import { Card, CardBody } from "@/components/ui/card";
import { formatCurrency } from "@/lib/format";
import { cn } from "@/lib/utils";

const AVATAR_TONES = [
  "bg-brand-subtle text-brand-hover",
  "bg-success-subtle text-success",
  "bg-warning-subtle text-warning",
  "bg-info-subtle text-info",
];

function avatarTone(name) {
  let hash = 0;
  for (let i = 0; i < name.length; i++) hash = (hash + name.charCodeAt(i)) % AVATAR_TONES.length;
  return AVATAR_TONES[hash];
}

/**
 * `PlatformTenantApiController::index` returns every tenant in one call —
 * modest volume for a B2B console, so search and status filtering happen
 * client-side over the full list rather than round-tripping the API per
 * keystroke the way `AssetsTable`'s much larger dataset needs to.
 */
function TenantsTable({ tenants }) {
  const router = useRouter();
  const [search, setSearch] = useState("");
  const [status, setStatus] = useState("");

  const filtered = useMemo(() => {
    const needle = search.trim().toLowerCase();

    return tenants.filter((tenant) => {
      const matchesSearch =
        needle === "" || tenant.name.toLowerCase().includes(needle) || tenant.code.toLowerCase().includes(needle);
      const matchesStatus = status === "" || tenant.status === status;

      return matchesSearch && matchesStatus;
    });
  }, [tenants, search, status]);

  return (
    <div className="flex flex-col gap-4">
      <Card>
        <CardBody className="flex flex-wrap items-center gap-3 py-3">
          <div className="relative w-full flex-1 min-w-[200px]">
            <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-foreground-subtle" />
            <Input
              value={search}
              onChange={(event) => setSearch(event.target.value)}
              placeholder="Search customer name or code…"
              className="pl-9"
            />
          </div>
          <div className="w-full max-w-[200px]">
            <Select
              options={[
                { value: "", label: "All statuses" },
                { value: "ACTIVE", label: "Active" },
                { value: "SUSPENDED", label: "Suspended" },
              ]}
              value={status}
              onValueChange={setStatus}
              placeholder="All statuses"
            />
          </div>
        </CardBody>
      </Card>

      <DataTable
        columns={[
          {
            key: "name",
            header: "Customer",
            render: (tenant) => (
              <div className="flex items-center gap-3">
                <span
                  className={cn(
                    "flex size-9 shrink-0 items-center justify-center rounded-full text-sm font-semibold",
                    avatarTone(tenant.name),
                  )}
                >
                  {tenant.name.charAt(0).toUpperCase()}
                </span>
                <div className="min-w-0">
                  <Link href={`/platform/tenants/${tenant.id}`} className="font-medium text-brand hover:underline">
                    {tenant.name}
                  </Link>
                  <div className="text-xs text-foreground-muted">{tenant.code}</div>
                </div>
              </div>
            ),
          },
          { key: "status", header: "Status", render: (tenant) => <StatusBadge status={tenant.status} /> },
          {
            key: "factories",
            header: "Factories",
            align: "right",
            render: (tenant) => <UsageStat icon={Factory} value={tenant.factories} />,
          },
          {
            key: "assets",
            header: "Assets",
            align: "right",
            render: (tenant) => <UsageStat icon={Boxes} value={tenant.assets} />,
          },
          {
            key: "users",
            header: "Users",
            align: "right",
            render: (tenant) => <UsageStat icon={Users} value={tenant.users} />,
          },
          {
            key: "contract",
            header: "Contract",
            render: (tenant) =>
              tenant.contract ? (
                <div className="flex items-center gap-2">
                  <div className="text-xs">
                    <span className="font-medium text-foreground">
                      {formatCurrency(tenant.contract.amount, tenant.contract.currency)}
                    </span>
                    <span className="text-foreground-muted"> / {tenant.contract.billing_cycle.toLowerCase()}</span>
                  </div>
                  {/* Only shown when it says something the row's own Status
                      badge doesn't already — an ACTIVE contract on an
                      ACTIVE company is the unremarkable common case, and a
                      second identical-looking green pill right next to the
                      first read as a duplicate rather than new information. */}
                  {tenant.contract.status !== "ACTIVE" ? <StatusBadge status={tenant.contract.status} /> : null}
                </div>
              ) : (
                <span className="text-xs text-foreground-subtle">No contract</span>
              ),
          },
          {
            key: "created_at",
            header: "Onboarded",
            render: (tenant) => (
              <span className="text-xs text-foreground-muted">
                {tenant.created_at ? new Date(tenant.created_at).toLocaleDateString() : "—"}
              </span>
            ),
          },
        ]}
        rows={filtered}
        rowKey={(tenant) => tenant.id}
        emptyTitle="No customers found."
        emptyDescription="Try a different search or filter."
        rowActions={(tenant) => [{ label: "View", onSelect: () => router.push(`/platform/tenants/${tenant.id}`) }]}
      />
    </div>
  );
}

function UsageStat({ icon: Icon, value }) {
  return (
    <span className="inline-flex items-center justify-end gap-1.5 tabular text-sm text-foreground">
      {value}
      <Icon className="size-3.5 text-foreground-subtle" />
    </span>
  );
}

export { TenantsTable };
