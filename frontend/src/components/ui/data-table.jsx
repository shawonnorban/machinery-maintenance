"use client";

import { ChevronUp, ChevronDown, ChevronsUpDown, MoreVertical, Search } from "lucide-react";
import { cn } from "@/lib/utils";
import { Input } from "@/components/ui/input";
import { Checkbox } from "@/components/ui/checkbox";
import { Skeleton } from "@/components/ui/skeleton";
import { EmptyState } from "@/components/ui/empty-state";
import { ErrorState } from "@/components/ui/error-state";
import { Dropdown } from "@/components/ui/dropdown";
import { Button } from "@/components/ui/button";

/**
 * docs/UI-DESIGN-SYSTEM.md §5: search/filter/sort/pagination/selection/bulk
 * actions/row actions, with explicit empty/loading/error states, compact
 * rows (px-4 py-3), tabular right-aligned numerics, sticky header, and a
 * mobile collapse to stacked cards below `md` (never a horizontally
 * scrolling page body — the table scrolls inside its own container instead).
 *
 * This component is a controlled shell: the caller owns data-fetching,
 * search/sort/pagination state, and passes the current page of rows in —
 * it never fetches or filters on its own, so the same contract works
 * against server-side pagination (the common case against the Laravel API).
 *
 * @param {{
 *   columns: { key: string, header: string, render?: (row: any) => React.ReactNode, sortable?: boolean, align?: "left" | "right" }[],
 *   rows: any[],
 *   rowKey: (row: any) => string,
 *   loading?: boolean,
 *   error?: string,
 *   onRetry?: () => void,
 *   emptyTitle?: string,
 *   emptyDescription?: string,
 *   emptyAction?: React.ReactNode,
 *   search?: { value: string, onChange: (value: string) => void, placeholder?: string },
 *   sort?: { key: string, direction: "asc" | "desc" },
 *   onSortChange?: (key: string) => void,
 *   pagination?: { page: number, perPage: number, total: number },
 *   onPageChange?: (page: number) => void,
 *   selectedKeys?: string[],
 *   onSelectedKeysChange?: (keys: string[]) => void,
 *   bulkActions?: { label: string, onSelect: (keys: string[]) => void, destructive?: boolean }[],
 *   rowActions?: (row: any) => { label: string, onSelect: () => void, destructive?: boolean }[],
 *   className?: string,
 * }} props
 */
