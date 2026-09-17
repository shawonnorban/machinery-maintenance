"use client";

import { Card, CardBody } from "@/components/ui/card";
import { Tabs, TabsList, TabsTab, TabsIndicator, TabsPanel } from "@/components/ui/tabs";
import { Skeleton } from "@/components/ui/skeleton";
import { ErrorState } from "@/components/ui/error-state";
import { FormattedDateTime } from "@/components/ui/formatted-date-time";
import { AssignTechnicianControl } from "@/components/work-orders/assign-technician-control";
import { HistoryTable } from "@/components/work-orders/history-table";
import { ChecklistTab } from "@/components/work-orders/checklist-tab";
import { LaborTab } from "@/components/work-orders/labor-tab";
import { PartsTab } from "@/components/work-orders/parts-tab";
import { AttachmentsTab } from "@/components/work-orders/attachments-tab";
import { useLazyTabData, withRefetch } from "@/lib/use-lazy-tab-data";
import { useT } from "@/lib/i18n";
import {
  assignTechnician, unassignTechnician,
  recordChecklistAnswer, recordLabor, deleteLabor, requestPart, issuePart, issueRequestedPart, consumePart, returnPart,
  uploadAttachment,
  getChecklist, getLabor, getPartsData, getAttachments, getHistory,
} from "@/app/(app)/work-orders/[workOrderId]/actions";

/**
 * Everything below the summary strip on the work order detail page.
 * Overview and Costs stay as plain props (`workOrder`/`technicians`/
 * `costs` are already fetched eagerly by the page — Overview is the
 * default tab, and Costs needs to be known before first paint just to
 * decide whether its own tab button exists at all). Checklist, Labor,
 * Parts, and Attachments fetch their own data lazily, the first time
 * each is actually opened, for the same reason `AssetDetailTabs` does:
 * `TabsPanel` (base-ui, `keepMounted` false by default) never mounts an
 * inactive panel's children at all, and the page's original all-at-once
 * fetch (ten concurrent calls, several of them not cheap — the full parts
 * ledger, the spare-parts catalog, every attachment) was slow enough on
 * this product's actual shared hosting to time out on a poor connection
 * before the page ever painted (confirmed live on the asset detail page,
 * fixed there first).
 */
function WorkOrderDetailTabs({ workOrder, workOrderId, technicians, costs, isTerminal, canExecuteChecklist }) {
  const t = useT("work_order");
  const tc = useT("common");

  return (
    <Card>
      <CardBody>
        <Tabs defaultValue="overview">
          <TabsList>
            <TabsTab value="overview">{tc("overview")}</TabsTab>
            <TabsTab value="checklist">{t("checklist")}</TabsTab>
            <TabsTab value="labor">{t("labor")}</TabsTab>
            <TabsTab value="parts">{t("parts")}</TabsTab>
            <TabsTab value="attachments">{t("attachments")}</TabsTab>
            <TabsTab value="history">{t("timeline")}</TabsTab>
            {costs ? <TabsTab value="costs">{t("cost")}</TabsTab> : null}
            <TabsIndicator />
          </TabsList>

          <TabsPanel value="overview">
            <div className="grid grid-cols-1 gap-x-8 gap-y-4 sm:grid-cols-2">
              <Field label={t("maintenance_type")}>{workOrder.maintenance_type ?? "—"}</Field>
              <Field label={t("source")}>{t(`source_${workOrder.source?.toLowerCase()}`)}</Field>
              <Field label={t("scheduled_start")}><FormattedDateTime value={workOrder.scheduled_start} /></Field>
              <Field label={t("scheduled_end")}><FormattedDateTime value={workOrder.scheduled_end} /></Field>
              <Field label={t("requires_shutdown")}>{workOrder.requires_shutdown ? tc("yes") : tc("no")}</Field>
              <Field label={t("requires_verification")}>{workOrder.requires_verification ? tc("yes") : tc("no")}</Field>
              <div className="sm:col-span-2">
                <Field label={t("assigned_technicians")}>
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
                  <Field label={t("description")}>{workOrder.description}</Field>
                </div>
              ) : null}
            </div>
          </TabsPanel>

          <TabsPanel value="checklist">
            <ChecklistPanel workOrderId={workOrderId} canExecute={canExecuteChecklist} />
          </TabsPanel>

          <TabsPanel value="labor">
            <LaborPanel workOrderId={workOrderId} technicians={technicians} isTerminal={isTerminal} />
          </TabsPanel>

          <TabsPanel value="parts">
            <PartsPanel workOrderId={workOrderId} isTerminal={isTerminal} costs={costs} />
          </TabsPanel>

          <TabsPanel value="attachments">
            <AttachmentsPanel workOrderId={workOrderId} isTerminal={isTerminal} />
          </TabsPanel>

          <TabsPanel value="history">
            <HistoryPanel workOrderId={workOrderId} />
          </TabsPanel>

          {costs ? (
            <TabsPanel value="costs">
              <div className="grid grid-cols-2 gap-4 sm:grid-cols-4">
                <Field label={t("cost_estimated_parts")}>{costs.estimated_parts_cost ?? "—"} {costs.currency}</Field>
                <Field label={t("cost_actual_parts")}>{costs.actual_parts_cost ?? "—"} {costs.currency}</Field>
                <Field label={t("cost_other")}>{costs.actual_other_cost ?? "—"} {costs.currency}</Field>
                <Field label={t("cost_actual_total")}>{costs.actual_cost ?? "—"} {costs.currency}</Field>
              </div>
            </TabsPanel>
          ) : null}
        </Tabs>
      </CardBody>
    </Card>
  );
}

