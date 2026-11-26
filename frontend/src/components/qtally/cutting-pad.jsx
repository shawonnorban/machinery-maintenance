"use client";

import { useCallback, useEffect, useRef, useState } from "react";
import { CheckCircle2, ScanLine, ShieldAlert, Undo2, Wrench, XCircle } from "lucide-react";
import { cn } from "@/lib/utils";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { useQt, num, Notice, Facts } from "./common";

/*
 * Quality Tally → Tally Entry, on a cutting table (user, 2026-11-25): the
 * cutting manager gives the bundle cards as input; the QC only checks. Scan
 * the card (or pick one of today's bundles on this table) — job, size, serial,
 * quantity and lot come with it — pick the panel in hand and tap: RFT, Defect
 * (with its codes), Rectify or Reject. The Cut Panel Inspection report and the
 * pieces that move on to sewing are built from these taps.
 */

const BUTTONS = [
  { key: "RFT", icon: CheckCircle2, cls: "bg-success hover:bg-success/90", ring: "ring-success" },
  { key: "DEFECT", icon: ShieldAlert, cls: "bg-warning hover:bg-warning/90", ring: "ring-warning" },
  { key: "RECTIFY", icon: Wrench, cls: "bg-info hover:bg-info/90", ring: "ring-info" },
  { key: "REJECT", icon: XCircle, cls: "bg-danger hover:bg-danger/90", ring: "ring-danger" },
];

