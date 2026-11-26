import Link from "next/link";
import { ListChecks } from "lucide-react";
import { cn } from "@/lib/utils";

/**
 * Brickland's two-pane tab (Lookup Tables, Rules Violation, Deletion Admin
 * Panel …): the groups on the left with their counts, the chosen group's
 * records on the right. Each group is a link, so a choice can be bookmarked.
 *
 * @param {{
 *   groups: {key: string, label: string, sub?: string, count: number}[], active?: string|null, hrefFor: (key: string) => string,
 *   groupsTitle: string, recordsTitle: string, emptyText: string, children?: React.ReactNode,
 * }} props
 */
function GroupedRecords({ groups, active, hrefFor, groupsTitle, recordsTitle, emptyText, children }) {
  return (
    <div className="grid grid-cols-1 items-start gap-4 xl:grid-cols-[20rem_1fr]">
      <div className="rounded-lg border border-border bg-surface">
        <div className="flex items-center justify-between border-b border-border px-4 py-3">
          <span className="text-sm font-semibold text-foreground">{groupsTitle}</span>
          <span className="rounded-full bg-surface-muted px-1.5 text-xs font-semibold text-foreground-muted tabular-nums">{groups.length}</span>
        </div>
        <ul className="max-h-[70vh] overflow-y-auto p-2">
          {groups.map((group) => (
            <li key={group.key}>
              <Link
                href={hrefFor(group.key)}
                scroll={false}
                className={cn("flex items-center justify-between gap-3 rounded-md px-3 py-2 text-sm", group.key === active ? "bg-brand-subtle text-brand" : "text-foreground hover:bg-surface-muted")}
              >
                <span className="min-w-0">
                  <span className="block truncate font-medium">{group.label}</span>
                  {group.sub ? <span className="block truncate text-xs text-foreground-muted">{group.sub}</span> : null}
                </span>
                <span className="text-xs font-semibold text-foreground-muted tabular-nums">{group.count}</span>
              </Link>
            </li>
          ))}
        </ul>
      </div>
      <div className="min-w-0">
        {active ? (
          <div className="flex flex-col gap-2">
            <p className="px-1 text-sm font-semibold text-foreground">{recordsTitle}</p>
            {children}
          </div>
        ) : (
          <div className="flex flex-col items-center gap-2 rounded-lg border border-border bg-surface px-5 py-14 text-center">
            <span className="flex size-12 items-center justify-center rounded-lg bg-surface-muted text-foreground-muted">
              <ListChecks className="size-5" />
            </span>
            <p className="max-w-md text-[13px] text-foreground-muted">{emptyText}</p>
          </div>
        )}
      </div>
    </div>
  );
}

export { GroupedRecords };
