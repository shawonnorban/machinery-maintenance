"use client";

import { useMemo, useRef, useState, useTransition, useEffect } from "react";
import { usePathname, useRouter } from "next/navigation";
import { ArrowRight, Calculator, CheckCircle2, ChevronDown, Circle, Filter, FolderOpen, Minus, Plus, Save, Tags, Trash2, Warehouse, X } from "lucide-react";
import { cn } from "@/lib/utils";
import { Select } from "@/components/ui/select";
import { Button } from "@/components/ui/button";
import { Modal } from "@/components/ui/modal";
import { ConfirmDialog } from "@/components/ui/confirm-dialog";
import { useToastManager } from "@/components/ui/toast";
import { useT } from "@/lib/i18n";
import { ApprovalPanel, StatusPill } from "./approval-panel";
import { PrintLinks } from "./print/print-links";

/*
 * Pre-Budget → Create Budget (AnnotechERP 33/34, Norban's pivot page).
 *
 * Without a budget: the completed requisitions no budget holds, filtered by
 * buyer / brand / style / item / job no; tick those of one buyer and Create
 * Budget — it is numbered at once. With one (?budget=PB-…): its header, the
 * Item Wise Summary, the Knit Yarn and Woven Fabric lines (worked out from
 * its requisitions — Need to Purchase, PI Qty, supplier price columns, the
 * price, PI Value, Excess/Shortage with Purchase / Not Purchase, remarks),
 * the Fabric Type / Fabrication and Delivery Date summaries, and Gross / Net
 * FOB with the cost breakdown. Figures are worked out as you type; Save sends
 * what was typed (the server works it all out again). The checklist —
 * Knit/Dying Costing, Stock Yarn Use, Accessories BOM — fills in with the
 * next steps; Finalize waits for all three.
 */

const PICK = ["buyer", "brand", "style", "item", "job_no"];
const n = (v, d = 0) => (v === null || v === undefined || v === "" || Number.isNaN(Number(v)) ? "" : Number(v).toLocaleString("en-US", { minimumFractionDigits: d, maximumFractionDigits: d }));
const money = (v, d = 0) => (v === null || v === undefined || v === "" ? "" : `${Number(v) < 0 ? "-" : ""}$${n(Math.abs(Number(v)), d)}`);
const num = (v) => (v === null || v === undefined || String(v).trim() === "" || Number.isNaN(Number(v)) ? null : Number(v));
const upToWhole = (q) => Math.ceil(Math.round(q * 1e6) / 1e6 - 1e-9);

function CreateBudget({ budgets, budget, candidates, query, act }) {
  return budget ? <BudgetPage key={`${budget.budget_no}|${budget.version}`} budgets={budgets} budget={budget} act={act} /> : <BudgetPicker budgets={budgets} candidates={candidates} query={query} act={act} />;
}

/* ── Load a budget ────────────────────────────────────────────────── */

function LoadBudget({ budgets, current }) {
  const t = useT("prebudget");
  const router = useRouter();
  const pathname = usePathname();
  const [pick, setPick] = useState(current ?? "");
  return (
    <div className="flex items-center gap-2">
      <select className="h-8 min-w-64 rounded border border-border-strong bg-surface px-2 text-[12px]" value={pick} onChange={(e) => setPick(e.target.value)} aria-label={t("budget.load")}>
        <option value="">{t("budget.load_pick")}</option>
        {budgets.map((b) => (
          <option key={b.budget_no} value={b.budget_no}>
            {b.budget_no} — {b.buyer} — {t(`budget.status.${b.status}`)}
          </option>
        ))}
      </select>
      <Button type="button" size="sm" variant="outline" disabled={!pick} onClick={() => router.push(`${pathname}?budget=${encodeURIComponent(pick)}`)}>
        <FolderOpen /> {t("budget.load")}
      </Button>
    </div>
  );
}

/* ── Picker: requisitions into a new budget ──────────────────────── */

