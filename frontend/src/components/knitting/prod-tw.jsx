"use client";

import { useState, useTransition } from "react";
import Link from "next/link";
import {
  ArrowLeft,
  Eraser,
  FilterX,
  Plus,
  Printer,
  Save,
  Search,
  Trash2,
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
import { yarnKey, yarnName, lotPickers } from "./prod-yt";

/*
 * Knit Production → Production (AnnotechERP 15/04) → Yarn Twisting Entry:
 * the Twist Program list and form (one line per yarn going out single and
 * coming back twisted), Twist Yarn Receive (its challan list beside the
 * form; each line against a programme line, the twisting loss shown) and
 * Yarn Twist Bill (S/C Out-house) (its bill list beside the form; each line
 * a finalised challan or an adjustment, amount = qty × rate). Nothing
 * reloads.
 */

const BASE = "/knit-production/yarn-twist";
const TONE = {
  DRAFT: "DRAFT",
  SUBMITTED: "WAITING_APPROVAL",
  APPROVED: "APPROVED",
  RUNNING: "PLACED",
  CLOSED: "CLOSED",
  PAID: "RECEIVED",
  CANCELLED: "CANCELLED",
};

function useTw() {
  const t = useT("knitting");
  return (k, p) => t(`tw.${k}`, p);
}

const pick = (list, ph) => [{ value: "", label: ph }, ...list];
const named = (list, v) => list.find((x) => x.value === v)?.label ?? null;
const stripCode = (label) => (label ?? "").replace(/\s*\([^)]*\)$/, "");

function printProgram(c, d) {
  const grey = d.lines.reduce((a, l) => a + num(l.grey_qty), 0);
  const recv = d.lines.reduce((a, l) => a + num(l.received_qty), 0);
  const value = d.lines.reduce((a, l) => a + num(l.grey_qty) * num(l.rate), 0);
  printDocument({
    title: c("p.title"),
    docNo: d.program_no,
    orientation: "landscape",
    meta: [
      [c("f.program_date"), dayFmt(d.program_date)],
      [c("f.target_date"), d.target_date ? dayFmt(d.target_date) : null],
      [c("f.company"), d.company],
      [c("f.supplier"), d.supplier],
      [c("p.address"), d.supplier_address],
      [c("f.store"), d.store],
      [c("f.status"), { v: c(`status.${d.status}`), tone: "info" }],
    ],
    metaColumns: 4,
    sections: [
      {
        title: c("p.lines"),
        columns: [
          { label: "#", align: "center", width: "4%" },
          { label: c("col.yarn") },
          { label: c("col.colour_group") },
          { label: c("col.brand") },
          { label: c("col.lot") },
          { label: c("col.tpi"), align: "right" },
          { label: c("col.ply"), align: "right" },
          { label: c("col.grey"), align: "right" },
          { label: c("col.received"), align: "right" },
          { label: c("p.balance"), align: "right" },
          { label: c("col.rate"), align: "right" },
          { label: c("p.value"), align: "right" },
        ],
        rows: d.lines.map((l, i) => [
          i + 1,
          { v: l.yarn ?? "—", bold: true, sub: l.remarks || undefined },
          l.colour_group,
          l.brand,
          l.lot_no,
          l.tpi,
          l.ply,
          `${qtyFmt.format(l.grey_qty)} ${l.uom}`,
          qtyFmt.format(l.received_qty),
          qtyFmt.format(Math.max(0, num(l.grey_qty) - num(l.received_qty))),
          l.rate === null ? "—" : moneyFmt.format(l.rate),
          l.rate === null
            ? "—"
            : moneyFmt.format(num(l.grey_qty) * num(l.rate)),
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
            { v: qtyFmt.format(grey), bold: true },
            { v: qtyFmt.format(recv), bold: true },
            { v: qtyFmt.format(Math.max(0, grey - recv)), bold: true },
            "",
            { v: moneyFmt.format(value), bold: true },
          ],
        ],
        empty: c("p.no_lines"),
      },
    ],
    notes: d.remarks ? [d.remarks] : [],
    signatures: [c("p.sign.prepared"), c("p.sign.store"), c("p.sign.party")],
  });
}

/* ---------------- Twist Program list ---------------- */

function TwList({ options, initialRows, canManage, act }) {
  const c = useTw();
  const blank = { from: "", to: "", supplier: "", status: "", no: "" };
  const [f, setFilters] = useState(blank);
  const [rows, setRows] = useState(initialRows);
  const [error, setError] = useState(null);
  const [pending, startTransition] = useTransition();
  const setF = (k, v) => setFilters((x) => ({ ...x, [k]: v ?? "" }));
  const shown = rows.filter(
    (r) =>
      (!f.from || r.program_date >= f.from) &&
      (!f.to || r.program_date <= f.to) &&
      (!f.supplier || r.supplier_id === f.supplier) &&
      (!f.status || r.status === f.status) &&
      (!f.no || r.program_no.toLowerCase().includes(f.no.toLowerCase())),
  );

  function refresh() {
    startTransition(async () => {
      const res = await act("twist/programs");
      if (res?.status !== "success") return setError(messagesOf(res));
      setRows(res.data);
      setError(null);
    });
  }
  function print(r) {
    startTransition(async () => {
      const res = await act(
        `twist/programs/${encodeURIComponent(r.program_no)}`,
      );
      if (res?.status !== "success") return setError(messagesOf(res));
      printProgram(c, res.data);
    });
  }
  const columns = [
    {
      key: "program_no",
      label: c("col.program_no"),
      nowrap: true,
      render: (r) => (
        <span className="flex flex-col items-start gap-1">
          <Link
            href={`${BASE}?tab=entry&ref=${encodeURIComponent(r.program_no)}`}
            className="font-medium text-brand hover:underline"
          >
            {r.program_no}
          </Link>
          <StatusChip status={TONE[r.status]} label={c(`status.${r.status}`)} />
        </span>
      ),
    },
    {
      key: "program_date",
      label: c("col.prg_date"),
      nowrap: true,
      text: (r) => dayFmt(r.program_date),
    },
    {
      key: "target_date",
      label: c("col.trgt"),
      nowrap: true,
      text: (r) => (r.target_date ? dayFmt(r.target_date) : "—"),
    },
    { key: "supplier", label: c("col.supplier") },
    {
      key: "grey",
      label: c("col.grey_kg"),
      num: true,
      text: (r) => qtyFmt.format(r.grey),
    },
    {
      key: "received",
      label: c("col.received_kg"),
      num: true,
      text: (r) => qtyFmt.format(r.received),
    },
    {
      key: "balance",
      label: c("col.balance_kg"),
      num: true,
      text: (r) => qtyFmt.format(r.balance),
    },
    { key: "remarks", label: c("col.remarks") },
  ];

  return (
    <div className="flex flex-col gap-4">
      <section className={cn(card, "p-5")}>
        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
          <Field label={c("f.date")} className="lg:col-span-2">
            <div className="flex items-center gap-2">
              <Input
                type="date"
                value={f.from}
                onChange={(e) => setF("from", e.target.value)}
                aria-label={c("f.from")}
              />
              <span className="text-sm text-foreground-muted">
                {c("f.to_word")}
              </span>
              <Input
                type="date"
                value={f.to}
                onChange={(e) => setF("to", e.target.value)}
                aria-label={c("f.to")}
              />
            </div>
          </Field>
          <Field label={c("f.supplier")}>
            <PSelect
              value={f.supplier}
              onValueChange={(v) => setF("supplier", v)}
              options={pick(options.suppliers, c("ph.all"))}
              aria-label={c("f.supplier")}
            />
          </Field>
          <Field label={c("f.status")}>
            <PSelect
              value={f.status}
              onValueChange={(v) => setF("status", v)}
              options={pick(
                options.statuses.map((s) => ({
                  value: s,
                  label: c(`status.${s}`),
                })),
                c("ph.all"),
              )}
              aria-label={c("f.status")}
            />
          </Field>
          <Field label={c("f.program_no")}>
            <Input
              value={f.no}
              onChange={(e) => setF("no", e.target.value)}
              placeholder={c("ph.program_no")}
              aria-label={c("f.program_no")}
            />
          </Field>
        </div>
        <div className="mt-4 flex items-center justify-end gap-3 text-[13px] text-foreground-muted">
          {c("shown", { shown: shown.length, total: rows.length })}
          <Button variant="outline" size="sm" onClick={() => setFilters(blank)}>
            <FilterX /> {c("btn.clear")}
          </Button>
          {canManage ? (
            <Link
              href={`${BASE}?tab=entry`}
              className={buttonVariants({ size: "sm" })}
            >
              <Plus /> {c("btn.add_new")}
            </Link>
          ) : null}
        </div>
      </section>
      {error ? <Alert variant="danger">{error.join(" · ")}</Alert> : null}
      <section className={card}>
        <p className="px-5 pt-4 text-[13px] text-foreground-muted">
          {c("hint.list")}
        </p>
        <ScTable
          rows={shown}
          columns={columns}
          exportName="twist-programmes"
          addHref={canManage ? `${BASE}?tab=entry` : null}
          empty={<NoLines title={c("empty.list")} hint="" />}
          onRefresh={refresh}
          pending={pending}
          actions={(r) => (
            <Button
              variant="outline"
              size="sm"
              onClick={() => print(r)}
              disabled={pending}
              aria-label={`${c("btn.print")} ${r.program_no}`}
            >
              <Printer /> {c("btn.print")}
            </Button>
          )}
        />
      </section>
    </div>
  );
}

