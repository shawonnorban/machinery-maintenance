"use client";

import { useMemo, useState, useTransition } from "react";
import { LayoutList, Plus, RotateCcw, Save, Search, Trash2 } from "lucide-react";
import { cn } from "@/lib/utils";
import { Input } from "@/components/ui/input";
import { Select } from "@/components/ui/select";
import { Button } from "@/components/ui/button";
import { Modal } from "@/components/ui/modal";
import { Alert } from "@/components/ui/alert";
import { useToastManager } from "@/components/ui/toast";
import { useT } from "@/lib/i18n";
import { UomSelect } from "@/components/erp/uom-select";

// An error's field messages, or its message when it names no field (an empty `errors` is no message at all).
const messagesOf = (result) => {
  const fields = [...new Set(Object.values(result?.errors ?? {}).flat())];
  return fields.length ? fields : [result?.message ?? ""];
};

const str = (v) => (v === null || v === undefined ? "" : String(v));
const asRows = (lines) => lines.map((l) => ({ ...l, colour_name: str(l.colour_name), gsm: str(l.gsm), dia: str(l.dia), qty: str(l.qty), uom: str(l.uom), rate: str(l.rate), process_loss: str(l.process_loss) }));
const FIELDS = ["colour_name", "gsm", "dia", "qty", "uom", "rate", "process_loss"];
const blankLine = () => ({ fabric_type_id: "", colour_id: "", colour_name: "", gsm: "", dia: "", qty: "", uom: "KG", rate: "", process_loss: "" });
const num = (v) => (v === "" ? null : Number(v));

/**
 * Booking Items (AnnotechERP 06/11), as Brickland lays it out on the
 * Additional and Buffer booking screens: every booking on the left, and the
 * fabric lines of the one picked on the right — Add fabric line keeps a line
 * at once, the rest are adjusted in place and kept with Save (a line removed
 * there goes on Save). A bulk booking's lines are derived on its own screen,
 * so they are only shown. Switching booking never reloads the page.
 *
 * @param {{ bookings: object[], initial: ?object, canManage: boolean, actions: { load: Function, add: Function, save: Function } }} props
 */
