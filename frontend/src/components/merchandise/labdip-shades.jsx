"use client";

import { useState, useTransition } from "react";
import { useRouter } from "next/navigation";
import { Download, Plus, RotateCcw, Save } from "lucide-react";
import { Input } from "@/components/ui/input";
import { Select } from "@/components/ui/select";
import { Button } from "@/components/ui/button";
import { Badge } from "@/components/ui/badge";
import { Alert } from "@/components/ui/alert";
import { Modal } from "@/components/ui/modal";
import { useToastManager } from "@/components/ui/toast";
import { useT } from "@/lib/i18n";

let seq = 0;
const asLines = (rows) =>
  rows.map((r) => ({
    key: r.id,
    id: r.id,
    saved: true,
    colour: r.colour,
    pantone: r.pantone ?? "",
    style_id: r.style_id ?? "",
    style_no: r.style_no,
    order_id: r.order_id ?? "",
    order_no: r.order_no,
    fabric: r.fabric ?? "",
    light_source: r.light_source ?? "",
    target_date: r.target_date ?? "",
    status: r.status ?? "PENDING",
    submitted_on: r.submitted_on ?? "",
    decided_on: r.decided_on ?? null,
    feedback: r.feedback ?? "",
  }));
const blank = () => ({ colour: "", pantone: "", style_id: "", order_id: "", fabric: "", light_source: "", target_date: "", status: "PENDING", submitted_on: "", decided_on: null, feedback: "" });
const TONE = { PENDING: "neutral", SUBMITTED: "info", APPROVED: "success", REJECTED: "danger", RESUBMIT: "warning" };

/**
 * Lab Dip Programme → Labdip Specification (AnnotechERP 06/10): the shades on
 * one program. Its light source and target date are changed in place, Remove
 * drops a shade, Add shade opens a small form (colour, Pantone, style, order,
 * fabric, light source, target date); Save keeps them all, Revert goes back
 * to what was saved. A shade not yet saved is marked New.
 *
 * On Lab Dip Submission (`submission`) each shade also shows its way to the
 * buyer: the day it was submitted, the buyer's verdict (with the day it was
 * recorded) and what the buyer said — saved with the shades. A shade that
 * has gone to the buyer cannot be removed.
 *
 * @param {{ programId: string, data: { program: object, rows: object[], styles: object[], orders: object[], light_sources: string[], statuses: string[] }, save: Function, canManage: boolean, submission?: boolean }} props
 */
