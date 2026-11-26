"use client";

import { useState, useTransition } from "react";
import Link from "next/link";
import {
  ArrowLeft,
  CheckCheck,
  Pause,
  Plus,
  Printer,
  RotateCcw,
  Save,
  Search,
  Trash2,
  XCircle,
} from "lucide-react";
import { cn } from "@/lib/utils";
import { printDocument } from "@/lib/print-document";
import { Input } from "@/components/ui/input";
import { Textarea } from "@/components/ui/textarea";
import { Button, buttonVariants } from "@/components/ui/button";
import { Alert } from "@/components/ui/alert";
import { useToastManager } from "@/components/ui/toast";
import {
  PSelect,
  str,
  num,
  qtyFmt,
  dayFmt,
  todayIso,
  messagesOf,
  card,
  th,
  Field,
  StatusChip,
  NoLines,
  orNull,
} from "./yarn-common";
import { usePr } from "./prod-order";

/*
 * Knit Production → Production (AnnotechERP 15/04) → Grey Roll Quality
 * Inspection: the Inspection List (Pass, Hold, Reject — decisions about the
 * roll — and Print) and the Roll Inspection sheet: pick the roll, count the
 * faults, and the sheet works out Total Point, Grade Point (per 100 kg) and
 * the grade. Nothing reloads.
 */

const BASE = "/knit-production/grey-fab-roll-inspection";
const STONE = {
  DRAFT: "DRAFT",
  PASSED: "RECEIVED",
  HOLD: "WAITING_APPROVAL",
  REJECTED: "REJECTED",
};
const gradeOf = (gp) =>
  gp <= 20 ? "A" : gp <= 40 ? "B" : gp <= 60 ? "C" : "D";

/** The inspection sheet, as Brickland's Grey Fabric Roll Inspection prints it. */
function printSheet(p, sheet) {
  const r = sheet.roll;
  const net = r.net - sheet.deduct_weight;
  printDocument({
    title: p("qc.print_title"),
    docNo: `${sheet.qci_no} · ${r.value}`,
    meta: [
      [p("qc.date"), dayFmt(sheet.inspected_on)],
      [p("qc.shift"), sheet.register.shift],
      [p("qc.qc_name"), sheet.register.qc_by],
      [p("qc.job_card"), r.kc_no],
      [p("qc.roll_no_short"), r.roll_no],
      [p("qc.buyer"), r.buyer],
      [p("qc.style"), r.style],
      [p("qc.order"), r.order_no],
      [p("qc.fab_type"), r.fabric],
      [p("qc.colour"), r.colour],
      [p("qc.finish_dia"), r.finish_dia],
      [p("qc.mc"), r.machine],
      [p("qc.dia_gauge"), r.dia_gauge],
      [p("qc.gsm"), r.gsm ? Number(r.gsm).toFixed(2) : null],
      [p("qc.yarn"), r.yarn_details],
      [p("qc.operator"), r.operator],
      [p("qc.roll_wt"), `${qtyFmt.format(r.weight)} Kg`],
      [p("qc.deduct"), `${qtyFmt.format(sheet.deduct_weight)} Kg`],
      [p("qc.net_wt"), `${qtyFmt.format(net)} Kg`],
      [p("qc.total_point"), sheet.total_points],
      [p("qc.grade_point_print"), Number(sheet.grade_point).toFixed(2)],
      [
        p("qc.grade"),
        {
          v: sheet.grade,
          tone:
            sheet.grade === "A" ? "ok" : sheet.grade === "D" ? "bad" : "warn",
        },
      ],
      [p("qc.decision"), { v: p(`qc_status.${sheet.status}`), tone: "info" }],
    ],
    sections: [
      {
        title: p("qc.defects_found"),
        columns: [
          { label: p("qc.lc.defect") },
          { label: p("qc.lc.group") },
          { label: p("qc.lc.count"), align: "right" },
          { label: p("qc.lc.points_each"), align: "right" },
          { label: p("qc.lc.points"), align: "right" },
          { label: p("qc.lc.remarks") },
        ],
        rows: sheet.lines.map((l) => [
          p(`defect.${l.defect}`),
          l.group ? p(`defect_group.${l.group}`) : null,
          l.count,
          l.points_each,
          { v: l.count * l.points_each, bold: true },
          l.remarks,
        ]),
        empty: p("qc.clean"),
      },
    ],
    notes: sheet.comments ? [sheet.comments] : [],
    signatures: [p("qc.sign_inspector"), p("qc.sign_incharge")],
  });
}

