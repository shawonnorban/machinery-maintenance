"use client";

import { useState, useTransition } from "react";
import { useRouter } from "next/navigation";
import { Plus, Trash2 } from "lucide-react";
import { cn } from "@/lib/utils";
import { Select } from "@/components/ui/select";
import { Input } from "@/components/ui/input";
import { Textarea } from "@/components/ui/textarea";
import { Button } from "@/components/ui/button";
import { useToastManager } from "@/components/ui/toast";
import { Field, Group, EditorHead, EditorFoot, LinesCard, usePaper } from "@/components/dyehouse/chem-store";
import { useT } from "@/lib/i18n";

const today = () => new Date().toLocaleDateString("en-CA", { timeZone: "Asia/Dhaka" });
const kg = (v) => Number(v || 0).toLocaleString("en-US", { maximumFractionDigits: 3 });
const blankText = (v) => (v === "" || v === undefined || v === null ? null : v);
const firstError = (res) => (res?.errors ? Object.values(res.errors).flat()[0] : res?.message);
const STATUSES = ["DRAFT", "SUBMITTED", "PARAMS_UPDATED", "DESPATCHED", "COMPLETED", "CANCELLED"];

/**
 * Out-House Sub-Contract Programme (AnnotechERP 17/09): the programme (dates,
 * company, the S/C party and store, gate pass, the status the desk sets) and
 * its Programme Shades — buyer, style, colour and kilos. A shade already
 * called off keeps its place; completed or cancelled, the programme reads only.
 *
 * @param {{ programmes: object[], options: object, canManage: boolean, load: Function, save: Function, backHref: string, initialNo?: string }} props
 */
