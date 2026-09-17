import Link from "next/link";
import { ArrowLeft } from "lucide-react";
import { apiFetch } from "@/lib/api-server";
import { PageHeader } from "@/components/layout/page-header";
import { buttonVariants } from "@/components/ui/button";
import { cn } from "@/lib/utils";
import { StatusBadge } from "@/components/ui/status-badge";
import { Badge } from "@/components/ui/badge";
import { Card } from "@/components/ui/card";
import { BreakdownActions } from "@/components/breakdowns/breakdown-actions";
import { BreakdownDetailTabs } from "@/components/breakdowns/breakdown-detail-tabs";
import { getT } from "@/lib/i18n-server";
import {
  acknowledge, arrive, startRepair, completeRepair, resumeProduction, resume, assign, hold, close, cancel,
  raiseWorkOrder, startWorkOrder,
} from "./actions";

const WORK_ORDER_TERMINAL_STATUSES = ["CLOSED", "CANCELLED"];

const PRIORITY_TONE = { CRITICAL: "danger", HIGH: "warning", MEDIUM: "info", LOW: "neutral" };
const PRIORITY_BORDER = { CRITICAL: "border-l-danger", HIGH: "border-l-warning", MEDIUM: "border-l-info", LOW: "border-l-border-strong" };

/**
 * Mirrors `breakdown::breakdowns.show`'s core: what was reported, its
 * current status, downtime so far, and the full repair chain (acknowledge,
 * record arrival, assign, start repair, hold/resume, complete repair,
 * resume production, close, cancel, raising an additional work order, and
 * correcting a chain timestamp).
 *
 * Only two calls up front — the breakdown itself and `form-options`
 * (needed by the always-visible header actions) — rather than the up to
 * ten this page used to fire in one `Promise.all` once a repair work
 * order existed. Attachments, downtime, and (when a work order exists)
 * checklist/labor/parts are fetched by their own tab, lazily, the first
 * time each is actually opened (`BreakdownDetailTabs`'s own note has
 * why): the same problem already found and fixed on the asset and
 * work-order detail pages.
 */
export default async function BreakdownDetailPage({ params }) {
  const { breakdownId } = await params;

  const [breakdown, formOptions, t, tc] = await Promise.all([
    apiFetch(`/breakdowns/${breakdownId}`),
    apiFetch(`/breakdowns/${breakdownId}/form-options`),
    getT("breakdown"),
    getT("common"),
  ]);

  // Raised via the "Raise work order" action (`RaiseBreakdownWorkOrder`,
  // which also puts the line's whole roster on it) — the technician still
  // works from this one screen rather than being sent to a separate work
  // order page to log labor, request parts or answer a checklist.
  const workOrderId = breakdown.work_order?.id ?? null;
  const workOrderIsTerminal = WORK_ORDER_TERMINAL_STATUSES.includes(breakdown.work_order?.status);
  const canExecuteChecklist = breakdown.work_order?.status === "IN_PROGRESS";

  return (
    <>
      <PageHeader
        breadcrumb={[{ label: t("breakdowns"), href: "/breakdowns" }, { label: breakdown.breakdown_number }]}
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
              <ArrowLeft /> {tc("back")}
            </Link>
          </div>
        }
      />

      <Card className={cn("mb-6 flex flex-wrap items-center gap-x-8 gap-y-3 border-l-4 p-4", PRIORITY_BORDER[breakdown.priority] ?? "border-l-border-strong")}>
        <SummaryItem label={t("status")}>
          <StatusBadge status={breakdown.status} label={t(`status_${breakdown.status?.toLowerCase()}`)} />
        </SummaryItem>
        <SummaryItem label={t("priority")}>
          <Badge variant={PRIORITY_TONE[breakdown.priority] ?? "neutral"}>{t(`priority_${breakdown.priority?.toLowerCase()}`)}</Badge>
        </SummaryItem>
        <SummaryItem label={t("factory")}>
          <span className="text-sm text-foreground">{breakdown.factory?.name ?? "—"}</span>
        </SummaryItem>
        <SummaryItem label={t("assigned_technician")}>
          <span className="text-sm text-foreground">{breakdown.assigned_technician ?? t("unassigned_placeholder")}</span>
        </SummaryItem>
        {breakdown.work_order ? (
          <SummaryItem label={t("repair_work_order")}>
            <Link href={`/work-orders/${breakdown.work_order.id}`} className="text-sm text-brand hover:underline">
              {breakdown.work_order.work_order_number}
            </Link>
          </SummaryItem>
        ) : null}
      </Card>

      <BreakdownDetailTabs
        breakdown={breakdown}
        breakdownId={breakdownId}
        workOrderId={workOrderId}
        workOrderIsTerminal={workOrderIsTerminal}
        canExecuteChecklist={canExecuteChecklist}
      />
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
