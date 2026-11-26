"use client";

import { useRef, useState } from "react";
import Link from "next/link";
import { useRouter } from "next/navigation";
import { Boxes, Check, Keyboard, LoaderCircle, Plus, QrCode, ScanLine, Trash2 } from "lucide-react";
import { cn } from "@/lib/utils";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Modal } from "@/components/ui/modal";
import { ConfirmDialog } from "@/components/ui/confirm-dialog";
import { useToastManager } from "@/components/ui/toast";
import { useQt, useRun, useFilters, num, day, Panel, Table, Field, Select, FilterCard, Kpi, Pill, Notice, Facts } from "./common";

/*
 * Quality Tally → Cutting → Cutting Input (user, 2026-11-25): nearly every
 * bundle card is scanned (QR or barcode, a scanner types it and Enter), so the
 * screen is the scan box; the card's details open in a pop-up, its job already
 * matched — Enter gives it, scanning the next card gives this one and opens
 * that one. No table is picked: the QC table that first scans a bundle takes
 * it. Typing a card by hand is a pop-up of its own, with the panels listed.
 */

const BLANK = { barcode: "", bundle_no: "", job_id: "", garment_type: "", panel_id: "", size: "", serial_from: "", serial_to: "", qty: "", cutting_no: "", batch_no: "", source_id: null };

/**
 * Scanning and giving go straight through the module's own proxy (as a tap does), not a server action: a
 * manager scans hundreds of cards and the pop-up must be there before the next one is in hand.
 * Answers { data } or { message, errors }.
 */
async function send(path, options = {}) {
  try {
    const res = await fetch(`/api/quality-tally/${path}`, { ...options, headers: { Accept: "application/json", "Content-Type": "application/json" }, cache: "no-store" });
    const json = await res.json().catch(() => ({}));
    return res.ok ? { data: json.data } : { message: json.message ?? `HTTP ${res.status}`, errors: json.errors ?? {} };
  } catch {
    return { message: null, errors: {} };
  }
}

/** Gives a bundle: toast, then the list re-read in the background. */
function useGive() {
  const p = useQt();
  const toast = useToastManager();
  const router = useRouter();
  const [busy, setBusy] = useState(false);
  const give = async (values) => {
    setBusy(true);
    const res = await send("cut-bundles", { method: "POST", body: JSON.stringify(values) });
    setBusy(false);
    toast.add(res.data ? { title: p("toast.bundle_given"), type: "success" } : { title: res.message ?? p("error"), type: "danger" });
    if (res.data) router.refresh();
    return res;
  };
  return { give, busy };
}

const body = (v, date) => ({
  ...v,
  barcode: v.barcode || v.bundle_no,
  panel_id: v.panel_id || null,
  input_date: date,
  serial_from: v.serial_from === "" ? null : Number(v.serial_from),
  serial_to: v.serial_to === "" ? null : Number(v.serial_to),
  qty: v.qty === "" ? null : Number(v.qty),
});

