"use client";

import { useActionState, useTransition } from "react";
import { Card, CardBody, CardFooter } from "@/components/ui/card";
import { Textarea } from "@/components/ui/textarea";
import { Select } from "@/components/ui/select";
import { Button } from "@/components/ui/button";
import { Alert } from "@/components/ui/alert";
import { StatusBadge } from "@/components/ui/status-badge";
import { FormattedDateTime } from "@/components/ui/formatted-date-time";
import { cn } from "@/lib/utils";
import { useToastManager } from "@/components/ui/toast";

const STATUS_OPTIONS = [
  { value: "OPEN", label: "Open" },
  { value: "IN_PROGRESS", label: "In progress" },
  { value: "RESOLVED", label: "Resolved" },
  { value: "CLOSED", label: "Closed" },
];

/** A ticket thread, reached the same way whether from the cross-customer inbox or a customer's own Tickets tab (mirrors `_ticket_thread.blade.php`). */
function TicketThread({ ticket, me, replyAction, statusAction, assignAction }) {
  const [pending, startTransition] = useTransition();
  const toastManager = useToastManager();

  function changeStatus(status) {
    startTransition(async () => {
      const result = await statusAction(status);
      if (result?.status === "error") toastManager.add({ title: result.message, type: "danger" });
    });
  }

  function toggleAssignment() {
    startTransition(async () => {
      const assignedToMe = ticket.assignee?.id === me.id;
      const result = await assignAction(assignedToMe ? null : me.id);
      if (result?.status === "error") toastManager.add({ title: result.message, type: "danger" });
    });
  }

  return (
    <div className="flex flex-col gap-5">
      <Card>
        <CardBody className="flex flex-wrap items-center justify-between gap-3">
          <div className="flex items-center gap-3">
            <StatusBadge status={ticket.status} />
            <span className="text-xs text-foreground-muted">
              Assigned to {ticket.assignee?.name ?? "nobody yet"}
            </span>
          </div>
          <div className="flex items-center gap-2">
            <Button size="sm" variant="outline" loading={pending} onClick={toggleAssignment}>
              {ticket.assignee?.id === me.id ? "Unassign me" : "Assign to me"}
            </Button>
            <div className="w-40">
              <Select options={STATUS_OPTIONS} value={ticket.status} onValueChange={changeStatus} />
            </div>
          </div>
        </CardBody>
      </Card>

      <Card>
        <CardBody className="flex flex-col gap-4">
          {ticket.messages.map((message) => (
            <div
              key={message.id}
              className={cn(
                "max-w-[85%] rounded-sm border p-3",
                message.author_is_platform ? "self-end border-brand/25 bg-brand-subtle" : "self-start border-border bg-surface-muted",
              )}
            >
              <div className="mb-1 flex items-center justify-between gap-4 text-xs text-foreground-muted">
                <span className="font-medium text-foreground">{message.author?.name ?? "—"}</span>
                <FormattedDateTime value={message.created_at} />
              </div>
              <p className="whitespace-pre-wrap text-sm text-foreground">{message.body}</p>
            </div>
          ))}
        </CardBody>
      </Card>

      <ReplyForm action={replyAction} />
    </div>
  );
}

function ReplyForm({ action }) {
  const [state, dispatch, pending] = useActionState(action, null);

  return (
    <Card>
      <CardBody>
        {state?.status === "error" ? <Alert variant="danger" className="mb-3">{state.message}</Alert> : null}
        <form action={dispatch} className="flex flex-col gap-3">
          <Textarea name="body" rows={3} maxLength={5000} placeholder="Reply to this ticket…" required />
          <div className="flex justify-end">
            <Button type="submit" loading={pending}>
              Send reply
            </Button>
          </div>
        </form>
      </CardBody>
    </Card>
  );
}

export { TicketThread };
