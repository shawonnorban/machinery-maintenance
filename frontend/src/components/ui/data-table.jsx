"use client";

import { useMemo, useRef, useState } from "react";
import { usePathname, useRouter } from "next/navigation";
import {
  ArrowDown,
  ArrowUp,
  CalendarClock,
  CircleCheck,
  Gauge,
  SkipForward,
  ChevronLeft,
  ChevronRight,
  ChevronsLeft,
  ChevronsRight,
  ChevronsUpDown,
  Download,
  Eye,
  Pencil,
  Power,
  RefreshCw,
  Search,
  Trash2,
} from "lucide-react";
import { cn } from "@/lib/utils";
import { Checkbox } from "@/components/ui/checkbox";
import { Skeleton } from "@/components/ui/skeleton";
import { EmptyState } from "@/components/ui/empty-state";
import { ErrorState } from "@/components/ui/error-state";
import { Button } from "@/components/ui/button";
import { useT } from "@/lib/i18n";
import { PER_PAGE_OPTIONS, perPageCookie } from "@/lib/per-page-shared";

/** Icons a row button may name instead of passing a node (`icon: "edit"`). */
const ICONS = {
  view: <Eye />,
  open: <Eye />,
  edit: <Pencil />,
  delete: <Trash2 />,
  toggle: <Power />,
  complete: <CircleCheck />,
  reschedule: <CalendarClock />,
  skip: <SkipForward />,
  reading: <Gauge />,
};

/** The searchable, sortable value behind a cell: the field itself, or a related record's name/code. */
function rawValue(row, column) {
  if (column.sortValue) return column.sortValue(row);
  const value = row?.[column.key];
  if (value && typeof value === "object") return value.name ?? value.asset_code ?? value.part_number ?? value.label ?? value.code ?? "";
  return value ?? "";
}

/** Every plain value of a row and the records it carries, for the quick search. */
function haystack(row, depth = 0) {
  if (row === null || row === undefined) return "";
  if (typeof row !== "object") return String(row);
  if (depth > 1) return "";
  return Object.entries(row)
    .filter(([key]) => key !== "id" && !key.endsWith("_id"))
    .map(([, value]) => haystack(value, depth + 1))
    .join(" ");
}

function compare(a, b) {
  const x = Number(a);
  const y = Number(b);
  if (a !== "" && b !== "" && !Number.isNaN(x) && !Number.isNaN(y)) return x - y;
  return String(a).localeCompare(String(b), undefined, { numeric: true, sensitivity: "base" });
}

