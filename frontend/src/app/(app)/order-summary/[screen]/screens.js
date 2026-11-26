import { forbidden, redirect } from "next/navigation";
import { apiFetch } from "@/lib/api-server";
import { OrderSheet } from "@/components/ordersummary/sheet";
import { OsDashboard } from "@/components/ordersummary/dashboard";
import { PendingReport, BuyerMonthReport, AuditLog } from "@/components/ordersummary/reports";
import { ColumnSetup } from "@/components/ordersummary/column-setup";
import { TeamSetup } from "@/components/ordersummary/teams";
import { MailSchedule } from "@/components/ordersummary/mail";
import { osAction } from "./actions";

// JOB No in the company's form (26NCL00001): the year and the row's Factory fill themselves, the user types the number.
const JOB_NO = { column: "job_no", factory: "factory", url: "/api/job-numbers?module=os" };

/*
 * Order Summary's ten screens (AnnotechERP 32). The sheet's filters, sort,
 * season tab and page live in the address (f[key][]=…, r[key][from]=…), so a
 * filtered view can be bookmarked or sent.
 */

/** The address's query as a query string, arrays kept (f[buyer][]=H&M&f[buyer][]=Next). */
function qs(query, only = null) {
  const p = new URLSearchParams();
  for (const [k, v] of Object.entries(query)) {
    if (only && !only.some((o) => k === o || k.startsWith(`${o}[`))) continue;
    for (const one of [v].flat()) if (one !== undefined && one !== null) p.append(k, String(one));
  }
  return p.toString();
}

const SHEET_KEYS = ["f", "r", "q", "season", "sort", "dir", "page", "per_page", "entry_from", "entry_to"];

const SCREENS = {
  list: async function render({ query }) {
    // Opened (or refreshed) with no filter: the orders of the latest Last Update Date only — the user clears
    // or changes it from the column's filter; with every filter cleared (`all=1`) the whole list shows.
    const filtered = Object.keys(query).some((k) => k.startsWith("f[") || k.startsWith("r[") || k === "q" || k === "season" || k === "all");
    if (!filtered) {
      const dates = await apiFetch("/order-summary/orders/distinct?column=last_update").catch(() => []);
      const latest = (dates ?? []).map((d) => d.value).filter(Boolean).sort().at(-1);
      if (latest) {
        const p = new URLSearchParams(qs(query, SHEET_KEYS));
        p.append("f[last_update][]", latest);
        redirect(`/order-summary/list?${p}`);
      }
    }
    const q = qs(query, SHEET_KEYS);
    const [meta, data] = await Promise.all([apiFetch("/order-summary/meta"), apiFetch(`/order-summary/orders?${q}`)]);
    return <OrderSheet mode="list" meta={meta} data={data} queryString={q} act={osAction} config={{ defaultFilter: "last_update", jobNo: JOB_NO }} />;
  },
  new: async function render({ can }) {
    if (!can("ordersummary.create")) forbidden();
    const meta = await apiFetch("/order-summary/meta");
    return <OrderSheet mode="new" meta={meta} data={null} queryString="" act={osAction} config={{ jobNo: JOB_NO }} />;
  },
  dashboard: async function render() {
    return <OsDashboard data={await apiFetch("/order-summary/dashboard")} />;
  },
  ldd: async function render({ query }) {
    return <PendingReport basis="ldd" data={await apiFetch(`/order-summary/reports/pending/ldd?${qs(query, ["type", "buyer", "from", "to"])}`)} query={query} />;
  },
  rfi: async function render({ query }) {
    return <PendingReport basis="rfi" data={await apiFetch(`/order-summary/reports/pending/rfi?${qs(query, ["type", "buyer", "from", "to"])}`)} query={query} />;
  },
  "buyer-month": async function render({ query }) {
    const basis = query.basis === "rfi" ? "rfi" : "ldd";
    return <BuyerMonthReport basis={basis} data={await apiFetch(`/order-summary/reports/buyer-month/${basis}?${qs(query, ["year", "buyer"])}`)} query={query} />;
  },
  audit: async function render({ query }) {
    return <AuditLog data={await apiFetch(`/order-summary/audit-log?${qs(query, ["from", "to", "kind", "user", "q", "page"])}`)} query={query} />;
  },
  columns: async function render() {
    return <ColumnSetup data={await apiFetch("/order-summary/columns")} act={osAction} />;
  },
  teams: async function render() {
    return <TeamSetup data={await apiFetch("/order-summary/teams")} act={osAction} />;
  },
  mail: async function render({ can }) {
    if (!can("ordersummary.mail.manage")) forbidden();
    return <MailSchedule data={await apiFetch("/order-summary/mail-settings")} act={osAction} />;
  },
};

export { SCREENS };
