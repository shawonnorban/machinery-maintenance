"use client";

import { useMemo, useRef, useState, useTransition } from "react";
import Link from "next/link";
import { ArrowRight, Barcode, CircleCheck, ChevronDown, ChevronUp, RotateCcw, ScanLine, Trash2, X } from "lucide-react";
import { cn } from "@/lib/utils";
import { Input } from "@/components/ui/input";
import { Button, buttonVariants } from "@/components/ui/button";
import { ConfirmDialog } from "@/components/ui/confirm-dialog";
import { useToastManager } from "@/components/ui/toast";
import { RecordsGrid } from "@/components/erp/records-grid";
import { useT } from "@/lib/i18n";

/*
 * Assembly Floor → Sewing (AnnotechERP 20/01), as Brickland lays its three
 * screens out: the Dashboard / Bundle IN / Bundle OUT buttons on top; Bundle
 * IN's four steps (location, scan, the line's plan, confirm), its chips, the
 * Recently Scanned Bundles grid with tick boxes, Take Action and Clear day,
 * and Line-wise Bundle IN; Bundle OUT's Full Bundle / Short Output scan with
 * Sewing WIP by Line; and the Sewing Dashboard board.
 */

const firstError = (res) => (res?.errors ? Object.values(res.errors).flat()[0] : res?.message);
const dmy = (d) => (d ? d.split("-").reverse().join("-") : "—");
const num = (v) => (v === null || v === undefined ? "—" : Number(v).toLocaleString("en-US"));
const dayLabel = (d) => new Date(`${d}T00:00:00`).toLocaleDateString("en-GB", { day: "2-digit", month: "short", year: "numeric" });
const nativeSelect = "h-10 w-full rounded-md border border-border bg-surface px-3 text-sm outline-none focus:border-brand disabled:opacity-60";

function useWords() {
  const t = useT("assembly");
  return (k, p) => t(k, p);
}

/** The Dashboard / Bundle IN / Bundle OUT buttons over each sewing screen. */
function SewingNav({ active }) {
  const w = useWords();
  const items = [
    ["dashboard", "/assembly-floor/sewing-dashboard", "nav.dashboard"],
    ["in", "/assembly-floor/bundle-in", "nav.bundle_in"],
    ["out", "/assembly-floor/bundle-out", "nav.bundle_out"],
  ];
  return (
    <nav className="mb-4 grid gap-3 sm:grid-cols-3">
      {items.map(([key, href, label]) => (
        <Link
          key={key}
          href={href}
          aria-current={key === active ? "page" : undefined}
          className={cn(
            "flex h-11 items-center justify-center rounded-lg border text-sm font-semibold transition-colors",
            key === active ? "border-brand bg-brand text-brand-foreground" : "border-border bg-surface text-foreground hover:bg-surface-muted",
          )}
        >
          {w(label)}
        </Link>
      ))}
    </nav>
  );
}

function Chip({ tone, children }) {
  return <span className={cn("rounded-md px-2 py-0.5 text-xs font-semibold tabular-nums", tone)}>{children}</span>;
}

function F({ label, required, hint, children, className }) {
  return (
    <label className={cn("flex flex-col gap-1.5", className)}>
      <span className="text-[13px] font-semibold">
        {label}
        {required ? <span className="ml-0.5 text-danger">*</span> : null}
      </span>
      {children}
      {hint ? <span className="text-xs text-foreground-muted">{hint}</span> : null}
    </label>
  );
}

/** A card with Brickland's title row: an icon, the title and its line, chips on the right. */
function Panel({ title, sub, chips, children, className }) {
  return (
    <section className={cn("rounded-lg border border-border bg-surface", className)}>
      <header className="flex flex-wrap items-start justify-between gap-3 px-5 pt-4">
        <div className="flex gap-2.5">
          <ScanLine className="mt-0.5 size-4 text-foreground-muted" />
          <div>
            <h2 className="text-[15px] font-semibold">{title}</h2>
            {sub ? <p className="text-[13px] text-foreground-muted">{sub}</p> : null}
          </div>
        </div>
        {chips ? <div className="flex flex-wrap gap-1.5">{chips}</div> : null}
      </header>
      {children}
    </section>
  );
}