function CutInput({ data, form, query, canManage, act }) {
  const p = useQt();
  const toast = useToastManager();
  const { apply, pending: filtering } = useFilters(query);
  const { run, pending } = useRun();
  const [date, setDate] = useState(query.date ?? data.date);
  const [search, setSearch] = useState(query.search ?? "");
  const [code, setCode] = useState("");
  const [reading, setReading] = useState(false);
  const [scanned, setScanned] = useState(null); // { kind: CARD | INPUT | MISSING, card?, bundle?, code? }
  const [manual, setManual] = useState(null); // the manual form's starting values
  const [last, setLast] = useState(null);
  const [deleting, setDeleting] = useState(null);
  const scanRef = useRef(null);
  const reads = useRef(0); // the newest read: a late answer to an older one is dropped
  const waiting = useRef([]); // cards scanned while another was still being read: opened in their turn
  const t = data.rows.reduce((a, r) => ({ qty: a.qty + r.qty, checked: a.checked + r.checked, reject: a.reject + r.reject }), { qty: 0, checked: 0, reject: 0 });

  const read = async (value) => {
    const turn = ++reads.current;
    setCode("");
    setReading(true);
    const res = await send(`cut-bundles/scan?${new URLSearchParams({ code: value })}`);
    if (turn !== reads.current) return;
    setReading(false);
    if (!res.data) {
      closeScan();
      return toast.add({ title: res.message ?? p("error"), type: "danger" });
    }
    const { found, bundle, card } = res.data;
    setScanned(found === "INPUT" ? { kind: "INPUT", bundle } : found === "CARD" ? { kind: "CARD", card } : { kind: "MISSING", code: value });
  };
  const scan = (e) => {
    e.preventDefault();
    const value = code.trim();
    if (!value) return;
    if (reading) {
      waiting.current.push(value);
      return setCode("");
    }
    read(value);
  };
  function closeScan() {
    reads.current += 1;
    setReading(false);
    setScanned(null);
    const next = waiting.current.shift();
    if (next) read(next);
    else scanRef.current?.focus();
  }
  const given = (v) => {
    const job = form.jobs.find((j) => j.value === v.job_id)?.label ?? "";
    setLast({ no: v.bundle_no, job });
  };

  return (
    <>
      {canManage ? (
        <Panel
          title={p("ci.qr_title")}
          subtitle={p("ci.qr_hint")}
          icon={QrCode}
          className="mb-4"
          action={
            <Button variant="outline" onClick={() => setManual({ ...BLANK })}>
              <Keyboard /> {p("ci.manual")}
            </Button>
          }
        >
          <form onSubmit={scan} className="flex flex-wrap items-end gap-3 p-5">
            <Field label={p("cut.scan")} className="min-w-64 flex-1">
              <span className="relative block">
                <ScanLine className="pointer-events-none absolute top-1/2 left-3 size-5 -translate-y-1/2 text-foreground-subtle" aria-hidden />
                <Input ref={scanRef} autoFocus value={code} onChange={(e) => setCode(e.target.value)} placeholder={p("cut.scan_hint")} className="h-12 pl-11 text-lg" />
              </span>
            </Field>
            {/* Never disabled while reading: a disabled submit button also stops Enter, and the next scan with it. */}
            <Button type="submit" aria-busy={reading} className="h-12">
              {reading ? <LoaderCircle className="animate-spin" /> : <ScanLine />} {p("ci.scan")}
            </Button>
          </form>
          {last ? <p className="flex items-center gap-2 border-t border-border px-5 py-2.5 text-[13px] text-success"><Check className="size-4" aria-hidden /> {p("ci.last_given", last)}</p> : null}
        </Panel>
      ) : null}

      <FilterCard onApply={() => apply({ date, search })} pending={filtering} applyLabel={p("apply")}>
        <Field label={p("f.date")}>
          <Input type="date" value={date} onChange={(e) => setDate(e.target.value)} className="w-44" />
        </Field>
        <Field label={p("f.search")} className="min-w-56 flex-1">
          <Input value={search} onChange={(e) => setSearch(e.target.value)} placeholder={p("ci.search_hint")} />
        </Field>
      </FilterCard>
      <div className="mb-4 grid grid-cols-2 gap-3 md:grid-cols-4">
        <Kpi label={p("col.bundles")} value={num(data.rows.length)} tone="brand" />
        <Kpi label={p("ci.bundle_pcs")} value={num(t.qty)} tone="muted" />
        <Kpi label={p("cpi.checked_panels")} value={num(t.checked)} tone="info" />
        <Kpi label={p("cpi.reject_panels")} value={num(t.reject)} tone="danger" />
      </div>
      <Panel title={p("ci.title")} subtitle={p("ci.count", { n: data.rows.length, date: day(data.date) })} icon={Boxes}>
        <Table
          rows={data.rows}
          empty={p("empty.bundles")}
          columns={[
            { key: "bundle_no", label: p("col.bundle_no"), render: (r) => (<><b className="font-mono">{r.bundle_no}</b><div><Pill tone={r.source === "SCAN" ? "bg-info-subtle text-info" : "bg-surface-muted text-foreground-muted"}>{p(`ci.source_${r.source}`)}</Pill></div></>) },
            { key: "job", label: p("col.job"), render: (r) => (<><b>{r.job_no}</b><div className="text-xs text-foreground-muted">{[r.style, r.color].filter(Boolean).join(" · ")}</div></>) },
            { key: "garment_type", label: p("col.garment_type") },
            { key: "panel", label: p("col.panel"), render: (r) => r.panel ?? p("ci.all_panels") },
            { key: "size", label: p("col.size"), render: (r) => r.size || "—" },
            { key: "serial", label: p("col.bundle_serial"), render: (r) => r.serial ?? "—" },
            { key: "qty", label: p("col.bundle_qty"), align: "right", render: (r) => num(r.qty) },
            { key: "cutting_no", label: p("col.cutting_no"), render: (r) => r.cutting_no || "—" },
            { key: "batch_no", label: p("col.batch_no"), render: (r) => r.batch_no || "—" },
            { key: "line", label: p("col.cutting_table"), render: (r) => r.line ?? <span className="text-xs text-foreground-muted">{p("ci.no_table")}</span> },
            { key: "checked", label: p("col.checked"), align: "right", render: (r) => num(r.checked) },
            { key: "pass", label: p("col.pass"), align: "right", render: (r) => num(r.pass) },
            { key: "reject", label: p("action.REJECT"), align: "right", render: (r) => num(r.reject) },
            ...(canManage
              ? [{ key: "act", label: "", render: (r) => (r.tapped ? null : <Button size="sm" variant="outline" onClick={() => setDeleting(r)} aria-label={p("btn.delete")}><Trash2 /></Button>) }]
              : []),
          ]}
        />
      </Panel>

      {scanned?.kind === "CARD" ? (
        <CardPopup
          key={scanned.card.barcode}
          card={scanned.card}
          form={form}
          date={data.date}
          onClose={closeScan}
          onGiven={(v, next) => {
            given(v);
            if (next) read(next);
            else closeScan();
          }}
        />
      ) : null}
      {scanned && scanned.kind !== "CARD" ? (
        <InfoPopup
          key={scanned.code ?? scanned.bundle?.id}
          scanned={scanned}
          onClose={closeScan}
          onNext={read}
          onManual={() => {
            setManual({ ...BLANK, barcode: scanned.code, bundle_no: scanned.code });
            setScanned(null);
          }}
        />
      ) : null}
      {manual ? <ManualPopup start={manual} form={form} date={data.date} onGiven={given} onClose={() => setManual(null)} /> : null}
      <ConfirmDialog
        open={deleting !== null}
        onOpenChange={(o) => !o && setDeleting(null)}
        title={p("ci.delete_title")}
        description={deleting?.bundle_no ?? ""}
        confirmLabel={p("btn.delete")}
        cancelLabel={p("btn.close")}
        loading={pending}
        onConfirm={() => run(() => act("deleteBundle", deleting.id), p("toast.deleted"), () => setDeleting(null))}
      />
    </>
  );
}

