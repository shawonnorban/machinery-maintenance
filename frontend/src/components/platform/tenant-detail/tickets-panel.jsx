"use client";

import Link from "next/link";
import { Ticket } from "lucide-react";
import { StatusBadge } from "@/components/ui/status-badge";
import { RelativeTime } from "@/components/ui/relative-time";
import { Panel, PTable } from "@/components/platform/ui";

/** This customer's own support tickets — the same thread the cross-customer inbox opens. */
function TicketsPanel({ tickets }) {
  return (
    <Panel title="Tickets" subtitle={`${tickets.length} ticket${tickets.length === 1 ? "" : "s"} from this customer`} icon={Ticket}>
      <PTable
        rows={tickets}
        rowKey={(t) => t.id}
        empty="No tickets from this customer."
        columns={[
          {
            key: "subject",
            label: "Subject",
            render: (t) => (
              <Link href={`/platform/tickets/${t.id}`} className="font-semibold text-brand hover:underline">
                {t.subject}
              </Link>
            ),
          },
          { key: "opener", label: "Opened by", render: (t) => t.opener?.name ?? "—" },
          { key: "assignee", label: "Assigned to", render: (t) => t.assignee?.name ?? <span className="text-foreground-subtle">Nobody</span> },
          { key: "last", label: "Last message", render: (t) => <span className="text-[13px] text-foreground-muted"><RelativeTime value={t.last_message_at} /></span> },
          { key: "status", label: "Status", render: (t) => <StatusBadge status={t.status} /> },
        ]}
      />
    </Panel>
  );
}

export { TicketsPanel };
