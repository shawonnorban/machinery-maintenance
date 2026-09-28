"use client";

import { useActionState, useRef, useState, useTransition } from "react";
import { Paperclip, Upload } from "lucide-react";
import { Card, CardBody, CardFooter } from "@/components/ui/card";
import { Textarea } from "@/components/ui/textarea";
import { Select } from "@/components/ui/select";
import { Checkbox } from "@/components/ui/checkbox";
import { Button } from "@/components/ui/button";
import { Alert } from "@/components/ui/alert";
import { Badge } from "@/components/ui/badge";
import { StatusBadge } from "@/components/ui/status-badge";
import { FormattedDateTime } from "@/components/ui/formatted-date-time";
import { EmptyState } from "@/components/ui/empty-state";
import { FileInput } from "@/components/ui/file-input";
import { cn } from "@/lib/utils";
import { useToastManager } from "@/components/ui/toast";

const STATUS_OPTIONS = [
  { value: "OPEN", label: "Open" },
  { value: "IN_PROGRESS", label: "In progress" },
  { value: "RESOLVED", label: "Resolved" },
  { value: "CLOSED", label: "Closed" },
];

const PRIORITY_OPTIONS = [
  { value: "LOW", label: "Low" },
  { value: "MEDIUM", label: "Medium" },
  { value: "HIGH", label: "High" },
  { value: "URGENT", label: "Urgent" },
];

const CATEGORY_OPTIONS = [
  { value: "GENERAL", label: "General" },
  { value: "BILLING", label: "Billing" },
  { value: "TECHNICAL", label: "Technical" },
  { value: "FEATURE_REQUEST", label: "Feature request" },
  { value: "BUG", label: "Bug" },
];

const PRIORITY_TONE = { URGENT: "danger", HIGH: "warning", MEDIUM: "info", LOW: "neutral" };

const UNASSIGNED = "__unassigned__";

/** A ticket thread, reached the same way whether from the cross-customer inbox or a customer's own Tickets tab (mirrors `_ticket_thread.blade.php`). */
function TicketThread({ ticket, me, staff = [], attachments = [], replyAction, statusAction, assignAction, reclassifyAction, uploadAction }) {
  const [pending, startTransition] = useTransition();
  const toastManager = useToastManager();

  const assigneeOptions = [
    { value: UNASSIGNED, label: "Unassigned" },
    ...staff.map((person) => ({ value: person.id, label: person.id === me.id ? `${person.name} (you)` : person.name })),
  ];

  function changeStatus(status) {
    startTransition(async () => {
      const result = await statusAction(status);
      if (result?.status === "error") toastManager.add({ title: result.message, type: "danger" });
    });
  }

  // A plain reassignment picker rather than only a self-assign toggle — a
  // ticket handed off to a colleague (they're out, or it needs a different
  // specialism) previously had no path except that colleague signing in and
  // assigning it to themselves.
  function changeAssignee(userId) {
    startTransition(async () => {
      const result = await assignAction(userId === UNASSIGNED ? null : userId);
      if (result?.status === "error") toastManager.add({ title: result.message, type: "danger" });
    });
  }

  function changePriority(priority) {
    startTransition(async () => {
      const result = await reclassifyAction(priority, ticket.category);
      if (result?.status === "error") toastManager.add({ title: result.message, type: "danger" });
    });
  }

  function changeCategory(category) {
    startTransition(async () => {
      const result = await reclassifyAction(ticket.priority, category);
      if (result?.status === "error") toastManager.add({ title: result.message, type: "danger" });
    });
  }

  return (
    <div className="flex flex-col gap-5">
      <Card>
        <CardBody className="flex flex-wrap items-center justify-between gap-3">
          <div className="flex flex-wrap items-center gap-2">
            <StatusBadge status={ticket.status} />
            {ticket.sla?.breached ? <Badge variant="danger">SLA breached</Badge> : null}
          </div>
          <div className="flex flex-wrap items-center gap-2">
            <div className="w-36">
              <Select options={PRIORITY_OPTIONS} value={ticket.priority} onValueChange={changePriority} disabled={pending} />
            </div>
            <div className="w-44">
              <Select options={CATEGORY_OPTIONS} value={ticket.category} onValueChange={changeCategory} disabled={pending} />
            </div>
            <div className="w-48">
              <Select
                options={assigneeOptions}
                value={ticket.assignee?.id ?? UNASSIGNED}
                onValueChange={changeAssignee}
                disabled={pending}
              />
            </div>
            <div className="w-40">
              <Select options={STATUS_OPTIONS} value={ticket.status} onValueChange={changeStatus} disabled={pending} />
            </div>
          </div>
        </CardBody>
        {ticket.sla ? (
          <CardFooter className="flex flex-wrap gap-x-6 gap-y-1 text-xs text-foreground-muted">
            {!ticket.sla.first_responded_at ? (
              <span>
                First response due: <FormattedDateTime value={ticket.sla.first_response_due_at} />
              </span>
            ) : null}
            <span>
              Resolution due: <FormattedDateTime value={ticket.sla.resolution_due_at} />
            </span>
          </CardFooter>
        ) : null}
      </Card>

      <Card>
        <CardBody className="flex flex-col gap-4">
          {ticket.messages.map((message) => (
            <div
              key={message.id}
              className={cn(
                "max-w-[85%] rounded-sm border p-3",
                message.is_internal
                  ? "self-end border-warning/30 bg-warning-subtle"
                  : message.author_is_platform
                    ? "self-end border-brand/25 bg-brand-subtle"
                    : "self-start border-border bg-surface-muted",
              )}
            >
              <div className="mb-1 flex items-center justify-between gap-4 text-xs text-foreground-muted">
                <span className="font-medium text-foreground">
                  {message.author?.name ?? "—"}
                  {message.is_internal ? " · Internal note" : null}
                </span>
                <FormattedDateTime value={message.created_at} />
              </div>
              <p className="whitespace-pre-wrap text-sm text-foreground">{message.body}</p>
            </div>
          ))}
        </CardBody>
      </Card>

      <ReplyForm action={replyAction} />

      <TicketAttachments attachments={attachments} action={uploadAction} />
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
          <div className="flex items-center justify-between">
            <label className="flex items-center gap-2 text-sm text-foreground-muted">
              <Checkbox name="is_internal" value="true" />
              Internal note — never sent to the customer
            </label>
            <Button type="submit" loading={pending}>
              Send reply
            </Button>
          </div>
        </form>
      </CardBody>
    </Card>
  );
}

