import Link from "next/link";
import { Settings } from "lucide-react";
import { apiFetch } from "@/lib/api-server";
import { PageHeader } from "@/components/layout/page-header";
import { buttonVariants } from "@/components/ui/button";
import { Badge } from "@/components/ui/badge";
import { cn } from "@/lib/utils";
import { NotificationsList } from "@/components/notifications/notifications-list";
import { MarkAllReadButton } from "@/components/notifications/mark-all-read-button";
import { markRead, markAllRead, acknowledge } from "./actions";

const TABS = [
  { value: "UNREAD", label: "Unread" },
  { value: "ALL", label: "All" },
];

/** Mirrors `NotificationController::index` — no permission beyond being signed in, since these are scoped to the caller by ownership rather than a role. */
export default async function NotificationsPage({ searchParams }) {
  const params = await searchParams;
  const filter = params.filter ?? "UNREAD";
  const page = Number(params.page ?? 1);

  const notifications = await apiFetch(`/notifications?filter=${filter}&page=${page}`, { includeMeta: true });
  const { unread_count: unreadCount, current_page: currentPage, last_page: lastPage, total } = notifications.meta;

  return (
    <>
      <PageHeader
        breadcrumb={[{ label: "Notifications" }]}
        title="Notifications"
        actions={
          <div className="flex flex-wrap items-center gap-2">
            <Link href="/notifications/preferences" className={cn(buttonVariants({ variant: "outline", size: "sm" }))}>
              <Settings /> Preferences
            </Link>
            {unreadCount > 0 ? <MarkAllReadButton action={markAllRead} /> : null}
          </div>
        }
      />

      <nav className="mb-4 flex gap-1 border-b border-border">
        {TABS.map((tab) => (
          <Link
            key={tab.value}
            href={`/notifications?filter=${tab.value}`}
            className={cn(
              "flex items-center gap-1.5 border-b-2 px-3 py-2 text-sm font-medium transition-colors duration-150",
              filter === tab.value ? "border-brand text-brand" : "border-transparent text-foreground-muted hover:text-foreground",
            )}
          >
            {tab.label}
            {tab.value === "UNREAD" && unreadCount > 0 ? <Badge variant="danger">{unreadCount}</Badge> : null}
          </Link>
        ))}
      </nav>

      <NotificationsList notifications={notifications.data} actions={{ markRead, acknowledge }} />

      {total > notifications.meta.per_page ? (
        <div className="mt-3 flex items-center justify-between gap-2 text-xs text-foreground-muted">
          <span>
            Page {currentPage} of {lastPage} · {total} total
          </span>
          <div className="flex gap-2">
            <Link
              href={`/notifications?filter=${filter}&page=${currentPage - 1}`}
              aria-disabled={currentPage <= 1}
              className={cn(buttonVariants({ variant: "outline", size: "sm" }), currentPage <= 1 && "pointer-events-none opacity-50")}
            >
              Previous
            </Link>
            <Link
              href={`/notifications?filter=${filter}&page=${currentPage + 1}`}
              aria-disabled={currentPage >= lastPage}
              className={cn(buttonVariants({ variant: "outline", size: "sm" }), currentPage >= lastPage && "pointer-events-none opacity-50")}
            >
              Next
            </Link>
          </div>
        </div>
      ) : null}
    </>
  );
}
