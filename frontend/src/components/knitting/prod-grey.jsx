"use client";

import { useRef, useState, useTransition } from "react";
import Link from "next/link";
import {
  Ban,
  Eraser,
  PackageCheck,
  Plus,
  Printer,
  RotateCcw,
  Search,
  Send,
} from "lucide-react";
import { cn } from "@/lib/utils";
import { useT } from "@/lib/i18n";
import { printDocument } from "@/lib/print-document";
import { Input } from "@/components/ui/input";
import { Textarea } from "@/components/ui/textarea";
import { Button, buttonVariants } from "@/components/ui/button";
import { Alert } from "@/components/ui/alert";
import { useToastManager } from "@/components/ui/toast";
import {
  PSelect,
  str,
  num,
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
import { ScTable } from "./prod-sc";
import { TONE, pick } from "./prod-yd";
import {
  useShowFilters,
  useMoves,
  ListShell,
  MoveButton,
  FormHead,
  FormFoot,
  RemoveCell,
} from "./ystore-yd";
import { useFs } from "./fstore-grey";

/*
 * Knit Production → Production (AnnotechERP 15/04): the grey movements that
 * ride on the Fabric Store's grey ledger — Flat Knit Grey Fabric Transfer
 * (collar / cuff rolls off the beds), Grey Store to Batch Transfer,
 * Order-to-Order Grey Transfer, Grey Reuse from Leftover Store, Grey Store
 * to Leftover and Batch Store to Leftover — one list and one form, told
 * apart by kind; and the Grey Fabric Racking Board. Nothing reloads.
 */

const KIND = {
  COLLAR_CUFF: {
    slug: "collar-cuff",
    base: "/knit-production/flat-knit-grey-transfer",
    rolls: "floor",
    fields: [
      "category",
      "cc_type",
      "buyer",
      "from_order",
      "to_order",
      "from_store",
      "to_store",
    ],
    need: ["category", "cc_type", "from_order", "to_store"],
    show: true,
  },
  TO_BATCH: {
    slug: "to-batch",
    base: "/knit-production/grey-fabric-transfer-grey-to-batch",
    rolls: "store",
    fields: [
      "category",
      "buyer",
      "from_order",
      "to_order",
      "from_store",
      "batch",
    ],
    need: ["category", "from_order", "batch"],
    show: true,
  },
  ORDER: {
    slug: "order",
    base: "/knit-production/grey-fabric-transfer-order-to-order",
    fields: ["type", "buyer", "from_order", "to_order"],
    need: ["type", "from_order", "to_order"],
  },
  REUSE: {
    slug: "reuse",
    base: "/knit-production/grey-fabric-reuse-from-left-over-store",
    fields: ["type", "buyer", "from_store", "order"],
    need: ["type", "from_store", "order"],
  },
  TO_LEFTOVER: {
    slug: "to-leftover",
    base: "/knit-production/grey-fabric-transfer-grey-store-to-left-over",
    fields: ["type", "buyer", "order", "from_store", "to_store"],
    need: ["type", "order", "from_store", "to_store"],
  },
  BATCH_TO_LEFTOVER: {
    slug: "batch-leftover",
    base: "/knit-production/grey-fabric-transfer-batch-store-to-left-over",
    fields: ["type", "buyer", "order", "from_store", "to_store"],
    need: ["type", "order", "from_store", "to_store"],
    batchOnly: true,
  },
};
const enc = encodeURIComponent;
const numIn = { type: "number", min: "0", step: "any" };
let seq = 0;
const lineKey = () => `m${++seq}`;

function useGt() {
  const t = useT("knitting");
  return (k, p) => t(`gt.${k}`, p);
}

function printMove(g, c, d) {
  const qty = d.lines.reduce((a, l) => a + num(l.qty), 0);
  const rolls = d.lines.reduce((a, l) => a + num(l.rolls), 0);
  printDocument({
    title: g(`p.title.${d.kind}`),
    docNo: d.transfer_no,
    orientation: "landscape",
    meta: [
      [c("f.date"), dayFmt(d.transfer_date)],
      [g("f.trans_category"), c(`trans_type.${d.trans_type}`)],
      [g("f.cc_type"), d.cc_type ? g(`cc_type.${d.cc_type}`) : null],
      [c("f.status"), { v: c(`transfer_status.${d.status}`), tone: "info" }],
      [
        ["COLLAR_CUFF", "TO_BATCH", "ORDER"].includes(d.kind)
          ? g("f.from_order")
          : g("f.style_order"),
        d.order,
      ],
      [g("f.to_order"), d.to_order],
      [c("f.from_store"), d.from_store],
      [c("f.to_store"), d.to_store],
      [g("f.to_batch"), d.batch_no],
      [c("f.trans_ref"), d.trans_ref],
      [c("f.buyer_ac"), d.buyer],
    ],
    metaColumns: 4,
    sections: [
      {
        title: c("sec.fabric_detail"),
        columns: [
          { label: "#", align: "center", width: "4%" },
          { label: c("col.roll"), nowrap: true },
          { label: c("col.fabric") },
          { label: c("col.colour") },
          { label: c("col.dia"), align: "right" },
          { label: c("col.gsm"), align: "right" },
          { label: g("col.rolls_n"), align: "right" },
          { label: g("col.qty"), align: "right" },
          { label: c("col.remarks") },
        ],
        rows: d.lines.map((l, i) => [
          i + 1,
          { v: l.roll_no ?? "—", bold: true },
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

/* ---------------- the list ---------------- */

function MoveList({ kind, options, initialRows, canManage, act }) {
  const c = useFs();
  const g = useGt();
  const cfg = KIND[kind];
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
    `moves/${cfg.slug}`,
    "transfer_no",
    setRows,
    setError,
  );
  // Brickland narrows two of these lists on Show, the rest as you type.
  const o = cfg.show ? flt.on : flt.f;
  const shown = rows.filter(
    (r) =>
      (!o.buyer || r.buyer_id === o.buyer) &&
      (!o.order || r.order_id === o.order || r.to_order_id === o.order) &&
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
      const res = await act(`moves/${cfg.slug}/${enc(no)}`);
      if (res?.status !== "success") return setError(messagesOf(res));
      printMove(g, c, res.data);
    });
  }
  const has = (f) => cfg.fields.includes(f);
  const columns = [
    {
      key: "transfer_date",
      label: c("col.date"),
      nowrap: true,
      text: (r) => dayFmt(r.transfer_date),
    },
    {
      key: "transfer_no",
      label: g("col.transfer_no"),
      nowrap: true,
      render: (r) => (
        <span className="flex flex-col items-start gap-1">
          <Link
            href={`${cfg.base}?tab=entry&ref=${enc(r.transfer_no)}`}
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
      key: "trans_type",
      label: has("category") ? g("col.trans_category") : c("col.trans_type"),
      text: (r) => c(`trans_type.${r.trans_type}`),
    },
    ...(has("cc_type")
      ? [
          {
            key: "cc_type",
            label: g("col.trans_type"),
            text: (r) => (r.cc_type ? g(`cc_type.${r.cc_type}`) : "—"),
          },
        ]
      : []),
    ...(has("from_order")
      ? [
          { key: "order", label: g("col.from_order") },
          { key: "to_order", label: g("col.to_order") },
        ]
      : [{ key: "order", label: g("col.style_order") }]),
    ...(has("from_store")
      ? [{ key: "from_store", label: c("col.from_store") }]
      : []),
    ...(has("to_store") ? [{ key: "to_store", label: c("col.to_store") }] : []),
    ...(has("batch") ? [{ key: "batch_no", label: g("col.batch") }] : []),
    { key: "buyer", label: c("col.buyer") },
    ...(kind === "TO_LEFTOVER" || kind === "BATCH_TO_LEFTOVER"
      ? []
      : [{ key: "trans_ref", label: c("col.trans_ref") }]),
    { key: "rolls", label: c("col.rolls"), num: true },
    {
      key: "qty",
      label: g("col.qty"),
      num: true,
      text: (r) => qtyFmt.format(r.qty),
    },
    { key: "remarks", label: c("col.remarks") },
  ];
  const filters = (
    <>
      <Field label={c("f.buyer_ac")}>
        <PSelect
          value={flt.f.buyer}
          onValueChange={(v) => flt.set("buyer", v)}
          options={pick(options.buyers, c("ph.select"))}
          aria-label={c("f.buyer_ac")}
        />
      </Field>
      <Field label={has("from_order") ? g("f.from_order") : g("f.style_order")}>
        <PSelect
          value={flt.f.order}
          onValueChange={(v) => flt.set("order", v)}
          options={pick(options.orders, c("ph.select"))}
          aria-label={
            has("from_order") ? g("f.from_order") : g("f.style_order")
          }
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
            options.transfer_statuses.map((s) => ({
              value: s,
              label: c(`transfer_status.${s}`),
            })),
            c("ph.all"),
          )}
          aria-label={c("f.status")}
        />
      </Field>
      {cfg.show ? null : (
        <>
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
      )}
    </>
  );
  const table = (
    <>
      <p className="px-5 pt-4 text-[13px] text-foreground-muted">
        {g("hint.list")}
      </p>
      <ScTable
        rows={shown}
        columns={columns}
        exportName={`grey-${cfg.slug}`}
        addHref={canManage ? `${cfg.base}?tab=entry` : null}
        empty={<NoLines title={g("empty.list")} hint={c("empty.hint")} />}
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
    </>
  );
  if (cfg.show)
    return (
      <ListShell
        c={c}
        error={error}
        onShow={flt.show}
        onReset={flt.reset}
        filters={filters}
      >
        {table}
      </ListShell>
    );
  return (
    <div className="flex flex-col gap-4">
      <section className={cn(card, "p-5")}>
        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
          {filters}
        </div>
        <div className="mt-4 flex justify-end">
          <Button variant="outline" size="sm" onClick={flt.reset}>
            <Eraser /> {c("btn.clear")}
          </Button>
        </div>
      </section>
      {error ? <Alert variant="danger">{error.join(" · ")}</Alert> : null}
      <section className={card}>{table}</section>
    </div>
  );
}

/* ---------------- the form ---------------- */

const HEAD = [
  "transfer_date",
  "trans_type",
  "cc_type",
  "trans_ref",
  "buyer_id",
  "order_id",
  "to_order_id",
  "from_store_id",
  "to_store_id",
  "batch_no",
  "remarks",
];

function MoveEntry({ kind, options, initial, initialSource, canManage, act }) {
  const c = useFs();
  const g = useGt();
  const toastManager = useToastManager();
  const cfg = KIND[kind];
  const [today] = useState(todayIso);
  const asHead = (d) => ({
    ...Object.fromEntries(HEAD.map((k) => [k, str(d?.[k])])),
    transfer_date: str(d?.transfer_date) || today,
  });
  const asLine = (l = {}) => ({
    key: lineKey(),
    lot_id: str(l.lot_id),
    roll_id: str(l.roll_id),
    roll_no: str(l.roll_no),
    fabric: str(l.fabric),
    colour_id: str(l.colour_id),
    colour_name: str(l.colour ?? l.colour_name),
    qty: str(l.qty),
    remarks: str(l.remarks),
    info: l,
  });
  const [doc, setDoc] = useState(initial);
  const [head, setHead] = useState(() => asHead(initial));
  const [lines, setLines] = useState(() => (initial?.lines ?? []).map(asLine));
  const [source, setSource] = useState(initialSource ?? []);
  const [draft, setDraft] = useState(() => asLine());
  const [errors, setErrors] = useState(null);
  const [pending, startTransition] = useTransition();
  const open = canManage && (!doc || doc.status === "DRAFT");
  const has = (f) => cfg.fields.includes(f);
  const need = (f) => cfg.need.includes(f);
  const total = lines.reduce((a, l) => a + num(l.qty), 0);
  const rollMode = Boolean(cfg.rolls);
  const sourceKey = cfg.rolls === "floor" ? "roll_id" : "lot_id";
  const used = lines.map((l) => l[sourceKey]).filter(Boolean);
  const picks = source
    .filter((s) => !(rollMode || s.roll_id) || !used.includes(s[sourceKey]))
    .map((s) => ({
      value: s[sourceKey],
      label:
        cfg.rolls === "floor"
          ? `${s.label} · ${qtyFmt.format(s.weight)} kg`
          : `${s.label} · ${qtyFmt.format(s.balance)} kg`,
    }));

  // Only the latest fetch counts: an order picked and then a store fires two.
  const ticket = useRef(0);
  function fetchSource(h) {
    const mine = ++ticket.current;
    let path = null;
    if (cfg.rolls === "floor") {
      if (h.order_id) path = `floor-rolls?order=${enc(h.order_id)}`;
    } else {
      const order = kind === "REUSE" ? "" : h.order_id;
      if (need("from_store") && !h.from_store_id) path = null;
      else if (h.from_store_id || order)
        path = `lots?${new URLSearchParams(
          Object.entries({
            store: h.from_store_id,
            order,
            rolls: cfg.rolls === "store" ? "1" : "",
            batch: cfg.rolls === "store" ? "none" : cfg.batchOnly ? "only" : "",
          }).filter(([, v]) => v),
        )}`;
    }
    if (!path) return setSource([]);
    startTransition(async () => {
      const res = await act(path);
      if (res?.status === "success" && mine === ticket.current)
        setSource(res.data);
    });
  }
  function setH(patch) {
    const next = { ...head, ...patch };
    setHead(next);
    if ("order_id" in patch || "from_store_id" in patch) {
      fetchSource(next);
      setLines([]);
    }
  }
  function show(d) {
    setDoc(d);
    setHead(asHead(d));
    setLines((d?.lines ?? []).map(asLine));
    setDraft(asLine());
    setErrors(null);
    if (d) fetchSource(asHead(d));
    window.history.replaceState(
      null,
      "",
      d ? `?tab=entry&ref=${enc(d.transfer_no)}` : "?tab=entry",
    );
  }
  function load(no) {
    if (!no) return show(null);
    startTransition(async () => {
      const res = await act(`moves/${cfg.slug}/${enc(no)}`);
      if (res?.status !== "success") return setErrors(messagesOf(res));
      show(res.data);
    });
  }
  /** A roll picked on a roll row fills its fabric, shade and weight. */
  function pickRoll(key, id) {
    const s = source.find((x) => x[sourceKey] === id);
    setLines((all) =>
      all.map((l) =>
        l.key === key
          ? s
            ? {
                ...l,
                [sourceKey]: id,
                roll_no: s.roll_no,
                fabric: s.fabric ?? "",
                colour_id: str(s.colour_id),
                colour_name: s.colour ?? "",
                qty: String(cfg.rolls === "floor" ? s.weight : s.balance),
                info: s,
              }
            : { ...l, [sourceKey]: "" }
          : l,
      ),
    );
  }
  const setL = (key, patch) =>
    setLines((all) => all.map((l) => (l.key === key ? { ...l, ...patch } : l)));
  const picked = source.find((s) => s.lot_id === draft.lot_id);
  function addFabric() {
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
          HEAD.map((k) => [k, head[k] === "" ? null : head[k]]),
        ),
        lines: lines.map((l) => ({
          lot_id: l.lot_id || null,
          roll_id: l.roll_id || null,
          roll_no: l.roll_no || null,
          fabric: l.fabric || null,
          colour_id: l.colour_id || null,
          colour_name: l.colour_name || null,
          qty: orNull(l.qty),
          remarks: l.remarks || null,
        })),
      };
      const path = `moves/${cfg.slug}`;
      const res = await act(
        doc ? `${path}/${enc(doc.transfer_no)}` : path,
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
  const orderPick = (field, label, hint) => (
    <Field label={label} required={need(field)} hint={hint}>
      <PSelect
        value={head[field === "to_order" ? "to_order_id" : "order_id"]}
        onValueChange={(v) =>
          setH(
            field === "to_order"
              ? { to_order_id: v ?? "" }
              : {
                  order_id: v ?? "",
                  buyer_id:
                    options.orders.find((x) => x.value === v)?.buyer_id ??
                    head.buyer_id,
                },
          )
        }
        options={pick(
          options.orders.filter(
            (x) =>
              field === "to_order" ||
              !head.buyer_id ||
              x.buyer_id === head.buyer_id,
          ),
          c("ph.select"),
        )}
        disabled={!open}
        aria-label={label}
      />
    </Field>
  );
  const storePick = (field, label) => (
    <Field label={label} required={need(field)}>
      <PSelect
        value={head[`${field}_id`]}
        onValueChange={(v) => setH({ [`${field}_id`]: v ?? "" })}
        options={pick(
          options.stores.filter(
            (s) => field !== "to_store" || s.value !== head.from_store_id,
          ),
          c(field === "from_store" ? "ph.from_store" : "ph.to_store"),
        )}
        disabled={!open}
        aria-label={label}
      />
    </Field>
  );

  return (
    <div className="flex flex-col gap-4">
      <section className={cn(card, "p-5")}>
        <FormHead
          c={c}
          title={doc ? doc.transfer_no : g(`tabs.${kind}_entry`)}
          hint={g(`hint.form.${kind}`)}
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
            options={pick(options.moves ?? [], c("ph.new_tr"))}
            disabled={pending}
            aria-label={c("f.existing_tr")}
          />
        </Field>
        <fieldset className="mt-4 rounded-lg border border-border p-4">
          <legend className="px-1 text-sm font-semibold">
            {rollMode ? g("sec.key") : c("sec.transfer")}
          </legend>
          <div className="grid gap-4 md:grid-cols-3">
            {rollMode ? (
              <Field label={g("f.transfer_no")} hint={g("hint.transfer_no")}>
                <Input
                  value={doc?.transfer_no ?? ""}
                  placeholder={c("ph.auto")}
                  disabled
                  aria-label={g("f.transfer_no")}
                />
              </Field>
            ) : null}
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
              label={
                has("category") ? g("f.trans_category") : c("f.trans_type")
              }
              required
              hint={has("category") ? g("hint.category") : c("hint.trans_type")}
            >
              <PSelect
                value={head.trans_type}
                onValueChange={(v) => setH({ trans_type: v ?? "" })}
                options={pick(
                  options.trans_types.map((t) => ({
                    value: t,
                    label: c(`trans_type.${t}`),
                  })),
                  c("ph.type"),
                )}
                disabled={!open}
                aria-label={
                  has("category") ? g("f.trans_category") : c("f.trans_type")
                }
              />
            </Field>
            {has("cc_type") ? (
              <Field label={g("f.cc_type")} required>
                <PSelect
                  value={head.cc_type}
                  onValueChange={(v) => setH({ cc_type: v ?? "" })}
                  options={pick(
                    options.cc_types.map((t) => ({
                      value: t,
                      label: g(`cc_type.${t}`),
                    })),
                    c("ph.select"),
                  )}
                  disabled={!open}
                  aria-label={g("f.cc_type")}
                />
              </Field>
            ) : null}
            {rollMode ? null : (
              <Field label={c("f.trans_ref")} hint={c("hint.trans_ref")}>
                <Input
                  value={head.trans_ref}
                  onChange={(e) => setH({ trans_ref: e.target.value })}
                  maxLength={40}
                  disabled={!open}
                  aria-label={c("f.trans_ref")}
                />
              </Field>
            )}
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
                onValueChange={(v) =>
                  setHead((h) => ({ ...h, buyer_id: v ?? "" }))
                }
                options={pick(options.buyers, c("ph.select"))}
                disabled={!open}
                aria-label={c("f.buyer_ac")}
              />
            </Field>
            {cfg.fields.map((f) => {
              if (f === "from_order")
                return (
                  <span key={f} className="contents">
                    {orderPick("from_order", g("f.from_order"))}
                  </span>
                );
              if (f === "to_order")
                return (
                  <span key={f} className="contents">
                    {orderPick(
                      "to_order",
                      g("f.to_order"),
                      kind === "ORDER" ? undefined : g("hint.to_order"),
                    )}
                  </span>
                );
              if (f === "order")
                return (
                  <span key={f} className="contents">
                    {orderPick("order", g("f.style_order"))}
                  </span>
                );
              if (f === "from_store")
                return (
                  <span key={f} className="contents">
                    {storePick("from_store", c("f.from_store"))}
                  </span>
                );
              if (f === "to_store")
                return (
                  <span key={f} className="contents">
                    {storePick("to_store", c("f.to_store"))}
                  </span>
                );
              if (f === "batch")
                return (
                  <Field
                    key={f}
                    label={g("f.to_batch")}
                    required
                    hint={g("hint.batch")}
                  >
                    <Input
                      value={head.batch_no}
                      onChange={(e) => setH({ batch_no: e.target.value })}
                      placeholder={g("ph.batch")}
                      maxLength={40}
                      disabled={!open}
                      aria-label={g("f.to_batch")}
                    />
                  </Field>
                );
              return null;
            })}
          </div>
        </fieldset>
        {rollMode ? (
          <fieldset className="mt-4 rounded-lg border border-border p-4">
            <legend className="px-1 text-sm font-semibold">
              {g("sec.reference")}
            </legend>
            <Field label={c("f.trans_ref")}>
              <Input
                value={head.trans_ref}
                onChange={(e) => setH({ trans_ref: e.target.value })}
                placeholder={g("ph.ref")}
                maxLength={40}
                disabled={!open}
                aria-label={c("f.trans_ref")}
              />
            </Field>
          </fieldset>
        ) : null}
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
              {rollMode ? g("sec.rolls") : c("sec.fabric_detail")}
            </h3>
            <p className="mt-0.5 max-w-3xl text-[13px] text-foreground-muted">
              {rollMode ? g("hint.rolls") : c("hint.tr_lines")}
            </p>
          </div>
          {open ? (
            rollMode ? (
              <Button
                variant="outline"
                size="sm"
                onClick={() => setLines((all) => [...all, asLine()])}
              >
                <Plus /> {g("btn.add_roll")}
              </Button>
            ) : (
              <div className="flex gap-2">
                <Button variant="outline" size="sm" onClick={addFabric}>
                  <Plus /> {c("btn.add_fabric")}
                </Button>
                <Button
                  variant="outline"
                  size="sm"
                  onClick={() => setDraft(asLine())}
                >
                  {c("btn.cancel")}
                </Button>
              </div>
            )
          ) : null}
        </div>
        {open && !rollMode ? (
          <div className="mt-4 grid gap-4 md:grid-cols-4">
            <Field label={c("f.roll_pick")} className="md:col-span-2">
              <PSelect
                value={draft.lot_id}
                onValueChange={(v) => {
                  const l = source.find((x) => x.lot_id === v);
                  setDraft((d) => ({
                    ...d,
                    lot_id: v ?? "",
                    qty: l?.roll_id ? String(l.balance) : d.qty,
                    roll_no: l?.roll_id ? "" : d.roll_no,
                  }));
                }}
                options={pick(picks, c("ph.lot"))}
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
            <NoLines title={c("empty.lines")} hint={g("empty.rolls")} />
          ) : rollMode ? (
            <table className="w-full min-w-[1000px] text-sm">
              <thead className="bg-brand text-brand-foreground">
                <tr>
                  {[
                    [g("f.roll")],
                    [g("f.fabric")],
                    [g("f.colour")],
                    [g("f.rolls"), true],
                    [g("f.qty"), true],
                    [c("f.remarks")],
                  ].map(([label, right]) => (
                    <th key={label} className={cn(th, right && "text-right")}>
                      {label}
                    </th>
                  ))}
                  <th className={th} />
                </tr>
              </thead>
              <tbody>
                {lines.map((l, i) => (
                  <tr key={l.key} className="border-t border-border">
                    <td className="min-w-[260px] px-2 py-1.5">
                      <PSelect
                        value={l[sourceKey]}
                        onValueChange={(v) => pickRoll(l.key, v ?? "")}
                        options={pick(
                          [
                            ...picks,
                            ...(l[sourceKey] &&
                            !picks.some((p) => p.value === l[sourceKey])
                              ? [
                                  {
                                    value: l[sourceKey],
                                    label: l.roll_no || l[sourceKey],
                                  },
                                ]
                              : []),
                          ],
                          g("ph.roll"),
                        )}
                        disabled={!open}
                        aria-label={`${g("f.roll")} ${i + 1}`}
                      />
                    </td>
                    <td className="min-w-[200px] px-2 py-1.5">
                      <Input
                        value={l.fabric}
                        onChange={(e) =>
                          setL(l.key, { fabric: e.target.value })
                        }
                        maxLength={160}
                        disabled={!open || cfg.rolls !== "floor"}
                        aria-label={`${g("f.fabric")} ${i + 1}`}
                      />
                    </td>
                    <td className="min-w-[180px] px-2 py-1.5">
                      {cfg.rolls === "floor" ? (
                        <PSelect
                          value={l.colour_id}
                          onValueChange={(v) =>
                            setL(l.key, {
                              colour_id: v ?? "",
                              colour_name:
                                options.colours.find((x) => x.value === v)
                                  ?.name ?? l.colour_name,
                            })
                          }
                          options={pick(options.colours, l.colour_name || "—")}
                          disabled={!open}
                          aria-label={`${g("f.colour")} ${i + 1}`}
                        />
                      ) : (
                        <span>{l.colour_name || "—"}</span>
                      )}
                    </td>
                    <td className="px-2 py-1.5 text-right tabular-nums">
                      {l[sourceKey] ? 1 : "—"}
                    </td>
                    <td className="px-2 py-1.5">
                      <Input
                        {...numIn}
                        value={l.qty}
                        onChange={(e) => setL(l.key, { qty: e.target.value })}
                        disabled={!open}
                        className="ml-auto w-28 text-right"
                        aria-label={`${g("f.qty")} ${i + 1}`}
                      />
                    </td>
                    <td className="px-2 py-1.5">
                      <Input
                        value={l.remarks}
                        onChange={(e) =>
                          setL(l.key, { remarks: e.target.value })
                        }
                        maxLength={300}
                        disabled={!open}
                        aria-label={`${c("f.remarks")} ${i + 1}`}
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
                  <td className="px-2 py-2" colSpan={3}>
                    {c("total")}
                  </td>
                  <td className="px-2 py-2 text-right tabular-nums">
                    {lines.filter((l) => l[sourceKey]).length}
                  </td>
                  <td className="px-2 py-2 text-right tabular-nums">
                    {qtyFmt.format(total)}
                  </td>
                  <td colSpan={2} />
                </tr>
              </tfoot>
            </table>
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
                      {(l.info?.roll_id ? l.info.roll_no : l.roll_no) || "—"}
                    </td>
                    <td className="px-2 py-2 whitespace-nowrap">
                      {l.info?.order_no ?? "—"}
                    </td>
                    <td className="px-2 py-2">{l.info?.fabric}</td>
                    <td className="px-2 py-2">{l.info?.colour ?? "—"}</td>
                    <td className="px-2 py-2">{l.info?.finish_dia ?? "—"}</td>
                    <td className="px-2 py-2">{l.info?.gsm ?? "—"}</td>
                    <td className="px-2 py-2 text-right tabular-nums">
                      {l.info?.roll_id ? 1 : "—"}
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
                  <td className="px-2 py-2" colSpan={7}>
                    {c("total")}
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
        hint={rollMode ? g(`hint.foot.${kind}`) : c("hint.tr_foot")}
        back={cfg.base}
        onClear={() => show(null)}
        onPrint={doc ? () => printMove(g, c, doc) : null}
        onSave={save}
        saveLabel={c("btn.save_tr")}
        pending={pending}
        open={open}
      />
    </div>
  );
}

/* ---------------- Grey Fabric Racking Board ---------------- */

function GreyRacks({ options, act }) {
  const c = useFs();
  const g = useGt();
  const blank = {
    count: "",
    group: "",
    composition: "",
    spin: "",
    cotton: "",
    brand: "",
    lot: "",
    buyer: "",
    order: "",
  };
  const [f, setFilters] = useState(blank);
  const [rows, setRows] = useState(null);
  const [error, setError] = useState(null);
  const [pending, startTransition] = useTransition();
  const setF = (k, v) => setFilters((x) => ({ ...x, [k]: v ?? "" }));
  const label = (list, v) => list.find((x) => x.value === v)?.label ?? "";

  function search(filters = f) {
    startTransition(async () => {
      const q = new URLSearchParams(
        Object.entries({
          ...filters,
          count: label(options.counts, filters.count),
          composition: label(options.compositions, filters.composition),
        }).filter(([, v]) => v),
      ).toString();
      const res = await act(`racks${q ? `?${q}` : ""}`);
      if (res?.status !== "success") return setError(messagesOf(res));
      setRows(res.data);
      setError(null);
    });
  }
  const racks = rows
    ? Object.entries(
        rows.reduce((a, r) => {
          const k = r.rack
            ? `${r.store} · ${r.rack}`
            : `${r.store} · ${g("unracked")}`;
          (a[k] ??= []).push(r);
          return a;
        }, {}),
      )
    : [];
  const all = (list) => pick(list, g("ph.all"));
  const sel = (k, labelText, list) => (
    <Field label={labelText}>
      <PSelect
        value={f[k]}
        onValueChange={(v) => setF(k, v)}
        options={all(list)}
        aria-label={labelText}
      />
    </Field>
  );

  return (
    <div className="flex flex-col gap-4">
      <section className={cn(card, "p-5")}>
        <h2 className="text-base font-semibold text-foreground">
          {g("sec.yarn_search")}
        </h2>
        <p className="mt-0.5 max-w-4xl text-[13px] text-foreground-muted">
          {g("hint.racks")}
        </p>
        <div className="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
          {sel("count", g("f.count"), options.counts)}
          {sel(
            "group",
            g("f.group"),
            options.colour_groups.map((x) => ({ value: x, label: x })),
          )}
          {sel("composition", g("f.composition"), options.compositions)}
          {sel(
            "spin",
            g("f.spin"),
            options.spin_types.map((x) => ({ value: x, label: x })),
          )}
          {sel(
            "cotton",
            g("f.cotton"),
            options.cotton_types.map((x) => ({ value: x, label: x })),
          )}
          {sel(
            "brand",
            g("f.brand"),
            options.brands.map((x) => ({ value: x, label: x })),
          )}
          <Field label={g("f.lot")}>
            <Input
              value={f.lot}
              onChange={(e) => setF("lot", e.target.value)}
              placeholder={g("ph.all")}
              aria-label={g("f.lot")}
            />
          </Field>
          {sel("buyer", g("f.buyer"), options.buyers)}
          {sel(
            "order",
            g("f.job_style"),
            options.orders.filter((o) => !f.buyer || o.buyer_id === f.buyer),
          )}
        </div>
        <div className="mt-4 flex justify-end gap-2">
          <Button
            variant="outline"
            size="sm"
            onClick={() => (setFilters(blank), setRows(null))}
          >
            <RotateCcw /> {c("btn.reset")}
          </Button>
          <Button size="sm" onClick={() => search()} loading={pending}>
            <Search /> {c("btn.search")}
          </Button>
        </div>
      </section>
      {error ? <Alert variant="danger">{error.join(" · ")}</Alert> : null}
      {rows ? (
        <section className={cn(card, "p-5")}>
          <p className="text-[13px] text-foreground-muted">
            {g("found", { count: rows.length })}
          </p>
          {rows.length === 0 ? (
            <NoLines title={g("empty.racks")} hint={c("empty.hint")} />
          ) : (
            <div className="mt-3 flex flex-col gap-4">
              {racks.map(([rack, list]) => (
                <div
                  key={rack}
                  className="overflow-x-auto rounded-lg border border-border"
                >
                  <div className="flex items-center justify-between bg-surface-muted px-3 py-2 text-sm">
                    <span className="font-semibold">{rack}</span>
                    <span className="text-foreground-muted">
                      {g("rack_total", {
                        rolls: list.reduce((a, r) => a + num(r.rolls), 0),
                        qty: qtyFmt.format(
                          list.reduce((a, r) => a + num(r.balance), 0),
                        ),
                      })}
                    </span>
                  </div>
                  <table className="w-full min-w-[1000px] text-sm">
                    <thead className="bg-brand text-brand-foreground">
                      <tr>
                        {[
                          c("col.roll"),
                          c("col.buyer"),
                          c("col.style_order"),
                          c("col.fabric"),
                          c("col.colour"),
                          g("col.yarn"),
                          g("col.batch_no"),
                          g("col.qty"),
                        ].map((h, i) => (
                          <th
                            key={h}
                            className={cn(th, i === 7 && "text-right")}
                          >
                            {h}
                          </th>
                        ))}
                      </tr>
                    </thead>
                    <tbody>
                      {list.map((r) => (
                        <tr key={r.lot_id} className="border-t border-border">
                          <td className="px-2 py-2 font-mono font-medium whitespace-nowrap">
                            {r.roll_no ?? "—"}
                          </td>
                          <td className="px-2 py-2">
                            {r.buyer ?? r.party ?? "—"}
                          </td>
                          <td className="px-2 py-2 whitespace-nowrap">
                            {r.order_no
                              ? `${r.style ? `${r.style} · ` : ""}${r.order_no}`
                              : "—"}
                          </td>
                          <td className="px-2 py-2">{r.fabric}</td>
                          <td className="px-2 py-2">{r.colour ?? "—"}</td>
                          <td className="px-2 py-2 text-[13px] text-foreground-muted">
                            {r.yarn ?? "—"}
                          </td>
                          <td className="px-2 py-2">{r.batch_no ?? "—"}</td>
                          <td className="px-2 py-2 text-right tabular-nums">
                            {qtyFmt.format(r.balance)}
                          </td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
              ))}
            </div>
          )}
        </section>
      ) : null}
    </div>
  );
}

export { MoveList, MoveEntry, GreyRacks, KIND as MOVE_KINDS };