function TicketAttachments({ attachments, action }) {
  const formRef = useRef(null);
  const [pending, setPending] = useState(false);
  const [error, setError] = useState(null);
  const toastManager = useToastManager();

  async function handleSubmit(event) {
    event.preventDefault();
    const formData = new FormData(event.currentTarget);
    setPending(true);
    setError(null);
    const result = await action(formData);
    setPending(false);
    if (result?.status === "success") {
      toastManager.add({ title: "File uploaded", type: "success" });
      formRef.current?.reset();
    } else if (result?.status === "error") {
      setError(result.message);
    }
  }

  return (
    <Card>
      <CardBody className="flex flex-col gap-3">
        <h3 className="text-sm font-medium text-foreground">Attachments</h3>

        {attachments.length === 0 ? (
          <EmptyState icon={<Paperclip />} title="No attachments yet" />
        ) : (
          <div className="divide-y divide-border rounded-sm border border-border">
            {attachments.map((file) => (
              <a
                key={file.id}
                href={`/api/files/${file.id}/download`}
                target="_blank"
                rel="noopener noreferrer"
                className="flex items-center justify-between gap-3 p-3 text-sm hover:bg-surface-muted"
              >
                <span className="text-foreground">{file.original_name}</span>
                <span className="shrink-0 text-xs text-foreground-muted">
                  {file.human_size} · <FormattedDateTime value={file.created_at} mode="date" fallback="" />
                </span>
              </a>
            ))}
          </div>
        )}

        {error ? <Alert variant="danger">{error}</Alert> : null}

        <form
          ref={formRef}
          onSubmit={handleSubmit}
          className="flex flex-col gap-3 rounded-sm border border-border bg-surface-muted/50 p-3 sm:flex-row sm:items-center"
        >
          <div className="flex-1">
            <FileInput name="file" required />
          </div>
          <Button type="submit" size="sm" loading={pending}>
            <Upload /> Upload
          </Button>
        </form>
      </CardBody>
    </Card>
  );
}

export { TicketThread };
