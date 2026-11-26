"use client";

import {
  Fragment,
  useEffect,
  useMemo,
  useRef,
  useState,
  useTransition,
} from "react";
import { printDocument } from "@/lib/print-document";
import {
  ChevronDown,
  ChevronLeft,
  ChevronRight,
  Download,
  Printer,
  Search,
} from "lucide-react";
import { cn } from "@/lib/utils";
import { Input } from "@/components/ui/input";
import { Select } from "@/components/ui/select";
import { Checkbox } from "@/components/ui/checkbox";
import { Textarea } from "@/components/ui/textarea";
import { FileInput } from "@/components/ui/file-input";
import { Alert } from "@/components/ui/alert";
import { useToastManager } from "@/components/ui/toast";
import { RecordsGrid } from "@/components/erp/records-grid";
import { useRouter } from "next/navigation";
import { useT } from "@/lib/i18n";
import {
  DocFoot,
  DocHead,
  Field,
  ReadOnly,
  Section,
  card,
  messagesOf,
  str,
} from "./proto-kit";
import { SeverityBadge } from "./desk-dashboard";
import { clockFormat } from "@/lib/clock";

/*
 * Prototype Desk → Sample 360° View (AnnotechERP 07/02), as Brickland draws it:
 * the Sample Register — month, type, stage, status, priority and company,
 * All / Open / Overdue / Completed, a search that also finds a sample by its
 * bundle, requisition, material requisition or handover number, My Samples
 * and Material Pending, and a buyer strip — whose rows open in place into
 * the sample end to end; and Sample Documents, the files kept on a sample
 * in versions. The address bar follows the open sample
 * (`?tab=view&sample=SMP-2026-00001`) without a reload.
 */

const PAGE = 10;
const dateFmt = new Intl.DateTimeFormat("en-GB", {
  day: "2-digit",
  month: "short",
  year: "numeric",
  timeZone: "Asia/Dhaka",
});
const dateTimeFmt = clockFormat({
  day: "2-digit",
  month: "short",
  year: "numeric",
  hour: "2-digit",
  minute: "2-digit",
  timeZone: "Asia/Dhaka",
});
const monthFmt = new Intl.DateTimeFormat("en-GB", {
  month: "short",
  year: "numeric",
  timeZone: "UTC",
});
const day = (iso) => (iso ? dateFmt.format(new Date(iso)) : "—");
const at = (iso) => (iso ? dateTimeFmt.format(new Date(iso)) : "—");
const dash = (v) => (v === null || v === undefined || v === "" ? "—" : v);
const num = (v) =>
  v === null || v === undefined
    ? "—"
    : Number(v).toLocaleString("en-US", { maximumFractionDigits: 2 });
// A sample's lifecycle status is a state, so its tint always comes with its word.
const STATUS_TONE = {
  DRAFT: "bg-surface-muted text-foreground-muted",
  PENDING: "bg-warning-subtle text-warning",
  APPROVED: "bg-success-subtle text-success",
  IN_PROGRESS: "bg-info-subtle text-info",
  ON_HOLD: "bg-warning-subtle text-warning",
  COMPLETED: "bg-success-subtle text-success",
  REJECTED: "bg-danger-subtle text-danger",
  CANCELLED: "bg-surface-muted text-foreground-muted",
  DELAYED: "bg-danger-subtle text-danger",
};
// How many columns each section of the 360° view has (its headings live in lang).
const COLS = {
  timeline: 6,
  decisions: 5,
  checklist: 4,
  developments: 7,
  revisions: 6,
  material: 9,
  issues: 8,
  bundles: 8,
  day_lines: 8,
  tracking: 6,
  qc: 6,
  approvals: 7,
  feedback: 6,
  handovers: 7,
  documents: 6,
  alerts: 5,
  audit: 6,
};
const FLAG_TONE = {
  OVERDUE: "bg-danger-subtle text-danger",
  MATERIAL: "bg-warning-subtle text-warning",
  URGENT: "bg-warning-subtle text-warning",
  CRITICAL: "bg-danger-subtle text-danger",
};

function StatusChip({ status }) {
  const t = useT("prototype");
  return (
    <span
      className={cn(
        "inline-flex rounded-md px-1.5 py-0.5 text-xs font-medium",
        STATUS_TONE[status],
      )}
    >
      {t(`life_status.${status}`)}
    </span>
  );
}

/** A sample's stage and status as Brickland writes a change: "Material · Pending". */
function useStateText() {
  const t = useT("prototype");
  return (v) => {
    if (!v) return "—";
    if (v === "ON_TIME") return t("ui.s360.on_time");
    if (v === "OVERDUE") return t("flag.OVERDUE");
    const [stage, status] = v.split("|");
    return `${t(`life_stage.${stage}`)} · ${t(`life_status.${status}`)}`;
  };
}

