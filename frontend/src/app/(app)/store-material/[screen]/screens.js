import { apiFetch } from "@/lib/api-server";
import { StoreDashboard } from "@/components/storematerial/dashboard";
import { InHouseGrn } from "@/components/storematerial/grn";
import {
  FabricWarehouse,
  TrimsWarehouse,
  StockLedger,
} from "@/components/storematerial/warehouse";
import {
  MaterialOut,
  MaterialReturn,
  StockAdjustment,
} from "@/components/storematerial/issue";
import { Traceability, StoreReports } from "@/components/storematerial/trace";
import * as actions from "./actions";

/*
 * Store & Material (AnnotechERP 09), one Brickland screen per route. A GRN
 * opens by its number (`?grn=GRN-2026-00005`), never its key.
 */

const safe = (path) => apiFetch(path).catch(() => null);

async function storeDashboard() {
  const [data, filters] = await Promise.all([
    apiFetch("/store-material/dashboard"),
    apiFetch("/store-material/filters"),
  ]);
  return (
    <StoreDashboard data={data} filters={filters} act={actions.storeAction} />
  );
}

async function inHouseGrn({ searchParams = {}, permissions = [] }) {
  const [options, initial] = await Promise.all([
    apiFetch("/store-material/grns/options"),
    searchParams.grn
      ? safe(`/store-material/grns/${encodeURIComponent(searchParams.grn)}`)
      : null,
  ]);
  return (
    <InHouseGrn
      key={initial?.grn_no ?? "new"}
      options={options}
      initial={initial}
      canReceive={permissions.includes("storematerial.receive")}
      canManage={permissions.includes("storematerial.manage")}
      act={actions.storeAction}
    />
  );
}

async function fabricWarehouse() {
  const [data, filters] = await Promise.all([
    apiFetch("/store-material/fabric-warehouse"),
    apiFetch("/store-material/filters"),
  ]);
  return (
    <FabricWarehouse data={data} filters={filters} act={actions.storeAction} />
  );
}

async function trimsWarehouse() {
  const [data, filters] = await Promise.all([
    apiFetch("/store-material/trims-warehouse"),
    apiFetch("/store-material/filters"),
  ]);
  return (
    <TrimsWarehouse data={data} filters={filters} act={actions.storeAction} />
  );
}

async function stockLedger() {
  const [data, filters] = await Promise.all([
    apiFetch("/store-material/stock-ledger"),
    apiFetch("/store-material/filters"),
  ]);
  return (
    <StockLedger data={data} filters={filters} act={actions.storeAction} />
  );
}

async function materialOut({ permissions = [] }) {
  const data = await apiFetch("/store-material/issue-options");
  return (
    <MaterialOut
      data={data}
      canIssue={permissions.includes("storematerial.issue")}
      act={actions.storeAction}
    />
  );
}

async function materialReturn({ permissions = [] }) {
  const data = await apiFetch("/store-material/issue-options");
  return (
    <MaterialReturn
      data={data}
      canIssue={permissions.includes("storematerial.issue")}
      act={actions.storeAction}
    />
  );
}

async function stockAdjustment({ permissions = [] }) {
  const data = await apiFetch("/store-material/issue-options");
  return (
    <StockAdjustment
      data={data}
      canAdjust={permissions.includes("storematerial.adjust")}
      canManage={permissions.includes("storematerial.manage")}
      act={actions.storeAction}
    />
  );
}

async function traceability() {
  return <Traceability act={actions.storeAction} />;
}

const REPORT_KEYS = [
  "stock-summary",
  "grn",
  "material-out",
  "material-return",
  "stock-ledger",
  "traceability",
  "fabric-roll",
  "low-stock",
];

async function storeReports({ searchParams = {} }) {
  const report = REPORT_KEYS.includes(searchParams.report)
    ? searchParams.report
    : "stock-summary";
  const [initial, filters] = await Promise.all([
    apiFetch(`/store-material/reports/${report}`),
    apiFetch("/store-material/filters"),
  ]);
  return (
    <StoreReports
      initial={initial}
      filters={filters}
      act={actions.storeAction}
    />
  );
}

const SCREENS = {
  "store-dashboard": storeDashboard,
  "in-house-grn": inHouseGrn,
  "fabric-warehouse": fabricWarehouse,
  "trims-warehouse": trimsWarehouse,
  "stock-ledger": stockLedger,
  "material-out": materialOut,
  "material-return": materialReturn,
  "stock-adjustment": stockAdjustment,
  traceability,
  "store-reports": storeReports,
};

export { SCREENS };
