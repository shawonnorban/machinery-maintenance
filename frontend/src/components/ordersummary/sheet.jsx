"use client";

import { createContext, memo, useCallback, useContext, useEffect, useId, useLayoutEffect, useMemo, useRef, useState, useTransition } from "react";
import { createPortal } from "react-dom";
import Link from "next/link";
import { usePathname, useRouter } from "next/navigation";
import { ArrowLeft, CheckCircle2, Copy, CopyPlus, FileSpreadsheet, Filter, Link2, Loader2, PencilLine, Plus, Printer, Save, Trash2, Upload, X } from "lucide-react";
import { cn } from "@/lib/utils";
import { Button, buttonVariants } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Checkbox } from "@/components/ui/checkbox";
import { Modal } from "@/components/ui/modal";
import { ConfirmDialog } from "@/components/ui/confirm-dialog";
import { useToastManager } from "@/components/ui/toast";
import { ColumnView, useColumnView } from "@/components/erp/column-view";
import { FormulaSet, blank, num, pairsNumber } from "@/lib/formula";
import { useT } from "@/lib/i18n";
import { DEFAULT_COLUMNS, GROUP_TONE, TEXT_TONE, TONE, copyText, display, pairNumber, toneOf } from "./common";

/*
 * Order Summary → View All / Create New (AnnotechERP 32): the factory's Excel
 * sheet in the ERP. Small type, grey upper-case headers under their coloured
 * groups, a filter on every column header (the values the other filters
 * leave, with counts, as Excel's), "N of M records found", the colour rules
 * and vertical text Column Setup gives each column (a vertical column is kept
 * narrow). A header click does not sort (the header filter is the way in).
 *
 * View All reads until rows are put in edit mode: tick them and press Bulk
 * Edit — only those rows open, their cells boxed; a click on a cell edits it
 * (Enter / Tab / arrows move on, Escape drops), a paste from Excel fills the
 * cells from the active one across and down, Ctrl+D fills the active cell's
 * value down the rows in edit mode. Create New is editable throughout.
 * Formulas are worked out as you type (lib/formula.js — the server works them
 * out again on save). Save sends only the changed rows, each with the version
 * it was read at; a row someone else saved meanwhile comes back as theirs,
 * marked. Each row is its own memoised component, so typing in one row does
 * not redraw the sheet.
 */

const PER_PAGE = ["25", "50", "100", "200", "500", "all"];
/** View All draws only the rows in view once it holds more than this (fixed row height); OVERSCAN rows either side. */
const VIRTUAL_AFTER = 100;
const VIRTUAL_OVERSCAN = 12;

/**
 * What a module tells the sheet (Order Summary's own values are the default;
 * Pre-Budget's requisitions pass theirs): where the list lives, the Column
 * View's storage key, the artwork upload address and the fields its title is
 * made of, the columns View All starts with (null: all), whether a column
 * belongs to a row (`applies`, e.g. a Knit-only column on a Woven row: the
 * cell stays empty and closed), the save buttons of Create New (`saveModes`:
 * [{ status, label, icon? }] — each sends `act("store", rows, status)`), and
 * extra buttons for ticked rows on View All (`listActions`: [{ action, label,
 * done }] — each calls `act(action, ids)`), and a fixed row height
 * (`rowHeight`, px — one number, or { list, new }: a cell shows what fits,
 * the artwork is scaled down). `draft` (Create New, Norban's Draft
 * Requisition): { no, rows, savedAt, url, words } — the page saves itself as
 * a draft every two minutes while there are changes, on a save mode marked
 * `draft` and as the page closes; opened with a draft, its rows are filled
 * back in; the save mode that stores the rows drops the draft (`draft_no`).
 */
const OS_CONFIG = {
  viewKey: "ordersummary",
  listPath: "/order-summary/list",
  artworkUrl: "/api/order-summary/artwork",
  titleKeys: ["style_number_name", "buyer"],
  defaultColumns: DEFAULT_COLUMNS,
  applies: null,
  saveModes: null,
  listActions: null,
  // View All opens filtered on this column's latest date (Order Summary's Last Update Date, user 2026-11-22);
  // clearing every filter then shows all (`all=1` in the address, so a refresh does not filter again).
  defaultFilter: null,
  // View All's Print (`print`: { href(queryString, shownKeys, tickedIds), label, title }) — Pre-Budget's requisitions.
  print: null,
  // View All's Export Excel (the company's own book layout, with the artwork): the route that writes it; null = none.
  excelUrl: "/api/order-summary/export",
  // View All's Print (the shown columns of the filtered or ticked orders, A4 Landscape): the print page; null = none.
  printUrl: "/print/order-summary",
  editable: true,
  rowHeight: { new: 60, list: 80 },
  draft: null,
  // A second value shown under a cell's own (`companions`: { columnKey: valueKey }) — Dev Sample's
  // Last Price Quote Date with who entered it, as in Norban.
  companions: null,
  // View All's Copy Row (Export Statement has none: an invoice number is never entered twice).
  copy: true,
  // More buttons on View All's toolbar, beside Export Excel (Export Statement's Import Excel).
  toolbarExtra: null,
  // The company's Job No (26NCL00001 — year, the row's factory, serial; user 2026-11-22): { column, factory, url };
  // factory null = the dialog picks one (Dev Sample has no factory column). A text column holds one number, a multi column a list.
  // That list column's dialog fills the year and factory and offers the next serial; null = a plain list.
  jobNo: null,
};

/** Every two minutes, as in Norban. */
const DRAFT_EVERY = 120000;

/** A draft's saved rows back as sheet rows (each cell counts as typed, so Complete sends it). */
function draftRows(saved, formulas, today) {
  return saved.map((r) => {
    const values = formulas.apply({ ...(r.values ?? {}) }, today, true);
    return { ...blankRow(), values, artwork_url: r.artwork_url ?? null, order: r.order ?? null, dirty: Object.fromEntries(Object.keys(r.values ?? {}).map((k) => [k, true])) };
  });
}
const SheetConfig = createContext(OS_CONFIG);
const NUMERIC = ["number", "money", "percent", "days"];

function OrderSheet(props) {
  // A fresh sheet when the view or its set of rows changes. A save only bumps versions:
  // the sheet stays (rows still in edit mode after a conflict keep their place) and takes the new rows itself.
  const key = props.data ? `${props.queryString}|${props.data.rows.map((r) => r.id).join(",")}` : "new";
  return <Sheet key={key} {...props} />;
}

let tempId = 0;
const blankRow = () => ({ key: `new-${++tempId}`, id: null, values: {}, dirty: {}, isNew: true });
/** A column's width as Column Setup sets it (a vertical one too), 30–600 px. */
const widthOf = (c) => Math.max(30, Math.min(c.width ?? 120, 600));
/** Frozen columns may take at most this share of the sheet's width, so the rest can still scroll. */
const FREEZE_MAX = 0.8;

