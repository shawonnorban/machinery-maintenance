"use client";

import { useState, useTransition } from "react";
import Link from "next/link";
import { Ban, CheckCheck, Plus, Printer, Send } from "lucide-react";
import { cn } from "@/lib/utils";
import { useT } from "@/lib/i18n";
import { Input } from "@/components/ui/input";
import { Textarea } from "@/components/ui/textarea";
import { Button } from "@/components/ui/button";
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
import { TONE, pick, named } from "./prod-yd";
import {
  useShowFilters,
  useMoves,
  ListShell,
  MoveButton,
  SideList,
  FormHead,
  FormFoot,
  RemoveCell,
} from "./ystore-yd";
import { printChallan, lineSection } from "./fstore-sc";

/*
 * Knit Production → Fabric Store (AnnotechERP 15/05), the yarn papers around
 * sub-contract and yarn-dyed work: Out-House Yarn Transfer (a running S/C
 * programme handed to another knitter), Grey Yarn Transfer for Dyeing,
 * Dyed Yarn Receive Challan and Out-House Dyed Yarn Transfer Challan. Each
 * goes Draft → Issued → Finalized from its list, or is Cancelled before it
 * is finalised. A challan travels by its number; nothing reloads.
 */

const KINDS = {
  prog: {
    path: "yarn/prog-transfers",
    base: "/knit-production/yarn-transfer-sc-out-house",
  },
  grey: {
    path: "yarn/grey-transfers",
    base: "/knit-production/grey-yarn-transfer-challan-for-yd",
  },
  dyed: {
    path: "yarn/dyed-receipts",
    base: "/knit-production/dyed-yarn-receive-challan",
  },
  fwd: {
    path: "yarn/dyed-transfers",
    base: "/knit-production/dyed-yarn-trans-challan-sc-out-house-knitting",
  },
};
const enc = encodeURIComponent;
const numIn = { type: "number", min: "0", step: "any" };
let seq = 0;
const lineKey = () => `t${++seq}`;

function useYtr() {
  const t = useT("knitting");
  return (k, p) => t(`ytr.${k}`, p);
}

const statusOpts = (c, options) =>
  options.statuses.map((s) => ({ value: s, label: c(`status.${s}`) }));
const has = (v, q) => (v ?? "").toLowerCase().includes(q.toLowerCase());

/* ---------------- prints ---------------- */

const meta = (c, d, extra) => [
  [c("f.date"), dayFmt(d.challan_date)],
  [c("f.party"), d.party],
  [c("p.address"), d.party_address],
  [c("f.status"), { v: c(`status.${d.status}`), tone: "info" }],
  ...extra,
];

function printDoc(c, kind, d) {
  if (kind === "prog")
    return printChallan(
      c,
      c("p.prog"),
      d.challan_no,
      [
        [c("f.date"), dayFmt(d.challan_date)],
        [c("f.sc_program"), d.program_no],
        [c("f.from_party"), d.from_party],
        [c("f.status"), { v: c(`status.${d.status}`), tone: "info" }],
        [c("f.to_party"), d.party],
        [c("p.address"), d.party_address],
        [c("f.yarn_store"), d.store],
        [c("f.gate_pass"), d.gate_pass_no],
        [c("f.vehicle"), d.vehicle_no],
      ],
      [
        lineSection(c, c("sec.yarn_detail"), d.lines, [
          [c("col.yarn"), (l) => ({ v: l.yarn, bold: true })],
          [c("col.colour"), (l) => l.colour_group],
          [c("col.shade"), (l) => l.shade],
          [c("col.lot"), (l) => l.lot_no],
          [c("col.bags"), (l) => l.bags, true],
        ]),
      ],
      [d.note],
      [c("p.store"), c("p.security"), c("p.received_by")],
    );
  if (kind === "grey")
    return printChallan(
      c,
      c("p.grey"),
      d.challan_no,
      meta(c, d, [
        [c("f.yd_program"), d.program_no],
        [c("f.store"), d.store],
        [c("f.gate_pass"), d.gate_pass_no],
        [c("f.vehicle"), d.vehicle_no],
      ]),
      [
        lineSection(c, c("sec.details"), d.lines, [
          [c("col.shade_prog"), (l) => l.shade],
          [c("col.yarn_item"), (l) => ({ v: l.yarn, bold: true })],
          [c("col.colour"), (l) => l.colour_name],
          [c("col.lot"), (l) => l.lot_no],
          [c("col.pack"), (l) => l.pack_qty, true],
        ]),
      ],
      [d.remarks],
      [c("p.store"), c("p.security"), c("p.dye_house")],
    );
  if (kind === "dyed")
    return printChallan(
      c,
      c("p.dyed"),
      d.challan_no,
      meta(c, d, [
        [c("f.grey_challan"), d.grey_challan],
        [c("f.g_yarn_qty"), qtyFmt.format(d.g_yarn_qty)],
        [c("f.program_ref"), d.program_no],
        [c("f.store"), d.store],
        [c("f.gate_pass"), d.gate_pass_no],
      ]),
      [
        lineSection(c, c("sec.details"), d.lines, [
          [c("col.knit_prog"), (l) => l.knit_prog],
          [c("col.collar_prog"), (l) => l.collar_prog],
          [c("col.yarn_item"), (l) => ({ v: l.yarn, bold: true })],
          [c("col.colour"), (l) => l.colour_name],
          [c("col.lot"), (l) => l.lot_no],
          [c("col.pack"), (l) => l.pack_qty, true],
        ]),
      ],
      [d.remarks],
      [c("p.received_by"), c("p.qc"), c("p.store")],
    );
  return printChallan(
    c,
    c("p.fwd"),
    d.challan_no,
    meta(c, d, [
      [c("f.store"), d.store],
      [c("f.gate_pass"), d.gate_pass_no],
      [c("f.vehicle"), d.vehicle_no],
    ]),
    [
      lineSection(c, c("sec.details"), d.lines, [
        [c("col.receive_no"), (l) => l.receipt_no],
        [c("col.knit_prog"), (l) => l.knit_prog],
        [c("col.collar_prog"), (l) => l.collar_prog],
        [c("col.colour"), (l) => l.colour_name],
        [c("col.yarn_item"), (l) => ({ v: l.yarn, bold: true })],
        [c("col.lot"), (l) => l.lot_no],
        [c("col.pack"), (l) => l.pack_qty, true],
      ]),
    ],
    [d.remarks],
    [c("p.store"), c("p.security"), c("p.received_by")],
  );
}

/* ---------------- the registers ---------------- */

