"use client";

import { useMemo, useState, useTransition } from "react";
import Link from "next/link";
import { usePathname, useRouter } from "next/navigation";
import { ArrowLeft, CheckCircle2, Circle, FolderOpen, Lock, Save, X } from "lucide-react";
import { cn } from "@/lib/utils";
import { Select } from "@/components/ui/select";
import { Button, buttonVariants } from "@/components/ui/button";
import { useToastManager } from "@/components/ui/toast";
import { useT } from "@/lib/i18n";
import { PrintLinks } from "./print/print-links";

/*
 * Pre-Budget's work on a budget (AnnotechERP 33/34, plan step 4; Norban's
 * costing, store stock entry and declaration):
 *
 *  - WorkList: the budgets of a screen, Pending / Done, each opened by its number.
 *  - CostingForm (Knit/Dying Costing): the Dollar Rate; per fabrication the
 *    Knitting, Dyeing Finishing, Yarn Dyeing and AOP rates (BDT per kg) with
 *    their BDT and USD values; the two notes; per style Embellishment and CM
 *    (USD per dozen) and the Badge Cost (user, 2026-11-19) — both one row per
 *    style, its requisitions' Order Qty added up. Save keeps it
 *    open, Complete marks it done (Dollar Rate, each fabrication's Knitting
 *    and Dyeing rate and each style's CM needed).
 *  - StockForm (Stock Yarn Use): each budget line's stock lots, or No stock;
 *    a line's lots cannot pass its Total Req. Complete marks it done.
 *  - Declaration (Stock Yarn Budget Declaration): Import / Local yarn and
 *    fabric used from stock, with Print.
 */

/** A figure as it is, to three places at most (the real Total Req behind a rounded one). */
const exact = (v) => (v === null || v === undefined || Number.isNaN(Number(v)) ? "" : Number(v).toLocaleString("en-US", { maximumFractionDigits: 3 }));
const n = (v, d = 0) => (v === null || v === undefined || v === "" || Number.isNaN(Number(v)) ? "" : Number(v).toLocaleString("en-US", { minimumFractionDigits: d, maximumFractionDigits: d }));
const money = (v, d = 2) => (v === null || v === undefined || v === "" ? "" : `${Number(v) < 0 ? "-" : ""}$${n(Math.abs(Number(v)), d)}`);
const num = (v) => (v === null || v === undefined || String(v).trim() === "" || Number.isNaN(Number(v)) ? null : Number(v));
const str = (v) => (v === null || v === undefined ? "" : String(v));
const errorOf = (res, t) => Object.values(res?.errors ?? {})[0]?.[0] ?? res?.message ?? t("budget.error");
const when = (iso) => (iso ? new Date(iso).toLocaleString("en-GB", { day: "2-digit", month: "short", year: "2-digit", hour: "2-digit", minute: "2-digit", timeZone: "Asia/Dhaka", hourCycle: "h12" }).replace(/\b[ap]m\b/, (x) => x.toUpperCase()) : "");
const cell = "border border-[#bfbfbf] px-1.5 py-1";
const input = "h-7 w-full min-w-14 rounded border border-border-strong bg-white px-1 text-center tabular-nums disabled:bg-surface-muted";

/* ── The budgets of a screen ──────────────────────────────────────── */

