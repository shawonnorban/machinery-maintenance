"use client";

import { useState, useTransition } from "react";
import Link from "next/link";
import {
  ArrowLeft,
  CheckCheck,
  Plus,
  Printer,
  RotateCcw,
  Save,
  Search,
  Trash2,
  Warehouse,
  XCircle,
} from "lucide-react";
import { cn } from "@/lib/utils";
import { printDocument } from "@/lib/print-document";
import { Input } from "@/components/ui/input";
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
  QtyInput,
  orNull,
} from "./yarn-common";
import { usePr } from "./prod-order";
import { clock12 } from "@/lib/clock";

/*
 * Knit Production → Production (AnnotechERP 15/04) → Manual Roll Production
 * Entry — the Roll List (Pass QC, Reject, To Grey Store, Print Label) and
 * the Roll Weight Entry: the open knit cards down the left, the card's
 * details above the roll grid, one line per roll — and Daily Knit Fabric
 * Output, the day's output per card, machine and shift against the
 * machine's plan. Nothing reloads.
 */

const QTONE = {
  PENDING: "DRAFT",
  PASSED: "RECEIVED",
  HOLD: "WAITING_APPROVAL",
  REJECTED: "REJECTED",
};
const BASE = "/knit-production/roll-production-manual";
const at = (v) =>
  v ? `${dayFmt(v.slice(0, 10))}, ${clock12(v.slice(11, 16) || "00:00")}` : "—";

/** The roll's tag, as Brickland's Grey Fabric Roll Tag prints it. */
function printLabel(p, r) {
  const c = r.card ?? {};
  printDocument({
    title: p("roll.label_title"),
    docNo: r.roll_code,
    metaColumns: 3,
    meta: [
      [p("roll.lab.buyer"), c.buyer],
      [p("roll.lab.job"), c.buyer_style_order],
      [p("roll.lab.fabric"), c.fabric],
      [p("roll.lab.colour"), c.colour],
      [p("roll.lab.yarn"), c.yarn_details],
      [p("roll.lab.machine"), r.machine],
      [p("roll.lab.dia_gauge"), `${c.machine_dia || "—"} x ${c.gauge || "—"}`],
      [p("roll.lab.finish_dia"), c.finish_dia],
      [p("roll.lab.shift"), r.shift],
      [p("roll.lab.operator"), r.operator],
      [p("roll.lab.date"), at(r.roll_at)],
      [
        p("roll.lab.movement"),
        { v: p(`movement.${r.movement}`), tone: "info" },
      ],
      [
        p("roll.lab.quality"),
        {
          v: p(`quality.${r.quality}`),
          tone:
            r.quality === "REJECTED"
              ? "bad"
              : r.quality === "PASSED"
                ? "ok"
                : "warn",
        },
      ],
      [p("roll.lab.label_info"), r.label_info],
    ],
    sections: [
      {
        title: p("roll.lab.weight"),
        columns: [
          { label: p("roll.lc.weight"), align: "right" },
          { label: p("roll.lc.deducted"), align: "right" },
          { label: p("roll.lc.net"), align: "right" },
        ],
        rows: [
          [
            qtyFmt.format(r.weight),
            qtyFmt.format(r.deducted),
            { v: qtyFmt.format(r.net), bold: true },
          ],
        ],
      },
    ],
    signatures: [p("roll.lab.sign_operator"), p("roll.lab.sign_quality")],
  });
}