/** A scanner keeps typing into this: empty Enter = done with this card, a code = the next one. */
function NextScan({ onEnter, busy, hint }) {
  const [code, setCode] = useState("");
  return (
    <form
      onSubmit={(e) => {
        e.preventDefault();
        if (busy) return;
        // Cleared at once: a refused card must not be glued onto the next one scanned.
        setCode("");
        onEnter(code.trim());
      }}
      className="relative mr-auto min-w-60 flex-1"
    >
      <ScanLine className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-foreground-subtle" aria-hidden />
      <Input autoFocus value={code} onChange={(e) => setCode(e.target.value)} placeholder={hint} aria-label={hint} className="pl-9" />
    </form>
  );
}

/** A card of the cutting department: what it says, the job it belongs to, and Give. */
function CardPopup({ card, form, date, onClose, onGiven }) {
  const p = useQt();
  const { give, busy } = useGive();
  const [errors, setErrors] = useState({});
  const [v, setV] = useState(() => ({ job_id: card.job_id ?? "", garment_type: card.garment_type ?? "", panel_id: card.panel_id ?? "" }));
  const set = (k) => (val) => setV((all) => ({ ...all, [k]: val }));
  const err = (k) => errors[k]?.[0];
  const panels = form.garment_types.find((g) => g.name === v.garment_type)?.panels ?? [];
  // The jobs of the card's style first; every open job when none has it.
  const jobs = card.job_choices.length ? form.jobs.filter((j) => card.job_choices.includes(j.value) || j.value === v.job_id) : form.jobs;

  const submit = async (next = "") => {
    const values = {
      ...BLANK, ...v, barcode: card.barcode, bundle_no: card.bundle_no, size: card.size ?? "", serial_from: card.serial_from ?? "", serial_to: card.serial_to ?? "",
      qty: card.qty ?? "", cutting_no: card.cutting_no ?? "", batch_no: card.batch_no ?? "", source_id: card.source_id,
    };
    const res = await give(body(values, date));
    if (res.data) onGiven(values, next);
    else setErrors(res.errors);
  };

  return (
    <Modal
      open
      onOpenChange={onClose}
      title={p("ci.card_title", { no: card.bundle_no })}
      className="max-w-3xl"
      footer={
        <>
          <NextScan onEnter={submit} busy={busy} hint={p("ci.next_scan")} />
          <Button variant="outline" onClick={onClose}>
            {p("btn.close")}
          </Button>
          <Button loading={busy} onClick={() => submit()}>
            <Plus /> {p("ci.give")}
          </Button>
        </>
      }
    >
      <Facts
        items={[
          [p("col.buyer"), card.buyer || "—"], [p("col.style"), card.style], [p("col.color"), card.color], [p("col.po"), card.po_no || "—"],
          [p("col.panel"), card.part], [p("col.size"), card.size], [p("col.bundle_serial"), `${card.serial_from}-${card.serial_to}`], [p("col.bundle_qty"), <b key="q" className="text-lg tabular-nums">{num(card.qty)}</b>],
          [p("col.cutting_no"), card.cutting_no || "—"], [p("col.batch_no"), card.batch_no || "—"], [p("col.garment_type"), card.garment || "—"], [p("f.date"), day(date)],
        ]}
      />
      <div className="mt-3">
        {card.match ? <p className="flex items-center gap-2 text-[13px] font-medium text-success"><Check className="size-4" aria-hidden /> {p(`ci.match_${card.match}`)}</p> : null}
        {!card.match && card.job_choices.length ? <Notice>{p("ci.match_none", { style: card.style, color: card.color })}</Notice> : null}
        {!card.match && !card.job_choices.length ? (
          <Notice>
            <span>
              {p("ci.no_job", { style: card.style })}{" "}
              <Link href="/quality-tally/jobs" className="underline">{p("ci.open_jobs")}</Link>
            </span>
          </Notice>
        ) : null}
      </div>
      <div className="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-2">
        <Field label={p("f.job")} required error={err("job_id")} className="sm:col-span-2">
          <Select value={v.job_id} onChange={set("job_id")} options={jobs} placeholder={p("f.select_job")} />
        </Field>
        <Field label={p("f.garment_type")} required error={err("garment_type")}>
          <Select value={v.garment_type} onChange={(g) => setV((all) => ({ ...all, garment_type: g, panel_id: "" }))} options={form.garment_types.map((g) => ({ value: g.name, label: g.name }))} placeholder={p("f.select_garment")} />
        </Field>
        <Field label={p("col.panel")} error={err("panel_id")} hint={p("ci.panel_hint")}>
          <Select value={v.panel_id} onChange={set("panel_id")} options={panels.map((x) => ({ value: x.id, label: x.name }))} placeholder={p("ci.all_panels")} />
        </Field>
      </div>
      {err("barcode") ? <p className="mt-2 text-xs text-danger">{err("barcode")}</p> : null}
      {err("qty") ? <p className="mt-2 text-xs text-danger">{err("qty")}</p> : null}
    </Modal>
  );
}

