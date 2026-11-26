"use client";

import { useMemo, useState, useTransition } from "react";
import { usePathname, useRouter, useSearchParams } from "next/navigation";
import { ArrowDown, ArrowUp, ChevronLeft, ChevronRight, ChevronsLeft, ChevronsRight, ChevronsUpDown, Database, Download, Eye, Layers, Pencil, RefreshCw, Search, Trash2, Zap } from "lucide-react";
import { cn } from "@/lib/utils";
import Link from "next/link";
import { Button, buttonVariants } from "@/components/ui/button";
import { Modal } from "@/components/ui/modal";
import { ConfirmDialog } from "@/components/ui/confirm-dialog";
import { FormattedDateTime } from "@/components/ui/formatted-date-time";
import { useToastManager } from "@/components/ui/toast";
import { Switch } from "@/components/ui/switch";
import { StatusPill } from "@/components/erp/status-pill";
import { EntryForm } from "@/components/erp/entry-form";
import { useScreenWords } from "@/components/erp/screen-words";
import { refsFor } from "@/lib/row-ref";
import { useT } from "@/lib/i18n";
import { ampm } from "@/lib/clock";

const PER_PAGE = [10, 25, 50, 100];

/** Plain text of a cell, for search, sort and the CSV. */
function textOf(column, row, t) {
  // A client screen's own cell (a workflow button) says what it reads as.
  if (column.read) return String(column.read(row) ?? "");
  const value = row[column.key];
  if (value === null || value === undefined || value === "") return "";
  if (column.type === "status" || column.type === "toggle") return isActive(value) ? (column.labels?.true ?? t("active")) : (column.labels?.[value] ?? column.labels?.false ?? t("inactive"));
  if (column.type === "yesno") return value ? t("yes") : t("no");
  if (column.type === "badge") return column.labels?.[value] ?? String(value);
  if (column.type === "list") return Array.isArray(value) ? value.join(", ") : String(value);
  if (column.type === "download" || column.type === "link") return "";
  return String(value);
}

const TONES = {
  success: "bg-success-subtle text-success",
  warning: "bg-warning-subtle text-warning",
  danger: "bg-danger-subtle text-danger",
  info: "bg-info-subtle text-info",
  muted: "bg-surface-muted text-foreground-muted",
};

function isActive(value) {
  return value === true || value === "ACTIVE" || value === 1;
}

