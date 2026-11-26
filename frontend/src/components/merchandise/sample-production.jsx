"use client";

import { useMemo, useState, useTransition } from "react";
import { printDocument, cardGrid } from "@/lib/print-document";
import { ChevronLeft, ChevronRight, ListChecks, Plus, Printer, Save, Send, Trash2 } from "lucide-react";
import { cn } from "@/lib/utils";
import { Input } from "@/components/ui/input";
import { Select } from "@/components/ui/select";
import { Checkbox } from "@/components/ui/checkbox";
import { Button } from "@/components/ui/button";
import { Badge } from "@/components/ui/badge";
import { Alert } from "@/components/ui/alert";
import { Modal } from "@/components/ui/modal";
import { useToastManager } from "@/components/ui/toast";
import { useT } from "@/lib/i18n";

const num = (v) => (v === "" || v === null || Number.isNaN(Number(v)) ? 0 : Number(v));
const asEdit = (lines) => Object.fromEntries(lines.map((l) => [l.id, { sewn_qty: String(l.sewn_qty), delivered_qty: String(l.delivered_qty), rejected_qty: String(l.rejected_qty), batch_remarks: l.batch_remarks ?? "" }]));

/**
 * Sample Production Entry (AnnotechERP 06/10), Brickland's workbench: the
 * Program Parameter (date and batch) and the Production Order List on the
 * left; the planned sample lines to tick and Add To Batch, and the batch's
 * lines with what each sewed, delivered and rejected, on the right; the card
 * format, Print, New Batch, Save and Submit below. New Batch opens a batch
 * on the date; Submit closes it. A batch is named in the url by its number;
 * nothing reloads.
 *
 * @param {{ data: { planned: object[], batches: object[], card_formats: string[] }, initial: ?object, act: object, canManage: boolean, today: string }} props
 */
