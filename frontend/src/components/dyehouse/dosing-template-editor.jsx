"use client";

import { useEffect, useState, useTransition } from "react";
import { useRouter } from "next/navigation";
import { ChevronLeft, ChevronRight, FilePlus2, Layers, Plus, RotateCcw, Save, Search, Trash2 } from "lucide-react";
import { cn } from "@/lib/utils";
import { Select } from "@/components/ui/select";
import { Input } from "@/components/ui/input";
import { Checkbox } from "@/components/ui/checkbox";
import { Button } from "@/components/ui/button";
import { ConfirmDialog } from "@/components/ui/confirm-dialog";
import { useToastManager } from "@/components/ui/toast";
import { useT } from "@/lib/i18n";
import { UomSelect } from "@/components/erp/uom-select";

const PAGE = 15;
const blank = () => ({ code: null, name: "", short_name: "", dye_method_id: "", colour_group_id: "", template_type: "", is_finalized: false, agents: [] });
const noEntry = { step_no: "", dye_method_id: "", phase: "", process: "", item_id: "", is_labdip: true, is_line_break: false, dose: "", uom: "" };

/** A template as the server returns it, shaped for the form. */
const toForm = (t) => ({
  ...t,
  short_name: t.short_name ?? "",
  agents: t.agents.map((a) => ({ ...a, step_no: String(a.step_no), dye_method_id: a.dye_method_id ?? "", process: a.process ?? "", dose: a.dose === null ? "" : String(a.dose), uom: a.uom ?? "" })),
});

/**
 * Dye House → Lab-Dip → Dosing Template Master's Detail Template (AnnotechERP
 * 17/02), laid out as Brickland lays it out: the Template List on the left —
 * searched, fifteen to a page — loads a template into the form on the right;
 * the Agents card enters one dosed agent at a time (step, method, phase, the
 * phase's process, the dye or chemical, lab dip / line break, dose and unit)
 * and Adds it to the curve under it, where every line stays editable. Save
 * changes writes the header and its agents together; a new template's code
 * is issued then.
 *
 * @param {{ templates: object[], options: object, load: Function, save: Function, remove: Function, canManage: boolean, initialCode?: string }} props
 */
