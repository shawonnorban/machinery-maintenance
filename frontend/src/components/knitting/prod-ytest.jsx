"use client";

import { useState, useTransition } from "react";
import Link from "next/link";
import {
  ArrowLeft,
  Eraser,
  Plus,
  RotateCcw,
  Save,
  Search,
  Trash2,
} from "lucide-react";
import { cn } from "@/lib/utils";
import { Input } from "@/components/ui/input";
import { Textarea } from "@/components/ui/textarea";
import { Checkbox } from "@/components/ui/checkbox";
import { Button, buttonVariants } from "@/components/ui/button";
import { Alert } from "@/components/ui/alert";
import { useToastManager } from "@/components/ui/toast";
import {
  PSelect,
  str,
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
} from "./yarn-common";
import { ScTable } from "./prod-sc";
import {
  useYt,
  yarnKey,
  yarnName,
  lotPickers,
  RESULT_TONE,
  ORDER_TONE,
} from "./prod-yt";

/*
 * Knit Production → Production (AnnotechERP 15/04) → Yarn Test Result
 * Register, Production Test Clearance and Approval Without Testing: the
 * Yarn Test form (the Requisition List beside it drops a test order's lots
 * into the grid; each lot gets its Result), the register list both test
 * screens and the approvals read (Searching Data above; the Basis column
 * where both kinds of row show), and the release of lots the store holds
 * without a lab reading. Nothing reloads.
 */

const LINE = [
  "composition_id",
  "yarn_count_id",
  "colour_group",
  "brand",
  "supplier_id",
  "lc_no",
  "lot_no",
  "colour",
  "result",
  "note",
];
let seq = 0;
const asLine = (l = {}) => ({
  key: `r${++seq}`,
  ...Object.fromEntries(LINE.map((k) => [k, str(l[k])])),
});
const PER = 5;

