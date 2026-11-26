"use client";

import { useState, useTransition } from "react";
import { Check, RotateCcw, Save, X } from "lucide-react";
import { cn } from "@/lib/utils";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Select } from "@/components/ui/select";
import { Textarea } from "@/components/ui/textarea";
import { Alert } from "@/components/ui/alert";
import { useToastManager } from "@/components/ui/toast";
import { useT } from "@/lib/i18n";
import { card, dash, day, messagesOf, qty, EmployeeField } from "./common";

/*
 * Store & Material's movements off the shelf (AnnotechERP 09/01), as Brickland
 * lays them out. Material Out: the material (by type), its available stock,
 * the purpose, quantity, floor → section → line and the receiver found by ID;
 * fabric held in rolls is taken roll by roll. Material Return: the Material
 * Out number brings what went out, what came back and what may still; the
 * roll it comes back on, the quantity, reason, line and the people.
 * Stock Adjustment: the system's quantity against the count, sent to the
 * Store Manager, who approves or rejects it in the list below; and the
 * minimum stock a material is kept at. Everything stays on the page.
 */

function Field({ label, required, error, hint, children, className }) {
  return (
    <label className={cn("flex flex-col gap-1.5", className)}>
      <span className="text-[13px] font-medium text-foreground">
        {label}
        {required ? <span className="text-danger"> *</span> : null}
      </span>
      {children}
      {error ? (
        <span className="text-xs text-danger">{error}</span>
      ) : hint ? (
        <span className="text-xs text-foreground-muted">{hint}</span>
      ) : null}
    </label>
  );
}

function Heading({ title, date }) {
  const t = useT("storematerial");
  return (
    <div className="flex flex-wrap items-baseline justify-between gap-2">
      <h2 className="text-base font-semibold text-foreground">{title}</h2>
      <p className="text-[13px] text-foreground-muted">
        {t("mo.date")}{" "}
        <span className="font-medium text-foreground">{day(date)}</span>
      </p>
    </div>
  );
}

/** A corrected field drops its message. */
const dropError = (all, k) =>
  all[k]
    ? Object.fromEntries(Object.entries(all).filter(([key]) => key !== k))
    : all;

const today = () =>
  new Intl.DateTimeFormat("en-CA", { timeZone: "Asia/Dhaka" }).format(
    new Date(),
  );

