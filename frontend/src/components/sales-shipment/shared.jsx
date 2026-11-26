"use client";

import { useMemo, useState, useTransition } from "react";
import { ArrowLeft, ChevronLeft, ChevronRight, ClipboardList, Plus, RotateCcw, Save, Search, Trash2 } from "lucide-react";
import { cn } from "@/lib/utils";
import { Select } from "@/components/ui/select";
import { Input } from "@/components/ui/input";
import { Button } from "@/components/ui/button";
import { Checkbox } from "@/components/ui/checkbox";
import { ConfirmDialog } from "@/components/ui/confirm-dialog";
import { useToastManager } from "@/components/ui/toast";
import { RecordsGrid } from "@/components/erp/records-grid";
import { FlowSteps } from "@/components/erp/flow-steps";
import { useT } from "@/lib/i18n";
import { ampm } from "@/lib/clock";

/*
 * Sales & Shipment → Order & Delivery (AnnotechERP 21/02): the pieces every
 * screen of the sub-module is built from, as Brickland draws them — the
 * list / entry tabs, the "Searching Data" card (Search, Reset), the stat
 * tiles over a register, the register with its row actions and the confirm
 * before a step, the pick-list on the left of an entry (search, a customer
 * filter, ten rows a page), the editable grids (Add line, Remove, totals)
 * and the entry's foot (Back To List, Action Status, Clear, Save).
 */

const today = () => new Date().toLocaleDateString("en-CA", { timeZone: "Asia/Dhaka" });
const num = (v, d = 3) => Number(v || 0).toLocaleString("en-US", { minimumFractionDigits: d, maximumFractionDigits: d });
const qty = (v) => Number(v || 0).toLocaleString("en-US", { maximumFractionDigits: 3 });
const money = (v) => num(v, 2);
const has = (v, q) => !q || String(v ?? "").toLowerCase().includes(String(q).trim().toLowerCase());
const within = (d, from, to) => (!from || (d && d >= from)) && (!to || (d && d <= to));
const blank = (v) => (v === "" || v === undefined ? null : v);
const firstError = (res) => (res?.errors ? Object.values(res.errors).flat()[0] : res?.message);
const day = (iso) => (iso ? new Date(`${String(iso).slice(0, 10)}T00:00:00`).toLocaleDateString("en-GB", { day: "2-digit", month: "short", year: "numeric" }) : "—");

function useSs() {
  const t = useT("salesshipment");
  return (k, p) => t(k, p);
}

/** The tabs over a screen, switching in place. */
function DeskTabs({ tab, setTab, items }) {
  return (
    <div className="mb-4 flex flex-wrap gap-2" role="tablist">
      {items.map(([key, label, Icon]) => (
        <button
          key={key}
          type="button"
          role="tab"
          aria-selected={tab === key}
          onClick={() => setTab(key)}
          className={cn(
            "flex h-9 items-center gap-1.5 rounded-lg border px-3 text-[13px] font-semibold transition-colors",
            tab === key ? "border-brand bg-brand text-brand-foreground" : "border-border bg-surface text-foreground hover:bg-surface-muted",
          )}
        >
          <Icon className="size-4" /> {label}
        </button>
      ))}
    </div>
  );
}

