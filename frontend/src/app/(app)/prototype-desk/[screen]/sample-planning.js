import { apiFetch } from "@/lib/api-server";
import { getT } from "@/lib/i18n-server";
import { ProtoList } from "@/components/prototype/proto-kit";
import { RequisitionApproval, RequisitionForm, RequisitionList } from "@/components/prototype/requisition";
import { DevelopmentForm } from "@/components/prototype/development";
import { RevisionForm } from "@/components/prototype/revision";
import * as actions from "./actions";

/*
 * Prototype Desk → OPERATION → Sample Planning & Development (AnnotechERP
 * 07/03), as Brickland lays the three screens out: a list tab and the form
 * tab (the requisition adds Requisition Approval). A row's Open loads it into
 * the form by its number (`?req=SR-2026-00001`).
 */

const ACTIONS = { load: actions.loadProto, save: actions.saveProto, act: actions.actProto };
const can = (permissions, code) => permissions.includes(code);
const unique = (rows, field) => [...new Set(rows.map((r) => r[field]).filter(Boolean))].sort().map((v) => ({ value: v, label: v }));
const safe = async (path) => {
  try {
    return await apiFetch(path);
  } catch {
    return null;
  }
};

/** Sample Requisition: Requisition List · Sample Requisition · Requisition Approval. */
async function requisition({ tab, searchParams, permissions }) {
  const t = await getT("prototype");
  const [rows, options, me] = await Promise.all([apiFetch("/prototype/requisitions"), apiFetch("/prototype/requisitions/options"), apiFetch("/auth/me")]);
  const canManage = can(permissions, "prototype.requisition.manage");
  const canApprove = can(permissions, "prototype.requisition.approve");
  const tabs = [
    { key: "records", label: t("ui.req.list"), count: rows.length },
    { key: "requisition", label: t("ui.req.form") },
    { key: "approval", label: t("ui.req.approval") },
  ];
  const initial = searchParams.req && ["requisition", "approval"].includes(tab) ? await safe(`/prototype/requisitions/${encodeURIComponent(searchParams.req)}`) : null;
  return {
    tabs,
    body: async () => {
      if (tab === "requisition") {
        const existing = rows.map((r) => ({ value: r.req_no, label: `${r.req_no} — ${[r.buyer, r.style_name ?? r.style_ref, r.req_date, t(`status.${r.status}`)].filter(Boolean).join(" · ")}` }));
        return <RequisitionForm key={initial?.req_no ?? "new"} options={options} initial={initial} existing={existing} companyName={me.company?.name} canManage={canManage} actions={ACTIONS} />;
      }
      if (tab === "approval") {
        const existing = rows
          .filter((r) => r.status === "PENDING_APPROVAL" || ["APPROVED", "REJECTED", "ASSIGNED"].includes(r.status))
          .map((r) => ({ value: r.req_no, label: `${r.req_no} — ${r.buyer ?? ""} · ${r.pending_level ? t("ui.req.awaiting", { level: t(`level.${r.pending_level}`) }) : t(`status.${r.status}`)}` }));
        return <RequisitionApproval key={initial?.req_no ?? "none"} options={options} initial={initial} existing={existing} canApprove={canApprove} actions={ACTIONS} />;
      }
      return <RequisitionList rows={rows} options={options} canManage={canManage} canApprove={canApprove} actions={ACTIONS} />;
    },
  };
}

/** Sample Development: Development List · Sample Development. */
async function development({ tab, searchParams, permissions }) {
  const t = await getT("prototype");
  const [rows, options] = await Promise.all([apiFetch("/prototype/developments"), apiFetch("/prototype/developments/options")]);
  const canManage = can(permissions, "prototype.development.manage");
  const tabs = [
    { key: "records", label: t("ui.dev.list"), count: rows.length },
    { key: "development", label: t("ui.dev.form") },
  ];
  const initial = searchParams.dev && tab === "development" ? await safe(`/prototype/developments/${encodeURIComponent(searchParams.dev)}`) : null;
  return {
    tabs,
    body: async () => {
      if (tab === "development") {
        const existing = rows.map((r) => ({ value: r.dev_no, label: `${r.dev_no} — ${r.sample_no} V${r.version} · ${t(`dev_status.${r.status}`)}` }));
        return <DevelopmentForm key={initial?.dev_no ?? "new"} options={options} initial={initial} existing={existing} canManage={canManage} actions={ACTIONS} />;
      }
      const data = rows.map((r) => ({ ...r, version_label: `V${r.version}`, tasks_label: `${r.tasks_done}/${r.tasks_total}`, progress_label: `${r.progress}%`, status_filter: r.overdue ? "OVERDUE" : r.status }));
      return (
        <ProtoList
          rows={data}
          rowKey="dev_no"
          exportName="sample-developments"
          title={t("ui.dev.list")}
          subtitle={t("ui.dev.list_hint")}
          emptyText={t("ui.dev.empty")}
          addHref={canManage ? "?tab=development" : undefined}
          addLabel={t("ui.dev.new")}
          openHref="?tab=development&dev="
          filters={[
            { key: "date", label: t("ui.dev.f.dev_date"), field: "dev_date", range: true },
            { key: "buyer", label: t("ui.dev.f.buyer"), field: "buyer", options: unique(rows, "buyer") },
            { key: "type", label: t("ui.dev.f.sample_type"), field: "sample_type", options: unique(rows, "sample_type") },
            { key: "status", label: t("ui.dev.f.status"), field: "status_filter", options: ["PENDING", "IN_PROGRESS", "ON_HOLD", "COMPLETED", "DELAYED", "CANCELLED", "OVERDUE"].map((s) => ({ value: s, label: t(`dev_status.${s}`) })) },
          ]}
          columns={[
            { key: "dev_no", label: t("ui.dev.col.dev_no"), type: "mono" },
            { key: "dev_date", label: t("ui.dev.col.date"), type: "date" },
            { key: "sample_no", label: t("ui.dev.col.sample_no"), type: "mono" },
            { key: "version_label", label: t("ui.dev.col.version") },
            { key: "buyer", label: t("ui.dev.col.buyer") },
            { key: "style_name", label: t("ui.dev.col.style") },
            { key: "sample_type", label: t("ui.dev.col.sample_type") },
            { key: "expected_completion", label: t("ui.dev.col.expected"), type: "date" },
            { key: "actual_completion", label: t("ui.dev.col.completed"), type: "date" },
            { key: "tasks_label", label: t("ui.dev.col.tasks") },
            { key: "progress_label", label: t("ui.dev.col.progress"), align: "right" },
          ]}
        />
      );
    },
  };
}

