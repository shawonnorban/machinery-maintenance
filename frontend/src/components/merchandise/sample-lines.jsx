"use client";

import { useState, useTransition } from "react";
import { useRouter } from "next/navigation";
import { Download, Plus, RotateCcw, Save } from "lucide-react";
import { Input } from "@/components/ui/input";
import { Select } from "@/components/ui/select";
import { Textarea } from "@/components/ui/textarea";
import { Button } from "@/components/ui/button";
import { Badge } from "@/components/ui/badge";
import { Alert } from "@/components/ui/alert";
import { Modal } from "@/components/ui/modal";
import { useToastManager } from "@/components/ui/toast";
import { useT } from "@/lib/i18n";

const asLines = (rows) => rows.map((r) => ({ ...r, item: r.item ?? "", colour: r.colour ?? "", size: r.size ?? "", req_qty: String(r.req_qty), unit: r.unit ?? "", remarks: r.remarks ?? "" }));
const blank = () => ({ sample_type_id: "", item: "", colour: "", size: "", req_qty: "", unit: "", remarks: "" });

/**
 * Sample Programme → Sample Lines (AnnotechERP 06/10): what one programme
 * asks the sample room for. Add line keeps the new line at once (Brickland's
 * "Planned 3 × Proto Sample"); item, colour, size, quantity, unit and
 * remarks are changed in place, Remove drops a line, and Save keeps them
 * all — Revert goes back to what was saved. A line already in a production
 * batch is shown but no longer edited: the batch owns its quantities.
 *
 * @param {{ programId: string, data: { program: object, rows: object[], types: object[] }, add: Function, save: Function, canManage: boolean }} props
 */
