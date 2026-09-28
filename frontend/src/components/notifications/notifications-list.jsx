"use client";

import { useTransition } from "react";
import { useRouter } from "next/navigation";
import Link from "next/link";
import { Card } from "@/components/ui/card";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { EmptyState } from "@/components/ui/empty-state";
import { useToastManager } from "@/components/ui/toast";
import { FormattedDateTime } from "@/components/ui/formatted-date-time";
import { toAppPath } from "@/lib/notification-path";
import { BellOff } from "lucide-react";
import { useT } from "@/lib/i18n";

const SEVERITY_TONE = { CRITICAL: "danger", WARNING: "warning", INFO: "info" };

/**
 * Mirrors `notification::notifications.index.blade.php`'s list-group —
 * severity, title, an escalation badge when it is one, body, actions
 * (open/acknowledge/mark read). Owns the read/acknowledge mutations itself
 * since it's the client component that actually needs to call them.
 */
function NotificationsList({ notifications, actions }) {
  const t = useT("notification");
  const router = useRouter();
  const [pending, startTransition] = useTransition();
  const toastManager = useToastManager();

  function run(action, id) {
    startTransition(async () => {
      const result = await action(id);
      if (result?.status === "success") {
        router.refresh();
      } else if (result?.status === "error") {
        toastManager.add({ title: result.message, type: "danger" });
      }
    });
  }

  if (notifications.length === 0) {
    return (
      <Card>
        <EmptyState
          icon={<BellOff />}
          title={t("no_notifications")}
          description={t("no_notifications_hint")}
        />
      </Card>
    );
  }

  return (
    <Card className="divide-y divide-border">
      {notifications.map((notification) => (
        <div key={notification.id} className={`flex items-start gap-3 p-4 ${notification.is_read ? "" : "bg-surface-muted"}`}>
          <div className="min-w-0 flex-1">
            <div className="flex flex-wrap items-center gap-2">
              <Badge variant={SEVERITY_TONE[notification.severity] ?? "neutral"}>{t(`severity_${notification.severity?.toLowerCase()}`)}</Badge>
              <span className={notification.is_read ? "text-foreground" : "font-semibold text-foreground"}>{notification.title}</span>
              {notification.is_escalation ? <Badge variant="danger">{t("escalated")}</Badge> : null}
            </div>

            {notification.body ? <p className="mt-1 text-sm text-foreground-muted">{notification.body}</p> : null}

            {notification.is_escalation ? (
              <p className="mt-1 text-xs text-danger">{t("escalated_from")}</p>
            ) : null}

            <p className="mt-1 text-xs text-foreground-muted"><FormattedDateTime value={notification.created_at} /></p>
          </div>

          <div className="flex shrink-0 flex-col items-end gap-1.5">
            {toAppPath(notification.action_url) ? (
              <Link href={toAppPath(notification.action_url)}>
                <Button size="sm">{t("open")}</Button>
              </Link>
            ) : null}

            {!notification.is_acknowledged ? (
              <Button variant="outline" size="sm" loading={pending} onClick={() => run(actions.acknowledge, notification.id)}>
                {t("acknowledge")}
              </Button>
            ) : null}

            {!notification.is_read ? (
              <Button variant="ghost" size="sm" loading={pending} onClick={() => run(actions.markRead, notification.id)}>
                {t("mark_read")}
              </Button>
            ) : null}
          </div>
        </div>
      ))}
    </Card>
  );
}

export { NotificationsList };
