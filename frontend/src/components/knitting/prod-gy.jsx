"use client";

import { useState, useTransition } from "react";
import Link from "next/link";
import {
  ArrowLeft,
  Ban,
  Eraser,
  FilterX,
  Lock,
  Plus,
  Save,
  Trash2,
} from "lucide-react";
import { cn } from "@/lib/utils";
import { useT } from "@/lib/i18n";
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
  useYs,
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
 * Knit Production → Production (AnnotechERP 15/04) → General Yarn Issue
 * Indent: the floor's yarn requisitions — the same requisitions the Yarn
 * Store's Issue Against Requisition (15/03) issues against. The list (Date,
 * Type, Style No, Order No, Status and Requisition No above; Close and
 * Cancel) and the form: Requisition, From, Booked Against, Standing (the
 * status the store's issues have brought it to, and Closed), then the yarn
 * asked for, one line per yarn, colour group, brand and lot. Nothing reloads.
 */

const BASE = "/knit-production/general-requisition-for-yarn-issue";
const TONE = {
  OPEN: "PLACED",
  PARTLY_ISSUED: "PART_RECEIVED",
  FULLY_ISSUED: "RECEIVED",
  CLOSED: "CLOSED",
  CANCELLED: "CANCELLED",
};
const CLOSABLE = ["OPEN", "PARTLY_ISSUED"];

function useGy() {
  const t = useT("knitting");
  return (k, p) => t(`gy.${k}`, p);
}

function GyList({ options, initialRows, canManage, act }) {
  const c = useGy();
  const ys = useYs();
  const toastManager = useToastManager();
  const blank = {
    from: "",
    to: "",
    type: "",
    style: "",
    order: "",
    status: "",
    no: "",
  };
  const [f, setFilters] = useState(blank);
  const [rows, setRows] = useState(initialRows);
  const [error, setError] = useState(null);
  const [ask, setAsk] = useState(null);
  const [pending, startTransition] = useTransition();
  const setF = (k, v) => setFilters((x) => ({ ...x, [k]: v ?? "" }));
  const all = (list) => [{ value: "", label: c("ph.all") }, ...list];
  const distinct = (k) =>
    [...new Set(rows.map((r) => r[k]).filter(Boolean))]
      .sort()
      .map((v) => ({ value: v, label: v }));
  const shown = rows.filter(
    (r) =>
      (!f.from || r.req_date >= f.from) &&
      (!f.to || r.req_date <= f.to) &&
      (!f.type || r.req_type === f.type) &&
      (!f.style || r.style_no === f.style) &&
      (!f.order || r.order_no === f.order) &&
      (!f.status || r.status === f.status) &&
      (!f.no || r.req_no.toLowerCase().includes(f.no.toLowerCase())),
  );

  function refresh() {
    startTransition(async () => {
      const res = await act("issue-reqs");
      if (res?.status !== "success") return setError(messagesOf(res));
      setRows(res.data);
      setError(null);
    });
  }
  function run() {
    const { row, action } = ask;
    startTransition(async () => {
      const res = await act(
        `issue-reqs/${encodeURIComponent(row.req_no)}/${action}`,
        "POST",
      );
      setAsk(null);
      if (res?.status !== "success") return setError(messagesOf(res));
      setRows((list) =>
        list.map((x) =>
          x.req_no === row.req_no
            ? { ...x, status: res.data.status, editable: false }
            : x,
        ),
      );
      setError(null);
      toastManager.add({
        title: c(`done.${action}`, { no: row.req_no }),
        type: "success",
      });
    });
  }
  const columns = [
    { key: "style_no", label: c("col.style") },
    { key: "order_no", label: c("col.order"), nowrap: true },
    {
      key: "req_no",
      label: c("col.req_no"),
      nowrap: true,
      render: (r) => (
        <span className="flex flex-col items-start gap-1">
          <Link
            href={`${BASE}?tab=entry&ref=${encodeURIComponent(r.req_no)}`}
            className="font-medium text-brand hover:underline"
          >
            {r.req_no}
          </Link>
          <StatusChip
            status={TONE[r.status]}
            label={ys(`req_status.${r.status}`)}
          />
        </span>
      ),
    },
    {
      key: "req_date",
      label: c("col.req_date"),
      nowrap: true,
      text: (r) => dayFmt(r.req_date),
    },
    {
      key: "req_type",
      label: c("col.req_type"),
      text: (r) => ys(`req_type.${r.req_type}`),
    },
    {
      key: "req_qty",
      label: c("col.req_qty"),
      num: true,
      text: (r) => qtyFmt.format(r.req_qty),
    },
    {
      key: "issued_qty",
      label: c("col.issue_qty"),
      num: true,
      text: (r) => qtyFmt.format(r.issued_qty),
    },
    {
      key: "balance",
      label: c("col.balance"),
      num: true,
      text: (r) => qtyFmt.format(r.balance),
    },
  ];

  return (
    <div className="flex flex-col gap-4">
      <section className={cn(card, "p-5")}>
        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-6">
          <Field label={c("f.req_date")} className="lg:col-span-2">
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
          <Field label={c("f.req_type")}>
            <PSelect
              value={f.type}
              onValueChange={(v) => setF("type", v)}
              options={all(
                options.req_types.map((t) => ({
                  value: t,
                  label: ys(`req_type.${t}`),
                })),
              )}
              aria-label={c("f.req_type")}
            />
          </Field>
          <Field label={c("f.style")}>
            <PSelect
              value={f.style}
              onValueChange={(v) => setF("style", v)}
              options={all(distinct("style_no"))}
              aria-label={c("f.style")}
            />
          </Field>
          <Field label={c("f.order")}>
            <PSelect
              value={f.order}
              onValueChange={(v) => setF("order", v)}
              options={all(distinct("order_no"))}
              aria-label={c("f.order")}
            />
          </Field>
          <Field label={c("f.status")}>
            <PSelect
              value={f.status}
              onValueChange={(v) => setF("status", v)}
              options={all(
                options.statuses.map((s) => ({
                  value: s,
                  label: ys(`req_status.${s}`),
                })),
              )}
              aria-label={c("f.status")}
            />
          </Field>
          <Field label={c("f.req_no")}>
            <Input
              value={f.no}
              onChange={(e) => setF("no", e.target.value)}
              placeholder={c("ph.search")}
              aria-label={c("f.req_no")}
            />
          </Field>
          <div className="flex items-end justify-end gap-3 text-[13px] text-foreground-muted lg:col-span-5">
            {c("shown", { shown: shown.length, total: rows.length })}
            <Button
              variant="outline"
              size="sm"
              onClick={() => setFilters(blank)}
            >
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
          exportName="general-yarn-issue-indent"
          addHref={canManage ? `${BASE}?tab=entry` : null}
          empty={<NoLines title={c("empty.list")} hint="" />}
          onRefresh={refresh}
          pending={pending}
          actions={(r) =>
            canManage ? (
              <>
                {CLOSABLE.includes(r.status) ? (
                  <Button
                    variant="outline"
                    size="sm"
                    onClick={() => setAsk({ row: r, action: "close" })}
                    disabled={pending}
                    aria-label={`${c("btn.close")} ${r.req_no}`}
                  >
                    <Lock /> {c("btn.close")}
                  </Button>
                ) : null}
                {r.status === "OPEN" && r.issued_qty <= 0 ? (
                  <Button
                    variant="outline"
                    size="sm"
                    className="text-danger"
                    onClick={() => setAsk({ row: r, action: "cancel" })}
                    disabled={pending}
                    aria-label={`${c("btn.cancel")} ${r.req_no}`}
                  >
                    <Ban /> {c("btn.cancel")}
                  </Button>
                ) : null}
              </>
            ) : null
          }
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

const LINE = [
  "composition_id",
  "yarn_count_id",
  "colour",
  "brand",
  "lot_no",
  "req_qty",
  "uom",
];
const HEAD = [
  "req_date",
  "req_type",
  "program_no",
  "store_id",
  "party_id",
  "buyer_id",
  "order_id",
  "remarks",
];
let seq = 0;
const asLine = (l = {}) => ({
  key: `g${++seq}`,
  ...Object.fromEntries(LINE.map((k) => [k, str(l[k])])),
  issued_qty: l.issued_qty ?? 0,
});

function GyEntry({ options, initial, canManage, act }) {
  const c = useGy();
  const ys = useYs();
  const toastManager = useToastManager();
  const [today] = useState(todayIso);
  const yarnStore =
    options.stores.find((s) => /yarn/i.test(s.label))?.value ?? "";
  const asHead = (d) => ({
    ...Object.fromEntries(HEAD.map((k) => [k, str(d?.[k])])),
    req_date: str(d?.req_date) || today,
    store_id: d ? str(d.store_id) : yarnStore,
  });
  const order0 = options.orders.find((o) => o.value === initial?.order_id);
  const [doc, setDoc] = useState(initial);
  const [head, setHead] = useState(() => asHead(initial));
  const [style, setStyle] = useState(str(order0?.style));
  const [closed, setClosed] = useState(initial?.status === "CLOSED");
  const [lines, setLines] = useState(() => (initial?.lines ?? []).map(asLine));
  const [draft, setDraft] = useState(() => asLine({ uom: "kg" }));
  const [errors, setErrors] = useState(null);
  const [pending, startTransition] = useTransition();
  const editable = canManage && (!doc || doc.editable);
  const closable = canManage && doc && CLOSABLE.includes(doc.status);
  const setH = (patch) => setHead((h) => ({ ...h, ...patch }));
  const setD = (patch) => setDraft((d) => ({ ...d, ...patch }));
  const pick = (list, ph) => [{ value: "", label: ph }, ...list];
  const draftKey = draft.composition_id ? yarnKey(draft) : "";
  const { items, brands } = lotPickers(options, head.store_id, draftKey);
  const byBuyer = options.orders.filter(
    (o) => !head.buyer_id || o.buyer_id === head.buyer_id,
  );
  const styles = [...new Set(byBuyer.map((o) => o.style).filter(Boolean))]
    .sort()
    .map((s) => ({ value: s, label: s }));
  const orders = byBuyer.filter((o) => !style || o.style === style);
  const total = lines.reduce((a, l) => a + num(l.req_qty), 0);
  const status = doc?.status ?? "OPEN";

  function show(d) {
    setDoc(d);
    setHead(asHead(d));
    setStyle(str(options.orders.find((o) => o.value === d?.order_id)?.style));
    setClosed(d?.status === "CLOSED");
    setLines((d?.lines ?? []).map(asLine));
    setDraft(asLine({ uom: "kg" }));
    setErrors(null);
    window.history.replaceState(
      null,
      "",
      d ? `?tab=entry&ref=${encodeURIComponent(d.req_no)}` : "?tab=entry",
    );
  }
  function open(no) {
    if (!no) return show(null);
    startTransition(async () => {
      const res = await act(`issue-reqs/${encodeURIComponent(no)}`);
      if (res?.status !== "success") return setErrors(messagesOf(res));
      show(res.data);
    });
  }
  function addLine() {
    const miss = [
      !draft.composition_id && c("line.need_yarn"),
      !(num(draft.req_qty) > 0) && c("line.need_qty"),
    ].filter(Boolean);
    if (miss.length) return setErrors(miss);
    setLines((all) => [...all, draft]);
    setDraft(asLine({ uom: draft.uom, colour: draft.colour }));
    setErrors(null);
  }
  function save() {
    startTransition(async () => {
      let saved = doc;
      if (editable) {
        const body = {
          ...Object.fromEntries(
            HEAD.map((k) => [k, head[k] === "" ? null : head[k]]),
          ),
          lines: lines.map((l) => ({
            ...Object.fromEntries(
              LINE.map((k) => [k, l[k] === "" ? null : l[k]]),
            ),
            req_qty: orNull(l.req_qty),
          })),
        };
        const res = await act(
          doc ? `issue-reqs/${encodeURIComponent(doc.req_no)}` : "issue-reqs",
          doc ? "PUT" : "POST",
          body,
        );
        if (res?.status !== "success") return setErrors(messagesOf(res));
        saved = res.data;
      }
      // Closed is a decision of its own, taken once the lines stand.
      if (closed && saved && CLOSABLE.includes(saved.status)) {
        const res = await act(
          `issue-reqs/${encodeURIComponent(saved.req_no)}/close`,
          "POST",
        );
        if (res?.status !== "success") return setErrors(messagesOf(res));
        saved = res.data;
      }
      show(saved);
      toastManager.add({
        title: c("done.saved", { no: saved.req_no }),
        type: "success",
      });
    });
  }
  const yarnOf = (l) => (l.composition_id ? yarnName(options, l) : "—");

  return (
    <div className="flex flex-col gap-4">
      <section className={cn(card, "p-5")}>
        <div className="flex items-start justify-between gap-3">
          <div>
            <h2 className="text-base font-semibold text-foreground">
              {doc ? doc.req_no : c("tabs.entry")}
            </h2>
            <p className="mt-0.5 text-[13px] text-foreground-muted">
              {c("hint.form")}
            </p>
          </div>
          <StatusChip
            status={TONE[status]}
            label={ys(`req_status.${status}`)}
          />
        </div>
        {doc && !editable && canManage ? (
          <Alert variant="info" className="mt-3">
            {c("hint.locked", {
              no: doc.req_no,
              status: ys(`req_status.${doc.status}`),
            })}
          </Alert>
        ) : null}
        <Field
          label={c("f.existing")}
          hint={c("hint.existing")}
          className="mt-4"
        >
          <PSelect
            value={doc?.req_no ?? ""}
            onValueChange={(v) => open(v ?? "")}
            options={pick(options.reqs, c("ph.new"))}
            disabled={pending}
            aria-label={c("f.existing")}
          />
        </Field>
        <h3 className="mt-5 mb-3 text-sm font-semibold text-foreground">
          {c("sec.requisition")}
        </h3>
        <div className="grid gap-4 md:grid-cols-3">
          <Field label={c("f.req_date")} required>
            <Input
              type="date"
              value={head.req_date}
              onChange={(e) => setH({ req_date: e.target.value })}
              disabled={!editable}
              aria-label={c("f.req_date")}
            />
          </Field>
          <Field label={c("f.req_type")} required>
            <PSelect
              value={head.req_type}
              onValueChange={(v) => setH({ req_type: v ?? "" })}
              options={pick(
                options.req_types.map((t) => ({
                  value: t,
                  label: ys(`req_type.${t}`),
                })),
                c("ph.type"),
              )}
              disabled={!editable}
              aria-label={c("f.req_type")}
            />
          </Field>
          <Field label={c("f.program")} hint={c("hint.program")}>
            <Input
              value={head.program_no}
              onChange={(e) => setH({ program_no: e.target.value })}
              maxLength={40}
              disabled={!editable}
              aria-label={c("f.program")}
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
              options={pick(options.stores, c("ph.select"))}
              disabled={!editable}
              aria-label={c("f.store")}
            />
          </Field>
        </div>
        <h3 className="mt-5 mb-3 text-sm font-semibold text-foreground">
          {c("sec.from")}
        </h3>
        <div className="grid gap-4 md:grid-cols-3">
          <Field label={c("f.party")} hint={c("hint.party")}>
            <PSelect
              value={head.party_id}
              onValueChange={(v) => setH({ party_id: v ?? "" })}
              options={pick(options.suppliers, c("ph.party"))}
              disabled={!editable}
              aria-label={c("f.party")}
            />
          </Field>
        </div>
        <h3 className="mt-5 mb-3 text-sm font-semibold text-foreground">
          {c("sec.booked")}
        </h3>
        <div className="grid gap-4 md:grid-cols-3">
          <Field label={c("f.buyer")}>
            <PSelect
              value={head.buyer_id}
              onValueChange={(v) => (
                setH({ buyer_id: v ?? "", order_id: "" }),
                setStyle("")
              )}
              options={pick(options.buyers, c("ph.buyer"))}
              disabled={!editable}
              aria-label={c("f.buyer")}
            />
          </Field>
          <Field label={c("f.style")}>
            <PSelect
              value={style}
              onValueChange={(v) => (setStyle(v ?? ""), setH({ order_id: "" }))}
              options={pick(styles, c("ph.style"))}
              disabled={!editable}
              aria-label={c("f.style")}
            />
          </Field>
          <Field label={c("f.order")}>
            <PSelect
              value={head.order_id}
              onValueChange={(v) => {
                const o = options.orders.find((x) => x.value === v);
                setH({
                  order_id: v ?? "",
                  buyer_id: o?.buyer_id ?? head.buyer_id,
                });
                if (o?.style) setStyle(o.style);
              }}
              options={pick(orders, c("ph.order"))}
              disabled={!editable}
              aria-label={c("f.order")}
            />
          </Field>
        </div>
        <h3 className="mt-5 mb-3 text-sm font-semibold text-foreground">
          {c("sec.standing")}
        </h3>
        <div className="grid gap-4 md:grid-cols-3">
          <Field label={c("f.status")} required>
            <PSelect
              value={status}
              options={options.statuses.map((s) => ({
                value: s,
                label: ys(`req_status.${s}`),
              }))}
              disabled
              aria-label={c("f.status")}
            />
          </Field>
          <div className="flex items-start gap-2 pt-7 md:col-span-2">
            <Checkbox
              checked={closed}
              onCheckedChange={(v) => setClosed(Boolean(v))}
              disabled={!closable || doc?.status === "CLOSED"}
              aria-label={c("f.closed")}
            />
            <span>
              <span className="block text-[13px] font-semibold text-foreground">
                {c("f.closed")}
              </span>
              <span className="text-xs text-foreground-muted">
                {c("hint.closed")}
              </span>
            </span>
          </div>
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
              {c("sec.yarn")}
            </h3>
            <p className="mt-0.5 max-w-3xl text-[13px] text-foreground-muted">
              {c("hint.lines")}
            </p>
          </div>
          {editable ? (
            <div className="flex gap-2">
              <Button variant="outline" size="sm" onClick={addLine}>
                <Plus /> {c("btn.add_yarn")}
              </Button>
              <Button
                variant="outline"
                size="sm"
                onClick={() => setDraft(asLine({ uom: "kg" }))}
              >
                {c("btn.cancel_line")}
              </Button>
            </div>
          ) : null}
        </div>
        {editable ? (
          <div className="mt-4 grid gap-4 md:grid-cols-3">
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
                value={draft.colour}
                onValueChange={(v) => setD({ colour: v ?? "" })}
                options={pick(
                  options.colours.map((g) => ({ value: g, label: g })),
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
            <Field label={c("f.req_qty")}>
              <Input
                type="number"
                min="0"
                step="any"
                value={draft.req_qty}
                onChange={(e) => setD({ req_qty: e.target.value })}
                aria-label={c("f.req_qty")}
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
          </div>
        ) : null}
        <div className="mt-5 overflow-x-auto rounded-lg border border-border">
          {lines.length === 0 ? (
            <NoLines title={c("empty.lines")} hint={c("empty.lines_hint")} />
          ) : (
            <table className="w-full min-w-[900px] text-sm">
              <thead className="bg-brand text-brand-foreground">
                <tr>
                  {[
                    ["yarn"],
                    ["colour_group"],
                    ["brand"],
                    ["lot"],
                    ["qty", true],
                    ["uom"],
                    ["issued", true],
                    ["bal", true],
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
                    <td className="px-2 py-2 font-medium">{yarnOf(l)}</td>
                    <td className="px-2 py-2">{l.colour || "—"}</td>
                    <td className="px-2 py-2">{l.brand || "—"}</td>
                    <td className="px-2 py-2">{l.lot_no || "—"}</td>
                    <td className="px-2 py-2 text-right tabular-nums">
                      {qtyFmt.format(num(l.req_qty))}
                    </td>
                    <td className="px-2 py-2">{l.uom || "—"}</td>
                    <td className="px-2 py-2 text-right tabular-nums">
                      {qtyFmt.format(num(l.issued_qty))}
                    </td>
                    <td className="px-2 py-2 text-right tabular-nums">
                      {qtyFmt.format(
                        Math.max(0, num(l.req_qty) - num(l.issued_qty)),
                      )}
                    </td>
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
                    {qtyFmt.format(total)}
                  </td>
                  <td colSpan={4} />
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
          {c("hint.foot")}
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
          {editable || (closable && closed) ? (
            <Button onClick={save} loading={pending}>
              <Save /> {c("btn.save")}
            </Button>
          ) : null}
        </div>
      </section>
    </div>
  );
}

export { GyList, GyEntry };