function WorkList({ kind, rows }) {
  const t = useT("prebudget");
  const pathname = usePathname();
  const [filter, setFilter] = useState(kind === "declaration" ? "all" : "pending");
  const shown = rows.filter((r) => filter === "all" || (filter === "done" ? r.done : !r.done));
  const counts = { all: rows.length, pending: rows.filter((r) => !r.done).length, done: rows.filter((r) => r.done).length };
  return (
    <section className="rounded-lg border border-border bg-surface text-[13px]">
      <div className="flex flex-wrap items-center gap-2 border-b border-border px-4 py-3">
        <h2 className="mr-auto font-semibold">{t(`work.${kind}.title`)}</h2>
        {kind !== "declaration"
          ? ["pending", "done", "all"].map((f) => (
              <button key={f} type="button" onClick={() => setFilter(f)} className={cn("rounded-full border px-3 py-0.5 text-[12px]", filter === f ? "border-brand bg-brand text-brand-foreground" : "border-border")}>
                {t(`work.filter.${f}`)} ({counts[f]})
              </button>
            ))
          : null}
      </div>
      {shown.length === 0 ? (
        <p className="px-4 py-10 text-center text-foreground-muted">{t(`work.${kind}.empty`)}</p>
      ) : (
        <div className="overflow-x-auto">
          <table className="w-full">
            <thead className="bg-surface-muted text-left text-[11px] font-semibold tracking-wide text-foreground-muted uppercase">
              <tr>
                <th className="px-4 py-2">{t("work.f.budget_no")}</th>
                <th className="px-4 py-2">{t("work.f.buyer")}</th>
                <th className="px-4 py-2">{t("work.f.contract_no")}</th>
                <th className="px-4 py-2 text-right">{t("work.f.styles")}</th>
                <th className="px-4 py-2">{t("work.f.budget_status")}</th>
                {kind !== "declaration" ? <th className="px-4 py-2">{t(`work.${kind}.status`)}</th> : null}
                <th className="px-4 py-2 text-right">{t(`work.${kind}.amount`)}</th>
                <th className="px-4 py-2 text-right">{t("work.f.actions")}</th>
              </tr>
            </thead>
            <tbody>
              {shown.map((r) => (
                <tr key={r.budget_no} className="border-t border-border">
                  <td className="px-4 py-2 font-mono font-semibold">{r.budget_no}</td>
                  <td className="px-4 py-2">{r.buyer}</td>
                  <td className="px-4 py-2">{r.contract_no || "—"}</td>
                  <td className="px-4 py-2 text-right tabular-nums">{r.styles}</td>
                  <td className="px-4 py-2">{t(`budget.status.${r.status}`)}</td>
                  {kind !== "declaration" ? (
                    <td className="px-4 py-2">
                      <span className={cn("inline-flex items-center gap-1 rounded px-2 py-0.5 text-[11px] font-semibold", r.done ? "bg-success-subtle text-success" : "bg-warning-subtle text-warning")}>
                        {r.done ? <CheckCircle2 className="size-3" /> : <Circle className="size-3" />} {r.done ? `${t("work.done")} ${when(r.done_at)}` : t("work.pending")}
                      </span>
                    </td>
                  ) : null}
                  <td className="px-4 py-2 text-right tabular-nums">{kind === "declaration" ? `${n(r.stock_qty)} · ${money(r.stock_value)}` : money(r.cost)}</td>
                  <td className="px-4 py-2 text-right">
                    <Link href={`${pathname}?budget=${encodeURIComponent(r.budget_no)}`} className={buttonVariants({ size: "sm", variant: "outline" })}>
                      <FolderOpen /> {t("work.open")}
                    </Link>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
    </section>
  );
}

/** Pre-Budget No, Buyer, Contract No and what is done — the top of every work screen. */
function Head({ data, t, done, doneAt, extra, print = [] }) {
  const pathname = usePathname();
  return (
    <section className="flex flex-wrap items-start justify-between gap-3 rounded-lg border border-border bg-surface p-4 text-[13px]">
      <div>
        <h2 className="text-lg font-semibold">{t("work.budget_no")}: {data.budget_no}</h2>
        <dl className="mt-1 grid grid-cols-[8rem_1fr] gap-x-3 gap-y-0.5">
          <dt className="font-medium">{t("budget.f.buyer")}</dt>
          <dd>{data.buyer}</dd>
          <dt className="font-medium">{t("budget.contract_no")}</dt>
          <dd>{data.contract_no || "—"}</dd>
          <dt className="font-medium">{t("work.f.budget_status")}</dt>
          <dd>{t(`budget.status.${data.status}`)}</dd>
          {done !== undefined ? (
            <>
              <dt className="font-medium">{t("work.this_part")}</dt>
              <dd className={done ? "font-semibold text-success" : "font-semibold text-warning"}>{done ? `${t("work.done")} ${when(doneAt)}` : t("work.pending")}</dd>
            </>
          ) : null}
        </dl>
        {extra}
        {/* Finalized (waiting for approval or approved): Knit/Dying Costing, Stock Yarn Use and the BOMs stay as they are (user 2026-11-22). */}
        {!data.editable && ["PENDING", "APPROVED", "REVISED", "CANCELLED"].includes(data.status) ? (
          <p className="mt-2 flex items-center gap-1.5 rounded border border-warning/50 bg-warning-subtle px-3 py-1.5 text-[12px] font-medium text-warning">
            <Lock className="size-3.5" /> {t("work.locked")}
          </p>
        ) : null}
      </div>
      <div className="flex gap-2">
        {print.length ? <PrintLinks budgetNo={data.budget_no} kinds={print} /> : null}
        <Link href={`/pre-budget/create-budget?budget=${encodeURIComponent(data.budget_no)}`} className={buttonVariants({ size: "sm", variant: "outline" })}>
          {t("work.open_budget")}
        </Link>
        <Link href={pathname} className={buttonVariants({ size: "sm", variant: "outline" })}>
          <ArrowLeft /> {t("work.back")}
        </Link>
      </div>
    </section>
  );
}

/* ── Knit/Dying Costing ───────────────────────────────────────────── */

const RATES = ["knit", "dye", "yarn_dye", "aop"];

/** `embedded` (Budget Finalized): without the budget head (number, links back). */
function CostingForm({ data, act, embedded = false }) {
  const t = useT("prebudget");
  const router = useRouter();
  const toast = useToastManager();
  const [pending, start] = useTransition();
  const c = data.costing;
  const editable = data.editable;
  const [dollar, setDollar] = useState(str(c.dollar_rate));
  const [notes, setNotes] = useState({ knit: c.knit_note ?? "", dye: c.dye_note ?? "" });
  const [fabrics, setFabrics] = useState(() => Object.fromEntries(c.fabrics.map((f) => [f.fabrication, Object.fromEntries(RATES.map((k) => [k, str(f[`${k}_rate`])]))])));
  // One row per style: its Embellishment, CM and Badge (wastage factor, cost per dozen, supplier, remarks).
  const [styles, setStyles] = useState(() =>
    Object.fromEntries(c.styles.map((s) => [s.style, { emb: str(s.emb_per_dz), cm: str(s.cm_per_dz), wastage: str(s.badge_wastage), badge: str(s.badge_per_dz), supplier: s.badge_supplier ?? "", remarks: s.badge_remarks ?? "" }])),
  );
  const [dirty, setDirty] = useState(false);
  const touch = (fn) => (...a) => { fn(...a); setDirty(true); };
  const setStyle = (style, patch) => touch(setStyles)((all) => ({ ...all, [style]: { ...all[style], ...patch } }));

  // Worked out as typed (BudgetSheet::costing).
  const live = useMemo(() => {
    const rate = num(dollar);
    const usd = (bdt) => (rate ? bdt / rate : 0);
    const fab = c.fabrics.map((f) => {
      const out = { ...f };
      for (const k of RATES) {
        const r = num(fabrics[f.fabrication]?.[k]);
        out[`${k}_bdt`] = r === null ? 0 : f.qty * r;
        out[`${k}_usd`] = usd(out[`${k}_bdt`]);
      }
      return out;
    });
    const sty = c.styles.map((s) => {
      const st = styles[s.style] ?? {};
      const withW = s.dz * (num(st.wastage) ?? 1);
      return { ...s, emb_usd: s.dz * (num(st.emb) ?? 0), cm_usd: s.dz * (num(st.cm) ?? 0), withW, badge_usd: withW * (num(st.badge) ?? 0) };
    });
    const sum = (rows, k) => rows.reduce((a, r) => a + (r[k] ?? 0), 0);
    const kg = sum(fab, "qty");
    const dz = sum(sty, "dz");
    return { fab, sty, totals: { kg, knit_bdt: sum(fab, "knit_bdt"), dye_bdt: sum(fab, "dye_bdt"), ...Object.fromEntries(RATES.flatMap((k) => [[`${k}_bdt`, sum(fab, `${k}_bdt`)], [`${k}_usd`, sum(fab, `${k}_usd`)]])), order_qty: sum(sty, "order_qty"), dz, emb: sum(sty, "emb_usd"), cm: sum(sty, "cm_usd"), badge: sum(sty, "badge_usd") } };
  }, [c, dollar, fabrics, styles]);

  const save = (complete) =>
    start(async () => {
      const body = {
        dollar_rate: num(dollar), knit_note: notes.knit || null, dye_note: notes.dye || null, complete,
        fabrics: c.fabrics.map((f) => ({ fabrication: f.fabrication, ...Object.fromEntries(RATES.map((k) => [`${k}_rate`, num(fabrics[f.fabrication]?.[k])])) })),
        styles: c.styles.map((s) => {
          const st = styles[s.style] ?? {};
          return { style: s.style, emb_per_dz: num(st.emb), cm_per_dz: num(st.cm), badge_wastage: num(st.wastage), badge_per_dz: num(st.badge), badge_supplier: st.supplier || null, badge_remarks: st.remarks || null };
        }),
      };
      const res = await act("saveCosting", data.budget_no, body);
      if (res?.status === "success") {
        setDirty(false);
        toast.add({ title: t(complete ? "costing.completed" : "costing.saved"), type: "success" });
        router.refresh();
      } else toast.add({ title: errorOf(res, t), type: "danger" });
    });

  const T = live.totals;
  return (
    <div className="flex flex-col gap-3 text-[12px]">
      {embedded ? null : <Head data={data} t={t} done={data.check.costing} doneAt={data.check.costing_at} print={["costing"]} />}

      <section className="rounded-lg border border-border bg-surface p-3">
        <div className="mb-2 flex flex-wrap items-center gap-x-6 gap-y-2">
          <h3 className="mr-auto text-base font-semibold text-brand">{t("costing.knit_dye")}</h3>
          <span className="font-semibold">{t("costing.avg_knit")}: {T.kg ? n(T.knit_bdt / T.kg, 2) : "—"} Tk/kg</span>
          <span className="font-semibold">{t("costing.avg_dye")}: {T.kg ? n(T.dye_bdt / T.kg, 2) : "—"} Tk/kg</span>
          <label className="flex items-center gap-2 font-semibold">
            {t("costing.dollar_rate")} <span className="text-danger">*</span>
            <input className={cn(input, "w-24")} disabled={!editable} value={dollar} onChange={(e) => touch(setDollar)(e.target.value)} aria-label={t("costing.dollar_rate")} />
          </label>
        </div>
        <div className="overflow-x-auto">
          <table className="w-full border-separate border-spacing-0">
            <thead className="bg-[#d9d9d9] text-[11px] font-bold">
              <tr>
                <th className={cell}>{t("costing.f.fabrication")}</th>
                <th className={cell}>{t("costing.f.qty")}</th>
                {RATES.map((k) => (
                  <th key={k} colSpan={3} className={cell}>{t(`costing.rate.${k}`)}{k === "knit" || k === "dye" ? <span className="text-danger"> *</span> : null}</th>
                ))}
              </tr>
              <tr>
                <th className={cell} />
                <th className={cell} />
                {RATES.map((k) => [
                  <th key={`${k}r`} className={cell}>{t("costing.f.rate")}</th>,
                  <th key={`${k}b`} className={cell}>{t("costing.f.bdt")}</th>,
                  <th key={`${k}u`} className={cell}>{t("costing.f.usd")}</th>,
                ])}
              </tr>
            </thead>
            <tbody>
              {live.fab.map((f) => (
                <tr key={f.fabrication}>
                  <td className={cell}>{f.fabrication || "—"}</td>
                  <td className={cn(cell, "text-right tabular-nums")}>{n(f.qty)}</td>
                  {RATES.map((k) => [
                    <td key={`${k}r`} className={cell}><input className={input} disabled={!editable} value={fabrics[f.fabrication]?.[k] ?? ""} onChange={(e) => touch(setFabrics)((all) => ({ ...all, [f.fabrication]: { ...all[f.fabrication], [k]: e.target.value } }))} aria-label={`${t(`costing.rate.${k}`)} ${f.fabrication}`} /></td>,
                    <td key={`${k}b`} className={cn(cell, "text-right tabular-nums")}>{n(f[`${k}_bdt`], 2)} Tk</td>,
                    <td key={`${k}u`} className={cn(cell, "text-right tabular-nums")}>{money(f[`${k}_usd`])}</td>,
                  ])}
                </tr>
              ))}
              <tr className="bg-[#f2f2f2] font-bold">
                <td className={cn(cell, "text-right")}>{t("budget.total")}</td>
                <td className={cn(cell, "text-right tabular-nums")}>{n(T.kg)}</td>
                {RATES.map((k) => [<td key={`${k}r`} className={cell} />, <td key={`${k}b`} className={cn(cell, "text-right tabular-nums")}>{n(T[`${k}_bdt`], 2)} Tk</td>, <td key={`${k}u`} className={cn(cell, "text-right tabular-nums")}>{money(T[`${k}_usd`])}</td>])}
              </tr>
            </tbody>
          </table>
        </div>
        <div className="mt-3 grid gap-3 md:grid-cols-2">
          {["knit", "dye"].map((k) => (
            <label key={k} className="flex flex-col gap-1">
              <span className="text-[11px] text-foreground-muted">{t(`costing.note_${k}`)}</span>
              <textarea rows={2} className="rounded border border-border-strong bg-surface px-2 py-1 disabled:bg-surface-muted" disabled={!editable} value={notes[k]} placeholder={t(`costing.note_${k}_ph`)} onChange={(e) => touch(setNotes)((x) => ({ ...x, [k]: e.target.value }))} />
            </label>
          ))}
        </div>
      </section>

      <section className="rounded-lg border border-border bg-surface p-3">
        <div className="mb-2 flex items-center justify-between">
          <h3 className="text-base font-semibold text-brand">{t("costing.emb")} <span className="text-[11px] font-normal text-foreground-muted">({t("costing.usd_inputs")})</span></h3>
          <span className="font-semibold">{t("costing.cm_per_dz")}: {T.dz ? money(T.cm / T.dz) : "—"}</span>
        </div>
        <table className="w-full border-separate border-spacing-0">
          <thead className="bg-[#d9d9d9] text-[11px] font-bold">
            <tr>
              {["sl", "style", "order_qty", "dz", "emb_per_dz", "emb_value", "cm_per_dz", "cm_value"].map((h) => <th key={h} className={cell}>{t(`costing.f.${h}`)}{h === "cm_per_dz" ? <span className="text-danger"> *</span> : null}</th>)}
            </tr>
          </thead>
          <tbody>
            {live.sty.map((s, i) => (
              <tr key={s.style}>
                <td className={cn(cell, "text-center")}>{i + 1}</td>
                <td className={cn(cell, "text-center font-semibold")}>{s.style || "—"}</td>
                <td className={cn(cell, "text-right tabular-nums")}>{n(s.order_qty)}</td>
                <td className={cn(cell, "text-right tabular-nums")}>{n(s.dz, 2)}</td>
                <td className={cell}><input className={input} disabled={!editable} value={styles[s.style]?.emb ?? ""} onChange={(e) => setStyle(s.style, { emb: e.target.value })} aria-label={`${t("costing.f.emb_per_dz")} ${s.style}`} /></td>
                <td className={cn(cell, "text-right tabular-nums")}>{money(s.emb_usd)}</td>
                <td className={cell}><input className={input} disabled={!editable} value={styles[s.style]?.cm ?? ""} onChange={(e) => setStyle(s.style, { cm: e.target.value })} aria-label={`${t("costing.f.cm_per_dz")} ${s.style}`} /></td>
                <td className={cn(cell, "text-right tabular-nums")}>{money(s.cm_usd)}</td>
              </tr>
            ))}
            <tr className="bg-[#f2f2f2] font-bold">
              <td className={cn(cell, "text-right")} colSpan={2}>{t("budget.total")}</td>
              <td className={cn(cell, "text-right tabular-nums")}>{n(T.order_qty)}</td>
              <td className={cell} />
              <td className={cell} />
              <td className={cn(cell, "text-right tabular-nums")}>{money(T.emb)}</td>
              <td className={cell} />
              <td className={cn(cell, "text-right tabular-nums")}>{money(T.cm)}</td>
            </tr>
          </tbody>
        </table>
      </section>

      <section className="rounded-lg border border-border bg-surface p-3">
        <h3 className="mb-2 text-base font-semibold text-brand">{t("costing.badge")} <span className="text-[11px] font-normal text-foreground-muted">({t("costing.usd_inputs")})</span></h3>
        <div className="overflow-x-auto">
          <table className="w-full border-separate border-spacing-0">
            <thead className="bg-[#c6d9f1] text-[11px] font-bold">
              <tr>{["sl", "style_no", "order_qty_pcs", "qty_dz", "wastage", "qty_dz_with", "badge_per_dz", "badge_total", "supplier", "remarks"].map((h) => <th key={h} className={cell}>{t(`costing.b.${h}`)}</th>)}</tr>
            </thead>
            <tbody>
              {live.sty.map((s, i) => {
                const st = styles[s.style] ?? {};
                return (
                  <tr key={s.style}>
                    <td className={cn(cell, "text-center")}>{i + 1}</td>
                    <td className={cn(cell, "text-center font-semibold")}>{s.style || "—"}</td>
                    <td className={cn(cell, "text-right tabular-nums")}>{n(s.order_qty)}</td>
                    <td className={cn(cell, "text-right tabular-nums")}>{n(s.dz, 2)}</td>
                    <td className={cell}><input className={input} disabled={!editable} value={st.wastage ?? ""} placeholder="1.030" onChange={(e) => setStyle(s.style, { wastage: e.target.value })} aria-label={`${t("costing.b.wastage")} ${s.style}`} /></td>
                    <td className={cn(cell, "text-right tabular-nums")}>{n(s.withW, 2)}</td>
                    <td className={cell}><input className={input} disabled={!editable} value={st.badge ?? ""} onChange={(e) => setStyle(s.style, { badge: e.target.value })} aria-label={`${t("costing.b.badge_per_dz")} ${s.style}`} /></td>
                    <td className={cn(cell, "text-right font-semibold tabular-nums")}>{money(s.badge_usd)}</td>
                    <td className={cell}>
                      <div className="min-w-32">
                        <Select size="sm" disabled={!editable} value={st.supplier ?? ""} onValueChange={(x) => setStyle(s.style, { supplier: x ?? "" })} options={[{ value: "", label: "—" }, ...data.supplier_options.map((o) => ({ value: o, label: o }))]} aria-label={`${t("costing.b.supplier")} ${s.style}`} />
                      </div>
                    </td>
                    <td className={cell}><input className={cn(input, "min-w-32 text-left")} disabled={!editable} value={st.remarks ?? ""} onChange={(e) => setStyle(s.style, { remarks: e.target.value })} aria-label={`${t("costing.b.remarks")} ${s.style}`} /></td>
                  </tr>
                );
              })}
              <tr className="bg-[#f2f2f2] font-bold">
                <td className={cn(cell, "text-right")} colSpan={2}>{t("budget.total")}</td>
                <td className={cn(cell, "text-right tabular-nums")}>{n(T.order_qty)}</td>
                <td className={cell} colSpan={4} />
                <td className={cn(cell, "text-right tabular-nums")}>{money(T.badge)}</td>
                <td className={cell} colSpan={2} />
              </tr>
            </tbody>
          </table>
        </div>
      </section>

      {editable ? (
        <div className="flex justify-end gap-2">
          {dirty ? <span className="self-center rounded bg-warning-subtle px-2 py-0.5 font-semibold text-warning">{t("budget.unsaved")}</span> : null}
          <Button type="button" variant="outline" onClick={() => save(false)} disabled={pending}><Save /> {t("costing.save")}</Button>
          <Button type="button" onClick={() => save(true)} disabled={pending}><CheckCircle2 /> {t("costing.complete")}</Button>
        </div>
      ) : null}
    </div>
  );
}

/* ── Stock Yarn Use ───────────────────────────────────────────────── */

const LOT_FIELDS = ["purchase_contract_no", "stock_contract_no", "mill", "origin", "lc_no", "lot_no", "qty", "price"];
const blankLot = () => Object.fromEntries(LOT_FIELDS.map((f) => [f, ""]));
/** The Qty column's place among the lot columns: a line's Total Req and Allocated stand over it. */
const QTY_AT = LOT_FIELDS.indexOf("qty");

function StockForm({ data, act }) {
  const t = useT("prebudget");
  const router = useRouter();
  const toast = useToastManager();
  const [pending, start] = useTransition();
  const editable = data.editable;
  const lines = useMemo(() => ["YARN", "WOVEN"].flatMap((s) => data.sections[s].lines.map((l) => ({ ...l, section: s, id: `${s}|${l.key}` }))), [data]);
  const [state, setState] = useState(() => Object.fromEntries(lines.map((l) => [l.id, { no_stock: l.no_stock, lots: l.lots.length ? l.lots.map((lot) => Object.fromEntries(LOT_FIELDS.map((f) => [f, str(lot[f])]))) : [blankLot()] }])));
  const [dirty, setDirty] = useState(false);
  const change = (id, fn) => { setState((all) => ({ ...all, [id]: fn(all[id]) })); setDirty(true); };
  // One click puts what the line still needs into a lot (user, 2026-11-28): the exact Total Req less the
  // other lots — the shown Total Req is rounded, so typing it could pass the real (fractional) one.
  const fill = (l, at) =>
    change(l.id, (x) => {
      const i = at ?? Math.max(0, x.lots.findIndex((lot) => (num(lot.qty) ?? 0) === 0));
      const others = x.lots.reduce((a, lot, j) => (j === i ? a : a + (num(lot.qty) ?? 0)), 0);
      const left = Math.max(0, Math.round((Number(l.total_req ?? 0) - others) * 1000) / 1000);
      return { ...x, no_stock: false, lots: x.lots.map((y, j) => (j === i ? { ...y, qty: String(left) } : y)) };
    });

  const save = (complete) =>
    start(async () => {
      const body = {
        complete,
        lines: lines.map((l) => ({
          section: l.section, key: l.key, no_stock: state[l.id].no_stock,
          lots: state[l.id].lots.map((lot) => ({ ...Object.fromEntries(LOT_FIELDS.map((f) => [f, lot[f] === "" ? null : lot[f]])), qty: num(lot.qty), price: num(lot.price) })),
        })),
      };
      const res = await act("saveStock", data.budget_no, body);
      if (res?.status === "success") {
        setDirty(false);
        toast.add({ title: t(complete ? "stock.completed" : "stock.saved"), type: "success" });
        router.refresh();
      } else toast.add({ title: errorOf(res, t), type: "danger" });
    });

  return (
    <div className="flex flex-col gap-3 text-[12px]">
      <Head data={data} t={t} done={data.check.stock} doneAt={data.check.stock_at} print={["stock-use"]} extra={<p className="mt-3 max-w-3xl text-foreground-muted">{t("stock.rule")}</p>} />
      {["YARN", "WOVEN"].map((s) => {
        const mine = lines.filter((l) => l.section === s);
        if (!mine.length) return null;
        const unit = s === "YARN" ? "kg" : "yds";
        return (
          <section key={s} className="rounded-lg border border-border bg-surface p-3">
            <h3 className="mb-2 text-base font-semibold text-brand">{t(`budget.section.${s}`)}</h3>
            <div className="overflow-x-auto">
              <table className="w-full border-separate border-spacing-0">
                <thead className="bg-[#d9d9d9] text-[11px] font-bold">
                  <tr>
                    <th className={cell}>#</th>
                    {LOT_FIELDS.map((f) => <th key={f} className={cell}>{t(`stock.f.${f}`, { unit })}</th>)}
                    <th className={cell}>{t("stock.f.value")}</th>
                    <th className={cell} />
                  </tr>
                </thead>
                <tbody>
                  {mine.map((l, li) => {
                    const st = state[l.id];
                    const allocated = st.lots.reduce((a, lot) => a + (num(lot.qty) ?? 0), 0);
                    const over = allocated > l.total_req + 0.005;
                    return [
                      <tr key={`${l.id}-h`} className="bg-[#f2f2f2]">
                        <td className={cn(cell, "font-semibold")}>{li + 1}.</td>
                        <td className={cell} colSpan={QTY_AT}>
                          <span className="font-semibold">{s === "YARN" ? `${l.yarn_count} · ${l.composition}` : `${l.style} · ${l.fabrication}`}</span>
                          {l.fabric_types.length ? <span className="text-foreground-muted"> · {l.fabric_types.join(", ")}</span> : null}
                        </td>
                        {/* Total Req and Allocated over the Qty column (user, 2026-11-24): the lots' Qty below add up against them. */}
                        <td className={cn(cell, "whitespace-nowrap text-right leading-tight")}>
                          {editable && !st.no_stock ? (
                            <button type="button" title={t("stock.fill_hint")} onClick={() => fill(l)} className="font-semibold text-success underline decoration-dotted underline-offset-2 hover:text-success/80">
                              {t("stock.total_req")}: {n(l.total_req)} {unit}
                            </button>
                          ) : (
                            <div className="font-semibold text-success">{t("stock.total_req")}: {n(l.total_req)} {unit}</div>
                          )}
                          {/* Allocated rounded, no ".000" (user, 2026-11-28). */}
                          <div className={cn("font-semibold", over && "text-danger")}>{t("stock.allocated")}: {n(allocated)} {unit}</div>
                          {over ? <div className="text-[11px] font-semibold text-danger">{t("stock.over", { req: exact(l.total_req) })}</div> : null}
                        </td>
                        <td className={cell} colSpan={LOT_FIELDS.length - QTY_AT} />
                        <td className={cn(cell, "whitespace-nowrap")}>
                          <label className="mr-2 inline-flex items-center gap-1">
                            <input type="checkbox" disabled={!editable} checked={st.no_stock} onChange={(e) => change(l.id, (x) => ({ ...x, no_stock: e.target.checked }))} /> {t("stock.no_stock")}
                          </label>
                          {editable ? (
                            <button type="button" className="font-semibold text-brand" onClick={() => change(l.id, (x) => ({ ...x, no_stock: false, lots: [...x.lots, blankLot()] }))}>
                              + {t("stock.add_lot")}
                            </button>
                          ) : null}
                        </td>
                      </tr>,
                      ...st.lots.map((lot, i) => {
                        const set = (f, v) => change(l.id, (x) => ({ ...x, lots: x.lots.map((y, j) => (j === i ? { ...y, [f]: v } : y)) }));
                        return (
                          <tr key={`${l.id}-${i}`} className={st.no_stock ? "opacity-40" : ""}>
                            <td className={cn(cell, "text-center text-foreground-subtle")}>{i + 1}</td>
                            {LOT_FIELDS.map((f) => (
                              <td key={f} className={cell}>
                                {f === "mill" ? (
                                  <div className="min-w-32">
                                    <Select size="sm" disabled={!editable || st.no_stock} value={lot.mill ?? ""} onValueChange={(x) => set("mill", x ?? "")} options={[{ value: "", label: "--" }, ...data.mills.map((m) => ({ value: m, label: m }))]} aria-label={t("stock.f.mill")} />
                                  </div>
                                ) : f === "origin" ? (
                                  <select className={cn(input, "min-w-20")} disabled={!editable || st.no_stock} value={lot.origin} onChange={(e) => set("origin", e.target.value)} aria-label={t("stock.f.origin")}>
                                    <option value="">--</option>
                                    <option value="LOCAL">{t("stock.local")}</option>
                                    <option value="IMPORT">{t("stock.import")}</option>
                                  </select>
                                ) : f === "qty" ? (
                                  <div className="flex items-center gap-1">
                                    <input className={cn(input, "min-w-20 text-right")} disabled={!editable || st.no_stock} value={lot.qty} onChange={(e) => set("qty", e.target.value)} aria-label={`${t("stock.f.qty", { unit })} ${i + 1}`} />
                                    {editable && !st.no_stock ? (
                                      <button type="button" title={t("stock.fill_hint")} onClick={() => fill(l, i)} className="shrink-0 rounded border border-success/40 bg-success-subtle px-1.5 py-1 text-[11px] font-semibold text-success hover:bg-success/15">
                                        {t("stock.fill")}
                                      </button>
                                    ) : null}
                                  </div>
                                ) : (
                                  <input className={cn(input, f === "price" ? "text-right" : "min-w-24 text-left")} disabled={!editable || st.no_stock} value={lot[f]} onChange={(e) => set(f, e.target.value)} aria-label={`${t(`stock.f.${f}`, { unit })} ${i + 1}`} />
                                )}
                              </td>
                            ))}
                            <td className={cn(cell, "text-right tabular-nums")}>{n((num(lot.qty) ?? 0) * (num(lot.price) ?? 0), 2)}</td>
                            <td className={cn(cell, "text-center")}>
                              {editable ? (
                                <button type="button" aria-label={t("stock.remove_lot")} onClick={() => change(l.id, (x) => ({ ...x, lots: x.lots.length > 1 ? x.lots.filter((_, j) => j !== i) : [blankLot()] }))} className="text-foreground-subtle hover:text-danger">
                                  <X className="size-3.5" />
                                </button>
                              ) : null}
                            </td>
                          </tr>
                        );
                      }),
                    ];
                  })}
                </tbody>
              </table>
            </div>
          </section>
        );
      })}
      {editable ? (
        <div className="flex justify-end gap-2">
          {dirty ? <span className="self-center rounded bg-warning-subtle px-2 py-0.5 font-semibold text-warning">{t("budget.unsaved")}</span> : null}
          <Button type="button" variant="outline" onClick={() => save(false)} disabled={pending}><Save /> {t("stock.save")}</Button>
          <Button type="button" onClick={() => save(true)} disabled={pending}><CheckCircle2 /> {t("stock.complete")}</Button>
        </div>
      ) : null}
    </div>
  );
}

/* ── Stock Yarn Budget Declaration ────────────────────────────────── */

function Declaration({ data, embedded = false }) {
  const t = useT("prebudget");
  const groups = { LOCAL: data.lots.filter((l) => l.origin !== "IMPORT"), IMPORT: data.lots.filter((l) => l.origin === "IMPORT") };
  const sum = (rows, k) => rows.reduce((a, r) => a + (k === "value" ? (r.qty ?? 0) * (r.price ?? 0) : r[k] ?? 0), 0);
  const cat = (section) => ({ IMPORT: groups.IMPORT.filter((l) => l.section === section), LOCAL: groups.LOCAL.filter((l) => l.section === section) });
  return (
    <div className="flex flex-col gap-3 text-[12px] print:text-[11px]">
      <div className="print:hidden">
        {embedded ? null : <Head data={data} t={t} print={["declaration"]} />}
      </div>
      <section className="rounded-lg border border-border bg-surface p-4 print:border-0 print:p-0">
        <div className="flex flex-wrap items-start justify-between gap-3">
          <div>
            <h2 className="text-lg font-semibold">{t("decl.title")}{data.factories.length ? ` : ${data.factories.join(", ")}` : ""}</h2>
            <p className="mt-1">{t("budget.f.buyer")}: {data.buyer}</p>
          </div>
          <p className="font-semibold">{t("work.budget_no")}: {data.budget_no}</p>
        </div>
        <table className="mt-3 ml-auto border border-[#bfbfbf]">
          <thead className="text-[11px] font-bold">
            <tr>
              <th rowSpan={2} className={cn(cell, "bg-[#d9d9d9]")}>{t("decl.category")}</th>
              <th colSpan={2} className={cn(cell, "bg-[#fde9d9] text-[#c55a11]")}>{t("decl.import")}</th>
              <th colSpan={2} className={cn(cell, "bg-[#dceaf7] text-[#1f4e79]")}>{t("decl.local")}</th>
              <th colSpan={2} className={cn(cell, "bg-[#e2efda] text-[#375623]")}>{t("decl.grand")}</th>
            </tr>
            <tr>{[0, 1, 2].flatMap((i) => [<th key={`q${i}`} className={cell}>{t("decl.qty")}</th>, <th key={`v${i}`} className={cell}>{t("decl.value")}</th>])}</tr>
          </thead>
          <tbody>
            {["YARN", "WOVEN"].map((s) => {
              const c = cat(s);
              if (!c.IMPORT.length && !c.LOCAL.length) return null;
              const unit = s === "YARN" ? "kg" : "yds";
              return (
                <tr key={s}>
                  <td className={cell}>{t(`decl.cat.${s}`)}</td>
                  <td className={cn(cell, "text-right tabular-nums")}>{n(sum(c.IMPORT, "qty"))} {unit}</td>
                  <td className={cn(cell, "text-right tabular-nums")}>{money(sum(c.IMPORT, "value"))}</td>
                  <td className={cn(cell, "text-right tabular-nums")}>{n(sum(c.LOCAL, "qty"))} {unit}</td>
                  <td className={cn(cell, "text-right tabular-nums")}>{money(sum(c.LOCAL, "value"))}</td>
                  <td className={cn(cell, "text-right tabular-nums")}>{n(sum([...c.IMPORT, ...c.LOCAL], "qty"))} {unit}</td>
                  <td className={cn(cell, "text-right font-bold tabular-nums")}>{money(sum([...c.IMPORT, ...c.LOCAL], "value"))}</td>
                </tr>
              );
            })}
          </tbody>
        </table>
        {["LOCAL", "IMPORT"].map((g) =>
          groups[g].length ? (
            <div key={g} className="mt-5">
              <h3 className={cn("mb-1 font-bold", g === "LOCAL" ? "text-[#1f4e79]" : "text-[#c55a11]")}>{t(`decl.${g === "LOCAL" ? "local_yarn" : "import_yarn"}`)}</h3>
              <table className="w-full border border-[#bfbfbf]">
                <thead className="bg-[#d9d9d9] text-[11px] font-bold">
                  <tr>{["sl", "budget_no", "purchase_contract_no", "stock_contract_no", "mill", "yarn_count", "composition", "lc_no", "lot_no", "qty", "price", "value"].map((h) => <th key={h} className={cell}>{t(`decl.f.${h}`)}</th>)}</tr>
                </thead>
                <tbody>
                  {groups[g].map((l, i) => (
                    <tr key={i}>
                      <td className={cn(cell, "text-center")}>{i + 1}</td>
                      <td className={cn(cell, "text-center")}>{data.budget_no}</td>
                      <td className={cell}>{l.purchase_contract_no}</td>
                      <td className={cell}>{l.stock_contract_no}</td>
                      <td className={cell}>{l.mill}</td>
                      <td className={cn(cell, "text-center")}>{l.section === "YARN" ? l.yarn_count : l.style}</td>
                      <td className={cell}>{l.section === "YARN" ? l.composition : l.fabrication}</td>
                      <td className={cell}>{l.lc_no}</td>
                      <td className={cell}>{l.lot_no}</td>
                      <td className={cn(cell, "text-right tabular-nums")}>{n(l.qty)} {l.section === "YARN" ? "kg" : "yds"}</td>
                      <td className={cn(cell, "text-right tabular-nums")}>{money(l.price)}</td>
                      <td className={cn(cell, "text-right tabular-nums")}>{money((l.qty ?? 0) * (l.price ?? 0))}</td>
                    </tr>
                  ))}
                  <tr className="font-bold">
                    <td className={cell} colSpan={9}>{t("budget.total")}</td>
                    <td className={cn(cell, "text-right tabular-nums")}>{n(sum(groups[g], "qty"))}</td>
                    <td className={cell} />
                    <td className={cn(cell, "text-right tabular-nums")}>{money(sum(groups[g], "value"))}</td>
                  </tr>
                </tbody>
              </table>
            </div>
          ) : null,
        )}
      </section>
    </div>
  );
}

export { WorkList, CostingForm, StockForm, Declaration };
