"use client";

import { useEffect, useMemo, useRef, useState, useTransition } from "react";
import { useRouter } from "next/navigation";
import { ArrowDown, ArrowUp, CheckCircle2, Eye, EyeOff, FunctionSquare, History, Pencil, Plus, Snowflake, Trash2, X, XCircle } from "lucide-react";
import { cn } from "@/lib/utils";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Textarea } from "@/components/ui/textarea";
import { Modal } from "@/components/ui/modal";
import { ConfirmDialog } from "@/components/ui/confirm-dialog";
import { useToastManager } from "@/components/ui/toast";
import { useT } from "@/lib/i18n";
import { GROUP_TONE, TEXT_TONE, TONE, display } from "./common";

/*
 * Order Summary → Column Setup (AnnotechERP 32): the sheet's groups and
 * columns as the company keeps them. Add Column says which group and after
 * which column, the type and list, and — for a worked-out column — its
 * formula, written with column names in [ ] (click a column or function to
 * put it in). The formula is checked as it is typed: a wrong name or a loop
 * shows at once, and a sample order's result. Colour rules, the summary
 * card and which roles see or edit the column are set here too. Saving a
 * formula works every order row out again; History keeps each change.
 */

const NUMERIC = ["number", "money", "percent", "days"];
const sel = "h-9 w-full rounded-md border border-border bg-surface px-2 text-[13px]";

/**
 * `appliesTo` (Pre-Budget): { both, KNIT, WOVEN } labels — each column then
 * says whether it is a Knit column, a Woven column or both, shown as a badge.
 */
