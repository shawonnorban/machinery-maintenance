"use client";

import { Card, CardBody } from "@/components/ui/card";
import { Tabs, TabsList, TabsTab, TabsIndicator, TabsPanel } from "@/components/ui/tabs";
import { Badge } from "@/components/ui/badge";
import { Skeleton } from "@/components/ui/skeleton";
import { ErrorState } from "@/components/ui/error-state";
import { FormattedDateTime } from "@/components/ui/formatted-date-time";
import { BreakdownTimelineTab } from "@/components/breakdowns/breakdown-timeline-tab";
import { ChecklistTab } from "@/components/work-orders/checklist-tab";
import { LaborTab } from "@/components/work-orders/labor-tab";
import { PartsTab } from "@/components/work-orders/parts-tab";
import { AttachmentsTab } from "@/components/work-orders/attachments-tab";
import { useLazyTabData, withRefetch } from "@/lib/use-lazy-tab-data";
import { useT } from "@/lib/i18n";
import {
  correctTimestamp, uploadAttachment,
  recordChecklistAnswer, recordLabor, deleteLabor, requestPart, issuePart, issueRequestedPart, consumePart, returnPart,
  getDowntime, getAttachments, getChecklist, getLaborData, getPartsData,
} from "@/app/(app)/breakdowns/[breakdownId]/actions";

/**
 * Everything below the summary strip on the breakdown detail page.
 * Overview stays as a plain prop (`breakdown` is already fetched eagerly
 * by the page — it's the default tab). Attachments, Timeline's own
 * correction form aside, Downtime, and — when a repair work order
 * exists — Checklist/Labor/Parts all fetch their own data lazily, the
 * first time each is actually opened, for the same reason
 * `AssetDetailTabs`/`WorkOrderDetailTabs` do: a tab nobody opens costs
 * nothing, and the page's original all-at-once fetch (up to ten
 * concurrent calls once a work order existed) was slow enough on this
 * product's actual shared hosting to time out on a poor connection
 * before the page ever painted.
 */
