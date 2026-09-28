"use client";

import { useState } from "react";
import { Popover } from "@base-ui/react/popover";
import Link from "next/link";
import { Bell } from "lucide-react";
import { cn } from "@/lib/utils";
import { RelativeTime } from "@/components/ui/relative-time";

const SEVERITY_DOT = { CRITICAL: "bg-danger", WARNING: "bg-warning", INFO: "bg-info" };

function truncate(text, length) {
  if (!text) return text;
  return text.length > length ? `${text.slice(0, length).trimEnd()}…` : text;
}

/** Mirrors `NotificationBell` exactly against the platform console's own notifications endpoint — the bell only previews, "View all" is the one link that navigates. */
function PlatformNotificationBell({ unreadCount = 0, recent = [] }) {
  const [open, setOpen] = useState(false);

  return (
    <Popover.Root open={open} onOpenChange={setOpen}>
      <Popover.Trigger
        className="relative rounded-sm p-2 text-foreground-muted outline-none hover:bg-surface-muted hover:text-foreground"
        aria-label={unreadCount > 0 ? `Notifications, ${unreadCount} unread` : "Notifications"}
      >
        <Bell className="size-[18px]" />
        {unreadCount > 0 ? (
          <span className="absolute top-1 right-1 flex size-2 rounded-full bg-brand" aria-hidden />
        ) : null}
      </Popover.Trigger>
      <Popover.Portal>
        <Popover.Positioner align="end" sideOffset={4} className="z-50 outline-none">
          <Popover.Popup className="w-80 rounded-sm border border-border bg-surface shadow-md">
            <div className="border-b border-border px-4 py-2.5 text-sm font-semibold text-foreground">
              Notifications
            </div>

            {recent.length === 0 ? (
              <p className="px-4 py-6 text-center text-sm text-foreground-muted">Nothing here.</p>
            ) : (
              <ul className="max-h-96 overflow-y-auto divide-y divide-border">
                {recent.map((notification) => (
                  <li key={notification.id}>
                    <Link
                      href="/platform/notifications"
                      onClick={() => setOpen(false)}
                      className="flex items-start gap-2.5 px-4 py-2.5 hover:bg-surface-muted"
                    >
                      <span
                        className={cn("mt-1.5 size-2 shrink-0 rounded-full", SEVERITY_DOT[notification.severity] ?? "bg-foreground-muted")}
                        aria-hidden
                      />
                      <span className="min-w-0 flex-1">
                        <span
                          className={cn(
                            "block truncate text-sm",
                            notification.is_read ? "text-foreground-muted" : "font-semibold text-foreground",
                          )}
                        >
                          {truncate(notification.title, 48)}
                        </span>
                        {notification.body ? (
                          <span className="block truncate text-xs text-foreground-muted">{truncate(notification.body, 64)}</span>
                        ) : null}
                        <span className="text-xs text-foreground-muted/70">
                          <RelativeTime value={notification.created_at} />
                        </span>
                      </span>
                    </Link>
                  </li>
                ))}
              </ul>
            )}

            <Link
              href="/platform/notifications"
              onClick={() => setOpen(false)}
              className="block border-t border-border px-4 py-2.5 text-center text-sm font-medium text-brand hover:bg-surface-muted"
            >
              View all
            </Link>
          </Popover.Popup>
        </Popover.Positioner>
      </Popover.Portal>
    </Popover.Root>
  );
}

export { PlatformNotificationBell };
