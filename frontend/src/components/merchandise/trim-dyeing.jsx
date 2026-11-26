"use client";

import { useMemo, useState, useTransition } from "react";
import { Palette, Plus, RotateCcw, Save, Search, Trash2 } from "lucide-react";
import { cn } from "@/lib/utils";
import { Input } from "@/components/ui/input";
import { Select } from "@/components/ui/select";
import { Textarea } from "@/components/ui/textarea";
import { Button } from "@/components/ui/button";
import { Modal } from "@/components/ui/modal";
import { Alert } from "@/components/ui/alert";
import { useToastManager } from "@/components/ui/toast";
import { useT } from "@/lib/i18n";
import { UomSelect } from "@/components/erp/uom-select";

// An error's field messages, or its message when it names no field.
const messagesOf = (result) => {
  const fields = [...new Set(Object.values(result?.errors ?? {}).flat())];
  return fields.length ? fields : [result?.message ?? ""];
};
const str = (v) => (v === null || v === undefined ? "" : String(v));
const qtyFmt = new Intl.NumberFormat("en-US", { maximumFractionDigits: 4 });
const card = "rounded-xl border border-border bg-surface";
// Brickland's default: pieces.
const blankItem = () => ({ item_id: "", description: "", colour_id: "", colour_name: "", qty: "", uom: "PCS" });

/**
 * Trims Dyeing Programme → Program Items (AnnotechERP 06/08), as Brickland
 * lays it out: every programme on the left (searchable, with prod type and
 * item count); the trims being dyed on the one picked on the right, Add item
 * keeping one at once (trim, description, colour and shade name, quantity,
 * UOM) and Remove taking one off. Nothing reloads.
 *
 * @param {{ programs: object[], options: object, initial: ?object, canManage: boolean, actions: object }} props
 */
