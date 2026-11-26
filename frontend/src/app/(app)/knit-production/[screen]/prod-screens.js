import { apiFetch } from "@/lib/api-server";
import { PoRegister, PoEntry, Desk } from "@/components/knitting/prod-order";
import {
  RollList,
  RollEntry,
  DailyOutput,
} from "@/components/knitting/prod-roll";
import { InspectionList, InspectionEntry } from "@/components/knitting/prod-qc";
import { ScRegister, ScEntry } from "@/components/knitting/prod-sc";
import { ScrList, ScrEntry } from "@/components/knitting/prod-scr";
import { YtoList, YtoEntry } from "@/components/knitting/prod-yt";
import { YtTest, YtResults, YtWaiver } from "@/components/knitting/prod-ytest";
import { GyList, GyEntry } from "@/components/knitting/prod-gy";
import { RrList, RrEntry } from "@/components/knitting/prod-rr";
import { SkList, SkEntry } from "@/components/knitting/prod-sk";
import { DbList, DbEntry } from "@/components/knitting/prod-db";
import {
  TwList,
  TwProgram,
  TwReceive,
  TwBill,
} from "@/components/knitting/prod-tw";
import { MlList, MlPlan } from "@/components/knitting/prod-ml";
import {
  OpProduction,
  OpSettings,
  OpStation,
} from "@/components/knitting/prod-op";
import {
  YdRegister,
  YdProgram,
  YdRevisions,
} from "@/components/knitting/prod-yd";
import {
  MoveList,
  MoveEntry,
  GreyRacks,
} from "@/components/knitting/prod-grey";
import {
  IssueReturnList,
  IssueReturnEntry,
} from "@/components/knitting/ystore-outward";
import * as actions from "./actions";

/*
 * Knit Production → Production (AnnotechERP 15/04): the four Fabric
 * Production Order screens (Bulk, Yarn-Dyed, Sample, Flat Bed Rib — one
 * register, Knit Programmes, Knitcard and the order's own form each),
 * Manual Roll Production Entry, Daily Knit Fabric Output and Grey Roll
 * Quality Inspection, the three Sub-Contract Knit Programmes and the
 * Sub-Contract Programme Receipt, and the yarn test chain (Knit Plan & Yarn
 * Test Indent, Yarn Test Result Register, Production Test Clearance,
 * Approval Without Testing), General Yarn Issue Indent and Yarn Return to
 * Yarn Store (the Yarn Store's own requisitions and return challans, from
 * the floor's side), Supplier Yarn Replacement Indent, Solid and Y/D Sample
 * Knit Plan, Direct Knit Fabric Booking and Yarn Twisting Entry. A document travels by its number
 * (?ref=FPO-…, ?order=FPO-…, ?ref=RS-…, ?ref=QCI-…, ?ref=SCP-…, ?ref=SCR-…,
 * ?ref=YTO-…, ?ref=YTS-…, ?ref=YIR-…, ?ref=YIRT-…, ?ref=YRR-…, ?ref=SYR-…,
 * ?ref=DFB-…, ?ref=TWP-…, ?tab=receive&ref=TWR-…, ?tab=bill&ref=TWB-…,
 * ?tab=program&ref=YDP-…, ?tab=revisions&ref=YDP-…). The Yarn Dyeing
 * Programme Register keeps its programme form beside Brickland's two tabs.
 * The grey movements (Flat Knit, Grey to Batch, Order-to-Order, Reuse and
 * the two to Leftover) and the Grey Fabric Racking Board read and write the
 * Fabric Store's grey ledger, under /knitting/fstore (?ref=CCT-…, GBT-…,
 * OOT-…, GRU-…, GLO-…, BLO-…).
 */

const text = (v) => (typeof v === "string" && v ? v : null);
const act = actions.prodAction;
const get = (path) => apiFetch(`/knitting/prod/${path}`);
const load = (path, no) =>
  no ? get(`${path}/${encodeURIComponent(no)}`).catch(() => null) : null;

