"use client";

import { useMemo, useRef, useState, useTransition } from "react";
import { printDocument } from "@/lib/print-document";
import { useRouter } from "next/navigation";
import { Eraser, Printer, ScanLine, Search, Save, Undo2, PackageCheck } from "lucide-react";
import { cn } from "@/lib/utils";
import { Input } from "@/components/ui/input";
import { Select } from "@/components/ui/select";
import { Checkbox } from "@/components/ui/checkbox";
import { Button } from "@/components/ui/button";
import { Alert } from "@/components/ui/alert";
import { useToastManager } from "@/components/ui/toast";
import { FormattedDateTime } from "@/components/ui/formatted-date-time";
import { useT } from "@/lib/i18n";
import { card, messagesOf, str } from "@/components/prototype/proto-kit";

/*
 * Prototype Desk → Sample Bundle Tracking (AnnotechERP 07/03): the scan desk
 * (each scan recorded on its own), the tracking of each movement, the bundle
 * lookup and the order's sample cards. Nothing here reloads the page.
 */

const th = "px-2 py-2 font-medium";
const TIMES = ["received_at", "start_at", "end_at"];
const td = "px-2 py-1.5";
/** "2026-10-22T08:00:00+00:00" → the local "2026-10-22T08:00" a datetime box takes. */
const localInput = (iso) => {
  if (!iso) return "";
  const d = new Date(iso);
  const pad = (n) => String(n).padStart(2, "0");
  return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
};

