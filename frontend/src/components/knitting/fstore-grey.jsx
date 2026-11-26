"use client";

import { useRef, useState, useTransition } from "react";
import Link from "next/link";
import {
  Ban,
  Check,
  CheckCheck,
  Eraser,
  Lock,
  PackageCheck,
  Plus,
  Printer,
  RotateCcw,
  ScanLine,
  Search,
  Send,
  ShieldCheck,
  Trash2,
  X,
} from "lucide-react";
import { cn } from "@/lib/utils";
import { useT } from "@/lib/i18n";
import { printDocument } from "@/lib/print-document";
import { Input } from "@/components/ui/input";
import { Textarea } from "@/components/ui/textarea";
import { Checkbox } from "@/components/ui/checkbox";
import { Button, buttonVariants } from "@/components/ui/button";
import { Alert } from "@/components/ui/alert";
import { useToastManager } from "@/components/ui/toast";
import {
  PSelect,
  str,
  num,
  qtyFmt,
  moneyFmt,
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
import { ScTable } from "./prod-sc";
import { TONE, pick, named } from "./prod-yd";
import {
  useShowFilters,
  useMoves,
  ListShell,
  MoveButton,
  FormHead,
  FormFoot,
  RemoveCell,
} from "./ystore-yd";

/*
 * Knit Production → Fabric Store (AnnotechERP 15/05), the grey store: Grey
 * Fabric Store Receipt (Fabric Stock / Receive Notes, and the scanning desk
 * where every roll tag is received on its own), Grey Fabric Opening Stock
 * (all lines, the three tabs that finalise, the entry form), Store-to-Store
 * Grey Transfer, Grey Fabric Stock Correction and Grey Fabric Stock Close.
 * A document opened travels by its number; nothing reloads.
 */

const BASE = {
  receipt: "/knit-production/grey-fabric-receive-to-store",
  opening: "/knit-production/grey-fabric-stock-opening",
  adj: "/knit-production/grey-fabric-stock-adjustment",
  tr: "/knit-production/grey-fabric-transfer-store-to-store",
};
const enc = encodeURIComponent;
const numIn = { type: "number", step: "any" };
const QTONE = {
  PENDING: "DRAFT",
  PASSED: "APPROVED",
  HOLD: "WAITING_APPROVAL",
  REJECTED: "REJECTED",
};
let seq = 0;
const lineKey = () => `g${++seq}`;

function useFs() {
  const t = useT("knitting");
  return (k, p) => t(`fs.${k}`, p);
}

const opts = (c, list, group) =>
  list.map((v) => ({ value: v, label: c(`${group}.${v}`) }));
const binsOf = (options, store) =>
  options.bins.filter((b) => !store || b.store_id === store);

/** A radio group's box — a <div>, since Field is a <label> and labels do not nest. */
function RadioField({ label, hint, className, children }) {
  return (
    <div
      role="radiogroup"
      aria-label={label}
      className={cn("flex flex-col gap-1.5", className)}
    >
      <span className="text-[13px] font-semibold text-foreground">{label}</span>
      {children}
      {hint ? (
        <span className="text-xs text-foreground-muted">{hint}</span>
      ) : null}
    </div>
  );
}

/* ---------------- prints ---------------- */

function printNote(c, d) {
  printDocument({
    title: c("p.note"),
    docNo: d.note_no,
    orientation: "landscape",
    meta: [
      [c("f.receive_date"), dayFmt(d.date)],
      [c("f.store"), d.store],
      [c("f.company"), d.company],
      [c("f.status"), { v: c(`note_status.${d.status}`), tone: "info" }],
    ],
    metaColumns: 4,
    sections: [
      {
        title: c("p.rolls"),
        columns: [
          { label: "#", align: "center", width: "4%" },
          { label: c("col.roll"), nowrap: true },
          { label: c("col.prod_type") },
          { label: c("col.buyer_party") },
          { label: c("col.style") },
          { label: c("col.order") },
          { label: c("col.fabric") },
          { label: c("col.colour") },
          { label: c("col.dia"), align: "right" },
          { label: c("col.gsm"), align: "right" },
          { label: c("col.rack") },
          { label: c("col.roll_wt"), align: "right" },
        ],
        rows: [...d.rolls]
          .sort((a, b) =>
            String(a.roll_no).localeCompare(String(b.roll_no), undefined, {
              numeric: true,
            }),
          )
          .map((r, i) => [
            i + 1,
            { v: r.roll_no, bold: true },
            r.prod_type ? c(`prod_type.${r.prod_type}`) : "—",
            r.buyer ?? r.party,
            r.style,
            r.order_no,
            r.fabric,
            r.colour,
            r.finish_dia,
            r.gsm,
            r.rack,
            qtyFmt.format(r.weight),
          ]),
        foot: [
          [
            "",
            { v: `${c("total")} · ${d.rolls.length}`, bold: true },
            ...Array(9).fill(""),
            { v: qtyFmt.format(d.total), bold: true },
          ],
        ],
        empty: c("p.no_lines"),
      },
    ],
    signatures: [c("p.knitting"), c("p.received_by"), c("p.store")],
  });
}

function printTransfer(c, d) {
  const qty = d.lines.reduce((a, l) => a + num(l.qty), 0);
  const rolls = d.lines.reduce((a, l) => a + num(l.rolls), 0);
  printDocument({
    title: c("p.transfer"),
    docNo: d.transfer_no,
    orientation: "landscape",
    meta: [
      [c("f.date"), dayFmt(d.transfer_date)],
      [c("f.trans_type"), c(`trans_type.${d.trans_type}`)],
      [c("f.trans_ref"), d.trans_ref],
      [c("f.status"), { v: c(`transfer_status.${d.status}`), tone: "info" }],
      [c("f.from_store"), d.from_store],
      [c("f.to_store"), d.to_store],
      [c("f.buyer_ac"), d.buyer],
      [c("f.style_order"), d.order],
    ],
    metaColumns: 4,
    sections: [
      {
        title: c("sec.fabric_detail"),
        columns: [
          { label: "#", align: "center", width: "4%" },
          { label: c("col.roll"), nowrap: true },
          { label: c("col.order") },
          { label: c("col.fabric") },
          { label: c("col.colour") },
          { label: c("col.dia"), align: "right" },
          { label: c("col.gsm"), align: "right" },
          { label: c("col.rolls"), align: "right" },
          { label: c("col.qty"), align: "right" },
          { label: c("col.remarks") },
        ],
        rows: d.lines.map((l, i) => [
          i + 1,
          { v: l.roll_no ?? "—", bold: true },
          l.order_no,
          l.fabric,
          l.colour,
          l.finish_dia,
          l.gsm,
          l.rolls || "—",
          qtyFmt.format(l.qty),
          l.remarks,
        ]),
        foot: [
          [
            "",
            { v: c("total"), bold: true },
            "",
            "",
            "",
            "",
            "",
            { v: rolls || "—", bold: true },
            { v: qtyFmt.format(qty), bold: true },
            "",
          ],
        ],
        empty: c("p.no_lines"),
      },
    ],
    notes: [d.remarks],
    signatures: [c("p.sender"), c("p.receiver"), c("p.approved")],
  });
}

function printAdjustment(c, d) {
  const qty = d.lines.reduce((a, l) => a + num(l.qty), 0);
  const value = d.lines.reduce((a, l) => a + num(l.value), 0);
  printDocument({
    title: c("p.adjustment"),
    docNo: d.adj_no,
    orientation: "landscape",
    meta: [
      [c("f.adj_date"), dayFmt(d.adj_date)],
      [c("f.store"), d.store],
      [c("f.company"), d.company],
      [
        c("f.status"),
        {
          v:
            d.activity === "CANCELLED"
              ? c("activity.CANCELLED")
              : c(`adj_status.${d.status}`),
          tone: "info",
        },
      ],
      [c("f.reason"), d.reason],
    ],
    metaColumns: 4,
    sections: [
      {
        title: c("sec.adj_detail"),
        columns: [
          { label: "#", align: "center", width: "4%" },
          { label: c("col.order") },
          { label: c("col.colour") },
          { label: c("col.fabric") },
          { label: c("col.roll"), nowrap: true },
          { label: c("col.part") },
          { label: c("col.adj_qty"), align: "right" },
          { label: c("col.line_rate"), align: "right" },
          { label: c("col.value"), align: "right" },
          { label: c("col.reason") },
        ],
        rows: d.lines.map((l, i) => [
          i + 1,
          l.order,
          l.colour_name,
          { v: l.fabric, bold: true },
          l.roll_no,
          l.part,
          `${qtyFmt.format(l.qty)} ${l.uom}`,
          l.rate === null ? "—" : moneyFmt.format(l.rate),
          moneyFmt.format(l.value),
          l.reason,
        ]),
        foot: [
          [
            "",
            { v: c("total"), bold: true },
            "",
            "",
            "",
            "",
            { v: qtyFmt.format(qty), bold: true },
            "",
            { v: moneyFmt.format(value), bold: true },
            "",
          ],
        ],
        empty: c("p.no_lines"),
      },
    ],
    notes: [d.remarks],
    signatures: [c("p.prepared"), c("p.store"), c("p.approved")],
  });
}

/* ---------------- Grey Fabric Store Receipt: Fabric Stock ---------------- */

function GreyStock({ options, act }) {
  const c = useFs();
  const blank = { type: "stock", store: "", bin: "", date: "", prod_type: "" };
  const [f, setFilters] = useState(blank);
  const [shown, setShown] = useState(null);
  const [error, setError] = useState(null);
  const [pending, startTransition] = useTransition();
  const setF = (k, v) => setFilters((x) => ({ ...x, [k]: v ?? "" }));

  function show(filters = f) {
    startTransition(async () => {
      const q = new URLSearchParams(
        Object.entries(
          filters.type === "stock"
            ? {
                store: filters.store,
                bin: filters.bin,
                date: filters.date,
                prod_type: filters.prod_type,
              }
            : { store: filters.store, date: filters.date },
        ).filter(([, v]) => v),
      ).toString();
      const res = await act(
        `${filters.type === "stock" ? "stock" : "receipts"}${q ? `?${q}` : ""}`,
      );
      if (res?.status !== "success") return setError(messagesOf(res));
      setShown({ type: filters.type, rows: res.data });
      setError(null);
    });
  }
  function printChallan(no) {
    startTransition(async () => {
      const res = await act(`receipts/${enc(no)}`);
      if (res?.status !== "success") return setError(messagesOf(res));
      printNote(c, res.data);
    });
  }
  const stockCols = [
    {
      key: "prod_type",
      label: c("col.prod_type"),
      text: (r) => (r.prod_type ? c(`prod_type.${r.prod_type}`) : "—"),
    },
    {
      key: "buyer",
      label: c("col.buyer_party"),
      text: (r) => r.buyer ?? r.party ?? "—",
    },
    { key: "style", label: c("col.style") },
    { key: "order_no", label: c("col.order") },
    { key: "colour", label: c("col.colour") },
    { key: "roll_no", label: c("col.roll"), nowrap: true },
    { key: "fabric", label: c("col.fabric") },
    { key: "fabric_type", label: c("col.fab_type") },
    { key: "finish_dia", label: c("col.dia") },
    { key: "gsm", label: c("col.gsm"), num: true },
    { key: "store", label: c("col.store") },
    { key: "rack", label: c("col.rack") },
    {
      key: "balance",
      label: c("col.roll_wt"),
      num: true,
      text: (r) => qtyFmt.format(r.balance),
    },
    {
      key: "quality",
      label: c("col.status"),
      render: (r) =>
        r.quality ? (
          <StatusChip
            status={QTONE[r.quality]}
            label={c(`quality.${r.quality}`)}
          />
        ) : (
          "—"
        ),
      text: (r) => (r.quality ? c(`quality.${r.quality}`) : ""),
    },
  ];
  const noteCols = [
    { key: "note_no", label: c("col.note_no"), nowrap: true },
    {
      key: "receive_date",
      label: c("col.date"),
      nowrap: true,
      text: (r) => dayFmt(r.receive_date),
    },
    { key: "store", label: c("col.store") },
    { key: "rolls", label: c("col.rolls"), num: true },
    {
      key: "weight",
      label: c("col.weight"),
      num: true,
      text: (r) => qtyFmt.format(r.weight),
    },
    {
      key: "status",
      label: c("col.status"),
      render: (r) => (
        <StatusChip
          status={r.status === "CONFIRMED" ? "APPROVED" : "DRAFT"}
          label={c(`note_status.${r.status}`)}
        />
      ),
      text: (r) => c(`note_status.${r.status}`),
    },
  ];
  const stock = f.type === "stock";

  return (
    <div className="flex flex-col gap-4">
      <section className={cn(card, "p-5")}>
        <h2 className="text-base font-semibold text-foreground">
          {c("sec.print")}
        </h2>
        <p className="mt-0.5 text-[13px] text-foreground-muted">
          {c("hint.stock")}
        </p>
        <RadioField
          label={c("f.report_type")}
          hint={c("hint.report_type")}
          className="mt-4"
        >
          <div className="flex gap-6 text-sm">
            {[
              ["stock", "f.fabric_stock"],
              ["notes", "f.receive_notes"],
            ].map(([v, label]) => (
              <label key={v} className="flex items-center gap-2">
                <input
                  type="radio"
                  name="report-type"
                  value={v}
                  checked={f.type === v}
                  onChange={() => (setF("type", v), setShown(null))}
                  className="size-4 accent-brand"
                />
                {c(label)}
              </label>
            ))}
          </div>
        </RadioField>
        <div className="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
          <Field label={c("f.store")}>
            <PSelect
              value={f.store}
              onValueChange={(v) => (setF("store", v), setF("bin", ""))}
              options={pick(options.stores, c("ph.all"))}
              aria-label={c("f.store")}
            />
          </Field>
          {stock ? (
            <Field label={c("f.rack")}>
              <PSelect
                value={f.bin}
                onValueChange={(v) => setF("bin", v)}
                options={pick(binsOf(options, f.store), c("ph.rack"))}
                aria-label={c("f.rack")}
              />
            </Field>
          ) : null}
          <Field label={c("f.date")}>
            <Input
              type="date"
              value={f.date}
              onChange={(e) => setF("date", e.target.value)}
              aria-label={c("f.date")}
            />
          </Field>
          {stock ? (
            <Field label={c("f.prod_type")}>
              <PSelect
                value={f.prod_type}
                onValueChange={(v) => setF("prod_type", v)}
                options={pick(
                  opts(c, options.prod_types, "prod_type"),
                  c("ph.all"),
                )}
                aria-label={c("f.prod_type")}
              />
            </Field>
          ) : null}
        </div>
        <div className="mt-4 flex justify-end gap-2">
          <Button
            variant="outline"
            size="sm"
            onClick={() => (setFilters(blank), setShown(null))}
          >
            <RotateCcw /> {c("btn.reset")}
          </Button>
          <Button size="sm" onClick={() => show()} loading={pending}>
            <Search /> {c("btn.show")}
          </Button>
        </div>
      </section>
      {error ? <Alert variant="danger">{error.join(" · ")}</Alert> : null}
      {shown ? (
        <section className={card}>
          <ScTable
            rows={shown.rows}
            columns={shown.type === "stock" ? stockCols : noteCols}
            exportName={
              shown.type === "stock"
                ? "grey-fabric-stock"
                : "grey-receive-notes"
            }
            minWidth={
              shown.type === "stock" ? "min-w-[1300px]" : "min-w-[800px]"
            }
            empty={
              <NoLines
                title={c(
                  shown.type === "stock" ? "empty.stock" : "empty.notes",
                )}
                hint={c("empty.hint")}
              />
            }
            pending={pending}
            actions={
              shown.type === "notes"
                ? (r) => (
                    <Button
                      variant="outline"
                      size="sm"
                      onClick={() => printChallan(r.note_no)}
                      disabled={pending}
                      aria-label={`${c("btn.print_challan")} ${r.note_no}`}
                    >
                      <Printer /> {c("btn.print_challan")}
                    </Button>
                  )
                : undefined
            }
          />
        </section>
      ) : null}
    </div>
  );
}

/* ---------------- Grey Fabric Store Receipt: Fabric Receive ---------------- */

function GreyReceive({ options, initialDay, canManage, act }) {
  const c = useFs();
  const toastManager = useToastManager();
  const store0 =
    options.stores.find((s) => /grey|fab/i.test(s.label))?.value ?? "";
  const [date, setDate] = useState(options.dates[0]);
  const [store, setStore] = useState(initialDay?.store_id ?? store0);
  const [bin, setBin] = useState("");
  const [code, setCode] = useState("");
  const [day, setDay] = useState(initialDay);
  const [ticked, setTicked] = useState([]);
  const [errors, setErrors] = useState(null);
  const [pending, startTransition] = useTransition();
  const box = useRef(null);
  const confirmed = day?.status === "CONFIRMED";
  const rolls = day?.rolls ?? [];

  function load(s, d) {
    startTransition(async () => {
      const res = await act(
        `receipts/day?date=${d}${s ? `&store=${enc(s)}` : ""}`,
      );
      if (res?.status !== "success") return setErrors(messagesOf(res));
      setDay(res.data);
      setTicked([]);
      setErrors(null);
    });
  }
  function scan() {
    if (!store || !bin) return setErrors([c("hint.store_first")]);
    if (!code.trim()) return setErrors([c("v.roll_code")]);
    startTransition(async () => {
      const res = await act("receipts/scan", "POST", {
        receive_date: date,
        roll_code: code.trim(),
        store_id: store,
        bin_id: bin,
      });
      if (res?.status !== "success") return setErrors(messagesOf(res));
      setDay(res.data);
      setCode("");
      setErrors(null);
      toastManager.add({
        title: c("done.scanned", { roll: res.data.scanned }),
        type: "success",
      });
      box.current?.focus();
    });
  }
  function run(path, method, body, done) {
    startTransition(async () => {
      const res = await act(path, method, body);
      if (res?.status !== "success") return setErrors(messagesOf(res));
      setDay(res.data);
      setTicked([]);
      setErrors(null);
      toastManager.add({ title: done, type: "success" });
    });
  }

  return (
    <div className="flex flex-col gap-4">
      <section className={cn(card, "p-5")}>
        <div className="flex flex-wrap items-start justify-between gap-3">
          <div>
            <h2 className="flex items-center gap-2 text-base font-semibold text-foreground">
              <ScanLine className="size-4" /> {c("sec.receive")}
            </h2>
            <p className="mt-0.5 text-[13px] text-foreground-muted">
              {c("hint.receive")}
            </p>
          </div>
          <div className="flex gap-2 text-xs font-medium">
            <span className="rounded-md bg-brand-subtle px-2 py-1 text-brand">
              {c("chip.rolls")} {rolls.length}
            </span>
            <span className="rounded-md bg-brand-subtle px-2 py-1 text-brand">
              {c("chip.weight")} {qtyFmt.format(day?.total ?? 0)}
            </span>
          </div>
        </div>
        <div className="mt-4 grid gap-4 md:grid-cols-[220px_minmax(0,1fr)_160px]">
          <Field label={c("f.receive_date")}>
            <PSelect
              value={date}
              onValueChange={(v) => (
                setDate(v ?? date),
                load(store, v ?? date)
              )}
              options={options.dates.map((d) => ({
                value: d,
                label: dayFmt(d),
              }))}
              aria-label={c("f.receive_date")}
            />
          </Field>
          <Field label={c("f.roll")}>
            <Input
              ref={box}
              value={code}
              onChange={(e) => setCode(e.target.value)}
              onKeyDown={(e) =>
                e.key === "Enter" && (e.preventDefault(), scan())
              }
              placeholder={c("ph.roll")}
              className="font-mono"
              disabled={!canManage || confirmed}
              aria-label={c("f.roll")}
            />
          </Field>
          <div className="flex items-end">
            <Button
              className="w-full"
              onClick={scan}
              loading={pending}
              disabled={!canManage || confirmed || !code.trim()}
            >
              <ScanLine /> {c("btn.receive")}
            </Button>
          </div>
          <Field label={c("f.store")} required>
            <PSelect
              value={store}
              onValueChange={(v) => (
                setStore(v ?? ""),
                setBin(""),
                load(v ?? "", date)
              )}
              options={pick(options.stores, c("ph.store"))}
              aria-label={c("f.store")}
            />
          </Field>
          <Field label={c("f.rack")} hint={c("hint.rack")}>
            <PSelect
              value={bin}
              onValueChange={(v) => setBin(v ?? "")}
              options={pick(binsOf(options, store), c("ph.rack"))}
              disabled={!store}
              aria-label={c("f.rack")}
            />
          </Field>
        </div>
        <p className="mt-3 text-[13px] text-foreground-muted">
          {c("hint.store_first")}
        </p>
        {confirmed ? (
          <Alert variant="info" className="mt-3">
            {c("hint.confirmed", { no: day.note_no })}
          </Alert>
        ) : null}
      </section>
      {errors ? <Alert variant="danger">{errors.join(" · ")}</Alert> : null}
      <section className={card}>
        <p className="px-5 pt-4 text-[13px] font-medium text-foreground-muted">
          {c("rolls_on", { date })}
          {day?.note_no ? ` · ${day.note_no}` : ""}
        </p>
        <div className="mt-3 overflow-x-auto">
          <table className="w-full min-w-[1300px] text-sm">
            <thead className="bg-brand text-brand-foreground">
              <tr>
                <th className={th}>{c("col.select")}</th>
                {[
                  "prod_type",
                  "buyer_party",
                  "style",
                  "order",
                  "colour",
                  "roll",
                  "fab_type",
                  "dia",
                  "gsm",
                  "store",
                  "rack",
                ].map((k) => (
                  <th key={k} className={th}>
                    {c(`col.${k}`)}
                  </th>
                ))}
                <th className={cn(th, "text-right")}>{c("col.roll_wt")}</th>
                <th className={th}>{c("col.status")}</th>
                <th className={th}>{c("col.action")}</th>
              </tr>
            </thead>
            <tbody>
              {rolls.length === 0 ? (
                <tr>
                  <td colSpan={15}>
                    <NoLines
                      title={c("empty.day")}
                      hint={c("empty.day_hint")}
                    />
                  </td>
                </tr>
              ) : (
                rolls.map((r) => (
                  <tr key={r.id} className="border-t border-border">
                    <td className="px-2 py-2">
                      <Checkbox
                        checked={ticked.includes(r.id)}
                        onCheckedChange={(v) =>
                          setTicked((all) =>
                            v ? [...all, r.id] : all.filter((x) => x !== r.id),
                          )
                        }
                        aria-label={`${c("col.select")} ${r.roll_no}`}
                      />
                    </td>
                    <td className="px-2 py-2">
                      {r.prod_type ? c(`prod_type.${r.prod_type}`) : "—"}
                    </td>
                    <td className="px-2 py-2">{r.buyer ?? r.party ?? "—"}</td>
                    <td className="px-2 py-2">{r.style ?? "—"}</td>
                    <td className="px-2 py-2 whitespace-nowrap">
                      {r.order_no ?? "—"}
                    </td>
                    <td className="px-2 py-2">{r.colour ?? "—"}</td>
                    <td className="px-2 py-2 font-mono font-medium whitespace-nowrap">
                      {r.roll_no}
                    </td>
                    <td className="px-2 py-2">{r.fabric_type ?? r.fabric}</td>
                    <td className="px-2 py-2">{r.finish_dia ?? "—"}</td>
                    <td className="px-2 py-2">{r.gsm ?? "—"}</td>
                    <td className="px-2 py-2">{r.store}</td>
                    <td className="px-2 py-2">{r.rack ?? "—"}</td>
                    <td className="px-2 py-2 text-right tabular-nums">
                      {qtyFmt.format(r.weight)}
                    </td>
                    <td className="px-2 py-2">
                      {r.quality ? (
                        <StatusChip
                          status={QTONE[r.quality]}
                          label={c(`quality.${r.quality}`)}
                        />
                      ) : (
                        "—"
                      )}
                    </td>
                    <td className="px-2 py-1.5">
                      {canManage && r.note_status === "DRAFT" ? (
                        <Button
                          variant="ghost"
                          size="sm"
                          className="text-danger"
                          onClick={() =>
                            run(
                              `receipts/rolls/${r.id}`,
                              "DELETE",
                              undefined,
                              c("done.withdrawn"),
                            )
                          }
                          disabled={pending}
                          aria-label={`${c("btn.withdraw")} ${r.roll_no}`}
                        >
                          <Trash2 /> {c("btn.withdraw")}
                        </Button>
                      ) : null}
                    </td>
                  </tr>
                ))
              )}
            </tbody>
          </table>
        </div>
        <div className="flex flex-wrap items-center justify-between gap-3 border-t border-border px-5 py-3">
          <p className="max-w-4xl text-[13px] text-foreground-muted">
            {c("hint.receive_foot", {
              scanned: rolls.length,
              ticked: ticked.length,
            })}
          </p>
          {canManage && day?.note_no && !confirmed ? (
            <div className="flex gap-2">
              <Button
                variant="outline"
                size="sm"
                onClick={() =>
                  run(
                    "receipts/clear",
                    "POST",
                    { store_id: store, receive_date: date },
                    c("done.cleared"),
                  )
                }
                disabled={pending}
              >
                <Eraser /> {c("btn.clear_day")}
              </Button>
              <Button
                size="sm"
                onClick={() =>
                  run(
                    `receipts/${enc(day.note_no)}/confirm`,
                    "POST",
                    undefined,
                    c("done.confirmed", { no: day.note_no }),
                  )
                }
                loading={pending}
              >
                <CheckCheck /> {c("btn.confirm_day")}
              </Button>
            </div>
          ) : day?.note_no ? (
            <Button
              variant="outline"
              size="sm"
              onClick={() => printNote(c, day)}
            >
              <Printer /> {c("btn.print_challan")}
            </Button>
          ) : null}
        </div>
      </section>
    </div>
  );
}

/* ---------------- Grey Fabric Stock Close ---------------- */

function GreyClose({ options, initialRows, canManage, act }) {
  const c = useFs();
  const toastManager = useToastManager();
  const [today] = useState(todayIso);
  const [period, setPeriod] = useState("");
  const [remarks, setRemarks] = useState("");
  const [rows, setRows] = useState(initialRows);
  const [exceptions, setExceptions] = useState(null);
  const [errors, setErrors] = useState(null);
  const [pending, startTransition] = useTransition();
  // Finished months, the last two years.
  const months = Array.from({ length: 24 }, (_, i) => {
    const d = new Date(`${today.slice(0, 7)}-01T00:00:00`);
    d.setMonth(d.getMonth() - i);
    return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, "0")}`;
  }).map((m) => ({ value: m, label: `${m.slice(5)}/${m.slice(0, 4)}` }));

  function call(fn) {
    startTransition(async () => {
      setErrors(null);
      await fn();
    });
  }
  const show = () =>
    call(async () => {
      const res = await act("closes");
      if (res?.status !== "success") return setErrors(messagesOf(res));
      setRows(res.data);
      setExceptions(null);
    });
  const exc = () =>
    call(async () => {
      if (!period) return setErrors([c("v.period")]);
      const res = await act(`closes/exceptions?period=${period}`);
      if (res?.status !== "success") return setErrors(messagesOf(res));
      setExceptions(res.data);
    });
  const close = () =>
    call(async () => {
      if (!period) return setErrors([c("v.period")]);
      const res = await act("closes", "POST", { period, remarks });
      if (res?.status !== "success") return setErrors(messagesOf(res));
      const list = await act("closes");
      if (list?.status === "success") setRows(list.data);
      setExceptions(null);
      toastManager.add({
        title: c("done.closed", { period }),
        type: "success",
      });
    });

  return (
    <div className="flex flex-col gap-4">
      <section className={cn(card, "p-5")}>
        <h2 className="text-base font-semibold text-foreground">
          {c("sec.parameters")}
        </h2>
        <p className="mt-0.5 max-w-3xl text-[13px] text-foreground-muted">
          {c("hint.close")}
        </p>
        <div className="mt-4 grid gap-4 md:grid-cols-[220px_minmax(0,1fr)]">
          <Field label={c("f.month")} hint={c("hint.month")}>
            <PSelect
              value={period}
              onValueChange={(v) => (setPeriod(v ?? ""), setExceptions(null))}
              options={pick(months, c("ph.month"))}
              aria-label={c("f.month")}
            />
          </Field>
          <Field label={c("f.remarks")}>
            <Input
              value={remarks}
              onChange={(e) => setRemarks(e.target.value)}
              maxLength={255}
              aria-label={c("f.remarks")}
            />
          </Field>
        </div>
        <div className="mt-4 flex flex-wrap justify-end gap-2">
          <Button
            variant="outline"
            size="sm"
            onClick={() => (
              setPeriod(""),
              setRemarks(""),
              setExceptions(null),
              setErrors(null)
            )}
          >
            <RotateCcw /> {c("btn.reset")}
          </Button>
          <Button variant="outline" size="sm" onClick={show} disabled={pending}>
            <Search /> {c("btn.show")}
          </Button>
          <Button variant="outline" size="sm" onClick={exc} disabled={pending}>
            <ShieldCheck /> {c("btn.show_exception")}
          </Button>
          {canManage ? (
            <Button size="sm" onClick={close} loading={pending}>
              <Lock /> {c("btn.month_close")}
            </Button>
          ) : null}
        </div>
      </section>
      {errors ? <Alert variant="danger">{errors.join(" · ")}</Alert> : null}
      {exceptions ? (
        <section className={cn(card, "p-5")}>
          <h3 className="text-base font-semibold text-foreground">
            {c("sec.exceptions")} · {exceptions.period}
          </h3>
          {exceptions.exceptions.length === 0 ? (
            <Alert variant="success" className="mt-3">
              {c("hint.no_exceptions", { period: exceptions.period })}
            </Alert>
          ) : (
            <div className="mt-3 overflow-x-auto rounded-lg border border-border">
              <table className="w-full text-sm">
                <thead className="bg-brand text-brand-foreground">
                  <tr>
                    <th className={th}>{c("col.exception")}</th>
                    <th className={th}>{c("col.doc_no")}</th>
                    <th className={th}>{c("col.date")}</th>
                  </tr>
                </thead>
                <tbody>
                  {exceptions.exceptions.map((e) => (
                    <tr key={e.no} className="border-t border-border">
                      <td className="px-2 py-2">{c(`exception.${e.kind}`)}</td>
                      <td className="px-2 py-2 font-medium">{e.no}</td>
                      <td className="px-2 py-2">{dayFmt(e.date)}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}
        </section>
      ) : null}
      <section className={cn(card, "p-5")}>
        <h3 className="text-base font-semibold text-foreground">
          {c("sec.closed")}
        </h3>
        <div className="mt-3 overflow-x-auto rounded-lg border border-border">
          {rows.length === 0 ? (
            <NoLines title={c("empty.closed")} hint={c("empty.hint")} />
          ) : (
            <table className="w-full text-sm">
              <thead className="bg-brand text-brand-foreground">
                <tr>
                  <th className={th}>{c("col.period")}</th>
                  <th className={cn(th, "text-right")}>{c("col.carried")}</th>
                  <th className={th}>{c("col.remarks")}</th>
                  <th className={th}>{c("col.closed_at")}</th>
                  <th className={th}>{c("col.closed_by")}</th>
                </tr>
              </thead>
              <tbody>
                {rows.map((r) => (
                  <tr key={r.period} className="border-t border-border">
                    <td className="px-2 py-2 font-medium">{r.period}</td>
                    <td className="px-2 py-2 text-right tabular-nums">
                      {qtyFmt.format(r.qty)}
                    </td>
                    <td className="px-2 py-2">{r.remarks ?? "—"}</td>
                    <td className="px-2 py-2 whitespace-nowrap">
                      {dayFmt(r.closed_at.slice(0, 10))}
                    </td>
                    <td className="px-2 py-2">{r.by ?? "—"}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          )}
        </div>
      </section>
    </div>
  );
}

/* ---------------- Grey Fabric Opening Stock ---------------- */

function OpeningList({ kind, options, initialRows, canManage, act }) {
  const c = useFs();
  const toastManager = useToastManager();
  const blank = {
    kind: kind ?? "",
    from: "",
    to: "",
    buyer: "",
    order: "",
    store: "",
    finalized: "",
  };
  const [f, setFilters] = useState(blank);
  const [rows, setRows] = useState(initialRows);
  const [ticked, setTicked] = useState([]);
  const [error, setError] = useState(null);
  const [pending, startTransition] = useTransition();
  const setF = (k, v) => setFilters((x) => ({ ...x, [k]: v ?? "" }));
  const open = rows.filter((r) => !r.finalized);

  function search(filters = f) {
    startTransition(async () => {
      const q = new URLSearchParams(
        Object.entries(filters).filter(([, v]) => v),
      ).toString();
      const res = await act(`openings${q ? `?${q}` : ""}`);
      if (res?.status !== "success") return setError(messagesOf(res));
      setRows(res.data);
      setTicked([]);
      setError(null);
    });
  }
  function finalize() {
    if (!ticked.length) return setError([c("line.tick")]);
    startTransition(async () => {
      const res = await act("openings/finalize", "POST", { nos: ticked });
      if (res?.status !== "success") return setError(messagesOf(res));
      toastManager.add({
        title: c("done.finalized", { count: res.data.finalized.length }),
        type: "success",
      });
      search();
    });
  }
  const columns = [
    ...(kind && canManage
      ? [
          {
            key: "tick",
            label: c("col.select"),
            render: (r) =>
              r.finalized ? null : (
                <Checkbox
                  checked={ticked.includes(r.opening_no)}
                  onCheckedChange={(v) =>
                    setTicked((all) =>
                      v
                        ? [...all, r.opening_no]
                        : all.filter((x) => x !== r.opening_no),
                    )
                  }
                  aria-label={`${c("col.select")} ${r.opening_no}`}
                />
              ),
            text: () => "",
          },
        ]
      : []),
    {
      key: "opening_no",
      label: c("col.opening_no"),
      nowrap: true,
      render: (r) =>
        r.finalized || !canManage ? (
          <span className="font-medium">{r.opening_no}</span>
        ) : (
          <Link
            href={`${BASE.opening}?tab=entry&ref=${enc(r.opening_no)}`}
            className="font-medium text-brand hover:underline"
          >
            {r.opening_no}
          </Link>
        ),
      text: (r) => r.opening_no,
    },
    ...(kind
      ? []
      : [
          {
            key: "kind",
            label: c("col.kind"),
            text: (r) => c(`kind.${r.kind}`),
          },
        ]),
    {
      key: "opening_date",
      label: c("col.date"),
      nowrap: true,
      text: (r) => dayFmt(r.opening_date),
    },
    { key: "store", label: c("col.store") },
    { key: "buyer", label: c("col.buyer") },
    {
      key: "style",
      label: c("col.job_style"),
      text: (r) =>
        r.order_no ? `${r.style ? `${r.style} · ` : ""}${r.order_no}` : "—",
    },
    { key: "fabric", label: c("col.fabric") },
    { key: "colour_name", label: c("col.colour") },
    { key: "finish_dia", label: c("col.dia") },
    { key: "gsm", label: c("col.gsm"), num: true },
    {
      key: "qty",
      label: c("col.opening"),
      num: true,
      text: (r) => qtyFmt.format(r.qty),
    },
    {
      key: "rate",
      label: c("col.rate"),
      num: true,
      text: (r) => (r.rate === null ? "—" : moneyFmt.format(r.rate)),
    },
    {
      key: "value",
      label: c("col.value"),
      num: true,
      text: (r) => moneyFmt.format(r.value),
    },
    {
      key: "finalized",
      label: c("col.finalized"),
      render: (r) => (
        <StatusChip
          status={r.finalized ? "YES" : "NO"}
          label={c(r.finalized ? "yes" : "no")}
        />
      ),
      text: (r) => c(r.finalized ? "yes" : "no"),
    },
  ];

  return (
    <div className="flex flex-col gap-4">
      <section className={cn(card, "p-5")}>
        <h2 className="text-[13px] font-semibold tracking-wide text-foreground-muted uppercase">
          {kind ? c(`tabs.ob_${kind}`) : c("sec.search")}
        </h2>
        <div className="mt-3 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
          {kind ? null : (
            <Field label={c("f.ob_for")}>
              <PSelect
                value={f.kind}
                onValueChange={(v) => setF("kind", v)}
                options={pick(opts(c, options.kinds, "kind"), c("ph.select"))}
                aria-label={c("f.ob_for")}
              />
            </Field>
          )}
          <Field label={c("f.from")}>
            <Input
              type="date"
              value={f.from}
              onChange={(e) => setF("from", e.target.value)}
              aria-label={c("f.from")}
            />
          </Field>
          <Field label={c("f.to")}>
            <Input
              type="date"
              value={f.to}
              onChange={(e) => setF("to", e.target.value)}
              aria-label={c("f.to")}
            />
          </Field>
          <Field label={c("f.buyer")}>
            <PSelect
              value={f.buyer}
              onValueChange={(v) => setF("buyer", v)}
              options={pick(options.buyers, c("ph.select"))}
              aria-label={c("f.buyer")}
            />
          </Field>
          <Field label={c("f.job_style")}>
            <PSelect
              value={f.order}
              onValueChange={(v) => setF("order", v)}
              options={pick(
                options.orders.filter(
                  (o) => !f.buyer || o.buyer_id === f.buyer,
                ),
                c("ph.select"),
              )}
              aria-label={c("f.job_style")}
            />
          </Field>
          <Field label={c("f.company")}>
            <PSelect
              value={options.company.value}
              options={[options.company]}
              disabled
              aria-label={c("f.company")}
            />
          </Field>
          <Field label={c("f.store")}>
            <PSelect
              value={f.store}
              onValueChange={(v) => setF("store", v)}
              options={pick(options.stores, c("ph.select"))}
              aria-label={c("f.store")}
            />
          </Field>
          <Field label={c("f.finalized")}>
            <PSelect
              value={f.finalized}
              onValueChange={(v) => setF("finalized", v)}
              options={pick(
                [
                  { value: "YES", label: c("yes") },
                  { value: "NO", label: c("no") },
                ],
                c("ph.select"),
              )}
              aria-label={c("f.finalized")}
            />
          </Field>
        </div>
        <div className="mt-4 flex flex-wrap items-center justify-end gap-2">
          <Button
            variant="outline"
            size="sm"
            onClick={() => (setFilters(blank), search(blank))}
          >
            <RotateCcw /> {c("btn.reset")}
          </Button>
          <Button
            variant="outline"
            size="sm"
            onClick={() => search()}
            disabled={pending}
          >
            <Search /> {c("btn.search")}
          </Button>
          {kind && canManage ? (
            <Button
              size="sm"
              onClick={finalize}
              loading={pending}
              disabled={!open.length}
            >
              <CheckCheck /> {c("btn.finalize")}
            </Button>
          ) : null}
        </div>
      </section>
      {error ? <Alert variant="danger">{error.join(" · ")}</Alert> : null}
      <section className={card}>
        <p className="px-5 pt-4 text-[13px] text-foreground-muted">
          {c(kind ? "hint.ob_tab" : "hint.ob_list")}
        </p>
        <ScTable
          rows={rows}
          columns={columns}
          exportName="grey-opening-stock"
          minWidth="min-w-[1300px]"
          addHref={canManage ? `${BASE.opening}?tab=entry` : null}
          empty={<NoLines title={c("empty.openings")} hint={c("empty.hint")} />}
          pending={pending}
        />
      </section>
    </div>
  );
}

const OB = [
  "kind",
  "opening_date",
  "store_id",
  "buyer_id",
  "order_id",
  "fabric",
  "colour_id",
  "colour_name",
  "finish_dia",
  "gsm",
  "qty",
  "rate",
  "remarks",
];

function OpeningEntry({ options, initial, canManage, act }) {
  const c = useFs();
  const toastManager = useToastManager();
  const [today] = useState(todayIso);
  const asForm = (d) => ({
    ...Object.fromEntries(OB.map((k) => [k, str(d?.[k])])),
    kind: str(d?.kind) || "IN_HOUSE",
    opening_date: str(d?.opening_date) || today,
  });
  const [doc, setDoc] = useState(initial);
  const [form, setForm] = useState(() => asForm(initial));
  const [errors, setErrors] = useState(null);
  const [pending, startTransition] = useTransition();
  const set = (patch) => setForm((x) => ({ ...x, ...patch }));

  function reset(d = null) {
    setDoc(d);
    setForm(asForm(d));
    setErrors(null);
    window.history.replaceState(
      null,
      "",
      d ? `?tab=entry&ref=${enc(d.opening_no)}` : "?tab=entry",
    );
  }
  function save() {
    startTransition(async () => {
      const body = {
        ...Object.fromEntries(
          OB.map((k) => [k, form[k] === "" ? null : form[k]]),
        ),
        qty: orNull(form.qty),
        rate: orNull(form.rate),
        gsm: orNull(form.gsm),
      };
      const res = await act(
        doc ? `openings/${enc(doc.opening_no)}` : "openings",
        doc ? "PUT" : "POST",
        body,
      );
      if (res?.status !== "success") return setErrors(messagesOf(res));
      toastManager.add({
        title: c("done.opening", { no: res.data.opening_no }),
        type: "success",
      });
      reset(doc ? { ...res.data, opening_date: form.opening_date } : null);
    });
  }

  return (
    <div className="flex flex-col gap-4">
      <section className={cn(card, "p-5")}>
        <div className="flex items-start justify-between gap-3">
          <div>
            <h2 className="text-base font-semibold text-foreground">
              {doc ? doc.opening_no : c("sec.new_opening")}
            </h2>
            <p className="mt-0.5 max-w-3xl text-[13px] text-foreground-muted">
              {c("hint.ob_entry")}
            </p>
          </div>
          <StatusChip
            status="PLACED"
            label={doc ? doc.opening_no : c("new_badge")}
          />
        </div>
        <RadioField
          label={c("f.ob_for")}
          hint={c("hint.ob_kind")}
          className="mt-4"
        >
          <div className="flex flex-wrap gap-6 text-sm">
            {options.kinds.map((k) => (
              <label key={k} className="flex items-center gap-2">
                <input
                  type="radio"
                  name="ob-kind"
                  value={k}
                  checked={form.kind === k}
                  onChange={() => set({ kind: k })}
                  disabled={!canManage}
                  className="size-4 accent-brand"
                />
                {c(`kind.${k}`)}
              </label>
            ))}
          </div>
        </RadioField>
        <div className="mt-4 grid gap-4 md:grid-cols-3">
          <Field label={c("f.opening_date")} required>
            <Input
              type="date"
              value={form.opening_date}
              onChange={(e) => set({ opening_date: e.target.value })}
              disabled={!canManage}
              aria-label={c("f.opening_date")}
            />
          </Field>
          <Field label={c("f.company")} required>
            <PSelect
              value={options.company.value}
              options={[options.company]}
              disabled
              aria-label={c("f.company")}
            />
          </Field>
          <Field label={c("f.store")} required>
            <PSelect
              value={form.store_id}
              onValueChange={(v) => set({ store_id: v ?? "" })}
              options={pick(options.stores, c("ph.store"))}
              disabled={!canManage}
              aria-label={c("f.store")}
            />
          </Field>
          <Field label={c("f.buyer")}>
            <PSelect
              value={form.buyer_id}
              onValueChange={(v) => set({ buyer_id: v ?? "", order_id: "" })}
              options={pick(options.buyers, c("ph.select"))}
              disabled={!canManage}
              aria-label={c("f.buyer")}
            />
          </Field>
          <Field label={c("f.job_style")}>
            <PSelect
              value={form.order_id}
              onValueChange={(v) =>
                set({
                  order_id: v ?? "",
                  buyer_id:
                    options.orders.find((o) => o.value === v)?.buyer_id ??
                    form.buyer_id,
                })
              }
              options={pick(
                options.orders.filter(
                  (o) => !form.buyer_id || o.buyer_id === form.buyer_id,
                ),
                c("ph.select"),
              )}
              disabled={!canManage}
              aria-label={c("f.job_style")}
            />
          </Field>
          <Field label={c("f.fabric")} required hint={c("hint.fabric")}>
            <Input
              value={form.fabric}
              onChange={(e) => set({ fabric: e.target.value })}
              placeholder={c("ph.fabric")}
              maxLength={160}
              disabled={!canManage}
              aria-label={c("f.fabric")}
            />
          </Field>
          <Field label={c("f.colour")}>
            <PSelect
              value={form.colour_id}
              onValueChange={(v) =>
                set({
                  colour_id: v ?? "",
                  colour_name:
                    options.colours.find((x) => x.value === v)?.name ??
                    form.colour_name,
                })
              }
              options={pick(options.colours, c("ph.colour"))}
              disabled={!canManage}
              aria-label={c("f.colour")}
            />
          </Field>
          <Field label={c("f.colour_written")}>
            <Input
              value={form.colour_name}
              onChange={(e) =>
                set({ colour_name: e.target.value, colour_id: "" })
              }
              placeholder={c("ph.colour_written")}
              maxLength={80}
              disabled={!canManage}
              aria-label={c("f.colour_written")}
            />
          </Field>
          <Field label={c("f.finish_dia")}>
            <Input
              value={form.finish_dia}
              onChange={(e) => set({ finish_dia: e.target.value })}
              maxLength={24}
              disabled={!canManage}
              aria-label={c("f.finish_dia")}
            />
          </Field>
          <Field label={c("f.gsm")}>
            <Input
              {...numIn}
              min="0"
              value={form.gsm}
              onChange={(e) => set({ gsm: e.target.value })}
              disabled={!canManage}
              aria-label={c("f.gsm")}
            />
          </Field>
          <Field label={c("f.opening")} required>
            <Input
              {...numIn}
              min="0"
              value={form.qty}
              onChange={(e) => set({ qty: e.target.value })}
              disabled={!canManage}
              aria-label={c("f.opening")}
            />
          </Field>
          <Field label={c("f.rate")}>
            <Input
              {...numIn}
              min="0"
              value={form.rate}
              onChange={(e) => set({ rate: e.target.value })}
              disabled={!canManage}
              aria-label={c("f.rate")}
            />
          </Field>
        </div>
        <Field label={c("f.remarks")} className="mt-4">
          <Textarea
            rows={2}
            value={form.remarks}
            onChange={(e) => set({ remarks: e.target.value })}
            maxLength={500}
            disabled={!canManage}
            aria-label={c("f.remarks")}
          />
        </Field>
      </section>
      {errors ? <Alert variant="danger">{errors.join(" · ")}</Alert> : null}
      {canManage ? (
        <section className={cn(card, "flex flex-wrap justify-end gap-2 p-4")}>
          <Button variant="outline" onClick={() => reset()} disabled={pending}>
            <X /> {c("btn.cancel")}
          </Button>
          <Button onClick={save} loading={pending}>
            <Check /> {c("btn.save_opening")}
          </Button>
        </section>
      ) : null}
    </div>
  );
}

/* ---------------- Grey Fabric Stock Correction ---------------- */

function AdjList({ options, initialRows, canManage, act }) {
  const c = useFs();
  const [f, setF] = useState({ status: "", from: "", to: "" });
  const [rows, setRows] = useState(initialRows);
  const [error, setError] = useState(null);
  const [move, pending, startTransition] = useMoves(
    c,
    "adjustments",
    "adj_no",
    setRows,
    setError,
    (d) =>
      setRows((all) =>
        all.map((r) =>
          r.adj_no === d.adj_no ? { ...r, activity: d.activity } : r,
        ),
      ),
  );
  const shown = rows.filter(
    (r) =>
      (!f.status || r.status === f.status) &&
      (!f.from || r.adj_date >= f.from) &&
      (!f.to || r.adj_date <= f.to),
  );
  function print(no) {
    startTransition(async () => {
      const res = await act(`adjustments/${enc(no)}`);
      if (res?.status !== "success") return setError(messagesOf(res));
      printAdjustment(c, res.data);
    });
  }
  const columns = [
    {
      key: "activity",
      label: c("col.activity"),
      render: (r) => (
        <StatusChip
          status={r.activity === "ACTIVE" ? "ACTIVE" : "CANCELLED"}
          label={c(`activity.${r.activity}`)}
        />
      ),
      text: (r) => c(`activity.${r.activity}`),
    },
    {
      key: "status",
      label: c("col.status"),
      render: (r) => (
        <StatusChip
          status={TONE[r.status]}
          label={c(`adj_status.${r.status}`)}
        />
      ),
      text: (r) => c(`adj_status.${r.status}`),
    },
    {
      key: "adj_no",
      label: c("col.adj_no"),
      nowrap: true,
      render: (r) => (
        <Link
          href={`${BASE.adj}?tab=entry&ref=${enc(r.adj_no)}`}
          className="font-medium text-brand hover:underline"
        >
          {r.adj_no}
        </Link>
      ),
      text: (r) => r.adj_no,
    },
    {
      key: "adj_date",
      label: c("col.adj_date"),
      nowrap: true,
      text: (r) => dayFmt(r.adj_date),
    },
    { key: "company", label: c("col.company") },
    { key: "store", label: c("col.store") },
    { key: "lines", label: c("col.lines"), num: true },
    {
      key: "qty",
      label: c("col.adj_qty"),
      num: true,
      text: (r) => qtyFmt.format(r.qty),
    },
    {
      key: "value",
      label: c("col.value"),
      num: true,
      text: (r) => moneyFmt.format(r.value),
    },
    { key: "reason", label: c("col.reason") },
  ];
  return (
    <div className="flex flex-col gap-4">
      <section className={cn(card, "p-5")}>
        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
          <Field label={c("f.status")}>
            <PSelect
              value={f.status}
              onValueChange={(v) => setF((x) => ({ ...x, status: v ?? "" }))}
              options={pick(
                opts(c, options.adj_statuses, "adj_status"),
                c("ph.all"),
              )}
              aria-label={c("f.status")}
            />
          </Field>
          <Field label={c("f.company")}>
            <PSelect
              value={options.company.value}
              options={[options.company]}
              disabled
              aria-label={c("f.company")}
            />
          </Field>
          <Field label={c("f.adj_from")}>
            <Input
              type="date"
              value={f.from}
              onChange={(e) => setF((x) => ({ ...x, from: e.target.value }))}
              aria-label={c("f.adj_from")}
            />
          </Field>
          <Field label={c("f.adj_to")}>
            <Input
              type="date"
              value={f.to}
              onChange={(e) => setF((x) => ({ ...x, to: e.target.value }))}
              aria-label={c("f.adj_to")}
            />
          </Field>
        </div>
        <div className="mt-4 flex justify-end">
          <Button
            variant="outline"
            size="sm"
            onClick={() => setF({ status: "", from: "", to: "" })}
          >
            <Eraser /> {c("btn.clear")}
          </Button>
        </div>
      </section>
      {error ? <Alert variant="danger">{error.join(" · ")}</Alert> : null}
      <section className={card}>
        <p className="px-5 pt-4 text-[13px] text-foreground-muted">
          {c("hint.adj_list")}
        </p>
        <ScTable
          rows={shown}
          columns={columns}
          colFilters
          exportName="grey-fabric-adjustments"
          addHref={canManage ? `${BASE.adj}?tab=entry` : null}
          empty={<NoLines title={c("empty.adjs")} hint="" />}
          pending={pending}
          actions={(r) => (
            <>
              {canManage && r.activity === "ACTIVE" && r.status === "DRAFT" ? (
                <MoveButton
                  icon={Check}
                  label={c("btn.verify")}
                  no={r.adj_no}
                  onClick={() => move(r.adj_no, "verify", act)}
                  disabled={pending}
                />
              ) : null}
              {canManage &&
              r.activity === "ACTIVE" &&
              r.status === "VERIFIED" ? (
                <MoveButton
                  icon={CheckCheck}
                  label={c("btn.approve")}
                  no={r.adj_no}
                  onClick={() => move(r.adj_no, "approve", act)}
                  disabled={pending}
                />
              ) : null}
              {canManage && r.activity === "ACTIVE" ? (
                <MoveButton
                  icon={Ban}
                  label={c("btn.cancel_doc")}
                  no={r.adj_no}
                  onClick={() => move(r.adj_no, "cancel", act)}
                  disabled={pending}
                  danger
                />
              ) : null}
              <MoveButton
                icon={Printer}
                label={c("btn.print")}
                no={r.adj_no}
                onClick={() => print(r.adj_no)}
                disabled={pending}
              />
            </>
          )}
        />
      </section>
    </div>
  );
}

const ALINE = [
  "order_id",
  "colour_id",
  "colour_name",
  "fabric",
  "roll_no",
  "part",
  "qty",
  "uom",
  "rate",
  "reason",
];

function AdjEntry({ options, initial, canManage, act }) {
  const c = useFs();
  const toastManager = useToastManager();
  const [today] = useState(todayIso);
  const asHead = (d) => ({
    adj_date: str(d?.adj_date) || today,
    store_id: str(d?.store_id),
    reason: str(d?.reason),
    remarks: str(d?.remarks),
  });
  const asLine = (l = {}) => ({
    key: lineKey(),
    ...Object.fromEntries(ALINE.map((k) => [k, str(l[k])])),
    uom: str(l.uom) || "kg",
  });
  const [doc, setDoc] = useState(initial);
  const [head, setHead] = useState(() => asHead(initial));
  const [lines, setLines] = useState(() => (initial?.lines ?? []).map(asLine));
  const [errors, setErrors] = useState(null);
  const [pending, startTransition] = useTransition();
  const open =
    canManage &&
    (!doc || (doc.status === "DRAFT" && doc.activity === "ACTIVE"));
  const setH = (patch) => setHead((h) => ({ ...h, ...patch }));
  const setL = (k, patch) =>
    setLines((all) => all.map((l) => (l.key === k ? { ...l, ...patch } : l)));
  const sum = lines.reduce((a, l) => a + num(l.qty), 0);
  const value = lines.reduce((a, l) => a + num(l.qty) * num(l.rate), 0);

  function show(d) {
    setDoc(d);
    setHead(asHead(d));
    setLines((d?.lines ?? []).map(asLine));
    setErrors(null);
    window.history.replaceState(
      null,
      "",
      d ? `?tab=entry&ref=${enc(d.adj_no)}` : "?tab=entry",
    );
  }
  function load(no) {
    if (!no) return show(null);
    startTransition(async () => {
      const res = await act(`adjustments/${enc(no)}`);
      if (res?.status !== "success") return setErrors(messagesOf(res));
      show(res.data);
    });
  }
  function save() {
    const miss = [
      lines.some((l) => !l.fabric.trim()) && c("line.need_fabric"),
      lines.some((l) => !num(l.qty)) && c("line.need_adj"),
    ].filter(Boolean);
    if (miss.length) return setErrors(miss);
    startTransition(async () => {
      const body = {
        ...Object.fromEntries(
          Object.entries(head).map(([k, v]) => [k, v === "" ? null : v]),
        ),
        lines: lines.map((l) => ({
          ...Object.fromEntries(
            ALINE.map((k) => [k, l[k] === "" ? null : l[k]]),
          ),
          qty: orNull(l.qty),
          rate: orNull(l.rate),
        })),
      };
      const res = await act(
        doc ? `adjustments/${enc(doc.adj_no)}` : "adjustments",
        doc ? "PUT" : "POST",
        body,
      );
      if (res?.status !== "success") return setErrors(messagesOf(res));
      show(res.data);
      toastManager.add({
        title: c("done.saved", { no: res.data.adj_no }),
        type: "success",
      });
    });
  }
  const statusOf = (d) => (d.activity === "CANCELLED" ? "CANCELLED" : d.status);

  return (
    <div className="flex flex-col gap-4">
      <section className={cn(card, "p-5")}>
        <FormHead
          c={c}
          title={doc ? doc.adj_no : c("tabs.adj_entry")}
          hint={c("hint.adj_form")}
          doc={doc}
          status={doc ? statusOf(doc) : null}
          group={doc?.activity === "CANCELLED" ? "activity" : "adj_status"}
        />
        {doc && !open && canManage ? (
          <Alert variant="info" className="mt-3">
            {c("hint.locked", {
              no: doc.adj_no,
              status:
                doc.activity === "CANCELLED"
                  ? c("activity.CANCELLED")
                  : c(`adj_status.${doc.status}`),
            })}
          </Alert>
        ) : null}
        <Field
          label={c("f.existing_adj")}
          hint={c("hint.existing")}
          className="mt-4"
        >
          <PSelect
            value={doc?.adj_no ?? ""}
            onValueChange={(v) => load(v ?? "")}
            options={pick(options.adjustments ?? [], c("ph.new_adj"))}
            disabled={pending}
            aria-label={c("f.existing_adj")}
          />
        </Field>
        <h3 className="mt-5 text-sm font-semibold text-foreground">
          {c("sec.adj_info")}
        </h3>
        <div className="mt-3 grid gap-4 md:grid-cols-3">
          <Field label={c("f.adj_no")} hint={c("hint.adj_no")}>
            <Input
              value={doc?.adj_no ?? ""}
              placeholder={c("ph.auto")}
              disabled
              aria-label={c("f.adj_no")}
            />
          </Field>
          <Field label={c("f.adj_date")} required>
            <Input
              type="date"
              value={head.adj_date}
              onChange={(e) => setH({ adj_date: e.target.value })}
              disabled={!open}
              aria-label={c("f.adj_date")}
            />
          </Field>
          <Field label={c("f.company")} required>
            <PSelect
              value={options.company.value}
              options={[options.company]}
              disabled
              aria-label={c("f.company")}
            />
          </Field>
          <Field label={c("f.store")} required>
            <PSelect
              value={head.store_id}
              onValueChange={(v) => setH({ store_id: v ?? "" })}
              options={pick(options.stores, c("ph.store"))}
              disabled={!open}
              aria-label={c("f.store")}
            />
          </Field>
        </div>
        <h3 className="mt-5 text-sm font-semibold text-foreground">
          {c("sec.why")}
        </h3>
        <Field label={c("f.reason")} className="mt-3">
          <Input
            value={head.reason}
            onChange={(e) => setH({ reason: e.target.value })}
            placeholder={c("ph.reason")}
            maxLength={255}
            disabled={!open}
            aria-label={c("f.reason")}
          />
        </Field>
        <Field label={c("f.remarks")} className="mt-4">
          <Textarea
            rows={2}
            value={head.remarks}
            onChange={(e) => setH({ remarks: e.target.value })}
            maxLength={2000}
            disabled={!open}
            aria-label={c("f.remarks")}
          />
        </Field>
      </section>
      <section className={cn(card, "p-5")}>
        <div className="flex flex-wrap items-start justify-between gap-3">
          <div>
            <h3 className="text-base font-semibold text-foreground">
              {c("sec.adj_detail")}
            </h3>
            <p className="mt-0.5 max-w-3xl text-[13px] text-foreground-muted">
              {c("hint.adj_lines")}
            </p>
          </div>
          {open ? (
            <Button
              variant="outline"
              size="sm"
              onClick={() => setLines((all) => [...all, asLine()])}
            >
              <Plus /> {c("btn.add_line")}
            </Button>
          ) : null}
        </div>
        <div className="mt-4 overflow-x-auto rounded-lg border border-border">
          {lines.length === 0 ? (
            <NoLines title={c("empty.lines")} hint={c("empty.adj_lines")} />
          ) : (
            <table className="w-full min-w-[1400px] text-sm">
              <thead className="bg-brand text-brand-foreground">
                <tr>
                  {[
                    ["order"],
                    ["colour"],
                    ["fabric"],
                    ["roll"],
                    ["part"],
                    ["adj_qty", true],
                    ["uom"],
                    ["line_rate", true],
                    ["value", true],
                    ["reason"],
                  ].map(([k, right]) => (
                    <th key={k} className={cn(th, right && "text-right")}>
                      {c(`col.${k}`)}
                    </th>
                  ))}
                  <th className={th} />
                </tr>
              </thead>
              <tbody>
                {lines.map((l, i) => (
                  <tr key={l.key} className="border-t border-border">
                    <td className="min-w-[220px] px-2 py-1.5">
                      <PSelect
                        value={l.order_id}
                        onValueChange={(v) =>
                          setL(l.key, { order_id: v ?? "" })
                        }
                        options={pick(options.orders, "—")}
                        disabled={!open}
                        aria-label={`${c("f.order")} ${i + 1}`}
                      />
                    </td>
                    <td className="min-w-[170px] px-2 py-1.5">
                      <Input
                        value={l.colour_name}
                        onChange={(e) =>
                          setL(l.key, {
                            colour_name: e.target.value,
                            colour_id: "",
                          })
                        }
                        list="fs-colours"
                        maxLength={80}
                        disabled={!open}
                        aria-label={`${c("f.colour")} ${i + 1}`}
                      />
                    </td>
                    <td className="min-w-[220px] px-2 py-1.5">
                      <Input
                        value={l.fabric}
                        onChange={(e) =>
                          setL(l.key, { fabric: e.target.value })
                        }
                        maxLength={160}
                        disabled={!open}
                        aria-label={`${c("f.fabric")} ${i + 1}`}
                      />
                    </td>
                    <td className="px-2 py-1.5">
                      <Input
                        value={l.roll_no}
                        onChange={(e) =>
                          setL(l.key, { roll_no: e.target.value })
                        }
                        maxLength={40}
                        disabled={!open}
                        className="w-32"
                        aria-label={`${c("f.roll")} ${i + 1}`}
                      />
                    </td>
                    <td className="px-2 py-1.5">
                      <Input
                        value={l.part}
                        onChange={(e) => setL(l.key, { part: e.target.value })}
                        maxLength={40}
                        disabled={!open}
                        className="w-24"
                        aria-label={`${c("f.part")} ${i + 1}`}
                      />
                    </td>
                    <td className="px-2 py-1.5">
                      <Input
                        {...numIn}
                        value={l.qty}
                        onChange={(e) => setL(l.key, { qty: e.target.value })}
                        disabled={!open}
                        className="w-28 text-right"
                        aria-label={`${c("f.adj_qty")} ${i + 1}`}
                      />
                    </td>
                    <td className="px-2 py-1.5">
                      <PSelect
                        value={l.uom}
                        onValueChange={(v) => setL(l.key, { uom: v ?? "" })}
                        options={options.uoms}
                        disabled={!open}
                        aria-label={`${c("f.uom")} ${i + 1}`}
                      />
                    </td>
                    <td className="px-2 py-1.5">
                      <Input
                        {...numIn}
                        min="0"
                        value={l.rate}
                        onChange={(e) => setL(l.key, { rate: e.target.value })}
                        disabled={!open}
                        className="w-24 text-right"
                        aria-label={`${c("f.line_rate")} ${i + 1}`}
                      />
                    </td>
                    <td className="px-2 py-1.5 text-right tabular-nums">
                      {moneyFmt.format(num(l.qty) * num(l.rate))}
                    </td>
                    <td className="min-w-[160px] px-2 py-1.5">
                      <Input
                        value={l.reason}
                        onChange={(e) =>
                          setL(l.key, { reason: e.target.value })
                        }
                        maxLength={255}
                        disabled={!open}
                        aria-label={`${c("f.reason")} ${i + 1}`}
                      />
                    </td>
                    <RemoveCell
                      c={c}
                      n={i + 1}
                      open={open}
                      onClick={() =>
                        setLines((all) => all.filter((x) => x.key !== l.key))
                      }
                    />
                  </tr>
                ))}
              </tbody>
              <tfoot>
                <tr className="border-t border-border font-semibold">
                  <td className="px-2 py-2" colSpan={5}>
                    {c("total")}
                  </td>
                  <td className="px-2 py-2 text-right tabular-nums">
                    {qtyFmt.format(sum)}
                  </td>
                  <td colSpan={2} />
                  <td className="px-2 py-2 text-right tabular-nums">
                    {moneyFmt.format(value)}
                  </td>
                  <td colSpan={2} />
                </tr>
              </tfoot>
            </table>
          )}
          <datalist id="fs-colours">
            {options.colours.map((x) => (
              <option key={x.value} value={x.name} />
            ))}
          </datalist>
        </div>
      </section>
      {errors ? <Alert variant="danger">{errors.join(" · ")}</Alert> : null}
      <FormFoot
        c={c}
        hint={c("hint.adj_foot")}
        back={BASE.adj}
        onClear={() => show(null)}
        onPrint={doc ? () => printAdjustment(c, doc) : null}
        onSave={save}
        saveLabel={c("btn.save_adj")}
        pending={pending}
        open={open}
      />
    </div>
  );
}

/* ---------------- Store-to-Store Grey Transfer ---------------- */

function TrList({ options, initialRows, canManage, act }) {
  const c = useFs();
  const flt = useShowFilters({
    buyer: "",
    order: "",
    ref: "",
    status: "",
    from: "",
    to: "",
  });
  const [rows, setRows] = useState(initialRows);
  const [error, setError] = useState(null);
  const [move, pending, startTransition] = useMoves(
    c,
    "transfers",
    "transfer_no",
    setRows,
    setError,
  );
  const o = flt.on;
  const shown = rows.filter(
    (r) =>
      (!o.buyer || r.buyer_id === o.buyer) &&
      (!o.order || r.order_id === o.order) &&
      (!o.ref ||
        `${r.trans_ref ?? ""} ${r.transfer_no}`
          .toLowerCase()
          .includes(o.ref.toLowerCase())) &&
      (!o.status || r.status === o.status) &&
      (!o.from || r.transfer_date >= o.from) &&
      (!o.to || r.transfer_date <= o.to),
  );
  function print(no) {
    startTransition(async () => {
      const res = await act(`transfers/${enc(no)}`);
      if (res?.status !== "success") return setError(messagesOf(res));
      printTransfer(c, res.data);
    });
  }
  const columns = [
    {
      key: "transfer_no",
      label: c("col.tr_no"),
      nowrap: true,
      render: (r) => (
        <span className="flex flex-col items-start gap-1">
          <Link
            href={`${BASE.tr}?tab=entry&ref=${enc(r.transfer_no)}`}
            className="font-medium text-brand hover:underline"
          >
            {r.transfer_no}
          </Link>
          <StatusChip
            status={TONE[r.status]}
            label={c(`transfer_status.${r.status}`)}
          />
        </span>
      ),
      text: (r) => r.transfer_no,
    },
    {
      key: "transfer_date",
      label: c("col.date"),
      nowrap: true,
      text: (r) => dayFmt(r.transfer_date),
    },
    {
      key: "trans_type",
      label: c("col.trans_type"),
      text: (r) => c(`trans_type.${r.trans_type}`),
    },
    { key: "trans_ref", label: c("col.trans_ref") },
    { key: "buyer", label: c("col.buyer") },
    { key: "order", label: c("col.style_order") },
    { key: "from_store", label: c("col.from_store") },
    { key: "to_store", label: c("col.to_store") },
    { key: "rolls", label: c("col.rolls"), num: true },
    {
      key: "qty",
      label: c("col.qty"),
      num: true,
      text: (r) => qtyFmt.format(r.qty),
    },
  ];
  return (
    <ListShell
      c={c}
      error={error}
      onShow={flt.show}
      onReset={flt.reset}
      extra={
        canManage ? (
          <Link
            href={`${BASE.tr}?tab=entry`}
            className={buttonVariants({ variant: "outline", size: "sm" })}
          >
            <Plus /> {c("btn.new")}
          </Link>
        ) : null
      }
      filters={
        <>
          <Field label={c("f.buyer_ac")}>
            <PSelect
              value={flt.f.buyer}
              onValueChange={(v) => flt.set("buyer", v)}
              options={pick(options.buyers, c("ph.select"))}
              aria-label={c("f.buyer_ac")}
            />
          </Field>
          <Field label={c("f.style_order")}>
            <PSelect
              value={flt.f.order}
              onValueChange={(v) => flt.set("order", v)}
              options={pick(options.orders, c("ph.select"))}
              aria-label={c("f.style_order")}
            />
          </Field>
          <Field label={c("f.trans_ref")}>
            <Input
              value={flt.f.ref}
              onChange={(e) => flt.set("ref", e.target.value)}
              placeholder={c("ph.search")}
              aria-label={c("f.trans_ref")}
            />
          </Field>
          <Field label={c("f.status")}>
            <PSelect
              value={flt.f.status}
              onValueChange={(v) => flt.set("status", v)}
              options={pick(
                opts(c, options.transfer_statuses, "transfer_status"),
                c("ph.select"),
              )}
              aria-label={c("f.status")}
            />
          </Field>
          <Field label={c("f.from")}>
            <Input
              type="date"
              value={flt.f.from}
              onChange={(e) => flt.set("from", e.target.value)}
              aria-label={c("f.from")}
            />
          </Field>
          <Field label={c("f.to")}>
            <Input
              type="date"
              value={flt.f.to}
              onChange={(e) => flt.set("to", e.target.value)}
              aria-label={c("f.to")}
            />
          </Field>
        </>
      }
    >
      <p className="px-5 pt-4 text-[13px] text-foreground-muted">
        {c("hint.tr_list")}
      </p>
      <ScTable
        rows={shown}
        columns={columns}
        exportName="grey-store-transfers"
        empty={<NoLines title={c("empty.trs")} hint={c("empty.hint")} />}
        pending={pending}
        actions={(r) => (
          <>
            {canManage && r.status === "DRAFT" ? (
              <MoveButton
                icon={Send}
                label={c("btn.submit")}
                no={r.transfer_no}
                onClick={() => move(r.transfer_no, "submit", act)}
                disabled={pending}
              />
            ) : null}
            {canManage && r.status === "SUBMITTED" ? (
              <MoveButton
                icon={PackageCheck}
                label={c("btn.receive_tr")}
                no={r.transfer_no}
                onClick={() => move(r.transfer_no, "receive", act)}
                disabled={pending}
              />
            ) : null}
            {canManage && ["DRAFT", "SUBMITTED"].includes(r.status) ? (
              <MoveButton
                icon={Ban}
                label={c("btn.cancel_doc")}
                no={r.transfer_no}
                onClick={() => move(r.transfer_no, "cancel", act)}
                disabled={pending}
                danger
              />
            ) : null}
            <MoveButton
              icon={Printer}
              label={c("btn.print")}
              no={r.transfer_no}
              onClick={() => print(r.transfer_no)}
              disabled={pending}
            />
          </>
        )}
      />
    </ListShell>
  );
}

const TRHEAD = [
  "transfer_date",
  "trans_type",
  "trans_ref",
  "buyer_id",
  "order_id",
  "from_store_id",
  "to_store_id",
  "remarks",
];

function TrEntry({ options, initial, initialLots, canManage, act }) {
  const c = useFs();
  const toastManager = useToastManager();
  const [today] = useState(todayIso);
  const asHead = (d) => ({
    ...Object.fromEntries(TRHEAD.map((k) => [k, str(d?.[k])])),
    transfer_date: str(d?.transfer_date) || today,
  });
  const asLine = (l = {}) => ({
    key: lineKey(),
    lot_id: str(l.lot_id),
    roll_no: str(l.roll_no),
    qty: str(l.qty),
    remarks: str(l.remarks),
    info: l,
  });
  const [doc, setDoc] = useState(initial);
  const [head, setHead] = useState(() => asHead(initial));
  const [lines, setLines] = useState(() => (initial?.lines ?? []).map(asLine));
  const [lots, setLots] = useState(initialLots ?? []);
  const [draft, setDraft] = useState(() => asLine());
  const [errors, setErrors] = useState(null);
  const [pending, startTransition] = useTransition();
  const open = canManage && (!doc || doc.status === "DRAFT");
  const picked = lots.find((l) => l.lot_id === draft.lot_id);
  const total = lines.reduce((a, l) => a + num(l.qty), 0);
  const rolls = lines.reduce((a, l) => a + (l.info.roll_id ? 1 : 0), 0);
  const lotOptions = lots
    .filter((l) => !lines.some((x) => x.lot_id === l.lot_id && l.roll_id))
    .map((l) => ({
      value: l.lot_id,
      label: `${l.label} · ${qtyFmt.format(l.balance)} kg`,
    }));

  function fetchLots(store, order) {
    if (!store) return setLots([]);
    startTransition(async () => {
      const res = await act(
        `lots?store=${enc(store)}${order ? `&order=${enc(order)}` : ""}`,
      );
      if (res?.status === "success") setLots(res.data);
    });
  }
  function setH(patch) {
    const next = { ...head, ...patch };
    setHead(next);
    if ("from_store_id" in patch || "order_id" in patch) {
      fetchLots(next.from_store_id, next.order_id);
      if ("from_store_id" in patch) setLines([]);
    }
  }
  function show(d) {
    setDoc(d);
    setHead(asHead(d));
    setLines((d?.lines ?? []).map(asLine));
    setDraft(asLine());
    setErrors(null);
    if (d) fetchLots(d.from_store_id, d.order_id);
    window.history.replaceState(
      null,
      "",
      d ? `?tab=entry&ref=${enc(d.transfer_no)}` : "?tab=entry",
    );
  }
  function load(no) {
    if (!no) return show(null);
    startTransition(async () => {
      const res = await act(`transfers/${enc(no)}`);
      if (res?.status !== "success") return setErrors(messagesOf(res));
      show(res.data);
    });
  }
  function addLine() {
    const qty = picked?.roll_id ? picked.balance : num(draft.qty);
    const miss = [
      !picked && c("line.need_lot"),
      picked && !(qty > 0) && c("line.need_qty"),
      picked &&
        qty > picked.balance + 0.00005 &&
        c("line.over", { have: picked.balance }),
    ].filter(Boolean);
    if (miss.length) return setErrors(miss);
    setLines((all) => [...all, { ...draft, qty: String(qty), info: picked }]);
    setDraft(asLine());
    setErrors(null);
  }
  function save() {
    startTransition(async () => {
      const body = {
        ...Object.fromEntries(
          TRHEAD.map((k) => [k, head[k] === "" ? null : head[k]]),
        ),
        lines: lines.map((l) => ({
          lot_id: l.lot_id,
          roll_no: l.roll_no || null,
          qty: orNull(l.qty),
          remarks: l.remarks || null,
        })),
      };
      const res = await act(
        doc ? `transfers/${enc(doc.transfer_no)}` : "transfers",
        doc ? "PUT" : "POST",
        body,
      );
      if (res?.status !== "success") return setErrors(messagesOf(res));
      show(res.data);
      toastManager.add({
        title: c("done.saved", { no: res.data.transfer_no }),
        type: "success",
      });
    });
  }

  return (
    <div className="flex flex-col gap-4">
      <section className={cn(card, "p-5")}>
        <FormHead
          c={c}
          title={doc ? doc.transfer_no : c("tabs.tr_entry")}
          hint={c("hint.tr_form")}
          doc={doc}
          status={doc?.status}
          group="transfer_status"
        />
        {doc && doc.status !== "DRAFT" && canManage ? (
          <Alert variant="info" className="mt-3">
            {c("hint.locked", {
              no: doc.transfer_no,
              status: c(`transfer_status.${doc.status}`),
            })}
          </Alert>
        ) : null}
        <Field
          label={c("f.existing_tr")}
          hint={c("hint.existing")}
          className="mt-4"
        >
          <PSelect
            value={doc?.transfer_no ?? ""}
            onValueChange={(v) => load(v ?? "")}
            options={pick(options.transfers ?? [], c("ph.new_tr"))}
            disabled={pending}
            aria-label={c("f.existing_tr")}
          />
        </Field>
        <fieldset className="mt-4 rounded-lg border border-border p-4">
          <legend className="px-1 text-sm font-semibold">
            {c("sec.transfer")}
          </legend>
          <div className="grid gap-4 md:grid-cols-3">
            <Field label={c("f.date")} required>
              <Input
                type="date"
                value={head.transfer_date}
                onChange={(e) => setH({ transfer_date: e.target.value })}
                disabled={!open}
                aria-label={c("f.date")}
              />
            </Field>
            <Field label={c("f.company")} required>
              <PSelect
                value={options.company.value}
                options={[options.company]}
                disabled
                aria-label={c("f.company")}
              />
            </Field>
            <Field
              label={c("f.trans_type")}
              required
              hint={c("hint.trans_type")}
            >
              <PSelect
                value={head.trans_type}
                onValueChange={(v) => setH({ trans_type: v ?? "" })}
                options={pick(
                  opts(c, options.trans_types, "trans_type"),
                  c("ph.type"),
                )}
                disabled={!open}
                aria-label={c("f.trans_type")}
              />
            </Field>
            <Field label={c("f.trans_ref")} hint={c("hint.trans_ref")}>
              <Input
                value={head.trans_ref}
                onChange={(e) => setH({ trans_ref: e.target.value })}
                maxLength={40}
                disabled={!open}
                aria-label={c("f.trans_ref")}
              />
            </Field>
          </div>
        </fieldset>
        <fieldset className="mt-4 rounded-lg border border-border p-4">
          <legend className="px-1 text-sm font-semibold">
            {c("sec.from_to")}
          </legend>
          <div className="grid gap-4 md:grid-cols-3">
            <Field label={c("f.buyer_ac")}>
              <PSelect
                value={head.buyer_id}
                onValueChange={(v) => setH({ buyer_id: v ?? "", order_id: "" })}
                options={pick(options.buyers, c("ph.select"))}
                disabled={!open}
                aria-label={c("f.buyer_ac")}
              />
            </Field>
            <Field label={c("f.from_store")} required>
              <PSelect
                value={head.from_store_id}
                onValueChange={(v) => setH({ from_store_id: v ?? "" })}
                options={pick(options.stores, c("ph.from_store"))}
                disabled={!open}
                aria-label={c("f.from_store")}
              />
            </Field>
            <Field label={c("f.to_store")} required>
              <PSelect
                value={head.to_store_id}
                onValueChange={(v) => setH({ to_store_id: v ?? "" })}
                options={pick(
                  options.stores.filter((s) => s.value !== head.from_store_id),
                  c("ph.to_store"),
                )}
                disabled={!open}
                aria-label={c("f.to_store")}
              />
            </Field>
            <Field label={c("f.style_order")}>
              <PSelect
                value={head.order_id}
                onValueChange={(v) =>
                  setH({
                    order_id: v ?? "",
                    buyer_id:
                      options.orders.find((x) => x.value === v)?.buyer_id ??
                      head.buyer_id,
                  })
                }
                options={pick(
                  options.orders.filter(
                    (x) => !head.buyer_id || x.buyer_id === head.buyer_id,
                  ),
                  c("ph.select"),
                )}
                disabled={!open}
                aria-label={c("f.style_order")}
              />
            </Field>
          </div>
        </fieldset>
        <Field label={c("f.remarks")} className="mt-4">
          <Textarea
            rows={2}
            value={head.remarks}
            onChange={(e) => setH({ remarks: e.target.value })}
            maxLength={2000}
            disabled={!open}
            aria-label={c("f.remarks")}
          />
        </Field>
      </section>
      <section className={cn(card, "p-5")}>
        <div className="flex flex-wrap items-start justify-between gap-3">
          <div>
            <h3 className="text-base font-semibold text-foreground">
              {c("sec.fabric_detail")}
            </h3>
            <p className="mt-0.5 max-w-3xl text-[13px] text-foreground-muted">
              {c("hint.tr_lines")}
            </p>
          </div>
          {open ? (
            <div className="flex gap-2">
              <Button variant="outline" size="sm" onClick={addLine}>
                <Plus /> {c("btn.add_fabric")}
              </Button>
              <Button
                variant="outline"
                size="sm"
                onClick={() => setDraft(asLine())}
              >
                <X /> {c("btn.cancel")}
              </Button>
            </div>
          ) : null}
        </div>
        {open ? (
          <div className="mt-4 grid gap-4 md:grid-cols-4">
            <Field label={c("f.roll_pick")} className="md:col-span-2">
              <PSelect
                value={draft.lot_id}
                onValueChange={(v) => {
                  const l = lots.find((x) => x.lot_id === v);
                  setDraft((d) => ({
                    ...d,
                    lot_id: v ?? "",
                    qty: l?.roll_id ? String(l.balance) : d.qty,
                    roll_no: l?.roll_id ? "" : d.roll_no,
                  }));
                }}
                options={pick(lotOptions, c("ph.lot"))}
                disabled={!head.from_store_id}
                aria-label={c("f.roll_pick")}
              />
            </Field>
            <Field label={c("f.roll_no")}>
              <Input
                value={picked?.roll_id ? picked.roll_no : draft.roll_no}
                onChange={(e) =>
                  setDraft((d) => ({ ...d, roll_no: e.target.value }))
                }
                maxLength={40}
                disabled={Boolean(picked?.roll_id)}
                aria-label={c("f.roll_no")}
              />
            </Field>
            <Field label={c("f.fabric")}>
              <Input
                value={picked?.fabric ?? ""}
                disabled
                aria-label={c("f.fabric")}
              />
            </Field>
            <Field label={c("f.colour_written")}>
              <Input
                value={picked?.colour ?? ""}
                disabled
                aria-label={c("f.colour_written")}
              />
            </Field>
            <Field label={c("f.finish_dia")}>
              <Input
                value={picked?.finish_dia ?? ""}
                disabled
                aria-label={c("f.finish_dia")}
              />
            </Field>
            <Field label={c("f.gsm")}>
              <Input
                value={picked?.gsm ?? ""}
                disabled
                aria-label={c("f.gsm")}
              />
            </Field>
            <Field label={c("f.rolls")}>
              <Input
                value={picked?.roll_id ? "1" : "0"}
                disabled
                aria-label={c("f.rolls")}
              />
            </Field>
            <Field
              label={c("f.qty")}
              hint={picked?.roll_id ? c("hint.roll_whole") : undefined}
            >
              <Input
                {...numIn}
                min="0"
                value={draft.qty}
                onChange={(e) =>
                  setDraft((d) => ({ ...d, qty: e.target.value }))
                }
                disabled={Boolean(picked?.roll_id)}
                aria-label={c("f.qty")}
              />
            </Field>
            <Field label={c("f.remarks")} className="md:col-span-3">
              <Input
                value={draft.remarks}
                onChange={(e) =>
                  setDraft((d) => ({ ...d, remarks: e.target.value }))
                }
                maxLength={300}
                aria-label={`${c("f.remarks")} (${c("f.roll_pick")})`}
              />
            </Field>
          </div>
        ) : null}
        <div className="mt-5 overflow-x-auto rounded-lg border border-border">
          {lines.length === 0 ? (
            <NoLines title={c("empty.lines")} hint={c("empty.tr_lines")} />
          ) : (
            <table className="w-full min-w-[1100px] text-sm">
              <thead className="bg-brand text-brand-foreground">
                <tr>
                  {[
                    ["roll"],
                    ["order"],
                    ["fabric"],
                    ["colour"],
                    ["dia"],
                    ["gsm"],
                    ["rolls", true],
                    ["qty", true],
                    ["remarks"],
                  ].map(([k, right]) => (
                    <th key={k} className={cn(th, right && "text-right")}>
                      {c(`col.${k}`)}
                    </th>
                  ))}
                  <th className={th} />
                </tr>
              </thead>
              <tbody>
                {lines.map((l, i) => (
                  <tr key={l.key} className="border-t border-border">
                    <td className="px-2 py-2 font-mono font-medium whitespace-nowrap">
                      {(l.info.roll_id ? l.info.roll_no : l.roll_no) || "—"}
                    </td>
                    <td className="px-2 py-2 whitespace-nowrap">
                      {l.info.order_no ?? "—"}
                    </td>
                    <td className="px-2 py-2">{l.info.fabric}</td>
                    <td className="px-2 py-2">{l.info.colour ?? "—"}</td>
                    <td className="px-2 py-2">{l.info.finish_dia ?? "—"}</td>
                    <td className="px-2 py-2">{l.info.gsm ?? "—"}</td>
                    <td className="px-2 py-2 text-right tabular-nums">
                      {l.info.roll_id ? 1 : "—"}
                    </td>
                    <td className="px-2 py-2 text-right tabular-nums">
                      {qtyFmt.format(num(l.qty))}
                    </td>
                    <td className="px-2 py-2">{l.remarks || "—"}</td>
                    <RemoveCell
                      c={c}
                      n={i + 1}
                      open={open}
                      onClick={() =>
                        setLines((all) => all.filter((x) => x.key !== l.key))
                      }
                    />
                  </tr>
                ))}
              </tbody>
              <tfoot>
                <tr className="border-t border-border font-semibold">
                  <td className="px-2 py-2" colSpan={6}>
                    {c("total")}
                  </td>
                  <td className="px-2 py-2 text-right tabular-nums">
                    {rolls || "—"}
                  </td>
                  <td className="px-2 py-2 text-right tabular-nums">
                    {qtyFmt.format(total)}
                  </td>
                  <td colSpan={2} />
                </tr>
              </tfoot>
            </table>
          )}
        </div>
      </section>
      {errors ? <Alert variant="danger">{errors.join(" · ")}</Alert> : null}
      <FormFoot
        c={c}
        hint={c("hint.tr_foot")}
        back={BASE.tr}
        onClear={() => show(null)}
        onPrint={doc ? () => printTransfer(c, doc) : null}
        onSave={save}
        saveLabel={c("btn.save_tr")}
        pending={pending}
        open={open}
      />
    </div>
  );
}

export {
  useFs,
  GreyStock,
  GreyReceive,
  GreyClose,
  OpeningList,
  OpeningEntry,
  AdjList,
  AdjEntry,
  TrList,
  TrEntry,
  named,
};
