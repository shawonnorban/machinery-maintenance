import { platformApiFetch } from "@/lib/platform-api-server";
import { Building2 } from "lucide-react";
import { PlatformHeader, Kpi } from "@/components/platform/ui";
import { formatCurrency } from "@/lib/format";
import { StatusBadge } from "@/components/ui/status-badge";
import { TenantTabs } from "@/components/platform/tenant-detail/tenant-tabs";
import * as actions from "./actions";

/** Mirrors `TenantController::show` — one customer, every tab's data fetched once up front. */
export default async function TenantDetailPage({ params }) {
  const { companyId } = await params;

  const [company, members, contracts, invoices, domains, grants, tickets, usage, modules] = await Promise.all([
    platformApiFetch(`/tenants/${companyId}`),
    platformApiFetch(`/tenants/${companyId}/members`),
    platformApiFetch(`/tenants/${companyId}/contracts`),
    platformApiFetch(`/tenants/${companyId}/invoices`),
    platformApiFetch(`/tenants/${companyId}/domains`),
    platformApiFetch(`/support-grants?company_id=${companyId}`),
    platformApiFetch(`/tickets?company_id=${companyId}&per_page=50`),
    platformApiFetch(`/tenants/${companyId}/usage`),
    platformApiFetch(`/tenants/${companyId}/modules`),
  ]);

  const bound = {
    updateDetails: actions.updateTenantDetails.bind(null, companyId),
    suspend: actions.suspendTenant.bind(null, companyId),
    reactivate: actions.reactivateTenant.bind(null, companyId),
    close: actions.closeTenant.bind(null, companyId),
    purge: actions.purgeTenant.bind(null, companyId),
    storeContract: actions.storeContract.bind(null, companyId),
    updateEntitlements: actions.updateEntitlements.bind(null, companyId),
    setModule: actions.setModule.bind(null, companyId),
    draftInvoice: actions.draftInvoice.bind(null, companyId),
    issueInvoice: actions.issueInvoice.bind(null, companyId),
    payInvoice: actions.payInvoice.bind(null, companyId),
    voidInvoice: actions.voidInvoice.bind(null, companyId),
    addDomain: actions.addDomain.bind(null, companyId),
    verifyDomain: actions.verifyDomain.bind(null, companyId),
    primaryDomain: actions.primaryDomain.bind(null, companyId),
    removeDomain: actions.removeDomain.bind(null, companyId),
    updateEmail: actions.updateMemberEmail.bind(null, companyId),
    resetPassword: actions.resetMemberPassword.bind(null, companyId),
    openSupportGrant: actions.openSupportGrant.bind(null, companyId),
    closeSupportGrant: actions.closeSupportGrant.bind(null, companyId),
    enterSupportGrant: actions.enterSupportGrant,
  };

  const contract = contracts[0] ?? null;
  const owed = invoices.filter((inv) => inv.status !== "VOID").reduce((a, inv) => a + Number(inv.balance_due ?? 0), 0);
  const overdue = invoices.filter((inv) => inv.is_overdue).length;
  const sellable = modules.filter((m) => !m.core);
  const openTickets = tickets.filter((t) => !["RESOLVED", "CLOSED"].includes(t.status)).length;
  const currency = contract?.currency ?? company.base_currency;

  return (
    <>
      <PlatformHeader
        breadcrumb={[{ label: "Customers", href: "/platform" }, { label: company.name }]}
        title={company.name}
        description={[company.legal_name, company.email, company.phone].filter(Boolean).join(" · ") || "Customer account"}
        icon={<Building2 />}
        badge={
          <>
            <span className="rounded-full border border-border bg-surface-muted px-2 py-0.5 text-xs font-semibold text-foreground-muted">{company.code}</span>
            <StatusBadge status={company.status} />
          </>
        }
      />

      <div className="mb-4 grid grid-cols-2 gap-3 md:grid-cols-3 xl:grid-cols-6">
        <Kpi label="Contract" value={contract ? formatCurrency(contract.amount, contract.currency) : "—"} sub={contract ? `${contract.billing_cycle.toLowerCase()} · ${contract.status.toLowerCase()}` : "No contract yet"} tone="brand" />
        <Kpi label="Users" value={members.length.toLocaleString("en-US")} sub={contract?.included_users ? `of ${contract.included_users} included` : "no limit"} tone="info" />
        <Kpi label="Modules on" value={`${sellable.filter((m) => m.enabled).length} / ${sellable.length}`} sub="plus the core modules" tone="success" />
        <Kpi label="Owed" value={formatCurrency(owed, currency)} sub={overdue ? `${overdue} overdue` : "nothing overdue"} tone={overdue ? "danger" : "muted"} />
        <Kpi label="Open tickets" value={openTickets} sub={`${tickets.length} in all`} tone="warning" />
        <Kpi label="Domains" value={domains.length} sub={domains.find((d) => d.is_primary)?.host ?? "no primary"} tone="muted" />
      </div>

      <TenantTabs
        data={{ company, members, contracts, invoices, domains, grants, tickets, usage, modules }}
        actions={bound}
      />
    </>
  );
}
