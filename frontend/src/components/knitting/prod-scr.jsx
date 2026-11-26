"use client";

import { useEffect, useState, useTransition } from "react";
import Link from "next/link";
import {
  ArrowLeft,
  Ban,
  Check,
  Eraser,
  Lock,
  Play,
  Plus,
  Printer,
  RotateCcw,
  Save,
  Search,
  Trash2,
} from "lucide-react";
import { cn } from "@/lib/utils";
import { printDocument } from "@/lib/print-document";
import { Input } from "@/components/ui/input";
import { Textarea } from "@/components/ui/textarea";
import { Checkbox } from "@/components/ui/checkbox";
import { Button, buttonVariants } from "@/components/ui/button";
import { Alert } from "@/components/ui/alert";
import { ConfirmDialog } from "@/components/ui/confirm-dialog";
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
import { useSc, ScTable } from "./prod-sc";

/*
 * Knit Production → Production (AnnotechERP 15/04) → Sub-Contract
 * Programme Receipt: the Sub-contract Program List (Program Search above,
 * then Confirm, Start Production, Close, Cancel and Print by status) and the
 * Knitting Sub-contract Program form — Key Information, the colour-wise
 * fabric lines (each a Fab. Ord. SL; blank takes the next free one) and the
 * yarn the party's store issues against a serial, picked from what the
 * chosen Yarn Store holds. Nothing reloads.
 */

const BASE = "/knit-production/receive-sc-program-from-party";
const TONE = {
  DRAFT: "DRAFT",
  CONFIRMED: "APPROVED",
  IN_PRODUCTION: "PLACED",
  CLOSED: "CLOSED",
  CANCELLED: "CANCELLED",
};
const NEXT = {
  DRAFT: ["confirm", "cancel"],
  CONFIRMED: ["start", "close", "cancel"],
  IN_PRODUCTION: ["close", "cancel"],
};
const ICON = { confirm: Check, start: Play, close: Lock, cancel: Ban };

/** "70.00 Inch (Open)" */
const finDia = (c, f) =>
  f.finish_dia === null || f.finish_dia === undefined || f.finish_dia === ""
    ? null
    : `${Number(f.finish_dia).toFixed(2)}${f.dia_unit ? ` ${c(`dia_unit.${f.dia_unit}`)}` : ""}${f.width_type ? ` (${c(`width_type.${f.width_type}`)})` : ""}`;
const colourOf = (f) =>
  [f.colour, f.colour_name].filter(Boolean).join(" · ") || null;

function printReceipt(c, d) {
  printDocument({
    title: c("p.receipt_title"),
    docNo: d.program_no,
    orientation: "landscape",
    meta: [
      [c("f.prog_date"), dayFmt(d.prog_date)],
      [c("f.party"), d.party],
      [c("p.address"), d.party_address],
      [c("f.yarn_store"), d.yarn_store],
      [c("f.company"), d.company],
      [c("f.status"), { v: c(`receipt_status.${d.status}`), tone: "info" }],
      [c("p.fabric_lines"), d.fabrics.length],
      [c("p.total_qty"), `${qtyFmt.format(d.qty)} Kg`],
    ],
    metaColumns: 4,
    sections: [
      {
        title: c("sec.colour_fabric"),
        columns: [
          { label: c("col.sl"), align: "center", width: "4%" },
          { label: c("col.order_ref") },
          { label: c("col.colour") },
          { label: c("col.fab_type") },
          { label: c("col.composition") },
          { label: c("col.fin_dia") },
          { label: c("col.fgsm"), align: "right" },
          { label: c("f.qty"), align: "right" },
          { label: c("p.yd_type") },
          { label: c("f.feeder"), align: "right" },
          { label: c("p.mc_gg") },
          { label: c("col.rate"), align: "right" },
          { label: c("p.start") },
          { label: c("p.end") },
        ],
        rows: d.fabrics.map((f) => [
          f.sl,
          {
            v: f.order ?? f.party_ref ?? "—",
            sub: f.order && f.party_ref ? f.party_ref : undefined,
          },
          colourOf(f),
          f.fabric_type,
          f.composition,
          finDia(c, f),
          f.gsm === null ? null : Number(f.gsm).toFixed(2),
          { v: `${qtyFmt.format(f.qty)} ${f.uom}`, bold: true },
          f.yd_type ? c(`yd_type.${f.yd_type}`) : null,
          f.feeder,
          [f.mc_dia, f.gauge].filter(Boolean).join(" x ") || null,
          f.rate === null
            ? null
            : `${moneyFmt.format(f.rate)}${f.rate_type ? ` ${c(`rate_type.${f.rate_type}`)}` : ""}`,
          f.start_date ? dayFmt(f.start_date) : null,
          f.end_date ? dayFmt(f.end_date) : null,
        ]),
        foot: [
          [
            "",
            { v: c("p.total"), bold: true },
            "",
            "",
            "",
            "",
            "",
            { v: qtyFmt.format(d.qty), bold: true },
            "",
            "",
            "",
            "",
            "",
            "",
          ],
        ],
        empty: c("p.no_lines"),
      },
      {
        title: c("sec.yarn"),
        columns: [
          { label: c("col.sl"), align: "center", width: "4%" },
          { label: c("f.yarn") },
          { label: c("f.fab_part") },
          { label: c("f.col_grp") },
          { label: c("f.brand") },
          { label: c("f.lot") },
          { label: c("f.s_len"), align: "right" },
          { label: c("f.ela_pct"), align: "right" },
          { label: c("f.ratio"), align: "right" },
          { label: c("f.rqd"), align: "right" },
        ],
        rows: d.yarns.map((y) => [
          y.sl,
          { v: y.yarn, sub: y.remarks || undefined },
          y.fab_part,
          y.colour_group,
          y.brand,
          y.lot_no,
          y.stitch_length,
          y.elastane_pct,
          y.ratio_qty,
          y.required_qty === null ? null : qtyFmt.format(y.required_qty),
        ]),
        foot: d.yarns.length
          ? [
              [
                "",
                { v: c("p.total"), bold: true },
                "",
                "",
                "",
                "",
                "",
                "",
                "",
                { v: qtyFmt.format(d.yarn_qty), bold: true },
              ],
            ]
          : [],
        empty: c("p.no_yarn"),
      },
    ],
    notes: d.remarks ? [d.remarks] : [],
    signatures: [c("p.sign.prepared"), c("p.sign.store_rc"), c("p.sign.party")],
  });
}

