"use client";

import { useState, useTransition } from "react";
import { useRouter } from "next/navigation";
import { CheckCircle2, Circle, Clock, CopyPlus, Send, Undo2, XCircle } from "lucide-react";
import { cn } from "@/lib/utils";
import { Button } from "@/components/ui/button";
import { ConfirmDialog } from "@/components/ui/confirm-dialog";
import { useToastManager } from "@/components/ui/toast";
import { useT } from "@/lib/i18n";

/*
 * Pre-Budget step 6: a budget's way through the Approval Center
 * (Merchandising Head → CFO → MD) — each level with who may sign it and who
 * did, the history of every round, and the buttons this user has now:
 * Finalize (the checklist complete), Withdraw (the sender, while it waits),
 * Approve / Reject (whoever signs the current step), Revise (approved: a
 * copy -R1 to change). Shown on Create Budget and on Budget Finalized.
 */

const when = (iso) => (iso ? new Date(iso).toLocaleString("en-GB", { day: "2-digit", month: "short", year: "2-digit", hour: "2-digit", minute: "2-digit", timeZone: "Asia/Dhaka", hourCycle: "h12" }).replace(/\b[ap]m\b/, (x) => x.toUpperCase()) : "");
const errorOf = (res, t) => Object.values(res?.errors ?? {})[0]?.[0] ?? res?.message ?? t("budget.error");

const STATE = {
  APPROVED: { icon: CheckCircle2, tone: "text-success" },
  REJECTED: { icon: XCircle, tone: "text-danger" },
  RETURNED: { icon: Undo2, tone: "text-warning" },
  CURRENT: { icon: Clock, tone: "text-brand font-semibold" },
  WAITING: { icon: Circle, tone: "text-foreground-muted" },
};

const STATUS_TONE = { DRAFT: "bg-surface-muted", PENDING: "bg-warning-subtle text-warning", APPROVED: "bg-success-subtle text-success", RETURNED: "bg-danger-subtle text-danger", REVISED: "bg-info-subtle text-info", CANCELLED: "bg-danger-subtle text-danger line-through" };

function StatusPill({ status }) {
  const t = useT("prebudget");
  return <span className={cn("inline-flex rounded px-2 py-0.5 text-[11px] font-semibold whitespace-nowrap", STATUS_TONE[status])}>{t(`budget.status.${status}`)}</span>;
}

