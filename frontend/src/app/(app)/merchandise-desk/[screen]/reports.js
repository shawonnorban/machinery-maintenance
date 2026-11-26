import { ReportSet } from "@/components/erp/report-set";
import * as actions from "./actions";

/*
 * Merchandise Desk → Reports (AnnotechERP 06/13): Order Merchandising
 * Reports — Brickland's report set, picked one at a time and previewed.
 */

const REPORTS = [
  "sample-production",
  "bulk-fabric-booking",
  "budget-sheet",
  "task-tna-fail",
  "dept-tna-fail",
  "style-dept-tna-fail",
  "buyer-dept-tna-fail",
  "order-accessories-status",
  "tna-pass-fail-summary",
  "buyer-costing-margin",
  "buyer-cm-earning",
  "order-budget-costing",
  "budget-vs-actual",
  "budget-vs-actual-chart",
  "order-confirmation-list",
  "buyer-accessories-pr",
  "order-tracking",
  "textile-budget-vs-actual",
  "import-fabric-receive",
  "po-actual-shipment",
  "purchase-fabric-stock",
  "finish-fabric-stock",
  "trims-stock",
  "order-size-breakdown",
];

async function merchandisingReports() {
  return {
    tabs: [],
    body: async () => <ReportSet namespace="merchandise" prefix="master.mrp" reports={REPORTS} preview={actions.previewMerchandiseReport} />,
  };
}

const REPORT_SCREENS = {
  "merchandising-reports": merchandisingReports,
};

/** Reports are read only: nothing on the screen changes a record. */
const REPORT_MANAGE = {
  "merchandising-reports": "merchandise.report.view",
};

export { REPORT_SCREENS, REPORT_MANAGE };
