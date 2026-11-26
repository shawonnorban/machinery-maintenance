"use client";

import { useState, useTransition } from "react";
import { useRouter } from "next/navigation";
import { Check, Hourglass, Undo2, X } from "lucide-react";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { useToastManager } from "@/components/ui/toast";
import { useT } from "@/lib/i18n";
import { cn } from "@/lib/utils";

/*
 * Export Statement → Pending Approvals (AnnotechERP 35, Norban's approvals
 * page): each edit waiting — the invoice, who sent it and when, every changed
 * field with its current and new value, the step it is on and who may sign
 * it. Whoever's turn it is approves or rejects (with a note); the editor may
 * take it back. The latest decisions underneath.
 */

const when = (iso) => (iso ? new Date(iso).toLocaleString("en-GB", { timeZone: "Asia/Dhaka", dateStyle: "medium", timeStyle: "short", hourCycle: "h12" }).replace(/\b[ap]m\b/, (x) => x.toUpperCase()) : "—");

function EditCard({ e, act, t }) {
  const router = useRouter();
  const toast = useToastManager();
  const [note, setNote] = useState("");
  const [pending, start] = useTransition();
  const run = (name, ...args) =>
    start(async () => {
      const res = await act(name, e.id, ...args);
      if (res?.status !== "success") {
        const first = res?.errors ? Object.values(res.errors)[0]?.[0] : null;
        toast.add({ title: first ?? res?.message ?? "Error", type: "danger" });
        return;
      }
      const status = res.data.status;
      toast.add({ title: name === "withdraw" ? t("appr.withdrawn") : status === "APPROVED" ? t("appr.approved") : status === "REJECTED" ? t("appr.rejected") : t("appr.next"), type: "success" });
      router.refresh();
    });

  return (
    <section className="overflow-hidden rounded-lg border border-[#fcd9b6] bg-surface">
      <header className="flex flex-wrap items-center gap-x-3 gap-y-1 bg-[#fff4e8] px-4 py-2 text-[13px]">
        <Hourglass className="size-4 text-[#c2410c]" />
        <span className="font-semibold">
          #{e.serial_no} · {t("appr.invoice")} {e.invoice_no ?? "—"} · {e.company ?? "—"} · {e.buyer ?? "—"}
        </span>
        <span className="rounded bg-[#fde68a] px-1.5 py-0.5 text-[11px] font-semibold">{t(`appr.source.${e.source}`)}</span>
        <span className="text-foreground-muted">{t("appr.requested", { name: e.requested_by ?? "—", at: when(e.requested_at) })}</span>
        <span className="ml-auto text-[12px] text-foreground-muted">
          {e.step ? t("appr.step", { step: e.step, steps: e.steps, name: e.current ?? "—" }) : null}
          {e.step ? " · " : null}
          {e.approvers?.length ? t("appr.approvers", { names: e.approvers.join(", ") }) : t("appr.no_approvers")}
        </span>
      </header>
      <table className="w-full text-[12px]">
        <thead>
          <tr className="border-b border-border bg-surface-muted text-left">
            <th className="w-1/4 px-4 py-1.5 font-semibold">{t("appr.field")}</th>
            <th className="px-4 py-1.5 font-semibold">{t("appr.current")}</th>
            <th className="px-4 py-1.5 font-semibold">{t("appr.new")}</th>
          </tr>
        </thead>
        <tbody>
          {e.changes.map((c) => (
            <tr key={c.key} className="border-b border-border last:border-0">
              <td className="px-4 py-1.5">{c.label}</td>
              <td className="px-4 py-1.5 text-foreground-muted">{c.old ?? "—"}</td>
              <td className="px-4 py-1.5 font-semibold text-[#0d9488]">{c.new ?? "—"}</td>
            </tr>
          ))}
        </tbody>
      </table>
      {e.can_act || e.can_withdraw ? (
        <div className="flex flex-wrap items-center gap-2 border-t border-border px-4 py-2">
          {e.can_act ? (
            <>
              <Input value={note} onChange={(ev) => setNote(ev.target.value)} maxLength={1000} placeholder={t("appr.note")} className="h-8 min-w-60 flex-1 text-[12px]" />
              <Button type="button" size="sm" variant="success" disabled={pending} onClick={() => run("decide", "approve", note)}>
                <Check /> {t("appr.approve")}
              </Button>
              <Button type="button" size="sm" variant="danger" disabled={pending} onClick={() => run("decide", "reject", note)}>
                <X /> {t("appr.reject")}
              </Button>
            </>
          ) : (
            <span className="text-[12px] text-foreground-muted">{t("appr.not_your_turn")}</span>
          )}
          {e.can_withdraw ? (
            <Button type="button" size="sm" variant="outline" className={cn(!e.can_act && "ml-auto")} disabled={pending} onClick={() => run("withdraw")}>
              <Undo2 /> {t("appr.withdraw")}
            </Button>
          ) : null}
        </div>
      ) : null}
    </section>
  );
}

function EsApprovals({ data, act }) {
  const t = useT("exportstatement");
  return (
    <div className="flex flex-col gap-3">
      {data.pending.length === 0 ? (
        <p className="rounded-lg border border-border bg-surface px-4 py-6 text-center text-[13px] text-foreground-muted">{t("appr.empty")}</p>
      ) : (
        data.pending.map((e) => <EditCard key={e.id} e={e} act={act} t={t} />)
      )}
      {data.recent.length ? (
        <section className="rounded-lg border border-border bg-surface">
          <h3 className="border-b border-border px-4 py-2 text-[13px] font-semibold">{t("appr.recent")}</h3>
          <ul className="divide-y divide-border text-[12px]">
            {data.recent.map((e) => (
              <li key={e.id} className="flex flex-wrap items-center gap-x-3 gap-y-1 px-4 py-2">
                <span className={cn("rounded px-1.5 py-0.5 text-[11px] font-semibold", e.status === "APPROVED" ? "bg-success-subtle text-success" : e.status === "REJECTED" ? "bg-danger-subtle text-danger" : "bg-surface-muted text-foreground-muted")}>
                  {t(`appr.status.${e.status}`)}
                </span>
                <span className="font-medium">
                  #{e.serial_no} · {e.invoice_no ?? "—"} · {e.company ?? "—"}
                </span>
                <span className="text-foreground-muted">{e.changes.map((c) => `${c.label}: ${c.old ?? "—"} → ${c.new ?? "—"}`).join(" · ")}</span>
                <span className="ml-auto text-foreground-muted">
                  {e.requested_by ?? "—"} → {t("appr.decided", { status: t(`appr.status.${e.status}`), name: e.decided_by ?? "—", at: when(e.decided_at) })}
                  {e.note ? ` — “${e.note}”` : ""}
                </span>
              </li>
            ))}
          </ul>
        </section>
      ) : null}
    </div>
  );
}

export { EsApprovals };
