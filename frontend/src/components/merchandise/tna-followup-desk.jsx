"use client";

import { useMemo, useState, useTransition } from "react";
import { printDocument } from "@/lib/print-document";
import { AlertTriangle, CalendarClock, CalendarDays, CheckCircle2, Clock, Download, Eye, FileText, GanttChartSquare, Grid3x3, History, ListChecks, Palette, Printer, RefreshCw, Ruler, Search, Shirt, Workflow } from "lucide-react";
import { cn } from "@/lib/utils";
import { Input } from "@/components/ui/input";
import { Select } from "@/components/ui/select";
import { Textarea } from "@/components/ui/textarea";
import { Button } from "@/components/ui/button";
import { Modal } from "@/components/ui/modal";
import { ConfirmDialog } from "@/components/ui/confirm-dialog";
import { useToastManager } from "@/components/ui/toast";
import { useT } from "@/lib/i18n";
import { clockFormat } from "@/lib/clock";

// An error's field messages, or its message when it names no field.
const messagesOf = (result) => {
  const fields = [...new Set(Object.values(result?.errors ?? {}).flat())];
  return fields.length ? fields : [result?.message ?? ""];
};
const MONTHS = ["Jan", "Feb", "Mar", "Apr", "May", "Jun", "Jul", "Aug", "Sep", "Oct", "Nov", "Dec"];
// Brickland's "08-Oct-2026" on this screen.
const dmy = (v) => {
  if (!v) return "—";
  const [y, mo, d] = v.slice(0, 10).split("-");
  return `${d}-${MONTHS[Number(mo) - 1]}-${y}`;
};
const ms = (v) => new Date(`${v.slice(0, 10)}T00:00:00Z`).getTime();
const todayIso = () => new Intl.DateTimeFormat("en-CA", { timeZone: "Asia/Dhaka" }).format(new Date());
const TONE = { COMPLETED: "bg-success-subtle text-success", OVERDUE: "bg-danger-subtle text-danger", DUE_TODAY: "bg-warning-subtle text-warning", PENDING: "bg-warning-subtle text-warning", PLANNED: "bg-surface-muted text-foreground", NOT_STARTED: "bg-surface-muted text-foreground-muted" };
const DOT = { COMPLETED: "bg-success", OVERDUE: "bg-danger", DUE_TODAY: "bg-warning", PENDING: "bg-warning", PLANNED: "bg-foreground-subtle", NOT_STARTED: "bg-foreground-subtle" };
const STATUSES = ["NOT_STARTED", "PLANNED", "PENDING", "DUE_TODAY", "IN_PROGRESS", "COMPLETED", "OVERDUE", "WAITING", "THIS_WEEK"];
const TABS = [
  ["schedule", ListChecks],
  ["gantt", GanttChartSquare],
  ["style", Shirt],
  ["colour", Palette],
  ["size", Ruler],
  ["matrix", Grid3x3],
  ["documents", FileText],
  ["history", History],
];

/**
 * Merchandise Desk → T&A Followup (AnnotechERP 06/12), one page as Brickland
 * lays it out: the confirmed order picked, its milestone counts and overall
 * progress, the order's facts (read only, with Print and Export), and the
 * Time & Action schedule with its views — the milestone list (search,
 * department, status and the overdue / due-today / due-this-week shortcuts;
 * Recalculate Plan Date; View to record a milestone), the Gantt view, the
 * order quantity by style, colour, size and colour × size, its related
 * documents, and remarks with the change history — then which milestones
 * the modules record. Switching order or view never reloads the page.
 *
 * @param {{ orders: object[], initialOrder: string, initial: ?object, canManage: boolean, actions: { load: Function, recalc: Function, save: Function } }} props
 */