function BudgetPicker({ budgets, candidates, query, act }) {
  const t = useT("prebudget");
  const router = useRouter();
  const pathname = usePathname();
  const toast = useToastManager();
  const [filters, setFilters] = useState(() => Object.fromEntries(PICK.map((k) => [k, [query[k] ?? []].flat()])));
  const [ticked, setTicked] = useState(() => new Set());
  const [pending, start] = useTransition();
  const rows = candidates.rows;
  const buyer = rows.find((r) => ticked.has(r.id))?.buyer ?? null;

  const apply = (next) => {
    const p = new URLSearchParams();
    for (const k of PICK) for (const v of next[k]) p.append(`${k}[]`, v);
    router.push(`${pathname}${p.size ? `?${p}` : ""}`);
  };
  const create = () =>
    start(async () => {
      const res = await act("createBudget", [...ticked]);
      if (res?.status === "success") {
        toast.add({ title: t("budget.created", { no: res.data.budget_no }), type: "success" });
        router.push(`${pathname}?budget=${encodeURIComponent(res.data.budget_no)}`);
      } else toast.add({ title: Object.values(res?.errors ?? {})[0]?.[0] ?? res?.message ?? t("budget.error"), type: "danger" });
    });

  return (
    <div className="flex flex-col gap-3 text-[12px]">
      <section className="rounded-lg border border-border bg-surface p-3">
        <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
          {PICK.map((k) => (
            <MultiPick key={k} label={t(`budget.f.${k}`)} options={candidates.options[k] ?? []} value={filters[k]} onChange={(v) => setFilters((f) => ({ ...f, [k]: v }))} />
          ))}
        </div>
        <div className="mt-3 flex flex-wrap items-center gap-2">
          <Button type="button" size="sm" onClick={() => apply(filters)}>
            <Filter /> {t("budget.filter")}
          </Button>
          <Button type="button" size="sm" variant="outline" onClick={() => apply(Object.fromEntries(PICK.map((k) => [k, []])))}>
            <X /> {t("budget.clear")}
          </Button>
          <span className="ml-auto">
            <LoadBudget budgets={budgets} />
          </span>
        </div>
      </section>

      <section className="rounded-lg border border-border bg-surface">
        <div className="flex flex-wrap items-center gap-2 border-b border-border px-3 py-2">
          <Button type="button" size="sm" disabled={ticked.size === 0 || pending} onClick={create}>
            <Plus /> {ticked.size ? t("budget.create_n", { n: ticked.size }) : t("budget.create")}
          </Button>
          <span className="text-foreground-muted">{buyer ? t("budget.one_buyer_hint", { buyer }) : t("budget.pick_hint", { n: rows.length })}</span>
        </div>
        {rows.length === 0 ? (
          <p className="px-4 py-10 text-center text-foreground-muted">{t("budget.no_free")}</p>
        ) : (
          <div className="max-h-[60vh] overflow-auto">
            <table className="w-full border-separate border-spacing-0">
              <thead className="sticky top-0 bg-[#d9d9d9] text-[11px] font-bold uppercase">
                <tr>
                  <th className="border-b border-[#bfbfbf] px-2 py-1.5">
                    <input
                      type="checkbox"
                      aria-label={t("budget.tick_all")}
                      checked={rows.length > 0 && rows.filter((r) => !buyer || r.buyer === buyer).every((r) => ticked.has(r.id))}
                      onChange={(e) => {
                        const b = buyer ?? rows[0]?.buyer;
                        setTicked(e.target.checked ? new Set(rows.filter((r) => r.buyer === b).map((r) => r.id)) : new Set());
                      }}
                    />
                  </th>
                  {["serial", "entry_type", "buyer", "brand", "style", "item", "job_no", "fabrication", "order_qty", "total_req"].map((h) => (
                    <th key={h} className="border-b border-[#bfbfbf] px-2 py-1.5 text-center">{t(`budget.f.${h}`)}</th>
                  ))}
                </tr>
              </thead>
              <tbody>
                {rows.map((r) => {
                  const other = buyer && r.buyer !== buyer;
                  return (
                    <tr key={r.id} className={cn(other && "opacity-40", ticked.has(r.id) && "bg-brand-subtle/40")}>
                      <td className="border-b border-[#e5e7eb] px-2 py-1 text-center">
                        <input type="checkbox" disabled={Boolean(other)} checked={ticked.has(r.id)} aria-label={t("budget.tick", { no: r.serial_no })} onChange={(e) => setTicked((s) => { const nx = new Set(s); e.target.checked ? nx.add(r.id) : nx.delete(r.id); return nx; })} />
                      </td>
                      <td className="border-b border-[#e5e7eb] px-2 py-1 text-center">{r.serial_no}</td>
                      <td className="border-b border-[#e5e7eb] px-2 py-1 text-center">{r.entry_type === "WOVEN" ? "Woven" : "Knit"}</td>
                      <td className="border-b border-[#e5e7eb] px-2 py-1 text-center">{r.buyer}</td>
                      <td className="border-b border-[#e5e7eb] px-2 py-1 text-center">{r.brand}</td>
                      <td className="border-b border-[#e5e7eb] px-2 py-1 text-center font-semibold">{r.style}</td>
                      <td className="border-b border-[#e5e7eb] px-2 py-1 text-center">{r.item}</td>
                      <td className="border-b border-[#e5e7eb] px-2 py-1 text-center">{r.job_no}</td>
                      <td className="border-b border-[#e5e7eb] px-2 py-1">{r.fabrication}</td>
                      <td className="border-b border-[#e5e7eb] px-2 py-1 text-right tabular-nums">{n(r.order_qty)}</td>
                      <td className="border-b border-[#e5e7eb] px-2 py-1 text-right tabular-nums">{n(r.total_req, 2)} {r.entry_type === "WOVEN" ? "yds" : "kg"}</td>
                    </tr>
                  );
                })}
              </tbody>
            </table>
          </div>
        )}
      </section>
    </div>
  );
}

/** A filter of many values: a button with what is picked, a searchable checklist. */
function MultiPick({ label, options, value, onChange }) {
  const t = useT("prebudget");
  const [open, setOpen] = useState(false);
  const [q, setQ] = useState("");
  const box = useRef(null);
  useEffect(() => {
    if (!open) return undefined;
    const close = (e) => box.current && !box.current.contains(e.target) && setOpen(false);
    document.addEventListener("mousedown", close);
    return () => document.removeEventListener("mousedown", close);
  }, [open]);
  const shown = options.filter((o) => o.toLowerCase().includes(q.toLowerCase())).slice(0, 300);
  return (
    <div ref={box} className="relative flex flex-col gap-1">
      <span className="text-[12px] font-medium">{label}</span>
      <button type="button" onClick={() => setOpen((o) => !o)} className="flex h-8 items-center justify-between rounded border border-border-strong bg-surface px-2 text-left text-[12px]">
        <span className={cn("truncate", !value.length && "text-foreground-muted")}>{value.length ? value.join(", ") : t("budget.select_multiple")}</span>
        <ChevronDown className="size-3.5 shrink-0" />
      </button>
      {open ? (
        <div className="absolute top-full z-30 mt-1 w-full min-w-56 rounded border border-border bg-surface p-2 shadow-lg">
          <input autoFocus className="mb-2 h-7 w-full rounded border border-border px-2 text-[12px]" placeholder={t("budget.search")} value={q} onChange={(e) => setQ(e.target.value)} />
          <div className="max-h-56 overflow-auto">
            {shown.length === 0 ? <p className="p-2 text-foreground-muted">{t("budget.no_match")}</p> : null}
            {shown.map((o) => (
              <label key={o} className="flex cursor-pointer items-center gap-2 rounded px-1 py-0.5 hover:bg-surface-muted">
                <input type="checkbox" checked={value.includes(o)} onChange={(e) => onChange(e.target.checked ? [...value, o] : value.filter((v) => v !== o))} />
                <span className="truncate">{o}</span>
              </label>
            ))}
          </div>
        </div>
      ) : null}
    </div>
  );
}

/* ── The budget ───────────────────────────────────────────────────── */

const SECTION_UNIT = { YARN: "kg", WOVEN: "yds" };

