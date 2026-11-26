import { apiFetch } from "@/lib/api-server";
import {
  MrrList,
  MrrEntry,
  ReturnDesk,
  ReturnList,
} from "@/components/knitting/ystore-receipt";
import {
  ObList,
  ObEntry,
  AdjList,
  AdjEntry,
} from "@/components/knitting/ystore-ob";
import {
  RackBoard,
  RackAssign,
  OpeningGrid,
  MonthClose,
} from "@/components/knitting/ystore-misc";
import {
  IssueReqList,
  ReqEntry,
  IssueEntry,
} from "@/components/knitting/ystore-issue";
import {
  IssueReturnList,
  IssueReturnEntry,
  PartyChallanList,
  PartyChallanEntry,
  ReplacementList,
  ReplacementEntry,
} from "@/components/knitting/ystore-outward";
import {
  YdReceiptList,
  YdReceiptEntry,
  LossList,
  LossEntry,
  RpList,
  RpEntry,
  ScChallanList,
  ScChallanEntry,
  YdStatement,
} from "@/components/knitting/ystore-yd";
import * as actions from "./actions";

/*
 * Knit Production → Yarn Store (AnnotechERP 15/03): Yarn Opening Entry, Yarn
 * Store Opening Balance, Daily Yarn Receipt (with Returns & Exchanges), Yarn
 * Racking Board, Daily Yarn Receipt Return, Yarn Store Month Closing and Yarn
 * Stock Adjustment; and the dye house's side of a Y/D programme — In-House
 * Dyed Yarn Receipt, Yarn Dyeing Loss Adjustment, the two YD Re-process
 * screens, Yarn Dyeing Statement — with In-House Sub-Contract Yarn Receipt.
 * A document opened travels by its number (?ref=YMRR-2026120001).
 */

const ref = (searchParams) =>
  typeof searchParams.ref === "string" && searchParams.ref
    ? searchParams.ref
    : null;
const load = (path, no) =>
  no
    ? apiFetch(`/knitting/ystore/${path}/${encodeURIComponent(no)}`).catch(
        () => null,
      )
    : null;
const act = actions.ystoreAction;

async function yarnOpening({ can }) {
  const options = await apiFetch("/knitting/ystore/options");
  return {
    tabs: [],
    body: async () => (
      <OpeningGrid options={options} canManage={can.store} act={act} />
    ),
  };
}

async function openingBalance({ s, tab, can, searchParams = {} }) {
  const [options, rows] = await Promise.all([
    apiFetch("/knitting/ystore/options"),
    apiFetch("/knitting/ystore/obs"),
  ]);
  return {
    tabs: [
      {
        key: "records",
        label: s("tabs.ob_list"),
        icon: "Table2",
        count: rows.length,
      },
      { key: "entry", label: s("tabs.ob_entry"), icon: "Layers" },
    ],
    body: async () => {
      if (tab === "entry") {
        const initial = await load("obs", ref(searchParams));
        return (
          <ObEntry
            key={initial?.ob_no ?? "new"}
            options={options}
            initial={initial}
            canManage={can.store}
            act={act}
          />
        );
      }
      return (
        <ObList
          options={options}
          initialRows={rows}
          canManage={can.store}
          act={act}
        />
      );
    },
  };
}

async function dailyReceive({ s, tab, can, searchParams = {} }) {
  const [options, rows] = await Promise.all([
    apiFetch("/knitting/ystore/receipts/options"),
    apiFetch("/knitting/ystore/receipts"),
  ]);
  return {
    tabs: [
      {
        key: "records",
        label: s("tabs.mrr_list"),
        icon: "Table2",
        count: rows.length,
      },
      { key: "entry", label: s("tabs.mrr_entry"), icon: "Layers" },
      { key: "returns", label: s("tabs.mrr_returns"), icon: "Layers" },
    ],
    body: async () => {
      if (tab === "entry") {
        const initial = await load("receipts", ref(searchParams));
        return (
          <MrrEntry
            key={initial?.mrr_no ?? "new"}
            options={options}
            initial={initial}
            canManage={can.store}
            act={act}
          />
        );
      }
      if (tab === "returns") {
        const initial = await load("returns", ref(searchParams));
        return (
          <ReturnDesk
            key={initial?.return_no ?? "none"}
            options={options}
            initial={initial}
            canManage={can.store}
            act={act}
          />
        );
      }
      return (
        <MrrList
          options={options}
          initialRows={rows}
          canManage={can.store}
          act={act}
        />
      );
    },
  };
}

async function rackingBoard({ s, tab, can }) {
  const [options, rows] = await Promise.all([
    apiFetch("/knitting/ystore/options"),
    apiFetch("/knitting/ystore/racks/board"),
  ]);
  return {
    tabs: [
      {
        key: "records",
        label: s("tabs.rack_board"),
        icon: "Table2",
        count: rows.length,
      },
      { key: "assign", label: s("tabs.rack_assign"), icon: "Layers" },
    ],
    body: async () =>
      tab === "assign" ? (
        <RackAssign options={options} canManage={can.store} act={act} />
      ) : (
        <RackBoard options={options} initialRows={rows} act={act} />
      ),
  };
}

