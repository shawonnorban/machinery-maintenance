import { apiFetch } from "@/lib/api-server";
import { PageHeader } from "@/components/layout/page-header";
import { Card, CardBody } from "@/components/ui/card";
import { WebhookForm } from "@/components/webhooks/webhook-form";
import { createWebhook } from "./actions";

export default async function CreateWebhookPage() {
  const eventOptions = await apiFetch("/webhooks/events");

  return (
    <>
      <PageHeader breadcrumb={[{ label: "Settings" }, { label: "Webhooks", href: "/settings/webhooks" }, { label: "New endpoint" }]} title="New webhook endpoint" />

      <Card>
        <CardBody>
          <WebhookForm eventOptions={eventOptions} action={createWebhook} />
        </CardBody>
      </Card>
    </>
  );
}
