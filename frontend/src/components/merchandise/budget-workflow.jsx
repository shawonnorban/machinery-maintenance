"use client";

import { useMemo, useState, useTransition } from "react";
import Link from "next/link";
import { useRouter } from "next/navigation";
import { ChevronLeft, ChevronRight, ImageOff, Lock, LockOpen, Search, ShieldCheck } from "lucide-react";
import { cn } from "@/lib/utils";
import { Input } from "@/components/ui/input";
import { Select } from "@/components/ui/select";
import { Checkbox } from "@/components/ui/checkbox";
import { Textarea } from "@/components/ui/textarea";
import { Button, buttonVariants } from "@/components/ui/button";
import { Badge } from "@/components/ui/badge";
import { Modal } from "@/components/ui/modal";
import { useToastManager } from "@/components/ui/toast";
import { formatNumber } from "@/lib/format";
import { useT } from "@/lib/i18n";

const PER_PAGE = 10;
const WEEKS = ["all", "1", "2", "3", "4", "5"];

/** Week n of a month is its days 7(n-1)+1 to 7n; the fifth runs to the month's end. */
const weekOf = (date) => String(Math.min(5, Math.ceil(Number(date.slice(8, 10)) / 7)));

/**
 * Budget Sheet Workflow (AnnotechERP 06/09): the approval route a budget
 * sheet travels before it is locked for costing. Filters as Brickland lays
 * them out — shipment month and week, budget date range (skipped unless
 * "Skip Date?" is cleared), Job#/Style/PO search and approved only — with a
 * pill per buyer; all of it filters in place, nothing reloads. An approver
 * approves a waiting sheet (it locks) or unlocks an approved one, saying why.
 *
 * @param {{ rows: object[], decide: Function, canApprove: boolean, locale: string }} props
 */
