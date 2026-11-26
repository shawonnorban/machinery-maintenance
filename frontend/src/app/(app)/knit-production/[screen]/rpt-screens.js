import { apiFetch } from "@/lib/api-server";
import { KnitReportSet, KnitPosition } from "@/components/knitting/reports";
import * as actions from "./actions";

/*
 * Knit Production → Reports (AnnotechERP 15/11): Brickland's five report
 * sets — Knit Production Reports, Yarn Store Register Report, Grey Fabric
 * Store Report, Knit Quality Report, Flat Knit Production Report — and its
 * Yarn Stock Position, Grey Fabric Stock Position and Daily Knit Output
 * Report. Nothing here changes a record.
 */

const get = (path) => apiFetch(`/knitting/reports/${path}`);
const load = actions.reportAction;

/** Each set's reports, in Brickland's order (its radio values). */
const SETS = {
  knitting: [
    "yarn-purchase-requisition-statement",
    "grey-fabric-stock-summary",
    "yarn-purchase-requisition-summery",
    "yarn-requisition-statement-for-knitting-sample-yd-by-date",
    "yarn-requisition-statement-for-knitting-sample-solid-by-date",
    "projection-order-grey-stock-summary",
    "yarn-requsition-statment-for-knitting-bulk-by-date",
    "order-wise-yarn-issue-register",
    "order-and-color-wise-fabric-production-statement",
    "order-and-color-wise-knitting-statement-new",
    "daily-knitting-production-with-value",
    "daily-mc-and-shift-wise-knitting-statement",
    "party-wise-order-statement-sc-outhouse-knitting",
    "party-wise-knitting-performance-summary-sc-out-house",
    "mc-wise-knitting-production-capacity-vs-achievement",
    "order-and-color-wise-production-status-sc-out-house",
    "daily-order-and-color-wise-knitting-production",
    "daily-roll-labelling-statement-sc-out-house",
    "knitting-department-followup-report",
    "knitting-program-for-sample",
    "job-wise-knitting-program",
    "date-wise-knit-card-report",
    "yarn-to-finish-fabric-status",
    "color-wise-yarn-lot-alocation-and-lot-wise-knitting-production",
    "order-and-color-wise-summery",
    "sample-grey-fabric-receive-and-delivery-status",
    "sample-grey-fabric-collar-culf-receive-and-delivery-status",
    "sample-grey-fabric-receive-and-delivery-status-test",
    "date-wise-sample-grey-fabric-receive-and-delivery",
    "lot-wise-loose-yarn-statement",
    "lot-wise-yarn-statement-report",
    "mc-and-date-wise-needle-broken",
    "needle-reason-type-wise-summary",
    "daily-mc-and-shift-wise-needle-statement",
    "needle-stock-taking",
    "mc-and-operator-wise-oil-consumption-unit-cost",
    "mc-and-shift-wise-oil-consumption-unit-cost",
    "date-wise-mc-oil-consumption",
    "oil-stock-taking",
    "sc-party-wise-rate",
    "order-and-color-wise-grey-fabric-transfer-statement",
    "sc-pre-treatment-ledger-for-all",
    "monthly-grey-qc-summary-report",
    "rack-wise-stock-report",
    "save-fabric-quantity",
    "order-wise-requisition-receive-and-issue-report",
    "physical-dyed-yarn-inventory-report",
    "physical-yarn-inventory-report",
    "buyer-wise-yarn-in-hand-status",
    "roll-list-without-grey-fabric-receive",
    "knit-loan-ledger-details",
    "knit-loan-ledger-summary",
    "knit-card-wise-quality-check-status",
    "knit-needle-loan-statement",
    "sample-fabric-progress-report",
    "knit-oil-loan-ledger-details",
    "knit-oil-loan-ledger-summary",
    "knit-oil-loan-statement",
    "program-wise-process-loss-yarn-vs-fabric",
    "mc-wise-efficiency-downtime-and-running-cost",
    "program-wise-knitting-cost-per-kg",
  ],
  "yarn-store": [
    "yarn-receive-statement",
    "yarn-issue-statement",
    "order-wise-yarn-issue-register-with-value",
    "order-wise-yarn-issue-register",
    "party-wise-yarn-issue-statement",
    "yarn-issue-return-statement",
    "department-wise-excess-yarn-summery",
    "excess-yarn-usage-statement",
    "excess-yarn-statement",
    "advance-yarn-report",
    "lot-and-count-wise-loose-yarn-stock",
    "yarn-stock-with-value",
    "yarn-stock-with-summary-value",
    "loose-yarn-statement-sc-out-house",
    "yarn-inventory-aging",
    "finishing-production-report-4a",
    "delivery-challan-for-yarn-return-to-supplier",
    "daily-and-monthly-yarn-operation-stock-and-value-report",
    "buyer-wise-yarn-stock-taking-report",
    "yarn-stock-taking-report",
    "lot-wise-loose-yarn-statement",
    "lot-wise-yarn-statement-report",
    "production-report-with-target-qty",
    "yarn-purchase-order-statement",
    "yarn-proforma-invoice-statement",
    "yarn-closing-stock-summary",
    "dyed-yarn-receive-challan-statement",
    "excess-yarn-target-report",
  ],
  gfs: [
    "grey-fabric-stock-detail",
    "buyer-ac-wise-grey-fabric-stock",
    "grey-fabric-stock-by-dia-and-yarn",
    "projection-order-grey-fabric-stock-by-fin-dia",
    "grey-fabric-delivery-challan-sc-in-house",
    "yarn-transfer-challan-sc-in-house",
    "sc-yarn-delivery-challan-out-house",
    "sc-program-cum-contract-out-house",
    "party-wise-order-statement-sc-outhouse-knitting",
    "party-and-order-wise-yd-register-sc-outhouse",
    "party-wise-yarn-balance-sc-outhouse-knitting",
    "party-wise-yarn-balance-sc-outhouse-yd",
    "party-wise-ledger-sc-outhouse-knitting",
    "supplier-wise-yarn-test-result",
    "party-wise-ledger-sc-outhouse-yd",
    "order-wise-yd-ledger",
    "party-wise-bill-statement-sc-out-house",
    "party-wise-ledger-sc-in-house",
    "party-wise-knitting-performance-by-program-sc-out-house",
    "fabric-reject-summery-sc-out-house",
    "daily-trims-receive-statement",
    "party-wise-order-statement-sc-in-house",
    "utilization-of-left-over-grey-fabric-transfer-and-re-use-summery",
    "utilization-of-left-over-grey-fabric-re-use-with-value",
    "utilization-of-left-over-grey-fabric-transfer-and-re-use",
    "party-wise-stock-summery-sc-in-house",
    "party-wise-summery-sc-in-house",
    "party-wise-bill-statement-sc-in-house",
    "order-and-color-wise-roll-available-in-grey-store-sc-in-house",
    "order-and-color-wise-grey-fabric-transfer-statement",
    "sc-pre-treatment-ledger",
    "order-and-color-wise-fabric-delivery-status",
  ],
  qc: [
    "grey-fabric-roll-inspection",
    "shift-wise-grey-fabric-roll-inspection",
    "roll-list-without-grey-qc",
    "order-and-color-wise-inspection-report",
    "date-wise-grey-fabric-qc-status",
    "grey-fabric-delivery-challan-to-store",
    "roll-wise-grey-fabric-delivery-challan-to-store",
    "monthly-out-side-quality-summary",
    "daily-return-challan-for-grey-qc-deduction-fabric",
    "monthly-quality-summary-report-before-dyeing",
  ],
  flatknit: [
    "yarn-requsition-statement-for-collarcuff",
    "order-wise-flat-knit-requirement",
    "daily-collarcuff-production",
    "flat-knit-stock-detail",
    "daily-collarcuff-transfer-to-qc-and-store",
    "buyer-ac-wise-flat-knit-stock",
    "order-wise-collarcuff-stock",
    "store-wise-collarcuff-stock",
    "date-and-mc-wise-collarcuff-production",
    "order-wise-yarn-issue-register-for-collar-cuff-production",
    "collarcuff-challan-receive-sc-out-house",
    "flat-knit-production-statement",
  ],
};