function BookingItemsPanel({ bookings: initialBookings, initial, canManage, actions }) {
  const t = useT("merchandise");
  const m = (k, p) => t(`master.bki.${k}`, p);
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
  const shown = useMemo(() => bookings.filter((b) => !query.trim() || b.booking_no.toLowerCase().includes(query.trim().toLowerCase())), [bookings, query]);
  const editable = Boolean(canManage && data?.editable);
  const before = Object.fromEntries(base.map((r) => [r.id, r]));
  const edited = (r) => FIELDS.some((k) => before[r.id]?.[k] !== r[k]);
  const dirty = rows.length !== base.length || rows.some(edited);

  function take(next) {
    setData(next);
    setBase(asRows(next.lines));
    setRows(asRows(next.lines));
    setBookings((all) => all.map((b) => (b.booking_no === next.booking_no ? { ...b, lines: next.lines.length } : b)));
    setErrors(null);
  }

  function open(no) {
    window.history.replaceState(null, "", `?tab=items&booking=${encodeURIComponent(no)}`);
    startTransition(async () => {
      const result = await actions.load(no);
      if (result?.status === "success") take(result.data);
      else setErrors([result?.message]);
    });
  }

  function add() {
    if (!line.fabric_type_id) return setLineError(m("need_fabric"));
    if (!(Number(line.qty) > 0)) return setLineError(m("need_qty"));
    startTransition(async () => {
      const result = await actions.add(data.booking_no, { ...line, colour_id: line.colour_id || null, gsm: num(line.gsm), dia: num(line.dia), qty: num(line.qty), rate: num(line.rate), process_loss: num(line.process_loss) });
      if (result?.status === "success") {
        take({ ...data, lines: result.data });
        setAdding(false);
        setLine(blankLine());
        setLineError(null);
        toastManager.add({ title: m("added", { no: data.booking_no }), type: "success" });
      } else setLineError(messagesOf(result).join(" "));
    });
  }

  function save() {
    startTransition(async () => {
      const result = await actions.save(data.booking_no, rows.map((r) => ({ id: r.id, colour_name: r.colour_name || null, gsm: num(r.gsm), dia: num(r.dia), qty: num(r.qty), uom: r.uom || null, rate: num(r.rate), process_loss: num(r.process_loss) })));
      if (result?.status === "success") {
        take({ ...data, lines: result.data });
        toastManager.add({ title: m("saved", { no: data.booking_no, n: result.data.length }), type: "success" });
      } else setErrors(messagesOf(result));
    });
  }

  const set = (id, k, v) => setRows((all) => all.map((r) => (r.id === id ? { ...r, [k]: v } : r)));
  const th = "px-3 py-2.5 text-left text-[13px] font-semibold whitespace-nowrap";
  const cell = "h-8 w-24";
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
        <ul className="border-t border-border">
          {shown.map((b) => (
            <li key={b.booking_no}>
              <button
                type="button"
                onClick={() => open(b.booking_no)}
                className={cn("flex w-full items-center justify-between gap-3 border-b border-border px-5 py-3 text-left hover:bg-surface-muted", data?.booking_no === b.booking_no && "bg-brand-subtle")}
              >
                <span>
                  <span className="block text-sm font-semibold text-foreground">{b.booking_no}</span>
                  <span className="block text-xs text-foreground-muted">{m(`type.${b.booking_type}`)}</span>
                </span>
                <span className="text-sm tabular-nums text-foreground">{b.lines}</span>
              </button>
            </li>
          ))}
          {shown.length === 0 ? <li className="px-5 py-6 text-center text-sm text-foreground-muted">{m("none")}</li> : null}
        </ul>
      </section>

      <section className="flex flex-col rounded-xl border border-border bg-surface">
        <div className="flex items-start justify-between gap-3 px-5 py-4">
          <div>
            <h2 className="flex items-center gap-2 text-base font-semibold text-foreground">
              <LayoutList className="size-4" />
              {data ? m("fabric_of", { no: data.booking_no }) : m("fabric")}
            </h2>
            <p className="mt-0.5 text-[13px] text-foreground-muted">{m("hint")}</p>
          </div>
          {editable ? (
            <Button variant="outline" size="sm" onClick={() => setAdding(true)}>
              <Plus /> {m("add_line")}
            </Button>
          ) : null}
        </div>
        {data && !data.editable ? (
          <Alert className="mx-5 mb-3">{data.booking_type === "BULK" ? m("bulk_read_only") : m("locked")}</Alert>
        ) : null}
        {!data ? (
          <Empty title={m("pick")} hint={m("pick_hint")} />
        ) : rows.length === 0 ? (
          <Empty title={m("empty", { no: data.booking_no })} hint={editable ? m("empty_hint") : ""} />
        ) : (
          <div className="overflow-x-auto">
            <table className="w-full text-sm">
              <thead className="bg-brand text-brand-foreground">
                <tr>
                  {["fabric", "colour", "gsm", "dia", "qty", "uom", "rate", "loss", "status", "action"].map((k) => (
                    <th key={k} className={th}>
                      {m(`col.${k}`)}
                    </th>
                  ))}
                </tr>
              </thead>
              <tbody>
                {rows.map((r) => (
                  <tr key={r.id} className="border-t border-border">
                    <td className="px-3 py-2 whitespace-nowrap">{r.fabric ?? "—"}</td>
                    <td className="px-3 py-2">
                      <Input value={r.colour_name} onChange={(e) => set(r.id, "colour_name", e.target.value)} maxLength={120} className="h-8 w-32" disabled={!editable} aria-label={m("col.colour")} />
                    </td>
                    {["gsm", "dia", "qty"].map((k) => (
                      <td key={k} className="px-3 py-2">
                        <Input type="number" min="0" step="any" value={r[k]} onChange={(e) => set(r.id, k, e.target.value)} className={cell} disabled={!editable} aria-label={m(`col.${k}`)} />
                      </td>
                    ))}
                    <td className="px-3 py-2">
                      <UomSelect value={r.uom} onChange={(v) => set(r.id, "uom", v)} size="sm" className="w-32" disabled={!editable} aria-label={m("col.uom")} />
                    </td>
                    {["rate", "process_loss"].map((k) => (
                      <td key={k} className="px-3 py-2">
                        <Input type="number" min="0" step="any" max={k === "process_loss" ? 100 : undefined} value={r[k]} onChange={(e) => set(r.id, k, e.target.value)} className={cell} disabled={!editable} aria-label={m(`col.${k === "process_loss" ? "loss" : k}`)} />
                      </td>
                    ))}
                    <td className="px-3 py-2">
                      <span className={cn("rounded-md px-1.5 py-0.5 text-xs font-medium", edited(r) ? "bg-warning-subtle text-warning" : "bg-surface-muted text-foreground-muted")}>{edited(r) ? m("edited") : m("kept")}</span>
                    </td>
                    <td className="px-3 py-2">
                      {editable ? (
                        <Button variant="outline" size="icon" className="size-8 text-danger" aria-label={m("remove")} onClick={() => setRows((all) => all.filter((x) => x.id !== r.id))}>
                          <Trash2 />
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
        <div className="mt-auto flex flex-wrap items-center justify-between gap-3 border-t border-border px-5 py-3">
          <span className="text-[13px] text-foreground-muted">{m("footer", { n: rows.length })}</span>
          {editable ? (
            <div className="flex gap-2">
              <Button
                variant="outline"
                onClick={() => {
                  setRows(base);
                  setErrors(null);
                }}
                disabled={!dirty || pending}
              >
                <RotateCcw /> {m("revert")}
              </Button>
              <Button onClick={save} loading={pending} disabled={!dirty}>
                <Save /> {m("save")}
              </Button>
            </div>
          ) : null}
        </div>
      </section>

      <Modal open={adding} onOpenChange={setAdding} title={m("add_title", { no: data?.booking_no ?? "" })} className="max-w-2xl">
        <div className="rounded-lg border border-border p-4">
          <h3 className="text-sm font-semibold text-foreground">{m("new_line")}</h3>
          <p className="mt-0.5 text-xs text-foreground-muted">{m("loss_hint")}</p>
          <div className="mt-3 grid gap-3 sm:grid-cols-3">
            <Field label={m("f.fabric_type")} required>
              <Select value={line.fabric_type_id} onValueChange={(v) => setLine((l) => ({ ...l, fabric_type_id: v ?? "" }))} options={[{ value: "", label: m("select") }, ...(data?.fabrics ?? [])]} placeholder={m("select")} aria-label={m("f.fabric_type")} />
            </Field>
            <Field label={m("f.colour")}>
              <Select
                value={line.colour_id}
                onValueChange={(v) => setLine((l) => ({ ...l, colour_id: v ?? "", colour_name: data?.colours.find((c) => c.value === v)?.name ?? l.colour_name }))}
                options={[{ value: "", label: m("select") }, ...(data?.colours ?? []).map(({ value, label }) => ({ value, label }))]}
                placeholder={m("select")}
                aria-label={m("f.colour")}
              />
            </Field>
            <Field label={m("f.colour_name")}>
              <Input value={line.colour_name} onChange={(e) => setLine((l) => ({ ...l, colour_name: e.target.value }))} maxLength={120} aria-label={m("f.colour_name")} />
            </Field>
            {[
              ["gsm", "f.gsm"],
              ["dia", "f.dia"],
              ["qty", "f.qty", true],
            ].map(([k, label, required]) => (
              <Field key={k} label={m(label)} required={required}>
                <Input type="number" min="0" step="any" value={line[k]} onChange={(e) => setLine((l) => ({ ...l, [k]: e.target.value }))} aria-label={m(label)} />
              </Field>
            ))}
            <Field label={m("f.uom")}>
              <UomSelect value={line.uom} onChange={(v) => setLine((l) => ({ ...l, uom: v }))} aria-label={m("f.uom")} />
            </Field>
            <Field label={m("f.rate")}>
              <Input type="number" min="0" step="any" value={line.rate} onChange={(e) => setLine((l) => ({ ...l, rate: e.target.value }))} aria-label={m("f.rate")} />
            </Field>
            <Field label={m("f.loss")}>
              <Input type="number" min="0" max="100" step="any" value={line.process_loss} onChange={(e) => setLine((l) => ({ ...l, process_loss: e.target.value }))} aria-label={m("f.loss")} />
            </Field>
          </div>
        </div>
        {lineError ? <p className="mt-3 text-sm text-danger">{lineError}</p> : null}
        <div className="mt-4 flex gap-2">
          <Button
            variant="outline"
            onClick={() => {
              setLine(blankLine());
              setLineError(null);
            }}
            disabled={pending}
          >
            <RotateCcw /> {m("reset")}
          </Button>
          <Button onClick={add} loading={pending}>
            <Save /> {m("add_line")}
          </Button>
        </div>
      </Modal>
    </div>
  );
}

function Field({ label, required = false, children }) {
  return (
    <div className="flex flex-col gap-1.5">
      <span className="text-[13px] font-semibold text-foreground">
        {label}
        {required ? <span className="text-danger">*</span> : null}
      </span>
      {children}
    </div>
  );
}

function Empty({ title, hint }) {
  return (
    <div className="flex flex-col items-center gap-2 px-5 py-14 text-center">
      <span className="flex size-12 items-center justify-center rounded-lg bg-surface-muted text-foreground-muted">
        <LayoutList className="size-5" />
      </span>
      <p className="text-sm font-semibold text-foreground">{title}</p>
      {hint ? <p className="text-xs text-foreground-muted">{hint}</p> : null}
    </div>
  );
}

export { BookingItemsPanel };