/** One section of the sample's 360° view: its table, or what it has not had yet. */
function DetailTable({ k, cols, rows, cells }) {
  const t = useT("prototype");
  const s = (x) => t(`ui.s360.sec.${k}.${x}`);
  return (
    <section className="flex min-w-0 flex-col gap-2">
      <h3 className="text-sm font-semibold text-foreground">{s("title")}</h3>
      {rows.length === 0 && s("empty") ? (
        <p className="text-[13px] text-foreground-muted">{s("empty")}</p>
      ) : (
        <div className="overflow-x-auto rounded-lg border border-border">
          <table className="w-full text-[13px]">
            <thead className="bg-surface-muted">
              <tr>
                {Array.from({ length: cols }, (_, i) => (
                  <th
                    key={i}
                    className="whitespace-nowrap px-3 py-2 text-left font-medium text-foreground-muted"
                  >
                    {s(`cols.${i}`)}
                  </th>
                ))}
              </tr>
            </thead>
            <tbody>
              {rows.map((r, i) => (
                <tr key={i} className="border-t border-border align-top">
                  {cells(r).map((c, j) => (
                    <td key={j} className="px-3 py-2 text-foreground">
                      {c}
                    </td>
                  ))}
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
    </section>
  );
}

/** One sample end to end, as the register opens it. */
function SampleDetail({ data }) {
  const t = useT("prototype");
  const s = (k, p) => t(`ui.s360.sum.${k}`, p);
  const stateText = useStateText();
  const lbl = (group, v) => (v ? t(`${group}.${v}`) : "—");
  const file = (d) =>
    d.file_id ? (
      <a
        href={`/api/files/${d.file_id}/download`}
        className="inline-flex items-center gap-1 text-brand hover:underline"
      >
        <Download className="size-3.5" aria-hidden />{" "}
        {d.file_name ?? t("ui.docs.download")}
      </a>
    ) : d.file && /^https?:\/\//.test(d.file) ? (
      <a
        href={d.file}
        target="_blank"
        rel="noreferrer"
        className="text-brand hover:underline"
      >
        {d.file}
      </a>
    ) : (
      dash(d.file)
    );
  const summary = [
    ["sample", data.sample_no],
    ["version", `V${data.version}`],
    [
      "status_stage",
      `${t(`life_stage.${data.stage}`)} · ${t(`life_status.${data.status}`)}`,
    ],
    ["company", data.company],
    ["buyer", data.buyer],
    ["buying_house", data.buying_house],
    ["order", data.order_no],
    ["style", data.style],
    ["season", data.season],
    ["sample_type", data.sample_type],
    ["sample_stage", lbl("stage", data.sample_stage)],
    ["qty", data.qty],
    ["priority", lbl("priority", data.priority)],
    ["required", day(data.required_date)],
    ["req", data.req_no ? `${data.req_no} (${day(data.req_date)})` : null],
    ["requested_by", data.requested_by],
    ["responsible", data.responsible],
    ["progress", `${data.progress}%`],
    [
      "dev_progress",
      data.development_progress === null
        ? null
        : `${num(data.development_progress)}%`,
    ],
    ["overdue", data.overdue ? s("days", { n: data.days_late }) : null],
    ["material_pending", data.material_pending ? t("ui.yes") : t("ui.no")],
  ];
  const sections = [
    [
      "timeline",
      data.timeline,
      (e) => [
        at(e.at),
        lbl("life_stage", e.stage),
        e.title,
        e.summary,
        e.from ? `${stateText(e.from)} → ${stateText(e.to)}` : "—",
        dash(e.by),
      ],
    ],
    [
      "decisions",
      data.decisions,
      (x) => [
        lbl("level", x.level),
        lbl("decision", x.decision),
        dash(x.by),
        at(x.at),
        dash(x.comments),
      ],
    ],
    [
      "checklist",
      data.checklist,
      (x) => [
        lbl("task", x.task),
        lbl("task_status", x.status),
        at(x.completed_at),
        dash(x.remarks),
      ],
    ],
    [
      "developments",
      data.developments,
      (x) => [
        x.dev_no,
        `V${x.version}`,
        lbl("dev_status", x.status),
        `${num(x.progress)}%`,
        day(x.start),
        day(x.expected),
        day(x.completed),
      ],
    ],
    [
      "revisions",
      data.revisions,
      (x) => [
        x.rev_no,
        day(x.date),
        `V${x.from} → V${x.to}`,
        dash(x.reason),
        lbl("rev_status", x.status),
        x.approved ? t("ui.yes") : t("ui.no"),
      ],
    ],
    [
      "material",
      data.material,
      (x) => [
        `${x.mr_no} (${day(x.mr_date)})`,
        dash(x.material),
        num(x.required),
        num(x.available),
        num(x.issued),
        num(x.balance),
        num(x.short),
        dash(x.unit),
        lbl("mr_state", x.state),
      ],
    ],
    [
      "issues",
      data.issues,
      (x) => [
        x.issue_no,
        day(x.date),
        dash(x.mr_no),
        dash(x.store),
        x.lines,
        num(x.qty),
        dash(x.issued_by),
        dash(x.received_by),
      ],
    ],
    [
      "bundles",
      data.bundles,
      (x) => [
        dash(x.card_no),
        x.bundle_no,
        dash(x.color),
        dash(x.size),
        num(x.req_qty),
        num(x.cutting_qty),
        x.now_at ? t(`process.${x.now_at}`) : "—",
        lbl("bundle_status", x.bundle_status),
      ],
    ],
    [
      "day_lines",
      data.day_lines,
      (x) => [
        day(x.date),
        dash(x.line),
        num(x.target),
        num(x.achieved),
        num(x.manpower),
        num(x.wh),
        num(x.target_hrs),
        num(x.actual_hrs),
      ],
    ],
    [
      "tracking",
      data.tracking,
      (x) => [
        at(x.at),
        lbl("process", x.process),
        dash(x.bundle_no),
        num(x.qty),
        lbl("move_status", x.status),
        num(x.delay_hours),
      ],
    ],
    [
      "qc",
      data.qc,
      (x) => [
        x.qc_no,
        day(x.date),
        `V${x.version}`,
        lbl("result", x.result),
        lbl("severity", x.severity),
        dash(x.defect),
      ],
    ],
    [
      "approvals",
      data.approvals,
      (x) => [
        x.approval_no,
        `V${x.version}`,
        lbl("step", x.step),
        day(x.submitted),
        lbl("decision_sa", x.decision),
        day(x.approval_date),
        dash(x.by),
      ],
    ],
    [
      "feedback",
      data.feedback,
      (x) => [
        x.feedback_no,
        day(x.date),
        lbl("fb_type", x.type),
        lbl("fb_status", x.status),
        dash(x.comment),
        day(x.due),
      ],
    ],
    [
      "handovers",
      data.handovers,
      (x) => [
        x.handover_no,
        day(x.date),
        num(x.qty),
        dash(x.to),
        lbl("ho_status", x.status),
        dash(x.courier),
        dash(x.received_by),
      ],
    ],
    [
      "documents",
      data.documents,
      (x) => [
        x.doc_no,
        lbl("doc_type", x.type),
        `V${x.version}`,
        x.title,
        file(x),
        at(x.at),
      ],
    ],
    [
      "alerts",
      data.alerts,
      (x) => [
        at(x.raised_at),
        <SeverityBadge
          key="s"
          severity={x.severity}
          label={t(`alert_severity.${x.severity}`)}
        />,
        lbl("alert_kind", x.kind),
        x.title,
        x.resolved
          ? t("ui.s360.resolved")
          : x.read
            ? t("ui.s360.read")
            : t("ui.s360.unread"),
      ],
    ],
    [
      "audit",
      data.audit,
      (e) => [
        at(e.at),
        e.title,
        e.from ? stateText(e.from) : "—",
        e.to ? stateText(e.to) : "—",
        dash(e.doc_no),
        dash(e.by),
      ],
    ],
  ];
  return (
    <div className="flex flex-col gap-5 bg-surface px-4 py-4">
      <h3 className="text-xs font-semibold uppercase tracking-wide text-foreground-muted">
        {s("title")}
      </h3>
      <dl className="grid gap-x-6 gap-y-1.5 text-[13px] sm:grid-cols-2 lg:grid-cols-3">
        {summary.map(([k, v]) => (
          <div key={k} className="flex gap-2">
            <dt className="text-foreground-muted">{s(k)}</dt>
            <dd className="font-semibold text-foreground">{dash(v)}</dd>
          </div>
        ))}
      </dl>
      {sections.map(([k, rows, cells]) => (
        <DetailTable
          key={k}
          k={k}
          cols={COLS[k]}
          rows={rows ?? []}
          cells={cells}
        />
      ))}
    </div>
  );
}

/**
 * The Sample Register: filters over the desk's samples, each row opening in
 * place into its 360° view.
 *
 * @param {{ rows: object[], types: { value: string, label: string }[], company: string, initialOpen?: string, load: (no: string) => Promise<object> }} props
 */
function SampleRegister({ rows, types, company, initialOpen, load }) {
  const t = useT("prototype");
  const f = (k, p) => t(`ui.s360.f.${k}`, p);
  const c = (k) => t(`ui.s360.col.${k}`);
  const blankFilters = {
    month: "",
    type: "",
    stage: "",
    status: "",
    priority: "",
    company: "",
    show: "all",
    mine: false,
    material: false,
    buyer: "",
  };
  const [filters, setFilters] = useState(blankFilters);
  const [query, setQuery] = useState("");
  const [search, setSearch] = useState(initialOpen ?? "");
  const [page, setPage] = useState(1);
  const [open, setOpen] = useState(initialOpen ?? null);
  const [details, setDetails] = useState({});
  const [error, setError] = useState(null);
  const [pending, startTransition] = useTransition();
  const loaded = useRef(new Set());
  const set = (k) => (v) => (
    setFilters((all) => ({ ...all, [k]: v ?? "" })),
    setPage(1)
  );

  function fetchDetail(no) {
    if (loaded.current.has(no)) return;
    loaded.current.add(no);
    startTransition(async () => {
      const result = await load(no);
      if (result?.status === "success")
        setDetails((all) => ({ ...all, [no]: result.data }));
      else (loaded.current.delete(no), setError(messagesOf(result)));
    });
  }
  // Opened from the address bar (`?sample=`): its view is read once on mount.
  useEffect(() => {
    if (initialOpen) fetchDetail(initialOpen);
  }, []); // eslint-disable-line react-hooks/exhaustive-deps

  function toggle(no) {
    const next = open === no ? null : no;
    setOpen(next);
    if (next) fetchDetail(next);
    setError(null);
    window.history.replaceState(
      null,
      "",
      next ? `?tab=view&sample=${encodeURIComponent(next)}` : "?tab=view",
    );
  }

  const months = useMemo(
    () =>
      [...new Set(rows.map((r) => r.req_month).filter(Boolean))]
        .sort()
        .reverse(),
    [rows],
  );
  const buyers = useMemo(
    () => [...new Set(rows.map((r) => r.buyer).filter(Boolean))].sort(),
    [rows],
  );
  const needle = search.trim().toLowerCase();
  const matched = rows.filter((r) => {
    if (filters.month && r.req_month !== filters.month) return false;
    if (filters.type && r.sample_type_id !== filters.type) return false;
    if (filters.stage && r.stage !== filters.stage) return false;
    if (filters.status && r.status !== filters.status) return false;
    if (filters.priority && r.priority !== filters.priority) return false;
    if (filters.show === "open" && !r.open) return false;
    if (filters.show === "overdue" && !r.overdue) return false;
    if (filters.show === "completed" && r.status !== "COMPLETED") return false;
    if (filters.mine && !r.mine) return false;
    if (filters.material && !r.material_pending) return false;
    if (
      needle &&
      ![
        r.sample_no,
        r.req_no,
        r.style,
        r.style_name,
        r.order_no,
        r.buyer,
        ...(r.docs ?? []),
      ].some((v) => str(v).toLowerCase().includes(needle))
    )
      return false;
    return true;
  });
  const shown = filters.buyer
    ? matched.filter((r) => r.buyer === filters.buyer)
    : matched;
  const pages = Math.max(1, Math.ceil(shown.length / PAGE));
  const current = Math.min(page, pages);
  const slice = shown.slice((current - 1) * PAGE, current * PAGE);
  const opts = (group, list) =>
    list.map((v) => ({ value: v, label: t(`${group}.${v}`) }));
  const PRINT_TONE = {
    DRAFT: "muted",
    PENDING: "warn",
    APPROVED: "ok",
    IN_PROGRESS: "info",
    ON_HOLD: "warn",
    COMPLETED: "ok",
    REJECTED: "bad",
    CANCELLED: "muted",
    DELAYED: "bad",
  };
  // The register on paper: every sample the filters match (not just the page on screen).
  const printRegister = () =>
    printDocument({
      title: t("ui.s360.print_title"),
      orientation: "landscape",
      meta: [
        [t("ui.s360.print_count"), String(shown.length)],
        [
          t("ui.s360.print_overdue"),
          String(shown.filter((r) => r.overdue).length),
        ],
      ],
      sections: [
        {
          columns: [
            "status",
            "sample_no",
            "version",
            "style",
            "buyer",
            "order",
            "sample_type",
            "stage",
            "progress",
            "required",
            "late",
            "responsible",
            "flags",
          ].map((k) => ({
            label: c(k),
            align: ["progress", "late"].includes(k) ? "right" : undefined,
            nowrap: ["sample_no", "required"].includes(k),
          })),
          rows: shown.map((r) => [
            {
              v: t(`life_status.${r.status}`),
              tone: PRINT_TONE[r.status] ?? "muted",
            },
            { v: r.sample_no, bold: true, sub: r.req_no },
            `V${r.version}`,
            { v: r.style_name, sub: r.style },
            r.buyer,
            r.order_no,
            r.sample_type,
            t(`life_stage.${r.stage}`),
            `${r.progress}%`,
            r.required_date,
            r.overdue ? t("ui.s360.late_n", { n: r.days_late }) : null,
            r.responsible,
            (r.flags ?? []).map((fl) => t(`flag.${fl}`)).join(", "),
          ]),
          empty: t("ui.s360.empty"),
        },
      ],
    });

  return (
    <div className="flex flex-col gap-4">
      <section className={cn(card, "flex flex-col gap-4 p-4 print:hidden")}>
        <div className="grid gap-3 sm:grid-cols-3 lg:grid-cols-6">
          {[
            [
              "month",
              f("month"),
              f("all_months"),
              months.map((m) => ({
                value: m,
                label: monthFmt.format(new Date(`${m}-01`)),
              })),
            ],
            ["type", f("type"), f("all_types"), types],
            [
              "stage",
              f("stage"),
              f("all_stages"),
              opts("life_stage", [
                "REQUESTED",
                "DEVELOPMENT",
                "MATERIAL",
                "PRODUCTION",
                "QUALITY",
                "APPROVAL",
                "REVISION",
                "HANDOVER",
                "DELIVERY",
                "COMPLETED",
              ]),
            ],
            [
              "status",
              f("status"),
              f("all_statuses"),
              opts("life_status", Object.keys(STATUS_TONE)),
            ],
            [
              "priority",
              f("priority"),
              f("all_priorities"),
              opts("priority", ["LOW", "NORMAL", "HIGH", "URGENT", "CRITICAL"]),
            ],
            [
              "company",
              f("company"),
              f("all_companies"),
              [{ value: "this", label: company }],
            ],
          ].map(([k, label, all, options]) => (
            <label key={k} className="flex flex-col gap-1.5">
              <span className="text-[13px] font-medium text-foreground">
                {label}
              </span>
              <Select
                value={filters[k]}
                onValueChange={set(k)}
                options={[{ value: "", label: all }, ...options]}
                placeholder={all}
                aria-label={label}
              />
            </label>
          ))}
        </div>
        <div className="flex flex-wrap items-center justify-between gap-3">
          <div
            role="radiogroup"
            aria-label={f("status")}
            className="flex flex-wrap gap-4"
          >
            {["all", "open", "overdue", "completed"].map((v) => (
              <label
                key={v}
                className="inline-flex items-center gap-1.5 text-sm text-foreground"
              >
                <input
                  type="radio"
                  name="s360-show"
                  value={v}
                  checked={filters.show === v}
                  onChange={() => set("show")(v)}
                  className="size-4 accent-[var(--brand)]"
                />{" "}
                {f(v)}
              </label>
            ))}
          </div>
          <form
            className="flex flex-wrap items-center gap-3"
            onSubmit={(e) => {
              e.preventDefault();
              setSearch(query);
              setPage(1);
            }}
          >
            <Input
              value={query}
              onChange={(e) => setQuery(e.target.value)}
              placeholder={f("search")}
              aria-label={f("search")}
              className="w-72"
            />
            <button
              type="submit"
              className="inline-flex h-9 items-center gap-1.5 rounded-md border border-border px-3 text-sm font-medium text-foreground hover:bg-surface-muted"
            >
              <Search className="size-4" aria-hidden /> {f("search_btn")}
            </button>
            {[
              ["mine", f("mine")],
              ["material", f("material_pending")],
            ].map(([k, label]) => (
              <label
                key={k}
                className="inline-flex items-center gap-1.5 text-sm text-foreground"
              >
                <Checkbox
                  checked={filters[k]}
                  onCheckedChange={(v) => set(k)(Boolean(v))}
                  aria-label={label}
                />{" "}
                {label}
              </label>
            ))}
          </form>
        </div>
        <div
          className="flex flex-wrap gap-2 border-t border-border pt-3"
          role="tablist"
        >
          {["", ...buyers].map((b) => (
            <button
              key={b || "all"}
              type="button"
              role="tab"
              aria-selected={filters.buyer === b}
              onClick={() => set("buyer")(b)}
              className={cn(
                "rounded-md border px-3 py-1 text-[13px] font-medium",
                filters.buyer === b
                  ? "border-brand bg-brand text-brand-foreground"
                  : "border-border text-foreground hover:bg-surface-muted",
              )}
            >
              {b || f("all")}
            </button>
          ))}
        </div>
      </section>

      {error ? (
        <Alert variant="danger">
          {error.map((e) => (
            <p key={e}>{e}</p>
          ))}
        </Alert>
      ) : null}

      <section className={cn(card, "min-w-0 overflow-hidden")}>
        <div className="overflow-x-auto">
          <table className="w-full min-w-[1100px] text-[13px]">
            <thead className="bg-brand text-brand-foreground">
              <tr>
                <th className="w-8" aria-label={t("ui.s360.open")} />
                {[
                  "status",
                  "sample_no",
                  "version",
                  "style",
                  "buyer",
                  "order",
                  "sample_type",
                  "stage",
                  "progress",
                  "required",
                  "late",
                  "responsible",
                  "flags",
                ].map((k) => (
                  <th
                    key={k}
                    className={cn(
                      "whitespace-nowrap px-3 py-2.5 text-left font-semibold",
                      ["progress", "late"].includes(k) && "text-right",
                    )}
                  >
                    {c(k)}
                  </th>
                ))}
              </tr>
            </thead>
            <tbody>
              {slice.length === 0 ? (
                <tr>
                  <td
                    colSpan={14}
                    className="px-3 py-10 text-center text-foreground-muted"
                  >
                    {t("ui.s360.empty")}
                  </td>
                </tr>
              ) : null}
              {slice.map((r) => {
                const isOpen = open === r.sample_no;
                return (
                  <Fragment key={r.sample_no}>
                    <tr
                      className={cn(
                        "border-t border-border align-top",
                        isOpen && "bg-surface-muted",
                      )}
                    >
                      <td className="px-2 py-2.5">
                        <button
                          type="button"
                          onClick={() => toggle(r.sample_no)}
                          aria-expanded={isOpen}
                          aria-label={`${t("ui.s360.open")} ${r.sample_no}`}
                          className="flex size-6 items-center justify-center rounded text-foreground-muted hover:bg-surface-muted"
                        >
                          {isOpen ? (
                            <ChevronDown className="size-4" />
                          ) : (
                            <ChevronRight className="size-4" />
                          )}
                        </button>
                      </td>
                      <td className="px-3 py-2.5">
                        <StatusChip status={r.status} />
                      </td>
                      <td className="px-3 py-2.5">
                        <p className="font-medium text-foreground">
                          {r.sample_no}
                        </p>
                        <p className="text-xs italic text-foreground-muted">
                          {r.req_no}
                        </p>
                      </td>
                      <td className="px-3 py-2.5 text-foreground">
                        V{r.version}
                      </td>
                      <td className="px-3 py-2.5">
                        <p className="text-foreground">{dash(r.style_name)}</p>
                        <p className="text-xs italic text-foreground-muted">
                          {r.style}
                        </p>
                      </td>
                      <td className="px-3 py-2.5 text-foreground">
                        {dash(r.buyer)}
                      </td>
                      <td className="px-3 py-2.5 text-foreground">
                        {dash(r.order_no)}
                      </td>
                      <td className="px-3 py-2.5 text-foreground">
                        {dash(r.sample_type)}
                      </td>
                      <td className="px-3 py-2.5 text-foreground">
                        {t(`life_stage.${r.stage}`)}
                      </td>
                      <td className="px-3 py-2.5 text-right tabular-nums text-foreground">
                        {r.progress}%
                      </td>
                      <td className="px-3 py-2.5 whitespace-nowrap text-foreground">
                        {day(r.required_date)}
                      </td>
                      <td
                        className={cn(
                          "px-3 py-2.5 whitespace-nowrap text-right tabular-nums",
                          r.overdue ? "text-danger" : "text-foreground-muted",
                        )}
                      >
                        {r.overdue
                          ? t("ui.s360.late_n", { n: r.days_late })
                          : "—"}
                      </td>
                      <td className="px-3 py-2.5 text-foreground">
                        {dash(r.responsible)}
                      </td>
                      <td className="px-3 py-2.5">
                        <div className="flex flex-wrap gap-1">
                          {(r.flags ?? []).map((fl) => (
                            <span
                              key={fl}
                              className={cn(
                                "rounded-md px-1.5 py-0.5 text-[11px] font-medium",
                                FLAG_TONE[fl],
                              )}
                            >
                              {t(`flag.${fl}`)}
                            </span>
                          ))}
                        </div>
                      </td>
                    </tr>
                    {isOpen ? (
                      <tr className="border-t border-border">
                        <td colSpan={14} className="p-0">
                          {details[r.sample_no] ? (
                            <SampleDetail data={details[r.sample_no]} />
                          ) : (
                            <p className="px-4 py-6 text-center text-foreground-muted">
                              {pending ? t("ui.s360.loading") : ""}
                            </p>
                          )}
                        </td>
                      </tr>
                    ) : null}
                  </Fragment>
                );
              })}
            </tbody>
          </table>
        </div>
        <div className="flex flex-wrap items-center justify-between gap-3 border-t border-border px-4 py-3 print:hidden">
          <div className="flex items-center gap-1.5">
            <button
              type="button"
              disabled={current === 1}
              onClick={() => setPage(current - 1)}
              aria-label={t("ui.s360.prev")}
              className="flex size-9 items-center justify-center rounded-md border border-border text-foreground-muted hover:bg-surface-muted disabled:opacity-40"
            >
              <ChevronLeft className="size-4" />
            </button>
            <span className="flex size-9 items-center justify-center rounded-md bg-brand text-sm font-medium text-brand-foreground">
              {current}
            </span>
            <button
              type="button"
              disabled={current >= pages}
              onClick={() => setPage(current + 1)}
              aria-label={t("ui.s360.next")}
              className="flex size-9 items-center justify-center rounded-md border border-border text-foreground-muted hover:bg-surface-muted disabled:opacity-40"
            >
              <ChevronRight className="size-4" />
            </button>
          </div>
          <div className="flex items-center gap-3">
            <span className="text-[13px] text-foreground-muted">
              {t("ui.s360.shown", {
                from: shown.length ? (current - 1) * PAGE + 1 : 0,
                to: Math.min(current * PAGE, shown.length),
                n: shown.length,
              })}
            </span>
            <button
              type="button"
              onClick={printRegister}
              className="inline-flex h-9 items-center gap-1.5 rounded-md border border-border px-3 text-sm font-medium text-foreground hover:bg-surface-muted"
            >
              <Printer className="size-4" aria-hidden /> {t("ui.s360.print")}
            </button>
          </div>
        </div>
      </section>
      <p className="text-[13px] text-foreground-muted">{t("ui.s360.note")}</p>
    </div>
  );
}

const formOf = (row) => ({
  sample_id: row?.sample_id ?? "",
  doc_type: row?.doc_type ?? "",
  title: row?.title ?? "",
  file: row?.file ?? "",
  remarks: row?.remarks ?? "",
});

/**
 * Sample Documents: a new document is the next version of its type on the
 * sample, with a path or link, or a file attached (sent through the
 * /api/prototype/documents route, which streams it on); a saved one changes
 * only its title and remarks.
 *
 * @param {{ options: { samples: object[], types: string[] }, initial: ?object, existing: object[], canManage: boolean, load: Function, retitle: Function }} props
 */
function SampleDocumentForm({
  options,
  initial,
  existing,
  canManage,
  load,
  retitle,
}) {
  const t = useT("prototype");
  const te = useT("erp");
  const g = (k, p) => t(`ui.docs.${k}`, p);
  const router = useRouter();
  const toastManager = useToastManager();
  const [row, setRow] = useState(initial);
  const [form, setForm] = useState(() => formOf(initial));
  const [upload, setUpload] = useState(null);
  const [errors, setErrors] = useState(null);
  const [pending, startTransition] = useTransition();
  const fileRef = useRef(null);
  const set = (k) => (v) => setForm((all) => ({ ...all, [k]: v ?? "" }));
  const take = (data) => (
    setRow(data),
    setForm(formOf(data)),
    setUpload(null),
    fileRef.current && (fileRef.current.value = "")
  );
  const address = (no) =>
    window.history.replaceState(
      null,
      "",
      no ? `?tab=docs&doc=${encodeURIComponent(no)}` : "?tab=docs",
    );
  const saved = Boolean(row);
  const lock = !canManage || pending;

  function pick(no) {
    address(no);
    setErrors(null);
    if (!no) return take(null);
    startTransition(async () => {
      const result = await load(no);
      if (result?.status === "success") take(result.data);
      else setErrors(messagesOf(result));
    });
  }

  function save() {
    setErrors(null);
    startTransition(async () => {
      let result;
      if (saved) {
        result = await retitle(row.doc_no, {
          title: form.title,
          remarks: form.remarks || null,
        });
      } else {
        const body = new FormData();
        Object.entries(form).forEach(([k, v]) => v !== "" && body.append(k, v));
        if (upload) body.append("upload", upload);
        const response = await fetch("/api/prototype/documents", {
          method: "POST",
          body,
        });
        const json = await response.json().catch(() => ({}));
        result = response.ok
          ? { status: "success", data: json.data }
          : {
              status: "error",
              message: json.message ?? json.error?.message,
              errors: json.errors ?? json.error?.details,
            };
      }
      if (result?.status !== "success") return setErrors(messagesOf(result));
      // Loaded back with its versions.
      const full = await load(result.data.doc_no);
      take(full?.status === "success" ? full.data : result.data);
      address(result.data.doc_no);
      toastManager.add({
        title: g("saved", { no: result.data.doc_no }),
        type: "success",
      });
      router.refresh();
    });
  }

  const sampleOptions = options.samples.map((s) => ({
    value: s.value ?? s.id,
    label: s.label,
  }));
  return (
    <div className="flex flex-col gap-4">
      <section className={cn(card, "flex flex-col gap-4 p-5")}>
        <DocHead
          title={g("title")}
          hint={g("hint")}
          badge={row ? `${row.doc_no} · V${row.version}` : t("ui.new")}
          existingLabel={g("existing")}
          newOption={g("new_option")}
          existing={existing}
          value={row?.doc_no ?? ""}
          onPick={pick}
          errors={errors}
        />
        <Section title={g("title")}>
          <Field label={g("f.sample")} required>
            <Select
              value={form.sample_id}
              onValueChange={set("sample_id")}
              options={sampleOptions}
              placeholder={t("ui.select")}
              disabled={lock || saved}
              aria-label={g("f.sample")}
            />
          </Field>
          <Field label={g("f.doc_type")} required>
            <Select
              value={form.doc_type}
              onValueChange={set("doc_type")}
              options={options.types.map((v) => ({
                value: v,
                label: t(`doc_type.${v}`),
              }))}
              placeholder={t("ui.select")}
              disabled={lock || saved}
              aria-label={g("f.doc_type")}
            />
          </Field>
          <Field label={g("f.version")} hint={g("h.version")}>
            <ReadOnly
              value={row ? `V${row.version}` : ""}
              placeholder={t("ui.issued_on_save")}
            />
          </Field>
          <Field label={g("f.title")} required>
            <Input
              value={form.title}
              onChange={(e) => set("title")(e.target.value)}
              disabled={lock}
              aria-label={g("f.title")}
            />
          </Field>
          <Field
            label={g("f.file")}
            required
            hint={saved && row.file_id ? null : g("h.file")}
          >
            {saved && row.file_id ? (
              <a
                href={`/api/files/${row.file_id}/download`}
                className="inline-flex h-9 items-center gap-1.5 text-sm text-brand hover:underline"
              >
                <Download className="size-4" aria-hidden /> {row.file_name}
              </a>
            ) : (
              <Input
                value={form.file}
                onChange={(e) => set("file")(e.target.value)}
                placeholder={g("h.file")}
                disabled={lock || saved}
                aria-label={g("f.file")}
              />
            )}
          </Field>
          <Field
            label={g("f.attach")}
            hint={upload ? g("chosen", { name: upload.name }) : g("h.attach")}
          >
            <FileInput
              ref={fileRef}
              accept="image/*,.pdf"
              onChange={(e) => setUpload(e.target.files?.[0] ?? null)}
              disabled={lock || saved}
              aria-label={g("f.attach")}
            />
          </Field>
          <Field label={g("f.remarks")} wide>
            <Textarea
              value={form.remarks}
              onChange={(e) => set("remarks")(e.target.value)}
              disabled={lock}
              rows={3}
              aria-label={g("f.remarks")}
            />
          </Field>
        </Section>
      </section>
      <RecordsGrid
        rows={(row?.versions ?? []).map((v) => ({
          ...v,
          version: `V${v.version}`,
          file_label: v.file_name ?? v.file,
        }))}
        rowKey="doc_no"
        exportName="sample-document-versions"
        searchable={false}
        header={{
          title: g("versions"),
          subtitle: g("versions_hint"),
          searchLabel: t("ui.search"),
          searchPlaceholder: t("ui.search"),
          exportLabel: te("export"),
        }}
        emptyText={t("ui.no_lines")}
        columns={[
          { key: "version", label: g("col.version") },
          { key: "doc_no", label: g("col.doc_no"), type: "mono" },
          { key: "title", label: g("col.title") },
          { key: "file_label", label: g("col.file") },
          { key: "uploaded_by_name", label: g("col.uploaded_by") },
          { key: "uploaded_at", label: g("col.uploaded_at"), type: "datetime" },
        ]}
        rowActions={[
          { key: "load", label: t("ui.open"), onClick: (v) => pick(v.doc_no) },
        ]}
      />
      <DocFoot
        note={g("note")}
        onClear={() => pick("")}
        onSave={canManage ? save : undefined}
        saveLabel={g("save")}
        pending={pending}
      />
    </div>
  );
}

export { SampleDetail, SampleDocumentForm, SampleRegister };