function YtTest({ base, options, initial, canManage, act }) {
  const c = useYt();
  const ys = useYs();
  const toastManager = useToastManager();
  const [today] = useState(todayIso);
  const asHead = (d) => ({
    order_no: str(d?.order_no),
    test_date: str(d?.test_date) || today,
    test_by: str(d?.test_by),
    remarks: str(d?.remarks),
  });
  const [doc, setDoc] = useState(initial);
  const [head, setHead] = useState(() => asHead(initial));
  const [lines, setLines] = useState(() => (initial?.lines ?? []).map(asLine));
  const [draft, setDraft] = useState(() => asLine({ result: "PENDING" }));
  const [reqType, setReqType] = useState(str(initial?.req_type));
  const [listStatus, setListStatus] = useState("");
  const [q, setQ] = useState("");
  const [page, setPage] = useState(0);
  const [errors, setErrors] = useState(null);
  const [pending, startTransition] = useTransition();
  const editable = canManage;
  const setH = (patch) => setHead((h) => ({ ...h, ...patch }));
  const setD = (patch) => setDraft((d) => ({ ...d, ...patch }));
  const setL = (k, patch) =>
    setLines((all) => all.map((l) => (l.key === k ? { ...l, ...patch } : l)));
  const pick = (list, ph) => [{ value: "", label: ph }, ...list];
  const order = options.orders.find((o) => o.value === head.order_no);
  const draftKey = draft.composition_id ? yarnKey(draft) : "";
  const { items, brands } = lotPickers(options, null, draftKey);
  const named = (list, v) => list.find((x) => x.value === v)?.label ?? null;
  const results = options.results.map((r) => ({
    value: r,
    label: c(`result.${r}`),
  }));
  const reqList = options.orders.filter(
    (o) =>
      (!listStatus || o.status === listStatus) &&
      (!q ||
        [o.value, o.yarn_description, o.requested_by]
          .join(" ")
          .toLowerCase()
          .includes(q.toLowerCase())),
  );
  const pages = Math.max(1, Math.ceil(reqList.length / PER));
  const at = Math.min(page, pages - 1);
  const reqNos = options.orders.filter(
    (o) => o.status !== "CANCELLED" && (!reqType || o.req_type === reqType),
  );

  function show(d) {
    setDoc(d);
    setHead(asHead(d));
    setLines((d?.lines ?? []).map(asLine));
    setReqType(str(d?.req_type));
    setDraft(asLine({ result: "PENDING" }));
    setErrors(null);
    window.history.replaceState(
      null,
      "",
      d ? `?tab=entry&ref=${encodeURIComponent(d.test_no)}` : "?tab=entry",
    );
  }
  function openTest(no) {
    if (!no) return show(null);
    startTransition(async () => {
      const res = await act(`yarn-test/tests/${encodeURIComponent(no)}`);
      if (res?.status !== "success") return setErrors(messagesOf(res));
      show(res.data);
    });
  }
  /** A test order picked: its own test if it has one, else its lots, each Pending. */
  function pickOrder(no) {
    if (!no) return setH({ order_no: "" });
    startTransition(async () => {
      const res = await act(`yarn-test/orders/${encodeURIComponent(no)}`);
      if (res?.status !== "success") return setErrors(messagesOf(res));
      const o = res.data;
      if (o.test_no) {
        const t = await act(`yarn-test/tests/${encodeURIComponent(o.test_no)}`);
        if (t?.status === "success") return show(t.data);
      }
      setDoc(null);
      setH({ order_no: o.order_no });
      setReqType(o.req_type);
      setLines(
        o.lines.map((l) =>
          asLine({ ...l, colour: "", result: "PENDING", note: "" }),
        ),
      );
      setErrors(null);
    });
  }
  function addLine() {
    const miss = [
      !draft.lot_no.trim() && c("line.need_lot"),
      !draft.result && c("line.need_result"),
    ].filter(Boolean);
    if (miss.length) return setErrors(miss);
    setLines((all) => [...all, draft]);
    setDraft(asLine({ result: "PENDING", supplier_id: draft.supplier_id }));
    setErrors(null);
  }
  function save() {
    startTransition(async () => {
      const body = {
        order_no: head.order_no || null,
        test_date: head.test_date || null,
        test_by: head.test_by || null,
        remarks: head.remarks || null,
        lines: lines.map((l) =>
          Object.fromEntries(LINE.map((k) => [k, l[k] === "" ? null : l[k]])),
        ),
      };
      const res = await act(
        doc && doc.order_no === head.order_no
          ? `yarn-test/tests/${encodeURIComponent(doc.test_no)}`
          : "yarn-test/tests",
        doc && doc.order_no === head.order_no ? "PUT" : "POST",
        body,
      );
      if (res?.status !== "success") return setErrors(messagesOf(res));
      show(res.data);
      toastManager.add({
        title: c("done.test_saved", {
          no: res.data.test_no,
          order: res.data.order_no,
        }),
        type: "success",
      });
    });
  }

  return (
    <div className="grid items-start gap-4 2xl:grid-cols-[400px_minmax(0,1fr)]">
      <section className={cn(card, "p-5")}>
        <h2 className="text-base font-semibold text-foreground">
          {c("sec.req_list")}
        </h2>
        <Field label={c("f.status")} className="mt-3">
          <PSelect
            value={listStatus}
            onValueChange={(v) => (setListStatus(v ?? ""), setPage(0))}
            options={pick(
              options.statuses.map((s) => ({
                value: s,
                label: c(`status.${s}`),
              })),
              c("ph.any_status"),
            )}
            aria-label={c("f.status")}
          />
        </Field>
        <div className="relative mt-3">
          <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-foreground-muted" />
          <Input
            value={q}
            onChange={(e) => (setQ(e.target.value), setPage(0))}
            placeholder={c("ph.req_search")}
            className="pl-9"
            aria-label={c("f.search_req")}
          />
        </div>
        <div className="mt-3 overflow-hidden rounded-lg border border-border">
          <table className="w-full text-sm">
            <thead className="bg-brand text-brand-foreground">
              <tr>
                {["order_no", "order_date", "req_by", "yarn_description"].map(
                  (k) => (
                    <th key={k} className={th}>
                      {c(`col.${k}`)}
                    </th>
                  ),
                )}
                <th className={cn(th, "text-right")}>{c("col.lots")}</th>
              </tr>
            </thead>
            <tbody>
              {reqList.length === 0 ? (
                <tr>
                  <td
                    colSpan={5}
                    className="px-3 py-8 text-center text-foreground-muted"
                  >
                    {c("hint.req_empty")}
                  </td>
                </tr>
              ) : (
                reqList.slice(at * PER, at * PER + PER).map((o) => (
                  <tr
                    key={o.value}
                    onClick={() => editable && pickOrder(o.value)}
                    className={cn(
                      "cursor-pointer border-t border-border hover:bg-surface-muted",
                      head.order_no === o.value && "bg-brand-subtle",
                    )}
                  >
                    <td className="px-2 py-2 font-medium whitespace-nowrap">
                      {o.value}
                      <div className="mt-0.5">
                        <StatusChip
                          status={ORDER_TONE[o.status]}
                          label={c(`status.${o.status}`)}
                        />
                      </div>
                    </td>
                    <td className="px-2 py-2 whitespace-nowrap">
                      {dayFmt(o.order_date)}
                    </td>
                    <td className="px-2 py-2">{o.requested_by ?? "—"}</td>
                    <td className="px-2 py-2">{o.yarn_description}</td>
                    <td className="px-2 py-2 text-right tabular-nums">
                      {o.lots}
                    </td>
                  </tr>
                ))
              )}
            </tbody>
          </table>
        </div>
        <div className="mt-3 flex items-center justify-between text-[13px] text-foreground-muted">
          {c("showing", {
            from: reqList.length ? at * PER + 1 : 0,
            to: Math.min(reqList.length, at * PER + PER),
            count: reqList.length,
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

      <div className="flex min-w-0 flex-col gap-4">
        <section className={cn(card, "p-5")}>
          <div className="flex items-start justify-between gap-3">
            <div>
              <h2 className="text-base font-semibold text-foreground">
                {doc ? doc.test_no : c("tabs.test")}
              </h2>
              <p className="mt-0.5 text-[13px] text-foreground-muted">
                {c("hint.test_form")}
              </p>
            </div>
            <StatusChip
              status={doc ? "RECEIVED" : "PLACED"}
              label={doc ? c("status.TESTED") : c("new_badge")}
            />
          </div>
          <Field
            label={c("f.existing_test")}
            hint={c("hint.existing")}
            className="mt-4"
          >
            <PSelect
              value={doc?.test_no ?? ""}
              onValueChange={(v) => openTest(v ?? "")}
              options={pick(options.tests, c("ph.new_test"))}
              disabled={pending}
              aria-label={c("f.existing_test")}
            />
          </Field>
          <h3 className="mt-5 mb-3 text-sm font-semibold text-foreground">
            {c("sec.search_data")}
          </h3>
          <div className="grid gap-4 md:grid-cols-2">
            <Field label={c("f.req_type")}>
              <PSelect
                value={reqType}
                onValueChange={(v) => setReqType(v ?? "")}
                options={pick(
                  options.req_types.map((t) => ({
                    value: t,
                    label: ys(`req_type.${t}`),
                  })),
                  c("ph.select"),
                )}
                disabled={!editable}
                aria-label={c("f.req_type")}
              />
            </Field>
            <Field label={c("f.req_no")} required hint={c("hint.req_no")}>
              <PSelect
                value={head.order_no}
                onValueChange={(v) => pickOrder(v ?? "")}
                options={pick(reqNos, c("ph.select"))}
                disabled={!editable || pending}
                aria-label={c("f.req_no")}
              />
            </Field>
            <Field label={c("f.yarn_description")} className="md:col-span-2">
              <Input
                value={order?.yarn_description ?? doc?.yarn_description ?? ""}
                disabled
                aria-label={c("f.yarn_description")}
              />
            </Field>
          </div>
          <h3 className="mt-5 mb-3 text-sm font-semibold text-foreground">
            {c("sec.saving_data")}
          </h3>
          <div className="grid gap-4 md:grid-cols-2">
            <Field label={c("f.test_date")} required>
              <Input
                type="date"
                value={head.test_date}
                onChange={(e) => setH({ test_date: e.target.value })}
                disabled={!editable}
                aria-label={c("f.test_date")}
              />
            </Field>
            <Field label={c("f.test_by")} required>
              <PSelect
                value={head.test_by}
                onValueChange={(v) => setH({ test_by: v ?? "" })}
                options={pick(options.employees, c("ph.select"))}
                disabled={!editable}
                aria-label={c("f.test_by")}
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
                {c("sec.details")}
              </h3>
              <p className="mt-0.5 max-w-3xl text-[13px] text-foreground-muted">
                {c("hint.test_lines")}
              </p>
            </div>
            {editable ? (
              <div className="flex gap-2">
                <Button variant="outline" size="sm" onClick={addLine}>
                  <Plus /> {c("btn.add_lot")}
                </Button>
                <Button
                  variant="outline"
                  size="sm"
                  onClick={() => setDraft(asLine({ result: "PENDING" }))}
                >
                  {c("btn.cancel_line")}
                </Button>
              </div>
            ) : null}
          </div>
          {editable ? (
            <div className="mt-4 grid gap-4 md:grid-cols-3">
              <Field label={c("f.yarn_item")}>
                <PSelect
                  value={draftKey}
                  onValueChange={(v) => {
                    const [composition_id = "", yarn_count_id = ""] = (
                      v ?? ""
                    ).split("|");
                    setD({ composition_id, yarn_count_id, brand: "" });
                  }}
                  options={pick(items, c("ph.yarn"))}
                  aria-label={c("f.yarn_item")}
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
              <Field label={c("f.supplier")}>
                <PSelect
                  value={draft.supplier_id}
                  onValueChange={(v) => setD({ supplier_id: v ?? "" })}
                  options={pick(options.suppliers, c("ph.supplier"))}
                  aria-label={c("f.supplier")}
                />
              </Field>
              {[
                ["lc_no", "f.lc"],
                ["lot_no", "f.lot"],
                ["colour", "f.colour"],
              ].map(([k, label]) => (
                <Field key={k} label={c(label)}>
                  <Input
                    value={draft[k]}
                    onChange={(e) => setD({ [k]: e.target.value })}
                    maxLength={40}
                    aria-label={c(label)}
                  />
                </Field>
              ))}
              <Field label={c("f.result")}>
                <PSelect
                  value={draft.result}
                  onValueChange={(v) => setD({ result: v ?? "" })}
                  options={pick(results, c("ph.choose"))}
                  aria-label={c("f.result")}
                />
              </Field>
              <Field label={c("f.note")}>
                <Input
                  value={draft.note}
                  onChange={(e) => setD({ note: e.target.value })}
                  maxLength={300}
                  aria-label={c("f.note")}
                />
              </Field>
            </div>
          ) : null}
          <div className="mt-5 overflow-x-auto rounded-lg border border-border">
            {lines.length === 0 ? (
              <NoLines title={c("empty.lines")} hint={c("empty.test_lines")} />
            ) : (
              <table className="w-full min-w-[1100px] text-sm">
                <thead className="bg-brand text-brand-foreground">
                  <tr>
                    {[
                      "yarn_item",
                      "colour_group",
                      "brand",
                      "supplier",
                      "lc",
                      "lot",
                      "colour",
                      "result",
                      "note",
                    ].map((k) => (
                      <th key={k} className={th}>
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
                        {l.composition_id ? yarnName(options, l) : "—"}
                      </td>
                      <td className="px-2 py-2">{l.colour_group || "—"}</td>
                      <td className="px-2 py-2">{l.brand || "—"}</td>
                      <td className="px-2 py-2">
                        {named(options.suppliers, l.supplier_id) ?? "—"}
                      </td>
                      <td className="px-2 py-2">{l.lc_no || "—"}</td>
                      <td className="px-2 py-2 font-semibold">{l.lot_no}</td>
                      <td className="w-32 px-2 py-1.5">
                        <Input
                          value={l.colour}
                          onChange={(e) =>
                            setL(l.key, { colour: e.target.value })
                          }
                          maxLength={40}
                          disabled={!editable}
                          className="h-8"
                          aria-label={`${c("f.colour")} ${i + 1}`}
                        />
                      </td>
                      <td className="w-40 px-2 py-1.5">
                        <PSelect
                          value={l.result}
                          onValueChange={(v) =>
                            setL(l.key, { result: v ?? "" })
                          }
                          options={results}
                          disabled={!editable}
                          aria-label={`${c("f.result")} ${i + 1}`}
                        />
                      </td>
                      <td className="min-w-48 px-2 py-1.5">
                        <Input
                          value={l.note}
                          onChange={(e) =>
                            setL(l.key, { note: e.target.value })
                          }
                          maxLength={300}
                          disabled={!editable}
                          className="h-8"
                          aria-label={`${c("f.note")} ${i + 1}`}
                        />
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
            {c("hint.test_foot")}
          </p>
          <div className="flex flex-wrap gap-2">
            <Link
              href={base}
              className={buttonVariants({ variant: "outline" })}
            >
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
                <Save /> {c("btn.save_test")}
              </Button>
            ) : null}
          </div>
        </section>
      </div>
    </div>
  );
}

/** The register: `basis` LAB, WAIVED or null (both, with a Basis column). */
function YtResults({ base, basis, options, initialRows, canManage, act }) {
  const c = useYt();
  const blank = { count: "", brand: "", lot: "", status: "", from: "", to: "" };
  const [f, setFilters] = useState(blank);
  const [rows, setRows] = useState(initialRows);
  const [error, setError] = useState(null);
  const [pending, startTransition] = useTransition();
  const setF = (k, v) => setFilters((x) => ({ ...x, [k]: v ?? "" }));
  const pick = (list, ph) => [{ value: "", label: ph }, ...list];
  const brands = [
    ...new Set(options.lots.map((l) => l.brand).filter(Boolean)),
  ].map((b) => ({ value: b, label: b }));

  function search(next = f) {
    startTransition(async () => {
      const q = new URLSearchParams(
        Object.entries({ ...next, basis: basis ?? "" }).filter(([, v]) => v),
      );
      const res = await act(`yarn-test/results?${q}`);
      if (res?.status !== "success") return setError(messagesOf(res));
      setRows(res.data);
      setError(null);
    });
  }
  const columns = [
    ...(basis
      ? []
      : [
          {
            key: "basis",
            label: c("col.basis"),
            text: (r) => c(`basis.${r.basis}`),
            render: (r) => (
              <StatusChip
                status={r.basis === "LAB" ? "PLACED" : "WAITING_APPROVAL"}
                label={c(`basis.${r.basis}`)}
              />
            ),
          },
        ]),
    ...(basis === "WAIVED"
      ? []
      : [
          {
            key: "test_no",
            label: c("col.test"),
            nowrap: true,
            text: (r) => [r.test_no, r.order_no].filter(Boolean).join(" "),
            render: (r) =>
              r.test_no ? (
                <span>
                  <Link
                    href={`${base}?tab=entry&ref=${encodeURIComponent(r.test_no)}`}
                    className="font-medium text-brand hover:underline"
                  >
                    {r.test_no}
                  </Link>
                  <span className="block text-xs text-foreground-muted">
                    {r.order_no}
                  </span>
                </span>
              ) : (
                "—"
              ),
          },
        ]),
    {
      key: "test_date",
      label: c("col.test_date"),
      nowrap: true,
      text: (r) => dayFmt(r.test_date),
    },
    { key: "yarn", label: c("col.yarn") },
    { key: "brand", label: c("col.brand") },
    { key: "supplier", label: c("col.supplier") },
    ...(basis === "WAIVED" ? [] : [{ key: "lc_no", label: c("col.lc") }]),
    { key: "lot_no", label: c("col.lot"), nowrap: true },
    { key: "colour", label: c("col.colour") },
    {
      key: "result",
      label: c("col.result"),
      text: (r) => c(`result.${r.result}`),
      render: (r) => (
        <StatusChip
          status={RESULT_TONE[r.result]}
          label={c(`result.${r.result}`)}
        />
      ),
    },
    {
      key: "note",
      label: basis === "WAIVED" ? c("col.authority") : c("col.note"),
    },
    { key: "test_by", label: c("col.test_by") },
  ];
  const hint =
    basis === "LAB"
      ? "hint.test_list"
      : basis === "WAIVED"
        ? "hint.waived_list"
        : "hint.register";

  return (
    <div className="flex flex-col gap-4">
      <section className={cn(card, "p-5")}>
        <h2 className="mb-4 text-base font-semibold text-foreground">
          {c("sec.searching")}
        </h2>
        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-6">
          <Field label={c("f.yarn_count")}>
            <PSelect
              value={f.count}
              onValueChange={(v) => setF("count", v)}
              options={pick(options.counts, c("ph.count"))}
              aria-label={c("f.yarn_count")}
            />
          </Field>
          <Field label={c("f.brand")}>
            <PSelect
              value={f.brand}
              onValueChange={(v) => setF("brand", v)}
              options={pick(brands, c("ph.select_brand"))}
              aria-label={c("f.brand")}
            />
          </Field>
          <Field label={c("f.lot")}>
            <Input
              value={f.lot}
              onChange={(e) => setF("lot", e.target.value)}
              aria-label={c("f.lot")}
            />
          </Field>
          <Field label={c("f.status")}>
            <PSelect
              value={f.status}
              onValueChange={(v) => setF("status", v)}
              options={pick(
                options.results.map((r) => ({
                  value: r,
                  label: c(`result.${r}`),
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
                href={`${base}?tab=entry`}
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
        <p className="px-5 pt-4 text-[13px] text-foreground-muted">{c(hint)}</p>
        <ScTable
          rows={rows}
          columns={columns}
          exportName={`yarn-tests-${(basis ?? "all").toLowerCase()}`}
          empty={
            <NoLines
              title={c(basis === "WAIVED" ? "empty.waived" : "empty.results")}
              hint={c("empty.results_hint")}
            />
          }
          onRefresh={() => search()}
          pending={pending}
        />
      </section>
    </div>
  );
}

/** Release lots the store is holding without a lab reading. */
function YtWaiver({ options, canManage, act }) {
  const c = useYt();
  const toastManager = useToastManager();
  const [today] = useState(todayIso);
  const [f, setFilters] = useState({
    test_date: today,
    test_by: "",
    brand: "",
    count: "",
    lot: "",
  });
  const [lots, setLots] = useState(null);
  const [ticked, setTicked] = useState({});
  const [errors, setErrors] = useState(null);
  const [pending, startTransition] = useTransition();
  const setF = (k, v) => setFilters((x) => ({ ...x, [k]: v ?? "" }));
  const pick = (list, ph) => [{ value: "", label: ph }, ...list];
  const brands = [
    ...new Set(options.lots.map((l) => l.brand).filter(Boolean)),
  ].map((b) => ({ value: b, label: b }));
  const picked = Object.keys(ticked);

  function search() {
    startTransition(async () => {
      const q = new URLSearchParams(
        Object.entries({ brand: f.brand, count: f.count, lot: f.lot }).filter(
          ([, v]) => v,
        ),
      );
      const res = await act(`yarn-test/waiver-lots?${q}`);
      if (res?.status !== "success") return setErrors(messagesOf(res));
      setLots(res.data);
      setTicked({});
      setErrors(null);
    });
  }
  function save() {
    startTransition(async () => {
      const res = await act("yarn-test/waivers", "POST", {
        test_date: f.test_date || null,
        test_by: f.test_by || null,
        lots: picked.map((id) => ({ lot_id: id, note: ticked[id] || null })),
      });
      if (res?.status !== "success") return setErrors(messagesOf(res));
      toastManager.add({
        title: c("done.waived", { count: picked.length }),
        type: "success",
      });
      setErrors(null);
      search();
    });
  }
  const tick = (id, on) =>
    setTicked((t) => {
      const next = { ...t };
      if (on) next[id] = next[id] ?? "";
      else delete next[id];
      return next;
    });

  return (
    <div className="flex flex-col gap-4">
      <section className={card}>
        <div className="p-5">
          <div className="flex items-start justify-between gap-3">
            <div>
              <h2 className="text-base font-semibold text-foreground">
                {c("tabs.waiver")}
              </h2>
              <p className="mt-0.5 text-[13px] text-foreground-muted">
                {c("hint.waiver")}
              </p>
            </div>
            <StatusChip
              status="PLACED"
              label={c("lots_badge", { count: picked.length })}
            />
          </div>
          <div className="mt-4 grid gap-4 md:grid-cols-3">
            <Field label={c("f.test_date")}>
              <Input
                type="date"
                value={f.test_date}
                onChange={(e) => setF("test_date", e.target.value)}
                aria-label={c("f.test_date")}
              />
            </Field>
            <Field label={c("f.test_by")} hint={c("hint.authority")}>
              <PSelect
                value={f.test_by}
                onValueChange={(v) => setF("test_by", v)}
                options={pick(options.employees, c("ph.select"))}
                aria-label={c("f.test_by")}
              />
            </Field>
            <Field label={c("f.yarn_brand")}>
              <PSelect
                value={f.brand}
                onValueChange={(v) => setF("brand", v)}
                options={pick(brands, c("ph.select_brand"))}
                aria-label={c("f.yarn_brand")}
              />
            </Field>
            <Field label={c("f.yarn_count")}>
              <PSelect
                value={f.count}
                onValueChange={(v) => setF("count", v)}
                options={pick(options.counts, c("ph.count_short"))}
                aria-label={c("f.yarn_count")}
              />
            </Field>
            <Field label={c("f.lot")}>
              <Input
                value={f.lot}
                onChange={(e) => setF("lot", e.target.value)}
                placeholder={c("ph.lot_all")}
                aria-label={c("f.lot")}
              />
            </Field>
          </div>
          <Button
            variant="outline"
            className="mt-4"
            onClick={search}
            loading={pending}
          >
            <Search /> {c("btn.search")}
          </Button>
        </div>
        <div className="overflow-x-auto border-t border-border">
          {!lots || lots.length === 0 ? (
            <NoLines title={c("empty.lots")} hint={c("empty.lots_hint")} />
          ) : (
            <table className="w-full min-w-[1100px] text-sm">
              <thead className="bg-brand text-brand-foreground">
                <tr>
                  {[
                    "release",
                    "yarn",
                    "brand",
                    "lot",
                    "supplier",
                    "store",
                    "balance",
                    "answered",
                    "note",
                  ].map((k) => (
                    <th
                      key={k}
                      className={cn(th, k === "balance" && "text-right")}
                    >
                      {c(`col.${k}`)}
                    </th>
                  ))}
                </tr>
              </thead>
              <tbody>
                {lots.map((l) => {
                  const on = l.lot_id in ticked;
                  return (
                    <tr
                      key={l.lot_id}
                      className={cn(
                        "border-t border-border",
                        on && "bg-brand-subtle",
                      )}
                    >
                      <td className="px-3 py-2">
                        <Checkbox
                          checked={on}
                          onCheckedChange={(v) => tick(l.lot_id, Boolean(v))}
                          disabled={!canManage}
                          aria-label={`${c("col.release")} ${l.lot_no}`}
                        />
                      </td>
                      <td className="px-2 py-2 font-medium">{l.yarn}</td>
                      <td className="px-2 py-2">{l.brand ?? "—"}</td>
                      <td className="px-2 py-2 font-semibold">{l.lot_no}</td>
                      <td className="px-2 py-2">{l.supplier ?? "—"}</td>
                      <td className="px-2 py-2">{l.store ?? "—"}</td>
                      <td className="px-2 py-2 text-right tabular-nums">
                        {qtyFmt.format(l.balance)}
                      </td>
                      <td className="px-2 py-2">
                        {l.answered ? (
                          <StatusChip
                            status={RESULT_TONE[l.answered.result]}
                            label={c("answered", {
                              result: c(`result.${l.answered.result}`),
                              basis: c(`basis.${l.answered.basis}`),
                              date: dayFmt(l.answered.test_date),
                            })}
                          />
                        ) : (
                          "—"
                        )}
                      </td>
                      <td className="min-w-56 px-2 py-1.5">
                        <Input
                          value={ticked[l.lot_id] ?? ""}
                          onChange={(e) =>
                            setTicked((t) => ({
                              ...t,
                              [l.lot_id]: e.target.value,
                            }))
                          }
                          maxLength={300}
                          disabled={!canManage}
                          className="h-8"
                          aria-label={`${c("col.note")} ${l.lot_no}`}
                        />
                      </td>
                    </tr>
                  );
                })}
              </tbody>
            </table>
          )}
        </div>
        {errors ? (
          <div className="px-5 pt-4">
            <Alert variant="danger">{errors.join(" · ")}</Alert>
          </div>
        ) : null}
        <div className="flex flex-wrap items-center justify-between gap-3 border-t border-border p-5">
          <p className="max-w-3xl text-[13px] text-foreground-muted">
            {c("hint.waiver_foot")}
          </p>
          {canManage ? (
            <Button
              onClick={save}
              loading={pending}
              disabled={picked.length === 0}
            >
              <Save /> {c("btn.save")}
            </Button>
          ) : null}
        </div>
      </section>
    </div>
  );
}

export { YtTest, YtResults, YtWaiver };