async function receiveReturn({ s, tab, can, searchParams = {} }) {
  const [options, rows] = await Promise.all([
    apiFetch("/knitting/ystore/receipts/options"),
    apiFetch("/knitting/ystore/returns"),
  ]);
  return {
    tabs: [
      {
        key: "records",
        label: s("tabs.rr_list"),
        icon: "Table2",
        count: rows.length,
      },
      { key: "entry", label: s("tabs.rr_entry"), icon: "Layers" },
    ],
    body: async () => {
      if (tab === "entry") {
        const initial = await load("returns", ref(searchParams));
        return (
          <ReturnDesk
            key={initial?.return_no ?? "none"}
            options={options}
            initial={initial}
            canManage={can.store}
            act={act}
            tab="entry"
          />
        );
      }
      return <ReturnList options={options} initialRows={rows} act={act} />;
    },
  };
}

async function monthClosing({ can }) {
  const [options, closes] = await Promise.all([
    apiFetch("/knitting/ystore/options"),
    apiFetch("/knitting/ystore/closes"),
  ]);
  return {
    tabs: [],
    body: async () => (
      <MonthClose
        options={options}
        initialCloses={closes}
        canManage={can.store}
        act={act}
      />
    ),
  };
}

async function adjustment({ s, tab, can, searchParams = {} }) {
  const [options, rows] = await Promise.all([
    apiFetch("/knitting/ystore/options"),
    apiFetch("/knitting/ystore/adjustments"),
  ]);
  return {
    tabs: [
      {
        key: "records",
        label: s("tabs.adj_list"),
        icon: "Table2",
        count: rows.length,
      },
      { key: "entry", label: s("tabs.adj_entry"), icon: "Layers" },
    ],
    body: async () => {
      if (tab === "entry") {
        const initial = await load("adjustments", ref(searchParams));
        const first = (
          options.store_codes.find((x) => /yarn/i.test(x.label)) ??
          options.store_codes[0]
        )?.value;
        const lots =
          !initial && first
            ? await apiFetch(
                `/knitting/ystore/lots?store=${encodeURIComponent(first)}`,
              ).catch(() => [])
            : [];
        return (
          <AdjEntry
            key={initial?.adj_no ?? "new"}
            options={options}
            initial={initial}
            initialLots={lots}
            canManage={can.store}
            act={act}
          />
        );
      }
      return <AdjList initialRows={rows} canManage={can.store} act={act} />;
    },
  };
}

async function issueAgainstReq({ s, tab, can, searchParams = {} }) {
  const [options, rows] = await Promise.all([
    apiFetch("/knitting/ystore/issue-reqs/options"),
    apiFetch("/knitting/ystore/issue-reqs"),
  ]);
  return {
    tabs: [
      {
        key: "records",
        label: s("tabs.ir_list"),
        icon: "Table2",
        count: rows.length,
      },
      { key: "entry", label: s("tabs.ir_entry"), icon: "Layers" },
      { key: "issue", label: s("tabs.is_entry"), icon: "Layers" },
    ],
    body: async () => {
      if (tab === "entry") {
        const initial = await load("issue-reqs", ref(searchParams));
        return (
          <ReqEntry
            key={initial?.req_no ?? "new"}
            options={options}
            initial={initial}
            canManage={can.store}
            act={act}
          />
        );
      }
      if (tab === "issue") {
        const initial = await load("issues", ref(searchParams));
        // An issue opened from the list's Issue button arrives with its requisition.
        const req =
          initial?.req_no ??
          (typeof searchParams.req === "string" ? searchParams.req : null);
        const source = req
          ? await apiFetch(
              `/knitting/ystore/issue-reqs/${encodeURIComponent(req)}/source`,
            ).catch(() => null)
          : null;
        return (
          <IssueEntry
            key={initial?.issue_no ?? req ?? "new"}
            options={options}
            initial={initial}
            initialSource={source}
            canManage={can.store}
            act={act}
          />
        );
      }
      return (
        <IssueReqList
          options={options}
          initialRows={rows}
          canManage={can.store}
          act={act}
        />
      );
    },
  };
}

/** Lots of the yarn store a new party challan or replacement opens on. */
const firstLots = (options) => {
  const code = (
    options.store_codes.find((x) => /yarn/i.test(x.label)) ??
    options.store_codes[0]
  )?.value;
  return code
    ? apiFetch(`/knitting/ystore/lots?store=${encodeURIComponent(code)}`).catch(
        () => [],
      )
    : [];
};

