import { apiFetch } from "@/lib/api-server";
import {
  GreyStock,
  GreyReceive,
  GreyClose,
  OpeningList,
  OpeningEntry,
  AdjList,
  AdjEntry,
  TrList,
  TrEntry,
} from "@/components/knitting/fstore-grey";
import {
  ScReceiptList,
  ScReceiptEntry,
  ScReturnList,
  ScReturnEntry,
  ScDeliveryList,
  ScDeliveryEntry,
} from "@/components/knitting/fstore-sc";
import {
  YtrList,
  ProgEntry,
  GreyEntry,
  DyedEntry,
  FwdEntry,
} from "@/components/knitting/fstore-ytr";
import {
  BillList,
  BillEntry,
  MemoList,
  MemoEntry,
} from "@/components/knitting/fstore-bill";
import * as actions from "./actions";

/*
 * Knit Production → Fabric Store (AnnotechERP 15/05), the grey store: Grey
 * Fabric Store Receipt, Grey Fabric Opening Stock, Store-to-Store Grey
 * Transfer, Grey Fabric Stock Correction and Grey Fabric Stock Close; and
 * the sub-contract challans — Out-House Grey Receipt (with its Collar &
 * Cuff QC desk), the two Grey Return Challans and In-House Grey Delivery;
 * and the yarn papers — Out-House Yarn Transfer, Grey Yarn Transfer for
 * Dyeing, Dyed Yarn Receive Challan, Out-House Dyed Yarn Transfer Challan;
 * and the money papers — the three sub-contract bills and the debit memo.
 * A document opened travels by its number (?ref=GFT-…, ?ref=OGR-…, …).
 */

const ref = (searchParams) =>
  typeof searchParams.ref === "string" && searchParams.ref
    ? searchParams.ref
    : null;
const get = (path) => apiFetch(`/knitting/fstore/${path}`);
const load = (path, no) =>
  no ? get(`${path}/${encodeURIComponent(no)}`).catch(() => null) : null;
const act = actions.fstoreAction;

async function greyReceipt({ fs, tab, can }) {
  const options = await get("options");
  return {
    tabs: [
      { key: "records", label: fs("tabs.stock"), icon: "Table2" },
      { key: "entry", label: fs("tabs.receive"), icon: "ScanLine" },
    ],
    body: async () => {
      if (tab === "entry") {
        const store = options.stores.find((s) => /grey|fab/i.test(s.label));
        const day = await get(
          `receipts/day?date=${options.dates[0]}${store ? `&store=${encodeURIComponent(store.value)}` : ""}`,
        );
        return (
          <GreyReceive
            options={options}
            initialDay={day}
            canManage={can.fstore}
            act={act}
          />
        );
      }
      return <GreyStock options={options} act={act} />;
    },
  };
}

async function greyClose({ can }) {
  const [options, rows] = await Promise.all([get("options"), get("closes")]);
  return {
    tabs: [],
    body: async () => (
      <GreyClose
        options={options}
        initialRows={rows}
        canManage={can.fstore}
        act={act}
      />
    ),
  };
}

async function greyOpening({ fs, tab, can, searchParams = {} }) {
  const [options, rows] = await Promise.all([get("options"), get("openings")]);
  const kinds = ["IN_HOUSE", "COLLAR_CUFF", "SC_IN_HOUSE"];
  return {
    tabs: [
      {
        key: "records",
        label: fs("tabs.ob_list"),
        icon: "Table2",
        count: rows.length,
      },
      ...kinds.map((k) => ({
        key: k.toLowerCase(),
        label: fs(`tabs.ob_${k}`),
        icon: "ListChecks",
      })),
      { key: "entry", label: fs("tabs.ob_entry"), icon: "Plus" },
    ],
    body: async () => {
      if (tab === "entry") {
        const no = ref(searchParams);
        const initial = no
          ? (rows.find((r) => r.opening_no === no && !r.finalized) ?? null)
          : null;
        return (
          <OpeningEntry
            key={initial?.opening_no ?? "new"}
            options={options}
            initial={initial}
            canManage={can.fstore}
            act={act}
          />
        );
      }
      const kind = kinds.find((k) => k.toLowerCase() === tab) ?? null;
      return (
        <OpeningList
          key={kind ?? "all"}
          kind={kind}
          options={options}
          initialRows={kind ? rows.filter((r) => r.kind === kind) : rows}
          canManage={can.fstore}
          act={act}
        />
      );
    },
  };
}