/* ---------------- Twist Program form ---------------- */

const PLINE = [
  "composition_id",
  "yarn_count_id",
  "colour_group",
  "brand",
  "lot_no",
  "grey_qty",
  "tpi",
  "ply",
  "rate",
  "uom",
  "remarks",
];
const PHEAD = [
  "program_date",
  "target_date",
  "supplier_id",
  "store_id",
  "status",
  "remarks",
];
let seq = 0;
const asPLine = (l = {}) => ({
  key: `w${++seq}`,
  ...Object.fromEntries(PLINE.map((k) => [k, str(l[k])])),
  received_qty: l.received_qty ?? 0,
});

function TwProgram({ options, initial, canManage, act }) {
  const c = useTw();
  const toastManager = useToastManager();
  const [today] = useState(todayIso);
  const asHead = (d) => ({
    ...Object.fromEntries(PHEAD.map((k) => [k, str(d?.[k])])),
    program_date: str(d?.program_date) || today,
    status: str(d?.status) || "DRAFT",
  });
  const [doc, setDoc] = useState(initial);
  const [head, setHead] = useState(() => asHead(initial));
  const [lines, setLines] = useState(() => (initial?.lines ?? []).map(asPLine));
  const [draft, setDraft] = useState(() => asPLine({ uom: "kg" }));
  const [errors, setErrors] = useState(null);
  const [pending, startTransition] = useTransition();
  const linesOpen = canManage && !doc?.received;
  const setH = (patch) => setHead((h) => ({ ...h, ...patch }));
  const setD = (patch) => setDraft((d) => ({ ...d, ...patch }));
  const draftKey = draft.composition_id ? yarnKey(draft) : "";
  const { items, brands } = lotPickers(options, head.store_id, draftKey);
  const grey = lines.reduce((a, l) => a + num(l.grey_qty), 0);

  function show(d) {
    setDoc(d);
    setHead(asHead(d));
    setLines((d?.lines ?? []).map(asPLine));
    setDraft(asPLine({ uom: "kg" }));
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
      const res = await act(`twist/programs/${encodeURIComponent(no)}`);
      if (res?.status !== "success") return setErrors(messagesOf(res));
      show(res.data);
    });
  }
  function addLine() {
    const miss = [
      !(num(draft.grey_qty) > 0) && c("line.need_grey"),
      !draft.uom && c("line.need_uom"),
    ].filter(Boolean);
    if (miss.length) return setErrors(miss);
    setLines((all) => [...all, draft]);
    setDraft(
      asPLine({
        uom: draft.uom,
        tpi: draft.tpi,
        ply: draft.ply,
        rate: draft.rate,
      }),
    );
    setErrors(null);
  }
  function save() {
    startTransition(async () => {
      const body = {
        ...Object.fromEntries(
          PHEAD.map((k) => [k, head[k] === "" ? null : head[k]]),
        ),
        lines: lines.map((l) => ({
          ...Object.fromEntries(
            PLINE.map((k) => [k, l[k] === "" ? null : l[k]]),
          ),
          grey_qty: orNull(l.grey_qty),
          tpi: orNull(l.tpi),
          ply: orNull(l.ply),
          rate: orNull(l.rate),
        })),
      };
      const res = await act(
        doc
          ? `twist/programs/${encodeURIComponent(doc.program_no)}`
          : "twist/programs",
        doc ? "PUT" : "POST",
        body,
      );
      if (res?.status !== "success") return setErrors(messagesOf(res));
      show(res.data);
      toastManager.add({
        title: c("done.saved_program", { no: res.data.program_no }),
        type: "success",
      });
    });
  }
  const num4 = { type: "number", min: "0", step: "any" };

  return (
    <div className="flex flex-col gap-4">
      <section className={cn(card, "p-5")}>
        <div className="flex items-start justify-between gap-3">
          <div>
            <h2 className="text-base font-semibold text-foreground">
              {doc ? doc.program_no : c("tabs.entry")}
            </h2>
            <p className="mt-0.5 text-[13px] text-foreground-muted">
              {c("hint.program_form")}
            </p>
          </div>
          <StatusChip
            status={doc ? TONE[doc.status] : "PLACED"}
            label={doc ? c(`status.${doc.status}`) : c("new_badge")}
          />
        </div>
        {doc?.received && canManage ? (
          <Alert variant="info" className="mt-3">
            {c("hint.program_locked", { no: doc.program_no })}
          </Alert>
        ) : null}
        <Field
          label={c("f.existing_program")}
          hint={c("hint.existing")}
          className="mt-4"
        >
          <PSelect
            value={doc?.program_no ?? ""}
            onValueChange={(v) => open(v ?? "")}
            options={pick(options.programs, c("ph.new_program"))}
            disabled={pending}
            aria-label={c("f.existing_program")}
          />
        </Field>
        <div className="mt-4 grid gap-4 md:grid-cols-3">
          <Field label={c("f.program_date")} required>
            <Input
              type="date"
              value={head.program_date}
              onChange={(e) => setH({ program_date: e.target.value })}
              disabled={!canManage}
              aria-label={c("f.program_date")}
            />
          </Field>
          <Field label={c("f.target_date")}>
            <Input
              type="date"
              value={head.target_date}
              onChange={(e) => setH({ target_date: e.target.value })}
              disabled={!canManage}
              aria-label={c("f.target_date")}
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
          <Field label={c("f.supplier")} required>
            <PSelect
              value={head.supplier_id}
              onValueChange={(v) => setH({ supplier_id: v ?? "" })}
              options={pick(options.suppliers, c("ph.select"))}
              disabled={!canManage || Boolean(doc?.received)}
              aria-label={c("f.supplier")}
            />
          </Field>
          <Field label={c("f.store")} hint={c("hint.store")}>
            <PSelect
              value={head.store_id}
              onValueChange={(v) => setH({ store_id: v ?? "" })}
              options={pick(options.stores, c("ph.store"))}
              disabled={!canManage}
              aria-label={c("f.store")}
            />
          </Field>
          <Field label={c("f.status")} required>
            <PSelect
              value={head.status}
              onValueChange={(v) => setH({ status: v ?? "" })}
              options={pick(
                options.statuses.map((s) => ({
                  value: s,
                  label: c(`status.${s}`),
                })),
                c("ph.choose"),
              )}
              disabled={!canManage}
              aria-label={c("f.status")}
            />
          </Field>
        </div>
        <Field label={c("f.remarks")} className="mt-4">
          <Textarea
            rows={3}
            value={head.remarks}
            onChange={(e) => setH({ remarks: e.target.value })}
            maxLength={2000}
            disabled={!canManage}
            aria-label={c("f.remarks")}
          />
        </Field>
      </section>
      <section className={cn(card, "p-5")}>
        <div className="flex flex-wrap items-start justify-between gap-3">
          <div>
            <h3 className="text-base font-semibold text-foreground">
              {c("sec.yarn")}
            </h3>
            <p className="mt-0.5 max-w-3xl text-[13px] text-foreground-muted">
              {c("hint.lines")}
            </p>
          </div>
          {linesOpen ? (
            <div className="flex gap-2">
              <Button variant="outline" size="sm" onClick={addLine}>
                <Plus /> {c("btn.add_yarn")}
              </Button>
              <Button
                variant="outline"
                size="sm"
                onClick={() => setDraft(asPLine({ uom: "kg" }))}
              >
                {c("btn.cancel_line")}
              </Button>
            </div>
          ) : null}
        </div>
        {linesOpen ? (
          <div className="mt-4 grid gap-4 md:grid-cols-4">
            <Field label={c("f.yarn")}>
              <PSelect
                value={draftKey}
                onValueChange={(v) => {
                  const [composition_id = "", yarn_count_id = ""] = (
                    v ?? ""
                  ).split("|");
                  setD({ composition_id, yarn_count_id, brand: "" });
                }}
                options={pick(items, c("ph.yarn"))}
                aria-label={c("f.yarn")}
              />
            </Field>
            <Field label={c("f.colour_group")}>
              <PSelect
                value={draft.colour_group}
                onValueChange={(v) => setD({ colour_group: v ?? "" })}
                options={pick(
                  options.colour_groups.map((g) => ({ value: g, label: g })),
                  c("ph.group"),
                )}
                aria-label={c("f.colour_group")}
              />
            </Field>
            <Field label={c("f.brand")}>
              <PSelect
                value={draft.brand}
                onValueChange={(v) => setD({ brand: v ?? "" })}
                options={pick(brands, c("ph.brand"))}
                aria-label={c("f.brand")}
              />
            </Field>
            <Field label={c("f.lot")}>
              <Input
                value={draft.lot_no}
                onChange={(e) => setD({ lot_no: e.target.value })}
                maxLength={40}
                aria-label={c("f.lot")}
              />
            </Field>
            {[
              ["grey_qty", "f.grey"],
              ["tpi", "f.tpi"],
              ["ply", "f.ply"],
              ["rate", "f.rate"],
            ].map(([k, label]) => (
              <Field key={k} label={c(label)}>
                <Input
                  {...num4}
                  step={k === "ply" ? "1" : "any"}
                  value={draft[k]}
                  onChange={(e) => setD({ [k]: e.target.value })}
                  aria-label={c(label)}
                />
              </Field>
            ))}
            <Field label={c("f.uom")}>
              <PSelect
                value={draft.uom}
                onValueChange={(v) => setD({ uom: v ?? "" })}
                options={pick(options.uoms, c("ph.choose"))}
                aria-label={c("f.uom")}
              />
            </Field>
            <Field label={c("f.remarks")} className="md:col-span-3">
              <Input
                value={draft.remarks}
                onChange={(e) => setD({ remarks: e.target.value })}
                maxLength={300}
                aria-label={`${c("f.remarks")} (${c("f.yarn")})`}
              />
            </Field>
          </div>
        ) : null}
        <div className="mt-5 overflow-x-auto rounded-lg border border-border">
          {lines.length === 0 ? (
            <NoLines title={c("empty.lines")} hint={c("empty.program_lines")} />
          ) : (
            <table className="w-full min-w-[1000px] text-sm">
              <thead className="bg-brand text-brand-foreground">
                <tr>
                  {[
                    ["yarn"],
                    ["colour_group"],
                    ["brand"],
                    ["lot"],
                    ["grey", true],
                    ["tpi", true],
                    ["ply", true],
                    ["rate", true],
                    ["uom"],
                    ["received", true],
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
                    <td className="px-2 py-2 font-medium">
                      {l.composition_id || l.yarn_count_id
                        ? yarnName(options, l)
                        : "—"}
                    </td>
                    <td className="px-2 py-2">{l.colour_group || "—"}</td>
                    <td className="px-2 py-2">{l.brand || "—"}</td>
                    <td className="px-2 py-2">{l.lot_no || "—"}</td>
                    <td className="px-2 py-2 text-right tabular-nums">
                      {qtyFmt.format(num(l.grey_qty))}
                    </td>
                    <td className="px-2 py-2 text-right tabular-nums">
                      {l.tpi || "—"}
                    </td>
                    <td className="px-2 py-2 text-right tabular-nums">
                      {l.ply || "—"}
                    </td>
                    <td className="px-2 py-2 text-right tabular-nums">
                      {l.rate === "" ? "—" : moneyFmt.format(num(l.rate))}
                    </td>
                    <td className="px-2 py-2">{l.uom}</td>
                    <td className="px-2 py-2 text-right tabular-nums">
                      {qtyFmt.format(num(l.received_qty))}
                    </td>
                    <td className="px-2 py-1.5">
                      {linesOpen ? (
                        <Button
                          variant="ghost"
                          size="sm"
                          className="text-danger"
                          onClick={() =>
                            setLines((all) =>
                              all.filter((x) => x.key !== l.key),
                            )
                          }
                          aria-label={`${c("btn.remove")} ${i + 1}`}
                        >
                          <Trash2 /> {c("btn.remove")}
                        </Button>
                      ) : null}
                    </td>
                  </tr>
                ))}
              </tbody>
              <tfoot>
                <tr className="border-t border-border font-semibold">
                  <td className="px-2 py-2" colSpan={4}>
                    {c("total")}
                  </td>
                  <td className="px-2 py-2 text-right tabular-nums">
                    {qtyFmt.format(grey)}
                  </td>
                  <td colSpan={6} />
                </tr>
              </tfoot>
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
          {c("hint.program_foot")}
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
          {canManage ? (
            <Button onClick={save} loading={pending}>
              <Save /> {c("btn.save_program")}
            </Button>
          ) : null}
        </div>
      </section>
    </div>
  );
}

