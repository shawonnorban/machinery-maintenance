"use client";

import { useState, useTransition } from "react";
import Link from "next/link";
import { useRouter } from "next/navigation";
import { FolderOpen, Trash2 } from "lucide-react";
import { buttonVariants, Button } from "@/components/ui/button";
import { ConfirmDialog } from "@/components/ui/confirm-dialog";
import { useToastManager } from "@/components/ui/toast";
import { useT } from "@/lib/i18n";

/*
 * Pre-Budget → Draft Requisition (AnnotechERP 33/34), Norban's list: the Add
 * Requisition pages kept as drafts — saved when, how many rows, which
 * buyers and styles, and — for who sees every draft — whose; each opened
 * again in Add Requisition (by its number) or deleted. A person sees their
 * own drafts only.
 */
function DraftList({ data, act }) {
  const { drafts, all } = data;
  const t = useT("prebudget");
  const router = useRouter();
  const toast = useToastManager();
  const [deleting, setDeleting] = useState(null);
  const [pending, start] = useTransition();
  // Who sees every draft (View All) sees whose each one is; a person seeing only their own needs no such column.
  const showOwner = all;

  const remove = () =>
    start(async () => {
      const res = await act("deleteDraft", deleting);
      if (res?.status === "success") {
        toast.add({ title: t("draft.deleted", { no: deleting }), type: "success" });
        setDeleting(null);
        router.refresh();
      } else toast.add({ title: res?.message ?? t("draft.failed"), type: "danger" });
    });

  return (
    <section className="rounded-lg border border-border bg-surface text-[13px]">
      <div className="flex items-center justify-between gap-2 border-b border-border px-4 py-3">
        <div>
          <h2 className="font-semibold">{t("draft.title")}</h2>
          <p className="text-[12px] text-foreground-muted">{t("draft.subtitle", { n: drafts.length })}</p>
        </div>
        <Link href="/pre-budget/add-requisition" className={buttonVariants({ size: "sm" })}>
          {t("draft.new")}
        </Link>
      </div>
      {drafts.length === 0 ? (
        <p className="px-4 py-10 text-center text-foreground-muted">{t("draft.empty")}</p>
      ) : (
        <div className="overflow-x-auto">
          <table className="w-full">
            <thead className="bg-surface-muted text-left text-[11px] font-semibold tracking-wide text-foreground-muted uppercase">
              <tr>
                <th className="px-4 py-2">{t("draft.f.no")}</th>
                <th className="px-4 py-2">{t("draft.f.saved")}</th>
                <th className="px-4 py-2 text-right">{t("draft.f.rows")}</th>
                <th className="px-4 py-2">{t("draft.f.type")}</th>
                <th className="px-4 py-2">{t("draft.f.buyers")}</th>
                <th className="px-4 py-2">{t("draft.f.styles")}</th>
                {showOwner ? <th className="px-4 py-2">{t("draft.f.owner")}</th> : null}
                <th className="px-4 py-2 text-right">{t("draft.f.actions")}</th>
              </tr>
            </thead>
            <tbody>
              {drafts.map((d) => (
                <tr key={d.draft_no} className="border-t border-border">
                  <td className="px-4 py-2 font-mono font-semibold">D-{d.draft_no}</td>
                  <td className="px-4 py-2 whitespace-nowrap">{d.saved_at ? new Date(d.saved_at).toLocaleString("en-GB", { day: "2-digit", month: "short", year: "2-digit", hour: "2-digit", minute: "2-digit", timeZone: "Asia/Dhaka", hourCycle: "h12" }).replace(/\b[ap]m\b/, (x) => x.toUpperCase()) : "—"}</td>
                  <td className="px-4 py-2 text-right tabular-nums">{d.row_count}</td>
                  <td className="px-4 py-2">{d.types.join(", ") || "—"}</td>
                  <td className="px-4 py-2">{d.buyers.join(", ") || "—"}</td>
                  <td className="px-4 py-2">{d.styles.join(", ") || "—"}</td>
                  {showOwner ? <td className="px-4 py-2">{d.owner ?? "—"}</td> : null}
                  <td className="px-4 py-2 text-right whitespace-nowrap">
                    <Link href={`/pre-budget/add-requisition?draft=${d.draft_no}`} className={buttonVariants({ size: "sm", variant: "outline" })}>
                      <FolderOpen /> {t("draft.open")}
                    </Link>{" "}
                    <Button type="button" size="sm" variant="outline" onClick={() => setDeleting(d.draft_no)} aria-label={t("draft.delete")}>
                      <Trash2 /> {t("draft.delete")}
                    </Button>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
      <ConfirmDialog
        open={deleting !== null}
        onOpenChange={(o) => !o && setDeleting(null)}
        title={t("draft.delete_title", { no: deleting ?? "" })}
        description={t("draft.delete_text")}
        confirmLabel={t("draft.delete")}
        cancelLabel={t("draft.cancel")}
        destructive
        loading={pending}
        onConfirm={remove}
      />
    </section>
  );
}

export { DraftList };
