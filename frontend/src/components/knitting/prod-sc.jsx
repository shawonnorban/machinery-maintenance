"use client";

import { useMemo, useState, useTransition } from "react";
import Link from "next/link";
import {
  ArrowLeft,
  Check,
  CheckCheck,
  Download,
  Eraser,
  FilterX,
  Plus,
  Printer,
  RotateCcw,
  Save,
  Search,
  Trash2,
  XCircle,
} from "lucide-react";
import { cn } from "@/lib/utils";
import { useT } from "@/lib/i18n";
import { printDocument } from "@/lib/print-document";
import { Input } from "@/components/ui/input";
import { Textarea } from "@/components/ui/textarea";
import { Button, buttonVariants } from "@/components/ui/button";
import { Alert } from "@/components/ui/alert";
import { ConfirmDialog } from "@/components/ui/confirm-dialog";
import { useToastManager } from "@/components/ui/toast";
import {
  PSelect,
  str,
  num,
  qtyFmt,
  moneyFmt,
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

/*
 * Knit Production → Production (AnnotechERP 15/04) → Bulk Solid, Sample
 * Solid and Yarn-Dyed Sub-Contract Knit Programme: the register (filters
 * above, a search box, Export, and Program, Challan, Submit, Approve,
 * Reject and Delete by status — each asked first) and the programme's own
 * form: Programme, Party, Booked Against, then the fabric lines, added one
 * at a time and edited in the grid. Also the shared register table the
 * Programme Receipt list uses. Nothing reloads.
 */

const SLUG = {
  BULK_SOLID: "issue-sc-knitting-program-bulk-solid",
  SAMPLE_SOLID: "issue-sc-knitting-program-sample-solid",
  PARTY_YD: "issue-sc-knitting-program-party-yd",
};
const TONE = {
  DRAFT: "DRAFT",
  SUBMITTED: "WAITING_APPROVAL",
  APPROVED: "APPROVED",
  REJECTED: "REJECTED",
  CANCELLED: "CANCELLED",
};

function useSc() {
  const t = useT("knitting");
  return (k, p) => t(`sc.${k}`, p);
}

const csvOut = (name, columns, rows, text) => {
  const esc = (v) => `"${String(v ?? "").replaceAll('"', '""')}"`;
  const lines = [
    columns.map((c) => esc(c.label)).join(","),
    ...rows.map((r) => columns.map((c) => esc(text(c, r))).join(",")),
  ];
  const url = URL.createObjectURL(
    new Blob([lines.join("\n")], { type: "text/csv" }),
  );
  Object.assign(document.createElement("a"), {
    href: url,
    download: `${name}.csv`,
  }).click();
  URL.revokeObjectURL(url);
};

/**
 * The register table the S/C and yarn test desks share: a search box, Add
 * New and Export, a filter box under every column where asked, an ACTION
 * column where there are actions, and pages of 10 / 25 / 50 / 100 with
 * Refresh.
 */
function ScTable({
  rows,
  columns,
  actions,
  colFilters = false,
  exportName,
  addHref,
  empty,
  onRefresh,
  pending,
  minWidth = "min-w-[1200px]",
}) {
  const c = useSc();
  const [q, setQ] = useState("");
  const [col, setCol] = useState({});
  const [per, setPer] = useState(10);
  const [page, setPage] = useState(0);
  const text = (x, r) => String(x.text ? x.text(r) : (r[x.key] ?? ""));
  const shown = useMemo(
    () =>
      rows.filter(
        (r) =>
          (!q ||
            columns.some((x) =>
              text(x, r).toLowerCase().includes(q.toLowerCase()),
            )) &&
          columns.every(
            (x) =>
              !col[x.key] ||
              text(x, r).toLowerCase().includes(col[x.key].toLowerCase()),
          ),
      ),
    [rows, q, col, columns],
  );
  const pages = Math.max(1, Math.ceil(shown.length / per));
  const at = Math.min(page, pages - 1);
  const slice = shown.slice(at * per, at * per + per);

  return (
    <>
      <div className="flex flex-wrap items-center justify-between gap-3 px-5 py-3">
        <div className="relative w-full max-w-xs">
          <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-foreground-muted" />
          <Input
            value={q}
            onChange={(e) => (setQ(e.target.value), setPage(0))}
            placeholder={c("ph.search", { count: rows.length })}
            className="pl-9"
            aria-label={c("btn.search")}
          />
        </div>
        <div className="flex gap-2">
          {addHref ? (
            <Link href={addHref} className={buttonVariants({ size: "sm" })}>
              <Plus /> {c("btn.add_new")}
            </Link>
          ) : null}
          <Button
            variant="outline"
            size="sm"
            onClick={() => csvOut(exportName, columns, shown, text)}
          >
            <Download /> {c("btn.export")}
          </Button>
        </div>
      </div>
      <div className="overflow-x-auto">
        <table className={cn("w-full text-sm", minWidth)}>
          <thead className="bg-brand text-brand-foreground">
            <tr>
              {columns.map((x) => (
                <th key={x.key} className={cn(th, x.num && "text-right")}>
                  {x.label}
                </th>
              ))}
              {actions ? (
                <th className={cn(th, "text-right")}>{c("col.action")}</th>
              ) : null}
            </tr>
            {colFilters ? (
              <tr className="bg-surface">
                {columns.map((x) => (
                  <th key={x.key} className="px-2 py-1.5">
                    <Input
                      value={col[x.key] ?? ""}
                      onChange={(e) => (
                        setCol((v) => ({ ...v, [x.key]: e.target.value })),
                        setPage(0)
                      )}
                      className="h-8 text-foreground"
                      aria-label={c("filter_by", { col: x.label })}
                    />
                  </th>
                ))}
                {actions ? <th /> : null}
              </tr>
            ) : null}
          </thead>
          <tbody>
            {slice.length === 0 ? (
              <tr>
                <td colSpan={columns.length + (actions ? 1 : 0)}>{empty}</td>
              </tr>
            ) : (
              slice.map((r, i) => (
                <tr key={i} className="border-t border-border">
                  {columns.map((x) => (
                    <td
                      key={x.key}
                      className={cn(
                        "px-2 py-2",
                        x.num && "text-right tabular-nums",
                        x.nowrap && "whitespace-nowrap",
                      )}
                    >
                      {x.render
                        ? x.render(r)
                        : x.text
                          ? x.text(r)
                          : (r[x.key] ?? "—")}
                    </td>
                  ))}
                  {actions ? (
                    <td className="px-2 py-1.5">
                      <div className="flex justify-end gap-1">{actions(r)}</div>
                    </td>
                  ) : null}
                </tr>
              ))
            )}
          </tbody>
        </table>
      </div>
      <div className="flex flex-wrap items-center justify-between gap-3 border-t border-border px-5 py-3 text-[13px] text-foreground-muted">
        <div className="flex items-center gap-2">
          <Button
            variant="outline"
            size="sm"
            onClick={() => setPage(Math.max(0, at - 1))}
            disabled={at === 0}
            aria-label={c("prev")}
          >
            ‹
          </Button>
          {at + 1} / {pages}
          <Button
            variant="outline"
            size="sm"
            onClick={() => setPage(Math.min(pages - 1, at + 1))}
            disabled={at >= pages - 1}
            aria-label={c("next")}
          >
            ›
          </Button>
          <div className="w-20">
            <PSelect
              value={String(per)}
              onValueChange={(v) => (setPer(Number(v ?? 10)), setPage(0))}
              options={[10, 25, 50, 100].map((n) => ({
                value: String(n),
                label: String(n),
              }))}
              aria-label={c("per_page")}
            />
          </div>
          {c("per_page")}
        </div>
        <div className="flex items-center gap-2">
          {c("items", {
            from: shown.length ? at * per + 1 : 0,
            to: at * per + slice.length,
            count: shown.length,
          })}
          <Button
            variant="outline"
            size="icon"
            className="size-8"
            onClick={onRefresh}
            loading={pending}
            aria-label={c("refresh")}
          >
            <RotateCcw />
          </Button>
        </div>
      </div>
    </>
  );
}

/** "M.Dia 30 · 24G · F.Dia 72 · 160 GSM", as Brickland's programme prints it. */
const construction = (l) =>
  [
    l.machine_dia ? `M.Dia ${l.machine_dia}` : null,
    l.gauge ? `${l.gauge}${/g$/i.test(l.gauge) ? "" : "G"}` : null,
    l.finish_dia ? `F.Dia ${l.finish_dia}` : null,
    l.gsm ? `${l.gsm} GSM` : null,
  ]
    .filter(Boolean)
    .join(" · ");

function programMeta(c, d) {
  return [
    [c("f.po_date"), dayFmt(d.po_date)],
    [c("f.company"), d.company],
    [c("f.party"), d.party],
    [c("p.address"), d.party_address],
    [c("p.program_type"), c(`type.${d.program_type}`)],
    [c("f.category"), d.category],
    [c("p.buyer"), d.buyer],
    [c("f.style"), d.style_ref],
    [c("f.work_style"), d.work_style_no],
    [c("f.order"), d.order],
    [c("p.target"), d.target_date ? dayFmt(d.target_date) : null],
    [c("p.transferred"), d.transferred ? c("yes") : c("no")],
    [c("f.status"), { v: c(`status.${d.status}`), tone: "info" }],
  ];
}

const colourOf = (l) =>
  [l.colour, l.colour_group].filter(Boolean).join(" · ") || null;

/** The programme as the party receives it: what to knit, at what rate. */
function printProgram(c, d) {
  const total = d.lines.reduce((a, l) => a + num(l.qty), 0);
  const value = d.lines.reduce((a, l) => a + num(l.qty) * num(l.rate), 0);
  printDocument({
    title: c(`p.program_title.${d.kind}`),
    docNo: d.program_no,
    orientation: "landscape",
    meta: programMeta(c, d),
    sections: [
      {
        title: c("p.to_knit"),
        columns: [
          { label: "#", align: "center", width: "4%" },
          { label: c("col.fabric") },
          { label: c("p.colour") },
          { label: c("p.construction") },
          { label: c("col.uom"), align: "center" },
          { label: c("f.qty"), align: "right" },
          { label: c("p.received"), align: "right" },
          { label: c("p.balance"), align: "right" },
          { label: c("col.rate"), align: "right" },
          { label: c("p.value"), align: "right" },
        ],
        rows: d.lines.map((l, i) => [
          i + 1,
          { v: l.fabric, bold: true, sub: l.remarks || undefined },
          colourOf(l),
          construction(l),
          l.uom,
          qtyFmt.format(l.qty),
          qtyFmt.format(l.received_qty),
          qtyFmt.format(num(l.qty) - num(l.received_qty)),
          l.rate === null ? "—" : moneyFmt.format(l.rate),
          l.rate === null ? "—" : moneyFmt.format(num(l.qty) * num(l.rate)),
        ]),
        foot: [
          [
            "",
            { v: c("p.total"), bold: true },
            "",
            "",
            "",
            { v: qtyFmt.format(total), bold: true },
            {
              v: qtyFmt.format(
                d.lines.reduce((a, l) => a + num(l.received_qty), 0),
              ),
              bold: true,
            },
            {
              v: qtyFmt.format(
                total - d.lines.reduce((a, l) => a + num(l.received_qty), 0),
              ),
              bold: true,
            },
            "",
            { v: moneyFmt.format(value), bold: true },
          ],
        ],
        empty: c("p.no_lines"),
      },
    ],
    notes: d.remarks ? [d.remarks] : [],
    signatures: [
      c("p.sign.prepared"),
      c("p.sign.approved"),
      c("p.sign.party_accept"),
    ],
  });
}

/** The delivery challan the yarn and the programme go out on. */
function printChallan(c, d) {
  const total = d.lines.reduce((a, l) => a + num(l.qty), 0);
  const got = d.lines.reduce((a, l) => a + num(l.received_qty), 0);
  printDocument({
    title: c("p.challan_title"),
    docNo: d.program_no,
    orientation: "landscape",
    meta: programMeta(c, d),
    sections: [
      {
        title: c("p.going_out"),
        columns: [
          { label: "#", align: "center", width: "4%" },
          { label: c("col.fabric") },
          { label: c("p.colour") },
          { label: c("p.construction") },
          { label: c("col.uom"), align: "center" },
          { label: c("f.qty"), align: "right" },
          { label: c("p.already"), align: "right" },
          { label: c("p.balance"), align: "right" },
        ],
        rows: d.lines.map((l, i) => [
          i + 1,
          { v: l.fabric, bold: true },
          colourOf(l),
          construction(l),
          l.uom,
          qtyFmt.format(l.qty),
          qtyFmt.format(l.received_qty),
          qtyFmt.format(num(l.qty) - num(l.received_qty)),
        ]),
        foot: [
          [
            "",
            { v: c("p.total"), bold: true },
            "",
            "",
            "",
            { v: qtyFmt.format(total), bold: true },
            { v: qtyFmt.format(got), bold: true },
            { v: qtyFmt.format(total - got), bold: true },
          ],
        ],
        empty: c("p.no_lines"),
      },
    ],
    notes: d.remarks ? [d.remarks] : [],
    signatures: [
      c("p.sign.store"),
      c("p.sign.security"),
      c("p.sign.driver"),
      c("p.sign.received"),
    ],
  });
}

function ScRegister({ kind, options, initialRows, canManage, act }) {
  const c = useSc();
  const toastManager = useToastManager();
  const base = `/knit-production/${SLUG[kind]}`;
  const blank = {
    category: "",
    party: "",
    buyer: "",
    style: "",
    q: "",
    status: "",
    from: "",
    to: "",
  };
  const [f, setFilters] = useState(blank);
  const [rows, setRows] = useState(initialRows);
  const [error, setError] = useState(null);
  const [ask, setAsk] = useState(null);
  const [pending, startTransition] = useTransition();
  const setF = (k, v) => setFilters((x) => ({ ...x, [k]: v ?? "" }));
  const styles = [...new Set(rows.map((r) => r.work_style_no).filter(Boolean))]
    .sort()
    .map((s) => ({ value: s, label: s }));
  const shown = rows.filter(
    (r) =>
      (!f.category || r.category_code === f.category) &&
      (!f.party || r.party_id === f.party) &&
      (!f.buyer || r.buyer_id === f.buyer) &&
      (!f.style || r.work_style_no === f.style) &&
      (!f.q || r.program_no.toLowerCase().includes(f.q.toLowerCase())) &&
      (!f.status || r.status === f.status) &&
      (!f.from || r.po_date >= f.from) &&
      (!f.to || r.po_date <= f.to),
  );

  function refresh() {
    startTransition(async () => {
      const res = await act(`sc/${kind}`);
      if (res?.status !== "success") return setError(messagesOf(res));
      setRows(res.data);
      setError(null);
    });
  }
  function run() {
    const { row, action } = ask;
    startTransition(async () => {
      const path = `sc/${kind}/${encodeURIComponent(row.program_no)}`;
      const res =
        action === "delete"
          ? await act(path, "DELETE")
          : await act(`${path}/${action}`, "POST");
      setAsk(null);
      if (res?.status !== "success") return setError(messagesOf(res));
      setRows((all) =>
        action === "delete"
          ? all.filter((x) => x.program_no !== row.program_no)
          : all.map((x) =>
              x.program_no === row.program_no
                ? { ...x, status: res.data.status }
                : x,
            ),
      );
      setError(null);
      toastManager.add({
        title: c(`done.${action}`, { no: row.program_no }),
        type: "success",
      });
    });
  }
  function print(r, challan) {
    startTransition(async () => {
      const res = await act(`sc/${kind}/${encodeURIComponent(r.program_no)}`);
      if (res?.status !== "success") return setError(messagesOf(res));
      (challan ? printChallan : printProgram)(c, res.data);
    });
  }
  const pick = (list, ph) => [{ value: "", label: ph }, ...list];
  const columns = [
    {
      key: "program_type",
      label: c("col.type"),
      text: (r) => c(`type.${r.program_type}`),
    },
    {
      key: "program_no",
      label: c("col.program"),
      nowrap: true,
      render: (r) => (
        <Link
          href={`${base}?tab=entry&ref=${encodeURIComponent(r.program_no)}`}
          className="font-medium text-brand hover:underline"
        >
          {r.program_no}
        </Link>
      ),
    },
    { key: "party", label: c("col.supplier") },
    {
      key: "po_date",
      label: c("col.po_date"),
      nowrap: true,
      text: (r) => dayFmt(r.po_date),
    },
    {
      key: "transferred",
      label: c("col.transferred"),
      text: (r) => (r.transferred ? c("yes") : c("no")),
    },
    {
      key: "workflow",
      label: c("col.workflow"),
      text: (r) =>
        ["DRAFT", "REJECTED", "SUBMITTED"].includes(r.status)
          ? c(`workflow.${r.status}`)
          : "—",
    },
    {
      key: "qty",
      label: c("col.qty"),
      num: true,
      text: (r) => qtyFmt.format(r.qty),
    },
    { key: "buyer", label: c("col.buyer") },
    { key: "work_style_no", label: c("col.work_style") },
    {
      key: "status",
      label: c("col.status"),
      text: (r) => c(`status.${r.status}`),
      render: (r) => (
        <StatusChip status={TONE[r.status]} label={c(`status.${r.status}`)} />
      ),
    },
  ];
  const btn = (r, action, Icon, danger = false) => (
    <Button
      variant="outline"
      size="sm"
      className={danger ? "text-danger" : undefined}
      onClick={() => setAsk({ row: r, action })}
      disabled={pending}
      aria-label={`${c(`btn.${action}`)} ${r.program_no}`}
    >
      <Icon /> {action === "delete" ? null : c(`btn.${action}`)}
    </Button>
  );

  return (
    <div className="flex flex-col gap-4">
      <section className={card}>
        <div className="grid gap-4 p-5 sm:grid-cols-2 lg:grid-cols-6">
          <Field label={c("f.category")}>
            <PSelect
              value={f.category}
              onValueChange={(v) => setF("category", v)}
              options={pick(options.categories, c("ph.category"))}
              aria-label={c("f.category")}
            />
          </Field>
          <Field label={c("f.party")}>
            <PSelect
              value={f.party}
              onValueChange={(v) => setF("party", v)}
              options={pick(options.parties, c("ph.party"))}
              aria-label={c("f.party")}
            />
          </Field>
          <Field label={c("f.buyer")}>
            <PSelect
              value={f.buyer}
              onValueChange={(v) => setF("buyer", v)}
              options={pick(options.buyers, c("ph.buyer"))}
              aria-label={c("f.buyer")}
            />
          </Field>
          <Field label={c("f.work_style")}>
            <PSelect
              value={f.style}
              onValueChange={(v) => setF("style", v)}
              options={pick(styles, c("ph.select"))}
              aria-label={c("f.work_style")}
            />
          </Field>
          <Field label={c("f.program")}>
            <Input
              value={f.q}
              onChange={(e) => setF("q", e.target.value)}
              placeholder={c("ph.program")}
              aria-label={c("f.program")}
            />
          </Field>
          <Field label={c("f.status")}>
            <PSelect
              value={f.status}
              onValueChange={(v) => setF("status", v)}
              options={pick(
                options.statuses.map((s) => ({
                  value: s,
                  label: c(`status.${s}`),
                })),
                c("ph.status"),
              )}
              aria-label={c("f.status")}
            />
          </Field>
          <Field label={c("f.po_date")} className="lg:col-span-2">
            <div className="flex items-center gap-2">
              <Input
                type="date"
                value={f.from}
                onChange={(e) => setF("from", e.target.value)}
                aria-label={c("f.from")}
              />
              <span className="text-sm text-foreground-muted">
                {c("f.to_word")}
              </span>
              <Input
                type="date"
                value={f.to}
                onChange={(e) => setF("to", e.target.value)}
                aria-label={c("f.to")}
              />
            </div>
          </Field>
          <div className="flex items-end justify-end gap-3 text-[13px] text-foreground-muted lg:col-span-4">
            {c("shown", { shown: shown.length, total: rows.length })}
            <Button
              variant="outline"
              size="sm"
              onClick={() => setFilters(blank)}
            >
              <FilterX /> {c("btn.clear")}
            </Button>
            {canManage ? (
              <Link
                href={`${base}?tab=entry`}
                className={buttonVariants({ size: "sm" })}
              >
                <Plus /> {c("btn.add_new")}
              </Link>
            ) : null}
          </div>
        </div>
      </section>
      {error ? <Alert variant="danger">{error.join(" · ")}</Alert> : null}
      <section className={card}>
        <p className="px-5 pt-4 text-[13px] text-foreground-muted">
          {c(`hint.search.${kind}`)}
        </p>
        <ScTable
          rows={shown}
          columns={columns}
          exportName={`sc-programmes-${kind.toLowerCase()}`}
          addHref={canManage ? `${base}?tab=entry` : null}
          empty={
            <NoLines
              title={c("empty.register")}
              hint={c("empty.register_hint")}
            />
          }
          onRefresh={refresh}
          pending={pending}
          actions={(r) => (
            <>
              <Button
                variant="outline"
                size="sm"
                onClick={() => print(r, false)}
                disabled={pending}
              >
                <Printer /> {c("btn.program")}
              </Button>
              <Button
                variant="outline"
                size="sm"
                onClick={() => print(r, true)}
                disabled={pending}
              >
                <Printer /> {c("btn.challan")}
              </Button>
              {canManage && ["DRAFT", "REJECTED"].includes(r.status)
                ? btn(r, "submit", Check)
                : null}
              {canManage && r.status === "SUBMITTED" ? (
                <>
                  {btn(r, "approve", CheckCheck)}
                  {btn(r, "reject", XCircle, true)}
                </>
              ) : null}
              {canManage && r.status === "DRAFT"
                ? btn(r, "delete", Trash2, true)
                : null}
            </>
          )}
        />
      </section>
      <ConfirmDialog
        open={Boolean(ask)}
        onOpenChange={(o) => (o ? null : setAsk(null))}
        title={ask ? c(`ask.${ask.action}.title`) : ""}
        description={ask ? c(`ask.${ask.action}.body`) : ""}
        confirmLabel={ask ? c(`btn.${ask.action}`) : ""}
        cancelLabel={c("ask.no")}
        destructive={ask ? ["reject", "delete"].includes(ask.action) : false}
        loading={pending}
        onConfirm={run}
      />
    </div>
  );
}

const LINE = [
  "fabric",
  "colour_id",
  "colour_group",
  "machine_dia",
  "gauge",
  "finish_dia",
  "gsm",
  "qty",
  "rate",
  "uom",
  "remarks",
];
const HEAD = [
  "po_date",
  "target_date",
  "program_type",
  "party_id",
  "category_code",
  "buyer_id",
  "style_ref",
  "order_id",
  "work_style_no",
  "remarks",
];
let seq = 0;
const asLine = (l = {}) => ({
  key: `s${++seq}`,
  ...Object.fromEntries(LINE.map((k) => [k, str(l[k])])),
});

function ScEntry({ kind, options, initial, canManage, act }) {
  const c = useSc();
  const toastManager = useToastManager();
  const base = `/knit-production/${SLUG[kind]}`;
  const [today] = useState(todayIso);
  const asHead = (d) => ({
    ...Object.fromEntries(HEAD.map((k) => [k, str(d?.[k])])),
    po_date: str(d?.po_date) || today,
  });
  const [doc, setDoc] = useState(initial);
  const [head, setHead] = useState(() => asHead(initial));
  const [lines, setLines] = useState(() => (initial?.lines ?? []).map(asLine));
  const [draft, setDraft] = useState(() => asLine());
  const [errors, setErrors] = useState(null);
  const [pending, startTransition] = useTransition();
  const editable =
    canManage && (!doc || ["DRAFT", "REJECTED"].includes(doc.status));
  const setH = (patch) => setHead((h) => ({ ...h, ...patch }));
  const setD = (patch) => setDraft((d) => ({ ...d, ...patch }));
  const setL = (k, patch) =>
    setLines((all) => all.map((l) => (l.key === k ? { ...l, ...patch } : l)));
  const pick = (list, ph) => [{ value: "", label: ph }, ...list];
  const groups = options.colour_groups.map((g) => ({ value: g, label: g }));
  const byBuyer = options.orders.filter(
    (o) => !head.buyer_id || o.buyer_id === head.buyer_id,
  );
  const styles = [...new Set(byBuyer.map((o) => o.style).filter(Boolean))]
    .sort()
    .map((s) => ({ value: s, label: s }));
  const orders = byBuyer.filter(
    (o) => !head.style_ref || o.style === head.style_ref,
  );
  const existing = options.programs.filter((x) => x.kind === kind);
  const total = lines.reduce((a, l) => a + num(l.qty), 0);

  function show(d) {
    setDoc(d);
    setHead(asHead(d));
    setLines((d?.lines ?? []).map(asLine));
    setDraft(asLine());
    setErrors(null);
    window.history.replaceState(
      null,
      "",
      d ? `?tab=entry&ref=${encodeURIComponent(d.program_no)}` : "?tab=entry",
    );
  }
  function open(no) {
    if (!no) return show(null);
    startTransition(async () => {
      const res = await act(`sc/${kind}/${encodeURIComponent(no)}`);
      if (res?.status !== "success") return setErrors(messagesOf(res));
      show(res.data);
    });
  }
  function addLine() {
    const miss = [
      !draft.fabric.trim() && c("line.need_fabric"),
      !(num(draft.qty) > 0) && c("line.need_qty"),
      !draft.uom && c("line.need_uom"),
    ].filter(Boolean);
    if (miss.length) return setErrors(miss);
    setLines((all) => [...all, draft]);
    setDraft(asLine({ uom: draft.uom, colour_group: draft.colour_group }));
    setErrors(null);
  }
  function save() {
    startTransition(async () => {
      const body = {
        ...Object.fromEntries(
          HEAD.map((k) => [k, head[k] === "" ? null : head[k]]),
        ),
        lines: lines.map((l) => ({
          ...Object.fromEntries(
            LINE.map((k) => [k, l[k] === "" ? null : l[k]]),
          ),
          qty: orNull(l.qty),
          rate: orNull(l.rate),
        })),
      };
      const res = await act(
        doc ? `sc/${kind}/${encodeURIComponent(doc.program_no)}` : `sc/${kind}`,
        doc ? "PUT" : "POST",
        body,
      );
      if (res?.status !== "success") return setErrors(messagesOf(res));
      show(res.data);
      toastManager.add({
        title: c("done.saved", { no: res.data.program_no }),
        type: "success",
      });
    });
  }
  const cell = (l, i, k, label, props = {}) => (
    <Input
      value={l[k]}
      onChange={(e) => setL(l.key, { [k]: e.target.value })}
      disabled={!editable}
      className="h-8"
      aria-label={`${label} ${i + 1}`}
      {...props}
    />
  );
  const sel = (l, i, k, label, list, ph) => (
    <PSelect
      value={l[k]}
      onValueChange={(v) => setL(l.key, { [k]: v ?? "" })}
      options={pick(list, ph)}
      disabled={!editable}
      aria-label={`${label} ${i + 1}`}
    />
  );

  return (
    <div className="flex flex-col gap-4">
      <section className={cn(card, "p-5")}>
        <div className="flex items-start justify-between gap-3">
          <div>
            <h2 className="text-base font-semibold text-foreground">
              {doc ? doc.program_no : c(`tabs.entry.${kind}`)}
            </h2>
            <p className="mt-0.5 text-[13px] text-foreground-muted">
              {c(`hint.form.${kind}`)}
            </p>
          </div>
          {doc ? (
            <StatusChip
              status={TONE[doc.status]}
              label={c(`status.${doc.status}`)}
            />
          ) : null}
        </div>
        {doc && !editable && canManage ? (
          <Alert variant="info" className="mt-3">
            {c("hint.fixed", {
              no: doc.program_no,
              status: c(`status.${doc.status}`),
            })}
          </Alert>
        ) : null}
        <h3 className="mt-5 mb-3 text-sm font-semibold text-foreground">
          {c("sec.programme")}
        </h3>
        <div className="grid gap-4 md:grid-cols-3">
          <Field label={c("f.existing")}>
            <PSelect
              value={doc?.program_no ?? ""}
              onValueChange={(v) => open(v ?? "")}
              options={pick(existing, c("ph.new"))}
              disabled={pending}
              aria-label={c("f.existing")}
            />
          </Field>
          <Field label={c("f.po_date")} required>
            <Input
              type="date"
              value={head.po_date}
              onChange={(e) => setH({ po_date: e.target.value })}
              disabled={!editable}
              aria-label={c("f.po_date")}
            />
          </Field>
          <Field label={c("f.target_date")}>
            <Input
              type="date"
              value={head.target_date}
              onChange={(e) => setH({ target_date: e.target.value })}
              disabled={!editable}
              aria-label={c("f.target_date")}
            />
          </Field>
          <Field label={c("f.type")} required>
            <PSelect
              value={head.program_type}
              onValueChange={(v) => setH({ program_type: v ?? "" })}
              options={pick(
                options.types[kind].map((t) => ({
                  value: t,
                  label: c(`type.${t}`),
                })),
                c("ph.choose"),
              )}
              disabled={!editable}
              aria-label={c("f.type")}
            />
          </Field>
          <Field label={c("f.company")} required>
            <PSelect
              value={options.company.value}
              options={[options.company]}
              disabled
              aria-label={c("f.company")}
            />
          </Field>
        </div>
        <h3 className="mt-5 mb-3 text-sm font-semibold text-foreground">
          {c("sec.party")}
        </h3>
        <div className="grid gap-4 md:grid-cols-3">
          <Field label={c("f.party")} required>
            <PSelect
              value={head.party_id}
              onValueChange={(v) => setH({ party_id: v ?? "" })}
              options={pick(options.parties, c("ph.party"))}
              disabled={!editable}
              aria-label={c("f.party")}
            />
          </Field>
          <Field label={c("f.category")}>
            <PSelect
              value={head.category_code}
              onValueChange={(v) => setH({ category_code: v ?? "" })}
              options={pick(options.categories, c("ph.category"))}
              disabled={!editable}
              aria-label={c("f.category")}
            />
          </Field>
        </div>
        <h3 className="mt-5 mb-3 text-sm font-semibold text-foreground">
          {c("sec.booked")}
        </h3>
        <div className="grid gap-4 md:grid-cols-3">
          <Field label={c("f.buyer")}>
            <PSelect
              value={head.buyer_id}
              onValueChange={(v) =>
                setH({ buyer_id: v ?? "", style_ref: "", order_id: "" })
              }
              options={pick(options.buyers, c("ph.buyer"))}
              disabled={!editable}
              aria-label={c("f.buyer")}
            />
          </Field>
          <Field label={c("f.style")}>
            <PSelect
              value={head.style_ref}
              onValueChange={(v) => setH({ style_ref: v ?? "", order_id: "" })}
              options={pick(styles, c("ph.style"))}
              disabled={!editable}
              aria-label={c("f.style")}
            />
          </Field>
          <Field label={c("f.order")}>
            <PSelect
              value={head.order_id}
              onValueChange={(v) => {
                const o = options.orders.find((x) => x.value === v);
                setH(
                  o
                    ? {
                        order_id: v,
                        buyer_id: o.buyer_id ?? head.buyer_id,
                        style_ref: o.style ?? head.style_ref,
                        work_style_no: o.style ?? head.work_style_no,
                      }
                    : { order_id: "" },
                );
              }}
              options={pick(orders, c("ph.select"))}
              disabled={!editable}
              aria-label={c("f.order")}
            />
          </Field>
          <Field label={c("f.work_style")} hint={c("hint.work_style")}>
            <Input
              value={head.work_style_no}
              onChange={(e) => setH({ work_style_no: e.target.value })}
              maxLength={64}
              disabled={!editable || Boolean(head.order_id)}
              aria-label={c("f.work_style")}
            />
          </Field>
        </div>
        <Field label={c("f.remarks")} className="mt-4">
          <Textarea
            rows={3}
            value={head.remarks}
            onChange={(e) => setH({ remarks: e.target.value })}
            maxLength={2000}
            disabled={!editable}
            aria-label={c("f.remarks")}
          />
        </Field>
      </section>
      <section className={cn(card, "p-5")}>
        <div className="flex flex-wrap items-start justify-between gap-3">
          <div>
            <h3 className="text-base font-semibold text-foreground">
              {c("sec.fabrics")}
            </h3>
            <p className="mt-0.5 text-[13px] text-foreground-muted">
              {c("hint.lines")}
            </p>
          </div>
          {editable ? (
            <div className="flex gap-2">
              <Button variant="outline" size="sm" onClick={addLine}>
                <Plus /> {c("btn.add_fabric")}
              </Button>
              <Button
                variant="outline"
                size="sm"
                onClick={() => setDraft(asLine())}
              >
                {c("btn.cancel_line")}
              </Button>
            </div>
          ) : null}
        </div>
        {editable ? (
          <div className="mt-4 grid gap-4 md:grid-cols-3">
            <Field label={c("f.fabric")}>
              <Input
                value={draft.fabric}
                onChange={(e) => setD({ fabric: e.target.value })}
                maxLength={160}
                aria-label={c("f.fabric")}
              />
            </Field>
            <Field label={c("f.colour")}>
              <PSelect
                value={draft.colour_id}
                onValueChange={(v) => setD({ colour_id: v ?? "" })}
                options={pick(options.colours, c("ph.colour"))}
                aria-label={c("f.colour")}
              />
            </Field>
            <Field label={c("f.colour_group")}>
              <PSelect
                value={draft.colour_group}
                onValueChange={(v) => setD({ colour_group: v ?? "" })}
                options={pick(groups, c("ph.group"))}
                aria-label={c("f.colour_group")}
              />
            </Field>
            {["machine_dia", "gauge", "finish_dia", "gsm"].map((k) => (
              <Field
                key={k}
                label={c(`f.${k === "machine_dia" ? "mc_dia" : k}`)}
              >
                <Input
                  value={draft[k]}
                  onChange={(e) => setD({ [k]: e.target.value })}
                  maxLength={24}
                  aria-label={c(`f.${k === "machine_dia" ? "mc_dia" : k}`)}
                />
              </Field>
            ))}
            <Field label={c("f.qty_kg")}>
              <Input
                type="number"
                min="0"
                step="any"
                value={draft.qty}
                onChange={(e) => setD({ qty: e.target.value })}
                aria-label={c("f.qty_kg")}
              />
            </Field>
            <Field label={c("f.rate")}>
              <Input
                type="number"
                min="0"
                step="any"
                value={draft.rate}
                onChange={(e) => setD({ rate: e.target.value })}
                aria-label={c("f.rate")}
              />
            </Field>
            <Field label={c("f.uom")}>
              <PSelect
                value={draft.uom}
                onValueChange={(v) => setD({ uom: v ?? "" })}
                options={pick(options.uoms, c("ph.choose"))}
                aria-label={c("f.uom")}
              />
            </Field>
            <Field label={c("f.remarks")}>
              <Input
                value={draft.remarks}
                onChange={(e) => setD({ remarks: e.target.value })}
                maxLength={300}
                aria-label={`${c("f.remarks")} (${c("f.fabric")})`}
              />
            </Field>
          </div>
        ) : null}
        <div className="mt-5 overflow-x-auto rounded-lg border border-border">
          {lines.length === 0 ? (
            <NoLines title={c("empty.lines")} hint={c("empty.lines_hint")} />
          ) : (
            <table className="w-full min-w-[1400px] text-sm">
              <thead className="bg-brand text-brand-foreground">
                <tr>
                  {[
                    "fabric",
                    "colour",
                    "colour_group",
                    "mc_dia",
                    "gauge",
                    "fin_dia",
                    "gsm",
                    "qty_kg",
                    "rate",
                    "uom",
                    "remarks",
                  ].map((k) => (
                    <th
                      key={k}
                      className={cn(
                        th,
                        ["qty_kg", "rate"].includes(k) && "text-right",
                      )}
                    >
                      {c(`col.${k}`)}
                    </th>
                  ))}
                  <th className={th} />
                </tr>
              </thead>
              <tbody>
                {lines.map((l, i) => (
                  <tr key={l.key} className="border-t border-border">
                    <td className="min-w-48 px-2 py-1.5">
                      {cell(l, i, "fabric", c("f.fabric"), { maxLength: 160 })}
                    </td>
                    <td className="min-w-40 px-2 py-1.5">
                      {sel(
                        l,
                        i,
                        "colour_id",
                        c("f.colour"),
                        options.colours,
                        c("ph.colour"),
                      )}
                    </td>
                    <td className="min-w-36 px-2 py-1.5">
                      {sel(
                        l,
                        i,
                        "colour_group",
                        c("f.colour_group"),
                        groups,
                        c("ph.group"),
                      )}
                    </td>
                    {["machine_dia", "gauge", "finish_dia", "gsm"].map((k) => (
                      <td key={k} className="w-24 px-2 py-1.5">
                        {cell(
                          l,
                          i,
                          k,
                          c(`f.${k === "machine_dia" ? "mc_dia" : k}`),
                          { maxLength: 24 },
                        )}
                      </td>
                    ))}
                    <td className="w-28 px-2 py-1.5">
                      {cell(l, i, "qty", c("f.qty_kg"), {
                        type: "number",
                        min: "0",
                        step: "any",
                        className: "h-8 text-right",
                      })}
                    </td>
                    <td className="w-24 px-2 py-1.5">
                      {cell(l, i, "rate", c("f.rate"), {
                        type: "number",
                        min: "0",
                        step: "any",
                        className: "h-8 text-right",
                      })}
                    </td>
                    <td className="w-28 px-2 py-1.5">
                      {sel(
                        l,
                        i,
                        "uom",
                        c("f.uom"),
                        options.uoms,
                        c("ph.choose"),
                      )}
                    </td>
                    <td className="min-w-40 px-2 py-1.5">
                      {cell(l, i, "remarks", c("f.remarks"), {
                        maxLength: 300,
                      })}
                    </td>
                    <td className="px-2 py-1.5">
                      {editable ? (
                        <Button
                          variant="ghost"
                          size="sm"
                          className="text-danger"
                          onClick={() =>
                            setLines((all) =>
                              all.filter((x) => x.key !== l.key),
                            )
                          }
                          aria-label={`${c("btn.remove")} ${i + 1}`}
                        >
                          <Trash2 /> {c("btn.remove")}
                        </Button>
                      ) : null}
                    </td>
                  </tr>
                ))}
              </tbody>
              <tfoot>
                <tr className="border-t border-border font-semibold">
                  <td className="px-2 py-2" colSpan={7}>
                    {c("p.total")}
                  </td>
                  <td className="px-2 py-2 text-right tabular-nums">
                    {qtyFmt.format(total)}
                  </td>
                  <td colSpan={4} />
                </tr>
              </tfoot>
            </table>
          )}
        </div>
      </section>
      {errors ? <Alert variant="danger">{errors.join(" · ")}</Alert> : null}
      <section
        className={cn(
          card,
          "flex flex-wrap items-center justify-between gap-3 p-4",
        )}
      >
        <p className="max-w-2xl text-[13px] text-foreground-muted">
          {c("hint.foot")}
        </p>
        <div className="flex flex-wrap gap-2">
          <Link href={base} className={buttonVariants({ variant: "outline" })}>
            <ArrowLeft /> {c("btn.back")}
          </Link>
          <Button
            variant="outline"
            onClick={() => show(null)}
            disabled={pending}
          >
            <Eraser /> {c("btn.clear")}
          </Button>
          {editable ? (
            <Button onClick={save} loading={pending}>
              <Save /> {c("btn.save")}
            </Button>
          ) : null}
        </div>
      </section>
    </div>
  );
}

export { useSc, ScTable, ScRegister, ScEntry, SLUG as SC_SLUG };
