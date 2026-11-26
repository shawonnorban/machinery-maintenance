"use client";

import { useState, useTransition } from "react";
import { Eye, Lock, Plus, Printer, RotateCcw, Save, Scale, Table2 } from "lucide-react";
import { cn } from "@/lib/utils";
import { Select } from "@/components/ui/select";
import { Input } from "@/components/ui/input";
import { Button } from "@/components/ui/button";
import { Checkbox } from "@/components/ui/checkbox";
import { Modal } from "@/components/ui/modal";
import { ConfirmDialog } from "@/components/ui/confirm-dialog";
import { useToastManager } from "@/components/ui/toast";
import { printDocument } from "@/lib/print-document";
import { blank, day, Filters, firstError, has, money, Register, within } from "@/components/sales-shipment/shared";
import { Box } from "@/components/tradeexport/operations";
import { Field, Tabs, useW } from "@/components/finance/vouchers";

/*
 * Finance & Ledger → Period Close (AnnotechERP 23/04), each screen with its
 * own tab strip, switching in place:
 *
 * - Stock Value Closing: Records (searched, with the codes and the total),
 *   the Stock Closing worksheet (company, year, month → Show → every stock
 *   head's figure → Save Closing) and New stock closing.
 * - Bank Statement Reconciliation: Records (searched, with the book and bank
 *   balances, Print), Bank Reconcilations (account on the left, its lines on
 *   the right — cheque, bank date, cleared, remarks — saved together) and New
 *   bank line.
 * - Fiscal Year Closing: Records and the Closing Process.
 */

const STOCK = "/accounting/period/stock";
const BANK = "/accounting/period/bank";
const FY = "/accounting/period/fiscal-years";
const MONTHS = Array.from({ length: 12 }, (_, i) => i + 1);

function Tiles({ items }) {
  return (
    <div className="grid gap-3 sm:grid-cols-3">
      {items.map((t) => (
        <div key={t.label} className={cn("rounded-lg border border-border bg-surface p-4", t.tone === "good" && "border-success/40", t.tone === "info" && "border-brand/40")}>
          <p className="text-xs font-semibold tracking-wide text-foreground-muted uppercase">{t.label}</p>
          <p className="mt-1 text-xl font-semibold tabular-nums">{t.value}</p>
        </div>
      ))}
    </div>
  );
}

const monthOpts = (w) => MONTHS.map((m) => ({ value: String(m), label: w(`month.${m}`) }));

/* ───────────────────────── Stock Value Closing ───────────────────────── */

