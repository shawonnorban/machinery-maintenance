import { apiFetch } from "@/lib/api-server";
import {
  ReqList,
  ReqForm,
  ProdDesk,
  ProgList,
  ProgEntry,
  YarnList,
  YarnEntry,
  YarnIssue,
  ChallanList,
  ChallanEntry,
} from "@/components/knitting/prod-cc";
import * as actions from "./actions";

/*
 * Knit Production → Collar/Cuff (AnnotechERP 15/06): Collar & Cuff Output
 * Entry (Style List, the production desk and New flat knit requirement),
 * Out-House Collar & Cuff Programme, Solid / Yarn-Dyed Collar & Cuff Indent
 * (with Issue Against Requisition) and Bulk / Sample Collar & Cuff Store
 * Receipt. A document opened travels by its number (?ref=CCR-…, CCP-…).
 */

const ref = (searchParams) =>
  typeof searchParams.ref === "string" && searchParams.ref
    ? searchParams.ref
    : null;
const get = (path) => apiFetch(`/knitting/prod/cc/${path}`);
const load = (path, no) =>
  no ? get(`${path}/${encodeURIComponent(no)}`).catch(() => null) : null;
const act = actions.prodAction;

async function output({ cc, tab, can, searchParams = {} }) {
  const [options, rows] = await Promise.all([
    get("options"),
    get("requirements"),
  ]);
  const no = ref(searchParams);
  return {
    tabs: [
      {
        key: "records",
        label: cc("tabs.style_list"),
        icon: "Table2",
        count: rows.length,
      },
      {
        key: "production",
        label: cc("tabs.production"),
        icon: "ListChecks",
      },
      { key: "requirement", label: cc("tabs.requirement"), icon: "Plus" },
    ],
    body: async () => {
      if (tab === "production") {
        const initial = no
          ? await get(
              `requirements/${encodeURIComponent(no)}/productions`,
            ).catch(() => null)
          : null;
        return (
          <ProdDesk
            options={options}
            initialRows={rows}
            initial={initial}
            canManage={can.prod}
            act={act}
          />
        );
      }
      if (tab === "requirement") {
        const initial = await load("requirements", no);
        return (
          <ReqForm
            key={initial?.req_no ?? "new"}
            options={options}
            initial={initial}
            canManage={can.prod}
            act={act}
          />
        );
      }
      return (
        <ReqList
          options={options}
          initialRows={rows}
          canManage={can.prod}
          act={act}
        />
      );
    },
  };
}

async function program({ cc, tab, can, searchParams = {} }) {
  const [base, rows] = await Promise.all([get("options"), get("programs")]);
  const options = {
    ...base,
    docs: rows.map((r) => ({ value: r.program_no, label: r.program_no })),
  };
  return {
    tabs: [
      {
        key: "records",
        label: cc("tabs.program_list"),
        icon: "Table2",
        count: rows.length,
      },
      { key: "entry", label: cc("tabs.program_entry"), icon: "Layers" },
    ],
    body: async () => {
      if (tab === "entry") {
        const initial = await load("programs", ref(searchParams));
        return (
          <ProgEntry
            key={initial?.program_no ?? "new"}
            options={options}
            initial={initial}
            canManage={can.prod}
            act={act}
          />
        );
      }
      return (
        <ProgList
          options={options}
          initialRows={rows}
          canManage={can.prod}
          act={act}
        />
      );
    },
  };
}

function yarnDesk(kind) {
  const path = `yarn/${kind === "SOLID" ? "solid" : "yd"}`;
  return async ({ cc, tab, can, searchParams = {} }) => {
    const [base, rows] = await Promise.all([get("options"), get(path)]);
    const options = {
      ...base,
      docs: rows.map((r) => ({ value: r.req_no, label: r.req_no })),
    };
    return {
      tabs: [
        {
          key: "records",
          label: cc("tabs.yarn_list"),
          icon: "Table2",
          count: rows.length,
        },
        { key: "entry", label: cc(`tabs.yarn_${kind}_entry`), icon: "Layers" },
        {
          key: "issue",
          label: cc(`tabs.yarn_${kind}_issue`),
          icon: "ListChecks",
        },
      ],
      body: async () => {
        if (tab === "entry") {
          const [initial, plans] = await Promise.all([
            load(path, ref(searchParams)),
            get("yarn/plans").catch(() => []),
          ]);
          return (
            <YarnEntry
              key={initial?.req_no ?? "new"}
              kind={kind}
              options={{ ...options, plans }}
              initial={initial}
              canManage={can.prod}
              act={act}
            />
          );
        }
        if (tab === "issue") {
          return (
            <YarnIssue
              kind={kind}
              initialRows={rows}
              canIssue={can.prod || can.store}
              act={act}
            />
          );
        }
        return (
          <YarnList
            kind={kind}
            options={options}
            initialRows={rows}
            canManage={can.prod}
            act={act}
          />
        );
      },
    };
  };
}

function storeDesk(kind) {
  const slug = kind === "BULK" ? "bulk" : "sample";
  const path = `challans/${slug}`;
  return async ({ cc, tab, can, searchParams = {} }) => {
    const [base, rows] = await Promise.all([get("options"), get(path)]);
    const options = {
      ...base,
      docs: rows.map((r) => ({ value: r.challan_no, label: r.challan_no })),
    };
    const manage = can.fstore || can.prod;
    return {
      tabs: [
        {
          key: "records",
          label: cc("tabs.challan_list"),
          icon: "Table2",
          count: rows.length,
        },
        {
          key: "entry",
          label: cc(`tabs.challan_${kind}_entry`),
          icon: "Layers",
        },
      ],
      body: async () => {
        if (tab === "entry") {
          const no = ref(searchParams);
          const [initial, open] = await Promise.all([
            load(path, no),
            get(
              `${path}/open${no ? `?challan=${encodeURIComponent(no)}` : ""}`,
            ).catch(() => []),
          ]);
          return (
            <ChallanEntry
              key={initial?.challan_no ?? "new"}
              kind={kind}
              options={options}
              initial={initial}
              initialOpen={open}
              canManage={manage}
              act={act}
            />
          );
        }
        return (
          <ChallanList
            kind={kind}
            options={options}
            initialRows={rows}
            canManage={manage}
            act={act}
          />
        );
      },
    };
  };
}

const CC_SCREENS = {
  "collar-cuff-production": output,
  "collar-cuff-program-sc-out-house": program,
  "yarn-req-collar-cuff-solid": yarnDesk("SOLID"),
  "yarn-req-collar-cuff-yd": yarnDesk("YD"),
  "collar-cuff-trans-recv-store-bulk": storeDesk("BULK"),
  "collar-cuff-trans-recv-store-sample": storeDesk("SAMPLE"),
};

export { CC_SCREENS };