/**
 * The ERP's record grid (the same face as `RecordsGrid` on every Brickland
 * screen): a card with a quick search over the rows shown and Export, the
 * brand-blue heading where every column sorts, a row's buttons spelled out in
 * an Action column, and the numbered pager with Refresh.
 *
 * Machinery's lists page on the server, so a column the API can sort
 * (`sortable` + `onSortChange`) asks the server; any other column sorts the
 * rows on screen. The quick search and Export likewise work on those rows.
 *
 * @param {{
 *   columns: { key: string, header: string, render?: (row: any) => React.ReactNode, sortable?: boolean, sortValue?: (row: any) => any, align?: "left" | "right" }[],
 *   rows: any[],
 *   rowKey: (row: any) => string,
 *   loading?: boolean,
 *   error?: string,
 *   onRetry?: () => void,
 *   onRefresh?: () => void,
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
 *   rowActions?: (row: any) => { label: string, onSelect: () => void, destructive?: boolean, icon?: string | React.ReactNode, disabled?: boolean }[],
 *   exportName?: string,
 *   perPageSelect?: boolean,
 *   toolbar?: boolean,
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
  onRefresh,
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
  exportName = "records",
  perPageSelect = false,
  toolbar = true,
  className,
}) {
  const t = useT("erp");
  const router = useRouter();
  const pathname = usePathname();
  const tableRef = useRef(null);
  const [query, setQuery] = useState("");
  const [localSort, setLocalSort] = useState(null);
  const selectable = Boolean(selectedKeys && onSelectedKeysChange);

  const shown = useMemo(() => {
    const needle = query.trim().toLowerCase();
    let list = needle ? rows.filter((row) => haystack(row).toLowerCase().includes(needle)) : rows;
    if (localSort) {
      const column = columns.find((c) => c.key === localSort.key);
      if (column) list = [...list].sort((a, b) => compare(rawValue(a, column), rawValue(b, column)) * (localSort.direction === "asc" ? 1 : -1));
    }
    return list;
  }, [rows, query, localSort, columns]);

  const allKeys = shown.map(rowKey);
  const allSelected = selectable && allKeys.length > 0 && allKeys.every((key) => selectedKeys.includes(key));
  const hasActions = Boolean(rowActions);
  const totalPages = pagination ? Math.max(1, Math.ceil(pagination.total / pagination.perPage)) : 1;
  const page = pagination?.page ?? 1;
  const from = pagination ? (pagination.total === 0 ? 0 : (page - 1) * pagination.perPage + 1) : shown.length ? 1 : 0;
  const to = pagination ? Math.min(page * pagination.perPage, pagination.total) : shown.length;
  const count = pagination ? pagination.total : shown.length;
  const refresh = onRefresh ?? onRetry ?? (() => router.refresh());

  /**
   * The pager's "Rows" select (opt-in: the list's server page must read the
   * choice back through lib/per-page.js). Remembered per list in a cookie,
   * then back to page 1, since page 7 of 10 rows is nowhere in 100.
   */
  function changePerPage(value) {
    document.cookie = `${perPageCookie(pathname)}=${value}; path=/; max-age=31536000; samesite=lax`;
    if (page > 1 && onPageChange) onPageChange(1);
    router.refresh();
  }

  function toggleAll() {
    onSelectedKeysChange(allSelected ? [] : allKeys);
  }

  function toggleRow(key) {
    onSelectedKeysChange(selectedKeys.includes(key) ? selectedKeys.filter((k) => k !== key) : [...selectedKeys, key]);
  }

  function sortBy(column) {
    if (column.sortable && onSortChange) {
      setLocalSort(null);
      onSortChange(column.key);
      return;
    }
    setLocalSort((s) => (s?.key !== column.key ? { key: column.key, direction: "asc" } : s.direction === "asc" ? { key: column.key, direction: "desc" } : null));
  }

  function sortIcon(column) {
    const active = column.sortable && onSortChange && !localSort ? (sort?.key === column.key ? sort.direction : null) : localSort?.key === column.key ? localSort.direction : null;
    if (active === "asc") return <ArrowUp className="size-3" />;
    if (active === "desc") return <ArrowDown className="size-3" />;
    return <ChevronsUpDown className="size-3 opacity-70" />;
  }

  /** The rows on screen as a CSV file, read off the table itself so every cell exports as people see it. */
  function exportCsv() {
    const table = tableRef.current;
    if (!table) return;
    const escape = (text) => `"${String(text ?? "").replace(/\s+/g, " ").trim().replaceAll('"', '""')}"`;
    const keep = (cells) => cells.filter((cell) => !cell.hasAttribute("data-skip-export"));
    const lines = [...table.querySelectorAll("tr")].map((tr) => keep([...tr.children]).map((cell) => escape(cell.innerText)).join(","));
    const blob = new Blob([`\uFEFF${lines.join("\n")}`], { type: "text/csv;charset=utf-8" });
    const link = document.createElement("a");
    link.href = URL.createObjectURL(blob);
    link.download = `${exportName}.csv`;
    link.click();
    URL.revokeObjectURL(link.href);
  }

  const actionButtons = (row) => (
    <span className="inline-flex flex-wrap justify-end gap-1.5">
      {rowActions(row).filter(Boolean).map((action) => (
        <Button
          key={action.label}
          size="sm"
          variant="outline"
          disabled={action.disabled}
          onClick={action.onSelect}
          className={action.destructive ? "text-danger hover:bg-danger-subtle" : undefined}
        >
          {typeof action.icon === "string" ? ICONS[action.icon] : (action.icon ?? (action.destructive ? <Trash2 /> : null))} {action.label}
        </Button>
      ))}
    </span>
  );

  return (
    <div className={cn("overflow-hidden rounded-lg border border-border bg-surface", className)}>
      {toolbar ? (
        <div className="flex flex-wrap items-center justify-between gap-3 p-4">
          <div className="relative w-full max-w-80">
            <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-foreground-muted" />
            <input
              value={search ? search.value : query}
              onChange={(event) => (search ? search.onChange(event.target.value) : setQuery(event.target.value))}
              placeholder={search?.placeholder ?? t("search_records", { count: rows.length })}
              aria-label={search?.placeholder ?? t("search_records", { count: rows.length })}
              className="h-10 w-full rounded-md border border-border-strong bg-surface pr-3 pl-9 text-sm outline-none placeholder:text-foreground-muted focus:border-brand"
            />
          </div>
          <div className="flex flex-wrap items-center gap-2">
            {selectable && selectedKeys.length > 0 && bulkActions?.length ? (
              <>
                <span className="text-xs text-foreground-muted">{selectedKeys.length} ✓</span>
                {bulkActions.map((action) => (
                  <Button key={action.label} size="sm" variant={action.destructive ? "danger" : "outline"} onClick={() => action.onSelect(selectedKeys)}>
                    {action.label}
                  </Button>
                ))}
              </>
            ) : null}
            <Button variant="outline" onClick={exportCsv} disabled={shown.length === 0}>
              <Download /> {t("export")}
            </Button>
          </div>
        </div>
      ) : null}

      {error ? (
        <div className="p-4">
          <ErrorState description={error} onRetry={onRetry} />
        </div>
      ) : !loading && shown.length === 0 ? (
        <div className="border-t border-border p-4">
          <EmptyState title={emptyTitle} description={emptyDescription} action={emptyAction} />
        </div>
      ) : (
        <>
          {/* Desktop / tablet: the table scrolls inside the card, never the page. */}
          <div className="hidden overflow-x-auto md:block">
            <table ref={tableRef} className="w-full text-sm">
              <thead>
                <tr className="bg-brand text-left text-brand-foreground">
                  {selectable ? (
                    <th className="w-10 px-4 py-3" data-skip-export>
                      <Checkbox checked={allSelected} onCheckedChange={toggleAll} aria-label="Select all rows" />
                    </th>
                  ) : null}
                  {columns.map((column) => (
                    <th key={column.key} className={cn("px-4 py-3 text-[13px] font-semibold whitespace-nowrap", column.align === "right" && "text-right")}>
                      {column.header ? (
                        <button type="button" onClick={() => sortBy(column)} className="inline-flex items-center gap-1">
                          {column.header}
                          {sortIcon(column)}
                        </button>
                      ) : column.key === "actions" ? (
                        t("action")
                      ) : null}
                    </th>
                  ))}
                  {hasActions ? (
                    <th className="px-4 py-3 text-right text-[13px] font-semibold whitespace-nowrap" data-skip-export>
                      {t("action")}
                    </th>
                  ) : null}
                </tr>
              </thead>
              <tbody>
                {loading
                  ? Array.from({ length: 5 }).map((_, index) => (
                      <tr key={index} className="border-t border-border">
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
                        {hasActions ? <td className="px-4 py-3" /> : null}
                      </tr>
                    ))
                  : shown.map((row) => {
                      const key = rowKey(row);
                      return (
                        <tr key={key} className="border-t border-border hover:bg-surface-muted/60">
                          {selectable ? (
                            <td className="px-4 py-2.5" data-skip-export>
                              <Checkbox checked={selectedKeys.includes(key)} onCheckedChange={() => toggleRow(key)} aria-label="Select row" />
                            </td>
                          ) : null}
                          {columns.map((column) => (
                            <td key={column.key} className={cn("px-4 py-2.5 text-foreground", column.align === "right" && "text-right tabular-nums")} data-skip-export={column.key === "actions" ? "" : undefined}>
                              {column.render ? column.render(row) : row[column.key]}
                            </td>
                          ))}
                          {hasActions ? (
                            <td className="px-4 py-2.5 text-right whitespace-nowrap" data-skip-export>
                              {actionButtons(row)}
                            </td>
                          ) : null}
                        </tr>
                      );
                    })}
              </tbody>
            </table>
          </div>

          {/* Mobile: stacked cards, never a horizontally scrolling page body. */}
          <div className="flex flex-col gap-3 border-t border-border p-4 md:hidden">
            {loading
              ? Array.from({ length: 3 }).map((_, index) => (
                  <div key={index} className="rounded-md border border-border p-4">
                    <Skeleton className="mb-2 h-4 w-1/2" />
                    <Skeleton className="h-4 w-1/3" />
                  </div>
                ))
              : shown.map((row) => {
                  const key = rowKey(row);
                  return (
                    <div key={key} className="rounded-md border border-border bg-surface p-4">
                      <div className="flex items-start gap-2">
                        {selectable ? <Checkbox checked={selectedKeys.includes(key)} onCheckedChange={() => toggleRow(key)} aria-label="Select row" className="mt-0.5" /> : null}
                        <dl className="flex flex-col gap-1.5">
                          {columns.map((column) => (
                            <div key={column.key} className="flex items-baseline gap-2 text-sm">
                              <dt className="text-xs font-medium text-foreground-muted">{column.header}</dt>
                              <dd className="text-foreground">{column.render ? column.render(row) : row[column.key]}</dd>
                            </div>
                          ))}
                        </dl>
                      </div>
                      {hasActions ? <div className="mt-3 flex justify-end">{actionButtons(row)}</div> : null}
                    </div>
                  );
                })}
          </div>
        </>
      )}

      {!error ? (
        <div className="flex flex-wrap items-center justify-between gap-3 border-t border-border px-4 py-3">
          <div className="flex items-center gap-1.5">
            {pagination ? (
              <>
                <PagerButton label={t("first_page")} disabled={page <= 1} onClick={() => onPageChange(1)} icon={<ChevronsLeft />} />
                <PagerButton label={t("previous_page")} disabled={page <= 1} onClick={() => onPageChange(page - 1)} icon={<ChevronLeft />} />
                <span className="flex size-9 items-center justify-center rounded-md bg-brand text-sm font-semibold text-brand-foreground tabular-nums">{page}</span>
                <PagerButton label={t("next_page")} disabled={page >= totalPages} onClick={() => onPageChange(page + 1)} icon={<ChevronRight />} />
                <PagerButton label={t("last_page")} disabled={page >= totalPages} onClick={() => onPageChange(totalPages)} icon={<ChevronsRight />} />
              </>
            ) : null}
          </div>
          <div className="flex items-center gap-3">
            {pagination && perPageSelect ? (
              <label className="flex items-center gap-1.5 text-sm text-foreground-muted">
                {t("rows_per_page")}
                <select
                  value={pagination.perPage}
                  onChange={(e) => changePerPage(e.target.value)}
                  className="h-9 rounded-md border border-border bg-surface px-2 text-sm text-foreground tabular-nums"
                >
                  {[...new Set([...PER_PAGE_OPTIONS, Number(pagination.perPage)])].sort((a, b) => a - b).map((n) => (
                    <option key={n} value={n}>
                      {n}
                    </option>
                  ))}
                </select>
              </label>
            ) : null}
            <span className="text-sm text-foreground-muted tabular-nums">{t("range", { start: from, end: to, count })}</span>
            <PagerButton label={t("refresh")} onClick={refresh} icon={<RefreshCw />} />
          </div>
        </div>
      ) : null}
    </div>
  );
}

function PagerButton({ label, onClick, disabled, icon }) {
  return (
    <button
      type="button"
      onClick={onClick}
      disabled={disabled}
      aria-label={label}
      title={label}
      className="flex size-9 items-center justify-center rounded-md border border-border bg-surface text-foreground-muted hover:bg-surface-muted disabled:opacity-40 [&_svg]:size-4"
    >
      {icon}
    </button>
  );
}

export { DataTable };