const BLANK = {
  party: "",
  status: "",
  challan: "",
  program: "",
  buyer: "",
  order: "",
  from: "",
  to: "",
};
const FILTERS = {
  prog: ["party", "status", "challan"],
  grey: ["party", "program", "challan", "from", "to", "status"],
  dyed: ["program", "party", "buyer", "order", "from", "to", "status"],
  fwd: ["party", "challan", "from", "to", "status"],
};

function YtrList({ kind, options, initialRows, canManage, act }) {
  const c = useYtr();
  const k = KINDS[kind];
  const flt = useShowFilters(BLANK);
  const [rows, setRows] = useState(initialRows);
  const [error, setError] = useState(null);
  const [move, pending, startTransition] = useMoves(
    c,
    k.path,
    "challan_no",
    setRows,
    setError,
  );
  const o = flt.on;
  const shown = rows.filter(
    (r) =>
      (!o.party ||
        r.party_id === o.party ||
        r.from_party_id === o.party ||
        r.to_party_id === o.party) &&
      (!o.status || r.status === o.status) &&
      (!o.challan || has(r.challan_no, o.challan)) &&
      (!o.program || has(r.program_no, o.program)) &&
      (!o.buyer || r.buyer_id === o.buyer) &&
      (!o.order || r.order_id === o.order) &&
      (!o.from || r.challan_date >= o.from) &&
      (!o.to || r.challan_date <= o.to),
  );
  function print(no) {
    startTransition(async () => {
      const res = await act(`${k.path}/${enc(no)}`);
      if (res?.status !== "success") return setError(messagesOf(res));
      printDoc(c, kind, res.data);
    });
  }
  const text = (key, label, max) => (
    <Field key={key} label={c(label)}>
      <Input
        value={flt.f[key]}
        onChange={(e) => flt.set(key, e.target.value)}
        maxLength={max}
        aria-label={c(label)}
      />
    </Field>
  );
  const date = (key, label) => (
    <Field key={key} label={c(label)}>
      <Input
        type="date"
        value={flt.f[key]}
        onChange={(e) => flt.set(key, e.target.value)}
        aria-label={c(label)}
      />
    </Field>
  );
  const select = (key, label, list) => (
    <Field key={key} label={c(label)}>
      <PSelect
        value={flt.f[key]}
        onValueChange={(v) => flt.set(key, v)}
        options={pick(list, c("ph.select"))}
        aria-label={c(label)}
      />
    </Field>
  );
  const filter = {
    party: () => select("party", "f.party", options.suppliers),
    status: () => select("status", "f.status", statusOpts(c, options)),
    challan: () => text("challan", "f.challan_no", 40),
    program: () =>
      text("program", kind === "dyed" ? "f.program_no" : "f.yd_program", 40),
    buyer: () => select("buyer", "f.buyer", options.buyers),
    order: () =>
      select(
        "order",
        "f.order",
        options.orders.filter(
          (x) => !flt.f.buyer || x.buyer_id === flt.f.buyer,
        ),
      ),
    from: () => date("from", "f.from"),
    to: () => date("to", "f.to"),
  };
  const qty = (key, label) => ({
    key,
    label: c(label),
    num: true,
    text: (r) => qtyFmt.format(r[key]),
  });
  const columns = [
    {
      key: "challan_no",
      label: c("col.challan_no"),
      nowrap: true,
      render: (r) => (
        <span className="flex flex-col items-start gap-1">
          <Link
            href={`${k.base}?tab=entry&ref=${enc(r.challan_no)}`}
            className="font-medium text-brand hover:underline"
          >
            {r.challan_no}
          </Link>
          <StatusChip status={TONE[r.status]} label={c(`status.${r.status}`)} />
        </span>
      ),
      text: (r) => r.challan_no,
    },
    {
      key: "challan_date",
      label: c("col.date"),
      nowrap: true,
      text: (r) => dayFmt(r.challan_date),
    },
    ...{
      prog: [
        { key: "program_no", label: c("col.sc_program"), nowrap: true },
        { key: "from_party", label: c("col.from_party") },
        { key: "to_party", label: c("col.to_party") },
        { key: "bags", label: c("col.bags"), num: true },
        qty("qty", "col.qty"),
      ],
      grey: [
        { key: "party", label: c("col.party") },
        { key: "program_no", label: c("col.yd_program"), nowrap: true },
        { key: "store", label: c("col.store") },
        qty("qty", "col.qty_kg"),
      ],
      dyed: [
        { key: "party", label: c("col.from_party") },
        { key: "grey_challan", label: c("col.grey_challan"), nowrap: true },
        qty("g_yarn_qty", "col.g_yarn_qty"),
        { key: "program_no", label: c("col.program_ref"), nowrap: true },
        { key: "order", label: c("col.order") },
        qty("qty", "col.qty_kg"),
        {
          key: "transferred",
          label: c("col.transfer"),
          text: (r) =>
            r.forwarded > 0
              ? `${c(r.transferred ? "yes" : "part")} · ${qtyFmt.format(r.forwarded)}`
              : c("no"),
        },
      ],
      fwd: [
        { key: "party", label: c("col.party") },
        { key: "store", label: c("col.store") },
        qty("qty", "col.qty_kg"),
      ],
    }[kind],
    { key: "gate_pass_no", label: c("col.gate_pass") },
  ];
  return (
    <ListShell
      c={c}
      error={error}
      onShow={flt.show}
      onReset={flt.reset}
      filters={FILTERS[kind].map((f) => filter[f]())}
    >
      <p className="px-5 pt-4 text-[13px] text-foreground-muted">
        {c(`hint.${kind}_list`)}
      </p>
      <ScTable
        rows={shown}
        columns={columns}
        exportName={`yarn-${kind}-challans`}
        addHref={canManage ? `${k.base}?tab=entry` : null}
        empty={<NoLines title={c(`empty.${kind}`)} hint={c("empty.hint")} />}
        pending={pending}
        actions={(r) => (
          <>
            {canManage && r.status === "DRAFT" ? (
              <MoveButton
                icon={Send}
                label={c("btn.issue")}
                no={r.challan_no}
                onClick={() => move(r.challan_no, "issue", act)}
                disabled={pending}
              />
            ) : null}
            {canManage && r.status === "ISSUED" ? (
              <MoveButton
                icon={CheckCheck}
                label={c("btn.finalize")}
                no={r.challan_no}
                onClick={() => move(r.challan_no, "finalize", act)}
                disabled={pending}
              />
            ) : null}
            {canManage && ["DRAFT", "ISSUED"].includes(r.status) ? (
              <MoveButton
                icon={Ban}
                label={c("btn.cancel")}
                no={r.challan_no}
                onClick={() => move(r.challan_no, "cancel", act)}
                disabled={pending}
                danger
              />
            ) : null}
            <MoveButton
              icon={Printer}
              label={c("btn.print")}
              no={r.challan_no}
              onClick={() => print(r.challan_no)}
              disabled={pending}
            />
          </>
        )}
      />
    </ListShell>
  );
}

