import { apiFetch } from "@/lib/api-server";
import { PageHeader } from "@/components/layout/page-header";
import { Card, CardBody } from "@/components/ui/card";
import { WebhookForm } from "@/components/webhooks/webhook-form";
import { updateWebhook } from "../actions";

export default async function EditWebhookPage({ params }) {
  const { endpointId } = await params;

  const [endpoint, eventOptions] = await Promise.all([
    apiFetch(`/webhooks/${endpointId}`),
    apiFetch("/webhooks/events"),
  ]);

  return (
    <>
      <PageHeader
        breadcrumb={[
          { label: "Settings" },
          { label: "Webhooks", href: "/settings/webhooks" },
          { label: endpoint.url, href: `/settings/webhooks/${endpointId}` },
          { label: "Edit" },
        ]}
        title="Edit webhook endpoint"
      />

      <Card>
        <CardBody>
          <WebhookForm endpoint={endpoint} eventOptions={eventOptions} action={updateWebhook.bind(null, endpointId)} />
        </CardBody>
      </Card>
    </>
  );
}
