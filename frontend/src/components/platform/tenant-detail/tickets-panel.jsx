"use client";

import Link from "next/link";
import { Card, CardHeader, CardTitle, CardBody } from "@/components/ui/card";
import { StatusBadge } from "@/components/ui/status-badge";
import { RelativeTime } from "@/components/ui/relative-time";

/** This customer's own support tickets — the same thread `/platform/tickets/[id]` shows from the cross-customer inbox. */
function TicketsPanel({ tickets }) {
  return (
    <Card>
      <CardHeader>
        <CardTitle>Tickets ({tickets.length})</CardTitle>
      </CardHeader>
      <CardBody className="flex flex-col divide-y divide-border p-0">
        {tickets.length === 0 ? (
          <p className="p-5 text-sm text-foreground-muted">No tickets from this customer.</p>
        ) : (
          tickets.map((ticket) => (
            <Link
              key={ticket.id}
              href={`/platform/tickets/${ticket.id}`}
              className="flex flex-wrap items-center justify-between gap-3 p-5 hover:bg-surface-muted"
            >
              <div>
                <p className="text-sm font-medium text-brand">{ticket.subject}</p>
                <p className="text-xs text-foreground-muted">
                  Opened by {ticket.opener?.name ?? "—"}
                  {ticket.assignee ? ` · assigned to ${ticket.assignee.name}` : ""}
                </p>
              </div>
              <div className="flex items-center gap-3">
                <span className="text-xs text-foreground-subtle">
                  <RelativeTime value={ticket.last_message_at} />
                </span>
                <StatusBadge status={ticket.status} />
              </div>
            </Link>
          ))
        )}
      </CardBody>
    </Card>
  );
}

export { TicketsPanel };
