import { apiFetch } from "@/lib/api-server";
import { getT } from "@/lib/i18n-server";
import { ProtoList } from "@/components/prototype/proto-kit";
import { DaySheetForm, DaySheetList, TargetPlanForm } from "@/components/prototype/daily-target";
import { MaterialIssueForm, MaterialRequisitionForm, SampleProgramsBrowser } from "@/components/prototype/material";
import { BundleCardForm, CuttingListBrowser } from "@/components/prototype/bundle-card";
import { BundleLookup, SampleCardBoard, ScanDesk, TrackingUpdate } from "@/components/prototype/tracking";
import * as actions from "./actions";

/*
 * Prototype Desk → OPERATION → Sample Material & Production (AnnotechERP
 * 07/03). Prototype Material Requisition: Records · Sample Fabric
 * Requisition (`?tab=requisition&mr=MR-2026-00001`) · Sample Programs ·
 * Material Issue (`?mi=MI-…`). Sample Bundle Card Issue: Records · Bundle
 * Card Creation (`?tab=card&card=SBC-2026100001`) · Cutting List. Sample Bundle
 * Tracking: Records · Scan Desk · Tracking Update · Bundle Lookup · Sample Card.
 * Sample Room Daily Target: Records (the day
 * sheets), Target Setting (a day sheet, addressed by its date —
 * `?tab=target&date=2026-10-22`) and Weekly / Monthly Plan (`?plan=TP-…`).
 */

const ACTIONS = { load: actions.loadProto, save: actions.saveProto, act: actions.actProto, remove: actions.removeProto };
const unique = (rows, field) => [...new Set(rows.map((r) => r[field]).filter(Boolean))].sort().map((v) => ({ value: v, label: v }));
const safe = async (path) => {
  try {
    return await apiFetch(path);
  } catch {
    return null;
  }
};

/** Prototype Material Requisition: Records · Sample Fabric Requisition · Sample Programs · Material Issue. */
async function materialRequisition({ tab, searchParams, permissions }) {
  const t = await getT("prototype");
  const can = (code) => permissions.includes(code);
  const [rows, options] = await Promise.all([apiFetch("/prototype/material-requisitions"), apiFetch("/prototype/material-requisitions/options")]);
  const tabs = [
    { key: "records", label: t("ui.records"), count: rows.length },
    { key: "requisition", label: t("ui.mr.form_tab") },
    { key: "programs", label: t("ui.mr.programs_tab") },
    { key: "issue", label: t("ui.mr.issue_tab") },
  ];
  return {
    tabs,
    body: async () => {
      if (tab === "requisition") {
        const initial = searchParams.mr ? await safe(`/prototype/material-requisitions/${encodeURIComponent(searchParams.mr)}`) : null;
        const existing = rows.map((r) => ({ value: r.mr_no, label: `${r.mr_no} — ${r.sample_no ?? ""} · ${t(`mr_state.${r.state}`)}` }));
        return <MaterialRequisitionForm key={initial?.mr_no ?? "new"} options={options} initial={initial} existing={existing} canManage={can("prototype.material.manage")} canApprove={can("prototype.material.approve")} actions={ACTIONS} />;
      }
      if (tab === "programs") return <SampleProgramsBrowser programs={options.programs} />;
      if (tab === "issue") {
        const [issues, issueOptions] = await Promise.all([apiFetch("/prototype/material-issues"), apiFetch("/prototype/material-issues/options")]);
        const initial = searchParams.mi ? await safe(`/prototype/material-issues/${encodeURIComponent(searchParams.mi)}`) : null;
        const existing = issues.map((r) => ({ value: r.issue_no, label: `${r.issue_no} — ${r.mr_no} · ${r.issue_date}` }));
        return <MaterialIssueForm key={initial?.issue_no ?? "new"} options={issueOptions} initial={initial} existing={existing} canIssue={can("prototype.material.issue")} actions={ACTIONS} />;
      }
      const data = rows.map((r) => ({
        ...r,
        all: true,
        awaiting: ["SUBMITTED", "DEPT_APPROVED", "STORE_VERIFIED"].includes(r.state),
        to_issue: ["APPROVED", "PARTIALLY_ISSUED"].includes(r.state),
        shortage: r.short_lines > 0,
        issued_label: `${r.issued_pct}%`,
      }));
      return (
        <ProtoList
          rows={data}
          rowKey="mr_no"
          exportName="prototype-material-requisitions"
          title={t("ui.mr.list")}
          subtitle={t("ui.mr.list_hint")}
          emptyText={t("ui.mr.empty")}
          addHref={can("prototype.material.manage") ? "?tab=requisition" : undefined}
          addLabel={t("ui.mr.new")}
          openHref="?tab=requisition&mr="
          filters={[
            { key: "state", label: t("ui.mr.f.state"), field: "state", options: options.states.map((v) => ({ value: v, label: t(`mr_state.${v}`) })) },
            { key: "sample", label: t("ui.mr.f.sample_no"), field: "sample_no", options: unique(rows, "sample_no") },
            { key: "date", label: t("ui.mr.col.req_date"), field: "req_date", range: true },
          ]}
          pills={[
            { key: "all", label: t("ui.mr.pill.all"), field: "all", value: true },
            { key: "awaiting", label: t("ui.mr.pill.awaiting"), field: "awaiting", value: true },
            { key: "to_issue", label: t("ui.mr.pill.to_issue"), field: "to_issue", value: true },
            { key: "fully", label: t("ui.mr.pill.fully"), field: "state", value: "FULLY_ISSUED" },
            { key: "shortage", label: t("ui.mr.pill.shortage"), field: "shortage", value: true },
          ]}
          columns={[
            { key: "mr_no", label: t("ui.mr.col.mr_no"), type: "mono" },
            { key: "req_date", label: t("ui.mr.col.req_date"), type: "date" },
            { key: "sample_no", label: t("ui.mr.col.sample_no"), type: "mono" },
            { key: "required_date", label: t("ui.mr.col.required_date"), type: "date" },
            { key: "requested_by_name", label: t("ui.mr.col.requested_by") },
            { key: "requisition_type", label: t("ui.mr.col.requisition_type") },
            { key: "requisition_to", label: t("ui.mr.col.requisition_to") },
            { key: "delivery_to", label: t("ui.mr.col.delivery_to") },
            { key: "programs_count", label: t("ui.mr.col.programs"), type: "number" },
            { key: "items_count", label: t("ui.mr.col.items"), type: "number" },
            { key: "short_lines", label: t("ui.mr.col.short_lines"), type: "number" },
            { key: "total_req", label: t("ui.mr.col.req_qty"), type: "number" },
            { key: "issued_label", label: t("ui.mr.col.issued_pct"), align: "right" },
            { key: "state", label: t("ui.mr.col.state"), type: "badge", labels: Object.fromEntries(options.states.map((v) => [v, t(`mr_state.${v}`)])) },
            { key: "remarks", label: t("ui.mr.col.remarks") },
          ]}
        />
      );
    },
  };
}

