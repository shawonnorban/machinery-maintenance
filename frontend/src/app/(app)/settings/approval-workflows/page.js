import { apiFetch } from "@/lib/api-server";
import { PageHeader } from "@/components/layout/page-header";
import { EmptyState } from "@/components/ui/empty-state";
import { WorkflowCard } from "@/components/approval/workflow-card";
import { NewWorkflowForm } from "@/components/approval/new-workflow-form";
import { getT } from "@/lib/i18n-server";
import { createWorkflow, toggleWorkflow, addRule, removeRule } from "./actions";

const ENTITY_TYPES = ["WORK_ORDER", "INVENTORY_TRANSFER", "COST_ENTRY"];

/**
 * Who has to sign, and above what (SRS 14) — mirrors `WorkflowController::index`
 * (`ApprovalWorkflowApiController`, built this pass to back it: no API for
 * this admin-config side existed before, only the runtime `/approvals` inbox).
 */
export default async function ApprovalWorkflowsPage() {
  const [workflows, roles, t, tn] = await Promise.all([
    apiFetch("/approval-workflows"),
    apiFetch("/roles?per_page=100"),
    getT("approval"),
    getT("nav"),
  ]);

  return (
    <>
      <PageHeader
        breadcrumb={[{ label: tn("settings") }, { label: t("workflows") }]}
        title={t("workflows")}
        description={t("workflows_intro")}
      />

      <div className="flex flex-col gap-4">
        <NewWorkflowForm entityTypes={ENTITY_TYPES} action={createWorkflow} />

        {workflows.length === 0 ? (
          <EmptyState title={t("no_workflows")} description={t("no_workflows_hint")} />
        ) : (
          workflows.map((workflow) => (
            <WorkflowCard
              key={workflow.id}
              workflow={workflow}
              roles={roles}
              toggleAction={toggleWorkflow.bind(null, workflow.id)}
              addRuleAction={addRule.bind(null, workflow.id)}
              removeRuleAction={removeRule.bind(null, workflow.id)}
            />
          ))
        )}
      </div>
    </>
  );
}