/** A card already given (where, and how far it is checked), or a code nobody knows. */
function InfoPopup({ scanned, onClose, onNext, onManual }) {
  const p = useQt();
  const b = scanned.bundle;
  return (
    <Modal
      open
      onOpenChange={onClose}
      title={b ? p("ci.given_title") : p("ci.missing_title")}
      className="max-w-2xl"
      footer={
        <>
          <NextScan onEnter={(next) => (next ? onNext(next) : onClose())} hint={p("ci.next_scan_close")} />
          {b ? null : (
            <Button variant="outline" onClick={onManual}>
              <Keyboard /> {p("ci.manual")}
            </Button>
          )}
          <Button onClick={onClose}>{p("btn.close")}</Button>
        </>
      }
    >
      <Notice tone={b ? "warning" : "danger"} className="mb-3">
        {b ? (b.line ? p("ci.given_to", { line: b.line, date: day(b.input_date) }) : p("ci.given_free", { date: day(b.input_date) })) : p("ci.missing", { code: scanned.code })}
      </Notice>
      {b ? (
        <Facts
          items={[
            [p("col.bundle_no"), b.bundle_no], [p("col.job"), b.job_no], [p("col.style"), b.style], [p("col.color"), b.color || "—"],
            [p("col.garment_type"), b.garment_type], [p("col.panel"), b.panel ?? p("ci.all_panels")], [p("col.size"), b.size || "—"], [p("col.bundle_qty"), num(b.qty)],
          ]}
        />
      ) : null}
    </Modal>
  );
}

