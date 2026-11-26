"use client";

import { useState, useTransition } from "react";
import {
  Building2,
  ChevronLeft,
  ChevronRight,
  Search,
  Users,
} from "lucide-react";
import { cn } from "@/lib/utils";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Select } from "@/components/ui/select";
import { Textarea } from "@/components/ui/textarea";
import { Checkbox } from "@/components/ui/checkbox";
import { Alert } from "@/components/ui/alert";
import { useToastManager } from "@/components/ui/toast";
import { useT } from "@/lib/i18n";

/*
 * HR & Payroll → Employee → Employee Assignment (AnnotechERP 24/03), as
 * Brickland lays it out: the employees on the left (Pending / Assigned / All,
 * searched by code, name or card, ticked one by one or a page at a time) and
 * the posting on the right — factory, building, floor, department, section,
 * line, designation, grade and shift, each list narrowing to what sits under
 * the one before, every option from Company Management. Somebody already
 * posted is transferred, with a reason. Nothing reloads.
 */

const card = "rounded-xl border border-border bg-surface";
const blank = {
  factory_id: "",
  building_id: "",
  floor_id: "",
  department_id: "",
  section_id: "",
  line_id: "",
  designation_id: "",
  grade_id: "",
  shift_id: "",
  reason: "",
};
const messagesOf = (result) => result?.errors ?? { _: [result?.message ?? ""] };

