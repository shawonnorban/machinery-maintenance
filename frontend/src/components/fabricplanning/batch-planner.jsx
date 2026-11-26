"use client";

import { useState, useTransition } from "react";
import { Layers, Plus, RotateCcw, Save, Trash2 } from "lucide-react";
import { cn } from "@/lib/utils";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { useToastManager } from "@/components/ui/toast";
import { useT } from "@/lib/i18n";

/*
 * Fabric Requirement → Fabric Batch Plan Entry → Batch Planning (AnnotechERP
 * 13/02), as Brickland lays it out: an existing plan to load back, or a new
 * one; the week, buyer, order, colour, colour group, dye method, machine and
 * dates; and the fabric lines — grey required against what is planned, the
 * balance worked out. The planned quantity is the sum of the lines; the BP
 * number is given on save. The plan stays in the address by its number.
 */

const card = "rounded-lg border border-border bg-surface";
const firstError = (res) =>
  Object.values(res?.errors ?? {})[0]?.[0] ?? res?.message;
const n = (v) => (v === "" || v === null || v === undefined ? 0 : Number(v));
const qty = (v) =>
  Number(v || 0).toLocaleString("en-US", { maximumFractionDigits: 2 });
const COLOUR_TYPES = ["SOLID", "MELANGE", "YARN_DYED", "AOP"];
const METHODS = ["EXHAUST", "COLD_PAD_BATCH", "CONTINUOUS", "PIGMENT"];
const STATUSES = ["DRAFT", "PLANNED", "SCHEDULED", "RELEASED", "CANCELLED"];
const HEADER = {
  week_start: "",
  buyer_id: "",
  style_job: "",
  order_no: "",
  order_cat: "",
  colour_type: "",
  colour_id: "",
  colour_group_id: "",
  dyeing_method: "",
  dye_machine_id: "",
  shipment_date: "",
  booking_confirm_date: "",
  production_hours: "",
  pending_qty: "",
  batches: "1",
  status: "DRAFT",
};
let seq = 0;
const line = (l = {}) => ({
  key: `l${++seq}`,
  fabric: l.fabric ?? "",
  grey_req: l.grey_req ?? "",
  bp_plus: l.bp_plus ?? "",
  bp_minus: l.bp_minus ?? "",
  bp_qty: l.bp_qty ?? "",
});
const fromPlan = (p) => ({
  header: Object.fromEntries(
    Object.keys(HEADER).map((k) => [
      k,
      p[k] === null || p[k] === undefined ? "" : String(p[k]),
    ]),
  ),
  lines: p.lines.map(line),
});

