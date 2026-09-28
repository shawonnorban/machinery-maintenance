"use client";

import { useTransition } from "react";
import { useRouter } from "next/navigation";
import { Popover } from "@base-ui/react/popover";
import { LogOut, ChevronDown } from "lucide-react";
import { cn } from "@/lib/utils";

/**
 * `PlatformShell`'s own identity/sign-out block, rendered in the topbar
 * (there's no bottom-of-sidebar profile footer here the way `AppShell` has
 * `SidebarProfile` — the console's sidebar is nav-only).
 */
function PlatformUserMenu({ name, email }) {
  const [pending, startTransition] = useTransition();
  const router = useRouter();

  function signOut() {
    startTransition(async () => {
      await fetch("/api/platform-auth/logout", { method: "POST" });
      router.push("/platform/login");
      router.refresh();
    });
  }

  return (
    <Popover.Root>
      <Popover.Trigger
        className="flex items-center gap-2 rounded-sm px-2 py-1.5 text-left outline-none hover:bg-surface-muted"
        aria-label="Account menu"
      >
        <span className="flex size-7 shrink-0 items-center justify-center rounded-full bg-brand-subtle text-xs font-semibold text-brand-hover">
          {name?.charAt(0)?.toUpperCase() || "?"}
        </span>
        <span className="hidden flex-col leading-tight sm:flex">
          <span className="truncate text-xs font-medium text-foreground">{name}</span>
        </span>
        <ChevronDown className="size-3.5 shrink-0 text-foreground-muted" />
      </Popover.Trigger>
      <Popover.Portal>
        <Popover.Positioner side="bottom" align="end" sideOffset={4} className="z-50 outline-none">
          <Popover.Popup className="w-56 rounded-sm border border-border bg-surface shadow-md">
            <div className="border-b border-border px-4 py-3">
              <p className="truncate text-sm font-medium text-foreground">{name}</p>
              <p className="truncate text-xs text-foreground-muted">{email}</p>
            </div>
            <button
              type="button"
              onClick={signOut}
              disabled={pending}
              className={cn(
                "flex w-full items-center gap-2 px-4 py-2.5 text-left text-sm text-danger hover:bg-danger-subtle disabled:opacity-50",
              )}
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

export { PlatformUserMenu };