/* ---------------- the desks ---------------- */

/**
 * A challan desk's state: the document opened (by number), its head and
 * lines, and save — a draft is the only thing that edits.
 */
function useDesk({ kind, initial, HEAD, LINE, defaults, act, onShown }) {
  const c = useYtr();
  const toastManager = useToastManager();
  const [today] = useState(todayIso);
  const path = KINDS[kind].path;
  const asHead = (d) => ({
    ...Object.fromEntries(HEAD.map((k) => [k, str(d?.[k])])),
    challan_date: str(d?.challan_date) || today,
  });
  const asLine = (l = {}) => ({
    key: lineKey(),
    ...Object.fromEntries(LINE.map((k) => [k, str(l[k])])),
    uom: str(l.uom) || defaults.uom,
    info: l,
  });
  const [doc, setDoc] = useState(initial);
  const [head, setHead] = useState(() => asHead(initial));
  const [lines, setLines] = useState(() => (initial?.lines ?? []).map(asLine));
  const [errors, setErrors] = useState(null);
  const [pending, startTransition] = useTransition();
  const open = defaults.canManage && (!doc || doc.status === "DRAFT");
  const setH = (patch) => setHead((h) => ({ ...h, ...patch }));
  const setL = (k, patch) =>
    setLines((all) => all.map((l) => (l.key === k ? { ...l, ...patch } : l)));
  function show(d) {
    setDoc(d);
    setHead(asHead(d));
    setLines((d?.lines ?? []).map(asLine));
    setErrors(null);
    window.history.replaceState(
      null,
      "",
      d ? `?tab=entry&ref=${enc(d.challan_no)}` : "?tab=entry",
    );
  }
  function load(no) {
    if (!no) return show(null);
    startTransition(async () => {
      const res = await act(`${path}/${enc(no)}`);
      if (res?.status !== "success") return setErrors(messagesOf(res));
      show(res.data);
    });
  }
  function save(extra = {}) {
    startTransition(async () => {
      const body = {
        ...Object.fromEntries(
          HEAD.map((k) => [k, head[k] === "" ? null : head[k]]),
        ),
        ...extra,
        lines: lines.map((l) => ({
          ...Object.fromEntries(
            LINE.map((k) => [k, l[k] === "" ? null : l[k]]),
          ),
          qty: orNull(l.qty),
          ...(LINE.includes("bags") ? { bags: orNull(l.bags) } : {}),
          ...(LINE.includes("pack_qty")
            ? { pack_qty: orNull(l.pack_qty) }
            : {}),
        })),
      };
      const res = await act(
        doc ? `${path}/${enc(doc.challan_no)}` : path,
        doc ? "PUT" : "POST",
        body,
      );
      if (res?.status !== "success") return setErrors(messagesOf(res));
      show(res.data);
      onShown?.();
      toastManager.add({
        title: c("done.saved", { no: res.data.challan_no }),
        type: "success",
      });
    });
  }
  const add = (l) => setLines((all) => [...all, asLine(l)]);
  const remove = (k) => setLines((all) => all.filter((x) => x.key !== k));
  return {
    c,
    canManage: defaults.canManage,
    doc,
    head,
    setH,
    lines,
    setLines,
    setL,
    asLine,
    add,
    remove,
    errors,
    pending,
    open,
    show,
    load,
    save,
  };
}

/** The desk's opening block: title, the locked note, and the existing-challan picker. */
function DeskHead({ d, kind, options, title }) {
  const { c, doc, open, pending, load } = d;
  return (
    <>
      <FormHead
        c={c}
        title={doc ? doc.challan_no : title}
        hint={c(`hint.${kind}_form`)}
        doc={doc}
        status={doc?.status}
        group="status"
      />
      {doc && !open && d.canManage ? (
        <Alert variant="info" className="mt-3">
          {c("hint.locked", {
            no: doc.challan_no,
            status: c(`status.${doc.status}`),
          })}
        </Alert>
      ) : null}
      <Field
        label={c(kind === "dyed" ? "f.existing_receipt" : "f.existing")}
        hint={c("hint.existing")}
        className="mt-4"
      >
        <PSelect
          value={doc?.challan_no ?? ""}
          onValueChange={(v) => load(v ?? "")}
          options={pick(
            options.docs ?? [],
            c(kind === "dyed" ? "ph.new_receipt" : "ph.new"),
          )}
          disabled={pending}
          aria-label={c(kind === "dyed" ? "f.existing_receipt" : "f.existing")}
        />
      </Field>
    </>
  );
}

function ChallanNo({ c, doc }) {
  return (
    <Field label={c("f.challan_no")} hint={c("hint.challan_no")}>
      <Input
        value={doc?.challan_no ?? ""}
        placeholder={c("ph.auto")}
        disabled
        aria-label={c("f.challan_no")}
      />
    </Field>
  );
}

function Company({ c, options }) {
  return (
    <Field label={c("f.company")} required>
      <PSelect
        value={options.company.value}
        options={[options.company]}
        disabled
        aria-label={c("f.company")}
      />
    </Field>
  );
}

function TextIn({ d, k, label, max = 40 }) {
  const { c, head, setH, open } = d;
  return (
    <Field label={c(label)}>
      <Input
        value={head[k]}
        onChange={(e) => setH({ [k]: e.target.value })}
        maxLength={max}
        disabled={!open}
        aria-label={c(label)}
      />
    </Field>
  );
}

function Remarks({ d, k = "remarks", label = "f.remarks" }) {
  const { c, head, setH, open } = d;
  return (
    <Field label={c(label)} className="mt-4">
      <Textarea
        rows={2}
        value={head[k]}
        onChange={(e) => setH({ [k]: e.target.value })}
        maxLength={2000}
        disabled={!open}
        aria-label={c(label)}
      />
    </Field>
  );
}

/** A grid of lines under its heading, with the add button when the draft is open. */
function LinesCard({ d, title, hint, empty, button, onAdd, heads, children }) {
  const { c, open, lines } = d;
  return (
    <section className={cn(card, "p-5")}>
      <div className="flex flex-wrap items-start justify-between gap-3">
        <div>
          <h3 className="text-base font-semibold">{title}</h3>
          <p className="mt-0.5 max-w-3xl text-[13px] text-foreground-muted">
            {hint}
          </p>
        </div>
        {open ? (
          <Button variant="outline" size="sm" onClick={onAdd}>
            <Plus /> {button}
          </Button>
        ) : null}
      </div>
      <div className="mt-4 overflow-x-auto rounded-lg border border-border">
        {lines.length ? (
          <table className="w-full min-w-[1200px] text-sm">
            <thead className="bg-brand text-brand-foreground">
              <tr>
                {heads.map(([k, right]) => (
                  <th key={k} className={cn(th, right && "text-right")}>
                    {c(`col.${k}`)}
                  </th>
                ))}
                <th className={th} />
              </tr>
            </thead>
            <tbody>{children}</tbody>
          </table>
        ) : (
          <NoLines title={c("empty.lines")} hint={empty} />
        )}
      </div>
    </section>
  );
}