/** Shared shape every lazy panel below renders while its one fetch is in flight or failed. */
function TabLoadState({ loading, error, onRetry, rows = 4 }) {
  if (loading) {
    return (
      <div className="flex flex-col gap-2">
        {Array.from({ length: rows }, (_, index) => (
          <Skeleton key={index} className="h-10 w-full" />
        ))}
      </div>
    );
  }

  return <ErrorState description={error.message} onRetry={onRetry} />;
}

function ChecklistPanel({ workOrderId, canExecute }) {
  const { data, loading, error, refetch } = useLazyTabData(() => getChecklist(workOrderId));

  if (loading || error) {
    return <TabLoadState loading={loading} error={error} onRetry={refetch} />;
  }

  return (
    <ChecklistTab
      progress={data.progress}
      items={data.items}
      canExecute={canExecute}
      action={withRefetch(recordChecklistAnswer.bind(null, workOrderId), refetch)}
    />
  );
}

function LaborPanel({ workOrderId, technicians, isTerminal }) {
  const { data, loading, error, refetch } = useLazyTabData(() => getLabor(workOrderId));

  if (loading || error) {
    return <TabLoadState loading={loading} error={error} onRetry={refetch} />;
  }

  return (
    <LaborTab
      entries={data}
      technicians={technicians}
      canManage
      isTerminal={isTerminal}
      recordAction={withRefetch(recordLabor.bind(null, workOrderId), refetch)}
      deleteAction={withRefetch(deleteLabor.bind(null, workOrderId), refetch)}
    />
  );
}

function PartsPanel({ workOrderId, isTerminal, costs }) {
  const { data, loading, error, refetch } = useLazyTabData(() => getPartsData(workOrderId));

  if (loading || error) {
    return <TabLoadState loading={loading} error={error} onRetry={refetch} />;
  }

  return (
    <PartsTab
      lines={data.parts}
      spareParts={data.spareParts}
      bins={data.bins}
      isTerminal={isTerminal}
      showCosts={Boolean(costs)}
      currency={costs?.currency}
      actions={{
        requestPart: withRefetch(requestPart.bind(null, workOrderId), refetch),
        issuePart: withRefetch(issuePart.bind(null, workOrderId), refetch),
        issueRequestedPart: withRefetch(issueRequestedPart.bind(null, workOrderId), refetch),
        consumePart: withRefetch(consumePart.bind(null, workOrderId), refetch),
        returnPart: withRefetch(returnPart.bind(null, workOrderId), refetch),
      }}
    />
  );
}

function AttachmentsPanel({ workOrderId, isTerminal }) {
  const { data, loading, error, refetch } = useLazyTabData(() => getAttachments(workOrderId));

  if (loading || error) {
    return <TabLoadState loading={loading} error={error} onRetry={refetch} rows={3} />;
  }

  return <AttachmentsTab attachments={data} isTerminal={isTerminal} action={withRefetch(uploadAttachment.bind(null, workOrderId), refetch)} />;
}

function HistoryPanel({ workOrderId }) {
  const { data, loading, error, refetch } = useLazyTabData(() => getHistory(workOrderId));

  if (loading || error) {
    return <TabLoadState loading={loading} error={error} onRetry={refetch} />;
  }

  return <HistoryTable history={data} />;
}

function Field({ label, children }) {
  return (
    <div className="flex flex-col gap-1">
      <span className="text-xs font-medium text-foreground-muted">{label}</span>
      <span className="text-sm text-foreground">{children}</span>
    </div>
  );
}

export { WorkOrderDetailTabs };