function TnaFollowupDesk({ orders, initialOrder, initial, canManage, actions }) {
  const t = useT("merchandise");
  const m = (k, p) => t(`master.tfu.${k}`, p);
  const toastManager = useToastManager();
  const [orderNo, setOrderNo] = useState(initialOrder);
  const [data, setData] = useState(initial);
  const [tab, setTab] = useState("schedule");
  const [query, setQuery] = useState("");
  const [dept, setDept] = useState("");
  const [status, setStatus] = useState("");
  const [viewing, setViewing] = useState(null);
  const [form, setForm] = useState({ actual_date: "", remarks: "" });
  const [formError, setFormError] = useState(null);
  const [recalcOpen, setRecalcOpen] = useState(false);
  const [pending, startTransition] = useTransition();
  const [today] = useState(todayIso);
  const rows = useMemo(() => data?.rows ?? [], [data]);
  const departments = useMemo(() => [...new Set(rows.map((r) => r.department).filter(Boolean))].sort(), [rows]);
  const shown = useMemo(() => {
    const needle = query.trim().toLowerCase();
    return rows.filter((r) => {
      if (needle && !`${r.task} ${r.source_module ?? ""} ${r.remarks ?? ""}`.toLowerCase().includes(needle)) return false;
      if (dept && r.department !== dept) return false;
      if (!status) return true;
      if (status === "WAITING") return r.status !== "COMPLETED" && r.d2g !== null && r.d2g > 0;
      if (status === "THIS_WEEK") return r.status !== "COMPLETED" && r.d2g !== null && r.d2g >= 0 && r.d2g <= 6;
      if (status === "IN_PROGRESS") return r.completion > 0 && r.completion < 100;
      return r.status === status;
    });
  }, [rows, query, dept, status]);
  const say = (result, success) => {
    if (result?.status === "success") {
      if (success) toastManager.add({ title: success, type: "success" });
      return true;
    }
    toastManager.add({ title: messagesOf(result)[0], type: "warning" });
    return false;
  };

  function pickOrder(no) {
    setOrderNo(no);
    window.history.replaceState(null, "", no ? `?order=${encodeURIComponent(no)}` : "?");
    if (!no) return setData(null);
    startTransition(async () => {
      const result = await actions.load(no);
      if (say(result)) setData(result.data);
    });
  }

  function recalculate() {
    startTransition(async () => {
      const result = await actions.recalc(orderNo);
      if (say(result, m("recalculated", { n: result?.data?.rows?.length ?? 0 }))) setData(result.data);
      setRecalcOpen(false);
    });
  }

  function openView(r) {
    setViewing(r);
    setForm({ actual_date: r.actual_date ?? "", remarks: r.remarks ?? "" });
    setFormError(null);
  }

  function saveMilestone(completeToday = false) {
    startTransition(async () => {
      const payload = { actual_date: viewing.auto ? viewing.actual_date : form.actual_date || null, remarks: form.remarks || null, ...(completeToday ? { complete_today: true } : {}) };
      const result = await actions.save(orderNo, viewing.id, payload);
      if (result?.status === "success") {
        setData(result.data);
        setViewing(null);
        toastManager.add({ title: m("saved", { task: viewing.task }), type: "success" });
      } else setFormError(messagesOf(result).join(" "));
    });
  }

  function exportCsv() {
    const head = ["sl", "task", "department", "plan", "actual", "d2g", "required", "done", "completion", "status", "source", "doc"].map((k) => m(`col.${k}`));
    const body = rows.map((r, i) => [i + 1, r.task, r.department ?? "", dmy(r.plan_date), dmy(r.actual_date), r.d2g ?? "", r.required ? m("yes") : m("no"), r.done, `${r.completion}%`, m(`status.${r.status}`), r.source_module ?? m("merchandising"), r.related_doc ?? ""]);
    const csv = [head, ...body].map((l) => l.map((v) => `"${String(v).replaceAll('"', '""')}"`).join(",")).join("\n");
    const link = document.createElement("a");
    link.href = URL.createObjectURL(new Blob([csv], { type: "text/csv" }));
    link.download = `tna-${orderNo}.csv`;
    link.click();
  }

  const card = "rounded-xl border border-border bg-surface";
  const o = data?.order;
  const k = data?.kpis;
  const TONES = { COMPLETED: "ok", IN_PROGRESS: "info", PENDING: "muted", OVERDUE: "bad", DUE_TODAY: "warn" };
  // The order's T&A on paper: the order, where it stands, and every task with its plan and actual dates.
  const printSchedule = () =>
    printDocument({
      title: m("schedule"),
      docNo: o.order_no,
      subtitle: `${o.buyer ?? ""} · ${o.style ?? ""}`,
      orientation: "landscape",
      metaColumns: 6,
      meta: [
        [m("f.buyer_ac"), o.buyer_ac],
        [m("f.po"), o.po_no],
        [m("f.order_date"), o.order_date ? dmy(o.order_date) : null],
        [m("f.ship_date"), o.ship_date ? dmy(o.ship_date) : null],
        [m("f.qty"), o.qty ? `${new Intl.NumberFormat("en-US").format(o.qty)} ${m(`unit.${o.unit}`)}` : null],
        [m("f.template"), o.template],
        ...["total", "completed", "in_progress", "pending", "overdue", "due_today"].map((key) => [m(`kpi.${key}`), String(k?.[key] ?? 0)]),
        [m("kpi.progress"), `${k?.progress ?? 0}%`],
      ],
      sections: [
        {
          columns: [
            { label: m("col.sl"), align: "center", width: "4%" },
            { label: m("col.task") },
            { label: m("col.department") },
            { label: m("col.plan"), nowrap: true },
            { label: m("col.actual"), nowrap: true },
            { label: m("col.d2g"), align: "right" },
            { label: m("col.required"), align: "center" },
            { label: m("col.completion"), align: "right" },
            { label: m("col.status") },
            { label: m("col.source") },
          ],
          rows: rows.map((r, i) => [i + 1, r.task, r.department, dmy(r.plan_date), dmy(r.actual_date), r.d2g, r.required ? m("yes") : m("no"), `${r.completion}%`, { v: m(`status.${r.status}`), tone: TONES[r.status] ?? "muted" }, r.source_module ?? m("merchandising")]),
        },
      ],
      signatures: true,
    });
  return (
    <div className="flex flex-col gap-4">
      <section className={cn(card, "p-5")}>
        <div className="flex flex-col gap-1.5">
          <span className="text-[13px] font-semibold text-foreground">
            {m("order_no")}
            <span className="text-danger">*</span>
          </span>
          <Select value={orderNo} onValueChange={(v) => pickOrder(v ?? "")} options={[{ value: "", label: m("pick_order") }, ...orders]} placeholder={m("pick_order")} aria-label={m("order_no")} />
          <span className="text-xs text-foreground-muted">{m("order_hint")}</span>
        </div>
      </section>

      <div className="grid gap-3 sm:grid-cols-3 lg:grid-cols-7">
        {[
          ["total", ListChecks, "bg-brand-subtle text-brand"],
          ["completed", CheckCircle2, "bg-success-subtle text-success"],
          ["in_progress", Clock, "bg-info-subtle text-info"],
          ["pending", CalendarClock, "bg-warning-subtle text-warning"],
          ["overdue", AlertTriangle, "bg-danger-subtle text-danger"],
          ["due_today", CalendarDays, "bg-warning-subtle text-warning"],
        ].map(([key, Icon, tone]) => (
          <div key={key} className={cn(card, "flex items-center gap-3 p-4")}>
            <span className={cn("flex size-10 items-center justify-center rounded-lg", tone)}>
              <Icon className="size-5" />
            </span>
            <span>
              <span className="block text-xs text-foreground-muted">{m(`kpi.${key}`)}</span>
              <span className="block text-xl font-semibold tabular-nums text-foreground">{k?.[key] ?? 0}</span>
            </span>
          </div>
        ))}
        <div className={cn(card, "flex flex-col justify-center gap-2 p-4")}>
          <span className="text-xs text-foreground-muted">{m("kpi.progress")}</span>
          <span className="flex items-center gap-2">
            <span className="h-2 flex-1 overflow-hidden rounded-full bg-surface-muted">
              <span className="block h-full rounded-full bg-success" style={{ width: `${k?.progress ?? 0}%` }} />
            </span>
            <span className="text-sm font-semibold tabular-nums text-foreground">{k?.progress ?? 0}%</span>
          </span>
        </div>
      </div>

      {!data ? (
        <section className={cn(card, "flex flex-col items-center gap-2 px-5 py-14 text-center")}>
          <span className="flex size-12 items-center justify-center rounded-lg bg-surface-muted text-foreground-muted">
            <CalendarClock className="size-5" />
          </span>
          <p className="text-sm font-semibold text-foreground">{m("pick")}</p>
          <p className="text-xs text-foreground-muted">{m("order_hint")}</p>
        </section>
      ) : (
        <>
          <section className={cn(card, "p-5")}>
            <div className="flex flex-wrap items-start justify-between gap-3">
              <div>
                <h2 className="text-base font-semibold text-foreground">{m("info")}</h2>
                <p className="mt-0.5 text-[13px] text-foreground-muted">{m("info_hint")}</p>
              </div>
              <div className="flex items-center gap-2">
                <span className="rounded-md bg-success-subtle px-2 py-1 text-xs font-medium text-success">{m(`order_status.${o.status}`)}</span>
                <Button variant="outline" size="sm" onClick={printSchedule}>
                  <Printer /> {m("print")}
                </Button>
                <Button variant="outline" size="sm" onClick={exportCsv} disabled={!rows.length}>
                  <Download /> {m("export")}
                </Button>
              </div>
            </div>
            <div className="mt-4 flex flex-col gap-5 sm:flex-row">
              <span className="flex size-28 shrink-0 items-center justify-center rounded-lg border border-border bg-surface-muted text-foreground-muted">
                <Shirt className="size-9" />
              </span>
              <dl className="grid flex-1 gap-x-6 gap-y-4 sm:grid-cols-2 lg:grid-cols-4">
                {[
                  ["order_no", o.order_no],
                  ["buyer", o.buyer],
                  ["buyer_ac", o.buyer_ac],
                  ["style", o.style],
                  ["po", o.po_no],
                  ["order_date", o.order_date ? dmy(o.order_date) : null],
                  ["ship_date", o.ship_date ? dmy(o.ship_date) : null],
                  ["ship_month", o.ship_date ? new Intl.DateTimeFormat("en-GB", { month: "long", year: "numeric", timeZone: "UTC" }).format(new Date(`${o.ship_date}T00:00:00Z`)) : null],
                  ["qty", o.qty ? `${new Intl.NumberFormat("en-US").format(o.qty)} ${m(`unit.${o.unit}`)}` : null],
                  ["gmt_type", o.gmt_type],
                  ["template", o.template],
                  ["season", o.season],
                ].map(([key, v]) => (
                  <div key={key}>
                    <dt className="text-xs text-foreground-muted">{m(`f.${key}`)}</dt>
                    <dd className="mt-0.5 text-sm font-semibold text-foreground">{v || "—"}</dd>
                  </div>
                ))}
              </dl>
            </div>
          </section>

          <section className={cn(card, "overflow-hidden")}>
            <div className="px-5 pt-4">
              <h2 className="text-base font-semibold text-foreground">{m("schedule")}</h2>
              <p className="mt-0.5 text-[13px] text-foreground-muted">{m("schedule_count", { shown: shown.length, total: rows.length, date: dmy(today) })}</p>
            </div>
            <div role="tablist" className="mt-3 flex flex-wrap gap-1 border-b border-border px-5">
              {TABS.map(([key, Icon]) => (
                <button key={key} type="button" role="tab" aria-selected={tab === key} onClick={() => setTab(key)} className={cn("-mb-px flex items-center gap-1.5 border-b-2 px-3 py-2 text-[14px] font-medium", tab === key ? "border-brand text-brand" : "border-transparent text-foreground-muted hover:text-foreground")}>
                  <Icon className="size-4" />
                  {m(`tab.${key}`)}
                  {key === "documents" || key === "history" ? <span className="rounded-full bg-surface-muted px-1.5 text-xs text-foreground">{key === "documents" ? data.documents.length : data.history.length}</span> : null}
                </button>
              ))}
            </div>

            {tab === "schedule" ? (
              <>
                <div className="flex flex-wrap items-center gap-2 px-5 py-3">
                  <div className="relative w-64">
                    <Search className="pointer-events-none absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-foreground-muted" />
                    <Input value={query} onChange={(e) => setQuery(e.target.value)} placeholder={m("search")} className="pl-8" aria-label={m("search_label")} />
                  </div>
                  <div className="w-48">
                    <Select value={dept} onValueChange={(v) => setDept(v ?? "")} options={[{ value: "", label: m("all_departments") }, ...departments.map((d) => ({ value: d, label: d }))]} aria-label={m("department")} />
                  </div>
                  <div className="w-52">
                    <Select value={status} onValueChange={(v) => setStatus(v ?? "")} options={[{ value: "", label: m("all_statuses") }, ...STATUSES.map((s) => ({ value: s, label: m(`status.${s}`) }))]} aria-label={m("status_label")} />
                  </div>
                  {["OVERDUE", "DUE_TODAY", "THIS_WEEK"].map((s) => (
                    <Button key={s} variant={status === s ? "primary" : "outline"} size="sm" onClick={() => setStatus(status === s ? "" : s)}>
                      {m(`quick.${s}`)}
                    </Button>
                  ))}
                  {canManage ? (
                    <Button size="sm" className="ml-auto" onClick={() => setRecalcOpen(true)} disabled={pending}>
                      <RefreshCw /> {m("recalculate")}
                    </Button>
                  ) : null}
                </div>
                <div className="overflow-x-auto">
                  <table className="w-full text-sm">
                    <thead className="bg-brand text-brand-foreground">
                      <tr>
                        {["sl", "task", "department", "plan", "actual", "d2g", "required", "done", "completion", "status", "source", "doc", "action"].map((c) => (
                          <th key={c} className="px-3 py-2.5 text-left text-[12px] font-semibold tracking-wide whitespace-nowrap uppercase">
                            {m(`col.${c}`)}
                          </th>
                        ))}
                      </tr>
                    </thead>
                    <tbody>
                      {rows.length === 0 ? (
                        <tr>
                          <td colSpan={13} className="px-3 py-10 text-center text-foreground-muted">
                            {m("no_calendar")}
                          </td>
                        </tr>
                      ) : shown.length === 0 ? (
                        <tr>
                          <td colSpan={13} className="px-3 py-10 text-center text-foreground-muted">
                            {m("no_match")}
                          </td>
                        </tr>
                      ) : (
                        shown.map((r) => (
                          <tr key={r.id} className="border-t border-border">
                            <td className="px-3 py-2 tabular-nums">{rows.indexOf(r) + 1}</td>
                            <td className="px-3 py-2">
                              <span className="font-medium text-foreground">{r.task}</span>
                              {r.source_text ? <span className="block text-xs text-foreground-muted">{r.source_text}</span> : null}
                            </td>
                            <td className="px-3 py-2">{r.department ?? "—"}</td>
                            <td className="px-3 py-2 whitespace-nowrap">{dmy(r.plan_date)}</td>
                            <td className="px-3 py-2 whitespace-nowrap">{dmy(r.actual_date)}</td>
                            <td className={cn("px-3 py-2 tabular-nums", r.d2g !== null && r.d2g < 0 && r.status !== "COMPLETED" && "text-danger")}>{r.d2g ?? "—"}</td>
                            <td className="px-3 py-2">{r.required ? m("yes") : m("no")}</td>
                            <td className="px-3 py-2 tabular-nums">{r.done}</td>
                            <td className="px-3 py-2 tabular-nums">{r.completion}%</td>
                            <td className="px-3 py-2">
                              <span className={cn("rounded-md px-1.5 py-0.5 text-xs font-medium whitespace-nowrap", TONE[r.status])}>{m(`status.${r.status}`)}</span>
                            </td>
                            <td className="px-3 py-2">{r.source_module ?? m("merchandising")}</td>
                            <td className="px-3 py-2">{r.related_doc ?? "—"}</td>
                            <td className="px-3 py-2">
                              <Button variant="outline" size="icon" className="size-8" aria-label={m("view", { task: r.task })} onClick={() => openView(r)}>
                                <Eye />
                              </Button>
                            </td>
                          </tr>
                        ))
                      )}
                    </tbody>
                  </table>
                </div>
              </>
            ) : null}

            {tab === "gantt" ? <Gantt rows={rows} today={today} m={m} /> : null}
            {tab === "style" ? (
              <QtyTable caption={m("style_caption")} head={[m("col.sl"), m("q.style"), m("q.gmt_type"), m("q.qty")]} body={data.styles.map((s, i) => [i + 1, s.style ?? "—", s.gmt_type ?? "—", s.qty])} total={[m("q.total"), "", data.order.qty]} numeric={[3]} />
            ) : null}
            {tab === "colour" ? <ShareTable caption={m("colour_caption")} label={m("q.colour")} rows={data.colours.map((c) => [c.colour, c.qty])} m={m} /> : null}
            {tab === "size" ? <ShareTable caption={m("size_caption")} label={m("q.size")} rows={data.sizes.map((s) => [s.size, s.qty])} m={m} /> : null}
            {tab === "matrix" && !data.matrix.rows.length ? <p className="px-5 py-10 text-center text-sm text-foreground-muted">{m("no_breakdown")}</p> : null}
            {tab === "matrix" && data.matrix.rows.length ? (
              <QtyTable
                caption={m("matrix_caption")}
                head={[m("q.colour"), ...data.matrix.sizes, m("q.total")]}
                body={data.matrix.rows.map((r) => [r.colour, ...data.matrix.sizes.map((s) => r.cells[s] ?? 0), r.total])}
                total={[...data.matrix.sizes.map((s) => data.matrix.rows.reduce((n, r) => n + (r.cells[s] ?? 0), 0)), data.matrix.rows.reduce((n, r) => n + r.total, 0)]}
                numeric={data.matrix.sizes.map((_, i) => i + 1).concat(data.matrix.sizes.length + 1)}
                totalLabel={m("q.total")}
              />
            ) : null}
            {tab === "documents" ? (
              <QtyTable caption={m("documents_caption")} head={[m("doc.document"), m("doc.reference"), m("doc.date"), m("doc.status")]} body={data.documents.map((d) => [m(`doc.type.${d.document}`), d.reference, dmy(d.date), m(`doc.status_of.${d.status}`)])} />
            ) : null}
            {tab === "history" ? (
              <div className="grid gap-6 p-5 lg:grid-cols-2">
                <div>
                  <h3 className="text-sm font-semibold text-foreground">{m("remarks")}</h3>
                  {data.remarks.length ? (
                    <ul className="mt-3 flex flex-col gap-2">
                      {data.remarks.map((r) => (
                        <li key={r.task} className="rounded-lg border border-border p-3 text-sm">
                          <span className="font-semibold text-foreground">{r.task}</span>
                          <span className="mt-1 block whitespace-pre-wrap text-foreground-muted">{r.remarks}</span>
                        </li>
                      ))}
                    </ul>
                  ) : (
                    <p className="mt-3 text-sm text-foreground-muted">{m("no_remarks")}</p>
                  )}
                </div>
                <div>
                  <h3 className="text-sm font-semibold text-foreground">{m("history")}</h3>
                  {data.history.length ? (
                    <ul className="mt-3 flex flex-col gap-2">
                      {data.history.map((h, i) => (
                        <li key={i} className="rounded-lg border border-border p-3 text-sm">
                          <span className="block text-xs font-semibold text-foreground">
                            {h.at ? clockFormat({ day: "2-digit", month: "short", year: "numeric", hour: "2-digit", minute: "2-digit", timeZone: "Asia/Dhaka" }).format(new Date(h.at)) : ""} · {h.by ?? m("system")}
                          </span>
                          <span className="mt-1 block text-foreground">{h.message}</span>
                        </li>
                      ))}
                    </ul>
                  ) : (
                    <p className="mt-3 text-sm text-foreground-muted">{m("no_history")}</p>
                  )}
                </div>
              </div>
            ) : null}
          </section>

          {data.modules.length ? (
            <section className={cn(card, "p-5")}>
              <h2 className="flex items-center gap-2 text-base font-semibold text-foreground">
                <Workflow className="size-4" />
                {m("auto")}
              </h2>
              <p className="mt-0.5 text-[13px] text-foreground-muted">{m("auto_hint", { order: o.order_no })}</p>
              <div className="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                {data.modules.map((x) => (
                  <div key={x.task} className="flex gap-3 rounded-lg border border-border p-3">
                    <span className={cn("mt-1.5 size-2.5 shrink-0 rounded-full", x.done ? "bg-success" : DOT[x.status])} />
                    <span className="text-sm">
                      <span className="block font-semibold text-foreground">{x.task}</span>
                      <span className="block text-foreground-muted">{x.module}</span>
                      <span className="block text-foreground-muted">{x.done ? m("auto_done") : m("auto_waiting")}</span>
                    </span>
                  </div>
                ))}
              </div>
            </section>
          ) : null}
        </>
      )}

      <ConfirmDialog
        open={recalcOpen}
        onOpenChange={setRecalcOpen}
        title={m("recalc_title")}
        description={m("recalc_hint")}
        confirmLabel={m("recalc_confirm")}
        cancelLabel={m("cancel")}
        onConfirm={recalculate}
        loading={pending}
      />

      <Modal open={viewing !== null} onOpenChange={(v) => !v && setViewing(null)} title={viewing?.task ?? ""} description={viewing ? m("view_sub", { dept: viewing.department ?? "—", source: viewing.source_module ?? m("merchandising") }) : ""} className="max-w-2xl">
        {viewing ? (
          <div className="flex flex-col gap-4">
            <dl className="grid gap-3 sm:grid-cols-3">
              {[
                ["plan", dmy(viewing.plan_date)],
                ["actual", dmy(viewing.actual_date)],
                ["d2g", viewing.d2g ?? "—"],
                ["status", m(`status.${viewing.status}`)],
                ["completion", `${viewing.completion}%`],
              ].map(([key, v]) => (
                <div key={key}>
                  <dt className="text-xs text-foreground-muted">{m(`col.${key}`)}</dt>
                  <dd className="mt-0.5 text-sm font-semibold text-foreground">{v}</dd>
                </div>
              ))}
              <div className="sm:col-span-3">
                <dt className="text-xs text-foreground-muted">{m("source_tx")}</dt>
                <dd className="mt-0.5 text-sm text-foreground">{viewing.source_text ?? m("no_tx")}</dd>
              </div>
            </dl>
            {viewing.auto ? (
              <p className="rounded-lg bg-surface-muted p-3 text-sm text-foreground-muted">{m("module_records", { module: viewing.source_module })}</p>
            ) : (
              <label className="flex flex-col gap-1.5">
                <span className="text-[13px] font-semibold text-foreground">{m("col.actual")}</span>
                <Input type="date" max={today} value={form.actual_date} onChange={(e) => setForm((f) => ({ ...f, actual_date: e.target.value }))} disabled={!canManage} aria-label={m("col.actual")} />
                <span className="text-xs text-foreground-muted">{m("manual_hint")}</span>
              </label>
            )}
            <label className="flex flex-col gap-1.5">
              <span className="text-[13px] font-semibold text-foreground">{m("remarks")}</span>
              <Textarea rows={3} value={form.remarks} onChange={(e) => setForm((f) => ({ ...f, remarks: e.target.value }))} placeholder={m("remarks_placeholder")} disabled={!canManage} maxLength={2000} aria-label={m("remarks")} />
            </label>
            {formError ? <p className="text-sm text-danger">{formError}</p> : null}
            {canManage ? (
              <div className="flex justify-end gap-2">
                {!viewing.auto && !viewing.actual_date ? (
                  <Button variant="outline" onClick={() => saveMilestone(true)} disabled={pending}>
                    <CheckCircle2 /> {m("complete_today")}
                  </Button>
                ) : null}
                <Button onClick={() => saveMilestone(false)} loading={pending}>
                  {m("save")}
                </Button>
              </div>
            ) : null}
          </div>
        ) : null}
      </Modal>
    </div>
  );
}

