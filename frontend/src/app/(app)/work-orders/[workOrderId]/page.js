import Link from "next/link";
import { ArrowLeft } from "lucide-react";
import { apiFetch } from "@/lib/api-server";
import { PageHeader } from "@/components/layout/page-header";
import { buttonVariants } from "@/components/ui/button";
import { cn } from "@/lib/utils";
import { StatusBadge, formatStatus } from "@/components/ui/status-badge";
import { Badge } from "@/components/ui/badge";
import { Card, CardBody } from "@/components/ui/card";
import { FormattedDateTime } from "@/components/ui/formatted-date-time";
import { Tabs, TabsList, TabsTab, TabsIndicator, TabsPanel } from "@/components/ui/tabs";
import { WorkOrderActions } from "@/components/work-orders/work-order-actions";
import { AssignTechnicianControl } from "@/components/work-orders/assign-technician-control";
import { HistoryTable } from "@/components/work-orders/history-table";
import { ChecklistTab } from "@/components/work-orders/checklist-tab";
import { LaborTab } from "@/components/work-orders/labor-tab";
import { PartsTab } from "@/components/work-orders/parts-tab";
import { AttachmentsTab } from "@/components/work-orders/attachments-tab";
import {
  submitForApproval, start, resume, complete, verify, close, hold, cancel, reopen,
  assignTechnician, unassignTechnician,
  recordChecklistAnswer, recordLabor, deleteLabor, requestPart, issuePart, issueRequestedPart, consumePart, returnPart,
  uploadAttachment,
} from "./actions";

const PRIORITY_TONE = { CRITICAL: "danger", HIGH: "warning", MEDIUM: "info", LOW: "neutral" };
const PRIORITY_BORDER = { CRITICAL: "border-l-danger", HIGH: "border-l-warning", MEDIUM: "border-l-info", LOW: "border-l-border-strong" };
const TERMINAL_STATUSES = ["CLOSED", "CANCELLED"];

/**
 * Mirrors `work_order::work-orders.show`'s core plus its checklist/labor/
 * parts panels (`_checklist`/`_labor`/`_parts.blade.php`) and a new
 * attachments upload the web never offered (it only ever lists them
 * read-only).
 */
