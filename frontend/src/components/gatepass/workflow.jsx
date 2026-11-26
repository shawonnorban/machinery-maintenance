"use client";

import { useState, useTransition } from "react";
import Link from "next/link";
import {
  Ban,
  CircleCheck,
  Download,
  LogIn,
  LogOut,
  Pencil,
  Plus,
  RotateCcw,
  ScanLine,
  Search,
  Send,
  ShieldCheck,
  Undo2,
  X,
} from "lucide-react";
import { cn } from "@/lib/utils";
import { Button, buttonVariants } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Select } from "@/components/ui/select";
import { useToastManager } from "@/components/ui/toast";
import {
  card,
  qty,
  firstMessage,
  Field,
  useGp,
  PassTable,
  PassView,
  ConfirmDialog,
} from "./common";

/*
 * Gate Pass → Approval, Out, In, Return Reconciliation and List
 * (AnnotechERP 11/04), as Brickland lays them out. Each screen lists its
 * passes; a click (or a scanned number) opens the pass in place above the
 * lists with the buttons its status allows, and every step asks first in a
 * dialog. Nothing reloads the page.
 */

const LIST_COLS = [
  "gp_no",
  "date",
  "category",
  "type",
  "purpose",
  "destination",
  "taken_by",
  "items",
  "qty",
  "status",
];

/**
 * The open pass and the steps taken on it: each step's dialog, the call,
 * the toast, and the lists reloaded after it.
 */
function useWorkflow(act, reload, first = null) {
  const { g } = useGp();
  const toastManager = useToastManager();
  const [pass, setPass] = useState(first);
  const [dialog, setDialog] = useState(null);
  const [error, setError] = useState("");
  const [pending, startTransition] = useTransition();

  const open = (no) =>
    startTransition(async () => {
      const res = await act("show", no);
      if (res?.status !== "success") {
        toastManager.add({ title: firstMessage(res), type: "error" });
        return;
      }
      setPass(res.data);
    });
  const run = (op, payload, toastKey, { keep = true, done } = {}) =>
    startTransition(async () => {
      setError("");
      const no = pass.gp_no;
      const res = await act(op, no, payload);
      if (res?.status !== "success") {
        const first = firstMessage(res);
        setError(first);
        toastManager.add({ title: first, type: "error" });
        return;
      }
      toastManager.add({
        title: g(`toast.${toastKey}`, { no }),
        type: "success",
      });
      setDialog(null);
      setPass(keep ? res.data : null);
      done?.();
      await reload?.();
    });
  const ask = (kind) => {
    setError("");
    setDialog(kind);
  };

  const DIALOGS = {
    approve: {
      title: g("dialog.approve_title", { no: pass?.gp_no }),
      body: g("dialog.approve_body"),
      field: { label: g("dialog.note_optional") },
      confirm: g("btn.approve"),
      variant: "success",
      go: (text) => run("approve", { note: text || null }, "approved"),
    },
    reject: {
      title: g("dialog.reject_title", { no: pass?.gp_no }),
      body: g("dialog.reject_body"),
      field: { label: g("dialog.reason"), required: true },
      confirm: g("btn.reject"),
      variant: "danger",
      go: (text) => run("reject", { reason: text }, "rejected"),
    },
    send_back: {
      title: g("dialog.send_back_title", { no: pass?.gp_no }),
      body: g("dialog.send_back_body"),
      field: { label: g("dialog.reason"), required: true },
      confirm: g("btn.send_back"),
      go: (text) => run("sendBack", { reason: text }, "sent_back"),
    },
    cancel: {
      title: g("dialog.cancel_title", { no: pass?.gp_no }),
      body: g("dialog.cancel_body"),
      field: { label: g("dialog.reason_optional") },
      confirm: g("btn.cancel_pass"),
      variant: "danger",
      go: (text) => run("cancel", { reason: text || null }, "cancelled"),
    },
  };
  const dialogs =
    dialog && DIALOGS[dialog] ? (
      <ConfirmDialog
        key={dialog}
        title={DIALOGS[dialog].title}
        body={DIALOGS[dialog].body}
        field={DIALOGS[dialog].field}
        confirm={DIALOGS[dialog].confirm}
        variant={DIALOGS[dialog].variant}
        pending={pending}
        error={error}
        onConfirm={DIALOGS[dialog].go}
        onClose={() => setDialog(null)}
      />
    ) : null;

  return {
    pass,
    setPass,
    open,
    run,
    ask,
    dialog,
    setDialog,
    dialogs,
    pending,
    error,
    setError,
  };
}

