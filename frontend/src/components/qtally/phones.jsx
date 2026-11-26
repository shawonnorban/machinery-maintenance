"use client";

import { useState } from "react";
import { Ban, Check, Pencil, RotateCcw, Smartphone, Trash2 } from "lucide-react";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Modal } from "@/components/ui/modal";
import { ConfirmDialog } from "@/components/ui/confirm-dialog";
import { SECTIONS, useQt, useRun, num, stamp, Panel, Table, Field, Select, Pill, SectionPill, Notice } from "./common";

/*
 * Quality Tally → Planning → Phones (AnnotechERP 37): the phones the app runs
 * on. A QC's first sign-in on a new phone puts it here to approve; its code is
 * on the phone's own screen.
 */

const TONE = { PENDING: "bg-warning-subtle text-warning", APPROVED: "bg-success-subtle text-success", PERSONAL: "bg-info-subtle text-info", BLOCKED: "bg-danger-subtle text-danger" };
const called = (r) => r.name ?? r.model ?? r.code;

function QtPhones({ rows, lookups, act }) {
  const p = useQt();
  const [editing, setEditing] = useState(null);
  const [asking, setAsking] = useState(null);
  const { run, pending } = useRun();
  const waiting = rows.filter((r) => r.status === "PENDING").length;

  const cols = [
    {
      key: "phone",
      label: p("col.phone"),
      render: (r) => (
        <>
          <b>{called(r)}</b>
          <div className="text-xs text-foreground-muted">{[p("phone.code", { code: r.code }), r.name ? r.model : null, r.os].filter(Boolean).join(" · ")}</div>
        </>
      ),
    },
    {
      key: "status",
      label: p("col.status"),
      render: (r) => (
        <>
          <Pill tone={TONE[r.status]}>{p(`phone.${r.status}`)}</Pill>
          {r.status === "PENDING" && r.first_user ? (
            <div className="mt-1 text-xs text-foreground-muted">
              {p("phone.asked_by", { name: r.first_user })}
              <br />
              {stamp(r.requested_at)}
            </div>
          ) : null}
        </>
      ),
    },
    {
      key: "line",
      label: p("col.phone_line"),
      render: (r) =>
        r.line ? (
          <span className="inline-flex items-center gap-1 text-xs">
            <SectionPill section={r.section} /> {r.line}
          </span>
        ) : (
          <span className="text-xs text-foreground-muted">{p("phone.any_line")}</span>
        ),
    },
    {
      key: "signed_in",
      label: p("col.signed_in"),
      render: (r) =>
        r.signed_in ? (
          <>
            {r.signed_in.name}
            <div className="text-xs text-foreground-muted">{p("phone.since", { at: stamp(r.signed_in.since) })}</div>
          </>
        ) : (
          <span className="text-xs text-foreground-muted">{p("phone.nobody")}</span>
        ),
    },
    { key: "waiting_taps", label: p("col.waiting"), align: "right", render: (r) => <span className={r.waiting_taps ? "font-semibold text-warning" : ""}>{num(r.waiting_taps)}</span> },
    {
      key: "last_seen_at",
      label: p("col.last_seen"),
      render: (r) => (
        <>
          {stamp(r.last_seen_at) || "—"}
          <div className="text-xs text-foreground-muted">
            {p("col.app")} {r.app_version ?? "—"}
          </div>
        </>
      ),
    },
    {
      key: "act",
      label: "",
      render: (r) => (
        <div className="flex gap-1.5">
          {r.status === "PENDING" || r.status === "PERSONAL" ? (
            <Button size="sm" variant={r.status === "PENDING" ? "primary" : "outline"} onClick={() => setEditing({ ...r, approve: true })}>
              <Check /> {p("btn.approve")}
            </Button>
          ) : null}
          {r.status === "APPROVED" ? (
            <Button size="sm" variant="outline" onClick={() => setEditing(r)}>
              <Pencil /> {p("btn.edit")}
            </Button>
          ) : null}
          {r.status === "BLOCKED" ? (
            <Button size="sm" variant="outline" disabled={pending} onClick={() => run(() => act("unblockPhone", r.id), p("toast.phone_unblocked"))}>
              <RotateCcw /> {p("btn.unblock")}
            </Button>
          ) : (
            <Button size="sm" variant="outline" onClick={() => setAsking({ kind: "block", row: r })}>
              <Ban /> {p("btn.block")}
            </Button>
          )}
          {r.removable ? (
            <Button size="sm" variant="outline" aria-label={p("btn.delete")} onClick={() => setAsking({ kind: "remove", row: r })}>
              <Trash2 />
            </Button>
          ) : null}
        </div>
      ),
    },
  ];

  const ask = asking?.kind === "block" ? ["blockPhone", "toast.phone_blocked", "btn.block"] : ["deletePhone", "toast.deleted", "btn.delete"];

  return (
    <>
      <Panel title={p("phone.title")} subtitle={[p("phone.count", { n: rows.length }), waiting ? p("phone.waiting", { n: waiting }) : null].filter(Boolean).join(" · ")} icon={Smartphone}>
        <Table columns={cols} rows={rows} empty={p("empty.phones")} />
      </Panel>
      {editing ? <PhoneForm row={editing} lookups={lookups} act={act} onClose={() => setEditing(null)} /> : null}
      <ConfirmDialog
        open={asking !== null}
        onOpenChange={(o) => !o && setAsking(null)}
        title={asking ? p(`phone.${asking.kind}_title`) : ""}
        description={asking ? p(`phone.${asking.kind}_body`, { name: called(asking.row) }) : ""}
        confirmLabel={p(ask[2])}
        cancelLabel={p("btn.close")}
        loading={pending}
        onConfirm={() => run(() => act(ask[0], asking.row.id), p(ask[1]), () => setAsking(null))}
      />
    </>
  );
}