function RollList({ options, initialRows, canManage, act }) {
  const p = usePr();
  const toastManager = useToastManager();
  const blank = {
    card: "",
    machine: "",
    movement: "",
    quality: "",
    from: "",
    to: "",
  };
  const [f, setFilters] = useState(blank);
  const [rows, setRows] = useState(initialRows);
  const [error, setError] = useState(null);
  const [pending, startTransition] = useTransition();
  const setF = (k, v) => setFilters((x) => ({ ...x, [k]: v ?? "" }));

  function search(next = f) {
    startTransition(async () => {
      const q = new URLSearchParams(Object.entries(next).filter(([, v]) => v));
      const res = await act(`rolls?${q}`);
      if (res?.status !== "success") return setError(messagesOf(res));
      setRows(res.data);
      setError(null);
    });
  }
  function run(r, action) {
    startTransition(async () => {
      const res = await act(`rolls/${action}`, "POST", { roll: r.roll_code });
      if (res?.status !== "success") return setError(messagesOf(res));
      setRows((all) =>
        all.map((x) => (x.roll_code === r.roll_code ? res.data : x)),
      );
      toastManager.add({
        title: p(`roll.done.${action}`, { no: r.roll_code }),
        type: "success",
      });
    });
  }
  const pick = (list, ph) => [{ value: "", label: ph }, ...list];

  return (
    <div className="flex flex-col gap-4">
      <section className={cn(card, "p-5")}>
        <h2 className="text-base font-semibold text-foreground">
          {p("roll.job_card")}
        </h2>
        <p className="mt-0.5 text-[13px] text-foreground-muted">
          {p("roll.list_hint")}
        </p>
        <div className="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
          <Field label={p("roll.card_no")} hint={p("roll.card_hint")}>
            <PSelect
              value={f.card}
              onValueChange={(v) => setF("card", v)}
              options={pick(options.all_cards, "-- Select Job Card --")}
              aria-label={p("roll.card_no")}
            />
          </Field>
          <Field label={p("roll.mc")}>
            <PSelect
              value={f.machine}
              onValueChange={(v) => setF("machine", v)}
              options={pick(options.machines, "-- Any machine --")}
              aria-label={p("roll.mc")}
            />
          </Field>
          <Field label={p("roll.movement")}>
            <PSelect
              value={f.movement}
              onValueChange={(v) => setF("movement", v)}
              options={pick(
                options.movements.map((m) => ({
                  value: m,
                  label: p(`movement.${m}`),
                })),
                "-- All --",
              )}
              aria-label={p("roll.movement")}
            />
          </Field>
          <Field label={p("roll.quality")}>
            <PSelect
              value={f.quality}
              onValueChange={(v) => setF("quality", v)}
              options={pick(
                options.qualities.map((m) => ({
                  value: m,
                  label: p(`quality.${m}`),
                })),
                "-- All --",
              )}
              aria-label={p("roll.quality")}
            />
          </Field>
          <Field label={p("from")}>
            <Input
              type="date"
              value={f.from}
              onChange={(e) => setF("from", e.target.value)}
              aria-label={p("from")}
            />
          </Field>
          <Field label={p("to")}>
            <Input
              type="date"
              value={f.to}
              onChange={(e) => setF("to", e.target.value)}
              aria-label={p("to")}
            />
          </Field>
        </div>
        <div className="mt-4 flex justify-between gap-2 border-t border-border pt-4">
          <Button onClick={() => search()} loading={pending}>
            <Search /> {p("go")}
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
        {rows.length === 0 ? (
          <NoLines title={p("roll.empty")} hint={p("roll.empty_hint")} />
        ) : (
          <div className="overflow-x-auto">
            <table className="w-full min-w-[1700px] text-sm">
              <thead className="bg-brand text-brand-foreground">
                <tr>
                  {[
                    "roll",
                    "label_info",
                    "at",
                    "shift",
                    "operator",
                    "mc",
                    "weight",
                    "deducted",
                    "net",
                    "movement",
                    "comments",
                    "status",
                  ].map((k) => (
                    <th
                      key={k}
                      className={cn(
                        th,
                        ["weight", "deducted", "net"].includes(k) &&
                          "text-right",
                      )}
                    >
                      {p(`roll.lc.${k}`)}
                    </th>
                  ))}
                  <th className={th}>{p("action_col")}</th>
                </tr>
              </thead>
              <tbody>
                {rows.map((r) => (
                  <tr key={r.roll_code} className="border-t border-border">
                    <td className="px-2 py-2 font-medium whitespace-nowrap">
                      {r.roll_code}
                    </td>
                    <td className="px-2 py-2">{r.label_info ?? "—"}</td>
                    <td className="px-2 py-2 whitespace-nowrap">
                      {at(r.roll_at)}
                    </td>
                    <td className="px-2 py-2">{r.shift ?? "—"}</td>
                    <td className="px-2 py-2">{r.operator ?? "—"}</td>
                    <td className="px-2 py-2">{r.machine ?? "—"}</td>
                    <td className="px-2 py-2 text-right tabular-nums">
                      {qtyFmt.format(r.weight)}
                    </td>
                    <td className="px-2 py-2 text-right tabular-nums">
                      {qtyFmt.format(r.deducted)}
                    </td>
                    <td className="px-2 py-2 text-right font-semibold tabular-nums">
                      {qtyFmt.format(r.net)}
                    </td>
                    <td className="px-2 py-2">{p(`movement.${r.movement}`)}</td>
                    <td className="px-2 py-2">{r.comments ?? "—"}</td>
                    <td className="px-2 py-2">
                      <StatusChip
                        status={QTONE[r.quality]}
                        label={p(`quality.${r.quality}`)}
                      />
                    </td>
                    <td className="px-2 py-1.5">
                      <div className="flex gap-1">
                        {canManage && r.quality !== "PASSED" ? (
                          <Button
                            variant="outline"
                            size="sm"
                            onClick={() => run(r, "pass")}
                            disabled={pending}
                            aria-label={`${p("roll.btn.pass")} ${r.roll_code}`}
                          >
                            <CheckCheck /> {p("roll.btn.pass")}
                          </Button>
                        ) : null}
                        {canManage && r.quality !== "REJECTED" ? (
                          <Button
                            variant="outline"
                            size="sm"
                            className="text-danger"
                            onClick={() => run(r, "reject")}
                            disabled={pending}
                            aria-label={`${p("roll.btn.reject")} ${r.roll_code}`}
                          >
                            <XCircle /> {p("roll.btn.reject")}
                          </Button>
                        ) : null}
                        {canManage &&
                        r.movement === "IN_KNITTING" &&
                        r.quality !== "REJECTED" ? (
                          <Button
                            variant="outline"
                            size="sm"
                            onClick={() => run(r, "grey")}
                            disabled={pending}
                            aria-label={`${p("roll.btn.grey")} ${r.roll_code}`}
                          >
                            <Warehouse /> {p("roll.btn.grey")}
                          </Button>
                        ) : null}
                        <Button
                          variant="outline"
                          size="sm"
                          onClick={() => printLabel(p, r)}
                          aria-label={`${p("roll.btn.label")} ${r.roll_code}`}
                        >
                          <Printer /> {p("roll.btn.label")}
                        </Button>
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

let seq = 0;
const rollLine = (l = {}, today) => ({
  key: `r${++seq}`,
  roll_no: str(l.roll_no),
  roll_date: str(l.roll_date) || today,
  shift_id: str(l.shift_id),
  operator_id: str(l.operator_id),
  sc_party_id: str(l.sc_party_id),
  machine_id: str(l.machine_id),
  weight: str(l.weight),
  deducted: str(l.deducted),
  movement: str(l.movement) || "IN_KNITTING",
  quality: str(l.quality) || "PENDING",
  comments: str(l.comments),
  inspected: Boolean(l.inspected),
});

function RollEntry({ options, initial, canManage, act }) {
  const p = usePr();
  const toastManager = useToastManager();
  const [today] = useState(todayIso);
  const [doc, setDoc] = useState(initial);
  const [cardRow, setCardRow] = useState(initial?.card ?? null);
  const [lines, setLines] = useState(() =>
    (initial?.rolls ?? []).map((l) => rollLine(l, today)),
  );
  const [machine, setMachine] = useState("");
  const [q, setQ] = useState("");
  const [page, setPage] = useState(0);
  const [errors, setErrors] = useState(null);
  const [pending, startTransition] = useTransition();
  const editable = canManage;
  const setLine = (k, patch) =>
    setLines((all) => all.map((l) => (l.key === k ? { ...l, ...patch } : l)));
  const cards = options.cards.filter(
    (c) =>
      (!machine || c.machine_id === machine) &&
      (!q ||
        [c.kc_no, c.buyer, c.fabric, c.colour, c.label]
          .join(" ")
          .toLowerCase()
          .includes(q.toLowerCase())),
  );
  const PER = 8;
  const pages = Math.max(1, Math.ceil(cards.length / PER));
  const total = lines.reduce((t, l) => t + num(l.weight), 0);
  const deducted = lines.reduce((t, l) => t + num(l.deducted), 0);
  const pick = (list) => [{ value: "", label: "—" }, ...list];

  function chooseCard(kc) {
    if (!kc) return (setCardRow(null), setLines([]));
    const local = options.cards.find((c) => c.kc_no === kc);
    if (local) return setCardRow(local);
    startTransition(async () => {
      const res = await act(`rolls/card/${encodeURIComponent(kc)}`);
      if (res?.status === "success") setCardRow(res.data);
    });
  }
  function take(data) {
    setDoc(data);
    setCardRow(data.card);
    setLines(data.rolls.map((l) => rollLine(l, today)));
    setErrors(null);
    window.history.replaceState(
      null,
      "",
      `?tab=entry&ref=${encodeURIComponent(data.sheet_no)}`,
    );
  }
  function clear() {
    setDoc(null);
    setCardRow(null);
    setLines([]);
    setErrors(null);
    window.history.replaceState(null, "", "?tab=entry");
  }
  function load(no) {
    if (!no) return clear();
    startTransition(async () => {
      const res = await act(`roll-sheets/${encodeURIComponent(no)}`);
      if (res?.status === "success") take(res.data);
      else setErrors(messagesOf(res));
    });
  }
  function save() {
    startTransition(async () => {
      const body = {
        kc_no: cardRow?.kc_no ?? null,
        rolls: lines.map((l) => ({
          roll_no: l.roll_no ? Number(l.roll_no) : null,
          roll_date: l.roll_date || null,
          shift_id: l.shift_id || null,
          operator_id: l.operator_id || null,
          sc_party_id: l.sc_party_id || null,
          machine_id: l.machine_id || null,
          weight: orNull(l.weight),
          deducted: orNull(l.deducted),
          movement: l.movement || null,
          quality: l.quality || null,
          comments: l.comments || null,
        })),
      };
      const res = await act(
        doc ? `roll-sheets/${encodeURIComponent(doc.sheet_no)}` : "roll-sheets",
        doc ? "PUT" : "POST",
        body,
      );
      if (res?.status !== "success") return setErrors(messagesOf(res));
      take(res.data);
      toastManager.add({
        title: p("roll.saved", {
          no: res.data.sheet_no,
          count: res.data.rolls.length,
        }),
        type: "success",
      });
    });
  }
  const sel = (l, k, list, w = "w-40", n = 0) => (
    <td className="px-2 py-1.5">
      <div className={w}>
        <PSelect
          value={l[k]}
          onValueChange={(v) => setLine(l.key, { [k]: v ?? "" })}
          options={list}
          disabled={!editable}
          aria-label={`${p(`roll.lc.${k}`)} ${n}`}
        />
      </div>
    </td>
  );

  return (
    <div className="grid gap-4 xl:grid-cols-[22rem_1fr]">
      <section className={cn(card, "self-start")}>
        <div className="flex flex-col gap-3 p-4">
          <Field label={p("roll.mc")}>
            <PSelect
              value={machine}
              onValueChange={(v) => (setMachine(v ?? ""), setPage(0))}
              options={[
                { value: "", label: "-- Any machine --" },
                ...options.machines,
              ]}
              aria-label={p("roll.mc")}
            />
          </Field>
          <div className="relative">
            <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-foreground-muted" />
            <Input
              value={q}
              onChange={(e) => (setQ(e.target.value), setPage(0))}
              placeholder={p("roll.search_ph")}
              className="pl-9"
              aria-label={p("roll.search")}
            />
          </div>
        </div>
        <div className="overflow-x-auto border-t border-border">
          <table className="w-full text-sm">
            <thead className="bg-brand text-brand-foreground">
              <tr>
                {["kc", "buyer", "fabric", "colour", "left"].map((k) => (
                  <th key={k} className={cn(th, k === "left" && "text-right")}>
                    {p(`roll.side.${k}`)}
                  </th>
                ))}
              </tr>
            </thead>
            <tbody>
              {cards.length === 0 ? (
                <tr>
                  <td
                    colSpan={5}
                    className="px-3 py-6 text-center text-foreground-muted"
                  >
                    {p("roll.no_cards")}
                  </td>
                </tr>
              ) : (
                cards.slice(page * PER, page * PER + PER).map((c) => (
                  <tr
                    key={c.kc_no}
                    className={cn(
                      "border-t border-border",
                      cardRow?.kc_no === c.kc_no && "bg-brand-subtle",
                    )}
                  >
                    <td className="px-2 py-2">
                      <button
                        type="button"
                        className="font-medium text-brand hover:underline"
                        onClick={() => !doc && chooseCard(c.kc_no)}
                        disabled={Boolean(doc)}
                      >
                        {c.kc_no}
                      </button>
                    </td>
                    <td className="px-2 py-2">{c.buyer ?? "—"}</td>
                    <td className="px-2 py-2">{c.fabric}</td>
                    <td className="px-2 py-2">{c.colour ?? "—"}</td>
                    <td className="px-2 py-2 text-right tabular-nums">
                      {qtyFmt.format(c.left)}
                    </td>
                  </tr>
                ))
              )}
            </tbody>
          </table>
        </div>
        <div className="flex items-center justify-between px-4 py-3 text-xs text-foreground-muted">
          {p("roll.showing", {
            from: cards.length ? page * PER + 1 : 0,
            to: Math.min(cards.length, page * PER + PER),
            total: cards.length,
          })}
          <div className="flex items-center gap-1">
            <Button
              variant="outline"
              size="sm"
              onClick={() => setPage((x) => Math.max(0, x - 1))}
              disabled={page === 0}
              aria-label={p("prev")}
            >
              ‹
            </Button>
            {page + 1} / {pages}
            <Button
              variant="outline"
              size="sm"
              onClick={() => setPage((x) => Math.min(pages - 1, x + 1))}
              disabled={page >= pages - 1}
              aria-label={p("next")}
            >
              ›
            </Button>
          </div>
        </div>
      </section>

      <div className="flex min-w-0 flex-col gap-4">
        <section className={cn(card, "p-5")}>
          <h2 className="text-base font-semibold text-foreground">
            {p("roll.entry_title")}
          </h2>
          <p className="mt-0.5 text-[13px] text-foreground-muted">
            {p("roll.entry_hint")}
          </p>
          <Field
            label={p("roll.existing")}
            hint={p("roll.existing_hint")}
            className="mt-4"
          >
            <PSelect
              value={doc?.sheet_no ?? ""}
              onValueChange={(v) => load(v ?? "")}
              options={[
                { value: "", label: "-- New roll sheet --" },
                ...options.sheets,
              ]}
              aria-label={p("roll.existing")}
            />
          </Field>
          <fieldset className="mt-4 rounded-lg border border-border p-4">
            <legend className="px-1 text-[13px] font-semibold">
              {p("roll.card_detail")}
            </legend>
            <div className="grid gap-4 md:grid-cols-2">
              <Field label={p("roll.card_no")} required>
                <PSelect
                  value={cardRow?.kc_no ?? ""}
                  onValueChange={(v) => chooseCard(v ?? "")}
                  options={[
                    { value: "", label: "-- Select Job Card --" },
                    ...options.cards.map((c) => ({
                      value: c.kc_no,
                      label: c.label,
                    })),
                    ...(cardRow &&
                    !options.cards.some((c) => c.kc_no === cardRow.kc_no)
                      ? [{ value: cardRow.kc_no, label: cardRow.label }]
                      : []),
                  ]}
                  disabled={!editable || Boolean(doc)}
                  aria-label={p("roll.card_no")}
                />
              </Field>
              <Field label={p("roll.bso")}>
                <Input
                  value={cardRow?.buyer_style_order ?? ""}
                  placeholder={p("roll.bso_ph")}
                  readOnly
                  className="bg-surface-muted"
                  aria-label={p("roll.bso")}
                />
              </Field>
              <Field label={p("roll.yarn")} className="md:col-span-2">
                <Input
                  value={cardRow?.yarn_details ?? ""}
                  readOnly
                  className="bg-surface-muted"
                  aria-label={p("roll.yarn")}
                />
              </Field>
            </div>
          </fieldset>
          <fieldset className="mt-4 rounded-lg border border-border p-4">
            <legend className="px-1 text-[13px] font-semibold">
              {p("roll.machine")}
            </legend>
            <div className="grid gap-4 md:grid-cols-3">
              {["machine_dia", "finish_dia", "gauge"].map((k) => (
                <Field key={k} label={p(`roll.${k}`)}>
                  <Input
                    value={cardRow?.[k] ?? ""}
                    readOnly
                    className="bg-surface-muted"
                    aria-label={p(`roll.${k}`)}
                  />
                </Field>
              ))}
            </div>
            {cardRow ? (
              <p className="mt-3 text-[13px] text-foreground-muted tabular-nums">
                {p("roll.card_figures", {
                  assigned: qtyFmt.format(cardRow.assigned),
                  rolled: qtyFmt.format(cardRow.rolled),
                  left: qtyFmt.format(cardRow.left),
                })}
              </p>
            ) : null}
          </fieldset>
        </section>

        <section className={cn(card, "overflow-hidden")}>
          <div className="flex items-start justify-between gap-3 px-5 py-4">
            <div>
              <h2 className="text-base font-semibold text-foreground">
                {p("roll.lines")}
              </h2>
              <p className="mt-0.5 text-[13px] text-foreground-muted">
                {p("roll.lines_hint")}
              </p>
            </div>
            {editable ? (
              <Button
                variant="outline"
                size="sm"
                onClick={() =>
                  setLines((all) => [
                    ...all,
                    rollLine({ machine_id: cardRow?.machine_id }, today),
                  ])
                }
                disabled={!cardRow}
              >
                <Plus /> {p("roll.add")}
              </Button>
            ) : null}
          </div>
          {lines.length === 0 ? (
            <NoLines title={p("no_lines")} hint={p("roll.no_lines_hint")} />
          ) : (
            <div className="overflow-x-auto">
              <table className="w-full min-w-[2000px] text-sm">
                <thead className="bg-brand text-brand-foreground">
                  <tr>
                    {[
                      "roll_no",
                      "roll_date",
                      "shift_id",
                      "operator_id",
                      "sc_party_id",
                      "machine_id",
                      "weight",
                      "deducted",
                      "movement",
                      "quality",
                      "comments",
                      "remove",
                    ].map((k) => (
                      <th
                        key={k}
                        className={cn(
                          th,
                          ["weight", "deducted"].includes(k) && "text-right",
                        )}
                      >
                        {p(`roll.lc.${k}`)}
                      </th>
                    ))}
                  </tr>
                </thead>
                <tbody>
                  {lines.map((l, i) => (
                    <tr key={l.key} className="border-t border-border">
                      <td className="px-2 py-1.5">
                        <Input
                          value={l.roll_no}
                          onChange={(e) =>
                            setLine(l.key, {
                              roll_no: e.target.value.replace(/\D/g, ""),
                            })
                          }
                          placeholder={p("roll.auto")}
                          className="h-8 w-20"
                          disabled={!editable || l.inspected}
                          aria-label={`${p("roll.lc.roll_no")} ${i + 1}`}
                        />
                      </td>
                      <td className="px-2 py-1.5">
                        <Input
                          type="date"
                          value={l.roll_date}
                          onChange={(e) =>
                            setLine(l.key, { roll_date: e.target.value })
                          }
                          className="h-8 w-36"
                          disabled={!editable}
                          aria-label={`${p("roll.lc.roll_date")} ${i + 1}`}
                        />
                      </td>
                      {sel(l, "shift_id", pick(options.shifts), "w-44", i + 1)}
                      {sel(
                        l,
                        "operator_id",
                        pick(options.employees),
                        "w-48",
                        i + 1,
                      )}
                      {sel(
                        l,
                        "sc_party_id",
                        pick(options.parties),
                        "w-48",
                        i + 1,
                      )}
                      {sel(
                        l,
                        "machine_id",
                        pick(options.machines),
                        "w-28",
                        i + 1,
                      )}
                      <td className="px-2 py-1.5">
                        <div className="flex justify-end">
                          <QtyInput
                            value={l.weight}
                            onChange={(v) => setLine(l.key, { weight: v })}
                            disabled={!editable}
                            label={`${p("roll.lc.weight")} ${i + 1}`}
                          />
                        </div>
                      </td>
                      <td className="px-2 py-1.5">
                        <div className="flex justify-end">
                          <QtyInput
                            value={l.deducted}
                            onChange={(v) => setLine(l.key, { deducted: v })}
                            disabled={!editable}
                            label={`${p("roll.lc.deducted")} ${i + 1}`}
                          />
                        </div>
                      </td>
                      {sel(
                        l,
                        "movement",
                        pick(
                          options.movements.map((m) => ({
                            value: m,
                            label: p(`movement.${m}`),
                          })),
                        ),
                        "w-36",
                        i + 1,
                      )}
                      {sel(
                        l,
                        "quality",
                        pick(
                          options.qualities.map((m) => ({
                            value: m,
                            label: p(`quality.${m}`),
                          })),
                        ),
                        "w-32",
                        i + 1,
                      )}
                      <td className="px-2 py-1.5">
                        <Input
                          value={l.comments}
                          onChange={(e) =>
                            setLine(l.key, { comments: e.target.value })
                          }
                          maxLength={300}
                          className="h-8 w-40"
                          disabled={!editable}
                          aria-label={`${p("roll.lc.comments")} ${i + 1}`}
                        />
                      </td>
                      <td className="px-1 py-1.5">
                        <Button
                          variant="outline"
                          size="icon"
                          className="size-8 text-danger"
                          onClick={() =>
                            setLines((all) =>
                              all.filter((x) => x.key !== l.key),
                            )
                          }
                          disabled={!editable || l.inspected}
                          aria-label={`${p("roll.remove")} ${i + 1}`}
                        >
                          <Trash2 />
                        </Button>
                      </td>
                    </tr>
                  ))}
                </tbody>
                <tfoot className="bg-surface-muted font-semibold">
                  <tr className="border-t-2 border-border">
                    <td colSpan={6} />
                    <td className="px-2 py-2 text-right tabular-nums">
                      {qtyFmt.format(total)}
                    </td>
                    <td className="px-2 py-2 text-right tabular-nums">
                      {qtyFmt.format(deducted)}
                    </td>
                    <td colSpan={4} />
                  </tr>
                </tfoot>
              </table>
            </div>
          )}
          <div className="flex flex-wrap gap-2 border-t border-border px-5 py-3 text-[13px]">
            {[
              [p("roll.weight_kg"), qtyFmt.format(total - deducted)],
              [p("roll.no_of_roll"), lines.length],
            ].map(([k, v]) => (
              <span
                key={k}
                className="inline-flex items-center gap-2 rounded-lg border border-border px-3 py-1"
              >
                {k}
                <span className="rounded-full bg-brand-subtle px-2 text-xs font-semibold text-brand tabular-nums">
                  {v}
                </span>
              </span>
            ))}
          </div>
        </section>

        {errors ? <Alert variant="danger">{errors.join(" · ")}</Alert> : null}
        <section className={cn(card, "flex flex-col gap-3 p-5")}>
          <div className="flex flex-wrap items-center gap-2">
            <Link
              href={BASE}
              className={buttonVariants({ variant: "outline" })}
            >
              <ArrowLeft /> {p("back_list")}
            </Link>
            <span className="text-[13px] font-semibold">
              <span className="text-danger">*</span> {p("roll.action_status")}
            </span>
            <span className="rounded-md border border-border px-2 py-1 text-[13px]">
              {p("roll.weighed")}
            </span>
          </div>
          <p className="text-[13px] text-foreground-muted">{p("roll.foot")}</p>
          <div className="flex flex-wrap gap-2">
            <Button variant="outline" onClick={clear} disabled={pending}>
              <RotateCcw /> {p("clear")}
            </Button>
            {editable ? (
              <Button onClick={save} loading={pending}>
                <Save /> {p("roll.save")}
              </Button>
            ) : null}
          </div>
        </section>
      </div>
    </div>
  );
}

/** Daily Knit Fabric Output: what was knitted, day by day, machine and shift. */
function DailyOutput({ options, initialRows, act }) {
  const p = usePr();
  const blank = {
    from: "",
    to: "",
    buyer: "",
    order: "",
    category: "",
    colour: "",
    floor: "",
  };
  const [f, setFilters] = useState(blank);
  const [rows, setRows] = useState(initialRows);
  const [error, setError] = useState(null);
  const [pending, startTransition] = useTransition();
  const setF = (k, v) => setFilters((x) => ({ ...x, [k]: v ?? "" }));
  const toastManager = useToastManager();

  function search(next = f) {
    startTransition(async () => {
      const q = new URLSearchParams(Object.entries(next).filter(([, v]) => v));
      const res = await act(`output?${q}`);
      if (res?.status !== "success") return setError(messagesOf(res));
      setRows(res.data);
      setError(null);
      toastManager.add({
        title: p("found", { count: res.data.length }),
        type: "success",
      });
    });
  }
  const pick = (list) => [{ value: "", label: "-- Select --" }, ...list];
  const cols = [
    "date",
    "floor",
    "kind",
    "buyer",
    "style",
    "order_no",
    "colour",
    "fabric",
    "finish_dia",
    "gsm",
    "machine",
    "rpm",
    "machine_dia",
    "shift",
    "all_shift",
    "target",
    "total",
    "balance",
  ];
  const NUMS = ["gsm", "rpm", "all_shift", "target", "total", "balance"];

  return (
    <div className="flex flex-col gap-4">
      <section className={cn(card, "p-5")}>
        <p className="text-[13px] text-foreground-muted">{p("out.hint")}</p>
        <div className="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
          <Field label={p("from")}>
            <Input
              type="date"
              value={f.from}
              onChange={(e) => setF("from", e.target.value)}
              aria-label={p("from")}
            />
          </Field>
          <Field label={p("to")}>
            <Input
              type="date"
              value={f.to}
              onChange={(e) => setF("to", e.target.value)}
              aria-label={p("to")}
            />
          </Field>
          <Field label={p("out.buyer")}>
            <PSelect
              value={f.buyer}
              onValueChange={(v) => setF("buyer", v)}
              options={pick(options.buyers)}
              aria-label={p("out.buyer")}
            />
          </Field>
          <Field label={p("out.job")}>
            <PSelect
              value={f.order}
              onValueChange={(v) => setF("order", v)}
              options={pick(options.orders)}
              aria-label={p("out.job")}
            />
          </Field>
          <Field label={p("out.cat")}>
            <PSelect
              value={f.category}
              onValueChange={(v) => setF("category", v)}
              options={pick(options.categories)}
              aria-label={p("out.cat")}
            />
          </Field>
          <Field label={p("out.colour")}>
            <PSelect
              value={f.colour}
              onValueChange={(v) => setF("colour", v)}
              options={[
                { value: "", label: "-- Color --" },
                ...options.colours.map((c) => ({
                  value: c.name,
                  label: c.label,
                })),
              ]}
              aria-label={p("out.colour")}
            />
          </Field>
          <Field label={p("out.floor")}>
            <PSelect
              value={f.floor}
              onValueChange={(v) => setF("floor", v)}
              options={[{ value: "", label: "-- Floor --" }, ...options.floors]}
              aria-label={p("out.floor")}
            />
          </Field>
        </div>
        <div className="mt-4 flex justify-between gap-2 border-t border-border pt-4">
          <Button onClick={() => search()} loading={pending}>
            <Search /> {p("search")}
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
        {rows.length === 0 ? (
          <NoLines title={p("out.empty")} hint={p("out.empty_hint")} />
        ) : (
          <div className="overflow-x-auto">
            <table className="w-full min-w-[2000px] text-sm">
              <thead className="bg-brand text-brand-foreground">
                <tr>
                  {cols.map((k) => (
                    <th
                      key={k}
                      className={cn(th, NUMS.includes(k) && "text-right")}
                    >
                      {p(`out.lc.${k}`)}
                    </th>
                  ))}
                </tr>
              </thead>
              <tbody>
                {rows.map((r, i) => (
                  <tr key={i} className="border-t border-border">
                    {cols.map((k) => (
                      <td
                        key={k}
                        className={cn(
                          "px-2 py-2",
                          NUMS.includes(k) && "text-right tabular-nums",
                          k === "date" && "whitespace-nowrap",
                          k === "total" && "font-semibold",
                        )}
                      >
                        {k === "date"
                          ? dayFmt(r.date)
                          : k === "kind"
                            ? p(`kind_short.${r.kind}`)
                            : [
                                  "all_shift",
                                  "target",
                                  "total",
                                  "balance",
                                ].includes(k)
                              ? r[k] === null
                                ? "—"
                                : qtyFmt.format(r[k])
                              : k === "gsm" && r.gsm !== null
                                ? Number(r.gsm).toFixed(2)
                                : (r[k] ?? "—")}
                      </td>
                    ))}
                  </tr>
                ))}
              </tbody>
              <tfoot className="bg-surface-muted font-semibold">
                <tr className="border-t-2 border-border">
                  <td colSpan={14} className="px-2 py-2">
                    {p("total_row")}
                  </td>
                  <td />
                  <td className="px-2 py-2 text-right tabular-nums">
                    {qtyFmt.format(
                      rows.reduce((t, r) => t + (r.target ?? 0), 0),
                    )}
                  </td>
                  <td className="px-2 py-2 text-right tabular-nums">
                    {qtyFmt.format(rows.reduce((t, r) => t + r.total, 0))}
                  </td>
                  <td />
                </tr>
              </tfoot>
            </table>
          </div>
        )}
      </section>
    </div>
  );
}

export { RollList, RollEntry, DailyOutput };
