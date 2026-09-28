import { apiFetch } from "@/lib/api-server";
import { PageHeader } from "@/components/layout/page-header";
import { Card, CardHeader, CardTitle, CardBody } from "@/components/ui/card";
import { StatusBadge } from "@/components/ui/status-badge";
import { Badge } from "@/components/ui/badge";
import { FormattedDateTime } from "@/components/ui/formatted-date-time";
import { ReplyForm } from "@/components/support/reply-form";
import { TicketAttachments } from "@/components/support/ticket-attachments";
import { TicketHeaderActions } from "@/components/support/ticket-header-actions";
import { cn } from "@/lib/utils";
import { getT } from "@/lib/i18n-server";
import { replyTicket, deleteTicket } from "../actions";

const PRIORITY_TONE = { URGENT: "danger", HIGH: "warning", MEDIUM: "info", LOW: "neutral" };
const PRIORITY_BORDER = { URGENT: "border-l-danger", HIGH: "border-l-warning", MEDIUM: "border-l-info", LOW: "border-l-border-strong" };

/** Mirrors `tickets/show.blade.php` — the conversation itself. */
export default async function SupportTicketDetailPage({ params }) {
  const { ticketId } = await params;
  const [ticket, attachments, t] = await Promise.all([
    apiFetch(`/support/tickets/${ticketId}`),
    apiFetch(`/support/tickets/${ticketId}/attachments`),
    getT("support"),
  ]);

  return (
    <>
      <PageHeader
        breadcrumb={[{ label: t("support") }, { label: t("tickets"), href: "/support/tickets" }, { label: ticket.subject }]}
        title={ticket.subject}
        actions={ticket.can_edit ? <TicketHeaderActions ticketId={ticketId} deleteTicket={deleteTicket} /> : null}
      />

      {/* One glance answers "where does this stand" regardless of how long
          the thread below gets — the accent color is the same priority cue
          Assets/Breakdowns use for their own summary strip. */}
      <Card className={cn("mb-5 border-l-4 p-4", PRIORITY_BORDER[ticket.priority] ?? "border-l-border-strong")}>
        <div className="flex flex-wrap items-center justify-between gap-x-8 gap-y-3">
          <div className="flex flex-wrap items-center gap-x-8 gap-y-3">
            <SummaryItem label={t("priority")}>
              <Badge variant={PRIORITY_TONE[ticket.priority] ?? "neutral"}>{t(`priority_${ticket.priority?.toLowerCase()}`)}</Badge>
            </SummaryItem>
            <SummaryItem label={t("category")}>
              <Badge>{t(`category_${ticket.category?.toLowerCase()}`)}</Badge>
            </SummaryItem>
            <SummaryItem label={t("status")}>
              <StatusBadge status={ticket.status} label={t(`status_${ticket.status?.toLowerCase()}`)} />
            </SummaryItem>
          </div>

          {ticket.is_open && ticket.sla?.resolution_due_at ? (
            <div className="flex flex-wrap items-center gap-x-6 gap-y-1 text-sm">
              {ticket.sla.breached ? <Badge variant="danger">{t("sla_breached")}</Badge> : null}
              {!ticket.sla.first_responded_at ? (
                <span className="text-foreground-muted">
                  {t("sla_first_response_due")}: <FormattedDateTime value={ticket.sla.first_response_due_at} mode="datetime" />
                </span>
              ) : null}
              <span className="text-foreground-muted">
                {t("sla_resolution_due")}: <FormattedDateTime value={ticket.sla.resolution_due_at} mode="datetime" />
              </span>
            </div>
          ) : null}
        </div>
      </Card>

      {/* Same 2/3–1/3 split as the create page's own form + "what to
          expect" sidebar — attachments live where that sidebar's response-
          time card sat, not buried under the thread. */}
      <div className="grid grid-cols-1 gap-5 lg:grid-cols-3">
        <div className="lg:col-span-2">
          <Card>
            <CardHeader>
              <CardTitle>{t("conversation")}</CardTitle>
            </CardHeader>
            <CardBody className="flex flex-col gap-4">
              <div className="flex flex-col gap-3">
                {ticket.messages.map((message) => (
                  <div
                    key={message.id}
                    className={cn(
                      "rounded-sm border border-border p-4",
                      message.author_is_platform ? "bg-brand-subtle/40" : "bg-surface",
                    )}
                  >
                    <div className="mb-2 flex items-center justify-between text-xs text-foreground-muted">
                      <span className="font-medium text-foreground">
                        {message.author?.name ?? "—"}
                        {message.author_is_platform ? ` · ${t("platform_support")}` : null}
                      </span>
                      <FormattedDateTime value={message.created_at} mode="datetime" />
                    </div>
                    <p className="whitespace-pre-wrap text-sm text-foreground">{message.body}</p>
                  </div>
                ))}
              </div>

              {ticket.is_open ? (
                <div className="border-t border-border pt-4">
                  <ReplyForm action={replyTicket.bind(null, ticketId)} />
                </div>
              ) : (
                <p className="border-t border-border pt-4 text-sm text-foreground-muted">{t("closed_hint")}</p>
              )}
            </CardBody>
          </Card>
        </div>

        <div className="lg:col-span-1">
          <Card>
            <CardBody>
              <TicketAttachments attachments={attachments} />
            </CardBody>
          </Card>
        </div>
      </div>
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
