"use client";

import { useState } from "react";
import { Popover } from "@base-ui/react/popover";
import Link from "next/link";
import { Bell } from "lucide-react";
import { cn } from "@/lib/utils";
import { RelativeTime } from "@/components/ui/relative-time";
import { toAppPath } from "@/lib/notification-path";
import { useT } from "@/lib/i18n";

const SEVERITY_DOT = { CRITICAL: "bg-danger", WARNING: "bg-warning", INFO: "bg-info" };

function truncate(text, length) {
  if (!text) return text;
  return text.length > length ? `${text.slice(0, length).trimEnd()}…` : text;
}

/**
 * Mirrors `components/layout/notification-bell.blade.php` — the bell itself
 * only opens a preview of the 5 most recent notifications (CoreUI's
 * dropdown there, a Base UI popover here); it never navigates on its own.
 * "View all notifications" at the bottom is the one link that does.
 */
function NotificationBell({ unreadCount = 0, recent = [] }) {
  const [open, setOpen] = useState(false);
  const t = useT("notification");

  return (
    <Popover.Root open={open} onOpenChange={setOpen}>
      <Popover.Trigger
        className="relative rounded-sm p-2 text-foreground-muted outline-none hover:bg-surface-muted hover:text-foreground"
        aria-label={unreadCount > 0 ? `Notifications, ${unreadCount} unread` : "Notifications"}
      >
        <Bell className="size-[18px]" />
        {unreadCount > 0 ? (
          <span className="absolute top-1 right-1 flex size-2 rounded-full bg-danger" aria-hidden />
        ) : null}
      </Popover.Trigger>
      <Popover.Portal>
        <Popover.Positioner align="end" sideOffset={4} className="z-50 outline-none">
          <Popover.Popup className="w-80 rounded-sm border border-border bg-surface shadow-md">
            <div className="border-b border-border px-4 py-2.5 text-sm font-semibold text-foreground">{t("notifications")}</div>

            {recent.length === 0 ? (
              <p className="px-4 py-6 text-center text-sm text-foreground-muted">{t("no_notifications")}</p>
            ) : (
              <ul className="max-h-96 overflow-y-auto divide-y divide-border">
                {recent.map((notification) => {
                  const href = toAppPath(notification.action_url) ?? "/notifications";
                  return (
                    <li key={notification.id}>
                      <Link
                        href={href}
                        onClick={() => setOpen(false)}
                        className="flex items-start gap-2.5 px-4 py-2.5 hover:bg-surface-muted"
                      >
                        <span
                          className={cn("mt-1.5 size-2 shrink-0 rounded-full", SEVERITY_DOT[notification.severity] ?? "bg-foreground-subtle")}
                          aria-hidden
                        />
                        <span className="min-w-0 flex-1">
                          <span className={cn("block truncate text-sm", notification.is_read ? "text-foreground" : "font-semibold text-foreground")}>
                            {truncate(notification.title, 48)}
                          </span>
                          {notification.body ? (
                            <span className="block truncate text-xs text-foreground-muted">{truncate(notification.body, 64)}</span>
                          ) : null}
                          <span className="text-xs text-foreground-subtle">
                            <RelativeTime value={notification.created_at} />
                          </span>
                        </span>
                      </Link>
                    </li>
                  );
                })}
              </ul>
            )}

            <Link
              href="/notifications"
              onClick={() => setOpen(false)}
              className="block border-t border-border px-4 py-2.5 text-center text-sm font-medium text-brand hover:bg-surface-muted"
            >
              {t("view_all")}
            </Link>
          </Popover.Popup>
        </Popover.Positioner>
      </Popover.Portal>
    </Popover.Root>
  );
}

export { NotificationBell };