function Sheet({ mode, meta, data, queryString, act, config }) {
  const cfg = useMemo(() => ({ ...OS_CONFIG, ...config }), [config]);
  const jobCache = useJobNoCache(cfg.jobNo);
  const t = useT("ordersummary");
  const router = useRouter();
  const pathname = usePathname();
  const toast = useToastManager();
  // Export Excel: the list's filters and sort (and the ticked rows, if any) to the book-layout .xlsx, downloaded when written.
  const [exporting, setExporting] = useState(false);
  const params = useMemo(() => new URLSearchParams(queryString), [queryString]);
  // Create New has no system serial yet (it is given on save), so its column is left out there (user, 2026-11-23).
  const columns = useMemo(() => meta.columns.filter((c) => !c.hidden && !(mode === "new" && c.key === "serial_no")), [meta, mode]);
  const allKeys = useMemo(() => columns.map((c) => c.key), [columns]);
  const defaults = useMemo(() => (mode === "new" || !cfg.defaultColumns ? allKeys : cfg.defaultColumns.filter((k) => allKeys.includes(k))), [mode, allKeys, cfg.defaultColumns]);
  const [visible, setVisible] = useColumnView(`${cfg.viewKey}.${mode}.columns`, allKeys, defaults);
  const shown = useMemo(() => columns.filter((c) => visible.includes(c.key)), [columns, visible]);
  // Every column exactly as wide as Column Setup says (a fixed layout needs the table's own width).
  const tableWidth = useMemo(() => 32 + shown.reduce((sum, c) => sum + widthOf(c), 0), [shown]);
  // Freeze Panes (set in Column Setup): every shown column up to the frozen one keeps its place
  // while the sheet scrolls right — `left` is each one's sticky offset.
  const [gridWidth, setGridWidth] = useState(0);
  const freeze = useMemo(() => {
    // Create New keeps every column scrolling (nothing frozen there).
    const until = mode === "new" ? -1 : columns.findIndex((c) => c.frozen);
    const left = {};
    let last = null;
    let x = 32;
    for (const c of shown) {
      if (until < 0 || columns.indexOf(c) > until) break;
      if (gridWidth && x + widthOf(c) > gridWidth * FREEZE_MAX) break;
      left[c.key] = x;
      x += widthOf(c);
      last = c.key;
    }
    return { left, last };
  }, [mode, columns, shown, gridWidth]);
  const formulas = useMemo(() => new FormulaSet(Object.fromEntries(meta.columns.map((c) => [c.key, { formula: c.formula, type: c.type, decimals: c.decimals, reduce: c.pair_reduce }]))), [meta]);
  const [rows, setRows] = useState(() =>
    mode === "new"
      ? cfg.draft?.rows?.length
        ? [...draftRows(cfg.draft.rows, formulas, meta.today), blankRow()]
        : [blankRow(), blankRow(), blankRow()]
      : data.rows.map((r) => ({ ...r, key: r.id, dirty: {} })),
  );
  const [selected, setSelected] = useState(() => new Set());
  const [editRows, setEditRows] = useState(() => new Set()); // rows in edit mode (View All)
  const [active, setActive] = useState(null); // { row: index, col: index }
  const [editing, setEditing] = useState(false);
  const [filterFor, setFilterFor] = useState(null);
  const [deleting, setDeleting] = useState(false);
  const [pending, start] = useTransition();
  const grid = useRef(null);

  // Only the rows in view are drawn once View All holds many (Show: All — 1,700+ orders made the page crawl, user
  // 2026-11-20): rows are a fixed height, so the rest are stood in for by two spacer rows of the right height.
  const listRowHeight = mode === "list" ? (cfg.rowHeight && typeof cfg.rowHeight === "object" ? cfg.rowHeight.list : cfg.rowHeight) : null;
  const virtual = Boolean(listRowHeight) && rows.length > VIRTUAL_AFTER;
  const [view, setView] = useState({ top: 0, height: 900, head: 0, row: listRowHeight || 0 });
  useEffect(() => {
    const el = grid.current;
    if (!virtual || !el) return undefined;
    let frame = 0;
    const measure = () => {
      cancelAnimationFrame(frame);
      frame = requestAnimationFrame(() => {
        const head = el.querySelector("thead")?.getBoundingClientRect().height ?? 0;
        const row = el.querySelector("tbody tr[data-row]")?.getBoundingClientRect().height || listRowHeight;
        setView((v) => (v.top === el.scrollTop && v.height === el.clientHeight && v.head === head && v.row === row ? v : { top: el.scrollTop, height: el.clientHeight, head, row }));
      });
    };
    measure();
    el.addEventListener("scroll", measure, { passive: true });
    window.addEventListener("resize", measure);
    return () => {
      cancelAnimationFrame(frame);
      el.removeEventListener("scroll", measure);
      window.removeEventListener("resize", measure);
    };
  }, [virtual, listRowHeight, rows.length]);
  const rowPx = view.row || listRowHeight || 1;
  const firstRow = virtual ? Math.max(0, Math.floor((view.top - view.head) / rowPx) - VIRTUAL_OVERSCAN) : 0;
  const lastRow = virtual ? Math.min(rows.length, Math.ceil((view.top + view.height) / rowPx) + VIRTUAL_OVERSCAN) : rows.length;
  // The keyboard's active cell is brought into view (a row out of view is not drawn at all).
  useEffect(() => {
    const el = grid.current;
    if (!virtual || !el || !active) return;
    const top = view.head + active.row * rowPx;
    if (top < el.scrollTop + view.head) el.scrollTop = top - view.head;
    else if (top + rowPx > el.scrollTop + el.clientHeight) el.scrollTop = top + rowPx - el.clientHeight;
  }, [virtual, active, rowPx, view.head]);

  // The sheet's visible width, so frozen columns never fill it (see FREEZE_MAX).
  useEffect(() => {
    const el = grid.current;
    if (!el || typeof ResizeObserver === "undefined") return undefined;
    const ro = new ResizeObserver(([entry]) => setGridWidth(Math.round(entry.contentRect.width)));
    ro.observe(el);
    return () => ro.disconnect();
  }, []);
  const canEdit = cfg.editable && (mode === "new" ? meta.can.create : meta.can.update);
  // New rows from the server (after a save, a formula change…) replace the shown ones unless rows are being edited.
  const [seen, setSeen] = useState(data);
  if (data !== seen) {
    setSeen(data);
    if (mode === "list" && editRows.size === 0) setRows(data.rows.map((r) => ({ ...r, key: r.id, dirty: {} })));
  }
  const dirtyCount = rows.filter((r) => Object.keys(r.dirty).length).length;

  // Leaving with unsaved cells asks first (a page that keeps a draft saves it instead, below).
  const drafting = mode === "new" && Boolean(cfg.draft);
  useEffect(() => {
    if (drafting) return undefined;
    const warn = (e) => {
      if (dirtyCount) {
        e.preventDefault();
        e.returnValue = "";
      }
    };
    window.addEventListener("beforeunload", warn);
    return () => window.removeEventListener("beforeunload", warn);
  }, [dirtyCount, drafting]);

  /* ── Draft (Create New, Norban's Draft Requisition) ── */
  const [draftNo, setDraftNo] = useState(cfg.draft?.no ?? null);
  const [draftStatus, setDraftStatus] = useState(() => (cfg.draft?.savedAt ? { kind: "saved", at: cfg.draft.savedAt } : null));
  // The draft's client key (one per draft): made at its first save, so render stays pure.
  const draftKey = useRef(null);
  // What the rows hold (typed cells and artwork), to tell whether anything changed since the last save.
  const draftPayload = useCallback(
    (rs) => rs.map((r) => ({ values: sendable(r.values, formulas), artwork_url: r.values.artwork ? (r.artwork_url ?? null) : null, ...(r.order ? { order: r.order } : {}) })).filter((r) => Object.keys(r.values).length),
    [formulas],
  );
  const lastDrafted = useRef(drafting ? JSON.stringify(draftPayload(rows)) : "");
  const draftState = useRef({});
  useLayoutEffect(() => {
    draftState.current = { rows, draftNo };
  });
  const saveDraft = useCallback(
    async ({ manual = false, closing = false } = {}) => {
      if (!drafting) return;
      const payload = draftPayload(draftState.current.rows);
      const text = JSON.stringify(payload);
      if (!payload.length) {
        if (manual) toast.add({ title: cfg.draft.words.nothing, type: "info" });
        return;
      }
      if (!manual && text === lastDrafted.current) return;
      if (draftKey.current === null) draftKey.current = newDraftKey();
      const body = JSON.stringify({ draft_no: draftState.current.draftNo, client_key: draftKey.current, rows: payload });
      if (!closing) setDraftStatus({ kind: "saving" });
      try {
        const res = await fetch(cfg.draft.url, { method: "POST", headers: { "Content-Type": "application/json" }, body, keepalive: closing });
        if (closing) return;
        const json = await res.json().catch(() => null);
        if (res.ok) {
          lastDrafted.current = text;
          const no = json.data.draft_no;
          setDraftNo(no);
          setDraftStatus({ kind: "saved", at: json.data.saved_at });
          // A reload opens this draft again (its number, not an id, in the address).
          const url = new URL(window.location.href);
          if (url.searchParams.get("draft") !== String(no)) {
            url.searchParams.set("draft", String(no));
            window.history.replaceState(window.history.state, "", url);
          }
          if (manual) toast.add({ title: cfg.draft.words.saved_toast(no), type: "success" });
        } else if (res.status === 409) {
          // Completed or deleted elsewhere: no more saving into it; Save Draft starts a new one.
          setDraftNo(null);
          draftKey.current = null;
          setDraftStatus({ kind: "gone" });
        } else {
          setDraftStatus({ kind: "failed", message: json?.message });
        }
      } catch {
        if (!closing) setDraftStatus({ kind: "failed" });
      }
    },
    [drafting, draftPayload, cfg.draft, toast],
  );
  // The latest saveDraft through a ref: the timer and the leaving-save are set up once for the page — a re-render
  // that makes a new saveDraft (the module's config redrawn after a server action) must not run the cleanup, whose
  // closing save would start a draft the page does not know the number of (it outlived Complete, 2026-11-24).
  const saveDraftRef = useRef(saveDraft);
  useLayoutEffect(() => {
    saveDraftRef.current = saveDraft;
  });
  useEffect(() => {
    if (!drafting) return undefined;
    const id = setInterval(() => saveDraftRef.current(), DRAFT_EVERY);
    const onHide = () => saveDraftRef.current({ closing: true });
    window.addEventListener("pagehide", onHide);
    return () => {
      clearInterval(id);
      window.removeEventListener("pagehide", onHide);
      // Leaving the page inside the app (a sidebar link): what is unsaved goes too.
      onHide();
    };
  }, [drafting]);

  const go = useCallback(
    (mutate) => {
      const p = new URLSearchParams(queryString);
      mutate(p);
      p.delete("page");
      if (cfg.defaultFilter) {
        // No filter left: say so, or the list would open on its default filter again.
        const filtered = [...p.keys()].some((k) => k.startsWith("f[") || k.startsWith("r[") || k === "q" || k === "season");
        filtered ? p.delete("all") : p.set("all", "1");
      }
      router.push(`${pathname}${p.size ? `?${p}` : ""}`, { scroll: false });
    },
    [queryString, pathname, router, cfg.defaultFilter],
  );

  const rowOpen = useCallback((row) => canEdit && (mode === "new" || editRows.has(row.key)), [canEdit, mode, editRows]);
  const cellOpen = useCallback((row, c) => rowOpen(row) && c.access === "edit" && !c.formula && c.type !== "system" && (!cfg.applies || cfg.applies(c, row)) && !lockedCell(cfg, row, c), [rowOpen, cfg]);

  /** A row made from a picked order (Add Requisition): the order's cells filled, on the first empty row or a new one. */
  const addPicked = useCallback(
    ({ values, artwork_url, order }) =>
      setRows((all) => {
        const row = { ...blankRow(), values: formulas.apply({ ...values }, meta.today, true), artwork_url: artwork_url ?? null, order, dirty: Object.fromEntries(Object.keys(values).map((k) => [k, true])) };
        const at = all.findIndex((r) => !Object.values(r.values).some((v) => !blank(v)));
        return at === -1 ? [...all, row] : all.map((r, i) => (i === at ? { ...row, key: r.key } : r));
      }),
    [formulas, meta.today],
  );

  /** Put a typed value in a cell; the row's formulas are worked out again. */
  const setCell = useCallback(
    (rowIndex, column, raw) => {
      setRows((all) =>
        all.map((r, i) => {
          if (i !== rowIndex) return r;
          const value = parse(column, raw);
          const values = formulas.apply({ ...r.values, [column.key]: value }, meta.today, true);
          return { ...r, values, dirty: { ...r.dirty, [column.key]: true }, conflict: false };
        }),
      );
    },
    [formulas, meta.today],
  );

  const move = useCallback(
    (dr, dc) => setActive((a) => (a ? { row: clamp(a.row + dr, 0, rows.length - 1), col: clamp(a.col + dc, 0, shown.length - 1) } : { row: 0, col: 0 })),
    [rows.length, shown.length],
  );

  // The row components get stable handlers that read the latest state through this ref.
  const live = useRef({});
  // Factory changed on a row whose JOB No carries another factory's code: the number stays, a warning says so (user 2026-11-22).
  const jobWarn = (ri, c, raw) => {
    const j = cfg.jobNo;
    if (!j?.factory || c.key !== j.factory) return;
    const g = String(parse(c, raw) ?? "").toUpperCase();
    const nos = [].concat(rows[ri]?.values?.[j.column] ?? []).map(String).filter((v) => {
      const m = JOB_RE.exec(v);
      return g && m && m[2] !== g;
    });
    if (nos.length) toast.add({ title: t("sheet.job_factory_changed", { g, nos: nos.join(", ") }), type: "warning" });
  };
  useLayoutEffect(() => {
    live.current = { rows, shown, cellOpen, setCell, move, active, jobWarn, jobCol: cfg.jobNo?.column ?? null };
  });
  const onCell = useCallback((ri, ci) => {
    const { rows: rs, shown: sh, cellOpen: open, active: was, jobCol } = live.current;
    setActive({ row: ri, col: ci });
    const c = sh[ci];
    const again = was?.row === ri && was?.col === ci;
    // A click on an open cell edits it at once — but a pairs cell (Colors with their quantities) or a list
    // cell (several JOB / PO numbers) opens its dialog only on a second click (or Enter / typing), so the
    // first click leaves Ctrl+C / Ctrl+V to the sheet.
    const dialog = ["pairs", "colorsize"].includes(c?.type) || (c?.type === "multi" && c.multiple !== false); // a one-value list cell edits as text
    const edit = c && open(rs[ri], c) && c.type !== "image" && ((!dialog && c.key !== jobCol) || again);
    setEditing(edit ? true : false);
    if (!edit) grid.current?.focus();
  }, []);
  const onDone = useCallback((ri, c, raw, step) => {
    if (raw && typeof raw === "object" && raw.__companion) {
      // A cell typed with its companion (Last Price Quote Date with who entered it): both values, both sent on save.
      live.current.setCell(ri, c, raw.value);
      live.current.setCell(ri, { key: raw.__companion, type: "text" }, raw.by);
    } else if (raw !== undefined) {
      live.current.setCell(ri, c, raw);
      live.current.jobWarn(ri, c, raw);
    }
    setEditing(false);
    if (step) live.current.move(step[0], step[1]);
    grid.current?.focus();
  }, []);
  const onArtwork = useCallback((ri, id, url) => setRows((all) => all.map((x, i) => (i === ri ? { ...x, values: { ...x.values, artwork: id }, artwork_url: url, dirty: { ...x.dirty, artwork: true } } : x))), []);
  const onTick = useCallback((key, on) => setSelected((s) => { const n = new Set(s); on ? n.add(key) : n.delete(key); return n; }), []);
  const onRemove = useCallback((key) => setRows((all) => all.filter((x) => x.key !== key)), []);
  // Create New's copy button: the row goes on the clipboard (tab-separated, for Excel too) and is kept here
  // with its artwork, so Ctrl+V on another row's cell puts the whole row there — nothing is pasted until then.
  const copiedRow = useRef(null);
  const onDuplicate = useCallback(
    (key) => {
      const { rows: rs, shown: sh } = live.current;
      const src = rs.find((x) => x.key === key);
      if (!src) return;
      const text = sh.map((col) => copyText(col, src.values[col.key]).replace(/[\t\n]+/g, " ")).join("\t");
      // A module's cells a copied row does not take (Pre-Budget: a style's quantity and price stay on its first row).
      const skip = cfg.copySkip ?? [];
      const typed = Object.fromEntries(Object.entries(src.values).filter(([k, v]) => !formulas.isFormula(k) && !blank(v) && !skip.includes(k)));
      const order = src.order ? { ...src.order, locked: (src.order.locked ?? []).filter((k) => !skip.includes(k)) } : null;
      copiedRow.current = { text, typed, artwork_url: src.artwork_url, order };
      navigator.clipboard?.writeText(text).catch(() => {});
      toast.add({ title: t("sheet.row_copied"), type: "info" });
    },
    [formulas, toast, t, cfg.copySkip],
  );

  const onKeyDown = (e) => {
    if (!active || editing || filterFor) return;
    const c = shown[active.col];
    const row = rows[active.row];
    const open = c && row && cellOpen(row, c);
    if (e.key === "ArrowDown") move(1, 0);
    else if (e.key === "ArrowUp") move(-1, 0);
    else if (e.key === "ArrowRight" || (e.key === "Tab" && !e.shiftKey)) move(0, 1);
    else if (e.key === "ArrowLeft" || (e.key === "Tab" && e.shiftKey)) move(0, -1);
    else if ((e.key === "Enter" || e.key === "F2") && open && c.type !== "image") setEditing(true);
    // Typing on an open cell starts editing it with that character, as in Excel.
    else if (e.key.length === 1 && !e.ctrlKey && !e.metaKey && !e.altKey && open && !["image", "date"].includes(c.type)) setEditing({ seed: e.key });
    else if ((e.key === "Delete" || e.key === "Backspace") && open) setCell(active.row, c, null);
    else if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === "d" && open) {
      // Fill Down: the active cell's value into the same column of every open row.
      const value = row.values[c.key] ?? null;
      rows.forEach((r, i) => i !== active.row && cellOpen(r, c) && (mode === "new" || selected.has(r.key)) && setCell(i, c, value));
    } else if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === "c" && c) {
      navigator.clipboard?.writeText(copyText(c, row?.values[c.key])).catch(() => {});
    } else return;
    e.preventDefault();
  };

  /** A paste from Excel: its rows and tab-separated cells, from the active cell across and down (open cells only). */
  const onPaste = (e) => {
    if (!active || editing) return;
    const text = e.clipboardData?.getData("text/plain");
    if (!text) return;
    e.preventDefault();
    // The row the copy button copied: the whole row onto the active one, artwork too, whichever cell is active.
    const copied = copiedRow.current;
    if (copied && text.replace(/\r/g, "").replace(/\n$/, "") === copied.text) {
      const target = rows[active.row];
      if (!target || !rowOpen(target)) return;
      const allowed = Object.fromEntries(Object.entries(copied.typed).filter(([k]) => {
        const col = columns.find((x) => x.key === k);
        return col && col.access === "edit" && !col.formula && col.type !== "system";
      }));
      setRows((all) => all.map((x, i) => (i === active.row ? { ...x, values: formulas.apply({ ...allowed }, meta.today, true), artwork_url: allowed.artwork ? copied.artwork_url : null, order: copied.order, dirty: { ...Object.fromEntries(Object.keys(x.values).map((k) => [k, true])), ...Object.fromEntries(Object.keys(allowed).map((k) => [k, true])) }, conflict: false } : x)));
      return;
    }
    const lines = clipboardGrid(text);
    const extra = mode === "new" ? Math.max(0, active.row + lines.length - rows.length) : 0;
    const all = extra ? [...rows, ...Array.from({ length: extra }, blankRow)] : rows;
    if (extra) setRows(all);
    let skipped = 0;
    const notInList = [];
    lines.forEach((cells, dr) =>
      cells.forEach((cell, dc) => {
        const c = shown[active.col + dc];
        const r = all[active.row + dr];
        if (!c || !r) return;
        if (!cellOpen(r, c) || c.type === "image") {
          skipped++;
          return;
        }
        // A dropdown takes only what its list holds (spelled as the list spells it); anything else is not pasted.
        const listed = fromList(c, cell, meta.sources[c.source]);
        if (listed === undefined) {
          notInList.push(`${c.label}: "${String(cell).trim()}"`);
          return;
        }
        setCell(active.row + dr, c, listed);
      }),
    );
    if (notInList.length) {
      const shownList = [...new Set(notInList)];
      toast.add({ title: t("sheet.paste_not_in_list", { n: notInList.length }), description: shownList.slice(0, 6).join(" · ") + (shownList.length > 6 ? " …" : ""), type: "warning" });
    }
    if (skipped) toast.add({ title: t("sheet.paste_skipped", { n: skipped }), type: "info" });
  };

  const showError = (res) => {
    const first = res?.errors ? Object.entries(res.errors)[0] : null;
    toast.add({ title: first ? first[1][0] : (res?.message ?? t("sheet.error")), type: "danger" });
  };

  const save = (saveMode = null) =>
    start(async () => {
      const changed = rows.filter((r) => Object.keys(r.dirty).length);
      if (!changed.length) return;
      if (mode === "new") {
        if (saveMode?.draft) return saveDraft({ manual: true });
        const res = await act("store", changed.map((r) => ({ values: sendable(r.values, formulas), ...(r.order ? { order_id: r.order.id } : {}) })), saveMode?.status, drafting ? draftNo : undefined, drafting ? draftKey.current : undefined);
        if (res?.status === "success") {
          // Saved: the draft is gone, nothing more to keep as one.
          if (drafting) lastDrafted.current = JSON.stringify(draftPayload(draftState.current.rows));
          toast.add({ title: saveMode?.done ? saveMode.done(res.data.rows.length) : t("sheet.created", { n: res.data.rows.length }), type: "success" });
          router.push(saveMode?.redirect ?? cfg.listPath);
        } else showError(res);
        return;
      }
      const res = await act("update", changed.map((r) => ({ id: r.id, version: r.version, values: Object.fromEntries(Object.keys(r.dirty).map((k) => [k, r.values[k] ?? null])) })));
      if (res?.status !== "success") return showError(res);
      const saved = new Map(res.data.rows.map((r) => [r.id, r]));
      const conflicts = new Map(res.data.conflicts.map((r) => [r.id, r]));
      // A row someone else saved meanwhile: their saved row, with this user's own changed cells put back on top.
      const rebase = (fresh, mine) => {
        const own = Object.fromEntries(Object.keys(mine.dirty).map((k) => [k, mine.values[k] ?? null]));
        return { ...fresh, key: mine.key, values: formulas.apply({ ...fresh.values, ...own }, meta.today, true), artwork_url: mine.dirty.artwork ? mine.artwork_url : fresh.artwork_url, dirty: mine.dirty, conflict: true };
      };
      setRows((all) => all.map((r) => (saved.has(r.id) ? { ...saved.get(r.id), key: r.id, dirty: {} } : conflicts.has(r.id) ? rebase(conflicts.get(r.id), r) : r)));
      setActive(null);
      // Saved rows close; a row that came back someone else's stays open to be done again.
      setEditRows(new Set([...conflicts.keys()]));
      setEditing(false);
      toast.add({ title: t("sheet.saved", { n: saved.size }), type: "success" });
      // What the module adds (Export Statement: edits sent for approval, the entries keeping their values).
      if (res.data.notice) toast.add({ title: res.data.notice, type: "info" });
      if (conflicts.size) toast.add({ title: t("sheet.conflicts", { n: conflicts.size }), type: "warning" });
    });

  const ticked = rows.filter((r) => r.id && selected.has(r.key)).map((r) => r.id);
  // Print: the same filters, sort, ticked rows and shown columns, on the print page (opens in a new tab).
  const printHref = () => {
    const q = new URLSearchParams(queryString);
    q.delete("page");
    q.delete("per_page");
    q.set("cols", shown.map((c) => c.key).join(","));
    if (ticked.length) q.set("selected", ticked.join(","));
    return `${cfg.printUrl}?${q.toString()}`;
  };
  const exportExcel = async () => {
    setExporting(true);
    try {
      const q = new URLSearchParams(queryString);
      q.delete("page");
      q.delete("per_page");
      ticked.forEach((id) => q.append("ids[]", id));
      // Only the columns shown (Column View).
      q.set("cols", shown.map((c) => c.key).join(","));
      const res = await fetch(`${cfg.excelUrl}?${q.toString()}`);
      if (!res.ok) throw new Error((await res.json().catch(() => null))?.message ?? res.statusText);
      const name = /filename="?([^";]+)"?/.exec(res.headers.get("Content-Disposition") ?? "")?.[1] ?? "Order-Summary.xlsx";
      const url = URL.createObjectURL(await res.blob());
      const a = document.createElement("a");
      a.href = url;
      a.download = name;
      a.click();
      setTimeout(() => URL.revokeObjectURL(url), 10000);
    } catch (e) {
      toast.add({ title: t("sheet.export_failed"), description: String(e?.message ?? e), type: "danger" });
    } finally {
      setExporting(false);
    }
  };
  const startEdit = () => {
    setEditRows(new Set(rows.filter((r) => selected.has(r.key)).map((r) => r.key)));
    setActive(null);
  };
  const cancelEdit = () => {
    setRows((all) => all.map((r) => (Object.keys(r.dirty).length ? { ...(data.rows.find((x) => x.id === r.id) ?? r), key: r.key, dirty: {} } : r)));
    setEditRows(new Set());
    setEditing(false);
  };
  const copyRows = () =>
    start(async () => {
      const res = await act("copy", ticked);
      if (res?.status === "success") {
        toast.add({ title: t("sheet.copied", { n: res.data.rows.length }), type: "success" });
        router.refresh();
      } else showError(res);
    });
  /** One of the module's own buttons for ticked rows (Complete for drafts …). */
  const runListAction = (a) =>
    start(async () => {
      const res = await act(a.action, ticked);
      if (res?.status === "success") {
        toast.add({ title: a.done ? a.done(res.data) : t("sheet.saved", { n: ticked.length }), type: "success" });
        setSelected(new Set());
        if (a.redirect) router.push(a.redirect(res.data));
        else router.refresh();
      } else showError(res);
    });
  const remove = () =>
    start(async () => {
      const res = await act("remove", ticked);
      if (res?.status === "success") {
        toast.add({ title: t("sheet.deleted", { n: res.data.deleted }), type: "success" });
        setDeleting(false);
        router.refresh();
      } else showError(res);
    });

  const groups = useMemo(() => {
    const out = [];
    for (const c of shown) {
      const last = out[out.length - 1];
      const left = freeze.left[c.key];
      // A group splits where the frozen columns end, so its frozen part can stay put.
      if (last && last.id === c.group_id && (last.left !== undefined) === (left !== undefined)) last.span++;
      else out.push({ id: c.group_id, span: 1, left, group: meta.groups.find((g) => g.id === c.group_id) });
    }
    return out;
  }, [shown, meta.groups, freeze]);

  // A column header takes its group's colour, as in the factory's Excel (Delay cyan, sample / planning pink).
  const toneByGroup = useMemo(() => new Map(meta.groups.map((g) => [g.id, g.tone])), [meta.groups]);
  const headTone = (c) => GROUP_TONE[toneByGroup.get(c.group_id)] ?? "bg-[#d9d9d9]";
  const totals = mode === "list" && data?.totals && shown.some((c) => data.totals[c.key] !== undefined) ? data.totals : null;

  // Day-dependent columns (Days Left…) filter too: the API works their values out for today.
  const canFilter = (c) => mode === "list" && c.type !== "image";
  const filtered = (key) => [...params.keys()].some((k) => k.startsWith(`f[${key}]`) || k.startsWith(`r[${key}]`));
  const editingRows = mode === "list" ? editRows.size : 0;

  return (
    <SheetConfig.Provider value={cfg}>
      <JobNoCache.Provider value={jobCache}>
    <div className="flex flex-col gap-3 text-[12px]">
      <section className="rounded-lg border border-border bg-surface">
        <div className="flex flex-wrap items-center gap-2 border-b border-border px-3 py-2">
          {mode === "new" ? (
            <>
              {cfg.saveModes ? (
                cfg.saveModes.map((m, i) => (
                  <Button key={m.status} type="button" size="sm" variant={i === cfg.saveModes.length - 1 ? "primary" : "outline"} onClick={() => save(m)} disabled={pending || dirtyCount === 0}>
                    {m.icon === "check" ? <CheckCircle2 /> : <Save />} {m.label}
                    {dirtyCount ? ` (${dirtyCount})` : ""}
                  </Button>
                ))
              ) : (
                <Button type="button" size="sm" onClick={() => save()} disabled={pending || dirtyCount === 0}>
                  <Save /> {dirtyCount ? t("sheet.save_n", { n: dirtyCount }) : t("sheet.save")}
                </Button>
              )}
              <Button type="button" size="sm" variant="outline" onClick={() => setRows((all) => [...all, blankRow()])}>
                <Plus /> {t("sheet.add_row")}
              </Button>
              {/* The module's own picker (Pre-Budget: an order from Order Summary fills a row). */}
              {cfg.newToolbar ? cfg.newToolbar({ addPicked, rows }) : null}
              <Link href={cfg.listPath} className={buttonVariants({ variant: "outline", size: "sm" })}>
                <ArrowLeft /> {t("sheet.back")}
              </Link>
              {drafting && draftStatus ? (
                <span className={cn("ml-1 rounded px-2 py-0.5 text-[11px] font-semibold", draftStatus.kind === "saved" ? "bg-success-subtle text-success" : draftStatus.kind === "saving" ? "bg-surface-muted text-foreground-muted" : "bg-danger-subtle text-danger")}>
                  {draftStatus.kind === "saved"
                    ? cfg.draft.words.saved(draftNo, new Date(draftStatus.at).toLocaleTimeString("en-GB", { hour: "2-digit", minute: "2-digit", timeZone: "Asia/Dhaka", hourCycle: "h12" }).replace(/\b[ap]m\b/, (x) => x.toUpperCase()))
                    : draftStatus.kind === "saving"
                      ? cfg.draft.words.saving
                      : draftStatus.kind === "gone"
                        ? cfg.draft.words.gone
                        : (draftStatus.message ?? cfg.draft.words.failed)}
                </span>
              ) : null}
              <span className="ml-1 text-foreground-muted">{drafting ? cfg.draft.words.hint : t("sheet.hint_new")}</span>
            </>
          ) : editingRows ? (
            <>
              <Button type="button" size="sm" onClick={() => save()} disabled={pending || dirtyCount === 0}>
                <Save /> {dirtyCount ? t("sheet.save_n", { n: dirtyCount }) : t("sheet.save")}
              </Button>
              <Button type="button" size="sm" variant="outline" onClick={cancelEdit} disabled={pending}>
                <X /> {t("sheet.cancel_edit")}
              </Button>
              <span className="ml-1 rounded bg-warning-subtle px-2 py-0.5 font-semibold text-warning">{t("sheet.editing_n", { n: editingRows })}</span>
              <span className="text-foreground-muted">{t("sheet.hint_edit")}</span>
            </>
          ) : (
            <>
              {meta.can.update && cfg.editable ? (
                <Button type="button" size="sm" disabled={ticked.length === 0} onClick={startEdit}>
                  <PencilLine /> {ticked.length ? t("sheet.bulk_edit_n", { n: ticked.length }) : t("sheet.bulk_edit")}
                </Button>
              ) : null}
              {(cfg.listActions ?? []).map((a) => (
                <Button key={a.action} type="button" size="sm" disabled={ticked.length === 0 || pending} onClick={() => runListAction(a)}>
                  <CheckCircle2 /> {a.label}
                </Button>
              ))}
              {meta.can.create && cfg.copy !== false ? (
                <Button type="button" size="sm" variant="outline" disabled={ticked.length === 0 || pending} onClick={copyRows}>
                  <Copy /> {t("sheet.copy_row")}
                </Button>
              ) : null}
              {meta.can.delete ? (
                <Button type="button" size="sm" variant="outline" disabled={ticked.length === 0} onClick={() => setDeleting(true)}>
                  <Trash2 /> {t("sheet.delete")}
                </Button>
              ) : null}
              <span className="ml-1 text-foreground-muted">{ticked.length ? t("sheet.ticked", { n: ticked.length }) : t("sheet.hint")}</span>
            </>
          )}
          <div className="ml-auto flex items-center gap-2">
            {mode === "list" && cfg.toolbarExtra ? cfg.toolbarExtra : null}
            {mode === "list" && cfg.excelUrl ? (
              <Button type="button" size="sm" variant="outline" disabled={exporting} onClick={exportExcel} title={t("sheet.export_excel_hint")}>
                {exporting ? <Loader2 className="animate-spin" /> : <FileSpreadsheet />} {exporting ? t("sheet.exporting") : t("sheet.export_excel")}
              </Button>
            ) : null}
            {mode === "list" && cfg.printUrl ? (
              <a href={printHref()} target="_blank" rel="noopener" className={buttonVariants({ size: "sm", variant: "outline" })} title={t("sheet.print_hint")}>
                <Printer /> {t("sheet.print")}
              </a>
            ) : null}
            {mode === "list" && cfg.print ? (
              <a href={cfg.print.href(queryString, shown.map((c) => c.key), ticked)} target="_blank" rel="noopener" className={buttonVariants({ size: "sm", variant: "outline" })} title={cfg.print.title}>
                <Printer /> {cfg.print.label}
              </a>
            ) : null}
            <ColumnView
              columns={columns.map((c) => ({ key: c.key, label: c.label, group: meta.groups.find((g) => g.id === c.group_id)?.name }))}
              visible={visible}
              defaults={defaults}
              onChange={setVisible}
              labels={{ button: t("sheet.columns"), all: t("sheet.all"), none: t("sheet.none"), apply: t("sheet.apply"), reset: t("sheet.reset"), hint: t("sheet.columns_hint") }}
            />
          </div>
        </div>

        <div ref={grid} tabIndex={0} onKeyDown={onKeyDown} onPaste={onPaste} style={cfg.fillHeight?.[mode] ? { height: cfg.maxHeight ?? "65vh" } : { maxHeight: cfg.maxHeight ?? "65vh" }} className="overflow-auto overscroll-contain outline-none" aria-label={t("screens.list")}>
          <table className="table-fixed border-separate border-spacing-0 text-[11px] leading-tight" style={{ width: tableWidth }}>
            <colgroup>
              <col style={{ width: 32 }} />
              {shown.map((c) => (
                <col key={c.key} style={{ width: widthOf(c) }} />
              ))}
            </colgroup>
            <thead className="sticky top-0 z-20">
              <tr>
                <th rowSpan={totals ? 3 : 2} className="sticky left-0 z-30 border border-[#bfbfbf] bg-[#d9d9d9] px-1">
                  {mode === "list" ? (
                    <Checkbox checked={rows.length > 0 && rows.every((r) => selected.has(r.key))} onCheckedChange={(on) => setSelected(on ? new Set(rows.map((r) => r.key)) : new Set())} aria-label={t("sheet.tick_all")} />
                  ) : null}
                </th>
                {groups.map((g, i) => (
                  <th key={`${g.id}-${i}`} colSpan={g.span} style={frozenStyle(g.left, 20)} className={cn("h-7 truncate border-t border-r border-b border-[#bfbfbf] px-2 text-center text-[11px] font-bold", GROUP_TONE[g.group?.tone] ?? "bg-[#c9d7ee]")}>
                    {g.group?.name}
                  </th>
                ))}
              </tr>
              {totals ? (
                // Excel's SUBTOTAL row: each total column over every filtered row, not just this page.
                <tr>
                  {shown.map((c) => {
                    const has = totals[c.key] !== undefined;
                    return (
                      <th key={c.key} style={frozenStyle(freeze.left[c.key], 20)} title={has ? t("sheet.totals_hint", { n: data.page.total }) : undefined}
                        className={cn("h-6 overflow-hidden border-r border-b border-[#bfbfbf] px-1 text-center text-[11px] font-bold whitespace-nowrap tabular-nums", has ? "bg-[#262626] text-white" : "bg-[#f2f2f2]", freeze.last === c.key && "border-r-[#6b7280]")}>
                        {has ? display(c, totals[c.key]) : c.key === "serial_no" ? <span className="text-[#6b7280]">{t("sheet.totals")}</span> : null}
                      </th>
                    );
                  })}
                </tr>
              ) : null}
              <tr>
                {shown.map((c) => (
                  <th key={c.key} style={frozenStyle(freeze.left[c.key], 20)} className={cn("relative h-16 border-r border-b border-[#bfbfbf] align-middle font-bold text-[#111827] uppercase", headTone(c), freeze.last === c.key && "border-r-[#6b7280]", c.vertical ? "px-0 py-1" : "px-1.5 py-1", canFilter(c) && (c.vertical ? "pb-4" : "pb-2"), c.key === "last_update" && "bg-[#c04f15] text-white")}>
                    <span className={cn("block w-full leading-tight text-center", c.vertical ? "text-[8px] tracking-tight [overflow-wrap:normal]" : "px-2")} title={c.label}>
                      {c.label}
                    </span>
                    {/* The filter sits in the cell's bottom-right corner, as in Excel. */}
                    {canFilter(c) ? (
                      <button type="button" onClick={() => setFilterFor(c)} aria-label={t("sheet.filter_by", { column: c.label })} className={cn("absolute right-0 bottom-0 rounded p-0.5 hover:bg-white/60", filtered(c.key) ? "text-brand" : c.key === "last_update" ? "text-white" : "text-[#6b7280]")}>
                        <Filter className="size-3" />
                      </button>
                    ) : null}
                  </th>
                ))}
              </tr>
            </thead>
            <tbody>
              {rows.length === 0 ? (
                <tr>
                  <td colSpan={shown.length + 1} className="px-4 py-10 text-center text-foreground-muted">
                    {t("sheet.empty")}
                  </td>
                </tr>
              ) : (
                <>
                {virtual && firstRow > 0 ? <tr aria-hidden="true" style={{ height: firstRow * rowPx }}><td colSpan={shown.length + 1} /></tr> : null}
                {rows.slice(firstRow, lastRow).map((r, i) => {
                  const ri = firstRow + i;
                  return (
                  <Row
                    key={r.key}
                    row={r}
                    ri={ri}
                    mode={mode}
                    shown={shown}
                    sources={meta.sources}
                    open={rowOpen(r)}
                    canEdit={canEdit}
                    ticked={selected.has(r.key)}
                    activeCol={active?.row === ri ? active.col : null}
                    editing={active?.row === ri ? editing : false}
                    onCell={onCell}
                    onDone={onDone}
                    onArtwork={onArtwork}
                    onTick={onTick}
                    onRemove={onRemove}
                    onDuplicate={onDuplicate}
                    freeze={freeze}
                  />
                  );
                })}
                {virtual && lastRow < rows.length ? <tr aria-hidden="true" style={{ height: (rows.length - lastRow) * rowPx }}><td colSpan={shown.length + 1} /></tr> : null}
                </>
              )}
            </tbody>
          </table>
        </div>

        {mode === "list" ? (
          <div className="flex flex-wrap items-center gap-3 border-t border-border px-3 py-2">
            <span className="text-foreground-muted">{t("sheet.found", { n: data.page.total, m: data.page.all_total })}
              {data.page.styles != null ? ` · ${t("sheet.styles_found", { n: data.page.styles })}` : ""}</span>
            {[...params.keys()].some((k) => k.startsWith("f[") || k.startsWith("r[") || k === "q") ? (
              <button type="button" className="font-semibold text-brand" onClick={() => go((p) => [...p.keys()].filter((k) => k.startsWith("f[") || k.startsWith("r[") || k === "q").forEach((k) => p.delete(k)))}>
                {t("sheet.clear_filters")}
              </button>
            ) : null}
            <div className="ml-auto flex items-center gap-2">
              <label className="flex items-center gap-1.5 text-foreground-muted">
                {t("sheet.per_page")}
                <select className="h-7 rounded border border-border bg-surface px-1" value={params.get("per_page") ?? "50"} onChange={(e) => go((p) => p.set("per_page", e.target.value))}>
                  {PER_PAGE.map((n) => (
                    <option key={n} value={n}>
                      {n === "all" ? t("sheet.all") : n}
                    </option>
                  ))}
                </select>
              </label>
              {data.page.last > 1 ? (
                <div className="flex gap-1">
                  {Array.from({ length: data.page.last }, (_, i) => i + 1)
                    .filter((n) => n === 1 || n === data.page.last || Math.abs(n - data.page.current) <= 2)
                    .map((n) => (
                      <Button key={n} type="button" size="sm" variant={n === data.page.current ? "primary" : "outline"} onClick={() => {
                        const p = new URLSearchParams(queryString);
                        p.set("page", String(n));
                        router.push(`${pathname}?${p}`, { scroll: false });
                      }}>
                        {n}
                      </Button>
                    ))}
                </div>
              ) : null}
            </div>
          </div>
        ) : null}
      </section>

      {filterFor ? <HeaderFilter column={filterFor} queryString={queryString} act={act} t={t} onClose={() => setFilterFor(null)} go={go} /> : null}
      <ConfirmDialog open={deleting} onOpenChange={setDeleting} title={t("sheet.delete_title", { n: ticked.length })} description={t("sheet.delete_text")} confirmLabel={t("sheet.delete")} cancelLabel={t("sheet.cancel")} destructive loading={pending} onConfirm={remove} />
    </div>
      </JobNoCache.Provider>
    </SheetConfig.Provider>
  );
}

