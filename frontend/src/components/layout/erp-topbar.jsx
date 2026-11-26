"use client";

import { useEffect, useMemo, useState, useTransition } from "react";
import Link from "next/link";
import { usePathname, useRouter } from "next/navigation";
import { Dialog } from "@base-ui/react/dialog";
import { Popover } from "@base-ui/react/popover";
import { ChevronDown, LogOut, Search, User as UserIcon } from "lucide-react";
import { cn } from "@/lib/utils";
import { moduleTone } from "@/lib/module-tones";
import { useT } from "@/lib/i18n";
import { activePath } from "@/components/layout/erp-sidebar";
import { pushOff } from "@/components/chat/chat-push";

/** Brickland's topbar module chip: the current module's tile, its name, and "MODULE" under it. */
function ModuleChip({ modules }) {
  const t = useT("nav");
  const pathname = usePathname();
  const active = activePath(modules, pathname);
  const mod = modules.find((m) => m.code === active?.moduleCode);
  if (!mod) return null;
  const tone = moduleTone(mod.code);

  return (
    <div className="hidden items-center gap-2.5 md:flex">
      <span className={cn("flex size-9 shrink-0 items-center justify-center rounded-md [&_svg]:size-[18px]", tone.tile)}>{mod.icon}</span>
      <span className="leading-tight">
        <span className="block text-sm font-semibold text-foreground">{mod.label}</span>
        <span className="block text-[10px] font-semibold tracking-[0.12em] text-foreground-muted uppercase">{t("module_label")}</span>
      </span>
    </div>
  );
}

/**
 * "Search modules… ⌘K": every screen the caller can open, by module and
 * name; Enter or a click goes there.
 */
function ScreenSearch({ modules }) {
  const t = useT("nav");
  const router = useRouter();
  const [open, setOpen] = useState(false);
  const [query, setQuery] = useState("");
  const [cursor, setCursor] = useState(0);

  const screens = useMemo(
    () =>
      modules.flatMap((mod) =>
        mod.entries.flatMap((entry) => (entry.type === "folder" ? entry.items : [entry])).map((screen) => ({ ...screen, moduleLabel: mod.label, moduleCode: mod.code })),
      ),
    [modules],
  );
  const results = useMemo(() => {
    const needle = query.trim().toLowerCase();
    const hits = needle ? screens.filter((s) => `${s.moduleLabel} ${s.label}`.toLowerCase().includes(needle)) : screens;
    return hits.slice(0, 50);
  }, [screens, query]);

  useEffect(() => {
    function onKey(event) {
      if ((event.metaKey || event.ctrlKey) && event.key.toLowerCase() === "k") {
        event.preventDefault();
        setOpen(true);
      }
    }
    window.addEventListener("keydown", onKey);
    return () => window.removeEventListener("keydown", onKey);
  }, []);

  function go(screen) {
    setOpen(false);
    setQuery("");
    router.push(screen.href);
  }

  function onInputKey(event) {
    if (event.key === "ArrowDown") {
      event.preventDefault();
      setCursor((c) => Math.min(c + 1, results.length - 1));
    } else if (event.key === "ArrowUp") {
      event.preventDefault();
      setCursor((c) => Math.max(c - 1, 0));
    } else if (event.key === "Enter" && results[cursor]) {
      go(results[cursor]);
    }
  }

  return (
    <Dialog.Root open={open} onOpenChange={setOpen}>
      <Dialog.Trigger className="hidden h-9 w-56 items-center gap-2 rounded-md border border-border bg-surface px-3 text-sm text-foreground-muted hover:border-border-strong lg:flex">
        <Search className="size-4" />
        <span className="flex-1 truncate text-left">{t("search_modules")}</span>
        <kbd className="rounded border border-border bg-surface-muted px-1.5 text-[10px] font-semibold">Ctrl K</kbd>
      </Dialog.Trigger>
      <Dialog.Portal>
        <Dialog.Backdrop className="fixed inset-0 z-50 bg-foreground/30" />
        <Dialog.Popup className="fixed top-24 left-1/2 z-50 w-[calc(100vw-2rem)] max-w-xl -translate-x-1/2 overflow-hidden rounded-md border border-border bg-surface shadow-lg outline-none">
          <div className="flex items-center gap-2 border-b border-border px-4">
            <Search className="size-4 text-foreground-muted" />
            <input
              autoFocus
              value={query}
              onChange={(event) => {
                setQuery(event.target.value);
                setCursor(0);
              }}
              onKeyDown={onInputKey}
              placeholder={t("search_screens")}
              className="h-12 flex-1 bg-transparent text-sm outline-none"
            />
          </div>
          <ul className="max-h-[50vh] overflow-y-auto p-1.5">
            {results.length === 0 ? <li className="px-3 py-6 text-center text-sm text-foreground-muted">{t("no_screen_found")}</li> : null}
            {results.map((screen, index) => (
              <li key={screen.code}>
                <button
                  type="button"
                  onMouseEnter={() => setCursor(index)}
                  onClick={() => go(screen)}
                  className={cn("flex w-full items-center gap-3 rounded-md px-3 py-2 text-left text-sm", index === cursor ? "bg-brand-subtle text-brand" : "text-foreground")}
                >
                  <span className={cn("flex size-7 items-center justify-center rounded-md [&_svg]:size-4", moduleTone(screen.moduleCode).tile)}>{screen.icon}</span>
                  <span className="min-w-0 flex-1 truncate font-medium">{screen.label}</span>
                  <span className="truncate text-xs text-foreground-muted">{screen.moduleLabel}</span>
                </button>
              </li>
            ))}
          </ul>
        </Dialog.Popup>
      </Dialog.Portal>
    </Dialog.Root>
  );
}

/** The signed-in person, top right as in Brickland: initials, name and email, with Your account and Sign out. */
function UserMenu({ name, email }) {
  const [pending, startTransition] = useTransition();
  const router = useRouter();
  const initials =
    (name ?? "?")
      .split(/\s+/)
      .map((part) => part.charAt(0))
      .join("")
      .slice(0, 2)
      .toUpperCase() || "?";

  function signOut() {
    startTransition(async () => {
      await pushOff(); // this browser stops taking the chat notifications of whoever signs out
      await fetch("/api/auth/logout", { method: "POST" });
      router.push("/login");
      router.refresh();
    });
  }

  return (
    <Popover.Root>
      <Popover.Trigger className="flex items-center gap-2.5 rounded-md py-1 pr-1 pl-1.5 outline-none hover:bg-surface-muted" aria-label="Account menu">
        <span className="flex size-8 shrink-0 items-center justify-center rounded-full bg-rose-50 text-xs font-semibold text-rose-500">{initials}</span>
        <span className="hidden min-w-0 text-left leading-tight sm:block">
          <span className="block max-w-40 truncate text-sm font-semibold text-foreground">{name}</span>
          <span className="block max-w-40 truncate text-[11px] text-foreground-muted">{email}</span>
        </span>
        <ChevronDown className="size-4 text-foreground-muted" />
      </Popover.Trigger>
      <Popover.Portal>
        <Popover.Positioner side="bottom" align="end" sideOffset={6} className="z-50 outline-none">
          <Popover.Popup className="w-60 rounded-md border border-border bg-surface shadow-md">
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

export { ModuleChip, ScreenSearch, UserMenu };
