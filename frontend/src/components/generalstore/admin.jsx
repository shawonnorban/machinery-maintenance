"use client";

import { useState, useTransition } from "react";
import { Download, Save, Search } from "lucide-react";
import { cn } from "@/lib/utils";
import { Button, buttonVariants } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Switch } from "@/components/ui/switch";
import { useToastManager } from "@/components/ui/toast";
import { useT } from "@/lib/i18n";
import { card, stamp, dash, messagesOf, Field } from "./common";

/*
 * General Store → Administration (AnnotechERP 10/07). Audit Log: the latest
 * 1,000 entries, searched in place and taken as Excel. Settings: requisition
 * approval, the issue rules, the name and footer on vouchers, and who holds
 * the store roles (given in Access Control).
 */

/** Audit Log. */
function AuditLog({ rows: firstRows, act }) {
  const t = useT("store");
  const a = (k, p) => t(`admin.${k}`, p);
  const [q, setQ] = useState("");
  const [shown, setShown] = useState("");
  const [rows, setRows] = useState(firstRows);
  const [pending, startTransition] = useTransition();

  return (
    <section className={cn(card, "min-w-0 overflow-hidden")}>
      <div className="flex flex-wrap items-start justify-between gap-3 p-5">
        <div>
          <h2 className="text-base font-semibold text-foreground">
            {a("audit_title", { n: rows.length })}
          </h2>
          <p className="mt-0.5 text-[13px] text-foreground-muted">
            {a("audit_sub")}
          </p>
        </div>
        <a
          href={`/api/general-store/excel/audit-log${shown ? `?q=${encodeURIComponent(shown)}` : ""}`}
          className={buttonVariants({ variant: "outline", size: "sm" })}
        >
          <Download className="size-4" aria-hidden />
          {a("excel")}
        </a>
      </div>
      <form
        className="flex flex-wrap items-end gap-2 px-5 pb-4"
        onSubmit={(ev) => {
          ev.preventDefault();
          startTransition(async () => {
            const res = await act("audit", q.trim());
            if (res?.status === "success") {
              setRows(res.data.rows);
              setShown(q.trim());
            }
          });
        }}
      >
        <Field label={a("search")} className="min-w-72 flex-1">
          <Input
            value={q}
            onChange={(ev) => setQ(ev.target.value)}
            placeholder={a("search_ph")}
            aria-label={a("search")}
          />
        </Field>
        <Button type="submit" loading={pending}>
          <Search className="size-4" aria-hidden />
          {a("search")}
        </Button>
      </form>
      <div className="overflow-x-auto">
        <table className="w-full min-w-[900px] text-sm">
          <thead className="bg-brand text-brand-foreground">
            <tr>
              {["when", "user", "action", "screen", "summary"].map((k) => (
                <th
                  key={k}
                  className="px-3 py-2.5 text-left text-xs font-semibold uppercase"
                >
                  {a(`col.${k}`)}
                </th>
              ))}
            </tr>
          </thead>
          <tbody>
            {rows.length === 0 ? (
              <tr>
                <td
                  colSpan={5}
                  className="px-3 py-10 text-center text-foreground-muted"
                >
                  {a("empty")}
                </td>
              </tr>
            ) : null}
            {rows.map((r, i) => (
              <tr key={i} className="border-t border-border align-top">
                <td className="px-3 py-2.5 whitespace-nowrap">
                  {stamp(r.when)}
                </td>
                <td className="px-3 py-2.5">{dash(r.user)}</td>
                <td className="px-3 py-2.5 font-mono text-xs">{r.action}</td>
                <td className="px-3 py-2.5 font-mono text-xs">{r.screen}</td>
                <td className="px-3 py-2.5">{r.summary}</td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </section>
  );
}

function Toggle({ label, hint, checked, onChange }) {
  return (
    <label className="flex items-start justify-between gap-4 rounded-lg border border-border p-3">
      <span className="flex flex-col gap-0.5">
        <span className="text-sm font-medium text-foreground">{label}</span>
        {hint ? (
          <span className="text-xs text-foreground-muted">{hint}</span>
        ) : null}
      </span>
      <Switch checked={checked} onCheckedChange={onChange} aria-label={label} />
    </label>
  );
}

/** Settings. */
function StoreSettings({ data, act }) {
  const t = useT("store");
  const s = (k, p) => t(`admin.set.${k}`, p);
  const toastManager = useToastManager();
  const [form, setForm] = useState({
    requisition_approval: data.requisition_approval,
    direct_issue: data.direct_issue,
    receiver_required: data.receiver_required,
    voucher_name: data.voucher_name ?? "",
    voucher_footer: data.voucher_footer ?? "",
  });
  const [saved, setSaved] = useState(data);
  const [errors, setErrors] = useState({});
  const [pending, startTransition] = useTransition();
  const set = (k) => (v) => setForm((all) => ({ ...all, [k]: v }));

  function save() {
    setErrors({});
    startTransition(async () => {
      const res = await act("saveSettings", {
        ...form,
        voucher_name: form.voucher_name || null,
        voucher_footer: form.voucher_footer || null,
      });
      if (res?.status !== "success") return setErrors(messagesOf(res));
      setSaved(res.data);
      toastManager.add({ title: s("saved"), type: "success" });
    });
  }

  return (
    <div className="grid gap-4 lg:grid-cols-[minmax(0,1.4fr)_minmax(0,1fr)]">
      <section className={cn(card, "flex flex-col gap-5 p-5")}>
        <div className="flex flex-col gap-2">
          <h2 className="text-base font-semibold text-foreground">
            {s("approval_title")}
          </h2>
          <Toggle
            label={s("approval")}
            hint={s("approval_hint")}
            checked={form.requisition_approval}
            onChange={set("requisition_approval")}
          />
        </div>
        <div className="flex flex-col gap-2">
          <h2 className="text-base font-semibold text-foreground">
            {s("issue_title")}
          </h2>
          <Toggle
            label={s("direct")}
            checked={form.direct_issue}
            onChange={set("direct_issue")}
          />
          <Toggle
            label={s("receiver")}
            checked={form.receiver_required}
            onChange={set("receiver_required")}
          />
        </div>
        <div className="flex flex-col gap-3">
          <h2 className="text-base font-semibold text-foreground">
            {s("voucher_title")}
          </h2>
          <Field
            label={s("voucher_name")}
            hint={s("voucher_name_hint", { name: data.company_name ?? "" })}
            error={errors.voucher_name?.[0]}
          >
            <Input
              value={form.voucher_name}
              maxLength={160}
              onChange={(ev) => set("voucher_name")(ev.target.value)}
              placeholder={data.company_name ?? ""}
              aria-label={s("voucher_name")}
            />
          </Field>
          <Field label={s("voucher_footer")} error={errors.voucher_footer?.[0]}>
            <Input
              value={form.voucher_footer}
              maxLength={300}
              onChange={(ev) => set("voucher_footer")(ev.target.value)}
              aria-label={s("voucher_footer")}
            />
          </Field>
        </div>
        <div className="flex flex-wrap items-center justify-between gap-3 border-t border-border pt-4">
          <p className="text-xs text-foreground-muted">
            {saved.updated_by
              ? s("updated", {
                  who: saved.updated_by,
                  when: stamp(saved.updated_at),
                })
              : null}
          </p>
          <Button onClick={save} loading={pending}>
            <Save className="size-4" aria-hidden />
            {s("save")}
          </Button>
        </div>
      </section>
      <section className={cn(card, "flex flex-col gap-3 p-5")}>
        <div>
          <h2 className="text-base font-semibold text-foreground">
            {s("roles_title")}
          </h2>
          <p className="text-[13px] text-foreground-muted">{s("roles_hint")}</p>
        </div>
        {data.roles.map((role) => (
          <div key={role.role} className="rounded-lg border border-border p-3">
            <p className="text-sm font-semibold text-foreground">
              {s(`role.${role.role}`)}
            </p>
            {role.people.length ? (
              <ul className="mt-1.5 flex flex-col gap-1 text-sm">
                {role.people.map((p) => (
                  <li
                    key={p.email}
                    className="flex flex-wrap justify-between gap-2"
                  >
                    <span className="text-foreground">{p.name}</span>
                    <span className="text-xs text-foreground-muted">
                      {p.email}
                    </span>
                  </li>
                ))}
              </ul>
            ) : (
              <p className="mt-1 text-xs text-foreground-muted">
                {s("nobody")}
              </p>
            )}
          </div>
        ))}
      </section>
    </div>
  );
}

export { AuditLog, StoreSettings };
