"use client";

import { useTransition } from "react";
import { Popover } from "@base-ui/react/popover";
import { ChevronsUpDown, Check } from "lucide-react";
import { cn } from "@/lib/utils";

/**
 * The workspace switcher pill at the top of the sidebar. Hidden entirely
 * for the (common) single-company account — a dropdown with one disabled
 * option would just be a slower label. Switching mints a fresh bearer token
 * scoped to the chosen company (`switchCompanyAction`) rather than mutating
 * the current one; see that action's own docblock.
 */
function CompanySwitcher({ companies, currentCompanyId, switchCompanyAction, collapsed = false }) {
  const [pending, startTransition] = useTransition();
  const current = companies.find((c) => c.id === currentCompanyId) ?? companies[0];

  if (companies.length <= 1 || !current) {
    return collapsed ? null : (
      <div className="flex items-center gap-2 truncate px-2 py-1.5 text-sm font-semibold text-foreground">
        <CompanyMark name={current?.name} />
        <span className="truncate">{current?.name}</span>
      </div>
    );
  }

  function choose(companyId) {
    if (companyId === currentCompanyId || pending) return;
    startTransition(() => switchCompanyAction(companyId));
  }

  return (
    <Popover.Root>
      <Popover.Trigger
        disabled={pending}
        className={cn(
          "flex items-center gap-2 rounded-sm px-2 py-1.5 text-sm font-semibold text-foreground outline-none hover:bg-surface-muted disabled:opacity-60",
          collapsed ? "justify-center" : "w-full",
        )}
      >
        <CompanyMark name={current.name} />
        {!collapsed ? (
          <>
            <span className="min-w-0 flex-1 truncate text-left">{current.name}</span>
            <ChevronsUpDown className="size-3.5 shrink-0 text-foreground-muted" />
          </>
        ) : null}
      </Popover.Trigger>
      <Popover.Portal>
        <Popover.Positioner align="start" sideOffset={4} className="z-50 outline-none">
          <Popover.Popup className="w-64 rounded-sm border border-border bg-surface p-1 shadow-md">
            {companies.map((company) => (
              <button
                key={company.id}
                type="button"
                onClick={() => choose(company.id)}
                className="flex w-full items-center gap-2 rounded-sm px-3 py-2 text-left text-sm text-foreground hover:bg-surface-muted"
              >
                <CompanyMark name={company.name} />
                <span className="min-w-0 flex-1 truncate">{company.name}</span>
                {company.id === currentCompanyId ? <Check className="size-4 shrink-0 text-brand" /> : null}
              </button>
            ))}
          </Popover.Popup>
        </Popover.Positioner>
      </Popover.Portal>
    </Popover.Root>
  );
}

function CompanyMark({ name }) {
  const initial = name?.charAt(0)?.toUpperCase() || "?";

  return (
    <span className="flex size-6 shrink-0 items-center justify-center rounded-sm bg-brand text-xs font-bold text-brand-foreground">
      {initial}
    </span>
  );
}

export { CompanySwitcher };