/* ---------------- the side list both desks share ---------------- */

const PER = 6;

function SideList({
  title,
  rows,
  columns,
  search,
  filterKey,
  filterOptions,
  onPick,
  active,
  c,
}) {
  const [party, setParty] = useState("");
  const [q, setQ] = useState("");
  const [page, setPage] = useState(0);
  const shown = rows.filter(
    (r) =>
      (!party || r[filterKey] === party) &&
      (!q || search(r).join(" ").toLowerCase().includes(q.toLowerCase())),
  );
  const pages = Math.max(1, Math.ceil(shown.length / PER));
  const at = Math.min(page, pages - 1);
  return (
    <section className={cn(card, "p-5")}>
      <h2 className="text-base font-semibold text-foreground">{title}</h2>
      <Field label={c("f.party")} className="mt-3">
        <PSelect
          value={party}
          onValueChange={(v) => (setParty(v ?? ""), setPage(0))}
          options={pick(filterOptions, c("ph.all"))}
          aria-label={c("f.party")}
        />
      </Field>
      <div className="relative mt-3">
        <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-foreground-muted" />
        <Input
          value={q}
          onChange={(e) => (setQ(e.target.value), setPage(0))}
          placeholder={search.placeholder}
          className="pl-9"
          aria-label={search.label}
        />
      </div>
      <div className="mt-3 overflow-x-auto rounded-lg border border-border">
        <table className="w-full text-sm">
          <thead className="bg-brand text-brand-foreground">
            <tr>
              {columns.map(([label, , right]) => (
                <th key={label} className={cn(th, right && "text-right")}>
                  {label}
                </th>
              ))}
            </tr>
          </thead>
          <tbody>
            {shown.length === 0 ? (
              <tr>
                <td
                  colSpan={columns.length}
                  className="px-3 py-8 text-center text-foreground-muted"
                >
                  {c("empty.side")}
                </td>
              </tr>
            ) : (
              shown.slice(at * PER, at * PER + PER).map((r) => (
                <tr
                  key={r.no}
                  onClick={() => onPick(r.no)}
                  className={cn(
                    "cursor-pointer border-t border-border hover:bg-surface-muted",
                    active === r.no && "bg-brand-subtle",
                  )}
                >
                  {columns.map(([label, cell, right]) => (
                    <td
                      key={label}
                      className={cn(
                        "px-2 py-2 whitespace-nowrap",
                        right && "text-right tabular-nums",
                      )}
                    >
                      {cell(r)}
                    </td>
                  ))}
                </tr>
              ))
            )}
          </tbody>
        </table>
      </div>
      <div className="mt-3 flex items-center justify-between text-[13px] text-foreground-muted">
        {c("showing", {
          from: shown.length ? at * PER + 1 : 0,
          to: Math.min(shown.length, at * PER + PER),
          count: shown.length,
        })}
        <div className="flex items-center gap-1">
          <Button
            variant="outline"
            size="sm"
            onClick={() => setPage(Math.max(0, at - 1))}
            disabled={at === 0}
          >
            ‹
          </Button>
          {at + 1} / {pages}
          <Button
            variant="outline"
            size="sm"
            onClick={() => setPage(Math.min(pages - 1, at + 1))}
            disabled={at >= pages - 1}
          >
            ›
          </Button>
        </div>
      </div>
    </section>
  );
}

