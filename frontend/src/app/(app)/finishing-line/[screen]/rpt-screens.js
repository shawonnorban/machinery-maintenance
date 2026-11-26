import { apiFetch } from "@/lib/api-server";
import { KnitReportSet } from "@/components/knitting/reports";
import * as actions from "./actions";

/*
 * Finishing Line → Fabric Finishing Reports (AnnotechERP 18/06): Brickland's
 * one report screen — its 37 reports as one list of choices, Company, Store,
 * S/C Party, From and To Date, Export to Excel? — the same card Knit
 * Production and Dye House use, with the Finishing Line's own words
 * (`finishing.rpt.*`). Nothing here changes a record.
 */

/** Brickland's reports, in its order (its radio values). */
const REPORTS = [
  "sc-program-delivery", "daily-production", "machine-shift-production", "mc-production-vs-target", "floor-status",
  "sc-party-production-summary", "sc-delivery-receive-ledger", "sc-bill-register", "sc-bill-outstanding", "hold-list",
  "hold-list-summary", "four-point-register", "shade-approval-status", "delivery-to-store", "waiting-for-delivery",
  "issue-register", "monthly-production", "mc-reprocess-with-reason", "current-stock", "stock-details-ledger",
  "process-type-register", "machine-process-register", "sc-party-production-register", "wet-hold-summary", "production-4a",
  "order-monitoring", "sc-aop-ledger", "stock-ledger-sample", "waiting-for-delivery-sample", "delivery-to-store-sample",
  "day-night-incentive", "shift-incentive", "wet-dry-hold-24hr", "left-over-stock", "left-over-utilization",
  "fiber-colour-booking", "daily-sc-aop-batch",
];

const reports = async () => {
  const [o, initial] = await Promise.all([apiFetch("/finishing/reports/options"), apiFetch(`/finishing/reports/${REPORTS[0]}`).catch(() => null)]);
  const fields = [
    { key: "company", type: "select", display: true, noBlank: true, initial: o.company?.value ?? "", options: o.company?.value ? [o.company] : [] },
    { key: "store", type: "select", options: o.stores },
    { key: "party", type: "select", options: o.parties },
    { key: "from", type: "date" },
    { key: "to", type: "date" },
  ];
  return {
    body: () => <KnitReportSet set="finishing" reports={REPORTS} fields={fields} initial={initial} load={actions.finReportAction} ns="finishing" prefix="rpt" />,
  };
};

const RPT_SCREENS = { "dyeing-finishing-report": reports };

export { RPT_SCREENS };
