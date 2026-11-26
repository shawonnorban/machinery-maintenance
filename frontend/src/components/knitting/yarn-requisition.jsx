"use client";

import { useState, useTransition } from "react";
import Link from "next/link";
import {
  ArrowLeft,
  Ban,
  Check,
  Lock,
  Pencil,
  Plus,
  RotateCcw,
  Save,
  Search,
  Send,
  Trash2,
  X,
} from "lucide-react";
import { cn } from "@/lib/utils";
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
 * Knit Production → Yarn Procurement (AnnotechERP 15/02): Yarn Purchase
 * Indent — the YR/PR register (search, Submit, Cancel, Close) and the entry
 * desk (the requisition, what it is raised against, the yarn required line
 * by line, local and import apart) — and the Yarn Indent Approval List
 * (tick, write the decision note, Approve or Reject). Nothing reloads.
 */

const ENTRY = "/knit-production/yarn-purchase-requisition?tab=entry";

/** The YR/PR register: nothing filled in lists the most recent requisitions. */
function RequisitionList({ options, initialRows, canManage, act }) {
  const y = useYarn();
  const toastManager = useToastManager();
  const blank = { no: "", buyer: "", status: "", from: "", to: "" };
  const [filters, setFilters] = useState(blank);
  const [rows, setRows] = useState(initialRows);
  const [error, setError] = useState(null);
  const [pending, startTransition] = useTransition();
  const setF = (k, v) => setFilters((f) => ({ ...f, [k]: v ?? "" }));

  function search(f = filters) {
    startTransition(async () => {
      const res = await act("requisitions", "list", { query: f });
      if (res?.status === "success") {
        setRows(res.data);
        setError(null);
      } else setError(messagesOf(res));
    });
  }
  function run(no, action) {
    startTransition(async () => {
      const res = await act("requisitions", action, { no });
      if (res?.status !== "success") return setError(messagesOf(res));
      toastManager.add({ title: y(`done.${action}`, { no }), type: "success" });
      search();
    });
  }

  return (
    <section className={card}>
      <div className="px-5 pt-5">
        <h2 className="text-base font-semibold text-foreground">
          {y("yr.list_title")}
        </h2>
        <p className="mt-0.5 text-[13px] text-foreground-muted">
          {y("yr.list_hint")}
        </p>
      </div>
      <div className="grid gap-4 px-5 pt-4 sm:grid-cols-2 lg:grid-cols-6">
        <Field label={y("yr.no")}>
          <Input
            value={filters.no}
            onChange={(e) => setF("no", e.target.value)}
            placeholder={y("search_ph")}
            aria-label={y("yr.no")}
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
        <Field label={y("buyer")}>
          <PSelect
            value={filters.buyer}
            onValueChange={(v) => setF("buyer", v)}
            options={[{ value: "", label: y("select") }, ...options.buyers]}
            aria-label={y("buyer")}
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
                label: y(`status.${s}`),
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
          <Link href={ENTRY} className={buttonVariants({ variant: "outline" })}>
            <Plus /> {y("add_new")}
          </Link>
        ) : null}
        <Button
          variant="outline"
          onClick={() => (setFilters(blank), search(blank))}
          disabled={pending}
        >
          <RotateCcw /> {y("reset")}
        </Button>
      </div>
      {error ? (
        <div className="px-5 pb-4">
          <Alert variant="danger">{error.join(" · ")}</Alert>
        </div>
      ) : null}
      {rows.length === 0 ? (
        <NoLines title={y("yr.empty")} hint={y("nothing")} />
      ) : (
        <div className="overflow-x-auto border-t border-border">
          <table className="w-full min-w-[1200px] text-sm">
            <thead className="bg-brand text-brand-foreground">
              <tr>
                {[
                  "no",
                  "date",
                  "store",
                  "buyer",
                  "order",
                  "required",
                  "lines",
                  "local",
                  "import",
                  "total",
                  "status",
                ].map((k) => (
                  <th
                    key={k}
                    className={cn(
                      th,
                      ["lines", "local", "import", "total"].includes(k) &&
                        "text-right",
                    )}
                  >
                    {y(`yr.lc.${k}`)}
                  </th>
                ))}
                <th className={th}>{y("actions")}</th>
              </tr>
            </thead>
            <tbody>
              {rows.map((r) => (
                <tr key={r.requisition_no} className="border-t border-border">
                  <td className="px-2 py-2 font-medium">
                    <Link
                      href={`${ENTRY}&ref=${encodeURIComponent(r.requisition_no)}`}
                      className="text-brand hover:underline"
                    >
                      {r.requisition_no}
                    </Link>
                  </td>
                  <td className="px-2 py-2 whitespace-nowrap">
                    {dayFmt(r.requisition_date)}
                  </td>
                  <td className="px-2 py-2">{r.store ?? "—"}</td>
                  <td className="px-2 py-2">{r.buyer ?? "—"}</td>
                  <td className="px-2 py-2">{r.order_no ?? "—"}</td>
                  <td className="px-2 py-2 whitespace-nowrap">
                    {dayFmt(r.required_date)}
                  </td>
                  <td className="px-2 py-2 text-right tabular-nums">
                    {r.lines}
                  </td>
                  <td className="px-2 py-2 text-right tabular-nums">
                    {qtyFmt.format(r.qty_local)}
                  </td>
                  <td className="px-2 py-2 text-right tabular-nums">
                    {qtyFmt.format(r.qty_import)}
                  </td>
                  <td className="px-2 py-2 text-right font-semibold tabular-nums">
                    {qtyFmt.format(r.total)}
                  </td>
                  <td className="px-2 py-2">
                    <StatusChip
                      status={r.status}
                      label={y(`status.${r.status}`)}
                    />
                    {r.decision_note ? (
                      <p className="mt-1 max-w-[16rem] text-xs text-foreground-muted">
                        {y("yr.note", { note: r.decision_note })}
                      </p>
                    ) : null}
                  </td>
                  <td className="px-2 py-1.5">
                    {canManage ? (
                      <div className="flex gap-1">
                        {r.editable ? (
                          <>
                            <Link
                              href={`${ENTRY}&ref=${encodeURIComponent(r.requisition_no)}`}
                              className={buttonVariants({
                                variant: "outline",
                                size: "sm",
                              })}
                            >
                              <Pencil /> {y("edit")}
                            </Link>
                            <Button
                              variant="outline"
                              size="sm"
                              onClick={() => run(r.requisition_no, "submit")}
                              disabled={pending}
                            >
                              <Send /> {y("btn.submit")}
                            </Button>
                            <Button
                              variant="outline"
                              size="sm"
                              className="text-danger"
                              onClick={() => run(r.requisition_no, "cancel")}
                              disabled={pending}
                            >
                              <Ban /> {y("btn.cancel")}
                            </Button>
                          </>
                        ) : null}
                        {["APPROVED", "ORDERED"].includes(r.status) ? (
                          <Button
                            variant="outline"
                            size="sm"
                            onClick={() => run(r.requisition_no, "close")}
                            disabled={pending}
                          >
                            <Lock /> {y("btn.close")}
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
  );
}

const blankHead = (today) => ({
  requisition_date: today,
  store_id: "",
  buyer_id: "",
  order_id: "",
  required_date: "",
  remarks: "",
});
const asLine = (l = {}) => ({
  key: key(),
  composition_id: str(l.composition_id),
  yarn_count_id: str(l.yarn_count_id),
  colour: str(l.colour),
  brand: str(l.brand),
  uom: str(l.uom),
  qty_local: str(l.qty_local),
  qty_import: str(l.qty_import),
  knit_start_date: str(l.knit_start_date),
  ship_date: str(l.ship_date),
  booking_price: str(l.booking_price),
  remarks: str(l.remarks),
});

/** The entry desk: header and lines saved together; a draft or a rejected one is rewritten. */
function RequisitionEntry({ options, initial, canManage, act }) {
  const y = useYarn();
  const toastManager = useToastManager();
  const [today] = useState(todayIso);
  const [doc, setDoc] = useState(initial);
  const [head, setHead] = useState(() =>
    initial
      ? Object.fromEntries(
          Object.keys(blankHead(today)).map((k) => [k, str(initial[k])]),
        )
      : blankHead(today),
  );
  const [lines, setLines] = useState(() => (initial?.lines ?? []).map(asLine));
  const [errors, setErrors] = useState(null);
  const [confirm, setConfirm] = useState(false);
  const [pending, startTransition] = useTransition();
  const editable = canManage && (!doc || doc.editable);
  const setH = (patch) => setHead((h) => ({ ...h, ...patch }));
  const setLine = (k, patch) =>
    setLines((all) => all.map((l) => (l.key === k ? { ...l, ...patch } : l)));
  const orders = options.orders.filter(
    (o) => !head.buyer_id || o.buyer_id === head.buyer_id,
  );
  const total = (l) => num(l.qty_local) + num(l.qty_import);

  function take(data) {
    setDoc(data);
    setHead(
      Object.fromEntries(
        Object.keys(blankHead(today)).map((k) => [k, str(data[k])]),
      ),
    );
    setLines(data.lines.map(asLine));
    setErrors(null);
    window.history.replaceState(
      null,
      "",
      `?tab=entry&ref=${encodeURIComponent(data.requisition_no)}`,
    );
  }
  function clear() {
    setDoc(null);
    setHead(blankHead(today));
    setLines([]);
    setErrors(null);
    window.history.replaceState(null, "", "?tab=entry");
  }
  function load(no) {
    if (!no) return clear();
    startTransition(async () => {
      const res = await act("requisitions", "show", { no });
      if (res?.status === "success") take(res.data);
      else setErrors(messagesOf(res));
    });
  }
  function save() {
    startTransition(async () => {
      const body = {
        ...Object.fromEntries(
          Object.entries(head).map(([k, v]) => [k, v === "" ? null : v]),
        ),
        lines: lines.map((l) => ({
          ...yarnPayload(l),
          qty_local: orNull(l.qty_local),
          qty_import: orNull(l.qty_import),
          booking_price: orNull(l.booking_price),
        })),
      };
      const res = await act("requisitions", "save", {
        no: doc?.requisition_no,
        body,
      });
      if (res?.status === "success") {
        take(res.data);
        toastManager.add({
          title: y(doc ? "updated" : "saved", { no: res.data.requisition_no }),
          type: "success",
        });
      } else setErrors(messagesOf(res));
    });
  }
  function remove() {
    startTransition(async () => {
      const res = await act("requisitions", "remove", {
        no: doc.requisition_no,
      });
      setConfirm(false);
      if (res?.status !== "success") return setErrors(messagesOf(res));
      toastManager.add({
        title: y("done.delete", { no: doc.requisition_no }),
        type: "success",
      });
      clear();
    });
  }

  return (
    <div className="flex flex-col gap-4">
      <section className={cn(card, "p-5")}>
        <div className="flex items-start justify-between gap-3">
          <div>
            <h2 className="text-base font-semibold text-foreground">
              {y("tabs.yr_entry")}
            </h2>
            <p className="mt-0.5 text-[13px] text-foreground-muted">
              {y("yr.entry_hint")}
            </p>
          </div>
          {doc ? (
            <StatusChip
              status={doc.status}
              label={
                doc.editable
                  ? y("editing", { no: doc.requisition_no })
                  : y("read_only", { status: y(`status.${doc.status}`) })
              }
            />
          ) : null}
        </div>
        <Field
          label={y("yr.existing")}
          hint={y("yr.existing_hint")}
          className="mt-4"
        >
          <PSelect
            value={doc?.requisition_no ?? ""}
            onValueChange={(v) => load(v ?? "")}
            options={[
              { value: "", label: y("yr.existing_new") },
              ...options.requisitions,
            ]}
            aria-label={y("yr.existing")}
          />
        </Field>
        {doc?.decision_note ? (
          <Alert variant="warning" className="mt-4">
            {y("yr.note", { note: doc.decision_note })}
          </Alert>
        ) : null}
        <h3 className="mt-5 text-sm font-semibold text-brand">
          {y("yr.sec_req")}
        </h3>
        <div className="mt-2 grid gap-4 md:grid-cols-3">
          <Field label={y("yr.no")} hint={y("yr.no_hint")}>
            <Input
              value={doc?.requisition_no ?? ""}
              placeholder={y("yr.no_ph")}
              readOnly
              className="bg-surface-muted"
              aria-label={y("yr.no")}
            />
          </Field>
          <Field label={y("yr.date")} required>
            <Input
              type="date"
              value={head.requisition_date}
              onChange={(e) => setH({ requisition_date: e.target.value })}
              disabled={!editable}
              aria-label={y("yr.date")}
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
          <Field label={y("store")} required hint={y("yr.store_hint")}>
            <PSelect
              value={head.store_id}
              onValueChange={(v) => setH({ store_id: v ?? "" })}
              options={[{ value: "", label: y("store_ph") }, ...options.stores]}
              disabled={!editable}
              aria-label={y("store")}
            />
          </Field>
          <Field label={y("yr.required_date")} hint={y("yr.required_hint")}>
            <Input
              type="date"
              value={head.required_date}
              onChange={(e) => setH({ required_date: e.target.value })}
              disabled={!editable}
              aria-label={y("yr.required_date")}
            />
          </Field>
        </div>
        <h3 className="mt-5 text-sm font-semibold text-brand">
          {y("yr.sec_against")}
        </h3>
        <div className="mt-2 grid gap-4 md:grid-cols-3">
          <Field label={y("buyer")}>
            <PSelect
              value={head.buyer_id}
              onValueChange={(v) => setH({ buyer_id: v ?? "", order_id: "" })}
              options={[{ value: "", label: y("select") }, ...options.buyers]}
              disabled={!editable}
              aria-label={y("buyer")}
            />
          </Field>
          <Field label={y("order")}>
            <PSelect
              value={head.order_id}
              onValueChange={(v) =>
                setH({
                  order_id: v ?? "",
                  buyer_id:
                    head.buyer_id ||
                    options.orders.find((o) => o.value === v)?.buyer_id ||
                    "",
                })
              }
              options={[{ value: "", label: y("select") }, ...orders]}
              disabled={!editable}
              aria-label={y("order")}
            />
          </Field>
          <Field label={y("remarks")} className="md:col-span-3">
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
              {y("yr.sec_lines")}
            </h2>
            <p className="mt-0.5 text-[13px] text-foreground-muted">
              {y("yr.lines_hint")}
            </p>
          </div>
          {editable ? (
            <Button
              variant="outline"
              size="sm"
              onClick={() => setLines((all) => [...all, asLine({ uom: "kg" })])}
            >
              <Plus /> {y("yr.add")}
            </Button>
          ) : null}
        </div>
        {lines.length === 0 ? (
          <NoLines title={y("no_lines")} hint={y("yr.no_lines_hint")} />
        ) : (
          <div className="overflow-x-auto">
            <table className="w-full min-w-[1700px] text-sm">
              <thead className="bg-brand text-brand-foreground">
                <tr>
                  <th
                    colSpan={5}
                    className={cn(th, "border-b border-white/20 text-center")}
                  >
                    {y("col.yarn")}
                  </th>
                  <th
                    colSpan={3}
                    className={cn(th, "border-b border-white/20 text-center")}
                  >
                    {y("col.require")}
                  </th>
                  <th
                    colSpan={2}
                    className={cn(th, "border-b border-white/20 text-center")}
                  >
                    {y("col.schedule")}
                  </th>
                  <th colSpan={4} />
                </tr>
                <tr>
                  {[
                    ...YARN_HEADS,
                    "local",
                    "import",
                    "total",
                    "knit_start",
                    "ship",
                    "booking_price",
                    "remarks",
                    "copy",
                    "remove",
                  ].map((k) => (
                    <th
                      key={k}
                      className={cn(
                        th,
                        ["local", "import", "total", "booking_price"].includes(
                          k,
                        ) && "text-right",
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
                      {total(l) ? qtyFmt.format(total(l)) : "—"}
                    </td>
                    <td className="px-2 py-1.5">
                      <DateInput
                        value={l.knit_start_date}
                        onChange={(v) => setLine(l.key, { knit_start_date: v })}
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
                          { ...l, key: key() },
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
                  <td colSpan={5} className="px-2 py-2">
                    {y("total_row")}
                  </td>
                  <td className="px-2 py-2 text-right tabular-nums">
                    {qtyFmt.format(
                      lines.reduce((s, l) => s + num(l.qty_local), 0),
                    )}
                  </td>
                  <td className="px-2 py-2 text-right tabular-nums">
                    {qtyFmt.format(
                      lines.reduce((s, l) => s + num(l.qty_import), 0),
                    )}
                  </td>
                  <td className="px-2 py-2 text-right tabular-nums">
                    {qtyFmt.format(lines.reduce((s, l) => s + total(l), 0))}
                  </td>
                  <td colSpan={6} />
                </tr>
              </tfoot>
            </table>
          </div>
        )}
      </section>

      {errors ? <Alert variant="danger">{errors.join(" · ")}</Alert> : null}
      <section className={cn(card, "flex flex-col gap-3 p-5")}>
        <p className="text-[13px] text-foreground-muted">{y("yr.foot")}</p>
        <div className="flex flex-wrap gap-2">
          <Link
            href="/knit-production/yarn-purchase-requisition"
            className={buttonVariants({ variant: "outline" })}
          >
            <ArrowLeft /> {y("back")}
          </Link>
          <Button variant="outline" onClick={clear} disabled={pending}>
            <RotateCcw /> {y("clear")}
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
              <Save /> {y("yr.save")}
            </Button>
          ) : null}
        </div>
      </section>
      <ConfirmDialog
        open={confirm}
        onOpenChange={setConfirm}
        title={y("delete")}
        description={doc ? y("confirm_delete", { no: doc.requisition_no }) : ""}
        confirmLabel={y("delete")}
        cancelLabel={y("cancel")}
        destructive
        onConfirm={remove}
        loading={pending}
      />
    </div>
  );
}

/** Yarn Indent Approval List: tick, note, approve or reject. */
function ApprovalList({ options, initialRows, act }) {
  const y = useYarn();
  const toastManager = useToastManager();
  const blank = { no: "", buyer: "", from: "", to: "" };
  const [filters, setFilters] = useState(blank);
  const [rows, setRows] = useState(initialRows);
  const [ticked, setTicked] = useState([]);
  const [note, setNote] = useState("");
  const [error, setError] = useState(null);
  const [pending, startTransition] = useTransition();
  const setF = (k, v) => setFilters((f) => ({ ...f, [k]: v ?? "" }));

  function search(f = filters) {
    startTransition(async () => {
      const res = await act("requisitions", "approvals", { query: f });
      if (res?.status === "success") {
        setRows(res.data);
        setTicked([]);
        setError(null);
      } else setError(messagesOf(res));
    });
  }
  function decide(action) {
    startTransition(async () => {
      const res = await act("requisitions", "decide", {
        body: { action, numbers: ticked, note: note || null },
      });
      if (res?.status !== "success") return setError(messagesOf(res));
      toastManager.add({
        title: y(`done.${action}`, { count: ticked.length }),
        type: "success",
      });
      setNote("");
      search();
    });
  }
  const toggle = (no, on) =>
    setTicked((all) => (on ? [...all, no] : all.filter((x) => x !== no)));

  return (
    <section className={card}>
      <div className="px-5 pt-5">
        <h2 className="text-base font-semibold text-foreground">
          {y("ap.title")}
        </h2>
        <p className="mt-0.5 text-[13px] text-foreground-muted">
          {y("ap.hint")}
        </p>
      </div>
      <div className="grid gap-4 px-5 pt-4 sm:grid-cols-2 lg:grid-cols-5">
        <Field label={y("yr.no")}>
          <Input
            value={filters.no}
            onChange={(e) => setF("no", e.target.value)}
            placeholder={y("search_ph")}
            aria-label={y("yr.no")}
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
        <Field label={y("buyer")}>
          <PSelect
            value={filters.buyer}
            onValueChange={(v) => setF("buyer", v)}
            options={[{ value: "", label: y("select") }, ...options.buyers]}
            aria-label={y("buyer")}
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
        <Field
          label={y("ap.note")}
          hint={y("ap.note_hint")}
          className="lg:col-span-5"
        >
          <Input
            value={note}
            onChange={(e) => setNote(e.target.value)}
            placeholder={y("ap.note_ph")}
            maxLength={255}
            aria-label={y("ap.note")}
          />
        </Field>
      </div>
      <div className="flex flex-wrap items-center gap-2 px-5 pt-4 pb-5">
        <Button onClick={() => search()} loading={pending}>
          <Search /> {y("show")}
        </Button>
        <Button
          variant="outline"
          onClick={() => (setFilters(blank), search(blank))}
          disabled={pending}
        >
          <RotateCcw /> {y("reset")}
        </Button>
        <span className="mx-2 text-sm text-foreground-muted">
          {ticked.length
            ? y("ap.selected", { n: ticked.length })
            : y("ap.pick")}
        </span>
        <Button
          variant="success"
          onClick={() => decide("approve")}
          disabled={!ticked.length || pending}
        >
          <Check /> {y("btn.approve")}
        </Button>
        <Button
          variant="outline"
          className="text-danger"
          onClick={() => decide("reject")}
          disabled={!ticked.length || pending}
        >
          <X /> {y("btn.reject")}
        </Button>
      </div>
      {error ? (
        <div className="px-5 pb-4">
          <Alert variant="danger">{error.join(" · ")}</Alert>
        </div>
      ) : null}
      {rows.length === 0 ? (
        <NoLines title={y("ap.empty")} hint={y("nothing")} />
      ) : (
        <div className="overflow-x-auto border-t border-border">
          <table className="w-full min-w-[1100px] text-sm">
            <thead className="bg-brand text-brand-foreground">
              <tr>
                <th className={cn(th, "w-10")}>
                  <Checkbox
                    checked={ticked.length === rows.length}
                    onCheckedChange={(c) =>
                      setTicked(
                        c === true ? rows.map((r) => r.requisition_no) : [],
                      )
                    }
                    aria-label={y("ap.pick")}
                  />
                </th>
                {[
                  "no",
                  "date",
                  "store",
                  "buyer",
                  "order",
                  "required",
                  "lines",
                  "total",
                  "raised_by",
                ].map((k) => (
                  <th
                    key={k}
                    className={cn(
                      th,
                      ["lines", "total"].includes(k) && "text-right",
                    )}
                  >
                    {y(`yr.lc.${k}`)}
                  </th>
                ))}
              </tr>
            </thead>
            <tbody>
              {rows.map((r) => (
                <tr
                  key={r.requisition_no}
                  className={cn(
                    "border-t border-border",
                    ticked.includes(r.requisition_no) && "bg-brand-subtle",
                  )}
                >
                  <td className="px-2 py-2">
                    <Checkbox
                      checked={ticked.includes(r.requisition_no)}
                      onCheckedChange={(c) =>
                        toggle(r.requisition_no, c === true)
                      }
                      aria-label={r.requisition_no}
                    />
                  </td>
                  <td className="px-2 py-2 font-medium">{r.requisition_no}</td>
                  <td className="px-2 py-2 whitespace-nowrap">
                    {dayFmt(r.requisition_date)}
                  </td>
                  <td className="px-2 py-2">{r.store ?? "—"}</td>
                  <td className="px-2 py-2">{r.buyer ?? "—"}</td>
                  <td className="px-2 py-2">{r.order_no ?? "—"}</td>
                  <td className="px-2 py-2 whitespace-nowrap">
                    {dayFmt(r.required_date)}
                  </td>
                  <td className="px-2 py-2 text-right tabular-nums">
                    {r.lines}
                  </td>
                  <td className="px-2 py-2 text-right font-semibold tabular-nums">
                    {qtyFmt.format(r.total)}
                  </td>
                  <td className="px-2 py-2">{r.raised_by ?? "—"}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
    </section>
  );
}

export { RequisitionList, RequisitionEntry, ApprovalList };
