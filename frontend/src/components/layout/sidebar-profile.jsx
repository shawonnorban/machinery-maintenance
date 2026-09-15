"use client";

import { useTransition } from "react";
import { useRouter } from "next/navigation";
import { Popover } from "@base-ui/react/popover";
import Link from "next/link";
import { LogOut, User as UserIcon, MoreHorizontal } from "lucide-react";
import { cn } from "@/lib/utils";

/**
 * The account identity block pinned to the bottom of the sidebar — where
 * this app's reference design puts it, rather than the topbar corner
 * `UserMenu` used before this. Same actions (account page, sign out), new
 * home.
 */
function SidebarProfile({ name, email, collapsed = false }) {
  const [pending, startTransition] = useTransition();
  const router = useRouter();

  function signOut() {
    startTransition(async () => {
      await fetch("/api/auth/logout", { method: "POST" });
      router.push("/login");
      router.refresh();
    });
  }

  const initial = name?.charAt(0)?.toUpperCase() || "?";

  return (
    <Popover.Root>
      <Popover.Trigger
        className={cn(
          "flex w-full items-center gap-2.5 border-t border-border px-3 py-3 text-left outline-none hover:bg-surface-muted",
          collapsed && "justify-center px-0",
        )}
        aria-label="Account menu"
      >
        <span className="flex size-8 shrink-0 items-center justify-center rounded-full bg-success text-sm font-semibold text-white">
          {initial}
        </span>
        {!collapsed ? (
          <>
            <span className="min-w-0 flex-1">
              <span className="block truncate text-sm font-medium text-foreground">{name}</span>
              <span className="block truncate text-xs text-foreground-muted">{email}</span>
            </span>
            <MoreHorizontal className="size-4 shrink-0 text-foreground-muted" />
          </>
        ) : null}
      </Popover.Trigger>
      <Popover.Portal>
        <Popover.Positioner side="top" align="start" sideOffset={4} className="z-50 outline-none">
          <Popover.Popup className="w-64 rounded-sm border border-border bg-surface shadow-md">
            <div className="border-b border-border px-4 py-3">
              <p className="truncate text-sm font-medium text-foreground">{name}</p>
              <p className="truncate text-xs text-foreground-muted">{email}</p>
            </div>

            <Link href="/account" className="flex items-center gap-2 px-4 py-2.5 text-sm text-foreground hover:bg-surface-muted">
              <UserIcon className="size-4 text-foreground-muted" />
              Your account
            </Link>

            <button
              type="button"
              onClick={signOut}
              disabled={pending}
              className="flex w-full items-center gap-2 border-t border-border px-4 py-2.5 text-left text-sm text-danger hover:bg-danger-subtle disabled:opacity-50"
            >
              <LogOut className="size-4" />
              {pending ? "Signing out…" : "Sign out"}
            </button>
          </Popover.Popup>
        </Popover.Positioner>
      </Popover.Portal>
    </Popover.Root>
  );
}

export { SidebarProfile };
