import Link from "next/link";
import { ArrowLeft } from "lucide-react";
import { apiFetch } from "@/lib/api-server";
import { PageHeader } from "@/components/layout/page-header";
import { buttonVariants } from "@/components/ui/button";
import { cn } from "@/lib/utils";
import { StatusBadge } from "@/components/ui/status-badge";
import { Badge } from "@/components/ui/badge";
import { Card } from "@/components/ui/card";
import { WorkOrderActions } from "@/components/work-orders/work-order-actions";
import { WorkOrderDetailTabs } from "@/components/work-orders/work-order-detail-tabs";
import { getT } from "@/lib/i18n-server";
import { submitForApproval, start, resume, complete, verify, close, hold, cancel, reopen } from "./actions";

const PRIORITY_TONE = { CRITICAL: "danger", HIGH: "warning", MEDIUM: "info", LOW: "neutral" };
const PRIORITY_BORDER = { CRITICAL: "border-l-danger", HIGH: "border-l-warning", MEDIUM: "border-l-info", LOW: "border-l-border-strong" };
const TERMINAL_STATUSES = ["CLOSED", "CANCELLED"];

/**
 * Mirrors `work_order::work-orders.show`'s core plus its checklist/labor/
 * parts panels (`_checklist`/`_labor`/`_parts.blade.php`) and a new
 * attachments upload the web never offered (it only ever lists them
 * read-only).
 *
 * Only three calls up front — the work order itself, assignable
 * technicians (needed by the Overview tab's always-visible assignment
 * control), and costs (needed just to decide whether the Costs tab exists
 * at all) — rather than the ten this page used to fire in one
 * `Promise.all`. Checklist/labor/parts/attachments/history are fetched by
 * their own tab, lazily, the first time each is actually opened
 * (`WorkOrderDetailTabs`'s own note has why): the same problem already
 * found and fixed on the asset detail page.
 */
export default async function WorkOrderDetailPage({ params }) {
  const { workOrderId } = await params;

  const [workOrder, technicians, costs, t, tc] = await Promise.all([
    apiFetch(`/work-orders/${workOrderId}`),
    apiFetch(`/work-orders/${workOrderId}/assignable-technicians`),
    // work_order.cost.view is a separate permission from viewing the work
    // order itself (SRS 25.1) — absent rather than erroring for a caller
    // who lacks it, mirroring the web's own `showCosts` conditional.
    apiFetch(`/work-orders/${workOrderId}/costs`).catch(() => null),
    getT("work_order"),
    getT("common"),
  ]);

  const isTerminal = TERMINAL_STATUSES.includes(workOrder.status);
  const canExecuteChecklist = workOrder.status === "IN_PROGRESS";

  return (
    <>
      <PageHeader
        breadcrumb={[{ label: t("work_orders"), href: "/work-orders" }, { label: workOrder.work_order_number }]}
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
              <ArrowLeft /> {tc("back")}
            </Link>
          </div>
        }
      />

      <Card className={cn("mb-6 flex flex-wrap items-center gap-x-8 gap-y-3 border-l-4 p-4", PRIORITY_BORDER[workOrder.priority] ?? "border-l-border-strong")}>
        <SummaryItem label={t("status")}>
          <StatusBadge status={workOrder.status} label={t(`status_${workOrder.status?.toLowerCase()}`)} />
        </SummaryItem>
        <SummaryItem label={t("priority")}>
          <Badge variant={PRIORITY_TONE[workOrder.priority] ?? "neutral"}>{t(`priority_${workOrder.priority?.toLowerCase()}`)}</Badge>
        </SummaryItem>
        <SummaryItem label={t("asset")}>
          <span className="text-sm font-medium text-foreground">
            {workOrder.asset?.asset_code} <span className="font-normal text-foreground-muted">— {workOrder.asset?.name}</span>
          </span>
        </SummaryItem>
        <SummaryItem label={tc("factory_scope")}>
          <span className="text-sm text-foreground">{workOrder.factory?.name ?? "—"}</span>
        </SummaryItem>
      </Card>

      <WorkOrderDetailTabs
        workOrder={workOrder}
        workOrderId={workOrderId}
        technicians={technicians}
        costs={costs}
        isTerminal={isTerminal}
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