function useLists(act, keys) {
  return async (setLists) => {
    const results = await Promise.all(keys.map((k) => act("list", k)));
    setLists((all) => {
      const next = { ...all };
      keys.forEach((k, i) => {
        if (results[i]?.status === "success") next[k] = results[i].data.rows;
      });
      return next;
    });
  };
}

/**
 * Gate Pass Approval: waiting for approval (oldest first) and approved —
 * waiting at the gate; a pass opens in place to approve, reject, send back
 * or cancel.
 *
 * @param {{ lists: { approval: object[], approved: object[] }, perms: object, act: Function }} props
 */
function GpApproval({ lists: initial, perms, act }) {
  const { g } = useGp();
  const a = (k, p) => g(`approval.${k}`, p);
  const [lists, setLists] = useState(initial);
  const refresh = useLists(act, ["approval", "approved"]);
  const wf = useWorkflow(act, () => refresh(setLists));
  const pass = wf.pass;
  const b = (label, Icon, onClick, variant = "outline") => (
    <Button key={label} size="sm" variant={variant} onClick={onClick}>
      <Icon className="size-4" aria-hidden />
      {g(`btn.${label}`)}
    </Button>
  );
  const buttons = !pass
    ? null
    : pass.status === "PENDING"
      ? [
          b("approve", CircleCheck, () => wf.ask("approve"), "success"),
          b("reject", X, () => wf.ask("reject")),
          b("cancel_pass", Ban, () => wf.ask("cancel")),
        ]
      : pass.status === "APPROVED"
        ? [
            b("send_back", Undo2, () => wf.ask("send_back")),
            perms.gate ? (
              <Link
                key="out"
                href={`/gate-pass/gp-out?no=${encodeURIComponent(pass.gp_no)}`}
                className={buttonVariants({ size: "sm" })}
              >
                <LogOut className="size-4" aria-hidden />
                {g("btn.gate_out")}
              </Link>
            ) : null,
            b("cancel_pass", Ban, () => wf.ask("cancel")),
          ]
        : null;

  return (
    <div className="flex flex-col gap-4">
      {pass ? (
        <PassView
          pass={pass}
          buttons={buttons}
          onClose={() => wf.setPass(null)}
        />
      ) : null}
      <PassTable
        title={a("waiting", { count: lists.approval.length })}
        hint={a("waiting_hint")}
        rows={lists.approval}
        cols={[...LIST_COLS, "prepared_by"]}
        empty={a("waiting_empty")}
        onOpen={wf.open}
        openNo={pass?.gp_no}
      />
      <PassTable
        title={a("approved", { count: lists.approved.length })}
        hint={a("approved_hint")}
        rows={lists.approved}
        cols={LIST_COLS}
        empty={a("approved_empty")}
        onOpen={wf.open}
        openNo={pass?.gp_no}
      />
      {wf.dialogs}
    </div>
  );
}

