import { apiFetch } from "@/lib/api-server";
import {
  LoanOpeningList,
  LoanOpeningForm,
  OpeningList,
  OpeningSheet,
  AssignList,
  AssignSheet,
  ClosingDesk,
  ReceiptList,
  ReceiptEntry,
  ConsumptionBoard,
  ReturnList,
  ReturnEntry,
  BrokenList,
  BrokenEntry,
  ReqList,
  ReqEntry,
  IssueList,
  IssueEntry,
  LoanList,
  LoanEntry,
  LoanReturnList,
  LoanReturnEntry,
  LoanReceiptList,
  LoanReceiptEntry,
} from "@/components/knitting/needle";
import {
  OilReqList,
  OilReqEntry,
  OilOpeningList,
  OilOpeningSheet,
  OilOpeningForm,
  OilConsList,
  OilConsEntry,
  OilReceiptList,
  OilReceiptEntry,
  OilReturnList,
  OilReturnEntry,
} from "@/components/knitting/oil";
import * as actions from "./actions";

/*
 * Knit Production → Needle Store (AnnotechERP 15/07): the ten needle and
 * sinker screens, and the loan screens of 15/08–09 on both books; Oil Store
 * (15/10) on the oil book. A paper opened travels by its number (?ref=NMR-…,
 * NBR-…, NRQ-…, ORQ-…, OMR-…); a sheet by its store code and date; a loan
 * position by its party and item codes; an oil opening line by its store,
 * date and item codes.
 */

const ref = (searchParams) =>
  typeof searchParams.ref === "string" && searchParams.ref
    ? searchParams.ref
    : null;
const get = (path) => apiFetch(`/knitting/needle/${path}`);
const load = (path, no) =>
  no ? get(`${path}/${encodeURIComponent(no)}`).catch(() => null) : null;
const act = actions.needleAction;
const docs = (rows, key) => rows.map((r) => ({ value: r[key], label: r[key] }));
const listTab = (nd, label, count) => ({
  key: "records",
  label: nd(`tabs.${label}`),
  icon: "Table2",
  count,
});

/** A list and its entry form: the common shape. */
function desk({ side, path, idKey, list, entry, labels }) {
  const List = list;
  const Entry = entry;
  return async ({ nd, tab, can, searchParams = {} }) => {
    const [base, rows] = await Promise.all([get(`${side}/options`), get(path)]);
    const options = { ...base, docs: docs(rows, idKey) };
    return {
      tabs: [
        listTab(nd, labels[0], rows.length),
        { key: "entry", label: nd(`tabs.${labels[1]}`), icon: "Layers" },
      ],
      body: async () => {
        if (tab === "entry") {
          const initial = await load(path, ref(searchParams));
          return (
            <Entry
              key={initial?.[idKey] ?? "new"}
              options={options}
              initial={initial}
              canManage={can.needle}
              act={act}
            />
          );
        }
        return (
          <List
            options={options}
            initialRows={rows}
            canManage={can.needle}
            act={act}
          />
        );
      },
    };
  };
}

/** Needle / Oil Loan Opening Balance: one net position per party and item, on its book. */
function loanOpening(book) {
  const base = `/knit-production/${book === "OIL" ? "mc-oil-loan-opening" : "mc-needle-loan-opening"}`;
  return (props) => loanOpeningScreen({ ...props, book, base });
}

async function loanOpeningScreen({
  nd,
  tab,
  can,
  searchParams = {},
  book,
  base,
}) {
  const [options, rows] = await Promise.all([
    get("store/options"),
    get(`loan-openings?book=${book}`),
  ]);
  return {
    tabs: [
      listTab(nd, "loan_opening_list", rows.length),
      { key: "entry", label: nd("tabs.loan_opening_entry"), icon: "Layers" },
    ],
    body: async () => {
      if (tab === "entry") {
        const initial =
          rows.find(
            (r) =>
              r.party_code === searchParams.party &&
              r.item_code === searchParams.item,
          ) ?? null;
        return (
          <LoanOpeningForm
            key={initial?.id ?? "new"}
            book={book}
            options={options}
            initial={initial}
            canManage={can.needle}
            act={act}
          />
        );
      }
      return (
        <LoanOpeningList
          book={book}
          base={base}
          options={options}
          initialRows={rows}
          canManage={can.needle}
        />
      );
    },
  };
}

