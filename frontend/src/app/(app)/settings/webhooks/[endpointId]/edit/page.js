import { apiFetch } from "@/lib/api-server";
import { PageHeader } from "@/components/layout/page-header";
import { Card, CardBody } from "@/components/ui/card";
import { WebhookForm } from "@/components/webhooks/webhook-form";
import { getT } from "@/lib/i18n-server";
import { updateWebhook } from "../actions";

export default async function EditWebhookPage({ params }) {
  const { endpointId } = await params;

  const [endpoint, eventOptions, t, tc, tn] = await Promise.all([
    apiFetch(`/webhooks/${endpointId}`),
    apiFetch("/webhooks/events"),
    getT("webhook"),
    getT("common"),
    getT("nav"),
  ]);

  return (
    <>
      <PageHeader
        breadcrumb={[
          { label: tn("settings") },
          { label: t("webhooks"), href: "/settings/webhooks" },
          { label: endpoint.url, href: `/settings/webhooks/${endpointId}` },
          { label: tc("edit") },
        ]}
        title={t("edit_webhook_endpoint")}
      />

      <Card>
        <CardBody>
          <WebhookForm endpoint={endpoint} eventOptions={eventOptions} action={updateWebhook.bind(null, endpointId)} />
        </CardBody>
      </Card>
    </>
  );
}