/** Brickland's "Searching Data" card: the boxes, the note, Search and Reset (and a link action). */
function Filters({ w, note, fields, value, onChange, extra = null }) {
  const [draft, setDraft] = useState(value);
  const set = (k, v) => setDraft((d) => ({ ...d, [k]: v }));
  return (
    <section className="mb-4 rounded-lg border border-border bg-surface p-5">
      <h3 className="text-[15px] font-semibold">{w("od.f.title")}</h3>
      {note ? <p className="mt-0.5 mb-3 text-[13px] text-foreground-muted">{note}</p> : <div className="mb-3" />}
      <form
        className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4"
        onSubmit={(e) => {
          e.preventDefault();
          onChange(draft);
        }}
      >
        {fields.map((f) =>
          f.type === "checkbox" ? (
            <label key={f.name} className="flex items-center gap-2 self-end pb-2 text-sm font-semibold">
              <Checkbox checked={Boolean(draft[f.name])} onCheckedChange={(v) => set(f.name, v === true)} aria-label={f.label} /> {f.label}
            </label>
          ) : (
          <label key={f.name} className="flex flex-col gap-1.5">
            <span className="text-[13px] font-semibold">{f.label}</span>
            {f.type === "select" ? (
              <Select value={draft[f.name] ?? ""} onValueChange={(v) => set(f.name, v ?? "")} options={[{ value: "", label: w("od.f.all") }, ...f.options]} placeholder={w("od.f.all")} aria-label={f.label} />
            ) : (
              <Input type={f.type === "date" ? "date" : "text"} value={draft[f.name] ?? ""} placeholder={f.placeholder} onChange={(e) => set(f.name, e.target.value)} aria-label={f.label} />
            )}
          </label>
          ),
        )}
        <div className="flex flex-wrap items-end gap-2 sm:col-span-2 lg:col-span-4">
          <Button type="submit">
            <Search /> {w("od.f.search")}
          </Button>
          <Button
            type="button"
            variant="outline"
            onClick={() => {
              setDraft({});
              onChange({});
            }}
          >
            <RotateCcw /> {w("od.f.reset")}
          </Button>
          {extra}
        </div>
      </form>
    </section>
  );
}

const TONES = { good: "text-success", warning: "text-warning", accent: "text-brand", info: "text-info", critical: "text-danger", neutral: "text-foreground" };

/** The stat tiles over a register. */
function Tiles({ items }) {
  return (
    <div className="mb-4 grid grid-cols-2 gap-3 md:grid-cols-3 xl:grid-cols-6">
      {items.map(([label, value, tone]) => (
        <div key={label} className="rounded-lg border border-border bg-surface px-4 py-3">
          <p className="text-xs font-medium text-foreground-muted">{label}</p>
          <p className={cn("mt-1 text-xl font-semibold tabular-nums", TONES[tone] ?? "text-foreground")}>{value}</p>
        </div>
      ))}
    </div>
  );
}

/**
 * The register: rows, the actions a row may take (`when(row)`), and the
 * confirm (or the prompt) before a step.
 */
function Register({ w, rows, columns, refKey, actions, exportName, onDone, emptyTitle, emptyText }) {
  const toastManager = useToastManager();
  const [asking, setAsking] = useState(null);
  const [pending, startTransition] = useTransition();
  const fill = (text, row) => String(text ?? "").replaceAll(":no", row[refKey] ?? "");
  const run = (action, row, extra) =>
    startTransition(async () => {
      const res = await action.run(row[refKey], extra, row);
      setAsking(null);
      if (res?.status === "success") {
        toastManager.add({ title: fill(action.done, row), type: "success" });
        onDone?.();
      } else toastManager.add({ title: firstError(res), type: "danger" });
    });
  const click = (a, row) => {
    if (a.open) return a.open(row);
    if (a.prompt) {
      const reason = window.prompt(fill(a.prompt, row));
      if (reason !== null) run(a, row, { reason });
      return;
    }
    if (!a.confirm) return run(a, row);
    setAsking({ action: a, row });
  };
  return (
    <>
      <RecordsGrid
        exportName={exportName}
        rows={rows}
        rowKey={refKey}
        columns={columns}
        emptyState={emptyTitle ? { title: emptyTitle, text: emptyText } : undefined}
        rowActions={actions.map((a) => ({ key: a.key, label: a.label, onClick: (row) => click(a, row), disabled: (row) => (a.when ? !a.when(row) : false) }))}
      />
      <ConfirmDialog
        open={asking !== null}
        onOpenChange={(o) => !o && setAsking(null)}
        title={asking ? fill(asking.action.title ?? asking.action.label, asking.row) : ""}
        description={asking ? fill(asking.action.confirm, asking.row) : ""}
        confirmLabel={asking?.action.label}
        cancelLabel={w("od.reg.keep")}
        destructive={asking?.action.destructive ?? false}
        loading={pending}
        onConfirm={() => run(asking.action, asking.row)}
      />
    </>
  );
}