/** Sample Revision: Revision List · Sample Revision. */
async function revision({ tab, searchParams, permissions }) {
  const t = await getT("prototype");
  const [rows, options] = await Promise.all([apiFetch("/prototype/revisions"), apiFetch("/prototype/revisions/options")]);
  const canManage = can(permissions, "prototype.revision.manage");
  const canApprove = can(permissions, "prototype.revision.approve");
  const tabs = [
    { key: "records", label: t("ui.rev.list"), count: rows.length },
    { key: "revision", label: t("ui.rev.form") },
  ];
  const initial = searchParams.rev && tab === "revision" ? await safe(`/prototype/revisions/${encodeURIComponent(searchParams.rev)}`) : null;
  return {
    tabs,
    body: async () => {
      if (tab === "revision") {
        const existing = rows.map((r) => ({ value: r.rev_no, label: `${r.rev_no} — ${r.sample_no} V${r.previous_version} → V${r.new_version} · ${t(`rev_status.${r.status}`)}` }));
        const preset = { sample: searchParams.sample, approval: searchParams.approval, feedback: searchParams.feedback };
        return <RevisionForm key={initial?.rev_no ?? "new"} options={options} initial={initial} existing={existing} canManage={canManage} canApprove={canApprove} actions={ACTIONS} preset={preset} />;
      }
      const data = rows.map((r) => ({ ...r, version_label: `V${r.previous_version} → V${r.new_version}`, approved_filter: r.approved_at ? "APPROVED" : "AWAITING" }));
      return (
        <ProtoList
          rows={data}
          rowKey="rev_no"
          exportName="sample-revisions"
          title={t("ui.rev.list")}
          subtitle={t("ui.rev.list_hint")}
          emptyText={t("ui.rev.empty")}
          addHref={canManage ? "?tab=revision" : undefined}
          addLabel={t("ui.rev.new")}
          openHref="?tab=revision&rev="
          filters={[
            { key: "date", label: t("ui.rev.f.revision_date"), field: "revision_date", range: true },
            { key: "buyer", label: t("ui.rev.f.buyer"), field: "buyer", options: unique(rows, "buyer") },
            { key: "sample", label: t("ui.rev.f.sample_no"), field: "sample_no", options: unique(rows, "sample_no") },
            { key: "status", label: t("ui.rev.f.status"), field: "status", options: ["OPEN", "IN_PROGRESS", "COMPLETED", "CANCELLED"].map((s) => ({ value: s, label: t(`rev_status.${s}`) })) },
            { key: "approved", label: t("ui.rev.f.approved"), field: "approved_filter", options: [{ value: "APPROVED", label: t("ui.rev.approved_option") }, { value: "AWAITING", label: t("ui.rev.awaiting_option") }] },
          ]}
          columns={[
            { key: "rev_no", label: t("ui.rev.col.rev_no"), type: "mono" },
            { key: "revision_date", label: t("ui.rev.col.date"), type: "date" },
            { key: "sample_no", label: t("ui.rev.col.sample_no"), type: "mono" },
            { key: "buyer", label: t("ui.rev.col.buyer") },
            { key: "style_name", label: t("ui.rev.col.style") },
            { key: "version_label", label: t("ui.rev.col.version") },
            { key: "reason", label: t("ui.rev.col.reason") },
            { key: "due_date", label: t("ui.rev.col.due"), type: "date" },
            { key: "requested_by_name", label: t("ui.rev.col.requested_by") },
            { key: "approved_by_name", label: t("ui.rev.col.approved_by") },
            { key: "revisions", label: t("ui.rev.col.revisions"), type: "number" },
          ]}
        />
      );
    },
  };
}

const SAMPLE_PLANNING_SCREENS = { "prototype-requisition": requisition, "sample-development": development, "sample-revision": revision };

export { SAMPLE_PLANNING_SCREENS };
