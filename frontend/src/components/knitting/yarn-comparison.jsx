"use client";

import { useState, useTransition } from "react";
import {
  ArrowLeft,
  Ban,
  CheckCheck,
  Plus,
  RotateCcw,
  Save,
  Search,
  Trash2,
} from "lucide-react";
import { cn } from "@/lib/utils";
import { Input } from "@/components/ui/input";
import { Textarea } from "@/components/ui/textarea";
import { Button } from "@/components/ui/button";
import { Alert } from "@/components/ui/alert";
import { ConfirmDialog } from "@/components/ui/confirm-dialog";
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
  card,
  th,
  Field,
  StatusChip,
  NoLines,
  YARN_HEADS,
  YarnCells,
  QtyInput,
  DateInput,
  LineButtons,
  yarnPayload,
  orNull,
} from "./yarn-common";

/*
 * Knit Production → Yarn Procurement (AnnotechERP 15/02) → Yarn Comparative
 * Statement, as Brickland lays it out: the list (search, Finalize, Cancel)
 * and, on Add New or a comparison opened, the comparison desk — the approved
 * requisitions beside it (Compare This fills the grid), the Details Data
 * grid with each line's stock, pipeline, quantity worth ordering and the
 * spinner it is finalized with, and the spinners' offers below, the lowest
 * on each SL # marked. List and desk swap in place; nothing reloads.
 */

const asLine = (l = {}) => ({
  key: key(),
  sl_no: str(l.sl_no),
  composition_id: str(l.composition_id),
  yarn_count_id: str(l.yarn_count_id),
  colour: str(l.colour),
  brand: str(l.brand),
  uom: str(l.uom),
  qty_local: str(l.qty_local),
  qty_import: str(l.qty_import),
  present_stock: str(l.present_stock ?? 0),
  pipeline: str(l.pipeline ?? 0),
  knit_start_date: str(l.knit_start_date),
  ship_date: str(l.ship_date),
  booking_price: str(l.booking_price),
  buyer_id: str(l.buyer_id),
  supplier_id: str(l.supplier_id),
  remarks: str(l.remarks),
});
const asOffer = (o = {}) => ({
  key: key(),
  sl_no: str(o.sl_no),
  source: str(o.source || "LOCAL"),
  supplier_id: str(o.supplier_id),
  price: str(o.price),
  currency_id: str(o.currency_id),
  conf_qty: str(o.conf_qty),
  lead_days: str(o.lead_days),
  remarks: str(o.remarks),
});
const blankHead = (today) => ({
  cs_date: today,
  requisition_no: "",
  requisition_date: "",
  remarks: "",
});
const PAGE = 10;

