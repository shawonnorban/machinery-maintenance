"use client";

import { useMemo, useState, useTransition } from "react";
import { Download, LayoutList, Plus, RotateCcw, Save, Search, Trash2 } from "lucide-react";
import { cn } from "@/lib/utils";
import { Input } from "@/components/ui/input";
import { Select } from "@/components/ui/select";
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
const FIELDS = ["description", "colour", "size", "qty", "uom", "rate"];
const asRows = (lines) => lines.map((l) => ({ ...l, ...Object.fromEntries(FIELDS.map((k) => [k, str(l[k])])) }));
// Brickland's default: pieces.
const blankLine = () => ({ item_id: "", description: "", colour: "", size: "", qty: "", uom: "PCS", rate: "" });
const num = (v) => (v === "" ? null : Number(v));
const blank = (v) => (v === "" ? null : v);

/**
 * Accessories Booking Lookup → Booked Trims (AnnotechERP 06/08), as Brickland
 * lays it out: every booking on the left (searchable, with its trim count);
 * the trims of the one picked on the right — Add trim line keeps a line at
 * once, the rest are adjusted in place and kept with Save (a line removed
 * there goes on Save, Revert puts everything back). Export downloads the
 * lines shown. Switching booking never reloads the page.
 *
 * @param {{ bookings: object[], options: object, initial: ?object, canManage: boolean, actions: { load: Function, add: Function, save: Function } }} props
 */