function Field({ label, required, hint, error, children }) {
  return (
    <label className="flex flex-col gap-1.5">
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

/**
 * Employee Assignment.
 *
 * @param {{ data: object, options: object, preselect: object|null, act: (op: string, ...args: any[]) => Promise<object> }} props
 */
function EmployeeAssignment({ data, options, preselect, act }) {
  const t = useT("hrpayroll");
  const e = (k, p) => t(`emp.${k}`, p);
  const p = (k) => t(`emp.p.${k}`);
  const toastManager = useToastManager();
  const [tab, setTab] = useState(
    preselect ? "ALL" : data.counts.pending ? "PENDING" : "ALL",
  );
  const [needle, setNeedle] = useState("");
  const [state, setState] = useState(data);
  const [picked, setPicked] = useState(
    preselect ? { [preselect.code]: preselect } : {},
  );
  const [form, setForm] = useState(() =>
    preselect
      ? {
          ...blank,
          ...Object.fromEntries(
            Object.entries(preselect.placement).map(([k, v]) => [k, v ?? ""]),
          ),
        }
      : blank,
  );
  const [errors, setErrors] = useState({});
  const [pending, startTransition] = useTransition();
  const chosen = Object.values(picked);
  const transfer = chosen.some((r) => r.assigned);
  const pages = Math.max(1, Math.ceil(state.total / state.per_page));
  const err = (k) => errors[k]?.[0];

  // One employee picked: the form starts from their current posting; several start blank.
  const formFor = (next) => {
    const list = Object.values(next);
    return list.length === 1
      ? {
          ...blank,
          ...Object.fromEntries(
            Object.entries(list[0].placement).map(([k, v]) => [k, v ?? ""]),
          ),
        }
      : blank;
  };
  function choose(next) {
    setPicked(next);
    setForm(formFor(next));
    setErrors({});
  }

  function load(next = {}) {
    const q = { tab, needle, page: 1, ...next };
    startTransition(async () => {
      const result = await act("list", {
        ...(q.tab !== "ALL" ? { assignment: q.tab } : {}),
        ...(q.needle ? { q: q.needle } : {}),
        page: q.page,
      });
      if (result?.status === "success") setState(result.data);
    });
  }

  const toggle = (r) =>
    choose(
      picked[r.code]
        ? Object.fromEntries(
            Object.entries(picked).filter(([k]) => k !== r.code),
          )
        : { ...picked, [r.code]: r },
    );
  const allTicked =
    state.rows.length > 0 && state.rows.every((r) => picked[r.code]);
  const tickPage = () =>
    choose(
      allTicked
        ? Object.fromEntries(
            Object.entries(picked).filter(
              ([k]) => !state.rows.some((r) => r.code === k),
            ),
          )
        : {
            ...picked,
            ...Object.fromEntries(state.rows.map((r) => [r.code, r])),
          },
    );

  const set = (k) => (v) =>
    setForm((all) => {
      const next = { ...all, [k]: v ?? "" };
      // A changed place clears what sat under it.
      const under =
        {
          factory_id: [
            "building_id",
            "floor_id",
            "department_id",
            "section_id",
            "line_id",
            "designation_id",
          ],
          building_id: ["floor_id", "line_id"],
          floor_id: ["line_id"],
          department_id: ["section_id", "line_id", "designation_id"],
          section_id: ["line_id"],
        }[k] ?? [];
      for (const u of under) next[u] = "";
      return next;
    });
  const list = (rows) => rows.map((o) => ({ value: o.value, label: o.label }));
  const buildings = options.buildings.filter(
    (b) =>
      !form.factory_id || !b.factory_id || b.factory_id === form.factory_id,
  );
  const floors = options.floors.filter(
    (f) => f.building_id === form.building_id,
  );
  const departments = options.departments.filter(
    (d) =>
      !form.factory_id || !d.factory_id || d.factory_id === form.factory_id,
  );
  const sections = options.sections.filter(
    (s) => s.department_id === form.department_id,
  );
  const lines = options.lines.filter(
    (l) =>
      (form.floor_id && l.floor_id === form.floor_id) ||
      (!l.floor_id &&
        ((form.section_id && l.section_id === form.section_id) ||
          (!l.section_id &&
            form.department_id &&
            l.department_id === form.department_id))),
  );
  const designations = options.designations.filter(
    (d) => !d.department_id || d.department_id === form.department_id,
  );

  function save() {
    setErrors({});
    const payload = {
      employees: chosen.map((r) => r.code),
      ...Object.fromEntries(
        Object.entries(form).map(([k, v]) => [k, v === "" ? null : v]),
      ),
    };
    startTransition(async () => {
      const result = await act("assign", payload);
      if (result?.status !== "success") return setErrors(messagesOf(result));
      toastManager.add({
        title: e("assigned_msg", { n: result.data.assigned }),
        type: "success",
      });
      const fresh = Object.fromEntries(
        result.data.rows.map((r) => [r.code, r]),
      );
      choose(chosen.length === 1 ? fresh : {});
      load({ page: state.page });
    });
  }

  const one = chosen.length === 1 ? chosen[0] : null;
  const general = Object.entries(errors)
    .filter(([k]) => !(k in blank))
    .flatMap(([, v]) => v);

  return (
    <div className="grid gap-4 xl:grid-cols-[minmax(0,1.3fr)_minmax(0,1fr)]">
      <section className={cn(card, "flex min-w-0 flex-col gap-4 p-5")}>
        <div>
          <h2 className="flex items-center gap-2 text-base font-semibold text-foreground">
            <Users className="size-4" aria-hidden />
            {e("employees")}
          </h2>
          <p className="mt-0.5 text-[13px] text-foreground-muted">
            {e("pick_hint")}
          </p>
        </div>
        <div className="flex flex-wrap items-end gap-3">
          <div
            role="tablist"
            className="flex rounded-lg border border-border p-1"
          >
            {["PENDING", "ASSIGNED", "ALL"].map((k) => (
              <button
                key={k}
                type="button"
                role="tab"
                aria-selected={tab === k}
                onClick={() => {
                  setTab(k);
                  load({ tab: k });
                }}
                className={cn(
                  "rounded-md px-3 py-1 text-[13px] font-medium",
                  tab === k
                    ? "bg-brand text-brand-foreground"
                    : "text-foreground hover:bg-surface-muted",
                )}
              >
                {e(`tab.${k}`, { n: state.counts[k.toLowerCase()] })}
              </button>
            ))}
          </div>
          <form
            className="flex flex-1 items-end gap-2"
            onSubmit={(ev) => {
              ev.preventDefault();
              load();
            }}
          >
            <label className="flex flex-1 flex-col gap-1.5">
              <span className="text-[13px] font-medium">{e("search")}</span>
              <Input
                value={needle}
                onChange={(ev) => setNeedle(ev.target.value)}
                placeholder={e("search_assign_ph")}
                aria-label={e("search")}
              />
            </label>
            <Button
              type="submit"
              variant="outline"
              size="icon"
              aria-label={e("search")}
              loading={pending}
            >
              <Search className="size-4" aria-hidden />
            </Button>
          </form>
        </div>
        <div className="overflow-x-auto rounded-lg border border-border">
          <table className="w-full min-w-[620px] text-sm">
            <thead className="bg-brand text-brand-foreground">
              <tr>
                <th className="w-10 px-3 py-2.5">
                  <Checkbox
                    checked={allTicked}
                    onCheckedChange={tickPage}
                    aria-label={e("tick_page")}
                  />
                </th>
                {[
                  "employee",
                  "card",
                  "designation",
                  "department",
                  "line",
                  "status",
                ].map((k) => (
                  <th
                    key={k}
                    className="px-3 py-2.5 text-left text-xs font-semibold"
                  >
                    {e(`col.${k}`)}
                  </th>
                ))}
              </tr>
            </thead>
            <tbody>
              {state.rows.length === 0 ? (
                <tr>
                  <td
                    colSpan={7}
                    className="px-3 py-10 text-center text-foreground-muted"
                  >
                    {tab === "PENDING" ? e("all_assigned") : e("no_employees")}
                  </td>
                </tr>
              ) : null}
              {state.rows.map((r) => (
                <tr
                  key={r.code}
                  onClick={() => choose({ [r.code]: r })}
                  className={cn(
                    "cursor-pointer border-t border-border hover:bg-surface-muted",
                    picked[r.code] && "bg-brand-subtle",
                  )}
                >
                  <td
                    className="px-3 py-2"
                    onClick={(ev) => ev.stopPropagation()}
                  >
                    <Checkbox
                      checked={Boolean(picked[r.code])}
                      onCheckedChange={() => toggle(r)}
                      aria-label={r.code}
                    />
                  </td>
                  <td className="px-3 py-2">
                    <p className="font-medium text-foreground">{r.name}</p>
                    <p className="font-mono text-xs text-foreground-muted">
                      {r.code}
                    </p>
                  </td>
                  <td className="px-3 py-2 font-mono text-xs">{r.card_no}</td>
                  <td className="px-3 py-2">{r.designation ?? "—"}</td>
                  <td className="px-3 py-2">{r.department ?? "—"}</td>
                  <td className="px-3 py-2">{r.line ?? "—"}</td>
                  <td className="px-3 py-2">
                    <span
                      className={cn(
                        "rounded-full px-2 py-0.5 text-xs font-medium whitespace-nowrap",
                        r.assigned
                          ? "bg-success-subtle text-success"
                          : "bg-warning-subtle text-warning",
                      )}
                    >
                      {e(`assignment.${r.assigned ? "ASSIGNED" : "PENDING"}`)}
                    </span>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
        <div className="flex items-center justify-between gap-3">
          <p className="text-[13px] text-foreground-muted">
            {e("page_of", { n: state.total, page: state.page, pages })}
          </p>
          <div className="flex gap-2">
            <Button
              size="sm"
              variant="outline"
              disabled={pending || state.page <= 1}
              onClick={() => load({ page: state.page - 1 })}
            >
              <ChevronLeft className="size-4" aria-hidden />
              {e("previous")}
            </Button>
            <Button
              size="sm"
              variant="outline"
              disabled={pending || state.page >= pages}
              onClick={() => load({ page: state.page + 1 })}
            >
              {e("next")}
              <ChevronRight className="size-4" aria-hidden />
            </Button>
          </div>
        </div>
      </section>

      <section className={cn(card, "flex min-w-0 flex-col gap-4 p-5")}>
        <h2 className="flex items-center gap-2 text-base font-semibold text-foreground">
          <Building2 className="size-4" aria-hidden />
          {e("assign_title")}
        </h2>
        {chosen.length === 0 ? (
          <p className="py-10 text-center text-sm text-foreground-muted">
            {e("assign_empty")}
          </p>
        ) : (
          <>
            <div className="flex flex-wrap items-center justify-between gap-2 rounded-lg bg-surface-muted px-4 py-3">
              <p className="text-sm font-medium text-foreground">
                {one
                  ? `${one.code} · ${one.name} · ${e("card_short", { card: one.card_no })}`
                  : e("several", { n: chosen.length })}
              </p>
              {one ? (
                <span className="rounded-full bg-success-subtle px-2 py-0.5 text-xs font-medium text-success">
                  {e(`status.${one.status}`)}
                </span>
              ) : null}
            </div>
            {one ? (
              <div className="rounded-lg border border-border px-4 py-3 text-[13px]">
                <p className="mb-1 font-medium text-foreground">
                  {e("current_posting")}
                </p>
                <p className="text-foreground-muted">
                  {one.assigned
                    ? [
                        one.factory,
                        one.building,
                        one.floor,
                        one.department,
                        one.section,
                        one.line,
                        one.designation,
                        one.grade,
                        one.shift,
                      ]
                        .filter(Boolean)
                        .join(" · ")
                    : e("no_posting")}
                </p>
              </div>
            ) : null}
            {general.length ? (
              <Alert variant="danger">
                {general.map((m) => (
                  <p key={m}>{m}</p>
                ))}
              </Alert>
            ) : null}
            <div className="grid gap-3 sm:grid-cols-2">
              <Field label={p("factory")} required error={err("factory_id")}>
                <Select
                  value={form.factory_id}
                  onValueChange={set("factory_id")}
                  options={list(options.factories)}
                  placeholder={t("emp.f.select")}
                  aria-label={p("factory")}
                />
              </Field>
              <Field label={p("building")} error={err("building_id")}>
                <Select
                  value={form.building_id}
                  onValueChange={set("building_id")}
                  options={list(buildings)}
                  placeholder={t("emp.f.select")}
                  aria-label={p("building")}
                />
              </Field>
              <Field
                label={p("floor")}
                hint={p("floor_hint")}
                error={err("floor_id")}
              >
                <Select
                  value={form.floor_id}
                  onValueChange={set("floor_id")}
                  options={list(floors)}
                  placeholder={
                    form.building_id ? t("emp.f.select") : p("building_first")
                  }
                  disabled={!form.building_id}
                  aria-label={p("floor")}
                />
              </Field>
              <Field
                label={p("department")}
                required
                error={err("department_id")}
              >
                <Select
                  value={form.department_id}
                  onValueChange={set("department_id")}
                  options={list(departments)}
                  placeholder={t("emp.f.select")}
                  aria-label={p("department")}
                />
              </Field>
              <Field label={p("section")} error={err("section_id")}>
                <Select
                  value={form.section_id}
                  onValueChange={set("section_id")}
                  options={list(sections)}
                  placeholder={
                    form.department_id
                      ? t("emp.f.select")
                      : p("department_first")
                  }
                  disabled={!form.department_id}
                  aria-label={p("section")}
                />
              </Field>
              <Field
                label={p("line")}
                hint={p("line_hint")}
                error={err("line_id")}
              >
                <Select
                  value={form.line_id}
                  onValueChange={set("line_id")}
                  options={list(lines)}
                  placeholder={
                    form.floor_id || form.department_id
                      ? t("emp.f.select")
                      : p("floor_first")
                  }
                  disabled={!form.floor_id && !form.department_id}
                  aria-label={p("line")}
                />
              </Field>
              <Field
                label={p("designation")}
                required
                error={err("designation_id")}
              >
                <Select
                  value={form.designation_id}
                  onValueChange={set("designation_id")}
                  options={list(designations)}
                  placeholder={
                    form.department_id
                      ? t("emp.f.select")
                      : p("department_first")
                  }
                  disabled={!form.department_id}
                  aria-label={p("designation")}
                />
              </Field>
              <Field label={p("grade")} error={err("grade_id")}>
                <Select
                  value={form.grade_id}
                  onValueChange={set("grade_id")}
                  options={list(options.grades)}
                  placeholder={t("emp.f.select")}
                  aria-label={p("grade")}
                />
              </Field>
              <Field label={p("shift")} error={err("shift_id")}>
                <Select
                  value={form.shift_id}
                  onValueChange={set("shift_id")}
                  options={list(options.shifts)}
                  placeholder={t("emp.f.select")}
                  aria-label={p("shift")}
                />
              </Field>
            </div>
            {transfer ? (
              <Field
                label={p("reason")}
                required
                hint={p("reason_hint")}
                error={err("reason")}
              >
                <Textarea
                  rows={2}
                  value={form.reason}
                  onChange={(ev) =>
                    setForm((all) => ({ ...all, reason: ev.target.value }))
                  }
                  aria-label={p("reason")}
                />
              </Field>
            ) : null}
            <p className="rounded-lg bg-info-subtle px-4 py-3 text-[13px] text-info">
              {e("rule_hint")}
            </p>
            <div className="flex justify-end gap-2">
              <Button
                variant="outline"
                onClick={() => choose({})}
                disabled={pending}
              >
                {e("cancel")}
              </Button>
              <Button onClick={save} loading={pending}>
                {transfer ? e("save_transfer") : e("save_assignment")}
              </Button>
            </div>
          </>
        )}
      </section>
    </div>
  );
}

export { EmployeeAssignment };