/** One line's input cells that every grid shares: pack/bags, qty, unit, remarks. */
function QtyCells({ d, l, n, options, countKey = "pack_qty" }) {
  const { c, setL, open } = d;
  return (
    <>
      <td className="px-2 py-1.5">
        <Input
          {...numIn}
          step="1"
          value={l[countKey]}
          onChange={(e) => setL(l.key, { [countKey]: e.target.value })}
          disabled={!open}
          className="ml-auto w-20 text-right"
          aria-label={`${c(countKey === "bags" ? "col.bags" : "col.pack")} ${n}`}
        />
      </td>
      <td className="px-2 py-1.5">
        <Input
          {...numIn}
          value={l.qty}
          onChange={(e) => setL(l.key, { qty: e.target.value })}
          disabled={!open}
          className="ml-auto w-24 text-right"
          aria-label={`${c("col.qty")} ${n}`}
        />
      </td>
      <td className="min-w-[90px] px-2 py-1.5">
        <PSelect
          value={l.uom}
          onValueChange={(v) => setL(l.key, { uom: v ?? "" })}
          options={options.uoms}
          disabled={!open}
          aria-label={`${c("col.uom")} ${n}`}
        />
      </td>
      <td className="min-w-[140px] px-2 py-1.5">
        <Input
          value={l.remarks}
          onChange={(e) => setL(l.key, { remarks: e.target.value })}
          maxLength={300}
          disabled={!open}
          aria-label={`${c("col.remarks")} ${n}`}
        />
      </td>
      <RemoveCell c={c} n={n} open={open} onClick={() => d.remove(l.key)} />
    </>
  );
}

/** Yarn item and count pickers in one cell. */
function YarnCell({ d, l, n, options }) {
  const { c, setL, open } = d;
  return (
    <td className="min-w-[300px] px-2 py-1.5">
      <div className="flex gap-1.5">
        <PSelect
          value={l.composition_id}
          onValueChange={(v) => setL(l.key, { composition_id: v ?? "" })}
          options={pick(options.compositions, c("ph.composition"))}
          disabled={!open}
          aria-label={`${c("col.yarn_item")} ${n}`}
        />
        <PSelect
          value={l.yarn_count_id}
          onValueChange={(v) => setL(l.key, { yarn_count_id: v ?? "" })}
          options={pick(options.counts, c("ph.count"))}
          disabled={!open}
          aria-label={`${c("col.count")} ${n}`}
        />
      </div>
    </td>
  );
}

function ColourCell({ d, l, n, options }) {
  const { c, setL, open } = d;
  return (
    <td className="min-w-[170px] px-2 py-1.5">
      <PSelect
        value={l.colour_id}
        onValueChange={(v) =>
          setL(l.key, {
            colour_id: v ?? "",
            colour_name: v ? named(options.colours, v) : l.colour_name,
          })
        }
        options={pick(options.colours, l.colour_name || c("ph.colour"))}
        disabled={!open}
        aria-label={`${c("col.colour")} ${n}`}
      />
    </td>
  );
}

const total = (lines) =>
  qtyFmt.format(lines.reduce((a, l) => a + num(l.qty), 0));

/* ---------------- Out-House Yarn Transfer ---------------- */

const PROG_HEAD = [
  "challan_date",
  "program_no",
  "to_party_id",
  "store_id",
  "gate_pass_no",
  "vehicle_no",
  "note",
];
const PROG_LINE = [
  "composition_id",
  "yarn_count_id",
  "colour_group",
  "shade",
  "lot_no",
  "bags",
  "qty",
  "uom",
  "remarks",
];