/** @param {{ initial: object|null, plans: object[], options: object, act: Function, canManage: boolean }} props */
function BatchPlanner({
  initial,
  plans: initialPlans,
  options,
  act,
  canManage,
}) {
  const t = useT("fabricplanning");
  const o = (k, x) => t(`ops.${k}`, x);
  const toastManager = useToastManager();
  const [plans, setPlans] = useState(initialPlans);
  const [bpNo, setBpNo] = useState(initial?.bp_no ?? "");
  const [form, setForm] = useState(() =>
    initial ? fromPlan(initial) : { header: HEADER, lines: [] },
  );
  const [errors, setErrors] = useState({});
  const [pending, startTransition] = useTransition();
  const h = form.header;
  const set = (patch) =>
    setForm((f) => ({ ...f, header: { ...f.header, ...patch } }));
  const setLine = (i, patch) =>
    setForm((f) => ({
      ...f,
      lines: f.lines.map((l, j) => (j === i ? { ...l, ...patch } : l)),
    }));
  const keep = (no) =>
    window.history.replaceState(
      null,
      "",
      `${window.location.pathname}?${new URLSearchParams(no ? { tab: "planning", bp: no } : { tab: "planning" })}`,
    );
  const load = (no) =>
    startTransition(async () => {
      setErrors({});
      setBpNo(no);
      keep(no);
      if (!no) return setForm({ header: HEADER, lines: [] });
      const res = await act("batchPlan", no);
      if (res?.status === "success") setForm(fromPlan(res.data));
    });
  const save = () =>
    startTransition(async () => {
      const payload = {
        ...Object.fromEntries(
          Object.entries(h).map(([k, v]) => [k, v === "" ? null : v]),
        ),
        lines: form.lines.map(
          ({ fabric, grey_req, bp_plus, bp_minus, bp_qty }) => ({
            fabric,
            grey_req: grey_req === "" ? null : grey_req,
            bp_plus: bp_plus === "" ? null : bp_plus,
            bp_minus: bp_minus === "" ? null : bp_minus,
            bp_qty: bp_qty === "" ? null : bp_qty,
          }),
        ),
      };
      const res = await act(
        bpNo ? "updateBatchPlan" : "storeBatchPlan",
        ...(bpNo ? [bpNo, payload] : [payload]),
      );
      if (res?.status !== "success") {
        setErrors(res?.errors ?? {});
        return toastManager.add({ title: firstError(res), type: "danger" });
      }
      setErrors({});
      setBpNo(res.data.bp_no);
      keep(res.data.bp_no);
      setForm(fromPlan(res.data));
      const list = await act("batchPlans");
      if (list?.status === "success") setPlans(list.data);
      toastManager.add({
        title: o("bp.saved", {
          no: res.data.bp_no,
          lines: res.data.lines.length,
          qty: qty(res.data.bp_qty),
        }),
        type: "success",
      });
    });

  const err = (k) => errors[k]?.[0];
  const sel =
    "h-10 w-full rounded-lg border border-border-strong bg-surface px-3 text-sm disabled:opacity-60";
  const field = (k, label, control, { required, help } = {}) => (
    <label className="flex flex-col gap-1.5 text-[13px] font-semibold text-foreground">
      <span>
        {label}
        {required ? <span className="text-danger"> *</span> : null}
      </span>
      {control}
      {err(k) ? (
        <span className="text-xs font-normal text-danger">{err(k)}</span>
      ) : help ? (
        <span className="text-xs font-normal text-foreground-muted">
          {help}
        </span>
      ) : null}
    </label>
  );
  const select = (k, label, choices, extra = {}) =>
    field(
      k,
      label,
      <select
        value={h[k]}
        disabled={!canManage}
        onChange={(ev) => set({ [k]: ev.target.value })}
        aria-label={label}
        className={sel}
      >
        <option value="">{o("select")}</option>
        {choices.map((c) => (
          <option key={c.value} value={c.value}>
            {c.label}
          </option>
        ))}
      </select>,
      {
        ...extra,
        help:
          extra.help ?? (choices.length === 0 ? o("bp.none_yet") : undefined),
      },
    );
  const text = (k, label, type = "text", extra = {}) =>
    field(
      k,
      label,
      <Input
        type={type}
        value={h[k]}
        disabled={!canManage}
        onChange={(ev) => set({ [k]: ev.target.value })}
        aria-label={label}
        {...(type === "number" ? { min: "0", step: "any" } : {})}
      />,
      extra,
    );
  const opts = (group, values) =>
    values.map((v) => ({ value: v, label: o(`opt.${group}.${v}`) }));
  const totals = form.lines.reduce(
    (s, l) => ({
      grey: s.grey + n(l.grey_req),
      plus: s.plus + n(l.bp_plus),
      minus: s.minus + n(l.bp_minus),
      qty: s.qty + n(l.bp_qty),
    }),
    { grey: 0, plus: 0, minus: 0, qty: 0 },
  );

  return (
    <div className={cn("flex flex-col gap-4", pending && "opacity-80")}>
      <section className={cn(card, "p-5")}>
        <div className="flex items-start justify-between gap-3">
          <div className="flex gap-3">
            <Layers
              className="mt-0.5 size-4 text-foreground-muted"
              aria-hidden
            />
            <div>
              <h2 className="text-base font-semibold text-foreground">
                {o("bp.title")}
              </h2>
              <p className="text-[13px] text-foreground-muted">
                {o("bp.hint")}
              </p>
            </div>
          </div>
          <span className="rounded-full border border-border px-2 py-0.5 text-xs font-semibold text-foreground">
            {bpNo || o("bp.new_badge")}
          </span>
        </div>
        <div className="mt-4">
          {field(
            "existing",
            o("f.existing"),
            <select
              value={bpNo}
              onChange={(ev) => load(ev.target.value)}
              disabled={plans.length === 0}
              aria-label={o("f.existing")}
              className={sel}
            >
              <option value="">{o("f.existing_ph")}</option>
              {plans.map((p) => (
                <option key={p.bp_no} value={p.bp_no}>
                  {[
                    p.bp_no,
                    p.week,
                    p.buyer,
                    p.style_job,
                    `${qty(p.bp_qty)} kg`,
                  ]
                    .filter(Boolean)
                    .join(" · ")}
                </option>
              ))}
            </select>,
            { help: o("f.existing_help") },
          )}
        </div>
        <div className="mt-4 grid gap-4 md:grid-cols-3">
          {select("week_start", o("f.week"), options.weeks, { required: true })}
          {select("buyer_id", o("f.buyer"), options.buyers)}
          {text("style_job", o("f.style_job"))}
          {text("order_no", o("f.order_no"))}
          {text("order_cat", o("f.order_cat"))}
          {select(
            "colour_type",
            o("f.colour_type"),
            opts("colour_type", COLOUR_TYPES),
          )}
          {select("colour_id", o("f.colour"), options.colours)}
          {select(
            "colour_group_id",
            o("f.colour_group"),
            options.colour_groups,
            { required: true },
          )}
          {select("dyeing_method", o("f.method"), opts("method", METHODS), {
            required: true,
          })}
          {select("dye_machine_id", o("f.machine"), options.machines)}
          {text("shipment_date", o("f.shipment"), "date")}
          {text("booking_confirm_date", o("f.b_confirm"), "date")}
          {text("production_hours", o("f.prod_hr"), "number")}
          {text("pending_qty", o("f.pending"), "number")}
          {text("batches", o("f.batches"), "number", { required: true })}
          {select("status", o("f.plan_status"), opts("status", STATUSES), {
            required: true,
          })}
        </div>
      </section>

      <section className={cn(card, "min-w-0 overflow-hidden")}>
        <div className="flex flex-wrap items-start justify-between gap-3 p-5">
          <div>
            <h2 className="text-base font-semibold text-foreground">
              {o("bp.lines_title")}
            </h2>
            <p className="text-[13px] text-foreground-muted">
              {o("bp.lines_hint")}
            </p>
          </div>
          {canManage ? (
            <Button
              size="sm"
              variant="outline"
              onClick={() =>
                setForm((f) => ({ ...f, lines: [...f.lines, line()] }))
              }
            >
              <Plus className="size-4" aria-hidden /> {o("bp.add_line")}
            </Button>
          ) : null}
        </div>
        {form.lines.length === 0 ? (
          <div className="border-t border-border px-5 py-10 text-center">
            <p className="text-sm font-semibold text-foreground">
              {o("bp.no_lines")}
            </p>
            <p
              className={cn(
                "text-[13px]",
                err("lines") ? "text-danger" : "text-foreground-muted",
              )}
            >
              {o("bp.no_lines_hint")}
            </p>
          </div>
        ) : (
          <div className="overflow-x-auto">
            <table className="w-full min-w-[900px] text-sm">
              <thead className="bg-brand text-brand-foreground">
                <tr>
                  {[
                    ["fabric"],
                    ["grey", true],
                    ["plus", true],
                    ["minus", true],
                    ["bp_qty", true],
                    ["balance", true],
                  ].map(([k, right]) => (
                    <th
                      key={k}
                      className={cn(
                        "px-3 py-2.5 text-left text-xs font-semibold",
                        right && "text-right",
                      )}
                    >
                      {o(`f.${k}`)}
                    </th>
                  ))}
                  <th className="px-3 py-2.5" aria-hidden />
                </tr>
              </thead>
              <tbody>
                {form.lines.map((l, i) => (
                  <tr key={l.key} className="border-t border-border">
                    <td className="px-3 py-2">
                      <Input
                        value={l.fabric}
                        disabled={!canManage}
                        onChange={(ev) =>
                          setLine(i, { fabric: ev.target.value })
                        }
                        aria-label={o("f.fabric")}
                        className={cn(
                          "h-8 min-w-60 px-2 text-xs",
                          err(`lines.${i}.fabric`) && "border-danger",
                        )}
                      />
                    </td>
                    {["grey_req", "bp_plus", "bp_minus", "bp_qty"].map((k) => (
                      <td key={k} className="px-3 py-2 text-right">
                        <Input
                          type="number"
                          min="0"
                          step="0.0001"
                          value={l[k]}
                          disabled={!canManage}
                          onChange={(ev) =>
                            setLine(i, { [k]: ev.target.value })
                          }
                          aria-label={o(
                            `f.${{ grey_req: "grey", bp_plus: "plus", bp_minus: "minus", bp_qty: "bp_qty" }[k]}`,
                          )}
                          className={cn(
                            "h-8 w-28 px-2 text-right text-xs",
                            err(`lines.${i}.${k}`) && "border-danger",
                          )}
                        />
                      </td>
                    ))}
                    <td className="px-3 py-2 text-right tabular-nums">
                      {qty(n(l.grey_req) - n(l.bp_qty))}
                    </td>
                    <td className="px-3 py-2 text-right">
                      {canManage ? (
                        <Button
                          size="icon"
                          variant="ghost"
                          className="size-8"
                          onClick={() =>
                            setForm((f) => ({
                              ...f,
                              lines: f.lines.filter((_, j) => j !== i),
                            }))
                          }
                          aria-label={o("bp.remove")}
                        >
                          <Trash2 className="size-4" aria-hidden />
                        </Button>
                      ) : null}
                    </td>
                  </tr>
                ))}
                <tr className="border-t border-border bg-surface-muted font-semibold">
                  <td className="px-3 py-2.5">{o("bp.total")}</td>
                  {[
                    totals.grey,
                    totals.plus,
                    totals.minus,
                    totals.qty,
                    totals.grey - totals.qty,
                  ].map((v, i) => (
                    <td key={i} className="px-3 py-2.5 text-right tabular-nums">
                      {qty(v)}
                    </td>
                  ))}
                  <td />
                </tr>
              </tbody>
            </table>
          </div>
        )}
        <div className="flex flex-wrap items-center justify-between gap-3 border-t border-border px-5 py-4">
          <p className="max-w-2xl text-[13px] text-foreground-muted">
            {o("bp.foot")}
          </p>
          {canManage ? (
            <div className="flex gap-2">
              <Button variant="outline" onClick={() => load("")}>
                <RotateCcw className="size-4" aria-hidden /> {o("bp.clear")}
              </Button>
              <Button loading={pending} onClick={save}>
                <Save className="size-4" aria-hidden /> {o("bp.save")}
              </Button>
            </div>
          ) : null}
        </div>
      </section>
    </div>
  );
}

export { BatchPlanner };