/** Sample Bundle Card Issue: Records · Bundle Card Creation · Cutting List. */
async function bundleCard({ tab, searchParams, permissions }) {
  const t = await getT("prototype");
  const canManage = permissions.includes("prototype.bundle.manage");
  const [rows, options] = await Promise.all([apiFetch("/prototype/bundle-cards"), apiFetch("/prototype/bundle-cards/options")]);
  const tabs = [
    { key: "records", label: t("ui.records"), count: rows.length },
    { key: "card", label: t("ui.bc.form_tab") },
    { key: "cutting", label: t("ui.bc.cutting_tab") },
  ];
  return {
    tabs,
    body: async () => {
      if (tab === "card") {
        const initial = searchParams.card ? await safe(`/prototype/bundle-cards/${encodeURIComponent(searchParams.card)}`) : null;
        const existing = rows.map((r) => ({ value: r.card_no, label: `${r.card_no} — ${[r.sample_no, r.buyer, r.table_code, `#${r.cutting_no}`].filter(Boolean).join(" · ")} · ${t(`bundle_status.${r.bundle_status}`)}` }));
        return <BundleCardForm key={initial?.card_no ?? "new"} options={options} initial={initial} existing={existing} canManage={canManage} actions={ACTIONS} />;
      }
      if (tab === "cutting") return <CuttingListBrowser tables={options.tables} cards={rows} />;
      const data = rows.map((r) => ({
        ...r,
        all: true,
        on_floor: ["ISSUED", "RECEIVED", "IN_PRODUCTION"].includes(r.bundle_status),
        overdue_filter: r.overdue ? "OVERDUE" : "ON_TIME",
        style_label: r.style_name ?? r.style,
      }));
      return (
        <ProtoList
          rows={data}
          rowKey="card_no"
          exportName="sample-bundle-cards"
          title={t("ui.bc.list")}
          subtitle={t("ui.bc.list_hint")}
          emptyText={t("ui.bc.empty")}
          addHref={canManage ? "?tab=card" : undefined}
          addLabel={t("ui.bc.new")}
          openHref="?tab=card&card="
          filters={[
            { key: "status", label: t("ui.bc.f.bundle_status"), field: "bundle_status", options: options.bundle_statuses.map((v) => ({ value: v, label: t(`bundle_status.${v}`) })) },
            { key: "sample", label: t("ui.bc.f.sample_no"), field: "sample_no", options: unique(rows, "sample_no") },
            { key: "overdue", label: t("ui.bc.f.overdue"), field: "overdue_filter", options: [{ value: "OVERDUE", label: t("ui.bc.overdue_yes") }, { value: "ON_TIME", label: t("ui.bc.overdue_no") }] },
            { key: "date", label: t("ui.bc.col.cut_date"), field: "cutting_date", range: true },
          ]}
          pills={[
            { key: "cards", label: t("ui.bc.pill.cards"), field: "all", value: true },
            { key: "floor", label: t("ui.bc.pill.floor"), field: "on_floor", value: true },
            { key: "hold", label: t("ui.bc.pill.hold"), field: "bundle_status", value: "HOLD" },
            { key: "overdue", label: t("ui.bc.pill.overdue"), field: "overdue", value: true },
          ]}
          columns={[
            { key: "card_no", label: t("ui.bc.col.card_no"), type: "mono" },
            { key: "cutting_date", label: t("ui.bc.col.cut_date"), type: "date" },
            { key: "sample_no", label: t("ui.bc.col.sample_no"), type: "mono" },
            { key: "buyer", label: t("ui.bc.col.buyer") },
            { key: "style_label", label: t("ui.bc.col.style") },
            { key: "order_no", label: t("ui.bc.col.order"), type: "mono" },
            { key: "cutting_no", label: t("ui.bc.col.cutting_no") },
            { key: "table_code", label: t("ui.bc.col.table") },
            { key: "fabric", label: t("ui.bc.col.fabric") },
            { key: "bundles_count", label: t("ui.bc.col.bundles"), type: "number" },
            { key: "req_qty", label: t("ui.bc.col.req_qty"), type: "number" },
            { key: "cutting_qty", label: t("ui.bc.col.cutting_qty"), type: "number" },
            { key: "operator_name", label: t("ui.bc.col.operator") },
            { key: "due_date", label: t("ui.bc.col.due_date"), type: "date" },
            { key: "bundle_status", label: t("ui.bc.col.bundle_status"), type: "badge", labels: Object.fromEntries(options.bundle_statuses.map((v) => [v, t(`bundle_status.${v}`)])) },
            { key: "is_finalized", label: t("ui.bc.col.is_finalized"), type: "yesno" },
          ]}
        />
      );
    },
  };
}

/** Sample Bundle Tracking: Records · Scan Desk · Tracking Update · Bundle Lookup · Sample Card. */
async function bundleTracking({ tab, permissions }) {
  const t = await getT("prototype");
  const canManage = permissions.includes("prototype.tracking.manage");
  const [rows, options] = await Promise.all([apiFetch("/prototype/tracking"), apiFetch("/prototype/tracking/options")]);
  const tabs = [
    { key: "records", label: t("ui.records"), count: rows.length },
    { key: "scan", label: t("ui.bt.scan_tab") },
    { key: "update", label: t("ui.bt.update_tab") },
    { key: "lookup", label: t("ui.bt.lookup_tab") },
    { key: "card", label: t("ui.bt.card_tab") },
  ];
  return {
    tabs,
    body: async () => {
      if (tab === "scan") {
        const desk = await apiFetch(`/prototype/tracking/desk/${options.processes[0]}`);
        return <ScanDesk processes={options.processes} initial={desk} canManage={canManage} track={actions.trackProto} />;
      }
      if (tab === "update") return <TrackingUpdate bundles={await apiFetch("/prototype/tracking/bundles")} options={options} canManage={canManage} track={actions.trackProto} />;
      if (tab === "lookup") return <BundleLookup bundles={await apiFetch("/prototype/tracking/lookup")} options={options} />;
      if (tab === "card") return <SampleCardBoard orders={await apiFetch("/prototype/tracking/sample-cards")} canManage={canManage} track={actions.trackProto} />;
      const data = rows.map((r) => ({ ...r, all: true, delay_label: r.delay_hours ?? null }));
      return (
        <ProtoList
          rows={data}
          rowKey="id"
          exportName="sample-bundle-movements"
          title={t("ui.bt.list")}
          subtitle={t("ui.bt.list_hint")}
          emptyText={t("ui.bt.empty")}
          filters={[
            { key: "process", label: t("ui.bt.f.process"), field: "process", options: options.processes.map((v) => ({ value: v, label: t(`process.${v}`) })) },
            { key: "status", label: t("ui.bt.f.status"), field: "status", options: options.statuses.map((v) => ({ value: v, label: t(`move_status.${v}`) })) },
            { key: "sample", label: t("ui.bt.f.sample_no"), field: "sample_no", options: unique(rows, "sample_no") },
            { key: "date", label: t("ui.bt.f.date"), field: "movement_date", range: true },
          ]}
          pills={[
            { key: "movements", label: t("ui.bt.pill.movements"), field: "all", value: true },
            { key: "delayed", label: t("ui.bt.pill.delayed"), field: "delayed", value: true },
            { key: "hours", label: t("ui.bt.pill.delay_hours"), sum: "delay_hours" },
          ]}
          columns={[
            { key: "movement_date", label: t("ui.bt.col.date"), type: "date" },
            { key: "process", label: t("ui.bt.col.process"), type: "badge", labels: Object.fromEntries(options.processes.map((v) => [v, t(`process.${v}`)])) },
            { key: "bundle_no", label: t("ui.bt.col.bundle"), type: "mono" },
            { key: "sample_no", label: t("ui.bt.col.sample_no"), type: "mono" },
            { key: "card_no", label: t("ui.bt.col.card_no"), type: "mono" },
            { key: "order_no", label: t("ui.bt.col.order"), type: "mono" },
            { key: "sample_type", label: t("ui.bt.col.sample_type") },
            { key: "color", label: t("ui.bt.col.color") },
            { key: "size", label: t("ui.bt.col.size") },
            { key: "qty", label: t("ui.bt.col.qty"), type: "number" },
            { key: "operator_name", label: t("ui.bt.col.operator") },
            { key: "status", label: t("ui.bt.col.status"), type: "badge", labels: Object.fromEntries(options.statuses.map((v) => [v, t(`move_status.${v}`)])) },
            { key: "delay_label", label: t("ui.bt.col.delay_hrs"), type: "number" },
            { key: "delay_reason", label: t("ui.bt.col.delay_reason") },
            { key: "scanned_at", label: t("ui.bt.col.scanned_at"), type: "datetime" },
          ]}
        />
      );
    },
  };
}

/** Sample Room Daily Target: Records · Target Setting · Weekly / Monthly Plan. */
async function dailyTarget({ tab, searchParams, permissions }) {
  const t = await getT("prototype");
  const canManage = permissions.includes("prototype.target.manage");
  const [sheets, options] = await Promise.all([apiFetch("/prototype/day-sheets"), apiFetch("/prototype/day-sheets/options")]);
  const tabs = [
    { key: "records", label: t("ui.records"), count: sheets.length },
    { key: "target", label: t("ui.dt.target_tab") },
    { key: "plan", label: t("ui.dt.plan_tab") },
  ];
  return {
    tabs,
    body: async () => {
      if (tab === "target") {
        const initial = searchParams.date ? await safe(`/prototype/day-sheets/${encodeURIComponent(searchParams.date)}`) : null;
        const existing = sheets.map((r) => ({ value: r.target_date, label: `${r.target_date} — ${[r.sample_room, r.shift ? t(`shift.${r.shift}`) : null, t(`day_state.${r.state}`)].filter(Boolean).join(" · ")}` }));
        return <DaySheetForm key={initial?.target_date ?? "new"} options={options} initial={initial} existing={existing} canManage={canManage} actions={ACTIONS} />;
      }
      if (tab === "plan") {
        const [plans, planOptions] = await Promise.all([apiFetch("/prototype/target-plans"), apiFetch("/prototype/target-plans/options")]);
        const initial = searchParams.plan ? await safe(`/prototype/target-plans/${encodeURIComponent(searchParams.plan)}`) : null;
        const existing = plans.map((p) => ({ value: p.plan_no, label: `${p.plan_no} — ${t(`period.${p.period}`)} · ${p.period_start} → ${p.period_end}${p.sample_room ? ` · ${p.sample_room}` : ""}` }));
        return <TargetPlanForm key={initial?.plan_no ?? "new"} options={planOptions} initial={initial} existing={existing} canManage={canManage} actions={ACTIONS} />;
      }
      return <DaySheetList rows={sheets} options={options} canManage={canManage} actions={ACTIONS} />;
    },
  };
}

const SAMPLE_MATERIAL_SCREENS = { "material-requisition": materialRequisition, "bundle-card-create": bundleCard, "daily-target-setting": dailyTarget, "bundle-movement": bundleTracking };

export { SAMPLE_MATERIAL_SCREENS };
