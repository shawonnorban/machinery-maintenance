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
import { UomSelect } from "@/components/erp/uom-select";

let seq = 0;
const asLines = (rows) => rows.map((r) => ({ key: r.id, saved: true, size: r.size, garment_part_id: r.garment_part_id ?? "", part: r.part, consumption: String(r.consumption), uom: r.uom ?? "" }));
const blank = () => ({ size: "", garment_part_id: "", consumption: "", uom: "" });

/**
 * CAD Consumption Register → Consumption (AnnotechERP 06/09): one CAD
 * record's marker consumption per size and garment part. A consumption or
 * unit is changed in place, Remove drops a line, Add size opens a small form;
 * Save keeps them all, Revert goes back to what was saved. A line not yet
 * saved is marked New.
 *
 * @param {{ recordId: string, data: { record: object, rows: object[], parts: object[] }, save: Function, canManage: boolean }} props
 */
function CadSizes({ recordId, data, save, canManage }) {
  const t = useT("merchandise");
  const m = (k, p) => t(`master.cad.${k}`, p);
  const router = useRouter();
  const toastManager = useToastManager();
  const [base, setBase] = useState(data.rows);
  const [lines, setLines] = useState(() => asLines(data.rows));
  const [adding, setAdding] = useState(false);
  const [draft, setDraft] = useState(blank);
  const [draftError, setDraftError] = useState(null);
  const [errors, setErrors] = useState(null);
  const [pending, startTransition] = useTransition();
  const partName = (id) => data.parts.find((p) => p.value === id)?.label ?? null;
  const set = (key, patch) => setLines((all) => all.map((l) => (l.key === key ? { ...l, ...patch } : l)));

  function addLine() {
    if (!draft.size.trim() || draft.consumption === "") {
      setDraftError(m("need"));
      return;
    }
    setLines((all) => [...all, { key: `c${++seq}`, saved: false, size: draft.size.trim(), garment_part_id: draft.garment_part_id, part: partName(draft.garment_part_id), consumption: draft.consumption, uom: draft.uom.trim() }]);
    setDraft(blank());
    setDraftError(null);
    setAdding(false);
  }

  function submit() {
    startTransition(async () => {
      const result = await save(recordId, { rows: lines.map((l) => ({ size: l.size, garment_part_id: l.garment_part_id || null, consumption: l.consumption === "" ? null : Number(l.consumption), uom: l.uom || null })) });
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
    const rows = [["size", "part", "consumption", "uom"].map((k) => quote(m(`f.${k}`))).join(","), ...lines.map((l) => [l.size, l.part, l.consumption, l.uom].map(quote).join(","))];
    const link = document.createElement("a");
    link.href = URL.createObjectURL(new Blob([`﻿${rows.join("\r\n")}`], { type: "text/csv;charset=utf-8" }));
    link.download = `cad-consumption-${data.record.job_no}.csv`;
    link.click();
    URL.revokeObjectURL(link.href);
  }

  const th = "px-4 py-2.5 text-left text-[13px] font-semibold whitespace-nowrap";
  return (
    <div className="flex flex-col">
      <div className="flex flex-wrap justify-end gap-2 border-b border-border px-5 py-3">
        <Button variant="outline" size="sm" onClick={exportCsv} disabled={lines.length === 0}>
          <Download /> {m("export")}
        </Button>
        {canManage ? (
          <Button variant="outline" size="sm" onClick={() => setAdding(true)}>
            <Plus /> {m("add_size")}
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
              {["size", "part", "consumption", "uom", "status"].map((k) => (
                <th key={k} className={th}>
                  {m(`f.${k}`)}
                </th>
              ))}
              <th className={th}>{m("f.action")}</th>
            </tr>
          </thead>
          <tbody>
            {lines.length === 0 ? (
              <tr>
                <td colSpan={6} className="px-4 py-12 text-center text-foreground-muted">
                  {m("no_sizes")}
                </td>
              </tr>
            ) : (
              lines.map((l) => (
                <tr key={l.key} className="border-b border-border align-middle last:border-0">
                  <td className="px-4 py-2 font-medium text-foreground">{l.size}</td>
                  <td className="px-4 py-2">{l.part ?? "—"}</td>
                  <td className="px-4 py-2">
                    <Input type="number" min="0" step="0.0001" value={l.consumption} onChange={(e) => set(l.key, { consumption: e.target.value })} className="w-32" disabled={!canManage} aria-label={`${m("f.consumption")} ${l.size}`} />
                  </td>
                  <td className="px-4 py-2">
                    <UomSelect value={l.uom} onChange={(v) => set(l.key, { uom: v })} compact className="w-32" disabled={!canManage} aria-label={`${m("f.uom")} ${l.size}`} />
                  </td>
                  <td className="px-4 py-2">{l.saved ? null : <Badge variant="info">{m("new_badge")}</Badge>}</td>
                  <td className="px-4 py-2">
                    {canManage ? (
                      <Button variant="outline" size="sm" className="text-danger" onClick={() => setLines((all) => all.filter((x) => x.key !== l.key))}>
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
            <Button variant="outline" onClick={() => { setLines(asLines(base)); setErrors(null); }} disabled={pending}>
              <RotateCcw /> {m("revert")}
            </Button>
            <Button onClick={submit} loading={pending}>
              <Save /> {m("save")}
            </Button>
          </div>
        ) : null}
      </div>

      <Modal open={adding} onOpenChange={(o) => { setAdding(o); if (!o) setDraftError(null); }} title={m("add_size_of", { job: data.record.job_no })} className="max-w-xl">
        <div className="flex flex-col gap-4">
          <div className="rounded-lg border border-border p-4">
            <h3 className="text-sm font-semibold text-foreground">{m("new_size")}</h3>
            <div className="mt-3 grid gap-3 sm:grid-cols-2">
              <label className="flex flex-col gap-1.5 text-[13px] font-semibold text-foreground">
                <span>
                  {m("f.size")}
                  <span className="text-danger">*</span>
                </span>
                <Input value={draft.size} onChange={(e) => setDraft((d) => ({ ...d, size: e.target.value }))} />
              </label>
              <label className="flex flex-col gap-1.5 text-[13px] font-semibold text-foreground">
                {m("f.part")}
                <Select value={draft.garment_part_id} onValueChange={(v) => setDraft((d) => ({ ...d, garment_part_id: v ?? "" }))} options={[{ value: "", label: t("master.select") }, ...data.parts]} placeholder={t("master.select")} />
              </label>
              <label className="flex flex-col gap-1.5 text-[13px] font-semibold text-foreground">
                <span>
                  {m("f.consumption")}
                  <span className="text-danger">*</span>
                </span>
                <Input type="number" min="0" step="0.0001" value={draft.consumption} onChange={(e) => setDraft((d) => ({ ...d, consumption: e.target.value }))} />
              </label>
              <label className="flex flex-col gap-1.5 text-[13px] font-semibold text-foreground">
                {m("f.uom")}
                <UomSelect value={draft.uom} onChange={(v) => setDraft((d) => ({ ...d, uom: v }))} />
              </label>
            </div>
            {draftError ? <p className="mt-2 text-xs text-danger">{draftError}</p> : null}
          </div>
          <div className="flex gap-2">
            <Button variant="outline" onClick={() => { setDraft(blank()); setDraftError(null); }}>
              <RotateCcw /> {m("reset")}
            </Button>
            <Button onClick={addLine}>
              <Plus /> {m("add_size")}
            </Button>
          </div>
        </div>
      </Modal>
    </div>
  );
}

export { CadSizes };
