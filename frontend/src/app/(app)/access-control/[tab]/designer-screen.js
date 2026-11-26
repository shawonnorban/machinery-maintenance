import { apiFetch } from "@/lib/api-server";
import { getT } from "@/lib/i18n-server";
import { RecordsGrid } from "@/components/erp/records-grid";
import { EntryForm } from "@/components/erp/entry-form";
import { WorkflowSteps } from "@/components/approval/workflow-steps";
import { StepAssignment } from "@/components/approval/step-assignment";
import * as designer from "./designer-actions";

/*
 * Access Control → Approval Workflow Designer (AnnotechERP 02/03), Brickland's
 * four tabs: Records · Approval Actions · Action Assignment · New action type.
 */

function workflowFields(ta, options) {
  return [
    {
      name: "type_code",
      label: ta("center.designer.f.type_code"),
      placeholder: "AT-011",
      help: ta("center.designer.f.type_code_hint"),
    },
    {
      name: "name",
      label: ta("center.designer.f.name"),
      required: true,
      placeholder: "Yarn Return Approval Workflow",
    },
    {
      name: "short_name",
      label: ta("center.designer.f.short_name"),
      placeholder: "YRet",
    },
    {
      name: "document_type",
      label: ta("center.designer.f.document_type"),
      required: true,
      placeholder: "Yarn Return",
    },
    {
      name: "entity_type",
      label: ta("center.designer.f.entity_type"),
      type: "select",
      required: true,
      options: options.entity_types.map((e) => ({
        value: e,
        label: ta(`center.entities.${e}`),
      })),
      default: "MANUAL_REQUEST",
      help: ta("center.designer.f.entity_type_hint"),
    },
    {
      name: "workflow_type",
      label: ta("center.designer.f.workflow_type"),
      type: "select",
      options: options.workflow_types.map((w) => ({
        value: w,
        label: ta(`center.designer.workflow_types.${w}`),
      })),
    },
    {
      name: "page_url",
      label: ta("center.designer.f.page_url"),
      placeholder: "/m/purchase/yarn-return",
    },
    {
      name: "active",
      label: ta("center.designer.f.active"),
      type: "checkbox",
      default: true,
    },
  ];
}

async function approvalWorkflow({ te, tab }) {
  const ta = await getT("approval");
  const result = await apiFetch("/approval-center/workflows", {
    includeMeta: true,
  });
  const rows = result.data;
  const options = result.meta.options;

  return {
    tabs: [
      {
        key: "records",
        label: te("records"),
        icon: "Table2",
        count: rows.length,
      },
      {
        key: "actions",
        label: ta("center.designer.tab_actions"),
        icon: "ListOrdered",
      },
      {
        key: "assignment",
        label: ta("center.designer.tab_assignment"),
        icon: "ListChecks",
      },
      { key: "new", label: ta("center.designer.tab_new"), icon: "Plus" },
    ],
    body: () => {
      if (tab === "actions")
        return (
          <WorkflowSteps
            workflows={rows}
            roles={options.roles}
            loadSteps={designer.loadSteps}
            saveSteps={designer.saveSteps}
          />
        );
      if (tab === "assignment")
        return (
          <StepAssignment
            steps={options.steps}
            loadUsers={designer.loadStepUsers}
            saveUsers={designer.saveStepUsers}
          />
        );
      if (tab === "new") {
        return (
          <EntryForm
            fields={workflowFields(ta, options)}
            action={designer.createWorkflow}
            title={ta("center.designer.tab_new")}
            submitLabel={ta("center.designer.save_type")}
            successHref="/access-control/approval-workflow?tab=actions"
          />
        );
      }
      return (
        <RecordsGrid
          exportName="approval-action-types"
          rows={rows.map((r) => ({
            ...r,
            workflow_type_label: r.workflow_type
              ? ta(`center.designer.workflow_types.${r.workflow_type}`)
              : null,
          }))}
          columns={[
            {
              key: "type_code",
              label: ta("center.designer.cols.type_code"),
              type: "mono",
            },
            { key: "name", label: ta("center.designer.cols.type_name") },
            { key: "short_name", label: ta("center.designer.cols.short_name") },
            {
              key: "workflow_type_label",
              label: ta("center.designer.cols.workflow_type"),
            },
            {
              key: "page_url",
              label: ta("center.designer.cols.page_url"),
              type: "mono",
            },
            {
              key: "actions",
              label: ta("center.designer.cols.actions"),
              type: "number",
            },
            {
              key: "active",
              label: ta("center.designer.cols.status"),
              type: "status",
            },
          ]}
          edit={{
            fields: workflowFields(ta, options),
            action: designer.updateWorkflow,
            title: ta("center.designer.edit_type"),
            submitLabel: ta("center.designer.save_type"),
          }}
        />
      );
    },
  };
}

export { approvalWorkflow };