function ComparisonScreen({ options, initialRows, initial, canManage, act }) {
  const y = useYarn();
  const toastManager = useToastManager();
  const [today] = useState(todayIso);
  const [view, setView] = useState(initial ? "desk" : "list");
  // The list.
  const blankF = { no: "", requisition: "", status: "", from: "", to: "" };
  const [filters, setFilters] = useState(blankF);
  const [rows, setRows] = useState(initialRows);
  // The desk.
  const [doc, setDoc] = useState(initial);
  const [head, setHead] = useState(() =>
    initial
      ? {
          cs_date: initial.cs_date,
          requisition_no: str(initial.requisition_no),
          requisition_date: str(initial.requisition_date),
          remarks: str(initial.remarks),
        }
      : blankHead(today),
  );
  const [lines, setLines] = useState(() => (initial?.lines ?? []).map(asLine));
  const [offers, setOffers] = useState(() =>
    (initial?.offers ?? []).map(asOffer),
  );
  const [reqQuery, setReqQuery] = useState("");
  const [page, setPage] = useState(0);
  const [errors, setErrors] = useState(null);
  const [confirm, setConfirm] = useState(false);
  const [pending, startTransition] = useTransition();
  const editable = canManage && (!doc || doc.editable);
  const setF = (k, v) => setFilters((f) => ({ ...f, [k]: v ?? "" }));
  const setH = (patch) => setHead((h) => ({ ...h, ...patch }));
  const setLine = (k, patch) =>
    setLines((all) => all.map((l) => (l.key === k ? { ...l, ...patch } : l)));
  const setOffer = (k, patch) =>
    setOffers((all) => all.map((o) => (o.key === k ? { ...o, ...patch } : o)));
  const total = (l) => num(l.qty_local) + num(l.qty_import);
  const worth = (l) =>
    Math.max(0, total(l) - num(l.present_stock) - num(l.pipeline));
  const lowest = {};
  for (const o of offers)
    if (o.sl_no && num(o.price) > 0)
      lowest[o.sl_no] = Math.min(lowest[o.sl_no] ?? Infinity, num(o.price));
  const shownReqs = options.requisitions.filter(
    (r) =>
      !reqQuery.trim() ||
      `${r.value} ${r.buyer ?? ""}`
        .toLowerCase()
        .includes(reqQuery.trim().toLowerCase()),
  );
  const pages = Math.max(1, Math.ceil(shownReqs.length / PAGE));
  const suppliers = [{ value: "", label: "—" }, ...options.suppliers];

  function url(no) {
    window.history.replaceState(
      null,
      "",
      no
        ? `?view=desk&ref=${encodeURIComponent(no)}`
        : view === "desk"
          ? "?view=desk"
          : "?",
    );
  }
  function search(f = filters) {
    startTransition(async () => {
      const res = await act("comparisons", "list", { query: f });
      if (res?.status === "success") {
        setRows(res.data);
        setErrors(null);
      } else setErrors(messagesOf(res));
    });
  }
  function run(no, action) {
    startTransition(async () => {
      const res = await act("comparisons", action, { no });
      if (res?.status !== "success") return setErrors(messagesOf(res));
      toastManager.add({ title: y(`done.${action}`, { no }), type: "success" });
      search();
    });
  }
  function take(data) {
    setDoc(data);
    setHead({
      cs_date: data.cs_date,
      requisition_no: str(data.requisition_no),
      requisition_date: str(data.requisition_date),
      remarks: str(data.remarks),
    });
    setLines(data.lines.map(asLine));
    setOffers(data.offers.map(asOffer));
    setErrors(null);
    url(data.cs_no);
  }
  function openDesk(no) {
    setView("desk");
    if (!no) return clear(true);
    startTransition(async () => {
      const res = await act("comparisons", "show", { no });
      if (res?.status === "success") take(res.data);
      else setErrors(messagesOf(res));
    });
  }
  function clear(keepDesk = true) {
    setDoc(null);
    setHead(blankHead(today));
    setLines([]);
    setOffers([]);
    setErrors(null);
    window.history.replaceState(null, "", keepDesk ? "?view=desk" : "?");
  }
  function backToList() {
    setView("list");
    window.history.replaceState(null, "", "?");
    search();
  }
  function compare(no) {
    startTransition(async () => {
      const res = await act("comparisons", "source", { requisition: no });
      if (res?.status !== "success") return setErrors(messagesOf(res));
      setH({
        requisition_no: res.data.requisition_no,
        requisition_date: res.data.requisition_date,
      });
      setLines(res.data.lines.map(asLine));
      setOffers([]);
      setErrors(null);
    });
  }
  function save() {
    startTransition(async () => {
      const body = {
        cs_date: head.cs_date || null,
        requisition_no: head.requisition_no || null,
        remarks: head.remarks || null,
        lines: lines.map((l) => ({
          ...yarnPayload(l),
          sl_no: orNull(l.sl_no),
          qty_local: orNull(l.qty_local),
          qty_import: orNull(l.qty_import),
          present_stock: orNull(l.present_stock),
          pipeline: orNull(l.pipeline),
          booking_price: orNull(l.booking_price),
          buyer_id: l.buyer_id || null,
          supplier_id: l.supplier_id || null,
        })),
        offers: offers.map((o) => ({
          sl_no: orNull(o.sl_no),
          source: o.source || null,
          supplier_id: o.supplier_id || null,
          price: orNull(o.price),
          currency_id: o.currency_id || null,
          conf_qty: orNull(o.conf_qty),
          lead_days: orNull(o.lead_days),
          remarks: o.remarks || null,
        })),
      };
      const res = await act("comparisons", "save", { no: doc?.cs_no, body });
      if (res?.status === "success") {
        take(res.data);
        toastManager.add({
          title: y(doc ? "updated" : "saved", { no: res.data.cs_no }),
          type: "success",
        });
      } else setErrors(messagesOf(res));
    });
  }
  function remove() {
    startTransition(async () => {
      const res = await act("comparisons", "remove", { no: doc.cs_no });
      setConfirm(false);
      if (res?.status !== "success") return setErrors(messagesOf(res));
      toastManager.add({
        title: y("done.delete", { no: doc.cs_no }),
        type: "success",
      });
      clear(true);
    });
  }

  if (view === "list")
    return (
      <section className={card}>
        <div className="px-5 pt-5">
          <h2 className="text-base font-semibold text-foreground">
            {y("cs.list_title")}
          </h2>
          <p className="mt-0.5 text-[13px] text-foreground-muted">
            {y("cs.list_hint")}
          </p>
        </div>
        <div className="grid gap-4 px-5 pt-4 sm:grid-cols-2 lg:grid-cols-6">
          <Field label={y("cs.no")}>
            <Input
              value={filters.no}
              onChange={(e) => setF("no", e.target.value)}
              placeholder={y("search_ph")}
              aria-label={y("cs.no")}
            />
          </Field>
          <Field label={y("cs.requisition")}>
            <PSelect
              value={filters.requisition}
              onValueChange={(v) => setF("requisition", v)}
              options={[
                { value: "", label: y("select") },
                ...options.requisitions.map((r) => ({
                  value: r.value,
                  label: r.label,
                })),
              ]}
              aria-label={y("cs.requisition")}
            />
          </Field>
          <Field label={y("company")}>
            <PSelect
              value={options.company.value}
              options={[options.company]}
              disabled
              aria-label={y("company")}
            />
          </Field>
          <Field label={y("status")}>
            <PSelect
              value={filters.status}
              onValueChange={(v) => setF("status", v)}
              options={[
                { value: "", label: y("any") },
                ...options.statuses.map((s) => ({
                  value: s,
                  label: y(`cs_status.${s}`),
                })),
              ]}
              aria-label={y("status")}
            />
          </Field>
          <Field label={y("from")}>
            <Input
              type="date"
              value={filters.from}
              onChange={(e) => setF("from", e.target.value)}
              aria-label={y("from")}
            />
          </Field>
          <Field label={y("to")}>
            <Input
              type="date"
              value={filters.to}
              onChange={(e) => setF("to", e.target.value)}
              aria-label={y("to")}
            />
          </Field>
        </div>
        <div className="flex flex-wrap gap-2 px-5 pt-4 pb-5">
          <Button onClick={() => search()} loading={pending}>
            <Search /> {y("show")}
          </Button>
          {canManage ? (
            <Button variant="outline" onClick={() => openDesk(null)}>
              <Plus /> {y("add_new")}
            </Button>
          ) : null}
          <Button
            variant="outline"
            onClick={() => (setFilters(blankF), search(blankF))}
            disabled={pending}
          >
            <RotateCcw /> {y("reset")}
          </Button>
        </div>
        {errors ? (
          <div className="px-5 pb-4">
            <Alert variant="danger">{errors.join(" · ")}</Alert>
          </div>
        ) : null}
        {rows.length === 0 ? (
          <NoLines title={y("cs.empty")} hint={y("nothing")} />
        ) : (
          <div className="overflow-x-auto border-t border-border">
            <table className="w-full min-w-[1100px] text-sm">
              <thead className="bg-brand text-brand-foreground">
                <tr>
                  {[
                    "no",
                    "date",
                    "requisition",
                    "req_date",
                    "buyer",
                    "lines",
                    "offers",
                    "qty",
                    "spinners",
                    "status",
                  ].map((k) => (
                    <th
                      key={k}
                      className={cn(
                        th,
                        ["lines", "offers", "qty"].includes(k) && "text-right",
                      )}
                    >
                      {y(`cs.lc.${k}`)}
                    </th>
                  ))}
                  <th className={th}>{y("actions")}</th>
                </tr>
              </thead>
              <tbody>
                {rows.map((r) => (
                  <tr key={r.cs_no} className="border-t border-border">
                    <td className="px-2 py-2 font-medium">
                      <button
                        type="button"
                        className="text-brand hover:underline"
                        onClick={() => openDesk(r.cs_no)}
                      >
                        {r.cs_no}
                      </button>
                    </td>
                    <td className="px-2 py-2 whitespace-nowrap">
                      {dayFmt(r.cs_date)}
                    </td>
                    <td className="px-2 py-2">{r.requisition_no ?? "—"}</td>
                    <td className="px-2 py-2 whitespace-nowrap">
                      {dayFmt(r.requisition_date)}
                    </td>
                    <td className="px-2 py-2">{r.buyer ?? "—"}</td>
                    <td className="px-2 py-2 text-right tabular-nums">
                      {r.lines}
                    </td>
                    <td className="px-2 py-2 text-right tabular-nums">
                      {r.offers}
                    </td>
                    <td className="px-2 py-2 text-right tabular-nums">
                      {qtyFmt.format(r.qty)}
                    </td>
                    <td className="max-w-[16rem] px-2 py-2">
                      {r.spinners || "—"}
                    </td>
                    <td className="px-2 py-2">
                      <StatusChip
                        status={r.status}
                        label={y(`cs_status.${r.status}`)}
                      />
                    </td>
                    <td className="px-2 py-1.5">
                      {canManage && r.status === "DRAFT" ? (
                        <div className="flex gap-1">
                          <Button
                            variant="success"
                            size="sm"
                            onClick={() => run(r.cs_no, "finalize")}
                            disabled={pending}
                          >
                            <CheckCheck /> {y("btn.finalize")}
                          </Button>
                          <Button
                            variant="outline"
                            size="sm"
                            className="text-danger"
                            onClick={() => run(r.cs_no, "cancel")}
                            disabled={pending}
                          >
                            <Ban /> {y("btn.cancel")}
                          </Button>
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
    );

  return (
    <div className="grid gap-4 2xl:grid-cols-[24rem_1fr]">
      <section className={cn(card, "self-start p-4")}>
        <h2 className="text-base font-semibold text-foreground">
          {y("cs.search_req")}
        </h2>
        <div className="relative mt-3">
          <Search className="pointer-events-none absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-foreground-muted" />
          <Input
            value={reqQuery}
            onChange={(e) => (setReqQuery(e.target.value), setPage(0))}
            placeholder={y("search_ph")}
            className="pl-8"
            aria-label={y("cs.search_req")}
          />
        </div>
        <div className="mt-3 overflow-x-auto rounded-lg border border-border">
          <table className="w-full text-sm">
            <thead className="bg-brand text-brand-foreground">
              <tr>
                {["no", "date", "buyer", "items", "required"].map((k) => (
                  <th
                    key={k}
                    className={cn(
                      th,
                      ["items", "required"].includes(k) && "text-right",
                    )}
                  >
                    {y(`cs.rc.${k}`)}
                  </th>
                ))}
                <th className={th} />
              </tr>
            </thead>
            <tbody>
              {shownReqs.slice(page * PAGE, page * PAGE + PAGE).map((r) => (
                <tr
                  key={r.value}
                  className={cn(
                    "border-t border-border",
                    head.requisition_no === r.value && "bg-brand-subtle",
                  )}
                >
                  <td className="px-2 py-2 font-medium whitespace-nowrap">
                    {r.value}
                  </td>
                  <td className="px-2 py-2 whitespace-nowrap">
                    {dayFmt(r.date)}
                  </td>
                  <td className="px-2 py-2">{r.buyer ?? "—"}</td>
                  <td className="px-2 py-2 text-right tabular-nums">
                    {r.items}
                  </td>
                  <td className="px-2 py-2 text-right tabular-nums">
                    {qtyFmt.format(r.required)}
                  </td>
                  <td className="px-2 py-1.5">
                    <Button
                      variant="outline"
                      size="sm"
                      onClick={() => compare(r.value)}
                      disabled={!editable || pending}
                    >
                      {y("cs.compare")}
                    </Button>
                  </td>
                </tr>
              ))}
              {shownReqs.length === 0 ? (
                <tr>
                  <td
                    colSpan={6}
                    className="px-3 py-6 text-center text-foreground-muted"
                  >
                    {y("cs.nothing_left")}
                  </td>
                </tr>
              ) : null}
            </tbody>
          </table>
        </div>
        <div className="mt-2 flex items-center justify-end gap-1 text-xs text-foreground-muted">
          <Button
            variant="outline"
            size="sm"
            onClick={() => setPage((p) => Math.max(0, p - 1))}
            disabled={page === 0}
            aria-label={y("prev")}
          >
            ‹
          </Button>
          {page + 1} / {pages}
          <Button
            variant="outline"
            size="sm"
            onClick={() => setPage((p) => Math.min(pages - 1, p + 1))}
            disabled={page >= pages - 1}
            aria-label={y("next")}
          >
            ›
          </Button>
        </div>
      </section>

      <div className="flex min-w-0 flex-col gap-4">
        <section className={cn(card, "p-5")}>
          <div className="flex items-start justify-between gap-3">
            <div>
              <h2 className="text-base font-semibold text-foreground">
                {y("cs.list_title")}
              </h2>
              <p className="mt-0.5 text-[13px] text-foreground-muted">
                {y("cs.desk_hint")}
              </p>
            </div>
            {doc ? (
              <StatusChip
                status={doc.status}
                label={
                  doc.editable
                    ? y("editing", { no: doc.cs_no })
                    : y("read_only", { status: y(`cs_status.${doc.status}`) })
                }
              />
            ) : null}
          </div>
          <Field
            label={y("cs.existing")}
            hint={y("yr.existing_hint")}
            className="mt-4"
          >
            <PSelect
              value={doc?.cs_no ?? ""}
              onValueChange={(v) => openDesk(v || null)}
              options={[
                { value: "", label: y("cs.existing_new") },
                ...options.comparisons,
              ]}
              aria-label={y("cs.existing")}
            />
          </Field>
          <div className="mt-4 grid gap-4 md:grid-cols-3">
            <Field label={y("cs.no")} hint={y("cs.no_hint")}>
              <Input
                value={doc?.cs_no ?? ""}
                placeholder={y("yr.no_ph")}
                readOnly
                className="bg-surface-muted"
                aria-label={y("cs.no")}
              />
            </Field>
            <Field label={y("cs.date")} required>
              <Input
                type="date"
                value={head.cs_date}
                onChange={(e) => setH({ cs_date: e.target.value })}
                disabled={!editable}
                aria-label={y("cs.date")}
              />
            </Field>
            <Field label={y("company")} required>
              <PSelect
                value={options.company.value}
                options={[options.company]}
                disabled
                aria-label={y("company")}
              />
            </Field>
            <Field
              label={y("cs.requisition")}
              required
              hint={y("cs.requisition_hint")}
            >
              <PSelect
                value={head.requisition_no}
                onValueChange={(v) => v && compare(v)}
                options={[
                  { value: "", label: y("select") },
                  ...options.requisitions.map((r) => ({
                    value: r.value,
                    label: r.label,
                  })),
                ]}
                disabled={!editable}
                aria-label={y("cs.requisition")}
              />
            </Field>
            <Field label={y("cs.req_date")} hint={y("cs.req_date_hint")}>
              <Input
                value={
                  head.requisition_date ? dayFmt(head.requisition_date) : ""
                }
                readOnly
                className="bg-surface-muted"
                aria-label={y("cs.req_date")}
              />
            </Field>
            <Field label={y("remarks")}>
              <Textarea
                rows={2}
                value={head.remarks}
                onChange={(e) => setH({ remarks: e.target.value })}
                maxLength={2000}
                disabled={!editable}
                aria-label={y("remarks")}
              />
            </Field>
          </div>
        </section>

        <section className={cn(card, "overflow-hidden")}>
          <div className="flex items-start justify-between gap-3 px-5 py-4">
            <div>
              <h2 className="text-base font-semibold text-foreground">
                {y("cs.details")}
              </h2>
              <p className="mt-0.5 text-[13px] text-foreground-muted">
                {y("cs.details_hint")}
              </p>
            </div>
            {editable ? (
              <Button
                variant="outline"
                size="sm"
                onClick={() =>
                  setLines((all) => [...all, asLine({ uom: "kg" })])
                }
              >
                <Plus /> {y("cs.add_item")}
              </Button>
            ) : null}
          </div>
          {lines.length === 0 ? (
            <NoLines title={y("no_lines")} hint={y("cs.no_lines_hint")} />
          ) : (
            <div className="overflow-x-auto">
              <table className="w-full min-w-[2400px] text-sm">
                <thead className="bg-brand text-brand-foreground">
                  <tr>
                    <th />
                    <th
                      colSpan={5}
                      className={cn(th, "border-b border-white/20 text-center")}
                    >
                      {y("col.composition")}
                    </th>
                    <th
                      colSpan={2}
                      className={cn(th, "border-b border-white/20 text-center")}
                    >
                      {y("col.require")}
                    </th>
                    <th
                      colSpan={4}
                      className={cn(th, "border-b border-white/20 text-center")}
                    >
                      {y("total_row")}
                    </th>
                    <th colSpan={4} />
                    <th
                      className={cn(th, "border-b border-white/20 text-center")}
                    >
                      {y("col.finalized_with")}
                    </th>
                    <th colSpan={3} />
                  </tr>
                  <tr>
                    {[
                      "sl",
                      ...YARN_HEADS,
                      "local",
                      "import",
                      "total",
                      "present_stock",
                      "pipeline",
                      "qty_kg",
                      "knit_start",
                      "ship",
                      "booking_price",
                      "buyer_name",
                      "spinner",
                      "remarks",
                      "copy",
                      "remove",
                    ].map((k) => (
                      <th
                        key={k}
                        className={cn(
                          th,
                          [
                            "local",
                            "import",
                            "total",
                            "present_stock",
                            "pipeline",
                            "qty_kg",
                            "booking_price",
                          ].includes(k) && "text-right",
                        )}
                      >
                        {y(`col.${k}`)}
                      </th>
                    ))}
                  </tr>
                </thead>
                <tbody>
                  {lines.map((l, i) => (
                    <tr key={l.key} className="border-t border-border">
                      <td className="px-2 py-1.5">
                        <QtyInput
                          value={l.sl_no}
                          onChange={(v) => setLine(l.key, { sl_no: v })}
                          disabled={!editable}
                          label={`${y("col.sl")} ${i + 1}`}
                          w="w-16"
                        />
                      </td>
                      <YarnCells
                        line={l}
                        set={(p) => setLine(l.key, p)}
                        options={options}
                        disabled={!editable}
                        n={i + 1}
                      />
                      <td className="px-2 py-1.5">
                        <QtyInput
                          value={l.qty_local}
                          onChange={(v) => setLine(l.key, { qty_local: v })}
                          disabled={!editable}
                          label={`${y("col.local")} ${i + 1}`}
                        />
                      </td>
                      <td className="px-2 py-1.5">
                        <QtyInput
                          value={l.qty_import}
                          onChange={(v) => setLine(l.key, { qty_import: v })}
                          disabled={!editable}
                          label={`${y("col.import")} ${i + 1}`}
                        />
                      </td>
                      <td className="px-2 py-1.5 text-right font-semibold tabular-nums">
                        {qtyFmt.format(total(l))}
                      </td>
                      <td className="px-2 py-1.5">
                        <QtyInput
                          value={l.present_stock}
                          onChange={(v) => setLine(l.key, { present_stock: v })}
                          disabled={!editable}
                          label={`${y("col.present_stock")} ${i + 1}`}
                        />
                      </td>
                      <td className="px-2 py-1.5">
                        <QtyInput
                          value={l.pipeline}
                          onChange={(v) => setLine(l.key, { pipeline: v })}
                          disabled={!editable}
                          label={`${y("col.pipeline")} ${i + 1}`}
                        />
                      </td>
                      <td className="px-2 py-1.5 text-right font-semibold tabular-nums">
                        {qtyFmt.format(worth(l))}
                      </td>
                      <td className="px-2 py-1.5">
                        <DateInput
                          value={l.knit_start_date}
                          onChange={(v) =>
                            setLine(l.key, { knit_start_date: v })
                          }
                          disabled={!editable}
                          label={`${y("col.knit_start")} ${i + 1}`}
                        />
                      </td>
                      <td className="px-2 py-1.5">
                        <DateInput
                          value={l.ship_date}
                          onChange={(v) => setLine(l.key, { ship_date: v })}
                          disabled={!editable}
                          label={`${y("col.ship")} ${i + 1}`}
                        />
                      </td>
                      <td className="px-2 py-1.5">
                        <QtyInput
                          value={l.booking_price}
                          onChange={(v) => setLine(l.key, { booking_price: v })}
                          disabled={!editable}
                          label={`${y("col.booking_price")} ${i + 1}`}
                        />
                      </td>
                      <td className="px-2 py-1.5">
                        <div className="w-36">
                          <PSelect
                            value={l.buyer_id}
                            onValueChange={(v) =>
                              setLine(l.key, { buyer_id: v ?? "" })
                            }
                            options={[
                              { value: "", label: "—" },
                              ...options.buyers,
                            ]}
                            disabled={!editable}
                            aria-label={`${y("col.buyer_name")} ${i + 1}`}
                          />
                        </div>
                      </td>
                      <td className="px-2 py-1.5">
                        <div className="w-52">
                          <PSelect
                            value={l.supplier_id}
                            onValueChange={(v) =>
                              setLine(l.key, { supplier_id: v ?? "" })
                            }
                            options={suppliers}
                            disabled={!editable}
                            aria-label={`${y("col.spinner")} ${i + 1}`}
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
                          className="h-8 w-36"
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
                            { ...l, key: key(), sl_no: "" },
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
              </table>
            </div>
          )}
        </section>

        <section className={cn(card, "overflow-hidden")}>
          <div className="flex items-start justify-between gap-3 px-5 py-4">
            <div>
              <h2 className="text-base font-semibold text-foreground">
                {y("cs.offers")}
              </h2>
              <p className="mt-0.5 text-[13px] text-foreground-muted">
                {y("cs.offers_hint")}
              </p>
            </div>
            {editable ? (
              <Button
                variant="outline"
                size="sm"
                onClick={() =>
                  setOffers((all) => [
                    ...all,
                    asOffer({ sl_no: lines[0]?.sl_no ?? "" }),
                  ])
                }
              >
                <Plus /> {y("cs.add_offer")}
              </Button>
            ) : null}
          </div>
          {offers.length === 0 ? (
            <NoLines title={y("no_lines")} hint={y("cs.no_offers")} />
          ) : (
            <div className="overflow-x-auto">
              <table className="w-full min-w-[1200px] text-sm">
                <thead className="bg-brand text-brand-foreground">
                  <tr>
                    {[
                      "sl",
                      "source",
                      "spinner",
                      "price",
                      "currency",
                      "conf_qty",
                      "lead",
                      "remarks",
                      "remove",
                    ].map((k) => (
                      <th
                        key={k}
                        className={cn(
                          th,
                          ["price", "conf_qty", "lead"].includes(k) &&
                            "text-right",
                        )}
                      >
                        {y(`col.${k}`)}
                      </th>
                    ))}
                  </tr>
                </thead>
                <tbody>
                  {offers.map((o, i) => {
                    const best =
                      o.sl_no &&
                      num(o.price) > 0 &&
                      lowest[o.sl_no] === num(o.price);
                    return (
                      <tr
                        key={o.key}
                        className={cn(
                          "border-t border-border",
                          best && "bg-success-subtle/60",
                        )}
                      >
                        <td className="px-2 py-1.5">
                          <QtyInput
                            value={o.sl_no}
                            onChange={(v) => setOffer(o.key, { sl_no: v })}
                            disabled={!editable}
                            label={`${y("col.sl")} ${y("cs.offers")} ${i + 1}`}
                            w="w-16"
                          />
                        </td>
                        <td className="px-2 py-1.5">
                          <div className="w-28">
                            <PSelect
                              value={o.source}
                              onValueChange={(v) =>
                                setOffer(o.key, { source: v ?? "LOCAL" })
                              }
                              options={options.sources.map((s) => ({
                                value: s,
                                label: y(`source.${s}`),
                              }))}
                              disabled={!editable}
                              aria-label={`${y("col.source")} ${i + 1}`}
                            />
                          </div>
                        </td>
                        <td className="px-2 py-1.5">
                          <div className="w-56">
                            <PSelect
                              value={o.supplier_id}
                              onValueChange={(v) =>
                                setOffer(o.key, { supplier_id: v ?? "" })
                              }
                              options={suppliers}
                              disabled={!editable}
                              aria-label={`${y("col.spinner")} ${y("cs.offers")} ${i + 1}`}
                            />
                          </div>
                        </td>
                        <td className="px-2 py-1.5">
                          <div className="flex items-center justify-end gap-2">
                            {best ? (
                              <span className="rounded bg-success px-1.5 py-0.5 text-[11px] font-semibold text-white">
                                {y("cs.lowest")}
                              </span>
                            ) : null}
                            <QtyInput
                              value={o.price}
                              onChange={(v) => setOffer(o.key, { price: v })}
                              disabled={!editable}
                              label={`${y("col.price")} ${i + 1}`}
                            />
                          </div>
                        </td>
                        <td className="px-2 py-1.5">
                          <div className="w-36">
                            <PSelect
                              value={o.currency_id}
                              onValueChange={(v) =>
                                setOffer(o.key, { currency_id: v ?? "" })
                              }
                              options={[
                                { value: "", label: "—" },
                                ...options.currencies,
                              ]}
                              disabled={!editable}
                              aria-label={`${y("col.currency")} ${i + 1}`}
                            />
                          </div>
                        </td>
                        <td className="px-2 py-1.5">
                          <QtyInput
                            value={o.conf_qty}
                            onChange={(v) => setOffer(o.key, { conf_qty: v })}
                            disabled={!editable}
                            label={`${y("col.conf_qty")} ${i + 1}`}
                          />
                        </td>
                        <td className="px-2 py-1.5">
                          <QtyInput
                            value={o.lead_days}
                            onChange={(v) => setOffer(o.key, { lead_days: v })}
                            disabled={!editable}
                            label={`${y("col.lead")} ${i + 1}`}
                            w="w-20"
                          />
                        </td>
                        <td className="px-2 py-1.5">
                          <Input
                            value={o.remarks}
                            onChange={(e) =>
                              setOffer(o.key, { remarks: e.target.value })
                            }
                            maxLength={300}
                            className="h-8 w-40"
                            disabled={!editable}
                            aria-label={`${y("col.remarks")} ${y("cs.offers")} ${i + 1}`}
                          />
                        </td>
                        <td className="px-1 py-1.5">
                          <Button
                            variant="outline"
                            size="icon"
                            className="size-8 text-danger"
                            onClick={() =>
                              setOffers((all) =>
                                all.filter((x) => x.key !== o.key),
                              )
                            }
                            disabled={!editable}
                            aria-label={`${y("remove_line")} ${y("cs.offers")} ${i + 1}`}
                          >
                            <Trash2 />
                          </Button>
                        </td>
                      </tr>
                    );
                  })}
                </tbody>
              </table>
            </div>
          )}
        </section>

        {errors ? <Alert variant="danger">{errors.join(" · ")}</Alert> : null}
        <section className={cn(card, "flex flex-col gap-3 p-5")}>
          <p className="text-[13px] text-foreground-muted">{y("cs.foot")}</p>
          <div className="flex flex-wrap gap-2">
            <Button variant="outline" onClick={backToList}>
              <ArrowLeft /> {y("back")}
            </Button>
            <Button
              variant="outline"
              onClick={() => clear(true)}
              disabled={pending}
            >
              <RotateCcw /> {y("cancel")}
            </Button>
            {doc && editable ? (
              <Button
                variant="outline"
                className="text-danger"
                onClick={() => setConfirm(true)}
                disabled={pending}
              >
                <Trash2 /> {y("delete")}
              </Button>
            ) : null}
            {editable ? (
              <Button onClick={save} loading={pending}>
                <Save /> {y("cs.save")}
              </Button>
            ) : null}
          </div>
        </section>
      </div>
      <ConfirmDialog
        open={confirm}
        onOpenChange={setConfirm}
        title={y("delete")}
        description={doc ? y("confirm_delete", { no: doc.cs_no }) : ""}
        confirmLabel={y("delete")}
        cancelLabel={y("cancel")}
        destructive
        onConfirm={remove}
        loading={pending}
      />
    </div>
  );
}

export { ComparisonScreen };
