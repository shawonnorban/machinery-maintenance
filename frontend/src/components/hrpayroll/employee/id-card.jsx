"use client";

import { useMemo, useState, useTransition } from "react";
import { Download, Pencil, Plus, Printer, RotateCcw, Save, Search, Trash2 } from "lucide-react";
import { cn } from "@/lib/utils";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Select } from "@/components/ui/select";
import { Checkbox } from "@/components/ui/checkbox";
import { Modal } from "@/components/ui/modal";
import { ConfirmDialog } from "@/components/ui/confirm-dialog";
import { useToastManager } from "@/components/ui/toast";
import { useT } from "@/lib/i18n";
import { ampm } from "@/lib/clock";

/*
 * HR & Payroll → Employee → ID Card (AnnotechERP 24/03), as Brickland lays it
 * out: Records — the Search boxes (employee status, section, joining date,
 * part of a code), the Print Option and Single employee in a page?, then
 * Search, Add (queue everyone the boxes pick), Clear Queue and Print Queue;
 * the queue below with Print, Edit and remove — and Add to print queue for
 * one employee. A card reads the profile at print time: photo, section,
 * designation. Nothing reloads.
 */

const card = "rounded-xl border border-border bg-surface";
const FONTS = ["BANGLA", "ENGLISH", "MTEX_ENGLISH"];
const COLUMNS = ["photo", "code", "name", "section", "designation", "font", "single", "queued_at"];
const esc = (v) => String(v ?? "").replaceAll("&", "&amp;").replaceAll("<", "&lt;").replaceAll(">", "&gt;").replaceAll('"', "&quot;");
const day = (iso) => (iso ? new Date(`${iso}T00:00:00`).toLocaleDateString("en-GB", { day: "2-digit", month: "short", year: "numeric" }) : "");
const BLOOD = { A_POS: "A+", A_NEG: "A−", B_POS: "B+", B_NEG: "B−", AB_POS: "AB+", AB_NEG: "AB−", O_POS: "O+", O_NEG: "O−" };

/** A card's own words — the card's language, whatever the screen's. */
const CARD_WORDS = {
  BANGLA: { id: "আইডি নং", designation: "পদবি", section: "সেকশন", department: "বিভাগ", joining: "যোগদান", blood: "রক্তের গ্রুপ", sign: "কর্তৃপক্ষের স্বাক্ষর" },
  ENGLISH: { id: "ID No", designation: "Designation", section: "Section", department: "Department", joining: "Joining", blood: "Blood Group", sign: "Authorised Signature" },
};
CARD_WORDS.MTEX_ENGLISH = CARD_WORDS.ENGLISH;

function cardHtml(c, org) {
  const w = CARD_WORDS[c.font] ?? CARD_WORDS.ENGLISH;
  const name = c.font === "BANGLA" && c.name_bn ? c.name_bn : c.name;
  const photo = c.photo_file_id ? `<img src="/api/files/${esc(c.photo_file_id)}/download" alt="">` : `<span>No Photo</span>`;
  const lines = [
    [w.id, c.code],
    [w.designation, c.designation],
    [w.section, c.section],
    [w.department, c.department],
    [w.joining, day(c.joining_date)],
    [w.blood, BLOOD[c.blood_group]],
  ].filter(([, v]) => v);
  const facts = lines.map(([k, v]) => `<div class="l"><span>${esc(k)}:</span> <b>${esc(v)}</b></div>`).join("");
  const head = `<div class="h"><div class="o">${esc(org.company)}</div><div class="a">${esc(org.address)}</div></div>`;
  const foot = `<div class="f"><span>${esc(c.card_no)}</span><span class="s">${esc(w.sign)}</span></div>`;
  if (c.font === "MTEX_ENGLISH")
    return `<div class="card v">${head}<div class="b"><div class="p">${photo}</div><div class="n">${esc(name)}</div>${facts}</div>${foot}</div>`;
  return `<div class="card">${head}<div class="b"><div class="i"><div class="n">${esc(name)}</div>${facts}</div><div class="p">${photo}</div></div>${foot}</div>`;
}

