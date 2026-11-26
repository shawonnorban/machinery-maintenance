"use client";

import { useEffect, useState, useTransition } from "react";
import { useRouter } from "next/navigation";
import { Check, CornerUpLeft, Pencil, Undo2, X } from "lucide-react";
import { cn } from "@/lib/utils";
import { Modal } from "@/components/ui/modal";
import { Button } from "@/components/ui/button";
import { Textarea } from "@/components/ui/textarea";
import { Alert } from "@/components/ui/alert";
import { FormattedDateTime } from "@/components/ui/formatted-date-time";
import { useToastManager } from "@/components/ui/toast";
import { useT } from "@/lib/i18n";

const TONE = {
  APPROVED: "bg-success-subtle text-success",
  REJECTED: "bg-danger-subtle text-danger",
  RETURNED: "bg-warning-subtle text-warning",
  CURRENT: "bg-brand-subtle text-brand",
  WAITING: "bg-surface-muted text-foreground-muted",
};

function Info({ label, children }) {
  return (
    <div className="min-w-0">
      <dt className="text-xs text-foreground-muted">{label}</dt>
      <dd className="truncate text-sm font-medium text-foreground">{children || "—"}</dd>
    </div>
  );
}

/**
 * One request's panel (Approval Center, AnnotechERP 04): what is asked, the
 * levels it passes through and where it stands, the full history including
 * earlier rounds, and — when it is this person's turn — Approve, Return and
 * Reject. A refusal or a return must say why.
 */
