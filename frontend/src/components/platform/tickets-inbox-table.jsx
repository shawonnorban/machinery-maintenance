"use client";

import { useRouter } from "next/navigation";
import Link from "next/link";
import { DataTable } from "@/components/ui/data-table";
import { Select } from "@/components/ui/select";
import { Card, CardBody } from "@/components/ui/card";
import { StatusBadge } from "@/components/ui/status-badge";
import { RelativeTime } from "@/components/ui/relative-time";

const STATUS_OPTIONS = [
  { value: "", label: "All statuses" },
  { value: "OPEN", label: "Open" },
  { value: "IN_PROGRESS", label: "In progress" },
  { value: "RESOLVED", label: "Resolved" },
  { value: "CLOSED", label: "Closed" },
];

function TicketsInboxTable({ tickets, meta, page, status }) {
  const router = useRouter();

  function navigate(next) {
    const params = new URLSearchParams({ page: String(next.page ?? page), status: next.status ?? status });
    for (const [key, value] of [...params.entries()]) if (!value) params.delete(key);
    router.push(`/platform/tickets?${params.toString()}`);
  }

  return (
    <div className="flex flex-col gap-4">
      <Card>
        <CardBody className="flex flex-wrap items-center gap-3 py-3">
          <div className="w-full max-w-[220px]">
            <Select options={STATUS_OPTIONS} value={status} onValueChange={(value) => navigate({ status: value, page: 1 })} />
          </div>
        </CardBody>
      </Card>

      <DataTable
        columns={[
          {
            key: "subject",
            header: "Ticket",
            render: (t) => (
              <div>
                <Link href={`/platform/tickets/${t.id}`} className="font-medium text-brand hover:underline">
                  {t.subject}
                </Link>
                <div className="text-xs text-foreground-muted">{t.company?.name}</div>
              </div>
            ),
          },
          { key: "opener", header: "Opened by", render: (t) => t.opener?.name ?? "—" },
          { key: "assignee", header: "Assigned to", render: (t) => t.assignee?.name ?? "—" },
          { key: "status", header: "Status", render: (t) => <StatusBadge status={t.status} /> },
          {
            key: "last_message_at",
            header: "Last activity",
            render: (t) => <RelativeTime value={t.last_message_at} fallback="—" />,
          },
        ]}
        rows={tickets}
        rowKey={(t) => t.id}
        emptyTitle="No tickets found."
        rowActions={(t) => [{ label: "Open", onSelect: () => router.push(`/platform/tickets/${t.id}`) }]}
        pagination={{ page: meta.current_page, perPage: meta.per_page, total: meta.total }}
        onPageChange={(nextPage) => navigate({ page: nextPage })}
      />
    </div>
  );
}

export { TicketsInboxTable };
