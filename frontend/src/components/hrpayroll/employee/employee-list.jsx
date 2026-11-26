"use client";

import { useState, useTransition } from "react";
import { useRouter } from "next/navigation";
import {
  ArrowRightLeft,
  ChevronLeft,
  ChevronRight,
  Filter,
  History,
  MoreVertical,
  Pencil,
  Search,
  User,
  UserCog,
  Users,
  Wallet,
} from "lucide-react";
import { cn } from "@/lib/utils";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Select } from "@/components/ui/select";
import { Modal } from "@/components/ui/modal";
import { Dropdown } from "@/components/ui/dropdown";
import { useT } from "@/lib/i18n";

/*
 * HR & Payroll → Employee → Employee List (AnnotechERP 24/03), as Brickland
 * draws it: every employee, 25 a page, with every column the profile fills
 * (an empty one shows a dash until the Employee Profile is built); Filter
 * opens the search, factory, department, section, line, designation, shift,
 * status and assignment filters; each row's menu transfers, edits, shows
 * the salary, assigns or shows the posting history. Nothing reloads.
 */

const card = "rounded-xl border border-border bg-surface";
const dash = (v) => (v === null || v === undefined || v === "" ? "—" : v);
const taka = (n) =>
  n == null
    ? "—"
    : `৳ ${Number(n).toLocaleString("en-US", { maximumFractionDigits: 0 })}`;
const dmy = (iso) => (iso ? iso.split("-").reverse().join("-") : "—");
const blankFilters = {
  q: "",
  factory: "",
  department: "",
  section: "",
  line: "",
  designation: "",
  shift: "",
  status: "",
  assignment: "",
};

/** "00 year, 0 month, 8 day" from the joining date to today. */
function servicePeriod(iso, t) {
  if (!iso) return "—";
  const from = new Date(`${iso}T00:00:00`);
  const now = new Date();
  let y = now.getFullYear() - from.getFullYear();
  let m = now.getMonth() - from.getMonth();
  let d = now.getDate() - from.getDate();
  if (d < 0) {
    m -= 1;
    d += new Date(now.getFullYear(), now.getMonth(), 0).getDate();
  }
  if (m < 0) {
    y -= 1;
    m += 12;
  }
  return y < 0
    ? "—"
    : t("emp.service_period", { y: String(y).padStart(2, "0"), m, d });
}