function ProgEntry({ options, initial, canManage, act }) {
  const d = useDesk({
    kind: "prog",
    initial,
    HEAD: PROG_HEAD,
    LINE: PROG_LINE,
    defaults: { uom: "KG", canManage },
    act,
  });
  const { c, head, setH, open, lines, setL } = d;
  const prog = options.sc_programs.find((p) => p.value === head.program_no);
  const fromParty = prog ? named(options.suppliers, prog.party_id) : null;
  return (
    <div className="flex flex-col gap-4">
      <section className={cn(card, "p-5")}>
        <DeskHead
          d={d}
          kind="prog"
          options={options}
          title={c("tabs.prog_entry")}
        />
        <h3 className="mt-5 text-sm font-semibold">{c("sec.challan_info")}</h3>
        <div className="mt-3 grid gap-4 md:grid-cols-3">
          <ChallanNo c={c} doc={d.doc} />
          <Field label={c("f.challan_date")} required>
            <Input
              type="date"
              value={head.challan_date}
              onChange={(e) => setH({ challan_date: e.target.value })}
              disabled={!open}
              aria-label={c("f.challan_date")}
            />
          </Field>
          <Company c={c} options={options} />
        </div>
        <h3 className="mt-5 text-sm font-semibold">{c("sec.programme")}</h3>
        <div className="mt-3 grid gap-4 md:grid-cols-3">
          <Field label={c("f.sc_program")} required hint={c("hint.sc_program")}>
            <PSelect
              value={head.program_no}
              onValueChange={(v) => setH({ program_no: v ?? "" })}
              options={pick(options.sc_programs, c("ph.programme"))}
              disabled={!open}
              aria-label={c("f.sc_program")}
            />
          </Field>
          <Field label={c("f.from_party")} required>
            <PSelect
              value={prog?.party_id ?? d.doc?.from_party_id ?? ""}
              options={pick(options.suppliers, c("ph.select"))}
              disabled
              aria-label={c("f.from_party")}
            />
          </Field>
          <Field label={c("f.to_party")} required hint={c("hint.to_party")}>
            <PSelect
              value={head.to_party_id}
              onValueChange={(v) => setH({ to_party_id: v ?? "" })}
              options={pick(
                options.suppliers.filter((s) => s.value !== prog?.party_id),
                c("ph.select"),
              )}
              disabled={!open}
              aria-label={c("f.to_party")}
            />
          </Field>
          <Field label={c("f.yarn_store")}>
            <PSelect
              value={head.store_id}
              onValueChange={(v) => setH({ store_id: v ?? "" })}
              options={pick(options.stores, c("ph.store"))}
              disabled={!open}
              aria-label={c("f.yarn_store")}
            />
          </Field>
        </div>
        {fromParty && head.to_party_id ? (
          <p className="mt-3 text-[13px] text-foreground-muted">
            {c("hint.prog_move", {
              no: head.program_no,
              from: fromParty,
              to: named(options.suppliers, head.to_party_id),
            })}
          </p>
        ) : null}
        <h3 className="mt-5 text-sm font-semibold">{c("sec.gate")}</h3>
        <div className="mt-3 grid gap-4 md:grid-cols-3">
          <TextIn d={d} k="gate_pass_no" label="f.gate_pass" />
          <TextIn d={d} k="vehicle_no" label="f.vehicle_prog" />
        </div>
        <Remarks d={d} k="note" label="f.note" />
      </section>
      <LinesCard
        d={d}
        title={c("sec.yarn_detail")}
        hint={c("hint.prog_lines")}
        empty={c("empty.prog_lines")}
        button={c("btn.add_yarn")}
        onAdd={() => d.add()}
        heads={[
          ["yarn"],
          ["colour"],
          ["shade"],
          ["lot"],
          ["bags", true],
          ["qty", true],
          ["uom"],
          ["remarks"],
        ]}
      >
        {lines.map((l, i) => (
          <tr key={l.key} className="border-t border-border">
            <YarnCell d={d} l={l} n={i + 1} options={options} />
            <td className="min-w-[130px] px-2 py-1.5">
              <PSelect
                value={l.colour_group}
                onValueChange={(v) => setL(l.key, { colour_group: v ?? "" })}
                options={pick(
                  options.colour_groups.map((g) => ({ value: g, label: g })),
                  c("ph.group"),
                )}
                disabled={!open}
                aria-label={`${c("col.colour")} ${i + 1}`}
              />
            </td>
            {[
              ["shade", "w-24"],
              ["lot_no", "w-28"],
            ].map(([k, w]) => (
              <td key={k} className="px-2 py-1.5">
                <Input
                  value={l[k]}
                  onChange={(e) => setL(l.key, { [k]: e.target.value })}
                  maxLength={40}
                  disabled={!open}
                  className={w}
                  aria-label={`${c(k === "shade" ? "col.shade" : "col.lot")} ${i + 1}`}
                />
              </td>
            ))}
            <QtyCells d={d} l={l} n={i + 1} options={options} countKey="bags" />
          </tr>
        ))}
      </LinesCard>
      {d.errors ? <Alert variant="danger">{d.errors.join(" · ")}</Alert> : null}
      <FormFoot
        c={c}
        hint={c("hint.prog_foot")}
        back={KINDS.prog.base}
        onClear={() => d.show(null)}
        onPrint={d.doc ? () => printDoc(c, "prog", d.doc) : null}
        onSave={() => d.save()}
        saveLabel={c("btn.save_transfer")}
        pending={d.pending}
        open={open}
      />
    </div>
  );
}

/* ---------------- Grey Yarn Transfer for Dyeing ---------------- */

const GREY_HEAD = [
  "challan_date",
  "gate_pass_no",
  "vehicle_no",
  "party_id",
  "program_no",
  "store_id",
  "remarks",
];
const GREY_LINE = [
  "program_line_id",
  "lot_id",
  "colour_id",
  "colour_name",
  "pack_qty",
  "qty",
  "uom",
  "remarks",
];