export default async function WorkOrderDetailPage({ params }) {
  const { workOrderId } = await params;

  const [workOrder, history, technicians, costs, checklist, labor, parts, attachments, spareParts, bins] = await Promise.all([
    apiFetch(`/work-orders/${workOrderId}`),
    apiFetch(`/work-orders/${workOrderId}/history`),
    apiFetch(`/work-orders/${workOrderId}/assignable-technicians`),
    // work_order.cost.view is a separate permission from viewing the work
    // order itself (SRS 25.1) — absent rather than erroring for a caller
    // who lacks it, mirroring the web's own `showCosts` conditional.
    apiFetch(`/work-orders/${workOrderId}/costs`).catch(() => null),
    apiFetch(`/work-orders/${workOrderId}/checklist`),
    apiFetch(`/work-orders/${workOrderId}/labor`),
    apiFetch(`/work-orders/${workOrderId}/parts`),
    apiFetch(`/work-orders/${workOrderId}/attachments`),
    apiFetch("/spare-parts?per_page=100"),
    apiFetch("/spare-parts/bins"),
  ]);

  const isTerminal = TERMINAL_STATUSES.includes(workOrder.status);
  const canExecuteChecklist = workOrder.status === "IN_PROGRESS";

  return (
    <>
      <PageHeader
        breadcrumb={[{ label: "Work Orders", href: "/work-orders" }, { label: workOrder.work_order_number }]}
        title={workOrder.work_order_number}
        description={workOrder.title}
        actions={
          <div className="flex flex-wrap items-center gap-2">
            <WorkOrderActions
              status={workOrder.status}
              workOrderId={workOrderId}
              actions={{ submitForApproval, start, resume, complete, verify, close, hold, cancel, reopen }}
            />
            <Link href="/work-orders" className={cn(buttonVariants({ variant: "outline", size: "sm" }))}>
              <ArrowLeft /> Back
            </Link>
          </div>
        }
      />

      <Card className={cn("mb-6 flex flex-wrap items-center gap-x-8 gap-y-3 border-l-4 p-4", PRIORITY_BORDER[workOrder.priority] ?? "border-l-border-strong")}>
        <SummaryItem label="Status">
          <StatusBadge status={workOrder.status} />
        </SummaryItem>
        <SummaryItem label="Priority">
          <Badge variant={PRIORITY_TONE[workOrder.priority] ?? "neutral"}>{formatStatus(workOrder.priority)}</Badge>
        </SummaryItem>
        <SummaryItem label="Asset">
          <span className="text-sm font-medium text-foreground">
            {workOrder.asset?.asset_code} <span className="font-normal text-foreground-muted">— {workOrder.asset?.name}</span>
          </span>
        </SummaryItem>
        <SummaryItem label="Factory">
          <span className="text-sm text-foreground">{workOrder.factory?.name ?? "—"}</span>
        </SummaryItem>
      </Card>

      <Card>
        <CardBody>
          <Tabs defaultValue="overview">
            <TabsList>
              <TabsTab value="overview">Overview</TabsTab>
              <TabsTab value="checklist">Checklist</TabsTab>
              <TabsTab value="labor">Labor</TabsTab>
              <TabsTab value="parts">Parts</TabsTab>
              <TabsTab value="attachments">Attachments</TabsTab>
              <TabsTab value="history">History</TabsTab>
              {costs ? <TabsTab value="costs">Costs</TabsTab> : null}
              <TabsIndicator />
            </TabsList>

            <TabsPanel value="overview">
              <div className="grid grid-cols-1 gap-x-8 gap-y-4 sm:grid-cols-2">
                <Field label="Maintenance type">{workOrder.maintenance_type ?? "—"}</Field>
                <Field label="Source">{formatStatus(workOrder.source)}</Field>
                <Field label="Scheduled start"><FormattedDateTime value={workOrder.scheduled_start} /></Field>
                <Field label="Scheduled end"><FormattedDateTime value={workOrder.scheduled_end} /></Field>
                <Field label="Requires shutdown">{workOrder.requires_shutdown ? "Yes" : "No"}</Field>
                <Field label="Requires verification">{workOrder.requires_verification ? "Yes" : "No"}</Field>
                <div className="sm:col-span-2">
                  <Field label="Assigned technicians">
                    <AssignTechnicianControl
                      status={workOrder.status}
                      workOrderId={workOrderId}
                      assignments={workOrder.assignments}
                      technicians={technicians}
                      assign={assignTechnician}
                      unassign={unassignTechnician}
                    />
                  </Field>
                </div>
                {workOrder.description ? (
                  <div className="sm:col-span-2">
                    <Field label="Description">{workOrder.description}</Field>
                  </div>
                ) : null}
              </div>
            </TabsPanel>

            <TabsPanel value="checklist">
              <ChecklistTab
                progress={checklist.progress}
                items={checklist.items}
                canExecute={canExecuteChecklist}
                action={recordChecklistAnswer.bind(null, workOrderId)}
              />
            </TabsPanel>

            <TabsPanel value="labor">
              <LaborTab
                entries={labor}
                technicians={technicians}
                canManage
                isTerminal={isTerminal}
                recordAction={recordLabor.bind(null, workOrderId)}
                deleteAction={deleteLabor.bind(null, workOrderId)}
              />
            </TabsPanel>

            <TabsPanel value="parts">
              <PartsTab
                lines={parts}
                spareParts={spareParts}
                bins={bins}
                isTerminal={isTerminal}
                showCosts={Boolean(costs)}
                currency={costs?.currency}
                actions={{
                  requestPart: requestPart.bind(null, workOrderId),
                  issuePart: issuePart.bind(null, workOrderId),
                  issueRequestedPart: issueRequestedPart.bind(null, workOrderId),
                  consumePart: consumePart.bind(null, workOrderId),
                  returnPart: returnPart.bind(null, workOrderId),
                }}
              />
            </TabsPanel>

            <TabsPanel value="attachments">
              <AttachmentsTab attachments={attachments} isTerminal={isTerminal} action={uploadAttachment.bind(null, workOrderId)} />
            </TabsPanel>

            <TabsPanel value="history">
              <HistoryTable history={history} />
            </TabsPanel>

            {costs ? (
              <TabsPanel value="costs">
                <div className="grid grid-cols-2 gap-4 sm:grid-cols-4">
                  <Field label="Estimated parts">{costs.estimated_parts_cost ?? "—"} {costs.currency}</Field>
                  <Field label="Actual parts">{costs.actual_parts_cost ?? "—"} {costs.currency}</Field>
                  <Field label="Other costs">{costs.actual_other_cost ?? "—"} {costs.currency}</Field>
                  <Field label="Actual total">{costs.actual_cost ?? "—"} {costs.currency}</Field>
                </div>
              </TabsPanel>
            ) : null}
          </Tabs>
        </CardBody>
      </Card>
    </>
  );
}

function SummaryItem({ label, children }) {
  return (
    <div className="flex flex-col gap-1">
      <span className="text-xs font-medium text-foreground-muted">{label}</span>
      {children}
    </div>
  );
}

function Field({ label, children }) {
  return (
    <div className="flex flex-col gap-1">
      <span className="text-xs font-medium text-foreground-muted">{label}</span>
      <span className="text-sm text-foreground">{children}</span>
    </div>
  );
}