/** A collapsible summary card (Line-wise Bundle IN, Sewing WIP by Line). */
function Collapsible({ title, children }) {
  const w = useWords();
  const [open, setOpen] = useState(false);
  return (
    <section className="mt-4 rounded-lg border border-border bg-surface">
      <header className="flex items-center justify-between px-5 py-4">
        <h2 className="text-[15px] font-semibold">{title}</h2>
        <Button variant="outline" size="sm" onClick={() => setOpen((o) => !o)} aria-expanded={open}>
          {open ? <ChevronUp /> : <ChevronDown />} {w(open ? "act.hide" : "act.show")}
        </Button>
      </header>
      {open ? <div className="border-t border-border p-4">{children}</div> : null}
    </section>
  );
}

function Tick({ checked, onChange, label, disabled }) {
  return <input type="checkbox" className="size-4 accent-[var(--color-brand)]" checked={checked} onChange={(e) => onChange(e.target.checked)} aria-label={label} disabled={disabled} />;
}

/** Ticked rows, Take Action (withdraw them) and Clear day (withdraw every scan of the day that still may be). */
function useWithdraw({ rows, withdraw, reload, w, kind }) {
  const toastManager = useToastManager();
  const [ticked, setTicked] = useState(() => new Set());
  const [asking, setAsking] = useState(null);
  const [pending, startTransition] = useTransition();
  const open = rows.filter((r) => r.can_withdraw).map((r) => r.bundle_no);
  const toggle = (no, on) =>
    setTicked((s) => {
      const n = new Set(s);
      if (on) n.add(no);
      else n.delete(no);
      return n;
    });
  const run = (nos) =>
    startTransition(async () => {
      const res = await withdraw(nos);
      setAsking(null);
      if (res?.status !== "success") return toastManager.add({ title: firstError(res), type: "danger" });
      setTicked(new Set());
      toastManager.add({ title: w("toast.withdrawn", { n: res.data.withdrawn }), type: "success" });
      reload();
    });
  const take = () => {
    const nos = [...ticked].filter((n) => rows.some((r) => r.bundle_no === n));
    if (!nos.length) return toastManager.add({ title: w("toast.tick"), type: "warning" });
    setAsking({ nos, clear: false });
  };
  const dialog = (date) => (
    <ConfirmDialog
      open={asking !== null}
      onOpenChange={(o) => !o && setAsking(null)}
      title={asking ? (asking.clear ? w("confirm.clear_title") : w("confirm.withdraw_title", { n: asking.nos.length })) : ""}
      description={asking ? (asking.clear ? w("confirm.clear_text", { date: dmy(date), n: asking.nos.length }) : w(`confirm.withdraw_${kind}`)) : ""}
      confirmLabel={w("act.withdraw")}
      cancelLabel={w("act.cancel")}
      destructive
      loading={pending}
      onConfirm={() => run(asking.nos)}
    />
  );
  return { ticked, toggle, take, clearDay: () => open.length && setAsking({ nos: open, clear: true }), canClear: open.length > 0, ask: (no) => setAsking({ nos: [no], clear: false }), pending, dialog, reset: () => setTicked(new Set()) };
}

const selectColumn = (wd, w) => ({
  key: "_select",
  label: w("col.select"),
  sortable: false,
  read: () => "",
  render: (r) => <Tick checked={wd.ticked.has(r.bundle_no)} onChange={(on) => wd.toggle(r.bundle_no, on)} label={`${w("col.select")} ${r.bundle_no}`} disabled={!r.can_withdraw} />,
});

const actionColumn = (wd, w, canManage) => ({
  key: "_action",
  label: w("col.action"),
  sortable: false,
  read: () => "",
  render: (r) =>
    canManage && r.can_withdraw ? (
      <Button variant="ghost" size="sm" className="text-danger" onClick={() => wd.ask(r.bundle_no)} aria-label={`${w("act.withdraw")} ${r.bundle_no}`}>
        <RotateCcw /> {w("act.withdraw")}
      </Button>
    ) : null,
});

const BSTATUS_TONE = { IN_SEWING: "bg-info-subtle text-info", QC_PENDING: "bg-warning/15 text-warning", IN_FINISHING: "bg-success/15 text-success" };

/**
 * Bundle IN: Section > Floor > Line, a card scanned (or typed) with the
 * pieces short in the bag, what it resolves to and the line's plan shown
 * before anything is saved, Confirm Bundle IN; the day's receipts below.
 */