/** A list (tab "records") and its form ("entry"); `pickKey` names the form's existing-document picker. */
function desk({ path, list, entry, tabs, idKey, pickKey, lots = false }) {
  const List = list;
  const Entry = entry;
  return async ({ fs, tab, can, searchParams = {} }) => {
    const [base, rows] = await Promise.all([get("options"), get(path)]);
    const options = {
      ...base,
      [pickKey]: rows.map((r) => ({ value: r[idKey], label: r[idKey] })),
    };
    return {
      tabs: [
        {
          key: "records",
          label: fs(`tabs.${tabs}_list`),
          icon: "Table2",
          count: rows.length,
        },
        { key: "entry", label: fs(`tabs.${tabs}_entry`), icon: "Layers" },
      ],
      body: async () => {
        if (tab === "entry") {
          const initial = await load(path, ref(searchParams));
          const initialLots =
            lots && initial
              ? await get(
                  `lots?store=${encodeURIComponent(initial.from_store_id)}${initial.order_id ? `&order=${encodeURIComponent(initial.order_id)}` : ""}`,
                ).catch(() => [])
              : [];
          return (
            <Entry
              key={initial?.[idKey] ?? "new"}
              options={options}
              initial={initial}
              initialLots={initialLots}
              canManage={can.fstore}
              act={act}
            />
          );
        }
        return (
          <List
            options={options}
            initialRows={rows}
            canManage={can.fstore}
            act={act}
          />
        );
      },
    };
  };
}

/**
 * A sub-contract challan desk: its list and its form. `lots` names what the
 * form's pickers read for an opened draft (the store's grey lines, the
 * party's yarn).
 */
function scDesk({
  path,
  list,
  entry,
  tabs,
  idKey,
  extra = {},
  lots = false,
  yarn = false,
}) {
  const List = list;
  const Entry = entry;
  return async ({ scg, tab, can, searchParams = {} }) => {
    const [base, rows] = await Promise.all([get("sc/options"), get(path)]);
    const options = {
      ...base,
      docs: rows.map((r) => ({ value: r[idKey], label: r[idKey] })),
    };
    return {
      tabs: [
        {
          key: "records",
          label: scg(`tabs.${tabs}_list`),
          icon: "Table2",
          count: rows.length,
        },
        {
          key: "entry",
          label: scg(
            `tabs.${tabs === "ret_OUT_HOUSE" || tabs === "ret_IN_HOUSE" ? "ret" : tabs}_entry`,
          ),
          icon: "Layers",
        },
      ],
      body: async () => {
        if (tab === "entry") {
          const initial = await load(path, ref(searchParams));
          const store = initial?.store_id;
          const [initialLots, initialYarn] = await Promise.all([
            lots && store
              ? get(`lots?store=${encodeURIComponent(store)}`).catch(() => [])
              : [],
            yarn && store
              ? get(
                  `sc/party-yarn?party=${encodeURIComponent(initial.party_id)}&store=${encodeURIComponent(store)}`,
                ).catch(() => [])
              : [],
          ]);
          return (
            <Entry
              key={initial?.[idKey] ?? "new"}
              options={options}
              initial={initial}
              initialLots={initialLots}
              initialYarn={initialYarn}
              canManage={can.fstore}
              act={act}
              {...extra}
            />
          );
        }
        return (
          <List
            options={options}
            initialRows={rows}
            canManage={can.fstore}
            act={act}
            {...extra}
          />
        );
      },
    };
  };
}

async function collarQc({ can }) {
  const [options, rows] = await Promise.all([
    get("sc/options"),
    get("sc/receipts?qc=1"),
  ]);
  return {
    tabs: [],
    body: async () => (
      <ScReceiptList
        options={options}
        initialRows={rows}
        canManage={can.fstore}
        act={act}
        qc
      />
    ),
  };
}

/**
 * A yarn transfer desk: its register and its form. The dyed yarn transfer
 * reads the receive lines still unforwarded, for its side list.
 */
function ytrDesk({ kind, path, entry, source = false }) {
  const Entry = entry;
  return async ({ ytr, tab, can, searchParams = {} }) => {
    const [base, rows] = await Promise.all([get("yarn/options"), get(path)]);
    const options = {
      ...base,
      docs: rows.map((r) => ({ value: r.challan_no, label: r.challan_no })),
    };
    return {
      tabs: [
        {
          key: "records",
          label: ytr(`tabs.${kind}_list`),
          icon: "Table2",
          count: rows.length,
        },
        { key: "entry", label: ytr(`tabs.${kind}_entry`), icon: "Layers" },
      ],
      body: async () => {
        if (tab === "entry") {
          const [initial, initialSource] = await Promise.all([
            load(path, ref(searchParams)),
            source ? get("yarn/forwardable").catch(() => []) : [],
          ]);
          return (
            <Entry
              key={initial?.challan_no ?? "new"}
              options={options}
              initial={initial}
              initialSource={initialSource}
              canManage={can.fstore}
              act={act}
            />
          );
        }
        return (
          <YtrList
            kind={kind}
            options={options}
            initialRows={rows}
            canManage={can.fstore}
            act={act}
          />
        );
      },
    };
  };
}