function ScProgrammeEditor({ programmes, options, canManage, load, save, backHref, initialNo = null }) {
  const t = useT("dyehouse");
  const router = useRouter();
  const c = (k, p) => t(`sc.${k}`, p);
  const blank = () => ({ program_no: "", issue_date: today(), exp_delivery_date: "", party_id: options.companies[0]?.value ?? "", supplier_id: "", store_id: "", gate_pass_no: "", status: "DRAFT", remarks: "", shades: [] });
  const toForm = (r) => ({
    ...blank(),
    ...Object.fromEntries(["program_no", "issue_date", "exp_delivery_date", "party_id", "supplier_id", "store_id", "gate_pass_no", "status", "remarks"].map((k) => [k, r[k] ?? ""])),
    shades: r.shades.map((s) => ({ ...s, buyer_id: s.buyer_id ?? "", order_id: s.order_id ?? "", req_kg: String(s.req_kg), remarks: s.remarks ?? "" })),
  });
  const paper = usePaper({ blank, toForm, load, initialNo, docKey: "programme" });
  const { form, setForm, pending, startTransition, warn } = paper;
  const noEntry = { buyer_id: "", order_id: "", colour_id: "", req_kg: "", remarks: "" };
  const entry = paper.entry ?? noEntry;
  const saved = form.program_no ? (programmes.find((x) => x.program_no === form.program_no)?.status ?? form.status) : "DRAFT";
  const editable = canManage && !["COMPLETED", "CANCELLED"].includes(saved);
  const set = (k, v) => setForm((f) => ({ ...f, [k]: v }));
  const orderPick = (v) => ({ buyer_id: options.orders.find((o) => o.value === v)?.buyer_id ?? "" });

  function add() {
    if (!entry.colour_id || !(Number(entry.req_kg) > 0)) return warn(c("pg.line_incomplete"));
    setForm((f) => ({ ...f, shades: [...f.shades, { ...entry }] }));
    paper.setEntry(null);
  }

  function submit() {
    startTransition(async () => {
      const payload = {
        ...Object.fromEntries(["issue_date", "exp_delivery_date", "party_id", "supplier_id", "store_id", "gate_pass_no", "status", "remarks"].map((k) => [k, blankText(form[k])])),
        shades: form.shades.map((s) => ({ id: s.id ?? null, buyer_id: blankText(s.buyer_id), order_id: blankText(s.order_id), colour_id: blankText(s.colour_id), req_kg: s.req_kg === "" ? null : Number(s.req_kg), remarks: blankText(s.remarks) })),
      };
      const res = await save(form.program_no || null, payload);
      if (res?.status === "success") {
        setForm(toForm(res.programme));
        paper.toastManager.add({ title: c("ed.saved", { no: res.programme.program_no }), type: "success" });
        router.refresh();
      } else warn(firstError(res));
    });
  }

  return (
    <div className="flex flex-col gap-4">
      <div className="flex flex-col gap-4 rounded-lg border border-border bg-surface p-5">
        <EditorHead title={c("pg.title")} hint={c("pg.hint")} editing={Boolean(form.program_no)} c={c} />
        <Field label={c("pg.existing")} hint={c("ed.existing_hint")}>
          <Select value={form.program_no} onValueChange={(v) => (v ? paper.open(v) : setForm(blank()))} options={[{ value: "", label: c("pg.new_option") }, ...programmes.map((x) => ({ value: x.program_no, label: `${x.program_no} · ${x.issue_date} · ${c(`opt.status.${x.status}`)}` }))]} placeholder={c("pg.new_option")} />
        </Field>
        {form.program_no && !editable ? <p className="rounded-md bg-warning-subtle px-3 py-2 text-sm text-warning">{c("ed.locked", { no: form.program_no, status: c(`opt.status.${saved}`) })}</p> : null}
        <Group legend={c("pg.section")}>
          <Field label={c("pg.no")} hint={c("ed.no_hint")}>
            <Input value={form.program_no} placeholder={c("ed.no_ph")} readOnly />
          </Field>
          <Field label={c("pg.issue_date")} required>
            <Input type="date" value={form.issue_date} onChange={(e) => set("issue_date", e.target.value)} disabled={!editable} />
          </Field>
          <Field label={c("pg.exp_delivery")}>
            <Input type="date" value={form.exp_delivery_date} onChange={(e) => set("exp_delivery_date", e.target.value)} disabled={!editable} />
          </Field>
          <Field label={c("pg.company")}>
            <Select value={form.party_id} onValueChange={(v) => set("party_id", v ?? "")} options={options.companies} placeholder={c("pg.select_company")} disabled={!editable} />
          </Field>
          <Field label={c("pg.party")} required>
            <Select value={form.supplier_id} onValueChange={(v) => set("supplier_id", v ?? "")} options={options.suppliers} placeholder={c("pg.select_party")} disabled={!editable} />
          </Field>
          <Field label={c("pg.store")} required>
            <Select value={form.store_id} onValueChange={(v) => set("store_id", v ?? "")} options={options.stores} placeholder={c("pg.select_store")} disabled={!editable} />
          </Field>
          <Field label={c("pg.gate_pass")}>
            <Input value={form.gate_pass_no} onChange={(e) => set("gate_pass_no", e.target.value)} maxLength={40} disabled={!editable} />
          </Field>
          <Field label={c("pg.status")} required hint={c("pg.status_hint")}>
            <Select value={form.status} onValueChange={(v) => set("status", v ?? "")} options={STATUSES.map((x) => ({ value: x, label: c(`opt.status.${x}`) }))} placeholder={c("pg.select")} disabled={!editable} />
          </Field>
          <Field label={c("pg.remarks")} className="sm:col-span-3">
            <Textarea value={form.remarks} onChange={(e) => set("remarks", e.target.value)} rows={2} disabled={!editable} />
          </Field>
        </Group>
      </div>
      <LinesCard
        c={c}
        title={c("pg.section_shades")}
        hint={c("pg.shades_hint")}
        cols={[
          { key: "buyer_id", label: c("pg.buyer"), type: "item", options: options.buyers, placeholder: c("pg.select"), width: "w-44" },
          { key: "order_id", label: c("pg.order"), type: "item", options: options.orders, placeholder: c("pg.select_style"), onPick: orderPick, width: "w-56" },
          { key: "colour_id", label: c("pg.colour"), type: "item", options: options.colours, placeholder: c("pg.select_colour"), width: "w-48" },
          { key: "req_kg", label: c("pg.req_kg"), type: "number" },
          { key: "remarks", label: c("pg.line_remarks"), type: "text" },
        ]}
        entry={entry}
        setEntry={(x) => paper.setEntry(x)}
        lines={form.shades}
        setLines={(shades) => setForm((f) => ({ ...f, shades }))}
        editable={editable}
        onAdd={add}
        emptyHint={c("pg.no_lines_hint")}
        totalLabel={c("pg.total_kg")}
        totalKey="req_kg"
      />
      <EditorFoot c={c} backHref={backHref} note={c("pg.footer")} editable={editable} pending={pending} onClear={() => setForm(blank())} onSave={submit} saveLabel={c("pg.save")} />
    </div>
  );
}

