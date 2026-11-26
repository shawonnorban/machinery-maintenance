"use client";

import { useState, useTransition } from "react";
import { useRouter } from "next/navigation";
import { Download, Plus, RotateCcw, Save } from "lucide-react";
import { Input } from "@/components/ui/input";
import { Button } from "@/components/ui/button";
import { Badge } from "@/components/ui/badge";
import { Alert } from "@/components/ui/alert";
import { Modal } from "@/components/ui/modal";
import { useToastManager } from "@/components/ui/toast";
import { useT } from "@/lib/i18n";
import { MEASURE_UNITS, UomSelect } from "@/components/erp/uom-select";

const asRows = (rows) => rows.map((r) => ({ ...r, value: String(r.value), tolerance: r.tolerance === null ? "" : String(r.tolerance), uom: r.uom ?? "" }));
const blank = () => ({ size: "", point: "", value: "", tolerance: "", uom: "" });
const num = (v) => (v === "" ? null : Number(v));

/**
 * Collar & Cuff Measurement Sheet → Add Measurement (AnnotechERP 06/10): one
 * chart's points of measure per size. Add measurement keeps the point at
 * once; the value, tolerance and unit are changed in place, Remove drops a
 * point, Save keeps them all and Revert goes back. A point changed but not
 * yet saved is marked Edited.
 *
 * @param {{ chartId: string, data: { chart: object, rows: object[] }, add: Function, save: Function, canManage: boolean }} props
 */
