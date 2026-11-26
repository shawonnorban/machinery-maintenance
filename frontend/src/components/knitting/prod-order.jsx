"use client";

import { useMemo, useState, useTransition } from "react";
import Link from "next/link";
import {
  ArrowLeft,
  ArrowRight,
  CheckCheck,
  Copy,
  Download,
  ListChecks,
  ListTree,
  Pause,
  Pencil,
  Play,
  Plus,
  RotateCcw,
  Save,
  Scissors,
  Search,
  Send,
  Trash2,
} from "lucide-react";
import { cn } from "@/lib/utils";
import { useT } from "@/lib/i18n";
import { Input } from "@/components/ui/input";
import { Textarea } from "@/components/ui/textarea";
import { Button, buttonVariants } from "@/components/ui/button";
import { Alert } from "@/components/ui/alert";
import { Modal } from "@/components/ui/modal";
import { ConfirmDialog } from "@/components/ui/confirm-dialog";
import { useToastManager } from "@/components/ui/toast";
import {
  PSelect,
  str,
  qtyFmt,
  dayFmt,
  todayIso,
  messagesOf,
  card,
  th,
  Field,
  StatusChip,
  NoLines,
  orNull,
} from "./yarn-common";

/*
 * Knit Production → Production (AnnotechERP 15/04) → Bulk, Yarn-Dyed,
 * Sample and Flat Bed Rib Fabric Production Order: the register (nothing in
 * it is typed — KP Made, KP Blnc, KC Done, KC Blnc and Rolled are what the
 * programmes, cards and rolls come to), the order's own form in three steps
 * (Order, Fabric, Quantities), and the Knit Programmes and Knitcard desks —
 * the orders down the left, the selected order's programmes or cards on the
 * right, edited in place and saved together. Nothing reloads.
 */

function usePr() {
  const t = useT("knitting");
  return (k, p) => t(`prod.${k}`, p);
}

const TONE = {
  RUNNING: "PLACED",
  COMPLETED: "RECEIVED",
  HOLD: "WAITING_APPROVAL",
  CLOSED: "CLOSED",
  CANCELLED: "CANCELLED",
  OPEN: "DRAFT",
};
const months = () => {
  const out = [];
  const now = new Date();
  for (let i = -6; i < 18; i++) {
    const d = new Date(now.getFullYear(), now.getMonth() - i, 1);
    out.push(`${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, "0")}`);
  }
  return out;
};
const csv = (name, head, rows) => {
  const esc = (v) => `"${String(v ?? "").replaceAll('"', '""')}"`;
  const url = URL.createObjectURL(
    new Blob([[head, ...rows].map((r) => r.map(esc).join(",")).join("\n")], {
      type: "text/csv",
    }),
  );
  Object.assign(document.createElement("a"), {
    href: url,
    download: `${name}.csv`,
  }).click();
  URL.revokeObjectURL(url);
};

const REG_COLS = [
  "category",
  "buyer",
  "order",
  "order_type",
  "fabric",
  "colour",
  "booking_ref",
  "req_qty",
  "kp_made",
  "kp_balance",
  "kc_done",
  "kc_balance",
  "adjusted",
  "yarn_stock",
  "rolled",
  "saving_target",
  "status",
];
const NUM = new Set([
  "req_qty",
  "kp_made",
  "kp_balance",
  "kc_done",
  "kc_balance",
  "adjusted",
  "yarn_stock",
  "rolled",
  "saving_target",
]);