function StockClosingDesk({ initial, opt, canManage, act }) {
  const w = useW();
  const toastManager = useToastManager();
  const [rows, setRows] = useState(initial);
  const [tab, setTab] = useState("records");
  const [filter, setFilter] = useState({});
  const [sheet, setSheet] = useState({ year: opt.years[0]?.value ?? "", month: "", rows: null });
  const [entry, setEntry] = useState({ year: "", month: "", code: "", particular: "", closing: "" });
  const [editing, setEditing] = useState(null);
  const [pending, startTransition] = useTransition();
  const reload = () => act.read(STOCK).then((r) => r?.status === "success" && setRows(r.data));
  const done = (res, okText, after) => {
    if (res?.status !== "success") return toastManager.add({ title: firstError(res), type: "warning" });
    toastManager.add({ title: okText, type: "success" });
    reload();
    after?.(res);
  };
  const show = () =>
    startTransition(async () => {
      if (!sheet.year || !sheet.month) return toastManager.add({ title: w("pc.pick_period"), type: "warning" });
      const res = await act.read(`${STOCK}/worksheet?year=${sheet.year}&month=${sheet.month}`);
      if (res?.status !== "success") return toastManager.add({ title: firstError(res), type: "warning" });
      setSheet((s) => ({ ...s, rows: res.data.map((r) => ({ ...r, closing: r.closing === null ? "" : String(r.closing) })) }));
    });
  const saveSheet = () =>
    startTransition(async () => {
      const res = await act.write("PUT", `${STOCK}/worksheet`, { year: sheet.year, month: sheet.month, rows: sheet.rows.map((r) => ({ code: r.code, particular: r.particular, closing: r.closing === "" ? null : r.closing })) });
      done(res, w("pc.sheet_saved", { n: res?.data?.changed ?? 0 }));
    });
  const saveEntry = () =>
    startTransition(async () => {
      const res = await act.write("POST", STOCK, { ...Object.fromEntries(Object.entries(entry).map(([k, v]) => [k, blank(v)])) });
      done(res, w("saved"), () => setEntry({ year: "", month: "", code: "", particular: "", closing: "" }));
    });
  const saveEdit = () =>
    startTransition(async () => {
      const res = await act.write("PUT", `${STOCK}/${editing.id}`, { particular: editing.particular, closing: blank(editing.closing) });
      done(res, w("saved"), () => setEditing(null));
    });
  const filters = [
    { name: "company", label: w("f.company"), type: "select", options: opt.companies, test: () => true },
    { name: "year", label: w("pc.f.year"), type: "select", options: opt.years, test: (r, v) => !v || String(r.year) === v },
    { name: "month", label: w("pc.f.month"), type: "select", options: monthOpts(w), test: (r, v) => !v || String(r.month) === v },
  ];
  const shown = rows.filter((r) => filters.every((f) => f.test(r, filter[f.name])));
  return (
    <div className="flex flex-col gap-4">
      <Tabs
        active={tab}
        onPick={setTab}
        tabs={[
          { key: "records", label: w("tab.records"), icon: <Table2 className="size-4" />, count: rows.length },
          { key: "sheet", label: w("pc.tab.sheet"), icon: <Eye className="size-4" /> },
          ...(canManage ? [{ key: "new", label: w("pc.tab.new_stock"), icon: <Plus className="size-4" /> }] : []),
        ]}
      />
      {tab === "records" ? (
        <>
          <Filters w={w} value={filter} onChange={setFilter} fields={filters} note={w("pc.stock_filter_note")} />
          <Tiles
            items={[
              { label: w("pc.codes"), value: shown.length },
              { label: w("pc.total_closing"), value: money(shown.reduce((a, r) => a + r.closing, 0)), tone: "info" },
            ]}
          />
          <Register
            w={w}
            rows={shown}
            refKey="id"
            exportName="stock-closing"
            onDone={reload}
            emptyTitle={w("empty")}
            emptyText=""
            actions={canManage ? [{ key: "edit", label: w("edit"), open: (r) => setEditing({ ...r, closing: String(r.closing) }) }, { key: "delete", label: w("delete"), confirm: w("pc.confirm_stock_delete"), done: w("pc.stock_deleted"), destructive: true, run: (no, _e, r) => act.write("DELETE", `${STOCK}/${r.id}`) }] : []}
            columns={[
              { key: "company", label: w("col.company") },
              { key: "period", label: w("pc.col.period") },
              { key: "code", label: w("pc.col.code"), type: "mono" },
              { key: "particular", label: w("pc.col.particular") },
              { key: "closing", label: w("pc.col.closing"), type: "number", align: "right" },
            ]}
          />
          <p className="text-xs text-foreground-muted">{w("pc.stock_form_note")}</p>
        </>
      ) : tab === "sheet" ? (
        <Box legend={w("pc.tab.sheet")} grid={false}>
          <p className="mb-3 text-[13px] text-foreground-muted">{w("pc.sheet_hint")}</p>
          <div className="grid items-end gap-3 lg:grid-cols-4">
            <Field label={w("f.company")} req>
              <Select value={opt.companies[0]?.value ?? ""} options={opt.companies} aria-label={w("f.company")} />
            </Field>
            <Field label={w("pc.f.year")} req>
              <Select value={sheet.year} onValueChange={(v) => setSheet({ ...sheet, year: v ?? "", rows: null })} options={opt.years} placeholder={w("pc.pick_year")} aria-label={w("pc.f.year")} />
            </Field>
            <Field label={w("pc.f.month")} req>
              <Select value={sheet.month} onValueChange={(v) => setSheet({ ...sheet, month: v ?? "", rows: null })} options={monthOpts(w)} placeholder={w("pc.pick_month")} aria-label={w("pc.f.month")} />
            </Field>
            <Button type="button" variant="outline" onClick={show} loading={pending}>
              <Eye /> {w("pc.show")}
            </Button>
          </div>
          {sheet.rows === null ? (
            <p className="py-8 text-center text-sm text-foreground-muted">{w("pc.sheet_empty")}</p>
          ) : (
            <>
              <div className="mt-4 overflow-x-auto rounded-md border border-border">
                <table className="w-full min-w-max text-sm">
                  <thead>
                    <tr className="bg-brand text-left text-brand-foreground">
                      <th className="px-3 py-2 font-semibold">{w("pc.col.code")}</th>
                      <th className="px-3 py-2 font-semibold">{w("pc.col.particular")}</th>
                      <th className="px-3 py-2 text-right font-semibold">{w("pc.col.closing")}</th>
                    </tr>
                  </thead>
                  <tbody>
                    {sheet.rows.length ? (
                      sheet.rows.map((r, i) => (
                        <tr key={r.code} className="border-b border-border last:border-0">
                          <td className="px-3 py-1.5 font-mono text-xs">{r.code}</td>
                          <td className="px-3 py-1.5">{r.particular}</td>
                          <td className="px-3 py-1">
                            <Input type="number" min="0" step="any" value={r.closing} disabled={!canManage} onChange={(e) => setSheet((s) => ({ ...s, rows: s.rows.map((x, j) => (j === i ? { ...x, closing: e.target.value } : x)) }))} className="ml-auto w-40 text-right" aria-label={`${w("pc.col.closing")} ${r.code}`} />
                          </td>
                        </tr>
                      ))
                    ) : (
                      <tr>
                        <td colSpan={3} className="px-3 py-6 text-center text-foreground-muted">
                          {w("pc.no_heads")}
                        </td>
                      </tr>
                    )}
                  </tbody>
                </table>
              </div>
              <div className="mt-3 flex flex-wrap items-center gap-3">
                <p className="min-w-0 flex-1 text-xs text-foreground-muted">{w("pc.sheet_note")}</p>
                <p className="text-sm font-semibold">{w("pc.sheet_total", { amount: money(sheet.rows.reduce((a, r) => a + Number(r.closing || 0), 0)) })}</p>
                {canManage ? (
                  <Button onClick={saveSheet} loading={pending} disabled={!sheet.rows.length}>
                    <Save /> {w("pc.save_closing")}
                  </Button>
                ) : null}
              </div>
            </>
          )}
        </Box>
      ) : (
        <Box legend={w("pc.tab.new_stock")} grid={false}>
          <div className="grid gap-x-6 gap-y-3 lg:grid-cols-3">
            <Field label={w("f.company")} req>
              <Select value={opt.companies[0]?.value ?? ""} options={opt.companies} aria-label={w("f.company")} />
            </Field>
            <Field label={w("pc.f.year")} req>
              <Input type="number" value={entry.year} onChange={(e) => setEntry({ ...entry, year: e.target.value })} aria-label={w("pc.f.year")} />
            </Field>
            <Field label={w("pc.f.month")} req>
              <Input type="number" min="1" max="12" value={entry.month} onChange={(e) => setEntry({ ...entry, month: e.target.value })} aria-label={w("pc.f.month")} />
            </Field>
            <Field label={w("pc.col.code")} req>
              <Input value={entry.code} maxLength={24} onChange={(e) => setEntry({ ...entry, code: e.target.value })} aria-label={w("pc.col.code")} />
            </Field>
            <Field label={w("pc.col.particular")} req>
              <Input value={entry.particular} maxLength={160} onChange={(e) => setEntry({ ...entry, particular: e.target.value })} aria-label={w("pc.col.particular")} />
            </Field>
            <Field label={w("pc.col.closing")} req>
              <Input type="number" min="0" step="any" value={entry.closing} onChange={(e) => setEntry({ ...entry, closing: e.target.value })} aria-label={w("pc.col.closing")} />
            </Field>
          </div>
          <div className="mt-4 flex justify-end gap-3">
            <Button variant="outline" onClick={() => setEntry({ year: "", month: "", code: "", particular: "", closing: "" })} disabled={pending}>
              <RotateCcw /> {w("reset")}
            </Button>
            <Button onClick={saveEntry} loading={pending}>
              <Save /> {w("pc.save_stock")}
            </Button>
          </div>
        </Box>
      )}
      <Modal open={editing !== null} onOpenChange={(o) => !o && setEditing(null)} title={editing ? `${editing.code} · ${editing.period}` : ""} className="max-w-lg">
        {editing ? (
          <div className="flex flex-col gap-3">
            <Field label={w("pc.col.particular")} req>
              <Input value={editing.particular} maxLength={160} onChange={(e) => setEditing({ ...editing, particular: e.target.value })} aria-label={w("pc.col.particular")} />
            </Field>
            <Field label={w("pc.col.closing")} req>
              <Input type="number" min="0" step="any" value={editing.closing} onChange={(e) => setEditing({ ...editing, closing: e.target.value })} aria-label={w("pc.col.closing")} />
            </Field>
            <div className="flex justify-end">
              <Button onClick={saveEdit} loading={pending}>
                <Save /> {w("save")}
              </Button>
            </div>
          </div>
        ) : null}
      </Modal>
    </div>
  );
}