function GreyEntry({ options, initial, canManage, act }) {
  const d = useDesk({
    kind: "grey",
    initial,
    HEAD: GREY_HEAD,
    LINE: GREY_LINE,
    defaults: { uom: "KG", canManage },
    act,
  });
  const { c, head, setH, open, lines, setL } = d;
  const prog = options.yd_programs.find((p) => p.value === head.program_no);
  const shades = prog?.lines ?? [];
  // The cones a line may leave from: the store's (or any store's, until one is named).
  const lots = options.lots.filter(
    (x) => !head.store_id || x.store_id === head.store_id,
  );
  function choose(no) {
    if (!open) return;
    const p = options.yd_programs.find((x) => x.value === no);
    if (!p) return;
    setH({ program_no: no, party_id: p.party_id ?? head.party_id });
    d.setLines(
      p.lines
        .filter((s) => s.to_send > 0)
        .map((s) =>
          d.asLine({
            program_line_id: s.value,
            colour_id: s.colour_id,
            colour_name: s.colour_name,
            qty: s.to_send,
            shade: s.label,
          }),
        ),
    );
  }
  function pickShade(l, id) {
    const s = shades.find((x) => x.value === id);
    setL(
      l.key,
      s
        ? {
            program_line_id: id,
            colour_id: str(s.colour_id),
            colour_name: str(s.colour_name),
            qty: String(s.to_send),
          }
        : { program_line_id: "" },
    );
  }
  const lotOpts = (l) => {
    const s = shades.find((x) => x.value === l.program_line_id);
    return lots
      .filter(
        (x) =>
          !s ||
          ((!s.composition_id || x.composition_id === s.composition_id) &&
            (!s.yarn_count_id || x.yarn_count_id === s.yarn_count_id)),
      )
      .map((x) => ({
        value: x.lot_id,
        label: `${x.label} · ${qtyFmt.format(x.balance)} ${x.uom}`,
      }));
  };
  return (
    <div className="grid gap-4 2xl:grid-cols-[400px_minmax(0,1fr)]">
      <SideList
        c={c}
        title={c("sec.yd_list")}
        rows={options.yd_programs.filter((p) => p.to_send > 0)}
        placeholder={c("ph.search_yd")}
        active={head.program_no}
        onPick={choose}
        columns={[
          [c("col.yd_program"), (r) => r.value],
          [c("col.party"), (r) => r.party],
          [c("col.ship_month"), (r) => r.ship_month],
          [c("col.to_send"), (r) => qtyFmt.format(r.to_send), true],
        ]}
      />
      <div className="flex min-w-0 flex-col gap-4">
        <section className={cn(card, "p-5")}>
          <DeskHead
            d={d}
            kind="grey"
            options={options}
            title={c("tabs.grey_entry")}
          />
          <h3 className="mt-5 text-sm font-semibold">
            {c("sec.challan_info")}
          </h3>
          <div className="mt-3 grid gap-4 md:grid-cols-3">
            <ChallanNo c={c} doc={d.doc} />
            <Field label={c("f.challan_date")} required>
              <Input
                type="date"
                value={head.challan_date}
                onChange={(e) => setH({ challan_date: e.target.value })}
                disabled={!open}
                aria-label={c("f.challan_date")}
              />
            </Field>
            <TextIn d={d} k="gate_pass_no" label="f.gate_pass" />
            <Field
              label={c("f.from_party")}
              required
              hint={c("hint.grey_party")}
            >
              <PSelect
                value={head.party_id}
                onValueChange={(v) => setH({ party_id: v ?? "" })}
                options={pick(options.suppliers, c("ph.select"))}
                disabled={!open}
                aria-label={c("f.from_party")}
              />
            </Field>
          </div>
          <h3 className="mt-5 text-sm font-semibold">{c("sec.against")}</h3>
          <div className="mt-3 grid gap-4 md:grid-cols-3">
            <Field label={c("f.yd_program")} hint={c("hint.yd_program")}>
              <PSelect
                value={head.program_no}
                onValueChange={(v) => setH({ program_no: v ?? "" })}
                options={pick(options.yd_programs, c("ph.programme"))}
                disabled={!open}
                aria-label={c("f.yd_program")}
              />
            </Field>
            <Company c={c} options={options} />
            <Field label={c("f.store")} hint={c("hint.grey_store")}>
              <PSelect
                value={head.store_id}
                onValueChange={(v) => setH({ store_id: v ?? "" })}
                options={pick(options.stores, c("ph.store"))}
                disabled={!open}
                aria-label={c("f.store")}
              />
            </Field>
          </div>
          <h3 className="mt-5 text-sm font-semibold">{c("sec.movement")}</h3>
          <div className="mt-3 grid gap-4 md:grid-cols-3">
            <TextIn d={d} k="vehicle_no" label="f.vehicle" />
          </div>
          <Remarks d={d} />
        </section>
        <LinesCard
          d={d}
          title={c("sec.details")}
          hint={c("hint.grey_lines")}
          empty={c("empty.grey_lines")}
          button={c("btn.add_shade")}
          onAdd={() => d.add()}
          heads={[
            ["shade_prog"],
            ["yarn_item"],
            ["colour"],
            ["lot"],
            ["pack", true],
            ["yrn_qty", true],
            ["uom"],
            ["remarks"],
          ]}
        >
          {lines.map((l, i) => {
            const lot = options.lots.find((x) => x.lot_id === l.lot_id);
            return (
              <tr key={l.key} className="border-t border-border">
                <td className="min-w-[200px] px-2 py-1.5">
                  <PSelect
                    value={l.program_line_id}
                    onValueChange={(v) => pickShade(l, v ?? "")}
                    options={pick(shades, c("ph.shade"))}
                    disabled={!open || !prog}
                    aria-label={`${c("col.shade_prog")} ${i + 1}`}
                  />
                </td>
                <td className="min-w-[320px] px-2 py-1.5">
                  <PSelect
                    value={l.lot_id}
                    onValueChange={(v) => setL(l.key, { lot_id: v ?? "" })}
                    options={pick(
                      lotOpts(l),
                      l.info?.yarn && !lot ? l.info.yarn : c("ph.yarn"),
                    )}
                    disabled={!open}
                    aria-label={`${c("col.yarn_item")} ${i + 1}`}
                  />
                </td>
                <ColourCell d={d} l={l} n={i + 1} options={options} />
                <td className="px-2 py-1.5 font-mono whitespace-nowrap">
                  {lot?.lot_no ?? l.info?.lot_no ?? "—"}
                </td>
                <QtyCells d={d} l={l} n={i + 1} options={options} />
              </tr>
            );
          })}
        </LinesCard>
        {lines.length ? (
          <p className="text-right text-[13px] text-foreground-muted">
            {c("total_kg", { qty: total(lines) })}
          </p>
        ) : null}
        {d.errors ? (
          <Alert variant="danger">{d.errors.join(" · ")}</Alert>
        ) : null}
        <FormFoot
          c={c}
          hint={c("hint.grey_foot")}
          back={KINDS.grey.base}
          onClear={() => d.show(null)}
          onPrint={d.doc ? () => printDoc(c, "grey", d.doc) : null}
          onSave={() => d.save()}
          saveLabel={c("btn.save_transfer")}
          pending={d.pending}
          open={open}
        />
      </div>
    </div>
  );
}

/* ---------------- Dyed Yarn Receive Challan ---------------- */

const DYED_HEAD = [
  "challan_date",
  "gate_pass_no",
  "party_id",
  "grey_challan",
  "program_no",
  "buyer_id",
  "order_id",
  "store_id",
  "remarks",
];
const DYED_LINE = [
  "knit_prog",
  "collar_prog",
  "composition_id",
  "yarn_count_id",
  "colour_id",
  "colour_name",
  "lot_no",
  "pack_qty",
  "qty",
  "uom",
  "remarks",
];