function PoRegister({ base, kind, options, initialRows, canManage, act }) {
  const p = usePr();
  const toastManager = useToastManager();
  const blank = {
    buyer: "",
    ship_month: "",
    style: "",
    order: "",
    status: "RUNNING",
  };
  const [f, setFilters] = useState(blank);
  const [rows, setRows] = useState(initialRows);
  const [error, setError] = useState(null);
  const [pending, startTransition] = useTransition();
  const setF = (k, v) => setFilters((x) => ({ ...x, [k]: v ?? "" }));
  const styles = useMemo(
    () =>
      [...new Set(options.orders.map((o) => o.style).filter(Boolean))].map(
        (v) => ({ value: v, label: v }),
      ),
    [options.orders],
  );

  function search(next = f) {
    startTransition(async () => {
      const q = new URLSearchParams(
        Object.entries({ kind, ...next }).filter(([, v]) => v),
      );
      const res = await act(`orders?${q}`);
      if (res?.status !== "success") return setError(messagesOf(res));
      setRows(res.data);
      setError(null);
    });
  }
  function run(r, action) {
    startTransition(async () => {
      const res = await act(
        `orders/${encodeURIComponent(r.po_no)}/${action}`,
        "POST",
      );
      if (res?.status !== "success") return setError(messagesOf(res));
      setRows((all) => all.map((x) => (x.po_no === r.po_no ? res.data : x)));
      toastManager.add({
        title: p(`order_done.${action}`, { order: r.order }),
        type: "success",
      });
    });
  }
  const cell = (r, k) =>
    k === "status" ? (
      <StatusChip
        status={TONE[r.status]}
        label={p(`order_status.${r.status}`)}
      />
    ) : NUM.has(k) ? (
      qtyFmt.format(r[k])
    ) : (
      (r[k] ?? "—")
    );

  return (
    <div className="flex flex-col gap-4">
      <section className={cn(card, "p-5")}>
        <h2 className="flex items-center gap-2 text-base font-semibold text-foreground">
          <Search className="size-4" /> {p("po.search")}
        </h2>
        <p className="mt-0.5 text-[13px] text-foreground-muted">
          {p("po.list_hint")}
        </p>
        <div className="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
          <Field label={p("po.type")}>
            <Input
              value={p(`kind.${kind}`)}
              readOnly
              className="bg-surface-muted"
              aria-label={p("po.type")}
            />
          </Field>
          <Field label={p("po.buyer")}>
            <PSelect
              value={f.buyer}
              onValueChange={(v) => setF("buyer", v)}
              options={[
                { value: "", label: "--- Buyer A/C ---" },
                ...options.buyers,
              ]}
              aria-label={p("po.buyer")}
            />
          </Field>
          <Field label={p("po.ship_month")}>
            <PSelect
              value={f.ship_month}
              onValueChange={(v) => setF("ship_month", v)}
              options={[
                { value: "", label: "-- Select Month --" },
                ...months().map((m) => ({ value: m, label: m })),
              ]}
              aria-label={p("po.ship_month")}
            />
          </Field>
          <Field label={p("po.style")}>
            <PSelect
              value={f.style}
              onValueChange={(v) => setF("style", v)}
              options={[{ value: "", label: "--Style --" }, ...styles]}
              aria-label={p("po.style")}
            />
          </Field>
          <Field label={p("po.order_style")}>
            <PSelect
              value={f.order}
              onValueChange={(v) => setF("order", v)}
              options={[{ value: "", label: "--Order# --" }, ...options.orders]}
              aria-label={p("po.order_style")}
            />
          </Field>
          <Field label={p("po.status")}>
            <PSelect
              value={f.status}
              onValueChange={(v) => setF("status", v)}
              options={[
                { value: "", label: "-- All --" },
                ...options.order_statuses.map((s) => ({
                  value: s,
                  label: p(`order_status.${s}`),
                })),
              ]}
              aria-label={p("po.status")}
            />
          </Field>
        </div>
        <div className="mt-4 flex justify-between gap-2 border-t border-border pt-4">
          <Button onClick={() => search()} loading={pending}>
            <Search /> {p("show")}
          </Button>
          <Button
            variant="outline"
            onClick={() => (setFilters(blank), search(blank))}
            disabled={pending}
          >
            <RotateCcw /> {p("reset")}
          </Button>
        </div>
      </section>
      {error ? <Alert variant="danger">{error.join(" · ")}</Alert> : null}
      <section className={card}>
        <div className="flex items-center justify-between gap-3 px-5 py-3">
          <p className="text-[13px] text-foreground-muted">
            {p("po.grid_hint")}
          </p>
          <Button
            variant="outline"
            size="sm"
            onClick={() =>
              csv(
                `production-orders-${kind.toLowerCase()}`,
                REG_COLS.map((k) => p(`po.lc.${k}`)),
                rows.map((r) =>
                  REG_COLS.map((k) =>
                    k === "status" ? p(`order_status.${r.status}`) : r[k],
                  ),
                ),
              )
            }
          >
            <Download /> {p("export")}
          </Button>
        </div>
        {rows.length === 0 ? (
          <NoLines title={p("po.empty")} hint={p("po.empty_hint")} />
        ) : (
          <div className="overflow-x-auto border-t border-border">
            <table className="w-full min-w-[1900px] text-sm">
              <thead className="bg-brand text-brand-foreground">
                <tr>
                  {REG_COLS.map((k) => (
                    <th key={k} className={cn(th, NUM.has(k) && "text-right")}>
                      {p(`po.lc.${k}`)}
                    </th>
                  ))}
                  <th className={th}>{p("action_col")}</th>
                </tr>
              </thead>
              <tbody>
                {rows.map((r) => (
                  <tr key={r.po_no} className="border-t border-border">
                    {REG_COLS.map((k) => (
                      <td
                        key={k}
                        className={cn(
                          "px-2 py-2",
                          NUM.has(k) && "text-right tabular-nums",
                          ["order", "category"].includes(k) &&
                            "whitespace-nowrap",
                          k === "kp_balance" && "font-semibold",
                        )}
                      >
                        {cell(r, k)}
                      </td>
                    ))}
                    <td className="px-2 py-1.5">
                      <div className="flex gap-1">
                        {canManage && ["RUNNING", "HOLD"].includes(r.status) ? (
                          <Button
                            variant="outline"
                            size="sm"
                            onClick={() => run(r, "complete")}
                            disabled={pending}
                          >
                            <CheckCheck /> {p("po.btn.complete")}
                          </Button>
                        ) : null}
                        {canManage && r.status === "RUNNING" ? (
                          <Button
                            variant="outline"
                            size="sm"
                            onClick={() => run(r, "hold")}
                            disabled={pending}
                          >
                            <Pause /> {p("po.btn.hold")}
                          </Button>
                        ) : null}
                        {canManage &&
                        ["HOLD", "COMPLETED"].includes(r.status) ? (
                          <Button
                            variant="outline"
                            size="sm"
                            onClick={() => run(r, "resume")}
                            disabled={pending}
                          >
                            <Play /> {p("po.btn.resume")}
                          </Button>
                        ) : null}
                        <Link
                          href={`${base}?tab=knitcard&order=${encodeURIComponent(r.po_no)}`}
                          className={buttonVariants({
                            variant: "outline",
                            size: "sm",
                          })}
                        >
                          <ListChecks /> {p("po.btn.knitcard")}
                        </Link>
                        {canManage ? (
                          <>
                            <Link
                              href={`${base}?tab=entry&ref=${encodeURIComponent(r.po_no)}`}
                              className={buttonVariants({
                                variant: "outline",
                                size: "sm",
                              })}
                            >
                              <Pencil /> {p("edit")}
                            </Link>
                            <Link
                              href={`${base}?tab=entry&copy=${encodeURIComponent(r.po_no)}`}
                              className={buttonVariants({
                                variant: "outline",
                                size: "sm",
                              })}
                            >
                              <Copy /> {p("copy")}
                            </Link>
                          </>
                        ) : null}
                      </div>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </section>
    </div>
  );
}

const HEAD = [
  "order_id",
  "category_code",
  "ship_month",
  "fabric_type_id",
  "fabric_desc",
  "colour_id",
  "colour_name",
  "finish_dia",
  "gsm",
  "item_image",
  "booking_ref",
  "req_qty",
  "adjusted_qty",
  "saving_target_qty",
  "yarn_stock_qty",
  "status",
  "remarks",
];
const STEPS = ["order", "fabric", "quantities"];

function PoEntry({ base, kind, options, initial, copy, canManage, act }) {
  const p = usePr();
  const toastManager = useToastManager();
  const asHead = (d) =>
    d
      ? Object.fromEntries(HEAD.map((k) => [k, str(d[k])]))
      : { ...Object.fromEntries(HEAD.map((k) => [k, ""])), status: "RUNNING" };
  const [doc, setDoc] = useState(initial);
  const [head, setHead] = useState(() => asHead(initial ?? copy));
  const [step, setStep] = useState("order");
  const [errors, setErrors] = useState(null);
  const [pending, startTransition] = useTransition();
  const editable = canManage;
  const setH = (patch) => setHead((h) => ({ ...h, ...patch }));
  const order = options.orders.find((o) => o.value === head.order_id);
  const pick = (list, ph) => [{ value: "", label: ph }, ...list];
  const at = STEPS.indexOf(step);

  function clear() {
    setDoc(null);
    setHead(asHead(null));
    setStep("order");
    setErrors(null);
    window.history.replaceState(null, "", "?tab=entry");
  }
  function save() {
    startTransition(async () => {
      const body = {
        order_kind: kind,
        ...Object.fromEntries(
          HEAD.map((k) => [k, head[k] === "" ? null : head[k]]),
        ),
        gsm: orNull(head.gsm),
        req_qty: orNull(head.req_qty),
        adjusted_qty: orNull(head.adjusted_qty),
        saving_target_qty: orNull(head.saving_target_qty),
        yarn_stock_qty: orNull(head.yarn_stock_qty),
      };
      const res = await act(
        doc ? `orders/${encodeURIComponent(doc.po_no)}` : "orders",
        doc ? "PUT" : "POST",
        body,
      );
      if (res?.status !== "success") return setErrors(messagesOf(res));
      setDoc(res.data);
      setHead(asHead(res.data));
      setErrors(null);
      window.history.replaceState(
        null,
        "",
        `?tab=entry&ref=${encodeURIComponent(res.data.po_no)}`,
      );
      toastManager.add({
        title: p("po.saved", { order: res.data.figures.order }),
        type: "success",
      });
    });
  }

  return (
    <div className="flex flex-col gap-4">
      <section className={cn(card, "p-5")}>
        <div className="flex items-start justify-between gap-3">
          <div>
            <h2 className="text-base font-semibold text-foreground">
              {doc
                ? p("po.editing", { order: doc.figures.order })
                : p(`po.new.${kind}`)}
            </h2>
            <p className="mt-0.5 text-[13px] text-foreground-muted">
              {p("po.entry_hint")}
            </p>
          </div>
          {doc ? (
            <StatusChip
              status={TONE[doc.status]}
              label={p(`order_status.${doc.status}`)}
            />
          ) : null}
        </div>
      </section>
      <div className="flex gap-1 border-b border-border" role="tablist">
        {STEPS.map((s) => (
          <button
            key={s}
            type="button"
            role="tab"
            aria-selected={step === s}
            onClick={() => setStep(s)}
            className={cn(
              "-mb-px border-b-2 px-3 py-2 text-sm font-medium",
              step === s
                ? "border-brand text-brand"
                : "border-transparent text-foreground-muted hover:text-foreground",
            )}
          >
            {p(`po.step.${s}`)}
          </button>
        ))}
      </div>
      <section className={cn(card, "p-5")}>
        <p className="mb-4 text-[13px] text-foreground-muted">
          {p(`po.step_hint.${step}`)}
        </p>
        {step === "order" ? (
          <div className="grid gap-4 md:grid-cols-3">
            <Field label={p("po.order")} required>
              <PSelect
                value={head.order_id}
                onValueChange={(v) => {
                  const o = options.orders.find((x) => x.value === v);
                  setH({
                    order_id: v ?? "",
                    ship_month: head.ship_month || str(o?.ship_month),
                  });
                }}
                options={pick(options.orders, "-- Select Order --")}
                disabled={!editable}
                aria-label={p("po.order")}
              />
            </Field>
            <Field label={p("po.style_one")}>
              <Input
                value={order?.style ?? ""}
                placeholder="-- Select Style --"
                readOnly
                className="bg-surface-muted"
                aria-label={p("po.style_one")}
              />
            </Field>
            <Field label={p("po.buyer")}>
              <Input
                value={order?.buyer ?? ""}
                placeholder="-- Select Buyer A/C --"
                readOnly
                className="bg-surface-muted"
                aria-label={p("po.buyer")}
              />
            </Field>
            <Field label={p("company")} required>
              <PSelect
                value={options.company.value}
                options={[options.company]}
                disabled
                aria-label={p("company")}
              />
            </Field>
            <Field label={p("po.cat")}>
              <PSelect
                value={head.category_code}
                onValueChange={(v) => setH({ category_code: v ?? "" })}
                options={pick(options.categories, "-- Select Category --")}
                disabled={!editable}
                aria-label={p("po.cat")}
              />
            </Field>
            <Field label={p("po.ship_month")}>
              <PSelect
                value={head.ship_month}
                onValueChange={(v) => setH({ ship_month: v ?? "" })}
                options={pick(
                  months().map((m) => ({ value: m, label: m })),
                  "-- Select Month --",
                )}
                disabled={!editable}
                aria-label={p("po.ship_month")}
              />
            </Field>
          </div>
        ) : null}
        {step === "fabric" ? (
          <div className="grid gap-4 md:grid-cols-3">
            <Field label={p("po.fabric_type")}>
              <PSelect
                value={head.fabric_type_id}
                onValueChange={(v) => setH({ fabric_type_id: v ?? "" })}
                options={pick(options.fabric_types, "-- Select Fabric --")}
                disabled={!editable}
                aria-label={p("po.fabric_type")}
              />
            </Field>
            <Field label={p("po.fab")} required className="md:col-span-2">
              <Input
                value={head.fabric_desc}
                onChange={(e) => setH({ fabric_desc: e.target.value })}
                placeholder="S/J 30/1 Combed 180 GSM"
                maxLength={160}
                disabled={!editable}
                aria-label={p("po.fab")}
              />
            </Field>
            <Field label={p("po.colour")}>
              <PSelect
                value={head.colour_id}
                onValueChange={(v) => setH({ colour_id: v ?? "" })}
                options={pick(options.colours, "-- Select Colour --")}
                disabled={!editable}
                aria-label={p("po.colour")}
              />
            </Field>
            <Field label={p("po.colour_written")}>
              <Input
                value={head.colour_name}
                onChange={(e) => setH({ colour_name: e.target.value })}
                placeholder={p("po.colour_written_ph")}
                maxLength={80}
                disabled={!editable}
                aria-label={p("po.colour_written")}
              />
            </Field>
            <Field label={p("po.finish_dia")}>
              <Input
                value={head.finish_dia}
                onChange={(e) => setH({ finish_dia: e.target.value })}
                maxLength={24}
                disabled={!editable}
                aria-label={p("po.finish_dia")}
              />
            </Field>
            <Field label={p("po.gsm")}>
              <Input
                type="number"
                min="0"
                step="any"
                value={head.gsm}
                onChange={(e) => setH({ gsm: e.target.value })}
                disabled={!editable}
                aria-label={p("po.gsm")}
              />
            </Field>
            <Field label={p("po.image")} hint={p("po.image_hint")}>
              <Input
                value={head.item_image}
                onChange={(e) => setH({ item_image: e.target.value })}
                placeholder="/uploads/styles/1043278.jpg"
                maxLength={255}
                disabled={!editable}
                aria-label={p("po.image")}
              />
            </Field>
            <Field label={p("po.bk_rpt")}>
              <Input
                value={head.booking_ref}
                onChange={(e) => setH({ booking_ref: e.target.value })}
                placeholder={p("po.bk_rpt_ph")}
                maxLength={80}
                disabled={!editable}
                aria-label={p("po.bk_rpt")}
              />
            </Field>
          </div>
        ) : null}
        {step === "quantities" ? (
          <div className="grid gap-4 md:grid-cols-3">
            {[
              ["req_qty", true, null],
              ["adjusted_qty", false, "adjusted_hint"],
              ["saving_target_qty", false, null],
              ["yarn_stock_qty", false, "yarn_stock_hint"],
            ].map(([k, req, hint]) => (
              <Field
                key={k}
                label={p(`po.${k}`)}
                required={req}
                hint={hint ? p(`po.${hint}`) : undefined}
              >
                <Input
                  type="number"
                  step="any"
                  value={head[k]}
                  onChange={(e) => setH({ [k]: e.target.value })}
                  disabled={!editable}
                  aria-label={p(`po.${k}`)}
                />
              </Field>
            ))}
            <Field label={p("po.status")} required>
              <PSelect
                value={head.status}
                onValueChange={(v) => setH({ status: v ?? "" })}
                options={[
                  { value: "", label: "Select…" },
                  ...options.order_statuses.map((s) => ({
                    value: s,
                    label: p(`order_status.${s}`),
                  })),
                ]}
                disabled={!editable}
                aria-label={p("po.status")}
              />
            </Field>
            <Field label={p("remarks")} className="md:col-span-3">
              <Textarea
                rows={2}
                value={head.remarks}
                onChange={(e) => setH({ remarks: e.target.value })}
                maxLength={2000}
                disabled={!editable}
                aria-label={p("remarks")}
              />
            </Field>
          </div>
        ) : null}
      </section>
      {errors ? <Alert variant="danger">{errors.join(" · ")}</Alert> : null}
      <section
        className={cn(
          card,
          "flex flex-wrap items-center justify-between gap-2 p-4",
        )}
      >
        <div className="flex gap-2">
          {at > 0 ? (
            <Button variant="outline" onClick={() => setStep(STEPS[at - 1])}>
              <ArrowLeft /> {p("back")}
            </Button>
          ) : null}
          {at < STEPS.length - 1 ? (
            <Button variant="outline" onClick={() => setStep(STEPS[at + 1])}>
              {p("continue")} <ArrowRight />
            </Button>
          ) : null}
        </div>
        <div className="flex flex-wrap gap-2">
          {editable ? (
            <Button onClick={save} loading={pending}>
              <Save /> {p("po.save")}
            </Button>
          ) : null}
          <Button variant="outline" onClick={clear} disabled={pending}>
            <Plus /> {p("add_new")}
          </Button>
          <Link href={base} className={buttonVariants({ variant: "outline" })}>
            <ArrowLeft /> {p("back_list")}
          </Link>
        </div>
      </section>
    </div>
  );
}

/** The orders down the left of the KP and Knitcard desks. */
function OrderPicker({ orders, current, onPick }) {
  const p = usePr();
  const [q, setQ] = useState("");
  const shown = orders.filter((o) =>
    !q
      ? true
      : [o.order, o.fabric, o.colour, o.buyer]
          .join(" ")
          .toLowerCase()
          .includes(q.toLowerCase()),
  );
  return (
    <section className={cn(card, "self-start")}>
      <div className="flex items-center justify-between px-5 pt-5">
        <h2 className="text-base font-semibold text-foreground">
          {p("desk.orders")}
        </h2>
        <span className="rounded-full border border-border px-2 text-xs tabular-nums">
          {orders.length}
        </span>
      </div>
      <div className="relative px-5 py-3">
        <Search className="pointer-events-none absolute top-1/2 left-8 size-4 -translate-y-1/2 text-foreground-muted" />
        <Input
          value={q}
          onChange={(e) => setQ(e.target.value)}
          placeholder={p("desk.search_ph")}
          className="pl-9"
          aria-label={p("desk.search")}
        />
      </div>
      <ul className="max-h-[32rem] overflow-y-auto border-t border-border">
        {shown.length === 0 ? (
          <li className="px-5 py-6 text-center text-sm text-foreground-muted">
            {p("desk.no_order")}
          </li>
        ) : (
          shown.map((o) => (
            <li key={o.po_no}>
              <button
                type="button"
                onClick={() => onPick(o.po_no)}
                className={cn(
                  "w-full border-b border-border px-5 py-3 text-left hover:bg-surface-muted",
                  current === o.po_no && "bg-brand-subtle",
                )}
              >
                <span className="block text-sm font-medium text-foreground">
                  {[o.style || o.order, o.fabric, o.colour]
                    .filter(Boolean)
                    .join(" · ")}
                </span>
                <span className="mt-0.5 block text-xs text-foreground-muted tabular-nums">
                  {p("desk.line", {
                    req: qtyFmt.format(o.req_qty),
                    kp: qtyFmt.format(o.kp_balance),
                    kc: qtyFmt.format(o.kc_balance),
                  })}
                </span>
              </button>
            </li>
          ))
        )}
      </ul>
    </section>
  );
}

/**
 * The Knit Programmes and Knitcard desks: `mode` is "kp" or "kc". Edits in
 * the grid are held until Save (or thrown away with Revert).
 */
function Desk({
  mode,
  options,
  orders: initialOrders,
  initialPo,
  initial,
  canManage,
  act,
}) {
  const p = usePr();
  const toastManager = useToastManager();
  const kp = mode === "kp";
  const path = kp ? "programs" : "cards";
  const id = kp ? "kp_no" : "kc_no";
  const [orders, setOrders] = useState(initialOrders);
  const [po, setPo] = useState(initialPo);
  const [data, setData] = useState(initial);
  const [rows, setRows] = useState(initial?.rows ?? []);
  const [dialog, setDialog] = useState(false);
  const [confirm, setConfirm] = useState(null);
  const [error, setError] = useState(null);
  const [pending, startTransition] = useTransition();
  const [today] = useState(todayIso);
  const blankForm = () =>
    kp
      ? { program_date: today, qty: "", floor_id: "", remarks: "" }
      : {
          card_date: today,
          kp_no: "",
          assigned_qty: "",
          colour_name: str(data?.defaults?.colour_name),
          machine_dia: "",
          finish_dia: str(data?.defaults?.finish_dia),
          gauge: "",
          floor_id: "",
          yarn_details: "",
        };
  const [form, setForm] = useState(blankForm);
  const dirty = JSON.stringify(rows) !== JSON.stringify(data?.rows ?? []);
  const setRow = (no, patch) =>
    setRows((all) => all.map((r) => (r[id] === no ? { ...r, ...patch } : r)));

  function take(next) {
    setData(next);
    setRows(next.rows);
    setOrders((all) =>
      all.map((o) => (o.po_no === next.order.po_no ? next.order : o)),
    );
  }
  function pick(no) {
    setPo(no);
    window.history.replaceState(
      null,
      "",
      `?tab=${kp ? "kp" : "knitcard"}&order=${encodeURIComponent(no)}`,
    );
    startTransition(async () => {
      const res = await act(`orders/${encodeURIComponent(no)}/${path}`);
      if (res?.status !== "success") return setError(messagesOf(res));
      take(res.data);
      setError(null);
    });
  }
  function send(method, body, then) {
    startTransition(async () => {
      const res = await act(
        `orders/${encodeURIComponent(po)}/${path}`,
        method,
        body,
      );
      if (res?.status !== "success") return setError(messagesOf(res));
      take(res.data);
      setError(null);
      then?.();
    });
  }
  function release() {
    const body = kp
      ? {
          ...form,
          qty: orNull(form.qty),
          floor_id: form.floor_id || null,
          remarks: form.remarks || null,
        }
      : {
          ...Object.fromEntries(
            Object.entries(form).map(([k, v]) => [k, v === "" ? null : v]),
          ),
          assigned_qty: orNull(form.assigned_qty),
        };
    send("POST", body, () => {
      setDialog(false);
      toastManager.add({
        title: p(kp ? "desk.released" : "desk.cut"),
        type: "success",
      });
    });
  }
  function saveRows() {
    const keys = kp
      ? ["kp_no", "program_date", "qty", "status", "remarks"]
      : [
          "kc_no",
          "card_date",
          "assigned_qty",
          "status",
          "colour_name",
          "machine_dia",
          "finish_dia",
          "gauge",
          "yarn_details",
        ];
    send(
      "PUT",
      {
        rows: rows.map((r) =>
          Object.fromEntries(
            keys.map((k) => [
              k,
              ["qty", "assigned_qty"].includes(k)
                ? orNull(r[k])
                : r[k] === ""
                  ? null
                  : r[k],
            ]),
          ),
        ),
      },
      () => toastManager.add({ title: p("desk.saved"), type: "success" }),
    );
  }
  function remove(no) {
    startTransition(async () => {
      const res = await act(`${path}/${encodeURIComponent(no)}`, "DELETE");
      setConfirm(null);
      if (res?.status !== "success") return setError(messagesOf(res));
      take(res.data);
    });
  }
  const statusOpts = options.line_statuses.map((s) => ({
    value: s,
    label: p(`line_status.${s}`),
  }));
  const order = orders.find((o) => o.po_no === po);
  const cols = kp
    ? [
        "kp_no",
        "date",
        "qty",
        "cards",
        "carded",
        "status_edit",
        "remarks",
        "status",
        "action",
      ]
    : [
        "kc_no",
        "date",
        "colour",
        "machine_dia",
        "finish_dia",
        "gauge",
        "assigned",
        "rolls",
        "rolled",
        "machine",
        "status_edit",
        "yarn_details",
        "status",
        "action",
      ];

  return (
    <div className="grid gap-4 xl:grid-cols-[22rem_1fr]">
      <OrderPicker orders={orders} current={po} onPick={pick} />
      <section className={cn(card, "min-w-0")}>
        <div className="flex flex-wrap items-start justify-between gap-3 px-5 pt-5">
          <div>
            <h2 className="flex items-center gap-2 text-base font-semibold text-foreground">
              <ListTree className="size-4" />{" "}
              {p(kp ? "desk.programmes" : "desk.cards")}
            </h2>
            <p className="mt-0.5 text-[13px] text-foreground-muted">
              {p(kp ? "desk.kp_hint" : "desk.kc_hint")}
            </p>
          </div>
          {po ? (
            <div className="flex flex-wrap gap-2">
              <Button
                variant="outline"
                size="sm"
                onClick={() =>
                  csv(
                    kp ? "knit-programmes" : "knit-cards",
                    cols
                      .filter((c) => !["action", "status_edit"].includes(c))
                      .map((c) => p(`desk.lc.${c}`)),
                    rows.map((r) =>
                      kp
                        ? [
                            r.kp_no,
                            r.program_date,
                            r.qty,
                            r.cards,
                            r.carded,
                            r.remarks,
                            p(`line_status.${r.status}`),
                          ]
                        : [
                            r.kc_no,
                            r.card_date,
                            r.colour_name,
                            r.machine_dia,
                            r.finish_dia,
                            r.gauge,
                            r.assigned_qty,
                            r.rolls,
                            r.rolled,
                            r.machine,
                            r.yarn_details,
                            p(`line_status.${r.status}`),
                          ],
                    ),
                  )
                }
              >
                <Download /> {p("export")}
              </Button>
              {canManage ? (
                <>
                  <Button
                    size="sm"
                    onClick={() => (setForm(blankForm()), setDialog(true))}
                    disabled={order?.status !== "RUNNING"}
                  >
                    {kp ? <Send /> : <Scissors />}{" "}
                    {p(kp ? "desk.release" : "desk.cut_btn")}
                  </Button>
                  <Button
                    variant="outline"
                    size="sm"
                    onClick={() => setRows(data.rows)}
                    disabled={!dirty || pending}
                  >
                    <RotateCcw /> {p("desk.revert")}
                  </Button>
                  <Button
                    variant="outline"
                    size="sm"
                    onClick={saveRows}
                    disabled={!dirty}
                    loading={pending}
                  >
                    <Save /> {p("save")}
                  </Button>
                </>
              ) : null}
            </div>
          ) : null}
        </div>
        {error ? (
          <div className="px-5 pt-3">
            <Alert variant="danger">{error.join(" · ")}</Alert>
          </div>
        ) : null}
        {!po ? (
          <NoLines
            title={p("desk.pick")}
            hint={p(kp ? "desk.pick_kp" : "desk.pick_kc")}
          />
        ) : rows.length === 0 ? (
          <NoLines
            title={p(kp ? "desk.no_kp" : "desk.no_kc", {
              order: [
                order?.style || order?.order,
                order?.fabric,
                order?.colour,
              ]
                .filter(Boolean)
                .join(" · "),
            })}
            hint={p(kp ? "desk.no_kp_hint" : "desk.no_kc_hint")}
          />
        ) : (
          <div className="mt-4 overflow-x-auto border-t border-border">
            <table
              className={cn(
                "w-full text-sm",
                kp ? "min-w-[1000px]" : "min-w-[1600px]",
              )}
            >
              <thead className="bg-brand text-brand-foreground">
                <tr>
                  {cols.map((c) => (
                    <th
                      key={c}
                      className={cn(
                        th,
                        [
                          "qty",
                          "cards",
                          "carded",
                          "assigned",
                          "rolls",
                          "rolled",
                        ].includes(c) && "text-right",
                      )}
                    >
                      {p(`desk.lc.${c}`)}
                    </th>
                  ))}
                </tr>
              </thead>
              <tbody>
                {rows.map((r, i) => {
                  const ro = !canManage;
                  const date = kp ? "program_date" : "card_date";
                  const qty = kp ? "qty" : "assigned_qty";
                  const txt = (k, w = "w-24") => (
                    <td className="px-2 py-1.5">
                      <Input
                        value={str(r[k])}
                        onChange={(e) => setRow(r[id], { [k]: e.target.value })}
                        className={cn("h-8", w)}
                        disabled={ro}
                        aria-label={`${p(`desk.lc.${k === "colour_name" ? "colour" : k}`)} ${i + 1}`}
                      />
                    </td>
                  );
                  return (
                    <tr key={r[id]} className="border-t border-border">
                      <td className="px-2 py-2 font-medium whitespace-nowrap">
                        {r[id]}
                      </td>
                      <td className="px-2 py-1.5">
                        <Input
                          type="date"
                          value={str(r[date])}
                          onChange={(e) =>
                            setRow(r[id], { [date]: e.target.value })
                          }
                          className="h-8 w-36"
                          disabled={ro}
                          aria-label={`${p("desk.lc.date")} ${i + 1}`}
                        />
                      </td>
                      {kp ? null : (
                        <>
                          {txt("colour_name", "w-28")}
                          {txt("machine_dia", "w-20")}
                          {txt("finish_dia", "w-20")}
                          {txt("gauge", "w-20")}
                        </>
                      )}
                      <td className="px-2 py-1.5">
                        <Input
                          type="number"
                          min="0"
                          step="any"
                          value={str(r[qty])}
                          onChange={(e) =>
                            setRow(r[id], { [qty]: e.target.value })
                          }
                          className="h-8 w-28 text-right tabular-nums"
                          disabled={ro}
                          aria-label={`${p(kp ? "desk.lc.qty" : "desk.lc.assigned")} ${i + 1}`}
                        />
                      </td>
                      <td className="px-2 py-2 text-right tabular-nums">
                        {kp ? r.cards : r.rolls}
                      </td>
                      <td className="px-2 py-2 text-right tabular-nums">
                        {qtyFmt.format(kp ? r.carded : r.rolled)}
                      </td>
                      {kp ? null : (
                        <td className="px-2 py-2">{r.machine ?? "—"}</td>
                      )}
                      <td className="px-2 py-1.5">
                        <div className="w-36">
                          <PSelect
                            value={r.status}
                            onValueChange={(v) =>
                              setRow(r[id], { status: v ?? r.status })
                            }
                            options={statusOpts}
                            disabled={ro}
                            aria-label={`${p("desk.lc.status_edit")} ${i + 1}`}
                          />
                        </div>
                      </td>
                      {kp
                        ? txt("remarks", "w-48")
                        : txt("yarn_details", "w-56")}
                      <td className="px-2 py-2">
                        <StatusChip
                          status={TONE[r.status]}
                          label={p(`line_status.${r.status}`)}
                        />
                      </td>
                      <td className="px-2 py-1.5">
                        {canManage ? (
                          <Button
                            variant="outline"
                            size="sm"
                            className="text-danger"
                            onClick={() => setConfirm(r[id])}
                            disabled={
                              pending || (kp ? r.cards > 0 : r.rolls > 0)
                            }
                          >
                            <Trash2 /> {p("delete")}
                          </Button>
                        ) : null}
                      </td>
                    </tr>
                  );
                })}
              </tbody>
            </table>
          </div>
        )}
        {po ? (
          <p className="border-t border-border px-5 py-3 text-[13px] text-foreground-muted">
            {p(kp ? "desk.kp_foot" : "desk.kc_foot", { count: rows.length })}
          </p>
        ) : null}
      </section>
      <Modal
        open={dialog}
        onOpenChange={setDialog}
        title={`${p(kp ? "desk.release" : "desk.cut_btn")} — ${[order?.style || order?.order, order?.fabric, order?.colour].filter(Boolean).join(" · ")}`}
        description={p(kp ? "desk.kp_rule" : "desk.kc_rule")}
        className="max-w-2xl"
        footer={
          <div className="flex gap-2">
            <Button
              variant="outline"
              onClick={() => setForm(blankForm())}
              disabled={pending}
            >
              <RotateCcw /> {p("reset")}
            </Button>
            <Button onClick={release} loading={pending}>
              {kp ? <Send /> : <Scissors />}{" "}
              {p(kp ? "desk.release" : "desk.cut_btn")}
            </Button>
          </div>
        }
      >
        {error ? <Alert variant="danger">{error.join(" · ")}</Alert> : null}
        <div className="grid gap-4 sm:grid-cols-3">
          <Field label={p("desk.f.date")} required>
            <Input
              type="date"
              value={kp ? form.program_date : form.card_date}
              onChange={(e) =>
                setForm((x) => ({
                  ...x,
                  [kp ? "program_date" : "card_date"]: e.target.value,
                }))
              }
              aria-label={p("desk.f.date")}
            />
          </Field>
          {kp ? null : (
            <Field label={p("desk.f.program")} required>
              <PSelect
                value={form.kp_no}
                onValueChange={(v) =>
                  setForm((x) => ({ ...x, kp_no: v ?? "" }))
                }
                options={[
                  { value: "", label: "-- Select Programme --" },
                  ...(data?.programs ?? []),
                ]}
                aria-label={p("desk.f.program")}
              />
            </Field>
          )}
          <Field
            label={p(kp ? "desk.f.qty" : "desk.f.assigned")}
            required
            hint={
              kp
                ? p("desk.f.left", {
                    left: qtyFmt.format(order?.kp_balance ?? 0),
                  })
                : form.kp_no
                  ? p("desk.f.left", {
                      left: qtyFmt.format(
                        data?.programs?.find((x) => x.value === form.kp_no)
                          ?.left ?? 0,
                      ),
                    })
                  : undefined
            }
          >
            <Input
              type="number"
              min="0"
              step="any"
              value={kp ? form.qty : form.assigned_qty}
              onChange={(e) =>
                setForm((x) => ({
                  ...x,
                  [kp ? "qty" : "assigned_qty"]: e.target.value,
                }))
              }
              aria-label={p(kp ? "desk.f.qty" : "desk.f.assigned")}
            />
          </Field>
          {kp ? null : (
            <>
              {["colour_name", "machine_dia", "finish_dia", "gauge"].map(
                (k) => (
                  <Field key={k} label={p(`desk.f.${k}`)}>
                    <Input
                      value={form[k]}
                      onChange={(e) =>
                        setForm((x) => ({ ...x, [k]: e.target.value }))
                      }
                      maxLength={k === "colour_name" ? 80 : 24}
                      aria-label={p(`desk.f.${k}`)}
                    />
                  </Field>
                ),
              )}
            </>
          )}
          <Field label={p("desk.f.floor")}>
            <PSelect
              value={form.floor_id}
              onValueChange={(v) =>
                setForm((x) => ({ ...x, floor_id: v ?? "" }))
              }
              options={[
                { value: "", label: "-- Any floor --" },
                ...options.floors,
              ]}
              aria-label={p("desk.f.floor")}
            />
          </Field>
          {kp ? (
            <Field label={p("remarks")} className="sm:col-span-3">
              <Textarea
                rows={3}
                value={form.remarks}
                onChange={(e) =>
                  setForm((x) => ({ ...x, remarks: e.target.value }))
                }
                maxLength={2000}
                aria-label={p("remarks")}
              />
            </Field>
          ) : (
            <Field label={p("desk.f.yarn_details")} className="sm:col-span-2">
              <Input
                value={form.yarn_details}
                onChange={(e) =>
                  setForm((x) => ({ ...x, yarn_details: e.target.value }))
                }
                placeholder="30/1 Combed Cotton · lot A-2261"
                maxLength={255}
                aria-label={p("desk.f.yarn_details")}
              />
            </Field>
          )}
        </div>
      </Modal>
      <ConfirmDialog
        open={Boolean(confirm)}
        onOpenChange={(o) => (o ? null : setConfirm(null))}
        title={p("delete")}
        description={confirm ? p("desk.confirm_delete", { no: confirm }) : ""}
        confirmLabel={p("delete")}
        cancelLabel={p("cancel")}
        destructive
        onConfirm={() => remove(confirm)}
        loading={pending}
      />
    </div>
  );
}

export { usePr, PoRegister, PoEntry, Desk, csv, TONE as PROD_TONE };