/* ───────────────────────── Bank Statement Reconciliation ───────────────────────── */

const blankLine = () => ({ voucher_line_id: "", bank_account_id: "", cheque_no: "", bank_date: "", cleared: false, remarks: "" });

function BankRecDesk({ initial, board: firstBoard, opt, canManage, act }) {
  const w = useW();
  const toastManager = useToastManager();
  const [rows, setRows] = useState(initial);
  const [board, setBoard] = useState(firstBoard);
  const [tab, setTab] = useState("records");
  const [filter, setFilter] = useState({});
  const [picked, setPicked] = useState(null);
  const [edits, setEdits] = useState({});
  const [q, setQ] = useState("");
  const [line, setLine] = useState(blankLine());
  const [candidates, setCandidates] = useState(null);
  const [pending, startTransition] = useTransition();
  const reload = () => {
    act.read(BANK).then((r) => r?.status === "success" && setRows(r.data));
    act.read(`${BANK}/board`).then((r) => r?.status === "success" && setBoard(r.data));
  };
  const openNew = () => {
    setTab("new");
    if (candidates === null) act.read(`${BANK}/candidates`).then((r) => r?.status === "success" && setCandidates(r.data));
  };
  const account = board.find((a) => a.id === picked);
  const lines = (account?.lines ?? []).map((l) => ({ ...l, ...(edits[l.voucher_line_id] ?? {}) }));
  const edit = (id, k, v) => setEdits((e) => ({ ...e, [id]: { ...(e[id] ?? {}), [k]: v } }));
  const balances = (ls) => {
    const book = ls.reduce((a, l) => a + l.dr - l.cr, 0);
    const bank = ls.filter((l) => l.cleared).reduce((a, l) => a + l.dr - l.cr, 0);
    return [
      { label: w("pc.book_balance"), value: money(book) },
      { label: w("pc.bank_balance"), value: money(bank), tone: "good" },
      { label: w("pc.not_in_bank"), value: money(book - bank) },
    ];
  };
  const saveBoard = () =>
    startTransition(async () => {
      const res = await act.write("PUT", `${BANK}/accounts/${picked}`, { rows: lines.map((l) => ({ voucher_line_id: l.voucher_line_id, cheque_no: blank(l.cheque_no ?? ""), bank_date: blank(l.bank_date ?? ""), cleared: Boolean(l.cleared), remarks: blank(l.remarks ?? "") })) });
      if (res?.status !== "success") return toastManager.add({ title: firstError(res), type: "warning" });
      toastManager.add({ title: w("saved"), type: "success" });
      setEdits({});
      reload();
    });
  const saveLine = () =>
    startTransition(async () => {
      const res = await act.write("POST", BANK, { voucher_line_id: line.voucher_line_id || null, bank_account_id: line.bank_account_id || null, cheque_no: blank(line.cheque_no), bank_date: blank(line.bank_date), cleared: line.cleared, remarks: blank(line.remarks) });
      if (res?.status !== "success") return toastManager.add({ title: firstError(res), type: "warning" });
      toastManager.add({ title: w("saved"), type: "success" });
      setLine(blankLine());
      setCandidates(null);
      act.read(`${BANK}/candidates`).then((r) => r?.status === "success" && setCandidates(r.data));
      reload();
    });
  const filters = [
    { name: "company", label: w("f.company"), type: "select", options: opt.companies, test: () => true },
    { name: "from", label: w("pc.f.from"), type: "date", test: (r, v) => within(r.voucher_date, v, null) },
    { name: "to", label: w("pc.f.to"), type: "date", test: (r, v) => within(r.voucher_date, null, v) },
    { name: "bank", label: w("pc.f.bank"), type: "select", options: opt.accounts, test: (r, v) => !v || r.bank_account_id === v },
    { name: "status", label: w("pc.f.status"), type: "select", options: [{ value: "cleared", label: w("pc.cleared") }, { value: "uncleared", label: w("pc.not_in_bank_opt") }], test: (r, v) => !v || (v === "cleared" ? r.cleared : !r.cleared) },
  ];
  const shown = rows.map((r) => ({ ...r, _key: r.voucher_line_id, type_label: w(`vtype.${r.voucher_type}`) })).filter((r) => filters.every((f) => f.test(r, filter[f.name])));
  const print = () =>
    printDocument({
      title: w("pc.print_title"),
      orientation: "landscape",
      meta: [
        [w("pc.f.from"), filter.from ? day(filter.from) : "—"],
        [w("pc.f.to"), filter.to ? day(filter.to) : "—"],
        [w("pc.f.bank"), opt.accounts.find((a) => a.value === filter.bank)?.label ?? w("od.f.all")],
      ],
      sections: [
        {
          columns: [{ label: w("pc.col.account") }, { label: w("pc.col.voucher") }, { label: w("pc.col.cheque") }, { label: w("pc.col.type") }, { label: w("pc.col.voucher_date") }, { label: w("pc.col.dr"), align: "right" }, { label: w("pc.col.cr"), align: "right" }, { label: w("pc.col.bank_date") }, { label: w("pc.col.remarks") }],
          rows: shown.map((r) => [r.account, r.voucher_no, r.cheque_no ?? "—", r.type_label, day(r.voucher_date), money(r.dr), money(r.cr), r.bank_date ? day(r.bank_date) : "—", r.remarks ?? "—"]),
        },
      ],
      notes: balances(shown).map((b) => `${b.label}: ${b.value}`),
    });
  return (
    <div className="flex flex-col gap-4">
      <Tabs
        active={tab}
        onPick={(t) => (t === "new" ? openNew() : setTab(t))}
        tabs={[
          { key: "records", label: w("tab.records"), icon: <Table2 className="size-4" />, count: rows.length },
          { key: "board", label: w("pc.tab.board"), icon: <Scale className="size-4" /> },
          ...(canManage ? [{ key: "new", label: w("pc.tab.new_line"), icon: <Plus className="size-4" /> }] : []),
        ]}
      />
      {tab === "records" ? (
        <>
          <Filters
            w={w}
            value={filter}
            onChange={setFilter}
            fields={filters}
            note={w("pc.bank_filter_note")}
            extra={
              <Button type="button" variant="outline" onClick={print}>
                <Printer /> {w("print")}
              </Button>
            }
          />
          <Tiles items={balances(shown)} />
          <Register
            w={w}
            rows={shown}
            refKey="_key"
            exportName="bank-reconciliation"
            onDone={reload}
            emptyTitle={w("empty")}
            emptyText=""
            actions={canManage ? [{ key: "remove", label: w("pc.remove_line"), when: (r) => r.on_file && !r.cleared, confirm: w("pc.confirm_remove_line"), done: w("pc.line_removed"), destructive: true, run: (no, _e, r) => act.write("DELETE", `${BANK}/${r.voucher_line_id}`) }] : []}
            columns={[
              { key: "account", label: w("pc.col.account") },
              { key: "voucher_no", label: w("pc.col.voucher"), type: "mono" },
              { key: "cheque_no", label: w("pc.col.cheque") },
              { key: "type_label", label: w("pc.col.type") },
              { key: "voucher_date", label: w("pc.col.voucher_date"), type: "date" },
              { key: "dr", label: w("pc.col.dr"), type: "number", align: "right" },
              { key: "cr", label: w("pc.col.cr"), type: "number", align: "right" },
              { key: "bank_date", label: w("pc.col.bank_date"), type: "date" },
              { key: "remarks", label: w("pc.col.remarks") },
            ]}
          />
          <p className="text-xs text-foreground-muted">{w("pc.bank_form_note")}</p>
        </>
      ) : tab === "board" ? (
        <div className="grid gap-4 lg:grid-cols-[320px_minmax(0,1fr)]">
          <aside className="flex flex-col gap-3 self-start rounded-lg border border-border bg-surface p-4">
            <div className="flex items-center justify-between">
              <h3 className="text-[15px] font-semibold">{w("pc.bank_account")}</h3>
              <span className="rounded-full bg-surface-muted px-2 py-0.5 text-xs">{board.length}</span>
            </div>
            <Input type="search" value={q} onChange={(e) => setQ(e.target.value)} placeholder={w("search_dots")} aria-label={w("pc.search_accounts")} />
            {board
              .filter((a) => !q || has(a.label, q))
              .map((a) => (
                <button
                  key={a.id}
                  type="button"
                  onClick={() => {
                    setPicked(a.id);
                    setEdits({});
                  }}
                  className={cn("flex items-center justify-between gap-2 rounded-md border border-border px-3 py-2 text-left text-sm", picked === a.id ? "border-brand bg-brand-subtle" : "hover:bg-surface-muted")}
                >
                  <span className="min-w-0">
                    <span className="block font-semibold break-words">{a.label}</span>
                    <span className="text-xs text-foreground-muted">{w("pc.lines_count", { n: a.count })}</span>
                  </span>
                  <span className="tabular-nums">{a.count}</span>
                </button>
              ))}
          </aside>
          <section className="min-w-0 rounded-lg border border-border bg-surface p-4">
            <h3 className="text-[15px] font-semibold">{w("pc.voucher_lines")}</h3>
            <p className="mb-3 text-[13px] text-foreground-muted">{w("pc.board_hint")}</p>
            {!account ? (
              <p className="py-10 text-center text-sm text-foreground-muted">{w("pc.pick_account")}</p>
            ) : (
              <>
                <div className="overflow-x-auto rounded-md border border-border">
                  <table className="w-full min-w-max text-sm">
                    <thead>
                      <tr className="bg-brand text-left text-brand-foreground">
                        {["voucher", "cheque", "type", "voucher_date", "dr", "cr", "bank_date", "cleared", "remarks"].map((k) => (
                          <th key={k} className={cn("px-3 py-2 font-semibold whitespace-nowrap", ["dr", "cr"].includes(k) && "text-right", k === "cleared" && "text-center")}>
                            {w(`pc.col.${k}`)}
                          </th>
                        ))}
                      </tr>
                    </thead>
                    <tbody>
                      {lines.length ? (
                        lines.map((l) => (
                          <tr key={l.voucher_line_id} className="border-b border-border last:border-0">
                            <td className="px-3 py-1.5 font-mono text-xs">{l.voucher_no}</td>
                            <td className="px-2 py-1">
                              <Input value={l.cheque_no ?? ""} maxLength={48} disabled={!canManage} onChange={(e) => edit(l.voucher_line_id, "cheque_no", e.target.value)} className="w-32" aria-label={`${w("pc.col.cheque")} ${l.voucher_no}`} />
                            </td>
                            <td className="px-3 py-1.5">{w(`vtype.${l.voucher_type}`)}</td>
                            <td className="px-3 py-1.5">{day(l.voucher_date)}</td>
                            <td className="px-3 py-1.5 text-right tabular-nums">{money(l.dr)}</td>
                            <td className="px-3 py-1.5 text-right tabular-nums">{money(l.cr)}</td>
                            <td className="px-2 py-1">
                              <Input type="date" value={l.bank_date ?? ""} disabled={!canManage} onChange={(e) => edit(l.voucher_line_id, "bank_date", e.target.value)} className="w-40" aria-label={`${w("pc.col.bank_date")} ${l.voucher_no}`} />
                            </td>
                            <td className="px-3 py-1.5 text-center">
                              <Checkbox checked={Boolean(l.cleared)} disabled={!canManage} onCheckedChange={(v) => edit(l.voucher_line_id, "cleared", v === true)} aria-label={`${w("pc.col.cleared")} ${l.voucher_no}`} />
                            </td>
                            <td className="px-2 py-1">
                              <Input value={l.remarks ?? ""} maxLength={300} disabled={!canManage} onChange={(e) => edit(l.voucher_line_id, "remarks", e.target.value)} className="w-48" aria-label={`${w("pc.col.remarks")} ${l.voucher_no}`} />
                            </td>
                          </tr>
                        ))
                      ) : (
                        <tr>
                          <td colSpan={9} className="px-3 py-6 text-center text-foreground-muted">
                            {w("pc.no_lines")}
                          </td>
                        </tr>
                      )}
                    </tbody>
                  </table>
                </div>
                <div className="mt-3">
                  <Tiles items={balances(lines)} />
                </div>
                <div className="mt-3 flex flex-wrap items-center gap-3">
                  <p className="min-w-0 flex-1 text-xs text-foreground-muted">{w("pc.board_note")}</p>
                  {canManage ? (
                    <Button onClick={saveBoard} loading={pending} disabled={!lines.length}>
                      <Save /> {w("pc.save_lines")}
                    </Button>
                  ) : null}
                </div>
              </>
            )}
          </section>
        </div>
      ) : (
        <Box legend={w("pc.tab.new_line")} grid={false}>
          <div className="grid gap-x-6 gap-y-3 lg:grid-cols-3">
            <Field label={w("pc.f.voucher_line")} req>
              <Select value={line.voucher_line_id} onValueChange={(v) => setLine({ ...line, voucher_line_id: v ?? "" })} options={candidates ?? []} placeholder={w("select")} aria-label={w("pc.f.voucher_line")} />
            </Field>
            <Field label={w("pc.bank_account")} req>
              <Select value={line.bank_account_id} onValueChange={(v) => setLine({ ...line, bank_account_id: v ?? "" })} options={opt.accounts} placeholder={w("select")} aria-label={w("pc.bank_account")} />
            </Field>
            <Field label={w("pc.col.cheque")}>
              <Input value={line.cheque_no} maxLength={48} onChange={(e) => setLine({ ...line, cheque_no: e.target.value })} aria-label={w("pc.col.cheque")} />
            </Field>
            <Field label={w("pc.col.bank_date")}>
              <Input type="date" value={line.bank_date} onChange={(e) => setLine({ ...line, bank_date: e.target.value })} aria-label={w("pc.col.bank_date")} />
            </Field>
            <label className="flex items-center gap-2 self-end pb-2 text-sm font-semibold">
              <Checkbox checked={line.cleared} onCheckedChange={(v) => setLine({ ...line, cleared: v === true })} aria-label={w("pc.col.cleared")} /> {w("pc.col.cleared")}
            </label>
            <Field label={w("pc.col.remarks")}>
              <Input value={line.remarks} maxLength={300} onChange={(e) => setLine({ ...line, remarks: e.target.value })} aria-label={w("pc.col.remarks")} />
            </Field>
          </div>
          <p className="mt-2 text-xs text-foreground-muted">{w("pc.new_line_hint")}</p>
          <div className="mt-4 flex justify-end gap-3">
            <Button variant="outline" onClick={() => setLine(blankLine())} disabled={pending}>
              <RotateCcw /> {w("reset")}
            </Button>
            <Button onClick={saveLine} loading={pending}>
              <Save /> {w("pc.save_line")}
            </Button>
          </div>
        </Box>
      )}
    </div>
  );
}