function ColumnSetup({ data, act, appliesTo = null }) {
  const t = useT("ordersummary");
  const router = useRouter();
  const toast = useToastManager();
  const [search, setSearch] = useState("");
  const [editing, setEditing] = useState(null); // column, or { group_id } for a new one
  const [groupEdit, setGroupEdit] = useState(null);
  const [history, setHistory] = useState(null);
  const [deleting, setDeleting] = useState(null);
  const [pending, start] = useTransition();
  const manage = data.can_manage;
  const cols = data.columns.filter((c) => !search || c.label.toLowerCase().includes(search.toLowerCase()) || (c.formula_text ?? "").toLowerCase().includes(search.toLowerCase()));

  const run = (promise, ok) =>
    start(async () => {
      const res = await promise;
      if (res?.status === "success") {
        toast.add({ title: ok(res.data), type: "success" });
        router.refresh();
      } else {
        const first = res?.errors ? Object.values(res.errors)[0]?.[0] : null;
        toast.add({ title: first ?? res?.message ?? t("sheet.error"), type: "danger" });
      }
    });

  return (
    <div className="flex flex-col gap-3 text-[13px]">
      <div className="flex flex-wrap items-center gap-2 rounded-lg border border-border bg-surface p-3">
        <Input className="w-72" value={search} onChange={(e) => setSearch(e.target.value)} placeholder={t("cs.search")} aria-label={t("cs.search")} />
        <span className="text-foreground-muted">{t("cs.count", { n: data.columns.length, f: data.columns.filter((c) => c.formula).length })}</span>
        {/* Excel's Freeze Panes: every column up to this one stays on screen while the sheet scrolls right. */}
        <label className="ml-2 flex items-center gap-2" title={t("cs.freeze_hint")}>
          <Snowflake className="size-4 text-brand" />
          <span className="font-medium">{t("cs.freeze")}</span>
          <select
            className="h-8 rounded-md border border-border bg-surface px-2 text-[13px] disabled:opacity-60"
            value={data.columns.find((c) => c.frozen)?.key ?? ""}
            disabled={!manage || pending}
            onChange={(e) => run(act("freeze", e.target.value || null), () => t("cs.saved"))}
          >
            <option value="">{t("cs.freeze_none")}</option>
            {data.columns.filter((c) => !c.hidden).map((c) => (
              <option key={c.key} value={c.key}>{c.label}</option>
            ))}
          </select>
        </label>
        <div className="ml-auto flex gap-2">
          <Button type="button" variant="outline" size="sm" onClick={() => act("history").then((r) => setHistory(r?.data ?? []))}>
            <History /> {t("cs.history")}
          </Button>
          {manage ? (
            <>
              <Button type="button" variant="outline" size="sm" onClick={() => setGroupEdit({})}>
                <Plus /> {t("cs.add_group")}
              </Button>
              <Button type="button" size="sm" onClick={() => setEditing({ group_id: data.groups[0]?.id })}>
                <Plus /> {t("cs.add_column")}
              </Button>
            </>
          ) : null}
        </div>
      </div>

      {data.groups.map((g) => {
        const list = cols.filter((c) => c.group_id === g.id);
        if (search && !list.length) return null;
        return (
          <section key={g.id} className="rounded-lg border border-border bg-surface">
            <header className={cn("flex items-center gap-2 rounded-t-lg px-3 py-2 font-semibold", GROUP_TONE[g.tone] ?? "bg-[#c9d7ee]", !g.active && "opacity-60")}>
              <span>{g.name}</span>
              <span className="text-[11px] font-normal text-foreground-muted">({list.length})</span>
              {manage ? (
                <div className="ml-auto flex gap-1">
                  <IconButton label={t("cs.move_up")} onClick={() => run(act("saveGroup", { name: g.name, tone: g.tone, active: g.active, move: "up" }, g.id), () => t("cs.saved"))}><ArrowUp /></IconButton>
                  <IconButton label={t("cs.move_down")} onClick={() => run(act("saveGroup", { name: g.name, tone: g.tone, active: g.active, move: "down" }, g.id), () => t("cs.saved"))}><ArrowDown /></IconButton>
                  <IconButton label={t("cs.edit_group")} onClick={() => setGroupEdit(g)}><Pencil /></IconButton>
                  <IconButton label={t("cs.add_column")} onClick={() => setEditing({ group_id: g.id, after: list[list.length - 1]?.key })}><Plus /></IconButton>
                </div>
              ) : null}
            </header>
            <table className="w-full">
              <tbody>
                {list.map((c) => (
                  <tr key={c.key} className={cn("border-t border-border", c.hidden && "text-foreground-subtle")}>
                    <td className="w-64 px-3 py-1.5 font-medium">
                      {c.label}
                      {c.is_system ? null : <span className="ml-2 rounded bg-info-subtle px-1.5 text-[10px] font-semibold text-info">{t("cs.added")}</span>}
                      {c.frozen ? <span className="ml-2 inline-flex items-center gap-0.5 rounded bg-brand-subtle px-1.5 text-[10px] font-semibold text-brand"><Snowflake className="size-3" />{t("cs.frozen")}</span> : null}
                      {appliesTo && c.applies_to ? <span className="ml-2 rounded bg-warning-subtle px-1.5 text-[10px] font-semibold text-warning">{appliesTo[c.applies_to]}</span> : null}
                    </td>
                    <td className="w-28 px-2 py-1.5"><span className="rounded bg-surface-muted px-1.5 py-0.5 text-[11px]">{t(`cs.type.${c.type}`)}</span></td>
                    <td className="px-2 py-1.5 font-mono text-[11px] text-foreground-muted">
                      {c.formula_text ? (
                        <span className="flex items-center gap-1"><FunctionSquare className="size-3.5 shrink-0 text-brand" />= {c.formula_text}</span>
                      ) : c.source ? t("cs.from_list", { list: data.sources.find((s) => s.value === c.source)?.label ?? c.source }) : null}
                    </td>
                    <td className="w-40 px-2 py-1.5 text-right">
                      {(c.color_rules ?? []).map((r, i) => <span key={i} className={cn("ml-0.5 inline-block size-3 rounded-sm align-middle", TONE[r.tone])} title={`${r.op} ${r.value ?? ""}`} />)}
                      {Object.keys(c.role_access ?? {}).length ? <span className="ml-1 rounded bg-warning-subtle px-1.5 text-[10px] font-semibold text-warning">{t("cs.roles_set")}</span> : null}
                    </td>
                    {manage ? (
                      <td className="w-32 px-2 py-1.5 text-right whitespace-nowrap">
                        <IconButton label={t(c.hidden ? "cs.show" : "cs.hide")} onClick={() => run(act("columnVisibility", c.key, !c.hidden), () => t("cs.saved"))}>{c.hidden ? <EyeOff /> : <Eye />}</IconButton>
                        <IconButton label={t("cs.edit")} onClick={() => setEditing(c)}><Pencil /></IconButton>
                        {!c.is_system ? <IconButton label={t("cs.delete")} onClick={() => setDeleting(c)}><Trash2 /></IconButton> : null}
                      </td>
                    ) : null}
                  </tr>
                ))}
              </tbody>
            </table>
          </section>
        );
      })}

      {editing ? <ColumnEditor data={data} column={editing.key ? editing : null} start={editing} act={act} t={t} appliesTo={appliesTo} onClose={() => setEditing(null)} onSaved={(res) => { setEditing(null); toast.add({ title: res.recalculated ? t("cs.saved_recalc", { n: res.recalculated }) : t("cs.saved"), type: "success" }); router.refresh(); }} /> : null}
      {groupEdit ? <GroupEditor group={groupEdit} t={t} pending={pending} onClose={() => setGroupEdit(null)} onSave={(body) => { run(act("saveGroup", body, groupEdit.id), () => t("cs.saved")); setGroupEdit(null); }} /> : null}
      {history ? (
        <Modal open onOpenChange={(o) => !o && setHistory(null)} title={t("cs.history")} className="max-w-4xl">
          <ul className="max-h-[60vh] divide-y divide-border overflow-y-auto text-[12px]">
            {history.length === 0 ? <li className="py-3 text-foreground-muted">{t("cs.no_history")}</li> : null}
            {history.map((h, i) => (
              <li key={i} className="py-2">
                <div className="flex gap-2"><b>{h.column}</b><span className="rounded bg-surface-muted px-1.5 text-[11px]">{t(`cs.action.${h.action}`)}</span>{h.recalculated !== null ? <span className="text-foreground-muted">{t("cs.recalculated", { n: h.recalculated })}</span> : null}<span className="ml-auto text-foreground-muted">{h.by} · {new Date(h.at).toLocaleString("en-GB", { timeZone: "Asia/Dhaka", dateStyle: "medium", timeStyle: "short", hourCycle: "h12" }).replace(/\b[ap]m\b/, (x) => x.toUpperCase())}</span></div>
                {h.before ? <div className="font-mono text-[11px] text-danger">− {h.before}</div> : null}
                {h.after ? <div className="font-mono text-[11px] text-success">+ {h.after}</div> : null}
              </li>
            ))}
          </ul>
        </Modal>
      ) : null}
      <ConfirmDialog open={Boolean(deleting)} onOpenChange={(o) => !o && setDeleting(null)} title={t("cs.delete_title", { name: deleting?.label ?? "" })} description={t("cs.delete_text")} confirmLabel={t("cs.delete")} cancelLabel={t("sheet.cancel")} destructive loading={pending} onConfirm={() => { run(act("deleteColumn", deleting.key), () => t("cs.deleted")); setDeleting(null); }} />
    </div>
  );
}

