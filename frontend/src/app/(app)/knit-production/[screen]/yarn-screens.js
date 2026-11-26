import { apiFetch } from "@/lib/api-server";
import {
  RequisitionList,
  RequisitionEntry,
  ApprovalList,
} from "@/components/knitting/yarn-requisition";
import { ComparisonScreen } from "@/components/knitting/yarn-comparison";
import { PoList, PoEntry } from "@/components/knitting/yarn-po";
import * as actions from "./actions";

/*
 * Knit Production → Yarn Procurement (AnnotechERP 15/02): Yarn Purchase
 * Indent (the YR/PR register and the entry desk), Yarn Indent Approval List,
 * Yarn Comparative Statement (list and desk in place) and Yarn Purchase
 * Order (the PO register and the order desk). A document opened travels by
 * its number (?ref=YR-2026100001).
 */

const ref = (searchParams) =>
  typeof searchParams.ref === "string" && searchParams.ref
    ? searchParams.ref
    : null;
const load = (path, no) =>
  no ? apiFetch(`${path}/${encodeURIComponent(no)}`).catch(() => null) : null;

async function yarnIndent({ y, tab, can, searchParams = {} }) {
  const [options, rows] = await Promise.all([
    apiFetch("/knitting/yarn/requisitions/options"),
    apiFetch("/knitting/yarn/requisitions"),
  ]);
  const tabs = [
    {
      key: "records",
      label: y("tabs.yr_list"),
      icon: "Table2",
      count: rows.length,
    },
    { key: "entry", label: y("tabs.yr_entry"), icon: "CalendarRange" },
  ];
  return {
    tabs,
    body: async () => {
      if (tab === "entry") {
        const initial = await load(
          "/knitting/yarn/requisitions",
          ref(searchParams),
        );
        return (
          <RequisitionEntry
            key={initial?.requisition_no ?? "new"}
            options={options}
            initial={initial}
            canManage={can.manage}
            act={actions.yarnAction}
          />
        );
      }
      return (
        <RequisitionList
          options={options}
          initialRows={rows}
          canManage={can.manage}
          act={actions.yarnAction}
        />
      );
    },
  };
}

async function yarnApprovals() {
  const [options, rows] = await Promise.all([
    apiFetch("/knitting/yarn/requisitions/options"),
    apiFetch("/knitting/yarn/approvals"),
  ]);
  return {
    tabs: [],
    body: async () => (
      <ApprovalList
        options={options}
        initialRows={rows}
        act={actions.yarnAction}
      />
    ),
  };
}

async function yarnComparison({ y, can, searchParams = {} }) {
  const [options, rows] = await Promise.all([
    apiFetch("/knitting/yarn/comparisons/options"),
    apiFetch("/knitting/yarn/comparisons"),
  ]);
  return {
    tabs: [
      {
        key: "records",
        label: y("tabs.cs_list"),
        icon: "Table2",
        count: rows.length,
      },
    ],
    body: async () => {
      const initial =
        searchParams.view === "desk"
          ? await load("/knitting/yarn/comparisons", ref(searchParams))
          : null;
      return (
        <ComparisonScreen
          key={initial?.cs_no ?? searchParams.view ?? "list"}
          options={options}
          initialRows={rows}
          initial={initial}
          canManage={can.manage}
          act={actions.yarnAction}
        />
      );
    },
  };
}

async function yarnOrder({ y, tab, can, searchParams = {} }) {
  const [options, rows] = await Promise.all([
    apiFetch("/knitting/yarn/orders/options"),
    apiFetch("/knitting/yarn/orders"),
  ]);
  const tabs = [
    {
      key: "records",
      label: y("tabs.po_list"),
      icon: "Table2",
      count: rows.length,
    },
    { key: "entry", label: y("tabs.po_entry"), icon: "CalendarRange" },
  ];
  return {
    tabs,
    body: async () => {
      if (tab === "entry") {
        const initial = await load("/knitting/yarn/orders", ref(searchParams));
        return (
          <PoEntry
            key={initial?.po_no ?? "new"}
            options={options}
            initial={initial}
            canManage={can.manage}
            act={actions.yarnAction}
          />
        );
      }
      return (
        <PoList
          options={options}
          initialRows={rows}
          canManage={can.manage}
          act={actions.yarnAction}
        />
      );
    },
  };
}

const YARN_SCREENS = {
  "yarn-purchase-requisition": yarnIndent,
  "yr-pr-approve-list": yarnApprovals,
  "comparative-statement": yarnComparison,
  "yarn-purchase-order": yarnOrder,
};

export { YARN_SCREENS };
