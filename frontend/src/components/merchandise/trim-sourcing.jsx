"use client";

import { useMemo, useState, useTransition } from "react";
import { ListChecks, RotateCcw, Save, Search, Trash2 } from "lucide-react";
import { cn } from "@/lib/utils";
import { Input } from "@/components/ui/input";
import { Select } from "@/components/ui/select";
import { Textarea } from "@/components/ui/textarea";
import { Checkbox } from "@/components/ui/checkbox";
import { Button } from "@/components/ui/button";
import { ConfirmDialog } from "@/components/ui/confirm-dialog";
import { Alert } from "@/components/ui/alert";
import { useToastManager } from "@/components/ui/toast";
import { useT } from "@/lib/i18n";

// An error's field messages, or its message when it names no field.
const messagesOf = (result) => {
  const fields = [...new Set(Object.values(result?.errors ?? {}).flat())];
  return fields.length ? fields : [result?.message ?? ""];
};
const str = (v) => (v === null || v === undefined ? "" : String(v));
const dateFmt = new Intl.DateTimeFormat("en-GB", { day: "2-digit", month: "short", year: "numeric", timeZone: "UTC" });
const day = (v) => (v ? dateFmt.format(new Date(`${v}T00:00:00Z`)) : "—");
const card = "rounded-xl border border-border bg-surface";
const asRow = (l) => ({ ...l, supplier_id: str(l.supplier_id), status: str(l.status), remarks: str(l.remarks) });

/**
 * Trims & Accessories Sourcing → Details (AnnotechERP 06/08), as Brickland
 * lays it out: the confirmed orders on the left; picking one loads every trim
 * sourced for it, where the supplier, the buyer's nomination and the status
 * (approving the sourcing) are set line by line. A nominated supplier is not
 * swapped while the nomination stands. Nothing reloads.
 *
 * @param {{ orders: object[], options: object, initial: ?{ order: string, lines: object[] }, canManage: boolean, actions: object }} props
 */