/**
 * Brickland's Records tab (AnnotechERP UI parity): a card with "Search N
 * records…" and Export, an indigo-headed table whose columns sort on click,
 * a Status pill, Edit and Delete in the ACTION column, and underneath the
 * « ‹ page › » pager, items per page, "1 - N of N items" and Refresh.
 *
 * Columns are data (`type` picks the cell: text, mono, number, status, yesno,
 * badge, list, date, datetime, flags, download and link — the row's value is the url — and toggle, a switch that
 * saves through `column.action`), because a server page cannot pass a
 * render function. Edit opens the screen's own entry form in a dialog;
 * `edit.action` and `remove` are server actions taking the row id first.
 * `deleteLabels: {title, confirm, aria, nameKey, text}` words the delete as Brickland
 * does — "Remove this colour?", a Remove button, and the row's button named
 * "Remove colour Solid" (`aria` followed by the row's `nameKey`); `text`'s
 * ":name" becomes that value ("Solid will be removed …").
 * Under a `ScreenWordsProvider` (a screen with Brickland's words), Edit opens
 * in place of the grid as Brickland's "Edit X" view (`?edit=<id>`, the tab
 * strip showing it) and the delete is worded "Remove this X?".
 * Edit links and in-place edit carry the row's readable reference (`lib/row-ref`:
 * its first column, or `edit.refKey`) — never its internal id.
 * A client screen may give a column `render(row)` (its own cell — a workflow
 * button) and `read(row)` (what that cell reads as for search, sort and CSV).
 * `edit.href` makes Edit open Brickland's "Edit X" tab instead of a dialog:
 * the row id is appended to it (`/hr-payroll/shift-master?tab=edit&id=`).
 * A row whose `lockedField` is true is read-only (a shared system row).
 * `edit.rowFields` names a row key holding that row's own fields, for a grid
 * whose rows edit differently (a parameter's value is a number, a choice …).
 *
 * @param {{
 *   columns: { key: string, label: string, type?: string, align?: "left"|"right"|"center", labels?: object, sortable?: boolean, format?: (value: number) => string, group?: string, groupLabel?: string }[],
 *   rows: object[], rowKey?: string, lockedField?: string, exportName?: string,
 *   edit?: { fields?: object[], rowFields?: string, action: Function, title: string, submitLabel: string, columns?: 2|3, href?: string },
 *   remove?: Function, deleteDescription?: string, rowAction?: { label: string, action: Function, labels?: object, labelField?: string },
 *   rowOpen?: { label: string, onOpen: (row: object) => void }, searchable?: boolean,
 *   rowActions?: { key: string, label: string, icon?: React.ReactNode, onClick: (row: object) => void, disabled?: (row: object) => boolean }[],
 *   header?: { title: string, subtitle?: string, action?: React.ReactNode, searchLabel: string, searchPlaceholder: string, exportLabel: string, filters?: React.ReactNode },
 *   emptyText?: string, emptyState?: { title: string, text: string }, plain?: boolean,
 *   rowLink?: { label: string, href: string, refKey?: string },
 * }} props
 *
 * `header` makes the grid Brickland's one card (Gate Pass, General Store,
 * Material Procurement): the title, Excel and the add button on top, then the
 * filters — labelled Search, or `searchInline` beside the pills. `plain` is
 * that card's table: upper-case headings that do not sort, a blank action
 * heading, every row and no pager.
 */