/** The scan box Gate Security types or scans a pass number into. */
function ScanBox({ onSearch, onClear, pending, loaded }) {
  const { g } = useGp();
  const [no, setNo] = useState("");
  return (
    <form
      className={cn(card, "flex flex-wrap items-end gap-3 p-4")}
      onSubmit={(ev) => {
        ev.preventDefault();
        if (no.trim()) onSearch(no.trim().toUpperCase());
      }}
    >
      <Field label={g("gate.scan")} className="min-w-64 flex-1">
        <div className="relative">
          <ScanLine
            className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-foreground-muted"
            aria-hidden
          />
          <Input
            value={no}
            onChange={(ev) => setNo(ev.target.value)}
            placeholder={g("gate.scan_ph")}
            aria-label={g("gate.scan")}
            className="pl-9 font-mono"
            autoFocus
          />
        </div>
      </Field>
      <Button type="submit" loading={pending}>
        <Search className="size-4" aria-hidden />
        {g("btn.search")}
      </Button>
      {loaded ? (
        <Button
          type="button"
          variant="outline"
          onClick={() => {
            setNo("");
            onClear();
          }}
        >
          <RotateCcw className="size-4" aria-hidden />
          {g("btn.clear")}
        </Button>
      ) : null}
    </form>
  );
}

/**
 * Gate Pass Out: scan or pick an approved pass, check it, add a remark and
 * confirm OUT; today's OUTs below.
 *
 * @param {{ lists: { approved: object[], "out-today": object[] }, initial?: object, act: Function }} props
 */
function GateOut({ lists: initial, initial: first, act }) {
  const { g } = useGp();
  const [lists, setLists] = useState(initial);
  const [remarks, setRemarks] = useState("");
  const refresh = useLists(act, ["approved", "out-today"]);
  // Opened from Gate Pass Approval's Gate OUT button with the pass loaded.
  const wf = useWorkflow(act, () => refresh(setLists), first ?? null);
  const pass = wf.pass;
  const ready = pass?.status === "APPROVED";

  return (
    <div className="flex flex-col gap-4">
      <ScanBox
        onSearch={wf.open}
        onClear={() => wf.setPass(null)}
        pending={wf.pending}
        loaded={Boolean(pass)}
      />
      {pass ? (
        <PassView
          pass={pass}
          onClose={() => wf.setPass(null)}
          notice={
            ready ? null : (
              <p className="rounded-lg border border-danger/30 bg-danger-subtle px-4 py-2.5 text-sm text-danger">
                {g("gate.not_ready_out", {
                  no: pass.gp_no,
                  status: g(`status.${pass.status}`),
                })}
              </p>
            )
          }
        >
          {ready ? (
            <div className="flex flex-wrap items-end gap-3">
              <Field label={g("gate.gate_remarks")} className="min-w-64 flex-1">
                <Input
                  value={remarks}
                  onChange={(ev) => setRemarks(ev.target.value)}
                  aria-label={g("gate.gate_remarks")}
                />
              </Field>
              <Button
                variant="success"
                size="default"
                onClick={() => wf.ask("out")}
                className="px-6 font-semibold tracking-wide"
              >
                <LogOut className="size-4" aria-hidden />
                {g("btn.confirm_out")}
              </Button>
            </div>
          ) : null}
        </PassView>
      ) : null}
      <PassTable
        title={g("gate.waiting_out", { count: lists.approved.length })}
        hint={g("gate.click_load")}
        rows={lists.approved}
        cols={LIST_COLS}
        empty={g("approval.approved_empty")}
        onOpen={wf.open}
        openNo={pass?.gp_no}
      />
      <PassTable
        title={g("gate.today_out", { count: lists["out-today"].length })}
        rows={lists["out-today"]}
        cols={[...LIST_COLS, "out_time"]}
        empty={g("gate.nothing_today")}
      />
      {wf.dialog === "out" && pass ? (
        <ConfirmDialog
          title={g("dialog.out_title", { no: pass.gp_no })}
          body={g(
            pass.pass_type === "RETURNABLE"
              ? "dialog.out_body"
              : "dialog.out_body_once",
          )}
          confirm={g("btn.confirm_out_dialog")}
          variant="success"
          pending={wf.pending}
          error={wf.error}
          onConfirm={() => {
            wf.run("out", { remarks: remarks || null }, "out", { keep: false });
            setRemarks("");
          }}
          onClose={() => wf.setDialog(null)}
        />
      ) : null}
    </div>
  );
}

const blankEntry = { qty: {}, final: false, reason: "", remarks: "" };