async function opening({ nd, tab, can, searchParams = {} }) {
  const [options, rows] = await Promise.all([
    get("store/options"),
    get("openings"),
  ]);
  return {
    tabs: [
      listTab(nd, "opening_list", rows.length),
      { key: "sheet", label: nd("tabs.opening_sheet"), icon: "Layers" },
    ],
    body: async () => {
      if (tab === "sheet") {
        const store = options.stores.find((s) => s.code === searchParams.store);
        const initial =
          store && searchParams.date
            ? await get(
                `openings/sheet?store_id=${store.value}&ob_date=${encodeURIComponent(searchParams.date)}`,
              ).catch(() => null)
            : null;
        return (
          <OpeningSheet
            key={initial ? `${store.code}-${searchParams.date}` : "new"}
            options={options}
            initial={initial}
            canManage={can.needle}
            act={act}
          />
        );
      }
      return <OpeningList initialRows={rows} canManage={can.needle} />;
    },
  };
}

async function assign({ nd, tab, can }) {
  const [options, rows] = await Promise.all([
    get("floor/options"),
    get("assignments"),
  ]);
  return {
    tabs: [
      listTab(nd, "assign_list", rows.length),
      { key: "sheet", label: nd("tabs.assign_sheet"), icon: "Layers" },
    ],
    body: async () =>
      tab === "sheet" ? (
        <AssignSheet options={options} canManage={can.needle} act={act} />
      ) : (
        <AssignList initialRows={rows} canManage={can.needle} />
      ),
  };
}

async function receipt({ nd, tab, can, searchParams = {} }) {
  const [base, rows] = await Promise.all([
    get("store/options"),
    get("receipts"),
  ]);
  const options = { ...base, docs: docs(rows, "mrr_no") };
  return {
    tabs: [
      listTab(nd, "receipt_list", rows.length),
      { key: "entry", label: nd("tabs.receipt_entry"), icon: "Layers" },
      {
        key: "consumption",
        label: nd("tabs.consumption"),
        icon: "ListChecks",
      },
    ],
    body: async () => {
      if (tab === "entry") {
        const initial = await load("receipts", ref(searchParams));
        return (
          <ReceiptEntry
            key={initial?.mrr_no ?? "new"}
            options={options}
            initial={initial}
            canManage={can.needle}
            act={act}
          />
        );
      }
      if (tab === "consumption")
        return <ConsumptionBoard options={options} act={act} />;
      return (
        <ReceiptList
          options={options}
          initialRows={rows}
          canManage={can.needle}
          act={act}
        />
      );
    },
  };
}

async function returns({ nd, tab, can, searchParams = {} }) {
  const [base, rows] = await Promise.all([
    get("store/options"),
    get("returns"),
  ]);
  const options = { ...base, docs: docs(rows, "challan_no") };
  return {
    tabs: [
      listTab(nd, "return_list", rows.length),
      { key: "entry", label: nd("tabs.return_entry"), icon: "Layers" },
    ],
    body: async () => {
      if (tab === "entry") {
        const no = ref(searchParams);
        const mrr =
          typeof searchParams.mrr === "string" ? searchParams.mrr : null;
        const [initial, receipts, initialSource] = await Promise.all([
          load("returns", no),
          get("receipts"),
          !no && mrr
            ? get(`receipts/${encodeURIComponent(mrr)}/source`).catch(
                () => null,
              )
            : null,
        ]);
        return (
          <ReturnEntry
            key={initial?.challan_no ?? mrr ?? "new"}
            options={options}
            initial={initial}
            initialSource={initialSource}
            receipts={receipts.filter((r) =>
              ["VERIFIED", "WAIT_ACCOUNTS", "APPROVED"].includes(r.status),
            )}
            canManage={can.needle}
            act={act}
          />
        );
      }
      return (
        <ReturnList
          options={options}
          initialRows={rows}
          canManage={can.needle}
          act={act}
        />
      );
    },
  };
}

async function issue({ nd, tab, can, searchParams = {} }) {
  const [base, rows] = await Promise.all([get("floor/options"), get("issues")]);
  const options = { ...base, docs: docs(rows, "issue_no") };
  return {
    tabs: [
      listTab(nd, "issue_list", rows.length),
      { key: "entry", label: nd("tabs.issue_entry"), icon: "Layers" },
    ],
    body: async () => {
      if (tab === "entry") {
        const [initial, owing] = await Promise.all([
          load("issues", ref(searchParams)),
          get("issues/owing").catch(() => []),
        ]);
        return (
          <IssueEntry
            key={initial?.issue_no ?? "new"}
            options={options}
            initial={initial}
            owing={owing}
            canManage={can.needle}
            act={act}
          />
        );
      }
      return (
        <IssueList
          options={options}
          initialRows={rows}
          canManage={can.needle}
          act={act}
        />
      );
    },
  };
}