function ScrList({ options, initialRows, canManage, act }) {
  const c = useSc();
  const toastManager = useToastManager();
  const blank = { party: "", store: "", status: "", from: "", to: "" };
  const [f, setFilters] = useState(blank);
  const [rows, setRows] = useState(initialRows);
  const [error, setError] = useState(null);
  const [ask, setAsk] = useState(null);
  const [pending, startTransition] = useTransition();
  const setF = (k, v) => setFilters((x) => ({ ...x, [k]: v ?? "" }));
  const pick = (list, ph) => [{ value: "", label: ph }, ...list];

  function search(next = f) {
    startTransition(async () => {
      const q = new URLSearchParams(Object.entries(next).filter(([, v]) => v));
      const res = await act(`sc-receipts?${q}`);
      if (res?.status !== "success") return setError(messagesOf(res));
      setRows(res.data);
      setError(null);
    });
  }
  function run() {
    const { row, action } = ask;
    startTransition(async () => {
      const res = await act(
        `sc-receipts/${encodeURIComponent(row.program_no)}/${action}`,
        "POST",
      );
      setAsk(null);
      if (res?.status !== "success") return setError(messagesOf(res));
      setRows((all) =>
        all.map((x) =>
          x.program_no === row.program_no
            ? { ...x, status: res.data.status }
            : x,
        ),
      );
      setError(null);
      toastManager.add({
        title: c(`receipt_done.${action}`, { no: row.program_no }),
        type: "success",
      });
    });
  }
  function print(r) {
    startTransition(async () => {
      const res = await act(`sc-receipts/${encodeURIComponent(r.program_no)}`);
      if (res?.status !== "success") return setError(messagesOf(res));
      printReceipt(c, res.data);
    });
  }
  const columns = [
    {
      key: "program_no",
      label: c("col.program_no"),
      nowrap: true,
      render: (r) => (
        <Link
          href={`${BASE}?tab=entry&ref=${encodeURIComponent(r.program_no)}`}
          className="font-medium text-brand hover:underline"
        >
          {r.program_no}
        </Link>
      ),
    },
    {
      key: "prog_date",
      label: c("col.prog_date"),
      nowrap: true,
      text: (r) => dayFmt(r.prog_date),
    },
    { key: "party", label: c("col.party") },
    { key: "yarn_store", label: c("col.yarn_store") },
    { key: "company", label: c("col.company") },
    { key: "fabrics", label: c("col.fabrics"), num: true },
    {
      key: "qty",
      label: c("col.qty"),
      num: true,
      text: (r) => qtyFmt.format(r.qty),
    },
    {
      key: "yarn_qty",
      label: c("col.yarn_qty"),
      num: true,
      text: (r) => qtyFmt.format(r.yarn_qty),
    },
    { key: "remarks", label: c("col.remarks") },
    {
      key: "status",
      label: c("col.status"),
      text: (r) => c(`receipt_status.${r.status}`),
      render: (r) => (
        <StatusChip
          status={TONE[r.status]}
          label={c(`receipt_status.${r.status}`)}
        />
      ),
    },
  ];

  return (
    <div className="flex flex-col gap-4">
      <section className={cn(card, "p-5")}>
        <h2 className="flex items-center gap-2 text-base font-semibold text-foreground">
          <Search className="size-4" /> {c("rc_search")}
        </h2>
        <p className="mt-0.5 mb-4 text-[13px] text-foreground-muted">
          {c("hint.rc_search")}
        </p>
        <div className="grid gap-4 md:grid-cols-3">
          <Field label={c("f.party")}>
            <PSelect
              value={f.party}
              onValueChange={(v) => setF("party", v)}
              options={pick(options.parties, c("ph.select"))}
              aria-label={c("f.party")}
            />
          </Field>
          <Field label={c("f.yarn_store")}>
            <PSelect
              value={f.store}
              onValueChange={(v) => setF("store", v)}
              options={pick(options.stores, c("ph.select"))}
              aria-label={c("f.yarn_store")}
            />
          </Field>
          <Field label={c("f.status")}>
            <PSelect
              value={f.status}
              onValueChange={(v) => setF("status", v)}
              options={pick(
                options.receipt_statuses.map((s) => ({
                  value: s,
                  label: c(`receipt_status.${s}`),
                })),
                c("ph.all"),
              )}
              aria-label={c("f.status")}
            />
          </Field>
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
        </div>
        <div className="mt-4 flex justify-between gap-2 border-t border-border pt-4">
          <div className="flex gap-2">
            <Button onClick={() => search()} loading={pending}>
              <Search /> {c("btn.search")}
            </Button>
            {canManage ? (
              <Link
                href={`${BASE}?tab=entry`}
                className={buttonVariants({ variant: "outline" })}
              >
                <Plus /> {c("btn.add_new")}
              </Link>
            ) : null}
          </div>
          <Button
            variant="outline"
            onClick={() => (setFilters(blank), search(blank))}
            disabled={pending}
          >
            <RotateCcw /> {c("btn.reset")}
          </Button>
        </div>
      </section>
      {error ? <Alert variant="danger">{error.join(" · ")}</Alert> : null}
      <section className={card}>
        <div className="flex flex-wrap gap-2 border-b border-border px-5 py-4">
          {[
            [c("chip.programmes"), rows.length],
            [
              c("chip.fabric"),
              qtyFmt.format(rows.reduce((a, r) => a + num(r.qty), 0)),
            ],
          ].map(([label, v]) => (
            <span
              key={label}
              className="inline-flex items-center gap-2 rounded-lg border border-border px-3 py-1.5 text-[13px]"
            >
              {label}
              <span className="rounded-full bg-brand-subtle px-2 text-xs font-semibold text-brand tabular-nums">
                {v}
              </span>
            </span>
          ))}
        </div>
        <ScTable
          rows={rows}
          columns={columns}
          colFilters
          exportName="sc-programme-receipts"
          addHref={canManage ? `${BASE}?tab=entry` : null}
          empty={
            <NoLines
              title={c("empty.register")}
              hint={c("empty.register_hint")}
            />
          }
          onRefresh={() => search()}
          pending={pending}
          actions={(r) => (
            <>
              {canManage
                ? (NEXT[r.status] ?? []).map((a) => {
                    const Icon = ICON[a];
                    return (
                      <Button
                        key={a}
                        variant="outline"
                        size="sm"
                        className={a === "cancel" ? "text-danger" : undefined}
                        onClick={() => setAsk({ row: r, action: a })}
                        disabled={pending}
                        aria-label={`${c(`btn.${a}`)} ${r.program_no}`}
                      >
                        <Icon /> {c(`btn.${a}`)}
                      </Button>
                    );
                  })
                : null}
              <Button
                variant="outline"
                size="sm"
                onClick={() => print(r)}
                disabled={pending}
                aria-label={`${c("btn.print")} ${r.program_no}`}
              >
                <Printer /> {c("btn.print")}
              </Button>
            </>
          )}
        />
      </section>
      <ConfirmDialog
        open={Boolean(ask)}
        onOpenChange={(o) => (o ? null : setAsk(null))}
        title={ask ? c(`ask.${ask.action}.title`) : ""}
        description={ask ? c(`ask.${ask.action}.body`) : ""}
        confirmLabel={ask ? c(`btn.${ask.action}`) : ""}
        cancelLabel={c("ask.no")}
        destructive={ask?.action === "cancel"}
        loading={pending}
        onConfirm={run}
      />
    </div>
  );
}