function CuttingPad({ ctx, line, pick, onPick, summary, ready, flash, onAction, onUndo, call }) {
  const p = useQt();
  const [code, setCode] = useState("");
  const [msg, setMsg] = useState(null);
  const [today, setToday] = useState([]);
  const scanRef = useRef(null);
  const bundle = pick.bundle ?? null;
  const garmentPanels = (ctx.garment_types ?? []).find((g) => g.name === bundle?.garment_type)?.panels ?? [];
  const panels = bundle?.panel_id ? garmentPanels.filter((x) => x.id === bundle.panel_id) : garmentPanels;
  const counts = summary?.cutting?.panels ?? {};
  const c = summary?.cutting;
  const cur = counts[pick.panel] ?? {};
  const job = ctx.jobs.find((j) => j.id === bundle?.job_id);

  const loadToday = useCallback(() => {
    call(`cut-bundles?${new URLSearchParams({ line_id: line.line_id })}`)
      .then((d) => setToday(d.rows ?? []))
      .catch(() => {});
  }, [call, line.line_id]);
  // Read again on each change of bundle, so the list shows what has been checked since.
  const bundleId = bundle?.id;
  useEffect(loadToday, [loadToday, bundleId]);
  // Panels to check for one piece of the bundle: a paired part (Sleeve) counts two.
  const panelsOf = (b) => {
    const all = (ctx.garment_types ?? []).find((g) => g.name === b.garment_type)?.panels ?? [];
    return (b.panel_id ? all.filter((x) => x.id === b.panel_id) : all).reduce((a, x) => a + (x.per_piece ?? 1), 0) || 1;
  };
  const holds = (pn) => bundle.qty * (pn?.per_piece ?? 1);
  const checkedOf = (b) => (b.id === bundleId && c ? Object.values(counts).reduce((a, n) => a + (n.checked ?? 0), 0) : b.checked);

  const take = (b) => {
    const firstPanel = b.panel_id || ((ctx.garment_types ?? []).find((g) => g.name === b.garment_type)?.panels?.[0]?.id ?? "");
    onPick({ bundle: b, job: b.job_id, panel: firstPanel });
    setMsg(null);
  };

  const scan = async (e) => {
    e?.preventDefault();
    const value = code.trim();
    if (!value) return;
    try {
      const res = await call(`cut-bundles/scan?${new URLSearchParams({ code: value })}`);
      if (res.found === "INPUT") {
        if (res.bundle.line_id && res.bundle.line_id !== line.line_id) setMsg({ tone: "danger", text: p("cut.other_table", { line: res.bundle.line ?? "" }) });
        else take(res.bundle);
      } else if (res.found === "CARD") setMsg({ tone: "warning", text: p("cut.not_input", { no: res.card.bundle_no }) });
      else setMsg({ tone: "danger", text: p("cut.not_found", { code: value }) });
    } catch (err) {
      setMsg({ tone: "danger", text: err.message });
    }
    setCode("");
    scanRef.current?.focus();
  };

  return (
    <div className="flex flex-col gap-4">
      {/* Scan the bundle card — a scanner types the code and Enter. */}
      <form onSubmit={scan} className="flex flex-wrap items-end gap-3 rounded-lg border border-border bg-surface p-4">
        <label className="min-w-64 flex-1 text-[13px] font-semibold text-foreground">
          {p("cut.scan")}
          <span className="relative mt-1.5 block">
            <ScanLine className="pointer-events-none absolute top-1/2 left-3 size-5 -translate-y-1/2 text-foreground-subtle" />
            <Input ref={scanRef} autoFocus value={code} onChange={(e) => setCode(e.target.value)} placeholder={p("cut.scan_hint")} className="h-12 pl-11 text-lg" />
          </span>
        </label>
        <Button type="submit" className="h-12">{p("cut.open_bundle")}</Button>
        {today.length ? (
          <div className="w-full">
            <p className="mb-1.5 text-xs text-foreground-muted">{p("cut.today_bundles")}</p>
            <div className="flex flex-wrap gap-1.5">
              {today.slice(0, 40).map((b) => (
                <button
                  key={b.id}
                  type="button"
                  onClick={() => take(b)}
                  className={cn("rounded-md border px-2.5 py-1 text-xs font-semibold", bundle?.id === b.id ? "border-brand bg-brand-subtle text-brand" : "border-border bg-surface hover:border-border-strong")}
                >
                  {b.bundle_no}
                  <span className="ml-1 font-normal text-foreground-muted">{[b.size, b.panel].filter(Boolean).join(" · ")} · {num(checkedOf(b))}/{num(b.qty * panelsOf(b))}</span>
                </button>
              ))}
            </div>
          </div>
        ) : null}
      </form>
      {msg ? <Notice tone={msg.tone}>{msg.text}</Notice> : null}

      {!bundle ? (
        <Notice tone="info">{p("cut.scan_first")}</Notice>
      ) : (
        <>
          <Facts
            items={[
              [p("col.bundle_no"), bundle.bundle_no], [p("f.job"), job ? `${job.job_no} · ${job.style}` : bundle.job_no], [p("col.size"), bundle.size || "—"],
              [p("col.bundle_serial"), bundle.serial ?? "—"], [p("col.bundle_qty"), num(bundle.qty)], [p("col.garment_type"), bundle.garment_type],
              [p("col.cutting_no"), bundle.cutting_no || "—"], [p("col.batch_no"), bundle.batch_no || "—"],
            ]}
          />

          {panels.length === 0 ? <Notice>{p("cpi.no_panels")}</Notice> : null}
          <div className="grid grid-cols-2 gap-2 sm:grid-cols-3 lg:grid-cols-6">
            {panels.map((pn) => {
              const n = counts[pn.id] ?? {};
              const on = pick.panel === pn.id;
              const full = (n.checked ?? 0) >= holds(pn);
              return (
                <button
                  key={pn.id}
                  type="button"
                  onClick={() => onPick({ panel: pn.id })}
                  className={cn("flex min-h-16 flex-col items-start justify-center rounded-lg border-2 px-3 py-2 text-left transition", on ? "border-brand bg-brand-subtle" : "border-border bg-surface hover:border-border-strong", full && !on && "opacity-70")}
                >
                  <span className="text-sm font-bold text-foreground">{pn.name}{pn.per_piece > 1 ? <span className="ml-1 font-semibold text-brand">×{pn.per_piece}</span> : null}</span>
                  <span className="text-xs tabular-nums text-foreground-muted">{p("cut.panel_counts", { checked: num(n.checked ?? 0), qty: num(holds(pn)), rectify: num(n.rectify ?? 0), reject: num(n.reject ?? 0) })}</span>
                </button>
              );
            })}
          </div>

          {ready ? (
            <div className="grid grid-cols-2 gap-3 lg:grid-cols-4">
              {BUTTONS.map(({ key, icon: Icon, cls, ring }) => (
                <button
                  key={key}
                  type="button"
                  onClick={() => onAction(key)}
                  className={cn("flex min-h-36 flex-col items-center justify-center gap-2 rounded-2xl p-4 text-white shadow-sm transition select-none active:scale-[0.98]", cls, flash === key && `ring-4 ring-offset-2 ${ring}`)}
                >
                  <Icon className="size-9" aria-hidden />
                  <span className="text-xl font-bold tracking-wide">{p(`action.${key}`)}</span>
                  <span className="text-3xl font-black tabular-nums">{num(cur[key.toLowerCase()] ?? (key === "RFT" ? Math.max(0, (cur.checked ?? 0) - (cur.defect ?? 0)) : 0))}</span>
                  <span className="text-xs opacity-90">{p(`action_hint.${key}`)}</span>
                </button>
              ))}
            </div>
          ) : (
            <Notice tone="info">{p("cut.pick_panel")}</Notice>
          )}

          <div className="grid grid-cols-2 gap-3 sm:grid-cols-4">
            <Mini label={p("cut.panel_checked")} value={`${num(cur.checked ?? 0)} / ${num(holds(panels.find((x) => x.id === pick.panel)))}`} />
            <Mini label={p("kpi.pass")} value={num(cur.pass ?? 0)} cls="text-success" />
            <Mini label={p("cpi.checked_panels")} value={num(c?.lot_checked ?? 0)} />
            <Mini label={p("cpi.reject_panels")} value={num(c?.lot_reject ?? 0)} cls="text-danger" />
          </div>
          <div>
            <Button variant="outline" size="lg" onClick={onUndo} disabled={!summary?.mine}>
              <Undo2 /> {p("btn.undo")}
            </Button>
          </div>
        </>
      )}
    </div>
  );
}

function Mini({ label, value, cls }) {
  return (
    <div className="rounded-lg border border-border bg-surface px-3 py-2.5">
      <p className="text-[11px] font-semibold tracking-wide text-foreground-muted uppercase">{label}</p>
      <p className={cn("mt-1 text-2xl font-bold text-foreground tabular-nums", cls)}>{value}</p>
    </div>
  );
}

export { CuttingPad };
