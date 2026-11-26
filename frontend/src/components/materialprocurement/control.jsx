"use client";

import { useState, useTransition } from "react";
import Link from "next/link";
import { CheckCheck, Search } from "lucide-react";
import { cn } from "@/lib/utils";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Alert } from "@/components/ui/alert";
import { useT } from "@/lib/i18n";
import { clockFormat } from "@/lib/clock";

/*
 * Material Procurement → Control (AnnotechERP 08/07), as Brickland draws it.
 * Notifications: this module's notifications addressed to the caller — new
 * requirements, quotations, revisions, decisions, handovers and receipts —
 * unread marked with a dot, each opening its screen, and Mark all read.
 * Audit Trail: every critical transaction in the module, permanent and
 * read-only, searched by user, document or summary.
 */

const card = "rounded-xl border border-border bg-surface";
const dateTimeFmt = clockFormat({
  day: "2-digit",
  month: "short",
  year: "numeric",
  hour: "2-digit",
  minute: "2-digit",
  timeZone: "Asia/Dhaka",
});
const at = (iso) => (iso ? dateTimeFmt.format(new Date(iso)) : "—");
const messagesOf = (result) => {
  const fields = [...new Set(Object.values(result?.errors ?? {}).flat())];
  return fields.length ? fields : [result?.message ?? ""];
};
const actionTone = {
  CREATE: "bg-success-subtle text-success",
  UPDATE: "bg-info-subtle text-info",
  DELETE: "bg-danger-subtle text-danger",
};

/**
 * Notifications.
 *
 * @param {{ data: { rows: object[] }, act: (op: string, ...args: any[]) => Promise<object> }} props
 */
function ProcurementNotifications({ data, act }) {
  const t = useT("materialprocurement");
  const [rows, setRows] = useState(data.rows);
  const [errors, setErrors] = useState(null);
  const [pending, startTransition] = useTransition();
  const unread = rows.filter((r) => !r.read).length;

  function readAll() {
    setErrors(null);
    startTransition(async () => {
      const result = await act("readAll");
      if (result?.status !== "success") return setErrors(messagesOf(result));
      setRows((all) => all.map((r) => ({ ...r, read: true })));
    });
  }

  // Opening one marks it read; the screen it points at loads as usual.
  function open(row) {
    if (row.read) return;
    setRows((all) =>
      all.map((r) => (r.id === row.id ? { ...r, read: true } : r)),
    );
    act("read", row.id);
  }

  return (
    <section className={cn(card, "min-w-0 overflow-hidden")}>
      <div className="flex flex-wrap items-start justify-between gap-3 p-5">
        <div>
          <h2 className="text-base font-semibold text-foreground">
            {t("ctl.notif_title")}
          </h2>
          <p className="mt-0.5 text-[13px] text-foreground-muted">
            {t("ctl.unread", { unread, total: rows.length })}
          </p>
        </div>
        <Button
          variant="outline"
          size="sm"
          onClick={readAll}
          loading={pending}
          disabled={!unread}
        >
          <CheckCheck className="size-4" aria-hidden />
          {t("ctl.mark_all")}
        </Button>
      </div>
      {errors ? (
        <div className="px-5 pb-4">
          <Alert variant="danger">
            {errors.map((e) => (
              <p key={e}>{e}</p>
            ))}
          </Alert>
        </div>
      ) : null}
      {rows.length === 0 ? (
        <p className="border-t border-border px-5 py-10 text-center text-sm text-foreground-muted">
          {t("ctl.no_notifications")}
        </p>
      ) : (
        <ul className="divide-y divide-border border-t border-border">
          {rows.map((r) => (
            <li
              key={r.id}
              className={cn(
                "flex gap-3 px-5 py-4",
                !r.read && "bg-brand-subtle/40",
              )}
            >
              <span
                className={cn(
                  "mt-1.5 size-2 shrink-0 rounded-full",
                  r.read ? "bg-transparent" : "bg-brand",
                )}
                aria-hidden
              />
              <div className="flex min-w-0 flex-col gap-0.5">
                <p className="text-sm font-semibold text-foreground">
                  {r.title}
                </p>
                <p className="text-sm text-foreground">{r.body}</p>
                <p className="text-xs text-foreground-muted">
                  {at(r.at)}
                  {r.url ? (
                    <>
                      {" · "}
                      <Link
                        href={r.url}
                        onClick={() => open(r)}
                        className="font-medium text-brand hover:underline"
                      >
                        {t("ctl.open")}
                      </Link>
                    </>
                  ) : null}
                </p>
              </div>
            </li>
          ))}
        </ul>
      )}
    </section>
  );
}

/** Audit Trail: permanent, read-only; the search runs over what is already loaded. */
function AuditTrail({ data }) {
  const t = useT("materialprocurement");
  const [needle, setNeedle] = useState("");
  const q = needle.trim().toLowerCase();
  const shown = data.rows.filter(
    (r) =>
      !q ||
      [r.user, r.reference, r.summary, r.screen, r.action].some((v) =>
        (v ?? "").toLowerCase().includes(q),
      ),
  );

  return (
    <section className={cn(card, "min-w-0 overflow-hidden")}>
      <div className="flex flex-col gap-4 p-5">
        <div>
          <h2 className="text-base font-semibold text-foreground">
            {t("ctl.audit_title")}
          </h2>
          <p className="mt-0.5 text-[13px] text-foreground-muted">
            {t("ctl.audit_hint")}
          </p>
        </div>
        <div className="relative w-full max-w-sm">
          <Search
            className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-foreground-muted"
            aria-hidden
          />
          <Input
            value={needle}
            onChange={(e) => setNeedle(e.target.value)}
            placeholder={t("ctl.audit_search")}
            aria-label={t("ctl.audit_search")}
            className="pl-9"
          />
        </div>
      </div>
      <div className="overflow-x-auto">
        <table className="w-full min-w-[960px] text-sm">
          <thead className="bg-brand text-brand-foreground">
            <tr>
              {["when", "user", "action", "screen", "reference", "summary"].map(
                (k) => (
                  <th
                    key={k}
                    className="px-3 py-2.5 text-left text-xs font-semibold uppercase"
                  >
                    {t(`ctl.col.${k}`)}
                  </th>
                ),
              )}
            </tr>
          </thead>
          <tbody>
            {shown.length === 0 ? (
              <tr>
                <td
                  colSpan={6}
                  className="px-3 py-10 text-center text-foreground-muted"
                >
                  {t("ctl.no_audit")}
                </td>
              </tr>
            ) : null}
            {shown.map((r) => (
              <tr key={r.id} className="border-t border-border align-top">
                <td className="px-3 py-2.5 whitespace-nowrap text-foreground">
                  {at(r.at)}
                </td>
                <td className="px-3 py-2.5 text-foreground">{r.user ?? "—"}</td>
                <td className="px-3 py-2.5">
                  <span
                    className={cn(
                      "rounded-md px-1.5 py-0.5 text-xs font-medium",
                      actionTone[r.action],
                    )}
                  >
                    {r.action}
                  </span>
                </td>
                <td className="px-3 py-2.5 text-foreground">
                  {t(`ctl.screen.${r.screen}`)}
                </td>
                <td className="px-3 py-2.5 font-mono text-xs whitespace-nowrap text-foreground">
                  {r.reference ?? "—"}
                </td>
                <td className="px-3 py-2.5 text-foreground">{r.summary}</td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </section>
  );
}

export { ProcurementNotifications, AuditTrail };
