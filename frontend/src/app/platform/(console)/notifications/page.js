import Link from "next/link";
import { Bell, CircleAlert, Info, TriangleAlert } from "lucide-react";
import { platformApiFetch } from "@/lib/platform-api-server";
import { RelativeTime } from "@/components/ui/relative-time";
import { cn } from "@/lib/utils";
import { PlatformHeader, Panel, Empty } from "@/components/platform/ui";
import { MarkAllReadButton } from "@/components/platform/mark-all-read-button";
import { markAllRead } from "./actions";

const SEVERITY = {
  CRITICAL: { icon: CircleAlert, tile: "bg-danger-subtle text-danger" },
  WARNING: { icon: TriangleAlert, tile: "bg-warning-subtle text-warning" },
  INFO: { icon: Info, tile: "bg-info-subtle text-info" },
};

const FILTERS = [
  ["UNREAD", "Unread", "/platform/notifications"],
  ["ALL", "All", "/platform/notifications?filter=ALL"],
];

export default async function PlatformNotificationsPage({ searchParams }) {
  const params = await searchParams;
  const filter = params.filter === "ALL" ? "ALL" : "UNREAD";
  const notifications = await platformApiFetch(`/notifications?filter=${filter}&per_page=50`, { includeMeta: true });

  return (
    <>
      <PlatformHeader
        breadcrumb={[{ label: "Notifications" }]}
        title="Notifications"
        description="What the platform itself has flagged for you."
        icon={<Bell />}
        actions={<MarkAllReadButton action={markAllRead} />}
      />
      <Panel
        title={filter === "ALL" ? "All notifications" : "Unread"}
        subtitle={`${notifications.meta?.unread_count ?? 0} unread`}
        icon={Bell}
        action={
          <div className="flex rounded-md border border-border-strong p-0.5 text-[13px] font-semibold">
            {FILTERS.map(([key, label, href]) => (
              <Link key={key} href={href} className={cn("rounded px-3 py-1", filter === key ? "bg-brand text-brand-foreground" : "text-foreground-muted hover:text-foreground")}>
                {label}
              </Link>
            ))}
          </div>
        }
      >
        {notifications.data.length === 0 ? (
          <Empty>Nothing here.</Empty>
        ) : (
          <ul className="divide-y divide-border">
            {notifications.data.map((n) => {
              const sev = SEVERITY[n.severity] ?? SEVERITY.INFO;
              const Icon = sev.icon;
              return (
                <li key={n.id} className={cn("flex items-start gap-3 px-5 py-3.5", !n.read_at && "bg-brand-subtle/30")}>
                  <span className={cn("mt-0.5 flex size-8 shrink-0 items-center justify-center rounded-md", sev.tile)}>
                    <Icon className="size-4" />
                  </span>
                  <div className="min-w-0 flex-1">
                    <p className="text-sm font-semibold text-foreground">{n.title}</p>
                    <p className="mt-0.5 text-sm text-foreground-muted">{n.body}</p>
                  </div>
                  <span className="shrink-0 text-xs text-foreground-subtle">
                    <RelativeTime value={n.created_at} />
                  </span>
                </li>
              );
            })}
          </ul>
        )}
      </Panel>
    </>
  );
}