function TrimDyeingItems({ programs: initialPrograms, options, initial, canManage, actions }) {
  const t = useT("merchandise");
  const m = (k, p) => t(`master.tdp.${k}`, p);
  const toastManager = useToastManager();
  const [programs, setPrograms] = useState(initialPrograms);
  const [query, setQuery] = useState("");
  const [data, setData] = useState(initial);
  const [adding, setAdding] = useState(false);
  const [item, setItem] = useState(blankItem);
  const [itemError, setItemError] = useState(null);
  const [errors, setErrors] = useState(null);
  const [pending, startTransition] = useTransition();
  const shown = useMemo(() => programs.filter((p) => !query.trim() || p.program_no.toLowerCase().includes(query.trim().toLowerCase())), [programs, query]);

  function take(next) {
    setData(next);
    setPrograms((all) => all.map((p) => (p.program_no === next.program_no ? { ...p, items: next.items.length } : p)));
    setErrors(null);
  }

  function open(no) {
    window.history.replaceState(null, "", `?tab=items&program=${encodeURIComponent(no)}`);
    startTransition(async () => {
      const result = await actions.load(no);
      if (result?.status === "success") take(result.data);
      else setErrors(messagesOf(result));
    });
  }

  function add() {
    startTransition(async () => {
      const blank = (v) => (v === "" ? null : v);
      const result = await actions.add(data.program_no, { item_id: blank(item.item_id), description: item.description, colour_id: blank(item.colour_id), colour_name: item.colour_name, qty: item.qty === "" ? null : Number(item.qty), uom: blank(item.uom) });
      if (result?.status !== "success") return setItemError(messagesOf(result).join(" "));
      take(result.data);
      setAdding(false);
      setItem(blankItem());
      setItemError(null);
      toastManager.add({ title: m("item_added", { no: data.program_no }), type: "success" });
    });
  }

  function remove(id) {
    startTransition(async () => {
      const result = await actions.remove(data.program_no, id);
      if (result?.status !== "success") return setErrors(messagesOf(result));
      take(result.data);
      toastManager.add({ title: m("item_removed", { no: data.program_no }), type: "success" });
    });
  }

  const field = "flex flex-col gap-1.5";
  const lab = "text-[13px] font-semibold text-foreground";
  const star = <span className="text-danger">*</span>;
  return (
    <div className="grid gap-4 lg:grid-cols-[20rem_1fr]">
      <section className={cn(card, "self-start overflow-hidden")}>
        <div className="flex items-center justify-between px-5 pt-4">
          <h2 className="text-base font-semibold text-foreground">{m("programs")}</h2>
          <span className="rounded-full bg-surface-muted px-2 py-0.5 text-xs font-semibold text-foreground">{programs.length}</span>
        </div>
        <div className="relative px-5 py-3">
          <Search className="pointer-events-none absolute top-1/2 left-7.5 size-4 -translate-y-1/2 text-foreground-muted" />
          <Input value={query} onChange={(e) => setQuery(e.target.value)} placeholder={m("search")} className="pl-8" aria-label={m("search_programs")} />
        </div>
        <ul className="max-h-[36rem] overflow-y-auto border-t border-border">
          {shown.map((p) => (
            <li key={p.program_no}>
              <button type="button" onClick={() => open(p.program_no)} className={cn("flex w-full items-center justify-between gap-3 border-b border-border px-5 py-3 text-left hover:bg-surface-muted", data?.program_no === p.program_no && "bg-brand-subtle")}>
                <span>
                  <span className="block text-sm font-semibold text-foreground">{p.program_no}</span>
                  <span className="block text-xs text-foreground-muted">{m(`prod_types.${p.prod_type}`)}</span>
                </span>
                <span className="text-sm tabular-nums text-foreground">{p.items}</span>
              </button>
            </li>
          ))}
          {shown.length === 0 ? <li className="px-5 py-6 text-center text-sm text-foreground-muted">{m("no_match")}</li> : null}
        </ul>
      </section>

      <section className={cn(card, "flex flex-col self-start overflow-hidden")}>
        <div className="flex items-start justify-between gap-3 px-5 py-4">
          <div>
            <h2 className="flex items-center gap-2 text-base font-semibold text-foreground">
              <Palette className="size-4" /> {data ? m("items_of", { no: data.program_no }) : m("items")}
            </h2>
            <p className="mt-0.5 text-[13px] text-foreground-muted">{m("hint")}</p>
          </div>
          {data && canManage ? (
            <Button variant="outline" size="sm" onClick={() => (setAdding(true), setItemError(null))}>
              <Plus /> {m("add_item")}
            </Button>
          ) : null}
        </div>
        {errors ? <Alert variant="danger" className="mx-5 mb-3">{errors.join(" · ")}</Alert> : null}
        {!data ? (
          <div className="border-t border-border px-5 py-12 text-center">
            <p className="text-sm font-semibold text-foreground">{m("pick")}</p>
            <p className="mt-1 text-xs text-foreground-muted">{m("pick_hint")}</p>
          </div>
        ) : data.items.length === 0 ? (
          <div className="border-t border-border px-5 py-12 text-center">
            <p className="text-sm font-semibold text-foreground">{m("none", { no: data.program_no })}</p>
            <p className="mt-1 text-xs text-foreground-muted">{m("none_hint")}</p>
          </div>
        ) : (
          <div className="overflow-x-auto">
            <table className="w-full text-sm">
              <thead className="bg-brand text-brand-foreground">
                <tr>
                  {["trim", "description", "colour", "colour_name", "qty", "uom", "action"].map((k) => (
                    <th key={k} className={cn("px-3 py-2.5 text-left text-[13px] font-semibold whitespace-nowrap", k === "qty" && "text-right")}>
                      {m(`col.${k}`)}
                    </th>
                  ))}
                </tr>
              </thead>
              <tbody>
                {data.items.map((i) => (
                  <tr key={i.id} className="border-t border-border">
                    <td className="px-3 py-2 whitespace-nowrap">{i.trim ?? "—"}</td>
                    <td className="px-3 py-2">{i.description}</td>
                    <td className="px-3 py-2">{i.colour ?? "—"}</td>
                    <td className="px-3 py-2">{i.colour_name}</td>
                    <td className="px-3 py-2 text-right tabular-nums">{qtyFmt.format(i.qty)}</td>
                    <td className="px-3 py-2">{i.uom ?? "—"}</td>
                    <td className="px-3 py-2">
                      {canManage ? (
                        <Button variant="outline" size="sm" className="text-danger" onClick={() => remove(i.id)} disabled={pending}>
                          <Trash2 /> {m("remove")}
                        </Button>
                      ) : null}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
        {data ? <p className="border-t border-border px-5 py-3 text-xs text-foreground-muted">{m("count", { n: data.items.length })}</p> : null}
      </section>

      <Modal open={adding} onOpenChange={setAdding} title={m("add_item_of", { no: data?.program_no ?? "" })} className="max-w-2xl">
        <div className="rounded-lg border border-border p-4">
          <h3 className="text-sm font-semibold text-foreground">{m("new_item")}</h3>
          <div className="mt-3 grid gap-3 sm:grid-cols-2">
            <div className={field}>
              <span className={lab}>{m("f.item_id")}</span>
              <Select
                value={item.item_id}
                onValueChange={(v) => {
                  const trim = options.items.find((x) => x.value === v);
                  setItem((it) => ({ ...it, item_id: v ?? "", description: trim && !it.description ? trim.name : it.description, uom: trim?.uom || it.uom }));
                }}
                options={[{ value: "", label: m("select") }, ...options.items.map(({ value, label }) => ({ value, label }))]}
                placeholder={m("select")}
                aria-label={m("f.item_id")}
              />
            </div>
            <label className={field}>
              <span className={lab}>
                {m("f.description")}
                {star}
              </span>
              <Input value={item.description} onChange={(e) => setItem((it) => ({ ...it, description: e.target.value }))} maxLength={200} aria-label={m("f.description")} />
            </label>
            <div className={field}>
              <span className={lab}>{m("f.colour_id")}</span>
              <Select
                value={item.colour_id}
                onValueChange={(v) => setItem((it) => ({ ...it, colour_id: v ?? "", colour_name: options.colours.find((c) => c.value === v)?.name ?? it.colour_name }))}
                options={[{ value: "", label: m("select") }, ...options.colours.map(({ value, label }) => ({ value, label }))]}
                placeholder={m("select")}
                aria-label={m("f.colour_id")}
              />
            </div>
            <label className={field}>
              <span className={lab}>
                {m("f.colour_name")}
                {star}
              </span>
              <Input value={item.colour_name} onChange={(e) => setItem((it) => ({ ...it, colour_name: e.target.value }))} maxLength={80} aria-label={m("f.colour_name")} />
            </label>
            <label className={field}>
              <span className={lab}>
                {m("f.qty")}
                {star}
              </span>
              <Input type="number" min="0" step="any" value={item.qty} onChange={(e) => setItem((it) => ({ ...it, qty: e.target.value }))} aria-label={m("f.qty")} />
            </label>
            <label className={field}>
              <span className={lab}>{m("f.uom")}</span>
              <UomSelect value={item.uom} onChange={(v) => setItem((it) => ({ ...it, uom: v }))} aria-label={m("f.uom")} />
            </label>
          </div>
        </div>
        {itemError ? <p className="mt-3 text-sm text-danger">{itemError}</p> : null}
        <div className="mt-4 flex flex-wrap items-center justify-between gap-2">
          <span className="text-xs text-foreground-muted">{m("footer")}</span>
          <div className="flex gap-2">
            <Button variant="outline" onClick={() => (setItem(blankItem()), setItemError(null))} disabled={pending}>
              <RotateCcw /> {m("reset")}
            </Button>
            <Button onClick={add} loading={pending}>
              <Save /> {m("add_item")}
            </Button>
          </div>
        </div>
      </Modal>
    </div>
  );
}

const todayIso = () => new Intl.DateTimeFormat("en-CA", { timeZone: "Asia/Dhaka" }).format(new Date());
const blankProgram = (date) => ({ prod_type: "BULK", buyer_id: "", order_id: "", program_date: date, status: "DRAFT", remarks: "" });

/**
 * Trims Dyeing Programme → New dyeing program (AnnotechERP 06/08), as
 * Brickland lays it out: prod type, buyer, style/order (picking it names the
 * buyer), program date, status and remarks; Reset and Save dyeing program.
 * Opened from a record's Edit, the same form corrects that programme.
 *
 * @param {{ options: object, initial: ?object, canManage: boolean, save: (no: ?string, payload: object) => Promise<object> }} props
 */
function TrimDyeingForm({ options, initial, canManage, save }) {
  const t = useT("merchandise");
  const m = (k, p) => t(`master.tdp.${k}`, p);
  const toastManager = useToastManager();
  const [today] = useState(todayIso);
  const [program, setProgram] = useState(initial);
  const headOf = (p) => (p ? Object.fromEntries(Object.entries(blankProgram(today)).map(([k, v]) => [k, p[k] === null || p[k] === undefined ? v : str(p[k])])) : blankProgram(today));
  const [form, setForm] = useState(() => headOf(initial));
  const [errors, setErrors] = useState(null);
  const [pending, startTransition] = useTransition();
  const set = (patch) => setForm((f) => ({ ...f, ...patch }));

  function submit() {
    startTransition(async () => {
      const result = await save(program?.program_no ?? null, Object.fromEntries(Object.entries(form).map(([k, v]) => [k, v === "" ? null : v])));
      if (result?.status !== "success") return setErrors(messagesOf(result));
      setErrors(null);
      toastManager.add({ title: m("saved", { no: result.data.program_no }), type: "success" });
      if (program) {
        setProgram(result.data);
        setForm(headOf(result.data));
      } else setForm(blankProgram(today));
    });
  }

  const field = "flex flex-col gap-1.5";
  const lab = "text-[13px] font-semibold text-foreground";
  return (
    <div className="flex flex-col gap-4">
      <section className={cn(card, "p-5")}>
        <h2 className="text-base font-semibold text-foreground">{program ? m("edit_of", { no: program.program_no }) : m("new")}</h2>
        <p className="mt-0.5 text-[13px] text-foreground-muted">{m("form_hint")}</p>
        <div className="mt-4 grid gap-4 md:grid-cols-2">
          <div className={field}>
            <span className={lab}>{m("f.prod_type")}</span>
            <Select value={form.prod_type} onValueChange={(v) => set({ prod_type: v ?? "" })} options={[{ value: "", label: m("select") }, ...options.prod_types.map((v) => ({ value: v, label: m(`prod_types.${v}`) }))]} disabled={!canManage} aria-label={m("f.prod_type")} />
          </div>
          <div className={field}>
            <span className={lab}>{m("f.buyer_id")}</span>
            <Select value={form.buyer_id} onValueChange={(v) => set({ buyer_id: v ?? "" })} options={[{ value: "", label: m("select") }, ...options.buyers]} disabled={!canManage} aria-label={m("f.buyer_id")} />
            {options.buyers.length === 0 ? <span className="text-xs text-foreground-muted">{m("no_buyers")}</span> : null}
          </div>
          <div className={field}>
            <span className={lab}>{m("f.order_id")}</span>
            <Select
              value={form.order_id}
              onValueChange={(v) => set({ order_id: v ?? "", ...(form.buyer_id ? {} : { buyer_id: options.orders.find((o) => o.value === v)?.buyer_id ?? "" }) })}
              options={[{ value: "", label: m("select") }, ...options.orders.map(({ value, label }) => ({ value, label }))]}
              placeholder={m("select")}
              disabled={!canManage}
              aria-label={m("f.order_id")}
            />
          </div>
          <label className={field}>
            <span className={lab}>
              {m("f.program_date")}
              <span className="text-danger">*</span>
            </span>
            <Input type="date" value={form.program_date} onChange={(e) => set({ program_date: e.target.value })} disabled={!canManage} aria-label={m("f.program_date")} />
          </label>
          <div className={field}>
            <span className={lab}>{m("f.status")}</span>
            <Select value={form.status} onValueChange={(v) => set({ status: v ?? "" })} options={[{ value: "", label: m("select") }, ...options.statuses.map((s) => ({ value: s, label: m(`status.${s}`) }))]} disabled={!canManage} aria-label={m("f.status")} />
          </div>
          <label className={cn(field, "md:col-span-2")}>
            <span className={lab}>{m("f.remarks")}</span>
            <Textarea rows={3} value={form.remarks} onChange={(e) => set({ remarks: e.target.value })} maxLength={2000} disabled={!canManage} aria-label={m("f.remarks")} />
          </label>
        </div>
      </section>
      {errors ? <Alert variant="danger">{errors.join(" · ")}</Alert> : null}
      <section className={cn(card, "flex flex-wrap items-center justify-between gap-3 p-5")}>
        <p className="text-[13px] text-foreground-muted">{m("footer")}</p>
        <div className="flex gap-2">
          <Button variant="outline" onClick={() => (setForm(headOf(program)), setErrors(null))} disabled={pending}>
            <RotateCcw /> {m("reset")}
          </Button>
          {canManage ? (
            <Button onClick={submit} loading={pending}>
              <Save /> {m("save")}
            </Button>
          ) : null}
        </div>
      </section>
    </div>
  );
}

export { TrimDyeingForm, TrimDyeingItems };
