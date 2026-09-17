import { apiFetch } from "@/lib/api-server";
import { PageHeader } from "@/components/layout/page-header";
import { Card, CardBody } from "@/components/ui/card";
import { StatusBadge } from "@/components/ui/status-badge";
import { WebhookHeaderActions } from "@/components/webhooks/webhook-header-actions";
import { DeliveriesTable } from "@/components/webhooks/deliveries-table";
import { getT } from "@/lib/i18n-server";
import { enableEndpoint, pauseEndpoint, rotateSecret, redeliver } from "../actions";

/** Mirrors `WebhookController::show`. */
export default async function WebhookDetailPage({ params }) {
  const { endpointId } = await params;
  const [endpoint, t, tn] = await Promise.all([apiFetch(`/webhooks/${endpointId}`), getT("webhook"), getT("nav")]);

  return (
    <>
      <PageHeader
        breadcrumb={[{ label: tn("settings") }, { label: t("webhooks"), href: "/settings/webhooks" }, { label: endpoint.url }]}
        title={endpoint.url}
        description={endpoint.description ?? undefined}
        actions={<WebhookHeaderActions endpoint={endpoint} actions={{ enableEndpoint, pauseEndpoint, rotateSecret }} />}
      />

      <div className="flex flex-col gap-4">
        <Card>
          <CardBody className="flex flex-wrap gap-6 text-sm">
            <div>
              <div className="text-xs text-foreground-muted">{t("status")}</div>
              <StatusBadge status={endpoint.status} label={t(`statuses.${endpoint.status}`)} />
            </div>
            <div>
              <div className="text-xs text-foreground-muted">{t("events")}</div>
              <div className="font-medium text-foreground">{endpoint.events?.join(", ") || "—"}</div>
            </div>
            <div>
              <div className="text-xs text-foreground-muted">{t("failures")}</div>
              <div className="font-medium text-foreground">{endpoint.consecutive_failure_count}</div>
            </div>
            {endpoint.disabled_reason ? (
              <div>
                <div className="text-xs text-foreground-muted">{t("disabled_reason")}</div>
                <div className="font-medium text-danger">{endpoint.disabled_reason}</div>
              </div>
            ) : null}
          </CardBody>
        </Card>

        <Card>
          <CardBody>
            <h2 className="mb-4 text-sm font-medium text-foreground">{t("recent_deliveries")}</h2>
            <DeliveriesTable deliveries={endpoint.recent_deliveries} endpointId={endpointId} redeliver={redeliver} />
          </CardBody>
        </Card>
      </div>
    </>
  );
}