/** Scan Desk: pick the process, scan or type the bundle card, and today's movements there below. */
function ScanDesk({ processes, initial, canManage, track }) {
  const t = useT("prototype");
  const toastManager = useToastManager();
  const router = useRouter();
  const input = useRef(null);
  const [process, setProcess] = useState(processes[0]);
  const [desk, setDesk] = useState(initial);
  const [code, setCode] = useState("");
  const [errors, setErrors] = useState(null);
  const [pending, startTransition] = useTransition();
  const rows = desk.movements;

  function refresh(next = process) {
    startTransition(async () => {
      const result = await track("desk", next);
      if (result?.status === "success") setDesk(result.data);
    });
  }

  function pick(next) {
    setProcess(next);
    setErrors(null);
    refresh(next);
    input.current?.focus();
  }

  function scan() {
    if (!code.trim()) return;
    setErrors(null);
    startTransition(async () => {
      const result = await track("scan", process, code.trim());
      if (result?.status !== "success") return setErrors(messagesOf(result));
      toastManager.add({ title: t("ui.bt.scan.done", { code: result.data.bundle_no, process: t(`process.${process}`) }), type: "success" });
      setCode("");
      const fresh = await track("desk", process);
      if (fresh?.status === "success") setDesk(fresh.data);
      input.current?.focus();
      router.refresh();
    });
  }

  function undo(id) {
    setErrors(null);
    startTransition(async () => {
      const result = await track("undo", id);
      if (result?.status !== "success") return setErrors(messagesOf(result));
      toastManager.add({ title: t("ui.bt.scan.undone"), type: "success" });
      const fresh = await track("desk", process);
      if (fresh?.status === "success") setDesk(fresh.data);
      router.refresh();
    });
  }

  return (
    <section className={cn(card, "flex flex-col gap-4 p-5")}>
      <div>
        <h2 className="text-base font-semibold text-foreground">{t("ui.bt.scan.title")}</h2>
        <p className="mt-0.5 text-[13px] text-foreground-muted">{t("ui.bt.scan.hint")}</p>
      </div>
      <div className="flex flex-wrap gap-2" role="tablist">
        {processes.map((p) => (
          <button
            key={p}
            type="button"
            role="tab"
            aria-selected={p === process}
            onClick={() => pick(p)}
            className={cn("inline-flex items-center gap-2 rounded-md border px-3 py-1.5 text-[13px] font-medium", p === process ? "border-brand bg-brand text-white" : "border-border text-foreground hover:bg-surface-muted")}
          >
            {t(`process.${p}`)}
            <span className={cn("rounded-full px-1.5 text-xs tabular-nums", p === process ? "bg-white/20" : "bg-surface-muted")}>{desk.counts?.[p] ?? 0}</span>
          </button>
        ))}
      </div>
      <div className="flex flex-col gap-1.5 border-t border-border pt-4">
        <label htmlFor="bundle-card" className="text-[13px] font-medium text-foreground">
          {t("ui.bt.f.bundle_card")}
        </label>
        <div className="flex flex-wrap items-center gap-2">
          <div className="min-w-[240px] flex-1">
            <Input
              id="bundle-card"
              ref={input}
              autoFocus
              value={code}
              onChange={(e) => setCode(e.target.value)}
              onKeyDown={(e) => e.key === "Enter" && (e.preventDefault(), scan())}
              placeholder={t("ui.bt.scan.placeholder")}
              disabled={!canManage}
              className="font-mono"
              aria-label={t("ui.bt.f.bundle_card")}
            />
          </div>
          <Button type="button" onClick={scan} disabled={!canManage || pending || !code.trim()}>
            <ScanLine /> {t("ui.bt.scan.scan")}
          </Button>
          <span className="rounded-full bg-brand-subtle px-2 py-0.5 text-xs font-medium text-brand">{t("ui.bt.scan.scanned", { n: rows.length })}</span>
          <span className="rounded-full bg-surface-muted px-2 py-0.5 text-xs font-medium tabular-nums">{t("ui.bt.scan.pcs", { n: rows.reduce((a, r) => a + r.qty, 0) })}</span>
          <Button type="button" variant="outline" onClick={() => (setCode(""), setErrors(null))} disabled={!code}>
            <Eraser /> {t("ui.bt.scan.clear")}
          </Button>
        </div>
        <p className="text-xs text-foreground-muted">{t(`process_hint.${process}`)}</p>
      </div>
      {errors ? (
        <Alert variant="danger">
          {errors.map((e) => (
            <p key={e}>{e}</p>
          ))}
        </Alert>
      ) : null}
      {rows.length === 0 ? (
        <div className="py-8 text-center text-foreground-muted">
          <p className="font-medium text-foreground">{t("ui.bt.scan.empty", { process: t(`process.${process}`) })}</p>
          <p className="text-xs">{t("ui.bt.scan.empty_hint")}</p>
        </div>
      ) : (
        <div className="overflow-x-auto">
          <table className="w-full text-[13px]">
            <thead>
              <tr className="border-b border-border text-left text-foreground-muted">
                {["scanned_at", "bundle", "card_no", "sample_no", "color", "size", "qty", "operator"].map((k) => (
                  <th key={k} className={th}>
                    {t(`ui.bt.col.${k}`)}
                  </th>
                ))}
                <th />
              </tr>
            </thead>
            <tbody>
              {rows.map((r) => (
                <tr key={r.id} className="border-b border-border last:border-0">
                  <td className={td}>
                    <FormattedDateTime value={r.scanned_at} />
                  </td>
                  <td className={cn(td, "font-mono")}>{r.bundle_no}</td>
                  <td className={cn(td, "font-mono")}>{r.card_no}</td>
                  <td className={cn(td, "font-mono")}>{r.sample_no ?? "—"}</td>
                  <td className={td}>{r.color ?? "—"}</td>
                  <td className={td}>{r.size ?? "—"}</td>
                  <td className={cn(td, "tabular-nums")}>{r.qty}</td>
                  <td className={td}>{r.operator_name ?? "—"}</td>
                  <td className={cn(td, "text-right")}>
                    {canManage ? (
                      <Button type="button" variant="ghost" size="sm" onClick={() => undo(r.id)} disabled={pending}>
                        <Undo2 /> {t("ui.bt.scan.undo")}
                      </Button>
                    ) : null}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
      <p className="border-t border-border pt-3 text-[13px] text-foreground-muted">{t("ui.bt.scan.note")}</p>
    </section>
  );
}

/** One movement's tracking, saved on its own. */
function MovementRow({ m, options, canManage, track, onSaved }) {
  const t = useT("prototype");
  const toastManager = useToastManager();
  const [form, setForm] = useState({
    operator_id: str(m.operator_id), received_at: localInput(m.received_at), start_at: localInput(m.start_at), end_at: localInput(m.end_at),
    expected_hours: str(m.expected_hours), status: m.status, delay_reason: str(m.delay_reason), remarks: str(m.remarks),
  });
  const [error, setError] = useState(null);
  const [pending, startTransition] = useTransition();
  const set = (k) => (v) => setForm((f) => ({ ...f, [k]: v ?? "" }));
  const off = !canManage;
  function save() {
    setError(null);
    // A datetime box holds local time; it goes out with its offset, so the server keeps the moment the operator meant.
    const payload = Object.fromEntries(Object.entries(form).map(([k, v]) => [k, v === "" ? null : TIMES.includes(k) ? new Date(v).toISOString() : v]));
    startTransition(async () => {
      const result = await track("update", m.id, payload);
      if (result?.status !== "success") return setError(messagesOf(result).join(" "));
      toastManager.add({ title: t("ui.bt.upd.saved"), type: "success" });
      onSaved(result.data);
    });
  }
  return (
    <>
      <tr className="border-b border-border align-top">
        <td className={cn(td, "font-medium")}>{t(`process.${m.process}`)}</td>
        <td className={cn(td, "w-40")}>
          <Select value={form.operator_id} onValueChange={set("operator_id")} options={[{ value: "", label: "—" }, ...options.users]} placeholder="—" disabled={off} aria-label={`${t("ui.bt.upd.f.operator")} ${t(`process.${m.process}`)}`} />
        </td>
        {["received_at", "start_at", "end_at"].map((k) => (
          <td key={k} className={cn(td, "w-48")}>
            <Input type="datetime-local" value={form[k]} onChange={(e) => set(k)(e.target.value)} disabled={off} aria-label={`${t(`ui.bt.upd.f.${k}`)} ${t(`process.${m.process}`)}`} />
          </td>
        ))}
        <td className={cn(td, "w-24")}>
          <Input type="number" min={0} step="0.25" value={form.expected_hours} onChange={(e) => set("expected_hours")(e.target.value)} disabled={off} aria-label={`${t("ui.bt.upd.f.expected_hours")} ${t(`process.${m.process}`)}`} />
        </td>
        <td className={cn(td, "w-36")}>
          <Select value={form.status} onValueChange={set("status")} options={options.statuses.map((s) => ({ value: s, label: t(`move_status.${s}`) }))} disabled={off} aria-label={`${t("ui.bt.upd.f.status")} ${t(`process.${m.process}`)}`} />
        </td>
        <td className={cn(td, "w-20 tabular-nums", m.delayed && "text-danger")}>{m.delay_hours ?? "—"}</td>
        <td className={cn(td, "w-48")}>
          <Input value={form.delay_reason} onChange={(e) => set("delay_reason")(e.target.value)} disabled={off} maxLength={255} aria-label={`${t("ui.bt.upd.f.delay_reason")} ${t(`process.${m.process}`)}`} />
        </td>
        <td className={td}>
          {canManage ? (
            <Button type="button" variant="outline" size="sm" onClick={save} disabled={pending}>
              <Save /> {t("ui.bt.upd.save")}
            </Button>
          ) : null}
        </td>
      </tr>
      {error ? (
        <tr>
          <td colSpan={10} className="px-2 pb-2 text-xs text-danger">
            {error}
          </td>
        </tr>
      ) : null}
    </>
  );
}

/** Tracking Update: the bundles that have moved, and on picking one its movements to record. */
function TrackingUpdate({ bundles, options, canManage, track }) {
  const t = useT("prototype");
  const [query, setQuery] = useState("");
  const [picked, setPicked] = useState(null);
  const [saved, setSaved] = useState({});
  const shown = bundles.filter((b) => b.bundle_no.toLowerCase().includes(query.trim().toLowerCase()));
  const bundle = bundles.find((b) => b.bundle_no === picked);
  return (
    <div className="grid gap-4 lg:grid-cols-[300px_1fr]">
      <section className={cn(card, "flex flex-col gap-3 p-4")}>
        <h3 className="text-sm font-semibold text-foreground">{t("ui.bt.upd.bundles")}</h3>
        <div className="relative">
          <Search className="pointer-events-none absolute left-2.5 top-1/2 size-4 -translate-y-1/2 text-foreground-muted" />
          <Input value={query} onChange={(e) => setQuery(e.target.value)} placeholder={t("ui.bt.upd.search")} className="pl-8" aria-label={t("ui.bt.upd.bundles")} />
        </div>
        {shown.length === 0 ? (
          <p className="py-6 text-center text-[13px] text-foreground-muted">{t("ui.bt.upd.none")}</p>
        ) : (
          <ul className="flex flex-col gap-1">
            {shown.map((b) => (
              <li key={b.bundle_no}>
                <button type="button" onClick={() => setPicked(b.bundle_no)} className={cn("flex w-full items-center justify-between rounded-md px-3 py-2 text-left text-[13px]", picked === b.bundle_no ? "bg-brand-subtle text-brand" : "hover:bg-surface-muted")}>
                  <span className="font-mono">{b.bundle_no}</span>
                  <span className="text-xs text-foreground-muted">{b.now_at ? t(`process.${b.now_at}`) : "—"}</span>
                </button>
              </li>
            ))}
          </ul>
        )}
      </section>
      <section className={cn(card, "min-w-0 p-4")}>
        <h3 className="text-sm font-semibold text-foreground">{t("ui.bt.upd.movements")}</h3>
        <p className="mt-0.5 text-xs text-foreground-muted">{t("ui.bt.upd.hint")}</p>
        {!bundle ? (
          <div className="py-10 text-center text-foreground-muted">
            <p className="font-medium text-foreground">{t("ui.bt.upd.pick")}</p>
            <p className="text-xs">{t("ui.bt.upd.pick_hint")}</p>
          </div>
        ) : (
          <div className="mt-3 overflow-x-auto">
            <table className="w-full min-w-[1300px] text-[13px]">
              <thead>
                <tr className="border-b border-border text-left text-foreground-muted">
                  <th className={th}>{t("ui.bt.col.process")}</th>
                  {["operator", "received_at", "start_at", "end_at", "expected_hours", "status", "delay_hrs", "delay_reason"].map((k) => (
                    <th key={k} className={th}>
                      {t(`ui.bt.upd.f.${k}`)}
                    </th>
                  ))}
                  <th />
                </tr>
              </thead>
              <tbody>
                {bundle.movements.map((m) => (
                  <MovementRow key={m.id} m={saved[m.id] ?? m} options={options} canManage={canManage} track={track} onSaved={(row) => setSaved((s) => ({ ...s, [row.id]: row }))} />
                ))}
              </tbody>
            </table>
          </div>
        )}
      </section>
    </div>
  );
}

/** Bundle Lookup: every bundle by cut month, status, code and delay. */
function BundleLookup({ bundles, options }) {
  const t = useT("prototype");
  const [month, setMonth] = useState("");
  const [status, setStatus] = useState("");
  const [query, setQuery] = useState("");
  const [applied, setApplied] = useState("");
  const [delayed, setDelayed] = useState(false);
  const shown = useMemo(() => {
    const q = applied.trim().toLowerCase();
    return bundles.filter(
      (b) =>
        (!month || b.cut_month === month) &&
        (!status || b.bundle_status === status) &&
        (!delayed || b.delayed) &&
        (!q || [b.bundle_no, b.card_no, b.sample_no, b.style].some((v) => (v ?? "").toLowerCase().includes(q))),
    );
  }, [bundles, month, status, applied, delayed]);
  return (
    <div className="flex flex-col gap-4">
      <section className={cn(card, "flex flex-col gap-3 p-4")}>
        <div className="flex flex-wrap items-end gap-3">
          <div className="flex w-48 flex-col gap-1.5">
            <span className="text-[13px] font-medium text-foreground">{t("ui.bt.lookup.month")}</span>
            <Select value={month} onValueChange={(v) => setMonth(v ?? "")} options={[{ value: "", label: t("ui.bt.lookup.all_months") }, ...options.months.map((m) => ({ value: m, label: m }))]} aria-label={t("ui.bt.lookup.month")} />
          </div>
          <div className="flex w-48 flex-col gap-1.5">
            <span className="text-[13px] font-medium text-foreground">{t("ui.bt.lookup.status")}</span>
            <Select value={status} onValueChange={(v) => setStatus(v ?? "")} options={[{ value: "", label: t("ui.bt.lookup.all_status") }, ...options.bundle_statuses.map((s) => ({ value: s, label: t(`bundle_status.${s}`) }))]} aria-label={t("ui.bt.lookup.status")} />
          </div>
          <label className="mb-2 inline-flex items-center gap-1.5 text-[13px]">
            <input type="radio" checked readOnly className="accent-brand" /> {t("ui.bt.lookup.all")}
          </label>
          <div className="ml-auto flex items-center gap-2">
            <div className="w-64">
              <Input value={query} onChange={(e) => setQuery(e.target.value)} onKeyDown={(e) => e.key === "Enter" && setApplied(query)} placeholder={t("ui.bt.lookup.search")} aria-label={t("ui.bt.lookup.search")} />
            </div>
            <Button type="button" variant="outline" onClick={() => setApplied(query)}>
              <Search /> {t("ui.bt.lookup.search_btn")}
            </Button>
            <label className="inline-flex items-center gap-1.5 text-[13px]">
              <Checkbox checked={delayed} onCheckedChange={(c) => setDelayed(c === true)} aria-label={t("ui.bt.lookup.delayed")} /> {t("ui.bt.lookup.delayed")}
            </label>
          </div>
        </div>
        <div className="flex flex-wrap gap-2 border-t border-border pt-3">
          {["", ...options.months].map((m) => (
            <button key={m || "all"} type="button" onClick={() => setMonth(m)} className={cn("rounded-md px-2.5 py-1 text-xs font-medium", month === m ? "bg-brand text-white" : "border border-border hover:bg-surface-muted")}>
              {m || t("ui.bt.lookup.all")}
            </button>
          ))}
        </div>
      </section>
      <section className={cn(card, "p-4")}>
        {shown.length === 0 ? (
          <div className="py-10 text-center text-foreground-muted">
            <p className="font-medium text-foreground">{t("ui.bt.lookup.empty")}</p>
            <p className="text-xs">{t("ui.bt.lookup.empty_hint")}</p>
          </div>
        ) : (
          <div className="overflow-x-auto">
            <table className="w-full text-[13px]">
              <thead>
                <tr className="border-b border-border text-left text-foreground-muted">
                  {["bundle", "card_no", "sample_no", "style", "color", "size", "qty", "now_at", "bundle_status", "movements", "delayed"].map((k) => (
                    <th key={k} className={th}>
                      {t(`ui.bt.lookup.col.${k}`)}
                    </th>
                  ))}
                </tr>
              </thead>
              <tbody>
                {shown.map((b) => (
                  <tr key={b.bundle_no} className="border-b border-border last:border-0">
                    <td className={cn(td, "font-mono")}>{b.bundle_no}</td>
                    <td className={cn(td, "font-mono")}>{b.card_no}</td>
                    <td className={cn(td, "font-mono")}>{b.sample_no ?? "—"}</td>
                    <td className={td}>{b.style ?? "—"}</td>
                    <td className={td}>{b.color ?? "—"}</td>
                    <td className={td}>{b.size ?? "—"}</td>
                    <td className={cn(td, "tabular-nums")}>{b.qty}</td>
                    <td className={td}>{b.now_at ? t(`process.${b.now_at}`) : "—"}</td>
                    <td className={td}>{t(`bundle_status.${b.bundle_status}`)}</td>
                    <td className={cn(td, "tabular-nums")}>{b.movements}</td>
                    <td className={cn(td, b.delayed && "text-danger")}>{b.delayed ? t("ui.yes") : t("ui.no")}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
        <div className="mt-3 flex items-center justify-end gap-3 border-t border-border pt-3 text-[13px] text-foreground-muted">
          <span>{shown.length ? t("ui.shown", { shown: shown.length, total: bundles.length }) : t("ui.bt.lookup.no_match")}</span>
          <Button
            type="button"
            variant="outline"
            size="sm"
            onClick={() =>
              // The bundles the lookup shows, on paper.
              printDocument({
                title: t("ui.bt.lookup.title"),
                orientation: "landscape",
                meta: [[t("ui.bt.lookup.print_count"), String(shown.length)]],
                sections: [
                  {
                    columns: ["bundle", "card_no", "sample_no", "style", "color", "size", "qty", "now_at", "bundle_status", "movements", "delayed"].map((k) => ({ label: t(`ui.bt.lookup.col.${k}`), align: ["qty", "movements"].includes(k) ? "right" : k === "delayed" ? "center" : undefined, nowrap: ["bundle", "card_no", "sample_no"].includes(k) })),
                    rows: shown.map((b) => [b.bundle_no, b.card_no, b.sample_no, b.style, b.color, b.size, b.qty, b.now_at ? t(`process.${b.now_at}`) : null, t(`bundle_status.${b.bundle_status}`), b.movements, b.delayed ? { v: t("ui.yes"), tone: "bad" } : t("ui.no")]),
                  },
                ],
                notes: [t("ui.bt.lookup.note")],
              })
            }
          >
            <Printer /> {t("ui.bt.lookup.print")}
          </Button>
        </div>
      </section>
      <p className="text-[13px] text-foreground-muted">{t("ui.bt.lookup.note")}</p>
    </div>
  );
}

/** One card on the sample-card board: delivered and rejected edited in place, or the whole card handed over. */
function SampleCardRow({ c, canManage, track, onSaved }) {
  const t = useT("prototype");
  const toastManager = useToastManager();
  const [delivered, setDelivered] = useState(String(c.delivered_qty));
  const [rejected, setRejected] = useState(String(c.rejected_qty));
  const [error, setError] = useState(null);
  const [pending, startTransition] = useTransition();
  function send(handOver) {
    setError(null);
    startTransition(async () => {
      const result = await track("deliver", c.card_no, handOver ? { rejected_qty: Number(rejected) || 0, hand_over: true } : { delivered_qty: Number(delivered) || 0, rejected_qty: Number(rejected) || 0 });
      if (result?.status !== "success") return setError(messagesOf(result).join(" "));
      setDelivered(String(result.data.delivered_qty));
      toastManager.add({ title: t("ui.bt.sc.saved", { no: c.card_no, delivered: result.data.delivered_qty, rejected: result.data.rejected_qty }), type: "success" });
      onSaved();
    });
  }
  return (
    <>
      <tr className="border-b border-border">
        <td className={cn(td, "font-mono")}>{c.card_no}</td>
        <td className={cn(td, "font-mono")}>{c.sample_no ?? "—"}</td>
        <td className={td}>{c.sample_types.join(", ") || "—"}</td>
        {["req_qty", "cutting_qty", "sewn_qty", "out_qty"].map((k) => (
          <td key={k} className={cn(td, "tabular-nums")}>
            {c[k]}
          </td>
        ))}
        <td className={cn(td, "w-24")}>
          <Input type="number" min={0} value={delivered} onChange={(e) => setDelivered(e.target.value)} disabled={!canManage} aria-label={`${t("ui.bt.sc.col.delivered_qty")} ${c.card_no}`} />
        </td>
        <td className={cn(td, "w-24")}>
          <Input type="number" min={0} value={rejected} onChange={(e) => setRejected(e.target.value)} disabled={!canManage} aria-label={`${t("ui.bt.sc.col.rejected_qty")} ${c.card_no}`} />
        </td>
        <td className={td}>{t(`bundle_status.${c.bundle_status}`)}</td>
        <td className={cn(td, "whitespace-nowrap text-right")}>
          {canManage ? (
            <>
              <Button type="button" variant="outline" size="sm" onClick={() => send(false)} disabled={pending}>
                <Save /> {t("ui.bt.sc.save")}
              </Button>{" "}
              <Button type="button" variant="outline" size="sm" onClick={() => send(true)} disabled={pending}>
                <PackageCheck /> {t("ui.bt.sc.hand_over")}
              </Button>
            </>
          ) : null}
        </td>
      </tr>
      {error ? (
        <tr>
          <td colSpan={12} className="px-2 pb-2 text-xs text-danger">
            {error}
          </td>
        </tr>
      ) : null}
    </>
  );
}

/** Sample Card: the orders with cards, and on picking one its sample cards. */
function SampleCardBoard({ orders, canManage, track }) {
  const t = useT("prototype");
  const router = useRouter();
  const [query, setQuery] = useState("");
  const [picked, setPicked] = useState(null);
  const shown = orders.filter((o) => o.label.toLowerCase().includes(query.trim().toLowerCase()));
  const order = orders.find((o) => o.order_id === picked);
  return (
    <div className="grid gap-4 lg:grid-cols-[320px_1fr]">
      <section className={cn(card, "flex flex-col gap-3 p-4")}>
        <h3 className="text-sm font-semibold text-foreground">{t("ui.bt.sc.orders")}</h3>
        <div className="relative">
          <Search className="pointer-events-none absolute left-2.5 top-1/2 size-4 -translate-y-1/2 text-foreground-muted" />
          <Input value={query} onChange={(e) => setQuery(e.target.value)} placeholder={t("ui.bt.sc.search")} className="pl-8" aria-label={t("ui.bt.sc.orders")} />
        </div>
        {shown.length === 0 ? (
          <p className="py-6 text-center text-[13px] text-foreground-muted">{t("ui.bt.sc.none")}</p>
        ) : (
          <ul className="flex flex-col gap-1">
            {shown.map((o) => (
              <li key={o.order_id}>
                <button type="button" onClick={() => setPicked(o.order_id)} className={cn("flex w-full items-center justify-between rounded-md px-3 py-2 text-left text-[13px]", picked === o.order_id ? "bg-brand-subtle text-brand" : "hover:bg-surface-muted")}>
                  <span>{o.label}</span>
                  <span className="rounded-full bg-surface-muted px-1.5 text-xs tabular-nums">{o.cards.length}</span>
                </button>
              </li>
            ))}
          </ul>
        )}
      </section>
      <section className={cn(card, "min-w-0 p-4")}>
        <h3 className="text-sm font-semibold text-foreground">{t("ui.bt.sc.cards")}</h3>
        <p className="mt-0.5 text-xs text-foreground-muted">{t("ui.bt.sc.hint")}</p>
        {!order ? (
          <div className="py-10 text-center text-foreground-muted">
            <p className="font-medium text-foreground">{t("ui.bt.sc.pick")}</p>
            <p className="text-xs">{t("ui.bt.sc.pick_hint")}</p>
          </div>
        ) : (
          <div className="mt-3 overflow-x-auto">
            <table className="w-full min-w-[1100px] text-[13px]">
              <thead>
                <tr className="border-b border-border text-left text-foreground-muted">
                  {["card_no", "sample_no", "sample_type", "req_qty", "cutting_qty", "sewn_qty", "out_qty", "delivered_qty", "rejected_qty", "bundle_status"].map((k) => (
                    <th key={k} className={th}>
                      {t(`ui.bt.sc.col.${k}`)}
                    </th>
                  ))}
                  <th />
                </tr>
              </thead>
              <tbody>
                {order.cards.map((c) => (
                  <SampleCardRow key={c.card_no} c={c} canManage={canManage} track={track} onSaved={() => router.refresh()} />
                ))}
              </tbody>
            </table>
          </div>
        )}
      </section>
    </div>
  );
}

export { BundleLookup, SampleCardBoard, ScanDesk, TrackingUpdate };