const words = (v) =>
  String(v)
    .toLowerCase()
    .split("_")
    .map((w) => w.charAt(0).toUpperCase() + w.slice(1))
    .join(" ");

/** The boxes, from the screens' options: a select's choices are {value, label}. */
function boxes(o, rp) {
  const pick = (list) =>
    list.map((v) =>
      typeof v === "string" ? { value: v, label: words(v) } : v,
    );
  return {
    from: { key: "from", type: "date" },
    to: { key: "to", type: "date" },
    yarn_item: { key: "yarn_item", type: "select", options: o.yarn_items },
    yr_approve: {
      key: "yr_approve",
      type: "select",
      noBlank: true,
      initial: "ALL",
      options: ["ALL", "YES", "NO"].map((v) => ({
        value: v,
        label: rp(`yr.${v}`),
      })),
    },
    date_type: {
      key: "date_type",
      type: "select",
      noBlank: true,
      initial: "DOCUMENT",
      options: ["DOCUMENT", "APPROVED", "DELIVERY"].map((v) => ({
        value: v,
        label: rp(`date_type.${v}`),
      })),
    },
    company: {
      key: "company",
      type: "select",
      display: true,
      options: o.company?.value ? [o.company] : [],
    },
    buyer: { key: "buyer", type: "select", options: o.buyers },
    order: { key: "order", type: "select", options: o.orders },
    store: { key: "store", type: "select", options: o.stores },
    prod_type: {
      key: "prod_type",
      type: "select",
      options: o.kinds.map((k) => ({ value: k, label: rp(`kind.${k}`) })),
    },
    colour: { key: "colour", type: "select", options: o.colours },
    qc_status: {
      key: "qc_status",
      type: "select",
      options: pick(o.qc_statuses),
    },
    qc_user: { key: "qc_user", type: "select", options: o.employees },
    kc_no: { key: "kc_no", type: "text" },
    count: { key: "count", type: "select", options: o.counts },
    grade: {
      key: "grade",
      type: "select",
      options: o.grades.map((g) => ({ value: g, label: g })),
    },
    shift: { key: "shift", type: "select", options: o.shifts },
    fabric_type: {
      key: "fabric_type",
      type: "select",
      options: o.fabric_types,
    },
    roll_no: { key: "roll_no", type: "text" },
    req_type: {
      key: "req_type",
      type: "select",
      options: o.cc_req_types.map((k) => ({
        value: k,
        label: rp(`req_type.${k}`),
      })),
    },
    floor: { key: "floor", type: "select", options: o.floors },
    composition: {
      key: "composition",
      type: "select",
      options: o.compositions,
    },
    spin: {
      key: "spin",
      type: "select",
      options: o.spins.map((v) => ({ value: v, label: v })),
    },
    cotton: {
      key: "cotton",
      type: "select",
      options: o.cottons.map((v) => ({ value: v, label: v })),
    },
    slub: { key: "slub", type: "check" },
    mellange: { key: "mellange", type: "check" },
    solid: { key: "solid", type: "check" },
    yd: { key: "yd", type: "check" },
    sc_in: { key: "sc_in", type: "check" },
    brand: {
      key: "brand",
      type: "select",
      options: o.brands.map((v) => ({ value: v, label: v })),
    },
    lot: { key: "lot", type: "text" },
    shade: {
      key: "shade",
      type: "select",
      options: o.shades.map((v) => ({ value: v, label: v })),
    },
    po: { key: "po", type: "select", options: o.pos },
    category: {
      key: "category",
      type: "select",
      options: o.sources.map((v) => ({ value: v, label: rp(`source.${v}`) })),
    },
    part: {
      key: "part",
      type: "select",
      options: ["GREY", "COLLAR_CUFF"].map((v) => ({
        value: v,
        label: rp(`part.${v}`),
      })),
    },
  };
}