function BundleInDesk({ options, initial, canManage, act }) {
  const w = useWords();
  const toastManager = useToastManager();
  const scanRef = useRef(null);
  const [date, setDate] = useState(options.today);
  const [section, setSection] = useState("Sewing");
  const [floor, setFloor] = useState("");
  const [line, setLine] = useState("");
  const [code, setCode] = useState("");
  const [short, setShort] = useState("");
  const [preview, setPreview] = useState(null);
  const [day, setDay] = useState(initial);
  const [pending, startTransition] = useTransition();
  const lines = useMemo(() => options.lines.filter((l) => l.floor_id === floor), [options.lines, floor]);
  const located = Boolean(floor && line);
  const lineLabel = options.lines.find((l) => l.value === line)?.label;
  const floorLabel = options.floors.find((f) => f.value === floor)?.label;

  const reload = (d = date) =>
    startTransition(async () => {
      const res = await act.listIns(d);
      if (res?.status === "success") setDay(res.data);
    });
  const wd = useWithdraw({ rows: day.rows, withdraw: act.withdrawIns, reload: () => reload(), w, kind: "in" });

  const clear = () => {
    setCode("");
    setShort("");
    setPreview(null);
    scanRef.current?.focus();
  };
  const scan = () => {
    if (!located || !code.trim()) return;
    startTransition(async () => {
      const res = await act.resolve({ code: code.trim(), date, line_id: line });
      if (res?.status !== "success") {
        setPreview(null);
        return toastManager.add({ title: firstError(res), type: "danger" });
      }
      setPreview(res.data);
    });
  };
  const confirm = () => {
    if (!preview) return;
    startTransition(async () => {
      const res = await act.receive({ code: preview.bundle.bundle_no, receive_date: date, section, floor_id: floor, line_id: line, short_qty: short === "" ? 0 : Number(short) });
      if (res?.status !== "success") return toastManager.add({ title: firstError(res), type: "danger" });
      toastManager.add({ title: w("toast.received", { no: res.data.bundle_no, line: res.data.line }), type: "success" });
      clear();
      reload();
    });
  };
  const changeDate = (d) => {
    setDate(d);
    setPreview(null);
    wd.reset();
    reload(d);
  };

  const step = !located ? 1 : !preview ? 2 : 4;
  const shortN = short === "" ? 0 : Number(short);
  const hand = preview ? preview.bundle.qty - shortN : null;

  return (
    <>
      <ol className="mb-4 grid gap-2 rounded-lg border border-border bg-surface p-4 sm:grid-cols-2 lg:grid-cols-4">
        {[1, 2, 3, 4].map((n) => {
          const done = n < step || (n === 3 && preview);
          const current = n === step;
          return (
            <li key={n} className="flex items-center gap-3">
              <span className={cn("grid size-8 shrink-0 place-items-center rounded-full text-sm font-semibold", current ? "bg-brand text-brand-foreground" : done ? "bg-success text-white" : "bg-surface-muted text-foreground-muted")}>{n}</span>
              <span>
                <span className="block text-sm font-semibold">{w(`steps.${n}.title`)}</span>
                <span className="block text-xs text-foreground-muted">{w(`steps.${n}.sub`)}</span>
              </span>
            </li>
          );
        })}
      </ol>

      <Panel
        title={w("in.title")}
        sub={w("in.sub")}
        chips={
          <>
            <Chip tone="bg-info-subtle text-info">
              {w("chip.bundle_in")} {num(day.bundles)}
            </Chip>
            <Chip tone="bg-success/15 text-success">
              {w("chip.in_qty")} {num(day.qty)}
            </Chip>
            <Chip tone="bg-danger/15 text-danger">
              {w("chip.short")} {num(day.short)}
            </Chip>
          </>
        }
      >
        <div className="grid gap-4 px-5 pt-4 sm:grid-cols-2 lg:grid-cols-4">
          <F label={w("in.receive_date")}>
            <select className={nativeSelect} value={date} onChange={(e) => changeDate(e.target.value)} aria-label={w("in.receive_date")}>
              {options.days.map((d) => (
                <option key={d} value={d}>
                  {dayLabel(d)}
                </option>
              ))}
            </select>
          </F>
          <F label={w("in.section")}>
            <Input value={section} onChange={(e) => setSection(e.target.value)} maxLength={40} />
          </F>
          <F label={w("in.floor")} required>
            <select
              className={nativeSelect}
              value={floor}
              onChange={(e) => {
                setFloor(e.target.value);
                setLine("");
                setPreview(null);
              }}
              aria-label={w("in.floor")}
            >
              <option value="">{w("in.select_floor")}</option>
              {options.floors.map((f) => (
                <option key={f.value} value={f.value}>
                  {f.label}
                </option>
              ))}
            </select>
          </F>
          <F label={w("in.line")} required hint={floor && !lines.length ? w("in.no_lines") : !floor ? w("in.no_lines") : null}>
            <select
              className={nativeSelect}
              value={line}
              onChange={(e) => {
                setLine(e.target.value);
                setPreview(null);
              }}
              disabled={!floor}
              aria-label={w("in.line")}
            >
              <option value="">{w("in.select_line")}</option>
              {lines.map((l) => (
                <option key={l.value} value={l.value}>
                  {l.label}
                </option>
              ))}
            </select>
          </F>
        </div>

        <div className={cn("mx-5 mt-4 flex items-center gap-3 rounded-lg border px-4 py-3", located ? "border-success/40 bg-success/10" : "border-border bg-surface-muted")}>
          <CircleCheck className={cn("size-5", located ? "text-success" : "text-foreground-muted")} />
          <div>
            <p className="text-sm font-semibold">{w(located ? "in.loc_ready" : "in.loc_title")}</p>
            <p className="text-xs text-foreground-muted">{located ? w("in.loc_ready_hint", { line: lineLabel, floor: floorLabel }) : w("in.loc_hint")}</p>
          </div>
        </div>

        <div className="grid gap-4 p-5 lg:grid-cols-[minmax(0,26rem)_1fr]">
          <div className="rounded-lg border border-brand/40 bg-brand-subtle/40 p-4">
            <div className="flex flex-col items-center gap-1.5 pb-3">
              <Barcode className="size-8 text-brand" />
              <p className="text-sm font-semibold">
                {w("in.scan_label")} <span className="text-danger">*</span>
              </p>
            </div>
            <div className="rounded-md border border-border bg-surface p-3 text-center">
              <Input
                ref={scanRef}
                value={code}
                onChange={(e) => setCode(e.target.value)}
                onKeyDown={(e) => e.key === "Enter" && (e.preventDefault(), scan())}
                placeholder={w("in.scan_here")}
                aria-label={w("in.scan_label")}
                className="h-11 text-center font-mono text-base tracking-wider"
                disabled={!located}
                autoComplete="off"
              />
              <p className="mt-2 text-xs text-foreground-muted">{w("in.scan_hint")}</p>
            </div>
            <F label={w("in.short")} hint={w("in.short_hint")} className="mt-3">
              <Input type="number" min={0} value={short} onChange={(e) => setShort(e.target.value)} disabled={!located} aria-label={w("in.short")} />
            </F>
            <Button className="mt-3 w-full" onClick={scan} disabled={!located || !code.trim() || pending}>
              <ScanLine /> {w("in.scan")}
            </Button>
          </div>

          <div className="flex flex-col">
            {preview ? (
              <div className="grid flex-1 gap-3 md:grid-cols-2">
                <div className="rounded-lg border border-border p-4">
                  <p className="mb-2 text-xs font-semibold tracking-wide text-foreground-muted uppercase">{w("in.bundle")}</p>
                  <p className="font-mono text-base font-bold">{preview.bundle.bundle_no}</p>
                  <dl className="mt-2 grid grid-cols-[auto_1fr] gap-x-3 gap-y-1 text-sm">
                    {[
                      ["col.po", preview.bundle.po_no],
                      ["col.buyer", preview.bundle.buyer],
                      ["col.style", preview.bundle.style],
                      ["col.item", preview.bundle.item],
                      ["col.colour", preview.bundle.colour],
                      ["col.size", preview.bundle.size],
                      ["col.card_no", preview.bundle.card_no],
                      ["col.cutting", preview.bundle.cutting_no],
                      ["col.bundle_qty", num(preview.bundle.qty)],
                    ].map(([k, v]) => (
                      <div key={k} className="contents">
                        <dt className="text-foreground-muted">{w(k)}</dt>
                        <dd className="font-medium">{v ?? "—"}</dd>
                      </div>
                    ))}
                    <dt className="text-foreground-muted">{w("in.hand")}</dt>
                    <dd className={cn("font-bold", hand < 1 ? "text-danger" : "text-success")}>{num(hand)}</dd>
                  </dl>
                </div>
                <div className="rounded-lg border border-border p-4">
                  <p className="mb-2 text-xs font-semibold tracking-wide text-foreground-muted uppercase">{w("in.plan")}</p>
                  <p className="font-semibold">
                    {preview.line} · {preview.floor}
                  </p>
                  {preview.plan.source === "NONE" ? <p className="mt-2 text-sm text-warning">{w("in.plan_none")}</p> : null}
                  <dl className="mt-2 grid grid-cols-[auto_1fr] gap-x-3 gap-y-1 text-sm">
                    {[
                      ["col.style", preview.plan.style],
                      ["col.item", preview.plan.item],
                      ["col.smv", preview.plan.smv],
                      ["col.manpower", preview.plan.manpower],
                      ["col.daily_target", num(preview.plan.target)],
                    ].map(([k, v]) => (
                      <div key={k} className="contents">
                        <dt className="text-foreground-muted">{w(k)}</dt>
                        <dd className="font-medium">{v ?? "—"}</dd>
                      </div>
                    ))}
                  </dl>
                  <p className="mt-2 text-xs text-foreground-muted">{w(`in.plan_src.${preview.plan.source}`)}</p>
                  {preview.style_differs ? <p className="mt-2 rounded-md bg-warning/15 px-2 py-1 text-xs text-warning">{w("in.style_differs", { style: preview.plan.style, bundle: preview.bundle.style })}</p> : null}
                </div>
              </div>
            ) : (
              <div className="flex flex-1 flex-col items-center justify-center gap-2 py-10 text-center">
                <span className="grid size-12 place-items-center rounded-lg border border-border bg-surface-muted">
                  <Barcode className="size-5" />
                </span>
                <p className="font-semibold">{w("in.waiting_title")}</p>
                <p className="max-w-sm text-[13px] text-foreground-muted">{w("in.waiting_text")}</p>
              </div>
            )}
            <div className="mt-4 flex items-center justify-between gap-3">
              <Button variant="outline" className="text-danger" onClick={clear} disabled={!code && !preview && !short}>
                <X /> {w("in.clear")}
              </Button>
              {canManage ? (
                <Button onClick={confirm} disabled={!preview || pending || hand < 1}>
                  <CircleCheck /> {w("in.confirm")}
                </Button>
              ) : null}
            </div>
          </div>
        </div>

        <div className="border-t border-border">
          <h3 className="px-4 pt-4 pb-2 text-[15px] font-semibold">{w("in.recent")}</h3>
          <div className="px-4 pb-4">
            <RecordsGrid
              exportName={`bundle-in-${date}`}
              rows={day.rows.map((r) => ({ ...r, bundle_status_label: w(`bstatus.${r.bundle_status}`), status_label: w(`status.${r.status}`) }))}
              rowKey="bundle_no"
              emptyState={{ title: w("in.empty_title"), text: w("in.empty_text") }}
              columns={[
                ...(canManage ? [selectColumn(wd, w)] : []),
                { key: "sl", label: w("col.sl"), type: "number" },
                { key: "time", label: w("col.time") },
                { key: "bundle_no", label: w("col.bundle_no"), type: "mono" },
                { key: "po_no", label: w("col.po") },
                { key: "card_no", label: w("col.card_no") },
                { key: "cutting_no", label: w("col.cutting"), type: "number" },
                { key: "buyer", label: w("col.buyer") },
                { key: "style", label: w("col.style") },
                { key: "item", label: w("col.item") },
                { key: "colour", label: w("col.colour") },
                { key: "size", label: w("col.size") },
                { key: "bundle_qty", label: w("col.bundle_qty"), type: "number", align: "right" },
                { key: "in_qty", label: w("col.qty"), type: "number", align: "right" },
                { key: "short", label: w("col.short"), type: "number", align: "right" },
                { key: "section", label: w("col.section") },
                { key: "floor", label: w("col.floor") },
                { key: "line", label: w("col.line") },
                { key: "smv", label: w("col.smv"), type: "number", align: "right" },
                { key: "manpower", label: w("col.manpower"), type: "number", align: "right" },
                { key: "daily_target", label: w("col.daily_target"), type: "number", align: "right" },
                { key: "bundle_status_label", label: w("col.bundle_status"), read: (r) => r.bundle_status_label, render: (r) => <Chip tone={BSTATUS_TONE[r.bundle_status]}>{r.bundle_status_label}</Chip> },
                { key: "status_label", label: w("col.status"), type: "badge" },
                ...(canManage ? [actionColumn(wd, w, canManage)] : []),
              ]}
            />
          </div>
          <footer className="flex flex-wrap items-center justify-between gap-3 border-t border-border px-5 py-3">
            <p className="text-[13px] text-foreground-muted">{w("in.foot", { n: day.bundles, t: wd.ticked.size })}</p>
            <div className="flex flex-wrap gap-2">
              <Link href="/assembly-floor/bundle-out" className={buttonVariants({ variant: "outline", size: "sm" })}>
                {w("in.go_out")} <ArrowRight />
              </Link>
              {canManage ? (
                <>
                  <Button variant="secondary" size="sm" onClick={wd.take} disabled={wd.pending}>
                    {w("act.take_action")}
                  </Button>
                  <Button variant="outline" size="sm" onClick={wd.clearDay} disabled={!wd.canClear || wd.pending}>
                    <Trash2 /> {w("act.clear_day")}
                  </Button>
                </>
              ) : null}
            </div>
          </footer>
        </div>
      </Panel>

      <Collapsible title={w("in.line_wise")}>
        <RecordsGrid
          exportName={`line-wise-bundle-in-${date}`}
          rows={day.by_line}
          rowKey="line"
          searchable={false}
          emptyState={{ title: w("in.empty_title"), text: w("in.empty_text") }}
          columns={[
            { key: "floor", label: w("col.floor") },
            { key: "line", label: w("col.line") },
            { key: "bundles", label: w("col.bundles"), type: "number", align: "right" },
            { key: "bundle_qty", label: w("col.bundle_qty"), type: "number", align: "right" },
            { key: "short", label: w("col.short"), type: "number", align: "right" },
            { key: "qty", label: w("col.in_qty"), type: "number", align: "right" },
          ]}
        />
      </Collapsible>
      {wd.dialog(date)}
    </>
  );
}

