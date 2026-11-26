"use client";

import { useState, useTransition } from "react";
import Link from "next/link";
import {
  ArrowLeft,
  Ban,
  CheckCheck,
  Eye,
  Plus,
  RotateCcw,
  Save,
  Send,
  Trash2,
  XCircle,
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
  QtyInput,
  orNull,
} from "./yarn-common";
import { KitList, lotOptions } from "./ystore-kit";

/*
 * Knit Production → Yarn Store (AnnotechERP 15/03): the challans that move
 * yarn out and back — Yarn Issue Return against Indent (unused yarn off the
 * floor; Issue, Accept, Reject, Cancel on the register), Party-Wise Yarn
 * Issue Challan (yarn out to a sub-contract party; Issue, Accept, Reject,
 * Cancel) and the Supplier Yarn Replacement Register (yarn back to a
 * spinner; Submit, Accept, Cancel). Each is a register and an entry desk;
 * only a draft is corrected. Nothing reloads.
 */

const TONE = {
  DRAFT: "DRAFT",
  ISSUED: "PLACED",
  SUBMITTED: "PLACED",
  ACCEPTED: "RECEIVED",
  REJECTED: "REJECTED",
  CANCELLED: "CANCELLED",
};
const yarnStore = (stores) =>
  (stores.find((x) => /yarn/i.test(x.label)) ?? stores[0])?.value ?? "";

/** A register of one kind of challan, with its per-status buttons. */
function useRegister(path, initialRows, act, doneKey) {
  const s = useYs();
  const toastManager = useToastManager();
  const [rows, setRows] = useState(initialRows);
  const [error, setError] = useState(null);
  const [pending, startTransition] = useTransition();
  const refresh = () =>
    startTransition(async () => {
      const res = await act(path);
      if (res?.status === "success") {
        setRows(res.data);
        setError(null);
      } else setError(messagesOf(res));
    });
  const run = (no, action) =>
    startTransition(async () => {
      const res = await act(
        `${path}/${encodeURIComponent(no)}/${action}`,
        "POST",
      );
      if (res?.status !== "success") return setError(messagesOf(res));
      toastManager.add({
        title: s(`${doneKey}.${action}`, { no }),
        type: "success",
      });
      const again = await act(path);
      if (again?.status === "success") setRows(again.data);
      setError(null);
    });
  return { rows, error, pending, refresh, run };
}

function ActionButtons({ no, status, steps, run, pending, canManage, open }) {
  const s = useYs();
  const y = useYarn();
  const icons = {
    issue: Send,
    submit: Send,
    accept: CheckCheck,
    reject: XCircle,
    cancel: Ban,
  };
  return (
    <>
      <Link
        href={open}
        className={buttonVariants({ variant: "outline", size: "sm" })}
      >
        <Eye /> {y("open")}
      </Link>
      {canManage
        ? steps
            .filter(([, from]) => from.includes(status))
            .map(([action]) => {
              const Icon = icons[action];
              return (
                <Button
                  key={action}
                  variant="outline"
                  size="sm"
                  className={cn(
                    ["reject", "cancel"].includes(action) && "text-danger",
                  )}
                  onClick={() => run(no, action)}
                  disabled={pending}
                >
                  <Icon /> {s(`doc_btn.${action}`)}
                </Button>
              );
            })
        : null}
    </>
  );
}

const statusOpts = (s, group, list) =>
  list.map((v) => ({ value: v, label: s(`${group}.${v}`) }));
const chip = (s, group, status) => (
  <StatusChip status={TONE[status]} label={s(`${group}.${status}`)} />
);

/* ---------------- Yarn Issue Return against Indent ---------------- */

const RET = "/knit-production/issue-return-against-requisition";
const RET_STEPS = [
  ["issue", ["DRAFT"]],
  ["accept", ["ISSUED"]],
  ["reject", ["ISSUED"]],
  ["cancel", ["DRAFT", "ISSUED"]],
];

/* `base` and `steps`: Yarn Return to Yarn Store (15/04) shows the same challans at its own route, without the store's Accept and Reject. */
function IssueReturnList({
  options,
  initialRows,
  canManage,
  act,
  base = RET,
  steps = RET_STEPS,
}) {
  const s = useYs();
  const r = useRegister("issue-returns", initialRows, act, "ret_done");
  const sum = (k) => qtyFmt.format(r.rows.reduce((t, x) => t + x[k], 0));
  return (
    <KitList
      rows={r.rows}
      dateKey="return_date"
      statusOptions={statusOpts(s, "ret_status", options.return_statuses)}
      chips={[
        [s("irr.chip.count"), r.rows.length],
        [s("irr.chip.ret"), sum("ret")],
        [s("irr.chip.recv"), sum("recv")],
      ]}
      columns={[
        { key: "return_no", label: s("irr.lc.no"), nowrap: true },
        {
          key: "return_date",
          label: s("irr.lc.date"),
          nowrap: true,
          render: (x) => dayFmt(x.return_date),
        },
        {
          key: "req_type",
          label: s("irr.lc.type"),
          text: (x) => s(`req_type.${x.req_type}`),
          render: (x) => s(`req_type.${x.req_type}`),
        },
        { key: "company", label: s("irr.lc.company") },
        { key: "store", label: s("irr.lc.store") },
        { key: "return_by", label: s("irr.lc.return_by") },
        {
          key: "ret",
          label: s("irr.lc.ret"),
          num: true,
          render: (x) => qtyFmt.format(x.ret),
        },
        {
          key: "recv",
          label: s("irr.lc.recv"),
          num: true,
          render: (x) => qtyFmt.format(x.recv),
        },
        {
          key: "status",
          label: s("kit.status"),
          text: (x) => s(`ret_status.${x.status}`),
          render: (x) => chip(s, "ret_status", x.status),
        },
      ]}
      actions={(x) => (
        <ActionButtons
          no={x.return_no}
          status={x.status}
          steps={steps}
          run={r.run}
          pending={r.pending}
          canManage={canManage}
          open={`${base}?tab=entry&ref=${encodeURIComponent(x.return_no)}`}
        />
      )}
      addHref={canManage ? `${base}?tab=entry` : null}
      empty={s("irr.empty")}
      exportName="yarn-return-to-store"
      error={r.error}
      onRefresh={r.refresh}
      pending={r.pending}
    />
  );
}