/** One sheet row; redrawn only when its own data, its active cell or its edit state change. */
const Row = memo(function Row({ row, ri, mode, shown, sources, open, canEdit, ticked, activeCol, editing, onCell, onDone, onArtwork, onTick, onRemove, onDuplicate, freeze }) {
  const t = useT("ordersummary");
  const cfg = useContext(SheetConfig);
  const { applies, rowHeight: fixedHeight, companions, jobNo } = cfg;
  // A fixed row height: one number for every screen, or { list, new } per screen.
  const rowHeight = fixedHeight && typeof fixedHeight === "object" ? (fixedHeight[mode] ?? null) : fixedHeight;
  return (
    <tr data-row style={rowHeight ? { height: rowHeight } : undefined} className={cn(row.conflict && "outline outline-2 -outline-offset-2 outline-warning", open && mode === "list" && "bg-[#fffbeb]")}>
      <td className={cn("sticky left-0 z-10 border-r border-b border-l border-[#d4d4d4] px-1 text-center", open && mode === "list" ? "bg-[#fef3c7]" : "bg-surface")}>
        {mode === "list" ? (
          <Checkbox checked={ticked} onCheckedChange={(on) => onTick(row.key, on)} aria-label={t("sheet.tick", { no: row.serial_no })} />
        ) : (
          <span className="flex flex-col items-center gap-1.5">
            <button type="button" onClick={() => onDuplicate(row.key)} aria-label={t("sheet.copy_to_empty")} title={t("sheet.copy_to_empty")} className="text-foreground-subtle hover:text-brand">
              <CopyPlus className="size-3.5" />
            </button>
            <button type="button" onClick={() => onRemove(row.key)} aria-label={t("sheet.remove_row")} title={t("sheet.remove_row")} className="text-foreground-subtle hover:text-danger">
              <X className="size-3.5" />
            </button>
            {row.order && cfg.orderMark ? <span className="text-brand" title={cfg.orderMark(row.order)}><Link2 className="size-3.5" /></span> : null}
          </span>
        )}
      </td>
      {shown.map((c, ci) => {
        const isActive = activeCol === ci;
        const has = !applies || applies(c, row);
        const value = has ? row.values[c.key] : undefined;
        const tone = has ? toneOf(c, value) : null;
        const locked = lockedCell(cfg, row, c);
        const editableCell = has && open && c.access === "edit" && !c.formula && c.type !== "system" && !locked;
        return (
          <td
            key={c.key}
            onClick={() => onCell(ri, ci)}
            style={frozenStyle(freeze.left[c.key], 5)}
            title={locked && open ? cfg.lockedTitle?.(c, row) : undefined}
            className={cn(
              "relative overflow-hidden border-r border-b border-[#d4d4d4] align-middle",
              freeze.last === c.key && "border-r-[#6b7280]",
              c.vertical ? "px-0 py-1" : c.type === "image" ? "p-0.5" : "px-1.5 py-1",
              tone ? TONE[tone] : editableCell ? "cursor-text bg-white hover:shadow-[inset_0_0_0_1px_#6366f1]" : "bg-surface",
              "text-center",
              NUMERIC.includes(c.type) && "tabular-nums whitespace-nowrap",
              row.dirty[c.key] && "after:absolute after:top-0 after:right-0 after:border-t-[6px] after:border-l-[6px] after:border-t-brand after:border-l-transparent",
              isActive && "outline outline-2 -outline-offset-2 outline-brand",
              c.formula && !tone && !editableCell && "bg-[#f2f2f2]",
              locked && open && !tone && "bg-[#eef4ff]",
              !has && "bg-[repeating-linear-gradient(135deg,#f3f4f6,#f3f4f6_4px,#e5e7eb_4px,#e5e7eb_5px)]",
              !tone && c.text_tone && TEXT_TONE[c.text_tone],
              c.bold && "font-bold",
            )}
          >
            {!has ? null : isActive && editing ? (
              <CellEditor column={c} value={value} seed={typeof editing === "object" ? editing.seed : undefined} options={sources[c.source] ?? []} sizes={sources.sizes ?? []} job={jobNo && c.key === jobNo.column ? { url: jobNo.url, factory: jobNo.factory ? (row.values[jobNo.factory] ?? null) : null, pick: !jobNo.factory, single: c.type !== "multi" || c.multiple === false } : null} companion={companions?.[c.key] ?? null} by={companions?.[c.key] ? row.values[companions[c.key]] : null} onDone={(raw, step) => onDone(ri, c, raw, step)} />
            ) : rowHeight ? (
              // The row keeps its height: what does not fit is cut, the full text is the tooltip.
              <div style={{ maxHeight: rowHeight - 8 }} className="overflow-hidden" title={c.type === "image" ? undefined : display(c, value) || undefined}>
                <Cell column={c} value={value} row={row} by={companions?.[c.key] ? row.values[companions[c.key]] : null} maxHeight={rowHeight ? rowHeight - 10 : null} editable={editableCell && canEdit} onArtwork={(id, url) => onArtwork(ri, id, url)} />
              </div>
            ) : (
              <Cell column={c} value={value} row={row} by={companions?.[c.key] ? row.values[companions[c.key]] : null} maxHeight={rowHeight ? rowHeight - 10 : null} editable={editableCell && canEdit} onArtwork={(id, url) => onArtwork(ri, id, url)} />
            )}
          </td>
        );
      })}
    </tr>
  );
});