/** A budget's page. `embedded` (Budget Finalized): the sheet alone, without its toolbar, checklist and approval panel. */
function BudgetPage({ budgets, budget, act, embedded = false }) {
  const t = useT("prebudget");
  const router = useRouter();
  const pathname = usePathname();
  const toast = useToastManager();
  const [pending, start] = useTransition();
  const editable = budget.editable;
  const [head, setHead] = useState({ contract_no: budget.contract_no ?? "", discount_percent: String(budget.fob.discount_percent ?? 0) });
  const [suppliers, setSuppliers] = useState({ YARN: budget.sections.YARN.suppliers, WOVEN: budget.sections.WOVEN.suppliers });
  // What is typed on each line, as typed (blank = worked out).
  const [typed, setTyped] = useState(() => {
    const out = {};
    for (const s of ["YARN", "WOVEN"]) {
      for (const l of budget.sections[s].lines) {
        out[`${s}|${l.key}`] = {
          need_to_purchase: l.need_typed ? String(l.need_to_purchase) : "",
          pi_qty: l.pi_typed ? String(l.pi_qty) : "",
          price: l.price_typed ? String(l.price) : "",
          supplier_prices: Object.fromEntries(Object.entries(l.supplier_prices).map(([k, v]) => [k, v === null ? "" : String(v)])),
          shortage_action: l.shortage_action ?? "",
          remarks: l.remarks ?? "",
        };
      }
    }
    return out;
  });
  const [dirty, setDirty] = useState(false);
  const [styleModal, setStyleModal] = useState(null); // "add" | "remove"
  const [deleting, setDeleting] = useState(false);
  const [summaryBy, setSummaryBy] = useState("fabric_type");
  const [wovenBy, setWovenBy] = useState("fabric_type");

  useEffect(() => {
    const warn = (e) => {
      if (dirty) {
        e.preventDefault();
        e.returnValue = "";
      }
    };
    window.addEventListener("beforeunload", warn);
    return () => window.removeEventListener("beforeunload", warn);
  }, [dirty]);

  const edit = (section, key, patch) => {
    setTyped((all) => ({ ...all, [`${section}|${key}`]: { ...all[`${section}|${key}`], ...patch } }));
    setDirty(true);
  };

  // The lines worked out again from what is typed (the server's rules, BudgetSheet).
  const sections = useMemo(() => {
    const out = {};
    for (const s of ["YARN", "WOVEN"]) {
      const lines = budget.sections[s].lines.map((l) => {
        const tp = typed[`${s}|${l.key}`] ?? {};
        const need = num(tp.need_to_purchase) ?? upToWhole(Math.max(l.total_req - l.stock_qty, 0));
        const pi = num(tp.pi_qty) ?? need;
        const prices = Object.fromEntries(suppliers[s].map((name) => [name, num(tp.supplier_prices?.[name])]));
        const offered = Object.values(prices).filter((p) => p !== null && p > 0);
        const lowest = offered.length ? Math.min(...offered) : null;
        const price = num(tp.price) ?? lowest;
        const stockPurchase = Math.round((l.stock_qty + pi) * 1000) / 1000;
        const excess = Math.round((stockPurchase - l.total_req) * 1000) / 1000;
        const value = price === null ? 0 : Math.round(pi * price * 100) / 100;
        const short = excess < 0 && tp.shortage_action === "PURCHASE" && price !== null ? Math.round(-excess * price * 100) / 100 : 0;
        return { ...l, need, pi, lowest, price, stockPurchase, excess, value, short, tp };
      });
      const sum = (k) => lines.reduce((a, l) => a + (l[k] ?? 0), 0);
      const value = sum("value");
      const stockValue = sum("stock_value");
      const short = sum("short");
      out[s] = { lines, totals: { total_req: sum("total_req"), stock_qty: sum("stock_qty"), stock_value: stockValue, need: sum("need"), pi: sum("pi"), stockPurchase: sum("stockPurchase"), excess: sum("excess"), value, short, grand: value + stockValue + short } };
    }
    return out;
  }, [budget, typed, suppliers]);

  const fob = useMemo(() => {
    const gross = budget.fob.gross;
    const pct = num(head.discount_percent) ?? 0;
    const discount = Math.round(gross * pct) / 100;
    return { gross, pct, discount, net: gross - discount, avg: budget.fob.avg };
  }, [budget, head.discount_percent]);
  const costs = useMemo(() => {
    const c = { ...budget.costs, yarn: sections.YARN.totals.grand, woven: sections.WOVEN.totals.grand };
    const fabric = c.yarn + c.woven + c.knitting + c.dyeing + c.yarn_dyeing + c.aop;
    const total = fabric + c.accessories + c.embellishment + c.badge;
    return { ...c, fabric_total: fabric, total, others: fob.net - total - c.cm };
  }, [budget, sections, fob]);

  const save = () =>
    start(async () => {
      const lines = Object.entries(typed).map(([id, tp]) => {
        const [section, ...rest] = id.split("|");
        return {
          section, key: rest.join("|"),
          need_to_purchase: num(tp.need_to_purchase), pi_qty: num(tp.pi_qty), price: num(tp.price),
          supplier_prices: Object.fromEntries(Object.entries(tp.supplier_prices ?? {}).filter(([name, v]) => suppliers[section].includes(name) && num(v) !== null).map(([name, v]) => [name, num(v)])),
          shortage_action: tp.shortage_action || null, remarks: tp.remarks || null,
        };
      });
      const res = await act("saveBudget", budget.budget_no, { version: budget.version, contract_no: head.contract_no || null, discount_percent: num(head.discount_percent) ?? 0, suppliers, lines });
      if (res?.status === "success") {
        setDirty(false);
        toast.add({ title: t("budget.saved", { no: budget.budget_no }), type: "success" });
        router.refresh();
      } else toast.add({ title: Object.values(res?.errors ?? {})[0]?.[0] ?? res?.message ?? t("budget.error"), type: "danger" });
    });

  const remove = () =>
    start(async () => {
      const res = await act("deleteBudget", budget.budget_no);
      if (res?.status === "success") {
        toast.add({ title: t("budget.deleted", { no: budget.budget_no }), type: "success" });
        router.push(pathname);
      } else toast.add({ title: Object.values(res?.errors ?? {})[0]?.[0] ?? res?.message ?? t("budget.error"), type: "danger" });
    });

  const pct = (v) => (fob.net > 0 ? `${n((v / fob.net) * 100, 2)}%` : "");
  const summary = summaryBy === "fabric_type" ? budget.fabric_type_summary : budget.fabrication_summary;
  const wovenSummary = (wovenBy === "fabric_type" ? budget.woven_fabric_type_summary : budget.woven_fabrication_summary) ?? { rows: [], total: {} };
  // Knit's tables (yarn kg) and woven's (fabric yds) each show when the budget has that kind of style.
  const hasKnit = (budget.fabric_type_summary?.rows ?? []).length > 0;
  const hasWoven = (budget.woven_delivery_summary?.rows ?? []).length > 0;
  // One row per style (its requisitions together — a style's colours / POs are requisitions of their own).
  const styleRows = useMemo(() => {
    const groups = new Map();
    for (const r of budget.requisitions) {
      const key = `${String(r.style ?? "").trim().toLowerCase() || `#${r.serial_no}`}|${r.entry_type}`;
      const g = groups.get(key) ?? { serials: [], style: r.style, entry_type: r.entry_type, items: new Set(), order_qty: 0, total_req: 0 };
      g.serials.push(r.serial_no);
      if (r.item) g.items.add(r.item);
      g.order_qty += Number(r.order_qty) || 0;
      g.total_req += Number(r.total_req) || 0;
      groups.set(key, g);
    }
    return [...groups.values()];
  }, [budget.requisitions]);

  return (
    <div className="flex flex-col gap-3 text-[12px]">
      {embedded ? null : <section className="flex flex-wrap items-center gap-2 rounded-lg border border-border bg-surface px-3 py-2">
        {editable ? (
          <Button type="button" size="sm" onClick={save} disabled={pending || !dirty}>
            <Save /> {t("budget.save")}
          </Button>
        ) : null}
        {editable ? (
          <>
            <Button type="button" size="sm" variant="outline" onClick={() => setStyleModal("add")} disabled={dirty}>
              <Plus /> {t("budget.add_style")}
            </Button>
            <Button type="button" size="sm" variant="outline" onClick={() => setStyleModal("remove")} disabled={dirty || budget.requisitions.length < 2}>
              <Minus /> {t("budget.remove_style")}
            </Button>
          </>
        ) : null}
        {budget.can_delete ? (
          <Button type="button" size="sm" variant="outline" onClick={() => setDeleting(true)}>
            <Trash2 /> {t("budget.delete")}
          </Button>
        ) : null}
        {/* Norban's budget page: Print (Landscape) and Print Portrait, the Fabric Type / Fabrication summary as chosen here. */}
        <PrintLinks budgetNo={budget.budget_no} kinds={["budget", "portrait"]} extra={summaryBy === "fabrication" ? { summary: "fabrication" } : {}} />
        {dirty ? <span className="rounded bg-warning-subtle px-2 py-0.5 font-semibold text-warning">{t("budget.unsaved")}</span> : null}
        <span className="ml-auto flex items-center gap-2">
          <a href={`/pre-budget/budget-overview?budget=${encodeURIComponent(budget.budget_no)}`} className="inline-flex h-8 items-center rounded-md border border-border-strong bg-surface px-3 text-[12px] font-medium hover:border-brand">{t("overview.button")}</a>
          <LoadBudget budgets={budgets} current={budget.budget_no} />
        </span>
      </section>}

      <div className="grid gap-3 lg:grid-cols-[1fr_auto]">
        <section className="rounded-lg border border-border bg-surface p-4">
          <h2 className="text-lg font-semibold">
            {t("budget.title")}: {budget.budget_no}
            <span className="ml-2 align-middle"><StatusPill status={budget.status} /></span>
          </h2>
          {/* Cancelled by its order (Order Summary): kept as it was, with why; its revision budgets again. */}
          {budget.cancelled ? (
            <p className="mt-2 rounded border border-danger/40 bg-danger-subtle px-3 py-1.5 text-[12px] font-medium text-danger">
              {t("cancel.banner", {
                at: budget.cancelled.at ? new Date(budget.cancelled.at).toLocaleString("en-GB", { day: "2-digit", month: "short", year: "2-digit", hour: "2-digit", minute: "2-digit", timeZone: "Asia/Dhaka", hourCycle: "h12" }).replace(/\b[ap]m\b/, (x) => x.toUpperCase()) : "—",
                by: budget.cancelled.by ?? "—",
                reason: budget.cancelled.reason ?? "—",
              })}
            </p>
          ) : null}
          {budget.revisions?.length > 1 ? (
            <p className="mt-1 flex flex-wrap items-center gap-1.5 text-[11px]">
              <span className="text-foreground-muted">{t("final.revisions")}:</span>
              {budget.revisions.map((r) => (
                <a key={r.budget_no} href={`/pre-budget/create-budget?budget=${encodeURIComponent(r.budget_no)}`} className={cn("rounded border px-1.5 py-0.5 font-mono", r.budget_no === budget.budget_no ? "border-brand bg-brand/10 font-semibold" : "border-border hover:border-brand")}>
                  {r.budget_no} · {t(`budget.status.${r.status}`)}
                </a>
              ))}
            </p>
          ) : null}
          <dl className="mt-2 grid max-w-xl grid-cols-[8rem_1fr] items-center gap-x-3 gap-y-1.5">
            <dt className="font-medium">{t("budget.f.buyer")}</dt>
            <dd>{budget.buyer}</dd>
            <dt className="font-medium">{t("budget.factory")}</dt>
            <dd>{budget.factories.join(", ") || "—"}</dd>
            <dt className="font-medium">{t("budget.contract_no")}</dt>
            <dd>
              <input className="h-8 w-full rounded border border-border-strong bg-surface px-2 disabled:bg-surface-muted" disabled={!editable} value={head.contract_no} placeholder="e.g. HAL/ZXY/8/Oct-25" onChange={(e) => { setHead((h) => ({ ...h, contract_no: e.target.value })); setDirty(true); }} />
            </dd>
          </dl>
          {embedded ? null : <Checklist t={t} check={budget.check} no={budget.budget_no} />}
        </section>
        <SummaryTable
          title={t("budget.item_summary")}
          heads={[t("budget.f.order_type"), t("budget.f.dept"), t("budget.f.item"), t("budget.f.no_of_style"), t("budget.f.order_qty")]}
          rows={budget.item_summary.rows.map((r) => [r.order_type, r.dept, r.item, n(r.no_of_style), `${n(r.order_qty)} Pcs`])}
          total={[t("budget.grand_total"), n(budget.item_summary.total.no_of_style), `${n(budget.item_summary.total.order_qty)} Pcs`]}
          span={3}
        />
      </div>

      {embedded ? null : <ApprovalPanel budget={budget} act={act} ready={!dirty && budget.check.costing && budget.check.stock && budget.check.bom} />}

      {["YARN", "WOVEN"].map((s) =>
        sections[s].lines.length ? (
          <LineTable key={s} section={s} t={t} editable={editable} lines={sections[s].lines} totals={sections[s].totals} suppliers={suppliers[s]} options={budget.supplier_options}
            onEdit={(key, patch) => edit(s, key, patch)}
            onSuppliers={(list) => { setSuppliers((all) => ({ ...all, [s]: list })); setDirty(true); }} />
        ) : null,
      )}

      <div className="grid gap-3 lg:grid-cols-3">
        <div className="flex flex-col gap-3">
        {hasKnit || !hasWoven ? (
        <section className="rounded-lg border border-border bg-surface p-3">
          <div className="mb-2 flex items-center justify-between gap-2">
            <h3 className="font-semibold">{t("budget.fabric_summary")}</h3>
            <div className="inline-flex overflow-hidden rounded border border-border">
              {["fabric_type", "fabrication"].map((k) => (
                <button key={k} type="button" onClick={() => setSummaryBy(k)} className={cn("px-2 py-0.5 text-[11px]", summaryBy === k ? "bg-brand text-brand-foreground" : "bg-surface")}>{t(`budget.f.${k}`)}</button>
              ))}
            </div>
          </div>
          <PlainTable heads={[t(`budget.f.${summaryBy}`), t("budget.f.order_qty"), t("budget.f.total_yarn")]} rows={summary.rows.map((r) => [r[summaryBy] || "—", `${n(r.order_qty)} pcs`, `${n(r.total_req)} kg`])} total={[t("budget.total"), `${n(summary.total.order_qty)} pcs`, `${n(summary.total.total_req)} kg`]} />
        </section>
        ) : null}
        {hasWoven ? (
          <section className="rounded-lg border border-border bg-surface p-3">
            <div className="mb-2 flex items-center justify-between gap-2">
              <h3 className="font-semibold">{t("budget.woven_fabric_summary")}</h3>
              <div className="inline-flex overflow-hidden rounded border border-border">
                {["fabric_type", "fabrication"].map((k) => (
                  <button key={k} type="button" onClick={() => setWovenBy(k)} className={cn("px-2 py-0.5 text-[11px]", wovenBy === k ? "bg-brand text-brand-foreground" : "bg-surface")}>{t(`budget.f.${k}`)}</button>
                ))}
              </div>
            </div>
            <PlainTable heads={[t(`budget.f.${wovenBy}`), t("budget.f.order_qty"), t("budget.f.total_fabric")]} rows={wovenSummary.rows.map((r) => [r[wovenBy] || "—", `${n(r.order_qty)} pcs`, `${n(r.total_req, 2)} yds`])} total={[t("budget.total"), `${n(wovenSummary.total.order_qty)} pcs`, `${n(wovenSummary.total.total_req, 2)} yds`]} />
          </section>
        ) : null}
        </div>
        <div className="flex flex-col gap-3">
        {hasKnit || !hasWoven ? (
        <section className="rounded-lg border border-border bg-surface p-3">
          <h3 className="mb-2 font-semibold">{t("budget.delivery_summary")}</h3>
          <PlainTable
            heads={[t("budget.f.delivery_date"), t("budget.f.no_of_style"), t("budget.f.order_qty"), t("budget.f.order_value"), t("budget.f.total_yarn")]}
            rows={budget.delivery_summary.rows.map((r) => [r.delivery_date || "—", n(r.no_of_style), `${n(r.order_qty)} pcs`, money(r.fob_value), `${n(r.total_req)} kg`])}
            total={[t("budget.grand_total"), n(budget.delivery_summary.total.no_of_style), `${n(budget.delivery_summary.total.order_qty)} pcs`, money(budget.delivery_summary.total.fob_value), `${n(budget.delivery_summary.total.total_req)} kg`]}
          />
        </section>
        ) : null}
        {hasWoven ? (
          <section className="rounded-lg border border-border bg-surface p-3">
            <h3 className="mb-2 font-semibold">{t("budget.woven_delivery_summary")}</h3>
            <PlainTable
              heads={[t("budget.f.delivery_date"), t("budget.f.no_of_style"), t("budget.f.order_qty"), t("budget.f.order_value"), t("budget.f.total_fabric")]}
              rows={budget.woven_delivery_summary.rows.map((r) => [r.delivery_date || "—", n(r.no_of_style), `${n(r.order_qty)} pcs`, money(r.fob_value), `${n(r.total_req, 2)} yds`])}
              total={[t("budget.grand_total"), n(budget.woven_delivery_summary.total.no_of_style), `${n(budget.woven_delivery_summary.total.order_qty)} pcs`, money(budget.woven_delivery_summary.total.fob_value), `${n(budget.woven_delivery_summary.total.total_req, 2)} yds`]}
            />
          </section>
        ) : null}
        </div>
        <section className="flex flex-col gap-2 rounded-lg border border-border bg-surface p-3">
          <table className="w-full border border-[#bfbfbf] text-[12px]">
            <tbody>
              <tr className="bg-[#dceaf7] font-bold">
                <td className="border border-[#bfbfbf] px-2 py-1">{t("budget.gross_fob")}</td>
                <td className="border border-[#bfbfbf] px-2 py-1 text-right tabular-nums">{money(fob.gross, 2)}</td>
              </tr>
              <tr className="bg-[#dceaf7] font-bold">
                <td className="border border-[#bfbfbf] px-2 py-1">
                  {t("budget.discount")}{" "}
                  <input className="ml-1 h-6 w-14 rounded border border-border-strong bg-white px-1 text-right disabled:bg-surface-muted" disabled={!editable} value={head.discount_percent} onChange={(e) => { setHead((h) => ({ ...h, discount_percent: e.target.value })); setDirty(true); }} aria-label={t("budget.discount")} /> %
                </td>
                <td className="border border-[#bfbfbf] px-2 py-1 text-right tabular-nums">{money(fob.discount, 2)}</td>
              </tr>
              <tr className="bg-[#dceaf7] font-bold">
                <td className="border border-[#bfbfbf] px-2 py-1">{t("budget.net_fob")}</td>
                <td className="border border-[#bfbfbf] px-2 py-1 text-right tabular-nums">{money(fob.net, 2)}</td>
              </tr>
              <tr className="font-bold">
                <td className="border border-[#bfbfbf] px-2 py-1">{t("budget.avg_fob")}</td>
                <td className="border border-[#bfbfbf] px-2 py-1 text-right tabular-nums">{money(fob.avg, 2)}</td>
              </tr>
            </tbody>
          </table>
          <table className="w-full border border-[#bfbfbf] text-[12px]">
            <thead className="bg-[#d9d9d9] text-[11px] font-bold">
              <tr>
                <th className="border border-[#bfbfbf] px-2 py-1 text-left">{t("budget.component")}</th>
                <th className="border border-[#bfbfbf] px-2 py-1 text-right">{t("budget.cost")}</th>
                <th className="border border-[#bfbfbf] px-2 py-1 text-right">{t("budget.of_fob")}</th>
              </tr>
            </thead>
            <tbody>
              {[
                ["yarn", costs.yarn],
                ...(costs.woven ? [["woven", costs.woven]] : []),
                ["knitting", costs.knitting],
                ["dyeing", costs.dyeing],
                ...(costs.yarn_dyeing ? [["yarn_dyeing", costs.yarn_dyeing]] : []),
                ...(costs.aop ? [["aop", costs.aop]] : []),
                ["fabric_total", costs.fabric_total, false, true],
                ["accessories", costs.accessories],
                ["embellishment", costs.embellishment],
                ["badge", costs.badge],
                ["total", costs.total, false, true],
                ["cm", costs.cm, false, true],
                ["others", costs.others],
              ].map(([k, v, later, bold]) => (
                <tr key={k} className={cn(bold && "bg-[#f2f2f2] font-bold")}>
                  <td className="border border-[#bfbfbf] px-2 py-1">
                    {/* CM's label carries the average CM per dozen, as Norban's (and the budget print) does. */}
                    {k === "cm" ? t("budget.c.cm", { avg: n(budget.costing?.totals?.cm_per_dz ?? 0, 2) }) : t(`budget.c.${k}`)}
                    {later ? <span className="ml-1 text-[10px] font-normal text-foreground-subtle">({t("budget.later")})</span> : null}
                  </td>
                  <td className="border border-[#bfbfbf] px-2 py-1 text-right tabular-nums">{money(v)}</td>
                  <td className="border border-[#bfbfbf] px-2 py-1 text-right tabular-nums">{pct(v)}</td>
                </tr>
              ))}
              {/* CM + the rest of the margin as one share of the net FOB (Norban's last line). */}
              <tr className="bg-[#eef2ff]">
                <td colSpan={2} className="border border-[#bfbfbf] px-2 py-1.5 text-right font-bold">{t("budget.c.cm_others")}</td>
                <td className="border border-[#bfbfbf] px-2 py-1.5 text-right text-[13px] font-bold tabular-nums">{pct((costs.cm ?? 0) + (costs.others ?? 0))}</td>
              </tr>
            </tbody>
          </table>
        </section>
      </div>

      <SummaryTable
        title={t("budget.styles_in", { n: styleRows.length, r: budget.requisitions.length })}
        heads={[t("budget.f.style"), t("budget.f.entry_type"), t("budget.requisitions_col"), t("budget.f.item"), t("budget.f.order_qty"), t("budget.f.total_req")]}
        rows={styleRows.map((g) => [g.style || "—", g.entry_type === "WOVEN" ? "Woven" : "Knit", g.serials.join(", "), [...g.items].join(", "), n(g.order_qty), `${n(g.total_req, 2)} ${g.entry_type === "WOVEN" ? "yds" : "kg"}`])}
      />

      {styleModal ? <StyleModal mode={styleModal} budget={budget} act={act} t={t} onClose={() => setStyleModal(null)} onDone={() => { setStyleModal(null); router.refresh(); }} /> : null}
      <ConfirmDialog open={deleting} onOpenChange={setDeleting} title={t("budget.delete_title", { no: budget.budget_no })} description={t("budget.delete_text")} confirmLabel={t("budget.delete")} cancelLabel={t("budget.cancel")} destructive loading={pending} onConfirm={remove} />
    </div>
  );
}

