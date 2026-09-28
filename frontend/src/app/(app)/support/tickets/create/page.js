import { Clock, ListChecks, Paperclip } from "lucide-react";
import { PageHeader } from "@/components/layout/page-header";
import { Card, CardBody } from "@/components/ui/card";
import { NewTicketForm } from "@/components/support/new-ticket-form";
import { getT } from "@/lib/i18n-server";
import { createTicket } from "../actions";

export default async function CreateSupportTicketPage() {
  const t = await getT("support");

  return (
    <>
      <PageHeader
        breadcrumb={[{ label: t("support") }, { label: t("tickets"), href: "/support/tickets" }, { label: t("new_ticket") }]}
        title={t("new_ticket_title")}
      />

      <div className="grid grid-cols-1 gap-5 lg:grid-cols-3">
        <div className="lg:col-span-2">
          <Card>
            <CardBody>
              <NewTicketForm action={createTicket} />
            </CardBody>
          </Card>
        </div>

        {/* Sets expectations up front — response time and the status flow —
            the way a commercial support desk's contact form usually does,
            rather than leaving the customer to find out by waiting. */}
        <div className="lg:col-span-1">
          <Card>
            <CardBody className="flex flex-col gap-5">
              <InfoRow icon={<Clock />} title={t("response_time_heading")} description={t("response_time_hint")} />

              <div className="border-t border-border pt-4">
                <InfoRow icon={<ListChecks />} title={t("what_happens_next")} />
                <ol className="mt-3 flex flex-col gap-2 pl-1 text-sm text-foreground-muted">
                  <li className="flex gap-2">
                    <span className="font-medium text-foreground">1.</span> {t("step_open")}
                  </li>
                  <li className="flex gap-2">
                    <span className="font-medium text-foreground">2.</span> {t("step_in_progress")}
                  </li>
                  <li className="flex gap-2">
                    <span className="font-medium text-foreground">3.</span> {t("step_resolved")}
                  </li>
                </ol>
              </div>

              <div className="border-t border-border pt-4">
                <InfoRow icon={<Paperclip />} description={t("attachments_hint_create")} />
              </div>
            </CardBody>
          </Card>
        </div>
      </div>
    </>
  );
}

function InfoRow({ icon, title, description }) {
  return (
    <div className="flex items-start gap-3">
      <span className="flex size-8 shrink-0 items-center justify-center rounded-sm bg-surface-muted text-foreground-muted [&_svg]:size-4">
        {icon}
      </span>
      <div>
        {title ? <p className="text-sm font-semibold text-foreground">{title}</p> : null}
        {description ? <p className="text-sm text-foreground-muted">{description}</p> : null}
      </div>
    </div>
  );
}