const clamp = (n, lo, hi) => Math.max(lo, Math.min(hi, n));
/** A cell a picked order fills (Pre-Budget's requisition made from an Order Summary row): changed at the order, not here. */
// Only the cells the order has a value in: one the order leaves empty is typed on the requisition.
const lockedCell = (cfg, row, c) => Boolean(cfg.lockedKeys?.includes(c.key) && (row.order?.locked ?? row.order_locked ?? []).includes(c.key));
/** A frozen cell's sticky place (an inline style wins over the cell's `relative`). */
const frozenStyle = (left, z) => (left === undefined ? undefined : { position: "sticky", left, zIndex: z });

/** A typed value as the row keeps it. */
function parse(column, raw) {
  if (raw === null || raw === undefined) return null;
  if (Array.isArray(raw)) return raw.length ? raw : null;
  const s = String(raw).trim();
  if (s === "") return null;
  if (["number", "money", "percent", "days"].includes(column.type)) {
    const n = num(s.replace(/[$%]|\s*(tkt|pcs|pac|kg|days?)$/i, ""));
    return n === null ? s : n;
  }
  if (column.type === "multi") return column.multiple === false ? [s] : s.split(/\s*[,\n]\s*/).filter(Boolean);
  if (column.type === "pairs") return parsePairs(s);
  if (column.type === "colorsize") return parseColorSizes(s);
  if (column.type === "date") return excelDate(s) ?? s;
  return s;
}