function SalaryDialog({ row, onClose, history }) {
  const t = useT("hrpayroll");
  const e = (k, p) => t(`emp.${k}`, p);
  const heads = [
    "basic",
    "house_rent",
    "conveyance",
    "food",
    "medical",
    "other",
  ];
  return (
    <Modal
      open
      onOpenChange={(v) => !v && onClose()}
      title={e("salary_title", { name: row.name })}
      description={row.structure_label ?? ""}
      className="max-w-2xl"
      footer={<Button onClick={onClose}>{e("close")}</Button>}
    >
      <div className="flex flex-col gap-4">
        <div className="grid gap-3 sm:grid-cols-2">
          {[
            ["split_a", row.split_a, "total_a"],
            ["split_b", row.split_b, "total_b"],
          ].map(([k, parts, total]) => (
            <div
              key={k}
              className="overflow-hidden rounded-lg border border-border"
            >
              <p className="bg-brand-subtle px-3 py-2 text-[13px] font-medium text-brand">
                {e(k)}
              </p>
              <dl className="divide-y divide-border text-[13px]">
                {heads.map((h) => (
                  <div key={h} className="flex justify-between px-3 py-1.5">
                    <dt className="text-foreground-muted">{e(`head.${h}`)}</dt>
                    <dd className="tabular-nums">
                      {parts ? parts[h].toLocaleString("en-US") : "—"}
                    </dd>
                  </div>
                ))}
                <div className="flex justify-between px-3 py-1.5 font-semibold">
                  <dt>{e(total)}</dt>
                  <dd className="tabular-nums">
                    {parts ? parts.total.toLocaleString("en-US") : "—"}
                  </dd>
                </div>
              </dl>
            </div>
          ))}
        </div>
        {history ? (
          <table className="w-full text-sm">
            <thead className="bg-brand text-brand-foreground">
              <tr>
                {["from", "structure_col"].map((k) => (
                  <th
                    key={k}
                    className="px-3 py-2 text-left text-xs font-semibold uppercase"
                  >
                    {e(k)}
                  </th>
                ))}
                {["gross_a", "gross_b"].map((k) => (
                  <th
                    key={k}
                    className="px-3 py-2 text-right text-xs font-semibold uppercase"
                  >
                    {e(`col.${k}`)}
                  </th>
                ))}
                <th className="px-3 py-2 text-right text-xs font-semibold uppercase">
                  {t("emp.f.actual")}
                </th>
              </tr>
            </thead>
            <tbody>
              {history.salaries.map((s, i) => (
                <tr key={`${s.from}-${i}`} className="border-t border-border">
                  <td className="px-3 py-2 whitespace-nowrap">{dmy(s.from)}</td>
                  <td className="px-3 py-2">{dash(s.structure)}</td>
                  <td className="px-3 py-2 text-right tabular-nums">
                    {taka(s.gross_a)}
                  </td>
                  <td className="px-3 py-2 text-right tabular-nums">
                    {taka(s.gross_b)}
                  </td>
                  <td className="px-3 py-2 text-right tabular-nums">
                    {taka(s.actual)}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        ) : null}
      </div>
    </Modal>
  );
}

function HistoryDialog({ row, history, onClose }) {
  const t = useT("hrpayroll");
  const e = (k, p) => t(`emp.${k}`, p);
  const cols = [
    "factory",
    "department",
    "section",
    "line",
    "designation",
    "grade",
    "shift",
  ];
  return (
    <Modal
      open
      onOpenChange={(v) => !v && onClose()}
      title={e("history_title", { name: row.name })}
      description={`${row.code} · ${e("card_short", { card: row.card_no })}`}
      className="max-w-4xl"
      footer={<Button onClick={onClose}>{e("close")}</Button>}
    >
      {history?.assignments.length ? (
        <div className="overflow-x-auto">
          <table className="w-full min-w-[760px] text-sm">
            <thead className="bg-brand text-brand-foreground">
              <tr>
                {["from", "to"].map((k) => (
                  <th
                    key={k}
                    className="px-3 py-2 text-left text-xs font-semibold uppercase"
                  >
                    {e(k)}
                  </th>
                ))}
                {cols.map((k) => (
                  <th
                    key={k}
                    className="px-3 py-2 text-left text-xs font-semibold uppercase"
                  >
                    {e(`col.${k}`)}
                  </th>
                ))}
                <th className="px-3 py-2 text-left text-xs font-semibold uppercase">
                  {e("reason")}
                </th>
              </tr>
            </thead>
            <tbody>
              {history.assignments.map((a, i) => (
                <tr
                  key={`${a.from}-${i}`}
                  className="border-t border-border align-top"
                >
                  <td className="px-3 py-2 whitespace-nowrap">{dmy(a.from)}</td>
                  <td className="px-3 py-2 whitespace-nowrap">
                    {a.to ? (
                      dmy(a.to)
                    ) : (
                      <span className="rounded-full bg-success-subtle px-2 py-0.5 text-xs font-medium text-success">
                        {e("current")}
                      </span>
                    )}
                  </td>
                  {cols.map((k) => (
                    <td key={k} className="px-3 py-2">
                      {dash(a[k])}
                    </td>
                  ))}
                  <td className="px-3 py-2">{dash(a.reason)}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      ) : (
        <p className="py-6 text-center text-sm text-foreground-muted">
          {e("no_history")}
        </p>
      )}
    </Modal>
  );
}

/**
 * Employee List.
 *
 * @param {{ data: object, options: object, canManage: boolean, act: (op: string, ...args: any[]) => Promise<object> }} props
 */
function EmployeeList({ data, options, canManage, act }) {
  const t = useT("hrpayroll");
  const e = (k, p) => t(`emp.${k}`, p);
  const router = useRouter();
  const [state, setState] = useState(data);
  const [filters, setFilters] = useState(blankFilters);
  const [open, setOpen] = useState(false);
  const [dialog, setDialog] = useState(null);
  const [pending, startTransition] = useTransition();
  const setF = (k) => (v) => setFilters((all) => ({ ...all, [k]: v ?? "" }));
  const pages = Math.max(1, Math.ceil(state.total / state.per_page));
  const from = state.total ? (state.page - 1) * state.per_page + 1 : 0;
  const to = Math.min(state.total, state.page * state.per_page);

  function load(page, f = filters) {
    startTransition(async () => {
      const result = await act("list", {
        ...Object.fromEntries(Object.entries(f).filter(([, v]) => v)),
        page,
      });
      if (result?.status === "success") setState(result.data);
    });
  }

  function openDialog(kind, row) {
    startTransition(async () => {
      const result = await act("history", row.code);
      setDialog({
        kind,
        row,
        history: result?.status === "success" ? result.data : null,
      });
    });
  }

  const opt = (list, all = true) => [
    ...(all ? [{ value: "", label: e("all") }] : []),
    ...list.map((o) => ({ value: o.value, label: o.label })),
  ];
  const cols = [
    "sl",
    "employee",
    "card",
    "father",
    "mother",
    "email",
    "department",
    "section",
    "sub_section",
    "line",
    "designation",
    "grade",
    "duty_type",
    "reg_shift",
    "shift",
    "starting",
    "gross_b",
    "gross_a",
    "join",
    "service",
    "phone",
    "rocket",
    "nid",
    "present",
    "permanent",
    "dob",
    "gender",
    "blood",
    "marital",
    "country",
    "religion",
    "assignment",
    "status",
    "action",
  ];
  const cell = (r, k, i) =>
    ({
      sl: (state.page - 1) * state.per_page + i + 1,
      card: <span className="font-mono">{r.card_no}</span>,
      father: dash(r.father_name),
      mother: dash(r.mother_name),
      email: dash(r.email),
      department: dash(r.department),
      section: dash(r.section),
      sub_section: "—",
      line: dash(r.line),
      designation: dash(r.designation),
      grade: dash(r.grade),
      duty_type: "—",
      reg_shift: "—",
      shift: dash(r.shift),
      starting: taka(r.starting_salary),
      gross_b: taka(r.gross_b),
      gross_a: taka(r.gross_a),
      join: dmy(r.joining_date),
      service: servicePeriod(r.joining_date, t),
      phone: dash(r.mobile),
      rocket: dash(r.rocket_no),
      nid: dash(r.nid),
      present: dash(r.present_address),
      permanent: dash(r.permanent_address),
      dob: dmy(r.date_of_birth),
      gender: dash(r.gender),
      blood: dash(r.blood_group),
      marital: dash(r.marital_status),
      country: dash(r.country),
      religion: dash(r.religion),
      assignment: (
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
      ),
      status: (
        <span
          className={cn(
            "rounded-full px-2 py-0.5 text-xs font-medium",
            r.status === "ACTIVE"
              ? "bg-success-subtle text-success"
              : "bg-surface-muted text-foreground-muted",
          )}
        >
          {e(`status.${r.status}`)}
        </span>
      ),
    })[k];

  const menu = (r) => [
    ...(canManage
      ? [
          {
            label: e("act.transfer"),
            icon: <ArrowRightLeft />,
            onSelect: () =>
              router.push(
                `/hr-payroll/employee-assignment?employee=${encodeURIComponent(r.code)}`,
              ),
          },
          {
            label: e("act.edit"),
            icon: <Pencil />,
            onSelect: () =>
              router.push(
                `/hr-payroll/employee-quick-registration?edit=${encodeURIComponent(r.code)}`,
              ),
          },
        ]
      : []),
    {
      label: e("act.salary"),
      icon: <Wallet />,
      onSelect: () => openDialog("salary", r),
    },
    ...(canManage
      ? [
          {
            label: e("act.assignment"),
            icon: <UserCog />,
            onSelect: () =>
              router.push(
                `/hr-payroll/employee-assignment?employee=${encodeURIComponent(r.code)}`,
              ),
          },
        ]
      : []),
    {
      label: e("act.history"),
      icon: <History />,
      onSelect: () => openDialog("history", r),
    },
  ];

  return (
    <section className={cn(card, "min-w-0 overflow-hidden")}>
      <div className="flex flex-wrap items-start justify-between gap-3 p-5">
        <div>
          <h2 className="flex items-center gap-2 text-base font-semibold text-foreground">
            <Users className="size-4" aria-hidden />
            {e("employees")}
          </h2>
          <p className="mt-0.5 text-[13px] text-foreground-muted">
            {e("range", { from, to, total: state.total })}
          </p>
        </div>
        <Button
          variant="outline"
          size="sm"
          onClick={() => setOpen((v) => !v)}
          aria-expanded={open}
        >
          <Filter className="size-4" aria-hidden />
          {e("filter")}
        </Button>
      </div>
      {open ? (
        <form
          className="grid gap-3 border-t border-border p-5 sm:grid-cols-2 lg:grid-cols-5"
          onSubmit={(ev) => {
            ev.preventDefault();
            load(1);
          }}
        >
          <label className="flex flex-col gap-1.5 lg:col-span-2">
            <span className="text-[13px] font-medium">{e("search")}</span>
            <div className="relative">
              <Search
                className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-foreground-muted"
                aria-hidden
              />
              <Input
                value={filters.q}
                onChange={(ev) => setF("q")(ev.target.value)}
                placeholder={e("search_ph")}
                aria-label={e("search")}
                className="pl-9"
              />
            </div>
          </label>
          {[
            ["factory", opt(options.factories)],
            ["department", opt(options.departments)],
            [
              "section",
              opt(
                options.sections.filter(
                  (s) =>
                    !filters.department ||
                    s.department_id === filters.department,
                ),
              ),
            ],
            ["line", opt(options.lines)],
            [
              "designation",
              opt(
                options.designations.filter(
                  (d) =>
                    !filters.department ||
                    d.department_id === filters.department,
                ),
              ),
            ],
            ["shift", opt(options.shifts)],
            [
              "status",
              [
                { value: "", label: e("all") },
                ...options.statuses.map((s) => ({
                  value: s,
                  label: e(`status.${s}`),
                })),
              ],
            ],
            [
              "assignment",
              [
                { value: "", label: e("all") },
                ...["ASSIGNED", "PENDING"].map((s) => ({
                  value: s,
                  label: e(`assignment.${s}`),
                })),
              ],
            ],
          ].map(([k, list]) => (
            <label key={k} className="flex flex-col gap-1.5">
              <span className="text-[13px] font-medium">
                {k === "status"
                  ? t("emp.f.status")
                  : k === "assignment"
                    ? e("assignment_status")
                    : e(`col.${k}`)}
              </span>
              <Select
                value={filters[k]}
                onValueChange={setF(k)}
                options={list}
                aria-label={e(`col.${k}`)}
              />
            </label>
          ))}
          <div className="flex items-end gap-2">
            <Button type="submit" loading={pending}>
              <Search className="size-4" aria-hidden />
              {e("search")}
            </Button>
            <Button
              type="button"
              variant="outline"
              onClick={() => {
                setFilters(blankFilters);
                load(1, blankFilters);
              }}
            >
              {e("reset")}
            </Button>
          </div>
        </form>
      ) : null}
      <div className="overflow-x-auto">
        <table className="w-full min-w-[3600px] text-sm">
          <thead className="bg-brand text-brand-foreground">
            <tr>
              {cols.map((k) => (
                <th
                  key={k}
                  className={cn(
                    "px-3 py-2.5 text-left text-xs font-semibold whitespace-nowrap",
                    ["starting", "gross_a", "gross_b"].includes(k) &&
                      "text-right",
                  )}
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
                  colSpan={cols.length}
                  className="px-3 py-10 text-left text-foreground-muted sm:text-center"
                >
                  {e("no_employees")}
                </td>
              </tr>
            ) : null}
            {state.rows.map((r, i) => (
              <tr key={r.code} className="border-t border-border align-middle">
                {cols.map((k) =>
                  k === "employee" ? (
                    <td key={k} className="px-3 py-2">
                      <div className="flex items-center gap-2">
                        <span className="flex size-9 shrink-0 items-center justify-center rounded-md bg-surface-muted text-foreground-muted">
                          <User className="size-4" aria-hidden />
                        </span>
                        <div className="min-w-0">
                          <p className="font-medium whitespace-nowrap text-brand">
                            {r.name}
                          </p>
                          <p className="font-mono text-xs text-foreground-muted">
                            {r.code}
                          </p>
                        </div>
                      </div>
                    </td>
                  ) : k === "action" ? (
                    <td key={k} className="px-3 py-2">
                      <Dropdown
                        trigger={
                          <span
                            aria-label={e("act.for", { code: r.code })}
                            className="inline-flex size-8 items-center justify-center rounded-sm text-foreground-muted hover:bg-surface-muted hover:text-foreground"
                          >
                            <MoreVertical className="size-4" />
                          </span>
                        }
                        items={menu(r)}
                      />
                    </td>
                  ) : (
                    <td
                      key={k}
                      className={cn(
                        "px-3 py-2 text-foreground",
                        ["starting", "gross_a", "gross_b"].includes(k)
                          ? "text-right whitespace-nowrap tabular-nums"
                          : ["present", "permanent"].includes(k)
                            ? "min-w-48"
                            : "whitespace-nowrap",
                      )}
                    >
                      {cell(r, k, i)}
                    </td>
                  ),
                )}
              </tr>
            ))}
          </tbody>
        </table>
      </div>
      <div className="flex items-center justify-between gap-3 border-t border-border px-5 py-3">
        <p className="text-[13px] text-foreground-muted">
          {e("page_of", { n: state.total, page: state.page, pages })}
        </p>
        <div className="flex gap-2">
          <Button
            size="sm"
            variant="outline"
            disabled={pending || state.page <= 1}
            onClick={() => load(state.page - 1)}
          >
            <ChevronLeft className="size-4" aria-hidden />
            {e("previous")}
          </Button>
          <Button
            size="sm"
            variant="outline"
            disabled={pending || state.page >= pages}
            onClick={() => load(state.page + 1)}
          >
            {e("next")}
            <ChevronRight className="size-4" aria-hidden />
          </Button>
        </div>
      </div>
      {dialog?.kind === "salary" ? (
        <SalaryDialog
          row={dialog.row}
          history={dialog.history}
          onClose={() => setDialog(null)}
        />
      ) : null}
      {dialog?.kind === "history" ? (
        <HistoryDialog
          row={dialog.row}
          history={dialog.history}
          onClose={() => setDialog(null)}
        />
      ) : null}
    </section>
  );
}

export { EmployeeList };