function DataTable({
  columns,
  rows,
  rowKey,
  loading = false,
  error,
  onRetry,
  emptyTitle = "Nothing here yet",
  emptyDescription,
  emptyAction,
  search,
  sort,
  onSortChange,
  pagination,
  onPageChange,
  selectedKeys,
  onSelectedKeysChange,
  bulkActions,
  rowActions,
  className,
}) {
  const selectable = Boolean(selectedKeys && onSelectedKeysChange);
  const allKeys = rows.map(rowKey);
  const allSelected = selectable && allKeys.length > 0 && allKeys.every((key) => selectedKeys.includes(key));

  function toggleAll() {
    onSelectedKeysChange(allSelected ? [] : allKeys);
  }

  function toggleRow(key) {
    onSelectedKeysChange(
      selectedKeys.includes(key) ? selectedKeys.filter((k) => k !== key) : [...selectedKeys, key],
    );
  }

  const totalPages = pagination ? Math.max(1, Math.ceil(pagination.total / pagination.perPage)) : 1;

  return (
    <div className={cn("flex flex-col gap-3", className)}>
      {(search || (selectable && selectedKeys.length > 0 && bulkActions?.length)) && (
        <div className="flex flex-wrap items-center justify-between gap-2">
          {search ? (
            <div className="relative w-full max-w-xs">
              <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-foreground-subtle" />
              <Input
                value={search.value}
                onChange={(event) => search.onChange(event.target.value)}
                placeholder={search.placeholder ?? "Search…"}
                className="pl-9"
              />
            </div>
          ) : (
            <div />
          )}

          {selectable && selectedKeys.length > 0 && bulkActions?.length ? (
            <div className="flex items-center gap-2">
              <span className="text-xs text-foreground-muted">{selectedKeys.length} selected</span>
              {bulkActions.map((action) => (
                <Button
                  key={action.label}
                  size="sm"
                  variant={action.destructive ? "danger" : "outline"}
                  onClick={() => action.onSelect(selectedKeys)}
                >
                  {action.label}
                </Button>
              ))}
            </div>
          ) : null}
        </div>
      )}

      {error ? (
        <ErrorState description={error} onRetry={onRetry} />
      ) : !loading && rows.length === 0 ? (
        <EmptyState title={emptyTitle} description={emptyDescription} action={emptyAction} />
      ) : (
        <>
          {/* Desktop / tablet: real table, scrolling inside its own container.
              Rounding and the border live on this outer, `overflow-hidden`
              wrapper rather than the scrolling one below — `overflow-x-auto`
              alone doesn't clip the sticky header's own background, which
              otherwise squares off the top corners despite `rounded-sm`. */}
          <div className="hidden rounded-sm border border-border overflow-hidden md:block">
            <div className="overflow-x-auto">
            <table className="w-full text-sm">
              <thead className="sticky top-0 bg-surface-muted text-xs font-medium text-foreground-muted">
                <tr className="divide-x divide-border">
                  {selectable ? (
                    <th className="w-10 px-4 py-3">
                      <Checkbox checked={allSelected} onCheckedChange={toggleAll} aria-label="Select all rows" />
                    </th>
                  ) : null}
                  {columns.map((column) => (
                    <th
                      key={column.key}
                      className={cn("px-4 py-3 font-medium", column.align === "right" && "text-right")}
                    >
                      {column.sortable && onSortChange ? (
                        <button
                          type="button"
                          onClick={() => onSortChange(column.key)}
                          className="inline-flex items-center gap-1 hover:text-foreground"
                        >
                          {column.header}
                          {sort?.key === column.key ? (
                            sort.direction === "asc" ? (
                              <ChevronUp className="size-3.5" />
                            ) : (
                              <ChevronDown className="size-3.5" />
                            )
                          ) : (
                            <ChevronsUpDown className="size-3.5 opacity-50" />
                          )}
                        </button>
                      ) : (
                        column.header
                      )}
                    </th>
                  ))}
                  {rowActions ? <th className="w-10 px-4 py-3" /> : null}
                </tr>
              </thead>
              <tbody className="divide-y divide-border">
                {loading
                  ? Array.from({ length: 5 }).map((_, index) => (
                      <tr key={index} className="divide-x divide-border bg-surface">
                        {selectable ? (
                          <td className="px-4 py-3">
                            <Skeleton className="size-5" />
                          </td>
                        ) : null}
                        {columns.map((column) => (
                          <td key={column.key} className="px-4 py-3">
                            <Skeleton className="h-4 w-24" />
                          </td>
                        ))}
                        {rowActions ? <td className="px-4 py-3" /> : null}
                      </tr>
                    ))
                  : rows.map((row) => {
                      const key = rowKey(row);
                      return (
                        <tr key={key} className="divide-x divide-border bg-surface hover:bg-surface-muted/60">
                          {selectable ? (
                            <td className="px-4 py-3">
                              <Checkbox
                                checked={selectedKeys.includes(key)}
                                onCheckedChange={() => toggleRow(key)}
                                aria-label="Select row"
                              />
                            </td>
                          ) : null}
                          {columns.map((column) => (
                            <td
                              key={column.key}
                              className={cn(
                                "px-4 py-3 text-foreground",
                                column.align === "right" && "text-right tabular",
                              )}
                            >
                              {column.render ? column.render(row) : row[column.key]}
                            </td>
                          ))}
                          {rowActions ? (
                            <td className="px-4 py-3 text-right">
                              <Dropdown
                                trigger={
                                  <span className="inline-flex size-8 items-center justify-center rounded-sm text-foreground-muted hover:bg-surface-muted hover:text-foreground">
                                    <MoreVertical className="size-4" />
                                  </span>
                                }
                                items={rowActions(row)}
                              />
                            </td>
                          ) : null}
                        </tr>
                      );
                    })}
              </tbody>
            </table>
            </div>
          </div>

          {/* Mobile: stacked cards, never a horizontally scrolling page body. */}
          <div className="flex flex-col gap-3 md:hidden">
            {loading
              ? Array.from({ length: 3 }).map((_, index) => (
                  <div key={index} className="rounded-sm border border-border p-4">
                    <Skeleton className="mb-2 h-4 w-1/2" />
                    <Skeleton className="h-4 w-1/3" />
                  </div>
                ))
              : rows.map((row) => {
                  const key = rowKey(row);
                  return (
                    <div key={key} className="rounded-sm border border-border bg-surface p-4">
                      <div className="flex items-start justify-between gap-2">
                        <div className="flex items-start gap-2">
                          {selectable ? (
                            <Checkbox
                              checked={selectedKeys.includes(key)}
                              onCheckedChange={() => toggleRow(key)}
                              aria-label="Select row"
                              className="mt-0.5"
                            />
                          ) : null}
                          <dl className="flex flex-col gap-1.5">
                            {columns.map((column) => (
                              <div key={column.key} className="flex items-baseline gap-2 text-sm">
                                <dt className="text-xs font-medium text-foreground-muted">{column.header}</dt>
                                <dd className="text-foreground">{column.render ? column.render(row) : row[column.key]}</dd>
                              </div>
                            ))}
                          </dl>
                        </div>
                        {rowActions ? (
                          <Dropdown
                            trigger={
                              <span className="inline-flex size-8 shrink-0 items-center justify-center rounded-sm text-foreground-muted hover:bg-surface-muted">
                                <MoreVertical className="size-4" />
                              </span>
                            }
                            items={rowActions(row)}
                          />
                        ) : null}
                      </div>
                    </div>
                  );
                })}
          </div>
        </>
      )}

      {pagination && !error && rows.length > 0 ? (
        <div className="flex items-center justify-between gap-2 text-xs text-foreground-muted">
          <span>
            Page {pagination.page} of {totalPages} · {pagination.total} total
          </span>
          <div className="flex gap-2">
            <Button
              size="sm"
              variant="outline"
              disabled={pagination.page <= 1}
              onClick={() => onPageChange(pagination.page - 1)}
            >
              Previous
            </Button>
            <Button
              size="sm"
              variant="outline"
              disabled={pagination.page >= totalPages}
              onClick={() => onPageChange(pagination.page + 1)}
            >
              Next
            </Button>
          </div>
        </div>
      ) : null}
    </div>
  );
}

export { DataTable };