/** Each set's boxes, in Brickland's order. */
const SET_FIELDS = {
  knitting: ["yarn_item", "from", "to", "yr_approve", "date_type"],
  "yarn-store": ["from", "to"],
  gfs: ["buyer", "prod_type", "order", "store", "from", "to"],
  qc: [
    "company",
    "buyer",
    "order",
    "colour",
    "qc_status",
    "qc_user",
    "kc_no",
    "count",
    "yarn_item",
    "grade",
    "shift",
    "prod_type",
    "fabric_type",
    "roll_no",
    "from",
    "to",
  ],
  flatknit: ["prod_type", "order", "req_type", "from", "to"],
};

function reportSet(set) {
  return async ({ rp }) => {
    const [options, initial] = await Promise.all([
      get("options"),
      get(`${set}/${SETS[set][0]}`).catch(() => null),
    ]);
    const all = boxes(options, rp);
    return {
      tabs: [],
      body: async () => (
        <KnitReportSet
          set={set}
          reports={SETS[set]}
          fields={SET_FIELDS[set].map((k) => all[k])}
          initial={initial}
          load={load}
        />
      ),
    };
  };
}

/** Boxes a searched screen words its own way (`rp.fx.<kind>.<box>`, `rp.px.<kind>.<box>`). */
const WORDED = {
  "yarn-stock": ["count", "buyer", "order", "store"],
  "grey-stock": ["from", "to", "prod_type", "order", "colour"],
  "daily-output": ["company", "shift", "order"],
};

/** A searched screen: its boxes and the first read, everything on file. */
function position(kind, path, keys, button) {
  return async ({ rp }) => {
    const [options, initial] = await Promise.all([get("options"), get(path)]);
    const all = boxes(options, rp);
    for (const k of WORDED[kind] ?? [])
      all[k] = {
        ...all[k],
        labelKey: `fx.${kind}.${k}`,
        phKey: `px.${kind}.${k}`,
      };
    return {
      tabs: [],
      body: async () => (
        <KnitPosition
          kind={kind}
          path={path}
          fields={keys.map((k) => all[k])}
          initial={initial}
          load={load}
          button={button}
        />
      ),
    };
  };
}

const REPORT_SCREENS = {
  "knitting-reports": reportSet("knitting"),
  "yarn-current-stock": position(
    "yarn-stock",
    "yarn-stock",
    [
      "count",
      "composition",
      "spin",
      "cotton",
      "slub",
      "mellange",
      "solid",
      "yd",
      "sc_in",
      "brand",
      "lot",
      "store",
      "shade",
      "buyer",
      "order",
      "po",
      "category",
    ],
    "search",
  ),
  "fabric-current-stock": position(
    "grey-stock",
    "grey-stock",
    ["from", "to", "part", "prod_type", "buyer", "order", "colour"],
    "search",
  ),
  "yarn-store-report": reportSet("yarn-store"),
  "gfs-report": reportSet("gfs"),
  "qc-report": reportSet("qc"),
  "flatknit-report": reportSet("flatknit"),
  "daily-knitting-production-report": position(
    "daily-output",
    "daily-output",
    ["company", "floor", "shift", "from", "to", "order"],
    "show",
  ),
};

export { REPORT_SCREENS };