function outward(path, tabsKey, List, Entry, withLots) {
  return async ({ s, tab, can, searchParams = {} }) => {
    const [options, rows] = await Promise.all([
      apiFetch("/knitting/ystore/outward/options"),
      apiFetch(`/knitting/ystore/${path}`),
    ]);
    return {
      tabs: [
        {
          key: "records",
          label: s(`tabs.${tabsKey}_list`),
          icon: "Table2",
          count: rows.length,
        },
        { key: "entry", label: s(`tabs.${tabsKey}_entry`), icon: "Layers" },
      ],
      body: async () => {
        if (tab === "entry") {
          const initial = await load(path, ref(searchParams));
          const lots = withLots && !initial ? await firstLots(options) : [];
          return (
            <Entry
              key={initial?.return_no ?? initial?.challan_no ?? "new"}
              options={options}
              initial={initial}
              initialLots={lots}
              canManage={can.store}
              act={act}
            />
          );
        }
        return (
          <List
            options={options}
            initialRows={rows}
            canManage={can.store}
            act={act}
          />
        );
      },
    };
  };
}

/**
 * A Y/D desk: its list (tab "records") and its form ("entry"); `pickKey`
 * names the existing-document picker the form offers from the list.
 */
function ydDesk({ path, list, entry, tabs, idKey, pickKey, extra = {} }) {
  const List = list;
  const Entry = entry;
  return async ({ yd, tab, can, searchParams = {} }) => {
    const [base, rows] = await Promise.all([
      apiFetch("/knitting/ystore/yd/options"),
      apiFetch(`/knitting/ystore/${path}`),
    ]);
    const options = {
      ...base,
      [pickKey]: rows.map((r) => ({ value: r[idKey], label: r[idKey] })),
    };
    return {
      tabs: [
        {
          key: "records",
          label: yd(`tabs.${tabs}_list`),
          icon: "Table2",
          count: rows.length,
        },
        { key: "entry", label: yd(`tabs.${tabs}_entry`), icon: "Layers" },
      ],
      body: async () => {
        if (tab === "entry") {
          const initial = await load(path, ref(searchParams));
          return (
            <Entry
              key={initial?.[idKey] ?? "new"}
              options={options}
              initial={initial}
              canManage={can.store}
              act={act}
              {...extra}
            />
          );
        }
        return (
          <List
            options={options}
            initialRows={rows}
            canManage={can.store}
            act={act}
            {...extra}
          />
        );
      },
    };
  };
}

async function ydStatement() {
  const [options, rows] = await Promise.all([
    apiFetch("/knitting/ystore/yd/options"),
    apiFetch("/knitting/ystore/yd/statement"),
  ]);
  return {
    tabs: [],
    body: async () => (
      <YdStatement options={options} initialRows={rows} act={act} />
    ),
  };
}

const YSTORE_SCREENS = {
  "issue-against-requisition": issueAgainstReq,
  "issue-return-against-requisition": outward(
    "issue-returns",
    "irr",
    IssueReturnList,
    IssueReturnEntry,
    false,
  ),
  "party-wise-yarn-issue-challan": outward(
    "party-challans",
    "pw",
    PartyChallanList,
    PartyChallanEntry,
    true,
  ),
  "issue-yarn-replace-by-supplier-list": outward(
    "replacements",
    "rp",
    ReplacementList,
    ReplacementEntry,
    true,
  ),
  "yarn-opening": yarnOpening,
  "opening-balance": openingBalance,
  "daily-receive": dailyReceive,
  "yarn-stock-racking-board": rackingBoard,
  "daily-receive-return": receiveReturn,
  "yarn-month-closing": monthClosing,
  "yarn-adjustment": adjustment,
  "dyed-yarn-receive-in-house": ydDesk({
    path: "yd/receipts",
    list: YdReceiptList,
    entry: YdReceiptEntry,
    tabs: "receipt",
    idKey: "receive_no",
    pickKey: "receipts",
  }),
  "process-loss-adjustment-yd": ydDesk({
    path: "yd/loss-memos",
    list: LossList,
    entry: LossEntry,
    tabs: "loss",
    idKey: "memo_no",
    pickKey: "memos",
  }),
  "yd-reprocess-in-house-to-yd-factory": ydDesk({
    path: "yd/reprocess/in-house",
    list: RpList,
    entry: RpEntry,
    tabs: "rp_ih",
    idKey: "ref_no",
    pickKey: "reprocesses",
    extra: { kind: "IN_HOUSE" },
  }),
  "yd-reprocess-sc-knitting-to-yd-factory": ydDesk({
    path: "yd/reprocess/sc-knitting",
    list: RpList,
    entry: RpEntry,
    tabs: "rp_sc",
    idKey: "ref_no",
    pickKey: "reprocesses",
    extra: { kind: "SUB_CONTRACT" },
  }),
  "yarn-receive-challan-sc-in-house": ydDesk({
    path: "sc-yarn-challans",
    list: ScChallanList,
    entry: ScChallanEntry,
    tabs: "syc",
    idKey: "challan_no",
    pickKey: "challans",
  }),
  "knitting-yd-statement": ydStatement,
};

export { YSTORE_SCREENS };