function BreakdownDetailTabs({ breakdown, breakdownId, workOrderId, workOrderIsTerminal, canExecuteChecklist }) {
  const t = useT("breakdown");
  const tc = useT("common");

  return (
    <Card>
      <CardBody>
        <Tabs defaultValue="overview">
          <TabsList>
            <TabsTab value="overview">{tc("overview")}</TabsTab>
            <TabsTab value="attachments">{t("attachments")}</TabsTab>
            <TabsTab value="timeline">{t("timeline")}</TabsTab>
            <TabsTab value="downtime">{t("downtime")}</TabsTab>
            {workOrderId ? (
              <>
                <TabsTab value="checklist">{t("checklist")}</TabsTab>
                <TabsTab value="labor">{t("labor")}</TabsTab>
                <TabsTab value="parts">{t("parts")}</TabsTab>
              </>
            ) : null}
            <TabsIndicator />
          </TabsList>

          <TabsPanel value="overview">
            <div className="grid grid-cols-1 gap-x-8 gap-y-4 sm:grid-cols-2">
              <Field label={t("severity")}>{breakdown.severity ? t(`severity_${breakdown.severity.toLowerCase()}`) : "—"}</Field>
              <Field label={t("production_order_reference")}>{breakdown.production_order_reference ?? "—"}</Field>
              <Field label={t("reported_at")}><FormattedDateTime value={breakdown.reported_at} /></Field>
              <Field label={t("failure_at")}><FormattedDateTime value={breakdown.failure_at} /></Field>
              <div className="sm:col-span-2">
                <Field label={t("problem_description")}>{breakdown.problem_description}</Field>
              </div>
              {breakdown.failure_category || breakdown.failure_code || breakdown.failure_code_other || breakdown.root_cause ? (
                <>
                  <Field label={t("failure_category")}>{breakdown.failure_category ?? "—"}</Field>
                  <Field label={t("failure_code")}>
                    {breakdown.failure_code ?? (breakdown.failure_code_other ? `${t("other_option")}: ${breakdown.failure_code_other}` : "—")}
                  </Field>
                  <Field label={t("root_cause")}>{breakdown.root_cause ?? "—"}</Field>
                </>
              ) : null}
              {breakdown.downtime_reason || breakdown.downtime_reason_other ? (
                <Field label={t("reason_code")}>{breakdown.downtime_reason ?? `${t("other_option")}: ${breakdown.downtime_reason_other}`}</Field>
              ) : null}
            </div>
          </TabsPanel>

          <TabsPanel value="attachments">
            <AttachmentsPanel breakdownId={breakdownId} isTerminal={breakdown.is_terminal} />
          </TabsPanel>

          <TabsPanel value="timeline">
            <BreakdownTimelineTab
              timestamps={breakdown.timestamps}
              isTerminal={breakdown.is_terminal}
              action={correctTimestamp.bind(null, breakdownId)}
            />
          </TabsPanel>

          <TabsPanel value="downtime">
            <DowntimePanel breakdownId={breakdownId} />
          </TabsPanel>

          {workOrderId ? (
            <>
              <TabsPanel value="checklist">
                <ChecklistPanel breakdownId={breakdownId} workOrderId={workOrderId} canExecute={canExecuteChecklist} />
              </TabsPanel>

              <TabsPanel value="labor">
                <LaborPanel breakdownId={breakdownId} workOrderId={workOrderId} isTerminal={workOrderIsTerminal} />
              </TabsPanel>

              <TabsPanel value="parts">
                <PartsPanel breakdownId={breakdownId} workOrderId={workOrderId} isTerminal={workOrderIsTerminal} />
              </TabsPanel>
            </>
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

function DowntimePanel({ breakdownId }) {
  const t = useT("breakdown");
  const tc = useT("common");
  const { data: downtime, loading, error, refetch } = useLazyTabData(() => getDowntime(breakdownId));

  if (loading || error) {
    return <TabLoadState loading={loading} error={error} onRetry={refetch} />;
  }

  if (!downtime) {
    return <p className="text-sm text-foreground-muted">{t("no_downtime_record")}</p>;
  }

  return (
    <div className="grid grid-cols-2 gap-4 sm:grid-cols-4">
      <Field label={t("response_time")}>{formatMinutes(downtime.response_minutes)}</Field>
      <Field label={t("repair_time")}>{formatMinutes(downtime.repair_minutes)}</Field>
      <Field label={t("hold_time")}>{formatMinutes(downtime.hold_minutes)}</Field>
      <Field label={t("total_downtime")}>{formatMinutes(downtime.total_downtime_minutes)}</Field>
      <Field label={t("downtime_class")}>{downtime.downtime_class ? t(`class_${downtime.downtime_class.toLowerCase()}`) : "—"}</Field>
      <Field label={t("counts_against_availability")}>{downtime.counts_against_availability ? tc("yes") : tc("no")}</Field>
      {downtime.needs_review ? (
        <div className="col-span-2">
          <Badge variant="warning">{t("needs_review")}</Badge>
        </div>
      ) : null}
    </div>
  );
}

function AttachmentsPanel({ breakdownId, isTerminal }) {
  const { data, loading, error, refetch } = useLazyTabData(() => getAttachments(breakdownId));

  if (loading || error) {
    return <TabLoadState loading={loading} error={error} onRetry={refetch} rows={3} />;
  }

  return <AttachmentsTab attachments={data} isTerminal={isTerminal} action={withRefetch(uploadAttachment.bind(null, breakdownId), refetch)} />;
}

function ChecklistPanel({ breakdownId, workOrderId, canExecute }) {
  const { data, loading, error, refetch } = useLazyTabData(() => getChecklist(workOrderId));

  if (loading || error) {
    return <TabLoadState loading={loading} error={error} onRetry={refetch} />;
  }

  return (
    <ChecklistTab
      progress={data.progress}
      items={data.items}
      canExecute={canExecute}
      action={withRefetch(recordChecklistAnswer.bind(null, breakdownId, workOrderId), refetch)}
    />
  );
}

function LaborPanel({ breakdownId, workOrderId, isTerminal }) {
  const { data, loading, error, refetch } = useLazyTabData(() => getLaborData(workOrderId));

  if (loading || error) {
    return <TabLoadState loading={loading} error={error} onRetry={refetch} />;
  }

  return (
    <LaborTab
      entries={data.labor}
      technicians={data.technicians}
      canManage
      isTerminal={isTerminal}
      recordAction={withRefetch(recordLabor.bind(null, breakdownId, workOrderId), refetch)}
      deleteAction={withRefetch(deleteLabor.bind(null, breakdownId, workOrderId), refetch)}
    />
  );
}

function PartsPanel({ breakdownId, workOrderId, isTerminal }) {
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
      showCosts={false}
      actions={{
        requestPart: withRefetch(requestPart.bind(null, breakdownId, workOrderId), refetch),
        issuePart: withRefetch(issuePart.bind(null, breakdownId, workOrderId), refetch),
        issueRequestedPart: withRefetch(issueRequestedPart.bind(null, breakdownId, workOrderId), refetch),
        consumePart: withRefetch(consumePart.bind(null, breakdownId, workOrderId), refetch),
        returnPart: withRefetch(returnPart.bind(null, breakdownId, workOrderId), refetch),
      }}
    />
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

export { BreakdownDetailTabs };
