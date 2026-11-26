"use client";

import { useState, useTransition } from "react";
import Link from "next/link";
import {
  ArrowLeft,
  Ban,
  Lock,
  Pencil,
  Plus,
  RotateCcw,
  Save,
  Search,
  Send,
} from "lucide-react";
import { cn } from "@/lib/utils";
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
  key,
  useYarn,
  useYs,
  card,
  th,
  Field,
  StatusChip,
  NoLines,
  YarnCells,
  QtyInput,
  LineButtons,
  yarnPayload,
  orNull,
} from "./yarn-common";
import { lotOptions } from "./ystore-kit";

/*
 * Knit Production → Yarn Store (AnnotechERP 15/03) → Yarn Issue against
 * Indent: the Yarn Issue List (the requisitions with TTL Req. Qty, TTL
 * Issue Qty and Balance; Issue, Close, Cancel), the Yarn Requisition Entry
 * (what the floor asks for, one count per line) and the Yarn Issue Entry —
 * each requisition line taken off a lot of the requisition's store. Nothing
 * reloads; a document travels by its number.
 */

const BASE = "/knit-production/issue-against-requisition";
const qs = (f) =>
  new URLSearchParams(Object.entries(f).filter(([, v]) => v)).toString();
const TONE = {
  OPEN: "DRAFT",
  PARTLY_ISSUED: "PART_RECEIVED",
  FULLY_ISSUED: "RECEIVED",
  CLOSED: "CLOSED",
  CANCELLED: "CANCELLED",
};