function LabdipShades({ programId, data, save, canManage, submission = false }) {
  const t = useT("merchandise");
  const m = (k, p) => t(`master.ldp.${k}`, p);
  const router = useRouter();
  const toastManager = useToastManager();
  const [base, setBase] = useState(data.rows);
  const [lines, setLines] = useState(() => asLines(data.rows));
  const [adding, setAdding] = useState(false);
  const [draft, setDraft] = useState(blank);
  const [draftError, setDraftError] = useState(null);
  const [errors, setErrors] = useState(null);
  const [pending, startTransition] = useTransition();
  const set = (key, patch) => setLines((all) => all.map((l) => (l.key === key ? { ...l, ...patch } : l)));
  const lights = [{ value: "", label: m("select") }, ...data.light_sources.map((v) => ({ value: v, label: v }))];
  const statuses = (data.statuses ?? []).map((v) => ({ value: v, label: m(`verdict.${v}`) }));
  // Today in the viewer's own zone (the server refuses a submission dated later).
  const [today] = useState(() => {
    const now = new Date();
    return new Date(now.getTime() - now.getTimezoneOffset() * 60000).toISOString().slice(0, 10);
  });
  // A saved shade that has gone to the buyer stays on the program.
  const savedStatus = (l) => base.find((b) => b.id === l.id)?.status ?? "PENDING";
  const sent = (l) => l.saved && savedStatus(l) !== "PENDING";
  const draftOrders = data.orders.filter((o) => !draft.style_id || o.style_id === draft.style_id);

  function addLine() {
    if (!draft.colour.trim()) {
      setDraftError(m("need"));
      return;
    }
    const style = data.styles.find((s) => s.value === draft.style_id);
    const order = data.orders.find((o) => o.value === draft.order_id);
    setLines((all) => [...all, { key: `n${++seq}`, id: null, saved: false, ...draft, colour: draft.colour.trim(), style_no: style?.label.split(" · ")[0] ?? null, order_no: order?.label.split(" · ")[0] ?? null }]);
    setDraft(blank());
    setDraftError(null);
    setAdding(false);
  }

  function submit() {
    startTransition(async () => {
      const result = await save(programId, {
        rows: lines.map((l) => ({
          id: l.id,
          colour: l.colour,
          pantone: l.pantone || null,
          style_id: l.style_id || null,
          order_id: l.order_id || null,
          fabric: l.fabric || null,
          light_source: l.light_source || null,
          target_date: l.target_date || null,
          // Only Lab Dip Submission speaks for the submission; the programme's own panel leaves it as it is.
          ...(submission ? { status: l.status, submitted_on: l.submitted_on || null, feedback: l.feedback || null } : {}),
        })),
      });
      if (result?.status === "success") {
        setBase(result.data.rows);
        setLines(asLines(result.data.rows));
        setErrors(null);
        toastManager.add({ title: m("saved"), type: "success" });
        router.refresh();
      } else {
        setErrors(result?.errors ? [...new Set(Object.values(result.errors).flat())] : [result?.message]);
      }
    });
  }

  function exportCsv() {
    const quote = (v) => `"${String(v ?? "").replace(/"/g, '""')}"`;
    const keys = ["colour", "pantone", "style", "order", "fabric", "light_source", "target_date"];
    const rows = [keys.map((k) => quote(m(`f.${k}`))).join(","), ...lines.map((l) => [l.colour, l.pantone, l.style_no, l.order_no, l.fabric, l.light_source, l.target_date].map(quote).join(","))];
    const link = document.createElement("a");
    link.href = URL.createObjectURL(new Blob([`﻿${rows.join("\r\n")}`], { type: "text/csv;charset=utf-8" }));
    link.download = `labdip-${data.program.program_no}.csv`;
    link.click();
    URL.revokeObjectURL(link.href);
  }

  const th = "px-4 py-2.5 text-left text-[13px] font-semibold whitespace-nowrap";
  const label = "flex flex-col gap-1.5 text-[13px] font-semibold text-foreground";
  return (
    <div className="flex flex-col">
      <div className="flex flex-wrap justify-end gap-2 border-b border-border px-5 py-3">
        <Button variant="outline" size="sm" onClick={exportCsv} disabled={lines.length === 0}>
          <Download /> {m("export")}
        </Button>
        {canManage ? (
          <Button variant="outline" size="sm" onClick={() => setAdding(true)}>
            <Plus /> {m("add_shade")}
          </Button>
        ) : null}
      </div>
      {errors ? (
        <Alert variant="danger" className="m-4 mb-0">
          {errors.join(" · ")}
        </Alert>
      ) : null}
      <div className="overflow-x-auto">
        <table className="w-full text-sm">
          <thead>
            <tr className="bg-brand text-brand-foreground">
              {["colour", "pantone", "style", "order", "fabric", "light_source", "target_date", ...(submission ? ["submitted_on", "verdict", "feedback"] : []), "status", "action"].map((k) => (
                <th key={k} className={th}>
                  {m(`f.${k}`)}
                </th>
              ))}
            </tr>
          </thead>
          <tbody>
            {lines.length === 0 ? (
              <tr>
                <td colSpan={submission ? 12 : 9} className="px-4 py-12 text-center text-foreground-muted">
                  {m("no_shades")}
                </td>
              </tr>
            ) : (
              lines.map((l) => (
                <tr key={l.key} className="border-b border-border align-middle last:border-0">
                  <td className="px-4 py-2 font-medium text-foreground">{l.colour}</td>
                  <td className="px-4 py-2">{l.pantone || "—"}</td>
                  <td className="px-4 py-2">{l.style_no ?? "—"}</td>
                  <td className="px-4 py-2">{l.order_no ?? "—"}</td>
                  <td className="px-4 py-2">{l.fabric || "—"}</td>
                  <td className="px-4 py-2">
                    <Select value={l.light_source} onValueChange={(v) => set(l.key, { light_source: v ?? "" })} options={lights} placeholder={m("select")} className="w-28" disabled={!canManage} aria-label={`${m("f.light_source")} ${l.colour}`} />
                  </td>
                  <td className="px-4 py-2">
                    <Input type="date" value={l.target_date} onChange={(e) => set(l.key, { target_date: e.target.value })} className="w-40" disabled={!canManage} aria-label={`${m("f.target_date")} ${l.colour}`} />
                  </td>
                  {submission ? (
                    <>
                      <td className="px-4 py-2">
                        <Input type="date" value={l.submitted_on} min={data.program.request_date} max={today} onChange={(e) => set(l.key, { submitted_on: e.target.value, status: e.target.value && l.status === "PENDING" ? "SUBMITTED" : l.status })} className="w-40" disabled={!canManage} aria-label={`${m("f.submitted_on")} ${l.colour}`} />
                      </td>
                      <td className="px-4 py-2">
                        {canManage ? (
                          <Select value={l.status} onValueChange={(v) => v && set(l.key, { status: v })} options={statuses} className="w-36" aria-label={`${m("f.verdict")} ${l.colour}`} />
                        ) : (
                          <Badge variant={TONE[l.status]}>{m(`verdict.${l.status}`)}</Badge>
                        )}
                        {l.decided_on && l.status === savedStatus(l) ? <div className="mt-1 text-xs text-foreground-muted">{m("decided_on", { date: l.decided_on })}</div> : null}
                      </td>
                      <td className="px-4 py-2">
                        <Input value={l.feedback} onChange={(e) => set(l.key, { feedback: e.target.value })} className="w-56" disabled={!canManage} maxLength={2000} placeholder={m("feedback_ph")} aria-label={`${m("f.feedback")} ${l.colour}`} />
                      </td>
                    </>
                  ) : null}
                  <td className="px-4 py-2">{l.saved ? null : <Badge variant="info">{m("new_badge")}</Badge>}</td>
                  <td className="px-4 py-2">
                    {canManage ? (
                      <Button variant="outline" size="sm" className="text-danger" disabled={sent(l)} title={sent(l) ? m("sent_keep") : undefined} onClick={() => setLines((all) => all.filter((x) => x.key !== l.key))}>
                        {m("remove")}
                      </Button>
                    ) : null}
                  </td>
                </tr>
              ))
            )}
          </tbody>
        </table>
      </div>
      <div className="flex flex-wrap items-center justify-between gap-3 border-t border-border px-5 py-3">
        <p className="text-[13px] text-foreground-muted">{m("lines", { n: lines.length })}</p>
        {canManage ? (
          <div className="flex gap-2">
            <Button
              variant="outline"
              onClick={() => {
                setLines(asLines(base));
                setErrors(null);
              }}
              disabled={pending}
            >
              <RotateCcw /> {m("revert")}
            </Button>
            <Button onClick={submit} loading={pending}>
              <Save /> {m("save")}
            </Button>
          </div>
        ) : null}
      </div>

      <Modal
        open={adding}
        onOpenChange={(o) => {
          setAdding(o);
          if (!o) setDraftError(null);
        }}
        title={m("add_shade")}
        description={data.program.program_no}
        footer={
          <>
            <Button variant="outline" onClick={() => setAdding(false)}>
              {m("cancel")}
            </Button>
            <Button onClick={addLine}>
              <Plus /> {m("add")}
            </Button>
          </>
        }
      >
        <div className="grid gap-4 sm:grid-cols-2">
          <label className={label}>
            <span>
              {m("f.colour")}
              <span className="text-danger">*</span>
            </span>
            <Input value={draft.colour} onChange={(e) => setDraft((d) => ({ ...d, colour: e.target.value }))} maxLength={96} />
          </label>
          <label className={label}>
            <span>{m("f.pantone")}</span>
            <Input value={draft.pantone} onChange={(e) => setDraft((d) => ({ ...d, pantone: e.target.value }))} placeholder="19-4052 TCX" maxLength={48} />
          </label>
          <label className={label}>
            <span>{m("f.style")}</span>
            <Select value={draft.style_id} onValueChange={(v) => setDraft((d) => ({ ...d, style_id: v ?? "", order_id: "" }))} options={[{ value: "", label: m("select") }, ...data.styles]} placeholder={m("select")} />
          </label>
          <label className={label}>
            <span>{m("f.order")}</span>
            <Select value={draft.order_id} onValueChange={(v) => setDraft((d) => ({ ...d, order_id: v ?? "" }))} options={[{ value: "", label: m("select") }, ...draftOrders.map(({ value, label: text }) => ({ value, label: text }))]} placeholder={m("select")} />
          </label>
          <label className={label}>
            <span>{m("f.fabric")}</span>
            <Input value={draft.fabric} onChange={(e) => setDraft((d) => ({ ...d, fabric: e.target.value }))} placeholder={m("fabric_ph")} maxLength={160} />
          </label>
          <label className={label}>
            <span>{m("f.light_source")}</span>
            <Select value={draft.light_source} onValueChange={(v) => setDraft((d) => ({ ...d, light_source: v ?? "" }))} options={lights} placeholder={m("select")} />
          </label>
          <label className={label}>
            <span>{m("f.target_date")}</span>
            <Input type="date" value={draft.target_date} onChange={(e) => setDraft((d) => ({ ...d, target_date: e.target.value }))} />
          </label>
        </div>
        {draftError ? <p className="mt-3 text-sm text-danger">{draftError}</p> : null}
      </Modal>
    </div>
  );
}

export { LabdipShades };