function IconButton({ label, onClick, children }) {
  return (
    <button type="button" onClick={onClick} aria-label={label} title={label} className="inline-flex size-7 items-center justify-center rounded text-foreground-muted hover:bg-surface-muted hover:text-foreground [&_svg]:size-3.5">
      {children}
    </button>
  );
}

function GroupEditor({ group, t, pending, onClose, onSave }) {
  const [name, setName] = useState(group.name ?? "");
  const [tone, setTone] = useState(group.tone ?? "");
  return (
    <Modal open onOpenChange={(o) => !o && onClose()} title={t(group.id ? "cs.edit_group" : "cs.add_group")}
      footer={<><Button variant="outline" onClick={onClose}>{t("sheet.cancel")}</Button><Button disabled={pending || !name.trim()} onClick={() => onSave({ name, tone: tone || null, active: group.active ?? true })}>{t("sheet.save")}</Button></>}>
      <div className="flex flex-col gap-3">
        <label className="flex flex-col gap-1 font-semibold">{t("cs.group_name")}<Input value={name} onChange={(e) => setName(e.target.value)} /></label>
        <ToneChooser value={tone} onChange={setTone} t={t} group />
      </div>
    </Modal>
  );
}

function ToneChooser({ value, onChange, t, group = false }) {
  return (
    <div className="flex flex-wrap items-center gap-1.5">
      <span className="text-[12px] font-semibold">{t("cs.colour")}</span>
      <button type="button" onClick={() => onChange("")} className={cn("h-6 rounded border px-2 text-[11px]", !value && "ring-2 ring-brand")}>{t("cs.none")}</button>
      {Object.keys(TONE).map((k) => (
        <button key={k} type="button" onClick={() => onChange(k)} aria-label={t(`cs.tone.${k}`)} title={t(`cs.tone.${k}`)} className={cn("size-6 rounded border border-border", group ? GROUP_TONE[k] : TONE[k], value === k && "ring-2 ring-brand ring-offset-1")} />
      ))}
    </div>
  );
}

