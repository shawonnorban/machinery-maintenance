import { platformApiFetch } from "@/lib/platform-api-server";
import { PageHeader } from "@/components/layout/page-header";
import { Card, CardBody } from "@/components/ui/card";
import { Alert } from "@/components/ui/alert";
import { RelativeTime } from "@/components/ui/relative-time";
import { MarkAllReadButton } from "@/components/platform/mark-all-read-button";
import { markAllRead } from "./actions";

const VARIANT = { CRITICAL: "danger", WARNING: "warning", INFO: "info" };

export default async function PlatformNotificationsPage({ searchParams }) {
  const params = await searchParams;
  const filter = params.filter ?? "UNREAD";

  const notifications = await platformApiFetch(`/notifications?filter=${filter}&per_page=50`, { includeMeta: true });

  return (
    <>
      <PageHeader
        breadcrumb={[{ label: "Notifications" }]}
        title="Notifications"
        description="What the platform itself has flagged for you."
        actions={<MarkAllReadButton action={markAllRead} />}
      />

      <Card>
        <CardBody className="flex flex-col gap-3 p-5">
          {notifications.data.length === 0 ? (
            <p className="text-sm text-foreground-muted">Nothing here.</p>
          ) : (
            notifications.data.map((n) => (
              <Alert key={n.id} variant={VARIANT[n.severity] ?? "info"} title={n.title}>
                <div className="flex flex-col gap-1">
                  <span>{n.body}</span>
                  <span className="text-[11px] text-foreground-subtle">
                    <RelativeTime value={n.created_at} />
                  </span>
                </div>
              </Alert>
            ))
          )}
        </CardBody>
      </Card>
    </>
  );
}
