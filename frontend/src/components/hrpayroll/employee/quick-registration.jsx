"use client";

import { useMemo, useState, useTransition } from "react";
import Link from "next/link";
import { useRouter } from "next/navigation";
import {
  CheckCircle2,
  Eraser,
  FileText,
  IdCard,
  Save,
  UserPlus,
  User,
  Wallet,
  ArrowLeft,
  Building2,
  Eye,
} from "lucide-react";
import { cn } from "@/lib/utils";
import { Button, buttonVariants } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Select } from "@/components/ui/select";
import { Alert } from "@/components/ui/alert";
import { useToastManager } from "@/components/ui/toast";
import { useT } from "@/lib/i18n";

/*
 * HR & Payroll → Employee → Quick Registration (AnnotechERP 24/03), as
 * Brickland lays it out: the three-step strip; Basic Information; Salary
 * Information with the structure, Gross A, Gross B, the actual salary and
 * the automatic breakdown of both grosses by the structure's gazette lines;
 * the Employee Summary; and Save Options with the recently registered.
 * Save goes to the list, Save & Add New starts again (keeping the joining
 * date), Save & Assignment opens Employee Assignment on the new employee.
 * The same form edits an employee (`?edit=EMP-000001`).
 */

const card = "rounded-xl border border-border bg-surface";
const HEADS = ["basic", "house_rent", "conveyance", "food", "medical", "other"];
const blankForm = (joining = "") => ({
  card_no: "",
  name: "",
  nid: "",
  date_of_birth: "",
  joining_date: joining,
  mobile: "",
  status: "ACTIVE",
  structure: "",
  gross_a: "",
  gross_b: "",
  actual_salary: "",
});
const taka = (n) =>
  `৳ ${Number(n).toLocaleString("en-US", { maximumFractionDigits: 0 })}`;
const messagesOf = (result) => result?.errors ?? { _: [result?.message ?? ""] };

/** The same split the server keeps: Basic = (gross − medical − food − conveyance) ÷ the Basic divisor, House Rent a share of Basic. */
function split(heads, gross) {
  const g = Number(gross);
  if (!heads || !g) return null;
  const fixed = (heads.CONV ?? 0) + (heads.FOOD ?? 0) + (heads.MED ?? 0);
  const basic = Math.max(0, (g - fixed) / (heads.BASIC || 1));
  const parts = {
    basic: Math.round(basic),
    house_rent: Math.round((basic * (heads.HRENT ?? 0)) / 100),
    conveyance: Math.round(heads.CONV ?? 0),
    food: Math.round(heads.FOOD ?? 0),
    medical: Math.round(heads.MED ?? 0),
  };
  const sum = Object.values(parts).reduce((a, b) => a + b, 0);
  return { ...parts, other: Math.round(g) - sum, total: Math.round(g) };
}

function Field({ label, required, hint, error, children, className }) {
  return (
    <label className={cn("flex flex-col gap-1.5", className)}>
      <span className="text-[13px] font-medium text-foreground">
        {label}
        {required ? <span className="text-danger"> *</span> : null}
      </span>
      {children}
      {error ? (
        <span className="text-xs text-danger">{error}</span>
      ) : hint ? (
        <span className="text-xs text-foreground-muted">{hint}</span>
      ) : null}
    </label>
  );
}

function Breakdown({ title, parts, totalLabel }) {
  const t = useT("hrpayroll");
  return (
    <div className="flex flex-col overflow-hidden rounded-lg border border-border">
      <p className="bg-brand-subtle px-3 py-2 text-[13px] font-medium text-brand">
        {title}
      </p>
      {parts ? (
        <dl className="flex flex-col divide-y divide-border text-[13px]">
          {HEADS.map((h) => (
            <div key={h} className="flex justify-between gap-2 px-3 py-1.5">
              <dt className="text-foreground-muted">{t(`emp.head.${h}`)}</dt>
              <dd className="tabular-nums text-foreground">
                {parts[h].toLocaleString("en-US")}
              </dd>
            </div>
          ))}
          <div className="flex justify-between gap-2 px-3 py-1.5 font-semibold">
            <dt className="text-foreground">{totalLabel}</dt>
            <dd className="tabular-nums text-foreground">
              {parts.total.toLocaleString("en-US")}
            </dd>
          </div>
        </dl>
      ) : (
        <p className="px-3 py-6 text-center text-[13px] text-foreground-muted">
          {t("emp.split_empty")}
        </p>
      )}
    </div>
  );
}

