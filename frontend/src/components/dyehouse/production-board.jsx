"use client";

import { useEffect, useState, useTransition } from "react";
import { useRouter } from "next/navigation";
import { RotateCcw, Save } from "lucide-react";
import { cn } from "@/lib/utils";
import { Select } from "@/components/ui/select";
import { Input } from "@/components/ui/input";
import { Textarea } from "@/components/ui/textarea";
import { Button } from "@/components/ui/button";
import { useToastManager } from "@/components/ui/toast";
import { Field, Group, EditorHead, EditorFoot, LinesCard, usePaper } from "@/components/dyehouse/chem-store";
import { useT } from "@/lib/i18n";

const today = () => new Date().toLocaleDateString("en-CA", { timeZone: "Asia/Dhaka" });
const num = (v, d = 3) => Number(v || 0).toLocaleString("en-US", { maximumFractionDigits: d });
const blankText = (v) => (v === "" || v === undefined ? null : v);
const firstError = (res) => (res?.errors ? Object.values(res.errors).flat()[0] : res?.message);
const STATUSES = ["DRAFT", "SUBMITTED", "APPROVED", "CLOSED", "CANCELLED"];
const PROD_TYPES = ["BULK", "SAMPLE"];

/** "3h 05m" from minutes, or a dash. */
const hours = (mins) => (mins === null || mins === undefined || mins < 0 ? "—" : `${Math.floor(mins / 60)}h ${String(mins % 60).padStart(2, "0")}m`);
const minutesBetween = (from, to) => (from && to ? Math.round((new Date(to).getTime() - new Date(from).getTime()) / 60000) : null);

/**
 * Machine Wash Output (AnnotechERP 17/07): load a machine wash requisition
 * (17/05) — only ones not produced against yet, or the one being reworked —
 * and record what was actually dosed. Its details read off the requisition;
 * each line in kilos, grams and milligrams as the operator read them,
 * totalled onto one scale, beside what the sub-store and the other stores
 * hold now. One production per requisition.
 *
 * @param {{ productions: object[], options: object, canManage: boolean, load: Function, save: Function, backHref: string, initialNo?: string }} props
 */