/**
 * Before Finalize (user, 2026-11-22: a new design): Knit/Dying Costing, Stock
 * Yarn Use and the Accessories BOMs as three numbered step cards — each with
 * its own icon, Done / Pending and a link to its screen — under a bar of how
 * many are done; all three done, the card says the budget can be finalized.
 */
const CHECK_SCREEN = { costing: "/pre-budget/knit-dying-costing", stock: "/pre-budget/stock-yarn-use", bom: "/pre-budget/accessories-budget-pending" };
const CHECK_ICON = { costing: Calculator, stock: Warehouse, bom: Tags };
function Checklist({ t, check, no }) {
  const keys = ["costing", "stock", "bom"];
  const done = keys.filter((k) => check[k]).length;
  const all = done === keys.length;
  return (
    <section className={cn("mt-4 rounded-lg border p-3", all ? "border-success/40 bg-success-subtle/40" : "border-border bg-surface-muted/40")}>
      <div className="mb-3 flex flex-wrap items-center gap-x-3 gap-y-1">
        <h4 className="text-[13px] font-semibold text-foreground">{t("budget.checklist")}</h4>
        <span className={cn("rounded-full px-2 py-0.5 text-[11px] font-semibold", all ? "bg-success text-white" : "bg-warning-subtle text-warning")}>
          {t("budget.check_count", { done, total: keys.length })}
        </span>
        <div className="ml-auto h-1.5 w-40 overflow-hidden rounded-full bg-border" role="progressbar" aria-valuemin={0} aria-valuemax={keys.length} aria-valuenow={done} aria-label={t("budget.checklist")}>
          <div className={cn("h-full rounded-full transition-all", all ? "bg-success" : "bg-brand")} style={{ width: `${(done / keys.length) * 100}%` }} />
        </div>
      </div>
      <ol className="grid gap-2 sm:grid-cols-3">
        {keys.map((k, i) => {
          const ok = !!check[k];
          const Icon = CHECK_ICON[k];
          return (
            <li key={k}>
              <a
                href={`${CHECK_SCREEN[k]}?budget=${encodeURIComponent(no)}`}
                className={cn(
                  "group flex h-full items-start gap-2.5 rounded-md border bg-surface p-2.5 transition-colors",
                  ok ? "border-success/40 hover:border-success" : "border-border hover:border-brand hover:bg-brand-subtle/30",
                )}
              >
                <span className={cn("relative flex size-9 shrink-0 items-center justify-center rounded-full", ok ? "bg-success-subtle text-success" : "bg-brand-subtle text-brand")}>
                  <Icon className="size-4.5" />
                  <span className={cn("absolute -top-1 -left-1 flex size-4 items-center justify-center rounded-full text-[9px] font-bold text-white", ok ? "bg-success" : "bg-foreground-muted")}>{i + 1}</span>
                </span>
                <span className="flex min-w-0 flex-1 flex-col gap-1">
                  <span className="text-[12px] leading-tight font-semibold text-foreground">{t(`budget.check.${k}`)}</span>
                  <span className={cn("inline-flex w-fit items-center gap-1 rounded px-1.5 py-0.5 text-[10.5px] font-semibold", ok ? "bg-success-subtle text-success" : "bg-warning-subtle text-warning")}>
                    {ok ? <CheckCircle2 className="size-3" /> : <Circle className="size-3" />}
                    {t(ok ? "budget.check_done" : "budget.check_pending")}
                  </span>
                </span>
                <ArrowRight className="mt-0.5 size-3.5 shrink-0 text-foreground-subtle transition-transform group-hover:translate-x-0.5 group-hover:text-brand" />
              </a>
            </li>
          );
        })}
      </ol>
      {all ? (
        <p className="mt-2 flex items-center gap-1.5 text-[11.5px] font-medium text-success">
          <CheckCircle2 className="size-3.5" /> {t("budget.check_ready")}
        </p>
      ) : null}
    </section>
  );
}

