"use client";

import { useState } from "react";
import { usePathname, useSearchParams } from "next/navigation";
import { Tabs, TabsList, TabsTab, TabsIndicator, TabsPanel } from "@/components/ui/tabs";
import { CompanyPanel } from "./company-panel";
import { BillingPanel } from "./billing-panel";
import { DomainsPanel } from "./domains-panel";
import { SupportPanel } from "./support-panel";
import { TicketsPanel } from "./tickets-panel";
import { AnalyticsPanel } from "./analytics-panel";
import { DangerPanel } from "./danger-panel";

const TABS = [
  { value: "company", label: "Company" },
  { value: "billing", label: "Billing" },
  { value: "domains", label: "Domains" },
  { value: "support", label: "Support" },
  { value: "tickets", label: "Tickets" },
  { value: "analytics", label: "Analytics" },
  { value: "danger", label: "Danger zone" },
];

/**
 * URL-driven, like `tenants/{company}/{tab?}`'s own path segment was — a
 * query param here rather than a path segment, since the app router's data
 * for every tab is fetched once, server-side, by the page above this
 * component rather than per-tab (the tab-specific-fetch optimisation
 * `TenantController::show` used no longer applies once nothing here is a
 * full-page Blade reload).
 */
function TenantTabs({ data, actions }) {
  const pathname = usePathname();
  const searchParams = useSearchParams();
  // Plain client state, not `router.push` — every tab's content is already
  // in `data`, fetched once by the server page above this component, so
  // switching tabs needs no server round trip. This page's `platformApiFetch`
  // calls are all `cache: "no-store"`, which forces the whole route dynamic;
  // pushing a URL that only changes `?tab=` was triggering a full RSC
  // re-fetch for that on every click, one that silently never completed
  // ("the destination stream closed early") and left the tab stuck on
  // whatever it already was. `history.replaceState` still keeps the URL
  // shareable/bookmarkable without going through Next's router at all.
  const [tab, setTab] = useState(() => searchParams.get("tab") ?? "company");

  function onValueChange(value) {
    setTab(value);
    const params = new URLSearchParams(searchParams);
    params.set("tab", value);
    window.history.replaceState(null, "", `${pathname}?${params.toString()}`);
  }

  return (
    <Tabs value={tab} onValueChange={onValueChange}>
      <TabsList>
        {TABS.map((t) => (
          <TabsTab key={t.value} value={t.value}>
            {t.label}
          </TabsTab>
        ))}
        <TabsIndicator />
      </TabsList>

      <TabsPanel value="company">
        <CompanyPanel
          company={data.company}
          members={data.members}
          updateDetailsAction={actions.updateDetails}
          updateEmailAction={actions.updateEmail}
          resetPasswordAction={actions.resetPassword}
        />
      </TabsPanel>

      <TabsPanel value="billing">
        <BillingPanel
          company={data.company}
          contracts={data.contracts}
          invoices={data.invoices}
          contractAction={actions.storeContract}
          entitlementsAction={actions.updateEntitlements}
          invoiceActions={{ draft: actions.draftInvoice, issue: actions.issueInvoice, pay: actions.payInvoice, void: actions.voidInvoice }}
        />
      </TabsPanel>

      <TabsPanel value="domains">
        <DomainsPanel
          domains={data.domains}
          actions={{ add: actions.addDomain, verify: actions.verifyDomain, primary: actions.primaryDomain, remove: actions.removeDomain }}
        />
      </TabsPanel>

      <TabsPanel value="support">
        <SupportPanel
          grants={data.grants}
          members={data.members}
          openAction={actions.openSupportGrant}
          closeAction={actions.closeSupportGrant}
          enterAction={actions.enterSupportGrant}
        />
      </TabsPanel>

      <TabsPanel value="tickets">
        <TicketsPanel tickets={data.tickets} />
      </TabsPanel>

      <TabsPanel value="analytics">
        <AnalyticsPanel usage={data.usage} />
      </TabsPanel>

      <TabsPanel value="danger">
        <DangerPanel
          company={data.company}
          suspendAction={actions.suspend}
          reactivateAction={actions.reactivate}
          closeAction={actions.close}
        />
      </TabsPanel>
    </Tabs>
  );
}

export { TenantTabs };
