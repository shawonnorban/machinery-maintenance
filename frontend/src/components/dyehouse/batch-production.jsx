"use client";

import { useState, useTransition } from "react";
import { useRouter } from "next/navigation";
import { ChevronLeft, ChevronRight, FilePlus2, Layers, Plus, RotateCcw, Save, ScanLine, Trash2 } from "lucide-react";
import { cn } from "@/lib/utils";
import { Select } from "@/components/ui/select";
import { Input } from "@/components/ui/input";
import { Textarea } from "@/components/ui/textarea";
import { Checkbox } from "@/components/ui/checkbox";
import { Button } from "@/components/ui/button";
import { useToastManager } from "@/components/ui/toast";
import { useT } from "@/lib/i18n";

const PAGE = 15;
const STATUSES = ["LOADED", "RUNNING", "UNLOADED", "ABORTED"];
const today = () => new Date().toLocaleDateString("en-CA", { timeZone: "Asia/Dhaka" });
const blank = () => ({ original_date: "", production_date: today(), requisition_id: "", weight_kg: "", machine_id: "", model_combo: "", lab_dip_ref: "", liquor_ratio: "", bypass_check_roll: false, scouring: false, remarks: "", loads: [] });
const newLoad = () => ({ machine_id: "", load_time: "", unload_time: "", check_roll_at: "", load_type: "Dyeing", status: "" });
const num = (v) => Number(v || 0).toLocaleString("en-US", { maximumFractionDigits: 3 });

/** "3h 20m" between two wall-clock times, or a dash. */
function duration(from, to) {
  if (!from || !to) return "—";
  const mins = Math.round((new Date(to).getTime() - new Date(from).getTime()) / 60000);
  if (!(mins >= 0)) return "—";
  return `${Math.floor(mins / 60)}h ${String(mins % 60).padStart(2, "0")}m`;
}

/** A production as the server returns it, shaped for the form. */
const toForm = (p) => ({
  original_date: p.production_date,
  production_date: p.production_date,
  requisition_id: p.requisition_id ?? "",
  weight_kg: p.weight_kg === null ? "" : String(p.weight_kg),
  machine_id: p.machine_id ?? "",
  model_combo: p.model_combo ?? "",
  lab_dip_ref: p.lab_dip_ref ?? "",
  liquor_ratio: p.liquor_ratio ?? "",
  bypass_check_roll: Boolean(p.bypass_check_roll),
  scouring: Boolean(p.scouring),
  remarks: p.remarks ?? "",
  loads: p.loads.map((l) => ({ machine_id: l.machine_id ?? "", load_time: l.load_time ?? "", unload_time: l.unload_time ?? "", check_roll_at: l.check_roll_at ?? "", load_type: l.load_type ?? "Dyeing", status: l.status ?? "" })),
});

/**
 * Dye House batch production (AnnotechERP 17/04 Sample Batch Production; the
 * same sheet serves bulk in 17/07), laid out as Brickland lays it out: the
 * open cards of the scope on the left, the scan box searching the list a
 * barcode reader types into; the picked card's details filled in on the right
 * — its requisition, lot, shade, buyer, order and method — with what the floor
 * records: the date, weight, machine, model/combo, lab dip and liquor ratio;
 * the approved lab dip recipes for the shade on request; what the card is made
 * of; and the Batch History, one row per load, its duration worked out from
 * the two times. One production per batch per day; an existing one loads back.
 * `bulk` (17/07 Bulk Batch Production) makes the machine, model/combo and lab
 * dip required and adds Brickland's By-Pass Check Roll and Scouring ticks,
 * Remarks, and each load's check-roll time.
 *
 * @param {{ batches: object[], machines: object[], canManage: boolean, load: Function, save: Function, title: string, bulk?: boolean }} props
 */