/**
 * Bundle OUT: the day, a card scanned off its line — the whole bundle, or a
 * short output with the pieces that came off — each scan recorded on its own;
 * the day's outs below, then the sewing WIP line by line.
 */
function BundleOutDesk({ options, initial, canManage, act }) {
  const w = useWords();
  const toastManager = useToastManager();
  const scanRef = useRef(null);
  const [date, setDate] = useState(options.today);
  const [code, setCode] = useState("");
  const [mode, setMode] = useState("FULL");
  const [output, setOutput] = useState("");
  const [day, setDay] = useState(initial);
  const [pending, startTransition] = useTransition();

  const reload = (d = date) =>
    startTransition(async () => {
      const res = await act.listOuts(d);
      if (res?.status === "success") setDay(res.data);
    });
  const wd = useWithdraw({ rows: day.rows, withdraw: act.withdrawOuts, reload: () => reload(), w, kind: "out" });

  const receive = () => {
    if (!code.trim()) return;
    startTransition(async () => {
      const res = await act.sendOut({ code: code.trim(), out_date: date, mode, output_qty: mode === "SHORT" && output !== "" ? Number(output) : null });
      if (res?.status !== "success") return toastManager.add({ title: firstError(res), type: "danger" });
      toastManager.add({ title: w("toast.out", { no: res.data.bundle_no, qty: res.data.output }), type: "success" });
      setCode("");
      setOutput("");
      scanRef.current?.focus();
      reload();
    });
  };

  return (
    <>
      <Panel
        title={w("out.title")}
        sub={w("out.sub")}
        chips={
          <>
            <Chip tone="bg-info-subtle text-info">
              {w("chip.bundle_out")} {num(day.bundles)}
            </Chip>
            <Chip tone="bg-success/15 text-success">
              {w("chip.output")} {num(day.output)}
            </Chip>
            <Chip tone="bg-warning/15 text-warning">
              {w("chip.balance")} {num(day.balance)}
            </Chip>
          </>
        }
      >
        <div className="grid items-end gap-4 px-5 pt-4 md:grid-cols-[1fr_2fr_1fr]">
          <F label={w("in.receive_date")}>
            <select
              className={nativeSelect}
              value={date}
              onChange={(e) => {
                setDate(e.target.value);
                wd.reset();
                reload(e.target.value);
              }}
              aria-label={w("in.receive_date")}
            >
              {options.days.map((d) => (
                <option key={d} value={d}>
                  {dayLabel(d)}
                </option>
              ))}
            </select>
          </F>
          <F label={w("out.bundle_card")}>
            <Input
              ref={scanRef}
              value={code}
              onChange={(e) => setCode(e.target.value)}
              onKeyDown={(e) => e.key === "Enter" && (e.preventDefault(), receive())}
              placeholder={w("out.bundle_card")}
              aria-label={w("out.bundle_card")}
              className="h-10 font-mono tracking-wider"
              disabled={!canManage}
              autoComplete="off"
            />
          </F>
          {canManage ? (
            <Button onClick={receive} disabled={!code.trim() || pending || (mode === "SHORT" && output === "")}>
              <ScanLine /> {w("out.receive")}
            </Button>
          ) : (
            <span />
          )}
        </div>
        <div className="flex flex-wrap items-end gap-3 px-5 pt-4">
          {[
            ["FULL", "out.full", day.full],
            ["SHORT", "out.short", day.short],
          ].map(([m, label, n]) => (
            <button
              key={m}
              type="button"
              onClick={() => setMode(m)}
              aria-pressed={mode === m}
              className={cn("flex h-9 items-center gap-2 rounded-md border px-3 text-sm font-semibold", mode === m ? "border-brand bg-brand text-brand-foreground" : "border-border bg-surface hover:bg-surface-muted")}
            >
              <span className={cn("grid min-w-5 place-items-center rounded-full px-1 text-xs", mode === m ? "bg-white/25" : "bg-surface-muted")}>{n}</span>
              {w(label)}
            </button>
          ))}
          {mode === "SHORT" ? (
            <F label={w("out.output")} required className="w-40">
              <Input type="number" min={1} value={output} onChange={(e) => setOutput(e.target.value)} aria-label={w("out.output")} />
            </F>
          ) : null}
        </div>
        <p className="px-5 pt-3 pb-4 text-[13px] text-foreground-muted">{w(mode === "FULL" ? "out.full_hint" : "out.short_hint")}</p>

        <div className="border-t border-border p-4">
          <RecordsGrid
            exportName={`bundle-out-${date}`}
            rows={day.rows.map((r) => ({ ...r, bundle_status_label: w(`bstatus.${r.bundle_status}`), status_label: w(`status.${r.status}`) }))}
            rowKey="bundle_no"
            emptyState={{ title: w("in.empty_title"), text: w("out.empty_text") }}
            columns={[
              ...(canManage ? [selectColumn(wd, w)] : []),
              { key: "bundle_no", label: w("col.bundle_card"), type: "mono" },
              { key: "card_no", label: w("col.card_no") },
              { key: "cutting_no", label: w("col.cutting"), type: "number" },
              { key: "buyer", label: w("col.buyer") },
              { key: "style", label: w("col.style") },
              { key: "item", label: w("col.item") },
              { key: "colour", label: w("col.colour") },
              { key: "size", label: w("col.size") },
              { key: "line", label: w("col.line") },
              { key: "in_qty", label: w("col.bundle_in"), type: "number", align: "right" },
              { key: "output", label: w("col.output"), type: "number", align: "right" },
              { key: "balance", label: w("col.balance"), type: "number", align: "right" },
              { key: "bundle_status_label", label: w("col.bundle_status"), read: (r) => r.bundle_status_label, render: (r) => <Chip tone={BSTATUS_TONE[r.bundle_status]}>{r.bundle_status_label}</Chip> },
              { key: "out_at", label: w("col.out_at") },
              { key: "status_label", label: w("col.status"), type: "badge" },
              ...(canManage ? [actionColumn(wd, w, canManage)] : []),
            ]}
          />
        </div>
        <footer className="flex flex-col gap-3 border-t border-border px-5 py-3">
          <p className="text-[13px] text-foreground-muted">{w("out.foot", { n: day.bundles, t: wd.ticked.size })}</p>
          <div className="flex flex-wrap gap-2">
            <Link href="/assembly-floor/bundle-in" className={buttonVariants({ variant: "outline", size: "sm" })}>
              {w("out.go_in")} <ArrowRight />
            </Link>
            <Link href="/assembly-floor/finishing-bundle-in" className={buttonVariants({ variant: "outline", size: "sm" })}>
              {w("out.go_fin")} <ArrowRight />
            </Link>
            {canManage ? (
              <>
                <Button variant="secondary" size="sm" onClick={wd.take} disabled={wd.pending}>
                  {w("act.take_action")}
                </Button>
                <Button variant="outline" size="sm" onClick={wd.clearDay} disabled={!wd.canClear || wd.pending}>
                  <Trash2 /> {w("act.clear_day")}
                </Button>
              </>
            ) : null}
          </div>
        </footer>
      </Panel>

      <Collapsible title={w("out.wip_title")}>
        <RecordsGrid
          exportName={`sewing-wip-${date}`}
          rows={day.wip}
          rowKey="line"
          searchable={false}
          emptyState={{ title: w("in.empty_title"), text: w("in.empty_text") }}
          columns={[
            { key: "floor", label: w("col.floor") },
            { key: "line", label: w("col.line") },
            { key: "bundles", label: w("col.bundles"), type: "number", align: "right" },
            { key: "running", label: w("col.running"), type: "number", align: "right" },
            { key: "in_qty", label: w("col.in_qty"), type: "number", align: "right" },
            { key: "output", label: w("col.output"), type: "number", align: "right" },
            { key: "wip", label: w("col.wip"), type: "number", align: "right" },
          ]}
        />
      </Collapsible>
      {wd.dialog(date)}
    </>
  );
}