/** The Gantt view: each milestone's plan date, its actual date, and the days it ran late, against today. */
function Gantt({ rows, today, m }) {
  const dated = rows.filter((r) => r.plan_date);
  if (!dated.length) return <p className="px-5 py-10 text-center text-sm text-foreground-muted">{m("no_calendar")}</p>;
  const points = dated.flatMap((r) => [ms(r.plan_date), ...(r.actual_date ? [ms(r.actual_date)] : [])]).concat(ms(today));
  const start = new Date(Math.min(...points));
  start.setUTCDate(1);
  const endDate = new Date(Math.max(...points));
  const end = Date.UTC(endDate.getUTCFullYear(), endDate.getUTCMonth() + 1, 1);
  const span = end - start.getTime();
  const at = (v) => `${((v - start.getTime()) / span) * 100}%`;
  const months = [];
  for (let d = new Date(start); d.getTime() < end; d.setUTCMonth(d.getUTCMonth() + 1)) months.push(d.getTime());
  return (
    <div className="overflow-x-auto px-5 py-4">
      <div className="mb-3 flex flex-wrap gap-4 text-xs text-foreground-muted">
        <span className="flex items-center gap-1.5">
          <span className="h-3 w-0.5 bg-foreground" /> {m("gantt.plan")}
        </span>
        <span className="flex items-center gap-1.5">
          <span className="size-2.5 rounded-full bg-success" /> {m("gantt.actual")}
        </span>
        <span className="flex items-center gap-1.5">
          <span className="h-0.5 w-4 bg-danger" /> {m("gantt.late")}
        </span>
        <span>{m("gantt.today", { date: dmy(today) })}</span>
      </div>
      <div className="min-w-[48rem]">
        <div className="grid grid-cols-[14rem_1fr] text-xs text-foreground-muted">
          <span />
          <div className="relative h-6 border-b border-border">
            {months.map((t0) => (
              <span key={t0} className="absolute top-0 border-l border-border pl-1" style={{ left: at(t0) }}>
                {new Intl.DateTimeFormat("en-GB", { month: "short", year: "numeric", timeZone: "UTC" }).format(new Date(t0))}
              </span>
            ))}
          </div>
        </div>
        {dated.map((r) => {
          const plan = ms(r.plan_date);
          const done = r.actual_date ? ms(r.actual_date) : null;
          const lateTo = done ?? (r.status === "OVERDUE" ? ms(today) : null);
          return (
            <div key={r.id} className="grid grid-cols-[14rem_1fr] items-center border-b border-border text-sm">
              <span className="truncate py-2 pr-3 text-foreground" title={r.task}>
                {r.task}
              </span>
              <div className="relative h-8">
                {months.map((t0) => (
                  <span key={t0} className="absolute top-0 h-full border-l border-border/60" style={{ left: at(t0) }} />
                ))}
                <span className="absolute top-0 h-full border-l border-dashed border-brand" style={{ left: at(ms(today)) }} />
                {lateTo && lateTo > plan ? <span className="absolute top-1/2 h-0.5 bg-danger" style={{ left: at(plan), width: `calc(${at(lateTo)} - ${at(plan)})` }} /> : null}
                <span className="absolute top-1/2 h-4 w-0.5 -translate-y-1/2 bg-foreground" style={{ left: at(plan) }} />
                {done ? <span className="absolute top-1/2 size-2.5 -translate-x-1/2 -translate-y-1/2 rounded-full bg-success" style={{ left: at(done) }} /> : null}
              </div>
            </div>
          );
        })}
      </div>
    </div>
  );
}