function BatchProduction({ batches, machines, canManage, load, save, title, bulk = false }) {
  const t = useT("dyehouse");
  const router = useRouter();
  const toastManager = useToastManager();
  const [sheet, setSheet] = useState(null);
  const [form, setForm] = useState(blank());
  const [showRecipe, setShowRecipe] = useState(false);
  const [query, setQuery] = useState("");
  const [page, setPage] = useState(0);
  const [pending, startTransition] = useTransition();
  const p = (k, v) => t(`batch.prod.${k}`, v);
  // The words only the bulk sheet has (17/07).
  const b = (k, v) => t(`prod.sheet.${k}`, v);
  const warn = (text) => toastManager.add({ title: text, type: "danger" });

  function open(no) {
    startTransition(async () => {
      const r = await load(no);
      if (r?.status === "success") {
        setSheet(r.sheet);
        setForm({ ...blank(), machine_id: r.sheet.machine_id ?? "", weight_kg: String(r.sheet.card_qty), loads: [newLoad()] });
        setShowRecipe(false);
      } else warn(r?.message);
    });
  }

  const set = (field, value) => setForm((f) => ({ ...f, [field]: value }));
  const setLoad = (i, patch) => setForm((f) => ({ ...f, loads: f.loads.map((l, j) => (j === i ? { ...l, ...patch } : l)) }));
  const clear = () => {
    setSheet(null);
    setForm(blank());
    setShowRecipe(false);
  };

  function pickExisting(date) {
    if (!date) return setForm({ ...blank(), machine_id: sheet.machine_id ?? "", weight_kg: String(sheet.card_qty), loads: [newLoad()] });
    const found = sheet.productions.find((x) => x.production_date === date);
    if (found) setForm(toForm(found));
  }

  function submit() {
    if (!sheet) return warn(p("pick_first"));
    startTransition(async () => {
      const payload = {
        original_date: form.original_date || null,
        production_date: form.production_date || null,
        requisition_id: form.requisition_id || null,
        weight_kg: form.weight_kg === "" ? null : Number(form.weight_kg),
        machine_id: form.machine_id || null,
        model_combo: form.model_combo || null,
        lab_dip_ref: form.lab_dip_ref || null,
        liquor_ratio: form.liquor_ratio || null,
        ...(bulk ? { bypass_check_roll: form.bypass_check_roll, scouring: form.scouring, remarks: form.remarks || null } : {}),
        loads: form.loads.map((l) => ({ machine_id: l.machine_id || null, load_time: l.load_time || null, unload_time: l.unload_time || null, ...(bulk ? { check_roll_at: l.check_roll_at || null } : {}), load_type: l.load_type || null, status: l.status || null })),
      };
      const r = await save(sheet.batch_no, payload);
      if (r?.status === "success") {
        setSheet(r.sheet);
        const saved = r.sheet.productions.find((x) => x.production_date === form.production_date);
        if (saved) setForm(toForm(saved));
        toastManager.add({ title: p("saved", { no: sheet.batch_no, date: form.production_date }), type: "success" });
        router.refresh();
      } else warn(r?.errors ? Object.values(r.errors).flat()[0] : r?.message);
    });
  }

  const needle = query.trim().toLowerCase();
  const shown = batches.filter((b) => !needle || b.batch_no.toLowerCase().includes(needle) || (b.colour ?? "").toLowerCase().includes(needle));
  const pages = Math.max(1, Math.ceil(shown.length / PAGE));
  const at = Math.min(page, pages - 1);
  const slice = shown.slice(at * PAGE, at * PAGE + PAGE);
  const machineOptions = machines.map((x) => ({ value: x.value, label: x.label }));
  const requisition = sheet?.requisitions.find((r) => r.value === form.requisition_id);
  const statusOptions = [{ value: "", label: "—" }, ...STATUSES.map((s) => ({ value: s, label: t(`batch.opt.load_status.${s}`) }))];

  const field = (label, content, { required = false, hint = null } = {}) => (
    <label className="flex flex-col gap-1.5">
      <span className="text-[13px] font-semibold">
        {label}
        {required ? <span className="ml-0.5 text-danger">*</span> : null}
      </span>
      {content}
      {hint ? <span className="text-xs text-foreground-muted">{hint}</span> : null}
    </label>
  );
  const shownText = (v) => <Input value={v ?? ""} readOnly />;
  const group = (legend, children) => (
    <fieldset className="rounded-lg border border-border p-4">
      <legend className="px-1 text-[13px] font-semibold">{legend}</legend>
      <div className="grid gap-4 sm:grid-cols-3">{children}</div>
    </fieldset>
  );
  const empty = (text) => (
    <div className="flex flex-col items-center gap-2 px-4 py-8 text-center">
      <span className="flex size-12 items-center justify-center rounded-lg bg-surface-muted text-foreground-muted">
        <FilePlus2 className="size-5" />
      </span>
      <p className="text-sm font-semibold text-foreground">{p("no_lines")}</p>
      <p className="max-w-sm text-[13px] text-foreground-muted">{text}</p>
    </div>
  );

  return (
    <div className="grid items-start gap-4 xl:grid-cols-[22rem_1fr]">
      <div className="rounded-lg border border-border bg-surface p-4">
        <h2 className="mb-3 text-base font-semibold">{bulk ? b("list_title") : p("list_title")}</h2>
        <div className="relative mb-3">
          <ScanLine className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-foreground-subtle" />
          <Input
            value={query}
            onChange={(e) => {
              setQuery(e.target.value);
              setPage(0);
            }}
            onKeyDown={(e) => {
              // A barcode reader types the number and Enter: an exact match opens.
              if (e.key === "Enter") {
                const hit = batches.find((b) => b.batch_no.toLowerCase() === needle);
                if (hit) open(hit.batch_no);
              }
            }}
            placeholder={p("search")}
            className="pl-9"
          />
        </div>
        <table className="w-full overflow-hidden rounded-md border border-border text-sm">
          <thead>
            <tr className="bg-brand text-left text-brand-foreground">
              <th className="px-3 py-2 font-semibold">{p("batch_no")}</th>
              <th className="px-3 py-2 font-semibold">{p("colour")}</th>
              <th className="px-3 py-2 text-right font-semibold">{t("batch.f.qty_kg")}</th>
            </tr>
          </thead>
          <tbody>
            {slice.length === 0 ? (
              <tr>
                <td colSpan={3} className="px-3 py-6 text-center text-foreground-muted">
                  {p("none")}
                </td>
              </tr>
            ) : null}
            {slice.map((b) => (
              <tr key={b.batch_no} className={cn("border-t border-border", sheet?.batch_no === b.batch_no ? "bg-brand-subtle" : "hover:bg-surface-muted")}>
                <td className="px-3 py-1.5">
                  <button type="button" onClick={() => open(b.batch_no)} disabled={pending} className="font-mono font-medium text-brand hover:underline">
                    {b.batch_no}
                  </button>
                </td>
                <td className="px-3 py-1.5">{b.colour ?? "—"}</td>
                <td className="px-3 py-1.5 text-right tabular-nums">{num(b.qty)}</td>
              </tr>
            ))}
          </tbody>
        </table>
        <div className="mt-3 flex items-center justify-between gap-2 text-[13px] text-foreground-muted">
          <span>{t("batch.split.showing", { from: shown.length ? at * PAGE + 1 : 0, to: at * PAGE + slice.length, total: shown.length })}</span>
          <span className="flex items-center gap-1">
            <Button variant="outline" size="sm" onClick={() => setPage(at - 1)} disabled={at === 0} aria-label="‹">
              <ChevronLeft />
            </Button>
            <span className="tabular-nums">
              {at + 1} / {pages}
            </span>
            <Button variant="outline" size="sm" onClick={() => setPage(at + 1)} disabled={at >= pages - 1} aria-label="›">
              <ChevronRight />
            </Button>
          </span>
        </div>
      </div>

      <div className="flex flex-col gap-4">
        <div className="flex flex-col gap-4 rounded-lg border border-border bg-surface p-5">
          <div className="flex items-start justify-between gap-3">
            <div>
              <h2 className="flex items-center gap-2 text-base font-semibold">
                <Layers className="size-4" /> {title}
              </h2>
              <p className="mt-0.5 text-[13px] text-foreground-muted">{p("hint")}</p>
            </div>
            <span className="shrink-0 rounded-full bg-brand-subtle px-2.5 py-0.5 text-xs font-semibold text-brand">{form.original_date ? p("editing") : p("new_badge")}</span>
          </div>
          {field(
            p("existing"),
            <Select
              value={form.original_date}
              onValueChange={(v) => pickExisting(v ?? "")}
              options={[{ value: "", label: p("new_production") }, ...(sheet?.productions ?? []).map((x) => ({ value: x.production_date, label: `${x.production_date} · ${x.liquor_ratio}` }))]}
              placeholder={p("new_production")}
              disabled={!sheet}
            />,
            { hint: p("existing_hint") },
          )}
          {group(
            p("section_production"),
            <>
              {field(p("batch_no"), <Input value={sheet?.batch_no ?? ""} placeholder={p("batch_ph")} readOnly />)}
              {field(p("date"), <Input type="date" value={form.production_date} onChange={(e) => set("production_date", e.target.value)} disabled={!canManage} />, { required: true })}
            </>,
          )}
          {group(
            p("section_info"),
            <>
              {field(p("req_no"), <Select value={form.requisition_id} onValueChange={(v) => set("requisition_id", v ?? "")} options={[{ value: "", label: "—" }, ...(sheet?.requisitions ?? [])]} placeholder={p("select_req")} disabled={!canManage || !sheet} />, { hint: p("req_hint") })}
              {field(p("req_date"), shownText(requisition?.date))}
              {field(p("lot"), shownText(sheet?.lot_no))}
              {field(p("weights"), <Input type="number" min="0" step="0.001" value={form.weight_kg} onChange={(e) => set("weight_kg", e.target.value)} disabled={!canManage} />, { hint: p("weights_hint") })}
              {field(p("machine"), <Select value={form.machine_id} onValueChange={(v) => set("machine_id", v ?? "")} options={machineOptions} placeholder={p("select_machine")} disabled={!canManage} />, { required: bulk, hint: machineOptions.length ? null : p("no_choices") })}
              {field(p("model"), <Input value={form.model_combo} onChange={(e) => set("model_combo", e.target.value)} placeholder={t("batch.h.model_ph")} maxLength={120} disabled={!canManage} />, { required: bulk })}
              {field(p("colour"), shownText(sheet?.colour))}
              {field(p("group"), shownText(sheet?.colour_group))}
              {field(p("buyer"), shownText(sheet?.buyer))}
              {field(p("style"), shownText(sheet?.style))}
              {field(p("order"), shownText(sheet?.order_no))}
              {field(p("labdip"), <Input value={form.lab_dip_ref} onChange={(e) => set("lab_dip_ref", e.target.value)} placeholder={p("labdip_ph")} maxLength={40} disabled={!canManage} />, { required: bulk })}
              {field(p("method"), shownText(sheet?.dyeing_method))}
              {field(p("ratio"), <Input value={form.liquor_ratio} onChange={(e) => set("liquor_ratio", e.target.value)} placeholder={p("ratio_ph")} maxLength={16} disabled={!canManage} />, { required: true, hint: bulk ? b("ratio_hint") : null })}
              {bulk ? (
                <>
                  <label className="flex items-start gap-2 text-sm">
                    <Checkbox checked={form.bypass_check_roll} onCheckedChange={(v) => set("bypass_check_roll", v === true)} disabled={!canManage} className="mt-0.5" aria-label={b("bypass")} />
                    <span>
                      <span className="font-semibold">{b("bypass")}</span>
                      <span className="block text-xs text-foreground-muted">{b("bypass_hint")}</span>
                    </span>
                  </label>
                  <label className="flex items-start gap-2 text-sm">
                    <Checkbox checked={form.scouring} onCheckedChange={(v) => set("scouring", v === true)} disabled={!canManage} className="mt-0.5" aria-label={b("scouring")} />
                    <span>
                      <span className="font-semibold">{b("scouring")}</span>
                      <span className="block text-xs text-foreground-muted">{b("scouring_hint")}</span>
                    </span>
                  </label>
                  <label className="flex flex-col gap-1.5 sm:col-span-3">
                    <span className="text-[13px] font-semibold">{b("remarks")}</span>
                    <Textarea value={form.remarks} onChange={(e) => set("remarks", e.target.value)} rows={2} disabled={!canManage} />
                  </label>
                </>
              ) : null}
            </>,
          )}
          <fieldset className="rounded-lg border border-border p-4">
            <legend className="px-1 text-[13px] font-semibold">{p("section_recipe")}</legend>
            <label className="flex items-start gap-2 text-sm">
              <Checkbox checked={showRecipe} onCheckedChange={(v) => setShowRecipe(v === true)} className="mt-0.5" />
              <span>
                <span className="font-semibold">{p("show_recipe")}</span>
                <span className="block text-xs text-foreground-muted">{p("show_recipe_hint")}</span>
              </span>
            </label>
            <div className="mt-3">{field(p("recipe_details"), <Input value={showRecipe && sheet ? (sheet.recipes.length ? sheet.recipes.join("  |  ") : p("no_recipe")) : ""} readOnly />)}</div>
          </fieldset>
        </div>

        <div className="rounded-lg border border-border bg-surface p-5">
          <h3 className="text-base font-semibold">{p("fabrics")}</h3>
          <p className="mt-0.5 text-[13px] text-foreground-muted">{p("fabrics_hint")}</p>
          {!sheet || sheet.items.length === 0 ? (
            empty(p("nothing_loaded"))
          ) : (
            <table className="mt-4 w-full overflow-hidden rounded-md border border-border text-sm">
              <thead>
                <tr className="bg-brand text-left text-brand-foreground">
                  <th className="px-3 py-2 font-semibold">{t("batch.f.kind")}</th>
                  <th className="px-3 py-2 font-semibold">{t("batch.f.description")}</th>
                  <th className="px-3 py-2 text-right font-semibold">{t("batch.f.qty_kg")}</th>
                  <th className="px-3 py-2 text-right font-semibold">{t("batch.f.rolls")}</th>
                </tr>
              </thead>
              <tbody>
                {sheet.items.map((i, k) => (
                  <tr key={k} className="border-t border-border">
                    <td className="px-3 py-1.5">{t(`batch.opt.kind.${i.kind}`)}</td>
                    <td className="px-3 py-1.5">{i.description}</td>
                    <td className="px-3 py-1.5 text-right tabular-nums">{num(i.qty_kg)}</td>
                    <td className="px-3 py-1.5 text-right tabular-nums">{i.rolls || "—"}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          )}
        </div>

        <div className="rounded-lg border border-border bg-surface p-5">
          <div className="flex items-start justify-between gap-3">
            <div className="min-w-0 max-w-2xl flex-1">
              <h3 className="text-base font-semibold">{p("history")}</h3>
              <p className="mt-0.5 text-[13px] text-foreground-muted">{p("history_hint")}</p>
            </div>
            {canManage ? (
              <Button variant="outline" size="sm" onClick={() => setForm((f) => ({ ...f, loads: [...f.loads, newLoad()] }))} disabled={!sheet}>
                <Plus /> {p("add_load")}
              </Button>
            ) : null}
          </div>
          {form.loads.length === 0 ? (
            empty(p("no_loads_hint"))
          ) : (
            <>
              <div className="mt-4 overflow-x-auto rounded-md border border-border">
                <table className="w-full min-w-max text-sm">
                  <thead>
                    <tr className="bg-brand text-left text-brand-foreground">
                      <th className="px-3 py-2.5 font-semibold">{p("machine")}</th>
                      <th className="px-3 py-2.5 font-semibold">{p("load_time")}</th>
                      <th className="px-3 py-2.5 font-semibold">{p("unload_time")}</th>
                      <th className="px-3 py-2.5 font-semibold">{p("duration")}</th>
                      {bulk ? <th className="px-3 py-2.5 font-semibold">{b("check_roll_at")}</th> : null}
                      <th className="px-3 py-2.5 font-semibold">{p("type")}</th>
                      <th className="px-3 py-2.5 font-semibold">{p("status")}</th>
                      <th className="w-10 px-3 py-2.5" />
                    </tr>
                  </thead>
                  <tbody>
                    {form.loads.map((l, i) => (
                      <tr key={i} className="border-b border-border last:border-0">
                        <td className="px-3 py-1.5">
                          <Select value={l.machine_id} onValueChange={(v) => setLoad(i, { machine_id: v ?? "" })} options={[{ value: "", label: "—" }, ...machineOptions]} placeholder="—" disabled={!canManage} className="w-36" aria-label={`${p("machine")} ${i + 1}`} />
                        </td>
                        <td className="px-3 py-1.5">
                          <Input type="datetime-local" value={l.load_time} onChange={(e) => setLoad(i, { load_time: e.target.value })} disabled={!canManage} className="w-52" aria-label={`${p("load_time")} ${i + 1}`} />
                        </td>
                        <td className="px-3 py-1.5">
                          <Input type="datetime-local" value={l.unload_time} onChange={(e) => setLoad(i, { unload_time: e.target.value })} disabled={!canManage} className="w-52" aria-label={`${p("unload_time")} ${i + 1}`} />
                        </td>
                        <td className="px-3 py-1.5 tabular-nums">{duration(l.load_time, l.unload_time)}</td>
                        {bulk ? (
                          <td className="px-3 py-1.5">
                            <Input type="datetime-local" value={l.check_roll_at} onChange={(e) => setLoad(i, { check_roll_at: e.target.value })} disabled={!canManage} className="w-52" aria-label={`${b("check_roll_at")} ${i + 1}`} />
                          </td>
                        ) : null}
                        <td className="px-3 py-1.5">
                          <Input value={l.load_type} onChange={(e) => setLoad(i, { load_type: e.target.value })} maxLength={24} disabled={!canManage} className="w-32" aria-label={`${p("type")} ${i + 1}`} />
                        </td>
                        <td className="px-3 py-1.5">
                          <Select value={l.status} onValueChange={(v) => setLoad(i, { status: v ?? "" })} options={statusOptions} placeholder="—" disabled={!canManage} className="w-36" aria-label={`${p("status")} ${i + 1}`} />
                        </td>
                        <td className="px-3 py-1.5">
                          {canManage ? (
                            <Button variant="ghost" size="sm" onClick={() => setForm((f) => ({ ...f, loads: f.loads.filter((_, j) => j !== i) }))} aria-label={p("remove")}>
                              <Trash2 />
                            </Button>
                          ) : null}
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
              <p className="mt-3 flex items-center gap-2 text-[13px] text-foreground-muted">
                {p("loads")} <span className="rounded-md bg-surface-muted px-2 py-0.5 font-semibold text-foreground tabular-nums">{form.loads.length}</span>
              </p>
            </>
          )}
        </div>

        {canManage ? (
          <div className="rounded-lg border border-border bg-surface p-5">
            <p className="text-[13px] text-foreground-muted">{p("footer")}</p>
            <div className="mt-3 flex gap-2">
              <Button variant="outline" onClick={clear} disabled={pending}>
                <RotateCcw /> {p("clear")}
              </Button>
              <Button onClick={submit} loading={pending}>
                <Save /> {p("save")}
              </Button>
            </div>
          </div>
        ) : null}
      </div>
    </div>
  );
}

export { BatchProduction };