/** "Navy (S, M, L); White" (as copied out, or typed) → [{color: Navy, sizes: [S, M, L]}, {color: White, sizes: []}]. */
function parseColorSizes(s) {
  const lines = String(s)
    .split(/\s*(?:[;\n]|,(?![^(]*\)))\s*/)
    .map((p) => p.trim())
    .filter(Boolean)
    .map((p) => {
      const m = /^(.*?)\s*\((.*)\)\s*$/.exec(p);
      return m ? { color: m[1].trim(), sizes: m[2].split(/\s*,\s*/).filter(Boolean) } : { color: p, sizes: [] };
    });
  return lines.length ? lines : null;
}

/**
 * Excel's clipboard as rows of cells: tab between cells, a new line between rows — and a
 * cell holding new lines of its own comes quoted ("Navy: 600 Pcs↵White: 400 Pcs", "" for a
 * quote inside), so it stays one cell instead of breaking into rows.
 */
function clipboardGrid(text) {
  const s = text.replace(/\r\n?/g, "\n").replace(/\n$/, "");
  const rows = [[]];
  let cell = "";
  let i = 0;
  while (i < s.length) {
    const ch = s[i];
    if (cell === "" && ch === '"' && s.indexOf('"', i + 1) !== -1) {
      // A quoted cell: up to the closing quote that is followed by a tab, a new line or the end.
      let j = i + 1;
      let out = "";
      let closed = false;
      while (j < s.length) {
        if (s[j] === '"') {
          if (s[j + 1] === '"') {
            out += '"';
            j += 2;
            continue;
          }
          if (j + 1 === s.length || s[j + 1] === "\t" || s[j + 1] === "\n") {
            closed = true;
            break;
          }
        }
        out += s[j];
        j++;
      }
      if (closed) {
        cell = out;
        i = j + 1;
        continue;
      }
    }
    if (ch === "\t") {
      rows[rows.length - 1].push(cell);
      cell = "";
    } else if (ch === "\n") {
      rows[rows.length - 1].push(cell);
      rows.push([]);
      cell = "";
    } else cell += ch;
    i++;
  }
  rows[rows.length - 1].push(cell);
  return rows;
}

/**
 * Pasted / typed text as pairs lines: "Navy: 60, White 40" → [{Navy, 60}, {White, 40}],
 * "BIO $2.48, $3.10" → [{BIO, 2.48}, {"", 3.1}] (a thousands comma stays in its number).
 */
function parsePairs(s) {
  const lines = s
    .split(/[\n;]|,(?!\d{3}\b)/)
    .map((p) => p.trim())
    .filter(Boolean)
    .map((p) => {
      const m = /^(.*?)[\s:=–-]*\$?\s*(-?[\d,]*\.?\d+)\s*[A-Za-z]*$/.exec(p);
      return m ? { label: m[1].replace(/[\s:=–-]+$/, ""), value: num(m[2].replace(/,/g, "")) } : { label: p, value: null };
    });
  return lines.length ? lines : null;
}

/** 26-Nov-26, 26/11/2026, 2026-11-26 → 2026-11-26 */
function excelDate(s) {
  if (/^\d{4}-\d{2}-\d{2}/.test(s)) return s.slice(0, 10);
  const months = { jan: 1, feb: 2, mar: 3, apr: 4, may: 5, jun: 6, jul: 7, aug: 8, sep: 9, oct: 10, nov: 11, dec: 12 };
  let m = /^(\d{1,2})[-/ ]([A-Za-z]{3})[-/ ](\d{2,4})$/.exec(s);
  if (m && months[m[2].toLowerCase()]) return iso(m[3], months[m[2].toLowerCase()], m[1]);
  m = /^(\d{1,2})[/.-](\d{1,2})[/.-](\d{2,4})$/.exec(s);
  if (m) return iso(m[3], m[2], m[1]);
  return null;
}

function iso(y, m, d) {
  const year = String(y).length === 2 ? `20${y}` : String(y);
  return `${year}-${String(m).padStart(2, "0")}-${String(d).padStart(2, "0")}`;
}

/**
 * A pasted value for a list column, as the list spells it ("c&a" → "C&A"):
 * undefined when the list does not hold it (a multi column: when any part is
 * missing). A blank clears; a column without a list takes anything.
 */
function fromList(column, raw, options) {
  if (!["dropdown", "multi"].includes(column.type) || !column.source) return raw;
  const text = String(raw ?? "").trim();
  if (text === "") return raw;
  const byLower = new Map((options ?? []).map((o) => [String(o.value).toLowerCase(), o.value]));
  if (column.type === "dropdown") return byLower.get(text.toLowerCase());
  const parts = text.split(/\s*[,\n]\s*/).filter(Boolean).map((p) => byLower.get(p.toLowerCase()));
  return parts.some((p) => p === undefined) ? undefined : parts;
}

/** A draft's client key: tells the server a repeated save is the same draft. */
function newDraftKey() {
  return `${Date.now().toString(36)}-${Math.random().toString(36).slice(2, 10)}`;
}

/** What goes to the server for a new row: the typed cells, not the worked-out ones. */
function sendable(values, formulas) {
  return Object.fromEntries(Object.entries(values).filter(([k, v]) => !formulas.isFormula(k) && !blank(v)));
}

function Cell({ column, value, row, by, maxHeight, editable, onArtwork }) {
  if (column.type === "image") {
    return <ArtworkCell row={row} editable={editable} onArtwork={onArtwork} />;
  }
  const text = display(column, value);
  if (by && text) {
    // Two lines, as Norban's list: the value, and under it who set it.
    return (
      <span className="flex flex-col items-center gap-0.5 leading-tight">
        <span className="whitespace-nowrap">{text}</span>
        <span className="max-w-full truncate rounded bg-[#dbeafe] px-1.5 py-px text-[10px] font-bold text-[#1e40af]">{by}</span>
      </span>
    );
  }
  if (column.vertical && text) {
    // Centred by the flex box: in vertical writing mode the text's own auto margins don't centre it across the column.
    // The text wraps: a line runs up the cell to its height, then the next line starts beside it.
    return (
      <span className="flex justify-center">
        <span style={{ maxHeight: maxHeight ?? 80 }} className="overflow-hidden text-[10px] leading-[11px] break-words [writing-mode:vertical-rl] rotate-180">{text}</span>
      </span>
    );
  }
  if (column.type === "pairs" && Array.isArray(value)) {
    // One line each: the name, then its number (Navy  60 Pcs / BIO  $2.48).
    return (
      <span className="flex flex-col">
        {value.map((l, i) => (
          // A line without a name (a single price) shows its number alone, centred.
          <span key={i} className={cn("flex gap-2 whitespace-nowrap", l?.label ? "justify-between" : "justify-center")}>
            {l?.label ? <span className="truncate">{l.label}</span> : null}
            <span className="tabular-nums">{pairNumber(column, l?.value)}</span>
          </span>
        ))}
      </span>
    );
  }
  if (column.type === "colorsize" && Array.isArray(value)) {
    // One line each: the colour, then its sizes (Navy  S, M, L).
    return (
      <span className="flex flex-col">
        {value.map((l, i) => (
          <span key={i} className="flex justify-between gap-2 whitespace-nowrap">
            <span className="truncate font-medium">{l?.color}</span>
            {l?.sizes?.length ? <span className="text-foreground-muted">{l.sizes.join(", ")}</span> : null}
          </span>
        ))}
      </span>
    );
  }
  if (column.type === "multi" && Array.isArray(value)) {
    return (
      <span className="flex flex-col whitespace-nowrap">
        {value.map((v) => (
          <span key={v}>{v}</span>
        ))}
      </span>
    );
  }
  return <span className={cn("block break-words", column.type === "longtext" ? "whitespace-pre-line" : NUMERIC.includes(column.type) || column.type === "date" ? "whitespace-nowrap" : "")}>{text}</span>;
}

/** The artwork thumbnail; a click shows it large in a modal. */
function ArtworkCell({ row, editable, onArtwork }) {
  const t = useT("ordersummary");
  const [open, setOpen] = useState(false);
  const [loaded, setLoaded] = useState(false);
  const { titleKeys, rowHeight: fixedHeight } = useContext(SheetConfig);
  const rowHeight = fixedHeight && typeof fixedHeight === "object" ? Math.min(...Object.values(fixedHeight)) : fixedHeight;
  const url = row.artwork_url;
  const title = titleKeys.map((k) => row.values[k]).flat().filter(Boolean).join(" · ") || t("artwork");
  return (
    <span className="flex items-center justify-center gap-0.5">
      {url ? (
        // The picture fills the cell's width (Column Setup sets it), up to a sensible height.
        <button type="button" onClick={(e) => { e.stopPropagation(); setLoaded(false); setOpen(true); }} aria-label={t("sheet.view_artwork")} className="block min-w-0 flex-1 rounded hover:ring-2 hover:ring-brand">
          {/* The list's own thumbnail when it came with the page (shown at once); else the picture itself. */}
          {/* eslint-disable-next-line @next/next/no-img-element -- a stored upload behind the session */}
          <img src={row.artwork_thumb ?? url} alt="" decoding="async" style={rowHeight ? { maxHeight: rowHeight - 10 } : undefined} className="mx-auto block h-auto max-h-24 w-full rounded object-contain" />
        </button>
      ) : null}
      {editable ? <ArtworkUpload onUploaded={onArtwork} label={t("sheet.upload_artwork")} /> : null}
      {open ? (
        <Modal open onOpenChange={setOpen} title={title} className="max-w-3xl">
          <div onClick={(e) => e.stopPropagation()} className="flex flex-col items-center gap-3">
            <div className="relative flex min-h-60 w-full items-center justify-center rounded border border-border bg-surface-muted">
              {/* eslint-disable-next-line @next/next/no-img-element -- a stored upload behind the session */}
              <img src={url} alt={title} onLoad={() => setLoaded(true)} className={cn("max-h-[70vh] max-w-full object-contain", !loaded && "invisible")} />
              {!loaded ? <Loader2 className="absolute size-8 animate-spin text-foreground-subtle" aria-label={t("sheet.loading")} /> : null}
            </div>
            <a href={url} target="_blank" rel="noreferrer" className={buttonVariants({ variant: "outline", size: "sm" })}>
              {t("sheet.open_artwork")}
            </a>
          </div>
        </Modal>
      ) : null}
    </span>
  );
}

function ArtworkUpload({ onUploaded, label }) {
  const { artworkUrl } = useContext(SheetConfig);
  const input = useRef(null);
  const toast = useToastManager();
  return (
    <>
      <button type="button" onClick={(e) => { e.stopPropagation(); input.current?.click(); }} aria-label={label} className="rounded p-0.5 text-foreground-subtle hover:text-brand">
        <Upload className="size-3.5" />
      </button>
      <input
        ref={input}
        type="file"
        accept="image/png,image/jpeg,image/webp,image/gif"
        className="hidden"
        onChange={async (e) => {
          const file = e.target.files?.[0];
          if (!file) return;
          const body = new FormData();
          body.append("file", file);
          const res = await fetch(artworkUrl, { method: "POST", body });
          const json = await res.json().catch(() => null);
          if (res.ok) onUploaded(json.data.id, json.data.url);
          else toast.add({ title: json?.errors?.file?.[0] ?? json?.message ?? "Upload failed", type: "danger" });
        }}
      />
    </>
  );
}

/** The in-place editor of one cell: Enter / Tab keep, Escape drops. */
function CellEditor({ column, value, seed, options, sizes = [], job = null, companion, by, onDone: finish }) {
  const initial = seed ?? (Array.isArray(value) ? value.join(", ") : (value ?? ""));
  const [draft, setDraft] = useState(String(initial));
  const ref = useRef(null);
  // The editor closes once: closing moves focus to the grid, and the blur that
  // follows must not put the old draft back (a dropdown's pick was being blanked).
  const closed = useRef(false);
  const onDone = (raw, step) => {
    if (closed.current) return;
    closed.current = true;
    finish(raw, step);
  };
  // The key that opened the editor reaches it too (focus moves while that key is
  // still being handled): keys count only once that key is released (or a moment has passed).
  const armed = useRef(false);
  useEffect(() => {
    ref.current?.focus();
    if (seed === undefined) ref.current?.select?.();
    const id = setTimeout(() => {
      armed.current = true;
    }, 300);
    return () => clearTimeout(id);
  }, [seed]);
  const arm = () => {
    armed.current = true;
  };
  const keys = (e) => {
    if (!armed.current) {
      if (e.key === "Enter" || e.key === "Tab") e.preventDefault();
      return;
    }
    if (e.key === "Escape") return onDone(undefined);
    if (e.key === "Enter" && !(column.type === "longtext" && e.shiftKey)) {
      e.preventDefault();
      onDone(draft, [1, 0]);
    } else if (e.key === "Tab") {
      e.preventDefault();
      onDone(draft, [0, e.shiftKey ? -1 : 1]);
    }
  };
  const cls = "absolute inset-0 z-10 h-full min-h-7 w-full border-2 border-brand bg-surface px-1 text-[11px] outline-none";
  if (column.type === "dropdown") {
    return <DropdownSearch value={value} seed={seed} options={options} onDone={onDone} />;
  }
  if (column.type === "pairs") {
    return <PairsEditor column={column} value={value} seed={seed} options={options} onDone={onDone} />;
  }
  if (column.type === "colorsize") {
    return <ColorSizeEditor column={column} value={value} seed={seed} colours={options} sizes={sizes} onDone={finish} />;
  }
  if (job && (column.type === "multi" || column.type === "text")) {
    return <JobNoEditor column={column} value={value} seed={seed} job={job} onDone={onDone} />;
  }
  if (column.type === "multi" && column.multiple !== false && !companion) {
    return <ListEditor column={column} value={value} seed={seed} options={options} onDone={onDone} />;
  }
  if (companion) {
    return <CompanionEditor column={column} value={value} by={by} companion={companion} onDone={onDone} />;
  }
  const list = column.type === "multi" && options.length ? `os-opt-${column.key}` : undefined;
  return (
    <>
      <input
        ref={ref}
        className={cls}
        type={column.type === "date" ? "date" : "text"}
        inputMode={["number", "money", "percent", "days"].includes(column.type) ? "decimal" : undefined}
        value={draft}
        list={list}
        onChange={(e) => setDraft(e.target.value)}
        onKeyDown={keys}
        onKeyUp={arm}
        onBlur={() => armed.current && onDone(draft)}
      />
      {list ? (
        <datalist id={list}>
          {options.map((o) => (
            <option key={o.value} value={o.value} />
          ))}
        </datalist>
      ) : null}
    </>
  );
}