/** The pick-list on the left of an entry: search, a customer filter, ten rows a page; a row click picks it. */
function SideList({ w, title, rows, columns, refKey, searchPlaceholder, filterLabel, filterOptions, filterPlaceholder, onPick, picked }) {
  const [q, setQ] = useState("");
  const [party, setParty] = useState("");
  const [page, setPage] = useState(0);
  const shown = useMemo(() => rows.filter((r) => (!party || r.customer_id === party) && (!q || columns.some((c) => has(r[c.key], q)))), [rows, q, party, columns]);
  const pages = Math.max(1, Math.ceil(shown.length / 10));
  const slice = shown.slice(page * 10, page * 10 + 10);
  return (
    <aside className="flex flex-col gap-3 rounded-lg border border-border bg-surface p-4">
      <h3 className="text-[15px] font-semibold">{title}</h3>
      <Input type="search" value={q} onChange={(e) => (setQ(e.target.value), setPage(0))} placeholder={searchPlaceholder} aria-label={searchPlaceholder} />
      <label className="flex flex-col gap-1.5">
        <span className="text-xs font-semibold text-foreground-muted">{filterLabel}</span>
        <Select value={party} onValueChange={(v) => (setParty(v ?? ""), setPage(0))} options={[{ value: "", label: filterPlaceholder }, ...filterOptions]} placeholder={filterPlaceholder} aria-label={filterLabel} />
      </label>
      <div className="overflow-x-auto rounded-md border border-border">
        <table className="w-full text-[13px]">
          <thead>
            <tr className="bg-brand text-left text-brand-foreground">
              {columns.map((c) => (
                <th key={c.key} className={cn("px-2.5 py-2 font-semibold", c.align === "right" && "text-right")}>
                  {c.label}
                </th>
              ))}
            </tr>
          </thead>
          <tbody>
            {slice.length ? (
              slice.map((r) => (
                <tr key={r[refKey]} onClick={() => onPick(r)} className={cn("cursor-pointer border-b border-border last:border-0 hover:bg-surface-muted", picked === r[refKey] && "bg-brand-subtle")}>
                  {columns.map((c) => (
                    <td key={c.key} className={cn("px-2.5 py-1.5", c.align === "right" && "text-right tabular-nums", c.mono && "font-mono")}>
                      {c.align === "right" ? qty(r[c.key]) : (ampm(r[c.key]) ?? "—")}
                    </td>
                  ))}
                </tr>
              ))
            ) : (
              <tr>
                <td colSpan={columns.length} className="px-3 py-6 text-center text-foreground-muted">
                  {w("od.side.empty")}
                </td>
              </tr>
            )}
          </tbody>
        </table>
      </div>
      {pages > 1 ? (
        <div className="flex items-center justify-between text-xs text-foreground-muted">
          <Button size="sm" variant="outline" onClick={() => setPage((p) => Math.max(0, p - 1))} disabled={page === 0} aria-label={w("od.side.prev")}>
            <ChevronLeft />
          </Button>
          {page + 1} / {pages}
          <Button size="sm" variant="outline" onClick={() => setPage((p) => Math.min(pages - 1, p + 1))} disabled={page >= pages - 1} aria-label={w("od.side.next")}>
            <ChevronRight />
          </Button>
        </div>
      ) : null}
    </aside>
  );
}

/**
 * An editable grid: one input per column (`text`, `number`, `select`), a
 * `read(row)` column shows a figure, Remove per row, Add line, and the
 * summaries under it.
 */