/* ---------------- Twist Yarn Receive ---------------- */

const RLINE = [
  "program_line_id",
  "colour_group",
  "brand",
  "lot_no",
  "grey_qty",
  "receive_qty",
  "cones",
  "uom",
  "remarks",
];
const asRLine = (l = {}) => ({
  key: `r${++seq}`,
  ...Object.fromEntries(RLINE.map((k) => [k, str(l[k])])),
});

function TwReceive({ options, initialRows, initial, canManage, act }) {
  const c = useTw();
  const toastManager = useToastManager();
  const [today] = useState(todayIso);
  const asHead = (d) => ({
    receive_date: str(d?.receive_date) || today,
    program_no: str(d?.program_no),
    gate_pass_no: str(d?.gate_pass_no),
    store_id: str(d?.store_id),
    finalized: Boolean(d?.finalized),
    transferred: Boolean(d?.transferred),
    remarks: str(d?.remarks),
  });
  const [rows, setRows] = useState(initialRows);
  const [doc, setDoc] = useState(initial);
  const [head, setHead] = useState(() => asHead(initial));
  const [lines, setLines] = useState(() => (initial?.lines ?? []).map(asRLine));
  const [draft, setDraft] = useState(() => asRLine({ uom: "kg" }));
  const [errors, setErrors] = useState(null);
  const [pending, startTransition] = useTransition();
  const locked = Boolean(doc?.finalized);
  const editable = canManage && !locked;
  const setH = (patch) => setHead((h) => ({ ...h, ...patch }));
  const setD = (patch) => setDraft((d) => ({ ...d, ...patch }));
  const program = options.programs.find((p) => p.value === head.program_no);
  const programLines = program?.lines ?? [];
  const open = options.programs.filter(
    (p) =>
      !["DRAFT", "CANCELLED", "CLOSED"].includes(p.status) ||
      p.value === head.program_no,
  );
  const sum = (k) => lines.reduce((a, l) => a + num(l[k]), 0);

  function show(d) {
    setDoc(d);
    setHead(asHead(d));
    setLines((d?.lines ?? []).map(asRLine));
    setDraft(asRLine({ uom: "kg" }));
    setErrors(null);
    window.history.replaceState(
      null,
      "",
      d
        ? `?tab=receive&ref=${encodeURIComponent(d.challan_no)}`
        : "?tab=receive",
    );
  }
  function load(no) {
    if (!no) return show(null);
    startTransition(async () => {
      const res = await act(`twist/receives/${encodeURIComponent(no)}`);
      if (res?.status !== "success") return setErrors(messagesOf(res));
      show(res.data);
    });
  }
  function pickLine(id) {
    const pl = programLines.find((x) => x.value === id);
    setD({
      program_line_id: id ?? "",
      colour_group: str(pl?.colour_group),
      brand: str(pl?.brand),
      lot_no: str(pl?.lot_no),
      uom: str(pl?.uom) || draft.uom,
    });
  }
  function addLine() {
    const miss = [
      !draft.program_line_id && c("line.need_yarn"),
      !(num(draft.grey_qty) > 0) && c("line.need_grey"),
      !draft.uom && c("line.need_uom"),
    ].filter(Boolean);
    if (miss.length) return setErrors(miss);
    setLines((all) => [...all, draft]);
    setDraft(asRLine({ uom: draft.uom }));
    setErrors(null);
  }
  function save() {
    startTransition(async () => {
      const body = {
        ...head,
        program_no: head.program_no || null,
        store_id: head.store_id || null,
        gate_pass_no: head.gate_pass_no || null,
        remarks: head.remarks || null,
        lines: lines.map((l) => ({
          ...Object.fromEntries(
            RLINE.map((k) => [k, l[k] === "" ? null : l[k]]),
          ),
          grey_qty: orNull(l.grey_qty),
          receive_qty: orNull(l.receive_qty),
          cones: orNull(l.cones),
        })),
      };
      const res = await act(
        doc
          ? `twist/receives/${encodeURIComponent(doc.challan_no)}`
          : "twist/receives",
        doc ? "PUT" : "POST",
        body,
      );
      if (res?.status !== "success") return setErrors(messagesOf(res));
      show(res.data);
      const list = await act("twist/receives");
      if (list?.status === "success") setRows(list.data);
      toastManager.add({
        title: c("done.saved_receive", { no: res.data.challan_no }),
        type: "success",
      });
    });
  }
  const yesNo = (v) => (
    <StatusChip status={v ? "YES" : "NO"} label={v ? c("yes") : c("no")} />
  );

  return (
    <div className="grid items-start gap-4 2xl:grid-cols-[440px_minmax(0,1fr)]">
      <SideList
        c={c}
        title={c("sec.receive_list")}
        rows={rows.map((r) => ({ ...r, no: r.challan_no }))}
        filterKey="supplier_id"
        filterOptions={options.suppliers}
        search={Object.assign(
          (r) => [r.challan_no, r.gate_pass_no, r.program_no],
          {
            placeholder: c("ph.search_receive"),
            label: c("f.search_receive"),
          },
        )}
        columns={[
          [c("col.date"), (r) => dayFmt(r.receive_date)],
          [c("col.challan"), (r) => r.challan_no],
          [c("col.gate_pass"), (r) => r.gate_pass_no ?? "—"],
          [c("col.party"), (r) => r.party],
          [c("col.program"), (r) => r.program_no],
          [c("col.g_yarn"), (r) => qtyFmt.format(r.grey), true],
          [c("col.finalized"), (r) => yesNo(r.finalized)],
          [c("col.transferred"), (r) => yesNo(r.transferred)],
        ]}
        onPick={load}
        active={doc?.challan_no}
      />
      <div className="flex min-w-0 flex-col gap-4">
        <section className={cn(card, "p-5")}>
          <div className="flex items-start justify-between gap-3">
            <div>
              <h2 className="text-base font-semibold text-foreground">
                {doc ? doc.challan_no : c("tabs.receive")}
              </h2>
              <p className="mt-0.5 text-[13px] text-foreground-muted">
                {c("hint.receive_form")}
              </p>
            </div>
            <StatusChip
              status={doc ? (locked ? "RECEIVED" : "DRAFT") : "PLACED"}
              label={
                doc
                  ? locked
                    ? c("f.finalized")
                    : c("status.DRAFT")
                  : c("new_badge")
              }
            />
          </div>
          {locked && canManage ? (
            <Alert variant="info" className="mt-3">
              {c("hint.receive_locked", { no: doc.challan_no })}
            </Alert>
          ) : null}
          <Field
            label={c("f.existing_receive")}
            hint={c("hint.existing")}
            className="mt-4"
          >
            <PSelect
              value={doc?.challan_no ?? ""}
              onValueChange={(v) => load(v ?? "")}
              options={pick(
                rows.map((r) => ({
                  value: r.challan_no,
                  label: `${r.challan_no} — ${dayFmt(r.receive_date)}`,
                })),
                c("ph.new_receive"),
              )}
              disabled={pending}
              aria-label={c("f.existing_receive")}
            />
          </Field>
          <h3 className="mt-5 mb-3 text-sm font-semibold text-foreground">
            {c("sec.challan")}
          </h3>
          <div className="grid gap-4 md:grid-cols-3">
            <Field label={c("f.date")} required>
              <Input
                type="date"
                value={head.receive_date}
                onChange={(e) => setH({ receive_date: e.target.value })}
                disabled={!editable}
                aria-label={c("f.date")}
              />
            </Field>
            <Field label={c("f.program")} required>
              <PSelect
                value={head.program_no}
                onValueChange={(v) => {
                  const p = options.programs.find((x) => x.value === v);
                  setH({
                    program_no: v ?? "",
                    store_id: head.store_id || str(p?.store_id),
                  });
                  setLines([]);
                  setDraft(asRLine({ uom: "kg" }));
                }}
                options={pick(open, c("ph.select"))}
                disabled={!editable || Boolean(doc)}
                aria-label={c("f.program")}
              />
            </Field>
            <Field label={c("f.gate_pass")} hint={c("hint.gate_pass")}>
              <Input
                value={head.gate_pass_no}
                onChange={(e) => setH({ gate_pass_no: e.target.value })}
                maxLength={40}
                disabled={!editable}
                aria-label={c("f.gate_pass")}
              />
            </Field>
          </div>
          <h3 className="mt-5 mb-3 text-sm font-semibold text-foreground">
            {c("sec.from_to")}
          </h3>
          <div className="grid gap-4 md:grid-cols-3">
            <Field label={c("f.party")} required>
              <PSelect
                value={program?.supplier_id ?? ""}
                options={pick(options.suppliers, c("ph.select"))}
                disabled
                aria-label={`${c("f.party")} (${c("f.program")})`}
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
                disabled={!editable}
                aria-label={c("f.store")}
              />
            </Field>
          </div>
          <h3 className="mt-5 mb-3 text-sm font-semibold text-foreground">
            {c("sec.standing")}
          </h3>
          <div className="grid gap-4 md:grid-cols-2">
            {[
              ["finalized", !editable],
              ["transferred", !canManage],
            ].map(([k, off]) => (
              <div key={k} className="flex items-start gap-2">
                <Checkbox
                  checked={head[k]}
                  onCheckedChange={(v) => setH({ [k]: Boolean(v) })}
                  disabled={off}
                  aria-label={c(`f.${k}`)}
                />
                <span>
                  <span className="block text-[13px] font-semibold text-foreground">
                    {c(`f.${k}`)}
                  </span>
                  <span className="text-xs text-foreground-muted">
                    {c(`hint.${k}`)}
                  </span>
                </span>
              </div>
            ))}
          </div>
          <Field label={c("f.remarks")} className="mt-4">
            <Textarea
              rows={2}
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
                {c("sec.detail")}
              </h3>
              <p className="mt-0.5 max-w-3xl text-[13px] text-foreground-muted">
                {c("hint.receive_lines")}
              </p>
            </div>
            {editable ? (
              <div className="flex gap-2">
                <Button variant="outline" size="sm" onClick={addLine}>
                  <Plus /> {c("btn.add_line")}
                </Button>
                <Button
                  variant="outline"
                  size="sm"
                  onClick={() => setDraft(asRLine({ uom: "kg" }))}
                >
                  {c("btn.cancel_line")}
                </Button>
              </div>
            ) : null}
          </div>
          {editable ? (
            <div className="mt-4 grid gap-4 md:grid-cols-4">
              <Field label={c("f.yarn")} className="md:col-span-2">
                <PSelect
                  value={draft.program_line_id}
                  onValueChange={pickLine}
                  options={pick(programLines, c("ph.yarn"))}
                  disabled={!program}
                  aria-label={c("f.yarn")}
                />
              </Field>
              <Field label={c("f.colour_group")}>
                <PSelect
                  value={draft.colour_group}
                  onValueChange={(v) => setD({ colour_group: v ?? "" })}
                  options={pick(
                    options.colour_groups.map((g) => ({ value: g, label: g })),
                    c("ph.group"),
                  )}
                  aria-label={c("f.colour_group")}
                />
              </Field>
              <Field label={c("f.brand")}>
                <Input
                  value={draft.brand}
                  onChange={(e) => setD({ brand: e.target.value })}
                  maxLength={80}
                  aria-label={c("f.brand")}
                />
              </Field>
              <Field label={c("f.lot")}>
                <Input
                  value={draft.lot_no}
                  onChange={(e) => setD({ lot_no: e.target.value })}
                  maxLength={40}
                  aria-label={c("f.lot")}
                />
              </Field>
              {[
                ["grey_qty", "f.grey"],
                ["receive_qty", "f.receive"],
                ["cones", "f.cones"],
              ].map(([k, label]) => (
                <Field key={k} label={c(label)}>
                  <Input
                    type="number"
                    min="0"
                    step={k === "cones" ? "1" : "any"}
                    value={draft[k]}
                    onChange={(e) => setD({ [k]: e.target.value })}
                    aria-label={c(label)}
                  />
                </Field>
              ))}
              <Field label={c("f.uom")}>
                <PSelect
                  value={draft.uom}
                  onValueChange={(v) => setD({ uom: v ?? "" })}
                  options={pick(options.uoms, c("ph.choose"))}
                  aria-label={c("f.uom")}
                />
              </Field>
              <Field label={c("f.remarks")} className="md:col-span-3">
                <Input
                  value={draft.remarks}
                  onChange={(e) => setD({ remarks: e.target.value })}
                  maxLength={300}
                  aria-label={`${c("f.remarks")} (${c("f.yarn")})`}
                />
              </Field>
            </div>
          ) : null}
          <div className="mt-5 overflow-x-auto rounded-lg border border-border">
            {lines.length === 0 ? (
              <NoLines
                title={c("empty.lines")}
                hint={c("empty.receive_lines")}
              />
            ) : (
              <table className="w-full min-w-[900px] text-sm">
                <thead className="bg-brand text-brand-foreground">
                  <tr>
                    {[
                      ["yarn"],
                      ["colour_group"],
                      ["brand"],
                      ["lot"],
                      ["grey", true],
                      ["receive", true],
                      ["loss", true],
                      ["cones", true],
                      ["uom"],
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
                      <td className="px-2 py-2 font-medium">
                        {named(programLines, l.program_line_id) ?? "—"}
                      </td>
                      <td className="px-2 py-2">{l.colour_group || "—"}</td>
                      <td className="px-2 py-2">{l.brand || "—"}</td>
                      <td className="px-2 py-2">{l.lot_no || "—"}</td>
                      <td className="px-2 py-2 text-right tabular-nums">
                        {qtyFmt.format(num(l.grey_qty))}
                      </td>
                      <td className="px-2 py-2 text-right tabular-nums">
                        {qtyFmt.format(num(l.receive_qty))}
                      </td>
                      <td className="px-2 py-2 text-right tabular-nums">
                        {qtyFmt.format(num(l.grey_qty) - num(l.receive_qty))}
                      </td>
                      <td className="px-2 py-2 text-right tabular-nums">
                        {l.cones || "—"}
                      </td>
                      <td className="px-2 py-2">{l.uom}</td>
                      <td className="px-2 py-1.5">
                        {editable ? (
                          <Button
                            variant="ghost"
                            size="sm"
                            className="text-danger"
                            onClick={() =>
                              setLines((all) =>
                                all.filter((x) => x.key !== l.key),
                              )
                            }
                            aria-label={`${c("btn.remove")} ${i + 1}`}
                          >
                            <Trash2 /> {c("btn.remove")}
                          </Button>
                        ) : null}
                      </td>
                    </tr>
                  ))}
                </tbody>
                <tfoot>
                  <tr className="border-t border-border font-semibold">
                    <td className="px-2 py-2" colSpan={4}>
                      {c("total")}
                    </td>
                    <td className="px-2 py-2 text-right tabular-nums">
                      {qtyFmt.format(sum("grey_qty"))}
                    </td>
                    <td className="px-2 py-2 text-right tabular-nums">
                      {qtyFmt.format(sum("receive_qty"))}
                    </td>
                    <td className="px-2 py-2 text-right tabular-nums">
                      {qtyFmt.format(sum("grey_qty") - sum("receive_qty"))}
                    </td>
                    <td colSpan={3} />
                  </tr>
                </tfoot>
              </table>
            )}
          </div>
        </section>
        {errors ? <Alert variant="danger">{errors.join(" · ")}</Alert> : null}
        <section className={cn(card, "flex justify-end gap-2 p-4")}>
          <Button
            variant="outline"
            onClick={() => show(null)}
            disabled={pending}
          >
            <Eraser /> {c("btn.clear")}
          </Button>
          {canManage ? (
            <Button onClick={save} loading={pending}>
              <Save /> {c("btn.save_receive")}
            </Button>
          ) : null}
        </section>
      </div>
    </div>
  );
}

