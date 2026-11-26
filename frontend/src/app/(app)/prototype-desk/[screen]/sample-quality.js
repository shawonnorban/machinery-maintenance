import { apiFetch } from "@/lib/api-server";
import { getT } from "@/lib/i18n-server";
import { ProtoList } from "@/components/prototype/proto-kit";
import { QualityCheckForm } from "@/components/prototype/quality";
import { ApprovalForm } from "@/components/prototype/approval";
import { FeedbackForm } from "@/components/prototype/feedback";
import { HandoverForm, HandoverList } from "@/components/prototype/handover";
import * as actions from "./actions";

/*
 * Prototype Desk → OPERATION → Sample Quality & Approval and Sample Handover
 * & Delivery (AnnotechERP 07/03), as Brickland lays the four screens out:
 * Records and the form tab. A row's Open loads it into the form by its
 * number (`?tab=quality&qc=QC-2026-00001`).
 */

const ACTIONS = { load: actions.loadProto, save: actions.saveProto, remove: actions.removeProto };
const can = (permissions, code) => permissions.includes(code);
const unique = (rows, field) => [...new Set(rows.map((r) => r[field]).filter(Boolean))].sort().map((v) => ({ value: v, label: v }));
const safe = async (path) => {
  try {
    return await apiFetch(path);
  } catch {
    return null;
  }
};

/** Records · the form, the form loaded by its number when the address names one. */
async function screen({ tab, searchParams, kind, formTab, param, label }) {
  const t = await getT("prototype");
  const [rows, options] = await Promise.all([apiFetch(`/prototype/${kind}`), apiFetch(`/prototype/${kind}/options`)]);
  const initial = searchParams[param] && tab === formTab ? await safe(`/prototype/${kind}/${encodeURIComponent(searchParams[param])}`) : null;
  return { rows, options, initial, tabs: [{ key: "records", label: t("ui.records"), count: rows.length }, { key: formTab, label }] };
}

/** Sample Quality Check: Records · Sample Quality Check. */
async function quality({ tab, searchParams, permissions }) {
  const t = await getT("prototype");
  const s = await screen({ tab, searchParams, kind: "quality-checks", formTab: "quality", param: "qc", label: t("ui.qc.form") });
  const canManage = can(permissions, "prototype.quality.manage");
  return {
    tabs: s.tabs,
    body: async () => {
      if (tab === "quality") {
        const existing = s.rows.map((r) => ({ value: r.qc_no, label: `${r.qc_no} — ${r.sample_no} V${r.version} · ${t(`result.${r.result}`)}` }));
        return <QualityCheckForm key={s.initial?.qc_no ?? "new"} options={s.options} initial={s.initial} existing={existing} canManage={canManage} actions={ACTIONS} />;
      }
      const data = s.rows.map((r) => ({ ...r, all: true, passed: ["PASS", "PASS_WITH_COMMENT"].includes(r.result), version_label: `V${r.version}`, severity_label: r.severity ? t(`severity.${r.severity}`) : null }));
      return (
        <ProtoList
          rows={data}
          rowKey="qc_no"
          exportName="sample-quality-checks"
          title={t("ui.qc.list")}
          subtitle={t("ui.qc.list_hint")}
          emptyText={t("ui.qc.empty")}
          addHref={canManage ? "?tab=quality" : undefined}
          addLabel={t("ui.qc.new")}
          openHref="?tab=quality&qc="
          filters={[
            { key: "date", label: t("ui.qc.f.check_date"), field: "check_date", range: true },
            { key: "buyer", label: t("ui.qc.f.buyer"), field: "buyer", options: unique(s.rows, "buyer") },
            { key: "result", label: t("ui.qc.f.result_filter"), field: "result", options: s.options.results.map((v) => ({ value: v, label: t(`result.${v}`) })) },
            { key: "sample", label: t("ui.qc.f.sample_no"), field: "sample_no", options: unique(s.rows, "sample_no") },
          ]}
          pills={[
            { key: "total", label: t("ui.qc.pill.total"), field: "all", value: true },
            { key: "passed", label: t("ui.qc.pill.passed"), field: "passed", value: true },
            { key: "failed", label: t("ui.qc.pill.failed"), field: "result", value: "FAIL" },
            { key: "rework", label: t("ui.qc.pill.rework"), field: "result", value: "REWORK_REQUIRED" },
          ]}
          columns={[
            { key: "qc_no", label: t("ui.qc.col.qc_no"), type: "mono" },
            { key: "sample_no", label: t("ui.qc.col.sample_no"), type: "mono" },
            { key: "style_ref", label: t("ui.qc.col.style_no") },
            { key: "buyer", label: t("ui.qc.col.buyer") },
            { key: "version_label", label: t("ui.qc.col.version"), align: "right" },
            { key: "check_date", label: t("ui.qc.col.check_date"), type: "date" },
            { key: "defects", label: t("ui.qc.col.defects"), type: "number" },
            { key: "severity_label", label: t("ui.qc.col.severity") },
            { key: "defect", label: t("ui.qc.col.defect") },
            { key: "checked_by_name", label: t("ui.qc.col.checked_by") },
          ]}
        />
      );
    },
  };
}