function SummaryTable({ title, heads, rows, total, span = 1 }) {
  return (
    <section className="rounded-lg border border-border bg-surface p-3">
      <h3 className="mb-2 font-semibold text-brand">{title}</h3>
      <PlainTable heads={heads} rows={rows} total={total} span={span} />
    </section>
  );
}

function PlainTable({ heads, rows, total, span = 1 }) {
  return (
    <div className="overflow-x-auto">
      <table className="w-full border border-[#bfbfbf] text-[12px]">
        <thead className="bg-[#d9d9d9] text-[11px] font-bold">
          <tr>{heads.map((h) => <th key={h} className="border border-[#bfbfbf] px-2 py-1 text-center">{h}</th>)}</tr>
        </thead>
        <tbody>
          {rows.map((r, i) => (
            <tr key={i}>{r.map((c, j) => <td key={j} className={cn("border border-[#bfbfbf] px-2 py-1", j === 0 ? "text-left" : "text-right tabular-nums")}>{c}</td>)}</tr>
          ))}
          {total ? (
            <tr className="bg-[#f2f2f2] font-bold">
              {total.map((c, j) => <td key={j} colSpan={j === 0 ? span : 1} className={cn("border border-[#bfbfbf] px-2 py-1", j === 0 ? "text-right" : "text-right tabular-nums")}>{c}</td>)}
            </tr>
          ) : null}
        </tbody>
      </table>
    </div>
  );
}

