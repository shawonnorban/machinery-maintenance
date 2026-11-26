import { apiFetch } from "@/lib/api-server";
import { RequestList } from "@/components/approval/request-list";
import { RecordsGrid } from "@/components/erp/records-grid";
import { FilterBar } from "@/components/erp/filter-bar";
import { TypeManager } from "@/components/approval/type-manager";
import { RequestDashboard } from "@/components/approval/request-dashboard";
import * as actions from "./actions";

/*
 * Approval Center (AnnotechERP 04): the seven request lists, the history,
 * the dashboard and the request types.
 */

const ENTITY_TYPES = ["WORK_ORDER", "PURCHASE_REQUISITION", "PURCHASE_ORDER", "PRODUCTION_ISSUE", "PURCHASE_RETURN", "STOCK_COUNT", "FABRIC_BOOKING", "IMPORT_FILE", "PRE_BUDGET", "EXPORT_STATEMENT_EDIT"];

const LISTS = {
  "all-requests": { scope: "all", create: true },
  "pending-requests": { scope: "pending" },
  "my-submitted-requests": { scope: "mine", create: true },
  "assigned-to-me": { scope: "assigned" },
  "approved-requests": { scope: "approved", decided: "approved_by" },
  "rejected-requests": { scope: "rejected", decided: "rejected_by", reason: "rejection_reason" },
  "returned-requests": { scope: "returned", decided: "returned_by", reason: "revision_reason" },
};

function money(n) {
  return n === null || n === undefined ? null : Number(n).toLocaleString("en-US", { maximumFractionDigits: 2 });
}

/** A row as the grid shows it: the composed cells Brickland has. */
function present(row, t) {
  const qty = row.quantity !== null && row.quantity !== undefined ? `${money(row.quantity)} ${row.unit ?? ""}`.trim() : null;
  const amount = row.amount !== null && row.amount !== undefined ? `${money(row.amount)} ${row.currency ?? ""}`.trim() : null;
  return {
    ...row,
    type_label: row.type ?? t(`center.entities.${row.entity_type}`),
    buyer_order_style: [row.buyer, row.order_no, row.style_no].filter(Boolean).join(" · ") || null,
    qty_amount: [qty, amount].filter(Boolean).join(" / ") || null,
  };
}

function listScreen(config) {
  return async function renderList({ t, key, searchParams }) {
    const query = new URLSearchParams({ scope: config.scope });
    for (const name of ["q", "type", "status", "priority", "from", "to"]) if (searchParams[name]) query.set(name, searchParams[name]);
    const [list, options] = await Promise.all([apiFetch(`/approval-center/requests?${query}`, { includeMeta: true }), apiFetch("/approval-center/form-options")]);
    const rows = list.data.map((r) => present(r, t));
    const columns = [
      { key: "request_no", label: t("center.cols.request_id"), type: "mono" },
      { key: "type_label", label: t("center.cols.type") },
      { key: "requested_by", label: t("center.cols.requested_by") },
      { key: "buyer_order_style", label: t("center.cols.buyer_order_style") },
      { key: "qty_amount", label: t("center.cols.qty_amount"), align: "right" },
      config.decided ? { key: "decided_by", label: t(`center.cols.${config.decided}`) } : { key: "request_to", label: t("center.cols.request_to") },
      ...(config.reason ? [{ key: "reason", label: t(`center.cols.${config.reason}`) }] : []),
      { key: config.decided ? "decided_at" : "date", label: t("center.cols.date"), type: "datetime" },
      { key: "status", label: t("center.cols.status"), type: "badge" },
    ];
    return (
      <RequestList
        scope={config.scope}
        basePath={`/approval-center/${key}`}
        rows={rows}
        stats={list.meta?.stats ?? {}}
        options={{ ...options, entity_types: ENTITY_TYPES }}
        values={searchParams}
        columns={columns}
        canCreate={Boolean(config.create)}
        saveTicket={actions.saveTicket}
        loadRequest={actions.loadRequest}
        loadTicket={actions.loadTicket}
        decide={actions.decide}
        cancelTicket={actions.cancelTicket}
        deleteTicket={actions.deleteTicket}
      />
    );
  };
}

