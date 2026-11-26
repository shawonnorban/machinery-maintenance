"use client";

import { useState } from "react";
import Link from "next/link";
import { usePathname } from "next/navigation";
import { ChevronDown, ChevronRight } from "lucide-react";
import { cn } from "@/lib/utils";
import { moduleTone } from "@/lib/module-tones";
import { useT } from "@/lib/i18n";

function matches(pathname, href) {
  return pathname === href || (href !== "/" && pathname?.startsWith(`${href}/`));
}

/** Every screen under a list of entries, with the folders it sits in (outermost first). */
function screensOf(entries, chain = []) {
  return entries.flatMap((entry) => (entry.type === "folder" ? screensOf(entry.entries ?? entry.items, [...chain, entry.code]) : [[entry, chain]]));
}

/** The module, folders and screen the current url belongs to (longest href wins). */
function activePath(modules, pathname) {
  let best = null;
  for (const mod of modules) {
    for (const [screen, folders] of screensOf(mod.entries)) {
      if (matches(pathname, screen.href) && (!best || screen.href.length > best.screen.href.length)) {
        best = { moduleCode: mod.code, folder: folders.at(-1) ?? null, folders, screen };
      }
    }
  }
  return best;
}

/**
 * Brickland's sidebar (AnnotechERP UI parity): section headings, then modules
 * with their screen count, opening onto folders (with counts) and screens.
 * A folder may hold folders (OPERATION → Style & Order …). Every folder,
 * outer or inner, reads alike — capitals in the brand colour, as Dye House
 * shows them — an inner one only indented under its parent; screens in
 * green, a long name wrapping onto a second line rather than cut. Every
 * screen is its own link.
 * The module and folders holding the current page open by themselves;
 * others open and close by hand and stay as left.
 *
 * Icons arrive as rendered nodes (the server builds them from lucide names),
 * because a server component cannot hand a component function across.
 */
function ErpSidebar({ modules, collapsed = false, moduleCount = 0 }) {
  const t = useT("nav");
  const pathname = usePathname();
  const active = activePath(modules, pathname);
  const [open, setOpen] = useState(() => new Set([active?.moduleCode, ...(active?.folders ?? [])].filter(Boolean)));
  const [seenPath, setSeenPath] = useState(pathname);

  // A navigation opens the new page's module and folders, leaving the rest as they were.
  if (seenPath !== pathname) {
    setSeenPath(pathname);
    const needed = active ? [active.moduleCode, ...active.folders] : [];
    if (needed.some((code) => !open.has(code))) setOpen(new Set([...open, ...needed]));
  }

  function toggle(code) {
    setOpen((current) => {
      const next = new Set(current);
      if (next.has(code)) next.delete(code);
      else next.add(code);
      return next;
    });
  }

  return (
    <div className="flex min-h-0 flex-1 flex-col">
      <nav className="flex-1 overflow-y-auto px-3 pt-2 pb-4">
        {modules.map((mod, index) => {
          const heading = index === 0 || modules[index - 1].section !== mod.section ? mod.sectionLabel : null;
          const tone = moduleTone(mod.code);
          const isOpen = !collapsed && open.has(mod.code);
          const isActive = active?.moduleCode === mod.code;
          const single = mod.entries.length === 1 && mod.entries[0].type === "screen" && mod.count === 1;

          return (
            <div key={mod.code}>
              {heading && !collapsed ? <p className="mt-4 mb-1.5 px-2 text-[11px] font-semibold tracking-[0.08em] text-brand uppercase">{heading}</p> : null}
              {heading && collapsed ? <div className="my-3 border-t border-border" aria-hidden /> : null}

              <div className="group/mod relative">
                {single ? (
                  <Link
                    href={mod.entries[0].href}
                    title={collapsed ? mod.label : undefined}
                    className={cn(
                      "flex items-center gap-3 rounded-md px-2 py-2 text-sm font-medium transition-colors",
                      collapsed && "justify-center px-0",
                      isActive ? "bg-brand-subtle text-brand" : "text-foreground hover:bg-surface-muted",
                    )}
                  >
                    <span className={cn("size-[18px] shrink-0 [&_svg]:size-[18px]", tone.icon)}>{mod.icon}</span>
                    {!collapsed ? <span className="min-w-0 flex-1 truncate">{mod.label}</span> : null}
                  </Link>
                ) : (
                  <button
                    type="button"
                    onClick={() => !collapsed && toggle(mod.code)}
                    aria-expanded={isOpen}
                    title={collapsed ? mod.label : undefined}
                    className={cn(
                      "flex w-full items-center gap-3 rounded-md px-2 py-2 text-left text-sm font-medium transition-colors",
                      collapsed && "justify-center px-0",
                      isOpen || (collapsed && isActive) ? "bg-brand-subtle text-brand" : "text-foreground hover:bg-surface-muted",
                    )}
                  >
                    <span className={cn("size-[18px] shrink-0 [&_svg]:size-[18px]", tone.icon)}>{mod.icon}</span>
                    {!collapsed ? (
                      <>
                        <span className="min-w-0 flex-1 truncate">{mod.label}</span>
                        <span className="text-[11px] font-semibold text-foreground-muted tabular-nums">{mod.count}</span>
                        {isOpen ? <ChevronDown className="size-4 shrink-0" /> : <ChevronRight className="size-4 shrink-0 text-foreground-muted" />}
                      </>
                    ) : null}
                  </button>
                )}

                {isOpen && !single ? (
                  <div className="mt-0.5 mb-1 ml-[18px] flex flex-col gap-0.5 border-l border-border pl-2">
                    {mod.entries.map((entry) =>
                      entry.type === "folder" ? (
                        <Folder key={entry.code} folder={entry} open={open} onToggle={toggle} activeHref={active?.screen.href} />
                      ) : (
                        <ScreenLink key={entry.code} screen={entry} active={active?.screen.href === entry.href} />
                      ),
                    )}
                  </div>
                ) : null}

                {collapsed && !single ? (
                  // Collapsed rail: hovering a module shows its screens beside it.
                  <div className="invisible absolute top-0 left-full z-40 ml-2 max-h-[70vh] w-60 overflow-y-auto rounded-md border border-border bg-surface p-2 opacity-0 shadow-md transition-opacity group-hover/mod:visible group-hover/mod:opacity-100">
                    <p className="px-2 py-1 text-[11px] font-semibold tracking-wider text-brand uppercase">{mod.label}</p>
                    {mod.entries.flatMap((entry) => (entry.type === "folder" ? entry.items : [entry])).map((screen) => (
                      <ScreenLink key={screen.code} screen={screen} active={active?.screen.href === screen.href} />
                    ))}
                  </div>
                ) : null}
              </div>
            </div>
          );
        })}
      </nav>

      {!collapsed ? (
        <div className="m-3 rounded-md border border-border bg-surface p-3">
          <div className="flex items-center justify-between">
            <span className="text-xs font-semibold text-foreground">{t("system_status")}</span>
            <span className="inline-flex items-center gap-1 rounded-full bg-success-subtle px-2 py-0.5 text-[10px] font-semibold tracking-wide text-success uppercase">
              <span className="size-1.5 rounded-full bg-success" aria-hidden />
              {t("system_live")}
            </span>
          </div>
          <p className="mt-1.5 text-xs text-success">{t("all_systems_operational")}</p>
          <p className="mt-0.5 text-[11px] font-medium text-foreground">{t("modules_count", { count: moduleCount })}</p>
        </div>
      ) : null}
    </div>
  );
}

