import { platformApiFetch } from "@/lib/platform-api-server";
import { PageHeader } from "@/components/layout/page-header";
import { StatusBadge } from "@/components/ui/status-badge";
import { TenantTabs } from "@/components/platform/tenant-detail/tenant-tabs";
import * as actions from "./actions";

/** Mirrors `TenantController::show` — one customer, every tab's data fetched once up front. */
export default async function TenantDetailPage({ params }) {
  const { companyId } = await params;

  const [company, members, contracts, invoices, domains, grants, tickets, usage] = await Promise.all([
    platformApiFetch(`/tenants/${companyId}`),
    platformApiFetch(`/tenants/${companyId}/members`),
    platformApiFetch(`/tenants/${companyId}/contracts`),
    platformApiFetch(`/tenants/${companyId}/invoices`),
    platformApiFetch(`/tenants/${companyId}/domains`),
    platformApiFetch(`/support-grants?company_id=${companyId}`),
    platformApiFetch(`/tickets?company_id=${companyId}&per_page=50`),
    platformApiFetch(`/tenants/${companyId}/usage`),
  ]);

  const bound = {
    updateDetails: actions.updateTenantDetails.bind(null, companyId),
    suspend: actions.suspendTenant.bind(null, companyId),
    reactivate: actions.reactivateTenant.bind(null, companyId),
    close: actions.closeTenant.bind(null, companyId),
    purge: actions.purgeTenant.bind(null, companyId),
    storeContract: actions.storeContract.bind(null, companyId),
    updateEntitlements: actions.updateEntitlements.bind(null, companyId),
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
  };

  return (
    <>
      <PageHeader
        breadcrumb={[{ label: "Customers", href: "/platform" }, { label: company.name }]}
        title={company.name}
        description={company.code}
        actions={<StatusBadge status={company.status} />}
      />

      <TenantTabs
        data={{ company, members, contracts, invoices, domains, grants, tickets, usage }}
        actions={bound}
      />
    </>
  );
}
