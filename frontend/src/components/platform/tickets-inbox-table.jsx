"use client";

import { useRouter } from "next/navigation";
import Link from "next/link";
import { ChevronLeft, ChevronRight, Ticket } from "lucide-react";
import { Button } from "@/components/ui/button";
import { StatusBadge } from "@/components/ui/status-badge";
import { Badge } from "@/components/ui/badge";
import { RelativeTime } from "@/components/ui/relative-time";
import { Panel, PTable } from "./ui";

const STATUS_OPTIONS = [
  { value: "", label: "All statuses" },
  { value: "OPEN", label: "Open" },
  { value: "IN_PROGRESS", label: "In progress" },
  { value: "RESOLVED", label: "Resolved" },
  { value: "CLOSED", label: "Closed" },
];

const PRIORITY_OPTIONS = [
  { value: "", label: "All priorities" },
  { value: "URGENT", label: "Urgent" },
  { value: "HIGH", label: "High" },
  { value: "MEDIUM", label: "Medium" },
  { value: "LOW", label: "Low" },
];

const PRIORITY_TONE = { URGENT: "danger", HIGH: "warning", MEDIUM: "info", LOW: "neutral" };

function FilterSelect({ label, value, options, onChange }) {
  return (
    <label className="text-[13px] font-semibold text-foreground">
      {label}
      <select value={value} onChange={(e) => onChange(e.target.value)} className="mt-1.5 block h-9 w-52 rounded-sm border border-border-strong bg-surface px-2.5 text-sm font-normal text-foreground">
        {options.map((o) => (
          <option key={o.value} value={o.value}>
            {o.label}
          </option>
        ))}
      </select>
    </label>
  );
}

function TicketsInboxTable({ tickets, meta, page, status, priority }) {
  const router = useRouter();
  const last = Math.max(1, Math.ceil((meta.total ?? 0) / (meta.per_page || 1)));

  function navigate(next) {
    const params = new URLSearchParams({ page: String(next.page ?? page), status: next.status ?? status, priority: next.priority ?? priority });
    for (const [key, value] of [...params.entries()]) if (!value || (key === "page" && value === "1")) params.delete(key);
    router.push(`/platform/tickets${params.size ? `?${params.toString()}` : ""}`);
  }

  return (
    <>
      <div className="mb-4 flex flex-wrap items-end gap-3 rounded-lg border border-border bg-surface p-4">
        <FilterSelect label="Status" value={status} options={STATUS_OPTIONS} onChange={(v) => navigate({ status: v, page: 1 })} />
        <FilterSelect label="Priority" value={priority} options={PRIORITY_OPTIONS} onChange={(v) => navigate({ priority: v, page: 1 })} />
      </div>
      <Panel
        title="Inbox"
        subtitle={`${meta.total ?? tickets.length} tickets`}
        icon={Ticket}
        action={
          last > 1 ? (
            <div className="flex items-center gap-2 text-sm">
              <Button size="sm" variant="outline" disabled={meta.current_page <= 1} onClick={() => navigate({ page: meta.current_page - 1 })} aria-label="Previous page">
                <ChevronLeft />
              </Button>
              <span className="tabular-nums text-foreground-muted">
                Page {meta.current_page} of {last}
              </span>
              <Button size="sm" variant="outline" disabled={meta.current_page >= last} onClick={() => navigate({ page: meta.current_page + 1 })} aria-label="Next page">
                <ChevronRight />
              </Button>
            </div>
          ) : null
        }
      >
        <PTable
          rows={tickets}
          rowKey={(t) => t.id}
          empty="No tickets found."
          columns={[
            {
              key: "subject",
              label: "Ticket",
              render: (t) => (
                <div>
                  <Link href={`/platform/tickets/${t.id}`} className="font-semibold text-brand hover:underline">
                    {t.subject}
                  </Link>
                  <div className="text-xs text-foreground-muted">{t.company?.name}</div>
                </div>
              ),
            },
            {
              key: "priority",
              label: "Priority",
              render: (t) => (
                <div className="flex items-center gap-1.5">
                  <Badge variant={PRIORITY_TONE[t.priority] ?? "neutral"}>{t.priority}</Badge>
                  {t.sla?.breached ? <Badge variant="danger">SLA breached</Badge> : null}
                </div>
              ),
            },
            { key: "opener", label: "Opened by", render: (t) => t.opener?.name ?? "—" },
            { key: "assignee", label: "Assigned to", render: (t) => t.assignee?.name ?? <span className="text-foreground-subtle">Nobody</span> },
            { key: "status", label: "Status", render: (t) => <StatusBadge status={t.status} /> },
            {
              key: "last_message_at",
              label: "Last activity",
              render: (t) => (
                <span className="text-[13px] text-foreground-muted">
                  <RelativeTime value={t.last_message_at} fallback="—" />
                </span>
              ),
            },
          ]}
        />
      </Panel>
    </>
  );
}

export { TicketsInboxTable };