/** Print Queue / a row's Print: the cards laid out for the printer — single ones a sheet each, the rest several to an A4. */
function printIdCards(data, title, note) {
  const org = { company: data.company ?? "", address: data.address ?? "" };
  const single = data.cards.filter((c) => c.single);
  const many = data.cards.filter((c) => !c.single);
  const pages = [...single.map((c) => `<section class="page one">${cardHtml(c, org)}</section>`), ...(many.length ? [`<section class="page grid">${many.map((c) => cardHtml(c, org)).join("")}</section>`] : [])].join("");
  const css = `@page{size:A4;margin:10mm}*{box-sizing:border-box}body{margin:0;font-family:"Segoe UI",Arial,"Noto Sans Bengali",sans-serif;background:#e5e7eb;color:#111}
.bar{position:sticky;top:0;display:flex;justify-content:space-between;align-items:center;padding:10px 18px;background:#111827;color:#fff;font-size:13px}.bar b{font-size:14px}.bar button{background:#4f46e5;color:#fff;border:0;border-radius:6px;padding:7px 16px;font-weight:600;cursor:pointer}
.page{width:190mm;margin:12px auto;background:#fff;padding:6mm;min-height:40mm}.page.one{break-after:page}.page.grid{display:grid;grid-template-columns:repeat(2,85.6mm);gap:6mm;justify-content:center}
.card{width:85.6mm;height:54mm;border:1px solid #9ca3af;border-radius:3mm;overflow:hidden;display:flex;flex-direction:column;break-inside:avoid;background:#fff}
.card.v{width:54mm;height:85.6mm}
.h{background:#1f2937;color:#fff;padding:1.6mm 3mm}.o{font-size:9pt;font-weight:700;line-height:1.1}.a{font-size:5.5pt;opacity:.85;line-height:1.2}
.b{flex:1;display:flex;gap:2mm;padding:2mm 3mm}.i{flex:1;min-width:0}.n{font-size:8.5pt;font-weight:700;margin-bottom:.8mm}.l{font-size:6.3pt;line-height:1.45}.l span{color:#4b5563}
.p{width:19mm;height:23mm;flex:none;background:#f3f4f6;border:1px solid #d1d5db;display:flex;align-items:center;justify-content:center;font-size:6pt;color:#6b7280}.p img{width:100%;height:100%;object-fit:cover}
.v .b{flex-direction:column;align-items:center;text-align:center}.v .p{width:24mm;height:29mm}.v .n{margin-top:1mm}
.f{display:flex;justify-content:space-between;align-items:flex-end;padding:0 3mm 1.6mm;font-size:5.5pt;color:#374151}.f .s{border-top:.2mm solid #6b7280;padding-top:.4mm}
@media print{body{background:#fff}.bar{display:none}.page{margin:0;padding:0;width:auto}}`;
  const win = window.open("", "_blank");
  if (!win) return false;
  win.document.write(`<!doctype html><html><head><meta charset="utf-8"><title>${esc(title)}</title><style>${css}</style></head><body><div class="bar"><div><b>${esc(title)}</b><div>${esc(note)}</div></div><button onclick="window.print()">Print</button></div>${pages}</body></html>`);
  win.document.close();
  win.addEventListener("load", () => setTimeout(() => win.print(), 300));
  return true;
}

function Field({ label, required, hint, error, children, className }) {
  return (
    <div className={cn("flex min-w-0 flex-col gap-1.5", className)}>
      <span className="text-[13px] font-semibold">
        {label}
        {required ? <span className="text-danger"> *</span> : null}
      </span>
      {children}
      {error ? <span className="text-xs text-danger">{error}</span> : hint ? <span className="text-xs text-foreground-muted">{hint}</span> : null}
    </div>
  );
}