/**
 * An Order Color cell's editor (Pre-Budget, user 2026-11-22): a line per
 * colour — the colour (the Colour master suggests; it is needed) and its
 * sizes, ticked from the Size master (optional). Add line, a line's ✕; OK
 * keeps them, Cancel or Escape leaves the cell as it was. A pasted
 * "Navy (S, M, L); White" becomes lines.
 */
function ColorSizeEditor({ column, value, seed, colours, sizes, onDone }) {
  const t = useT("ordersummary");
  const listId = useId();
  const first = useRef(null);
  const [lines, setLines] = useState(() => {
    const out = (Array.isArray(value) ? value : []).map((l) => ({ color: String(l?.color ?? ""), sizes: Array.isArray(l?.sizes) ? [...l.sizes] : [] }));
    if (seed !== undefined) out.push({ color: seed, sizes: [] });
    return out.length ? out : [{ color: "", sizes: [] }];
  });
  const [error, setError] = useState(false);
  useEffect(() => {
    const id = setTimeout(() => first.current?.focus(), 50);
    return () => clearTimeout(id);
  }, []);
  const order = useMemo(() => new Map(sizes.map((s, i) => [s.value, i])), [sizes]);
  const setColor = (i, v) => setLines((all) => all.map((l, j) => (j === i ? { ...l, color: v } : l)));
  const toggle = (i, size) =>
    setLines((all) =>
      all.map((l, j) => {
        if (j !== i) return l;
        const has = l.sizes.includes(size);
        const next = has ? l.sizes.filter((x) => x !== size) : [...l.sizes, size];
        return { ...l, sizes: next.sort((a, b) => (order.get(a) ?? 999) - (order.get(b) ?? 999)) };
      }),
    );
  const keep = () => {
    const out = lines.map((l) => ({ color: l.color.trim(), sizes: l.sizes })).filter((l) => l.color !== "" || l.sizes.length);
    if (out.some((l) => l.color === "")) return setError(true);
    onDone(out.length ? out : null, [1, 0]);
  };
  const stop = (e) => e.stopPropagation();
  const keys = (e) => {
    e.stopPropagation();
    if (e.key === "Enter" && e.target.tagName === "INPUT") {
      e.preventDefault();
      keep();
    }
  };
  const paste = (i) => (e) => {
    e.stopPropagation();
    const text = (e.clipboardData?.getData("text/plain") ?? "").replace(/^"([\s\S]*)"\s*$/, "$1");
    if (!/[;(\n,]/.test(text)) return;
    const parsed = parseColorSizes(text);
    if (!parsed) return;
    e.preventDefault();
    setLines((all) => [...all.slice(0, i), ...(all[i].color.trim() || all[i].sizes.length ? [all[i]] : []), ...parsed, ...all.slice(i + 1)]);
  };

  return (
    <Modal open onOpenChange={(open) => !open && onDone(undefined)} title={column.label} description={t("sheet.colorsize_hint")} className="max-w-2xl">
      <div onClick={stop} onKeyDown={keys} className="flex flex-col gap-3">
        <div className="flex max-h-[55vh] flex-col gap-2 overflow-y-auto">
          {lines.map((l, i) => (
            <div key={i} className="rounded-md border border-border p-2">
              <div className="flex items-center gap-2">
                <span className="w-5 text-right text-xs text-foreground-muted">{i + 1}</span>
                <Input ref={i === 0 ? first : undefined} value={l.color} list={colours.length ? listId : undefined} placeholder={t("sheet.colorsize_colour")} onChange={(e) => { setColor(i, e.target.value); setError(false); }} onPaste={paste(i)} aria-label={`${t("sheet.colorsize_colour")} ${i + 1}`} className={error && !l.color.trim() ? "border-danger" : undefined} />
                <button type="button" onClick={() => setLines((all) => (all.length > 1 ? all.filter((_, j) => j !== i) : [{ color: "", sizes: [] }]))} aria-label={t("sheet.pairs_remove")} title={t("sheet.pairs_remove")} className="rounded p-1 text-foreground-subtle hover:bg-danger-subtle hover:text-danger">
                  <X className="size-4" />
                </button>
              </div>
              <div className="mt-1.5 flex flex-wrap gap-1 pl-7">
                {sizes.length === 0 ? <span className="text-xs text-foreground-muted">{t("sheet.colorsize_no_sizes")}</span> : null}
                {sizes.map((s) => {
                  const on = l.sizes.includes(s.value);
                  return (
                    <button key={s.value} type="button" onClick={() => toggle(i, s.value)} aria-pressed={on} className={`rounded border px-1.5 py-0.5 text-[11px] ${on ? "border-brand bg-brand text-white" : "border-border bg-surface text-foreground hover:border-brand"}`}>
                      {s.label}
                    </button>
                  );
                })}
              </div>
            </div>
          ))}
        </div>
        {colours.length ? (
          <datalist id={listId}>
            {colours.map((o) => (
              <option key={o.value} value={o.value} />
            ))}
          </datalist>
        ) : null}
        {error ? <p className="text-xs text-danger">{t("sheet.colorsize_needed")}</p> : null}
        <div className="flex items-center justify-between gap-2">
          <Button type="button" size="sm" variant="outline" onClick={() => setLines((all) => [...all, { color: "", sizes: [] }])}>
            <Plus /> {t("sheet.pairs_add")}
          </Button>
        </div>
        <div className="flex justify-end gap-2 border-t border-border pt-3">
          <Button type="button" variant="outline" size="sm" onClick={() => onDone(undefined)}>{t("sheet.pairs_cancel")}</Button>
          <Button type="button" size="sm" onClick={keep}>{t("sheet.pairs_ok")}</Button>
        </div>
      </div>
    </Modal>
  );
}

/**
 * A list cell's editor (JOB No, PO Number, Shipment ID — an order often has
 * several, user 2026-11-22): one value a line; Add line, a line's ✕, OK
 * keeps them, Cancel or Escape leaves the cell as it was. Enter in the last
 * line adds the next one; a pasted column or comma list becomes lines.
 */
function ListEditor({ column, value, seed, options, onDone }) {
  const t = useT("ordersummary");
  const listId = useId();
  const box = useRef(null);
  const [lines, setLines] = useState(() => {
    const out = (Array.isArray(value) ? value : blank(value) ? [] : [value]).map((v) => String(v));
    if (seed !== undefined) out.push(seed);
    return out.length ? out : [""];
  });
  const [focus, setFocus] = useState(() => Math.max(0, (Array.isArray(value) ? value.length : 0) + (seed !== undefined ? 0 : -1)));
  useEffect(() => {
    const id = setTimeout(() => box.current?.querySelectorAll("input")[focus]?.focus(), 30);
    return () => clearTimeout(id);
  }, [focus, lines.length]);
  const keep = () => {
    const out = [...new Set(lines.map((l) => l.trim()).filter(Boolean))];
    onDone(out.length ? out : null, [1, 0]);
  };
  const stop = (e) => e.stopPropagation();
  const keys = (i) => (e) => {
    e.stopPropagation();
    if (e.key === "Enter") {
      e.preventDefault();
      if (e.ctrlKey || e.metaKey || i < lines.length - 1 || lines[i].trim() === "") return keep();
      setLines((all) => [...all, ""]);
      setFocus(lines.length);
    }
  };
  const paste = (i) => (e) => {
    e.stopPropagation();
    const text = (e.clipboardData?.getData("text/plain") ?? "").replace(/^"([\s\S]*)"\s*$/, "$1");
    const parts = text.split(/\s*[\n,;\t]\s*/).map((p) => p.trim()).filter(Boolean);
    if (parts.length < 2) return;
    e.preventDefault();
    setLines((all) => {
      const next = [...all.slice(0, i), ...(all[i].trim() ? [all[i]] : []), ...parts, ...all.slice(i + 1)];
      return next.filter((l, j) => l.trim() !== "" || j === next.length - 1);
    });
  };

  return (
    <Modal open onOpenChange={(open) => !open && onDone(undefined)} title={column.label} description={t("sheet.list_hint")} className="max-w-sm">
      <div ref={box} onClick={stop} onKeyDown={stop} className="flex flex-col gap-3">
        <ol className="flex max-h-[50vh] flex-col gap-1.5 overflow-y-auto">
          {lines.map((l, i) => (
            <li key={i} className="flex items-center gap-2">
              <span className="w-5 text-right text-xs text-foreground-muted">{i + 1}</span>
              <Input value={l} list={options.length ? listId : undefined} onChange={(e) => setLines((all) => all.map((x, j) => (j === i ? e.target.value : x)))} onKeyDown={keys(i)} onPaste={paste(i)} aria-label={`${column.label} ${i + 1}`} />
              <button type="button" onClick={() => setLines((all) => (all.length > 1 ? all.filter((_, j) => j !== i) : [""]))} aria-label={t("sheet.pairs_remove")} title={t("sheet.pairs_remove")} className="rounded p-1 text-foreground-subtle hover:bg-danger-subtle hover:text-danger">
                <X className="size-4" />
              </button>
            </li>
          ))}
        </ol>
        {options.length ? (
          <datalist id={listId}>
            {options.map((o) => (
              <option key={o.value} value={o.value} />
            ))}
          </datalist>
        ) : null}
        <div className="flex items-center justify-between gap-2">
          <Button type="button" size="sm" variant="outline" onClick={() => { setLines((all) => [...all, ""]); setFocus(lines.length); }}>
            <Plus /> {t("sheet.pairs_add")}
          </Button>
          <span className="text-xs text-foreground-muted">{t("sheet.list_count", { n: lines.filter((l) => l.trim()).length })}</span>
        </div>
        <div className="flex justify-end gap-2 border-t border-border pt-3">
          <Button type="button" variant="outline" size="sm" onClick={() => onDone(undefined)}>{t("sheet.pairs_cancel")}</Button>
          <Button type="button" size="sm" onClick={keep}>{t("sheet.pairs_ok")}</Button>
        </div>
      </div>
    </Modal>
  );
}

/**
 * The company's Job No (user, 2026-11-22): 26NCL00001 — the year (today's, as
 * the server has it, so it turns over every January) and the row's factory
 * fill themselves; the user types only the number, and a new line comes with
 * the next free serial of that year and factory already in it (one past the
 * highest in any module: Order Summary, Pre-Budget, Dev Sample, Export
 * Statement). A sheet without a factory column (Dev Sample) picks the factory
 * in the dialog. A line already in that form edits only its number; any other
 * text stays an ordinary line. A number already in this sheet or entered twice
 * is flagged, a line of another factory is warned — neither is blocked.
 *
 * Every factory's numbers are loaded as the sheet opens (JobNoCache), so the
 * dialog shows at once; it asks again for its factory in the background and
 * moves a filled-in number on if someone took it meanwhile.
 */
const JOB_RE = /^(\d{2})([A-Z]{2,6})(\d{3,})$/;
const JOB_PICK_KEY = "jobno.factory";
const jobLine = (v) => {
  const m = JOB_RE.exec(String(v ?? "").trim());
  return m ? { year: m[1], factory: m[2], serial: m[3] } : { text: String(v ?? "") };
};
const jobText = (l, width = 5) => {
  if (l.text !== undefined) return l.text.trim();
  if (l.pending) return "";
  const digits = String(l.serial ?? "").replace(/\D/g, "").replace(/^0+(?=\d)/, "");
  return digits === "" ? "" : `${l.year}${l.factory}${digits.padStart(width, "0")}`;
};
const jobUrl = (url, factory) => `${url}${url.includes("?") ? "&" : "?"}factory=${encodeURIComponent(factory)}`;
const JobNoCache = createContext(null);

/** Every factory's Job No numbers, asked for once as a sheet with a Job No column opens. */
function useJobNoCache(jobNo) {
  const [store] = useState(() => ({ data: null, listeners: new Set() }));
  const url = jobNo?.url;
  useEffect(() => {
    if (!url) return;
    let gone = false;
    fetch(url, { cache: "no-store" })
      .then((r) => (r.ok ? r.json() : null))
      .then((json) => {
        if (gone || !json?.data?.factories) return;
        store.data = json.data.factories;
        store.listeners.forEach((l) => l());
      })
      .catch(() => {});
    return () => {
      gone = true;
    };
  }, [url, store]);
  return store;
}

/**
 * The lines as shown: a filled-in line (`fresh`) takes the factory's next
 * serials, past every other line's; a typed number (`typed`) takes the
 * factory's year and code. Before the numbers are known they wait (`pending`).
 */
function jobResolve(raw, info) {
  const mine = (l) => l.text === undefined && !l.fresh && (l.typed || (info && `${l.year}${l.factory}` === info.prefix));
  let at = info ? Math.max(info.next - 1, 0, ...raw.filter(mine).map((l) => Number(l.serial) || 0)) : 0;
  return raw.map((l) => {
    if (!l.fresh && !l.typed) return l;
    if (!info) return { ...l, year: "··", factory: "···", serial: l.serial ?? "", pending: true };
    return { ...l, year: info.year, factory: info.factory, serial: l.fresh ? String(++at) : l.serial };
  });
}

function JobNoEditor({ column, value, seed, job, onDone }) {
  const t = useT("ordersummary");
  const box = useRef(null);
  const cache = useContext(JobNoCache);
  const single = !!job.single;
  const [cached, setCached] = useState(() => cache?.data ?? null);
  useEffect(() => {
    if (!cache) return;
    const l = () => setCached(cache.data);
    cache.listeners.add(l);
    return () => cache.listeners.delete(l);
  }, [cache]);
  const [picked, setPicked] = useState(() => {
    if (!job.pick) return null;
    const own = jobLine(Array.isArray(value) ? value[0] : value).factory;
    if (own) return own;
    try {
      return window.localStorage.getItem(JOB_PICK_KEY) || null;
    } catch {
      return null;
    }
  });
  const factories = cached ? Object.keys(cached) : [];
  const factory = String((job.pick ? (picked && factories.includes(picked) ? picked : factories[0]) : job.factory) ?? "").toUpperCase() || null;
  const [latest, setLatest] = useState({});
  const info = factory ? (latest[factory] ?? cached?.[factory] ?? null) : null;
  const [failed, setFailed] = useState(false);
  const original = useMemo(() => (Array.isArray(value) ? value : blank(value) ? [] : [value]).map((v) => String(v)), [value]);
  // Opened on an empty cell: a line with the next number; opened by typing: what was typed.
  const [raw, setRaw] = useState(() => {
    const out = (Array.isArray(value) ? value : blank(value) ? [] : [value]).map((v) => jobLine(v));
    if (seed !== undefined) {
      const line = /^\d+$/.test(String(seed)) ? { typed: true, serial: String(seed) } : jobLine(seed);
      return single ? [line] : [...out, line];
    }
    return out.length ? out : [{ fresh: true }];
  });
  const [focus, setFocus] = useState(() => {
    const n = Array.isArray(value) ? value.length : blank(value) ? 0 : 1;
    return single ? 0 : seed !== undefined || n === 0 ? n : 0;
  });
  const lines = jobResolve(raw, info);

  // This factory's numbers asked for again (someone may have taken the next one since the sheet opened).
  useEffect(() => {
    if (!factory) return;
    let gone = false;
    fetch(jobUrl(job.url, factory), { cache: "no-store" })
      .then(async (r) => {
        const json = await r.json().catch(() => null);
        if (gone) return;
        if (!r.ok || !json?.data) return setFailed(true);
        if (cache?.data) cache.data = { ...cache.data, [factory]: json.data };
        setLatest((f) => ({ ...f, [factory]: json.data }));
      })
      .catch(() => !gone && setFailed(true));
    return () => {
      gone = true;
    };
  }, [factory, job.url, cache]);

  const ready = !!info;
  useEffect(() => {
    const id = setTimeout(() => {
      const input = box.current?.querySelectorAll("input[data-line]")[focus];
      if (!input || input.disabled) return;
      input.focus();
      if (seed === undefined) input.select?.();
      else input.setSelectionRange?.(input.value.length, input.value.length);
    }, 30);
    return () => clearTimeout(id);
  }, [focus, raw.length, seed, ready]);

  const width = info?.width ?? 5;
  const pick = (f) => {
    setPicked(f);
    try {
      window.localStorage.setItem(JOB_PICK_KEY, f);
    } catch {
      /* the choice is only remembered where storage works */
    }
  };
  const add = () => {
    setRaw((all) => [...all, info ? { fresh: true } : { text: "" }]);
    setFocus(raw.length);
  };
  const keep = () => {
    const out = [...new Set(lines.map((l) => jobText(l, width)).filter(Boolean))];
    onDone(single ? (out[0] ?? null) : out.length ? out : null, [1, 0]);
  };
  const stop = (e) => e.stopPropagation();
  const set = (i, patch) => setRaw((all) => all.map((x, j) => (j === i ? { ...x, ...patch } : x)));
  const setSerial = (i, serial) => set(i, raw[i].fresh || raw[i].typed ? { fresh: false, typed: true, serial } : { serial });
  // Enter keeps (a line comes already filled, so Enter adding one would never end); Add Line gives another.
  const keys = () => (e) => {
    e.stopPropagation();
    if (e.key === "Escape") return onDone(undefined);
    if (e.key === "Enter") {
      e.preventDefault();
      keep();
    }
  };
  // A pasted list becomes lines: a bare number takes this year and factory, a whole Job No (or other text) is kept as it is.
  const paste = (i) => (e) => {
    e.stopPropagation();
    const text = (e.clipboardData?.getData("text/plain") ?? "").replace(/^"([\s\S]*)"\s*$/, "$1");
    let parts = text.split(/\s*[\n,;\t]\s*/).map((p) => p.trim()).filter(Boolean);
    if (single) parts = parts.slice(0, 1);
    if (parts.length < 2 && !(parts.length === 1 && lines[i].text === undefined && !/^\d+$/.test(parts[0]))) return;
    e.preventDefault();
    // The line pasted into gives way when it was only the filled-in next number (or empty, or one value pasted).
    const drop = raw[i].fresh || jobText(lines[i], width) === "" || parts.length === 1;
    const added = parts.map((p) => (/^\d+$/.test(p) ? { typed: true, serial: p } : jobLine(p.toUpperCase())));
    setRaw((all) => [...all.slice(0, i), ...(drop ? [] : [all[i]]), ...added, ...all.slice(i + 1)]);
  };

  const own = new Set(original);
  const texts = lines.map((l) => jobText(l, width));
  const taken = new Set((info?.taken ?? []).map((n) => `${info.prefix}${String(n).padStart(width, "0")}`));
  const notes = lines.map((l, i) => {
    const v = texts[i];
    if (!v) return null;
    if (texts.indexOf(v) !== i) return { tone: "danger", text: t("sheet.job_duplicate", { no: v }) };
    if (taken.has(v) && !own.has(v)) return { tone: "danger", text: t("sheet.job_taken", { no: v }) };
    if (l.text === undefined && factory && l.factory !== factory) return { tone: "warning", text: t("sheet.job_other_factory", { f: l.factory, g: factory }) };
    return null;
  });
  const noFactory = !job.pick && !job.factory;
  const seg = "flex h-8 items-center border-r border-border bg-surface-muted px-2 font-mono text-[13px] text-foreground-muted";

  return (
    <Modal open onOpenChange={(open) => !open && onDone(undefined)} title={column.label} description={t("sheet.job_hint")} className="max-w-md">
      <div ref={box} onClick={stop} onKeyDown={stop} className="flex flex-col gap-3">
        {job.pick && factories.length ? (
          <label className="flex items-center gap-2 text-xs text-foreground-muted">
            <span className="font-medium">{t("sheet.job_factory")}</span>
            <select className="h-8 rounded-md border border-border-strong bg-surface px-2 font-mono text-[13px] text-foreground" value={factory ?? ""} onChange={(e) => pick(e.target.value)} aria-label={t("sheet.job_factory")}>
              {factories.map((f) => (
                <option key={f} value={f}>{f}</option>
              ))}
            </select>
          </label>
        ) : null}
        {noFactory || (failed && !info) ? <p className="rounded-md border border-warning/40 bg-warning-subtle px-3 py-2 text-xs text-warning">{t("sheet.job_no_factory")}</p> : null}
        {!noFactory && !failed && !info ? (
          <p className="flex items-center gap-2 text-xs text-foreground-muted">
            <Loader2 className="size-3.5 animate-spin" /> {t("sheet.job_loading")}
          </p>
        ) : null}
        {lines.length ? (
          <div className="flex items-center gap-2 pl-7 text-[11px] font-medium uppercase tracking-wide text-foreground-subtle">
            <span className="w-9">{t("sheet.job_year")}</span>
            <span className="w-14">{t("sheet.job_factory")}</span>
            <span>{t("sheet.job_serial")}</span>
          </div>
        ) : null}
        <ol className="flex max-h-[50vh] flex-col gap-1.5 overflow-y-auto">
          {lines.map((l, i) => (
            <li key={i} className="flex flex-col gap-0.5">
              <div className="flex items-center gap-2">
                <span className="w-5 text-right text-xs text-foreground-muted">{i + 1}</span>
                {l.text !== undefined ? (
                  <Input data-line value={l.text} onChange={(e) => set(i, { text: e.target.value })} onKeyDown={keys(i)} onPaste={paste(i)} aria-label={`${column.label} ${i + 1}`} />
                ) : (
                  <div className={cn("flex flex-1 items-stretch overflow-hidden rounded-md border bg-surface focus-within:ring-2 focus-within:ring-brand/40", notes[i]?.tone === "danger" ? "border-danger" : "border-border-strong")}>
                    <span className={cn(seg, "w-9 justify-center")} title={t("sheet.job_year")}>{l.year}</span>
                    <span className={cn(seg, "w-14 justify-center")} title={t("sheet.job_factory")}>{l.factory}</span>
                    <input
                      data-line
                      inputMode="numeric"
                      autoComplete="off"
                      className="h-8 min-w-0 flex-1 bg-transparent px-2 font-mono text-[13px] outline-none"
                      value={l.serial}
                      onChange={(e) => setSerial(i, e.target.value.replace(/\D/g, ""))}
                      disabled={!!l.pending}
                      onKeyDown={keys(i)}
                      onPaste={paste(i)}
                      aria-label={`${column.label} ${i + 1} — ${t("sheet.job_serial")}`}
                    />
                  </div>
                )}
                <span className="w-24 truncate text-right font-mono text-[11px] text-foreground-muted" title={texts[i]}>{l.text === undefined ? texts[i] : ""}</span>
                <button type="button" onClick={() => setRaw((all) => all.filter((_, j) => j !== i))} aria-label={t("sheet.pairs_remove")} title={t("sheet.pairs_remove")} className="rounded p-1 text-foreground-subtle hover:bg-danger-subtle hover:text-danger">
                  <X className="size-4" />
                </button>
              </div>
              {notes[i] ? <p className={cn("pl-7 text-[11px]", notes[i].tone === "danger" ? "text-danger" : "text-warning")}>{notes[i].text}</p> : null}
            </li>
          ))}
        </ol>
        {single ? (
          lines.length === 0 && info ? (
            <div>
              <Button type="button" size="sm" variant="outline" onClick={add}>
                <Plus /> {t("sheet.pairs_add")}
              </Button>
            </div>
          ) : null
        ) : (
          <div className="flex items-center justify-between gap-2">
            <Button type="button" size="sm" variant="outline" onClick={add} disabled={!info}>
              <Plus /> {t("sheet.pairs_add")}
            </Button>
            <span className="text-xs text-foreground-muted">{t("sheet.list_count", { n: texts.filter(Boolean).length })}</span>
          </div>
        )}
        <div className="flex justify-end gap-2 border-t border-border pt-3">
          <Button type="button" variant="outline" size="sm" onClick={() => onDone(undefined)}>{t("sheet.pairs_cancel")}</Button>
          <Button type="button" size="sm" onClick={keep}>{t("sheet.pairs_ok")}</Button>
        </div>
      </div>
    </Modal>
  );
}

/**
 * A pairs cell's editor (Dev Sample's Color with Qty, Buyer Target / FOB
 * Price — Norban's colour lines and price levels): a small table of lines,
 * each a name and a number; Add Line, a line's ✕, OK keeps them, Cancel or
 * Escape leaves the cell as it was. The column's number (what a formula
 * reads) shows below: the lines' total, or the first line.
 */
function PairsEditor({ column, value, seed, options, onDone }) {
  const t = useT("ordersummary");
  const first = useRef(null);
  const listId = useId();
  const [lines, setLines] = useState(() => {
    const out = (Array.isArray(value) ? value : []).map((l) => ({ label: String(l?.label ?? ""), value: l?.value === null || l?.value === undefined ? "" : String(l.value) }));
    if (seed !== undefined) out.push({ label: seed, value: "" });
    return out.length ? out : [{ label: "", value: "" }];
  });
  useEffect(() => {
    const id = setTimeout(() => first.current?.focus(), 50);
    return () => clearTimeout(id);
  }, []);
  const toNum = (v) => (String(v).trim() === "" ? null : num(String(v).replace(/[$,\s]/g, "")));
  const set = (i, key, v) => setLines((all) => all.map((l, j) => (j === i ? { ...l, [key]: v } : l)));
  const keep = () => {
    const out = lines.map((l) => ({ label: l.label.trim(), value: toNum(l.value) })).filter((l) => l.label !== "" || l.value !== null);
    onDone(out.length ? out : null, [1, 0]);
  };
  const reduce = column.pair_reduce ?? "SUM";
  const total = pairsNumber(lines.map((l) => ({ value: toNum(l.value) })), reduce);
  // Keys and pastes stay in the dialog (the sheet's own Enter / arrows / paste must not act on them).
  const stop = (e) => e.stopPropagation();
  const keys = (e) => {
    e.stopPropagation();
    if (e.key === "Enter") {
      e.preventDefault();
      keep();
    }
  };
  // A pasted cell or list ("Navy: 600, White: 400", one per line from Excel) becomes lines in place of
  // the empty ones; a plain word or number pastes into the field as usual.
  const paste = (e) => {
    e.stopPropagation();
    const text = e.clipboardData?.getData("text/plain") ?? "";
    const cleaned = text.replace(/^"([\s\S]*)"\s*$/, "$1").replace(/""/g, '"');
    if (!/[\n,;]|\d\s*[A-Za-z]*\s*$/.test(cleaned.trim()) || !/[A-Za-z]/.test(cleaned)) return;
    const parsed = parsePairs(cleaned);
    if (!parsed || (parsed.length < 2 && parsed[0]?.value === null)) return;
    e.preventDefault();
    setLines((all) => [...all.filter((l) => l.label.trim() !== "" || String(l.value).trim() !== ""), ...parsed.map((l) => ({ label: l.label, value: l.value === null ? "" : String(l.value) }))]);
  };

  return (
    <Modal open onOpenChange={(open) => !open && onDone(undefined)} title={column.label} description={t("sheet.pairs_hint")} className="max-w-md">
      <div onClick={stop} onKeyDown={keys} onPaste={paste} className="flex flex-col gap-3">
        <table className="w-full text-sm">
          <thead>
            <tr className="text-left text-xs text-foreground-muted">
              <th className="w-6 pb-1">#</th>
              <th className="pb-1">{t("sheet.pairs_name")}</th>
              <th className="w-32 pb-1 text-right">{[column.prefix, t("sheet.pairs_number"), column.suffix].filter(Boolean).join(" ")}</th>
              <th className="w-8 pb-1" />
            </tr>
          </thead>
          <tbody>
            {lines.map((l, i) => (
              <tr key={i}>
                <td className="py-1 text-xs text-foreground-muted">{i + 1}</td>
                <td className="py-1 pr-2">
                  <Input ref={i === 0 ? first : undefined} value={l.label} list={options.length ? listId : undefined} onChange={(e) => set(i, "label", e.target.value)} aria-label={`${t("sheet.pairs_name")} ${i + 1}`} />
                </td>
                <td className="py-1">
                  <Input value={l.value} inputMode="decimal" className="text-right tabular-nums" onChange={(e) => set(i, "value", e.target.value)} aria-label={`${t("sheet.pairs_number")} ${i + 1}`} />
                </td>
                <td className="py-1 text-right">
                  <button type="button" onClick={() => setLines((all) => (all.length > 1 ? all.filter((_, j) => j !== i) : [{ label: "", value: "" }]))} aria-label={t("sheet.pairs_remove")} title={t("sheet.pairs_remove")} className="rounded p-1 text-foreground-subtle hover:bg-danger-subtle hover:text-danger">
                    <X className="size-4" />
                  </button>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
        {options.length ? (
          <datalist id={listId}>
            {options.map((o) => (
              <option key={o.value} value={o.value} />
            ))}
          </datalist>
        ) : null}
        <div className="flex items-center justify-between gap-2">
          <Button type="button" size="sm" variant="outline" onClick={() => setLines((all) => [...all, { label: "", value: "" }])}>
            <Plus /> {t("sheet.pairs_add")}
          </Button>
          <span className="text-xs text-foreground-muted">
            {t(reduce === "FIRST" ? "sheet.pairs_first" : "sheet.pairs_total")}: <span className="font-semibold text-foreground tabular-nums">{total === null ? "—" : pairNumber(column, total)}</span>
          </span>
        </div>
        <div className="flex justify-end gap-2 border-t border-border pt-3">
          <Button type="button" variant="outline" size="sm" onClick={() => onDone(undefined)}>{t("sheet.pairs_cancel")}</Button>
          <Button type="button" size="sm" onClick={keep}>{t("sheet.pairs_ok")}</Button>
        </div>
      </div>
    </Modal>
  );
}

/**
 * A cell typed with its companion value (Dev Sample's Last Price Quote Date
 * and who entered the quote, both by hand — Norban's two-line cell): the
 * cell's own value and the name; OK keeps both, Cancel or Escape neither.
 */
function CompanionEditor({ column, value, by, companion, onDone }) {
  const t = useT("ordersummary");
  const [date, setDate] = useState(value ? String(value).slice(0, 10) : "");
  const [name, setName] = useState(by ? String(by) : "");
  const first = useRef(null);
  useEffect(() => {
    const id = setTimeout(() => first.current?.focus(), 50);
    return () => clearTimeout(id);
  }, []);
  const keep = () => onDone({ __companion: companion, value: date || null, by: name.trim() || null }, [1, 0]);
  const stop = (e) => e.stopPropagation();
  const keys = (e) => {
    e.stopPropagation();
    if (e.key === "Enter") {
      e.preventDefault();
      keep();
    }
  };

  return (
    <Modal open onOpenChange={(open) => !open && onDone(undefined)} title={column.label} className="max-w-sm">
      <div onClick={stop} onKeyDown={keys} onPaste={stop} className="flex flex-col gap-3">
        <label className="flex flex-col gap-1 text-sm">
          <span className="text-xs font-medium text-foreground-muted">{t("sheet.companion_date")}</span>
          <Input ref={first} type={column.type === "date" ? "date" : "text"} value={date} onChange={(e) => setDate(e.target.value)} />
        </label>
        <label className="flex flex-col gap-1 text-sm">
          <span className="text-xs font-medium text-foreground-muted">{t("sheet.companion_by")}</span>
          <Input value={name} maxLength={120} onChange={(e) => setName(e.target.value)} placeholder={t("sheet.companion_by_ph")} />
        </label>
        <div className="flex justify-end gap-2 border-t border-border pt-3">
          <Button type="button" variant="outline" size="sm" onClick={() => onDone(undefined)}>{t("sheet.pairs_cancel")}</Button>
          <Button type="button" size="sm" onClick={keep}>{t("sheet.pairs_ok")}</Button>
        </div>
      </div>
    </Modal>
  );
}

/** Most matches shown at once; typing narrows the rest. */
const DROPDOWN_LIMIT = 200;

/**
 * A dropdown cell's editor: a search box over the list. Typing narrows it
 * (any part of a value, any case), Arrow keys move, Enter / Tab / a click
 * take the highlighted value, Escape or a click elsewhere leaves the cell as
 * it was. The list floats over the sheet (a cell clips what it holds).
 */
function DropdownSearch({ value, seed, options, onDone }) {
  const t = useT("ordersummary");
  const current = value === null || value === undefined ? "" : String(value);
  const [query, setQuery] = useState(seed ?? "");
  const [place, setPlace] = useState(null);
  const input = useRef(null);
  const list = useRef(null);
  const armed = useRef(false);
  const listId = useId();
  // Every value, the cell's own first when the list lacks it (brought in from Excel).
  const all = useMemo(() => {
    const out = options.map((o) => o.value);
    if (current && !out.includes(current)) out.unshift(current);
    return out;
  }, [options, current]);
  const q = query.trim().toLowerCase();
  const matches = useMemo(() => (q ? all.filter((v) => v.toLowerCase().includes(q)) : ["", ...all]), [all, q]);
  const shown = matches.slice(0, DROPDOWN_LIMIT);
  const [active, setActive] = useState(() => Math.max(0, q ? 0 : shown.indexOf(current)));
  const highlighted = Math.min(active, Math.max(0, shown.length - 1));

  useLayoutEffect(() => {
    const el = input.current;
    if (!el) return;
    el.focus();
    const r = el.getBoundingClientRect();
    const below = window.innerHeight - r.bottom;
    const up = below < 260 && r.top > below;
    setPlace({ left: r.left, width: Math.max(r.width, 240), top: up ? undefined : r.bottom, bottom: up ? window.innerHeight - r.top : undefined });
    const id = setTimeout(() => {
      armed.current = true;
    }, 300);
    return () => clearTimeout(id);
  }, []);
  useEffect(() => {
    list.current?.querySelector(`[data-index="${highlighted}"]`)?.scrollIntoView({ block: "nearest" });
  }, [highlighted]);

  const pick = (v, step) => onDone(v, step);
  const keys = (e) => {
    if (e.key === "ArrowDown" || e.key === "ArrowUp") {
      e.preventDefault();
      setActive(Math.max(0, Math.min(shown.length - 1, highlighted + (e.key === "ArrowDown" ? 1 : -1))));
      return;
    }
    if (!armed.current) {
      if (e.key === "Enter" || e.key === "Tab") e.preventDefault();
      return;
    }
    if (e.key === "Escape") onDone(undefined);
    else if (e.key === "Enter" || e.key === "Tab") {
      e.preventDefault();
      if (shown.length) pick(shown[highlighted], e.key === "Enter" ? [1, 0] : [0, e.shiftKey ? -1 : 1]);
    }
  };

  return (
    <>
      <input
        ref={input}
        className="absolute inset-0 z-10 h-full min-h-7 w-full border-2 border-brand bg-surface px-1 text-[11px] outline-none"
        value={query}
        placeholder={current || t("sheet.search_list")}
        aria-label={t("sheet.search_list")}
        role="combobox"
        aria-expanded="true"
        aria-controls={listId}
        aria-autocomplete="list"
        onChange={(e) => {
          setQuery(e.target.value);
          setActive(0);
        }}
        onKeyDown={keys}
        onKeyUp={() => {
          armed.current = true;
        }}
        onBlur={() => onDone(undefined)}
      />
      {place
        ? createPortal(
            <ul
              ref={list}
              id={listId}
              role="listbox"
              className="fixed z-[70] max-h-64 overflow-auto rounded-md border border-border bg-surface py-1 text-[12px] shadow-lg"
              style={{ left: place.left, width: place.width, top: place.top, bottom: place.bottom }}
            >
              {shown.length === 0 ? <li className="px-2 py-1.5 text-foreground-muted">{t("sheet.no_match")}</li> : null}
              {shown.map((v, i) => (
                <li
                  key={v || "__blank"}
                  data-index={i}
                  role="option"
                  aria-selected={i === highlighted}
                  // mousedown, not click: the box keeps focus until the value is taken.
                  onMouseDown={(e) => {
                    e.preventDefault();
                    pick(v, [1, 0]);
                  }}
                  onMouseEnter={() => setActive(i)}
                  className={cn("cursor-pointer truncate px-2 py-1", i === highlighted ? "bg-brand text-brand-foreground" : v === current ? "font-semibold" : "", !v && i !== highlighted && "text-foreground-muted")}
                >
                  {v || "—"}
                </li>
              ))}
              {matches.length > shown.length ? <li className="px-2 py-1 text-[11px] text-foreground-muted">{t("sheet.more_matches", { n: matches.length - shown.length })}</li> : null}
            </ul>,
            document.body,
          )
        : null}
    </>
  );
}

/** Excel's header filter: the column's values under the other filters, with counts. */
function HeaderFilter({ column, queryString, act, t, onClose, go }) {
  const [values, setValues] = useState(null);
  const [search, setSearch] = useState("");
  const key = `f[${column.key}][]`;
  const current = useMemo(() => new URLSearchParams(queryString).getAll(key), [queryString, key]);
  const [chosen, setChosen] = useState(() => new Set(current));
  // A from/to range on this column (the dashboard's Days Left buckets set one): shown, and cleared with the filter.
  const range = useMemo(() => {
    const p = new URLSearchParams(queryString);
    const from = p.get(`r[${column.key}][from]`);
    const to = p.get(`r[${column.key}][to]`);
    return from === null && to === null ? null : { from, to };
  }, [queryString, column.key]);
  const dropRange = (p) => { p.delete(`r[${column.key}][from]`); p.delete(`r[${column.key}][to]`); };
  useEffect(() => {
    const p = new URLSearchParams(queryString);
    p.delete(key);
    p.delete("page");
    act("distinct", p.toString(), column.key).then((res) => setValues(res?.status === "success" ? res.data : []));
  }, [act, column.key, key, queryString]);
  const shownValues = (values ?? []).filter((v) => !search || v.value.toLowerCase().includes(search.toLowerCase()));
  const all = shownValues.length > 0 && shownValues.every((v) => chosen.has(v.value));
  return (
    <Modal
      open
      onOpenChange={(o) => !o && onClose()}
      title={t("sheet.filter_by", { column: column.label })}
      footer={
        <>
          <Button type="button" variant="outline" className="mr-auto" onClick={() => { go((p) => { p.delete(key); dropRange(p); }); onClose(); }}>
            {t("sheet.clear_filter")}
          </Button>
          <Button type="button" variant="outline" onClick={onClose}>
            {t("sheet.cancel")}
          </Button>
          <Button type="button" onClick={() => {
            go((p) => {
              p.delete(key);
              if (!all || search) chosen.forEach((v) => p.append(key, v));
              if (chosen.size > 0) dropRange(p); // ticked values replace a range
            });
            onClose();
          }}>
            {t("sheet.ok")}
          </Button>
        </>
      }
    >
      <div className="flex flex-col gap-2 text-[13px]">
        {range ? (
          <p className="rounded border border-brand/40 bg-brand/5 px-2 py-1.5 text-[12px]">
            {t("sheet.range_on", { from: range.from ?? "…", to: range.to ?? "…" })}
          </p>
        ) : null}
        <Input value={search} onChange={(e) => setSearch(e.target.value)} placeholder={t("sheet.search")} aria-label={t("sheet.search")} />
        <label className="flex items-center gap-2 font-semibold">
          <Checkbox checked={all} onCheckedChange={(on) => setChosen((s) => { const n = new Set(s); shownValues.forEach((v) => (on ? n.add(v.value) : n.delete(v.value))); return n; })} />
          {t("sheet.select_all")}
        </label>
        <div className="max-h-72 overflow-y-auto rounded border border-border p-1">
          {values === null ? <p className="p-2 text-foreground-muted">{t("sheet.loading")}</p> : null}
          {shownValues.map((v) => (
            <label key={v.value || "__blank"} className="flex items-center gap-2 rounded px-2 py-1 hover:bg-surface-muted">
              <Checkbox checked={chosen.has(v.value)} onCheckedChange={(on) => setChosen((s) => { const n = new Set(s); on ? n.add(v.value) : n.delete(v.value); return n; })} />
              <span className="flex-1 truncate">{v.value === "" ? t("sheet.blanks") : display(column, v.value)}</span>
              <span className="text-foreground-subtle tabular-nums">{v.count}</span>
            </label>
          ))}
        </div>
      </div>
    </Modal>
  );
}

export { OrderSheet };