function TrimSourcingDetails({ orders: initialOrders, options, initial, canManage, actions }) {
  const t = useT("merchandise");
  const m = (k, p) => t(`master.tsr.${k}`, p);
  const toastManager = useToastManager();
  const [orders, setOrders] = useState(initialOrders);
  const [query, setQuery] = useState("");
  const [picked, setPicked] = useState(() => (initial ? initialOrders.find((o) => o.order_no === initial.order) ?? null : null));
  const [lines, setLines] = useState(() => (initial?.lines ?? []).map(asRow));
  const [confirm, setConfirm] = useState(null);
  const [errors, setErrors] = useState(null);
  const [pending, startTransition] = useTransition();
  const shown = useMemo(() => {
    const needle = query.trim().toLowerCase();
    return orders.filter((o) => !needle || o.title.toLowerCase().includes(needle));
  }, [orders, query]);

  function take(next) {
    setLines(next.map(asRow));
    setOrders((all) => all.map((o) => (o.order_no === picked?.order_no ? { ...o, lines: next.length } : o)));
    setErrors(null);
  }

  function pick(o) {
    setPicked(o);
    window.history.replaceState(null, "", `?tab=details&order=${encodeURIComponent(o.order_no)}`);
    startTransition(async () => {
      const result = await actions.forOrder(o.order_no);
      if (result?.status !== "success") return setErrors(messagesOf(result));
      setLines(result.data.map(asRow));
      setErrors(null);
    });
  }

  function saveRow(l) {
    startTransition(async () => {
      const result = await actions.update(l.id, { item_id: l.item_id, description: l.description, supplier_id: l.supplier_id || null, nominated: l.nominated, status: l.status || null, remarks: l.remarks || null });
      if (result?.status !== "success") return setErrors(messagesOf(result));
      take(result.data);
      toastManager.add({ title: m("saved"), type: "success" });
    });
  }

  function remove() {
    startTransition(async () => {
      const result = await actions.remove(confirm);
      setConfirm(null);
      if (result?.status !== "success") return setErrors(messagesOf(result));
      take(result.data);
      toastManager.add({ title: m("removed"), type: "success" });
    });
  }

  const set = (id, patch) => setLines((all) => all.map((l) => (l.id === id ? { ...l, ...patch } : l)));
  return (
    <div className="grid gap-4 lg:grid-cols-[22rem_1fr]">
      <section className={cn(card, "self-start overflow-hidden")}>
        <div className="flex items-center justify-between px-5 pt-4">
          <h2 className="text-base font-semibold text-foreground">{m("orders")}</h2>
          <span className="rounded-full bg-surface-muted px-2 py-0.5 text-xs font-semibold text-foreground">{orders.length}</span>
        </div>
        <div className="relative px-5 py-3">
          <Search className="pointer-events-none absolute top-1/2 left-7.5 size-4 -translate-y-1/2 text-foreground-muted" />
          <Input value={query} onChange={(e) => setQuery(e.target.value)} placeholder={m("search")} className="pl-8" aria-label={m("search_orders")} />
        </div>
        <ul className="max-h-[36rem] overflow-y-auto border-t border-border">
          {shown.map((o) => (
            <li key={o.order_no}>
              <button type="button" onClick={() => pick(o)} className={cn("flex w-full items-center justify-between gap-3 border-b border-border px-5 py-3 text-left hover:bg-surface-muted", picked?.order_no === o.order_no && "bg-brand-subtle")}>
                <span>
                  <span className="block text-sm font-semibold text-foreground">{o.title}</span>
                  <span className="block text-xs text-foreground-muted">{day(o.ship_date)}</span>
                </span>
                <span className="text-sm tabular-nums text-foreground">{o.lines}</span>
              </button>
            </li>
          ))}
          {shown.length === 0 ? <li className="px-5 py-6 text-center text-sm text-foreground-muted">{m("no_match")}</li> : null}
        </ul>
      </section>

      <section className={cn(card, "flex flex-col self-start overflow-hidden")}>
        <div className="px-5 py-4">
          <h2 className="flex items-center gap-2 text-base font-semibold text-foreground">
            <ListChecks className="size-4" /> {picked ? m("trims_of", { order: picked.title }) : m("trims")}
          </h2>
          <p className="mt-0.5 text-[13px] text-foreground-muted">{m("hint")}</p>
        </div>
        {errors ? <Alert variant="danger" className="mx-5 mb-3">{errors.join(" · ")}</Alert> : null}
        {!picked ? (
          <div className="border-t border-border px-5 py-12 text-center">
            <p className="text-sm font-semibold text-foreground">{m("pick")}</p>
            <p className="mt-1 text-xs text-foreground-muted">{m("pick_hint")}</p>
          </div>
        ) : lines.length === 0 ? (
          <div className="border-t border-border px-5 py-12 text-center">
            <p className="text-sm font-semibold text-foreground">{m("none", { order: picked.title })}</p>
            <p className="mt-1 text-xs text-foreground-muted">{m("none_hint")}</p>
          </div>
        ) : (
          <div className="overflow-x-auto">
            <table className="w-full text-sm">
              <thead className="bg-brand text-brand-foreground">
                <tr>
                  {["trim", "description", "supplier", "nominated", "status", "action"].map((k) => (
                    <th key={k} className="px-3 py-2.5 text-left text-[13px] font-semibold whitespace-nowrap">
                      {m(`col.${k}`)}
                    </th>
                  ))}
                </tr>
              </thead>
              <tbody>
                {lines.map((l, i) => (
                  <tr key={l.id} className="border-t border-border">
                    <td className="px-3 py-2 whitespace-nowrap">{l.trim}</td>
                    <td className="px-3 py-2">{l.description}</td>
                    <td className="px-3 py-2">
                      <div className="w-56">
                        <Select value={l.supplier_id} onValueChange={(v) => set(l.id, { supplier_id: v ?? "" })} options={[{ value: "", label: m("select") }, ...options.suppliers]} disabled={!canManage} aria-label={`${m("col.supplier")} ${i + 1}`} />
                      </div>
                    </td>
                    <td className="px-3 py-2">
                      <Checkbox checked={l.nominated} onCheckedChange={(v) => set(l.id, { nominated: Boolean(v) })} disabled={!canManage} aria-label={`${m("col.nominated")} ${i + 1}`} />
                    </td>
                    <td className="px-3 py-2">
                      <div className="w-36">
                        <Select value={l.status} onValueChange={(v) => set(l.id, { status: v ?? "" })} options={options.statuses.map((s) => ({ value: s, label: m(`status.${s}`) }))} disabled={!canManage} aria-label={`${m("col.status")} ${i + 1}`} />
                      </div>
                    </td>
                    <td className="px-3 py-2">
                      {canManage ? (
                        <div className="flex gap-1.5">
                          <Button variant="outline" size="sm" onClick={() => saveRow(l)} disabled={pending}>
                            <Save /> {m("save_row")}
                          </Button>
                          <Button variant="outline" size="sm" className="text-danger" onClick={() => setConfirm(l.id)} disabled={pending} aria-label={`${m("remove")} ${i + 1}`}>
                            <Trash2 />
                          </Button>
                        </div>
                      ) : null}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
        {picked ? <p className="border-t border-border px-5 py-3 text-xs text-foreground-muted">{m("count", { n: lines.length })}</p> : null}
      </section>

      <ConfirmDialog
        open={confirm !== null}
        onOpenChange={(o) => !o && setConfirm(null)}
        title={m("confirm_remove_title")}
        description={m("confirm_remove_hint")}
        confirmLabel={m("remove")}
        cancelLabel={m("cancel")}
        destructive
        onConfirm={remove}
        loading={pending}
      />
    </div>
  );
}

const blank = () => ({ order_id: "", item_id: "", description: "", supplier_id: "", nominated: false, status: "DRAFT", remarks: "" });

/**
 * Trims & Accessories Sourcing → New sourcing line (AnnotechERP 06/08), as
 * Brickland lays it out: the order, the trim (its name fills the
 * description), the supplier, whether the buyer nominated it, the status and
 * remarks; Reset and Save sourcing line. Nothing reloads.
 *
 * @param {{ options: object, canManage: boolean, save: (payload: object) => Promise<object> }} props
 */
function TrimSourcingForm({ options, canManage, save }) {
  const t = useT("merchandise");
  const m = (k, p) => t(`master.tsr.${k}`, p);
  const toastManager = useToastManager();
  const [form, setForm] = useState(blank);
  const [errors, setErrors] = useState(null);
  const [pending, startTransition] = useTransition();
  const set = (patch) => setForm((f) => ({ ...f, ...patch }));

  function submit() {
    startTransition(async () => {
      const result = await save({ ...form, order_id: form.order_id || null, item_id: form.item_id || null, supplier_id: form.supplier_id || null, status: form.status || null, remarks: form.remarks || null });
      if (result?.status !== "success") return setErrors(messagesOf(result));
      setForm(blank());
      setErrors(null);
      toastManager.add({ title: m("saved"), type: "success" });
    });
  }

  const field = "flex flex-col gap-1.5";
  const lab = "text-[13px] font-semibold text-foreground";
  const star = <span className="text-danger">*</span>;
  return (
    <div className="flex flex-col gap-4">
      <section className={cn(card, "p-5")}>
        <h2 className="text-base font-semibold text-foreground">{m("new")}</h2>
        <div className="mt-4 grid gap-4 md:grid-cols-2">
          <div className={field}>
            <span className={lab}>
              {m("f.order_id")}
              {star}
            </span>
            <Select value={form.order_id} onValueChange={(v) => set({ order_id: v ?? "" })} options={[{ value: "", label: m("select") }, ...options.orders.map(({ value, label }) => ({ value, label }))]} placeholder={m("select")} disabled={!canManage} aria-label={m("f.order_id")} />
          </div>
          <div className={field}>
            <span className={lab}>{m("f.item_id")}</span>
            <Select
              value={form.item_id}
              onValueChange={(v) => set({ item_id: v ?? "", ...(form.description ? {} : { description: options.items.find((x) => x.value === v)?.name ?? "" }) })}
              options={[{ value: "", label: m("select") }, ...options.items.map(({ value, label }) => ({ value, label }))]}
              placeholder={m("select")}
              disabled={!canManage}
              aria-label={m("f.item_id")}
            />
          </div>
          <label className={field}>
            <span className={lab}>
              {m("f.description")}
              {star}
            </span>
            <Input value={form.description} onChange={(e) => set({ description: e.target.value })} maxLength={200} disabled={!canManage} aria-label={m("f.description")} />
          </label>
          <div className={field}>
            <span className={lab}>{m("f.supplier_id")}</span>
            <Select value={form.supplier_id} onValueChange={(v) => set({ supplier_id: v ?? "" })} options={[{ value: "", label: m("select") }, ...options.suppliers]} placeholder={m("select")} disabled={!canManage} aria-label={m("f.supplier_id")} />
          </div>
          <label className="flex items-start gap-2 md:col-span-2">
            <Checkbox checked={form.nominated} onCheckedChange={(v) => set({ nominated: Boolean(v) })} disabled={!canManage} aria-label={m("f.nominated")} />
            <span>
              <span className={lab}>{m("f.nominated")}</span>
              <span className="block text-xs text-foreground-muted">{m("nominated_hint")}</span>
            </span>
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
          <Button variant="outline" onClick={() => (setForm(blank()), setErrors(null))} disabled={pending}>
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

export { TrimSourcingDetails, TrimSourcingForm };
