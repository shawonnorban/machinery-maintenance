"use client";

import { useState, useTransition } from "react";
import Link from "next/link";
import { useRouter } from "next/navigation";
import { Download, RotateCcw, Search } from "lucide-react";
import { cn } from "@/lib/utils";
import { Button, buttonVariants } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Modal } from "@/components/ui/modal";
import { Textarea } from "@/components/ui/textarea";
import { useToastManager } from "@/components/ui/toast";
import { useT } from "@/lib/i18n";
import { RecordsGrid } from "@/components/erp/records-grid";
import { card, dmy, num, stamp, Pill } from "./plan-common";

/*
 * Capacity Planning → Master Plan List and Revision Request (AnnotechERP
 * 12/02), as Brickland lays them out: every version of every plan by status,
 * shipment window and search, each opening on Master Plan by its number and
 * version; the revision requests with the planning manager's Approve or
 * Reject; and the saved daily plans, each opening on its day. Filters apply in
 * place.
 */

const STATUSES = ["DRAFT", "AWAITING", "LOCKED", "REVISED"];
const planHref = (no, v) =>
  `/capacity-planning/master-plan?plan=${encodeURIComponent(no)}&v=${v}`;

function usePlanT() {
  const t = useT("planning");
  return (k, x) => t(`plan.${k}`, x);
}

/**
 * The plans table: on Master Plan with Brickland's status chips and search
 * (`chips`, opening in place through `onOpen`), on Master Plan List as links.
 */