async function approvalHistory({ t, te, searchParams }) {
  const query = new URLSearchParams();
  for (const name of ["q", "action", "from", "to"]) if (searchParams[name]) query.set(name, searchParams[name]);
  const rows = await apiFetch(`/approval-center/history${query.size ? `?${query}` : ""}`);
  const actionsList = ["SUBMITTED", "RESUBMITTED", "APPROVED", "REJECTED", "RETURNED", "CANCELLED", "EXPIRED", "DELEGATED"];
  const actionLabels = Object.fromEntries(actionsList.map((a) => [a, t(`center.actions.${a}`)]));
  const statusLabels = Object.fromEntries(["DRAFT", "PENDING", "APPROVED", "REJECTED", "RETURNED", "CANCELLED", "EXPIRED"].map((s) => [s, t(`center.statuses.${s}`)]));
  return (
    <div className="rounded-lg border border-border bg-surface p-5">
      <h2 className="text-base font-semibold text-foreground">{t("center.history_title")}</h2>
      <p className="mb-4 text-[13px] text-foreground-muted">{t("center.history_count", { count: rows.length })}</p>
      <FilterBar
        basePath="/approval-center/approval-history"
        values={searchParams}
        labels={{ show: t("center.search"), reset: te("reset") }}
        fields={[
          { name: "q", label: t("center.search"), placeholder: t("center.history_search") },
          {
            name: "action",
            label: t("center.cols.action"),
            type: "select",
            options: actionsList.map((a) => ({ value: a, label: actionLabels[a] })),
            placeholder: t("center.all_actions"),
          },
          { name: "from", label: t("center.from"), type: "date" },
          { name: "to", label: t("center.to"), type: "date" },
        ]}
      />
      <RecordsGrid
        exportName="approval-history"
        rows={rows.map((r) => ({ ...r, change: r.previous_status || r.new_status ? `${statusLabels[r.previous_status] ?? "—"} → ${statusLabels[r.new_status] ?? "—"}` : null }))}
        columns={[
          { key: "at", label: t("center.cols.when"), type: "datetime" },
          { key: "request_no", label: t("center.cols.request_id"), type: "mono" },
          { key: "action", label: t("center.cols.action"), type: "badge", labels: actionLabels },
          { key: "by", label: t("center.cols.action_by") },
          { key: "change", label: t("center.cols.change") },
          { key: "level", label: t("center.cols.level"), type: "number" },
          { key: "comment", label: t("center.cols.comment") },
        ]}
      />
    </div>
  );
}

async function requestTypes({ t }) {
  const [rows, options] = await Promise.all([apiFetch("/erp/approval-request-types"), apiFetch("/approval-center/form-options")]);
  return <TypeManager rows={rows} options={options} create={actions.createRequestType} update={actions.updateRequestType} remove={actions.deleteRequestType} />;
}

async function requestDashboard({ t }) {
  const data = await apiFetch("/approval-center/dashboard");
  const keys = (base, list) => Object.fromEntries(list.map((k) => [k, t(`${base}.${k}`)]));
  const panels = [
    "by_module",
    "by_status",
    "by_priority",
    "by_buyer",
    "by_department",
    "by_requester",
    "by_approver",
    "rejected_reasons",
    "revision_reasons",
    "turnaround",
    "overdue",
  ];
  return (
    <RequestDashboard
      data={data}
      labels={{
        tiles: keys("center.dash.tiles", ["total", "pending", "pending_on_me", "approved", "rejected", "returned", "overdue", "today", "this_month", "avg_turnaround_hours"]),
        panels: keys("center.dash.panels", panels),
        hints: keys("center.dash.hints", panels),
        statuses: keys("center.statuses", ["DRAFT", "PENDING", "APPROVED", "REJECTED", "RETURNED", "CANCELLED", "EXPIRED"]),
        priorities: keys("center.priorities", ["LOW", "MEDIUM", "HIGH", "URGENT"]),
        entities: keys("center.entities", [...ENTITY_TYPES, "MANUAL_REQUEST", "INVENTORY_TRANSFER", "COST_ENTRY"]),
        empty: t("center.dash.empty"),
        nothing_overdue: t("center.dash.nothing_overdue"),
        nothing_decided: t("center.dash.nothing_decided"),
        hours: t("center.dash.hours"),
        minutes: t("center.dash.minutes"),
        type: t("center.cols.type"),
        count: t("center.dash.count"),
        avg: t("center.dash.avg"),
        max: t("center.dash.max"),
        days: t("center.dash.days"),
        request_to: t("center.cols.request_to"),
        due: t("center.f.due_date"),
      }}
    />
  );
}

const SCREENS = {
  ...Object.fromEntries(Object.entries(LISTS).map(([key, config]) => [key, listScreen(config)])),
  "approval-history": approvalHistory,
  "request-dashboard": requestDashboard,
  "request-types": requestTypes,
};

export { SCREENS };