const FAB = [
  "sl",
  "order_id",
  "party_ref",
  "fabric_type_id",
  "colour_id",
  "colour_name",
  "composition_id",
  "gsm",
  "qty",
  "uom",
  "finish_dia",
  "dia_unit",
  "width_type",
  "yd_type",
  "feeder",
  "mc_dia_id",
  "gauge_id",
  "rate_type",
  "rate",
  "start_date",
  "end_date",
  "remarks",
];
const YARN = [
  "sl",
  "composition_id",
  "yarn_count_id",
  "fab_part",
  "colour_group",
  "brand",
  "lot_no",
  "stitch_length",
  "elastane_pct",
  "ratio_qty",
  "required_qty",
  "remarks",
];
const FAB_NUM = ["sl", "gsm", "qty", "finish_dia", "feeder", "rate"];
const YARN_NUM = [
  "sl",
  "stitch_length",
  "elastane_pct",
  "ratio_qty",
  "required_qty",
];
let seq = 0;
const fabLine = (f = {}) => ({
  key: `f${++seq}`,
  ...Object.fromEntries(FAB.map((k) => [k, str(f[k])])),
  dia_unit: str(f.dia_unit ?? "INCH"),
  width_type: str(f.width_type ?? "OPEN"),
  yd_type: str(f.yd_type ?? "SOLID"),
  rate_type: str(f.rate_type ?? "PER_KG"),
});
const yarnLine = (y = {}) => ({
  key: `y${++seq}`,
  ...Object.fromEntries(YARN.map((k) => [k, str(y[k])])),
  yarn: str(y.yarn),
});
const yarnKey = (x) => `${x.composition_id ?? ""}|${x.yarn_count_id ?? ""}`;