function SampleProduction({ data, initial, act, canManage, today }) {
  const t = useT("merchandise");
  const m = (k, p) => t(`master.spe.${k}`, p);
  const toastManager = useToastManager();
  const [planned, setPlanned] = useState(data.planned);
  const [batches, setBatches] = useState(data.batches);
  const [date, setDate] = useState(today);
  const [current, setCurrent] = useState(initial);
  const [edit, setEdit] = useState(() => asEdit(initial?.lines ?? []));
  const [format, setFormat] = useState(initial?.batch.card_format ?? "LINDEX");
  const [ticked, setTicked] = useState(() => new Set());
  const [group, setGroup] = useState(null);
  const [showList, setShowList] = useState(true);
  const [confirming, setConfirming] = useState(false);
  const [errors, setErrors] = useState(null);
  const [pending, startTransition] = useTransition();
  const batch = current?.batch ?? null;
  const open = batch?.status === "DRAFT";
  const lines = current?.lines ?? [];
  const toast = (title) => toastManager.add({ title, type: "success" });
  const fail = (result) => setErrors(result?.errors ? [...new Set(Object.values(result.errors).flat())] : [result?.message]);

  const groups = useMemo(() => {
    const map = new Map();
    for (const l of planned) {
      const key = `${l.job}|${l.type}`;
      map.set(key, { key, job: l.job, type: l.type, count: (map.get(key)?.count ?? 0) + 1 });
    }
    return [...map.values()];
  }, [planned]);
  const shown = group ? planned.filter((l) => `${l.job}|${l.type}` === group) : planned;
  const totals = lines.reduce((s, l) => ({ s: s.s + num(edit[l.id]?.sewn_qty), d: s.d + num(edit[l.id]?.delivered_qty), r: s.r + num(edit[l.id]?.rejected_qty) }), { s: 0, d: 0, r: 0 });
  const batchLabel = (b) => `${b.batch_no} — ${b.batch_date} · ${m(`status.${b.status}`)}`;

  function show(result) {
    setCurrent(result);
    setEdit(asEdit(result.lines));
    setFormat(result.batch.card_format);
    setBatches((all) => [result.batch, ...all.filter((b) => b.id !== result.batch.id)].sort((a, b) => b.batch_no.localeCompare(a.batch_no)));
    window.history.replaceState(null, "", `?batch=${encodeURIComponent(result.batch.batch_no)}`);
  }

  // Planned lines change when lines join or leave a batch.
  async function refreshPlanned() {
    const result = await act.load();
    if (result?.status === "success") setPlanned(result.data.planned);
  }

  function pickBatch(id) {
    setErrors(null);
    if (!id) {
      setCurrent(null);
      setEdit({});
      window.history.replaceState(null, "", window.location.pathname);
      return;
    }
    startTransition(async () => {
      const result = await act.show(id);
      if (result?.status === "success") show(result.data);
      else fail(result);
    });
  }

  function newBatch() {
    startTransition(async () => {
      const result = await act.open({ batch_date: date, card_format: format });
      if (result?.status === "success") {
        show(result.data);
        setErrors(null);
        toast(m("opened", { no: result.data.batch.batch_no }));
      } else fail(result);
    });
  }

  function addToBatch() {
    if (!open) {
      setErrors([m("open_first")]);
      return;
    }
    startTransition(async () => {
      const result = await act.add(batch.id, [...ticked]);
      if (result?.status === "success") {
        show(result.data);
        setTicked(new Set());
        setErrors(null);
        await refreshPlanned();
        toast(m("added", { n: result.data.added, no: result.data.batch.batch_no }));
      } else fail(result);
    });
  }

  function drop(lineId) {
    startTransition(async () => {
      const result = await act.drop(batch.id, lineId);
      if (result?.status === "success") {
        show(result.data);
        await refreshPlanned();
      } else fail(result);
    });
  }

  function save() {
    startTransition(async () => {
      const result = await act.save(batch.id, {
        card_format: format,
        lines: lines.map((l) => ({ id: l.id, sewn_qty: num(edit[l.id].sewn_qty), delivered_qty: num(edit[l.id].delivered_qty), rejected_qty: num(edit[l.id].rejected_qty), batch_remarks: edit[l.id].batch_remarks || null })),
      });
      if (result?.status === "success") {
        show(result.data);
        setErrors(null);
        toast(m("updated", { no: result.data.batch.batch_no, n: result.data.updated }));
      } else fail(result);
    });
  }

  function submit() {
    startTransition(async () => {
      const result = await act.submit(batch.id);
      setConfirming(false);
      if (result?.status === "success") {
        show(result.data);
        setErrors(null);
        toast(m("submitted", { no: result.data.batch.batch_no, n: result.data.submitted }));
      } else fail(result);
    });
  }

  // The sample cards, one per line in the chosen format, in a window of their own to print.
  function printCards() {
    const title = format === "LINDEX" ? m("card.lindex_title") : m("card.other_title");
    printDocument({
      title,
      docNo: batch?.batch_no,
      blocks: [
        {
          html: cardGrid(
            lines.map((l) => ({
              title,
              rows: [
                [m("card.batch"), batch?.batch_no],
                [m("card.date"), batch?.batch_date],
                [m("col.buyer_ac"), l.buyer_ac],
                [m("col.job"), l.job],
                [m("col.type"), l.type],
                [m("col.item"), l.item],
                [m("col.colour"), l.colour],
                [m("col.size"), l.size],
                [m("col.sewn_qty"), `${edit[l.id]?.sewn_qty ?? l.sewn_qty} ${l.unit ?? ""}`],
              ],
              note: format === "LINDEX" ? m("card.lindex_note") : undefined,
            })),
          ),
        },
      ],
    });
  }

  const th = "px-3 py-2.5 text-left text-[13px] font-semibold whitespace-nowrap";
  const td = "px-3 py-2";
  const card = "rounded-xl border border-border bg-surface";
  const set = (id, patch) => setEdit((e) => ({ ...e, [id]: { ...e[id], ...patch } }));
  return (
    <div className="flex flex-col gap-4">
      <div className={cn("grid gap-4", showList ? "lg:grid-cols-[18rem_1fr]" : "")}>
        {showList ? (
          <div className="flex flex-col gap-4">
            <section className={cn(card, "p-5")}>
              <h2 className="text-base font-semibold text-foreground">{m("parameter")}</h2>
              <label className="mt-3 flex flex-col gap-1.5 text-[13px] font-semibold text-foreground">
                <span>{m("date")}</span>
                <Input type="date" value={date} onChange={(e) => setDate(e.target.value)} disabled={!canManage} />
                <span className="text-xs font-normal text-foreground-muted">{m("date_hint")}</span>
              </label>
              <label className="mt-3 flex flex-col gap-1.5 text-[13px] font-semibold text-foreground">
                <span>{m("batch")}</span>
                <Select value={batch?.id ?? ""} onValueChange={(v) => pickBatch(v ?? "")} options={[{ value: "", label: m("new_batch_option") }, ...batches.map((b) => ({ value: b.id, label: batchLabel(b) }))]} placeholder={m("new_batch_option")} />
              </label>
            </section>
            <section className={card}>
              <h2 className="px-5 pt-4 pb-3 text-base font-semibold text-foreground">{m("order_list")}</h2>
              {groups.length ? (
                <table className="w-full text-sm">
                  <thead>
                    <tr className="bg-brand text-brand-foreground">
                      <th className={th}>{m("col.job")}</th>
                      <th className={th}>{m("col.type")}</th>
                    </tr>
                  </thead>
                  <tbody>
                    {groups.map((g) => (
                      <tr key={g.key} className={cn("cursor-pointer border-b border-border last:border-0 hover:bg-surface-muted", group === g.key && "bg-brand-subtle")} onClick={() => setGroup(group === g.key ? null : g.key)}>
                        <td className={td}>{g.job}</td>
                        <td className={td}>
                          <span className="flex items-center justify-between gap-2">
                            {g.type}
                            <Badge variant="neutral">{g.count}</Badge>
                          </span>
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              ) : (
                <p className="px-5 pb-4 text-[13px] text-foreground-muted">{m("order_list_empty")}</p>
              )}
            </section>
          </div>
        ) : null}

        <div className="flex min-w-0 flex-col gap-4">
          <button type="button" className="flex w-fit items-center gap-1 text-[13px] font-medium text-brand hover:underline" onClick={() => setShowList((v) => !v)}>
            {showList ? <ChevronLeft className="size-4" /> : <ChevronRight className="size-4" />}
            {showList ? m("hide_list") : m("show_list")}
          </button>

          <section className={card}>
            <div className="flex items-start justify-between gap-3 px-5 pt-4 pb-3">
              <div>
                <h2 className="text-base font-semibold text-foreground">{m("entry")}</h2>
                <p className="mt-0.5 text-[13px] text-foreground-muted">{m("entry_hint")}</p>
              </div>
              <Badge variant="neutral">{shown.length}</Badge>
            </div>
            {shown.length ? (
              <div className="overflow-x-auto">
                <table className="w-full text-sm">
                  <thead>
                    <tr className="bg-brand text-brand-foreground">
                      <th className={cn(th, "w-10")}>
                        <Checkbox checked={shown.length > 0 && shown.every((l) => ticked.has(l.id))} onCheckedChange={(c) => setTicked(c === true ? new Set(shown.map((l) => l.id)) : new Set())} aria-label={m("tick_all")} disabled={!canManage} />
                      </th>
                      {["buyer_ac", "job", "type", "item", "colour", "size", "req_qty", "unit", "remarks"].map((k) => (
                        <th key={k} className={th}>
                          {m(`col.${k}`)}
                        </th>
                      ))}
                    </tr>
                  </thead>
                  <tbody>
                    {shown.map((l) => (
                      <tr key={l.id} className="border-b border-border last:border-0">
                        <td className={td}>
                          <Checkbox
                            checked={ticked.has(l.id)}
                            onCheckedChange={(c) =>
                              setTicked((all) => {
                                const next = new Set(all);
                                if (c === true) next.add(l.id);
                                else next.delete(l.id);
                                return next;
                              })
                            }
                            aria-label={`${m("tick")} ${l.job} ${l.type}`}
                            disabled={!canManage}
                          />
                        </td>
                        <td className={td}>{l.buyer_ac ?? "—"}</td>
                        <td className={cn(td, "whitespace-nowrap")}>{l.job}</td>
                        <td className={td}>{l.type}</td>
                        <td className={td}>{l.item ?? "—"}</td>
                        <td className={td}>{l.colour ?? "—"}</td>
                        <td className={td}>{l.size ?? "—"}</td>
                        <td className={cn(td, "tabular-nums")}>{l.req_qty}</td>
                        <td className={td}>{l.unit ?? "—"}</td>
                        <td className={td}>{l.remarks ?? "—"}</td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            ) : (
              <div className="px-5 py-10 text-center">
                <p className="font-semibold text-foreground">{m("no_planned")}</p>
                <p className="text-sm text-foreground-muted">{m("no_planned_hint")}</p>
              </div>
            )}
            {canManage ? (
              <div className="flex items-center justify-end gap-3 border-t border-border px-5 py-3">
                <span className="text-[13px] text-foreground-muted">{m("selected", { n: ticked.size })}</span>
                <Button onClick={addToBatch} disabled={ticked.size === 0 || pending}>
                  <Plus /> {m("add_to_batch")}
                </Button>
              </div>
            ) : null}
          </section>

          <section className={card}>
            <div className="flex items-center justify-between gap-3 px-5 pt-4 pb-3">
              <h2 className="text-base font-semibold text-foreground">{m("delivery")}</h2>
              {batch ? <Badge variant={open ? "warning" : "success"}>{batchLabel(batch)}</Badge> : null}
            </div>
            {lines.length ? (
              <div className="overflow-x-auto">
                <table className="w-full text-sm">
                  <thead>
                    <tr className="bg-brand text-brand-foreground">
                      {["buyer_ac", "job", "type", "item", "colour", "size", "req_qty", "unit", "sewn_qty", "delivered_qty", "rejected_qty", "remarks"].map((k) => (
                        <th key={k} className={th}>
                          {m(`col.${k}`)}
                        </th>
                      ))}
                      {open && canManage ? <th className={th}>{m("col.remove")}</th> : null}
                    </tr>
                  </thead>
                  <tbody>
                    {lines.map((l) => (
                      <tr key={l.id} className="border-b border-border last:border-0">
                        <td className={td}>{l.buyer_ac ?? "—"}</td>
                        <td className={cn(td, "whitespace-nowrap")}>{l.job}</td>
                        <td className={td}>{l.type}</td>
                        <td className={td}>{l.item ?? "—"}</td>
                        <td className={td}>{l.colour ?? "—"}</td>
                        <td className={td}>{l.size ?? "—"}</td>
                        <td className={cn(td, "tabular-nums")}>{l.req_qty}</td>
                        <td className={td}>{l.unit ?? "—"}</td>
                        {["sewn_qty", "delivered_qty", "rejected_qty"].map((k) => (
                          <td key={k} className={td}>
                            <Input type="number" min="0" step="any" value={edit[l.id]?.[k] ?? "0"} onChange={(e) => set(l.id, { [k]: e.target.value })} className="w-20" disabled={!open || !canManage} aria-label={`${m(`col.${k}`)} ${l.job} ${l.type}`} />
                          </td>
                        ))}
                        <td className={td}>
                          <Input value={edit[l.id]?.batch_remarks ?? ""} onChange={(e) => set(l.id, { batch_remarks: e.target.value })} className="w-40" disabled={!open || !canManage} aria-label={`${m("col.remarks")} ${l.job} ${l.type}`} />
                        </td>
                        {open && canManage ? (
                          <td className={td}>
                            <Button variant="outline" size="icon" className="size-8 text-danger" aria-label={m("take_off")} title={m("take_off")} onClick={() => drop(l.id)} disabled={pending}>
                              <Trash2 />
                            </Button>
                          </td>
                        ) : null}
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            ) : (
              <div className="flex flex-col items-center gap-2 px-5 py-12 text-center">
                <span className="flex size-12 items-center justify-center rounded-xl bg-surface-muted text-foreground-muted">
                  <ListChecks className="size-5" />
                </span>
                <p className="font-semibold text-foreground">{m("batch_empty")}</p>
                <p className="text-sm text-foreground-muted">{m("batch_empty_hint")}</p>
              </div>
            )}
            <div className="flex flex-wrap items-center gap-4 border-t border-border px-5 py-3 text-[13px]">
              <span className="font-semibold text-foreground">{m("total_record", { n: lines.length })}</span>
              {[
                ["sewn_qty", totals.s],
                ["delivered_qty", totals.d],
                ["rejected_qty", totals.r],
              ].map(([k, v]) => (
                <span key={k} className="flex items-center gap-1.5 text-foreground-muted">
                  {m(`col.${k}`)}
                  <Badge variant="neutral">{v}</Badge>
                </span>
              ))}
            </div>
          </section>

          {errors ? <Alert variant="danger">{errors.join(" · ")}</Alert> : null}

          <section className={card}>
            <div className="flex flex-wrap items-center justify-between gap-4 px-5 py-3">
              <div role="radiogroup" aria-label={m("card_format")} className="flex flex-col gap-1">
                <span className="text-[13px] font-semibold text-foreground">{m("card_format")}</span>
                <div className="flex gap-4">
                  {data.card_formats.map((f) => (
                    <label key={f} className="flex cursor-pointer items-center gap-1.5 text-sm text-foreground">
                      <input type="radio" name="spe-card" value={f} checked={format === f} onChange={() => setFormat(f)} disabled={batch !== null && !open} className="size-4 accent-[var(--color-brand)]" />
                      {m(`format.${f}`)}
                    </label>
                  ))}
                </div>
              </div>
              <div className="flex flex-wrap gap-2">
                <Button variant="outline" onClick={printCards} disabled={lines.length === 0}>
                  <Printer /> {m("print")}
                </Button>
                {canManage ? (
                  <>
                    <Button variant="outline" onClick={newBatch} disabled={pending}>
                      <Plus /> {m("new_batch")}
                    </Button>
                    <Button onClick={save} disabled={!open || lines.length === 0} loading={pending}>
                      <Save /> {m("save")}
                    </Button>
                    <Button onClick={() => setConfirming(true)} disabled={!open || lines.length === 0 || pending}>
                      <Send /> {m("submit")}
                    </Button>
                  </>
                ) : null}
              </div>
            </div>
            <p className="border-t border-border px-5 py-3 text-[13px] text-foreground-muted">{m("rule")}</p>
          </section>
        </div>
      </div>

      <Modal
        open={confirming}
        onOpenChange={setConfirming}
        title={m("submit_title", { no: batch?.batch_no ?? "" })}
        description={m("submit_hint")}
        footer={
          <>
            <Button variant="outline" onClick={() => setConfirming(false)} disabled={pending}>
              {m("cancel")}
            </Button>
            <Button onClick={submit} loading={pending}>
              <Send /> {m("submit")}
            </Button>
          </>
        }
      >
        <p className="text-sm text-foreground-muted">{m("total_record", { n: lines.length })}</p>
      </Modal>
    </div>
  );
}

export { SampleProduction };