function InspectionList({ options, initialRows, canManage, act }) {
  const p = usePr();
  const toastManager = useToastManager();
  const blank = { from: "", to: "", status: "", grade: "", machine: "" };
  const [f, setFilters] = useState(blank);
  const [rows, setRows] = useState(initialRows);
  const [error, setError] = useState(null);
  const [pending, startTransition] = useTransition();
  const setF = (k, v) => setFilters((x) => ({ ...x, [k]: v ?? "" }));

  function search(next = f) {
    startTransition(async () => {
      const q = new URLSearchParams(Object.entries(next).filter(([, v]) => v));
      const res = await act(`inspections?${q}`);
      if (res?.status !== "success") return setError(messagesOf(res));
      setRows(res.data);
      setError(null);
    });
  }
  function decide(r, action) {
    startTransition(async () => {
      const res = await act(
        `inspections/${encodeURIComponent(r.qci_no)}/${action}`,
        "POST",
      );
      if (res?.status !== "success") return setError(messagesOf(res));
      setRows((all) => all.map((x) => (x.qci_no === r.qci_no ? res.data : x)));
      toastManager.add({
        title: p(`qc.done.${action}`, { no: r.qci_no, roll: r.roll_code }),
        type: "success",
      });
    });
  }
  function print(r) {
    startTransition(async () => {
      const res = await act(`inspections/${encodeURIComponent(r.qci_no)}`);
      if (res?.status === "success") printSheet(p, res.data);
      else setError(messagesOf(res));
    });
  }
  const pick = (list) => [{ value: "", label: "-- All --" }, ...list];
  const cols = [
    "qci",
    "roll",
    "date",
    "shift",
    "qc_name",
    "mc",
    "roll_wt",
    "deduct",
    "net",
    "defects",
    "total_point",
    "grade_point",
    "grade",
    "status",
  ];
  const NUMS = [
    "roll_wt",
    "deduct",
    "net",
    "defects",
    "total_point",
    "grade_point",
  ];

  return (
    <div className="flex flex-col gap-4">
      <section className={cn(card, "p-5")}>
        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
          <Field label={p("from")}>
            <Input
              type="date"
              value={f.from}
              onChange={(e) => setF("from", e.target.value)}
              aria-label={p("from")}
            />
          </Field>
          <Field label={p("to")}>
            <Input
              type="date"
              value={f.to}
              onChange={(e) => setF("to", e.target.value)}
              aria-label={p("to")}
            />
          </Field>
          <Field label={p("qc.status")}>
            <PSelect
              value={f.status}
              onValueChange={(v) => setF("status", v)}
              options={pick(
                options.statuses.map((s) => ({
                  value: s,
                  label: p(`qc_status.${s}`),
                })),
              )}
              aria-label={p("qc.status")}
            />
          </Field>
          <Field label={p("qc.grade")}>
            <PSelect
              value={f.grade}
              onValueChange={(v) => setF("grade", v)}
              options={pick(
                options.grades.map((g) => ({ value: g, label: g })),
              )}
              aria-label={p("qc.grade")}
            />
          </Field>
          <Field label={p("qc.mc")}>
            <PSelect
              value={f.machine}
              onValueChange={(v) => setF("machine", v)}
              options={[
                { value: "", label: "-- Any machine --" },
                ...options.machines,
              ]}
              aria-label={p("qc.mc")}
            />
          </Field>
        </div>
        <div className="mt-4 flex justify-between gap-2 border-t border-border pt-4">
          <div className="flex gap-2">
            <Button onClick={() => search()} loading={pending}>
              <Search /> {p("search")}
            </Button>
            {canManage ? (
              <Link
                href={`${BASE}?tab=entry`}
                className={buttonVariants({ variant: "outline" })}
              >
                <Plus /> {p("add_new")}
              </Link>
            ) : null}
          </div>
          <Button
            variant="outline"
            onClick={() => (setFilters(blank), search(blank))}
            disabled={pending}
          >
            <RotateCcw /> {p("reset")}
          </Button>
        </div>
      </section>
      {error ? <Alert variant="danger">{error.join(" · ")}</Alert> : null}
      <section className={card}>
        {rows.length === 0 ? (
          <NoLines title={p("qc.empty")} hint={p("qc.empty_hint")} />
        ) : (
          <div className="overflow-x-auto">
            <table className="w-full min-w-[1700px] text-sm">
              <thead className="bg-brand text-brand-foreground">
                <tr>
                  {cols.map((k) => (
                    <th
                      key={k}
                      className={cn(th, NUMS.includes(k) && "text-right")}
                    >
                      {p(`qc.lc.${k}`)}
                    </th>
                  ))}
                  <th className={th}>{p("action_col")}</th>
                </tr>
              </thead>
              <tbody>
                {rows.map((r) => (
                  <tr key={r.qci_no} className="border-t border-border">
                    <td className="px-2 py-2 font-medium whitespace-nowrap">
                      <Link
                        href={`${BASE}?tab=entry&ref=${encodeURIComponent(r.qci_no)}`}
                        className="text-brand hover:underline"
                      >
                        {r.qci_no}
                      </Link>
                    </td>
                    <td className="px-2 py-2 whitespace-nowrap">
                      {r.roll_code}
                    </td>
                    <td className="px-2 py-2 whitespace-nowrap">
                      {dayFmt(r.inspected_on)}
                    </td>
                    <td className="px-2 py-2">{r.shift ?? "—"}</td>
                    <td className="px-2 py-2">{r.qc_by ?? "—"}</td>
                    <td className="px-2 py-2">{r.machine ?? "—"}</td>
                    <td className="px-2 py-2 text-right tabular-nums">
                      {qtyFmt.format(r.weight)}
                    </td>
                    <td className="px-2 py-2 text-right tabular-nums">
                      {qtyFmt.format(r.deduct)}
                    </td>
                    <td className="px-2 py-2 text-right tabular-nums">
                      {qtyFmt.format(r.net)}
                    </td>
                    <td className="px-2 py-2 text-right tabular-nums">
                      {r.defects}
                    </td>
                    <td className="px-2 py-2 text-right tabular-nums">
                      {r.total_points}
                    </td>
                    <td className="px-2 py-2 text-right tabular-nums">
                      {Number(r.grade_point).toFixed(2)}
                    </td>
                    <td className="px-2 py-2 font-semibold">{r.grade}</td>
                    <td className="px-2 py-2">
                      <StatusChip
                        status={STONE[r.status]}
                        label={p(`qc_status.${r.status}`)}
                      />
                    </td>
                    <td className="px-2 py-1.5">
                      <div className="flex gap-1">
                        {canManage && ["DRAFT", "HOLD"].includes(r.status) ? (
                          <>
                            <Button
                              variant="outline"
                              size="sm"
                              onClick={() => decide(r, "pass")}
                              disabled={pending}
                            >
                              <CheckCheck /> {p("qc.btn.pass")}
                            </Button>
                            {r.status === "DRAFT" ? (
                              <Button
                                variant="outline"
                                size="sm"
                                onClick={() => decide(r, "hold")}
                                disabled={pending}
                              >
                                <Pause /> {p("qc.btn.hold")}
                              </Button>
                            ) : null}
                            <Button
                              variant="outline"
                              size="sm"
                              className="text-danger"
                              onClick={() => decide(r, "reject")}
                              disabled={pending}
                            >
                              <XCircle /> {p("qc.btn.reject")}
                            </Button>
                          </>
                        ) : null}
                        <Button
                          variant="outline"
                          size="sm"
                          onClick={() => print(r)}
                          disabled={pending}
                        >
                          <Printer /> {p("qc.btn.print")}
                        </Button>
                      </div>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </section>
    </div>
  );
}

let seq = 0;
const defectLine = (l = {}) => ({
  key: `d${++seq}`,
  defect: str(l.defect),
  count: str(l.count),
  remarks: str(l.remarks),
});

function InspectionEntry({ options, initial, initialRoll, canManage, act }) {
  const p = usePr();
  const toastManager = useToastManager();
  const [today] = useState(todayIso);
  const asHead = (d) => ({
    roll_code: str(d?.roll_code),
    inspected_on: str(d?.inspected_on) || today,
    shift_id: str(d?.shift_id),
    qc_by: str(d?.qc_by),
    deduct_weight: d?.deduct_weight ? String(d.deduct_weight) : "",
    grade_override: str(d?.grade_override),
    comments: str(d?.comments),
  });
  const [doc, setDoc] = useState(initial);
  const [head, setHead] = useState(() =>
    asHead(initial ?? (initialRoll ? { roll_code: initialRoll } : null)),
  );
  const [lines, setLines] = useState(() =>
    (initial?.lines ?? []).map(defectLine),
  );
  const [draft, setDraft] = useState({ defect: "", count: "", remarks: "" });
  const [machine, setMachine] = useState("");
  const [q, setQ] = useState("");
  const [errors, setErrors] = useState(null);
  const [pending, startTransition] = useTransition();
  const editable = canManage && (!doc || doc.editable);
  const setH = (patch) => setHead((h) => ({ ...h, ...patch }));
  const roll =
    options.rolls.find((r) => r.value === head.roll_code) ?? doc?.roll;
  const pointsOf = (code) =>
    options.defects.find((d) => d.value === code)?.points ?? 0;
  const total = lines.reduce(
    (t, l) => t + pointsOf(l.defect) * num(l.count),
    0,
  );
  const net = roll ? roll.net - num(head.deduct_weight) : 0;
  const gradePoint = net > 0 ? Math.round((total * 10000) / net) / 100 : 0;
  const grade = head.grade_override || gradeOf(gradePoint);
  const side = options.rolls.filter(
    (r) =>
      (!machine || r.machine_id === machine) &&
      (!q ||
        [r.value, r.buyer, r.colour, r.fabric]
          .join(" ")
          .toLowerCase()
          .includes(q.toLowerCase())),
  );
  const defectOpts = options.defects.map((d) => ({
    value: d.value,
    label: `${p(`defect.${d.value}`)} (${d.points} pt)`,
  }));

  function addDefect() {
    if (!draft.defect) return setErrors([p("qc.v_defect")]);
    if (!num(draft.count)) return setErrors([p("qc.v_count")]);
    setLines((all) => [...all, defectLine(draft)]);
    setDraft({ defect: "", count: "", remarks: "" });
    setErrors(null);
  }
  function take(data) {
    setDoc(data);
    setHead(asHead(data));
    setLines(data.lines.map(defectLine));
    setErrors(null);
    window.history.replaceState(
      null,
      "",
      `?tab=entry&ref=${encodeURIComponent(data.qci_no)}`,
    );
  }
  function clear() {
    setDoc(null);
    setHead(asHead(null));
    setLines([]);
    setErrors(null);
    window.history.replaceState(null, "", "?tab=entry");
  }
  function load(no) {
    if (!no) return clear();
    startTransition(async () => {
      const res = await act(`inspections/${encodeURIComponent(no)}`);
      if (res?.status === "success") take(res.data);
      else setErrors(messagesOf(res));
    });
  }
  function save() {
    startTransition(async () => {
      const body = {
        ...Object.fromEntries(
          Object.entries(head).map(([k, v]) => [k, v === "" ? null : v]),
        ),
        deduct_weight: orNull(head.deduct_weight),
        lines: lines.map((l) => ({
          defect: l.defect,
          count: Number(l.count),
          remarks: l.remarks || null,
        })),
      };
      const res = await act(
        doc ? `inspections/${encodeURIComponent(doc.qci_no)}` : "inspections",
        doc ? "PUT" : "POST",
        body,
      );
      if (res?.status !== "success") return setErrors(messagesOf(res));
      take(res.data);
      toastManager.add({
        title: p("qc.saved", {
          no: res.data.qci_no,
          points: res.data.total_points,
          gp: Number(res.data.grade_point).toFixed(2),
          grade: res.data.grade,
        }),
        type: "success",
      });
    });
  }
  const ro = (k, v) => (
    <Field key={k} label={p(`qc.${k}`)}>
      <Input
        value={v ?? ""}
        readOnly
        className="bg-surface-muted"
        aria-label={p(`qc.${k}`)}
      />
    </Field>
  );

  return (
    <div className="grid gap-4 xl:grid-cols-[22rem_1fr]">
      <section className={cn(card, "self-start")}>
        <div className="flex flex-col gap-3 p-4">
          <Field label={p("qc.mc")}>
            <PSelect
              value={machine}
              onValueChange={(v) => setMachine(v ?? "")}
              options={[
                { value: "", label: "-- Any machine --" },
                ...options.machines,
              ]}
              aria-label={p("qc.mc")}
            />
          </Field>
          <div className="relative">
            <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-foreground-muted" />
            <Input
              value={q}
              onChange={(e) => setQ(e.target.value)}
              placeholder={p("qc.search_ph")}
              className="pl-9"
              aria-label={p("qc.search")}
            />
          </div>
        </div>
        <div className="max-h-[36rem] overflow-auto border-t border-border">
          <table className="w-full text-sm">
            <thead className="bg-brand text-brand-foreground">
              <tr>
                {["roll", "buyer", "colour", "shift", "wt"].map((k) => (
                  <th key={k} className={cn(th, k === "wt" && "text-right")}>
                    {p(`qc.side.${k}`)}
                  </th>
                ))}
              </tr>
            </thead>
            <tbody>
              {side.length === 0 ? (
                <tr>
                  <td
                    colSpan={5}
                    className="px-3 py-6 text-center text-foreground-muted"
                  >
                    {p("qc.no_rolls")}
                  </td>
                </tr>
              ) : (
                side.map((r) => (
                  <tr
                    key={r.value}
                    className={cn(
                      "border-t border-border",
                      head.roll_code === r.value && "bg-brand-subtle",
                    )}
                  >
                    <td className="px-2 py-2">
                      <button
                        type="button"
                        className="font-medium text-brand hover:underline"
                        onClick={() => editable && setH({ roll_code: r.value })}
                      >
                        {r.value}
                      </button>
                    </td>
                    <td className="px-2 py-2">{r.buyer ?? "—"}</td>
                    <td className="px-2 py-2">{r.colour ?? "—"}</td>
                    <td className="px-2 py-2">{r.shift ?? "—"}</td>
                    <td className="px-2 py-2 text-right tabular-nums">
                      {qtyFmt.format(r.weight)}
                    </td>
                  </tr>
                ))
              )}
            </tbody>
          </table>
        </div>
      </section>

      <div className="flex min-w-0 flex-col gap-4">
        <section className={cn(card, "p-5")}>
          <div className="flex items-start justify-between gap-3">
            <div>
              <h2 className="text-base font-semibold text-foreground">
                {p("qc.entry_title")}
              </h2>
              <p className="mt-0.5 text-[13px] text-foreground-muted">
                {p("qc.entry_hint")}
              </p>
            </div>
            {doc ? (
              <StatusChip
                status={STONE[doc.status]}
                label={`${doc.qci_no} · ${p(`qc_status.${doc.status}`)}`}
              />
            ) : null}
          </div>
          <Field
            label={p("qc.existing")}
            hint={p("roll.existing_hint")}
            className="mt-4"
          >
            <PSelect
              value={doc?.qci_no ?? ""}
              onValueChange={(v) => load(v ?? "")}
              options={[
                { value: "", label: "-- New inspection sheet --" },
                ...options.sheets,
              ]}
              aria-label={p("qc.existing")}
            />
          </Field>
          <div className="mt-4 grid gap-4 md:grid-cols-3">
            <Field label={p("qc.roll_no")} required hint={p("qc.roll_hint")}>
              <PSelect
                value={head.roll_code}
                onValueChange={(v) => setH({ roll_code: v ?? "" })}
                options={[
                  { value: "", label: "-- Select Roll --" },
                  ...options.rolls.map((r) => ({
                    value: r.value,
                    label: r.label,
                  })),
                ]}
                disabled={!editable}
                aria-label={p("qc.roll_no")}
              />
            </Field>
            {ro("wt", roll ? qtyFmt.format(roll.weight) : "")}
            {ro("job_card", roll?.kc_no)}
            {ro("operator", roll?.operator)}
            {ro("buyer", roll?.buyer)}
            {ro("style", roll?.style)}
            {ro("order", roll?.order_no)}
            {ro("fab_type", roll?.fabric)}
            {ro("colour", roll?.colour)}
            {ro("finish_dia", roll?.finish_dia)}
            {ro("mc", roll?.machine)}
            {ro("dia_gauge", roll?.dia_gauge)}
            {ro("gsm", roll?.gsm ? Number(roll.gsm).toFixed(2) : "")}
            {ro("yarn", roll?.yarn_details)}
          </div>
          <div className="mt-4 grid gap-4 border-t border-border pt-4 md:grid-cols-3">
            <Field label={p("qc.date")} required>
              <Input
                type="date"
                value={head.inspected_on}
                onChange={(e) => setH({ inspected_on: e.target.value })}
                disabled={!editable}
                aria-label={p("qc.date")}
              />
            </Field>
            <Field label={p("qc.shift")}>
              <PSelect
                value={head.shift_id}
                onValueChange={(v) => setH({ shift_id: v ?? "" })}
                options={[
                  { value: "", label: "-- Shift --" },
                  ...options.shifts,
                ]}
                disabled={!editable}
                aria-label={p("qc.shift")}
              />
            </Field>
            <Field label={p("qc.qc_name")}>
              <PSelect
                value={head.qc_by}
                onValueChange={(v) => setH({ qc_by: v ?? "" })}
                options={[
                  { value: "", label: "-- Select --" },
                  ...options.employees,
                ]}
                disabled={!editable}
                aria-label={p("qc.qc_name")}
              />
            </Field>
            <Field label={p("qc.deduct")} hint={p("qc.deduct_hint")}>
              <Input
                type="number"
                min="0"
                step="any"
                value={head.deduct_weight}
                onChange={(e) => setH({ deduct_weight: e.target.value })}
                disabled={!editable}
                aria-label={p("qc.deduct")}
              />
            </Field>
            <Field label={p("qc.grade_is")} hint={p("qc.grade_hint")}>
              <PSelect
                value={head.grade_override}
                onValueChange={(v) => setH({ grade_override: v ?? "" })}
                options={[
                  { value: "", label: "-- Suggested on save --" },
                  ...options.grades.map((g) => ({ value: g, label: g })),
                ]}
                disabled={!editable}
                aria-label={p("qc.grade_is")}
              />
            </Field>
            <div className="flex flex-wrap items-end gap-2">
              {[
                [p("qc.total_is"), total],
                [p("qc.gp_is"), gradePoint.toFixed(2)],
                [p("qc.grade"), grade],
              ].map(([k, v]) => (
                <span
                  key={k}
                  className="inline-flex items-center gap-2 rounded-lg border border-border px-3 py-1.5 text-[13px]"
                >
                  {k}
                  <span className="rounded-full bg-brand-subtle px-2 text-xs font-semibold text-brand tabular-nums">
                    {v}
                  </span>
                </span>
              ))}
            </div>
            <Field label={p("qc.comments")} className="md:col-span-3">
              <Textarea
                rows={2}
                value={head.comments}
                onChange={(e) => setH({ comments: e.target.value })}
                maxLength={2000}
                disabled={!editable}
                aria-label={p("qc.comments")}
              />
            </Field>
          </div>
        </section>

        <section className={cn(card, "overflow-hidden")}>
          <div className="px-5 pt-4">
            <h2 className="text-base font-semibold text-foreground">
              {p("qc.defects")}
            </h2>
            <p className="mt-0.5 text-[13px] text-foreground-muted">
              {p("qc.lines_hint")}
            </p>
          </div>
          {editable ? (
            <div className="grid gap-3 px-5 py-4 md:grid-cols-[1fr_8rem_1fr_auto]">
              <Field label={p("qc.lc.defect")}>
                <PSelect
                  value={draft.defect}
                  onValueChange={(v) =>
                    setDraft((d) => ({ ...d, defect: v ?? "" }))
                  }
                  options={[
                    { value: "", label: "-- Select Defect --" },
                    ...defectOpts,
                  ]}
                  aria-label={p("qc.lc.defect")}
                />
              </Field>
              <Field label={p("qc.lc.count")}>
                <Input
                  type="number"
                  min="1"
                  step="1"
                  value={draft.count}
                  onChange={(e) =>
                    setDraft((d) => ({ ...d, count: e.target.value }))
                  }
                  aria-label={p("qc.lc.count")}
                />
              </Field>
              <Field label={p("qc.lc.remarks")}>
                <Input
                  value={draft.remarks}
                  onChange={(e) =>
                    setDraft((d) => ({ ...d, remarks: e.target.value }))
                  }
                  maxLength={300}
                  aria-label={p("qc.lc.remarks")}
                />
              </Field>
              <div className="flex items-end gap-2">
                <Button variant="outline" onClick={addDefect}>
                  <Plus /> {p("qc.add_defect")}
                </Button>
                <Button
                  variant="outline"
                  onClick={() =>
                    setDraft({ defect: "", count: "", remarks: "" })
                  }
                >
                  {p("cancel")}
                </Button>
              </div>
            </div>
          ) : null}
          {lines.length === 0 ? (
            <NoLines title={p("no_lines")} hint={p("qc.clean_hint")} />
          ) : (
            <div className="overflow-x-auto border-t border-border">
              <table className="w-full min-w-[800px] text-sm">
                <thead className="bg-brand text-brand-foreground">
                  <tr>
                    {[
                      "defect",
                      "count",
                      "points_each",
                      "points",
                      "remarks",
                    ].map((k) => (
                      <th
                        key={k}
                        className={cn(
                          th,
                          ["count", "points_each", "points"].includes(k) &&
                            "text-right",
                        )}
                      >
                        {p(`qc.lc.${k}`)}
                      </th>
                    ))}
                    <th className={th}>{p("qc.lc.remove")}</th>
                  </tr>
                </thead>
                <tbody>
                  {lines.map((l) => (
                    <tr key={l.key} className="border-t border-border">
                      <td className="px-2 py-2">{p(`defect.${l.defect}`)}</td>
                      <td className="px-2 py-2 text-right tabular-nums">
                        {l.count}
                      </td>
                      <td className="px-2 py-2 text-right tabular-nums">
                        {pointsOf(l.defect)}
                      </td>
                      <td className="px-2 py-2 text-right font-semibold tabular-nums">
                        {pointsOf(l.defect) * num(l.count)}
                      </td>
                      <td className="px-2 py-2">{l.remarks || "—"}</td>
                      <td className="px-1 py-1.5">
                        <Button
                          variant="outline"
                          size="icon"
                          className="size-8 text-danger"
                          onClick={() =>
                            setLines((all) =>
                              all.filter((x) => x.key !== l.key),
                            )
                          }
                          disabled={!editable}
                          aria-label={p("qc.lc.remove")}
                        >
                          <Trash2 />
                        </Button>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}
        </section>

        {errors ? <Alert variant="danger">{errors.join(" · ")}</Alert> : null}
        <section className={cn(card, "flex flex-col gap-3 p-5")}>
          <p className="text-[13px] text-foreground-muted">{p("qc.foot")}</p>
          <div className="flex flex-wrap gap-2">
            <Link
              href={BASE}
              className={buttonVariants({ variant: "outline" })}
            >
              <ArrowLeft /> {p("back_list")}
            </Link>
            <Button variant="outline" onClick={clear} disabled={pending}>
              <RotateCcw /> {p("clear")}
            </Button>
            {editable ? (
              <Button onClick={save} loading={pending}>
                <Save /> {p("qc.save")}
              </Button>
            ) : null}
          </div>
        </section>
      </div>
    </div>
  );
}

export { InspectionList, InspectionEntry };