function EditGrid({ w, title, hint, cols, rows, setRows, editable, addLabel, onAdd, emptyNote, summaries = [] }) {
  const set = (i, k, v) => setRows(rows.map((r, j) => (j === i ? { ...r, [k]: v } : r)));
  return (
    <section className="rounded-lg border border-border bg-surface p-5">
      <div className="flex flex-wrap items-start justify-between gap-3">
        <div>
          <h3 className="text-base font-semibold">{title}</h3>
          {hint ? <p className="mt-0.5 text-[13px] text-foreground-muted">{hint}</p> : null}
        </div>
        {editable && addLabel ? (
          <Button variant="outline" size="sm" onClick={onAdd}>
            <Plus /> {addLabel}
          </Button>
        ) : null}
      </div>
      <div className="mt-4 overflow-x-auto rounded-md border border-border">
        <table className="w-full min-w-max text-sm">
          <thead>
            <tr className="bg-brand text-left text-brand-foreground">
              {cols.map((c) => (
                <th key={c.key} className={cn("px-3 py-2.5 font-semibold whitespace-nowrap", c.align === "right" && "text-right", c.align === "center" && "text-center")}>
                  {c.label}
                </th>
              ))}
              {editable ? (
                <th className="px-3 py-2.5">
                  <span className="sr-only">{w("od.grid.remove")}</span>
                </th>
              ) : null}
            </tr>
          </thead>
          <tbody>
            {rows.length ? (
              rows.map((r, i) => (
                <tr key={r._k ?? i} className="border-b border-border last:border-0">
                  {cols.map((c) => (
                    <td key={c.key} className={cn("px-2 py-1.5", c.align === "right" && "text-right tabular-nums", c.align === "center" && "text-center")}>
                      {c.read ? (
                        <span className={cn("block px-1", c.align === "right" && "text-right")}>{c.read(r)}</span>
                      ) : !editable || (c.lockWhen && c.lockWhen(r)) ? (
                        <span className="block px-1">{c.type === "select" ? (c.options.find((o) => o.value === r[c.key])?.label ?? "—") : c.type === "number" ? qty(r[c.key]) : r[c.key] || "—"}</span>
                      ) : c.type === "select" ? (
                        <div style={{ minWidth: c.width ?? 180 }}>
                          <Select value={r[c.key] ?? ""} onValueChange={(v) => (c.onPick ? setRows(rows.map((x, j) => (j === i ? { ...x, [c.key]: v ?? "", ...c.onPick(v, x) } : x))) : set(i, c.key, v ?? ""))} options={c.options} placeholder="—" aria-label={`${c.label} ${i + 1}`} />
                        </div>
                      ) : (
                        <Input
                          type={c.type === "number" ? "number" : "text"}
                          min={c.type === "number" ? "0" : undefined}
                          step={c.type === "number" ? (c.step ?? "any") : undefined}
                          value={r[c.key] ?? ""}
                          maxLength={c.max}
                          placeholder={c.placeholder}
                          onChange={(e) => set(i, c.key, e.target.value)}
                          className={cn(c.type === "number" && "text-right")}
                          style={{ width: c.width ?? (c.type === "number" ? 110 : 150) }}
                          aria-label={`${c.label} ${i + 1}`}
                        />
                      )}
                    </td>
                  ))}
                  {editable ? (
                    <td className="px-2 py-1.5 text-right">
                      <Button size="sm" variant="ghost" onClick={() => setRows(rows.filter((_, j) => j !== i))} aria-label={`${w("od.grid.remove")} ${i + 1}`}>
                        <Trash2 className="text-danger" />
                      </Button>
                    </td>
                  ) : null}
                </tr>
              ))
            ) : (
              <tr>
                <td colSpan={cols.length + (editable ? 1 : 0)} className="px-3 py-6 text-center">
                  <p className="font-semibold">{w("od.grid.empty")}</p>
                  {emptyNote ? <p className="mt-0.5 text-[13px] text-foreground-muted">{emptyNote}</p> : null}
                </td>
              </tr>
            )}
          </tbody>
        </table>
      </div>
      {summaries.length ? (
        <div className="mt-3 flex flex-wrap gap-4 text-[13px] text-foreground-muted">
          {summaries.map(([label, value]) => (
            <span key={label}>
              {label} <span className="ml-1 rounded-md bg-surface-muted px-2 py-0.5 font-semibold text-foreground tabular-nums">{value}</span>
            </span>
          ))}
        </div>
      ) : null}
    </section>
  );
}