/** A card typed by hand — the rare case. The lot's fields stay for the next one. */
function ManualPopup({ start, form, date, onGiven, onClose }) {
  const p = useQt();
  const { give, busy } = useGive();
  const [v, setV] = useState(start);
  const [errors, setErrors] = useState({});
  const noRef = useRef(null);
  const set = (k) => (val) => setV((all) => ({ ...all, [k]: val }));
  const err = (k) => errors[k]?.[0];
  // A job is one garment: once it has bundles, only that garment's panels are its.
  const fixed = form.jobs.find((j) => j.value === v.job_id)?.garment_type ?? null;
  const garments = form.garment_types.filter((g) => !v.garment_type || g.name === v.garment_type);
  const pickJob = (id) => {
    const own = form.jobs.find((j) => j.value === id)?.garment_type;
    setV((all) => ({ ...all, job_id: id, ...(own && own !== all.garment_type ? { garment_type: own, panel_id: "" } : {}) }));
  };

  const save = async () => {
    const res = await give(body(v, date));
    if (!res.data) return setErrors(res.errors);
    onGiven(v);
    setV((all) => ({ ...BLANK, job_id: all.job_id, garment_type: all.garment_type, size: all.size, cutting_no: all.cutting_no, batch_no: all.batch_no }));
    setErrors({});
    noRef.current?.focus();
  };

  return (
    <Modal
      open
      onOpenChange={onClose}
      title={p("ci.manual_title")}
      className="max-w-3xl"
      footer={
        <>
          <Button variant="outline" onClick={onClose}>
            {p("btn.close")}
          </Button>
          <Button loading={busy} onClick={save}>
            <Plus /> {p("ci.give")}
          </Button>
        </>
      }
    >
      <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
        <Field label={p("col.bundle_no")} required error={err("bundle_no") ?? err("barcode")}>
          <Input ref={noRef} autoFocus value={v.bundle_no} onChange={(e) => setV((all) => ({ ...all, bundle_no: e.target.value, barcode: "", source_id: null }))} maxLength={40} />
        </Field>
        <Field label={p("f.job")} required error={err("job_id")}>
          <Select value={v.job_id} onChange={pickJob} options={form.jobs} placeholder={p("f.select_job")} />
        </Field>
        <Field label={p("f.garment_type")} required error={err("garment_type")}>
          <Select value={v.garment_type} onChange={(g) => setV((all) => ({ ...all, garment_type: g, panel_id: "" }))} options={form.garment_types.filter((g) => !fixed || g.name === fixed).map((g) => ({ value: g.name, label: g.name }))} placeholder={p("f.select_garment")} />
        </Field>
        <Field label={p("col.size")} error={err("size")}>
          <Input value={v.size} onChange={(e) => set("size")(e.target.value)} maxLength={32} />
        </Field>
        <Field label={p("ci.serial")} error={err("serial_to")}>
          <span className="flex items-center gap-2">
            <Input type="number" min="0" value={v.serial_from} onChange={(e) => set("serial_from")(e.target.value)} aria-label={p("f.from")} />
            <span className="text-foreground-muted">–</span>
            <Input type="number" min="0" value={v.serial_to} onChange={(e) => setV((all) => ({ ...all, serial_to: e.target.value, qty: all.qty === "" && all.serial_from !== "" && Number(e.target.value) >= Number(all.serial_from) ? Number(e.target.value) - Number(all.serial_from) + 1 : all.qty }))} aria-label={p("f.to")} />
          </span>
        </Field>
        <Field label={p("col.bundle_qty")} required error={err("qty")}>
          <Input type="number" min="1" value={v.qty} onChange={(e) => set("qty")(e.target.value)} />
        </Field>
        <div className="grid grid-cols-2 gap-2">
          <Field label={p("col.cutting_no")} error={err("cutting_no")}>
            <Input value={v.cutting_no} onChange={(e) => set("cutting_no")(e.target.value)} maxLength={40} />
          </Field>
          <Field label={p("col.batch_no")} error={err("batch_no")}>
            <Input value={v.batch_no} onChange={(e) => set("batch_no")(e.target.value)} maxLength={40} />
          </Field>
        </div>
      </div>
      <div className="mt-4">
        <p className="text-[13px] font-semibold text-foreground">
          {p("ci.panels")} <span className="font-normal text-foreground-muted">— {v.garment_type ? p("ci.panels_all") : p("ci.panels_pick")}</span>
        </p>
        <div className="mt-2 flex flex-col gap-2">
          {garments.map((g) => (
            <div key={g.name} className="flex flex-wrap items-center gap-2 rounded-md border border-border px-3 py-2">
              <span className="w-28 shrink-0 text-[13px] font-semibold text-foreground">{g.name}</span>
              <PanelChip on={v.garment_type === g.name && v.panel_id === ""} onClick={() => setV((all) => ({ ...all, garment_type: g.name, panel_id: "" }))}>
                {p("ci.all_panels")}
              </PanelChip>
              {g.panels.map((x) => (
                <PanelChip key={x.id} on={v.panel_id === x.id} onClick={() => setV((all) => ({ ...all, garment_type: g.name, panel_id: x.id }))}>
                  {x.name}
                  {x.per_piece > 1 ? <span className="ml-1 text-brand">×{x.per_piece}</span> : null}
                </PanelChip>
              ))}
            </div>
          ))}
        </div>
        {err("panel_id") ? <p className="mt-1 text-xs text-danger">{err("panel_id")}</p> : null}
      </div>
    </Modal>
  );
}

function PanelChip({ on, onClick, children }) {
  return (
    <button
      type="button"
      aria-pressed={on}
      onClick={onClick}
      className={cn("rounded-md border px-3 py-1.5 text-[13px] font-semibold", on ? "border-brand bg-brand-subtle text-brand" : "border-border bg-surface text-foreground hover:border-border-strong")}
    >
      {children}
    </button>
  );
}

export { CutInput };