function Folder({ folder, open, onToggle, activeHref, depth = 0 }) {
  const isOpen = open.has(folder.code);
  return (
    <div>
      <button
        type="button"
        onClick={() => onToggle(folder.code)}
        aria-expanded={isOpen}
        className={cn(
          "flex w-full items-center gap-2 rounded-md px-2 py-1.5 text-left transition-colors hover:bg-surface-muted",
          "text-[11px] font-semibold tracking-[0.04em] text-brand uppercase",
          isOpen && "bg-surface-muted",
        )}
      >
        <span className="size-4 shrink-0 [&_svg]:size-4">{folder.icon}</span>
        <span className="min-w-0 flex-1 leading-snug break-words">{folder.label}</span>
        <span className="text-[11px] font-semibold text-foreground-muted tabular-nums">{folder.items.length}</span>
        {isOpen ? <ChevronDown className="size-3.5 shrink-0" /> : <ChevronRight className="size-3.5 shrink-0 text-foreground-muted" />}
      </button>
      {isOpen ? (
        <div className="mt-0.5 ml-[15px] flex flex-col gap-0.5 border-l border-border pl-2">
          {(folder.entries ?? folder.items).map((entry) =>
            entry.type === "folder" ? (
              <Folder key={entry.code} folder={entry} open={open} onToggle={onToggle} activeHref={activeHref} depth={depth + 1} />
            ) : (
              <ScreenLink key={entry.code} screen={entry} active={activeHref === entry.href} />
            ),
          )}
        </div>
      ) : null}
    </div>
  );
}

function ScreenLink({ screen, active }) {
  return (
    <Link
      href={screen.href}
      target={screen.newWindow ? "_blank" : undefined}
      aria-current={active ? "page" : undefined}
      className={cn(
        "flex items-start gap-2 rounded-md px-2 py-1.5 text-[12.5px] leading-snug font-medium transition-colors",
        active ? "bg-brand-subtle text-brand" : "text-nav-leaf hover:bg-surface-muted hover:text-foreground",
      )}
    >
      <span className="mt-px size-4 shrink-0 [&_svg]:size-4">{screen.icon}</span>
      <span className="min-w-0 flex-1 break-words">{screen.label}</span>
    </Link>
  );
}

export { ErpSidebar, activePath };