/** One section's lines: Knit Yarn Requisition or Woven Fabric Requisition. */
function LineTable({ section, t, editable, lines, totals, suppliers, options, onEdit, onSuppliers }) {
  const unit = SECTION_UNIT[section];
  const [adding, setAdding] = useState("");
  const cell = "border border-[#bfbfbf] px-1.5 py-1";
  const input = "h-7 w-full min-w-16 rounded border border-border-strong bg-white px-1 text-center tabular-nums disabled:bg-surface-muted";
  // S/L · Yarn Count, Composition (fabric: Style, Composition, Fabrication) ·
  // Req, Use from Stock, Need, PI Qty, Stock/Purchase, Excess · suppliers ·
  // Price, PI Value, Remarks.
  // null = auto (Composition; fabric's Style, Composition, Fabrication) —
  // they share what is left, never below 120px each.
  const widths = [36, ...(section === "YARN" ? [110, null] : [null, null, null]), 110, 140, 110, 110, 110, 110, ...suppliers.map(() => 110), 110, 110, 150];
  const fixedSum = widths.reduce((a, w) => a + (w ?? 0), 0);
  const autoCount = widths.filter((w) => w === null).length;
  const tableStyle = autoCount ? { tableLayout: "fixed", width: "100%", minWidth: fixedSum + autoCount * 120 } : { tableLayout: "fixed", width: fixedSum };
  return (
    <section className="rounded-lg border border-border bg-surface p-3">
      <div className="mb-2 flex flex-wrap items-center gap-2">
        <h3 className="mr-auto font-semibold text-brand">{t(`budget.section.${section}`)}</h3>
        {editable ? (
          <>
            <div className="w-64">
              <Select
                value={adding}
                onValueChange={(x) => setAdding(x ?? "")}
                options={[{ value: "", label: t("budget.pick_supplier") }, ...options.filter((o) => !suppliers.includes(o)).map((o) => ({ value: o, label: o }))]}
                size="sm"
                aria-label={t("budget.add_supplier")}
              />
            </div>
            <Button type="button" size="sm" variant="outline" disabled={!adding} onClick={() => { onSuppliers([...suppliers, adding]); setAdding(""); }}>
              <Plus /> {t("budget.add_supplier")}
            </Button>
          </>
        ) : null}
      </div>
      <div className="overflow-x-auto">
        {/* Fixed widths (user's sizes); all fixed → the table is exactly their
            sum so none is stretched; wider than the card → it scrolls. */}
        <table className="border-separate border-spacing-0 text-[12px]" style={tableStyle}>
          <colgroup>
            {widths.map((w, k) => <col key={k} style={w === null ? undefined : { width: w }} />)}
          </colgroup>
          <thead className="bg-[#d9d9d9] text-[11px] font-bold">
            <tr>
              <th className={cell}>S/L</th>
              {section === "YARN" ? (
                <>
                  <th className={cell}>{t("budget.f.yarn_count")}</th>
                  <th className={cell}>{t("budget.f.composition")}</th>
                </>
              ) : (
                <>
                  <th className={cell}>{t("budget.f.style")}</th>
                  <th className={cell}>{t("budget.f.composition")}</th>
                  <th className={cell}>{t("budget.f.fabrication")}</th>
                </>
              )}
              <th className={cell}>{t("budget.f.total_req_unit", { unit })}</th>
              <th className={cell}>{t("budget.f.use_stock", { unit })}</th>
              <th className={cell}>{t("budget.f.need", { unit })}</th>
              <th className={cell}>{t("budget.f.pi_qty", { unit })}</th>
              <th className={cell}>{t("budget.f.stock_purchase", { unit })}</th>
              <th className={cell}>{t("budget.f.excess", { unit })}</th>
              {suppliers.map((s) => (
                <th key={s} className={cn(cell, "bg-[#fff2cc]")}>
                  <span className="flex items-center justify-center gap-1">
                    {s}
                    {editable ? (
                      <button type="button" aria-label={t("budget.remove_supplier", { name: s })} title={t("budget.remove_supplier", { name: s })} onClick={() => onSuppliers(suppliers.filter((x) => x !== s))} className="text-foreground-subtle hover:text-danger">
                        <X className="size-3" />
                      </button>
                    ) : null}
                  </span>
                </th>
              ))}
              <th className={cell}>{t("budget.f.price", { unit: unit === "kg" ? "Kg" : "Yd" })}</th>
              <th className={cell}>{t("budget.f.pi_value")}</th>
              <th className={cell}>{t("budget.f.remarks")}</th>
            </tr>
          </thead>
          <tbody>
            {lines.map((l, i) => (
              <tr key={l.key}>
                <td className={cn(cell, "text-center")}>{i + 1}</td>
                {section === "YARN" ? (
                  <>
                    <td className={cn(cell, "text-center font-semibold")}>{l.yarn_count}</td>
                    <td className={cn(cell, "text-center")} title={l.fabric_types.join(", ")}>{l.composition}</td>
                  </>
                ) : (
                  <>
                    <td className={cn(cell, "text-center font-semibold")}>{l.style}</td>
                    <td className={cn(cell, "text-center")}>{l.composition}</td>
                    <td className={cell}>{l.fabrication}</td>
                  </>
                )}
                <td className={cn(cell, "text-right tabular-nums")}>{n(l.total_req)} {unit}</td>
                <td className={cn(cell, "text-center text-foreground-subtle")}>{l.stock_qty ? `${n(l.stock_qty)} ${unit} | ${money(l.stock_value)}` : "—"}</td>
                <td className={cell}><input className={cn(input, "bg-[#f2f2f2]")} disabled={!editable} value={l.tp.need_to_purchase} placeholder={String(upToWhole(Math.max(l.total_req - l.stock_qty, 0)))} onChange={(e) => onEdit(l.key, { need_to_purchase: e.target.value })} aria-label={t("budget.f.need", { unit })} /></td>
                <td className={cn(cell, "bg-[#dceaf7]")}><input className={input} disabled={!editable} value={l.tp.pi_qty} placeholder={String(l.need)} onChange={(e) => onEdit(l.key, { pi_qty: e.target.value })} aria-label={t("budget.f.pi_qty", { unit })} /></td>
                <td className={cn(cell, "bg-[#dceaf7] text-right tabular-nums")}>{n(l.stockPurchase)} {unit}</td>
                <td className={cn(cell, "text-center tabular-nums")}>
                  <span className={cn("font-semibold", l.excess < 0 ? "text-danger" : l.excess > 0 ? "text-success" : "")}>{l.excess > 0 ? "+" : ""}{n(l.excess)} {unit}</span>
                  {l.excess < 0 ? (
                    <span className="mt-0.5 flex flex-col items-start gap-0.5 text-[10px]">
                      {["PURCHASE", "NOT_PURCHASE"].map((a) => (
                        <label key={a} className="flex items-center gap-0.5">
                          <input type="radio" disabled={!editable} name={`${section}-${l.key}`} checked={l.tp.shortage_action === a} onChange={() => onEdit(l.key, { shortage_action: a })} /> {t(`budget.action.${a}`)}
                        </label>
                      ))}
                    </span>
                  ) : null}
                </td>
                {suppliers.map((s) => (
                  <td key={s} className={cn(cell, "bg-[#fffbeb]")}>
                    <input className={cn(input, l.lowest !== null && num(l.tp.supplier_prices?.[s]) === l.lowest && "font-bold text-success")} disabled={!editable} value={l.tp.supplier_prices?.[s] ?? ""} onChange={(e) => onEdit(l.key, { supplier_prices: { ...l.tp.supplier_prices, [s]: e.target.value } })} aria-label={`${s} ${l.key}`} />
                  </td>
                ))}
                <td className={cell}><input className={input} disabled={!editable} value={l.tp.price} placeholder={l.lowest === null ? "" : String(l.lowest)} onChange={(e) => onEdit(l.key, { price: e.target.value })} aria-label={t("budget.f.price", { unit })} /></td>
                <td className={cn(cell, "text-right font-semibold tabular-nums")}>{money(l.value)}</td>
                <td className={cell}><input className={cn(input, "min-w-28 text-left")} disabled={!editable} value={l.tp.remarks} onChange={(e) => onEdit(l.key, { remarks: e.target.value })} aria-label={t("budget.f.remarks")} /></td>
              </tr>
            ))}
            <tr className="bg-[#f2f2f2] font-bold">
              <td className={cn(cell, "text-right")} colSpan={section === "YARN" ? 3 : 4}>{t("budget.grand_total")}</td>
              <td className={cn(cell, "text-right tabular-nums")}>{n(totals.total_req)} {unit}</td>
              <td className={cn(cell, "text-center tabular-nums")}>{totals.stock_qty ? `${n(totals.stock_qty)} ${unit} | ${money(totals.stock_value)}` : "—"}</td>
              <td className={cn(cell, "text-right tabular-nums")}>{n(totals.need)} {unit}</td>
              <td className={cn(cell, "text-right tabular-nums")}>{n(totals.pi)} {unit}</td>
              <td className={cn(cell, "text-right tabular-nums")}>{n(totals.stockPurchase)} {unit}</td>
              <td className={cn(cell, "text-center tabular-nums")}>{totals.excess > 0 ? "+" : ""}{n(totals.excess)} {unit}</td>
              {suppliers.map((s) => <td key={s} className={cell} />)}
              <td className={cell} />
              <td className={cn(cell, "text-right tabular-nums")}>{money(totals.value)}</td>
              <td className={cell} />
            </tr>
          </tbody>
        </table>
      </div>
      <dl className="mt-2 ml-auto grid w-fit grid-cols-[auto_auto] gap-x-6 gap-y-0.5 text-right font-semibold">
        <dt>{t("budget.stock_value")}</dt>
        <dd className="tabular-nums">{money(totals.stock_value)}</dd>
        <dt>{t("budget.short_value")}</dt>
        <dd className="tabular-nums">{money(totals.short)}</dd>
        <dt>{t("budget.grand_value")}</dt>
        <dd className="tabular-nums">{money(totals.grand)}</dd>
      </dl>
    </section>
  );
}

