import { apiFetch } from "@/lib/api-server";
import { PageHeader } from "@/components/layout/page-header";
import { EmptyState } from "@/components/ui/empty-state";
import { WorkflowCard } from "@/components/approval/workflow-card";
import { NewWorkflowForm } from "@/components/approval/new-workflow-form";
import { createWorkflow, toggleWorkflow, addRule, removeRule } from "./actions";

const ENTITY_TYPES = ["WORK_ORDER", "INVENTORY_TRANSFER", "COST_ENTRY"];

/**
 * Who has to sign, and above what (SRS 14) — mirrors `WorkflowController::index`
 * (`ApprovalWorkflowApiController`, built this pass to back it: no API for
 * this admin-config side existed before, only the runtime `/approvals` inbox).
 */
export default async function ApprovalWorkflowsPage() {
  const [workflows, roles] = await Promise.all([
    apiFetch("/approval-workflows"),
    apiFetch("/roles?per_page=100"),
  ]);

  return (
    <>
      <PageHeader
        breadcrumb={[{ label: "Settings" }, { label: "Approval workflows" }]}
        title="Approval workflows"
        description="Who has to sign, and above what. Each chain is a role, not a person — a chain that names one employee stops working the week they're on leave."
      />

      <div className="flex flex-col gap-4">
        <NewWorkflowForm entityTypes={ENTITY_TYPES} action={createWorkflow} />

        {workflows.length === 0 ? (
          <EmptyState title="No chains yet." description="Add one above to start routing approvals." />
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
