import Link from "next/link";
import { ArrowLeft } from "lucide-react";
import { apiFetch } from "@/lib/api-server";
import { PageHeader } from "@/components/layout/page-header";
import { buttonVariants } from "@/components/ui/button";
import { cn } from "@/lib/utils";
import { StatusBadge, formatStatus } from "@/components/ui/status-badge";
import { Badge } from "@/components/ui/badge";
import { Card, CardBody } from "@/components/ui/card";
import { Tabs, TabsList, TabsTab, TabsIndicator, TabsPanel } from "@/components/ui/tabs";
import { BreakdownActions } from "@/components/breakdowns/breakdown-actions";
import { BreakdownTimelineTab } from "@/components/breakdowns/breakdown-timeline-tab";
import { FormattedDateTime } from "@/components/ui/formatted-date-time";
import { ChecklistTab } from "@/components/work-orders/checklist-tab";
import { LaborTab } from "@/components/work-orders/labor-tab";
import { PartsTab } from "@/components/work-orders/parts-tab";
import {
  acknowledge, arrive, startRepair, completeRepair, resumeProduction, resume, assign, hold, close, cancel,
  raiseWorkOrder, startWorkOrder, correctTimestamp,
  recordChecklistAnswer, recordLabor, deleteLabor, requestPart, issuePart, issueRequestedPart, consumePart, returnPart,
} from "./actions";

const WORK_ORDER_TERMINAL_STATUSES = ["CLOSED", "CANCELLED"];

const PRIORITY_TONE = { CRITICAL: "danger", HIGH: "warning", MEDIUM: "info", LOW: "neutral" };
const PRIORITY_BORDER = { CRITICAL: "border-l-danger", HIGH: "border-l-warning", MEDIUM: "border-l-info", LOW: "border-l-border-strong" };

/**
 * Mirrors `breakdown::breakdowns.show`'s core: what was reported, its
 * current status, downtime so far, and the full repair chain (acknowledge,
 * record arrival, assign, start repair, hold/resume, complete repair,
 * resume production, close, cancel, raising an additional work order, and
 * correcting a chain timestamp). No separate Client Component wrapper
 * needed for the checklist/labor/parts tabs here (unlike Asset/Metering/
 * Inventory's detail pages): neither panel needs a `DataTable` or any
 * other function-holding child, so this Server Component can pass plain
 * rendered JSX straight into `<TabsPanel>` — the Server→Client boundary
 * only blocks functions, never elements.
 */