/**
 * Gate Pass In: scan or pick a returnable pass that is out; only the return
 * quantities are entered — partial, full, or a final return that declares
 * the rest short with a reason. An employee's pass just records the return.
 *
 * @param {{ lists: { "in-await": object[], "in-today": object[] }, act: Function }} props
 */
function GateIn({ lists: initial, act }) {
  const { g, withUnit } = useGp();
  const [lists, setLists] = useState(initial);
  const refresh = useLists(act, ["in-await", "in-today"]);
  const wf = useWorkflow(act, () => refresh(setLists));
  const [entry, setEntry] = useState(blankEntry);
  const pass = wf.pass;
  const ready =
    pass &&
    pass.pass_type === "RETURNABLE" &&
    ["OUT", "PARTIAL"].includes(pass.status);
  const openPass = (no) => {
    setEntry(blankEntry);
    wf.open(no);
  };

  const unit = pass?.totals.unit;
  const lines = (pass?.lines ?? []).map((l) => {
    const q = Math.min(Math.max(Number(entry.qty[l.id] || 0), 0), l.balance);
    return { ...l, q, rest: Math.max(0, l.balance - q) };
  });
  const coming = lines.reduce((s, l) => s + l.q, 0);
  const left = lines.reduce((s, l) => s + l.rest, 0);
  const reasonName = entry.reason
    ? g(`short_reason.${entry.reason}`)
    : g("gate.reason_q");
  const outcome =
    left <= 0
      ? g("gate.all_back")
      : entry.final
        ? g("gate.declared", { qty: withUnit(left, unit), reason: reasonName })
        : g("gate.stays", { qty: withUnit(left, unit) });
  const canConfirm =
    pass?.kind === "MAIN" ||
    ((coming > 0 || entry.final) &&
      (!entry.final || left <= 0 || entry.reason));

  return (
    <div className="flex flex-col gap-4">
      <ScanBox
        onSearch={openPass}
        onClear={() => wf.setPass(null)}
        pending={wf.pending}
        loaded={Boolean(pass)}
      />
      {pass ? (
        <PassView
          pass={pass}
          onClose={() => wf.setPass(null)}
          notice={
            ready ? null : (
              <p className="rounded-lg border border-danger/30 bg-danger-subtle px-4 py-2.5 text-sm text-danger">
                {g("gate.not_ready_in", {
                  no: pass.gp_no,
                  status: g(`status.${pass.status}`),
                })}
              </p>
            )
          }
        >
          {ready && pass.kind === "MAIN" ? (
            <div className="flex flex-wrap items-end gap-3">
              <p className="w-full text-sm text-foreground-muted">
                {g("gate.main_back")}
              </p>
              <Field label={g("f.remarks")} className="min-w-64 flex-1">
                <Input
                  value={entry.remarks}
                  onChange={(ev) =>
                    setEntry((e) => ({ ...e, remarks: ev.target.value }))
                  }
                  aria-label={g("f.remarks")}
                />
              </Field>
              <Button
                variant="success"
                onClick={() => wf.ask("in")}
                className="px-6 font-semibold"
              >
                <LogIn className="size-4" aria-hidden />
                {g("btn.confirm_in")}
              </Button>
            </div>
          ) : null}
          {ready && pass.kind === "ITEM" ? (
            <div className="flex flex-col gap-4">
              <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                {[
                  ["out_quantity", pass.totals.out],
                  ["already", pass.totals.returned],
                  ["total_after", pass.totals.returned + coming],
                  [entry.final ? "short" : "balance", left],
                ].map(([k, v], i) => (
                  <div
                    key={k}
                    className="rounded-lg border border-border bg-surface-muted px-4 py-3"
                  >
                    <p className="text-xs font-semibold text-foreground-muted">
                      {g(`gate.${k}`)}
                    </p>
                    <p
                      className={cn(
                        "mt-1 text-lg font-semibold tabular-nums text-foreground",
                        i === 3 && entry.final && left > 0 && "text-danger",
                      )}
                    >
                      {withUnit(v, unit)}
                    </p>
                  </div>
                ))}
              </div>
              <div className="overflow-x-auto rounded-lg border border-border">
                <table className="w-full min-w-[720px] text-sm">
                  <thead className="bg-brand text-brand-foreground">
                    <tr>
                      {[
                        "item",
                        "out",
                        "returned",
                        "balance",
                        "return_qty",
                        entry.final ? "short" : "left_out",
                      ].map((k, i) => (
                        <th
                          key={k}
                          className={cn(
                            "px-3 py-2.5 text-xs font-semibold uppercase",
                            i === 0 || i === 4 ? "text-left" : "text-right",
                          )}
                        >
                          {k === "out"
                            ? g("col.out_qty")
                            : k === "returned"
                              ? g("col.returned")
                              : g(`gate.${k}`)}
                        </th>
                      ))}
                    </tr>
                  </thead>
                  <tbody>
                    {lines.map((l) => (
                      <tr key={l.id} className="border-t border-border">
                        <td className="px-3 py-2.5">
                          <span className="font-semibold">{l.item}</span>{" "}
                          <span className="text-xs text-foreground-muted">
                            ({l.item_code})
                          </span>
                        </td>
                        <td className="px-3 py-2.5 text-right tabular-nums">
                          {withUnit(l.out, l.unit)}
                        </td>
                        <td className="px-3 py-2.5 text-right tabular-nums">
                          {qty(l.returned)}
                        </td>
                        <td className="px-3 py-2.5 text-right tabular-nums">
                          {qty(l.balance)}
                        </td>
                        <td className="w-40 px-3 py-2">
                          <Input
                            type="number"
                            min="0"
                            max={l.balance}
                            step="any"
                            value={entry.qty[l.id] ?? ""}
                            placeholder={qty(l.balance)}
                            disabled={l.balance <= 0}
                            onChange={(ev) =>
                              setEntry((e) => ({
                                ...e,
                                qty: { ...e.qty, [l.id]: ev.target.value },
                              }))
                            }
                            aria-label={g("gate.return_for", { item: l.item })}
                          />
                        </td>
                        <td
                          className={cn(
                            "px-3 py-2.5 text-right tabular-nums",
                            entry.final && l.rest > 0 && "text-danger",
                          )}
                        >
                          {qty(l.rest)}
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
              <div className="flex flex-wrap items-end gap-4">
                <label className="flex max-w-md cursor-pointer items-start gap-2">
                  <input
                    type="checkbox"
                    checked={entry.final}
                    onChange={(ev) =>
                      setEntry((e) => ({ ...e, final: ev.target.checked }))
                    }
                    className="mt-0.5 size-4 accent-[var(--brand)]"
                  />
                  <span>
                    <span className="block text-sm font-semibold text-foreground">
                      {g("gate.final")}
                    </span>
                    <span className="block text-xs text-foreground-muted">
                      {g("gate.final_hint")}
                    </span>
                  </span>
                </label>
                {entry.final && left > 0 ? (
                  <Field
                    label={g("gate.short_reason")}
                    required
                    className="w-56"
                  >
                    <Select
                      value={entry.reason}
                      onValueChange={(x) =>
                        setEntry((e) => ({ ...e, reason: x ?? "" }))
                      }
                      options={[
                        { value: "", label: g("f.select") },
                        ...[
                          "MISSING",
                          "DAMAGE",
                          "REJECTED",
                          "QUALITY",
                          "OTHER",
                        ].map((r) => ({
                          value: r,
                          label: g(`short_reason.${r}`),
                        })),
                      ]}
                      placeholder={g("f.select")}
                      aria-label={g("gate.short_reason")}
                    />
                  </Field>
                ) : null}
              </div>
              <div className="flex flex-wrap items-end gap-3">
                <Field label={g("f.remarks")} className="min-w-64 flex-1">
                  <Input
                    value={entry.remarks}
                    onChange={(ev) =>
                      setEntry((e) => ({ ...e, remarks: ev.target.value }))
                    }
                    aria-label={g("f.remarks")}
                  />
                </Field>
                <Button
                  variant="success"
                  disabled={!canConfirm}
                  onClick={() => wf.ask("in")}
                  className="px-6 font-semibold tracking-wide"
                >
                  <LogIn className="size-4" aria-hidden />
                  {g("btn.confirm_in")}
                </Button>
              </div>
              <p className="text-sm text-foreground-muted">{outcome}</p>
            </div>
          ) : null}
        </PassView>
      ) : null}
      <PassTable
        title={g("gate.await", { count: lists["in-await"].length })}
        hint={g("gate.click_load")}
        rows={lists["in-await"]}
        cols={[...LIST_COLS, "balance", "expected_return"]}
        empty={g("gate.await_empty")}
        onOpen={openPass}
        openNo={pass?.gp_no}
      />
      <PassTable
        title={g("gate.today_in", { count: lists["in-today"].length })}
        rows={lists["in-today"]}
        cols={LIST_COLS}
        empty={g("gate.nothing_today")}
      />
      {wf.dialog === "in" && pass ? (
        <ConfirmDialog
          title={g("dialog.in_title", { no: pass.gp_no })}
          body={
            pass.kind === "MAIN"
              ? g("dialog.in_main")
              : [
                  g("dialog.in_coming", { qty: withUnit(coming, unit) }),
                  left > 0 && entry.final
                    ? g("gate.declared", {
                        qty: withUnit(left, unit),
                        reason: reasonName,
                      })
                    : left > 0
                      ? g("gate.stays", { qty: withUnit(left, unit) })
                      : "",
                ]
                  .filter(Boolean)
                  .join(" ")
          }
          confirm={g("btn.confirm_in_dialog")}
          variant="success"
          pending={wf.pending}
          error={wf.error}
          onConfirm={() =>
            wf.run(
              "in",
              {
                lines: lines
                  .filter((l) => l.q > 0)
                  .map((l) => ({ line_id: l.id, quantity: l.q })),
                final: entry.final,
                short_reason: entry.final && left > 0 ? entry.reason : null,
                remarks: entry.remarks || null,
              },
              "in",
              { keep: false, done: () => setEntry(blankEntry) },
            )
          }
          onClose={() => wf.setDialog(null)}
        />
      ) : null}
    </div>
  );
}

/**
 * Return Reconciliation: every returnable item pass that went out, OUT
 * against IN; a short one is closed by the approver, who authorises the
 * short quantity with a note.
 *
 * @param {{ rows: object[], shortWaiting: number, perms: object, act: Function }} props
 */
function Reconciliation({ rows: initial, shortWaiting, perms, act }) {
  const { g, withUnit } = useGp();
  const [lists, setLists] = useState({ recon: initial });
  const [waiting, setWaiting] = useState(shortWaiting);
  const wf = useWorkflow(act, async () => {
    const res = await act("list", "recon");
    if (res?.status === "success") {
      setLists({ recon: res.data.rows });
      setWaiting(res.data.short_waiting);
    }
  });
  const pass = wf.pass;
  const short = pass?.status === "SHORT";

  return (
    <div className="flex flex-col gap-4">
      {perms.approve && waiting > 0 ? (
        <p className="rounded-lg border border-danger/30 bg-danger-subtle px-4 py-3 text-sm text-danger">
          {g(waiting === 1 ? "recon.banner" : "recon.banner_many", {
            count: waiting,
          })}
        </p>
      ) : null}
      {pass ? (
        <PassView
          pass={pass}
          onClose={() => wf.setPass(null)}
          buttons={
            short && perms.approve ? (
              <Button size="sm" onClick={() => wf.ask("close")}>
                <ShieldCheck className="size-4" aria-hidden />
                {g("btn.close_short")}
              </Button>
            ) : null
          }
          notice={
            short ? (
              <p className="rounded-lg border border-danger/30 bg-danger-subtle px-4 py-2.5 text-sm text-danger">
                {g("recon.short_line", {
                  qty: withUnit(pass.totals.short, pass.totals.unit),
                  reason: pass.short_reason
                    ? g(`short_reason.${pass.short_reason}`)
                    : "—",
                })}
              </p>
            ) : null
          }
        />
      ) : null}
      <PassTable
        title={g("recon.title")}
        hint={g("recon.hint")}
        rows={lists.recon}
        cols={[
          "gp_no",
          "destination",
          "purpose",
          "items",
          "expected_return_day",
          "out",
          "returned",
          "short",
          "balance",
          "return",
          "status",
        ]}
        empty={g("recon.empty")}
        onOpen={wf.open}
        openNo={pass?.gp_no}
      />
      {wf.dialog === "close" && pass ? (
        <ConfirmDialog
          title={g("dialog.close_title", {
            no: pass.gp_no,
            qty: withUnit(pass.totals.short, pass.totals.unit),
          })}
          body={g("dialog.close_body")}
          field={{ label: g("dialog.approval_note"), required: true }}
          confirm={g("btn.approve_close")}
          pending={wf.pending}
          error={wf.error}
          onConfirm={(text) => wf.run("close", { note: text }, "closed")}
          onClose={() => wf.setDialog(null)}
        />
      ) : null}
    </div>
  );
}

const STATUSES = [
  "DRAFT",
  "PENDING",
  "APPROVED",
  "REJECTED",
  "CANCELLED",
  "OUT",
  "PARTIAL",
  "SHORT",
  "CLOSED",
  "COMPLETED",
];
const filled = (f) =>
  Object.fromEntries(Object.entries(f).filter(([, x]) => x));

/**
 * Gate Pass List: every pass, by number, date, employee, item, buyer, style,
 * PO, destination, purpose, type, status and carrier; Excel; a click opens
 * the pass — a draft to edit, submit or cancel.
 *
 * @param {{ rows: object[], options: object, perms: object, act: Function }} props
 */
function GatePassList({ rows: initial, options, perms, act }) {
  const { g } = useGp();
  const l = (k, p) => g(`list.${k}`, p);
  const blank = {
    q: "",
    from: "",
    to: "",
    category: "",
    type: "",
    status: "",
    purpose: "",
    location: "",
    employee: "",
    item: "",
    buyer: "",
    style: "",
    po: "",
    carrier: "",
  };
  const [f, setF] = useState(blank);
  const [shown, setShown] = useState({});
  const [lists, setLists] = useState({ all: initial });
  const [loading, startLoading] = useTransition();
  const wf = useWorkflow(act, async () => {
    const res = await act("list", "all", shown);
    if (res?.status === "success") setLists({ all: res.data.rows });
  });
  const load = (nf) =>
    startLoading(async () => {
      const res = await act("list", "all", filled(nf));
      if (res?.status === "success") {
        setLists({ all: res.data.rows });
        setShown(filled(nf));
      }
    });
  const pass = wf.pass;
  const set = (k) => (x) => setF((all) => ({ ...all, [k]: x ?? "" }));
  const all = l("all");
  const pick = (k, list) => (
    <Field key={k} label={l(k)}>
      <Select
        value={f[k]}
        onValueChange={set(k)}
        options={[{ value: "", label: all }, ...list]}
        placeholder={all}
        aria-label={l(k)}
      />
    </Field>
  );
  const text = (k, extra = {}) => (
    <Field key={k} label={l(k)}>
      <Input
        value={f[k]}
        onChange={(ev) => set(k)(ev.target.value)}
        aria-label={l(k)}
        {...extra}
      />
    </Field>
  );
  const editable =
    pass &&
    pass.status === "DRAFT" &&
    perms.create &&
    (pass.mine || perms.approve);
  const cancellable =
    pass &&
    ((["DRAFT", "PENDING"].includes(pass.status) &&
      pass.mine &&
      perms.create) ||
      (["DRAFT", "PENDING", "APPROVED"].includes(pass.status) &&
        perms.approve));
  const excel = `/api/gate-pass/excel/list${Object.keys(shown).length ? `?${new URLSearchParams(shown)}` : ""}`;

  return (
    <div className="flex flex-col gap-4">
      <form
        className={cn(
          card,
          "grid gap-3 p-4 sm:grid-cols-2 lg:grid-cols-[repeat(7,minmax(0,1fr))]",
        )}
        onSubmit={(ev) => {
          ev.preventDefault();
          load(f);
        }}
      >
        {text("q", { placeholder: l("search_ph") })}
        <Field label={l("from")}>
          <Input
            type="date"
            value={f.from}
            onChange={(ev) => set("from")(ev.target.value)}
            aria-label={l("from")}
          />
        </Field>
        <Field label={l("to")}>
          <Input
            type="date"
            value={f.to}
            onChange={(ev) => set("to")(ev.target.value)}
            aria-label={l("to")}
          />
        </Field>
        {pick(
          "category",
          ["MAIN", "ITEM"].map((k) => ({ value: k, label: g(`kind.${k}`) })),
        )}
        {pick(
          "type",
          ["RETURNABLE", "NON_RETURNABLE"].map((k) => ({
            value: k,
            label: g(`type.${k}`),
          })),
        )}
        {pick(
          "status",
          STATUSES.map((k) => ({ value: k, label: g(`status.${k}`) })),
        )}
        {pick("purpose", options.purposes)}
        {pick("location", options.locations)}
        {text("employee")}
        {text("item")}
        {text("buyer")}
        {text("style")}
        {text("po")}
        {pick(
          "carrier",
          ["EMPLOYEE", "NON_EMPLOYEE"].map((k) => ({
            value: k,
            label: g(`carrier.${k}`),
          })),
        )}
        <div className="flex items-end gap-2 sm:col-span-2 lg:col-span-7">
          <Button type="submit" loading={loading}>
            <Search className="size-4" aria-hidden />
            {g("btn.search")}
          </Button>
          <Button
            type="button"
            variant="outline"
            onClick={() => {
              setF(blank);
              load({});
            }}
          >
            <RotateCcw className="size-4" aria-hidden />
            {g("btn.reset")}
          </Button>
        </div>
      </form>

      {pass ? (
        <PassView
          pass={pass}
          onClose={() => wf.setPass(null)}
          buttons={[
            editable ? (
              <Link
                key="edit"
                href={`/gate-pass/gp-create?edit=${encodeURIComponent(pass.gp_no)}`}
                className={buttonVariants({ size: "sm", variant: "outline" })}
              >
                <Pencil className="size-4" aria-hidden />
                {g("btn.edit")}
              </Link>
            ) : null,
            editable ? (
              <Button
                key="submit"
                size="sm"
                loading={wf.pending}
                onClick={() => wf.run("submit", undefined, "submitted")}
              >
                <Send className="size-4" aria-hidden />
                {g("btn.submit")}
              </Button>
            ) : null,
            cancellable ? (
              <Button
                key="cancel"
                size="sm"
                variant="outline"
                onClick={() => wf.ask("cancel")}
              >
                <Ban className="size-4" aria-hidden />
                {g("btn.cancel_pass")}
              </Button>
            ) : null,
          ]}
        />
      ) : null}

      <PassTable
        title={l("title", { count: lists.all.length })}
        hint={l("hint")}
        rows={lists.all}
        cols={[...LIST_COLS, "return"]}
        empty={l("empty")}
        onOpen={wf.open}
        openNo={pass?.gp_no}
        action={
          <div className="flex gap-2">
            <a
              href={excel}
              className={buttonVariants({ variant: "outline", size: "sm" })}
            >
              <Download className="size-4" aria-hidden />
              {g("btn.excel")}
            </a>
            {perms.create ? (
              <Link
                href="/gate-pass/gp-create"
                className={buttonVariants({ size: "sm" })}
              >
                <Plus className="size-4" aria-hidden />
                {g("btn.new")}
              </Link>
            ) : null}
          </div>
        }
      />
      {wf.dialogs}
    </div>
  );
}

export { GpApproval, GateOut, GateIn, Reconciliation, GatePassList };
