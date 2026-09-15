"use client";

import { useRouter } from "next/navigation";
import Link from "next/link";
import { DataTable } from "@/components/ui/data-table";
import { StatusBadge } from "@/components/ui/status-badge";
import { Badge } from "@/components/ui/badge";
import { FormattedDateTime } from "@/components/ui/formatted-date-time";

/**
 * Column `render` functions can't cross the Server→Client boundary, so this
 * client component owns the column definitions; the server page just hands
 * over the current page of `approvals` as plain data.
 */
function ApprovalsTable({ approvals, meta, page, status }) {
  const router = useRouter();

  function navigate(nextPage) {
    router.push(`/approvals?status=${status}&page=${nextPage}`);
  }

  return (
    <DataTable
      columns={[
        {
          key: "entity",
          header: "Entity",
          render: (approval) =>
            approval.work_order ? (
              <div>
                <Link href={`/work-orders/${approval.work_order.id}`} className="font-medium text-brand hover:underline">
                  {approval.work_order.work_order_number}
                </Link>
                <div className="text-xs text-foreground-muted">
                  {[approval.work_order.asset_code, approval.work_order.title].filter(Boolean).join(" — ")}
                </div>
              </div>
            ) : (
              <span className="text-foreground-muted">{approval.entity_type}</span>
            ),
        },
        {
          key: "cost",
          header: "Cost",
          align: "right",
          render: (approval) =>
            approval.context?.cost !== undefined
              ? `${Number(approval.context.cost).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })} ${approval.context.currency ?? ""}`
              : "—",
        },
        {
          key: "step",
          header: "Step",
          render: (approval) => `${approval.current_step} of ${approval.total_steps}`,
        },
        {
          key: "requested_at",
          header: "Requested",
          render: (approval) => <FormattedDateTime value={approval.requested_at} />,
        },
        {
          key: "status",
          header: "Status",
          render: (approval) => (
            <div className="flex items-center gap-1.5">
              <StatusBadge status={approval.status} />
              {approval.can_act ? <Badge variant="warning">Awaiting you</Badge> : null}
            </div>
          ),
        },
      ]}
      rows={approvals}
      rowKey={(approval) => approval.id}
      emptyTitle="No requests found."
      emptyDescription="Nothing is waiting in this view."
      rowActions={(approval) => [{ label: "View", onSelect: () => router.push(`/approvals/${approval.id}`) }]}
      pagination={{ page: meta.current_page, perPage: meta.per_page, total: meta.total }}
      onPageChange={navigate}
    />
  );
}

export { ApprovalsTable };