/** Add Style (the buyer's free requisitions) / Remove Style (this budget's). */
function StyleModal({ mode, budget, act, t, onClose, onDone }) {
  const toast = useToastManager();
  const [rows, setRows] = useState(mode === "remove" ? budget.requisitions : null);
  const [ticked, setTicked] = useState(() => new Set());
  const [pending, start] = useTransition();
  useEffect(() => {
    if (mode !== "add") return;
    act("budgetCandidates", budget.budget_no).then((res) => setRows(res?.status === "success" ? res.data.rows : []));
  }, [mode, act, budget.budget_no]);
  const go = () =>
    start(async () => {
      const res = await act(mode === "add" ? "addStyles" : "removeStyles", budget.budget_no, [...ticked]);
      if (res?.status === "success") {
        toast.add({ title: t(mode === "add" ? "budget.styles_added" : "budget.styles_removed", { n: ticked.size }), type: "success" });
        onDone();
      } else toast.add({ title: Object.values(res?.errors ?? {})[0]?.[0] ?? res?.message ?? t("budget.error"), type: "danger" });
    });
  return (
    <Modal open onOpenChange={(o) => !o && onClose()} title={t(mode === "add" ? "budget.add_style" : "budget.remove_style")} description={t(mode === "add" ? "budget.add_style_hint" : "budget.remove_style_hint")} className="max-w-3xl"
      footer={<><Button variant="outline" onClick={onClose}>{t("budget.cancel")}</Button><Button onClick={go} disabled={pending || ticked.size === 0}>{t(mode === "add" ? "budget.add_style" : "budget.remove_style")} ({ticked.size})</Button></>}>
      {rows === null ? (
        <p className="py-6 text-center text-foreground-muted">{t("budget.loading")}</p>
      ) : rows.length === 0 ? (
        <p className="py-6 text-center text-foreground-muted">{t("budget.no_free")}</p>
      ) : (
        <div className="max-h-[55vh] overflow-auto text-[12px]">
          <table className="w-full">
            <thead className="sticky top-0 bg-surface-muted text-[11px] font-bold uppercase">
              <tr>
                <th className="px-2 py-1" />
                <th className="px-2 py-1">{t("budget.f.serial")}</th>
                <th className="px-2 py-1">{t("budget.f.entry_type")}</th>
                <th className="px-2 py-1">{t("budget.f.style")}</th>
                <th className="px-2 py-1">{t("budget.f.item")}</th>
                <th className="px-2 py-1 text-right">{t("budget.f.order_qty")}</th>
              </tr>
            </thead>
            <tbody>
              {rows.map((r) => (
                <tr key={r.id} className="border-t border-border">
                  <td className="px-2 py-1"><input type="checkbox" checked={ticked.has(r.id)} aria-label={t("budget.tick", { no: r.serial_no })} onChange={(e) => setTicked((s) => { const nx = new Set(s); e.target.checked ? nx.add(r.id) : nx.delete(r.id); return nx; })} /></td>
                  <td className="px-2 py-1 text-center">{r.serial_no}</td>
                  <td className="px-2 py-1 text-center">{r.entry_type === "WOVEN" ? "Woven" : "Knit"}</td>
                  <td className="px-2 py-1 font-semibold">{r.style}</td>
                  <td className="px-2 py-1">{r.item}</td>
                  <td className="px-2 py-1 text-right tabular-nums">{n(r.order_qty)}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
    </Modal>
  );
}

export { CreateBudget, BudgetPage };