/** An entry's top card: title, explanation, the "Existing …" picker and the lock note. */
function EntryHead({ title, hint, children }) {
  return (
    <div className="flex flex-col gap-4 rounded-lg border border-border bg-surface p-5">
      <div>
        <h2 className="text-base font-semibold">{title}</h2>
        <p className="mt-0.5 text-[13px] text-foreground-muted">{hint}</p>
      </div>
      {children}
    </div>
  );
}

function LockNote({ text }) {
  return text ? <p className="rounded-md bg-warning-subtle px-3 py-2 text-sm text-warning">{text}</p> : null;
}

/** The entry's last card: Back To List, the Action Status, the footnote, Clear and Save. */
function Foot({ w, note, status, editable, pending, onBack, onClear, onSave, saveLabel }) {
  return (
    <div className="flex flex-wrap items-center gap-3 rounded-lg border border-border bg-surface p-5">
      <p className="basis-full text-[13px] text-foreground-muted">{note}</p>
      <Button variant="outline" onClick={onBack}>
        <ArrowLeft /> {w("od.ed.back")}
      </Button>
      {status ? (
        <span className="rounded-md border border-border px-2.5 py-1 text-xs">
          <span className="text-foreground-muted">{w("od.ed.action_status")}</span> <b>{status}</b>
        </span>
      ) : null}
      <span className="flex-1" />
      {editable ? (
        <>
          <Button variant="outline" onClick={onClear} disabled={pending}>
            <RotateCcw /> {w("od.ed.clear")}
          </Button>
          <Button onClick={onSave} loading={pending}>
            <Save /> {saveLabel}
          </Button>
        </>
      ) : null}
    </div>
  );
}

/** Brickland's "Order to Shipment · Step n of 12" strip; steps not built yet show without a link. */
const FLOW = [
  ["sales-order", "/sales-shipment/sales-order"],
  ["order-confirmation", "/sales-shipment/order-confirmation"],
  ["delivery-chalan", "/sales-shipment/delivery-chalan"],
  ["shipment-planning", "/sales-shipment/shipment-planning"],
  ["shipment-booking", "/sales-shipment/shipment-booking"],
  ["container-vessel", "/sales-shipment/container-vessel"],
  ["shipment-invoice", "/sales-shipment/shipment-invoice"],
  ["packing-list", "/sales-shipment/packing-list"],
  ["export-documents", "/sales-shipment/export-documents"],
  ["document-handover", "/sales-shipment/document-handover"],
  ["shipment-confirmation", "/sales-shipment/shipment-confirmation"],
  ["shipment-closing", "/sales-shipment/shipment-closing"],
];
function OrderFlow({ w, active }) {
  const at = FLOW.findIndex(([k]) => k === active);
  return (
    <FlowSteps
      title={w("od.flow.title")}
      stepLabel={w("od.flow.step", { n: at + 1, total: FLOW.length })}
      prevLabel={w("od.flow.prev")}
      nextLabel={w("od.flow.next")}
      active={active}
      steps={FLOW.map(([k, href]) => ({ key: k, label: w(`od.flow.s.${k}`), href }))}
    />
  );
}

const listTab = (label, count) => ["list", `${label} ${count}`, ClipboardList];
let seq = 0;
const key = () => `k${++seq}`;

export { today, num, qty, money, has, within, blank, firstError, day, useSs, DeskTabs, Filters, Tiles, Register, SideList, EditGrid, EntryHead, LockNote, Foot, OrderFlow, listTab, key };