function BudgetWorkflow({ rows, decide, canApprove, locale }) {
  const t = useT("merchandise");
  const m = (k, p) => t(`master.bsw.${k}`, p);
  const router = useRouter();
  const [month, setMonth] = useState("");
  const [from, setFrom] = useState("");
  const [to, setTo] = useState("");
  const [week, setWeek] = useState("all");
  const [typed, setTyped] = useState("");
  const [q, setQ] = useState("");
  const [approvedOnly, setApprovedOnly] = useState(false);
  const [skipDate, setSkipDate] = useState(true);
  const [buyer, setBuyer] = useState("");
  const [page, setPage] = useState(1);
  const [dialog, setDialog] = useState(null);

  const months = useMemo(() => {
    const keys = [...new Set(rows.map((r) => r.ship_date?.slice(0, 7)).filter(Boolean))].sort().reverse();
    const fmt = new Intl.DateTimeFormat(locale === "bn" ? "bn-BD" : "en-GB", { month: "long", year: "numeric" });
    return keys.map((k) => ({ value: k, label: fmt.format(new Date(`${k}-01T00:00:00`)) }));
  }, [rows, locale]);

  // Everything but the buyer pill; the pills count what the other filters leave.
  const filtered = useMemo(() => {
    const needle = q.trim().toLowerCase();
    return rows.filter((r) => {
      if (month && r.ship_date?.slice(0, 7) !== month) return false;
      if (week !== "all" && (!r.ship_date || weekOf(r.ship_date) !== week)) return false;
      if (!skipDate && from && (r.budget_date ?? "") < from) return false;
      if (!skipDate && to && (r.budget_date ?? "") > to) return false;
      if (approvedOnly && r.status !== "APPROVED") return false;
      if (needle && ![r.job_no, r.style_no, r.po_no, r.order_no].some((v) => v && String(v).toLowerCase().includes(needle))) return false;
      return true;
    });
  }, [rows, month, week, skipDate, from, to, approvedOnly, q]);
  const buyers = useMemo(() => {
    const counts = new Map();
    for (const r of filtered) counts.set(r.buyer ?? "—", (counts.get(r.buyer ?? "—") ?? 0) + 1);
    return [...counts.entries()].sort(([a], [b]) => a.localeCompare(b));
  }, [filtered]);
  const shown = buyer ? filtered.filter((r) => (r.buyer ?? "—") === buyer) : filtered;
  const pages = Math.max(1, Math.ceil(shown.length / PER_PAGE));
  const current = Math.min(page, pages);
  const slice = shown.slice((current - 1) * PER_PAGE, current * PER_PAGE);
  const reset = (fn) => (v) => {
    fn(v);
    setPage(1);
  };

  const th = "px-4 py-2.5 text-left text-[13px] font-semibold whitespace-nowrap";
  const label = "text-[13px] font-semibold text-foreground";
  return (
    <div className="flex flex-col gap-4">
      <section className="rounded-xl border border-border bg-surface">
        <div className="flex flex-wrap items-end gap-4 px-5 py-4">
          <label className="flex w-44 flex-col gap-1.5">
            <span className={label}>{m("month")}</span>
            <Select value={month} onValueChange={(v) => reset(setMonth)(v ?? "")} options={[{ value: "", label: m("all_months") }, ...months]} placeholder={m("all_months")} />
          </label>
          <label className="flex w-40 flex-col gap-1.5">
            <span className={label}>{m("from")}</span>
            <Input type="date" value={from} onChange={(e) => reset(setFrom)(e.target.value)} disabled={skipDate} />
          </label>
          <label className="flex w-40 flex-col gap-1.5">
            <span className={label}>{m("to")}</span>
            <Input type="date" value={to} onChange={(e) => reset(setTo)(e.target.value)} disabled={skipDate} />
          </label>
          <div role="radiogroup" aria-label={m("week")} className="flex flex-wrap items-center gap-4 pb-2">
            {WEEKS.map((w) => (
              <label key={w} className="flex cursor-pointer items-center gap-1.5 text-sm text-foreground">
                <input type="radio" name="bsw-week" value={w} checked={week === w} onChange={() => reset(setWeek)(w)} className="size-4 accent-[var(--color-brand)]" />
                {w === "all" ? m("all_week") : m("week_n", { n: w })}
              </label>
            ))}
          </div>
          <div className="ml-auto flex flex-wrap items-center gap-4">
            <form
              className="flex items-center gap-2"
              onSubmit={(e) => {
                e.preventDefault();
                reset(setQ)(typed);
              }}
            >
              <Input value={typed} onChange={(e) => setTyped(e.target.value)} placeholder={m("search")} className="w-56" aria-label={m("search")} />
              <Button type="submit" variant="outline" size="icon" aria-label={m("search_btn")}>
                <Search />
              </Button>
            </form>
            <label className="flex cursor-pointer items-center gap-2 text-sm text-foreground">
              <Checkbox checked={approvedOnly} onCheckedChange={(c) => reset(setApprovedOnly)(c === true)} />
              {m("approved_only")}
            </label>
            <label className="flex cursor-pointer items-center gap-2 text-sm text-foreground">
              <Checkbox checked={skipDate} onCheckedChange={(c) => reset(setSkipDate)(c === true)} />
              {m("skip_date")}
            </label>
          </div>
        </div>
        <div className="flex flex-wrap gap-2 border-t border-border px-5 py-3">
          <PillButton active={!buyer} onClick={() => reset(setBuyer)("")} label={m("all")} />
          {buyers.map(([b, n]) => (
            <PillButton key={b} active={buyer === b} onClick={() => reset(setBuyer)(b)} label={b} count={n} />
          ))}
        </div>
      </section>

      <section className="overflow-hidden rounded-xl border border-border bg-surface">
        {slice.length ? (
          <div className="overflow-x-auto">
            <table className="w-full text-sm">
              <thead>
                <tr className="bg-brand text-brand-foreground">
                  <th className={th}>{m("col.budget_no")}</th>
                  <th className={th}>{m("col.buyer")}</th>
                  <th className={th}>{m("col.job")}</th>
                  <th className={th}>{m("col.style")}</th>
                  <th className={th}>{m("col.po")}</th>
                  <th className={th}>{m("col.ship")}</th>
                  <th className={cn(th, "text-right")}>{m("col.qty")}</th>
                  <th className={cn(th, "text-right")}>{m("col.cost")}</th>
                  <th className={cn(th, "text-right")}>{m("col.fob")}</th>
                  <th className={th}>{m("col.status")}</th>
                  <th className={th} aria-label={m("col.actions")} />
                </tr>
              </thead>
              <tbody>
                {slice.map((r) => (
                  <tr key={r.id} className="border-b border-border align-top">
                    <td className="px-4 py-2.5 font-mono text-[13px] font-semibold text-foreground">{r.budget_no}</td>
                    <td className="px-4 py-2.5">
                      <div>{r.buyer ?? "—"}</div>
                      {r.buyer_ac ? <div className="text-xs text-foreground-muted">{r.buyer_ac}</div> : null}
                    </td>
                    <td className="px-4 py-2.5">{r.job_no ?? "—"}</td>
                    <td className="px-4 py-2.5">{r.style_no ?? "—"}</td>
                    <td className="px-4 py-2.5">
                      <div>{r.po_no ?? "—"}</div>
                      <div className="text-xs text-foreground-muted">{r.order_no}</div>
                    </td>
                    <td className="px-4 py-2.5 whitespace-nowrap">{r.ship_date ?? "—"}</td>
                    <td className="px-4 py-2.5 text-right tabular-nums">{formatNumber(r.order_qty)}</td>
                    <td className={cn("px-4 py-2.5 text-right tabular-nums", r.cost > r.fob + 0.0001 ? "text-danger" : "")}>{r.cost.toFixed(4)}</td>
                    <td className="px-4 py-2.5 text-right tabular-nums">
                      {r.fob.toFixed(4)} <span className="text-xs text-foreground-muted">{r.currency}</span>
                    </td>
                    <td className="px-4 py-2.5">
                      <Badge variant={r.status === "APPROVED" ? "success" : "warning"}>
                        {r.status === "APPROVED" ? <Lock className="size-3" /> : null}
                        {m(`status.${r.status}`)}
                      </Badge>
                      {r.status === "APPROVED" && r.approved_by ? <div className="mt-1 text-xs text-foreground-muted">{m("approved_by", { name: r.approved_by })}</div> : null}
                      {r.last_action === "UNLOCKED" && r.last_note ? <div className="mt-1 max-w-56 text-xs text-foreground-muted">{m("unlocked_note", { note: r.last_note })}</div> : null}
                    </td>
                    <td className="px-4 py-2.5">
                      <div className="flex justify-end gap-2">
                        <Link href={`/merchandise-desk/budget-sheet?order=${encodeURIComponent(r.order_no)}`} className={buttonVariants({ variant: "outline", size: "sm" })}>
                          {m("open")}
                        </Link>
                        {canApprove ? (
                          r.status === "APPROVED" ? (
                            <Button variant="outline" size="sm" onClick={() => setDialog({ move: "unlock", row: r })}>
                              <LockOpen /> {m("unlock")}
                            </Button>
                          ) : (
                            <Button size="sm" onClick={() => setDialog({ move: "approve", row: r })}>
                              <ShieldCheck /> {m("approve")}
                            </Button>
                          )
                        ) : null}
                      </div>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        ) : (
          <div className="flex flex-col items-center gap-2 px-6 py-16 text-center">
            <span className="flex size-12 items-center justify-center rounded-xl bg-surface-muted text-foreground-muted">
              <ImageOff className="size-5" />
            </span>
            <p className="font-semibold text-foreground">{m("empty_title")}</p>
            <p className="max-w-sm text-sm text-foreground-muted">{m("empty_text")}</p>
          </div>
        )}
        <div className="flex items-center justify-between border-t border-border px-5 py-3">
          <div className="flex items-center gap-1">
            <Button variant="outline" size="icon" className="size-8" onClick={() => setPage(current - 1)} disabled={current <= 1} aria-label={m("prev")}>
              <ChevronLeft />
            </Button>
            {Array.from({ length: pages }, (_, i) => i + 1).map((n) => (
              <Button key={n} variant={n === current ? "primary" : "outline"} size="icon" className="size-8" onClick={() => setPage(n)} aria-current={n === current ? "page" : undefined}>
                {n}
              </Button>
            ))}
            <Button variant="outline" size="icon" className="size-8" onClick={() => setPage(current + 1)} disabled={current >= pages} aria-label={m("next")}>
              <ChevronRight />
            </Button>
          </div>
          <span className="text-[13px] text-foreground-muted">{m("range", { from: shown.length ? (current - 1) * PER_PAGE + 1 : 0, to: Math.min(current * PER_PAGE, shown.length), total: shown.length })}</span>
        </div>
      </section>

      <DecideDialog dialog={dialog} decide={decide} onClose={() => setDialog(null)} onDone={() => router.refresh()} />
    </div>
  );
}

function PillButton({ active, onClick, label, count }) {
  return (
    <button
      type="button"
      onClick={onClick}
      aria-pressed={active}
      className={cn("inline-flex items-center gap-1.5 rounded-md border px-3 py-1 text-[13px] font-semibold transition-colors", active ? "border-brand bg-brand text-brand-foreground" : "border-border bg-surface text-foreground hover:bg-surface-muted")}
    >
      {label}
      {count !== undefined ? <span className={cn("rounded px-1.5 text-xs", active ? "bg-white/20" : "bg-surface-muted text-foreground-muted")}>{count}</span> : null}
    </button>
  );
}

/** Approve (note optional) or Unlock (a note says why) one budget sheet. */
function DecideDialog({ dialog, decide, onClose, onDone }) {
  const t = useT("merchandise");
  const m = (k, p) => t(`master.bsw.${k}`, p);
  const toastManager = useToastManager();
  const [note, setNote] = useState("");
  const [error, setError] = useState(null);
  const [pending, startTransition] = useTransition();
  const [open, setOpen] = useState(dialog);
  // A new dialog opens with an empty note (adjusting state during render).
  if (open !== dialog) {
    setOpen(dialog);
    setNote("");
    setError(null);
  }
  const move = dialog?.move;
  const required = move === "unlock";

  function confirm() {
    if (required && !note.trim()) {
      setError(m("dlg.unlock_hint"));
      return;
    }
    startTransition(async () => {
      const result = await decide(dialog.row.id, { move, note: note.trim() });
      if (result?.status === "success") {
        onClose();
        toastManager.add({ title: m(move === "approve" ? "approved" : "unlocked", { no: dialog.row.budget_no }), type: "success" });
        onDone();
      } else {
        setError(result?.errors ? [...new Set(Object.values(result.errors).flat())].join(" · ") : result?.message);
      }
    });
  }

  return (
    <Modal
      open={Boolean(dialog)}
      onOpenChange={(o) => !o && onClose()}
      title={move ? m(`dlg.${move}_title`) : ""}
      description={dialog ? [dialog.row.budget_no, dialog.row.buyer, dialog.row.style_no, dialog.row.order_no].filter(Boolean).join(" · ") : ""}
      footer={
        dialog ? (
          <>
            <Button variant="outline" onClick={onClose} disabled={pending}>
              {m("dlg.cancel")}
            </Button>
            <Button onClick={confirm} loading={pending} className={required ? "bg-warning text-white hover:bg-warning/90" : ""}>
              {m(move)}
            </Button>
          </>
        ) : null
      }
    >
      {dialog ? (
        <div className="flex flex-col gap-3">
          <p className="text-sm text-foreground-muted">{m(`dlg.${move}_hint`)}</p>
          <label className="flex flex-col gap-1.5 text-[13px] font-semibold text-foreground">
            <span>
              {required ? (
                <>
                  {m("dlg.note")}
                  <span className="text-danger">*</span>
                </>
              ) : (
                m("dlg.note_optional")
              )}
            </span>
            <Textarea value={note} onChange={(e) => setNote(e.target.value)} rows={3} />
          </label>
          {error ? <p className="text-sm text-danger">{error}</p> : null}
        </div>
      ) : null}
    </Modal>
  );
}

export { BudgetWorkflow };