/**
 * Sub-Contract Dye Batch Programme (17/09): pick a live programme on the
 * list (or the select), its party and date shown, the store, and how much
 * grey is called off now against each of its shades — no more than is left
 * of the shade. An existing requisition loads back while nothing is issued.
 *
 * @param {{ requisitions: object[], options: object, canManage: boolean, load: Function, save: Function }} props
 */
function ScBatchProgramme({ requisitions, options, canManage, load, save }) {
  const t = useT("dyehouse");
  const router = useRouter();
  const toastManager = useToastManager();
  const c = (k, p) => t(`sc.${k}`, p);
  const blank = () => ({ req_no: "", status: "WAITING", req_date: today(), program_no: "", store_id: "", lines: [] });
  const [form, setForm] = useState(blank);
  const [pick, setPick] = useState({ shade_id: "", req_kg: "" });
  const [pending, startTransition] = useTransition();
  const warn = (title) => toastManager.add({ title, type: "danger" });
  const programme = options.programmes.find((p) => p.program_no === form.program_no);
  const editable = canManage && form.status === "WAITING";

  function open(no) {
    if (!no) return setForm(blank());
    startTransition(async () => {
      const res = await load(no);
      if (res?.status !== "success") return warn(res?.message);
      const r = res.requisition;
      setForm({ req_no: r.req_no, status: r.status, req_date: r.req_date, program_no: r.program_no, store_id: r.store_id, lines: r.lines.map((l) => ({ shade_id: l.shade_id, shade: l.shade, req_kg: String(l.req_kg) })) });
    });
  }

  function choose(no) {
    const p = options.programmes.find((x) => x.program_no === no);
    setForm((f) => ({ ...f, program_no: no, store_id: f.store_id || p?.store_id || "", lines: [] }));
  }

  function add() {
    const s = programme?.shades.find((x) => x.id === pick.shade_id);
    if (!s || !(Number(pick.req_kg) > 0)) return warn(c("rq.line_incomplete"));
    setForm((f) => ({ ...f, lines: [...f.lines, { shade_id: s.id, shade: `${s.colour ?? "—"}${s.style ? ` · ${s.style}` : ""}`, req_kg: pick.req_kg }] }));
    setPick({ shade_id: "", req_kg: "" });
  }

  function submit() {
    startTransition(async () => {
      const res = await save(form.req_no || null, {
        req_date: form.req_date || null,
        ...(form.req_no ? {} : { program_no: form.program_no || null }),
        store_id: form.store_id || null,
        lines: form.lines.map((l) => ({ shade_id: l.shade_id, req_kg: l.req_kg === "" ? null : Number(l.req_kg) })),
      });
      if (res?.status === "success") {
        toastManager.add({ title: c("rq.saved", { no: res.requisition.req_no, batch: res.requisition.batch_no }), type: "success" });
        setForm(blank());
        router.refresh();
      } else warn(firstError(res));
    });
  }

  const shadeOptions = (programme?.shades ?? []).map((s) => ({ value: s.id, label: `${s.colour ?? "—"}${s.style ? ` · ${s.style}` : ""} · ${c("rq.left", { kg: kg(s.left_kg) })}` }));

  return (
    <div className="flex flex-col gap-4">
      <div className="rounded-lg border border-border bg-surface p-5">
        <h2 className="text-base font-semibold">{c("rq.list_title")}</h2>
        <p className="mt-0.5 text-[13px] text-foreground-muted">{c("rq.list_hint")}</p>
        <div className="mt-3 overflow-x-auto rounded-md border border-border">
          <table className="w-full min-w-max text-sm">
            <thead>
              <tr className="bg-brand text-left text-brand-foreground">
                {["program_no", "party", "issue_date", "req_kg", "left_kg"].map((k) => (
                  <th key={k} className={cn("px-3 py-2 font-semibold", ["req_kg", "left_kg"].includes(k) && "text-right")}>
                    {c(`rq.col.${k}`)}
                  </th>
                ))}
              </tr>
            </thead>
            <tbody>
              {options.programmes.length === 0 ? (
                <tr>
                  <td colSpan={5} className="px-3 py-6 text-center text-foreground-muted">
                    {c("rq.no_programmes")}
                  </td>
                </tr>
              ) : null}
              {options.programmes.map((p) => (
                <tr key={p.program_no} className={cn("border-t border-border", form.program_no === p.program_no ? "bg-brand-subtle" : "hover:bg-surface-muted")}>
                  <td className="px-3 py-1.5">
                    <button type="button" onClick={() => !form.req_no && choose(p.program_no)} className="font-mono font-medium text-brand hover:underline">
                      {p.program_no}
                    </button>
                  </td>
                  <td className="px-3 py-1.5">{p.supplier}</td>
                  <td className="px-3 py-1.5">{p.issue_date}</td>
                  <td className="px-3 py-1.5 text-right tabular-nums">{kg(p.req_kg)}</td>
                  <td className="px-3 py-1.5 text-right tabular-nums">{kg(p.shades.reduce((s, x) => s + x.left_kg, 0))}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </div>
      <div className="flex flex-col gap-4 rounded-lg border border-border bg-surface p-5">
        <EditorHead title={c("rq.title")} hint={c("rq.hint")} editing={Boolean(form.req_no)} c={c} />
        <Field label={c("rq.existing")} hint={c("ed.existing_hint")}>
          <Select value={form.req_no} onValueChange={(v) => open(v ?? "")} options={[{ value: "", label: c("rq.new_option") }, ...requisitions.map((x) => ({ value: x.req_no, label: `${x.req_no} · ${x.batch_no} · ${c(`opt.req_status.${x.status}`)}` }))]} placeholder={c("rq.new_option")} />
        </Field>
        {form.req_no && !editable ? <p className="rounded-md bg-warning-subtle px-3 py-2 text-sm text-warning">{c("ed.locked", { no: form.req_no, status: c(`opt.req_status.${form.status}`) })}</p> : null}
        <Group legend={c("rq.section")}>
          <Field label={c("rq.program")} required>
            <Select value={form.program_no} onValueChange={(v) => choose(v ?? "")} options={options.programmes.map((p) => ({ value: p.program_no, label: `${p.program_no} · ${p.supplier}` }))} placeholder={c("rq.select_program")} disabled={!editable || Boolean(form.req_no)} />
          </Field>
          <Field label={c("rq.party")}>
            <Input value={programme?.supplier ?? ""} readOnly />
          </Field>
          <Field label={c("rq.issue_date")}>
            <Input value={programme?.issue_date ?? ""} readOnly />
          </Field>
          <Field label={c("rq.req_date")} required>
            <Input type="date" value={form.req_date} onChange={(e) => setForm((f) => ({ ...f, req_date: e.target.value }))} disabled={!editable} />
          </Field>
          <Field label={c("rq.store")} required>
            <Select value={form.store_id} onValueChange={(v) => setForm((f) => ({ ...f, store_id: v ?? "" }))} options={options.stores} placeholder={c("pg.select_store")} disabled={!editable} />
          </Field>
        </Group>
        <fieldset className="rounded-lg border border-border p-4">
          <legend className="px-1 text-[13px] font-semibold">{c("rq.section_shades")}</legend>
          {editable ? (
            <div className="grid items-end gap-4 sm:grid-cols-4">
              <Field label={c("rq.shade")} className="sm:col-span-2">
                <Select value={pick.shade_id} onValueChange={(v) => setPick((p) => ({ ...p, shade_id: v ?? "" }))} options={shadeOptions} placeholder={c("rq.select_shade")} />
              </Field>
              <Field label={c("rq.req_now")}>
                <Input type="number" min="0" step="0.001" value={pick.req_kg} onChange={(e) => setPick((p) => ({ ...p, req_kg: e.target.value }))} />
              </Field>
              <div className="flex gap-2">
                <Button variant="outline" onClick={add} disabled={!programme}>
                  <Plus /> {c("rq.add")}
                </Button>
                <Button variant="outline" onClick={() => setPick({ shade_id: "", req_kg: "" })}>
                  {c("ed.cancel")}
                </Button>
              </div>
            </div>
          ) : null}
          {form.lines.length ? (
            <table className="mt-4 w-full text-sm">
              <thead>
                <tr className="bg-brand text-left text-brand-foreground">
                  <th className="px-3 py-2 font-semibold">{c("rq.shade")}</th>
                  <th className="px-3 py-2 text-right font-semibold">{c("rq.req_now")}</th>
                  <th className="w-10 px-3 py-2" />
                </tr>
              </thead>
              <tbody>
                {form.lines.map((l, i) => (
                  <tr key={i} className="border-b border-border last:border-0">
                    <td className="px-3 py-1.5">{l.shade}</td>
                    <td className="px-3 py-1.5">
                      <Input type="number" min="0" step="0.001" value={l.req_kg} onChange={(e) => setForm((f) => ({ ...f, lines: f.lines.map((x, j) => (j === i ? { ...x, req_kg: e.target.value } : x)) }))} disabled={!editable} className="ml-auto w-28 text-right" aria-label={`${c("rq.req_now")} ${i + 1}`} />
                    </td>
                    <td className="px-3 py-1.5">
                      {editable ? (
                        <Button variant="ghost" size="sm" onClick={() => setForm((f) => ({ ...f, lines: f.lines.filter((_, j) => j !== i) }))} aria-label={c("ed.remove")}>
                          <Trash2 />
                        </Button>
                      ) : null}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          ) : (
            <p className="mt-3 text-[13px] text-foreground-muted">{c("rq.no_lines_hint")}</p>
          )}
        </fieldset>
        {editable ? (
          <div className="flex flex-wrap items-center justify-end gap-3">
            <p className="min-w-0 flex-1 text-[13px] text-foreground-muted">{c("rq.footer")}</p>
            <Button variant="outline" onClick={() => setForm(blank())} disabled={pending}>
              {c("ed.clear")}
            </Button>
            <Button onClick={submit} loading={pending}>
              {c("rq.save")}
            </Button>
          </div>
        ) : null}
      </div>
    </div>
  );
}

/**
 * Out-House Delivery Challan (17/09): the challan (date, company, the S/C
 * party, store, gate pass) and the programmes on it — the party's live
 * programmes not on another challan, added one by one. Saving marks them
 * despatched; once anything on it has come back it reads only.
 *
 * @param {{ challans: object[], options: object, canManage: boolean, load: Function, save: Function, backHref: string, initialNo?: string }} props
 */
function ScChallanEditor({ challans, options, canManage, load, save, backHref, initialNo = null }) {
  const t = useT("dyehouse");
  const router = useRouter();
  const c = (k, p) => t(`sc.${k}`, p);
  const blank = () => ({ challan_no: "", challan_date: today(), party_id: options.companies[0]?.value ?? "", supplier_id: "", store_id: "", gate_pass_no: "", remarks: "", programmes: [], received: false });
  const toForm = (r) => ({ ...blank(), ...Object.fromEntries(Object.entries(r).map(([k, v]) => [k, v ?? ""])), programmes: r.programmes, received: r.received });
  const paper = usePaper({ blank, toForm, load, initialNo, docKey: "challan" });
  const { form, setForm, pending, startTransition, warn } = paper;
  const [pick, setPick] = useState("");
  const editable = canManage && !form.received;
  const set = (k, v) => setForm((f) => ({ ...f, [k]: v }));
  const free = options.programmes.filter((p) => p.supplier_id === form.supplier_id && (!p.challan_no || p.challan_no === form.challan_no) && !form.programmes.includes(p.program_no));

  function add() {
    if (!pick) return warn(c("ch.pick_programme"));
    setForm((f) => ({ ...f, programmes: [...f.programmes, pick] }));
    setPick("");
  }

  function submit() {
    startTransition(async () => {
      const res = await save(form.challan_no || null, {
        ...Object.fromEntries(["challan_date", "party_id", "supplier_id", "store_id", "gate_pass_no", "remarks"].map((k) => [k, blankText(form[k])])),
        programmes: form.programmes,
      });
      if (res?.status === "success") {
        setForm(toForm(res.challan));
        paper.toastManager.add({ title: c("ed.saved", { no: res.challan.challan_no }), type: "success" });
        router.refresh();
      } else warn(firstError(res));
    });
  }

  const info = (no) => options.programmes.find((p) => p.program_no === no);

  return (
    <div className="flex flex-col gap-4">
      <div className="flex flex-col gap-4 rounded-lg border border-border bg-surface p-5">
        <EditorHead title={c("ch.title")} hint={c("ch.hint")} editing={Boolean(form.challan_no)} c={c} />
        <Field label={c("ch.existing")} hint={c("ed.existing_hint")}>
          <Select value={form.challan_no} onValueChange={(v) => (v ? paper.open(v) : setForm(blank()))} options={[{ value: "", label: c("ch.new_option") }, ...challans.map((x) => ({ value: x.challan_no, label: `${x.challan_no} · ${x.challan_date} · ${x.supplier ?? ""}` }))]} placeholder={c("ch.new_option")} />
        </Field>
        {form.challan_no && !editable ? <p className="rounded-md bg-warning-subtle px-3 py-2 text-sm text-warning">{c("ch.locked", { no: form.challan_no })}</p> : null}
        <Group legend={c("ch.section")}>
          <Field label={c("ch.no")} hint={c("ed.no_hint")}>
            <Input value={form.challan_no} placeholder={c("ed.no_ph")} readOnly />
          </Field>
          <Field label={c("ch.date")} required>
            <Input type="date" value={form.challan_date} onChange={(e) => set("challan_date", e.target.value)} disabled={!editable} />
          </Field>
          <Field label={c("pg.company")}>
            <Select value={form.party_id} onValueChange={(v) => set("party_id", v ?? "")} options={options.companies} placeholder={c("pg.select_company")} disabled={!editable} />
          </Field>
          <Field label={c("pg.party")} required>
            <Select value={form.supplier_id} onValueChange={(v) => setForm((f) => ({ ...f, supplier_id: v ?? "", programmes: [] }))} options={options.suppliers} placeholder={c("pg.select_party")} disabled={!editable} />
          </Field>
          <Field label={c("pg.store")}>
            <Select value={form.store_id} onValueChange={(v) => set("store_id", v ?? "")} options={[{ value: "", label: "—" }, ...options.stores]} placeholder={c("pg.select_store")} disabled={!editable} />
          </Field>
          <Field label={c("pg.gate_pass")}>
            <Input value={form.gate_pass_no} onChange={(e) => set("gate_pass_no", e.target.value)} maxLength={40} disabled={!editable} />
          </Field>
          <Field label={c("pg.remarks")} className="sm:col-span-3">
            <Textarea value={form.remarks} onChange={(e) => set("remarks", e.target.value)} rows={2} disabled={!editable} />
          </Field>
        </Group>
      </div>
      <div className="rounded-lg border border-border bg-surface p-5">
        <h3 className="text-base font-semibold">{c("ch.section_programmes")}</h3>
        <p className="mt-0.5 text-[13px] text-foreground-muted">{c("ch.programmes_hint")}</p>
        {editable ? (
          <div className="mt-3 grid items-end gap-4 sm:grid-cols-3">
            <Field label={c("ch.programme")} className="sm:col-span-2" hint={form.supplier_id && !free.length ? c("ch.none_free") : null}>
              <Select value={pick} onValueChange={(v) => setPick(v ?? "")} options={free.map((p) => ({ value: p.program_no, label: `${p.program_no} · ${p.issue_date} · ${kg(p.req_kg)} kg` }))} placeholder={c("ch.select_programme")} disabled={!form.supplier_id} />
            </Field>
            <Button variant="outline" onClick={add} disabled={!pick}>
              <Plus /> {c("ch.add_programme")}
            </Button>
          </div>
        ) : null}
        {form.programmes.length ? (
          <table className="mt-4 w-full text-sm">
            <thead>
              <tr className="bg-brand text-left text-brand-foreground">
                <th className="px-3 py-2 font-semibold">{c("ch.col.program_no")}</th>
                <th className="px-3 py-2 font-semibold">{c("ch.col.issue_date")}</th>
                <th className="px-3 py-2 text-right font-semibold">{c("ch.col.req_kg")}</th>
                <th className="w-10 px-3 py-2" />
              </tr>
            </thead>
            <tbody>
              {form.programmes.map((no) => (
                <tr key={no} className="border-b border-border last:border-0">
                  <td className="px-3 py-1.5 font-mono">{no}</td>
                  <td className="px-3 py-1.5">{info(no)?.issue_date ?? "—"}</td>
                  <td className="px-3 py-1.5 text-right tabular-nums">{info(no) ? kg(info(no).req_kg) : "—"}</td>
                  <td className="px-3 py-1.5">
                    {editable ? (
                      <Button variant="ghost" size="sm" onClick={() => setForm((f) => ({ ...f, programmes: f.programmes.filter((x) => x !== no) }))} aria-label={c("ed.remove")}>
                        <Trash2 />
                      </Button>
                    ) : null}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        ) : (
          <p className="mt-3 text-[13px] text-foreground-muted">{c("ch.no_lines_hint")}</p>
        )}
      </div>
      <EditorFoot c={c} backHref={backHref} note={c("ch.footer")} editable={editable} pending={pending} onClear={() => setForm(blank())} onSave={submit} saveLabel={c("ch.save")} />
    </div>
  );
}

/**
 * Out-House Floor Receipt and Challan Receipt (17/09), told apart by `kind`
 * ("FLOOR", "STORE"): the receipt (date, the S/C and transfer party, store,
 * the challan — its programmes are what can come back — and the floor's QC
 * No or the store's Batch No), and the programmes coming back in kilos. The
 * store books no more than the floor has received.
 *
 * @param {{ kind: string, receipts: object[], challans: object[], options: object, canManage: boolean, load: Function, save: Function, backHref: string, initialNo?: string }} props
 */
function ScReceiptEditor({ kind, receipts, challans, options, canManage, load, save, backHref, initialNo = null }) {
  const t = useT("dyehouse");
  const router = useRouter();
  const c = (k, p) => t(`sc.${k}`, p);
  const floor = kind === "FLOOR";
  const blank = () => ({ mrr_no: "", issue_date: today(), supplier_id: "", transfer_party_id: "", store_id: "", challan_no: "", batch_no: "", qc_no: "", remarks: "", lines: [] });
  const toForm = (r) => ({ ...blank(), ...Object.fromEntries(Object.entries(r).map(([k, v]) => [k, v ?? ""])), lines: r.lines.map((l) => ({ ...l, qty_kg: String(l.qty_kg) })) });
  const paper = usePaper({ blank, toForm, load, initialNo, docKey: "receipt" });
  const { form, setForm, pending, startTransition, warn } = paper;
  const noEntry = { program_no: "", qty_kg: "" };
  const entry = paper.entry ?? noEntry;
  const set = (k, v) => setForm((f) => ({ ...f, [k]: v }));
  const challan = challans.find((x) => x.challan_no === form.challan_no);
  const ofParty = challans.filter((x) => !form.supplier_id || x.supplier_id === form.supplier_id);
  const k = (key) => c(`rc.${key}.${kind}`);

  function add() {
    if (!entry.program_no || !(Number(entry.qty_kg) > 0)) return warn(c("rc.line_incomplete"));
    setForm((f) => ({ ...f, lines: [...f.lines, { ...entry }] }));
    paper.setEntry(null);
  }

  function submit() {
    startTransition(async () => {
      const res = await save(form.mrr_no || null, {
        ...(form.mrr_no ? {} : { kind, challan_no: blankText(form.challan_no) }),
        issue_date: blankText(form.issue_date),
        transfer_party_id: blankText(form.transfer_party_id),
        store_id: blankText(form.store_id),
        ...(floor ? { qc_no: blankText(form.qc_no) } : { batch_no: blankText(form.batch_no) }),
        remarks: blankText(form.remarks),
        lines: form.lines.map((l) => ({ program_no: l.program_no, qty_kg: l.qty_kg === "" ? null : Number(l.qty_kg) })),
      });
      if (res?.status === "success") {
        setForm(toForm(res.receipt));
        paper.toastManager.add({ title: c("ed.saved", { no: res.receipt.mrr_no }), type: "success" });
        router.refresh();
      } else warn(firstError(res));
    });
  }

  return (
    <div className="flex flex-col gap-4">
      <div className="flex flex-col gap-4 rounded-lg border border-border bg-surface p-5">
        <EditorHead title={k("title")} hint={k("hint")} editing={Boolean(form.mrr_no)} c={c} />
        <Field label={k("existing")} hint={c("ed.existing_hint")}>
          <Select value={form.mrr_no} onValueChange={(v) => (v ? paper.open(v) : setForm(blank()))} options={[{ value: "", label: k("new_option") }, ...receipts.map((x) => ({ value: x.mrr_no, label: `${x.mrr_no} · ${x.issue_date} · ${x.challan_no}` }))]} placeholder={k("new_option")} />
        </Field>
        <Group legend={k("title")}>
          <Field label={c("rc.mrr_no")} hint={c("ed.no_hint")}>
            <Input value={form.mrr_no} placeholder={c("ed.no_ph")} readOnly />
          </Field>
          <Field label={c("rc.issue_date")} required>
            <Input type="date" value={form.issue_date} onChange={(e) => set("issue_date", e.target.value)} disabled={!canManage} />
          </Field>
          <Field label={c("pg.party")}>
            <Select value={form.supplier_id} onValueChange={(v) => setForm((f) => ({ ...f, supplier_id: v ?? "", challan_no: "", lines: [] }))} options={[{ value: "", label: "—" }, ...options.suppliers]} placeholder={c("pg.select_party")} disabled={!canManage || Boolean(form.mrr_no)} />
          </Field>
          <Field label={c("rc.transfer_party")}>
            <Select value={form.transfer_party_id} onValueChange={(v) => set("transfer_party_id", v ?? "")} options={[{ value: "", label: "—" }, ...options.suppliers]} placeholder={c("rc.select_transfer")} disabled={!canManage} />
          </Field>
          <Field label={c("pg.store")}>
            <Select value={form.store_id} onValueChange={(v) => set("store_id", v ?? "")} options={[{ value: "", label: "—" }, ...options.stores]} placeholder={c("pg.select_store")} disabled={!canManage} />
          </Field>
          <Field label={c("rc.challan")} required hint={c("rc.challan_hint")}>
            <Select
              value={form.challan_no}
              onValueChange={(v) => {
                const ch = challans.find((x) => x.challan_no === v);
                setForm((f) => ({ ...f, challan_no: v ?? "", supplier_id: ch?.supplier_id ?? f.supplier_id, store_id: f.store_id || ch?.store_id || "", lines: [] }));
              }}
              options={ofParty.map((x) => ({ value: x.challan_no, label: `${x.challan_no} · ${x.challan_date} · ${x.supplier ?? ""}` }))}
              placeholder={c("rc.select_challan")}
              disabled={!canManage || Boolean(form.mrr_no)}
            />
          </Field>
          {floor ? (
            <Field label={c("rc.qc_no")}>
              <Input value={form.qc_no} onChange={(e) => set("qc_no", e.target.value)} maxLength={40} disabled={!canManage} />
            </Field>
          ) : (
            <Field label={c("rc.batch_no")}>
              <Input value={form.batch_no} onChange={(e) => set("batch_no", e.target.value)} maxLength={40} disabled={!canManage} />
            </Field>
          )}
          <Field label={c("pg.remarks")} className="sm:col-span-3">
            <Textarea value={form.remarks} onChange={(e) => set("remarks", e.target.value)} rows={2} disabled={!canManage} />
          </Field>
        </Group>
      </div>
      <LinesCard
        c={c}
        title={c("rc.section_lines")}
        hint={k("lines_hint")}
        cols={[
          { key: "program_no", label: c("rc.programme"), type: "item", options: (challan?.programmes ?? []).map((p) => ({ value: p, label: p })), placeholder: c("ch.select_programme"), width: "w-56" },
          { key: "qty_kg", label: c("rc.qty_kg"), type: "number" },
        ]}
        entry={entry}
        setEntry={(x) => paper.setEntry(x)}
        lines={form.lines}
        setLines={(lines) => setForm((f) => ({ ...f, lines }))}
        editable={canManage && Boolean(form.challan_no)}
        onAdd={add}
        emptyHint={c("rc.no_lines_hint")}
        totalLabel={c("pg.total_kg")}
        totalKey="qty_kg"
      />
      <EditorFoot c={c} backHref={backHref} note={k("footer")} editable={canManage} pending={pending} onClear={() => setForm(blank())} onSave={submit} saveLabel={k("save")} />
    </div>
  );
}

export { ScProgrammeEditor, ScBatchProgramme, ScChallanEditor, ScReceiptEditor };
