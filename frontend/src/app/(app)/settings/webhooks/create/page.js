import { apiFetch } from "@/lib/api-server";
import { PageHeader } from "@/components/layout/page-header";
import { Card, CardBody } from "@/components/ui/card";
import { WebhookForm } from "@/components/webhooks/webhook-form";
import { getT } from "@/lib/i18n-server";
import { createWebhook } from "./actions";

export default async function CreateWebhookPage() {
  const [eventOptions, t, tn] = await Promise.all([apiFetch("/webhooks/events"), getT("webhook"), getT("nav")]);

  return (
    <>
      <PageHeader
        breadcrumb={[{ label: tn("settings") }, { label: t("webhooks"), href: "/settings/webhooks" }, { label: t("new_endpoint") }]}
        title={t("new_webhook_endpoint")}
      />

      <Card>
        <CardBody>
          <WebhookForm eventOptions={eventOptions} action={createWebhook} />
        </CardBody>
      </Card>
    </>
  );
}