async function closing({ can }) {
  const [options, rows] = await Promise.all([
    get("store/options"),
    get("closings"),
  ]);
  return {
    body: async () => (
      <ClosingDesk
        options={options}
        initialRows={rows}
        canManage={can.needle}
        act={act}
      />
    ),
  };
}

/**
 * The loan indent with its direction fixed, on one book: Needle / Oil Loan
 * Issue Indent (lent out) or Borrowed Needle / Oil Return Issue (going back).
 */
function loanIssue(book, kind = "LOAN_ISSUE") {
  const base = `/knit-production/${
    {
      LOAN_ISSUE: {
        NEEDLE: "needle-req-for-loan-issue",
        OIL: "oil-req-for-loan-issue",
      },
      LOAN_RETURN: {
        NEEDLE: "needle-received-loan-issue-to-party",
        OIL: "oil-received-loan-issue-to-party",
      },
    }[kind][book]
  }`;
  const list = `loan-reqs?book=${book}&kind=${kind}`;
  return async ({ nd, tab, can, searchParams = {} }) => {
    const [base0, rows] = await Promise.all([get("floor/options"), get(list)]);
    const options = { ...base0, docs: docs(rows, "req_no") };
    return {
      tabs: [
        listTab(nd, "loan_list", rows.length),
        {
          key: "entry",
          label: nd(kind === "LOAN_RETURN" ? "tabs.lr_entry" : "tabs.lo_entry"),
          icon: "Layers",
        },
      ],
      body: async () => {
        const props = {
          book,
          fixedKind: kind,
          base,
          options,
          canManage: can.needle,
          act,
        };
        if (tab === "entry") {
          const initial = await load("loan-reqs", ref(searchParams));
          return (
            <LoanEntry
              key={initial?.req_no ?? "new"}
              initial={initial}
              {...props}
            />
          );
        }
        return <LoanList initialRows={rows} {...props} />;
      },
    };
  };
}

/** Needle / Oil Loan Return from Party. */
function loanBack(book) {
  const path = `loan-returns/${book.toLowerCase()}`;
  return async ({ nd, tab, can, searchParams = {} }) => {
    const [base, rows] = await Promise.all([get("floor/options"), get(path)]);
    const options = { ...base, docs: docs(rows, "return_no") };
    return {
      tabs: [
        listTab(nd, "lb_list", rows.length),
        { key: "entry", label: nd(`tabs.lb_entry_${book}`), icon: "Layers" },
      ],
      body: async () => {
        if (tab === "entry") {
          const [initial, owing] = await Promise.all([
            load(path, ref(searchParams)),
            get(`${path}/owing`).catch(() => []),
          ]);
          return (
            <LoanReturnEntry
              key={initial?.return_no ?? "new"}
              book={book}
              options={options}
              initial={initial}
              owing={owing}
              canManage={can.needle}
              act={act}
            />
          );
        }
        return (
          <LoanReturnList
            book={book}
            options={options}
            initialRows={rows}
            canManage={can.needle}
            act={act}
          />
        );
      },
    };
  };
}

/** Borrowed Needle / Oil Receipt. */
function loanReceipt(book) {
  const path = `loan-receipts/${book.toLowerCase()}`;
  return async ({ nd, tab, can, searchParams = {} }) => {
    const [base, rows] = await Promise.all([get("store/options"), get(path)]);
    const options = { ...base, docs: docs(rows, "mrr_no") };
    return {
      tabs: [
        listTab(nd, "lrc_list", rows.length),
        { key: "entry", label: nd(`tabs.lrc_entry_${book}`), icon: "Layers" },
      ],
      body: async () => {
        if (tab === "entry") {
          const initial = await load(path, ref(searchParams));
          return (
            <LoanReceiptEntry
              key={initial?.mrr_no ?? "new"}
              book={book}
              options={options}
              initial={initial}
              canManage={can.needle}
              act={act}
            />
          );
        }
        return (
          <LoanReceiptList
            book={book}
            options={options}
            initialRows={rows}
            canManage={can.needle}
            act={act}
          />
        );
      },
    };
  };
}

/** Machine Oil Opening Balance: Details Data, the opening sheet, and the entry form. */
async function oilOpening({ nd, tab, can, searchParams = {} }) {
  const [options, rows] = await Promise.all([
    get("oil/options"),
    get("oil/openings"),
  ]);
  return {
    tabs: [
      listTab(nd, "oil_opening_list", rows.length),
      { key: "sheet", label: nd("tabs.oil_opening_sheet"), icon: "ListChecks" },
      { key: "entry", label: nd("tabs.oil_opening_entry"), icon: "Layers" },
    ],
    body: async () => {
      const props = { options, canManage: can.needle, act };
      if (tab === "entry") {
        const initial =
          rows.find(
            (r) =>
              r.store_code === searchParams.store &&
              r.ob_date === searchParams.date &&
              r.item_code === searchParams.item,
          ) ?? null;
        return (
          <OilOpeningForm
            key={initial?.id ?? "new"}
            initial={initial}
            {...props}
          />
        );
      }
      if (tab === "sheet")
        return <OilOpeningSheet initialRows={rows} {...props} />;
      return <OilOpeningList initialRows={rows} {...props} />;
    },
  };
}