const pct = (v) => (v === null || v === undefined ? "—" : `${Number(v).toFixed(1)}%`);

/** The Sewing Dashboard: a date and a line, Show and Today, the board of every line (Export with the grid). */
function SewingBoard({ options, initial, act }) {
  const w = useWords();
  const toastManager = useToastManager();
  const [date, setDate] = useState(initial.date);
  const [line, setLine] = useState("");
  const [board, setBoard] = useState(initial);
  const [pending, startTransition] = useTransition();
  const show = (d = date, l = line) =>
    startTransition(async () => {
      const res = await act.board({ date: d, line_id: l });
      if (res?.status !== "success") return toastManager.add({ title: firstError(res), type: "danger" });
      setBoard(res.data);
    });

  return (
    <section className="rounded-lg border border-border bg-surface">
      <div className="flex flex-wrap items-end gap-3 px-5 pt-4">
        <F label={w("dash.date")} className="w-44">
          <Input type="date" value={date} onChange={(e) => setDate(e.target.value)} aria-label={w("dash.date")} />
        </F>
        <F label={w("dash.line")} className="w-56">
          <select className={nativeSelect} value={line} onChange={(e) => setLine(e.target.value)} aria-label={w("dash.line")}>
            <option value="">{w("dash.all_lines")}</option>
            {options.lines.map((l) => (
              <option key={l.value} value={l.value}>
                {l.label}
              </option>
            ))}
          </select>
        </F>
        <Button onClick={() => show()} disabled={pending || !date}>
          {w("act.show")}
        </Button>
        <Button
          variant="outline"
          onClick={() => {
            setDate(options.today);
            show(options.today, line);
          }}
          disabled={pending}
        >
          {w("act.today")}
        </Button>
      </div>
      <div className="p-4">
        <RecordsGrid
          exportName={`sewing-dashboard-${board.date}`}
          rows={board.rows.map((r) => ({ ...r, achv_label: pct(r.achievement), eff_label: pct(r.efficiency), status_label: w(`status.${r.status}`) }))}
          rowKey="line"
          emptyState={{ title: w("dash.empty"), text: w("dash.empty_text") }}
          columns={[
            { key: "line", label: w("col.line") },
            { key: "floor", label: w("col.floor") },
            { key: "style", label: w("col.style") },
            { key: "target", label: w("col.target"), type: "number", align: "right" },
            { key: "output", label: w("col.output"), type: "number", align: "right" },
            { key: "achv_label", label: w("col.achv"), align: "right" },
            { key: "eff_label", label: w("col.eff"), align: "right" },
            { key: "bundles_in", label: w("col.bdl_in"), type: "number", align: "right" },
            { key: "running", label: w("col.running"), type: "number", align: "right" },
            { key: "bundles_out", label: w("col.bdl_out"), type: "number", align: "right" },
            { key: "wip", label: w("col.wip"), type: "number", align: "right" },
            { key: "good", label: w("col.good"), type: "number", align: "right" },
            { key: "rework", label: w("col.rework"), type: "number", align: "right" },
            { key: "reject", label: w("col.reject"), type: "number", align: "right" },
            { key: "qc_pending", label: w("col.qc_pend"), type: "number", align: "right" },
            {
              key: "status_label",
              label: w("col.status"),
              read: (r) => r.status_label,
              render: (r) => <Chip tone={r.status === "RUNNING" ? "bg-success/15 text-success" : "bg-surface-muted text-foreground-muted"}>{r.status_label}</Chip>,
            },
          ]}
        />
        <p className="mt-3 text-xs text-foreground-muted">{w("dash.pending")}</p>
      </div>
    </section>
  );
}

export { SewingNav, BundleInDesk, BundleOutDesk, SewingBoard };