/* ───────────────────────── Fiscal Year Closing ───────────────────────── */

function FyCloseDesk({ initial, opt, canManage, act }) {
  const w = useW();
  const toastManager = useToastManager();
  const [rows, setRows] = useState(initial);
  const [periods, setPeriods] = useState(opt.periods);
  const [tab, setTab] = useState("records");
  const [form, setForm] = useState({ fiscal_year_id: "", remarks: "" });
  const [asking, setAsking] = useState(false);
  const [pending, startTransition] = useTransition();
  const reload = () => {
    act.read(FY).then((r) => r?.status === "success" && setRows(r.data));
    act.read(`${FY}/options`).then((r) => r?.status === "success" && setPeriods(r.data.periods));
  };
  const close = () =>
    startTransition(async () => {
      const res = await act.write("POST", FY, { fiscal_year_id: form.fiscal_year_id || null, remarks: blank(form.remarks) });
      setAsking(false);
      if (res?.status !== "success") return toastManager.add({ title: firstError(res), type: "warning" });
      toastManager.add({ title: w("pc.fy_closed", { period: res.data.period, n: res.data.vouchers }), type: "success" });
      setForm({ fiscal_year_id: "", remarks: "" });
      reload();
      setTab("records");
    });
  return (
    <div className="flex flex-col gap-4">
      <Tabs
        active={tab}
        onPick={setTab}
        tabs={[{ key: "records", label: w("tab.records"), icon: <Table2 className="size-4" />, count: rows.length }, ...(canManage ? [{ key: "process", label: w("pc.tab.process"), icon: <Lock className="size-4" /> }] : [])]}
      />
      {tab === "records" ? (
        <>
          <Register
            w={w}
            rows={rows}
            refKey="id"
            exportName="fiscal-year-closing"
            onDone={reload}
            emptyTitle={w("empty")}
            emptyText=""
            actions={canManage ? [{ key: "reopen", label: w("pc.reopen"), confirm: w("pc.confirm_reopen"), done: w("pc.reopened"), destructive: true, run: (no, _e, r) => act.write("DELETE", `${FY}/${r.id}`) }] : []}
            columns={[
              { key: "company", label: w("col.company") },
              { key: "period", label: w("pc.col.period") },
              { key: "vouchers", label: w("pc.col.vouchers_posted"), type: "number", align: "right" },
              { key: "balance", label: w("pc.col.closing_balance"), type: "number", align: "right" },
              { key: "closed_by", label: w("pc.col.created_by") },
              { key: "closed_at", label: w("pc.col.closed") },
            ]}
          />
          <p className="text-xs text-foreground-muted">{w("pc.fy_form_note")}</p>
        </>
      ) : (
        <Box legend={w("pc.new_fy_closing")} grid={false}>
          <div className="grid gap-x-6 gap-y-3 lg:grid-cols-2">
            <Field label={w("pc.f.period")} req>
              <Select value={form.fiscal_year_id} onValueChange={(v) => setForm({ ...form, fiscal_year_id: v ?? "" })} options={periods} placeholder={w("select")} aria-label={w("pc.f.period")} />
            </Field>
            <Field label={w("pc.col.remarks")}>
              <textarea rows={3} value={form.remarks} onChange={(e) => setForm({ ...form, remarks: e.target.value })} aria-label={w("pc.col.remarks")} className="w-full rounded-md border border-border bg-surface px-3 py-2 text-sm" />
            </Field>
          </div>
          <p className="mt-2 text-xs text-foreground-muted">{w("pc.fy_form_note")}</p>
          <div className="mt-4 flex justify-end gap-3">
            <Button variant="outline" onClick={() => setForm({ fiscal_year_id: "", remarks: "" })} disabled={pending}>
              <RotateCcw /> {w("reset")}
            </Button>
            <Button onClick={() => (form.fiscal_year_id ? setAsking(true) : toastManager.add({ title: w("pc.pick_fy"), type: "warning" }))} loading={pending}>
              <Lock /> {w("pc.close_year")}
            </Button>
          </div>
        </Box>
      )}
      <ConfirmDialog
        open={asking}
        onOpenChange={(o) => !o && setAsking(false)}
        title={w("pc.close_year")}
        description={w("pc.confirm_close")}
        confirmLabel={w("pc.close_year")}
        cancelLabel={w("od.reg.keep")}
        destructive
        loading={pending}
        onConfirm={close}
      />
    </div>
  );
}

export { StockClosingDesk, BankRecDesk, FyCloseDesk };
