"use client";

import { useEffect, useState, useTransition } from "react";
import { useRouter } from "next/navigation";
import { CalendarRange, ChevronLeft, ChevronRight, Cpu, Eraser, MapPin, RefreshCw, Save } from "lucide-react";
import { cn } from "@/lib/utils";
import { Select } from "@/components/ui/select";
import { Input } from "@/components/ui/input";
import { Button } from "@/components/ui/button";
import { Modal } from "@/components/ui/modal";
import { useToastManager } from "@/components/ui/toast";
import { useT } from "@/lib/i18n";

const HOUR = 3600 * 1000;
const pad = (n) => String(n).padStart(2, "0");
/** A wall-clock time as the board keeps it: "2026-11-03T06:00". */
const stamp = (d) => `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
const MONTHS = ["Jan", "Feb", "Mar", "Apr", "May", "Jun", "Jul", "Aug", "Sep", "Oct", "Nov", "Dec"];
const label = (d) => `${pad(d.getDate())} ${MONTHS[d.getMonth()]}, ${pad(d.getHours())}:${pad(d.getMinutes())}`;
const kg = (v) => Number(v || 0).toLocaleString("en-US", { maximumFractionDigits: 2 });

/**
 * Dye House → Batch (Bulk) → Dye Batch Plan → Batch Plan Board (AnnotechERP
 * 17/03), as Brickland draws it: machines down the side and the clock across
 * the top, one column per scale step, as wide as the plan's cell width. Pick
 * a plan (Company/Party and S/B narrow the list); the board shows the bars
 * booked in its window and, under it, the cards pulled onto the plan that no
 * machine has hours for yet. Pick a card there, then the hour on a machine,
 * and Place books it; a bar opens to be cleared, which hands its hours back.
 * Cards come onto the plan from a released Fabric Batch Plan (cut into one
 * card per batch) or from those raised by hand, off finished fabric or by a
 * split. Move steps the window, Plan start returns it to the plan's date,
 * Save view keeps scale, width and window for next time. The plan open stays
 * in the address by its code.
 *
 * @param {{ options: object, canManage: boolean, initialCode?: string, actions: { load: Function, pull: Function, place: Function, clear: Function, saveView: Function } }} props
 */
function BatchBoard({ options, canManage, initialCode = null, actions }) {
  const t = useT("dyehouse");
  const router = useRouter();
  const toastManager = useToastManager();
  const [party, setParty] = useState("");
  const [scope, setScope] = useState("");
  const [code, setCode] = useState(initialCode ?? "");
  const [board, setBoard] = useState(null);
  const [view, setView] = useState({ scale: "", width: "", from: "" });
  const [picked, setPicked] = useState(null);
  const [placing, setPlacing] = useState(null);
  const [bar, setBar] = useState(null);
  const [pull, setPull] = useState({ fp: "", cards: "" });
  const [pending, startTransition] = useTransition();
  const b = (k, p) => t(`batch.board.${k}`, p);
  const f = (k) => t(`batch.f.${k}`);
  const warn = (title) => toastManager.add({ title, type: "danger" });
  const problem = (r) => warn(r?.errors ? Object.values(r.errors).flat()[0] : r?.message);

  const plans = options.plans.filter((p) => p.active && (!party || p.party_id === party || !p.party_id) && (!scope || p.scope === scope));

  function show(next, params = {}) {
    startTransition(async () => {
      const r = await actions.load(next, params);
      if (r?.status === "success") {
        setBoard(r.board);
        setView({ scale: String(r.board.window.scale), width: String(r.board.window.width), from: r.board.window.from });
        window.history.replaceState(null, "", `?tab=board&plan=${encodeURIComponent(next)}`);
      } else problem(r);
    });
  }

  useEffect(() => {
    if (initialCode) show(initialCode);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [initialCode]);

  const open = (next) => {
    setCode(next);
    setPicked(null);
    if (next) show(next);
    else setBoard(null);
  };
  const redraw = (over = {}) => show(code, { scale: view.scale, width: view.width, from: view.from, ...over });
  const shift = (sign) => {
    const from = new Date(new Date(view.from).getTime() + sign * board.window.columns * Number(view.scale) * HOUR);
    redraw({ from: stamp(from) });
  };

  function done(r, message) {
    if (r?.status === "success") {
      if (message) toastManager.add({ title: message, type: "success" });
      redraw();
      router.refresh();
      return true;
    }
    problem(r);
    return false;
  }

  function place() {
    startTransition(async () => {
      const r = await actions.place(code, { batch_no: placing.batch.batch_no, machine_id: placing.machine.id, start: placing.start, hours: Number(placing.hours) });
      if (done(r, b("placed", { no: placing.batch.batch_no, mc: placing.machine.machine_no }))) {
        setPlacing(null);
        setPicked(null);
      }
    });
  }

  function clear() {
    startTransition(async () => {
      const r = await actions.clear(code, bar.bar.batch_no);
      if (done(r, b("cleared", { no: bar.bar.batch_no }))) setBar(null);
    });
  }

  function pullIn(payload) {
    startTransition(async () => {
      const r = await actions.pull(code, payload);
      if (done(r, r?.status === "success" ? b("pulled", { n: r.data.pulled.length, code }) : null)) setPull({ fp: "", cards: "" });
    });
  }

  function saveView() {
    startTransition(async () => {
      const r = await actions.saveView(code, { scale: Number(view.scale), width: Number(view.width), from: view.from || null });
      if (r?.status === "success") toastManager.add({ title: b("view_saved"), type: "success" });
      else problem(r);
    });
  }

  const w = board?.window;
  const start = w ? new Date(w.from).getTime() : 0;
  const span = w ? new Date(w.to).getTime() - start : 1;
  const columns = w ? Array.from({ length: w.columns }, (_, i) => new Date(start + i * w.scale * HOUR)) : [];
  const cardsOff = options.batches.filter((x) => !x.on_plan && (!board || x.scope === board.plan.scope));
  const field = (text, content, className) => (
    <label className={cn("flex flex-col gap-1.5", className)}>
      <span className="text-[13px] font-semibold">{text}</span>
      {content}
    </label>
  );

  return (
    <div className="flex flex-col gap-4">
      <div className="rounded-lg border border-border bg-surface p-5">
        <h2 className="flex items-center gap-2 text-base font-semibold">
          <CalendarRange className="size-4" /> {b("title")}
        </h2>
        <p className="mt-0.5 text-[13px] text-foreground-muted">{b("hint")}</p>
        <div className="mt-4 grid gap-3 sm:grid-cols-3 xl:grid-cols-6">
          {field(f("search_plan"), <Select value={code} onValueChange={(v) => open(v ?? "")} options={plans} placeholder={b("select_plan")} />, "xl:col-span-2")}
          {field(f("party"), <Select value={party} onValueChange={(v) => setParty(v ?? "")} options={[{ value: "", label: b("all_companies") }, ...options.companies]} placeholder={b("all_companies")} />)}
          {field(f("scope"), <Select value={scope} onValueChange={(v) => setScope(v ?? "")} options={[{ value: "", label: b("all") }, ...options.scopes.map((s) => ({ value: s, label: t(`batch.opt.scope.${s}`) }))]} placeholder={b("all")} />)}
          {field(f("scale"), <Select value={view.scale} onValueChange={(v) => setView((x) => ({ ...x, scale: v ?? "" }))} options={options.scales.map((s) => ({ value: String(s), label: t(`batch.opt.scale.${s}`) }))} placeholder={b("scale_ph")} disabled={!board} />)}
          {field(f("cell_width"), <Input type="number" min="4" max="60" value={view.width} onChange={(e) => setView((x) => ({ ...x, width: e.target.value }))} disabled={!board} />)}
        </div>
        {board ? (
          <div className="mt-3 flex flex-wrap items-end gap-2">
            {field(
              f("window_from"),
              <Input type="datetime-local" value={view.from} onChange={(e) => setView((x) => ({ ...x, from: e.target.value }))} className="w-56" />,
            )}
            <Button variant="outline" onClick={() => shift(-1)} disabled={pending} aria-label={b("back")}>
              <ChevronLeft />
            </Button>
            <Button variant="outline" onClick={() => shift(1)} disabled={pending} aria-label={b("forward")}>
              {b("move")} <ChevronRight />
            </Button>
            <Button variant="outline" onClick={() => redraw()} disabled={pending}>
              <RefreshCw /> {b("redraw")}
            </Button>
            <Button variant="ghost" onClick={() => redraw({ from: `${board.plan.plan_date}T00:00` })} disabled={pending}>
              {b("plan_start")}
            </Button>
            {canManage ? (
              <Button variant="ghost" onClick={saveView} disabled={pending}>
                <Save /> {b("save_view")}
              </Button>
            ) : null}
            <span className="text-xs text-foreground-muted">{t("batch.h.window_from")}</span>
          </div>
        ) : null}
      </div>

      {!board ? (
        <div className="flex flex-col items-center gap-2 rounded-lg border border-border bg-surface px-4 py-12 text-center">
          <span className="flex size-12 items-center justify-center rounded-lg bg-surface-muted text-foreground-muted">
            <CalendarRange className="size-5" />
          </span>
          <p className="text-sm font-semibold text-foreground">{b("no_plan")}</p>
          <p className="max-w-md text-[13px] text-foreground-muted">{plans.length ? b("no_plan_hint") : b("no_match")}</p>
        </div>
      ) : (
        <>
          <div className="rounded-lg border border-border bg-surface p-5">
            <p className="text-sm font-semibold">
              {b("plan_line", { code: board.plan.code, name: board.plan.name, party: board.plan.party ?? "—", scope: t(`batch.opt.scope.${board.plan.scope}`), date: board.plan.plan_date })}
            </p>
            {board.bars.length === 0 ? <p className="mt-1 text-[13px] text-foreground-muted">{b("nothing", { from: label(new Date(w.from)), to: label(new Date(w.to)) })}</p> : null}
            {picked ? <p className="mt-1 text-[13px] font-medium text-brand">{b("picked", { no: picked.batch_no })}</p> : null}
            {board.machines.length === 0 ? (
              <div className="flex flex-col items-center gap-2 px-4 py-10 text-center">
                <span className="flex size-12 items-center justify-center rounded-lg bg-surface-muted text-foreground-muted">
                  <Cpu className="size-5" />
                </span>
                <p className="text-sm font-semibold text-foreground">{b("no_machine")}</p>
                <p className="max-w-md text-[13px] text-foreground-muted">{b("no_machine_hint")}</p>
              </div>
            ) : (
              <div className="mt-4 overflow-x-auto rounded-md border border-border">
                <div className="min-w-[48rem]">
                  <div className="flex bg-brand text-[12px] font-semibold text-brand-foreground">
                    <div className="w-40 shrink-0 px-3 py-2">{b("machine")}</div>
                    <div className="flex flex-1">
                      {columns.map((c) => (
                        <div key={c.getTime()} className="flex-1 border-l border-brand-foreground/20 px-2 py-2 whitespace-nowrap">
                          {label(c)}
                        </div>
                      ))}
                    </div>
                  </div>
                  {board.machines.map((mc) => (
                    <div key={mc.id} className="flex border-t border-border">
                      <div className="w-40 shrink-0 px-3 py-2">
                        <p className="font-mono text-sm font-semibold">{mc.machine_no}</p>
                        <p className="text-xs text-foreground-muted">{b("capacity", { cap: kg(mc.capacity), unit: mc.unit })}</p>
                      </div>
                      <div className="relative flex flex-1">
                        {columns.map((c) => (
                          <button
                            key={c.getTime()}
                            type="button"
                            disabled={!canManage || !picked}
                            onClick={() => setPlacing({ batch: picked, machine: mc, start: stamp(c), hours: view.scale || String(w.scale) })}
                            className={cn("h-14 flex-1 border-l border-border", canManage && picked ? "hover:bg-brand-subtle" : "cursor-default")}
                            aria-label={`${mc.machine_no} ${label(c)}`}
                          />
                        ))}
                        {board.bars
                          .filter((x) => x.machine_id === mc.id)
                          .map((x) => {
                            const s = Math.max(0, (new Date(x.start).getTime() - start) / span);
                            const e = Math.min(1, (new Date(x.end).getTime() - start) / span);
                            return (
                              <button
                                key={x.batch_no}
                                type="button"
                                onClick={() => setBar({ bar: x, machine: mc })}
                                className="absolute top-2 bottom-2 overflow-hidden rounded-md bg-brand px-2 text-left text-[11px] leading-tight text-brand-foreground shadow-sm"
                                style={{ left: `${s * 100}%`, width: `${Math.max(0.5, (e - s) * 100)}%` }}
                                title={`${x.batch_no} · ${kg(x.qty)} kg`}
                              >
                                <span className="block truncate font-mono font-semibold">{x.batch_no}</span>
                                <span className="block truncate">{[x.colour, `${kg(x.qty)} kg`].filter(Boolean).join(" · ")}</span>
                              </button>
                            );
                          })}
                      </div>
                    </div>
                  ))}
                </div>
              </div>
            )}
            <p className="mt-3 text-[13px] text-foreground-muted">{b("rule")}</p>
          </div>

          <div className="rounded-lg border border-border bg-surface p-5">
            <h3 className="text-base font-semibold">{b("waiting")}</h3>
            <p className="mt-0.5 text-[13px] text-foreground-muted">{b("waiting_hint")}</p>
            {board.waiting.length === 0 ? (
              <p className="mt-3 text-sm text-foreground-muted">{b("all_laid", { code: board.plan.code })}</p>
            ) : (
              <div className="mt-3 flex flex-wrap gap-2">
                {board.waiting.map((x) => (
                  <button
                    key={x.batch_no}
                    type="button"
                    onClick={() => setPicked(picked?.batch_no === x.batch_no ? null : x)}
                    disabled={!canManage}
                    className={cn("rounded-md border px-3 py-2 text-left text-sm", picked?.batch_no === x.batch_no ? "border-brand bg-brand-subtle" : "border-border hover:bg-surface-muted")}
                  >
                    <span className="block font-mono font-semibold">{x.batch_no}</span>
                    <span className="block text-xs text-foreground-muted">
                      {[x.buyer, x.order_no, x.colour, x.group].filter(Boolean).join(" · ")} · {kg(x.qty)} kg
                    </span>
                  </button>
                ))}
              </div>
            )}
            {canManage ? (
              <div className="mt-5 border-t border-border pt-4">
                <h4 className="text-sm font-semibold">{b("pull_title")}</h4>
                <p className="mt-0.5 text-[13px] text-foreground-muted">{b("pull_hint")}</p>
                {options.fp_plans.length === 0 && cardsOff.length === 0 ? (
                  <p className="mt-2 text-[13px] text-foreground-muted">{b("no_pullable")}</p>
                ) : (
                  <div className="mt-3 grid gap-3 sm:grid-cols-2">
                    <div className="flex items-end gap-2">
                      {field(b("pull_fp"), <Select value={pull.fp} onValueChange={(v) => setPull((p) => ({ ...p, fp: v ?? "" }))} options={options.fp_plans} placeholder={b("select")} />, "flex-1")}
                      <Button variant="outline" onClick={() => pullIn({ fp_batch_plan_id: pull.fp })} disabled={pending || !pull.fp}>
                        {b("pull")}
                      </Button>
                    </div>
                    <div className="flex items-end gap-2">
                      {field(b("pull_cards"), <Select value={pull.cards} onValueChange={(v) => setPull((p) => ({ ...p, cards: v ?? "" }))} options={cardsOff.map((x) => ({ value: x.label, label: `${x.label} · ${kg(x.qty)} kg` }))} placeholder={b("select")} />, "flex-1")}
                      <Button variant="outline" onClick={() => pullIn({ batch_nos: [pull.cards] })} disabled={pending || !pull.cards}>
                        {b("pull")}
                      </Button>
                    </div>
                  </div>
                )}
              </div>
            ) : null}
          </div>
        </>
      )}

      <Modal open={placing !== null} onOpenChange={(o) => !o && setPlacing(null)} title={placing ? b("place_title", { no: placing.batch.batch_no }) : ""} description={placing ? `${placing.machine.machine_no} · ${kg(placing.batch.qty)} kg` : ""}>
        {placing ? (
          <div className="flex flex-col gap-4">
            <div className="grid gap-3 sm:grid-cols-2">
              {field(b("start"), <Input type="datetime-local" value={placing.start} onChange={(e) => setPlacing((p) => ({ ...p, start: e.target.value }))} />)}
              {field(b("hours"), <Input type="number" min="0.5" step="0.5" value={placing.hours} onChange={(e) => setPlacing((p) => ({ ...p, hours: e.target.value }))} />)}
            </div>
            <div className="flex justify-end gap-2">
              <Button variant="outline" onClick={() => setPlacing(null)} disabled={pending}>
                {b("cancel")}
              </Button>
              <Button onClick={place} loading={pending}>
                <MapPin /> {b("place")}
              </Button>
            </div>
          </div>
        ) : null}
      </Modal>

      <Modal open={bar !== null} onOpenChange={(o) => !o && setBar(null)} title={bar ? b("bar_title", { no: bar.bar.batch_no, mc: bar.machine.machine_no }) : ""}>
        {bar ? (
          <div className="flex flex-col gap-4 text-sm">
            <p>
              {[bar.bar.buyer, bar.bar.order_no, bar.bar.colour, bar.bar.group].filter(Boolean).join(" · ")} · {kg(bar.bar.qty)} kg
            </p>
            <p className="text-foreground-muted">{b("runs", { from: label(new Date(bar.bar.start)), to: label(new Date(bar.bar.end)) })}</p>
            <div className="flex justify-end gap-2">
              <Button variant="outline" onClick={() => setBar(null)} disabled={pending}>
                {b("cancel")}
              </Button>
              {canManage ? (
                <Button variant="outline" className="text-danger" onClick={clear} loading={pending}>
                  <Eraser /> {b("clear")}
                </Button>
              ) : null}
            </div>
          </div>
        ) : null}
      </Modal>
    </div>
  );
}

export { BatchBoard };