function ApprovalPanel({ budget, act, ready = true }) {
  const t = useT("prebudget");
  const router = useRouter();
  const toast = useToastManager();
  const [pending, start] = useTransition();
  const [confirm, setConfirm] = useState(null); // finalize | withdraw | revise
  const [comment, setComment] = useState("");
  const a = budget.approval;

  const run = (name, args, done, after) =>
    start(async () => {
      const res = await act(name, ...args);
      setConfirm(null);
      if (res?.status === "success") {
        toast.add({ title: done, type: "success" });
        setComment("");
        if (after) after(res.data);
        else router.refresh();
      } else toast.add({ title: errorOf(res, t), type: "danger" });
    });

  const decide = (action) => {
    if (action === "reject" && !comment.trim()) {
      toast.add({ title: t("final.v.reason"), type: "danger" });
      return;
    }
    run("decideBudget", [budget.budget_no, { action, comment: comment || null }], t(action === "approve" ? "final.approved" : "final.rejected", { no: budget.budget_no }));
  };

  return (
    <section className="rounded-lg border border-border bg-surface p-3 text-[12px]">
      <div className="mb-2 flex flex-wrap items-center gap-2">
        <h3 className="mr-auto text-[13px] font-semibold">{t("final.title")}</h3>
        <StatusPill status={budget.status} />
        {budget.status === "PENDING" && a.current ? <span className="text-foreground-muted">{t("final.waiting_at", { step: a.current, n: a.step, of: a.steps })}</span> : null}
      </div>

      {a.levels.length ? (
        <ol className="grid gap-2 sm:grid-cols-3">
          {a.levels.map((l) => {
            const s = STATE[l.state] ?? STATE.WAITING;
            const Icon = s.icon;
            return (
              <li key={l.level} className={cn("rounded border border-border p-2", l.state === "CURRENT" && "border-brand bg-brand/5")}>
                <div className={cn("flex items-center gap-1.5", s.tone)}>
                  <Icon className="size-4" /> <span>{l.level}. {l.name}</span>
                </div>
                <div className="mt-1 text-[11px] text-foreground-muted">{l.approvers.length ? l.approvers.join(", ") : t("final.nobody")}</div>
                {l.by ? <div className="mt-1 text-[11px]">{t(`final.state.${l.state}`)} · {l.by} · {when(l.at)}</div> : null}
                {l.comment ? <div className="mt-0.5 text-[11px] italic">“{l.comment}”</div> : null}
              </li>
            );
          })}
        </ol>
      ) : <p className="text-foreground-muted">{t("final.no_chain")}</p>}

      {a.submitted_at ? (
        <p className="mt-2 text-[11px] text-foreground-muted">
          {t("final.sent", { by: a.submitted_by ?? "—", at: when(a.submitted_at) })}
          {a.approved_at ? ` · ${t("final.approved_line", { by: a.approved_by ?? "—", at: when(a.approved_at) })}` : ""}
        </p>
      ) : null}

      {a.can_act ? (
        <div className="mt-3 flex flex-wrap items-end gap-2 rounded border border-brand/40 bg-brand/5 p-2">
          <label className="flex min-w-64 flex-1 flex-col gap-1">
            <span className="font-medium">{t("final.comment")}</span>
            <textarea className="min-h-14 rounded border border-border-strong bg-white px-2 py-1" value={comment} onChange={(e) => setComment(e.target.value)} aria-label={t("final.comment")} />
          </label>
          <Button type="button" className="bg-success text-white hover:bg-success/90" disabled={pending} onClick={() => decide("approve")}><CheckCircle2 /> {t("final.approve")}</Button>
          <Button type="button" variant="outline" className="text-danger" disabled={pending} onClick={() => decide("reject")}><XCircle /> {t("final.reject")}</Button>
        </div>
      ) : null}

      <div className="mt-3 flex flex-wrap gap-2">
        {budget.can_finalize ? (
          <Button type="button" size="sm" disabled={pending || !ready} title={ready ? undefined : t("budget.finalize_hint")} onClick={() => setConfirm("finalize")}><Send /> {t("budget.finalize")}</Button>
        ) : null}
        {budget.can_withdraw ? <Button type="button" size="sm" variant="outline" disabled={pending} onClick={() => setConfirm("withdraw")}><Undo2 /> {t("final.withdraw")}</Button> : null}
        {budget.can_revise ? <Button type="button" size="sm" variant="outline" disabled={pending} onClick={() => setConfirm("revise")}><CopyPlus /> {t("final.revise")}</Button> : null}
      </div>

      {a.history.length ? (
        <details className="mt-3">
          <summary className="cursor-pointer font-medium">{t("final.history", { n: a.history.length })}</summary>
          <ul className="mt-1 flex flex-col gap-0.5">
            {a.history.map((h, i) => (
              <li key={i} className="text-[11px]">
                <span className="font-semibold">{t(`final.action.${h.action}`)}</span>
                {h.step ? ` · ${t("final.level", { n: h.step })}` : ""} · {h.by ?? "—"} · {when(h.at)}
                {h.comment ? <span className="italic"> — “{h.comment}”</span> : null}
              </li>
            ))}
          </ul>
        </details>
      ) : null}

      <ConfirmDialog
        open={confirm !== null}
        onOpenChange={(o) => !o && setConfirm(null)}
        title={confirm ? t(`final.confirm.${confirm}`, { no: budget.budget_no }) : ""}
        description={confirm ? t(`final.confirm.${confirm}_text`) : ""}
        confirmLabel={confirm === "finalize" ? t("budget.finalize") : confirm === "withdraw" ? t("final.withdraw") : t("final.revise")}
        cancelLabel={t("budget.cancel")}
        destructive={false}
        loading={pending}
        onConfirm={() => {
          if (confirm === "finalize") run("finalizeBudget", [budget.budget_no], t("final.sent_toast", { no: budget.budget_no }));
          else if (confirm === "withdraw") run("withdrawBudget", [budget.budget_no], t("final.withdrawn", { no: budget.budget_no }));
          else run("reviseBudget", [budget.budget_no], t("final.revised"), (d) => router.push(`/pre-budget/create-budget?budget=${encodeURIComponent(d.budget_no)}`));
        }}
      />
    </section>
  );
}

export { ApprovalPanel, StatusPill };
