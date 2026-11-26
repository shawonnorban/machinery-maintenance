"use client";

import { useMemo, useState } from "react";
import Link from "next/link";
import { Building2, Search } from "lucide-react";
import { Input } from "@/components/ui/input";
import { StatusBadge } from "@/components/ui/status-badge";
import { buttonVariants } from "@/components/ui/button";
import { formatCurrency } from "@/lib/format";
import { cn } from "@/lib/utils";
import { Panel, PTable, fmtDate } from "./ui";

const AVATAR_TONES = ["bg-brand-subtle text-brand-hover", "bg-success-subtle text-success", "bg-warning-subtle text-warning", "bg-info-subtle text-info"];

function avatarTone(name) {
  let hash = 0;
  for (let i = 0; i < name.length; i++) hash = (hash + name.charCodeAt(i)) % AVATAR_TONES.length;
  return AVATAR_TONES[hash];
}

/**
 * Every customer in one call — modest volume for a B2B console, so search
 * and the status filter run here over the full list.
 */
function TenantsTable({ tenants }) {
  const [search, setSearch] = useState("");
  const [status, setStatus] = useState("");

  const filtered = useMemo(() => {
    const needle = search.trim().toLowerCase();
    return tenants.filter((t) => (needle === "" || t.name.toLowerCase().includes(needle) || t.code.toLowerCase().includes(needle)) && (status === "" || t.status === status));
  }, [tenants, search, status]);

  const columns = [
    {
      key: "name",
      label: "Customer",
      render: (t) => (
        <div className="flex items-center gap-3">
          <span className={cn("flex size-9 shrink-0 items-center justify-center rounded-full text-sm font-semibold", avatarTone(t.name))}>{t.name.charAt(0).toUpperCase()}</span>
          <div className="min-w-0">
            <Link href={`/platform/tenants/${t.id}`} className="font-semibold text-brand hover:underline">
              {t.name}
            </Link>
            <div className="text-xs text-foreground-muted">{t.code}</div>
          </div>
        </div>
      ),
    },
    { key: "status", label: "Status", render: (t) => <StatusBadge status={t.status} /> },
    { key: "factories", label: "Factories", align: "right", render: (t) => (t.factories ?? 0).toLocaleString("en-US") },
    { key: "assets", label: "Assets", align: "right", render: (t) => (t.assets ?? 0).toLocaleString("en-US") },
    { key: "users", label: "Users", align: "right", render: (t) => (t.users ?? 0).toLocaleString("en-US") },
    {
      key: "contract",
      label: "Contract",
      render: (t) =>
        t.contract ? (
          <div className="flex items-center gap-2 text-[13px]">
            <span className="font-semibold text-foreground">{formatCurrency(t.contract.amount, t.contract.currency)}</span>
            <span className="text-foreground-muted">/ {t.contract.billing_cycle.toLowerCase()}</span>
            {/* Only when it says something the row's own status does not. */}
            {t.contract.status !== "ACTIVE" ? <StatusBadge status={t.contract.status} /> : null}
          </div>
        ) : (
          <span className="text-xs text-foreground-subtle">No contract</span>
        ),
    },
    { key: "created_at", label: "Onboarded", render: (t) => <span className="text-[13px] text-foreground-muted">{fmtDate(t.created_at)}</span> },
    {
      key: "act",
      label: "",
      align: "right",
      render: (t) => (
        <Link href={`/platform/tenants/${t.id}`} className={cn(buttonVariants({ size: "sm", variant: "outline" }))}>
          Open
        </Link>
      ),
    },
  ];

  return (
    <>
      <div className="mb-4 flex flex-wrap items-end gap-3 rounded-lg border border-border bg-surface p-4">
        <label className="min-w-64 flex-1 text-[13px] font-semibold text-foreground">
          Search
          <span className="relative mt-1.5 block">
            <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-foreground-subtle" />
            <Input value={search} onChange={(e) => setSearch(e.target.value)} placeholder="Customer name or code…" className="pl-9" />
          </span>
        </label>
        <label className="text-[13px] font-semibold text-foreground">
          Status
          <select value={status} onChange={(e) => setStatus(e.target.value)} className="mt-1.5 block h-9 w-48 rounded-sm border border-border-strong bg-surface px-2.5 text-sm font-normal text-foreground">
            <option value="">All statuses</option>
            <option value="ACTIVE">Active</option>
            <option value="SUSPENDED">Suspended</option>
          </select>
        </label>
      </div>
      <Panel title="Customers" subtitle={`${filtered.length} of ${tenants.length} customers`} icon={Building2}>
        <PTable columns={columns} rows={filtered} rowKey={(t) => t.id} empty="No customers match this search." />
      </Panel>
    </>
  );
}

export { TenantsTable };
