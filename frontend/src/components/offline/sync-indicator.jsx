"use client";

import { CloudOff, Cloud, CloudUpload, AlertTriangle, X } from "lucide-react";
import { Dropdown } from "@/components/ui/dropdown";
import { Badge } from "@/components/ui/badge";
import { useOfflineQueue } from "@/lib/offline/use-offline-queue";

/**
 * Always visible in the topbar (docs/12-Stack-Migration-Implementation-
 * Plan.md Phase E — "a technician must never have to wonder whether their
 * work was recorded"), not just on technician screens: a report can still
 * be syncing after its author has navigated away from the form that
 * created it. Renders nothing but a quiet cloud icon when the queue is
 * empty, so it costs nothing on every other screen either.
 */
function SyncIndicator() {
  const { pending, failed, discardDraft } = useOfflineQueue();

  if (pending.length === 0 && failed.length === 0) {
    return (
      <span className="rounded-sm p-2 text-foreground-muted" title="All work synced" aria-label="All work synced">
        <Cloud className="size-[18px]" />
      </span>
    );
  }

  const items = [
    ...pending.map((draft) => ({
      label: `${draft.label} — pending`,
      icon: <CloudUpload />,
      disabled: true,
    })),
    ...failed.map((draft) => ({
      label: `${draft.label} — failed, tap to discard`,
      icon: <X />,
      destructive: true,
      onSelect: () => discardDraft(draft.key),
    })),
  ];

  const Icon = failed.length > 0 ? AlertTriangle : CloudOff;

  return (
    <Dropdown
      trigger={
        <span
          className="relative flex size-9 items-center justify-center rounded-sm text-foreground-muted hover:bg-surface-muted hover:text-foreground"
          title={`${pending.length} pending, ${failed.length} failed`}
        >
          <Icon className={failed.length > 0 ? "size-[18px] text-danger" : "size-[18px]"} />
          <Badge variant={failed.length > 0 ? "danger" : "warning"} className="absolute -top-1 -right-1 min-w-4 justify-center px-1 py-0 text-[10px]">
            {pending.length + failed.length}
          </Badge>
        </span>
      }
      items={items}
    />
  );
}

export { SyncIndicator };