/** Material Out. */
function MaterialOut({ data, canIssue, act }) {
  const t = useT("storematerial");
  const o = (k, p) => t(`mo.${k}`, p);
  const toastManager = useToastManager();
  const blank = {
    kind: "",
    stock_id: "",
    purpose: "",
    qty: "",
    floor_id: "",
    section_id: "",
    line_id: "",
    receiver: "",
    remarks: "",
  };
  const [form, setForm] = useState(blank);
  const [rollQty, setRollQty] = useState({});
  const [stocks, setStocks] = useState(data.stocks);
  const [next, setNext] = useState(data.next.mo);
  const [errors, setErrors] = useState({});
  const [pending, startTransition] = useTransition();
  const set = (k) => (v) => {
    setForm((all) => {
      const nf = { ...all, [k]: v ?? "" };
      if (k === "kind") nf.stock_id = "";
      if (k === "kind" || k === "stock_id") setRollQty({});
      if (k === "floor_id") Object.assign(nf, { section_id: "", line_id: "" });
      if (k === "section_id") nf.line_id = "";
      return nf;
    });
    setErrors((all) => dropError(all, k));
  };
  const stock = stocks.find((s) => s.value === form.stock_id);
  // Fabric held in rolls goes out roll by roll; the quantity is then what the rolls add up to.
  const takenRolls = (stock?.rolls ?? []).filter(
    (r) => Number(rollQty[r.id]) > 0,
  );
  const byRoll = takenRolls.length > 0;
  const rollTotal = takenRolls.reduce(
    (sum, r) => sum + Number(rollQty[r.id]),
    0,
  );
  const sections = data.sections.filter(
    (s) => form.floor_id && (s.floor_id === form.floor_id || !s.floor_id),
  );
  // The section's lines; a line not yet set under a section shows when its floor is the chosen one, or it has none.
  const lines = data.lines.filter(
    (l) =>
      form.section_id &&
      (l.section_id === form.section_id ||
        (!l.section_id && (!l.floor_id || l.floor_id === form.floor_id))),
  );
  const err = (k) => errors[k]?.[0];

  function confirm() {
    setErrors({});
    const payload = {
      ...Object.fromEntries(
        Object.entries(form)
          .filter(([k]) => k !== "kind")
          .map(([k, v]) => [k, v === "" ? null : v]),
      ),
      ...(byRoll
        ? {
            qty: Math.round(rollTotal * 1000) / 1000,
            rolls: takenRolls.map((r) => ({
              roll_id: r.id,
              qty: rollQty[r.id],
            })),
          }
        : {}),
    };
    startTransition(async () => {
      const result = await act("materialOut", payload);
      if (result?.status !== "success") return setErrors(messagesOf(result));
      toastManager.add({
        title: o("done", { no: result.data.mo_no }),
        type: "success",
      });
      setStocks(result.data.stocks);
      setRollQty({});
      setNext(
        `MO-${result.data.mo_no.slice(3, 7)}-${String(Number(result.data.mo_no.slice(8)) + 1).padStart(5, "0")}`,
      );
      setForm(blank);
    });
  }

  return (
    <section className={cn(card, "flex flex-col gap-4 p-5")}>
      <Heading title={o("title", { no: next })} date={today()} />
      <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
        <Field label={o("type")} required>
          <Select
            value={form.kind}
            onValueChange={set("kind")}
            options={[
              { value: "", label: t("f.all") },
              ...["FABRIC", "TRIMS"].map((k) => ({
                value: k,
                label: t(`kind.${k}`),
              })),
            ]}
            placeholder={t("f.all")}
            aria-label={o("type")}
          />
        </Field>
        <Field
          label={o("material")}
          required
          error={err("stock_id")}
          className="lg:col-span-2"
        >
          <Select
            value={form.stock_id}
            onValueChange={set("stock_id")}
            options={stocks
              .filter((s) => !form.kind || s.kind === form.kind)
              .map((s) => ({ value: s.value, label: s.label }))}
            placeholder={t("f.select")}
            aria-label={o("material")}
          />
        </Field>
        <Field label={o("available")}>
          <p className="flex h-10 items-center rounded-sm border border-border bg-surface-muted px-3 text-sm font-semibold tabular-nums">
            {stock ? `${qty(stock.available)} ${stock.uom}` : "—"}
          </p>
        </Field>
        <Field label={o("purpose")} required error={err("purpose")}>
          <Select
            value={form.purpose}
            onValueChange={set("purpose")}
            options={data.purposes.map((p) => ({
              value: p,
              label: t(`purpose.${p}`),
            }))}
            placeholder={t("f.select")}
            aria-label={o("purpose")}
          />
        </Field>
        <div className="grid grid-cols-[1fr_6rem] gap-3">
          <Field label={o("qty")} required error={err("qty")}>
            <Input
              type="number"
              min="0"
              step="any"
              value={
                byRoll ? String(Math.round(rollTotal * 1000) / 1000) : form.qty
              }
              onChange={(ev) => set("qty")(ev.target.value)}
              readOnly={byRoll}
              className={cn(byRoll && "bg-surface-muted")}
              aria-label={o("qty")}
            />
          </Field>
          <Field label={o("unit")}>
            <Input
              value={stock?.uom ?? ""}
              readOnly
              className="bg-surface-muted"
              aria-label={o("unit")}
            />
          </Field>
        </div>
        <Field label={o("floor")} required error={err("floor_id")}>
          <Select
            value={form.floor_id}
            onValueChange={set("floor_id")}
            options={data.floors}
            placeholder={t("f.select")}
            aria-label={o("floor")}
          />
        </Field>
        <Field
          label={o("section")}
          required
          error={err("section_id")}
          hint={form.floor_id && !sections.length ? o("no_records") : null}
        >
          <Select
            value={form.section_id}
            onValueChange={set("section_id")}
            options={sections.map((s) => ({
              value: s.value,
              label: s.label,
            }))}
            placeholder={t("f.select")}
            disabled={!form.floor_id}
            aria-label={o("section")}
          />
        </Field>
        <Field label={o("line")} required error={err("line_id")}>
          <Select
            value={form.line_id}
            onValueChange={set("line_id")}
            options={lines.map((l) => ({
              value: l.value,
              label: l.label,
            }))}
            placeholder={t("f.select")}
            disabled={!form.section_id}
            aria-label={o("line")}
          />
        </Field>
        <EmployeeField
          label={o("receiver")}
          required
          value={form.receiver}
          onChange={set("receiver")}
          error={err("receiver")}
          lookup={(id) => act("employee", id)}
        />
        <Field label={o("remarks")} className="lg:col-span-2">
          <Input
            value={form.remarks}
            onChange={(ev) => set("remarks")(ev.target.value)}
            aria-label={o("remarks")}
          />
        </Field>
      </div>
      {stock?.rolls?.length ? (
        <div className="flex flex-col gap-2 rounded-lg border border-dashed border-border bg-surface-muted/40 p-3">
          <p className="text-[13px] font-semibold text-foreground">
            {t("roll.pick_title")}{" "}
            <span className="font-normal text-foreground-muted">
              {t("roll.pick_hint")}
            </span>
          </p>
          <div className="overflow-x-auto">
            <table className="w-full min-w-[620px] text-sm">
              <thead>
                <tr className="text-left text-xs font-semibold uppercase text-foreground-muted">
                  {[
                    "roll_no",
                    "dye_lot",
                    "gsm",
                    "width",
                    "available",
                    "out",
                  ].map((k) => (
                    <th
                      key={k}
                      className={cn(
                        "px-2 py-1.5",
                        ["available", "out"].includes(k) && "text-right",
                      )}
                    >
                      {t(`roll.col.${k}`)}
                    </th>
                  ))}
                </tr>
              </thead>
              <tbody>
                {stock.rolls.map((r) => {
                  const over = Number(rollQty[r.id] || 0) > r.available;
                  return (
                    <tr key={r.id} className="border-t border-border">
                      <td className="px-2 py-1.5 font-semibold">{r.roll_no}</td>
                      <td className="px-2 py-1.5">{dash(r.dye_lot)}</td>
                      <td className="px-2 py-1.5">{dash(r.gsm)}</td>
                      <td className="px-2 py-1.5">{dash(r.width)}</td>
                      <td className="px-2 py-1.5 text-right tabular-nums">
                        {qty(r.available)} {stock.uom}
                      </td>
                      <td className="px-2 py-1">
                        <Input
                          type="number"
                          min="0"
                          step="any"
                          max={r.available}
                          value={rollQty[r.id] ?? ""}
                          onChange={(ev) => {
                            setRollQty((all) => ({
                              ...all,
                              [r.id]: ev.target.value,
                            }));
                            setErrors((all) =>
                              dropError(dropError(all, "rolls"), "qty"),
                            );
                          }}
                          placeholder="0"
                          aria-label={`${t("roll.col.out")} ${r.roll_no}`}
                          className={cn(
                            "ml-auto h-9 w-32 text-right",
                            over && "border-danger",
                          )}
                        />
                      </td>
                    </tr>
                  );
                })}
              </tbody>
            </table>
          </div>
          {err("rolls") ? (
            <span className="text-xs text-danger">{err("rolls")}</span>
          ) : null}
        </div>
      ) : null}
      {canIssue ? (
        <div className="flex justify-end gap-2 border-t border-border pt-4">
          <Button onClick={confirm} loading={pending}>
            {o("confirm")}
          </Button>
          <Button
            variant="outline"
            onClick={() => (setForm(blank), setErrors({}))}
          >
            <RotateCcw className="size-4" aria-hidden />
            {o("reset")}
          </Button>
        </div>
      ) : null}
    </section>
  );
}