function QtyTable({ caption, head, body, total, numeric = [], totalLabel }) {
  const fmt = (v) => (typeof v === "number" ? new Intl.NumberFormat("en-US").format(v) : v);
  return (
    <div className="p-5">
      <p className="mb-3 text-sm font-semibold text-foreground">{caption}</p>
      <div className="overflow-x-auto rounded-lg border border-border">
        <table className="w-full text-sm">
          <thead className="bg-surface-muted">
            <tr>
              {head.map((h, i) => (
                <th key={`${h}-${i}`} className={cn("px-3 py-2 text-left text-[12px] font-semibold uppercase", numeric.includes(i) && "text-right")}>
                  {h}
                </th>
              ))}
            </tr>
          </thead>
          <tbody>
            {body.map((row, ri) => (
              <tr key={ri} className="border-t border-border">
                {row.map((v, i) => (
                  <td key={i} className={cn("px-3 py-2", numeric.includes(i) && "text-right tabular-nums")}>
                    {fmt(v)}
                  </td>
                ))}
              </tr>
            ))}
            {total ? (
              <tr className="border-t border-border font-semibold">
                {totalLabel ? <td className="px-3 py-2">{totalLabel}</td> : null}
                {total.map((v, i) => (
                  <td key={i} className={cn("px-3 py-2", typeof v === "number" && "text-right tabular-nums")}>
                    {fmt(v)}
                  </td>
                ))}
              </tr>
            ) : null}
          </tbody>
        </table>
      </div>
    </div>
  );
}

function ShareTable({ caption, label, rows, m }) {
  const total = rows.reduce((n, [, q]) => n + q, 0);
  if (!rows.length) return <p className="px-5 py-10 text-center text-sm text-foreground-muted">{m("no_breakdown")}</p>;
  return (
    <QtyTable
      caption={caption}
      head={[label, m("q.qty"), "%"]}
      body={rows.map(([name, q]) => [name, q, total ? `${((100 * q) / total).toFixed(1)}%` : "—"])}
      total={[m("q.total"), total, "100%"]}
      numeric={[1, 2]}
    />
  );
}

export { TnaFollowupDesk };