const RET_HEAD = [
  "return_date",
  "req_type",
  "store_id",
  "req_no",
  "return_by",
  "remarks",
];
const retLine = (l = {}) => ({
  key: key(),
  issue_line_id: str(l.issue_line_id),
  composition_id: str(l.composition_id),
  yarn_count_id: str(l.yarn_count_id),
  colour: str(l.colour),
  brand: str(l.brand),
  lot_no: str(l.lot_no),
  uom: str(l.uom || "kg"),
  ret_qty: str(l.ret_qty),
  recv_qty: str(l.recv_qty),
  remarks: str(l.remarks),
});

function IssueReturnEntry({ options, initial, canManage, act, base = RET }) {
  const y = useYarn();
  const s = useYs();
  const toastManager = useToastManager();
  const [today] = useState(todayIso);
  const blankHead = () => ({
    ...Object.fromEntries(RET_HEAD.map((k) => [k, ""])),
    return_date: today,
    store_id: yarnStore(options.stores),
  });
  const asHead = (d) => Object.fromEntries(RET_HEAD.map((k) => [k, str(d[k])]));
  const [doc, setDoc] = useState(initial);
  const [head, setHead] = useState(() =>
    initial ? asHead(initial) : blankHead(),
  );
  const [issueLines, setIssueLines] = useState(initial?.issue_lines ?? []);
  const [lines, setLines] = useState(() => (initial?.lines ?? []).map(retLine));
  const [errors, setErrors] = useState(null);
  const [pending, startTransition] = useTransition();
  const editable = canManage && (!doc || doc.editable);
  const setH = (patch) => setHead((h) => ({ ...h, ...patch }));
  const setLine = (k, patch) =>
    setLines((all) => all.map((l) => (l.key === k ? { ...l, ...patch } : l)));
  const pick = (list, ph = s("select")) => [{ value: "", label: ph }, ...list];
  const ilOf = (id) => issueLines.find((x) => x.issue_line_id === id);

  function take(data) {
    setDoc(data);
    setHead(asHead(data));
    setIssueLines(data.issue_lines);
    setLines(data.lines.map(retLine));
    setErrors(null);
    window.history.replaceState(
      null,
      "",
      `?tab=entry&ref=${encodeURIComponent(data.return_no)}`,
    );
  }
  function clear() {
    setDoc(null);
    setHead(blankHead());
    setIssueLines([]);
    setLines([]);
    setErrors(null);
    window.history.replaceState(null, "", "?tab=entry");
  }
  function load(no) {
    if (!no) return clear();
    startTransition(async () => {
      const res = await act(`issue-returns/${encodeURIComponent(no)}`);
      if (res?.status === "success") take(res.data);
      else setErrors(messagesOf(res));
    });
  }
  function against(no) {
    setH({ req_no: no });
    if (!no) return setIssueLines([]);
    startTransition(async () => {
      const res = await act(`issue-returns/source/${encodeURIComponent(no)}`);
      if (res?.status !== "success") return setErrors(messagesOf(res));
      setH({
        req_no: no,
        req_type: res.data.req_type,
        store_id: res.data.store_id,
      });
      setIssueLines(res.data.issue_lines);
      // One line per issue line still out on the floor.
      setLines(
        res.data.issue_lines
          .filter((x) => x.out > 0)
          .map((x) => retLine({ ...x, ret_qty: x.out, recv_qty: x.out })),
      );
      setErrors(null);
    });
  }
  function pickIssueLine(k, id) {
    const il = ilOf(id);
    setLine(
      k,
      il
        ? {
            issue_line_id: id,
            composition_id: str(il.composition_id),
            yarn_count_id: str(il.yarn_count_id),
            colour: str(il.colour),
            brand: str(il.brand),
            lot_no: str(il.lot_no),
          }
        : { issue_line_id: "" },
    );
  }
  function save() {
    startTransition(async () => {
      const body = {
        ...Object.fromEntries(
          RET_HEAD.map((k) => [k, head[k] === "" ? null : head[k]]),
        ),
        lines: lines.map((l) => ({
          issue_line_id: l.issue_line_id || null,
          composition_id: l.composition_id || null,
          yarn_count_id: l.yarn_count_id || null,
          colour: l.colour || null,
          brand: l.brand || null,
          lot_no: l.lot_no || null,
          uom: l.uom || null,
          ret_qty: orNull(l.ret_qty),
          recv_qty: orNull(l.recv_qty),
          remarks: l.remarks || null,
        })),
      };
      const res = await act(
        doc
          ? `issue-returns/${encodeURIComponent(doc.return_no)}`
          : "issue-returns",
        doc ? "PUT" : "POST",
        body,
      );
      if (res?.status !== "success") return setErrors(messagesOf(res));
      take(res.data);
      toastManager.add({
        title: s("irr.saved", { no: res.data.return_no }),
        type: "success",
      });
    });
  }

  return (
    <div className="flex min-w-0 flex-col gap-4">
      <section className={cn(card, "p-5")}>
        <Head
          title={s("tabs.irr_entry")}
          hint={s("irr.entry_hint")}
          doc={doc}
          no={doc?.return_no}
          group="ret_status"
        />
        <Field
          label={s("irr.existing")}
          hint={s("ob.existing_hint")}
          className="mt-4"
        >
          <PSelect
            value={doc?.return_no ?? ""}
            onValueChange={(v) => load(v ?? "")}
            options={[
              { value: "", label: s("irr.existing_new") },
              ...options.returns,
            ]}
            aria-label={s("irr.existing")}
          />
        </Field>
        <div className="mt-4 grid gap-4 md:grid-cols-3">
          <Field label={s("irr.date")} required>
            <Input
              type="date"
              value={head.return_date}
              onChange={(e) => setH({ return_date: e.target.value })}
              disabled={!editable}
              aria-label={s("irr.date")}
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
          <Field label={s("irr.store")} required>
            <PSelect
              value={head.store_id}
              onValueChange={(v) => setH({ store_id: v ?? "" })}
              options={pick(options.stores)}
              disabled={!editable}
              aria-label={s("irr.store")}
            />
          </Field>
          <Field label={s("irr.against")}>
            <PSelect
              value={head.req_no}
              onValueChange={(v) => against(v ?? "")}
              options={pick(
                options.reqs.filter(
                  (r) =>
                    !head.req_type ||
                    r.req_type === head.req_type ||
                    r.value === head.req_no,
                ),
                "-- Select Requisition --",
              )}
              disabled={!editable}
              aria-label={s("irr.against")}
            />
          </Field>
          <Field label={s("irr.return_by")} required>
            <Input
              value={head.return_by}
              onChange={(e) => setH({ return_by: e.target.value })}
              maxLength={120}
              disabled={!editable}
              aria-label={s("irr.return_by")}
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
        <LinesHead
          title={s("irr.lines")}
          hint={s("irr.lines_hint")}
          onAdd={editable ? () => setLines((all) => [...all, retLine()]) : null}
        />
        {lines.length === 0 ? (
          <NoLines title={s("mrr.no_lines")} hint={s("ret.no_lines_hint")} />
        ) : (
          <div className="overflow-x-auto">
            <table className="w-full min-w-[1800px] text-sm">
              <thead className="bg-brand text-brand-foreground">
                <tr>
                  <th className={th}>{s("col.issue_line")}</th>
                  {["composition", "count", "colour", "brand", "lot"].map(
                    (k) => (
                      <th key={k} className={th}>
                        {y(`col.${k}`)}
                      </th>
                    ),
                  )}
                  <th className={cn(th, "text-right")}>{s("col.out")}</th>
                  <th className={cn(th, "text-right")}>{s("col.ret_qty")}</th>
                  <th className={cn(th, "text-right")}>{s("col.recv_qty")}</th>
                  <th className={th}>{y("col.remarks")}</th>
                  <th className={th}>{y("col.remove")}</th>
                </tr>
              </thead>
              <tbody>
                {lines.map((l, i) => {
                  const il = ilOf(l.issue_line_id);
                  const fixed = !editable || Boolean(il);
                  const n = i + 1;
                  const sel = (k, list, w = "w-36") => (
                    <td className="px-2 py-1.5">
                      <div className={w}>
                        <PSelect
                          value={l[k]}
                          onValueChange={(v) =>
                            setLine(l.key, { [k]: v ?? "" })
                          }
                          options={[{ value: "", label: "—" }, ...list]}
                          disabled={fixed}
                          aria-label={`${y(`col.${k === "composition_id" ? "composition" : k === "yarn_count_id" ? "count" : "colour"}`)} ${n}`}
                        />
                      </div>
                    </td>
                  );
                  return (
                    <tr key={l.key} className="border-t border-border">
                      <td className="px-2 py-1.5">
                        <div className="w-80">
                          <PSelect
                            value={l.issue_line_id}
                            onValueChange={(v) => pickIssueLine(l.key, v ?? "")}
                            options={[
                              { value: "", label: "-- Select Issue Line --" },
                              ...issueLines.map((x) => ({
                                value: x.issue_line_id,
                                label: x.label,
                              })),
                            ]}
                            disabled={!editable}
                            aria-label={`${s("col.issue_line")} ${n}`}
                          />
                        </div>
                      </td>
                      {sel("composition_id", options.compositions, "w-44")}
                      {sel("yarn_count_id", options.counts, "w-28")}
                      {sel(
                        "colour",
                        options.colours.map((c) => ({ value: c, label: c })),
                      )}
                      <td className="px-2 py-1.5">
                        <Input
                          value={l.brand}
                          onChange={(e) =>
                            setLine(l.key, { brand: e.target.value })
                          }
                          maxLength={80}
                          className="h-8 w-28"
                          disabled={fixed}
                          aria-label={`${y("col.brand")} ${n}`}
                        />
                      </td>
                      <td className="px-2 py-1.5">
                        <Input
                          value={l.lot_no}
                          onChange={(e) =>
                            setLine(l.key, { lot_no: e.target.value })
                          }
                          maxLength={40}
                          className="h-8 w-24"
                          disabled={fixed}
                          aria-label={`${y("col.lot")} ${n}`}
                        />
                      </td>
                      <td className="px-2 py-1.5 text-right tabular-nums">
                        {il ? qtyFmt.format(il.out) : "—"}
                      </td>
                      <td className="px-2 py-1.5">
                        <div className="flex justify-end">
                          <QtyInput
                            value={l.ret_qty}
                            onChange={(v) => setLine(l.key, { ret_qty: v })}
                            disabled={!editable}
                            label={`${s("col.ret_qty")} ${n}`}
                          />
                        </div>
                      </td>
                      <td className="px-2 py-1.5">
                        <div className="flex justify-end">
                          <QtyInput
                            value={l.recv_qty}
                            onChange={(v) => setLine(l.key, { recv_qty: v })}
                            disabled={!editable}
                            label={`${s("col.recv_qty")} ${n}`}
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
                          aria-label={`${y("col.remarks")} ${n}`}
                        />
                      </td>
                      <RemoveCell
                        n={n}
                        disabled={!editable}
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
                  <td colSpan={7} className="px-2 py-2">
                    {s("total_row")}
                  </td>
                  <td className="px-2 py-2 text-right tabular-nums">
                    {qtyFmt.format(
                      lines.reduce((t, l) => t + num(l.ret_qty), 0),
                    )}
                  </td>
                  <td className="px-2 py-2 text-right tabular-nums">
                    {qtyFmt.format(
                      lines.reduce(
                        (t, l) => t + num(l.recv_qty || l.ret_qty),
                        0,
                      ),
                    )}
                  </td>
                  <td colSpan={2} />
                </tr>
              </tfoot>
            </table>
          </div>
        )}
      </section>
      <Foot
        errors={errors}
        foot={s("irr.foot")}
        back={base}
        clear={clear}
        pending={pending}
        save={editable ? save : null}
        saveLabel={s("ret.save")}
      />
    </div>
  );
}

/* ---------------- shared desk pieces ---------------- */

function Head({ title, hint, doc, no, group }) {
  const y = useYarn();
  const s = useYs();
  return (
    <div className="flex items-start justify-between gap-3">
      <div>
        <h2 className="text-base font-semibold text-foreground">{title}</h2>
        <p className="mt-0.5 text-[13px] text-foreground-muted">{hint}</p>
      </div>
      {doc ? (
        <StatusChip
          status={TONE[doc.status]}
          label={
            doc.editable
              ? y("editing", { no })
              : y("read_only", { status: s(`${group}.${doc.status}`) })
          }
        />
      ) : null}
    </div>
  );
}

function LinesHead({ title, hint, onAdd, disabled }) {
  const s = useYs();
  return (
    <div className="flex items-start justify-between gap-3 px-5 py-4">
      <div>
        <h2 className="text-base font-semibold text-foreground">{title}</h2>
        <p className="mt-0.5 text-[13px] text-foreground-muted">{hint}</p>
      </div>
      {onAdd ? (
        <Button variant="outline" size="sm" onClick={onAdd} disabled={disabled}>
          <Plus /> {s("mrr.add")}
        </Button>
      ) : null}
    </div>
  );
}

function RemoveCell({ n, disabled, onRemove }) {
  const y = useYarn();
  return (
    <td className="px-1 py-1.5">
      <Button
        variant="outline"
        size="icon"
        className="size-8 text-danger"
        onClick={onRemove}
        disabled={disabled}
        aria-label={`${y("remove_line")} ${n}`}
      >
        <Trash2 />
      </Button>
    </td>
  );
}

function Foot({ errors, foot, back, clear, pending, save, saveLabel }) {
  const y = useYarn();
  return (
    <>
      {errors ? <Alert variant="danger">{errors.join(" · ")}</Alert> : null}
      <section className={cn(card, "flex flex-col gap-3 p-5")}>
        <p className="text-[13px] text-foreground-muted">{foot}</p>
        <div className="flex flex-wrap gap-2">
          <Link href={back} className={buttonVariants({ variant: "outline" })}>
            <ArrowLeft /> {y("back")}
          </Link>
          <Button variant="outline" onClick={clear} disabled={pending}>
            <RotateCcw /> {y("clear")}
          </Button>
          {save ? (
            <Button onClick={save} loading={pending}>
              <Save /> {saveLabel}
            </Button>
          ) : null}
        </div>
      </section>
    </>
  );
}

/**
 * Lines off a store's lots (party challan, replacement): the lot, what it
 * holds, the quantity, and the extra boxes `extra` names.
 */
function LotLines({ lines, setLines, lots, extra, editable }) {
  const y = useYarn();
  const s = useYs();
  const setLine = (k, patch) =>
    setLines((all) => all.map((l) => (l.key === k ? { ...l, ...patch } : l)));
  // A lot on the document stays on offer however much it now holds.
  const offered = [
    ...lots,
    ...lines
      .filter((l) => l.lot_id && !lots.some((x) => x.lot_id === l.lot_id))
      .map((l) => ({
        lot_id: l.lot_id,
        label: l.lot,
        balance: l.balance ?? 0,
      })),
  ];
  const balanceOf = (id) => offered.find((x) => x.lot_id === id)?.balance;
  return lines.length === 0 ? (
    <NoLines title={s("mrr.no_lines")} hint={s("ret.no_lines_hint")} />
  ) : (
    <div className="overflow-x-auto">
      <table className="w-full min-w-[1100px] text-sm">
        <thead className="bg-brand text-brand-foreground">
          <tr>
            <th className={th}>{s("col.stock_line")}</th>
            <th className={cn(th, "text-right")}>{s("col.balance")}</th>
            {extra
              .filter((e) => e.before)
              .map((e) => (
                <th key={e.key} className={cn(th, "text-right")}>
                  {e.label}
                </th>
              ))}
            <th className={cn(th, "text-right")}>{s("col.qty")}</th>
            {extra
              .filter((e) => !e.before)
              .map((e) => (
                <th key={e.key} className={cn(th, "text-right")}>
                  {e.label}
                </th>
              ))}
            <th className={th}>{y("col.remarks")}</th>
            <th className={th}>{y("col.remove")}</th>
          </tr>
        </thead>
        <tbody>
          {lines.map((l, i) => {
            const n = i + 1;
            const box = (e) => (
              <td key={e.key} className="px-2 py-1.5">
                <div className="flex justify-end">
                  <QtyInput
                    value={l[e.key]}
                    onChange={(v) => setLine(l.key, { [e.key]: v })}
                    disabled={!editable}
                    w="w-24"
                    label={`${e.label} ${n}`}
                  />
                </div>
              </td>
            );
            const b = balanceOf(l.lot_id);
            return (
              <tr key={l.key} className="border-t border-border">
                <td className="px-2 py-1.5">
                  <div className="w-[28rem]">
                    <PSelect
                      value={l.lot_id}
                      onValueChange={(v) => setLine(l.key, { lot_id: v ?? "" })}
                      options={[
                        { value: "", label: s("adj.lot_ph") },
                        ...lotOptions(offered, qtyFmt),
                      ]}
                      disabled={!editable}
                      aria-label={`${s("col.stock_line")} ${n}`}
                    />
                  </div>
                </td>
                <td className="px-2 py-1.5 text-right tabular-nums">
                  {b === undefined ? "—" : qtyFmt.format(b)}
                </td>
                {extra.filter((e) => e.before).map(box)}
                <td className="px-2 py-1.5">
                  <div className="flex justify-end">
                    <QtyInput
                      value={l.qty}
                      onChange={(v) => setLine(l.key, { qty: v })}
                      disabled={!editable}
                      label={`${s("col.qty")} ${n}`}
                    />
                  </div>
                </td>
                {extra.filter((e) => !e.before).map(box)}
                <td className="px-2 py-1.5">
                  <Input
                    value={l.remarks}
                    onChange={(e) =>
                      setLine(l.key, { remarks: e.target.value })
                    }
                    maxLength={300}
                    className="h-8 w-48"
                    disabled={!editable}
                    aria-label={`${y("col.remarks")} ${n}`}
                  />
                </td>
                <RemoveCell
                  n={n}
                  disabled={!editable}
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
            <td
              colSpan={2 + extra.filter((e) => e.before).length}
              className="px-2 py-2"
            >
              {s("total_row")}
            </td>
            <td className="px-2 py-2 text-right tabular-nums">
              {qtyFmt.format(lines.reduce((t, l) => t + num(l.qty), 0))}
            </td>
            <td colSpan={2 + extra.filter((e) => !e.before).length} />
          </tr>
        </tfoot>
      </table>
    </div>
  );
}

/** The store's lots, loaded again whenever the store changes. */
function useLots(options, act, initialCode, initialLots) {
  const [code, setCode] = useState(initialCode);
  const [lots, setLots] = useState(initialLots ?? []);
  const [pending, startTransition] = useTransition();
  const choose = (next) => {
    setCode(next);
    if (!next) return setLots([]);
    startTransition(async () => {
      const res = await act(`lots?store=${encodeURIComponent(next)}`);
      if (res?.status === "success") setLots(res.data);
    });
  };
  const idOf = (c) => options.store_codes.find((x) => x.value === c)?.id ?? "";
  const codeOf = (id) =>
    options.store_codes.find((x) => x.id === id)?.value ?? "";
  return { code, lots, choose, idOf, codeOf, pending };
}
const yarnCode = (codes) =>
  (codes.find((x) => /yarn/i.test(x.label)) ?? codes[0])?.value ?? "";

/* ---------------- Party-Wise Yarn Issue Challan ---------------- */

const PW = "/knit-production/party-wise-yarn-issue-challan";
const PW_STEPS = [
  ["issue", ["DRAFT"]],
  ["accept", ["ISSUED"]],
  ["reject", ["ISSUED"]],
  ["cancel", ["DRAFT", "ISSUED"]],
];

function PartyChallanList({ options, initialRows, canManage, act }) {
  const s = useYs();
  const r = useRegister("party-challans", initialRows, act, "pc_done");
  return (
    <KitList
      rows={r.rows}
      dateKey="challan_date"
      statusOptions={statusOpts(s, "pc_status", options.challan_statuses)}
      chips={[
        [s("pw.chip.count"), r.rows.length],
        [
          s("pw.chip.qty"),
          qtyFmt.format(
            r.rows
              .filter((x) => ["ISSUED", "ACCEPTED"].includes(x.status))
              .reduce((t, x) => t + x.qty, 0),
          ),
        ],
      ]}
      columns={[
        { key: "challan_no", label: s("pw.lc.no"), nowrap: true },
        {
          key: "challan_date",
          label: s("pw.lc.date"),
          nowrap: true,
          render: (x) => dayFmt(x.challan_date),
        },
        { key: "company", label: s("pw.lc.company") },
        { key: "party", label: s("pw.lc.party") },
        { key: "store", label: s("pw.lc.store") },
        { key: "gate_pass_no", label: s("pw.lc.gate_pass") },
        {
          key: "qty",
          label: s("pw.lc.qty"),
          num: true,
          render: (x) => qtyFmt.format(x.qty),
        },
        {
          key: "status",
          label: s("kit.status"),
          text: (x) => s(`pc_status.${x.status}`),
          render: (x) => chip(s, "pc_status", x.status),
        },
      ]}
      actions={(x) => (
        <ActionButtons
          no={x.challan_no}
          status={x.status}
          steps={PW_STEPS}
          run={r.run}
          pending={r.pending}
          canManage={canManage}
          open={`${PW}?tab=entry&ref=${encodeURIComponent(x.challan_no)}`}
        />
      )}
      addHref={canManage ? `${PW}?tab=entry` : null}
      empty={s("pw.empty")}
      exportName="party-wise-yarn-issue-challans"
      error={r.error}
      onRefresh={r.refresh}
      pending={r.pending}
    />
  );
}

const PW_HEAD = [
  "challan_date",
  "party_id",
  "gate_pass_no",
  "vehicle_no",
  "remarks",
];
const lotLine = (l = {}) => ({
  key: key(),
  lot_id: str(l.lot_id),
  lot: l.lot,
  balance: l.balance,
  qty: str(l.qty),
  rate: str(l.rate),
  cones: str(l.cones),
  cartons: str(l.cartons),
  remarks: str(l.remarks),
});

function PartyChallanEntry({ options, initial, initialLots, canManage, act }) {
  const s = useYs();
  const toastManager = useToastManager();
  const [today] = useState(todayIso);
  const blankHead = () => ({
    ...Object.fromEntries(PW_HEAD.map((k) => [k, ""])),
    challan_date: today,
  });
  const asHead = (d) => Object.fromEntries(PW_HEAD.map((k) => [k, str(d[k])]));
  const [doc, setDoc] = useState(initial);
  const [head, setHead] = useState(() =>
    initial ? asHead(initial) : blankHead(),
  );
  const st = useLots(
    options,
    act,
    initial
      ? (options.store_codes.find((x) => x.id === initial.store_id)?.value ??
          "")
      : yarnCode(options.store_codes),
    initialLots,
  );
  const [lines, setLines] = useState(() => (initial?.lines ?? []).map(lotLine));
  const [errors, setErrors] = useState(null);
  const [pending, startTransition] = useTransition();
  const editable = canManage && (!doc || doc.editable);
  const setH = (patch) => setHead((h) => ({ ...h, ...patch }));

  function take(data) {
    setDoc(data);
    setHead(asHead(data));
    setLines(data.lines.map(lotLine));
    setErrors(null);
    window.history.replaceState(
      null,
      "",
      `?tab=entry&ref=${encodeURIComponent(data.challan_no)}`,
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
      const res = await act(`party-challans/${encodeURIComponent(no)}`);
      if (res?.status !== "success") return setErrors(messagesOf(res));
      take(res.data);
      st.choose(st.codeOf(res.data.store_id));
    });
  }
  function save() {
    startTransition(async () => {
      const body = {
        ...Object.fromEntries(
          PW_HEAD.map((k) => [k, head[k] === "" ? null : head[k]]),
        ),
        store_id: st.idOf(st.code) || null,
        lines: lines.map((l) => ({
          lot_id: l.lot_id || null,
          qty: orNull(l.qty),
          rate: orNull(l.rate),
          cones: orNull(l.cones),
          remarks: l.remarks || null,
        })),
      };
      const res = await act(
        doc
          ? `party-challans/${encodeURIComponent(doc.challan_no)}`
          : "party-challans",
        doc ? "PUT" : "POST",
        body,
      );
      if (res?.status !== "success") return setErrors(messagesOf(res));
      take(res.data);
      toastManager.add({
        title: s("pw.saved", { no: res.data.challan_no }),
        type: "success",
      });
    });
  }

  return (
    <div className="flex min-w-0 flex-col gap-4">
      <section className={cn(card, "p-5")}>
        <Head
          title={s("tabs.pw_entry")}
          hint={s("pw.entry_hint")}
          doc={doc}
          no={doc?.challan_no}
          group="pc_status"
        />
        <Field
          label={s("pw.existing")}
          hint={s("ob.existing_hint")}
          className="mt-4"
        >
          <PSelect
            value={doc?.challan_no ?? ""}
            onValueChange={(v) => load(v ?? "")}
            options={[
              { value: "", label: s("pw.existing_new") },
              ...options.challans,
            ]}
            aria-label={s("pw.existing")}
          />
        </Field>
        <div className="mt-4 grid gap-4 md:grid-cols-3">
          <Field label={s("pw.date")} required>
            <Input
              type="date"
              value={head.challan_date}
              onChange={(e) => setH({ challan_date: e.target.value })}
              disabled={!editable}
              aria-label={s("pw.date")}
            />
          </Field>
          <Field label={s("pw.party")} required>
            <PSelect
              value={head.party_id}
              onValueChange={(v) => setH({ party_id: v ?? "" })}
              options={[
                { value: "", label: "-- Select Party --" },
                ...options.suppliers,
              ]}
              disabled={!editable}
              aria-label={s("pw.party")}
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
              value={st.code}
              onValueChange={(v) => (st.choose(v ?? ""), setLines([]))}
              options={[
                { value: "", label: s("select") },
                ...options.store_codes,
              ]}
              disabled={!editable}
              aria-label={s("store")}
            />
          </Field>
          <Field label={s("pw.gate_pass")}>
            <Input
              value={head.gate_pass_no}
              onChange={(e) => setH({ gate_pass_no: e.target.value })}
              maxLength={40}
              disabled={!editable}
              aria-label={s("pw.gate_pass")}
            />
          </Field>
          <Field label={s("pw.vehicle")}>
            <Input
              value={head.vehicle_no}
              onChange={(e) => setH({ vehicle_no: e.target.value })}
              maxLength={40}
              disabled={!editable}
              aria-label={s("pw.vehicle")}
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
        <LinesHead
          title={s("pw.lines")}
          hint={s("pw.lines_hint")}
          onAdd={editable ? () => setLines((all) => [...all, lotLine()]) : null}
          disabled={!st.code}
        />
        <LotLines
          lines={lines}
          setLines={setLines}
          lots={st.lots}
          editable={editable}
          extra={[
            { key: "rate", label: s("col.rate") },
            { key: "cones", label: s("col.cones") },
          ]}
        />
      </section>
      <Foot
        errors={errors}
        foot={s("pw.foot")}
        back={PW}
        clear={clear}
        pending={pending}
        save={editable ? save : null}
        saveLabel={s("pw.save")}
      />
    </div>
  );
}

/* ---------------- Supplier Yarn Replacement Register ---------------- */

const RP = "/knit-production/issue-yarn-replace-by-supplier-list";
const RP_STEPS = [
  ["submit", ["DRAFT"]],
  ["accept", ["SUBMITTED"]],
  ["cancel", ["DRAFT", "SUBMITTED"]],
];

function ReplacementList({ options, initialRows, canManage, act }) {
  const s = useYs();
  const r = useRegister("replacements", initialRows, act, "rp_done");
  return (
    <KitList
      rows={r.rows}
      dateKey="challan_date"
      statusOptions={statusOpts(s, "rp_status", options.replacement_statuses)}
      selects={[
        {
          key: "supplier_id",
          label: s("rp.supplier"),
          options: options.suppliers,
        },
        {
          key: "return_type",
          label: s("rp.return_type"),
          options: options.return_types.map((t) => ({
            value: t,
            label: s(`return_type.${t}`),
          })),
        },
      ]}
      chips={[
        [s("rp.chip.count"), r.rows.length],
        [
          s("rp.chip.qty"),
          qtyFmt.format(
            r.rows
              .filter((x) => x.status !== "CANCELLED")
              .reduce((t, x) => t + x.qty, 0),
          ),
        ],
      ]}
      columns={[
        { key: "challan_no", label: s("rp.lc.no"), nowrap: true },
        {
          key: "challan_date",
          label: s("rp.lc.date"),
          nowrap: true,
          render: (x) => dayFmt(x.challan_date),
        },
        { key: "company", label: s("rp.lc.company") },
        { key: "store", label: s("rp.lc.store") },
        { key: "supplier", label: s("rp.lc.supplier") },
        {
          key: "return_type",
          label: s("rp.lc.type"),
          text: (x) => s(`return_type.${x.return_type}`),
          render: (x) => s(`return_type.${x.return_type}`),
        },
        { key: "lc_no", label: s("rp.lc.lc") },
        { key: "lot_ref", label: s("rp.lc.lot") },
        { key: "claim_ref", label: s("rp.lc.claim") },
        { key: "cartons", label: s("rp.lc.cartons"), num: true },
        {
          key: "qty",
          label: s("rp.lc.qty"),
          num: true,
          render: (x) => qtyFmt.format(x.qty),
        },
        {
          key: "status",
          label: s("kit.status"),
          text: (x) => s(`rp_status.${x.status}`),
          render: (x) => chip(s, "rp_status", x.status),
        },
      ]}
      actions={(x) => (
        <ActionButtons
          no={x.challan_no}
          status={x.status}
          steps={RP_STEPS}
          run={r.run}
          pending={r.pending}
          canManage={canManage}
          open={`${RP}?tab=entry&ref=${encodeURIComponent(x.challan_no)}`}
        />
      )}
      addHref={canManage ? `${RP}?tab=entry` : null}
      empty={s("rp.empty")}
      exportName="supplier-yarn-replacements"
      error={r.error}
      onRefresh={r.refresh}
      pending={r.pending}
    />
  );
}

const RP_HEAD = [
  "challan_date",
  "supplier_id",
  "claim_ref",
  "return_type",
  "lc_no",
  "lot_ref",
  "remarks",
];

function ReplacementEntry({ options, initial, initialLots, canManage, act }) {
  const s = useYs();
  const toastManager = useToastManager();
  const [today] = useState(todayIso);
  const blankHead = () => ({
    ...Object.fromEntries(RP_HEAD.map((k) => [k, ""])),
    challan_date: today,
  });
  const asHead = (d) => Object.fromEntries(RP_HEAD.map((k) => [k, str(d[k])]));
  const [doc, setDoc] = useState(initial);
  const [head, setHead] = useState(() =>
    initial ? asHead(initial) : blankHead(),
  );
  const st = useLots(
    options,
    act,
    initial
      ? (options.store_codes.find((x) => x.id === initial.store_id)?.value ??
          "")
      : yarnCode(options.store_codes),
    initialLots,
  );
  const [lines, setLines] = useState(() => (initial?.lines ?? []).map(lotLine));
  const [errors, setErrors] = useState(null);
  const [pending, startTransition] = useTransition();
  const editable = canManage && (!doc || doc.editable);
  const setH = (patch) => setHead((h) => ({ ...h, ...patch }));
  // Only the spinner's own lots (or ones of no known supplier) can go back to it.
  const lots = st.lots.filter(
    (x) =>
      !head.supplier_id || !x.supplier_id || x.supplier_id === head.supplier_id,
  );

  function take(data) {
    setDoc(data);
    setHead(asHead(data));
    setLines(data.lines.map(lotLine));
    setErrors(null);
    window.history.replaceState(
      null,
      "",
      `?tab=entry&ref=${encodeURIComponent(data.challan_no)}`,
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
      const res = await act(`replacements/${encodeURIComponent(no)}`);
      if (res?.status !== "success") return setErrors(messagesOf(res));
      take(res.data);
      st.choose(st.codeOf(res.data.store_id));
    });
  }
  function save() {
    startTransition(async () => {
      const body = {
        ...Object.fromEntries(
          RP_HEAD.map((k) => [k, head[k] === "" ? null : head[k]]),
        ),
        store_id: st.idOf(st.code) || null,
        lines: lines.map((l) => ({
          lot_id: l.lot_id || null,
          cartons: orNull(l.cartons),
          qty: orNull(l.qty),
          remarks: l.remarks || null,
        })),
      };
      const res = await act(
        doc
          ? `replacements/${encodeURIComponent(doc.challan_no)}`
          : "replacements",
        doc ? "PUT" : "POST",
        body,
      );
      if (res?.status !== "success") return setErrors(messagesOf(res));
      take(res.data);
      toastManager.add({
        title: s("rp.saved", { no: res.data.challan_no }),
        type: "success",
      });
    });
  }

  return (
    <div className="flex min-w-0 flex-col gap-4">
      <section className={cn(card, "p-5")}>
        <Head
          title={s("tabs.rp_entry")}
          hint={s("rp.entry_hint")}
          doc={doc}
          no={doc?.challan_no}
          group="rp_status"
        />
        <Field
          label={s("rp.existing")}
          hint={s("ob.existing_hint")}
          className="mt-4"
        >
          <PSelect
            value={doc?.challan_no ?? ""}
            onValueChange={(v) => load(v ?? "")}
            options={[
              { value: "", label: s("rp.existing_new") },
              ...options.replacements,
            ]}
            aria-label={s("rp.existing")}
          />
        </Field>
        <fieldset className="mt-4 rounded-lg border border-border p-4">
          <legend className="px-1 text-[13px] font-semibold">
            {s("rp.key_info")}
          </legend>
          <div className="grid gap-4 md:grid-cols-3">
            <Field label={s("rp.no")}>
              <Input
                value={doc?.challan_no ?? ""}
                placeholder={s("rp.no_ph")}
                readOnly
                className="bg-surface-muted"
                aria-label={s("rp.no")}
              />
            </Field>
            <Field label={s("rp.date")} required>
              <Input
                type="date"
                value={head.challan_date}
                onChange={(e) => setH({ challan_date: e.target.value })}
                disabled={!editable}
                aria-label={s("rp.date")}
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
            <Field label={s("irr.store")} required>
              <PSelect
                value={st.code}
                onValueChange={(v) => (st.choose(v ?? ""), setLines([]))}
                options={[
                  { value: "", label: "-- Select --" },
                  ...options.store_codes,
                ]}
                disabled={!editable}
                aria-label={s("irr.store")}
              />
            </Field>
          </div>
        </fieldset>
        <fieldset className="mt-4 rounded-lg border border-border p-4">
          <legend className="px-1 text-[13px] font-semibold">
            {s("rp.consignment")}
          </legend>
          <div className="grid gap-4 md:grid-cols-3">
            <Field label={s("rp.supplier")} required>
              <PSelect
                value={head.supplier_id}
                onValueChange={(v) => setH({ supplier_id: v ?? "" })}
                options={[
                  { value: "", label: "-- Select --" },
                  ...options.suppliers,
                ]}
                disabled={!editable}
                aria-label={s("rp.supplier")}
              />
            </Field>
            <Field label={s("rp.claim")} hint={s("rp.claim_hint")}>
              <PSelect
                value=""
                options={[{ value: "", label: s("rp.no_claim") }]}
                disabled
                aria-label={s("rp.claim")}
              />
            </Field>
            <Field label={s("rp.return_type")} required>
              <PSelect
                value={head.return_type}
                onValueChange={(v) => setH({ return_type: v ?? "" })}
                options={[
                  { value: "", label: "Select..." },
                  ...options.return_types.map((t) => ({
                    value: t,
                    label: s(`return_type.${t}`),
                  })),
                ]}
                disabled={!editable}
                aria-label={s("rp.return_type")}
              />
            </Field>
            <Field label={s("rp.lc_no")} hint={s("rp.lc_no_hint")}>
              <Input
                value={head.lc_no}
                onChange={(e) => setH({ lc_no: e.target.value })}
                maxLength={40}
                disabled={!editable}
                aria-label={s("rp.lc_no")}
              />
            </Field>
            <Field label={s("rp.lot")} hint={s("rp.lot_hint")}>
              <Input
                value={head.lot_ref}
                onChange={(e) =>
                  setH({ lot_ref: e.target.value.toUpperCase() })
                }
                maxLength={40}
                disabled={!editable}
                aria-label={s("rp.lot")}
              />
            </Field>
          </div>
        </fieldset>
        <Field label={s("remarks")} className="mt-4">
          <Textarea
            rows={2}
            value={head.remarks}
            onChange={(e) => setH({ remarks: e.target.value })}
            maxLength={2000}
            disabled={!editable}
            aria-label={s("remarks")}
          />
        </Field>
      </section>
      <section className={cn(card, "overflow-hidden")}>
        <LinesHead
          title={s("rp.lines")}
          hint={s("rp.lines_hint")}
          onAdd={editable ? () => setLines((all) => [...all, lotLine()]) : null}
          disabled={!st.code}
        />
        <LotLines
          lines={lines}
          setLines={setLines}
          lots={lots}
          editable={editable}
          extra={[{ key: "cartons", label: s("col.cartons"), before: true }]}
        />
      </section>
      <Foot
        errors={errors}
        foot={s("rp.foot")}
        back={RP}
        clear={clear}
        pending={pending}
        save={editable ? save : null}
        saveLabel={s("rp.save")}
      />
    </div>
  );
}

export {
  IssueReturnList,
  IssueReturnEntry,
  PartyChallanList,
  PartyChallanEntry,
  ReplacementList,
  ReplacementEntry,
};