/** Material Return. */
function MaterialReturn({ data, canIssue, act }) {
  const t = useT("storematerial");
  const r = (k, p) => t(`mr.${k}`, p);
  const toastManager = useToastManager();
  const blank = {
    mo_no: "",
    roll_id: "",
    qty: "",
    reason: "",
    line_name: "",
    returned_by: "",
    received_by: "",
    remarks: "",
  };
  const [form, setForm] = useState(blank);
  const [mo, setMo] = useState(null);
  const [next, setNext] = useState(data.next.mr);
  const [errors, setErrors] = useState({});
  const [pending, startTransition] = useTransition();
  const set = (k) => (v) => {
    setForm((all) => ({ ...all, [k]: v ?? "" }));
    setErrors((all) => dropError(all, k));
  };
  const err = (k) => errors[k]?.[0];

  function fetchMo() {
    if (!form.mo_no.trim() || mo?.mo_no === form.mo_no.trim().toUpperCase())
      return;
    setErrors({});
    startTransition(async () => {
      const result = await act("materialOutOf", form.mo_no.trim());
      if (result?.status !== "success") {
        setMo(null);
        return setErrors(messagesOf(result));
      }
      setMo(result.data);
      setForm((all) => ({
        ...all,
        mo_no: result.data.mo_no,
        roll_id: "",
        line_name: result.data.line ?? "",
      }));
    });
  }

  function confirm() {
    setErrors({});
    const payload = Object.fromEntries(
      Object.entries(form).map(([k, v]) => [k, v === "" ? null : v]),
    );
    startTransition(async () => {
      const result = await act("materialReturn", payload);
      if (result?.status !== "success") return setErrors(messagesOf(result));
      toastManager.add({
        title: r("done", { no: result.data.mr_no }),
        type: "success",
      });
      setNext(
        `MR-${result.data.mr_no.slice(3, 7)}-${String(Number(result.data.mr_no.slice(8)) + 1).padStart(5, "0")}`,
      );
      setMo(null);
      setForm(blank);
    });
  }

  return (
    <section className={cn(card, "flex flex-col gap-4 p-5")}>
      <Heading title={r("title", { no: next })} date={today()} />
      <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <Field label={r("mo_no")} required error={err("mo_no")}>
          <Input
            value={form.mo_no}
            onChange={(ev) => set("mo_no")(ev.target.value)}
            onBlur={fetchMo}
            onKeyDown={(ev) =>
              ev.key === "Enter" && (ev.preventDefault(), fetchMo())
            }
            placeholder={r("mo_ph")}
            aria-label={r("mo_no")}
          />
        </Field>
        {[
          ["original", mo ? `${qty(mo.qty)} ${mo.uom}` : null],
          ["previous", mo ? `${qty(mo.returned)} ${mo.uom}` : null],
          ["eligible", mo ? `${qty(mo.eligible)} ${mo.uom}` : null],
        ].map(([k, v]) => (
          <Field key={k} label={r(k)}>
            <p
              className={cn(
                "flex h-10 items-center rounded-sm border border-border bg-surface-muted px-3 text-sm tabular-nums",
                k === "eligible" && "font-semibold",
              )}
            >
              {dash(v)}
            </p>
          </Field>
        ))}
        {mo ? (
          <p className="text-[13px] text-foreground-muted sm:col-span-2 lg:col-span-4">
            {r("material")}:{" "}
            <span className="font-medium text-foreground">{mo.material}</span> ·{" "}
            {t(`purpose.${mo.purpose}`)} · {r("receiver")}:{" "}
            <span className="font-medium text-foreground">
              {dash(mo.receiver)}
            </span>
          </p>
        ) : null}
        {mo?.rolls?.length ? (
          <Field label={r("roll")} required error={err("roll_id")}>
            <Select
              value={form.roll_id}
              onValueChange={set("roll_id")}
              options={mo.rolls.map((x) => ({
                value: x.roll_id,
                label: r("roll_option", {
                  roll: x.roll_no,
                  lot: x.dye_lot ?? "—",
                  n: qty(x.eligible),
                  uom: mo.uom,
                }),
              }))}
              placeholder={t("f.select")}
              aria-label={r("roll")}
            />
          </Field>
        ) : null}
        <Field label={r("qty")} required error={err("qty")}>
          <Input
            type="number"
            min="0"
            step="any"
            max={
              mo?.rolls?.find((x) => x.roll_id === form.roll_id)?.eligible ??
              mo?.eligible
            }
            value={form.qty}
            onChange={(ev) => set("qty")(ev.target.value)}
            aria-label={r("qty")}
          />
        </Field>
        <Field label={r("reason")} required error={err("reason")}>
          <Select
            value={form.reason}
            onValueChange={set("reason")}
            options={data.return_reasons.map((k) => ({
              value: k,
              label: t(`return_reason.${k}`),
            }))}
            placeholder={t("f.select")}
            aria-label={r("reason")}
          />
        </Field>
        <Field label={r("floor")}>
          <Input
            value={mo?.floor ?? ""}
            readOnly
            className="bg-surface-muted"
            aria-label={r("floor")}
          />
        </Field>
        <Field label={r("section")}>
          <Input
            value={mo?.section ?? ""}
            readOnly
            className="bg-surface-muted"
            aria-label={r("section")}
          />
        </Field>
        <Field label={r("line")} required error={err("line_name")}>
          <Input
            value={form.line_name}
            onChange={(ev) => set("line_name")(ev.target.value)}
            aria-label={r("line")}
          />
        </Field>
        <EmployeeField
          label={r("returned_by")}
          value={form.returned_by}
          onChange={set("returned_by")}
          error={err("returned_by")}
          lookup={(id) => act("employee", id)}
        />
        <EmployeeField
          label={r("received_by")}
          value={form.received_by}
          onChange={set("received_by")}
          error={err("received_by")}
          lookup={(id) => act("employee", id)}
        />
        <Field label={r("remarks")}>
          <Input
            value={form.remarks}
            onChange={(ev) => set("remarks")(ev.target.value)}
            aria-label={r("remarks")}
          />
        </Field>
      </div>
      {canIssue ? (
        <div className="flex justify-end gap-2 border-t border-border pt-4">
          <Button onClick={confirm} loading={pending} disabled={!mo}>
            {r("confirm")}
          </Button>
          <Button
            variant="outline"
            onClick={() => (setForm(blank), setMo(null), setErrors({}))}
          >
            <RotateCcw className="size-4" aria-hidden />
            {r("reset")}
          </Button>
        </div>
      ) : null}
    </section>
  );
}