function MeasurePoints({ chartId, data, add, save, canManage }) {
  const t = useT("merchandise");
  const m = (k, p) => t(`master.ccm.${k}`, p);
  const router = useRouter();
  const toastManager = useToastManager();
  const [base, setBase] = useState(data.rows);
  const [rows, setRows] = useState(() => asRows(data.rows));
  const [adding, setAdding] = useState(false);
  const [draft, setDraft] = useState(blank);
  const [draftErrors, setDraftErrors] = useState(null);
  const [errors, setErrors] = useState(null);
  const [pending, startTransition] = useTransition();
  const set = (id, patch) => setRows((all) => all.map((r) => (r.id === id ? { ...r, ...patch } : r)));
  const edited = (r) => {
    const was = base.find((b) => b.id === r.id);
    return was && (String(was.value) !== r.value || (was.tolerance === null ? "" : String(was.tolerance)) !== r.tolerance || (was.uom ?? "") !== r.uom);
  };
  const kept = (result) => {
    setBase(result.data.rows);
    setRows(asRows(result.data.rows));
    router.refresh();
  };
  const problems = (result) => (result?.errors ? [...new Set(Object.values(result.errors).flat())] : [result?.message]);

  function addPoint() {
    startTransition(async () => {
      const result = await add(chartId, { size: draft.size, point: draft.point, value: num(draft.value), tolerance: num(draft.tolerance), uom: draft.uom || null });
      if (result?.status === "success") {
        kept(result);
        toastManager.add({ title: m("added", { what: result.data.added }), type: "success" });
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
      const result = await save(chartId, { rows: rows.map((r) => ({ id: r.id, value: num(r.value), tolerance: num(r.tolerance), uom: r.uom || null })) });
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
    const keys = ["size", "point", "value", "tolerance", "uom"];
    const lines = [keys.map((k) => quote(m(`col.${k}`))).join(","), ...rows.map((r) => keys.map((k) => quote(r[k])).join(","))];
    const link = document.createElement("a");
    link.href = URL.createObjectURL(new Blob([`﻿${lines.join("\r\n")}`], { type: "text/csv;charset=utf-8" }));
    link.download = `measurement-${data.chart.label.replace(/\s+/g, "")}.csv`;
    link.click();
    URL.revokeObjectURL(link.href);
  }

  const th = "px-4 py-2.5 text-left text-[13px] font-semibold whitespace-nowrap";
  const label = "flex flex-col gap-1.5 text-[13px] font-semibold text-foreground";
  const star = <span className="text-danger">*</span>;
  return (
    <div className="flex flex-col">
      <div className="flex flex-wrap justify-end gap-2 border-b border-border px-5 py-3">
        <Button variant="outline" size="sm" onClick={exportCsv} disabled={rows.length === 0}>
          <Download /> {m("export")}
        </Button>
        {canManage ? (
          <Button variant="outline" size="sm" onClick={() => setAdding(true)}>
            <Plus /> {m("add_point")}
          </Button>
        ) : null}
      </div>
      {errors ? (
        <Alert variant="danger" className="m-4 mb-0">
          {errors.join(" · ")}
        </Alert>
      ) : null}
      {rows.length === 0 ? (
        <div className="flex flex-col items-center gap-1 px-6 py-14 text-center">
          <p className="font-semibold text-foreground">{m("no_points", { chart: data.chart.label })}</p>
          <p className="text-sm text-foreground-muted">{m("no_points_hint")}</p>
        </div>
      ) : (
        <div className="overflow-x-auto">
          <table className="w-full text-sm">
            <thead>
              <tr className="bg-brand text-brand-foreground">
                {["size", "point", "value", "tolerance", "uom", "status", "action"].map((k) => (
                  <th key={k} className={th}>
                    {m(`col.${k}`)}
                  </th>
                ))}
              </tr>
            </thead>
            <tbody>
              {rows.map((r) => (
                <tr key={r.id} className="border-b border-border align-middle last:border-0">
                  <td className="px-4 py-2 font-medium text-foreground">{r.size}</td>
                  <td className="px-4 py-2">{r.point}</td>
                  <td className="px-4 py-2">
                    <Input type="number" min="0" step="any" value={r.value} onChange={(e) => set(r.id, { value: e.target.value })} className="w-24" disabled={!canManage} aria-label={`${m("col.value")} ${r.size} ${r.point}`} />
                  </td>
                  <td className="px-4 py-2">
                    <Input type="number" min="0" step="any" value={r.tolerance} onChange={(e) => set(r.id, { tolerance: e.target.value })} className="w-24" disabled={!canManage} aria-label={`${m("col.tolerance")} ${r.size} ${r.point}`} />
                  </td>
                  <td className="px-4 py-2">
                    <UomSelect value={r.uom} onChange={(v) => set(r.id, { uom: v })} units={MEASURE_UNITS} compact className="w-32" disabled={!canManage} aria-label={`${m("col.uom")} ${r.size} ${r.point}`} />
                  </td>
                  <td className="px-4 py-2">{edited(r) ? <Badge variant="warning">{m("edited")}</Badge> : null}</td>
                  <td className="px-4 py-2">
                    {canManage ? (
                      <Button variant="outline" size="sm" className="text-danger" onClick={() => setRows((all) => all.filter((x) => x.id !== r.id))}>
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
        <p className="text-[13px] text-foreground-muted">{m("points_count", { n: rows.length })}</p>
        {canManage ? (
          <div className="flex gap-2">
            <Button
              variant="outline"
              onClick={() => {
                setRows(asRows(base));
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
        title={m("add_title", { chart: data.chart.label })}
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
            <Button onClick={addPoint} loading={pending}>
              <Plus /> {m("add_point")}
            </Button>
          </>
        }
      >
        <div className="grid gap-4 sm:grid-cols-2">
          <label className={label}>
            <span>
              {m("col.size")}
              {star}
            </span>
            <Input value={draft.size} onChange={(e) => setDraft((d) => ({ ...d, size: e.target.value }))} maxLength={32} />
          </label>
          <label className={label}>
            <span>
              {m("col.point")}
              {star}
            </span>
            <Input value={draft.point} onChange={(e) => setDraft((d) => ({ ...d, point: e.target.value }))} placeholder={m("point_ph")} maxLength={160} />
          </label>
          <label className={label}>
            <span>
              {m("col.value")}
              {star}
            </span>
            <Input type="number" min="0" step="any" value={draft.value} onChange={(e) => setDraft((d) => ({ ...d, value: e.target.value }))} />
          </label>
          <label className={label}>
            <span>{m("col.tolerance")}</span>
            <Input type="number" min="0" step="any" value={draft.tolerance} onChange={(e) => setDraft((d) => ({ ...d, tolerance: e.target.value }))} />
          </label>
          <label className={label}>
            <span>{m("col.uom")}</span>
            <UomSelect value={draft.uom} onChange={(v) => setDraft((d) => ({ ...d, uom: v }))} units={MEASURE_UNITS} />
          </label>
        </div>
        {draftErrors ? <p className="mt-3 text-sm text-danger">{draftErrors.join(" · ")}</p> : null}
      </Modal>
    </div>
  );
}

export { MeasurePoints };