/** Sample Approval: Records · Sample Approval. */
async function approval({ tab, searchParams, permissions }) {
  const t = await getT("prototype");
  const s = await screen({ tab, searchParams, kind: "approvals", formTab: "approval", param: "apr", label: t("ui.appr.form") });
  const canManage = can(permissions, "prototype.approval.manage");
  return {
    tabs: s.tabs,
    body: async () => {
      if (tab === "approval") {
        const existing = s.rows.map((r) => ({ value: r.approval_no, label: `${r.approval_no} — ${r.sample_no} V${r.version} · ${t(`step.${r.step}`)} · ${t(`decision_sa.${r.decision}`)}` }));
        return <ApprovalForm key={s.initial?.approval_no ?? "new"} options={s.options} initial={s.initial} existing={existing} canManage={canManage} canRevise={can(permissions, "prototype.revision.manage")} actions={ACTIONS} />;
      }
      const data = s.rows.map((r) => ({ ...r, version_label: `V${r.version}`, decision_group: r.decision === "APPROVED_WITH_COMMENT" ? "APPROVED" : r.decision }));
      return (
        <ProtoList
          rows={data}
          rowKey="approval_no"
          exportName="sample-approvals"
          title={t("ui.appr.list")}
          subtitle={t("ui.appr.list_hint")}
          emptyText={t("ui.appr.empty")}
          addHref={canManage ? "?tab=approval" : undefined}
          addLabel={t("ui.appr.new")}
          openHref="?tab=approval&apr="
          filters={[
            { key: "date", label: t("ui.appr.f.submitted_date"), field: "submitted_date", range: true },
            { key: "buyer", label: t("ui.appr.f.buyer"), field: "buyer", options: unique(s.rows, "buyer") },
            { key: "step", label: t("ui.appr.f.step"), field: "step", options: s.options.steps.map((v) => ({ value: v, label: t(`step.${v}`) })) },
            { key: "decision", label: t("ui.appr.f.decision"), field: "decision", options: s.options.decisions.map((v) => ({ value: v, label: t(`decision_sa.${v}`) })) },
            { key: "sample", label: t("ui.appr.f.sample_no"), field: "sample_no", options: unique(s.rows, "sample_no") },
          ]}
          pills={["PENDING", "APPROVED", "REJECTED", "REVISION_REQUIRED"].map((v) => ({ key: v, label: t(`decision_sa.${v}`), field: "decision_group", value: v }))}
          columns={[
            { key: "approval_no", label: t("ui.appr.col.approval_no"), type: "mono" },
            { key: "sample_no", label: t("ui.appr.col.sample_no"), type: "mono" },
            { key: "style_ref", label: t("ui.appr.col.style_no") },
            { key: "buyer", label: t("ui.appr.col.buyer") },
            { key: "version_label", label: t("ui.appr.col.version"), align: "right" },
            { key: "step", label: t("ui.appr.col.step"), type: "badge", labels: Object.fromEntries(s.options.steps.map((v) => [v, t(`step.${v}`)])) },
            { key: "submitted_date", label: t("ui.appr.col.submitted"), type: "date" },
            { key: "approval_date", label: t("ui.appr.col.approval_date"), type: "date" },
            { key: "waiting_days", label: t("ui.appr.col.waiting"), type: "number" },
            { key: "approval_by", label: t("ui.appr.col.approval_by") },
            { key: "qc_no", label: t("ui.appr.col.qc_ref"), type: "mono" },
            { key: "comment", label: t("ui.appr.col.comment") },
          ]}
        />
      );
    },
  };
}