/** Stock Adjustment. */
function StockAdjustment({ data, canAdjust, canManage, act }) {
  const t = useT("storematerial");
  const s = (k, p) => t(`sa.${k}`, p);
  const toastManager = useToastManager();
  const blank = { stock_id: "", physical_qty: "", reason: "", remarks: "" };
  const [form, setForm] = useState(blank);
  const [state, setState] = useState({
    stocks: data.stocks,
    adjustments: data.adjustments,
    min_levels: data.min_levels,
    next: data.next.sa,
  });
  const [min, setMin] = useState({ item_id: "", min_qty: "" });
  const [errors, setErrors] = useState({});
  const [pending, startTransition] = useTransition();
  const set = (k) => (v) => {
    setForm((all) => ({ ...all, [k]: v ?? "" }));
    setErrors((all) => dropError(all, k));
  };
  const stock = state.stocks.find((x) => x.value === form.stock_id);
  const diff =
    stock && form.physical_qty !== ""
      ? Math.round((Number(form.physical_qty) - stock.available) * 1000) / 1000
      : null;
  const err = (k) => errors[k]?.[0];

  function submit() {
    setErrors({});
    startTransition(async () => {
      const result = await act(
        "adjust",
        Object.fromEntries(
          Object.entries(form).map(([k, v]) => [k, v === "" ? null : v]),
        ),
      );
      if (result?.status !== "success") return setErrors(messagesOf(result));
      toastManager.add({ title: s("submitted"), type: "success" });
      setState((all) => ({
        ...all,
        adjustments: result.data.adjustments,
        next: result.data.next,
      }));
      setForm(blank);
    });
  }

  function decide(no, decision) {
    startTransition(async () => {
      const result = await act("decide", no, { decision });
      if (result?.status !== "success")
        return toastManager.add({
          title: messagesOf(result)[Object.keys(messagesOf(result))[0]]?.[0],
          type: "danger",
        });
      toastManager.add({
        title: s("decided_msg", {
          no,
          status: s(`status.${decision}`),
        }),
        type: "success",
      });
      setState((all) => ({
        ...all,
        adjustments: result.data.adjustments,
        stocks: result.data.stocks,
      }));
    });
  }

  function saveMin() {
    setErrors({});
    startTransition(async () => {
      const result = await act("minLevel", {
        item_id: min.item_id || null,
        min_qty: min.min_qty === "" ? null : min.min_qty,
      });
      if (result?.status !== "success") return setErrors(messagesOf(result));
      toastManager.add({ title: s("saved"), type: "success" });
      setState((all) => ({ ...all, min_levels: result.data.min_levels }));
      setMin({ item_id: "", min_qty: "" });
    });
  }

  const tone = {
    PENDING: "bg-warning-subtle text-warning",
    APPROVED: "bg-success-subtle text-success",
    REJECTED: "bg-danger-subtle text-danger",
  };

  return (
    <div className="flex flex-col gap-4">
      <section className={cn(card, "flex flex-col gap-4 p-5")}>
        <Heading title={s("title", { no: state.next })} date={today()} />
        <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
          <Field
            label={s("material")}
            required
            error={err("stock_id")}
            className="lg:col-span-2"
          >
            <Select
              value={form.stock_id}
              onValueChange={set("stock_id")}
              options={state.stocks.map((x) => ({
                value: x.value,
                label: x.label,
              }))}
              placeholder={t("f.select")}
              aria-label={s("material")}
            />
          </Field>
          <Field label={s("system")}>
            <p className="flex h-10 items-center rounded-sm border border-border bg-surface-muted px-3 text-sm tabular-nums">
              {stock ? `${qty(stock.available)} ${stock.uom}` : "—"}
            </p>
          </Field>
          <Field label={s("physical")} required error={err("physical_qty")}>
            <Input
              type="number"
              min="0"
              step="any"
              value={form.physical_qty}
              onChange={(ev) => set("physical_qty")(ev.target.value)}
              aria-label={s("physical")}
            />
          </Field>
          <Field label={s("difference")}>
            <p
              className={cn(
                "flex h-10 items-center rounded-sm border border-border bg-surface-muted px-3 text-sm font-semibold tabular-nums",
                diff > 0 && "text-success",
                diff < 0 && "text-danger",
              )}
            >
              {diff === null ? "—" : `${diff > 0 ? "+" : ""}${qty(diff)}`}
            </p>
          </Field>
          <Field label={s("reason")} required error={err("reason")}>
            <Select
              value={form.reason}
              onValueChange={set("reason")}
              options={data.adjust_reasons.map((k) => ({
                value: k,
                label: t(`adjust_reason.${k}`),
              }))}
              placeholder={t("f.select")}
              aria-label={s("reason")}
            />
          </Field>
          <Field label={s("remarks")} className="lg:col-span-2">
            <Textarea
              rows={2}
              value={form.remarks}
              onChange={(ev) => set("remarks")(ev.target.value)}
              aria-label={s("remarks")}
            />
          </Field>
        </div>
        {canAdjust ? (
          <div className="flex justify-end gap-2 border-t border-border pt-4">
            <Button
              variant="outline"
              onClick={() => (setForm(blank), setErrors({}))}
            >
              <RotateCcw className="size-4" aria-hidden />
              {s("reset")}
            </Button>
            <Button onClick={submit} loading={pending}>
              {s("submit")}
            </Button>
          </div>
        ) : null}
      </section>

      <section className={cn(card, "min-w-0 overflow-hidden")}>
        <h2 className="p-5 text-base font-semibold text-foreground">
          {s("list")}
        </h2>
        <div className="overflow-x-auto">
          <table className="w-full min-w-[980px] text-sm">
            <thead className="bg-brand text-brand-foreground">
              <tr>
                {[
                  "sa_no",
                  "date",
                  "material",
                  "buyer_style",
                  "system",
                  "physical",
                  "difference",
                  "reason",
                  "people",
                  "status",
                ].map((k) => (
                  <th
                    key={k}
                    className={cn(
                      "px-3 py-2.5 text-left text-xs font-semibold uppercase",
                      ["system", "physical", "difference"].includes(k) &&
                        "text-right",
                    )}
                  >
                    {s(`col.${k}`)}
                  </th>
                ))}
              </tr>
            </thead>
            <tbody>
              {state.adjustments.length === 0 ? (
                <tr>
                  <td
                    colSpan={10}
                    className="px-3 py-8 text-center text-foreground-muted"
                  >
                    {s("no_rows")}
                  </td>
                </tr>
              ) : null}
              {state.adjustments.map((a) => (
                <tr key={a.sa_no} className="border-t border-border align-top">
                  <td className="px-3 py-2.5 font-semibold whitespace-nowrap">
                    {a.sa_no}
                  </td>
                  <td className="px-3 py-2.5 whitespace-nowrap">
                    {day(a.date)}
                  </td>
                  <td className="px-3 py-2.5">{a.material}</td>
                  <td className="px-3 py-2.5">{dash(a.buyer_style)}</td>
                  <td className="px-3 py-2.5 text-right tabular-nums">
                    {qty(a.system)}
                  </td>
                  <td className="px-3 py-2.5 text-right tabular-nums">
                    {qty(a.physical)}
                  </td>
                  <td
                    className={cn(
                      "px-3 py-2.5 text-right font-semibold tabular-nums",
                      a.difference > 0 ? "text-success" : "text-danger",
                    )}
                  >
                    {a.difference > 0 ? "+" : ""}
                    {qty(a.difference)}
                  </td>
                  <td className="px-3 py-2.5">
                    {t(`adjust_reason.${a.reason}`)}
                  </td>
                  <td className="px-3 py-2.5 text-xs">
                    <p>{dash(a.requested_by)}</p>
                    <p className="text-foreground-muted">
                      {dash(a.decided_by)}
                    </p>
                  </td>
                  <td className="px-3 py-2.5">
                    <div className="flex flex-col items-start gap-1.5">
                      <span
                        className={cn(
                          "rounded-md px-1.5 py-0.5 text-xs font-medium",
                          tone[a.status],
                        )}
                      >
                        {s(`status.${a.status}`)}
                      </span>
                      {canManage && a.status === "PENDING" ? (
                        <div className="flex gap-1">
                          <Button
                            size="sm"
                            variant="success"
                            onClick={() => decide(a.sa_no, "APPROVED")}
                            disabled={pending}
                          >
                            <Check className="size-3.5" aria-hidden />
                            {s("approve")}
                          </Button>
                          <Button
                            size="sm"
                            variant="outline"
                            onClick={() => decide(a.sa_no, "REJECTED")}
                            disabled={pending}
                          >
                            <X className="size-3.5" aria-hidden />
                            {s("reject")}
                          </Button>
                        </div>
                      ) : null}
                    </div>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </section>

      <section className={cn(card, "flex flex-col gap-4 p-5")}>
        <h2 className="text-base font-semibold text-foreground">
          {s("min_title")}
        </h2>
        {canManage ? (
          <div className="grid items-end gap-3 sm:grid-cols-[1fr_12rem_auto]">
            <Field label={s("material")} error={err("item_id")}>
              <Select
                value={min.item_id}
                onValueChange={(v) =>
                  setMin((m) => ({ ...m, item_id: v ?? "" }))
                }
                options={data.items.map((i) => ({
                  value: i.value,
                  label: i.label,
                }))}
                placeholder={t("f.select")}
                aria-label={`${s("min_title")} ${s("material")}`}
              />
            </Field>
            <Field label={s("min_qty")} error={err("min_qty")}>
              <Input
                type="number"
                min="0"
                step="any"
                value={min.min_qty}
                onChange={(ev) =>
                  setMin((m) => ({
                    ...m,
                    min_qty: ev.target.value,
                  }))
                }
                aria-label={s("min_qty")}
              />
            </Field>
            <Button onClick={saveMin} loading={pending}>
              <Save className="size-4" aria-hidden />
              {s("save")}
            </Button>
          </div>
        ) : null}
        {state.min_levels.length ? (
          <ul className="flex flex-wrap gap-2">
            {state.min_levels.map((m) => (
              <li
                key={m.item_id}
                className="rounded-full border border-border px-3 py-1 text-[13px] text-foreground"
              >
                {m.material} ·{" "}
                <span className="font-semibold tabular-nums">
                  {qty(m.min_qty)}
                </span>{" "}
                {m.uom}
              </li>
            ))}
          </ul>
        ) : null}
        {errors._ ? <Alert variant="danger">{errors._[0]}</Alert> : null}
      </section>
    </div>
  );
}

export { MaterialOut, MaterialReturn, StockAdjustment };