function RequestDetail({ id, onClose, onEdit, loadRequest, decide, cancelTicket, statusLabels }) {
  const t = useT("approval");
  const router = useRouter();
  const toastManager = useToastManager();
  const [data, setData] = useState(null);
  const [failure, setFailure] = useState(null);
  const [comment, setComment] = useState("");
  const [error, setError] = useState(null);
  const [pending, startTransition] = useTransition();

  useEffect(() => {
    let live = true;
    loadRequest(id).then((result) => {
      if (!live) return;
      if (result?.status === "success") setData(result.request);
      else setFailure(result?.message ?? t("center.load_failed"));
    });
    return () => {
      live = false;
    };
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [id]);

  function act(decision) {
    if ((decision === "reject" || decision === "return") && comment.trim() === "") {
      setError(t("center.reason_required"));
      return;
    }
    startTransition(async () => {
      const result = decision === "cancel" ? await cancelTicket(data.ticket_id, comment) : await decide(id, decision, comment);
      if (result?.status === "success") {
        toastManager.add({ title: t(`center.done.${decision}`), type: "success" });
        router.refresh();
        onClose();
      } else {
        setError(result?.errors?.comment?.[0] ?? result?.message ?? null);
      }
    });
  }

  const ticket = data?.ticket;

  return (
    <Modal open onOpenChange={(o) => !o && onClose()} title={data ? `${data.request_no ?? t("center.draft")} · ${data.title ?? ""}` : t("center.loading")} className="max-w-4xl">
      {failure ? <Alert variant="danger">{failure}</Alert> : null}
      {data ? (
        <div className="flex max-h-[70vh] flex-col gap-4 overflow-y-auto pr-1">
          <div className="flex flex-wrap items-center gap-2 text-sm">
            <span className="rounded-md bg-surface-muted px-2 py-0.5 font-medium">{statusLabels[data.status] ?? data.status}</span>
            {data.priority ? <span className="rounded-md bg-surface-muted px-2 py-0.5">{t(`center.priorities.${data.priority}`)}</span> : null}
            {data.overdue ? <span className="rounded-md bg-danger-subtle px-2 py-0.5 text-danger">{t("center.overdue")}</span> : null}
          </div>

          <dl className="grid gap-x-6 gap-y-3 rounded-lg border border-border p-4 sm:grid-cols-3">
            <Info label={t("center.cols.type")}>{data.type ?? t(`center.entities.${data.entity_type}`)}</Info>
            <Info label={t("center.cols.requested_by")}>{data.requested_by}</Info>
            <Info label={t("center.cols.date")}>{data.date ? <FormattedDateTime value={data.date} /> : null}</Info>
            {ticket ? (
              <>
                <Info label={t("center.f.reference_no")}>{[ticket.reference_type, ticket.reference_no].filter(Boolean).join(" · ")}</Info>
                <Info label={t("center.f.buyer")}>{ticket.buyer_name}</Info>
                <Info label={t("center.f.order_no")}>{ticket.order_no}</Info>
                <Info label={t("center.f.style_no")}>{ticket.style_no}</Info>
                <Info label={t("center.f.po_no")}>{ticket.po_no}</Info>
                <Info label={t("center.f.quantity")}>{ticket.quantity !== null ? `${ticket.quantity} ${ticket.unit ?? ""}` : null}</Info>
              </>
            ) : null}
            <Info label={t("center.f.amount")}>{data.amount !== null && data.amount !== undefined ? `${Number(data.amount).toLocaleString()} ${data.currency ?? ""}` : null}</Info>
            <Info label={t("center.f.due_date")}>{data.due_at ? <FormattedDateTime value={data.due_at} mode="date" /> : null}</Info>
            {ticket?.remarks ? (
              <div className="sm:col-span-3">
                <dt className="text-xs text-foreground-muted">{t("center.f.remarks")}</dt>
                <dd className="text-sm whitespace-pre-wrap text-foreground">{ticket.remarks}</dd>
              </div>
            ) : null}
          </dl>

          <section>
            <h3 className="mb-2 text-sm font-semibold text-foreground">{t("center.levels")}</h3>
            <ol className="flex flex-col gap-2">
              {data.levels.map((level) => (
                <li key={level.level} className="flex flex-wrap items-center gap-3 rounded-md border border-border px-3 py-2 text-sm">
                  <span className="flex size-7 items-center justify-center rounded-full bg-surface-muted font-semibold tabular-nums">{level.level}</span>
                  <span className="min-w-0 flex-1">
                    <span className="block font-medium text-foreground">{level.name ?? t("center.request_to")}</span>
                    <span className="block text-xs text-foreground-muted">{level.approver ?? "—"}</span>
                  </span>
                  <span className={cn("rounded-md px-2 py-0.5 text-xs font-medium", TONE[level.state] ?? TONE.WAITING)}>{t(`center.level_states.${level.state}`)}</span>
                  {level.by ? (
                    <span className="text-xs text-foreground-muted">
                      {level.by} · <FormattedDateTime value={level.at} />
                    </span>
                  ) : null}
                </li>
              ))}
            </ol>
          </section>

          <section>
            <h3 className="mb-2 text-sm font-semibold text-foreground">{t("center.history")}</h3>
            <div className="overflow-x-auto rounded-md border border-border">
              <table className="w-full text-sm">
                <thead>
                  <tr className="bg-primary text-left text-primary-foreground">
                    <th className="px-3 py-2 font-semibold">{t("center.cols.when")}</th>
                    <th className="px-3 py-2 font-semibold">{t("center.cols.action")}</th>
                    <th className="px-3 py-2 font-semibold">{t("center.cols.action_by")}</th>
                    <th className="px-3 py-2 font-semibold">{t("center.cols.change")}</th>
                    <th className="px-3 py-2 font-semibold">{t("center.cols.level")}</th>
                    <th className="px-3 py-2 font-semibold">{t("center.cols.comment")}</th>
                  </tr>
                </thead>
                <tbody>
                  {data.history.map((h) => (
                    <tr key={h.id} className="border-b border-border last:border-0">
                      <td className="px-3 py-1.5 whitespace-nowrap">
                        <FormattedDateTime value={h.at} />
                      </td>
                      <td className="px-3 py-1.5">{t(`center.actions.${h.action}`)}</td>
                      <td className="px-3 py-1.5">{h.by ?? "—"}</td>
                      <td className="px-3 py-1.5 text-xs">
                        {h.previous_status || h.new_status ? `${statusLabels[h.previous_status] ?? "—"} → ${statusLabels[h.new_status] ?? "—"}` : "—"}
                      </td>
                      <td className="px-3 py-1.5 tabular-nums">{h.level ?? "—"}</td>
                      <td className="px-3 py-1.5 whitespace-pre-wrap">{h.comment ?? "—"}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </section>

          {data.can_act || data.can_cancel ? (
            <section className="flex flex-col gap-2 rounded-lg border border-border p-4">
              <label htmlFor="decision-comment" className="text-[13px] font-semibold text-foreground">
                {t("center.comment")}
              </label>
              <Textarea
                id="decision-comment"
                rows={3}
                value={comment}
                onChange={(e) => setComment(e.target.value)}
                placeholder={t("center.comment_hint")}
                aria-invalid={Boolean(error) || undefined}
              />
              {error ? <p className="text-xs text-danger">{error}</p> : null}
              <div className="flex flex-wrap justify-end gap-2">
                {data.can_cancel ? (
                  <Button variant="outline" onClick={() => act("cancel")} disabled={pending}>
                    <Undo2 /> {t("center.cancel_request")}
                  </Button>
                ) : null}
                {data.can_edit ? (
                  <Button variant="outline" onClick={() => onEdit(data.ticket_id)} disabled={pending}>
                    <Pencil /> {t("center.edit_resubmit")}
                  </Button>
                ) : null}
                {data.can_act ? (
                  <>
                    <Button variant="danger" onClick={() => act("reject")} disabled={pending}>
                      <X /> {t("center.reject")}
                    </Button>
                    {data.can_return ? (
                      <Button variant="outline" onClick={() => act("return")} disabled={pending}>
                        <CornerUpLeft /> {t("center.return")}
                      </Button>
                    ) : null}
                    <Button onClick={() => act("approve")} loading={pending}>
                      <Check /> {t("center.approve")}
                    </Button>
                  </>
                ) : null}
              </div>
            </section>
          ) : null}
        </div>
      ) : null}
    </Modal>
  );
}

export { RequestDetail };
