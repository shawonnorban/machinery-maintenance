"use client";

import { useState, useTransition } from "react";
import { cn } from "@/lib/utils";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Select } from "@/components/ui/select";
import { Switch } from "@/components/ui/switch";
import { Modal } from "@/components/ui/modal";
import { Alert } from "@/components/ui/alert";
import { useToastManager } from "@/components/ui/toast";
import { useT } from "@/lib/i18n";
import { clockFormat } from "@/lib/clock";

/*
 * Material Procurement → Control → Supplier Portal Access (AnnotechERP 08/07),
 * as Brickland draws it: one card of portal logins — supplier, contact,
 * sign-in email, created, last sign-in and the Active switch, which saves at
 * once — and New supplier login, a dialog of the supplier, contact name,
 * sign-in email and initial password.
 */

const card = "rounded-xl border border-border bg-surface";
const dateTimeFmt = clockFormat({ day: "2-digit", month: "short", year: "numeric", hour: "2-digit", minute: "2-digit", timeZone: "Asia/Dhaka" });
const at = (iso) => (iso ? dateTimeFmt.format(new Date(iso)) : "—");
const messagesOf = (result) => {
  const fields = [...new Set(Object.values(result?.errors ?? {}).flat())];
  return fields.length ? fields : [result?.message ?? ""];
};
const blank = { supplier_id: "", contact_name: "", email: "", password: "" };

function FieldBox({ label, hint, children }) {
  return (
    <label className="flex flex-col gap-1.5">
      <span className="text-[13px] font-medium text-foreground">
        {label}
        <span className="text-danger"> *</span>
      </span>
      {children}
      {hint ? <span className="text-xs text-foreground-muted">{hint}</span> : null}
    </label>
  );
}

/**
 * @param {{ initial: { logins: object[], suppliers: object[] }, act: (op: string, ...args: any[]) => Promise<object> }} props
 */
function SupplierLogins({ initial, act }) {
  const t = useT("materialprocurement");
  const p = (k, v) => t(`portal.${k}`, v);
  const toastManager = useToastManager();
  const [rows, setRows] = useState(initial.logins);
  const [open, setOpen] = useState(false);
  const [form, setForm] = useState(blank);
  const [errors, setErrors] = useState(null);
  const [pending, startTransition] = useTransition();
  const set = (k) => (v) => setForm((f) => ({ ...f, [k]: v ?? "" }));
  const active = rows.filter((r) => r.active).length;

  function create() {
    setErrors(null);
    startTransition(async () => {
      const result = await act("create", form);
      if (result?.status !== "success") return setErrors(messagesOf(result));
      setRows((all) => [result.data, ...all]);
      setOpen(false);
      setForm(blank);
      toastManager.add({ title: p("created", { email: result.data.email }), type: "success" });
    });
  }

  function flip(row, on) {
    startTransition(async () => {
      const result = await act("active", row.id, on);
      if (result?.status !== "success") return toastManager.add({ title: messagesOf(result)[0], type: "error" });
      setRows((all) => all.map((r) => (r.id === row.id ? result.data : r)));
      toastManager.add({ title: p(on ? "switched_on" : "switched_off", { email: row.email }), type: "success" });
    });
  }

  return (
    <section className={cn(card, "min-w-0 overflow-hidden")}>
      <div className="flex flex-wrap items-start justify-between gap-3 p-5">
        <div>
          <h2 className="text-base font-semibold text-foreground">{p("title")}</h2>
          <p className="mt-0.5 text-[13px] text-foreground-muted">{p("active_n", { n: active })}</p>
        </div>
        <Button size="sm" onClick={() => (setErrors(null), setOpen(true))}>
          {p("new")}
        </Button>
      </div>
      <div className="overflow-x-auto">
        <table className="w-full min-w-[820px] text-sm">
          <thead className="bg-surface-muted">
            <tr>
              {["supplier", "contact", "email", "created", "last_sign_in", "active"].map((k) => (
                <th key={k} className="px-4 py-2.5 text-left text-xs font-semibold uppercase text-foreground-muted">
                  {p(`col.${k}`)}
                </th>
              ))}
            </tr>
          </thead>
          <tbody>
            {rows.length === 0 ? (
              <tr>
                <td colSpan={6} className="px-4 py-10 text-center text-foreground-muted">
                  {p("empty")}
                </td>
              </tr>
            ) : null}
            {rows.map((r) => (
              <tr key={r.id} className="border-t border-border align-top">
                <td className="px-4 py-2.5">
                  <p className="font-medium text-foreground">{r.supplier}</p>
                  <p className="text-xs text-foreground-muted">{r.supplier_code}</p>
                </td>
                <td className="px-4 py-2.5 text-foreground">{r.contact_name}</td>
                <td className="px-4 py-2.5 text-foreground">{r.email}</td>
                <td className="px-4 py-2.5 text-foreground">{at(r.created_at)}</td>
                <td className="px-4 py-2.5 text-foreground">{at(r.last_sign_in)}</td>
                <td className="px-4 py-2.5">
                  <div className="flex items-center gap-2">
                    <Switch checked={r.active} onCheckedChange={(on) => flip(r, on)} disabled={pending} aria-label={p(r.active ? "disable" : "enable", { email: r.email })} />
                    <span className={cn("rounded-full px-2 py-0.5 text-[11px] font-medium", r.active ? "bg-success-subtle text-success" : "bg-surface-muted text-foreground-muted")}>{p(r.active ? "active" : "inactive")}</span>
                  </div>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
      <Modal
        open={open}
        onOpenChange={setOpen}
        title={p("new")}
        description={p("dialog_hint")}
        footer={
          <>
            <Button variant="outline" onClick={() => setOpen(false)} disabled={pending}>
              {p("cancel")}
            </Button>
            <Button onClick={create} loading={pending}>
              {p("create")}
            </Button>
          </>
        }
      >
        <div className="flex flex-col gap-4">
          {errors ? (
            <Alert variant="danger">
              {errors.map((e) => (
                <p key={e}>{e}</p>
              ))}
            </Alert>
          ) : null}
          <FieldBox label={p("f.supplier")}>
            <Select value={form.supplier_id} onValueChange={set("supplier_id")} options={initial.suppliers} placeholder={p("select")} aria-label={p("f.supplier")} />
          </FieldBox>
          <FieldBox label={p("f.contact")}>
            <Input value={form.contact_name} onChange={(e) => set("contact_name")(e.target.value)} aria-label={p("f.contact")} />
          </FieldBox>
          <FieldBox label={p("f.email")}>
            <Input type="email" autoComplete="off" value={form.email} onChange={(e) => set("email")(e.target.value)} aria-label={p("f.email")} />
          </FieldBox>
          <FieldBox label={p("f.password")} hint={p("password_hint")}>
            <Input type="password" autoComplete="new-password" value={form.password} onChange={(e) => set("password")(e.target.value)} aria-label={p("f.password")} />
          </FieldBox>
        </div>
      </Modal>
    </section>
  );
}

export { SupplierLogins };
