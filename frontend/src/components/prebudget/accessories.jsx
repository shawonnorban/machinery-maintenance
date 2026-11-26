"use client";

import { useState, useTransition } from "react";
import Link from "next/link";
import { useRouter } from "next/navigation";
import { ArrowLeft, FolderOpen, ImageIcon, Plus, Printer, Search, Trash2 } from "lucide-react";
import { cn } from "@/lib/utils";
import { Button, buttonVariants } from "@/components/ui/button";
import { ConfirmDialog } from "@/components/ui/confirm-dialog";
import { useToastManager } from "@/components/ui/toast";
import { useT } from "@/lib/i18n";
import { PrintLinks, printHref } from "./print/print-links";

/*
 * Pre-Budget → Accessories Budget Pending and BOM List (AnnotechERP 33/34,
 * plan step 5; Norban's Accessories Pre-Budget):
 *
 *  - PendingList: the budgets with where their accessories stand — Pending
 *    (no BOM yet), Partial, Created (every style in a final BOM).
 *  - BudgetBoms: a budget's styles (and the BOM each is in) and its BOMs;
 *    New BOM / Finish Remaining Style(s) opens an empty worksheet.
 *  - The BOM worksheet itself is bom-worksheet.jsx (the company's columns).
 *  - BomList: one row per BOM style.
 */

const n = (v, d = 0) => (v === null || v === undefined || v === "" || Number.isNaN(Number(v)) ? "" : Number(v).toLocaleString("en-US", { minimumFractionDigits: d, maximumFractionDigits: d }));
const money = (v, d = 2) => (v === null || v === undefined || v === "" ? "" : `${Number(v) < 0 ? "-" : ""}$${n(Math.abs(Number(v)), d)}`);
const errorOf = (res, t) => Object.values(res?.errors ?? {})[0]?.[0] ?? res?.message ?? t("budget.error");
const when = (iso) => (iso ? new Date(iso).toLocaleString("en-GB", { day: "2-digit", month: "short", year: "2-digit", hour: "2-digit", minute: "2-digit", timeZone: "Asia/Dhaka", hourCycle: "h12" }).replace(/\b[ap]m\b/, (x) => x.toUpperCase()) : "");
const SCREEN = "/pre-budget/accessories-budget-pending";

const STATE_TONE = { PENDING: "bg-warning-subtle text-warning", PARTIAL: "bg-info-subtle text-info", CREATED: "bg-success-subtle text-success" };
const STATUS_TONE = { DRAFT: "bg-warning-subtle text-warning", FINAL: "bg-success-subtle text-success" };

function Pill({ tone, children }) {
  return <span className={cn("inline-flex items-center rounded px-2 py-0.5 text-[11px] font-semibold whitespace-nowrap", tone)}>{children}</span>;
}

function Artwork({ url, size = 40 }) {
  if (!url) {
    return (
      <span className="mx-auto flex items-center justify-center rounded border border-dashed border-border-strong bg-surface-muted text-foreground-muted" style={{ width: size, height: size }}>
        <ImageIcon className="size-4" />
      </span>
    );
  }
  return (
    <a href={url} target="_blank" rel="noreferrer" className="mx-auto block" style={{ width: size, height: size }}>
      {/* eslint-disable-next-line @next/next/no-img-element */}
      <img src={url} alt="" className="size-full rounded border border-border object-cover" />
    </a>
  );
}

/* ── Accessories Budget Pending ───────────────────────────────────── */