function RecordsGrid({ columns, rows, rowKey = "id", lockedField, exportName = "records", edit, remove, deleteDescription, deleteLabels, rowAction, rowOpen, rowActions, searchable = true, header, emptyText, emptyState, plain = false, rowLink }) {
  const t = useT("erp");
  const router = useRouter();
  const toastManager = useToastManager();
  const [query, setQuery] = useState("");
  const [picked, setPicked] = useState("");
  // `header.check`: a tick that keeps only the rows whose key is true ("Published only").
  const [checked, setChecked] = useState(false);
  const [sort, setSort] = useState(null);
  const [page, setPage] = useState(1);
  const [perPage, setPerPage] = useState(10);
  const [editing, setEditing] = useState(null);
  const [deleting, setDeleting] = useState(null);
  const [pending, startTransition] = useTransition();
  const hasActions = Boolean(edit || remove || rowAction || rowOpen || rowLink || rowActions?.length);
  const words = useScreenWords();
  const pathname = usePathname();
  const params = useSearchParams();
  const nameKey = columns[0]?.key;
  deleteLabels = deleteLabels ?? (words ? { ...words.remove, nameKey } : undefined);
  const inPlace = Boolean(words && edit && !edit.href);
  // Links carry a row's readable reference (its first column, or `refKey`), never its id.
  const refKey = edit?.refKey ?? rowLink?.refKey ?? nameKey;
  const refs = useMemo(() => refsFor(rows, refKey, rowKey), [rows, refKey, rowKey]);
  const refOf = (row) => encodeURIComponent(refs.get(row[rowKey]) ?? "");
  const editingRef = inPlace ? params.get("edit") : null;
  const inPlaceRow = editingRef ? rows.find((r) => refs.get(r[rowKey]) === editingRef) ?? null : null;
  const withEdit = (id) => {
    const next = new URLSearchParams(params.toString());
    if (id) next.set("edit", id);
    else next.delete("edit");
    return `${pathname}${next.size ? `?${next}` : ""}`;
  };

  const filtered = useMemo(() => {
    const needle = query.trim().toLowerCase();
    let list = needle ? rows.filter((row) => columns.some((column) => textOf(column, row, t).toLowerCase().includes(needle) || (column.sub && String(row[column.sub] ?? "").toLowerCase().includes(needle)))) : rows;
    // `header.pick`: a select that narrows the rows by one key ("All buyers", "All steps").
    if (header?.pick && picked) list = list.filter((row) => String(row[header.pick.key] ?? "") === picked);
    if (header?.check && checked) list = list.filter((row) => Boolean(row[header.check.key]));
    if (sort) {
      const column = columns.find((c) => c.key === sort.key);
      const numeric = column?.type === "number" || column?.type === "progress";
      list = [...list].sort((a, b) => {
        const x = numeric ? Number(a[sort.key] ?? 0) : textOf(column, a, t).toLowerCase();
        const y = numeric ? Number(b[sort.key] ?? 0) : textOf(column, b, t).toLowerCase();
        return (x < y ? -1 : x > y ? 1 : 0) * (sort.direction === "asc" ? 1 : -1);
      });
    }
    return list;
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [rows, columns, query, sort, picked, checked]);

  const pageSize = plain ? Math.max(filtered.length, 1) : perPage;
  const pages = Math.max(1, Math.ceil(filtered.length / pageSize));
  const current = Math.min(page, pages);
  const shown = filtered.slice((current - 1) * pageSize, current * pageSize);
  const from = filtered.length === 0 ? 0 : (current - 1) * pageSize + 1;
  const to = Math.min(current * pageSize, filtered.length);

  function toggleSort(key) {
    setSort((s) => (s?.key !== key ? { key, direction: "asc" } : s.direction === "asc" ? { key, direction: "desc" } : null));
  }

  const grouped = columns.some((c) => c.group);
  /** A column's heading — its sort button, unless the grid is plain; `rowSpan` 2 under a grouped header. */
  const headCell = (column, index, rowSpan) => (
    <th key={`${index}-${column.key}`} rowSpan={rowSpan} className={cn("px-4 py-3 text-[13px] font-semibold whitespace-nowrap", plain && "text-xs tracking-wide uppercase", alignClass(column))}>
      {column.sortable === false || plain ? (
        column.label
      ) : (
        <button type="button" onClick={() => toggleSort(column.key)} className="inline-flex items-center gap-1">
          {column.label}
          {sort?.key === column.key ? sort.direction === "asc" ? <ArrowUp className="size-3" /> : <ArrowDown className="size-3" /> : <ChevronsUpDown className="size-3 opacity-70" />}
        </button>
      )}
    </th>
  );

  function exportCsv() {
    const escape = (text) => `"${String(text ?? "").replaceAll('"', '""')}"`;
    const lines = [columns.map((c) => escape(c.label)).join(",")];
    for (const row of filtered) lines.push(columns.map((c) => escape(textOf(c, row, t))).join(","));
    const blob = new Blob([`﻿${lines.join("\n")}`], { type: "text/csv;charset=utf-8" });
    const link = document.createElement("a");
    link.href = URL.createObjectURL(blob);
    link.download = `${exportName}.csv`;
    link.click();
    URL.revokeObjectURL(link.href);
  }

  /** A row's own action ("Test connection"): its answer's message becomes the toast. */
  function runRowAction(row) {
    startTransition(async () => {
      const result = await rowAction.action(row[rowKey]);
      toastManager.add({ title: result?.message ?? t("saved"), type: result?.status === "success" ? "success" : "danger" });
      router.refresh();
    });
  }

  function runDelete() {
    startTransition(async () => {
      const result = await remove(deleting[rowKey]);
      if (result?.status === "success") {
        toastManager.add({ title: t("deleted"), type: "success" });
        setDeleting(null);
        router.refresh();
      } else if (result?.status === "error") {
        toastManager.add({ title: result.message, type: "danger" });
      }
    });
  }

  if (inPlaceRow) {
    // Brickland's "Edit X": the form stands where the grid was.
    return (
      <EntryForm
        key={inPlaceRow[rowKey]}
        fields={edit.rowFields ? inPlaceRow[edit.rowFields] : edit.fields}
        action={edit.action.bind(null, inPlaceRow[rowKey])}
        initial={inPlaceRow}
        title={words.edit}
        description={words.rewriting.replace(":name", String(inPlaceRow[nameKey] ?? ""))}
        submitLabel={words.update}
        columns={edit.columns ?? 2}
        onCancel={() => router.push(withEdit(null), { scroll: false })}
        onDone={() => router.push(withEdit(null), { scroll: false })}
        cancelLabel={words.cancel}
      />
    );
  }

  // A list filtered down to nothing keeps its table and says "no match"; only a truly empty one gets the card.
  // `report` picks which report a report screen shows (Lab Test Reports); it filters nothing.
  const filtering = [...params.keys()].some((key) => !["tab", "edit", "id", "report"].includes(key));
  const empty = !header && !filtering && rows.length === 0 ? (emptyState ?? words?.empty) : null;
  if (empty) {
    // Brickland shows an empty list as a card — an icon, "No X records yet" and where to add one — not an empty table.
    return (
      <div className="flex flex-col items-center gap-2 rounded-lg border border-border bg-surface px-4 py-14 text-center">
        <span className="flex size-12 items-center justify-center rounded-lg bg-surface-muted text-foreground-muted">
          <Database className="size-5" />
        </span>
        <p className="text-sm font-semibold text-foreground">{empty.title}</p>
        <p className="max-w-md text-[13px] text-foreground-muted">{empty.text}</p>
      </div>
    );
  }

  const search = (value) => {
    setQuery(value);
    setPage(1);
  };

  return (
    <div className="rounded-lg border border-border bg-surface">
      {header ? (
        // Brickland's one-card list (General Store): the title, Excel and New on top, then labelled filters.
        <div className="flex flex-col gap-3 px-5 pt-4 pb-4">
          <div className="flex flex-wrap items-start justify-between gap-3">
            <div>
              <h2 className="text-base font-semibold text-foreground">{header.title}</h2>
              {header.subtitle ? <p className="mt-0.5 text-[13px] text-foreground-muted">{header.subtitle}</p> : null}
            </div>
            <div className="flex items-center gap-2">
              {header.exportLabel ? (
                <Button variant="outline" onClick={exportCsv} disabled={filtered.length === 0}>
                  <Download /> {header.exportLabel}
                </Button>
              ) : null}
              {header.action}
            </div>
          </div>
          {header.searchLeft ? (
            // Brickland's demo-data screens (Buyer Gateway): the search with its icon on the left, `pick` on the right.
            <div className="flex flex-wrap items-center justify-between gap-3">
              <div className="relative w-full max-w-80">
                <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-foreground-muted" />
                <input
                  value={query}
                  onChange={(event) => search(event.target.value)}
                  placeholder={header.searchPlaceholder}
                  aria-label={header.searchPlaceholder}
                  className="h-10 w-full rounded-md border border-border-strong bg-surface pr-3 pl-9 text-sm outline-none placeholder:text-foreground-muted focus:border-brand"
                />
              </div>
              {header.pick ? (
                <select
                  value={picked}
                  onChange={(event) => {
                    setPicked(event.target.value);
                    setPage(1);
                  }}
                  aria-label={header.pick.label}
                  className="h-10 rounded-md border border-border-strong bg-surface px-3 text-sm outline-none focus:border-brand"
                >
                  <option value="">{header.pick.all}</option>
                  {header.pick.options.map((o) => (
                    <option key={o.value} value={o.value}>
                      {o.label}
                    </option>
                  ))}
                </select>
              ) : null}
              {header.check ? (
                <label className="flex cursor-pointer items-center gap-2 text-sm text-foreground">
                  <input
                    type="checkbox"
                    checked={checked}
                    onChange={(event) => {
                      setChecked(event.target.checked);
                      setPage(1);
                    }}
                    className="size-4 accent-brand"
                  />
                  {header.check.label}
                </label>
              ) : null}
            </div>
          ) : header.searchInline ? (
            <div className="flex flex-wrap items-center justify-between gap-3">
              {header.filters}
              <div className="relative w-full max-w-72">
                <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-foreground-muted" />
                <input
                  value={query}
                  onChange={(event) => search(event.target.value)}
                  placeholder={header.searchPlaceholder}
                  aria-label={header.searchPlaceholder}
                  className="h-10 w-full rounded-md border border-border-strong bg-surface pr-3 pl-9 text-sm outline-none placeholder:text-foreground-muted focus:border-brand"
                />
              </div>
            </div>
          ) : header.searchLabel || header.filters || header.pick ? (
            <div className={cn("flex flex-wrap items-end gap-3", header.pick && "justify-between")}>
              {header.searchLabel ? (
                <label className="flex w-full max-w-66 flex-col gap-1.5">
                  <span className="text-[13px] font-semibold text-foreground">{header.searchLabel}</span>
                  <input
                    value={query}
                    onChange={(event) => search(event.target.value)}
                    placeholder={header.searchPlaceholder}
                    className="h-10 w-full rounded-md border border-border-strong bg-surface px-3 text-sm outline-none placeholder:text-foreground-muted focus:border-brand"
                  />
                </label>
              ) : null}
              {header.filters}
              {header.pick ? (
                <select
                  value={picked}
                  onChange={(event) => {
                    setPicked(event.target.value);
                    setPage(1);
                  }}
                  aria-label={header.pick.label}
                  className="h-10 rounded-md border border-border-strong bg-surface px-3 text-sm outline-none focus:border-brand"
                >
                  <option value="">{header.pick.all}</option>
                  {header.pick.options.map((o) => (
                    <option key={o.value} value={o.value}>
                      {o.label}
                    </option>
                  ))}
                </select>
              ) : null}
            </div>
          ) : null}
        </div>
      ) : null}
      <div className={cn("flex flex-wrap items-center gap-3 p-4", searchable ? "justify-between" : "justify-end", header && "hidden")}>
        {/* A screen with its own search above the card (the Approval Center's lists) leaves this one out. */}
        <div className={cn("relative w-full max-w-80", !searchable && "hidden")}>
          <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-foreground-muted" />
          <input
            value={query}
            onChange={(event) => search(event.target.value)}
            placeholder={t("search_records", { count: rows.length })}
            className="h-10 w-full rounded-md border border-border-strong bg-surface pr-3 pl-9 text-sm outline-none placeholder:text-foreground-muted focus:border-brand"
          />
        </div>
        <Button variant="outline" onClick={exportCsv} disabled={filtered.length === 0}>
          <Download /> {t("export")}
        </Button>
      </div>

      <div className="overflow-x-auto">
        <table className="w-full min-w-max text-sm">
          <thead>
            {/* Columns sharing a `group` sit under one heading, Brickland's two-row header ("DAY (8:30-20:30)" over Qty and Roll). */}
            {grouped ? (
              <tr className="bg-brand text-left text-brand-foreground">
                {groupSpans(columns).map(({ column, index, span }) =>
                  column.group ? (
                    <th key={`g-${index}`} colSpan={span} className="border-b border-l border-white/20 px-4 py-2 text-center text-xs font-semibold tracking-wide whitespace-nowrap uppercase first:border-l-0">
                      {column.groupLabel ?? column.group}
                    </th>
                  ) : (
                    headCell(column, index, 2)
                  ),
                )}
                {hasActions ? <th rowSpan={2} className="px-4 py-3 text-right text-[13px] font-semibold tracking-wide uppercase">{t("action")}</th> : null}
              </tr>
            ) : null}
            <tr className="bg-brand text-left text-brand-foreground">
              {/* Two columns may read one field ("Active?" and "Status"), so the key carries the position too. */}
              {columns.map((column, index) => (grouped && !column.group ? null : headCell(column, index)))}
              {hasActions && !grouped ? <th className="px-4 py-3 text-right text-[13px] font-semibold tracking-wide uppercase">{plain ? <span className="sr-only">{t("action")}</span> : t("action")}</th> : null}
            </tr>
          </thead>
          <tbody>
            {shown.length === 0 ? (
              <tr>
                <td colSpan={columns.length + (hasActions ? 1 : 0)} className="px-4 py-10 text-center text-foreground-muted">
                  {rows.length === 0 && !filtering ? (emptyText ?? t("no_records")) : t("no_match")}
                </td>
              </tr>
            ) : (
              shown.map((row) => {
                const locked = lockedField ? Boolean(row[lockedField]) : false;
                return (
                  <tr key={row[rowKey]} className="border-b border-border last:border-0 hover:bg-surface-muted/50">
                    {columns.map((column, index) => (
                      <td key={`${index}-${column.key}`} className={cn("px-4 py-3.5 align-middle", alignClass(column))}>
                        <Cell column={column} row={row} t={t} />
                      </td>
                    ))}
                    {hasActions ? (
                      <td className="px-4 py-2.5 text-right whitespace-nowrap">
                        {locked ? (
                          <span className="text-xs text-foreground-muted">{t("read_only")}</span>
                        ) : (
                          <span className="inline-flex gap-1.5">
                            {rowLink ? (
                              // A row's own page or tab (Style Development's "Sheet"): the row id is appended to the href.
                              <Link href={`${rowLink.href}${refOf(row)}`} className={cn(buttonVariants({ variant: "outline", size: "sm" }))}>
                                <Layers /> {rowLink.label}
                              </Link>
                            ) : null}
                            {rowOpen ? (
                              <Button variant="outline" size="sm" onClick={() => rowOpen.onOpen(row)}>
                                <Eye /> {rowOpen.label}
                              </Button>
                            ) : null}
                            {/* A client screen's own row buttons (Open · Decide · Submit · Assign · Cancel). */}
                            {(rowActions ?? []).map((a) => (
                              <Button key={a.key} variant="outline" size="sm" onClick={() => a.onClick(row)} disabled={a.disabled?.(row) ?? false}>
                                {a.icon} {a.label}
                              </Button>
                            ))}
                            {rowAction ? (
                              <Button variant="outline" size="sm" onClick={() => runRowAction(row)} disabled={pending}>
                                <Zap /> {rowAction.labels ? (rowAction.labels[String(row[rowAction.labelField])] ?? rowAction.label) : rowAction.label}
                              </Button>
                            ) : null}
                            {edit ? (
                              <Button variant="outline" size="sm" onClick={() => (edit.href ? router.push(`${edit.href}${refOf(row)}`) : inPlace ? router.push(withEdit(refs.get(row[rowKey])), { scroll: false }) : setEditing(row))}>
                                <Pencil /> {t("edit")}
                              </Button>
                            ) : null}
                            {remove ? (
                              <Button variant="outline" size="sm" onClick={() => setDeleting(row)} aria-label={deleteLabels ? `${deleteLabels.aria} ${row[deleteLabels.nameKey] ?? ""}`.trim() : t("delete")}>
                                <Trash2 />
                              </Button>
                            ) : null}
                          </span>
                        )}
                      </td>
                    ) : null}
                  </tr>
                );
              })
            )}
          </tbody>
        </table>
      </div>

      <div className={cn("flex flex-wrap items-center justify-between gap-3 border-t border-border px-4 py-3", plain && "hidden")}>
        <div className="flex items-center gap-1.5">
          <PagerButton label={t("first_page")} disabled={current === 1} onClick={() => setPage(1)} icon={<ChevronsLeft />} />
          <PagerButton label={t("previous_page")} disabled={current === 1} onClick={() => setPage(current - 1)} icon={<ChevronLeft />} />
          <span className="flex size-9 items-center justify-center rounded-md bg-brand text-sm font-semibold text-brand-foreground tabular-nums">{current}</span>
          <PagerButton label={t("next_page")} disabled={current >= pages} onClick={() => setPage(current + 1)} icon={<ChevronRight />} />
          <PagerButton label={t("last_page")} disabled={current >= pages} onClick={() => setPage(pages)} icon={<ChevronsRight />} />
          <select
            value={perPage}
            onChange={(event) => {
              setPerPage(Number(event.target.value));
              setPage(1);
            }}
            className="ml-2 h-9 rounded-md border border-border-strong bg-surface px-2 text-sm"
            aria-label={t("items_per_page")}
          >
            {PER_PAGE.map((n) => (
              <option key={n} value={n}>
                {n}
              </option>
            ))}
          </select>
          <span className="text-sm text-foreground-muted">{t("items_per_page")}</span>
        </div>
        <div className="flex items-center gap-3">
          <span className="text-sm text-foreground-muted tabular-nums">{t("range", { start: from, end: to, count: filtered.length })}</span>
          <PagerButton label={t("refresh")} onClick={() => router.refresh()} icon={<RefreshCw />} />
        </div>
      </div>

      {edit && !edit.href ? (
        <Modal open={editing !== null} onOpenChange={(open) => !open && setEditing(null)} title={edit.title} description={edit.description} className="max-h-[92vh] max-w-3xl overflow-y-auto">
          {editing ? (
            <EntryForm
              key={editing[rowKey]}
              fields={edit.rowFields ? editing[edit.rowFields] : edit.fields}
              action={edit.action.bind(null, editing[rowKey])}
              initial={editing}
              submitLabel={edit.submitLabel}
              columns={edit.columns ?? 2}
              onDone={() => setEditing(null)}
              onCancel={() => setEditing(null)}
              dialog
            />
          ) : null}
        </Modal>
      ) : null}

      {remove ? (
        <ConfirmDialog
          open={deleting !== null}
          onOpenChange={() => setDeleting(null)}
          title={deleteLabels?.title ?? t("delete_title")}
          description={deleteLabels?.text && deleting ? deleteLabels.text.replace(":name", String(deleting[deleteLabels.nameKey] ?? "")) : (deleteDescription ?? t("delete_description"))}
          confirmLabel={deleteLabels?.confirm ?? t("delete")}
          cancelLabel={t("cancel")}
          loading={pending}
          onConfirm={runDelete}
        />
      ) : null}
    </div>
  );
}

/** Runs of neighbouring columns under the same group: each run's first column and its width. */
function groupSpans(columns) {
  const out = [];
  columns.forEach((column, index) => {
    const last = out[out.length - 1];
    if (column.group && last?.column.group === column.group) last.span += 1;
    else out.push({ column, index, span: 1 });
  });
  return out;
}

function alignClass(column) {
  if (column.align === "right" || column.type === "number") return "text-right";
  if (column.align === "center" || column.type === "yesno") return "text-center";
  return "text-left";
}

/**
 * Brickland's Status switch: flipping it saves at once. `column.action` is a
 * server action taking the row id and the new state; without one the switch
 * only shows the state.
 */
function ToggleCell({ column, row, t }) {
  const router = useRouter();
  const toastManager = useToastManager();
  const [pending, startTransition] = useTransition();
  const on = isActive(row[column.key]);

  function flip(next) {
    startTransition(async () => {
      const result = await column.action(row.id, next);
      if (result?.status === "success") router.refresh();
      else toastManager.add({ title: result?.message, type: "danger" });
    });
  }

  return (
    <span className="inline-flex items-center gap-2">
      <Switch checked={on} onCheckedChange={flip} disabled={!column.action || pending} aria-label={column.label} />
      {column.bare ? null : <StatusPill active={on} label={on ? t("active") : t("inactive")} />}
    </span>
  );
}

function Cell({ column, row, t }) {
  if (column.render) return column.render(row);
  const value = row[column.key];
  if (column.type === "status") return <StatusPill active={isActive(value)} label={textOf(column, row, t) || t("inactive")} />;
  if (column.type === "toggle") return <ToggleCell column={column} row={row} t={t} />;
  if (value === null || value === undefined || value === "" || (Array.isArray(value) && value.length === 0)) return <span className="text-foreground-muted">—</span>;
  switch (column.type) {
    case "progress":
      return (
        <span className="inline-flex items-center gap-2.5">
          <span className="h-1.5 w-20 overflow-hidden rounded-full bg-surface-muted">
            <span className="block h-full rounded-full bg-brand" style={{ width: `${Math.max(0, Math.min(100, Number(value)))}%` }} />
          </span>
          <span className="w-10 text-right text-[13px] tabular-nums">{Number(value)}%</span>
        </span>
      );
    case "mono":
      return <span className="font-mono text-[13px]">{ampm(value)}</span>;
    case "number":
      // `grouping` writes 184,000; `decimals` fixes the places; `suffix` adds a unit (" MB") — all usable from a server page, unlike `format`.
      return (
        <span className="tabular-nums">
          {column.format
            ? column.format(value)
            : column.currency
              ? // `currency` writes Brickland's short amounts: $1.84M, $964K, $0.
                new Intl.NumberFormat("en-US", { style: "currency", currency: column.currency, notation: "compact", maximumFractionDigits: 2 }).format(Number(value))
              : column.grouping
                ? Number(value).toLocaleString("en-US")
                : // `decimals` keeps a fixed number of places: Brickland's 97.0%.
                  column.decimals !== undefined && value !== null && value !== undefined && value !== ""
                  ? Number(value).toFixed(column.decimals)
                  : value}
          {column.suffix ?? ""}
        </span>
      );
    case "yesno":
      return value ? t("yes") : t("no");
    case "badge":
      // `tones` colours a state: { ACTIVE: "success", SUSPENDED: "warning", LOCKED: "danger", INVITED: "info" }.
      // `toneKey`: the colour comes from another field (a board's health band behind its "94%").
      if (column.tones) return <span className={cn("inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold", TONES[column.tones[column.toneKey ? row[column.toneKey] : value]] ?? "bg-surface-muted text-foreground")}>{column.labels?.[value] ?? value}</span>;
      return <span className="rounded-md bg-surface-muted px-1.5 py-0.5 text-xs font-medium text-foreground">{column.labels?.[value] ?? value}</span>;
    case "list":
      return (
        <span className="flex flex-wrap gap-1">
          {(Array.isArray(value) ? value : [value]).map((item, i) => (
            <span key={`${item}-${i}`} className="rounded-md bg-surface-muted px-1.5 py-0.5 text-xs">
              {item}
            </span>
          ))}
        </span>
      );
    case "flags":
      return (
        <span className="inline-flex gap-0.5 font-mono text-xs">
          {["V", "C", "E", "D", "A", "P", "X"].map((flag) => (
            <span
              key={flag}
              className={cn("flex size-5 items-center justify-center rounded", String(value).includes(flag) ? "bg-brand-subtle font-bold text-brand" : "text-foreground-subtle")}
            >
              {flag}
            </span>
          ))}
        </span>
      );
    case "date":
      return <FormattedDateTime value={value} mode="date" />;
    case "datetime":
      return <FormattedDateTime value={value} />;
    case "link":
      return (
        <a href={value} className="text-sm font-medium text-brand hover:underline">
          {column.text ?? t("open")}
        </a>
      );
    case "download":
      return (
        <a href={value} className="inline-flex items-center gap-1 text-sm font-medium text-brand hover:underline" download>
          <Download className="size-3.5" /> {column.text ?? t("download")}
        </a>
      );
    default:
      // `sub`: a second, muted line under the value (a user's e-mail, a buyer under a role).
      return column.sub ? (
        <span className="flex flex-col">
          <span className="font-semibold whitespace-normal text-foreground">{ampm(value)}</span>
          {row[column.sub] ? <span className="text-xs text-foreground-muted">{row[column.sub]}</span> : null}
        </span>
      ) : (
        <span className="whitespace-normal">{ampm(value)}</span>
      );
  }
}

function PagerButton({ label, onClick, disabled, icon }) {
  return (
    <button
      type="button"
      onClick={onClick}
      disabled={disabled}
      aria-label={label}
      title={label}
      className="flex size-9 items-center justify-center rounded-md border border-border text-foreground-muted hover:bg-surface-muted disabled:opacity-40 [&_svg]:size-4"
    >
      {icon}
    </button>
  );
}

export { RecordsGrid };