function SampleLines({ programId, data, add, save, canManage }) {
  const t = useT("merchandise");
  const m = (k, p) => t(`master.smp.${k}`, p);
  const router = useRouter();
  const toastManager = useToastManager();
  const [base, setBase] = useState(data.rows);
  const [lines, setLines] = useState(() => asLines(data.rows));
  const [adding, setAdding] = useState(false);
  const [draft, setDraft] = useState(blank);
  const [draftErrors, setDraftErrors] = useState(null);
  const [errors, setErrors] = useState(null);
  const [pending, startTransition] = useTransition();
  const set = (id, patch) => setLines((all) => all.map((l) => (l.id === id ? { ...l, ...patch } : l)));
  const kept = (result) => {
    setBase(result.data.rows);
    setLines(asLines(result.data.rows));
    router.refresh();
  };
  const problems = (result) => (result?.errors ? [...new Set(Object.values(result.errors).flat())] : [result?.message]);

  function addLine() {
    startTransition(async () => {
      const result = await add(programId, { ...draft, req_qty: draft.req_qty === "" ? null : Number(draft.req_qty), sample_type_id: draft.sample_type_id || null });
      if (result?.status === "success") {
        kept(result);
        toastManager.add({ title: m("planned", { qty: result.data.added.qty, type: result.data.added.type }), type: "success" });
        setDraft(blank());
        setDraftErrors(null);
        setAdding(false);
      } else {
        setDraftErrors(problems(result));
      }
    });
  }

  function submit() {
    startTransition(async () => {
      const result = await save(programId, {
        rows: lines.map((l) => ({ id: l.id, item: l.item || null, colour: l.colour || null, size: l.size || null, req_qty: l.req_qty === "" ? null : Number(l.req_qty), unit: l.unit || null, remarks: l.remarks || null })),
      });
      if (result?.status === "success") {
        kept(result);
        setErrors(null);
        toastManager.add({ title: m("saved"), type: "success" });
      } else {
        setErrors(problems(result));
      }
    });
  }

  function exportCsv() {
    const quote = (v) => `"${String(v ?? "").replace(/"/g, '""')}"`;
    const keys = ["type", "job", "item", "colour", "size", "req_qty", "unit", "batch", "remarks", "status"];
    const rows = [keys.map((k) => quote(m(`f.${k}`))).join(","), ...lines.map((l) => [l.type, data.program.job, l.item, l.colour, l.size, l.req_qty, l.unit, l.batch_no, l.remarks, m(`line_status.${l.status}`)].map(quote).join(","))];
    const link = document.createElement("a");
    link.href = URL.createObjectURL(new Blob([`﻿${rows.join("\r\n")}`], { type: "text/csv;charset=utf-8" }));
    link.download = `sample-lines-${data.program.program_no}.csv`;
    link.click();
    URL.revokeObjectURL(link.href);
  }

  const th = "px-4 py-2.5 text-left text-[13px] font-semibold whitespace-nowrap";
  const label = "flex flex-col gap-1.5 text-[13px] font-semibold text-foreground";
  const cell = (l, key, cls, props = {}) => <Input value={l[key]} onChange={(e) => set(l.id, { [key]: e.target.value })} className={cls} disabled={!canManage || l.locked} aria-label={`${m(`f.${key}`)} ${l.type}`} {...props} />;
  return (
    <div className="flex flex-col">
      <div className="flex flex-wrap justify-end gap-2 border-b border-border px-5 py-3">
        <Button variant="outline" size="sm" onClick={exportCsv} disabled={lines.length === 0}>
          <Download /> {m("export")}
        </Button>
        {canManage ? (
          <Button variant="outline" size="sm" onClick={() => setAdding(true)}>
            <Plus /> {m("add_line")}
          </Button>
        ) : null}
      </div>
      {errors ? (
        <Alert variant="danger" className="m-4 mb-0">
          {errors.join(" · ")}
        </Alert>
      ) : null}
      {lines.length === 0 ? (
        <div className="flex flex-col items-center gap-1 px-6 py-14 text-center">
          <p className="font-semibold text-foreground">{m("no_lines", { no: data.program.program_no })}</p>
          <p className="text-sm text-foreground-muted">{m("no_lines_hint")}</p>
        </div>
      ) : (
        <div className="overflow-x-auto">
          <table className="w-full text-sm">
            <thead>
              <tr className="bg-brand text-brand-foreground">
                {["type", "job", "item", "colour", "size", "req_qty", "unit", "batch", "remarks", "status", "action"].map((k) => (
                  <th key={k} className={th}>
                    {m(`f.${k}`)}
                  </th>
                ))}
              </tr>
            </thead>
            <tbody>
              {lines.map((l) => (
                <tr key={l.id} className="border-b border-border align-middle last:border-0">
                  <td className="px-4 py-2 font-medium whitespace-nowrap text-foreground">{l.type}</td>
                  <td className="px-4 py-2 whitespace-nowrap">{data.program.job ?? "—"}</td>
                  <td className="px-4 py-2">{cell(l, "item", "w-32")}</td>
                  <td className="px-4 py-2">{cell(l, "colour", "w-28")}</td>
                  <td className="px-4 py-2">{cell(l, "size", "w-20")}</td>
                  <td className="px-4 py-2">{cell(l, "req_qty", "w-24", { type: "number", min: "0", step: "any" })}</td>
                  <td className="px-4 py-2">{cell(l, "unit", "w-20")}</td>
                  <td className="px-4 py-2 font-mono text-[13px]">{l.batch_no ?? "—"}</td>
                  <td className="px-4 py-2">{cell(l, "remarks", "w-48")}</td>
                  <td className="px-4 py-2">
                    <Badge variant={l.locked ? "info" : "neutral"}>{m(`line_status.${l.status}`)}</Badge>
                  </td>
                  <td className="px-4 py-2">
                    {canManage ? (
                      <Button variant="outline" size="sm" className="text-danger" disabled={l.locked} title={l.locked ? m("in_batch_keep") : undefined} onClick={() => setLines((all) => all.filter((x) => x.id !== l.id))}>
                        {m("remove")}
                      </Button>
                    ) : null}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
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
          if (!o) setDraftErrors(null);
        }}
        title={m("add_title", { no: data.program.program_no })}
        description={m("in_batch_note")}
        footer={
          <>
            <Button
              variant="outline"
              onClick={() => {
                setDraft(blank());
                setDraftErrors(null);
              }}
              disabled={pending}
            >
              <RotateCcw /> {m("reset")}
            </Button>
            <Button onClick={addLine} loading={pending}>
              <Plus /> {m("add_line")}
            </Button>
          </>
        }
      >
        <div className="grid gap-4 sm:grid-cols-2">
          <label className={label}>
            <span>
              {m("f.type")}
              <span className="text-danger">*</span>
            </span>
            <Select value={draft.sample_type_id} onValueChange={(v) => setDraft((d) => ({ ...d, sample_type_id: v ?? "" }))} options={[{ value: "", label: m("select") }, ...data.types]} placeholder={m("select")} />
          </label>
          {["item", "colour", "size"].map((k) => (
            <label key={k} className={label}>
              <span>{m(`f.${k}`)}</span>
              <Input value={draft[k]} onChange={(e) => setDraft((d) => ({ ...d, [k]: e.target.value }))} maxLength={k === "size" ? 32 : 96} />
            </label>
          ))}
          <label className={label}>
            <span>
              {m("f.req_qty")}
              <span className="text-danger">*</span>
            </span>
            <Input type="number" min="0" step="any" value={draft.req_qty} onChange={(e) => setDraft((d) => ({ ...d, req_qty: e.target.value }))} />
          </label>
          <label className={label}>
            <span>{m("f.unit")}</span>
            <Input value={draft.unit} onChange={(e) => setDraft((d) => ({ ...d, unit: e.target.value }))} placeholder="Pcs" maxLength={16} />
          </label>
          <label className={`${label} sm:col-span-2`}>
            <span>{m("f.remarks")}</span>
            <Textarea value={draft.remarks} onChange={(e) => setDraft((d) => ({ ...d, remarks: e.target.value }))} rows={2} />
          </label>
        </div>
        {draftErrors ? <p className="mt-3 text-sm text-danger">{draftErrors.join(" · ")}</p> : null}
      </Modal>
    </div>
  );
}

export { SampleLines };