/**
 * Quick Registration.
 *
 * @param {{ options: object, initial: object|null, act: (op: string, ...args: any[]) => Promise<object> }} props
 */
function QuickRegistration({ options, initial, act }) {
  const t = useT("hrpayroll");
  const e = (k, p) => t(`emp.${k}`, p);
  const f = (k) => t(`emp.f.${k}`);
  const router = useRouter();
  const toastManager = useToastManager();
  const editing = Boolean(initial);
  // Every field a string: an employee brought in without an NID (Norban's users) has none yet.
  const fromRow = (r) => ({
    card_no: r.card_no ?? "",
    name: r.name ?? "",
    nid: r.nid ?? "",
    date_of_birth: r.date_of_birth ?? "",
    joining_date: r.joining_date ?? "",
    mobile: r.mobile ?? "",
    status: r.status ?? "ACTIVE",
    structure: r.structure ?? "",
    // No salary yet (0): left blank for HR to fill, not a "0" to type over.
    gross_a: Number(r.gross_a) > 0 ? String(r.gross_a) : "",
    gross_b: Number(r.gross_b) > 0 ? String(r.gross_b) : "",
    actual_salary: r.actual_salary == null ? "" : String(r.actual_salary),
  });
  const [form, setForm] = useState(
    initial
      ? fromRow(initial)
      : { ...blankForm(), structure: options.structures[0]?.value ?? "" },
  );
  const [nextCode, setNextCode] = useState(options.next_code);
  const [recent, setRecent] = useState(options.recent);
  const [errors, setErrors] = useState({});
  const [showSplit, setShowSplit] = useState(true);
  const [pending, startTransition] = useTransition();
  const set = (k) => (v) => {
    setForm((all) => ({ ...all, [k]: v ?? "" }));
    // A corrected field drops its message.
    setErrors((all) => (all[k] ? Object.fromEntries(Object.entries(all).filter(([key]) => key !== k)) : all));
  };
  const structure = options.structures.find((s) => s.value === form.structure);
  const splitA = useMemo(
    () => split(structure?.heads, form.gross_a),
    [structure, form.gross_a],
  );
  const splitB = useMemo(
    () => split(structure?.heads, form.gross_b),
    [structure, form.gross_b],
  );
  const err = (k) => errors[k]?.[0];
  const general = Object.entries(errors)
    .filter(([k]) => !(k in blankForm()))
    .flatMap(([, v]) => v);

  function save(then) {
    setErrors({});
    const payload = Object.fromEntries(
      Object.entries(form).map(([k, v]) => [k, v === "" ? null : v]),
    );
    startTransition(async () => {
      const result = editing
        ? await act("update", initial.code, payload)
        : await act("store", payload);
      if (result?.status !== "success") return setErrors(messagesOf(result));
      const code = result.data.code;
      toastManager.add({
        title: editing ? e("updated", { code }) : e("saved", { code }),
        type: "success",
      });
      if (then === "assign")
        return router.push(
          `/hr-payroll/employee-assignment?employee=${encodeURIComponent(code)}`,
        );
      if (then === "list" || editing)
        return router.push("/hr-payroll/employee-register");
      // Save & Add New: start again, keeping the joining date and the structure for the next entry.
      setRecent((all) => [result.data, ...all].slice(0, 5));
      setNextCode(`EMP-${String(Number(code.slice(4)) + 1).padStart(6, "0")}`);
      setForm({ ...blankForm(form.joining_date), structure: form.structure });
    });
  }

  function onKeyDown(event) {
    if (event.key === "Enter" && !editing && event.target.tagName === "INPUT") {
      event.preventDefault();
      save("new");
    }
  }

  const steps = [
    ["basic", Boolean(form.card_no && form.name && form.nid)],
    ["salary", Boolean(form.structure && form.gross_a && form.gross_b)],
    ["finish", false],
  ];
  const current = steps.findIndex(([, done]) => !done);
  const summary = [
    ["card", form.card_no],
    ["nid", form.nid],
    ["dob", form.date_of_birth],
    ["mobile", form.mobile],
    ["joining", form.joining_date],
    ["structure", structure?.label],
    ["gross_a", form.gross_a ? taka(form.gross_a) : ""],
    ["gross_b", form.gross_b ? taka(form.gross_b) : ""],
    ["actual", form.actual_salary ? taka(form.actual_salary) : ""],
  ];

  return (
    <div className="flex flex-col gap-4" onKeyDown={onKeyDown}>
      <ol className={cn(card, "grid gap-3 p-4 sm:grid-cols-3")}>
        {steps.map(([k, done], i) => (
          <li key={k} className="flex items-center gap-3">
            <span
              className={cn(
                "flex size-8 shrink-0 items-center justify-center rounded-full text-sm font-semibold",
                done
                  ? "bg-success text-white"
                  : i === current
                    ? "bg-brand text-brand-foreground"
                    : "bg-surface-muted text-foreground-muted",
              )}
            >
              {done ? <CheckCircle2 className="size-4" aria-hidden /> : i + 1}
            </span>
            <div>
              <p
                className={cn(
                  "text-sm font-medium",
                  i === current ? "text-brand" : "text-foreground",
                )}
              >
                {e(`step.${k}`)}
              </p>
              <p className="text-xs text-foreground-muted">
                {e(`step.${k}_hint`)}
              </p>
            </div>
          </li>
        ))}
      </ol>

      {general.length ? (
        <Alert variant="danger">
          {general.map((m) => (
            <p key={m}>{m}</p>
          ))}
        </Alert>
      ) : null}

      <div className="grid gap-4 xl:grid-cols-[1fr_1.15fr_1fr_0.9fr]">
        <section className={cn(card, "flex flex-col gap-4 p-5")}>
          <div className="flex items-center justify-between gap-2">
            <h2 className="flex items-center gap-2 text-base font-semibold text-foreground">
              <User className="size-4" aria-hidden />
              {e("step.basic")}
            </h2>
            {!editing ? (
              <Button
                size="sm"
                variant="outline"
                onClick={() => (
                  setForm({
                    ...blankForm(form.joining_date),
                    structure: form.structure,
                  }),
                  setErrors({})
                )}
              >
                <Eraser className="size-4" aria-hidden />
                {e("clear")}
              </Button>
            ) : null}
          </div>
          <Field label={f("code")} hint={editing ? null : f("code_hint")}>
            <div className="relative">
              <Input
                value={editing ? initial.code : nextCode}
                readOnly
                aria-label={f("code")}
                className="bg-surface-muted pr-14"
              />
              {!editing ? (
                <span className="absolute top-1/2 right-3 -translate-y-1/2 text-xs text-foreground-muted">
                  {f("auto")}
                </span>
              ) : null}
            </div>
          </Field>
          <Field label={f("card")} required error={err("card_no")}>
            <Input
              value={form.card_no}
              onChange={(ev) => set("card_no")(ev.target.value)}
              placeholder={f("card_ph")}
              aria-label={f("card")}
              autoFocus={!editing}
            />
          </Field>
          <Field label={f("name")} required error={err("name")}>
            <Input
              value={form.name}
              onChange={(ev) => set("name")(ev.target.value)}
              placeholder={f("name_ph")}
              aria-label={f("name")}
            />
          </Field>
          <Field label={f("nid")} required error={err("nid")}>
            <Input
              value={form.nid}
              onChange={(ev) => set("nid")(ev.target.value.replace(/\D/g, ""))}
              inputMode="numeric"
              placeholder={f("nid_ph")}
              aria-label={f("nid")}
            />
          </Field>
          <div className="grid grid-cols-2 gap-3">
            <Field label={f("dob")} error={err("date_of_birth")}>
              <Input
                type="date"
                value={form.date_of_birth}
                onChange={(ev) => set("date_of_birth")(ev.target.value)}
                aria-label={f("dob")}
              />
            </Field>
            <Field
              label={f("joining")}
              hint={f("joining_hint")}
              error={err("joining_date")}
            >
              <Input
                type="date"
                value={form.joining_date}
                onChange={(ev) => set("joining_date")(ev.target.value)}
                aria-label={f("joining")}
              />
            </Field>
          </div>
          <Field label={f("mobile")} error={err("mobile")}>
            <Input
              value={form.mobile}
              onChange={(ev) =>
                set("mobile")(ev.target.value.replace(/\D/g, ""))
              }
              inputMode="numeric"
              placeholder={f("mobile_ph")}
              aria-label={f("mobile")}
            />
          </Field>
          <Field label={f("status")} hint={f("status_hint")}>
            <Select
              value={form.status}
              onValueChange={set("status")}
              options={options.statuses.map((s) => ({
                value: s,
                label: e(`status.${s}`),
              }))}
              aria-label={f("status")}
            />
          </Field>
        </section>

        <section className={cn(card, "flex flex-col gap-4 p-5")}>
          <h2 className="flex items-center gap-2 text-base font-semibold text-foreground">
            <Wallet className="size-4" aria-hidden />
            {e("step.salary")}
          </h2>
          <Field label={f("structure")} required error={err("structure")}>
            <Select
              value={form.structure}
              onValueChange={set("structure")}
              options={options.structures.map((s) => ({
                value: s.value,
                label: s.label,
              }))}
              placeholder={f("select")}
              aria-label={f("structure")}
            />
          </Field>
          <div className="grid grid-cols-2 gap-3">
            <Field
              label={f("gross_a")}
              required
              hint={f("gross_a_hint")}
              error={err("gross_a")}
            >
              <Input
                type="number"
                min="0"
                value={form.gross_a}
                onChange={(ev) => set("gross_a")(ev.target.value)}
                placeholder="14000"
                aria-label={f("gross_a")}
              />
            </Field>
            <Field
              label={f("gross_b")}
              required
              hint={f("gross_b_hint")}
              error={err("gross_b")}
            >
              <Input
                type="number"
                min="0"
                value={form.gross_b}
                onChange={(ev) => set("gross_b")(ev.target.value)}
                placeholder="18000"
                aria-label={f("gross_b")}
              />
            </Field>
          </div>
          <Field
            label={f("actual")}
            hint={f("actual_hint")}
            error={err("actual_salary")}
            className="sm:w-1/2"
          >
            <Input
              type="number"
              min="0"
              value={form.actual_salary}
              onChange={(ev) => set("actual_salary")(ev.target.value)}
              placeholder="15000"
              aria-label={f("actual")}
            />
          </Field>
          <div className="flex flex-col gap-3 rounded-lg border border-border p-3">
            <div className="flex items-center justify-between gap-2">
              <p className="flex items-center gap-2 text-sm font-medium text-foreground">
                <Eye className="size-4 text-brand" aria-hidden />
                {e("preview")}
              </p>
              <Button
                size="sm"
                variant="outline"
                onClick={() => setShowSplit((v) => !v)}
              >
                {showSplit ? e("hide") : e("show")}
              </Button>
            </div>
            {showSplit ? (
              <div className="grid gap-3 sm:grid-cols-2">
                <Breakdown
                  title={e("split_a")}
                  parts={splitA}
                  totalLabel={e("total_a")}
                />
                <Breakdown
                  title={e("split_b")}
                  parts={splitB}
                  totalLabel={e("total_b")}
                />
              </div>
            ) : null}
          </div>
        </section>

        <section className={cn(card, "flex flex-col gap-4 p-5")}>
          <h2 className="flex items-center gap-2 text-base font-semibold text-foreground">
            <FileText className="size-4" aria-hidden />
            {e("summary")}
          </h2>
          <div className="flex items-center gap-3 border-b border-border pb-4">
            <span className="flex size-16 items-center justify-center rounded-lg bg-surface-muted text-foreground-muted">
              <User className="size-7" aria-hidden />
            </span>
            <div className="flex flex-col gap-1">
              <span className="font-mono text-xs text-foreground-muted">
                {editing ? initial.code : nextCode}
              </span>
              <span className="text-base font-semibold text-foreground">
                {form.name || e("new_employee")}
              </span>
              <span className="w-fit rounded-full bg-success-subtle px-2 py-0.5 text-xs font-medium text-success">
                {e(`status.${form.status}`)}
              </span>
            </div>
          </div>
          <dl className="flex flex-col gap-1.5 text-[13px]">
            {summary.map(([k, v]) => (
              <div key={k} className="flex justify-between gap-3">
                <dt className="text-foreground-muted">
                  {f(k === "card" ? "card" : k)}
                </dt>
                <dd className="truncate text-right text-foreground">
                  {v || "—"}
                </dd>
              </div>
            ))}
          </dl>
          <div className="flex flex-col gap-2 border-t border-border pt-4">
            <div className="flex items-center justify-between gap-2">
              <p className="flex items-center gap-2 text-sm font-medium text-foreground">
                <Building2 className="size-4" aria-hidden />
                {e("assignment_status")}
              </p>
              <span
                className={cn(
                  "rounded-full px-2 py-0.5 text-xs font-medium",
                  initial?.assigned
                    ? "bg-success-subtle text-success"
                    : "bg-warning-subtle text-warning",
                )}
              >
                {e(`assignment.${initial?.assigned ? "ASSIGNED" : "PENDING"}`)}
              </span>
            </div>
            {!initial?.assigned ? (
              <p className="text-[13px] text-foreground-muted">
                {e("pending_hint")}
              </p>
            ) : null}
          </div>
        </section>

        <section className={cn(card, "flex flex-col gap-3 p-5")}>
          <h2 className="flex items-center gap-2 text-base font-semibold text-foreground">
            <Save className="size-4" aria-hidden />
            {e("save_options")}
          </h2>
          {editing ? (
            <Button onClick={() => save("list")} loading={pending}>
              <Save className="size-4" aria-hidden />
              {e("update")}
            </Button>
          ) : (
            <>
              <Button
                variant="outline"
                onClick={() => save("list")}
                loading={pending}
              >
                <Save className="size-4" aria-hidden />
                {e("save")}
              </Button>
              <Button
                variant="success"
                onClick={() => save("assign")}
                disabled={pending}
              >
                <IdCard className="size-4" aria-hidden />
                {e("save_assign")}
              </Button>
              <Button onClick={() => save("new")} disabled={pending}>
                <UserPlus className="size-4" aria-hidden />
                {e("save_new")}
              </Button>
              <p className="text-center text-xs text-foreground-muted">
                {e("enter_hint")}
              </p>
            </>
          )}
          <Link
            href="/hr-payroll/employee-register"
            className={cn(buttonVariants({ variant: "outline" }))}
          >
            <ArrowLeft className="size-4" aria-hidden />
            {e("back")}
          </Link>
          {recent.length ? (
            <div className="mt-2 flex flex-col gap-2 border-t border-border pt-4">
              <p className="text-xs font-semibold tracking-wide text-foreground-muted uppercase">
                {e("recent")}
              </p>
              <ul className="flex flex-col gap-2">
                {recent.map((r) => (
                  <li key={r.code} className="flex items-center gap-2">
                    <span className="flex size-8 shrink-0 items-center justify-center rounded-md bg-surface-muted text-foreground-muted">
                      <User className="size-4" aria-hidden />
                    </span>
                    <div className="min-w-0 flex-1">
                      <p className="truncate text-[13px] font-medium text-foreground">
                        {r.name}
                      </p>
                      <p className="truncate text-xs text-foreground-muted">
                        {r.code} • {r.card_no}
                      </p>
                    </div>
                    <span
                      className={cn(
                        "rounded-full px-1.5 py-0.5 text-[11px] font-medium",
                        r.assigned
                          ? "bg-success-subtle text-success"
                          : "bg-warning-subtle text-warning",
                      )}
                    >
                      {e(`assignment.${r.assigned ? "ASSIGNED" : "PENDING"}`)}
                    </span>
                  </li>
                ))}
              </ul>
            </div>
          ) : null}
        </section>
      </div>
    </div>
  );
}

export { QuickRegistration };