function PhoneForm({ row, lookups, act, onClose }) {
  const p = useQt();
  const { run, pending } = useRun();
  const [errors, setErrors] = useState({});
  const [name, setName] = useState(row.name ?? "");
  const [line, setLine] = useState(row.line_id ? `${row.section}:${row.line_id}` : "");
  const options = SECTIONS.flatMap((s) => (lookups.lines[s] ?? []).map((l) => ({ value: `${s}:${l.value}`, label: `${p(`section.${s}`)} · ${l.label}` })));
  // A line switched off since: kept on the list until another is picked.
  if (line && !options.some((o) => o.value === line)) options.unshift({ value: line, label: row.line ?? "—" });
  const err = (k) => errors[k]?.[0];

  const save = () => {
    const [section, line_id] = line ? line.split(":") : [null, null];
    run(
      () => act("savePhone", { name, section, line_id, ...(row.approve ? { approve: true } : {}) }, row.id),
      p(row.approve ? "toast.phone_approved" : "toast.phone_saved"),
      (res) => (res.status === "success" ? onClose() : setErrors(res.errors ?? {})),
    );
  };

  return (
    <Modal
      open
      onOpenChange={onClose}
      title={row.approve ? p("phone.approve_title", { code: row.code }) : p("phone.edit_title")}
      footer={
        <>
          <Button variant="outline" onClick={onClose}>
            {p("btn.close")}
          </Button>
          <Button loading={pending} disabled={!name.trim()} onClick={save}>
            {p(row.approve ? "btn.approve" : "btn.save")}
          </Button>
        </>
      }
    >
      <div className="flex flex-col gap-4">
        {row.approve ? <Notice tone="info">{p("phone.code_hint", { code: row.code })}</Notice> : null}
        <Field label={p("phone.name")} required error={err("name")} hint={p("phone.name_hint")}>
          <Input value={name} maxLength={60} onChange={(e) => setName(e.target.value)} autoFocus />
        </Field>
        <Field label={p("phone.line")} error={err("line_id") ?? err("section")} hint={p("phone.line_hint")}>
          <Select value={line} onChange={setLine} options={options} placeholder={p("phone.any_line")} />
        </Field>
      </div>
    </Modal>
  );
}

export { QtPhones };