/** Add / Edit Column, with the formula builder. */
function ColumnEditor({ data, column, start, act, t, appliesTo, onClose, onSaved }) {
  const toast = useToastManager();
  const [f, setF] = useState(() => ({
    label: column?.label ?? "", group_id: column?.group_id ?? start.group_id, after: start.after ?? "", type: column?.type ?? "text",
    source: column?.source ?? "", prefix: column?.prefix ?? "", suffix: column?.suffix ?? "", decimals: column?.decimals ?? "", width: column?.width ?? 130,
    vertical: column?.vertical ?? false, text_tone: column?.text_tone ?? "", bold: column?.bold ?? false, applies_to: column?.applies_to ?? "", kpi: column?.kpi ?? "", pair_reduce: column?.pair_reduce ?? "SUM", formula: column?.formula_text ?? "", color_rules: column?.color_rules ?? [], role_access: { ...(column?.role_access ?? {}) },
  }));
  const [check, setCheck] = useState(null);
  const [errors, setErrors] = useState({});
  const [pending, startT] = useTransition();
  const [pick, setPick] = useState("");
  const area = useRef(null);
  const set = (k, v) => setF((x) => ({ ...x, [k]: v }));
  const system = column?.is_system;
  const groupCols = data.columns.filter((c) => c.group_id === f.group_id && c.key !== column?.key);
  const canFormula = !["dropdown", "multi", "pairs", "colorsize", "image", "system"].includes(f.type);

  // The formula, checked a moment after typing stops.
  useEffect(() => {
    if (!f.formula.trim() || !canFormula) {
      setCheck(null);
      return undefined;
    }
    const id = setTimeout(() => {
      act("checkFormula", { formula: f.formula, type: f.type, decimals: f.decimals === "" ? null : Number(f.decimals), key: column?.key ?? null }).then((r) => setCheck(r?.data ?? null));
    }, 450);
    return () => clearTimeout(id);
  }, [f.formula, f.type, f.decimals, canFormula, act, column?.key]);

  const insert = (text) => {
    const el = area.current;
    const at = el ? el.selectionStart : f.formula.length;
    const end = el ? el.selectionEnd : f.formula.length;
    const next = f.formula.slice(0, at) + text + f.formula.slice(end);
    set("formula", next);
    requestAnimationFrame(() => {
      el?.focus();
      const pos = at + text.length - (text.endsWith("()") ? 1 : 0);
      el?.setSelectionRange(pos, pos);
    });
  };

  const save = () =>
    startT(async () => {
      setErrors({});
      const body = {
        ...f, source: f.source || null, prefix: f.prefix || null, suffix: f.suffix || null, decimals: f.decimals === "" ? null : Number(f.decimals),
        width: Number(f.width) || 130, text_tone: f.text_tone || null, ...(data.bold ? { bold: !!f.bold } : {}), ...(appliesTo ? { applies_to: f.applies_to || null } : {}), kpi: f.kpi || null, formula: canFormula ? f.formula || null : null, after: f.after || null,
        role_access: Object.fromEntries(Object.entries(f.role_access).filter(([, g]) => g && g !== "edit")),
      };
      if (column && !start.moved && f.group_id === column.group_id && !f.after) delete body.after;
      const res = await act("saveColumn", body, column?.key);
      if (res?.status === "success") onSaved(res.data);
      else {
        setErrors(res?.errors ?? {});
        toast.add({ title: res?.message ?? t("sheet.error"), type: "danger" });
      }
    });

  const err = (k) => errors[k]?.[0];
  const columnsToPick = useMemo(() => data.columns.filter((c) => c.key !== column?.key && (!pick || c.label.toLowerCase().includes(pick.toLowerCase()))), [data.columns, column, pick]);

  return (
    <Modal open onOpenChange={(o) => !o && onClose()} title={t(column ? "cs.edit_column" : "cs.add_column")} description={column ? column.label : undefined} className="max-w-5xl"
      footer={<><Button variant="outline" onClick={onClose}>{t("sheet.cancel")}</Button><Button onClick={save} disabled={pending || (check && !check.ok)}>{t("sheet.save")}</Button></>}>
      <div className="grid max-h-[70vh] gap-4 overflow-y-auto pr-1 text-[13px] lg:grid-cols-[1fr_18rem]">
        <div className="flex flex-col gap-3">
          <div className="grid gap-3 sm:grid-cols-2">
            <Field label={t("cs.f.label")} error={err("label")}><Input value={f.label} onChange={(e) => set("label", e.target.value)} maxLength={120} /></Field>
            <Field label={t("cs.f.group")} error={err("group_id")}>
              <select className={sel} value={f.group_id} onChange={(e) => setF((x) => ({ ...x, group_id: e.target.value, after: "" }))}>
                {data.groups.map((g) => <option key={g.id} value={g.id}>{g.name}</option>)}
              </select>
            </Field>
            <Field label={t("cs.f.after")} error={err("after")}>
              <select className={sel} value={f.after} onChange={(e) => set("after", e.target.value)}>
                <option value="">{column ? t("cs.keep_place") : t("cs.at_start")}</option>
                {column ? <option value="__start">{t("cs.at_start")}</option> : null}
                {groupCols.map((c) => <option key={c.key} value={c.key}>{t("cs.after_x", { column: c.label })}</option>)}
              </select>
            </Field>
            <Field label={t("cs.f.type")} error={err("type")}>
              <select className={sel} value={f.type} disabled={system} onChange={(e) => set("type", e.target.value)}>
                {data.types.filter((x) => x !== "system" || f.type === "system").map((x) => <option key={x} value={x}>{t(`cs.type.${x}`)}</option>)}
              </select>
            </Field>
            {["dropdown", "multi"].includes(f.type) ? (
              <Field label={t("cs.f.source")} error={err("source")}>
                <select className={sel} value={f.source} disabled={system} onChange={(e) => set("source", e.target.value)}>
                  <option value="">{f.type === "multi" ? t("cs.free_tags") : "—"}</option>
                  {data.sources.map((s) => <option key={s.value} value={s.value}>{s.label}</option>)}
                </select>
              </Field>
            ) : null}
            {NUMERIC.includes(f.type) ? (
              <>
                <Field label={t("cs.f.decimals")}><Input type="number" min={0} max={6} value={f.decimals} onChange={(e) => set("decimals", e.target.value)} /></Field>
                <Field label={t("cs.f.prefix")}><Input value={f.prefix} onChange={(e) => set("prefix", e.target.value)} maxLength={12} placeholder="$" /></Field>
                <Field label={t("cs.f.suffix")}><Input value={f.suffix} onChange={(e) => set("suffix", e.target.value)} maxLength={12} placeholder="Pcs" /></Field>
                <Field label={t("cs.f.kpi")} error={err("kpi")}>
                  <select className={sel} value={f.kpi} onChange={(e) => set("kpi", e.target.value)}>
                    <option value="">{t("cs.kpi_none")}</option>
                    <option value="SUM">{t("cs.kpi_sum")}</option>
                    <option value="AVG">{t("cs.kpi_avg")}</option>
                  </select>
                </Field>
              </>
            ) : null}
            {f.type === "pairs" ? (
              // Lines of a name and a number (Color with Qty, a price's levels): the number's format, and which number a formula reads.
              <>
                <Field label={t("cs.f.decimals")}><Input type="number" min={0} max={6} value={f.decimals} onChange={(e) => set("decimals", e.target.value)} /></Field>
                <Field label={t("cs.f.prefix")}><Input value={f.prefix} onChange={(e) => set("prefix", e.target.value)} maxLength={12} placeholder="$" /></Field>
                <Field label={t("cs.f.suffix")}><Input value={f.suffix} onChange={(e) => set("suffix", e.target.value)} maxLength={12} placeholder="Pcs" /></Field>
                <Field label={t("cs.f.pair_reduce")} error={err("pair_reduce")}>
                  <select className={sel} value={f.pair_reduce} onChange={(e) => set("pair_reduce", e.target.value)}>
                    <option value="SUM">{t("cs.pair_sum")}</option>
                    <option value="FIRST">{t("cs.pair_first")}</option>
                  </select>
                </Field>
              </>
            ) : null}
            <Field label={t("cs.f.width")}><Input type="number" min={30} max={600} value={f.width} onChange={(e) => set("width", e.target.value)} /></Field>
            {appliesTo ? (
              <Field label={appliesTo.label} error={err("applies_to")}>
                <select className={sel} value={f.applies_to} onChange={(e) => set("applies_to", e.target.value)}>
                  <option value="">{appliesTo.both}</option>
                  <option value="KNIT">{appliesTo.KNIT}</option>
                  <option value="WOVEN">{appliesTo.WOVEN}</option>
                </select>
              </Field>
            ) : null}
            <Field label={t("cs.f.text_tone")} error={err("text_tone")}>
              <select className={cn(sel, f.text_tone && TEXT_TONE[f.text_tone])} value={f.text_tone} onChange={(e) => set("text_tone", e.target.value)}>
                <option value="">{t("cs.text_tone_none")}</option>
                {(data.text_tones ?? []).map((k) => <option key={k} value={k} className={TEXT_TONE[k]}>{t(`cs.text.${k}`)}</option>)}
              </select>
            </Field>
            {data.bold ? (
              <label className={cn("flex items-center gap-2 pt-6", f.bold && "font-bold")}><input type="checkbox" checked={f.bold} onChange={(e) => set("bold", e.target.checked)} /> {t("cs.f.bold")}</label>
            ) : null}
            <label className="flex items-center gap-2 pt-6"><input type="checkbox" checked={f.vertical} onChange={(e) => { set("vertical", e.target.checked); if (e.target.checked && Number(f.width) > 60) set("width", 34); }} /> {t("cs.f.vertical")}</label>
          </div>

          {canFormula ? (
            <Field label={t("cs.f.formula")} error={err("formula")}>
              <Textarea ref={area} rows={3} className="font-mono text-[12px]" value={f.formula} onChange={(e) => set("formula", e.target.value)} placeholder={t("cs.formula_ph")} />
              <div className="mt-1 min-h-6 text-[12px]">
                {!f.formula.trim() ? <span className="text-foreground-muted">{t("cs.formula_none")}</span> : check === null ? <span className="text-foreground-muted">{t("cs.checking")}</span> : check.ok ? (
                  <span className="flex flex-wrap items-center gap-2 text-success"><CheckCircle2 className="size-4" />{t("cs.formula_ok", { reads: check.reads.join(", ") })}
                    {check.sample ? <span className="text-foreground">{t("cs.sample", { no: check.sample.serial_no, result: check.sample.result === null ? "—" : display({ ...f, decimals: f.decimals === "" ? null : Number(f.decimals) }, check.sample.result) })}</span> : null}
                    {check.volatile ? <span className="text-warning">{t("cs.volatile")}</span> : null}
                  </span>
                ) : <span className="flex items-center gap-1 text-danger"><XCircle className="size-4" />{check.error}</span>}
              </div>
            </Field>
          ) : null}

          <div>
            <div className="mb-1 flex items-center gap-2 font-semibold">{t("cs.f.rules")}
              <Button type="button" size="sm" variant="outline" onClick={() => set("color_rules", [...f.color_rules, { op: NUMERIC.includes(f.type) ? "lt" : "contains", value: "", tone: "green" }])}><Plus /> {t("cs.add_rule")}</Button>
            </div>
            {f.color_rules.map((r, i) => {
              const upd = (patch) => set("color_rules", f.color_rules.map((x, j) => (j === i ? { ...x, ...patch } : x)));
              return (
                <div key={i} className="mb-1 flex flex-wrap items-center gap-2">
                  <select className={cn(sel, "w-40")} value={r.op} onChange={(e) => upd({ op: e.target.value })}>{data.rule_ops.map((o) => <option key={o} value={o}>{t(`cs.op.${o}`)}</option>)}</select>
                  {!["any", "blank"].includes(r.op) ? <Input className="w-40" value={r.value ?? ""} onChange={(e) => upd({ value: e.target.value })} aria-label={t("cs.value")} /> : null}
                  {r.op === "between" ? <Input className="w-32" value={r.value2 ?? ""} onChange={(e) => upd({ value2: e.target.value })} aria-label={t("cs.value2")} /> : null}
                  <ToneChooser value={r.tone} onChange={(v) => upd({ tone: v || "gray" })} t={t} />
                  <IconButton label={t("cs.remove_rule")} onClick={() => set("color_rules", f.color_rules.filter((_, j) => j !== i))}><X /></IconButton>
                </div>
              );
            })}
            {errors["color_rules.0.op"] ? <p className="text-[12px] text-danger">{errors["color_rules.0.op"][0]}</p> : null}
          </div>

          <div>
            <div className="mb-1 font-semibold">{t("cs.f.roles")}</div>
            <p className="mb-2 text-[12px] text-foreground-muted">{t("cs.roles_hint")}</p>
            <table className="w-full max-w-xl text-[12px]">
              <tbody>
                {data.roles.map((r) => (
                  <tr key={r.value} className="border-t border-border">
                    <td className="py-1">{r.label}</td>
                    {["edit", "view", "none"].map((g) => (
                      <td key={g} className="py-1"><label className="flex items-center gap-1"><input type="radio" name={`role-${r.value}`} checked={(f.role_access[r.value] ?? "edit") === g} onChange={() => set("role_access", { ...f.role_access, [r.value]: g })} />{t(`cs.grant.${g}`)}</label></td>
                    ))}
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </div>

        {canFormula ? (
          <aside className="flex flex-col gap-2 rounded-md border border-border bg-surface-muted/40 p-2">
            <div className="font-semibold">{t("cs.functions")}</div>
            <div className="flex flex-wrap gap-1">
              {data.functions.map((fn) => <button key={fn} type="button" onClick={() => insert(fn === "TODAY" ? "TODAY()" : `${fn}()`)} title={t(`cs.fn.${fn}`)} className="rounded border border-border bg-surface px-1.5 py-0.5 font-mono text-[11px] hover:border-brand">{fn}</button>)}
            </div>
            <div className="mt-1 font-semibold">{t("cs.columns_pick")}</div>
            <Input value={pick} onChange={(e) => setPick(e.target.value)} placeholder={t("cs.search")} aria-label={t("cs.columns_pick")} />
            <div className="max-h-80 overflow-y-auto">
              {columnsToPick.map((c) => (
                <button key={c.key} type="button" onClick={() => insert(`[${c.label}]`)} className="block w-full truncate rounded px-1.5 py-1 text-left text-[12px] hover:bg-surface">
                  {c.formula ? <FunctionSquare className="mr-1 inline size-3 text-brand" /> : null}{c.label}
                </button>
              ))}
            </div>
            <p className="text-[11px] text-foreground-muted">{t("cs.formula_help")}</p>
          </aside>
        ) : null}
      </div>
    </Modal>
  );
}

function Field({ label, error, children }) {
  return (
    <label className="flex flex-col gap-1 font-semibold">
      {label}
      {children}
      {error ? <span className="text-[12px] font-normal text-danger">{error}</span> : null}
    </label>
  );
}

export { ColumnSetup };