/** Buyer Feedback: Records · Buyer Feedback. */
async function feedback({ tab, searchParams, permissions }) {
  const t = await getT("prototype");
  const s = await screen({ tab, searchParams, kind: "feedback", formTab: "feedback", param: "fb", label: t("ui.fb.form") });
  const canManage = can(permissions, "prototype.feedback.manage");
  return {
    tabs: s.tabs,
    body: async () => {
      if (tab === "feedback") {
        const existing = s.rows.map((r) => ({ value: r.feedback_no, label: `${r.feedback_no} — ${r.sample_no} V${r.version} · ${t(`fb_type.${r.feedback_type}`)} · ${t(`fb_status.${r.status}`)}` }));
        return <FeedbackForm key={s.initial?.feedback_no ?? "new"} options={s.options} initial={s.initial} existing={existing} canManage={canManage} actions={ACTIONS} />;
      }
      const closed = (r) => ["RESOLVED", "CLOSED"].includes(r.status);
      const data = s.rows.map((r) => ({ ...r, version_label: `V${r.version}`, action_open: r.action_required && !closed(r), overdue: (r.overdue_days ?? 0) > 0, closed: closed(r) }));
      return (
        <ProtoList
          rows={data}
          rowKey="feedback_no"
          exportName="buyer-feedback"
          title={t("ui.fb.list")}
          subtitle={t("ui.fb.list_hint")}
          emptyText={t("ui.fb.empty")}
          addHref={canManage ? "?tab=feedback" : undefined}
          addLabel={t("ui.fb.new")}
          openHref="?tab=feedback&fb="
          filters={[
            { key: "date", label: t("ui.fb.f.feedback_date"), field: "feedback_date", range: true },
            { key: "buyer", label: t("ui.fb.f.buyer"), field: "buyer", options: unique(s.rows, "buyer") },
            { key: "type", label: t("ui.fb.f.type"), field: "feedback_type", options: s.options.types.map((v) => ({ value: v, label: t(`fb_type.${v}`) })) },
            { key: "status", label: t("ui.fb.f.status"), field: "status", options: s.options.statuses.map((v) => ({ value: v, label: t(`fb_status.${v}`) })) },
            { key: "assigned", label: t("ui.fb.f.assigned_to"), field: "assigned_to", options: s.options.users },
          ]}
          pills={[
            { key: "open", label: t("ui.fb.pill.open"), field: "status", value: "OPEN" },
            { key: "action", label: t("ui.fb.pill.action"), field: "action_open", value: true },
            { key: "overdue", label: t("ui.fb.pill.overdue"), field: "overdue", value: true },
            { key: "closed", label: t("ui.fb.pill.closed"), field: "closed", value: true },
          ]}
          columns={[
            { key: "feedback_no", label: t("ui.fb.col.feedback_no"), type: "mono" },
            { key: "feedback_date", label: t("ui.fb.col.date"), type: "date" },
            { key: "sample_no", label: t("ui.fb.col.sample_no"), type: "mono" },
            { key: "version_label", label: t("ui.fb.col.version"), align: "right" },
            { key: "buyer", label: t("ui.fb.col.buyer") },
            { key: "feedback_type", label: t("ui.fb.col.type"), type: "badge", labels: Object.fromEntries(s.options.types.map((v) => [v, t(`fb_type.${v}`)])) },
            { key: "comment", label: t("ui.fb.col.comment") },
            { key: "approval_no", label: t("ui.fb.col.approval"), type: "mono" },
            { key: "action_required", label: t("ui.fb.col.action_req"), type: "yesno" },
            { key: "assigned_to_name", label: t("ui.fb.col.assigned_to") },
            { key: "due_date", label: t("ui.fb.col.due_date"), type: "date" },
            { key: "overdue_days", label: t("ui.fb.col.overdue"), type: "number" },
          ]}
        />
      );
    },
  };
}

/** Sample Handover / Delivery: Records · Sample Handover / Delivery. */
async function handover({ tab, searchParams, permissions }) {
  const t = await getT("prototype");
  const s = await screen({ tab, searchParams, kind: "handovers", formTab: "handover", param: "ho", label: t("ui.ho.form") });
  const canManage = can(permissions, "prototype.handover.manage");
  return {
    tabs: s.tabs,
    body: async () => {
      if (tab === "handover") {
        const existing = s.rows.map((r) => ({ value: r.handover_no, label: `${r.handover_no} — ${r.sample_no} · ${r.qty} · ${t(`ho_status.${r.status}`)}` }));
        return <HandoverForm key={s.initial?.handover_no ?? "new"} options={s.options} initial={s.initial} existing={existing} canManage={canManage} actions={ACTIONS} />;
      }
      return <HandoverList rows={s.rows} canManage={canManage} actions={ACTIONS} />;
    },
  };
}

const SAMPLE_QUALITY_SCREENS = { "sample-quality-check": quality, "sample-approval": approval, "buyer-feedback": feedback, "sample-handover": handover };

export { SAMPLE_QUALITY_SCREENS };