function DyedEntry({ options, initial, canManage, act }) {
  const d = useDesk({
    kind: "dyed",
    initial,
    HEAD: DYED_HEAD,
    LINE: DYED_LINE,
    defaults: { uom: "KG", canManage },
    act,
  });
  const { c, head, setH, open, lines, setL } = d;
  const grey = options.grey_challans.find((g) => g.value === head.grey_challan);
  const gQty = grey?.qty ?? d.doc?.g_yarn_qty ?? 0;
  const scProgs = pick(
    options.sc_programs.map((p) => ({ value: p.value, label: p.value })),
    "—",
  );
  function choose(no) {
    if (!open) return;
    const g = options.grey_challans.find((x) => x.value === no);
    if (!g) return;
    setH({
      grey_challan: no,
      party_id: g.party_id,
      program_no: g.program_no ?? head.program_no,
      buyer_id: g.buyer_id ?? head.buyer_id,
      order_id: g.order_id ?? head.order_id,
    });
    d.setLines(g.lines.map((l) => d.asLine(l)));
  }
  return (
    <div className="grid gap-4 2xl:grid-cols-[400px_minmax(0,1fr)]">
      <SideList
        c={c}
        title={c("sec.grey_list")}
        rows={options.grey_challans.filter((g) => g.received < g.qty)}
        placeholder={c("ph.search_grey")}
        active={head.grey_challan}
        onPick={choose}
        columns={[
          [c("col.date"), (r) => dayFmt(r.challan_date)],
          [c("col.challan_no"), (r) => r.value],
          [c("col.from_party"), (r) => r.party],
          [c("col.g_yarn_qty"), (r) => qtyFmt.format(r.qty), true],
        ]}
      />
      <div className="flex min-w-0 flex-col gap-4">
        <section className={cn(card, "p-5")}>
          <DeskHead
            d={d}
            kind="dyed"
            options={options}
            title={c("tabs.dyed_entry")}
          />
          <h3 className="mt-5 text-sm font-semibold">
            {c("sec.challan_info")}
          </h3>
          <div className="mt-3 grid gap-4 md:grid-cols-3">
            <ChallanNo c={c} doc={d.doc} />
            <Field label={c("f.date")} required>
              <Input
                type="date"
                value={head.challan_date}
                onChange={(e) => setH({ challan_date: e.target.value })}
                disabled={!open}
                aria-label={c("f.date")}
              />
            </Field>
            <TextIn d={d} k="gate_pass_no" label="f.gate_pass" />
            <Field label={c("f.party")} required hint={c("hint.dyed_party")}>
              <PSelect
                value={head.party_id}
                onValueChange={(v) => setH({ party_id: v ?? "" })}
                options={pick(options.suppliers, c("ph.select"))}
                disabled={!open}
                aria-label={c("f.party")}
              />
            </Field>
          </div>
          <h3 className="mt-5 text-sm font-semibold">{c("sec.against")}</h3>
          <div className="mt-3 grid gap-4 md:grid-cols-4">
            <Field label={c("f.grey_challan")} hint={c("hint.grey_challan")}>
              <PSelect
                value={head.grey_challan}
                onValueChange={(v) => setH({ grey_challan: v ?? "" })}
                options={pick(
                  options.grey_challans.filter(
                    (g) => !head.party_id || g.party_id === head.party_id,
                  ),
                  c("ph.select"),
                )}
                disabled={!open}
                aria-label={c("f.grey_challan")}
              />
            </Field>
            <Field label={c("f.program_ref")}>
              <PSelect
                value={head.program_no}
                onValueChange={(v) => setH({ program_no: v ?? "" })}
                options={pick(options.yd_programs, c("ph.programme"))}
                disabled={!open}
                aria-label={c("f.program_ref")}
              />
            </Field>
            <Field label={c("f.buyer")}>
              <PSelect
                value={head.buyer_id}
                onValueChange={(v) => setH({ buyer_id: v ?? "", order_id: "" })}
                options={pick(options.buyers, c("ph.select"))}
                disabled={!open}
                aria-label={c("f.buyer")}
              />
            </Field>
            <Field label={c("f.order")}>
              <PSelect
                value={head.order_id}
                onValueChange={(v) => setH({ order_id: v ?? "" })}
                options={pick(
                  options.orders.filter(
                    (o) => !head.buyer_id || o.buyer_id === head.buyer_id,
                  ),
                  c("ph.order"),
                )}
                disabled={!open}
                aria-label={c("f.order")}
              />
            </Field>
          </div>
          <h3 className="mt-5 text-sm font-semibold">{c("sec.booked_to")}</h3>
          <div className="mt-3 grid gap-4 md:grid-cols-3">
            <Company c={c} options={options} />
            <Field label={c("f.store")} hint={c("hint.dyed_store")}>
              <PSelect
                value={head.store_id}
                onValueChange={(v) => setH({ store_id: v ?? "" })}
                options={pick(options.stores, c("ph.store"))}
                disabled={!open}
                aria-label={c("f.store")}
              />
            </Field>
            <Field label={c("f.g_yarn_qty")}>
              <Input
                value={qtyFmt.format(gQty)}
                disabled
                className="text-right"
                aria-label={c("f.g_yarn_qty")}
              />
            </Field>
          </div>
          <Remarks d={d} />
        </section>
        <LinesCard
          d={d}
          title={c("sec.details")}
          hint={c("hint.dyed_lines")}
          empty={c("empty.dyed_lines")}
          button={c("btn.add_shade")}
          onAdd={() => d.add()}
          heads={[
            ["knit_prog"],
            ["collar_prog"],
            ["yarn_item"],
            ["colour"],
            ["lot"],
            ["pack", true],
            ["yrn_qty", true],
            ["uom"],
            ["remarks"],
          ]}
        >
          {lines.map((l, i) => (
            <tr key={l.key} className="border-t border-border">
              {["knit_prog", "collar_prog"].map((k) => (
                <td key={k} className="min-w-[170px] px-2 py-1.5">
                  <PSelect
                    value={l[k]}
                    onValueChange={(v) => setL(l.key, { [k]: v ?? "" })}
                    options={scProgs}
                    disabled={!open}
                    aria-label={`${c(`col.${k}`)} ${i + 1}`}
                  />
                </td>
              ))}
              <YarnCell d={d} l={l} n={i + 1} options={options} />
              <ColourCell d={d} l={l} n={i + 1} options={options} />
              <td className="px-2 py-1.5">
                <Input
                  value={l.lot_no}
                  onChange={(e) => setL(l.key, { lot_no: e.target.value })}
                  maxLength={40}
                  disabled={!open}
                  className="w-28"
                  aria-label={`${c("col.lot")} ${i + 1}`}
                />
              </td>
              <QtyCells d={d} l={l} n={i + 1} options={options} />
            </tr>
          ))}
        </LinesCard>
        {lines.length ? (
          <p className="text-right text-[13px] text-foreground-muted">
            {c("total_vs_grey", {
              qty: total(lines),
              grey: qtyFmt.format(gQty),
            })}
          </p>
        ) : null}
        {d.errors ? (
          <Alert variant="danger">{d.errors.join(" · ")}</Alert>
        ) : null}
        <FormFoot
          c={c}
          hint={c("hint.dyed_foot")}
          back={KINDS.dyed.base}
          onClear={() => d.show(null)}
          onPrint={d.doc ? () => printDoc(c, "dyed", d.doc) : null}
          onSave={() => d.save()}
          saveLabel={c("btn.save_receipt")}
          pending={d.pending}
          open={open}
        />
      </div>
    </div>
  );
}

/* ---------------- Out-House Dyed Yarn Transfer Challan ---------------- */

const FWD_HEAD = [
  "challan_date",
  "gate_pass_no",
  "vehicle_no",
  "party_id",
  "store_id",
  "remarks",
];
const FWD_LINE = [
  "receipt_line_id",
  "knit_prog",
  "collar_prog",
  "pack_qty",
  "qty",
  "uom",
  "remarks",
];