/**
 * A sub-contract bill (by kind) or the debit memo: its register and form.
 * The bill form also reads the register, for the Ref.Bill# it may revise.
 */
function moneyDesk({ kind, path, idKey, tabs }) {
  return async ({ scb, tab, can, searchParams = {} }) => {
    const [base, rows] = await Promise.all([get("bills/options"), get(path)]);
    const options = {
      ...base,
      docs: rows.map((r) => ({ value: r[idKey], label: r[idKey] })),
    };
    return {
      tabs: [
        {
          key: "records",
          label: scb(`tabs.${tabs}_list`),
          icon: "Table2",
          count: rows.length,
        },
        { key: "entry", label: scb(`tabs.${tabs}_entry`), icon: "Layers" },
      ],
      body: async () => {
        if (tab === "entry") {
          const initial = await load(path, ref(searchParams));
          const props = {
            options,
            initial,
            canManage: can.fstore,
            act,
          };
          return kind ? (
            <BillEntry
              key={initial?.bill_no ?? "new"}
              kind={kind}
              bills={rows}
              {...props}
            />
          ) : (
            <MemoEntry key={initial?.memo_no ?? "new"} {...props} />
          );
        }
        const props = {
          options,
          initialRows: rows,
          canManage: can.fstore,
          act,
        };
        return kind ? (
          <BillList kind={kind} {...props} />
        ) : (
          <MemoList {...props} />
        );
      },
    };
  };
}

const FSTORE_SCREENS = {
  "grey-fabric-receive-to-store": greyReceipt,
  "grey-fabric-stock-closing": greyClose,
  "grey-fabric-stock-opening": greyOpening,
  "grey-fabric-stock-adjustment": desk({
    path: "adjustments",
    list: AdjList,
    entry: AdjEntry,
    tabs: "adj",
    idKey: "adj_no",
    pickKey: "adjustments",
  }),
  "grey-fabric-transfer-store-to-store": desk({
    path: "transfers",
    list: TrList,
    entry: TrEntry,
    tabs: "tr",
    idKey: "transfer_no",
    pickKey: "transfers",
    lots: true,
  }),
  "grey-fabric-receive-challan-sc-out-house": scDesk({
    path: "sc/receipts",
    list: ScReceiptList,
    entry: ScReceiptEntry,
    tabs: "receipt",
    idKey: "rcv_no",
  }),
  "collar-cuff-receive-sc-out-house-qc": collarQc,
  "grey-fabric-return-challan-sc-out-house": scDesk({
    path: "sc/returns/out-house",
    list: ScReturnList,
    entry: ScReturnEntry,
    tabs: "ret_OUT_HOUSE",
    idKey: "challan_no",
    extra: { kind: "OUT_HOUSE" },
    lots: true,
  }),
  "grey-fabric-return-challan-sc-in-house": scDesk({
    path: "sc/returns/in-house",
    list: ScReturnList,
    entry: ScReturnEntry,
    tabs: "ret_IN_HOUSE",
    idKey: "challan_no",
    extra: { kind: "IN_HOUSE" },
    lots: true,
  }),
  "grey-fabric-delivery-challan-sc-in-house": scDesk({
    path: "sc/deliveries",
    list: ScDeliveryList,
    entry: ScDeliveryEntry,
    tabs: "delivery",
    idKey: "challan_no",
    lots: true,
    yarn: true,
  }),
  "yarn-transfer-sc-out-house": ytrDesk({
    kind: "prog",
    path: "yarn/prog-transfers",
    entry: ProgEntry,
  }),
  "grey-yarn-transfer-challan-for-yd": ytrDesk({
    kind: "grey",
    path: "yarn/grey-transfers",
    entry: GreyEntry,
  }),
  "dyed-yarn-receive-challan": ytrDesk({
    kind: "dyed",
    path: "yarn/dyed-receipts",
    entry: DyedEntry,
  }),
  "dyed-yarn-trans-challan-sc-out-house-knitting": ytrDesk({
    kind: "fwd",
    path: "yarn/dyed-transfers",
    entry: FwdEntry,
    source: true,
  }),
  "sc-in-house-bill": moneyDesk({
    kind: "IN_HOUSE",
    path: "bills/in-house",
    idKey: "bill_no",
    tabs: "bill_IN_HOUSE",
  }),
  "sc-out-house-bill": moneyDesk({
    kind: "OUT_HOUSE",
    path: "bills/out-house",
    idKey: "bill_no",
    tabs: "bill_OUT_HOUSE",
  }),
  "yd-bill-sc-out-house": moneyDesk({
    kind: "YD",
    path: "bills/yd",
    idKey: "bill_no",
    tabs: "bill_YD",
  }),
  "debit-memo-sc-out-house": moneyDesk({
    kind: null,
    path: "debit-memos",
    idKey: "memo_no",
    tabs: "memo",
  }),
};

export { FSTORE_SCREENS };