function MasterPlanTable({ rows, title, hint, chips, onOpen, action }) {
  const p = usePlanT();
  const [status, setStatus] = useState("");
  const [q, setQ] = useState("");
  const shown = chips
    ? rows.filter(
        (r) =>
          (!status || r.status === status) &&
          (!q ||
            [r.plan_no, r.order_no, r.buyer, r.style, r.lines]
              .join(" ")
              .toLowerCase()
              .includes(q.toLowerCase())),
      )
    : rows;
  const cols = chips
    ? [
        "plan_no",
        "order_no",
        "buyer",
        "style",
        "qty",
        "ship_date",
        "lines",
        "sewing",
        "pcs_day",
        "status",
      ]
    : [
        "plan_no",
        "version",
        "order_no",
        "buyer",
        "style",
        "qty",
        "lines",
        "daily_capacity",
        "sewing_start",
        "sewing_end",
        "ship_date",
        "status",
        "plan",
      ];
  const right = ["qty", "pcs_day", "daily_capacity"];
  const cell = (r, k) => {
    switch (k) {
      case "plan_no":
        return chips ? (
          <button
            type="button"
            className="font-semibold text-brand hover:underline"
            onClick={() => onOpen(r.plan_no, r.version)}
          >
            {r.plan_no}V{r.version}
          </button>
        ) : (
          <span className="font-semibold">{r.plan_no}</span>
        );
      case "version":
        return `V${r.version}`;
      case "qty":
      case "pcs_day":
      case "daily_capacity":
        return num(k === "pcs_day" ? r.daily_capacity : r[k]);
      case "ship_date":
      case "sewing_start":
      case "sewing_end":
        return dmy(r[k]);
      case "sewing":
        return r.sewing_start
          ? `${dmy(r.sewing_start)} → ${dmy(r.sewing_end)}`
          : "—";
      case "status":
        return <Pill tone={r.status}>{p(`status.${r.status}`)}</Pill>;
      case "plan":
        return (
          <Link
            href={planHref(r.plan_no, r.version)}
            className={buttonVariants({ size: "sm", variant: "outline" })}
          >
            {p("mpl.open")}
          </Link>
        );
      default:
        return r[k] || "—";
    }
  };

  return (
    <section className={cn(card, "min-w-0 overflow-hidden")}>
      <div className="flex flex-wrap items-start justify-between gap-3 p-5">
        <div>
          <h2 className="text-base font-semibold text-foreground">{title}</h2>
          {hint ? (
            <p className="text-[13px] text-foreground-muted">{hint}</p>
          ) : null}
        </div>
        {chips ? (
          <div className="flex flex-wrap items-center gap-2">
            {["", ...STATUSES].map((s) => (
              <Button
                key={s || "all"}
                size="sm"
                variant={status === s ? "primary" : "outline"}
                onClick={() => setStatus(s)}
              >
                {s ? p(`status.${s}`) : p("mp.all")}
              </Button>
            ))}
            <div className="relative w-56">
              <Search
                className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-foreground-muted"
                aria-hidden
              />
              <Input
                value={q}
                onChange={(ev) => setQ(ev.target.value)}
                placeholder={p("mp.search")}
                className="h-8 pl-9"
                aria-label={p("mp.search")}
              />
            </div>
          </div>
        ) : (
          action
        )}
      </div>
      <div className="overflow-x-auto">
        <table className="w-full min-w-[1000px] text-sm">
          <thead className="bg-brand text-brand-foreground">
            <tr>
              {cols.map((k) => (
                <th
                  key={k}
                  className={cn(
                    "px-3 py-2.5 text-left text-xs font-semibold uppercase",
                    right.includes(k) && "text-right",
                  )}
                >
                  {p(`col.${k}`)}
                </th>
              ))}
            </tr>
          </thead>
          <tbody>
            {shown.length === 0 ? (
              <tr>
                <td
                  colSpan={cols.length}
                  className="px-3 py-10 text-center text-foreground-muted"
                >
                  {p("mpl.empty")}
                </td>
              </tr>
            ) : null}
            {shown.map((r) => (
              <tr
                key={`${r.plan_no}-${r.version}`}
                className="border-t border-border"
              >
                {cols.map((k) => (
                  <td
                    key={k}
                    className={cn(
                      "px-3 py-2.5",
                      right.includes(k) && "text-right tabular-nums",
                      [
                        "plan_no",
                        "ship_date",
                        "sewing",
                        "sewing_start",
                        "sewing_end",
                      ].includes(k) && "whitespace-nowrap",
                    )}
                  >
                    {cell(r, k)}
                  </td>
                ))}
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </section>
  );
}

const filled = (f) =>
  Object.fromEntries(Object.entries(f).filter(([, v]) => v));

/** Master Plan List: Status, Shipment from — to, search; Clear, Export. */
function MasterPlanList({ rows: initial, act }) {
  const p = usePlanT();
  const blank = { status: "", from: "", to: "", q: "" };
  const [f, setF] = useState(blank);
  const [rows, setRows] = useState(initial);
  const [pending, startTransition] = useTransition();
  const load = (next) =>
    startTransition(async () => {
      setF(next);
      const res = await act("list", filled(next));
      if (res?.status === "success") setRows(res.data);
    });
  const query = new URLSearchParams(filled(f)).toString();

  return (
    <div className={cn("flex flex-col gap-4", pending && "opacity-80")}>
      <section className={cn(card, "flex flex-wrap items-end gap-3 p-4")}>
        <label className="flex flex-col gap-1.5 text-[13px] font-semibold text-foreground">
          {p("mpl.status")}
          <select
            value={f.status}
            onChange={(ev) => load({ ...f, status: ev.target.value })}
            className="h-10 w-40 rounded-lg border border-border-strong bg-surface px-3 text-sm"
            aria-label={p("mpl.status")}
          >
            <option value="">{p("mp.all")}</option>
            {STATUSES.map((s) => (
              <option key={s} value={s}>
                {p(`status.${s}`)}
              </option>
            ))}
          </select>
        </label>
        <label className="flex flex-col gap-1.5 text-[13px] font-semibold text-foreground">
          {p("mpl.shipment")}
          <Input
            type="date"
            value={f.from}
            onChange={(ev) => load({ ...f, from: ev.target.value })}
            className="w-40"
            aria-label={p("mpl.shipment")}
          />
        </label>
        <label className="flex flex-col gap-1.5 text-[13px] font-semibold text-foreground">
          {p("mpl.to")}
          <Input
            type="date"
            value={f.to}
            onChange={(ev) => load({ ...f, to: ev.target.value })}
            className="w-40"
            aria-label={p("mpl.to")}
          />
        </label>
        <div className="relative min-w-60 flex-1">
          <Search
            className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-foreground-muted"
            aria-hidden
          />
          <Input
            value={f.q}
            onChange={(ev) => setF({ ...f, q: ev.target.value })}
            onKeyDown={(ev) => ev.key === "Enter" && load(f)}
            placeholder={p("mp.search")}
            className="pl-9"
            aria-label={p("mp.search")}
          />
        </div>
        <span className="text-[13px] text-foreground-muted">
          {p("mpl.shown", { shown: rows.length, total: rows.length })}
        </span>
        <Button variant="outline" onClick={() => load(blank)}>
          <RotateCcw className="size-4" aria-hidden /> {p("mpl.clear")}
        </Button>
        <a
          href={`/api/capacity-planning/excel/master-plans${query ? `?${query}` : ""}`}
          className={buttonVariants({ variant: "outline" })}
        >
          <Download className="size-4" aria-hidden /> {p("mpl.export")}
        </a>
      </section>
      <MasterPlanTable rows={rows} title={p("mpl.title")} />
    </div>
  );
}

/** Revision Request: the requests, the decision, and the plan each is about. */
function RevisionRequests({ rows: initial, perms, act }) {
  const p = usePlanT();
  const toastManager = useToastManager();
  const [status, setStatus] = useState("");
  const [rows, setRows] = useState(initial);
  const [deciding, setDeciding] = useState(null);
  const [pending, startTransition] = useTransition();
  const load = (s) =>
    startTransition(async () => {
      setStatus(s);
      const res = await act("revisions", filled({ status: s }));
      if (res?.status === "success") setRows(res.data);
    });
  const decide = () =>
    startTransition(async () => {
      const res = await act(
        deciding.kind === "approve" ? "approveRevision" : "rejectRevision",
        deciding.id,
        { note: deciding.note || null },
      );
      if (res?.status !== "success")
        return toastManager.add({
          title: Object.values(res?.errors ?? {})[0]?.[0] ?? res?.message,
          type: "danger",
        });
      toastManager.add({
        title: p(
          deciding.kind === "approve" ? "toast.approved" : "toast.rejected",
          { v: res.data.revised_version ?? "" },
        ),
        type: "success",
      });
      setDeciding(null);
      const again = await act("revisions", filled({ status }));
      if (again?.status === "success") setRows(again.data);
    });
  const approve = deciding?.kind === "approve";

  return (
    <div className={cn("flex flex-col gap-4", pending && "opacity-80")}>
      <section className={cn(card, "flex flex-wrap items-end gap-3 p-4")}>
        <label className="flex flex-col gap-1.5 text-[13px] font-semibold text-foreground">
          {p("mpl.status")}
          <select
            value={status}
            onChange={(ev) => load(ev.target.value)}
            className="h-10 w-44 rounded-lg border border-border-strong bg-surface px-3 text-sm"
            aria-label={p("mpl.status")}
          >
            <option value="">{p("mp.all")}</option>
            {["PENDING", "APPROVED", "REJECTED"].map((s) => (
              <option key={s} value={s}>
                {p(`rev.statuses.${s}`)}
              </option>
            ))}
          </select>
        </label>
        <span className="text-[13px] text-foreground-muted">
          {p("mpl.shown", { shown: rows.length, total: rows.length })}
        </span>
        <Button variant="outline" onClick={() => load("")}>
          <RotateCcw className="size-4" aria-hidden /> {p("mpl.clear")}
        </Button>
      </section>
      <section className={cn(card, "min-w-0 overflow-hidden")}>
        <div className="p-5">
          <h2 className="text-base font-semibold text-foreground">
            {p("rev.title")}
          </h2>
        </div>
        <div className="overflow-x-auto">
          <table className="w-full min-w-[1100px] text-sm">
            <thead className="bg-brand text-brand-foreground">
              <tr>
                {[
                  ["requested", "rev"],
                  ["plan_no", "col"],
                  ["order_no", "col"],
                  ["reason", "rev"],
                  ["requested_by", "rev"],
                  ["status", "col"],
                  ["decided", "rev"],
                  ["decision_note", "rev"],
                  ["revised_version", "rev"],
                  ["action", "col"],
                ].map(([k, g]) => (
                  <th
                    key={k}
                    className="px-3 py-2.5 text-left text-xs font-semibold uppercase"
                  >
                    {k === "reason" ? p("rev.reason") : p(`${g}.${k}`)}
                  </th>
                ))}
              </tr>
            </thead>
            <tbody>
              {rows.length === 0 ? (
                <tr>
                  <td
                    colSpan={10}
                    className="px-3 py-10 text-center text-foreground-muted"
                  >
                    {p("rev.empty")}
                  </td>
                </tr>
              ) : null}
              {rows.map((r) => (
                <tr key={r.id} className="border-t border-border align-top">
                  <td className="px-3 py-2.5 whitespace-nowrap">
                    {stamp(r.requested_at)}
                  </td>
                  <td className="px-3 py-2.5 font-semibold whitespace-nowrap">
                    {r.plan_no} V{r.version}
                  </td>
                  <td className="px-3 py-2.5 whitespace-nowrap">
                    {r.order_no}
                  </td>
                  <td className="px-3 py-2.5">{r.reason}</td>
                  <td className="px-3 py-2.5">{r.requested_by ?? "—"}</td>
                  <td className="px-3 py-2.5">
                    <Pill tone={r.status === "PENDING" ? "AWAITING" : r.status}>
                      {p(`rev.statuses.${r.status}`)}
                    </Pill>
                  </td>
                  <td className="px-3 py-2.5">
                    {r.decided_at
                      ? `${stamp(r.decided_at)} · ${r.decided_by ?? "—"}`
                      : "—"}
                  </td>
                  <td className="px-3 py-2.5">{r.decision_note ?? "—"}</td>
                  <td className="px-3 py-2.5">
                    {r.revised_version
                      ? `V${r.revised_version} · ${p(`status.${r.revised_status}`)}`
                      : "—"}
                  </td>
                  <td className="px-3 py-2.5">
                    <div className="flex flex-wrap gap-2">
                      {r.status === "PENDING" && perms.approve ? (
                        <>
                          <Button
                            size="sm"
                            onClick={() =>
                              setDeciding({
                                id: r.id,
                                kind: "approve",
                                note: "",
                              })
                            }
                          >
                            {p("rev.approve")}
                          </Button>
                          <Button
                            size="sm"
                            variant="outline"
                            onClick={() =>
                              setDeciding({
                                id: r.id,
                                kind: "reject",
                                note: "",
                              })
                            }
                          >
                            {p("rev.reject")}
                          </Button>
                        </>
                      ) : null}
                      <Link
                        href={planHref(
                          r.plan_no,
                          r.revised_version ?? r.version,
                        )}
                        className={buttonVariants({
                          size: "sm",
                          variant: "outline",
                        })}
                      >
                        {p("rev.open_plan")}
                      </Link>
                    </div>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </section>
      {deciding ? (
        <Modal
          open
          onOpenChange={(o) => !o && setDeciding(null)}
          title={p(approve ? "rev.approve_title" : "rev.reject_title")}
          description={p(approve ? "rev.approve_body" : "rev.reject_body")}
          footer={
            <>
              <Button variant="outline" onClick={() => setDeciding(null)}>
                {p("mp.cancel")}
              </Button>
              <Button
                variant={approve ? "primary" : "danger"}
                loading={pending}
                disabled={!approve && !deciding.note.trim()}
                onClick={decide}
              >
                {p(approve ? "rev.approve" : "rev.reject")}
              </Button>
            </>
          }
        >
          {approve ? null : (
            <label className="flex flex-col gap-1.5 text-[13px] font-semibold text-foreground">
              <span>
                {p("rev.note")}
                <span className="text-danger"> *</span>
              </span>
              <Textarea
                rows={3}
                value={deciding.note}
                onChange={(ev) =>
                  setDeciding((d) => ({ ...d, note: ev.target.value }))
                }
                aria-label={p("rev.note")}
              />
            </label>
          )}
        </Modal>
      ) : null}
    </div>
  );
}

/** Daily Plan List's grid: every saved line day, sortable and paged, Open going to the day by its date and order. */
function DailyPlanGrid({ rows }) {
  const p = usePlanT();
  const router = useRouter();
  const c = (k) => p(`daily.col.${k}`);
  const list = rows.map((r) => ({
    ...r,
    id: `${r.date}|${r.order_no}|${r.line}`,
    day: dmy(r.date).replaceAll("-", " "),
    efficiency_label: `${num(r.efficiency)}%`,
    smv_label: Number(r.smv).toFixed(2),
    output_label:
      r.typed_output === null ? p("daily.from_scans") : num(r.typed_output),
    status_label: p(`daily.row.${r.status}`),
    remarks_label: r.remarks || "—",
  }));
  const n = (key, label) => ({
    key,
    label: c(label),
    type: "number",
    align: "right",
    format: num,
  });

  return (
    <section className={cn(card, "min-w-0 overflow-hidden p-4")}>
      <RecordsGrid
        rows={list}
        exportName="daily-plans"
        columns={[
          { key: "day", label: c("date") },
          { key: "order_no", label: c("order_no") },
          { key: "master_plan", label: c("master_plan") },
          { key: "line", label: c("line") },
          n("manpower", "manpower"),
          n("machines", "machines"),
          n("hours", "hours"),
          { key: "efficiency_label", label: c("efficiency"), align: "right" },
          { key: "smv_label", label: c("smv"), align: "right" },
          n("daily_capacity", "daily_capacity"),
          n("master_target", "master_target"),
          n("daily_target", "daily_target"),
          { key: "output_label", label: c("typed_output"), align: "center" },
          { key: "status_label", label: c("status") },
          { key: "remarks_label", label: c("remarks") },
        ]}
        rowOpen={{
          label: p("daily.list.open"),
          onOpen: (r) =>
            router.push(
              `/capacity-planning/daily-plan?${new URLSearchParams({ date: r.date, order: r.order_no })}`,
            ),
        }}
        emptyText={p("daily.list.empty")}
      />
    </section>
  );
}

export { MasterPlanTable, MasterPlanList, RevisionRequests, DailyPlanGrid };