function DosingTemplateEditor({ templates, options, load, save, remove, canManage, initialCode = null }) {
  const t = useT("dyehouse");
  const router = useRouter();
  const toastManager = useToastManager();
  const [form, setForm] = useState(blank());
  const [entry, setEntry] = useState(noEntry);
  const [query, setQuery] = useState("");
  const [page, setPage] = useState(0);
  const [deleting, setDeleting] = useState(false);
  const [pending, startTransition] = useTransition();
  const l = (k, p) => t(`labdip.${k}`, p);
  const f = (k) => t(`labdip.f.${k}`);
  const c = (k) => t(`labdip.col.${k}`);
  const warn = (title) => toastManager.add({ title, type: "danger" });

  useEffect(() => {
    if (initialCode) open(initialCode);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [initialCode]);

  function open(code) {
    startTransition(async () => {
      const r = await load(code);
      if (r?.status === "success") {
        setForm(toForm(r.template));
        setEntry(noEntry);
      } else warn(r?.message);
    });
  }

  const set = (field, value) => setForm((x) => ({ ...x, [field]: value }));
  const setAgent = (i, patch) => setForm((x) => ({ ...x, agents: x.agents.map((a, j) => (j === i ? { ...a, ...patch } : a)) }));
  const clear = () => {
    setForm(blank());
    setEntry(noEntry);
  };

  const phases = Object.keys(options.processes);
  const phaseOptions = phases.map((p) => ({ value: p, label: l(`opt.phase.${p}`) }));
  // A phase's processes ("Dyeing · Levelling"), or every phase's while none is picked. Picking a
  // process sets its phase too, and the phase select then echoes that back — only a real change of phase clears the process.
  const processOptions = (phase) => (phase ? [phase] : phases).flatMap((p) => options.processes[p].map((x) => ({ value: `${p}:${x}`, label: `${l(`opt.phase.${p}`)} · ${l(`opt.process.${x}`)}` })));
  const pickProcess = (value, apply) => {
    // The select also reports a null when its list narrows to the phase just set; only a pick counts.
    if (!value) return;
    const [phase, process] = value.split(":");
    apply({ phase, process });
  };

  /** Brickland's Add: the entry row becomes a line of the curve, in step order, and the row clears. */
  function addAgent() {
    const step = Number(entry.step_no);
    if (!entry.step_no || !entry.phase || !entry.item_id) return warn(l("tpl.entry_incomplete"));
    if (form.agents.some((a) => Number(a.step_no) === step)) return warn(l("tpl.step_taken", { n: step }));
    const item = options.chemicals.find((x) => x.value === entry.item_id);
    setForm((x) => ({ ...x, agents: [...x.agents, { ...entry, item: item?.label ?? "" }].sort((a, b) => Number(a.step_no) - Number(b.step_no)) }));
    setEntry(noEntry);
  }

  function submit() {
    startTransition(async () => {
      const payload = {
        name: form.name,
        short_name: form.short_name || null,
        dye_method_id: form.dye_method_id || null,
        colour_group_id: form.colour_group_id || null,
        template_type: form.template_type || null,
        is_finalized: form.is_finalized,
        agents: form.agents.map((a) => ({
          step_no: a.step_no === "" ? null : Number(a.step_no),
          dye_method_id: a.dye_method_id || null,
          phase: a.phase || null,
          process: a.process || null,
          item_id: a.item_id || null,
          is_labdip: Boolean(a.is_labdip),
          is_line_break: Boolean(a.is_line_break),
          dose: a.dose === "" ? null : Number(a.dose),
          uom: a.uom || null,
        })),
      };
      const result = await save(form.code, payload);
      if (result?.status === "success") {
        setForm(toForm(result.template));
        toastManager.add({ title: l("tpl.saved", { code: result.template.code }), type: "success" });
        router.refresh();
      } else {
        warn(result?.errors ? Object.values(result.errors).flat()[0] : result?.message);
      }
    });
  }

  function destroy() {
    startTransition(async () => {
      const result = await remove(form.code);
      setDeleting(false);
      if (result?.status === "success") {
        toastManager.add({ title: l("tpl.deleted", { code: form.code }), type: "success" });
        clear();
        router.refresh();
      } else warn(result?.message);
    });
  }

  const needle = query.trim().toLowerCase();
  const shown = templates.filter((x) => !needle || x.name.toLowerCase().includes(needle) || x.code.toLowerCase().includes(needle) || (x.short_name ?? "").toLowerCase().includes(needle));
  const pages = Math.max(1, Math.ceil(shown.length / PAGE));
  const at = Math.min(page, pages - 1);
  const slice = shown.slice(at * PAGE, at * PAGE + PAGE);
  const typeOptions = options.types.map((x) => ({ value: x, label: l(`opt.type.${x}`) }));

  const field = (label, content, { required = false, hint = null, className } = {}) => (
    <label className={cn("flex flex-col gap-1.5", className)}>
      <span className="text-[13px] font-semibold">
        {label}
        {required ? <span className="ml-0.5 text-danger">*</span> : null}
      </span>
      {content}
      {hint ? <span className="text-xs text-foreground-muted">{hint}</span> : null}
    </label>
  );
  const tick = (label, checked, onChange, hint = null) => (
    <label className="flex items-start gap-2 pt-1 text-sm">
      <Checkbox checked={checked} onCheckedChange={(v) => onChange(v === true)} disabled={!canManage} className="mt-0.5" />
      <span>
        <span className="font-semibold">{label}</span>
        {hint ? <span className="block text-xs text-foreground-muted">{hint}</span> : null}
      </span>
    </label>
  );

  return (
    <div className="grid items-start gap-4 xl:grid-cols-[22rem_1fr]">
      <div className="rounded-lg border border-border bg-surface p-4">
        <h2 className="mb-3 text-base font-semibold">{l("tpl.list_title")}</h2>
        <div className="relative mb-3">
          <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-foreground-subtle" />
          <Input
            value={query}
            onChange={(e) => {
              setQuery(e.target.value);
              setPage(0);
            }}
            placeholder={l("tpl.search")}
            className="pl-9"
          />
        </div>
        <ul className="overflow-hidden rounded-md border border-border">
          <li className="bg-brand px-3 py-2 text-[13px] font-semibold text-brand-foreground">{l("tpl.col_template")}</li>
          {slice.length === 0 ? <li className="border-t border-border px-3 py-3 text-sm text-foreground-muted">{l("tpl.none")}</li> : null}
          {slice.map((x) => (
            <li key={x.code}>
              <button
                type="button"
                onClick={() => open(x.code)}
                disabled={pending}
                className={cn("w-full border-t border-border px-3 py-2 text-left text-sm font-medium text-brand", form.code === x.code ? "bg-brand-subtle font-semibold underline" : "hover:bg-surface-muted")}
              >
                {x.name}
              </button>
            </li>
          ))}
        </ul>
        <div className="mt-3 flex items-center justify-between gap-2 text-[13px] text-foreground-muted">
          <span>{l("tpl.showing", { from: shown.length ? at * PAGE + 1 : 0, to: at * PAGE + slice.length, total: shown.length })}</span>
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
        <div className="rounded-lg border border-border bg-surface p-5">
          <div className="mb-4 flex items-start justify-between gap-3">
            <div>
              <h2 className="flex items-center gap-2 text-base font-semibold">
                <Layers className="size-4" /> {l("tpl.title")}
              </h2>
              <p className="mt-0.5 text-[13px] text-foreground-muted">{l("tpl.hint")}</p>
            </div>
            <span className="shrink-0 rounded-full bg-brand-subtle px-2.5 py-0.5 text-xs font-semibold text-brand">{form.code ? l("tpl.editing") : l("tpl.new")}</span>
          </div>
          <div className="grid gap-4 sm:grid-cols-3">
            {field(f("code"), <Input value={form.code ?? ""} placeholder={l("tpl.code_placeholder")} readOnly />)}
            <div className="sm:col-span-2" />
            {field(f("name"), <Input value={form.name} onChange={(e) => set("name", e.target.value)} placeholder="CONVENTIONAL FOR REACTIVE" maxLength={255} disabled={!canManage} />, { required: true, className: "sm:col-span-3" })}
            {field(f("short_name"), <Input value={form.short_name} onChange={(e) => set("short_name", e.target.value)} placeholder="CONV/REACT" maxLength={64} disabled={!canManage} />)}
            {field(f("dyeing_method"), <Select value={form.dye_method_id} onValueChange={(v) => set("dye_method_id", v ?? "")} options={options.methods} placeholder={l("tpl.select_dyeing_method")} disabled={!canManage} />, { required: true })}
            {field(f("colour_group"), <Select value={form.colour_group_id} onValueChange={(v) => set("colour_group_id", v ?? "")} options={options.colour_groups} placeholder={l("tpl.select_group")} disabled={!canManage} />, { required: true, hint: l("tpl.group_hint") })}
            {field(f("template_type"), <Select value={form.template_type} onValueChange={(v) => set("template_type", v ?? "")} options={typeOptions} placeholder={l("tpl.select_type")} disabled={!canManage} />, { required: true })}
            <div className="sm:col-span-2 sm:pt-6">{tick(f("is_finalized"), form.is_finalized, (v) => set("is_finalized", v), l("tpl.finalize_hint"))}</div>
          </div>
        </div>

        <div className="rounded-lg border border-border bg-surface p-5">
          <div className="flex items-start justify-between gap-3">
            <div className="min-w-0 max-w-2xl flex-1">
              <h3 className="text-base font-semibold">{l("tpl.agents")}</h3>
              <p className="mt-0.5 text-[13px] text-foreground-muted">{l("tpl.agents_hint")}</p>
            </div>
            {canManage ? (
              <div className="flex shrink-0 gap-2">
                <Button variant="outline" size="sm" onClick={addAgent}>
                  <Plus /> {l("tpl.add")}
                </Button>
                <Button variant="outline" size="sm" onClick={() => setEntry(noEntry)}>
                  {l("tpl.cancel")}
                </Button>
              </div>
            ) : null}
          </div>
          {canManage ? (
            <div className="mt-3 grid gap-4 sm:grid-cols-3">
              {field(f("step_no"), <Input type="number" min="1" max="999" step="1" value={entry.step_no} onChange={(e) => setEntry((x) => ({ ...x, step_no: e.target.value }))} />)}
              {field(f("dye_method"), <Select value={entry.dye_method_id} onValueChange={(v) => setEntry((x) => ({ ...x, dye_method_id: v ?? "" }))} options={options.methods} placeholder={l("tpl.select_method")} />)}
              {field(f("dye_phase"), <Select value={entry.phase} onValueChange={(v) => setEntry((x) => ((v ?? "") === x.phase ? x : { ...x, phase: v ?? "", process: "" }))} options={phaseOptions} placeholder={l("tpl.select_phase")} />)}
              {field(f("dye_process"), <Select value={entry.process ? `${entry.phase}:${entry.process}` : ""} onValueChange={(v) => pickProcess(v, (patch) => setEntry((x) => ({ ...x, ...patch })))} options={processOptions(entry.phase)} placeholder={l("tpl.select_process")} />)}
              {field(f("agent"), <Select value={entry.item_id} onValueChange={(v) => setEntry((x) => ({ ...x, item_id: v ?? "" }))} options={options.chemicals} placeholder={l("tpl.select_agent")} />)}
              <div className="sm:pt-6">{tick(f("is_labdip"), entry.is_labdip, (v) => setEntry((x) => ({ ...x, is_labdip: v })))}</div>
              <div className="sm:pt-6">{tick(f("is_line_break"), entry.is_line_break, (v) => setEntry((x) => ({ ...x, is_line_break: v })))}</div>
              {field(f("dose"), <Input type="number" min="0" step="0.0001" value={entry.dose} onChange={(e) => setEntry((x) => ({ ...x, dose: e.target.value }))} />)}
              {field(f("unit"), <UomSelect value={entry.uom} onChange={(v) => setEntry((x) => ({ ...x, uom: v }))} aria-label={f("unit")} />)}
            </div>
          ) : null}
          {form.agents.length === 0 ? (
            <Empty title={l("tpl.no_agents_title")} text={l("tpl.no_agents_text")} />
          ) : (
            <>
              <div className="mt-4 overflow-x-auto rounded-md border border-border">
                <table className="w-full min-w-max text-sm">
                  <thead>
                    <tr className="bg-brand text-left text-brand-foreground">
                      <th className="px-3 py-2.5 text-right font-semibold">{f("step_no")}</th>
                      <th className="px-3 py-2.5 font-semibold">{f("dye_method")}</th>
                      <th className="px-3 py-2.5 font-semibold">{c("phase_name")}</th>
                      <th className="px-3 py-2.5 font-semibold">{c("process_name")}</th>
                      <th className="px-3 py-2.5 font-semibold">{c("agent_name")}</th>
                      <th className="px-3 py-2.5 text-center font-semibold">{c("labdip")}</th>
                      <th className="px-3 py-2.5 text-center font-semibold">{c("line_brk")}</th>
                      <th className="px-3 py-2.5 text-right font-semibold">{c("dose_qty")}</th>
                      <th className="px-3 py-2.5 font-semibold">{f("unit")}</th>
                      <th className="px-3 py-2.5 font-semibold">{c("remove")}</th>
                    </tr>
                  </thead>
                  <tbody>
                    {form.agents.map((a, i) => (
                      <tr key={i} className="border-b border-border last:border-0">
                        <td className="px-3 py-1.5">
                          <Input type="number" min="1" max="999" step="1" value={a.step_no} onChange={(e) => setAgent(i, { step_no: e.target.value })} disabled={!canManage} className="w-20 text-right" aria-label={f("step_no")} />
                        </td>
                        <td className="px-3 py-1.5">
                          <Select value={a.dye_method_id} onValueChange={(v) => setAgent(i, { dye_method_id: v ?? "" })} options={options.methods} placeholder="—" disabled={!canManage} className="w-36" aria-label={f("dye_method")} />
                        </td>
                        <td className="px-3 py-1.5">
                          <Select value={a.phase} onValueChange={(v) => v && v !== a.phase && setAgent(i, { phase: v, process: "" })} options={phaseOptions} placeholder="—" disabled={!canManage} className="w-36" aria-label={c("phase_name")} />
                        </td>
                        <td className="px-3 py-1.5">
                          <Select value={a.process ? `${a.phase}:${a.process}` : ""} onValueChange={(v) => pickProcess(v, (patch) => setAgent(i, patch))} options={processOptions(a.phase)} placeholder="—" disabled={!canManage} className="w-48" aria-label={c("process_name")} />
                        </td>
                        <td className="px-3 py-1.5">
                          <Select
                            value={a.item_id}
                            onValueChange={(v) => v && setAgent(i, { item_id: v, item: options.chemicals.find((x) => x.value === v)?.label ?? "" })}
                            options={options.chemicals.some((x) => x.value === a.item_id) ? options.chemicals : [{ value: a.item_id, label: a.item ?? a.item_id }, ...options.chemicals]}
                            placeholder="—"
                            disabled={!canManage}
                            className="w-56"
                            aria-label={c("agent_name")}
                          />
                        </td>
                        <td className="px-3 py-1.5 text-center">
                          <Checkbox checked={Boolean(a.is_labdip)} onCheckedChange={(v) => setAgent(i, { is_labdip: v === true })} disabled={!canManage} aria-label={c("labdip")} />
                        </td>
                        <td className="px-3 py-1.5 text-center">
                          <Checkbox checked={Boolean(a.is_line_break)} onCheckedChange={(v) => setAgent(i, { is_line_break: v === true })} disabled={!canManage} aria-label={c("line_brk")} />
                        </td>
                        <td className="px-3 py-1.5">
                          <Input type="number" min="0" step="0.0001" value={a.dose} onChange={(e) => setAgent(i, { dose: e.target.value })} disabled={!canManage} className="ml-auto w-24 text-right" aria-label={c("dose_qty")} />
                        </td>
                        <td className="px-3 py-1.5">
                          <UomSelect value={a.uom} onChange={(v) => setAgent(i, { uom: v })} disabled={!canManage} compact className="w-28" aria-label={f("unit")} />
                        </td>
                        <td className="px-3 py-1.5">
                          {canManage ? (
                            <Button variant="ghost" size="sm" onClick={() => setForm((x) => ({ ...x, agents: x.agents.filter((_, j) => j !== i) }))} aria-label={`${c("remove")} ${a.step_no}`}>
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
                {l("tpl.steps")} <span className="rounded-md bg-surface-muted px-2 py-0.5 font-semibold text-foreground tabular-nums">{form.agents.length}</span>
              </p>
            </>
          )}
        </div>

        {canManage ? (
          <div className="rounded-lg border border-border bg-surface p-5">
            <p className="text-[13px] text-foreground-muted">{l("tpl.footer")}</p>
            <div className="mt-3 flex gap-2">
              <Button variant="outline" className="text-danger" onClick={() => setDeleting(true)} disabled={pending || !form.code}>
                <Trash2 /> {l("tpl.delete")}
              </Button>
              <Button variant="outline" onClick={clear} disabled={pending}>
                <RotateCcw /> {l("tpl.clear")}
              </Button>
              <Button onClick={submit} loading={pending}>
                <Save /> {l("tpl.save")}
              </Button>
            </div>
          </div>
        ) : null}
      </div>

      <ConfirmDialog
        open={deleting}
        onOpenChange={setDeleting}
        title={l("tpl.delete_title", { name: form.name })}
        description={l("tpl.delete_text")}
        confirmLabel={l("tpl.delete")}
        cancelLabel={l("tpl.cancel")}
        loading={pending}
        onConfirm={destroy}
      />
    </div>
  );
}

/** Brickland's empty list: an icon, a title and why it matters. */
function Empty({ title, text }) {
  return (
    <div className="flex flex-col items-center gap-2 px-4 py-10 text-center">
      <span className="flex size-12 items-center justify-center rounded-lg bg-surface-muted text-foreground-muted">
        <FilePlus2 className="size-5" />
      </span>
      <p className="text-sm font-semibold text-foreground">{title}</p>
      <p className="max-w-sm text-[13px] text-foreground-muted">{text}</p>
    </div>
  );
}

export { DosingTemplateEditor };