function productionOrder(kind) {
  const base = `/knit-production/fabric-production-order-${kind === "FLAT_BED_RIB" ? "flat-bed-rib" : kind.toLowerCase()}`;
  return async ({ pr, tab, can, searchParams = {} }) => {
    const [options, running, all] = await Promise.all([
      get("options"),
      get(`orders?kind=${kind}&status=RUNNING`),
      get(`orders?kind=${kind}`),
    ]);
    return {
      tabs: [
        {
          key: "records",
          label: pr(`tabs.po_list.${kind}`),
          icon: "Table2",
          count: running.length,
        },
        { key: "kp", label: pr("tabs.kp"), icon: "ListTree" },
        { key: "knitcard", label: pr("tabs.knitcard"), icon: "ListChecks" },
        { key: "entry", label: pr("tabs.po_entry"), icon: "Plus" },
      ],
      body: async () => {
        if (tab === "entry") {
          const initial = await load("orders", text(searchParams.ref));
          const copy = initial
            ? null
            : await load("orders", text(searchParams.copy));
          return (
            <PoEntry
              key={initial?.po_no ?? (copy ? `copy-${copy.po_no}` : "new")}
              base={base}
              kind={kind}
              options={options}
              initial={initial}
              copy={copy}
              canManage={can.prod}
              act={act}
            />
          );
        }
        if (tab === "kp" || tab === "knitcard") {
          const po = text(searchParams.order);
          const kp = tab === "kp";
          const initial = po
            ? await get(
                `orders/${encodeURIComponent(po)}/${kp ? "programs" : "cards"}`,
              ).catch(() => null)
            : null;
          return (
            <Desk
              key={`${tab}-${po ?? "none"}`}
              mode={kp ? "kp" : "kc"}
              options={options}
              orders={all.filter((o) => o.status !== "CANCELLED")}
              initialPo={initial ? po : null}
              initial={initial}
              canManage={can.prod}
              act={act}
            />
          );
        }
        return (
          <PoRegister
            base={base}
            kind={kind}
            options={options}
            initialRows={running}
            canManage={can.prod}
            act={act}
          />
        );
      },
    };
  };
}

async function rollManual({ pr, tab, can, searchParams = {} }) {
  const [options, rows] = await Promise.all([
    get("rolls/options"),
    get("rolls"),
  ]);
  return {
    tabs: [
      {
        key: "records",
        label: pr("tabs.roll_list"),
        icon: "Table2",
        count: rows.length,
      },
      { key: "entry", label: pr("tabs.roll_entry"), icon: "Scale" },
    ],
    body: async () => {
      if (tab === "entry") {
        const initial = await load("roll-sheets", text(searchParams.ref));
        return (
          <RollEntry
            key={initial?.sheet_no ?? "new"}
            options={options}
            initial={initial}
            canManage={can.prod}
            act={act}
          />
        );
      }
      return (
        <RollList
          options={options}
          initialRows={rows}
          canManage={can.prod}
          act={act}
        />
      );
    },
  };
}

async function dailyOutput() {
  const [options, rows] = await Promise.all([
    get("rolls/options"),
    get("output"),
  ]);
  return {
    tabs: [],
    body: async () => (
      <DailyOutput options={options} initialRows={rows} act={act} />
    ),
  };
}

async function rollInspection({ pr, tab, can, searchParams = {} }) {
  const [options, rows] = await Promise.all([
    get("inspections/options"),
    get("inspections"),
  ]);
  return {
    tabs: [
      {
        key: "records",
        label: pr("tabs.qc_list"),
        icon: "Table2",
        count: rows.length,
      },
      { key: "entry", label: pr("tabs.qc_entry"), icon: "SearchCheck" },
    ],
    body: async () => {
      if (tab === "entry") {
        const initial = await load("inspections", text(searchParams.ref));
        return (
          <InspectionEntry
            key={initial?.qci_no ?? "new"}
            options={options}
            initial={initial}
            initialRoll={text(searchParams.roll)}
            canManage={can.prod}
            act={act}
          />
        );
      }
      return (
        <InspectionList
          options={options}
          initialRows={rows}
          canManage={can.prod}
          act={act}
        />
      );
    },
  };
}