function AccBookedTrims({ bookings: initialBookings, options, initial, canManage, actions }) {
  const t = useT("merchandise");
  const m = (k, p) => t(`master.acb.${k}`, p);
  const toastManager = useToastManager();
  const [bookings, setBookings] = useState(initialBookings);
  const [query, setQuery] = useState("");
  const [data, setData] = useState(initial);
  const [base, setBase] = useState(() => asRows(initial?.lines ?? []));
  const [rows, setRows] = useState(() => asRows(initial?.lines ?? []));
  const [adding, setAdding] = useState(false);
  const [line, setLine] = useState(blankLine);
  const [lineError, setLineError] = useState(null);
  const [errors, setErrors] = useState(null);
  const [pending, startTransition] = useTransition();
  const shown = useMemo(() => bookings.filter((b) => !query.trim() || `${b.booking_no} ${b.po_no ?? ""}`.toLowerCase().includes(query.trim().toLowerCase())), [bookings, query]);
  const editable = Boolean(canManage && data?.editable);
  const before = Object.fromEntries(base.map((r) => [r.id, r]));
  const edited = (r) => FIELDS.some((k) => before[r.id]?.[k] !== r[k]);
  const dirty = rows.length !== base.length || rows.some(edited);

  function take(next) {
    setData(next);
    setBase(asRows(next.lines));
    setRows(asRows(next.lines));
    setBookings((all) => all.map((b) => (b.booking_no === next.booking_no ? { ...b, items: next.lines.length } : b)));
    setErrors(null);
  }

  function open(no) {
    window.history.replaceState(null, "", `?tab=trims&booking=${encodeURIComponent(no)}`);
    startTransition(async () => {
      const result = await actions.load(no);
      if (result?.status === "success") take(result.data);
      else setErrors(messagesOf(result));
    });
  }

  function add() {
    startTransition(async () => {
      const result = await actions.add(data.booking_no, { item_id: blank(line.item_id), description: line.description, colour: blank(line.colour), size: blank(line.size), qty: num(line.qty), uom: blank(line.uom), rate: num(line.rate) });
      if (result?.status === "success") {
        take(result.data);
        setAdding(false);
        setLine(blankLine());
        setLineError(null);
        toastManager.add({ title: m("line_added", { no: data.booking_no }), type: "success" });
      } else setLineError(messagesOf(result).join(" "));
    });
  }

  function save() {
    startTransition(async () => {
      const result = await actions.save(data.booking_no, rows.map((r) => ({ id: r.id, description: r.description, colour: blank(r.colour), size: blank(r.size), qty: num(r.qty), uom: blank(r.uom), rate: num(r.rate) })));
      if (result?.status === "success") {
        take(result.data);
        toastManager.add({ title: m("lines_saved", { no: data.booking_no, n: result.data.lines.length }), type: "success" });
      } else setErrors(messagesOf(result));
    });
  }

  function exportCsv() {
    const head = ["item", "description", "colour", "size", "qty", "uom", "rate", "status"].map((k) => m(`col.${k}`));
    const cell = (v) => `"${str(v).replace(/"/g, '""')}"`;
    const body = rows.map((r) => [r.item, r.description, r.colour, r.size, r.qty, r.uom, r.rate, m(`status.${r.status}`)].map(cell).join(","));
    const url = URL.createObjectURL(new Blob([[head.map(cell).join(","), ...body].join("\n")], { type: "text/csv" }));
    const a = Object.assign(document.createElement("a"), { href: url, download: `${data.booking_no}-trims.csv` });
    a.click();
    URL.revokeObjectURL(url);
  }

  const set = (id, k, v) => setRows((all) => all.map((r) => (r.id === id ? { ...r, [k]: v } : r)));
  const th = "px-3 py-2.5 text-left text-[13px] font-semibold whitespace-nowrap";
  const field = "flex flex-col gap-1.5";
  const lab = "text-[13px] font-semibold text-foreground";
  return (
    <div className="grid gap-4 lg:grid-cols-[20rem_1fr]">
      <section className="self-start overflow-hidden rounded-xl border border-border bg-surface">
        <div className="flex items-center justify-between px-5 pt-4">
          <h2 className="text-base font-semibold text-foreground">{m("bookings")}</h2>
          <span className="rounded-full bg-surface-muted px-2 py-0.5 text-xs font-semibold text-foreground">{bookings.length}</span>
        </div>
        <div className="relative px-5 py-3">
          <Search className="pointer-events-none absolute top-1/2 left-7.5 size-4 -translate-y-1/2 text-foreground-muted" />
          <Input value={query} onChange={(e) => setQuery(e.target.value)} placeholder={m("search")} className="pl-8" aria-label={m("search_bookings")} />
        </div>
        <ul className="max-h-[36rem] overflow-y-auto border-t border-border">
          {shown.map((b) => (
            <li key={b.booking_no}>
              <button type="button" onClick={() => open(b.booking_no)} className={cn("flex w-full items-center justify-between gap-3 border-b border-border px-5 py-3 text-left hover:bg-surface-muted", data?.booking_no === b.booking_no && "bg-brand-subtle")}>
                <span>
                  <span className="block text-sm font-semibold text-foreground">{b.booking_no}</span>
                  <span className="block text-xs text-foreground-muted">{b.po_no ?? "—"}</span>
                </span>
                <span className="text-sm tabular-nums text-foreground">{b.items}</span>
              </button>
            </li>
          ))}
          {shown.length === 0 ? <li className="px-5 py-6 text-center text-sm text-foreground-muted">{m("no_match")}</li> : null}
        </ul>
      </section>

      <section className="flex flex-col self-start overflow-hidden rounded-xl border border-border bg-surface">
        <div className="flex items-start justify-between gap-3 px-5 py-4">
          <div>
            <h2 className="flex items-center gap-2 text-base font-semibold text-foreground">
              <LayoutList className="size-4" />
              {data ? m("trims_of", { no: data.booking_no }) : m("trims")}
            </h2>
            <p className="mt-0.5 text-[13px] text-foreground-muted">{m("trims_hint")}</p>
          </div>
          {data ? (
            <div className="flex gap-2">
              <Button variant="outline" size="sm" onClick={exportCsv} disabled={rows.length === 0}>
                <Download /> {m("export")}
              </Button>
              {editable ? (
                <Button variant="outline" size="sm" onClick={() => (setAdding(true), setLineError(null))}>
                  <Plus /> {m("add_line")}
                </Button>
              ) : null}
            </div>
          ) : null}
        </div>
        {!data ? (
          <Empty title={m("pick_booking")} hint={m("pick_hint")} />
        ) : rows.length === 0 ? (
          <Empty title={m("no_trims", { no: data.booking_no })} hint={editable ? m("no_trims_hint") : ""} />
        ) : (
          <div className="overflow-x-auto">
            <table className="w-full text-sm">
              <thead className="bg-brand text-brand-foreground">
                <tr>
                  {["item", "description", "colour", "size", "qty", "uom", "rate", "status", "action"].map((k) => (
                    <th key={k} className={cn(th, ["qty", "rate"].includes(k) && "text-right")}>
                      {m(`col.${k}`)}
                    </th>
                  ))}
                </tr>
              </thead>
              <tbody>
                {rows.map((r, i) => (
                  <tr key={r.id} className="border-t border-border">
                    <td className="px-3 py-2 whitespace-nowrap">{r.item ?? "—"}</td>
                    {[
                      ["description", "w-40", 200],
                      ["colour", "w-28", 80],
                      ["size", "w-24", 40],
                    ].map(([k, w, max]) => (
                      <td key={k} className="px-3 py-2">
                        <Input value={r[k]} onChange={(e) => set(r.id, k, e.target.value)} maxLength={max} className={cn("h-8", w)} disabled={!editable} aria-label={`${m(`col.${k}`)} ${i + 1}`} />
                      </td>
                    ))}
                    <td className="px-3 py-2">
                      <Input type="number" min="0" step="any" value={r.qty} onChange={(e) => set(r.id, "qty", e.target.value)} className="h-8 w-24 text-right" disabled={!editable} aria-label={`${m("col.qty")} ${i + 1}`} />
                    </td>
                    <td className="px-3 py-2">
                      <UomSelect value={r.uom} onChange={(v) => set(r.id, "uom", v)} size="sm" className="w-32" disabled={!editable} aria-label={`${m("col.uom")} ${i + 1}`} />
                    </td>
                    <td className="px-3 py-2">
                      <Input type="number" min="0" step="any" value={r.rate} onChange={(e) => set(r.id, "rate", e.target.value)} className="h-8 w-24 text-right" disabled={!editable} aria-label={`${m("col.rate")} ${i + 1}`} />
                    </td>
                    <td className="px-3 py-2">
                      <span className={cn("rounded-md px-1.5 py-0.5 text-xs font-medium", edited(r) ? "bg-warning-subtle text-warning" : "bg-surface-muted text-foreground-muted")}>{edited(r) ? m("edited") : m(`status.${r.status}`)}</span>
                    </td>
                    <td className="px-3 py-2">
                      {editable ? (
                        <Button variant="outline" size="sm" className="text-danger" onClick={() => setRows((all) => all.filter((x) => x.id !== r.id))}>
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
        {errors ? <Alert variant="danger" className="mx-5 mt-3">{errors.join(" · ")}</Alert> : null}
        {data ? (
          <div className="flex flex-wrap items-center justify-between gap-3 border-t border-border px-5 py-3">
            <span className="text-[13px] text-foreground-muted">{m("trim_count", { n: rows.length })}</span>
            {editable ? (
              <div className="flex gap-2">
                <Button variant="outline" onClick={() => (setRows(base), setErrors(null))} disabled={!dirty || pending}>
                  <RotateCcw /> {m("revert")}
                </Button>
                <Button onClick={save} loading={pending} disabled={!dirty}>
                  <Save /> {m("save")}
                </Button>
              </div>
            ) : null}
          </div>
        ) : null}
      </section>

      <Modal open={adding} onOpenChange={setAdding} title={m("add_line_of", { no: data?.booking_no ?? "" })} className="max-w-2xl">
        <div className="rounded-lg border border-border p-4">
          <h3 className="text-sm font-semibold text-foreground">{m("new_line")}</h3>
          <div className="mt-3 grid gap-3 sm:grid-cols-2">
            <div className={field}>
              <span className={lab}>{m("f.item_id")}</span>
              <Select
                value={line.item_id}
                onValueChange={(v) => {
                  const item = options.items.find((x) => x.value === v);
                  setLine((l) => ({ ...l, item_id: v ?? "", description: item && !l.description ? item.name : l.description, uom: item?.uom || l.uom }));
                }}
                options={[{ value: "", label: m("select") }, ...options.items.map(({ value, label }) => ({ value, label }))]}
                placeholder={m("select")}
                aria-label={m("f.item_id")}
              />
            </div>
            {[
              ["description", true, 200],
              ["colour", false, 80],
              ["size", false, 40],
            ].map(([k, required, max]) => (
              <label key={k} className={field}>
                <span className={lab}>
                  {m(`f.${k}`)}
                  {required ? <span className="text-danger">*</span> : null}
                </span>
                <Input value={line[k]} onChange={(e) => setLine((l) => ({ ...l, [k]: e.target.value }))} maxLength={max} aria-label={m(`f.${k}`)} />
              </label>
            ))}
            <label className={field}>
              <span className={lab}>
                {m("f.qty")}
                <span className="text-danger">*</span>
              </span>
              <Input type="number" min="0" step="any" value={line.qty} onChange={(e) => setLine((l) => ({ ...l, qty: e.target.value }))} aria-label={m("f.qty")} />
            </label>
            <label className={field}>
              <span className={lab}>{m("f.uom")}</span>
              <UomSelect value={line.uom} onChange={(v) => setLine((l) => ({ ...l, uom: v }))} aria-label={m("f.uom")} />
            </label>
            <label className={field}>
              <span className={lab}>{m("f.rate")}</span>
              <Input type="number" min="0" step="any" value={line.rate} onChange={(e) => setLine((l) => ({ ...l, rate: e.target.value }))} aria-label={m("f.rate")} />
            </label>
          </div>
        </div>
        {lineError ? <p className="mt-3 text-sm text-danger">{lineError}</p> : null}
        <div className="mt-4 flex flex-wrap items-center justify-between gap-2">
          <span className="text-xs text-foreground-muted">{m("footer")}</span>
          <div className="flex gap-2">
            <Button variant="outline" onClick={() => (setLine(blankLine()), setLineError(null))} disabled={pending}>
              <RotateCcw /> {m("reset")}
            </Button>
            <Button onClick={add} loading={pending}>
              <Save /> {m("add_line")}
            </Button>
          </div>
        </div>
      </Modal>
    </div>
  );
}

function Empty({ title, hint }) {
  return (
    <div className="flex flex-col items-center gap-2 border-t border-border px-5 py-14 text-center">
      <span className="flex size-12 items-center justify-center rounded-lg bg-surface-muted text-foreground-muted">
        <LayoutList className="size-5" />
      </span>
      <p className="text-sm font-semibold text-foreground">{title}</p>
      {hint ? <p className="text-xs text-foreground-muted">{hint}</p> : null}
    </div>
  );
}

export { AccBookedTrims };