function PendingList({ data }) {
  const t = useT("prebudget");
  const [state, setState] = useState("PENDING");
  const shown = data.rows.filter((r) => state === "ALL" || r.state === state);
  return (
    <section className="rounded-lg border border-border bg-surface text-[13px]">
      <div className="flex flex-wrap items-center gap-2 border-b border-border px-4 py-3">
        <h2 className="mr-auto font-semibold">{t("screens.accessories-budget-pending")}</h2>
        {["PENDING", "PARTIAL", "CREATED", "ALL"].map((s) => (
          <button key={s} type="button" onClick={() => setState(s)} className={cn("rounded-full border px-3 py-0.5 text-[12px]", state === s ? "border-brand bg-brand text-brand-foreground" : "border-border")}>
            {t(`bom.state.${s}`)} ({data.counts[s] ?? 0})
          </button>
        ))}
      </div>
      {shown.length === 0 ? (
        <p className="px-4 py-10 text-center text-foreground-muted">{t("bom.empty_pending")}</p>
      ) : (
        <div className="overflow-x-auto">
          <table className="w-full">
            <thead className="bg-surface-muted text-left text-[11px] font-semibold tracking-wide text-foreground-muted uppercase">
              <tr>
                {["sl", "budget_no", "buyer", "contract_no", "styles", "boms", "total", "created_by", "updated_at", "state", "action"].map((h) => (
                  <th key={h} className={cn("px-3 py-2", ["styles", "boms", "total", "action"].includes(h) && "text-right")}>{t(`bom.f.${h}`)}</th>
                ))}
              </tr>
            </thead>
            <tbody>
              {shown.map((r, i) => (
                <tr key={r.budget_no} className="border-t border-border">
                  <td className="px-3 py-2 tabular-nums">{i + 1}</td>
                  <td className="px-3 py-2 font-mono font-semibold">{r.budget_no}</td>
                  <td className="px-3 py-2">{r.buyer}</td>
                  <td className="px-3 py-2">{r.contract_no || "—"}</td>
                  <td className="px-3 py-2 text-right tabular-nums">{r.budgeted}/{r.styles}</td>
                  <td className="px-3 py-2 text-right tabular-nums">{r.boms}</td>
                  <td className="px-3 py-2 text-right tabular-nums">{money(r.total)}</td>
                  <td className="px-3 py-2">{r.created_by || "—"}</td>
                  <td className="px-3 py-2 whitespace-nowrap">{when(r.updated_at)}</td>
                  <td className="px-3 py-2"><Pill tone={STATE_TONE[r.state]}>{t(`bom.state.${r.state}`)}</Pill></td>
                  <td className="px-3 py-2 text-right">
                    <Link href={`${SCREEN}?budget=${encodeURIComponent(r.budget_no)}`} className={buttonVariants({ size: "sm", variant: "outline" })}>
                      <FolderOpen /> {r.state === "PARTIAL" ? t("bom.finish") : t("bom.open")}
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

/** Pre-Budget No, Reference, Buyer, Contract No — the top of a budget's accessories pages. */
function BomHead({ data, t, children, back, print = null }) {
  return (
    <section className="flex flex-wrap items-start gap-x-8 gap-y-3 rounded-lg border border-border bg-surface p-4 text-[13px]">
      {[["budget_no", data.budget_no, "font-mono"], ["buyer", data.buyer], ["contract_no", data.contract_no || "—"], ["status", t(`budget.status.${data.status}`)]].map(([k, v, extra]) => (
        <div key={k}>
          <div className="text-[11px] text-foreground-muted">{t(`bom.f.${k}`)}</div>
          <div className={cn("font-semibold", extra)}>{v}</div>
        </div>
      ))}
      <div className="min-w-0 flex-1">{children}</div>
      <div className="flex gap-2">
        {print ? <PrintLinks budgetNo={data.budget_no} kinds={[print.kind]} extra={print.extra ?? {}} /> : null}
        <Link href={`/pre-budget/create-budget?budget=${encodeURIComponent(data.budget_no)}`} className={buttonVariants({ size: "sm", variant: "outline" })}>{t("bom.open_budget")}</Link>
        <Link href={back} className={buttonVariants({ size: "sm", variant: "outline" })}><ArrowLeft /> {t("bom.back")}</Link>
      </div>
    </section>
  );
}

/* ── A budget's BOMs ──────────────────────────────────────────────── */

function BudgetBoms({ data, act }) {
  const t = useT("prebudget");
  const router = useRouter();
  const toast = useToastManager();
  const [pending, start] = useTransition();
  const [deleting, setDeleting] = useState(null);
  const s = data.summary;
  const free = s.styles.filter((x) => !x.bom_no).length;

  const remove = () =>
    start(async () => {
      const res = await act("deleteBom", deleting);
      if (res?.status === "success") {
        toast.add({ title: t("bom.deleted", { no: deleting }), type: "success" });
        setDeleting(null);
        router.refresh();
      } else toast.add({ title: errorOf(res, t), type: "danger" });
    });

  return (
    <div className="flex flex-col gap-3 text-[13px]">
      <BomHead data={data} t={t} back={SCREEN} print={s.boms.length ? { kind: "accessories" } : null}>
        <div className="text-[11px] text-foreground-muted">{t("bom.styles_done", { done: s.budgeted, n: s.count })}</div>
        <div className="mt-1 flex flex-wrap gap-1.5">
          {s.styles.map((x) => (
            <span key={x.style} className={cn("rounded-full border px-2.5 py-0.5 text-[12px]", x.bom_status === "FINAL" ? "border-success bg-success-subtle text-success" : x.bom_no ? "border-warning bg-warning-subtle text-warning" : "border-border")} title={x.bom_no ?? ""}>
              {x.style || "—"}{x.bom_no ? ` · ${x.bom_no.split("-").pop()}` : ""}
            </span>
          ))}
        </div>
      </BomHead>
      {!data.editable && data.status !== "DRAFT" ? <p className="rounded border border-warning bg-warning-subtle px-3 py-2 text-warning">{t("bom.locked")}</p> : null}
      <section className="rounded-lg border border-border bg-surface">
        <div className="flex items-center gap-2 border-b border-border px-4 py-3">
          <h2 className="mr-auto font-semibold">{t("bom.budget_boms")}</h2>
          {data.editable && free > 0 ? (
            <Link href={`${SCREEN}?budget=${encodeURIComponent(data.budget_no)}&bom=new`} className={buttonVariants({ size: "sm" })}>
              <Plus /> {s.boms.length ? t("bom.finish") : t("bom.new_bom")}
            </Link>
          ) : free === 0 && s.count > 0 ? <span className="text-success">{t("bom.all_budgeted")}</span> : null}
        </div>
        {s.boms.length === 0 ? (
          <p className="px-4 py-10 text-center text-foreground-muted">{t("bom.empty")}</p>
        ) : (
          <table className="w-full">
            <thead className="bg-surface-muted text-left text-[11px] font-semibold tracking-wide text-foreground-muted uppercase">
              <tr>
                {["bom_no", "styles", "order_qty", "style_value", "acc_value", "cost_per_pc", "acc_pct", "status", "action"].map((h) => (
                  <th key={h} className={cn("px-3 py-2", !["bom_no", "styles", "status"].includes(h) && "text-right")}>{t(`bom.f.${h}`)}</th>
                ))}
              </tr>
            </thead>
            <tbody>
              {s.boms.map((b) => (
                <tr key={b.bom_no} className="border-t border-border">
                  <td className="px-3 py-2 font-mono font-semibold">{b.bom_no}</td>
                  <td className="px-3 py-2">{b.styles.map((x) => x.style || "—").join(", ")}</td>
                  <td className="px-3 py-2 text-right tabular-nums">{n(b.totals.order_qty)}</td>
                  <td className="px-3 py-2 text-right tabular-nums">{money(b.totals.style_value)}</td>
                  <td className="px-3 py-2 text-right tabular-nums font-semibold">{money(b.totals.value)}</td>
                  <td className="px-3 py-2 text-right tabular-nums">{money(b.totals.cost_pc, 4)}</td>
                  <td className="px-3 py-2 text-right tabular-nums">{n(b.totals.acc_pct, 2)}%</td>
                  <td className="px-3 py-2"><Pill tone={STATUS_TONE[b.status]}>{t(`bom.status.${b.status}`)}</Pill></td>
                  <td className="px-3 py-2 text-right whitespace-nowrap">
                    <Link href={`${SCREEN}?bom=${encodeURIComponent(b.bom_no)}`} className={buttonVariants({ size: "sm", variant: "outline" })}><FolderOpen /> {t("bom.open")}</Link>
                    <a href={printHref("bom", data.budget_no, { bom: b.bom_no })} target="_blank" rel="noopener" className={cn(buttonVariants({ size: "sm", variant: "ghost" }), "ml-1")} aria-label={`${t("print.btn.bom")} ${b.bom_no}`} title={t("print.btn.bom")}><Printer /></a>
                    {data.editable && b.status === "DRAFT" && data.can_delete ? (
                      <Button type="button" size="sm" variant="ghost" className="ml-1 text-danger" onClick={() => setDeleting(b.bom_no)} aria-label={`${t("bom.delete")} ${b.bom_no}`}><Trash2 /></Button>
                    ) : null}
                  </td>
                </tr>
              ))}
              <tr className="border-t border-border bg-surface-muted font-semibold">
                <td className="px-3 py-2" colSpan={4}>{t("bom.total_acc")}</td>
                <td className="px-3 py-2 text-right tabular-nums">{money(s.total)}</td>
                <td colSpan={4} />
              </tr>
            </tbody>
          </table>
        )}
      </section>
      <ConfirmDialog open={!!deleting} onOpenChange={(o) => !o && setDeleting(null)} title={t("bom.delete_title", { no: deleting ?? "" })} description={t("bom.delete_text")} confirmLabel={t("bom.delete")} cancelLabel={t("bom.cancel")} destructive loading={pending} onConfirm={remove} />
    </div>
  );
}

/* ── BOM List ─────────────────────────────────────────────────────── */

function BomList({ rows }) {
  const t = useT("prebudget");
  const [status, setStatus] = useState("ALL");
  const [q, setQ] = useState("");
  const needle = q.trim().toLowerCase();
  const shown = rows.filter((r) => (status === "ALL" || r.status === status) && (!needle || [r.bom_no, r.budget_no, r.buyer, r.brand, r.job_no, r.style].join(" ").toLowerCase().includes(needle)));
  const counts = { ALL: rows.length, DRAFT: rows.filter((r) => r.status === "DRAFT").length, FINAL: rows.filter((r) => r.status === "FINAL").length };
  const tot = shown.reduce((a, r) => ({ qty: a.qty + r.order_qty, value: a.value + r.value }), { qty: 0, value: 0 });
  const cols = ["sl", "bom_no", "budget_no", "brand", "job_no", "style", "artwork", "order_qty", "acc_value", "cost_per_pc", "acc_pct", "status", "created_by", "created_at", "action"];
  const right = ["order_qty", "acc_value", "cost_per_pc", "acc_pct", "action"];
  return (
    <section className="rounded-lg border border-border bg-surface text-[13px]">
      <div className="flex flex-wrap items-center gap-2 border-b border-border px-4 py-3">
        <h2 className="mr-auto font-semibold">{t("screens.bom-list")}</h2>
        <label className="relative">
          <Search className="pointer-events-none absolute top-1/2 left-2 size-3.5 -translate-y-1/2 text-foreground-muted" />
          <input value={q} onChange={(e) => setQ(e.target.value)} placeholder={t("bom.search")} aria-label={t("bom.search")} className="h-8 w-72 rounded border border-border-strong bg-white pr-2 pl-7 text-[12px]" />
        </label>
        {["ALL", "DRAFT", "FINAL"].map((s) => (
          <button key={s} type="button" onClick={() => setStatus(s)} className={cn("rounded-full border px-3 py-0.5 text-[12px]", status === s ? "border-brand bg-brand text-brand-foreground" : "border-border")}>
            {s === "ALL" ? t("bom.state.ALL") : t(`bom.status.${s}`)} ({counts[s]})
          </button>
        ))}
      </div>
      {shown.length === 0 ? (
        <p className="px-4 py-10 text-center text-foreground-muted">{t("bom.empty")}</p>
      ) : (
        <div className="overflow-x-auto">
          <table className="w-full">
            <thead className="bg-surface-muted text-left text-[11px] font-semibold tracking-wide text-foreground-muted uppercase">
              <tr>{cols.map((h) => <th key={h} className={cn("px-3 py-2", right.includes(h) && "text-right")}>{t(`bom.f.${h}`)}</th>)}</tr>
            </thead>
            <tbody>
              {shown.map((r, i) => (
                <tr key={`${r.bom_no}|${r.style}`} className="h-[60px] border-t border-border">
                  <td className="px-3 py-1 tabular-nums">{i + 1}</td>
                  <td className="px-3 py-1 font-mono font-semibold whitespace-nowrap">{r.bom_no}</td>
                  <td className="px-3 py-1 font-mono whitespace-nowrap">{r.budget_no}</td>
                  <td className="px-3 py-1">{r.brand || "—"}</td>
                  <td className="px-3 py-1">{r.job_no || "—"}</td>
                  <td className="px-3 py-1 font-semibold">{r.style || "—"}</td>
                  <td className="px-3 py-1"><Artwork url={r.artwork_url} size={44} /></td>
                  <td className="px-3 py-1 text-right tabular-nums">{n(r.order_qty)}</td>
                  <td className="px-3 py-1 text-right font-semibold tabular-nums">{money(r.value)}</td>
                  <td className="px-3 py-1 text-right tabular-nums">{money(r.cost_pc, 4)}</td>
                  <td className="px-3 py-1 text-right tabular-nums">{n(r.acc_pct, 2)}%</td>
                  <td className="px-3 py-1"><Pill tone={STATUS_TONE[r.status]}>{t(`bom.status.${r.status}`)}</Pill></td>
                  <td className="px-3 py-1">{r.created_by || "—"}</td>
                  <td className="px-3 py-1 whitespace-nowrap">{when(r.created_at)}</td>
                  <td className="px-3 py-1 text-right">
                    <Link href={`${SCREEN}?bom=${encodeURIComponent(r.bom_no)}`} className={buttonVariants({ size: "sm", variant: "outline" })}><FolderOpen /> {t("bom.open")}</Link>
                  </td>
                </tr>
              ))}
              <tr className="border-t border-border bg-surface-muted font-semibold">
                <td className="px-3 py-2" colSpan={7}>{t("budget.total")}</td>
                <td className="px-3 py-2 text-right tabular-nums">{n(tot.qty)}</td>
                <td className="px-3 py-2 text-right tabular-nums">{money(tot.value)}</td>
                <td className="px-3 py-2 text-right tabular-nums">{money(tot.qty > 0 ? tot.value / tot.qty : 0, 4)}</td>
                <td colSpan={5} />
              </tr>
            </tbody>
          </table>
        </div>
      )}
    </section>
  );
}

export { PendingList, BudgetBoms, BomList, BomHead, Pill, STATUS_TONE, SCREEN };