function IssueReqList({ options, initialRows, canManage, act }) {
  const y = useYarn();
  const s = useYs();
  const toastManager = useToastManager();
  const blank = {
    no: "",
    date: "",
    type: "",
    status: "",
    buyer: "",
    order: "",
    party: "",
    count: "",
  };
  const [filters, setFilters] = useState(blank);
  const [rows, setRows] = useState(initialRows);
  const [error, setError] = useState(null);
  const [pending, startTransition] = useTransition();
  const setF = (k, v) => setFilters((f) => ({ ...f, [k]: v ?? "" }));
  const pick = (list, ph = "---") => [{ value: "", label: ph }, ...list];
  const sum = (k) => rows.reduce((t, r) => t + r[k], 0);

  function search(f = filters) {
    startTransition(async () => {
      const res = await act(`issue-reqs?${qs(f)}`);
      if (res?.status === "success") {
        setRows(res.data);
        setError(null);
      } else setError(messagesOf(res));
    });
  }
  function run(no, action) {
    startTransition(async () => {
      const res = await act(
        `issue-reqs/${encodeURIComponent(no)}/${action}`,
        "POST",
      );
      if (res?.status !== "success") return setError(messagesOf(res));
      toastManager.add({
        title: s(`ir.done.${action}`, { no }),
        type: "success",
      });
      search();
    });
  }

  return (
    <div className="flex flex-col gap-4">
      <section className={cn(card, "p-5")}>
        <h2 className="flex items-center gap-2 text-base font-semibold text-foreground">
          <Search className="size-4" /> {s("ir.search_title")}
        </h2>
        <p className="mt-0.5 text-[13px] text-foreground-muted">
          {s("ir.list_hint")}
        </p>
        <div className="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
          <Field label={s("ir.no")}>
            <Input
              value={filters.no}
              onChange={(e) => setF("no", e.target.value.trim())}
              placeholder={s("ir.no")}
              aria-label={s("ir.no")}
            />
          </Field>
          <Field label={s("ir.date_filter")}>
            <Input
              type="date"
              value={filters.date}
              onChange={(e) => setF("date", e.target.value)}
              aria-label={s("ir.date_filter")}
            />
          </Field>
          <Field label={s("ir.type")}>
            <PSelect
              value={filters.type}
              onValueChange={(v) => setF("type", v)}
              options={pick(
                options.req_types.map((t) => ({
                  value: t,
                  label: s(`req_type.${t}`),
                })),
              )}
              aria-label={s("ir.type")}
            />
          </Field>
          <Field label={s("ir.action_status")}>
            <PSelect
              value={filters.status}
              onValueChange={(v) => setF("status", v)}
              options={pick(
                options.statuses.map((t) => ({
                  value: t,
                  label: s(`req_status.${t}`),
                })),
              )}
              aria-label={s("ir.action_status")}
            />
          </Field>
          <Field label={s("ir.buyer")}>
            <PSelect
              value={filters.buyer}
              onValueChange={(v) => setF("buyer", v)}
              options={pick(options.buyers, "--- Buyer A/C ---")}
              aria-label={s("ir.buyer")}
            />
          </Field>
          <Field label={s("ir.style_order")}>
            <PSelect
              value={filters.order}
              onValueChange={(v) => setF("order", v)}
              options={pick(options.orders, s("style_ph"))}
              aria-label={s("ir.style_order")}
            />
          </Field>
          <Field label={s("ir.party")}>
            <PSelect
              value={filters.party}
              onValueChange={(v) => setF("party", v)}
              options={pick(options.suppliers, "--- Supplier List ---")}
              aria-label={s("ir.party")}
            />
          </Field>
          <Field label={s("ir.yarn_count")}>
            <PSelect
              value={filters.count}
              onValueChange={(v) => setF("count", v)}
              options={pick(options.counts)}
              aria-label={s("ir.yarn_count")}
            />
          </Field>
        </div>
        <div className="mt-4 flex justify-between gap-2 border-t border-border pt-4">
          <div className="flex gap-2">
            <Button onClick={() => search()} loading={pending}>
              <Search /> {s("search")}
            </Button>
            {canManage ? (
              <Link
                href={`${BASE}?tab=entry`}
                className={buttonVariants({ variant: "outline" })}
              >
                <Plus /> {y("add_new")}
              </Link>
            ) : null}
          </div>
          <Button
            variant="outline"
            onClick={() => (setFilters(blank), search(blank))}
            disabled={pending}
          >
            <RotateCcw /> {y("reset")}
          </Button>
        </div>
      </section>
      {error ? <Alert variant="danger">{error.join(" · ")}</Alert> : null}
      <section className={card}>
        <div className="flex flex-wrap gap-2 border-b border-border px-5 py-4">
          {[
            [s("ir.chip.count"), rows.length],
            [s("ir.chip.req"), qtyFmt.format(sum("req_qty"))],
            [s("ir.chip.issued"), qtyFmt.format(sum("issued_qty"))],
            [s("ir.chip.balance"), qtyFmt.format(sum("balance"))],
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
        {rows.length === 0 ? (
          <NoLines title={s("ir.empty")} hint={s("mrr.empty_hint")} />
        ) : (
          <div className="overflow-x-auto">
            <table className="w-full min-w-[1500px] text-sm">
              <thead className="bg-brand text-brand-foreground">
                <tr>
                  {[
                    "no",
                    "date",
                    "type",
                    "store",
                    "buyer",
                    "order",
                    "party",
                    "program",
                    "req",
                    "issued",
                    "balance",
                    "status",
                  ].map((k) => (
                    <th
                      key={k}
                      className={cn(
                        th,
                        ["req", "issued", "balance"].includes(k) &&
                          "text-right",
                      )}
                    >
                      {s(`ir.lc.${k}`)}
                    </th>
                  ))}
                  <th className={th}>{s("actions")}</th>
                </tr>
              </thead>
              <tbody>
                {rows.map((r) => (
                  <tr key={r.req_no} className="border-t border-border">
                    <td className="px-2 py-2 font-medium whitespace-nowrap">
                      <Link
                        href={`${BASE}?tab=entry&ref=${encodeURIComponent(r.req_no)}`}
                        className="text-brand hover:underline"
                      >
                        {r.req_no}
                      </Link>
                    </td>
                    <td className="px-2 py-2 whitespace-nowrap">
                      {dayFmt(r.req_date)}
                    </td>
                    <td className="px-2 py-2">{s(`req_type.${r.req_type}`)}</td>
                    <td className="px-2 py-2">{r.store ?? "—"}</td>
                    <td className="px-2 py-2">{r.buyer ?? "—"}</td>
                    <td className="px-2 py-2">{r.order ?? "—"}</td>
                    <td className="px-2 py-2">{r.party ?? "—"}</td>
                    <td className="px-2 py-2">{r.program_no ?? "—"}</td>
                    <td className="px-2 py-2 text-right tabular-nums">
                      {qtyFmt.format(r.req_qty)}
                    </td>
                    <td className="px-2 py-2 text-right tabular-nums">
                      {qtyFmt.format(r.issued_qty)}
                    </td>
                    <td className="px-2 py-2 text-right font-semibold tabular-nums">
                      {qtyFmt.format(r.balance)}
                    </td>
                    <td className="px-2 py-2">
                      <StatusChip
                        status={TONE[r.status]}
                        label={s(`req_status.${r.status}`)}
                      />
                    </td>
                    <td className="px-2 py-1.5">
                      {canManage ? (
                        <div className="flex gap-1">
                          {["OPEN", "PARTLY_ISSUED"].includes(r.status) ? (
                            <Link
                              href={`${BASE}?tab=issue&req=${encodeURIComponent(r.req_no)}`}
                              className={buttonVariants({
                                variant: "outline",
                                size: "sm",
                              })}
                            >
                              <Send /> {s("ir.btn.issue")}
                            </Link>
                          ) : null}
                          {r.editable ? (
                            <Link
                              href={`${BASE}?tab=entry&ref=${encodeURIComponent(r.req_no)}`}
                              className={buttonVariants({
                                variant: "outline",
                                size: "sm",
                              })}
                            >
                              <Pencil /> {y("edit")}
                            </Link>
                          ) : null}
                          {["OPEN", "PARTLY_ISSUED"].includes(r.status) ? (
                            <Button
                              variant="outline"
                              size="sm"
                              onClick={() => run(r.req_no, "close")}
                              disabled={pending}
                            >
                              <Lock /> {s("ir.btn.close")}
                            </Button>
                          ) : null}
                          {r.editable ? (
                            <Button
                              variant="outline"
                              size="sm"
                              className="text-danger"
                              onClick={() => run(r.req_no, "cancel")}
                              disabled={pending}
                            >
                              <Ban /> {s("ir.btn.cancel")}
                            </Button>
                          ) : null}
                        </div>
                      ) : null}
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

const REQ_HEAD = [
  "req_date",
  "req_type",
  "store_id",
  "buyer_id",
  "order_id",
  "party_id",
  "program_no",
  "remarks",
];
const reqLine = (l = {}) => ({
  key: key(),
  composition_id: str(l.composition_id),
  yarn_count_id: str(l.yarn_count_id),
  colour: str(l.colour),
  brand: str(l.brand),
  lot_no: str(l.lot_no),
  uom: str(l.uom || "kg"),
  req_qty: str(l.req_qty),
  kp_alloc: str(l.kp_alloc),
  issued_qty: Number(l.issued_qty ?? 0),
  remarks: str(l.remarks),
});
const yarnStore = (stores) =>
  (stores.find((x) => /yarn/i.test(x.label)) ?? stores[0])?.value ?? "";

function ReqEntry({ options, initial, canManage, act }) {
  const y = useYarn();
  const s = useYs();
  const toastManager = useToastManager();
  const [today] = useState(todayIso);
  const blankHead = () => ({
    ...Object.fromEntries(REQ_HEAD.map((k) => [k, ""])),
    req_date: today,
    store_id: yarnStore(options.stores),
  });
  const asHead = (d) => Object.fromEntries(REQ_HEAD.map((k) => [k, str(d[k])]));
  const [doc, setDoc] = useState(initial);
  const [head, setHead] = useState(() =>
    initial ? asHead(initial) : blankHead(),
  );
  const [lines, setLines] = useState(() => (initial?.lines ?? []).map(reqLine));
  const [errors, setErrors] = useState(null);
  const [pending, startTransition] = useTransition();
  const editable = canManage && (!doc || doc.editable);
  const setH = (patch) => setHead((h) => ({ ...h, ...patch }));
  const setLine = (k, patch) =>
    setLines((all) => all.map((l) => (l.key === k ? { ...l, ...patch } : l)));
  const pick = (list, ph = s("select")) => [{ value: "", label: ph }, ...list];

  function take(data) {
    setDoc(data);
    setHead(asHead(data));
    setLines(data.lines.map(reqLine));
    setErrors(null);
    window.history.replaceState(
      null,
      "",
      `?tab=entry&ref=${encodeURIComponent(data.req_no)}`,
    );
  }
  function clear() {
    setDoc(null);
    setHead(blankHead());
    setLines([]);
    setErrors(null);
    window.history.replaceState(null, "", "?tab=entry");
  }
  function load(no) {
    if (!no) return clear();
    startTransition(async () => {
      const res = await act(`issue-reqs/${encodeURIComponent(no)}`);
      if (res?.status === "success") take(res.data);
      else setErrors(messagesOf(res));
    });
  }
  function save() {
    startTransition(async () => {
      const body = {
        ...Object.fromEntries(
          REQ_HEAD.map((k) => [k, head[k] === "" ? null : head[k]]),
        ),
        lines: lines.map((l) => ({
          ...yarnPayload(l),
          lot_no: l.lot_no || null,
          req_qty: orNull(l.req_qty),
          kp_alloc: orNull(l.kp_alloc),
        })),
      };
      const res = await act(
        doc ? `issue-reqs/${encodeURIComponent(doc.req_no)}` : "issue-reqs",
        doc ? "PUT" : "POST",
        body,
      );
      if (res?.status !== "success") return setErrors(messagesOf(res));
      take(res.data);
      toastManager.add({
        title: s("ir.saved", { no: res.data.req_no }),
        type: "success",
      });
    });
  }

  return (
    <div className="flex min-w-0 flex-col gap-4">
      <section className={cn(card, "p-5")}>
        <div className="flex items-start justify-between gap-3">
          <div>
            <h2 className="text-base font-semibold text-foreground">
              {s("tabs.ir_entry")}
            </h2>
            <p className="mt-0.5 text-[13px] text-foreground-muted">
              {s("ir.entry_hint")}
            </p>
          </div>
          {doc ? (
            <StatusChip
              status={TONE[doc.status]}
              label={
                doc.editable
                  ? y("editing", { no: doc.req_no })
                  : y("read_only", { status: s(`req_status.${doc.status}`) })
              }
            />
          ) : null}
        </div>
        <Field
          label={s("ir.existing")}
          hint={s("ob.existing_hint")}
          className="mt-4"
        >
          <PSelect
            value={doc?.req_no ?? ""}
            onValueChange={(v) => load(v ?? "")}
            options={[
              { value: "", label: s("ir.existing_new") },
              ...options.reqs,
            ]}
            aria-label={s("ir.existing")}
          />
        </Field>
        <div className="mt-4 grid gap-4 md:grid-cols-3">
          <Field label={s("ir.req_date")} required>
            <Input
              type="date"
              value={head.req_date}
              onChange={(e) => setH({ req_date: e.target.value })}
              disabled={!editable}
              aria-label={s("ir.req_date")}
            />
          </Field>
          <Field label={s("ir.type")} required>
            <PSelect
              value={head.req_type}
              onValueChange={(v) => setH({ req_type: v ?? "" })}
              options={pick(
                options.req_types.map((t) => ({
                  value: t,
                  label: s(`req_type.${t}`),
                })),
                "-- Select Type --",
              )}
              disabled={!editable}
              aria-label={s("ir.type")}
            />
          </Field>
          <Field label={s("company")} required>
            <PSelect
              value={options.company.value}
              options={[options.company]}
              disabled
              aria-label={s("company")}
            />
          </Field>
          <Field label={s("store")} required>
            <PSelect
              value={head.store_id}
              onValueChange={(v) => setH({ store_id: v ?? "" })}
              options={pick(options.stores)}
              disabled={!editable}
              aria-label={s("store")}
            />
          </Field>
          <Field label={s("ir.buyer")}>
            <PSelect
              value={head.buyer_id}
              onValueChange={(v) => setH({ buyer_id: v ?? "" })}
              options={pick(options.buyers, "--- Buyer A/C ---")}
              disabled={!editable}
              aria-label={s("ir.buyer")}
            />
          </Field>
          <Field label={s("ir.job_style")}>
            <PSelect
              value={head.order_id}
              onValueChange={(v) => setH({ order_id: v ?? "" })}
              options={pick(options.orders, s("style_ph"))}
              disabled={!editable}
              aria-label={s("ir.job_style")}
            />
          </Field>
          <Field label={s("ir.party")}>
            <PSelect
              value={head.party_id}
              onValueChange={(v) => setH({ party_id: v ?? "" })}
              options={pick(options.suppliers, "--- Supplier List ---")}
              disabled={!editable}
              aria-label={s("ir.party")}
            />
          </Field>
          <Field
            label={s("ir.program")}
            hint={s("ir.program_hint")}
            className="md:col-span-2"
          >
            <Input
              value={head.program_no}
              onChange={(e) => setH({ program_no: e.target.value })}
              maxLength={40}
              disabled={!editable}
              aria-label={s("ir.program")}
            />
          </Field>
          <Field label={s("remarks")} className="md:col-span-3">
            <Textarea
              rows={2}
              value={head.remarks}
              onChange={(e) => setH({ remarks: e.target.value })}
              maxLength={2000}
              disabled={!editable}
              aria-label={s("remarks")}
            />
          </Field>
        </div>
      </section>

      <section className={cn(card, "overflow-hidden")}>
        <div className="flex items-start justify-between gap-3 px-5 py-4">
          <div>
            <h2 className="text-base font-semibold text-foreground">
              {s("ir.lines")}
            </h2>
            <p className="mt-0.5 text-[13px] text-foreground-muted">
              {s("ir.lines_hint")}
            </p>
          </div>
          {editable ? (
            <Button
              variant="outline"
              size="sm"
              onClick={() => setLines((all) => [...all, reqLine()])}
            >
              <Plus /> {s("mrr.add")}
            </Button>
          ) : null}
        </div>
        {lines.length === 0 ? (
          <NoLines title={s("mrr.no_lines")} hint={s("ir.no_lines_hint")} />
        ) : (
          <div className="overflow-x-auto">
            <table className="w-full min-w-[1500px] text-sm">
              <thead className="bg-brand text-brand-foreground">
                <tr>
                  {[
                    "composition",
                    "count",
                    "colour",
                    "brand",
                    "lot",
                    "uom",
                  ].map((k) => (
                    <th key={k} className={th}>
                      {y(`col.${k}`)}
                    </th>
                  ))}
                  {[
                    "req_qty",
                    "kp_alloc",
                    ...(doc ? ["issued", "balance"] : []),
                  ].map((k) => (
                    <th key={k} className={cn(th, "text-right")}>
                      {s(`col.${k}`)}
                    </th>
                  ))}
                  <th className={th}>{y("col.remarks")}</th>
                  <th className={th}>{y("col.copy")}</th>
                  <th className={th}>{y("col.remove")}</th>
                </tr>
              </thead>
              <tbody>
                {lines.map((l, i) => (
                  <tr key={l.key} className="border-t border-border">
                    <YarnCells
                      line={l}
                      set={(p) => setLine(l.key, p)}
                      options={options}
                      disabled={!editable}
                      n={i + 1}
                      lot
                    />
                    <td className="px-2 py-1.5">
                      <QtyInput
                        value={l.req_qty}
                        onChange={(v) => setLine(l.key, { req_qty: v })}
                        disabled={!editable}
                        label={`${s("col.req_qty")} ${i + 1}`}
                      />
                    </td>
                    <td className="px-2 py-1.5">
                      <QtyInput
                        value={l.kp_alloc}
                        onChange={(v) => setLine(l.key, { kp_alloc: v })}
                        disabled={!editable}
                        label={`${s("col.kp_alloc")} ${i + 1}`}
                      />
                    </td>
                    {doc ? (
                      <>
                        <td className="px-2 py-1.5 text-right tabular-nums">
                          {qtyFmt.format(l.issued_qty)}
                        </td>
                        <td className="px-2 py-1.5 text-right font-semibold tabular-nums">
                          {qtyFmt.format(
                            Math.max(0, num(l.req_qty) - l.issued_qty),
                          )}
                        </td>
                      </>
                    ) : null}
                    <td className="px-2 py-1.5">
                      <Input
                        value={l.remarks}
                        onChange={(e) =>
                          setLine(l.key, { remarks: e.target.value })
                        }
                        maxLength={300}
                        className="h-8 w-40"
                        disabled={!editable}
                        aria-label={`${y("col.remarks")} ${i + 1}`}
                      />
                    </td>
                    <LineButtons
                      n={i + 1}
                      disabled={!editable}
                      onCopy={() =>
                        setLines((all) => [
                          ...all.slice(0, i + 1),
                          { ...l, key: key(), issued_qty: 0 },
                          ...all.slice(i + 1),
                        ])
                      }
                      onRemove={() =>
                        setLines((all) => all.filter((x) => x.key !== l.key))
                      }
                    />
                  </tr>
                ))}
              </tbody>
              <tfoot className="bg-surface-muted font-semibold">
                <tr className="border-t-2 border-border">
                  <td colSpan={6} className="px-2 py-2">
                    {s("total_row")}
                  </td>
                  <td className="px-2 py-2 text-right tabular-nums">
                    {qtyFmt.format(
                      lines.reduce((t, l) => t + num(l.req_qty), 0),
                    )}
                  </td>
                  <td className="px-2 py-2 text-right tabular-nums">
                    {qtyFmt.format(
                      lines.reduce((t, l) => t + num(l.kp_alloc), 0),
                    )}
                  </td>
                  <td colSpan={doc ? 5 : 3} />
                </tr>
              </tfoot>
            </table>
          </div>
        )}
      </section>

      {errors ? <Alert variant="danger">{errors.join(" · ")}</Alert> : null}
      <section className={cn(card, "flex flex-col gap-3 p-5")}>
        <p className="text-[13px] text-foreground-muted">{s("ir.foot")}</p>
        <div className="flex flex-wrap gap-2">
          <Link href={BASE} className={buttonVariants({ variant: "outline" })}>
            <ArrowLeft /> {y("back")}
          </Link>
          <Button variant="outline" onClick={clear} disabled={pending}>
            <RotateCcw /> {y("clear")}
          </Button>
          {editable ? (
            <Button onClick={save} loading={pending}>
              <Save /> {s("ir.save")}
            </Button>
          ) : null}
        </div>
      </section>
    </div>
  );
}

const issueLine = (l = {}) => ({
  key: key(),
  req_line_id: str(l.req_line_id),
  lot_id: str(l.lot_id),
  qty: str(l.qty),
  cones: str(l.cones),
  remarks: str(l.remarks),
});

/**
 * The Yarn Issue Entry. `source` is the requisition's lines with what each
 * still owes and the store's lots of that yarn; an issue opened from the
 * list arrives with one line per requisition line still owed.
 */
function IssueEntry({ options, initial, initialSource, canManage, act }) {
  const y = useYarn();
  const s = useYs();
  const toastManager = useToastManager();
  const [today] = useState(todayIso);
  const [doc, setDoc] = useState(initial);
  const [source, setSource] = useState(initialSource);
  const asHead = (d) => ({
    issue_date: str(d?.issue_date) || today,
    req_no: str(d?.req_no),
    gate_pass_no: str(d?.gate_pass_no),
    remarks: str(d?.remarks),
  });
  // A line per requisition line still owed, off its only lot when there is one.
  const fresh = (src) =>
    src.lines
      .filter((l) => l.balance > 0)
      .map((l) =>
        issueLine({
          req_line_id: l.id,
          lot_id: l.lots.length === 1 ? l.lots[0].lot_id : "",
          qty:
            l.lots.length === 1
              ? Math.min(l.balance, l.lots[0].balance)
              : l.balance,
        }),
      );
  const [head, setHead] = useState(() =>
    asHead(
      initial ?? (initialSource ? { req_no: initialSource.req_no } : null),
    ),
  );
  const [lines, setLines] = useState(() =>
    initial
      ? initial.lines.map(issueLine)
      : initialSource
        ? fresh(initialSource)
        : [],
  );
  const [errors, setErrors] = useState(null);
  const [pending, startTransition] = useTransition();
  const editable = canManage && (!doc || doc.editable);
  const setH = (patch) => setHead((h) => ({ ...h, ...patch }));
  const setLine = (k, patch) =>
    setLines((all) => all.map((l) => (l.key === k ? { ...l, ...patch } : l)));
  const reqLineOf = (id) => source?.lines.find((l) => l.id === id);
  // A lot this issue already took stays on offer even if nothing is left on it.
  const lotsFor = (l) => {
    const own = (doc?.lines ?? [])
      .filter((x) => x.lot_id === l.lot_id)
      .map((x) => ({ lot_id: x.lot_id, label: x.lot, balance: 0 }));
    const offered = reqLineOf(l.req_line_id)?.lots ?? [];
    return [
      ...offered,
      ...own.filter((o) => !offered.some((x) => x.lot_id === o.lot_id)),
    ];
  };
  const reqs = options.open_reqs.some((r) => r.value === head.req_no)
    ? options.open_reqs
    : head.req_no
      ? [{ value: head.req_no, label: head.req_no }, ...options.open_reqs]
      : options.open_reqs;

  function bring(no, keepLines) {
    setH({ req_no: no });
    if (!no) return (setSource(null), setLines([]));
    startTransition(async () => {
      const res = await act(`issue-reqs/${encodeURIComponent(no)}/source`);
      if (res?.status !== "success") return setErrors(messagesOf(res));
      setSource(res.data);
      if (!keepLines) setLines(fresh(res.data));
      setErrors(null);
    });
  }
  function take(data) {
    setDoc(data);
    setHead(asHead(data));
    setLines(data.lines.map(issueLine));
    setErrors(null);
    bring(data.req_no, true);
    window.history.replaceState(
      null,
      "",
      `?tab=issue&ref=${encodeURIComponent(data.issue_no)}`,
    );
  }
  function clear() {
    setDoc(null);
    setSource(null);
    setHead(asHead(null));
    setLines([]);
    setErrors(null);
    window.history.replaceState(null, "", "?tab=issue");
  }
  function load(no) {
    if (!no) return clear();
    startTransition(async () => {
      const res = await act(`issues/${encodeURIComponent(no)}`);
      if (res?.status === "success") take(res.data);
      else setErrors(messagesOf(res));
    });
  }
  function save() {
    startTransition(async () => {
      const body = {
        issue_date: head.issue_date || null,
        req_no: head.req_no || null,
        gate_pass_no: head.gate_pass_no || null,
        remarks: head.remarks || null,
        lines: lines.map((l) => ({
          req_line_id: l.req_line_id || null,
          lot_id: l.lot_id || null,
          qty: orNull(l.qty),
          cones: orNull(l.cones),
          remarks: l.remarks || null,
        })),
      };
      const res = await act(
        doc ? `issues/${encodeURIComponent(doc.issue_no)}` : "issues",
        doc ? "PUT" : "POST",
        body,
      );
      if (res?.status !== "success") return setErrors(messagesOf(res));
      take(res.data);
      toastManager.add({
        title: s("is.saved", { no: res.data.issue_no }),
        type: "success",
      });
    });
  }
  const storeLabel =
    options.stores.find((x) => x.value === source?.store_id)?.label ?? "";

  return (
    <div className="flex min-w-0 flex-col gap-4">
      <section className={cn(card, "p-5")}>
        <div className="flex items-start justify-between gap-3">
          <div>
            <h2 className="text-base font-semibold text-foreground">
              {s("tabs.is_entry")}
            </h2>
            <p className="mt-0.5 text-[13px] text-foreground-muted">
              {s("is.entry_hint")}
            </p>
          </div>
          {doc ? (
            <StatusChip
              status={doc.editable ? "DRAFT" : "CLOSED"}
              label={
                doc.editable
                  ? y("editing", { no: doc.issue_no })
                  : y("read_only", { status: s("is.credited") })
              }
            />
          ) : null}
        </div>
        <Field
          label={s("is.existing")}
          hint={s("ob.existing_hint")}
          className="mt-4"
        >
          <PSelect
            value={doc?.issue_no ?? ""}
            onValueChange={(v) => load(v ?? "")}
            options={[
              { value: "", label: s("is.existing_new") },
              ...options.issues,
            ]}
            aria-label={s("is.existing")}
          />
        </Field>
        <div className="mt-4 grid gap-4 md:grid-cols-3">
          <Field label={s("is.date")} required>
            <Input
              type="date"
              value={head.issue_date}
              onChange={(e) => setH({ issue_date: e.target.value })}
              disabled={!editable}
              aria-label={s("is.date")}
            />
          </Field>
          <Field label={s("is.req")} required hint={s("is.req_hint")}>
            <PSelect
              value={head.req_no}
              onValueChange={(v) => bring(v ?? "", false)}
              options={[
                { value: "", label: "-- Select Requisition --" },
                ...reqs,
              ]}
              disabled={!editable}
              aria-label={s("is.req")}
            />
          </Field>
          <Field label={s("company")} required>
            <PSelect
              value={options.company.value}
              options={[options.company]}
              disabled
              aria-label={s("company")}
            />
          </Field>
          <Field label={s("store")} required>
            <Input
              value={storeLabel}
              readOnly
              className="bg-surface-muted"
              aria-label={s("store")}
            />
          </Field>
          <Field label={s("is.gate_pass")}>
            <Input
              value={head.gate_pass_no}
              onChange={(e) => setH({ gate_pass_no: e.target.value })}
              maxLength={40}
              disabled={!editable}
              aria-label={s("is.gate_pass")}
            />
          </Field>
          <Field label={s("remarks")} className="md:col-span-3">
            <Textarea
              rows={2}
              value={head.remarks}
              onChange={(e) => setH({ remarks: e.target.value })}
              maxLength={2000}
              disabled={!editable}
              aria-label={s("remarks")}
            />
          </Field>
        </div>
      </section>

      <section className={cn(card, "overflow-hidden")}>
        <div className="flex items-start justify-between gap-3 px-5 py-4">
          <div>
            <h2 className="text-base font-semibold text-foreground">
              {s("is.lines")}
            </h2>
            <p className="mt-0.5 text-[13px] text-foreground-muted">
              {s("is.lines_hint")}
            </p>
          </div>
          {editable && source ? (
            <Button
              variant="outline"
              size="sm"
              onClick={() => setLines((all) => [...all, issueLine()])}
            >
              <Plus /> {s("mrr.add")}
            </Button>
          ) : null}
        </div>
        {lines.length === 0 ? (
          <NoLines title={s("mrr.no_lines")} hint={s("is.no_lines_hint")} />
        ) : (
          <div className="overflow-x-auto">
            <table className="w-full min-w-[1300px] text-sm">
              <thead className="bg-brand text-brand-foreground">
                <tr>
                  <th className={th}>{s("col.req_line")}</th>
                  <th className={cn(th, "text-right")}>{s("col.balance")}</th>
                  <th className={th}>{s("col.stock_line")}</th>
                  <th className={cn(th, "text-right")}>{s("col.issue_qty")}</th>
                  <th className={th}>{y("col.uom")}</th>
                  <th className={cn(th, "text-right")}>{s("col.cones")}</th>
                  <th className={th}>{y("col.remarks")}</th>
                  <th className={th}>{y("col.copy")}</th>
                  <th className={th}>{y("col.remove")}</th>
                </tr>
              </thead>
              <tbody>
                {lines.map((l, i) => {
                  const rl = reqLineOf(l.req_line_id);
                  return (
                    <tr key={l.key} className="border-t border-border">
                      <td className="px-2 py-1.5">
                        <div className="w-80">
                          <PSelect
                            value={l.req_line_id}
                            onValueChange={(v) =>
                              setLine(l.key, {
                                req_line_id: v ?? "",
                                lot_id: "",
                              })
                            }
                            options={[
                              { value: "", label: "-- Select Line --" },
                              ...(source?.lines ?? []).map((x) => ({
                                value: x.id,
                                label: x.label || "—",
                              })),
                            ]}
                            disabled={!editable}
                            aria-label={`${s("col.req_line")} ${i + 1}`}
                          />
                        </div>
                      </td>
                      <td className="px-2 py-1.5 text-right tabular-nums">
                        {rl ? qtyFmt.format(rl.balance) : "—"}
                      </td>
                      <td className="px-2 py-1.5">
                        <div className="w-96">
                          <PSelect
                            value={l.lot_id}
                            onValueChange={(v) =>
                              setLine(l.key, { lot_id: v ?? "" })
                            }
                            options={[
                              { value: "", label: s("adj.lot_ph") },
                              ...lotOptions(lotsFor(l), qtyFmt),
                            ]}
                            disabled={!editable || !l.req_line_id}
                            aria-label={`${s("col.stock_line")} ${i + 1}`}
                          />
                        </div>
                      </td>
                      <td className="px-2 py-1.5">
                        <div className="flex justify-end">
                          <QtyInput
                            value={l.qty}
                            onChange={(v) => setLine(l.key, { qty: v })}
                            disabled={!editable}
                            label={`${s("col.issue_qty")} ${i + 1}`}
                          />
                        </div>
                      </td>
                      <td className="px-2 py-1.5">{rl?.uom ?? "kg"}</td>
                      <td className="px-2 py-1.5">
                        <div className="flex justify-end">
                          <QtyInput
                            value={l.cones}
                            onChange={(v) => setLine(l.key, { cones: v })}
                            disabled={!editable}
                            w="w-20"
                            label={`${s("col.cones")} ${i + 1}`}
                          />
                        </div>
                      </td>
                      <td className="px-2 py-1.5">
                        <Input
                          value={l.remarks}
                          onChange={(e) =>
                            setLine(l.key, { remarks: e.target.value })
                          }
                          maxLength={300}
                          className="h-8 w-40"
                          disabled={!editable}
                          aria-label={`${y("col.remarks")} ${i + 1}`}
                        />
                      </td>
                      <LineButtons
                        n={i + 1}
                        disabled={!editable}
                        onCopy={() =>
                          setLines((all) => [
                            ...all.slice(0, i + 1),
                            { ...l, key: key(), lot_id: "", qty: "" },
                            ...all.slice(i + 1),
                          ])
                        }
                        onRemove={() =>
                          setLines((all) => all.filter((x) => x.key !== l.key))
                        }
                      />
                    </tr>
                  );
                })}
              </tbody>
              <tfoot className="bg-surface-muted font-semibold">
                <tr className="border-t-2 border-border">
                  <td colSpan={3} className="px-2 py-2">
                    {s("total_row")}
                  </td>
                  <td className="px-2 py-2 text-right tabular-nums">
                    {qtyFmt.format(lines.reduce((t, l) => t + num(l.qty), 0))}
                  </td>
                  <td colSpan={5} />
                </tr>
              </tfoot>
            </table>
          </div>
        )}
      </section>

      {errors ? <Alert variant="danger">{errors.join(" · ")}</Alert> : null}
      <section className={cn(card, "flex flex-col gap-3 p-5")}>
        <p className="text-[13px] text-foreground-muted">{s("is.foot")}</p>
        <div className="flex flex-wrap gap-2">
          <Link href={BASE} className={buttonVariants({ variant: "outline" })}>
            <ArrowLeft /> {y("back")}
          </Link>
          <Button variant="outline" onClick={clear} disabled={pending}>
            <RotateCcw /> {y("clear")}
          </Button>
          {editable ? (
            <Button onClick={save} loading={pending}>
              <Save /> {s("is.save")}
            </Button>
          ) : null}
        </div>
      </section>
    </div>
  );
}

export { IssueReqList, ReqEntry, IssueEntry };