/* ---------------- Yarn Twist Bill ---------------- */

const BLINE = [
  "challan_no",
  "particulars",
  "composition_id",
  "yarn_count_id",
  "qty",
  "rate",
  "uom",
  "remarks",
];
const asBLine = (l = {}) => ({
  key: `b${++seq}`,
  ...Object.fromEntries(BLINE.map((k) => [k, str(l[k])])),
});

function TwBill({ options, initialRows, initial, canManage, act }) {
  const c = useTw();
  const toastManager = useToastManager();
  const [today] = useState(todayIso);
  const asHead = (d) => ({
    bill_date: str(d?.bill_date) || today,
    supplier_id: str(d?.supplier_id),
    program_no: str(d?.program_no),
    party_bill_no: str(d?.party_bill_no),
    status: str(d?.status) || "DRAFT",
    remarks: str(d?.remarks),
  });
  const [rows, setRows] = useState(initialRows);
  const [doc, setDoc] = useState(initial);
  const [head, setHead] = useState(() => asHead(initial));
  const [lines, setLines] = useState(() => (initial?.lines ?? []).map(asBLine));
  const [draft, setDraft] = useState(() => asBLine({ uom: "kg" }));
  const [errors, setErrors] = useState(null);
  const [pending, startTransition] = useTransition();
  const editable =
    canManage && !(doc && ["APPROVED", "PAID"].includes(doc.status));
  const setH = (patch) => setHead((h) => ({ ...h, ...patch }));
  const setD = (patch) => setDraft((d) => ({ ...d, ...patch }));
  const programs = options.programs.filter(
    (p) => !head.supplier_id || p.supplier_id === head.supplier_id,
  );
  const challans = options.challans.filter(
    (x) =>
      x.finalized &&
      (!head.supplier_id || x.supplier_id === head.supplier_id) &&
      (!head.program_no || x.program === head.program_no),
  );
  const draftKey = draft.composition_id ? yarnKey(draft) : "";
  const { items } = lotPickers(options, null, draftKey);
  const total = lines.reduce((a, l) => a + num(l.qty) * num(l.rate), 0);

  function show(d) {
    setDoc(d);
    setHead(asHead(d));
    setLines((d?.lines ?? []).map(asBLine));
    setDraft(asBLine({ uom: "kg" }));
    setErrors(null);
    window.history.replaceState(
      null,
      "",
      d ? `?tab=bill&ref=${encodeURIComponent(d.bill_no)}` : "?tab=bill",
    );
  }
  function load(no) {
    if (!no) return show(null);
    startTransition(async () => {
      const res = await act(`twist/bills/${encodeURIComponent(no)}`);
      if (res?.status !== "success") return setErrors(messagesOf(res));
      show(res.data);
    });
  }
  function pickChallan(no) {
    const ch = options.challans.find((x) => x.value === no);
    const prog = options.programs.find((p) => p.value === ch?.program);
    setD({
      challan_no: no ?? "",
      qty: ch ? String(ch.qty) : draft.qty,
      rate: draft.rate || str(prog?.lines?.[0]?.rate),
    });
  }
  function addLine() {
    const miss = [
      !draft.challan_no &&
        !draft.particulars.trim() &&
        c("line.need_particulars"),
      !(num(draft.qty) > 0) && c("line.need_qty"),
      draft.rate === "" && c("line.need_rate"),
      !draft.uom && c("line.need_uom"),
    ].filter(Boolean);
    if (miss.length) return setErrors(miss);
    setLines((all) => [...all, draft]);
    setDraft(asBLine({ uom: draft.uom, rate: draft.rate }));
    setErrors(null);
  }
  function save() {
    startTransition(async () => {
      const body = {
        ...head,
        program_no: head.program_no || null,
        party_bill_no: head.party_bill_no || null,
        supplier_id: head.supplier_id || null,
        remarks: head.remarks || null,
        lines: lines.map((l) => ({
          ...Object.fromEntries(
            BLINE.map((k) => [k, l[k] === "" ? null : l[k]]),
          ),
          qty: orNull(l.qty),
          rate: orNull(l.rate),
        })),
      };
      const res = await act(
        doc ? `twist/bills/${encodeURIComponent(doc.bill_no)}` : "twist/bills",
        doc ? "PUT" : "POST",
        body,
      );
      if (res?.status !== "success") return setErrors(messagesOf(res));
      show(res.data);
      const list = await act("twist/bills");
      if (list?.status === "success") setRows(list.data);
      toastManager.add({
        title: c("done.saved_bill", { no: res.data.bill_no }),
        type: "success",
      });
    });
  }

  return (
    <div className="grid items-start gap-4 2xl:grid-cols-[400px_minmax(0,1fr)]">
      <SideList
        c={c}
        title={c("sec.bill_list")}
        rows={rows.map((r) => ({ ...r, no: r.bill_no }))}
        filterKey="supplier_id"
        filterOptions={options.suppliers}
        search={Object.assign((r) => [r.bill_no, r.party_bill_no], {
          placeholder: c("ph.search_bill"),
          label: c("f.search_bill"),
        })}
        columns={[
          [c("col.date"), (r) => dayFmt(r.bill_date)],
          [c("col.bill"), (r) => r.bill_no],
          [c("col.supplier"), (r) => stripCode(r.supplier)],
          [c("col.amount"), (r) => moneyFmt.format(r.amount), true],
          [
            c("col.status"),
            (r) => (
              <StatusChip
                status={TONE[r.status]}
                label={c(`bill_status.${r.status}`)}
              />
            ),
          ],
        ]}
        onPick={load}
        active={doc?.bill_no}
      />
      <div className="flex min-w-0 flex-col gap-4">
        <section className={cn(card, "p-5")}>
          <div className="flex items-start justify-between gap-3">
            <div>
              <h2 className="text-base font-semibold text-foreground">
                {doc ? doc.bill_no : c("tabs.bill")}
              </h2>
              <p className="mt-0.5 text-[13px] text-foreground-muted">
                {c("hint.bill_form")}
              </p>
            </div>
            <StatusChip
              status={doc ? TONE[doc.status] : "PLACED"}
              label={doc ? c(`bill_status.${doc.status}`) : c("new_badge")}
            />
          </div>
          {doc && !editable && canManage ? (
            <Alert variant="info" className="mt-3">
              {c("hint.bill_locked", {
                no: doc.bill_no,
                status: c(`bill_status.${doc.status}`),
              })}
            </Alert>
          ) : null}
          <Field
            label={c("f.existing_bill")}
            hint={c("hint.existing")}
            className="mt-4"
          >
            <PSelect
              value={doc?.bill_no ?? ""}
              onValueChange={(v) => load(v ?? "")}
              options={pick(options.bills, c("ph.new_bill"))}
              disabled={pending}
              aria-label={c("f.existing_bill")}
            />
          </Field>
          <div className="mt-4 grid gap-4 md:grid-cols-3">
            <Field label={c("f.date")} required>
              <Input
                type="date"
                value={head.bill_date}
                onChange={(e) => setH({ bill_date: e.target.value })}
                disabled={!editable}
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
            <Field label={c("f.supplier")} required>
              <PSelect
                value={head.supplier_id}
                onValueChange={(v) =>
                  setH({ supplier_id: v ?? "", program_no: "" })
                }
                options={pick(options.suppliers, c("ph.select"))}
                disabled={!editable}
                aria-label={c("f.supplier")}
              />
            </Field>
            <Field label={c("f.program")}>
              <PSelect
                value={head.program_no}
                onValueChange={(v) => setH({ program_no: v ?? "" })}
                options={pick(programs, c("ph.program"))}
                disabled={!editable || !head.supplier_id}
                aria-label={c("f.program")}
              />
            </Field>
            <Field label={c("f.party_bill")} hint={c("hint.party_bill")}>
              <Input
                value={head.party_bill_no}
                onChange={(e) => setH({ party_bill_no: e.target.value })}
                maxLength={40}
                disabled={!editable}
                aria-label={c("f.party_bill")}
              />
            </Field>
            <Field label={c("f.status")} required>
              <PSelect
                value={head.status}
                onValueChange={(v) => setH({ status: v ?? "" })}
                options={pick(
                  options.bill_statuses.map((s) => ({
                    value: s,
                    label: c(`bill_status.${s}`),
                  })),
                  c("ph.choose"),
                )}
                disabled={!editable}
                aria-label={c("f.status")}
              />
            </Field>
          </div>
          <Field label={c("f.remarks")} className="mt-4">
            <Textarea
              rows={2}
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
            <h3 className="text-base font-semibold text-foreground">
              {c("sec.bill_detail")}
            </h3>
            {editable ? (
              <div className="flex gap-2">
                <Button variant="outline" size="sm" onClick={addLine}>
                  <Plus /> {c("btn.add_line")}
                </Button>
                <Button
                  variant="outline"
                  size="sm"
                  onClick={() => setDraft(asBLine({ uom: "kg" }))}
                >
                  {c("btn.cancel_line")}
                </Button>
              </div>
            ) : null}
          </div>
          {editable ? (
            <div className="mt-4 grid gap-4 md:grid-cols-4">
              <Field label={c("f.challan")}>
                <PSelect
                  value={draft.challan_no}
                  onValueChange={pickChallan}
                  options={pick(challans, c("ph.challan"))}
                  disabled={!head.supplier_id}
                  aria-label={c("f.challan")}
                />
              </Field>
              <Field label={c("f.particulars")}>
                <Input
                  value={draft.particulars}
                  onChange={(e) => setD({ particulars: e.target.value })}
                  maxLength={200}
                  aria-label={c("f.particulars")}
                />
              </Field>
              <Field label={c("f.yarn")}>
                <PSelect
                  value={draftKey}
                  onValueChange={(v) => {
                    const [composition_id = "", yarn_count_id = ""] = (
                      v ?? ""
                    ).split("|");
                    setD({ composition_id, yarn_count_id });
                  }}
                  options={pick(items, c("ph.yarn"))}
                  aria-label={c("f.yarn")}
                />
              </Field>
              <Field label={c("f.qty")}>
                <Input
                  type="number"
                  min="0"
                  step="any"
                  value={draft.qty}
                  onChange={(e) => setD({ qty: e.target.value })}
                  aria-label={c("f.qty")}
                />
              </Field>
              <Field label={c("f.rate")}>
                <Input
                  type="number"
                  min="0"
                  step="any"
                  value={draft.rate}
                  onChange={(e) => setD({ rate: e.target.value })}
                  aria-label={c("f.rate")}
                />
              </Field>
              <Field label={c("f.uom")}>
                <PSelect
                  value={draft.uom}
                  onValueChange={(v) => setD({ uom: v ?? "" })}
                  options={pick(options.uoms, c("ph.choose"))}
                  aria-label={c("f.uom")}
                />
              </Field>
              <Field label={c("f.remarks")} className="md:col-span-2">
                <Input
                  value={draft.remarks}
                  onChange={(e) => setD({ remarks: e.target.value })}
                  maxLength={300}
                  aria-label={`${c("f.remarks")} (${c("f.challan")})`}
                />
              </Field>
            </div>
          ) : null}
          <div className="mt-5 overflow-x-auto rounded-lg border border-border">
            {lines.length === 0 ? (
              <NoLines title={c("empty.lines")} hint={c("empty.bill_lines")} />
            ) : (
              <table className="w-full min-w-[800px] text-sm">
                <thead className="bg-brand text-brand-foreground">
                  <tr>
                    {[
                      ["challan"],
                      ["particulars"],
                      ["yarn"],
                      ["qty", true],
                      ["rate", true],
                      ["amount", true],
                      ["uom"],
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
                      <td className="px-2 py-2 font-medium whitespace-nowrap">
                        {l.challan_no || "—"}
                      </td>
                      <td className="px-2 py-2">{l.particulars || "—"}</td>
                      <td className="px-2 py-2">
                        {l.composition_id || l.yarn_count_id
                          ? yarnName(options, l)
                          : "—"}
                      </td>
                      <td className="px-2 py-2 text-right tabular-nums">
                        {qtyFmt.format(num(l.qty))}
                      </td>
                      <td className="px-2 py-2 text-right tabular-nums">
                        {moneyFmt.format(num(l.rate))}
                      </td>
                      <td className="px-2 py-2 text-right font-semibold tabular-nums">
                        {moneyFmt.format(num(l.qty) * num(l.rate))}
                      </td>
                      <td className="px-2 py-2">{l.uom}</td>
                      <td className="px-2 py-1.5">
                        {editable ? (
                          <Button
                            variant="ghost"
                            size="sm"
                            className="text-danger"
                            onClick={() =>
                              setLines((all) =>
                                all.filter((x) => x.key !== l.key),
                              )
                            }
                            aria-label={`${c("btn.remove")} ${i + 1}`}
                          >
                            <Trash2 /> {c("btn.remove")}
                          </Button>
                        ) : null}
                      </td>
                    </tr>
                  ))}
                </tbody>
                <tfoot>
                  <tr className="border-t border-border font-semibold">
                    <td className="px-2 py-2" colSpan={5}>
                      {c("total")}
                    </td>
                    <td className="px-2 py-2 text-right tabular-nums">
                      {moneyFmt.format(total)}
                    </td>
                    <td colSpan={2} />
                  </tr>
                </tfoot>
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
            {c("hint.bill_foot")}
          </p>
          <div className="flex gap-2">
            <Button
              variant="outline"
              onClick={() => show(null)}
              disabled={pending}
            >
              <Eraser /> {c("btn.clear")}
            </Button>
            {editable ? (
              <Button onClick={save} loading={pending}>
                <Save /> {c("btn.save_bill")}
              </Button>
            ) : null}
          </div>
        </section>
      </div>
    </div>
  );
}

export { TwList, TwProgram, TwReceive, TwBill };