function WashProductionEditor({ productions, options, canManage, load, save, backHref, initialNo = null }) {
  const t = useT("dyehouse");
  const router = useRouter();
  const toastManager = useToastManager();
  const c = (k, p) => t(`prod.${k}`, p);
  const blank = () => ({ req_no: "", production_date: today(), party_id: options.companies[0]?.value ?? "", department_id: "", machine_id: "", requisition_to: "", remarks: "", lines: [] });
  const [form, setForm] = useState(blank);
  const [sheet, setSheet] = useState(null);
  const [existing, setExisting] = useState(false);
  const [entry, setEntry] = useState(null);
  const [pending, startTransition] = useTransition();
  const warn = (title) => toastManager.add({ title, type: "danger" });
  const set = (k, v) => setForm((f) => ({ ...f, [k]: v }));
  const noEntry = { item_id: "", kg: "", gm: "", mg: "" };
  const e = entry ?? noEntry;

  useEffect(() => {
    if (initialNo) pick(initialNo);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [initialNo]);

  /** Load a requisition: its details, and its production — or its lines as the starting dose. */
  function pick(no) {
    if (!no) {
      setForm(blank());
      setSheet(null);
      setExisting(false);
      return;
    }
    startTransition(async () => {
      const res = await load(no);
      if (res?.status !== "success") return warn(res?.message);
      const s = res.sheet;
      setSheet(s);
      setExisting(Boolean(s.production));
      const p = s.production;
      setForm({
        req_no: no,
        production_date: p?.production_date ?? today(),
        party_id: p?.party_id ?? options.companies[0]?.value ?? "",
        department_id: p?.department_id ?? "",
        machine_id: p?.machine_id ?? s.requisition.machine_id ?? "",
        requisition_to: p?.requisition_to ?? "",
        remarks: p?.remarks ?? "",
        lines: (p?.lines ?? s.requisition.lines.map((l) => ({ ...l, kg: l.required_qty, gm: 0, mg: 0 }))).map((l) => ({
          item_id: l.item_id,
          item: l.item,
          kg: String(l.kg ?? ""),
          gm: String(l.gm ?? ""),
          mg: String(l.mg ?? ""),
          sub_stock: l.sub_stock,
          central_stock: l.central_stock,
        })),
      });
    });
  }

  function add() {
    const total = Number(e.kg || 0) + Number(e.gm || 0) / 1000 + Number(e.mg || 0) / 1000000;
    if (!e.item_id || !(total > 0)) return warn(c("wash.line_incomplete"));
    const it = options.chemicals.find((x) => x.value === e.item_id);
    setForm((f) => ({ ...f, lines: [...f.lines, { ...e, item: it?.label ?? "", sub_stock: null, central_stock: null }] }));
    setEntry(null);
  }

  function submit() {
    if (!form.req_no) return warn(c("wash.pick_first"));
    startTransition(async () => {
      const res = await save(form.req_no, {
        production_date: blankText(form.production_date),
        party_id: blankText(form.party_id),
        department_id: blankText(form.department_id),
        machine_id: blankText(form.machine_id),
        requisition_to: blankText(form.requisition_to),
        remarks: blankText(form.remarks),
        lines: form.lines.map((l) => ({ item_id: l.item_id, kg: l.kg === "" ? null : Number(l.kg), gm: l.gm === "" ? null : Number(l.gm), mg: l.mg === "" ? null : Number(l.mg) })),
      });
      if (res?.status === "success") {
        setExisting(true);
        toastManager.add({ title: c("wash.saved", { no: form.req_no }), type: "success" });
        router.refresh();
      } else warn(firstError(res));
    });
  }

  const reqOptions = [...productions.map((x) => ({ value: x.req_no, label: `${x.req_no} · ${x.production_date}` })), ...options.wash_requisitions].filter((o, i, all) => all.findIndex((x) => x.value === o.value) === i);
  const total = (l) => Number(l.kg || 0) + Number(l.gm || 0) / 1000 + Number(l.mg || 0) / 1000000;
  const r = sheet?.requisition;

  return (
    <div className="flex flex-col gap-4">
      <div className="flex flex-col gap-4 rounded-lg border border-border bg-surface p-5">
        <EditorHead title={c("wash.title")} hint={c("wash.hint")} editing={existing} c={c} />
        <Field label={c("wash.existing")} hint={c("ed.existing_hint")}>
          <Select value={existing ? form.req_no : ""} onValueChange={(v) => pick(v ?? "")} options={[{ value: "", label: c("wash.new_option") }, ...productions.map((x) => ({ value: x.req_no, label: `${x.req_no} · ${x.production_date}` }))]} placeholder={c("wash.new_option")} />
        </Field>
        <Group legend={c("wash.section_production")}>
          <Field label={c("wash.req_no")} required hint={c("wash.req_hint")}>
            <Select value={form.req_no} onValueChange={(v) => pick(v ?? "")} options={reqOptions} placeholder={c("wash.req_ph")} disabled={!canManage} />
          </Field>
          <Field label={c("wash.date")} required>
            <Input type="date" value={form.production_date} onChange={(ev) => set("production_date", ev.target.value)} disabled={!canManage} />
          </Field>
          <Field label={c("wash.company")} required>
            <Select value={form.party_id} onValueChange={(v) => set("party_id", v ?? "")} options={options.companies} placeholder={c("wash.select_company")} disabled={!canManage} />
          </Field>
          <Field label={c("wash.department")} required>
            <Select value={form.department_id} onValueChange={(v) => set("department_id", v ?? "")} options={options.departments} placeholder={c("wash.select_dept")} disabled={!canManage} />
          </Field>
          <Field label={c("wash.machine")} required>
            <Select value={form.machine_id} onValueChange={(v) => set("machine_id", v ?? "")} options={options.machines} placeholder={c("wash.select_machine")} disabled={!canManage} />
          </Field>
          <Field label={c("wash.req_to")}>
            <Select value={form.requisition_to} onValueChange={(v) => set("requisition_to", v ?? "")} options={[{ value: "", label: "—" }, ...options.users]} placeholder={c("wash.select_user")} disabled={!canManage} />
          </Field>
          <Field label={c("wash.remarks")} className="sm:col-span-3">
            <Textarea value={form.remarks} onChange={(ev) => set("remarks", ev.target.value)} rows={2} disabled={!canManage} />
          </Field>
        </Group>
        <Group legend={c("wash.section_info")}>
          {[
            ["wash.info_date", r?.req_date],
            ["wash.info_type", r?.req_type],
            ["wash.info_store", r?.store],
            ["wash.info_machine", r?.machine],
            ["wash.info_status", r ? t(`req.opt.status.${r.status}`) : ""],
            ["wash.info_remarks", r?.remarks],
          ].map(([k, v]) => (
            <Field key={k} label={c(k)}>
              <Input value={v ?? ""} placeholder={r ? "" : c("ed.no_choices")} readOnly />
            </Field>
          ))}
        </Group>
      </div>

      <LinesCard
        c={c}
        title={c("wash.section_lines")}
        hint={c("wash.lines_hint")}
        cols={[
          { key: "item_id", label: c("wash.item"), type: "item", lineType: "readonly", labelKey: "item", options: options.chemicals },
          { key: "kg", label: c("wash.kg"), type: "number" },
          { key: "gm", label: c("wash.gm"), type: "number" },
          { key: "mg", label: c("wash.mg"), type: "number" },
        ]}
        entry={e}
        setEntry={setEntry}
        lines={form.lines}
        setLines={(lines) => setForm((f) => ({ ...f, lines }))}
        editable={canManage && Boolean(form.req_no)}
        onAdd={add}
        emptyHint={c("wash.no_lines_hint")}
      />
      {form.lines.length ? (
        <div className="overflow-x-auto rounded-lg border border-border bg-surface p-5">
          <h3 className="text-base font-semibold">{c("wash.totals")}</h3>
          <table className="mt-3 w-full min-w-max text-sm">
            <thead>
              <tr className="bg-brand text-left text-brand-foreground">
                <th className="px-3 py-2 font-semibold">{c("wash.item")}</th>
                <th className="px-3 py-2 text-right font-semibold">{c("wash.total_kg")}</th>
                <th className="px-3 py-2 text-right font-semibold">{c("wash.sub_stock")}</th>
                <th className="px-3 py-2 text-right font-semibold">{c("wash.central_stock")}</th>
              </tr>
            </thead>
            <tbody>
              {form.lines.map((l, i) => (
                <tr key={i} className="border-b border-border last:border-0">
                  <td className="px-3 py-1.5">{l.item}</td>
                  <td className="px-3 py-1.5 text-right tabular-nums">{num(total(l), 6)}</td>
                  <td className="px-3 py-1.5 text-right tabular-nums">{l.sub_stock === null || l.sub_stock === undefined ? "—" : num(l.sub_stock)}</td>
                  <td className="px-3 py-1.5 text-right tabular-nums">{l.central_stock === null || l.central_stock === undefined ? "—" : num(l.central_stock)}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      ) : null}
      <EditorFoot c={c} backHref={backHref} note={c("wash.footer")} editable={canManage} pending={pending} onClear={() => pick("")} onSave={submit} saveLabel={c("wash.save")} />
    </div>
  );
}

/**
 * Manual Daily Bulk Production (17/07): every load recorded against a bulk
 * card, newest first. Only Load Time and Unload Time are written — the
 * production hour follows from the two and is never typed. Save Production
 * sends what was changed; Revert puts the grid back as it was loaded.
 *
 * @param {{ rows: object[], canManage: boolean, save: Function }} props
 */
function ManualDailyGrid({ rows, canManage, save }) {
  const t = useT("dyehouse");
  const router = useRouter();
  const toastManager = useToastManager();
  const c = (k, p) => t(`prod.${k}`, p);
  const [edits, setEdits] = useState({});
  const [page, setPage] = useState(0);
  const [pending, startTransition] = useTransition();
  const PAGE = 25;
  const value = (row, key) => edits[row.id]?.[key] ?? row[key] ?? "";
  const changed = Object.keys(edits);
  const pages = Math.max(1, Math.ceil(rows.length / PAGE));
  const at = Math.min(page, pages - 1);
  const slice = rows.slice(at * PAGE, at * PAGE + PAGE);

  function submit() {
    if (!changed.length) return toastManager.add({ title: c("manual.nothing"), type: "danger" });
    startTransition(async () => {
      const byId = Object.fromEntries(rows.map((r) => [r.id, r]));
      const res = await save({ loads: changed.map((id) => ({ id, load_time: value(byId[id], "load_time") || null, unload_time: value(byId[id], "unload_time") || null })) });
      if (res?.status === "success") {
        toastManager.add({ title: c("manual.saved", { n: changed.length }), type: "success" });
        setEdits({});
        router.refresh();
      } else toastManager.add({ title: firstError(res), type: "danger" });
    });
  }

  return (
    <div className="flex flex-col gap-4 rounded-lg border border-border bg-surface p-5">
      <div>
        <h2 className="text-base font-semibold">{c("manual.title")}</h2>
        <p className="mt-0.5 text-[13px] text-foreground-muted">{c("manual.hint")}</p>
      </div>
      {rows.length === 0 ? (
        <div className="px-4 py-8 text-center">
          <p className="text-sm font-semibold">{c("manual.empty")}</p>
          <p className="text-[13px] text-foreground-muted">{c("manual.empty_hint")}</p>
        </div>
      ) : (
        <div className="overflow-x-auto rounded-md border border-border">
          <table className="w-full min-w-max text-sm">
            <thead>
              <tr className="bg-brand text-left text-brand-foreground">
                {["batch_no", "production_date", "lot", "colour", "qty", "machine", "type", "status", "load_time", "unload_time", "hour"].map((k) => (
                  <th key={k} className={cn("px-3 py-2.5 font-semibold", ["qty", "hour"].includes(k) && "text-right")}>
                    {c(`manual.col.${k}`)}
                  </th>
                ))}
              </tr>
            </thead>
            <tbody>
              {slice.map((r) => {
                const lt = value(r, "load_time");
                const ut = value(r, "unload_time");
                const mins = minutesBetween(lt, ut);
                const off = !canManage || r.closed;
                return (
                  <tr key={r.id} className={cn("border-b border-border last:border-0", edits[r.id] && "bg-warning-subtle")}>
                    <td className="px-3 py-1.5 font-mono">{r.batch_no}</td>
                    <td className="px-3 py-1.5">{r.production_date}</td>
                    <td className="px-3 py-1.5">{r.lot_no ?? "—"}</td>
                    <td className="px-3 py-1.5">{r.colour ?? "—"}</td>
                    <td className="px-3 py-1.5 text-right tabular-nums">{num(r.qty)}</td>
                    <td className="px-3 py-1.5">{r.machine ?? "—"}</td>
                    <td className="px-3 py-1.5">{r.load_type}</td>
                    <td className="px-3 py-1.5">{r.status ? t(`batch.opt.load_status.${r.status}`) : "—"}</td>
                    <td className="px-3 py-1.5">
                      <Input type="datetime-local" value={lt} onChange={(ev) => setEdits((x) => ({ ...x, [r.id]: { ...x[r.id], load_time: ev.target.value } }))} disabled={off} className="w-52" aria-label={`${c("manual.col.load_time")} ${r.batch_no}`} />
                    </td>
                    <td className="px-3 py-1.5">
                      <Input type="datetime-local" value={ut} onChange={(ev) => setEdits((x) => ({ ...x, [r.id]: { ...x[r.id], unload_time: ev.target.value } }))} disabled={off} className="w-52" aria-label={`${c("manual.col.unload_time")} ${r.batch_no}`} />
                    </td>
                    <td className={cn("px-3 py-1.5 text-right tabular-nums", mins !== null && mins < 0 && "text-danger")}>{mins !== null && mins < 0 ? c("manual.negative") : hours(mins)}</td>
                  </tr>
                );
              })}
            </tbody>
          </table>
        </div>
      )}
      <div className="flex flex-wrap items-center gap-3">
        <p className="min-w-0 flex-1 text-[13px] text-foreground-muted">{c("manual.footer", { n: rows.length })}</p>
        {pages > 1 ? (
          <span className="flex items-center gap-1 text-[13px]">
            <Button variant="outline" size="sm" onClick={() => setPage(at - 1)} disabled={at === 0}>
              ‹
            </Button>
            <span className="tabular-nums">
              {at + 1} / {pages}
            </span>
            <Button variant="outline" size="sm" onClick={() => setPage(at + 1)} disabled={at >= pages - 1}>
              ›
            </Button>
          </span>
        ) : null}
        {canManage ? (
          <>
            <Button variant="outline" onClick={() => setEdits({})} disabled={pending || !changed.length}>
              <RotateCcw /> {c("manual.revert")}
            </Button>
            <Button onClick={submit} loading={pending}>
              <Save /> {c("manual.save")}
            </Button>
          </>
        ) : null}
      </div>
    </div>
  );
}

/**
 * Direct and General Fabric Booking and the Trims Dyeing Programme (17/07):
 * one editor told apart by `kind` ("DIRECT", "GENERAL", "TRIM"). Key
 * Information (production type, date, buyer, style and order — required on a
 * direct booking and a trim programme — the requisition type and the status
 * the desk sets), and the lines: fabric and shade in kilos, or each trim in
 * yards and kilos. Closed or cancelled, it reads only.
 *
 * @param {{ kind: string, papers: object[], options: object, canManage: boolean, load: Function, save: Function, backHref: string, initialNo?: string }} props
 */
function ProgrammeEditor({ kind, papers, options, canManage, load, save, backHref, initialNo = null }) {
  const t = useT("dyehouse");
  const router = useRouter();
  const c = (k, p) => t(`prod.${k}`, p);
  const trim = kind === "TRIM";
  const needOrder = kind !== "GENERAL";
  const noKey = trim ? "programme_no" : "booking_no";
  const blank = () => ({ no: "", prod_type: "", prog_date: today(), buyer_id: "", order_id: "", req_type: "Dyeing", status: "DRAFT", remarks: "", lines: [] });
  const toForm = (r) => ({
    no: r[noKey],
    prod_type: r.prod_type,
    prog_date: r.prog_date,
    buyer_id: r.buyer_id ?? "",
    order_id: r.order_id ?? "",
    req_type: r.req_type ?? "Dyeing",
    status: r.status,
    remarks: r.remarks ?? "",
    lines: r.lines.map((l) => ({ ...l, colour_id: l.colour_id ?? "", qty_kg: l.qty_kg === null ? "" : String(l.qty_kg), yds: l.yds === null || l.yds === undefined ? "" : String(l.yds), remarks: l.remarks ?? "" })),
  });
  const paper = usePaper({ blank, toForm, load, initialNo, docKey: "paper" });
  const { form, setForm, pending, startTransition, warn } = paper;
  const noEntry = trim ? { item: "", colour_id: "", yds: "", qty_kg: "", remarks: "" } : { fabric: "", colour_id: "", qty_kg: "", remarks: "" };
  const entry = paper.entry ?? noEntry;
  // What the paper was saved as decides whether it is still edited, not the status picked on the form.
  const saved = form.no ? (papers.find((x) => x[noKey] === form.no)?.status ?? form.status) : "DRAFT";
  const editable = canManage && !["CLOSED", "CANCELLED"].includes(saved);
  const set = (k, v) => setForm((f) => ({ ...f, [k]: v }));
  const k = (key) => c(`pg.${key}.${kind}`);
  const ordersOf = options.orders.filter((o) => !form.buyer_id || o.buyer_id === form.buyer_id);
  const order = options.orders.find((o) => o.value === form.order_id);
  const colourPick = (v) => ({ colour: options.colours.find((x) => x.value === v)?.label ?? "" });

  function add() {
    const ok = trim ? entry.item.trim() && (Number(entry.yds) > 0 || Number(entry.qty_kg) > 0) : entry.fabric.trim() && Number(entry.qty_kg) > 0;
    if (!ok) return warn(k("line_incomplete"));
    setForm((f) => ({ ...f, lines: [...f.lines, { ...entry }] }));
    paper.setEntry(null);
  }

  function submit() {
    startTransition(async () => {
      const payload = {
        ...(form.no || trim ? {} : { kind }),
        prod_type: blankText(form.prod_type),
        prog_date: blankText(form.prog_date),
        buyer_id: blankText(form.buyer_id),
        order_id: blankText(form.order_id),
        ...(trim ? {} : { req_type: blankText(form.req_type) }),
        status: blankText(form.status),
        remarks: blankText(form.remarks),
        lines: form.lines.map((l) =>
          trim
            ? { item: l.item, colour_id: blankText(l.colour_id), yds: l.yds === "" ? null : Number(l.yds), qty_kg: l.qty_kg === "" ? null : Number(l.qty_kg), remarks: blankText(l.remarks) }
            : { fabric: l.fabric, colour_id: blankText(l.colour_id), qty_kg: l.qty_kg === "" ? null : Number(l.qty_kg), remarks: blankText(l.remarks) },
        ),
      };
      const res = await save(form.no || null, payload);
      if (res?.status === "success") {
        setForm(toForm(res.paper));
        paper.toastManager.add({ title: c("ed.saved", { no: res.paper[noKey] }), type: "success" });
        router.refresh();
      } else warn(firstError(res));
    });
  }

  return (
    <div className="flex flex-col gap-4">
      <div className="flex flex-col gap-4 rounded-lg border border-border bg-surface p-5">
        <EditorHead title={k("title")} hint={k("hint")} editing={Boolean(form.no)} c={c} />
        <Field label={k("existing")} hint={c("ed.existing_hint")}>
          <Select value={form.no} onValueChange={(v) => (v ? paper.open(v) : setForm(blank()))} options={[{ value: "", label: k("new_option") }, ...papers.map((x) => ({ value: x[noKey], label: `${x[noKey]} · ${x.prog_date} · ${c(`opt.status.${x.status}`)}` }))]} placeholder={k("new_option")} />
        </Field>
        {form.no && !editable ? <p className="rounded-md bg-warning-subtle px-3 py-2 text-sm text-warning">{c("ed.locked", { no: form.no, status: c(`opt.status.${saved}`) })}</p> : null}
        <Group legend={c("pg.section_key")}>
          <Field label={trim ? c("pg.no_trim") : c("pg.no")} hint={c("ed.no_hint")}>
            <Input value={form.no} placeholder={c("ed.no_ph")} readOnly />
          </Field>
          <Field label={c("pg.prod_type")} required>
            <Select value={form.prod_type} onValueChange={(v) => set("prod_type", v ?? "")} options={PROD_TYPES.map((x) => ({ value: x, label: c(`opt.prod_type.${x}`) }))} placeholder={c("pg.select_type")} disabled={!editable} />
          </Field>
          <Field label={c("pg.prog_date")} required>
            <Input type="date" value={form.prog_date} onChange={(ev) => set("prog_date", ev.target.value)} disabled={!editable} />
          </Field>
          <Field label={c("pg.buyer")} required={needOrder}>
            <Select value={form.buyer_id} onValueChange={(v) => setForm((f) => ({ ...f, buyer_id: v ?? "", order_id: "" }))} options={[...(needOrder ? [] : [{ value: "", label: "—" }]), ...options.buyers]} placeholder={c("pg.select")} disabled={!editable} />
          </Field>
          <Field label={c("pg.style")} required={needOrder} hint={needOrder ? k("style_hint") : null}>
            <Select value={form.order_id} onValueChange={(v) => setForm((f) => ({ ...f, order_id: v ?? "", buyer_id: options.orders.find((o) => o.value === v)?.buyer_id ?? f.buyer_id }))} options={[...(needOrder ? [] : [{ value: "", label: "—" }]), ...ordersOf]} placeholder={c("pg.select")} disabled={!editable} />
          </Field>
          <Field label={c("pg.order_no")}>
            <Input value={order?.order_no ?? ""} readOnly />
          </Field>
          {!trim ? (
            <Field label={c("pg.req_type")}>
              <Input value={form.req_type} onChange={(ev) => set("req_type", ev.target.value)} placeholder="Dyeing" maxLength={60} disabled={!editable} />
            </Field>
          ) : null}
          <Field label={c("pg.status")} required hint={c("pg.status_hint")}>
            <Select value={form.status} onValueChange={(v) => set("status", v ?? "")} options={STATUSES.map((x) => ({ value: x, label: c(`opt.status.${x}`) }))} placeholder={c("pg.select_status")} disabled={!editable} />
          </Field>
          <Field label={c("pg.remarks")} className="sm:col-span-3">
            <Textarea value={form.remarks} onChange={(ev) => set("remarks", ev.target.value)} rows={2} disabled={!editable} />
          </Field>
        </Group>
      </div>
      <LinesCard
        c={c}
        title={trim ? c("pg.section_trims") : c("pg.section_fabrics")}
        hint={trim ? c("pg.trims_hint") : c("pg.fabrics_hint")}
        cols={[
          trim ? { key: "item", label: c("pg.item"), type: "text", max: 160, placeholder: "Neck tape 12mm" } : { key: "fabric", label: c("pg.fabric"), type: "text", max: 160, placeholder: "S/J 160 GSM 100% Cotton" },
          { key: "colour_id", label: c("pg.colour"), type: "item", options: options.colours, placeholder: c("pg.select_colour"), onPick: colourPick, width: "w-56" },
          ...(trim ? [{ key: "yds", label: c("pg.yds"), type: "number" }] : []),
          { key: "qty_kg", label: c("pg.qty_kg"), type: "number" },
          { key: "remarks", label: c("pg.line_remarks"), type: "text" },
        ]}
        entry={entry}
        setEntry={(x) => paper.setEntry(x)}
        lines={form.lines}
        setLines={(lines) => setForm((f) => ({ ...f, lines }))}
        editable={editable}
        onAdd={add}
        emptyHint={k("no_lines_hint")}
        totalLabel={c("pg.total_kg")}
        totalKey="qty_kg"
      />
      <EditorFoot c={c} backHref={backHref} note={k("footer")} editable={editable} pending={pending} onClear={() => setForm(blank())} onSave={submit} saveLabel={k("save")} />
    </div>
  );
}

export { WashProductionEditor, ManualDailyGrid, ProgrammeEditor };