function FwdEntry({ options, initial, initialSource, canManage, act }) {
  const [source, setSource] = useState(initialSource ?? []);
  const d = useDesk({
    kind: "fwd",
    initial,
    HEAD: FWD_HEAD,
    LINE: FWD_LINE,
    defaults: { uom: "KG", canManage },
    act,
    onShown: () =>
      act("yarn/forwardable").then(
        (res) => res?.status === "success" && setSource(res.data),
      ),
  });
  const { c, head, setH, open, lines, setL } = d;
  // Every open receive line, plus the ones this draft already carries.
  const openLines = source.flatMap((r) => r.lines);
  const known = new Map(openLines.map((x) => [x.value, x]));
  for (const l of lines)
    if (
      l.receipt_line_id &&
      !known.has(l.receipt_line_id) &&
      l.info?.receipt_no
    )
      known.set(l.receipt_line_id, {
        value: l.receipt_line_id,
        label: `${l.info.receipt_no} · ${l.info.yarn ?? ""} · Lot ${l.info.lot_no ?? ""}`,
        ...l.info,
      });
  const receiveOpts = [...known.values()].map((x) => ({
    value: x.value,
    label:
      x.left !== undefined
        ? `${x.label} · ${qtyFmt.format(x.left)} ${x.uom}`
        : x.label,
  }));
  const fromLine = (x) => ({
    receipt_line_id: x.value,
    knit_prog: str(x.knit_prog),
    collar_prog: str(x.collar_prog),
    pack_qty: str(x.pack_qty),
    qty: String(x.left),
    uom: x.uom || "KG",
  });
  function include(no) {
    if (!open) return;
    const r = source.find((x) => x.value === no);
    if (!r) return;
    const taken = new Set(lines.map((l) => l.receipt_line_id));
    d.setLines((all) => [
      ...all,
      ...r.lines
        .filter((x) => !taken.has(x.value))
        .map((x) => d.asLine({ ...fromLine(x), ...x })),
    ]);
    if (!head.store_id && r.store_id) setH({ store_id: r.store_id });
  }
  function pickLine(l, id) {
    const x = known.get(id);
    setL(l.key, x ? { ...fromLine(x), info: x } : { receipt_line_id: "" });
  }
  const scProgs = pick(
    options.sc_programs.map((p) => ({ value: p.value, label: p.value })),
    "—",
  );
  return (
    <div className="grid gap-4 2xl:grid-cols-[400px_minmax(0,1fr)]">
      <SideList
        c={c}
        title={c("sec.receive_list")}
        rows={source}
        placeholder={c("ph.search_receive")}
        onPick={include}
        columns={[
          [c("col.date"), (r) => dayFmt(r.challan_date)],
          [c("col.challan_no"), (r) => r.value],
          [c("col.qty_kg"), (r) => qtyFmt.format(r.qty), true],
          [
            "",
            () =>
              open ? (
                <span className="text-xs font-semibold text-brand">
                  {c("btn.include")}
                </span>
              ) : null,
          ],
        ]}
      />
      <div className="flex min-w-0 flex-col gap-4">
        <section className={cn(card, "p-5")}>
          <DeskHead
            d={d}
            kind="fwd"
            options={options}
            title={c("tabs.fwd_entry")}
          />
          <h3 className="mt-5 text-sm font-semibold">
            {c("sec.challan_info")}
          </h3>
          <div className="mt-3 grid gap-4 md:grid-cols-3">
            <ChallanNo c={c} doc={d.doc} />
            <Field label={c("f.date")} required>
              <Input
                type="date"
                value={head.challan_date}
                onChange={(e) => setH({ challan_date: e.target.value })}
                disabled={!open}
                aria-label={c("f.date")}
              />
            </Field>
            <TextIn d={d} k="gate_pass_no" label="f.gate_pass" />
            <Field label={c("f.party")} required hint={c("hint.fwd_party")}>
              <PSelect
                value={head.party_id}
                onValueChange={(v) => setH({ party_id: v ?? "" })}
                options={pick(options.suppliers, c("ph.select"))}
                disabled={!open}
                aria-label={c("f.party")}
              />
            </Field>
          </div>
          <h3 className="mt-5 text-sm font-semibold">{c("sec.movement")}</h3>
          <div className="mt-3 grid gap-4 md:grid-cols-3">
            <Company c={c} options={options} />
            <Field label={c("f.store")}>
              <PSelect
                value={head.store_id}
                onValueChange={(v) => setH({ store_id: v ?? "" })}
                options={pick(options.stores, c("ph.store"))}
                disabled={!open}
                aria-label={c("f.store")}
              />
            </Field>
            <TextIn d={d} k="vehicle_no" label="f.vehicle" />
          </div>
          <Remarks d={d} />
        </section>
        <LinesCard
          d={d}
          title={c("sec.details")}
          hint={c("hint.fwd_lines")}
          empty={c("empty.fwd_lines")}
          button={c("btn.add_line")}
          onAdd={() => d.add()}
          heads={[
            ["receive_line"],
            ["knit_prog"],
            ["collar_prog"],
            ["colour"],
            ["yarn_item"],
            ["lot"],
            ["pack", true],
            ["yrn_qty", true],
            ["uom"],
            ["remarks"],
          ]}
        >
          {lines.map((l, i) => {
            const x = known.get(l.receipt_line_id) ?? l.info ?? {};
            return (
              <tr key={l.key} className="border-t border-border">
                <td className="min-w-[300px] px-2 py-1.5">
                  <PSelect
                    value={l.receipt_line_id}
                    onValueChange={(v) => pickLine(l, v ?? "")}
                    options={pick(receiveOpts, c("ph.receive_line"))}
                    disabled={!open}
                    aria-label={`${c("col.receive_line")} ${i + 1}`}
                  />
                </td>
                {["knit_prog", "collar_prog"].map((k) => (
                  <td key={k} className="min-w-[170px] px-2 py-1.5">
                    <PSelect
                      value={l[k]}
                      onValueChange={(v) => setL(l.key, { [k]: v ?? "" })}
                      options={scProgs}
                      disabled={!open}
                      aria-label={`${c(`col.${k}`)} ${i + 1}`}
                    />
                  </td>
                ))}
                <td className="px-2 py-1.5">{x.colour_name ?? "—"}</td>
                <td className="px-2 py-1.5">{x.yarn ?? "—"}</td>
                <td className="px-2 py-1.5 font-mono whitespace-nowrap">
                  {x.lot_no ?? "—"}
                </td>
                <QtyCells d={d} l={l} n={i + 1} options={options} />
              </tr>
            );
          })}
        </LinesCard>
        {lines.length ? (
          <p className="text-right text-[13px] text-foreground-muted">
            {c("total_kg", { qty: total(lines) })}
          </p>
        ) : null}
        {d.errors ? (
          <Alert variant="danger">{d.errors.join(" · ")}</Alert>
        ) : null}
        <FormFoot
          c={c}
          hint={c("hint.fwd_foot")}
          back={KINDS.fwd.base}
          onClear={() => d.show(null)}
          onPrint={d.doc ? () => printDoc(c, "fwd", d.doc) : null}
          onSave={() => d.save()}
          saveLabel={c("btn.save_transfer")}
          pending={d.pending}
          open={open}
        />
      </div>
    </div>
  );
}

export { YtrList, ProgEntry, GreyEntry, DyedEntry, FwdEntry };