export default async function BreakdownDetailPage({ params }) {
  const { breakdownId } = await params;

  const [breakdown, downtime, formOptions] = await Promise.all([
    apiFetch(`/breakdowns/${breakdownId}`),
    apiFetch(`/breakdowns/${breakdownId}/downtime`),
    apiFetch(`/breakdowns/${breakdownId}/form-options`),
  ]);

  // Raised via the "Raise work order" action (`RaiseBreakdownWorkOrder`,
  // which also puts the line's whole roster on it) — the technician still
  // works from this one screen rather than being sent to a separate work
  // order page to log labor, request parts or answer a checklist.
  const workOrderId = breakdown.work_order?.id ?? null;

  const [checklist, labor, parts, workOrderTechnicians, spareParts, bins] = workOrderId
    ? await Promise.all([
        apiFetch(`/work-orders/${workOrderId}/checklist`),
        apiFetch(`/work-orders/${workOrderId}/labor`),
        apiFetch(`/work-orders/${workOrderId}/parts`),
        apiFetch(`/work-orders/${workOrderId}/assignable-technicians`),
        apiFetch("/spare-parts?per_page=100"),
        apiFetch("/spare-parts/bins"),
      ])
    : [null, [], [], [], [], []];

  const workOrderIsTerminal = WORK_ORDER_TERMINAL_STATUSES.includes(breakdown.work_order?.status);
  const canExecuteChecklist = breakdown.work_order?.status === "IN_PROGRESS";

  return (
    <>
      <PageHeader
        breadcrumb={[{ label: "Breakdowns", href: "/breakdowns" }, { label: breakdown.breakdown_number }]}
        title={breakdown.breakdown_number}
        description={`${breakdown.asset?.asset_code} — ${breakdown.asset?.name}`}
        actions={
          <div className="flex flex-wrap items-center gap-2">
            <BreakdownActions
              status={breakdown.status}
              breakdownId={breakdownId}
              technicians={formOptions.technicians}
              failureCodes={formOptions.failure_codes}
              rootCauses={formOptions.root_causes}
              holdReasons={formOptions.hold_reasons}
              isOpen={breakdown.is_open}
              isTerminal={breakdown.is_terminal}
              arrivalRecorded={Boolean(breakdown.timestamps?.technician_arrival_at)}
              hasOpenWorkOrder={Boolean(breakdown.work_order) && !WORK_ORDER_TERMINAL_STATUSES.includes(breakdown.work_order?.status)}
              workOrderId={workOrderId}
              workOrderStatus={breakdown.work_order?.status ?? null}
              actions={{
                acknowledge, arrive, startRepair, completeRepair, resumeProduction, resume, assign, hold, close, cancel,
                raiseWorkOrder, startWorkOrder,
              }}
            />
            <Link href="/breakdowns" className={cn(buttonVariants({ variant: "outline", size: "sm" }))}>
              <ArrowLeft /> Back
            </Link>
          </div>
        }
      />

      <Card className={cn("mb-6 flex flex-wrap items-center gap-x-8 gap-y-3 border-l-4 p-4", PRIORITY_BORDER[breakdown.priority] ?? "border-l-border-strong")}>
        <SummaryItem label="Status">
          <StatusBadge status={breakdown.status} />
        </SummaryItem>
        <SummaryItem label="Priority">
          <Badge variant={PRIORITY_TONE[breakdown.priority] ?? "neutral"}>{formatStatus(breakdown.priority)}</Badge>
        </SummaryItem>
        <SummaryItem label="Factory">
          <span className="text-sm text-foreground">{breakdown.factory?.name ?? "—"}</span>
        </SummaryItem>
        <SummaryItem label="Assigned technician">
          <span className="text-sm text-foreground">{breakdown.assigned_technician ?? "Unassigned"}</span>
        </SummaryItem>
        {breakdown.work_order ? (
          <SummaryItem label="Repair work order">
            <Link href={`/work-orders/${breakdown.work_order.id}`} className="text-sm text-brand hover:underline">
              {breakdown.work_order.work_order_number}
            </Link>
          </SummaryItem>
        ) : null}
      </Card>

      <Card>
        <CardBody>
          <Tabs defaultValue="overview">
            <TabsList>
              <TabsTab value="overview">Overview</TabsTab>
              <TabsTab value="timeline">Timeline</TabsTab>
              <TabsTab value="downtime">Downtime</TabsTab>
              {workOrderId ? (
                <>
                  <TabsTab value="checklist">Checklist</TabsTab>
                  <TabsTab value="labor">Labor</TabsTab>
                  <TabsTab value="parts">Parts</TabsTab>
                </>
              ) : null}
              <TabsIndicator />
            </TabsList>

            <TabsPanel value="overview">
              <div className="grid grid-cols-1 gap-x-8 gap-y-4 sm:grid-cols-2">
                <Field label="Severity">{formatStatus(breakdown.severity) || "—"}</Field>
                <Field label="Production order">{breakdown.production_order_reference ?? "—"}</Field>
                <Field label="Reported at"><FormattedDateTime value={breakdown.reported_at} /></Field>
                <Field label="Failure at"><FormattedDateTime value={breakdown.failure_at} /></Field>
                <div className="sm:col-span-2">
                  <Field label="Problem description">{breakdown.problem_description}</Field>
                </div>
                {breakdown.failure_category || breakdown.failure_code || breakdown.root_cause ? (
                  <>
                    <Field label="Failure category">{breakdown.failure_category ?? "—"}</Field>
                    <Field label="Failure code">{breakdown.failure_code ?? "—"}</Field>
                    <Field label="Root cause">{breakdown.root_cause ?? "—"}</Field>
                  </>
                ) : null}
              </div>
            </TabsPanel>

            <TabsPanel value="timeline">
              <BreakdownTimelineTab
                timestamps={breakdown.timestamps}
                isTerminal={breakdown.is_terminal}
                action={correctTimestamp.bind(null, breakdownId)}
              />
            </TabsPanel>

            <TabsPanel value="downtime">
              {downtime ? (
                <div className="grid grid-cols-2 gap-4 sm:grid-cols-4">
                  <Field label="Response">{formatMinutes(downtime.response_minutes)}</Field>
                  <Field label="Repair">{formatMinutes(downtime.repair_minutes)}</Field>
                  <Field label="On hold">{formatMinutes(downtime.hold_minutes)}</Field>
                  <Field label="Total downtime">{formatMinutes(downtime.total_downtime_minutes)}</Field>
                  <Field label="Class">{downtime.downtime_class ?? "—"}</Field>
                  <Field label="Counts against availability">{downtime.counts_against_availability ? "Yes" : "No"}</Field>
                  {downtime.needs_review ? (
                    <div className="col-span-2">
                      <Badge variant="warning">Needs review</Badge>
                    </div>
                  ) : null}
                </div>
              ) : (
                <p className="text-sm text-foreground-muted">No downtime record yet — this breakdown hasn&apos;t reached a completed repair.</p>
              )}
            </TabsPanel>

            {workOrderId ? (
              <>
                <TabsPanel value="checklist">
                  <ChecklistTab
                    progress={checklist.progress}
                    items={checklist.items}
                    canExecute={canExecuteChecklist}
                    action={recordChecklistAnswer.bind(null, breakdownId, workOrderId)}
                  />
                </TabsPanel>

                <TabsPanel value="labor">
                  <LaborTab
                    entries={labor}
                    technicians={workOrderTechnicians}
                    canManage
                    isTerminal={workOrderIsTerminal}
                    recordAction={recordLabor.bind(null, breakdownId, workOrderId)}
                    deleteAction={deleteLabor.bind(null, breakdownId, workOrderId)}
                  />
                </TabsPanel>

                <TabsPanel value="parts">
                  <PartsTab
                    lines={parts}
                    spareParts={spareParts}
                    bins={bins}
                    isTerminal={workOrderIsTerminal}
                    showCosts={false}
                    actions={{
                      requestPart: requestPart.bind(null, breakdownId, workOrderId),
                      issuePart: issuePart.bind(null, breakdownId, workOrderId),
                      issueRequestedPart: issueRequestedPart.bind(null, breakdownId, workOrderId),
                      consumePart: consumePart.bind(null, breakdownId, workOrderId),
                      returnPart: returnPart.bind(null, breakdownId, workOrderId),
                    }}
                  />
                </TabsPanel>
              </>
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

function formatMinutes(minutes) {
  if (minutes === null || minutes === undefined) {
    return "—";
  }
  const hours = Math.floor(minutes / 60);
  const rest = Math.round(minutes % 60);
  return hours > 0 ? `${hours}h ${rest}m` : `${rest}m`;
}

function Field({ label, children }) {
  return (
    <div className="flex flex-col gap-1">
      <span className="text-xs font-medium text-foreground-muted">{label}</span>
      <span className="text-sm text-foreground">{children}</span>
    </div>
  );
}