function scProgramme(kind) {
  return async ({ sc, tab, can, searchParams = {} }) => {
    const [options, rows] = await Promise.all([
      get("sc/options"),
      get(`sc/${kind}`),
    ]);
    return {
      tabs: [
        {
          key: "records",
          label: sc("tabs.records"),
          icon: "Table2",
          count: rows.length,
        },
        { key: "entry", label: sc(`tabs.entry.${kind}`), icon: "Layers" },
      ],
      body: async () => {
        if (tab === "entry") {
          const initial = await load(`sc/${kind}`, text(searchParams.ref));
          return (
            <ScEntry
              key={initial?.program_no ?? "new"}
              kind={kind}
              options={options}
              initial={initial}
              canManage={can.prod}
              act={act}
            />
          );
        }
        return (
          <ScRegister
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

async function scReceipt({ sc, tab, can, searchParams = {} }) {
  const [options, rows] = await Promise.all([
    get("sc/options"),
    get("sc-receipts"),
  ]);
  return {
    tabs: [
      {
        key: "records",
        label: sc("tabs.rc_list"),
        icon: "Table2",
        count: rows.length,
      },
      { key: "entry", label: sc("tabs.rc_entry"), icon: "Layers" },
    ],
    body: async () => {
      if (tab === "entry") {
        const initial = await load("sc-receipts", text(searchParams.ref));
        return (
          <ScrEntry
            key={initial?.program_no ?? "new"}
            options={options}
            initial={initial}
            canManage={can.prod}
            act={act}
          />
        );
      }
      return (
        <ScrList
          options={options}
          initialRows={rows}
          canManage={can.prod}
          act={act}
        />
      );
    },
  };
}

async function yarnTestIndent({ yt, tab, can, searchParams = {} }) {
  const [options, rows] = await Promise.all([
    get("yarn-test/options"),
    get("yarn-test/orders"),
  ]);
  return {
    tabs: [
      {
        key: "records",
        label: yt("tabs.order_list"),
        icon: "Table2",
        count: rows.length,
      },
      { key: "entry", label: yt("tabs.order_entry"), icon: "Layers" },
    ],
    body: async () => {
      if (tab === "entry") {
        const initial = await load("yarn-test/orders", text(searchParams.ref));
        return (
          <YtoEntry
            key={initial?.order_no ?? "new"}
            options={options}
            initial={initial}
            canManage={can.prod}
            act={act}
          />
        );
      }
      return (
        <YtoList
          options={options}
          initialRows={rows}
          canManage={can.prod}
          act={act}
        />
      );
    },
  };
}

/** The register, Production Test Clearance and the approvals: one list, by basis. */
function yarnTests(slug, basis, listLabel) {
  const base = `/knit-production/${slug}`;
  return async ({ yt, tab, can, searchParams = {} }) => {
    const [options, rows] = await Promise.all([
      get("yarn-test/options"),
      get(`yarn-test/results${basis ? `?basis=${basis}` : ""}`),
    ]);
    const waiver = basis === "WAIVED";
    return {
      tabs: [
        {
          key: "records",
          label: yt(listLabel),
          icon: "Table2",
          count: rows.length,
        },
        {
          key: "entry",
          label: yt(waiver ? "tabs.waiver" : "tabs.test"),
          icon: "Layers",
        },
      ],
      body: async () => {
        if (tab === "entry" && waiver)
          return <YtWaiver options={options} canManage={can.prod} act={act} />;
        if (tab === "entry") {
          const initial = await load("yarn-test/tests", text(searchParams.ref));
          return (
            <YtTest
              key={initial?.test_no ?? "new"}
              base={base}
              options={options}
              initial={initial}
              canManage={can.prod}
              act={act}
            />
          );
        }
        return (
          <YtResults
            base={base}
            basis={basis}
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

/* The Yarn Store's paper (15/03) the floor writes too: its own API and action. */
const storeAct = actions.ystoreAction;
const storeGet = (path) => apiFetch(`/knitting/ystore/${path}`);
const storeLoad = (path, no) =>
  no ? storeGet(`${path}/${encodeURIComponent(no)}`).catch(() => null) : null;

async function generalIndent({ gy, tab, can, searchParams = {} }) {
  const [options, rows] = await Promise.all([
    storeGet("issue-reqs/options"),
    storeGet("issue-reqs"),
  ]);
  const manage = can.prod || can.store;
  return {
    tabs: [
      {
        key: "records",
        label: gy("tabs.records"),
        icon: "Table2",
        count: rows.length,
      },
      { key: "entry", label: gy("tabs.entry"), icon: "Layers" },
    ],
    body: async () => {
      if (tab === "entry") {
        const initial = await storeLoad("issue-reqs", text(searchParams.ref));
        return (
          <GyEntry
            key={initial?.req_no ?? "new"}
            options={options}
            initial={initial}
            canManage={manage}
            act={storeAct}
          />
        );
      }
      return (
        <GyList
          options={options}
          initialRows={rows}
          canManage={manage}
          act={storeAct}
        />
      );
    },
  };
}

const RETURN_BASE = "/knit-production/yarn-return-to-store";
/** The floor sends back and cancels; Accept and Reject stay the store's. */
const returnSteps = (store) => [
  ["issue", ["DRAFT"]],
  ...(store
    ? [
        ["accept", ["ISSUED"]],
        ["reject", ["ISSUED"]],
      ]
    : []),
  ["cancel", ["DRAFT", "ISSUED"]],
];

async function floorReturn({ gy, tab, can, searchParams = {} }) {
  const [options, rows] = await Promise.all([
    storeGet("outward/options"),
    storeGet("issue-returns"),
  ]);
  const manage = can.prod || can.store;
  return {
    tabs: [
      {
        key: "records",
        label: gy("tabs.records"),
        icon: "Table2",
        count: rows.length,
      },
      { key: "entry", label: gy("tabs.ret_entry"), icon: "Layers" },
    ],
    body: async () => {
      if (tab === "entry") {
        const initial = await storeLoad(
          "issue-returns",
          text(searchParams.ref),
        );
        return (
          <IssueReturnEntry
            key={initial?.return_no ?? "new"}
            base={RETURN_BASE}
            options={options}
            initial={initial}
            canManage={manage}
            act={storeAct}
          />
        );
      }
      return (
        <IssueReturnList
          base={RETURN_BASE}
          steps={returnSteps(can.store)}
          options={options}
          initialRows={rows}
          canManage={manage}
          act={storeAct}
        />
      );
    },
  };
}

async function replaceIndent({ rr, tab, can, searchParams = {} }) {
  const [options, rows] = await Promise.all([
    get("replace-reqs/options"),
    get("replace-reqs"),
  ]);
  return {
    tabs: [
      {
        key: "records",
        label: rr("tabs.list"),
        icon: "Table2",
        count: rows.length,
      },
      { key: "entry", label: rr("tabs.entry"), icon: "Layers" },
    ],
    body: async () => {
      if (tab === "entry") {
        const initial = await load("replace-reqs", text(searchParams.ref));
        return (
          <RrEntry
            key={initial?.req_no ?? "new"}
            options={options}
            initial={initial}
            canManage={can.prod}
            act={act}
          />
        );
      }
      return (
        <RrList
          options={options}
          initialRows={rows}
          canManage={can.prod}
          act={act}
        />
      );
    },
  };
}

function samplePlan(kind) {
  return async ({ sk, tab, can, searchParams = {} }) => {
    const [options, rows] = await Promise.all([
      get("sample-plans/options"),
      get(`sample-plans/${kind}`),
    ]);
    return {
      tabs: [
        {
          key: "records",
          label: sk("tabs.list"),
          icon: "Table2",
          count: rows.length,
        },
        { key: "entry", label: sk(`tabs.entry.${kind}`), icon: "Layers" },
      ],
      body: async () => {
        if (tab === "entry") {
          const initial = await load(
            `sample-plans/${kind}`,
            text(searchParams.ref),
          );
          return (
            <SkEntry
              key={initial?.plan_no ?? "new"}
              kind={kind}
              options={options}
              initial={initial}
              canManage={can.prod}
              act={act}
            />
          );
        }
        return (
          <SkList
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

async function directBooking({ db, tab, can, searchParams = {} }) {
  const [options, rows] = await Promise.all([
    get("direct-bookings/options"),
    get("direct-bookings"),
  ]);
  return {
    tabs: [
      {
        key: "records",
        label: db("tabs.list"),
        icon: "Table2",
        count: rows.length,
      },
      { key: "entry", label: db("tabs.entry"), icon: "Layers" },
    ],
    body: async () => {
      if (tab === "entry") {
        const initial = await load("direct-bookings", text(searchParams.ref));
        return (
          <DbEntry
            key={initial?.booking_no ?? "new"}
            options={options}
            initial={initial}
            canManage={can.prod}
            act={act}
          />
        );
      }
      return (
        <DbList
          options={options}
          initialRows={rows}
          canManage={can.prod}
          act={act}
        />
      );
    },
  };
}

async function yarnTwist({ tw, tab, can, searchParams = {} }) {
  const [options, rows] = await Promise.all([
    get("twist/options"),
    get("twist/programs"),
  ]);
  return {
    tabs: [
      {
        key: "records",
        label: tw("tabs.list"),
        icon: "Table2",
        count: rows.length,
      },
      { key: "entry", label: tw("tabs.entry"), icon: "Layers" },
      { key: "receive", label: tw("tabs.receive"), icon: "PackageCheck" },
      { key: "bill", label: tw("tabs.bill"), icon: "Receipt" },
    ],
    body: async () => {
      const ref = text(searchParams.ref);
      if (tab === "entry") {
        const initial = await load("twist/programs", ref);
        return (
          <TwProgram
            key={initial?.program_no ?? "new"}
            options={options}
            initial={initial}
            canManage={can.prod}
            act={act}
          />
        );
      }
      if (tab === "receive") {
        const [list, initial] = await Promise.all([
          get("twist/receives"),
          load("twist/receives", ref),
        ]);
        return (
          <TwReceive
            key={initial?.challan_no ?? "new"}
            options={options}
            initialRows={list}
            initial={initial}
            canManage={can.prod}
            act={act}
          />
        );
      }
      if (tab === "bill") {
        const [list, initial] = await Promise.all([
          get("twist/bills"),
          load("twist/bills", ref),
        ]);
        return (
          <TwBill
            key={initial?.bill_no ?? "new"}
            options={options}
            initialRows={list}
            initial={initial}
            canManage={can.prod}
            act={act}
          />
        );
      }
      return (
        <TwList
          options={options}
          initialRows={rows}
          canManage={can.prod}
          act={act}
        />
      );
    },
  };
}

async function machineLoading({ ml, tab, can }) {
  const [options, rows] = await Promise.all([
    get("machine-loads/options"),
    get("machine-loads"),
  ]);
  return {
    tabs: [
      {
        key: "records",
        label: ml("tabs.list"),
        icon: "Table2",
        count: rows.length,
      },
      { key: "entry", label: ml("tabs.plan"), icon: "CalendarRange" },
    ],
    body: async () => {
      if (tab === "entry") {
        const grid = await get("machine-loads/grid");
        return (
          <MlPlan
            options={options}
            initialGrid={grid}
            canManage={can.prod}
            act={act}
          />
        );
      }
      return <MlList options={options} initialRows={rows} />;
    },
  };
}

async function operatorRolls({ op, tab, can, searchParams = {} }) {
  const options = await get("stations/options");
  return {
    tabs: [
      {
        key: "records",
        label: op("tabs.production"),
        icon: "Scale",
        count: options.stations.length,
      },
      { key: "entry", label: op("tabs.settings"), icon: "Settings2" },
      { key: "station", label: op("tabs.station"), icon: "Save" },
    ],
    body: async () => {
      if (tab === "entry")
        return <OpSettings options={options} canManage={can.prod} act={act} />;
      if (tab === "station")
        return <OpStation options={options} canManage={can.prod} act={act} />;
      const code =
        text(searchParams.station) ??
        options.stations.find((s) => s.active && s.machines > 0)?.value ??
        null;
      const initial = await load("stations", code);
      return (
        <OpProduction
          key={initial?.code ?? "none"}
          options={options}
          initial={initial}
          canManage={can.prod}
          act={act}
        />
      );
    },
  };
}

async function ydProgramList({ yd, tab, can, searchParams = {} }) {
  const [options, rows] = await Promise.all([
    get("yd/options"),
    get("yd/programs"),
  ]);
  return {
    tabs: [
      {
        key: "records",
        label: yd("tabs.records"),
        icon: "Table2",
        count: rows.length,
      },
      { key: "revisions", label: yd("tabs.revisions"), icon: "History" },
      { key: "program", label: yd("tabs.program"), icon: "Palette" },
    ],
    body: async () => {
      const initial = await load("yd/programs", text(searchParams.ref));
      if (tab === "program")
        return (
          <YdProgram
            key={initial?.program_no ?? "new"}
            options={options}
            initial={initial}
            canManage={can.prod}
            act={act}
          />
        );
      if (tab === "revisions")
        return (
          <YdRevisions
            key={initial?.program_no ?? "none"}
            options={options}
            initial={initial}
            canManage={can.prod}
            act={act}
          />
        );
      return (
        <YdRegister
          options={options}
          initialRows={rows}
          canManage={can.prod}
          act={act}
        />
      );
    },
  };
}

const fstore = actions.fstoreAction;
const fget = (path) => apiFetch(`/knitting/fstore/${path}`);

/** A grey movement screen: its list and its form, the kind's own. */
function greyMove(kind) {
  const slug = {
    COLLAR_CUFF: "collar-cuff",
    TO_BATCH: "to-batch",
    ORDER: "order",
    REUSE: "reuse",
    TO_LEFTOVER: "to-leftover",
    BATCH_TO_LEFTOVER: "batch-leftover",
  }[kind];
  return async ({ gt, tab, can, searchParams = {} }) => {
    const [base, rows] = await Promise.all([
      fget("options"),
      fget(`moves/${slug}`),
    ]);
    const options = {
      ...base,
      moves: rows.map((r) => ({ value: r.transfer_no, label: r.transfer_no })),
    };
    const manage = can.prod || can.fstore;
    return {
      tabs: [
        {
          key: "records",
          label: gt(`tabs.${kind}_list`),
          icon: "Table2",
          count: rows.length,
        },
        { key: "entry", label: gt(`tabs.${kind}_entry`), icon: "Layers" },
      ],
      body: async () => {
        if (tab === "entry") {
          const no = text(searchParams.ref);
          const initial = no
            ? await fget(`moves/${slug}/${encodeURIComponent(no)}`).catch(
                () => null,
              )
            : null;
          // An opened draft's pickers: what its From Order / Store still holds.
          const q = (o) =>
            new URLSearchParams(Object.entries(o).filter(([, v]) => v));
          const source = !initial
            ? []
            : kind === "COLLAR_CUFF"
              ? await fget(
                  `floor-rolls?order=${encodeURIComponent(initial.order_id)}`,
                )
              : await fget(
                  `lots?${q({
                    store: initial.from_store_id,
                    order: kind === "REUSE" ? "" : initial.order_id,
                    rolls: kind === "TO_BATCH" ? "1" : "",
                    batch:
                      kind === "TO_BATCH"
                        ? "none"
                        : kind === "BATCH_TO_LEFTOVER"
                          ? "only"
                          : "",
                  })}`,
                ).catch(() => []);
          return (
            <MoveEntry
              key={initial?.transfer_no ?? "new"}
              kind={kind}
              options={options}
              initial={initial}
              initialSource={source}
              canManage={manage}
              act={fstore}
            />
          );
        }
        return (
          <MoveList
            kind={kind}
            options={options}
            initialRows={rows}
            canManage={manage}
            act={fstore}
          />
        );
      },
    };
  };
}

async function greyRacks() {
  const options = await fget("options");
  return {
    tabs: [],
    body: async () => <GreyRacks options={options} act={fstore} />,
  };
}

const PROD_SCREENS = {
  "fabric-production-order-bulk": productionOrder("BULK"),
  "fabric-production-order-yd": productionOrder("YD"),
  "fabric-production-order-sample": productionOrder("SAMPLE"),
  "fabric-production-order-flat-bed-rib": productionOrder("FLAT_BED_RIB"),
  "roll-production-manual": rollManual,
  "daily-fabric-production": dailyOutput,
  "grey-fab-roll-inspection": rollInspection,
  "issue-sc-knitting-program-bulk-solid": scProgramme("BULK_SOLID"),
  "issue-sc-knitting-program-sample-solid": scProgramme("SAMPLE_SOLID"),
  "issue-sc-knitting-program-party-yd": scProgramme("PARTY_YD"),
  "receive-sc-program-from-party": scReceipt,
  "knit-plan-requisition-for-yarn-test": yarnTestIndent,
  "yarn-test-register": yarnTests("yarn-test-register", null, "tabs.register"),
  "test-for-production": yarnTests(
    "test-for-production",
    "LAB",
    "tabs.test_list",
  ),
  "approval-without-test": yarnTests(
    "approval-without-test",
    "WAIVED",
    "tabs.waived_list",
  ),
  "general-requisition-for-yarn-issue": generalIndent,
  "yarn-return-to-store": floorReturn,
  "requisition-for-yarn-replace-by-supplier": replaceIndent,
  "knit-plan-for-sample-solid": samplePlan("SOLID"),
  "knit-plan-for-sample-yd": samplePlan("YD"),
  "direct-fabric-booking": directBooking,
  "yarn-twist": yarnTwist,
  "machine-loading-plan": machineLoading,
  "roll-production-operator": operatorRolls,
  "yd-program-list": ydProgramList,
  "flat-knit-grey-transfer": greyMove("COLLAR_CUFF"),
  "grey-fabric-transfer-grey-to-batch": greyMove("TO_BATCH"),
  "grey-fabric-transfer-order-to-order": greyMove("ORDER"),
  "grey-fabric-reuse-from-left-over-store": greyMove("REUSE"),
  "grey-fabric-transfer-grey-store-to-left-over": greyMove("TO_LEFTOVER"),
  "grey-fabric-transfer-batch-store-to-left-over":
    greyMove("BATCH_TO_LEFTOVER"),
  "grey-fabric-racking-board": greyRacks,
};

export { PROD_SCREENS };