/** Oil Receipt Return Register: against a finalized receipt that still has something returnable. */
async function oilReturns({ nd, tab, can, searchParams = {} }) {
  const [base, rows] = await Promise.all([
    get("oil/options"),
    get("oil/returns"),
  ]);
  const options = { ...base, docs: docs(rows, "challan_no") };
  return {
    tabs: [
      listTab(nd, "oil_return_list", rows.length),
      { key: "entry", label: nd("tabs.oil_return_entry"), icon: "Layers" },
    ],
    body: async () => {
      if (tab === "entry") {
        const no = ref(searchParams);
        const [initial, receipts] = await Promise.all([
          load("oil/returns", no),
          get("oil/receipts"),
        ]);
        const initialSource = initial
          ? await get(
              `oil/receipts/${encodeURIComponent(initial.mrr_no)}/source?challan=${encodeURIComponent(initial.challan_no)}`,
            ).catch(() => null)
          : null;
        return (
          <OilReturnEntry
            key={initial?.challan_no ?? "new"}
            options={options}
            initial={initial}
            initialSource={initialSource}
            receipts={receipts.filter(
              (r) =>
                r.finalized && r.status !== "CANCELLED" && r.returnable > 0,
            )}
            canManage={can.needle}
            act={act}
          />
        );
      }
      return (
        <OilReturnList
          options={options}
          initialRows={rows}
          canManage={can.needle}
          act={act}
        />
      );
    },
  };
}

const NEEDLE_SCREENS = {
  "mc-needle-loan-opening": loanOpening("NEEDLE"),
  "mc-needle-loan-requisition": desk({
    side: "floor",
    path: "loan-reqs",
    idKey: "req_no",
    list: LoanList,
    entry: LoanEntry,
    labels: ["loan_list", "loan_entry"],
  }),
  "mc-needle-receive": receipt,
  "mc-needle-opening-balance": opening,
  "mc-wise-needle-assign": assign,
  "mc-wise-needle-broken": desk({
    side: "floor",
    path: "brokens",
    idKey: "ref_no",
    list: BrokenList,
    entry: BrokenEntry,
    labels: ["broken_list", "broken_entry"],
  }),
  "mc-wise-needle-requisition": desk({
    side: "floor",
    path: "reqs",
    idKey: "req_no",
    list: ReqList,
    entry: ReqEntry,
    labels: ["req_list", "req_entry"],
  }),
  "mc-wise-needle-issue": issue,
  "mc-needle-closing": closing,
  "mc-needle-receive-return-list": returns,
  "needle-req-for-loan-issue": loanIssue("NEEDLE"),
  "needle-loan-return-by-party": loanBack("NEEDLE"),
  "oil-req-for-loan-issue": loanIssue("OIL"),
  "oil-loan-return-by-party": loanBack("OIL"),
  "needle-loan-receive-from-party": loanReceipt("NEEDLE"),
  "needle-received-loan-issue-to-party": loanIssue("NEEDLE", "LOAN_RETURN"),
  "oil-loan-receive-from-party": loanReceipt("OIL"),
  "oil-received-loan-issue-to-party": loanIssue("OIL", "LOAN_RETURN"),
  "requisition-for-knitting-mc-oil": desk({
    side: "oil",
    path: "oil/reqs",
    idKey: "req_no",
    list: OilReqList,
    entry: OilReqEntry,
    labels: ["req_list", "oil_req_entry"],
  }),
  "mc-oil-opening-blance": oilOpening,
  "daily-macine-wise-oil-consumption": desk({
    side: "oil",
    path: "oil/consumptions",
    idKey: "cons_no",
    list: OilConsList,
    entry: OilConsEntry,
    labels: ["oil_cons_list", "oil_cons_entry"],
  }),
  "mc-oil-loan-opening": loanOpening("OIL"),
  "mc-oil-receive": desk({
    side: "oil",
    path: "oil/receipts",
    idKey: "mrr_no",
    list: OilReceiptList,
    entry: OilReceiptEntry,
    labels: ["oil_receipt_list", "oil_receipt_entry"],
  }),
  "mc-oil-receive-return-list": oilReturns,
};

export { NEEDLE_SCREENS };