function ScrEntry({ options, initial, canManage, act }) {
  const c = useSc();
  const toastManager = useToastManager();
  const [today] = useState(todayIso);
  const asHead = (d) => ({
    program_no: d && !d.auto_number ? str(d.program_no) : "",
    auto_number: d ? Boolean(d.auto_number) : true,
    prog_date: str(d?.prog_date) || today,
    party_id: str(d?.party_id),
    yarn_store_id: str(d?.yarn_store_id),
    remarks: str(d?.remarks),
  });
  const [doc, setDoc] = useState(initial);
  const [head, setHead] = useState(() => asHead(initial));
  const [fabrics, setFabrics] = useState(() =>
    (initial?.fabrics ?? []).map(fabLine),
  );
  const [yarns, setYarns] = useState(() =>
    (initial?.yarns ?? []).map(yarnLine),
  );
  const [fd, setFd] = useState(() => fabLine());
  const [yd, setYd] = useState(() => yarnLine());
  const [held, setHeld] = useState({ store: "", lots: [] });
  const [errors, setErrors] = useState(null);
  const [pending, startTransition] = useTransition();
  const editable = canManage && (!doc || doc.status === "DRAFT");
  const setH = (patch) => setHead((h) => ({ ...h, ...patch }));
  const pick = (list, ph) => [{ value: "", label: ph }, ...list];
  const listOf = (codes, group) =>
    codes.map((v) => ({ value: v, label: c(`${group}.${v}`) }));
  const named = (list, v) => list.find((x) => x.value === v)?.label ?? null;

  // The yarn rows pick from what the chosen store holds.
  useEffect(() => {
    const store = head.yarn_store_id;
    if (!store) return undefined;
    let live = true;
    act(`sc-receipts/store-yarns?store=${encodeURIComponent(store)}`).then(
      (res) =>
        live &&
        setHeld({ store, lots: res?.status === "success" ? res.data : [] }),
    );
    return () => {
      live = false;
    };
  }, [head.yarn_store_id, act]);
  const lots = held.store === head.yarn_store_id ? held.lots : [];
  const yarnOptions = [
    ...new Map(lots.map((x) => [yarnKey(x), x.yarn])).entries(),
  ].map(([value, label]) => ({ value, label }));
  const brandOptions = [
    ...new Set(
      lots
        .filter((x) => yarnKey(x) === yarnKey(yd))
        .map((x) => x.brand)
        .filter(Boolean),
    ),
  ].map((b) => ({ value: b, label: b }));
  const serials = fabrics.map((f) => f.sl).filter(Boolean);

  function show(d) {
    setDoc(d);
    setHead(asHead(d));
    setFabrics((d?.fabrics ?? []).map(fabLine));
    setYarns((d?.yarns ?? []).map(yarnLine));
    setFd(fabLine());
    setYd(yarnLine());
    setErrors(null);
    window.history.replaceState(
      null,
      "",
      d ? `?tab=entry&ref=${encodeURIComponent(d.program_no)}` : "?tab=entry",
    );
  }
  function open(no) {
    if (!no) return show(null);
    startTransition(async () => {
      const res = await act(`sc-receipts/${encodeURIComponent(no)}`);
      if (res?.status !== "success") return setErrors(messagesOf(res));
      show(res.data);
    });
  }
  function addFabric() {
    const miss = [
      !(num(fd.qty) > 0) && c("line.need_qty"),
      !fd.uom && c("line.need_uom"),
      fd.sl &&
        serials.includes(String(Number(fd.sl))) &&
        c("line.sl_taken", { sl: fd.sl }),
    ].filter(Boolean);
    if (miss.length) return setErrors(miss);
    const sl = fd.sl
      ? String(Number(fd.sl))
      : String(Math.max(0, ...serials.map(Number)) + 1);
    setFabrics((all) =>
      [...all, { ...fd, sl }].sort((a, b) => Number(a.sl) - Number(b.sl)),
    );
    setFd(
      fabLine({
        uom: fd.uom,
        dia_unit: fd.dia_unit,
        width_type: fd.width_type,
        rate_type: fd.rate_type,
      }),
    );
    setErrors(null);
  }
  function removeFabric(f) {
    if (yarns.some((y) => y.sl === f.sl))
      return setErrors([c("line.sl_has_yarn", { sl: f.sl })]);
    setFabrics((all) => all.filter((x) => x.key !== f.key));
  }
  function addYarn() {
    const miss = [
      !yd.sl && c("line.need_sl"),
      !yd.composition_id && c("line.need_yarn"),
    ].filter(Boolean);
    if (miss.length) return setErrors(miss);
    setYarns((all) => [
      ...all,
      { ...yd, yarn: named(yarnOptions, yarnKey(yd)) ?? "" },
    ]);
    setYd(yarnLine({ sl: yd.sl }));
    setErrors(null);
  }
  function pickYarn(v) {
    const [composition_id = "", yarn_count_id = ""] = (v ?? "").split("|");
    setYd((y) => ({
      ...y,
      composition_id,
      yarn_count_id,
      brand: "",
      lot_no: "",
    }));
  }
  function setRatio(v) {
    const fab = fabrics.find((f) => f.sl === yd.sl);
    setYd((y) => ({
      ...y,
      ratio_qty: v,
      required_qty:
        fab && v !== ""
          ? String(Math.round(num(fab.qty) * num(v) * 10) / 1000)
          : y.required_qty,
    }));
  }
  function save() {
    startTransition(async () => {
      const blankNull = (l, keys, nums) =>
        Object.fromEntries(
          keys.map((k) => [
            k,
            nums.includes(k) ? orNull(l[k]) : l[k] === "" ? null : l[k],
          ]),
        );
      const body = {
        auto_number: head.auto_number,
        program_no: head.auto_number ? null : head.program_no || null,
        prog_date: head.prog_date || null,
        party_id: head.party_id || null,
        yarn_store_id: head.yarn_store_id || null,
        remarks: head.remarks || null,
        fabrics: fabrics.map((f) => blankNull(f, FAB, FAB_NUM)),
        yarns: yarns.map((y) => blankNull(y, YARN, YARN_NUM)),
      };
      const res = await act(
        doc
          ? `sc-receipts/${encodeURIComponent(doc.program_no)}`
          : "sc-receipts",
        doc ? "PUT" : "POST",
        body,
      );
      if (res?.status !== "success") return setErrors(messagesOf(res));
      show(res.data);
      toastManager.add({
        title: c("done.saved", { no: res.data.program_no }),
        type: "success",
      });
    });
  }
  const fIn = (k, label, { hint, ...props } = {}) => (
    <Field label={label} hint={hint}>
      <Input
        value={fd[k]}
        onChange={(e) => setFd((x) => ({ ...x, [k]: e.target.value }))}
        aria-label={label}
        {...props}
      />
    </Field>
  );
  const fSel = (k, label, list, ph) => (
    <Field label={label}>
      <PSelect
        value={fd[k]}
        onValueChange={(v) => setFd((x) => ({ ...x, [k]: v ?? "" }))}
        options={pick(list, ph)}
        aria-label={label}
      />
    </Field>
  );
  const yIn = (k, label, props = {}) => (
    <Field label={label}>
      <Input
        value={yd[k]}
        onChange={(e) => setYd((x) => ({ ...x, [k]: e.target.value }))}
        aria-label={label}
        {...props}
      />
    </Field>
  );
  const numeric = { type: "number", min: "0", step: "any" };

  return (
    <div className="flex flex-col gap-4">
      <section className={cn(card, "p-5")}>
        <div className="flex items-start justify-between gap-3">
          <div>
            <h2 className="text-base font-semibold text-foreground">
              {doc ? doc.program_no : c("tabs.rc_entry")}
            </h2>
            <p className="mt-0.5 text-[13px] text-foreground-muted">
              {c("hint.rc_form")}
            </p>
          </div>
          {doc ? (
            <StatusChip
              status={TONE[doc.status]}
              label={c(`receipt_status.${doc.status}`)}
            />
          ) : null}
        </div>
        {doc && !editable && canManage ? (
          <Alert variant="info" className="mt-3">
            {c("hint.fixed", {
              no: doc.program_no,
              status: c(`receipt_status.${doc.status}`),
            })}
          </Alert>
        ) : null}
        <h3 className="mt-5 mb-3 text-sm font-semibold text-foreground">
          {c("sec.key")}
        </h3>
        <div className="grid gap-4 md:grid-cols-3">
          <Field label={c("f.existing")}>
            <PSelect
              value={doc?.program_no ?? ""}
              onValueChange={(v) => open(v ?? "")}
              options={pick(options.receipts, c("ph.new"))}
              disabled={pending}
              aria-label={c("f.existing")}
            />
          </Field>
          <Field label={c("f.program_no")} hint={c("hint.auto")}>
            <div className="flex items-center gap-3">
              <Input
                value={
                  head.auto_number
                    ? doc?.auto_number
                      ? doc.program_no
                      : ""
                    : head.program_no
                }
                onChange={(e) => setH({ program_no: e.target.value })}
                placeholder={c("ph.issued")}
                maxLength={32}
                disabled={!editable || head.auto_number}
                aria-label={c("f.program_no")}
              />
              <span className="flex items-center gap-1.5 text-[13px] whitespace-nowrap">
                <Checkbox
                  checked={head.auto_number}
                  onCheckedChange={(v) => setH({ auto_number: Boolean(v) })}
                  disabled={!editable}
                  aria-label={c("f.auto")}
                />
                {c("f.auto")}
              </span>
            </div>
          </Field>
          <Field label={c("f.prog_date")} required>
            <Input
              type="date"
              value={head.prog_date}
              onChange={(e) => setH({ prog_date: e.target.value })}
              disabled={!editable}
              aria-label={c("f.prog_date")}
            />
          </Field>
          <Field label={c("f.party")} required>
            <PSelect
              value={head.party_id}
              onValueChange={(v) => setH({ party_id: v ?? "" })}
              options={pick(options.parties, c("ph.select"))}
              disabled={!editable}
              aria-label={c("f.party")}
            />
          </Field>
          <Field label={c("f.yarn_store")} required hint={c("hint.store")}>
            <PSelect
              value={head.yarn_store_id}
              onValueChange={(v) => setH({ yarn_store_id: v ?? "" })}
              options={pick(options.stores, c("ph.select"))}
              disabled={!editable}
              aria-label={c("f.yarn_store")}
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
        </div>
        <Field label={c("f.remarks")} className="mt-4">
          <Textarea
            rows={3}
            value={head.remarks}
            onChange={(e) => setH({ remarks: e.target.value })}
            maxLength={2000}
            disabled={!editable}
            aria-label={c("f.remarks")}
          />
        </Field>
      </section>

      <section className={cn(card, "p-5")}>
        <div className="flex flex-wrap items-start justify-between gap-3">
          <div>
            <h3 className="text-base font-semibold text-foreground">
              {c("sec.colour_fabric")}
            </h3>
            <p className="mt-0.5 text-[13px] text-foreground-muted">
              {c("hint.colour_fabric")}
            </p>
          </div>
          {editable ? (
            <div className="flex gap-2">
              <Button variant="outline" size="sm" onClick={addFabric}>
                <Plus /> {c("btn.add_grid")}
              </Button>
              <Button
                variant="outline"
                size="sm"
                onClick={() => setFd(fabLine())}
              >
                {c("btn.cancel_line")}
              </Button>
            </div>
          ) : null}
        </div>
        {editable ? (
          <div className="mt-4 grid gap-4 md:grid-cols-3">
            {fSel("order_id", c("f.order_ref"), options.orders, "--Select--")}
            {fIn("party_ref", c("f.party_ref"), {
              maxLength: 64,
              hint: c("hint.party_ref"),
            })}
            {fSel(
              "fabric_type_id",
              c("f.fabric_type"),
              options.fabric_types,
              "--Select--",
            )}
            {fSel("colour_id", c("f.colour"), options.colours, "--Select--")}
            {fIn("colour_name", c("f.colour_name"), { maxLength: 80 })}
            {fSel(
              "composition_id",
              c("f.composition"),
              options.compositions,
              "--Select--",
            )}
            {fIn("gsm", c("f.fgsm"), numeric)}
            {fIn("qty", c("f.qty"), numeric)}
            {fSel("uom", c("f.uom"), options.uoms, c("ph.choose"))}
            {fIn("finish_dia", c("f.finish_dia"), numeric)}
            {fSel(
              "dia_unit",
              c("f.dia_unit"),
              listOf(["INCH", "CM"], "dia_unit"),
              c("ph.choose"),
            )}
            {fSel(
              "width_type",
              c("f.width_type"),
              listOf(["OPEN", "TUBE"], "width_type"),
              c("ph.choose"),
            )}
            {fSel(
              "yd_type",
              c("f.yd_type"),
              listOf(options.yd_types, "yd_type"),
              c("ph.choose"),
            )}
            {fIn("feeder", c("f.feeder"), {
              ...numeric,
              step: "1",
              hint: c("hint.feeder"),
            })}
            {fSel("mc_dia_id", c("f.rc_mc_dia"), options.mc_dias, c("ph.dia"))}
            {fSel("gauge_id", c("f.gauge"), options.gauges, c("ph.gg"))}
            {fSel(
              "rate_type",
              c("f.rate_type"),
              listOf(options.rate_types, "rate_type"),
              c("ph.choose"),
            )}
            {fIn("rate", c("f.prod_rate"), numeric)}
            {fIn("start_date", c("f.start_date"), { type: "date" })}
            {fIn("end_date", c("f.end_date"), { type: "date" })}
            {fIn("sl", c("f.sl"), {
              ...numeric,
              min: "1",
              step: "1",
              hint: c("hint.sl"),
            })}
            {fIn("remarks", `${c("f.remarks")} (${c("f.fabric")})`, {
              maxLength: 300,
            })}
          </div>
        ) : null}
        <div className="mt-5 overflow-x-auto rounded-lg border border-border">
          {fabrics.length === 0 ? (
            <NoLines title={c("empty.lines")} hint={c("empty.lines_hint")} />
          ) : (
            <table className="w-full min-w-[1100px] text-sm">
              <thead className="bg-brand text-brand-foreground">
                <tr>
                  {[
                    "sl",
                    "order_ref",
                    "colour",
                    "fab_type",
                    "composition",
                    "fin_dia",
                    "fgsm",
                    "qty_kg",
                    "start",
                    "end",
                  ].map((k) => (
                    <th
                      key={k}
                      className={cn(
                        th,
                        ["fgsm", "qty_kg"].includes(k) && "text-right",
                      )}
                    >
                      {k === "qty_kg" ? c("f.qty") : c(`col.${k}`)}
                    </th>
                  ))}
                  <th className={th} />
                </tr>
              </thead>
              <tbody>
                {fabrics.map((f) => (
                  <tr key={f.key} className="border-t border-border">
                    <td className="px-2 py-2 font-semibold">{f.sl}</td>
                    <td className="px-2 py-2">
                      {named(options.orders, f.order_id) ??
                        (f.party_ref || "—")}
                    </td>
                    <td className="px-2 py-2">
                      {[named(options.colours, f.colour_id), f.colour_name]
                        .filter(Boolean)
                        .join(" · ") || "—"}
                    </td>
                    <td className="px-2 py-2">
                      {named(options.fabric_types, f.fabric_type_id) ?? "—"}
                    </td>
                    <td className="px-2 py-2">
                      {named(options.compositions, f.composition_id) ?? "—"}
                    </td>
                    <td className="px-2 py-2 whitespace-nowrap">
                      {finDia(c, {
                        ...f,
                        finish_dia: f.finish_dia === "" ? null : f.finish_dia,
                      }) ?? "—"}
                    </td>
                    <td className="px-2 py-2 text-right tabular-nums">
                      {f.gsm || "—"}
                    </td>
                    <td className="px-2 py-2 text-right tabular-nums">
                      {qtyFmt.format(num(f.qty))} {f.uom}
                    </td>
                    <td className="px-2 py-2 whitespace-nowrap">
                      {f.start_date ? dayFmt(f.start_date) : "—"}
                    </td>
                    <td className="px-2 py-2 whitespace-nowrap">
                      {f.end_date ? dayFmt(f.end_date) : "—"}
                    </td>
                    <td className="px-2 py-1.5">
                      {editable ? (
                        <Button
                          variant="ghost"
                          size="sm"
                          className="text-danger"
                          onClick={() => removeFabric(f)}
                          aria-label={`${c("btn.remove")} SL ${f.sl}`}
                        >
                          <Trash2 /> {c("btn.remove")}
                        </Button>
                      ) : null}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          )}
        </div>
      </section>

      <section className={cn(card, "p-5")}>
        <div className="flex flex-wrap items-start justify-between gap-3">
          <div>
            <h3 className="text-base font-semibold text-foreground">
              {c("sec.yarn")}
            </h3>
            <p className="mt-0.5 text-[13px] text-foreground-muted">
              {head.yarn_store_id ? c("hint.yarn") : c("hint.yarn_store_first")}
            </p>
          </div>
          {editable ? (
            <Button
              variant="outline"
              size="sm"
              onClick={addYarn}
              disabled={!head.yarn_store_id}
            >
              <Plus /> {c("btn.add_yarn")}
            </Button>
          ) : null}
        </div>
        {editable ? (
          <div className="mt-4 grid gap-4 md:grid-cols-4">
            <Field label={c("f.sl")}>
              <PSelect
                value={yd.sl}
                onValueChange={(v) => setYd((y) => ({ ...y, sl: v ?? "" }))}
                options={pick(
                  serials.map((s) => ({ value: s, label: s })),
                  c("ph.select"),
                )}
                aria-label={`${c("f.sl")} (${c("f.yarn")})`}
              />
            </Field>
            <Field label={c("f.yarn")} className="md:col-span-2">
              <PSelect
                value={yd.composition_id ? yarnKey(yd) : ""}
                onValueChange={pickYarn}
                options={pick(yarnOptions, c("ph.yarn"))}
                disabled={!head.yarn_store_id}
                aria-label={c("f.yarn")}
              />
            </Field>
            {yIn("fab_part", c("f.fab_part"), { maxLength: 60 })}
            <Field label={c("f.col_grp")}>
              <PSelect
                value={yd.colour_group}
                onValueChange={(v) =>
                  setYd((y) => ({ ...y, colour_group: v ?? "" }))
                }
                options={pick(
                  options.colour_groups.map((g) => ({ value: g, label: g })),
                  c("ph.group"),
                )}
                aria-label={c("f.col_grp")}
              />
            </Field>
            <Field label={c("f.brand")}>
              <PSelect
                value={yd.brand}
                onValueChange={(v) => setYd((y) => ({ ...y, brand: v ?? "" }))}
                options={pick(brandOptions, c("ph.brand"))}
                disabled={!yd.composition_id}
                aria-label={c("f.brand")}
              />
            </Field>
            {yIn("lot_no", c("f.lot"), { maxLength: 40 })}
            {yIn("stitch_length", c("f.s_len"), numeric)}
            {yIn("elastane_pct", c("f.ela_pct"), { ...numeric, max: "100" })}
            <Field label={c("f.ratio")}>
              <Input
                {...numeric}
                value={yd.ratio_qty}
                onChange={(e) => setRatio(e.target.value)}
                aria-label={c("f.ratio")}
              />
            </Field>
            {yIn("required_qty", c("f.rqd"), numeric)}
            {yIn("remarks", `${c("f.remarks")} (${c("f.yarn")})`, {
              maxLength: 300,
            })}
          </div>
        ) : null}
        <div className="mt-5 overflow-x-auto rounded-lg border border-border">
          {yarns.length === 0 ? (
            <NoLines title={c("empty.yarns")} hint={c("empty.yarns_hint")} />
          ) : (
            <table className="w-full min-w-[1100px] text-sm">
              <thead className="bg-brand text-brand-foreground">
                <tr>
                  {[
                    [c("col.sl")],
                    [c("f.yarn")],
                    [c("f.fab_part")],
                    [c("f.col_grp")],
                    [c("f.brand")],
                    [c("f.lot")],
                    [c("f.s_len"), true],
                    [c("f.ela_pct"), true],
                    [c("f.ratio"), true],
                    [c("f.rqd"), true],
                    [c("col.remarks")],
                  ].map(([label, right]) => (
                    <th key={label} className={cn(th, right && "text-right")}>
                      {label}
                    </th>
                  ))}
                  <th className={th} />
                </tr>
              </thead>
              <tbody>
                {yarns.map((y, i) => (
                  <tr key={y.key} className="border-t border-border">
                    <td className="px-2 py-2 font-semibold">{y.sl}</td>
                    <td className="px-2 py-2">{y.yarn || "—"}</td>
                    <td className="px-2 py-2">{y.fab_part || "—"}</td>
                    <td className="px-2 py-2">{y.colour_group || "—"}</td>
                    <td className="px-2 py-2">{y.brand || "—"}</td>
                    <td className="px-2 py-2">{y.lot_no || "—"}</td>
                    <td className="px-2 py-2 text-right tabular-nums">
                      {y.stitch_length || "—"}
                    </td>
                    <td className="px-2 py-2 text-right tabular-nums">
                      {y.elastane_pct || "—"}
                    </td>
                    <td className="px-2 py-2 text-right tabular-nums">
                      {y.ratio_qty || "—"}
                    </td>
                    <td className="px-2 py-2 text-right tabular-nums">
                      {y.required_qty === ""
                        ? "—"
                        : qtyFmt.format(num(y.required_qty))}
                    </td>
                    <td className="px-2 py-2">{y.remarks || "—"}</td>
                    <td className="px-2 py-1.5">
                      {editable ? (
                        <Button
                          variant="ghost"
                          size="sm"
                          className="text-danger"
                          onClick={() =>
                            setYarns((all) =>
                              all.filter((x) => x.key !== y.key),
                            )
                          }
                          aria-label={`${c("btn.remove")} ${c("f.yarn")} ${i + 1}`}
                        >
                          <Trash2 /> {c("btn.remove")}
                        </Button>
                      ) : null}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          )}
        </div>
      </section>

      {errors ? <Alert variant="danger">{errors.join(" · ")}</Alert> : null}
      <section
        className={cn(
          card,
          "flex flex-wrap items-center justify-between gap-3 p-4",
        )}
      >
        <p className="max-w-2xl text-[13px] text-foreground-muted">
          {c("hint.rc_foot")}
        </p>
        <div className="flex flex-wrap gap-2">
          <Link href={BASE} className={buttonVariants({ variant: "outline" })}>
            <ArrowLeft /> {c("btn.back")}
          </Link>
          <Button
            variant="outline"
            onClick={() => show(null)}
            disabled={pending}
          >
            <Eraser /> {c("btn.clear")}
          </Button>
          {editable ? (
            <Button onClick={save} loading={pending}>
              <Save /> {c("btn.save")}
            </Button>
          ) : null}
        </div>
      </section>
    </div>
  );
}

export { ScrList, ScrEntry };