function FontRadios({ w, value, onChange, name }) {
  return (
    <div className="flex flex-wrap gap-x-6 gap-y-2 rounded-lg border border-border px-4 py-3 text-sm" role="radiogroup" aria-label={w("f.font")}>
      {FONTS.map((f) => (
        <label key={f} className="flex items-center gap-2">
          <input type="radio" name={name} className="size-4 accent-[var(--color-brand)]" checked={value === f} onChange={() => onChange(f)} />
          {w(`font.${f}`)}
        </label>
      ))}
    </div>
  );
}

/**
 * ID Card.
 *
 * @param {{ rows: object[], options: object, canManage: boolean, act: (op: string, ...args: any[]) => Promise<object> }} props
 */
function IdCard({ rows: initial, options, canManage, act }) {
  const t = useT("hrpayroll");
  const w = (k, p) => t(`emp.idc.${k}`, p);
  const toastManager = useToastManager();
  const [rows, setRows] = useState(initial);
  const [tab, setTab] = useState("records");
  const blank = { status: "ACTIVE", section: "", joining_date: "", code: "" };
  const [q, setQ] = useState(blank);
  const [font, setFont] = useState("BANGLA");
  const [single, setSingle] = useState(false);
  const [needle, setNeedle] = useState("");
  const [one, setOne] = useState({ employee: "", font: "MTEX_ENGLISH", single_per_page: false });
  const [errors, setErrors] = useState({});
  const [editing, setEditing] = useState(null);
  const [asking, setAsking] = useState(null);
  const [pending, start] = useTransition();
  const query = (x = q) => Object.fromEntries(Object.entries(x).filter(([, v]) => v));
  const fail = (res) => toastManager.add({ title: Object.values(res?.errors ?? {})[0]?.[0] ?? res?.message ?? "", type: "danger" });

  const reload = (x = q) =>
    start(async () => {
      const res = await act("cards", query(x));
      if (res?.status === "success") setRows(res.data);
    });
  const run = (op, args, done) =>
    start(async () => {
      const res = await act(op, ...args);
      if (res?.status !== "success") return fail(res);
      done?.(res.data);
      const fresh = await act("cards", query());
      if (fresh?.status === "success") setRows(fresh.data);
    });
  const print = (ids, note) =>
    start(async () => {
      const res = await act("cardPrint", ids ? ids.join(",") : "");
      if (res?.status !== "success") return fail(res);
      if (!res.data.cards.length) return toastManager.add({ title: w("queue_empty"), type: "warning" });
      if (!printIdCards(res.data, w("print_title", { n: res.data.cards.length, company: res.data.company ?? "" }), note)) toastManager.add({ title: w("popup_blocked"), type: "warning" });
    });

  const shown = useMemo(
    () => rows.map((r) => ({ ...r, photo_label: r.photo ? w("yes") : w("no_photo"), font_label: w(`font.${r.font}`), single_label: r.single ? w("yes") : w("no") })).filter((r) => !needle || [r.code, r.name, r.section, r.designation, r.font_label].some((v) => String(v ?? "").toLowerCase().includes(needle.toLowerCase()))),
    // eslint-disable-next-line react-hooks/exhaustive-deps
    [rows, needle],
  );
  const exportCsv = () => {
    const text = [COLUMNS.map((c) => w(`col.${c}`)), ...shown.map((r) => [r.photo_label, r.code, r.name, r.section, r.designation, r.font_label, r.single_label, r.queued_at])].map((l) => l.map((v) => `"${String(v ?? "").replaceAll('"', '""')}"`).join(",")).join("\n");
    const link = document.createElement("a");
    link.href = URL.createObjectURL(new Blob([`﻿${text}`], { type: "text/csv;charset=utf-8" }));
    link.download = "id-card-queue.csv";
    link.click();
  };

  return (
    <div className="flex flex-col gap-4">
      <div className="flex flex-wrap gap-1 border-b border-border" role="tablist">
        {[
          ["records", w("records"), rows.length],
          ...(canManage ? [["new", w("add_tab")]] : []),
        ].map(([k, label, count]) => (
          <button key={k} type="button" role="tab" aria-selected={tab === k} onClick={() => setTab(k)} className={cn("-mb-px flex items-center gap-2 border-b-2 px-4 py-2.5 text-sm font-semibold", tab === k ? "border-brand text-brand" : "border-transparent text-foreground-muted hover:text-foreground")}>
            {k === "new" ? <Plus className="size-4" /> : null}
            {label}
            {count !== undefined ? <span className="rounded-full bg-surface-muted px-2 py-0.5 text-[11px] tabular-nums">{count}</span> : null}
          </button>
        ))}
      </div>

      <div hidden={tab !== "records"} className="flex flex-col gap-4">
        <section className={cn(card, "p-5")}>
          <h3 className="flex items-center gap-2 text-[15px] font-semibold">
            <Search className="size-4" /> {w("search")}
          </h3>
          <p className="mb-4 text-[13px] text-foreground-muted">{w("search_note")}</p>
          <div className="grid gap-4 md:grid-cols-3">
            <Field label={w("f.status")}>
              <Select value={q.status} onValueChange={(v) => setQ({ ...q, status: v ?? "" })} options={[{ value: "", label: w("all") }, ...options.statuses.map((s) => ({ value: s, label: t(`emp.prof.opt.status.${s}`) }))]} aria-label={w("f.status")} />
            </Field>
            <Field label={w("f.section")}>
              <Select value={q.section} onValueChange={(v) => setQ({ ...q, section: v ?? "" })} options={[{ value: "", label: w("all") }, ...options.sections]} placeholder={w("all")} aria-label={w("f.section")} />
            </Field>
            <Field label={w("f.joining_date")}>
              <Input type="date" value={q.joining_date} onChange={(e) => setQ({ ...q, joining_date: e.target.value })} aria-label={w("f.joining_date")} />
            </Field>
            <Field label={w("f.code")} hint={w("code_hint")}>
              <Input value={q.code} onChange={(e) => setQ({ ...q, code: e.target.value })} placeholder={w("code_placeholder")} aria-label={w("f.code")} />
            </Field>
            <Field label={w("f.font")} className="md:col-span-3">
              <FontRadios w={w} value={font} onChange={setFont} name="idc-font" />
            </Field>
            <label className="flex items-center gap-2 text-sm font-semibold md:col-span-3">
              <Checkbox checked={single} onCheckedChange={(v) => setSingle(v === true)} aria-label={w("f.single")} /> {w("f.single")}
            </label>
          </div>
          <div className="mt-4 flex flex-wrap items-center justify-between gap-2 border-t border-border pt-4">
            <div className="flex flex-wrap gap-2">
              <Button onClick={() => reload()} loading={pending}>
                <Search /> {w("search")}
              </Button>
              {canManage ? (
                <>
                  <Button variant="outline" onClick={() => run("cardAddPicked", [{ ...query(), font, single_per_page: single }], (d) => toastManager.add({ title: w("queued", { n: d.queued }), type: "success" }))}>
                    {w("add")}
                  </Button>
                  <Button variant="outline" className="text-danger" onClick={() => setAsking({ kind: "clear" })} disabled={!rows.length}>
                    {w("clear")}
                  </Button>
                </>
              ) : null}
              <Button variant="outline" onClick={() => print(null, w("queue_note"))} disabled={!rows.length}>
                <Printer /> {w("print_queue")}
              </Button>
            </div>
            <Button
              variant="outline"
              onClick={() => {
                setQ(blank);
                setFont("BANGLA");
                setSingle(false);
                reload(blank);
              }}
            >
              <RotateCcw /> {w("reset")}
            </Button>
          </div>
        </section>

        <section className={card}>
          <div className="flex flex-wrap items-center justify-between gap-3 px-5 py-4">
            <div className="relative w-full max-w-sm">
              <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-foreground-muted" />
              <Input className="pl-9" type="search" value={needle} onChange={(e) => setNeedle(e.target.value)} placeholder={w("search_records", { n: rows.length })} aria-label={w("search_records", { n: rows.length })} />
            </div>
            <Button variant="outline" onClick={exportCsv} disabled={!shown.length}>
              <Download /> {w("export")}
            </Button>
          </div>
          <div className="overflow-x-auto">
            <table className="w-full min-w-[1000px] text-sm">
              <thead>
                <tr className="bg-brand text-left text-[13px] text-brand-foreground">
                  {COLUMNS.map((c) => (
                    <th key={c} className="px-3 py-2.5 font-semibold whitespace-nowrap">
                      {w(`col.${c}`)}
                    </th>
                  ))}
                  <th className="px-3 py-2.5 text-right font-semibold">{w("col.action")}</th>
                </tr>
              </thead>
              <tbody>
                {shown.length ? (
                  shown.map((r) => (
                    <tr key={r.id} className="border-b border-border last:border-0 hover:bg-surface-muted">
                      <td className={cn("px-3 py-2.5", !r.photo && "text-foreground-muted")}>{r.photo_label}</td>
                      <td className="px-3 py-2.5 font-mono text-xs">{r.code}</td>
                      <td className="px-3 py-2.5 font-medium">{r.name}</td>
                      <td className="px-3 py-2.5">{r.section ?? "—"}</td>
                      <td className="px-3 py-2.5">{r.designation ?? "—"}</td>
                      <td className="px-3 py-2.5">{r.font_label}</td>
                      <td className="px-3 py-2.5">{r.single_label}</td>
                      <td className="px-3 py-2.5 whitespace-nowrap">{ampm(r.queued_at)}</td>
                      <td className="px-3 py-2">
                        <div className="flex justify-end gap-1.5">
                          <Button size="sm" variant="outline" onClick={() => print([r.id], w("card_note"))} aria-label={`${w("print")} ${r.code}`}>
                            <Printer /> {w("print")}
                          </Button>
                          {canManage ? (
                            <>
                              <Button size="sm" variant="outline" onClick={() => setEditing({ ...r, single_per_page: r.single })} aria-label={`${w("edit")} ${r.code}`}>
                                <Pencil /> {w("edit")}
                              </Button>
                              <Button size="sm" variant="outline" onClick={() => setAsking({ kind: "one", row: r })} aria-label={`${w("remove")} ${r.code}`}>
                                <Trash2 className="text-danger" />
                              </Button>
                            </>
                          ) : null}
                        </div>
                      </td>
                    </tr>
                  ))
                ) : (
                  <tr>
                    <td colSpan={COLUMNS.length + 1} className="px-5 py-10 text-center text-sm text-foreground-muted">
                      {w("empty")}
                    </td>
                  </tr>
                )}
              </tbody>
            </table>
          </div>
          <p className="border-t border-border px-5 py-3 text-[13px] text-foreground-muted">{w("summary", { n: shown.length })}</p>
        </section>
      </div>

      {tab === "new" ? (
        <section className={cn(card, "p-5")}>
          <h3 className="text-[15px] font-semibold">{w("new_title")}</h3>
          <p className="mb-4 text-[13px] text-foreground-muted">{w("form_note")}</p>
          <div className="grid gap-4 md:grid-cols-3">
            <Field label={w("f.employee")} required error={errors.employee?.[0]}>
              <Select value={one.employee} onValueChange={(v) => (setOne({ ...one, employee: v ?? "" }), setErrors({}))} options={options.employees} placeholder={w("select")} aria-label={w("f.employee")} />
            </Field>
            <Field label={w("f.font")} required error={errors.font?.[0]}>
              <Select value={one.font} onValueChange={(v) => setOne({ ...one, font: v ?? "" })} options={FONTS.map((f) => ({ value: f, label: w(`font.${f}`) }))} aria-label={w("f.font")} />
            </Field>
            <label className="flex items-start gap-2 pt-7 text-sm">
              <Checkbox checked={one.single_per_page} onCheckedChange={(v) => setOne({ ...one, single_per_page: v === true })} aria-label={w("f.single")} />
              <span>
                <span className="font-semibold">{w("f.single")}</span>
                <span className="block text-xs text-foreground-muted">{w("single_hint")}</span>
              </span>
            </label>
          </div>
          <div className="mt-5 flex justify-end gap-2 border-t border-border pt-4">
            <Button variant="outline" onClick={() => (setOne({ employee: "", font: "MTEX_ENGLISH", single_per_page: false }), setErrors({}))}>
              <RotateCcw /> {w("reset")}
            </Button>
            <Button
              loading={pending}
              onClick={() =>
                start(async () => {
                  const res = await act("cardAdd", one);
                  if (res?.status !== "success") return setErrors(res?.errors ?? { employee: [res?.message ?? ""] });
                  toastManager.add({ title: w("queued", { n: 1 }), type: "success" });
                  setOne({ employee: "", font: one.font, single_per_page: one.single_per_page });
                  const fresh = await act("cards", query());
                  if (fresh?.status === "success") setRows(fresh.data);
                  setTab("records");
                })
              }
            >
              <Save /> {w("add_tab")}
            </Button>
          </div>
        </section>
      ) : null}

      <Modal open={editing !== null} onOpenChange={(o) => !o && setEditing(null)} title={w("edit_title", { code: editing?.code ?? "" })}>
        {editing ? (
          <div className="flex flex-col gap-4">
            <Field label={w("f.font")} required>
              <Select value={editing.font} onValueChange={(v) => setEditing({ ...editing, font: v ?? editing.font })} options={FONTS.map((f) => ({ value: f, label: w(`font.${f}`) }))} aria-label={`${w("f.font")} ${editing.code}`} />
            </Field>
            <label className="flex items-center gap-2 text-sm font-semibold">
              <Checkbox checked={editing.single_per_page} onCheckedChange={(v) => setEditing({ ...editing, single_per_page: v === true })} aria-label={`${w("f.single")} ${editing.code}`} /> {w("f.single")}
            </label>
            <div className="flex justify-end gap-2">
              <Button variant="outline" onClick={() => setEditing(null)}>
                {w("cancel")}
              </Button>
              <Button loading={pending} onClick={() => run("cardUpdate", [editing.id, { font: editing.font, single_per_page: editing.single_per_page }], () => (setEditing(null), toastManager.add({ title: w("updated", { code: editing.code }), type: "success" })))}>
                <Save /> {w("save")}
              </Button>
            </div>
          </div>
        ) : null}
      </Modal>

      <ConfirmDialog
        open={asking !== null}
        onOpenChange={(o) => !o && setAsking(null)}
        title={asking?.kind === "clear" ? w("clear_title") : w("remove_title", { code: asking?.row?.code ?? "" })}
        description={asking?.kind === "clear" ? w("clear_confirm") : w("remove_confirm")}
        confirmLabel={asking?.kind === "clear" ? w("clear") : w("remove")}
        cancelLabel={w("keep")}
        destructive
        loading={pending}
        onConfirm={() => (asking.kind === "clear" ? run("cardClear", [], (d) => (setAsking(null), toastManager.add({ title: w("cleared", { n: d.deleted }), type: "success" }))) : run("cardRemove", [asking.row.id], () => (setAsking(null), toastManager.add({ title: w("removed", { code: asking.row.code }), type: "success" }))))}
      />
    </div>
  );
}

export { IdCard };
