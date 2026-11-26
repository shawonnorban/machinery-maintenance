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

const asRows = (rows) => rows.map((r) => ({ ...r, description: r.description, qty: String(r.qty), uom: r.uom ?? "" }));
const blank = () => ({ description: "", sample_type_id: "", qty: "", uom: "" });

/**
 * Sample Delivery Challan → Challan Items (AnnotechERP 06/10): what is on one
 * challan. Add item keeps the item at once (Brickland's "Added …"); the
 * description, quantity and unit are changed in place, Remove drops an item,
 * and Save keeps them all — Revert goes back to what was saved. An item
 * changed but not yet saved is marked Edited.
 *
 * @param {{ challanId: string, data: { challan: object, rows: object[], types: object[] }, add: Function, save: Function, canManage: boolean }} props
 */
function ChallanItems({ challanId, data, add, save, canManage }) {
  const t = useT("merchandise");
  const m = (k, p) => t(`master.sdc.${k}`, p);
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
    return was && (was.description !== r.description || String(was.qty) !== r.qty || (was.uom ?? "") !== r.uom);
  };
  const kept = (result) => {
    setBase(result.data.rows);
    setRows(asRows(result.data.rows));
    router.refresh();
  };
  const problems = (result) => (result?.errors ? [...new Set(Object.values(result.errors).flat())] : [result?.message]);

  function addItem() {
    startTransition(async () => {
      const result = await add(challanId, { description: draft.description, sample_type_id: draft.sample_type_id || null, qty: draft.qty === "" ? null : Number(draft.qty), uom: draft.uom || null });
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
      const result = await save(challanId, { rows: rows.map((r) => ({ id: r.id, description: r.description, qty: r.qty === "" ? null : Number(r.qty), uom: r.uom || null })) });
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
    const keys = ["description", "type", "qty", "uom"];
    const lines = [keys.map((k) => quote(m(`col.${k}`))).join(","), ...rows.map((r) => [r.description, r.type, r.qty, r.uom].map(quote).join(","))];
    const link = document.createElement("a");
    link.href = URL.createObjectURL(new Blob([`﻿${lines.join("\r\n")}`], { type: "text/csv;charset=utf-8" }));
    link.download = `challan-${data.challan.challan_no}.csv`;
    link.click();
    URL.revokeObjectURL(link.href);
  }

  const th = "px-4 py-2.5 text-left text-[13px] font-semibold whitespace-nowrap";
  const label = "flex flex-col gap-1.5 text-[13px] font-semibold text-foreground";
  return (
    <div className="flex flex-col">
      <div className="flex flex-wrap justify-end gap-2 border-b border-border px-5 py-3">
        <Button variant="outline" size="sm" onClick={exportCsv} disabled={rows.length === 0}>
          <Download /> {m("export")}
        </Button>
        {canManage ? (
          <Button variant="outline" size="sm" onClick={() => setAdding(true)}>
            <Plus /> {m("add_item")}
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
          <p className="font-semibold text-foreground">{m("no_items", { no: data.challan.challan_no })}</p>
          <p className="text-sm text-foreground-muted">{m("no_items_hint")}</p>
        </div>
      ) : (
        <div className="overflow-x-auto">
          <table className="w-full text-sm">
            <thead>
              <tr className="bg-brand text-brand-foreground">
                {["description", "type", "qty", "uom", "status", "action"].map((k) => (
                  <th key={k} className={th}>
                    {m(`col.${k}`)}
                  </th>
                ))}
              </tr>
            </thead>
            <tbody>
              {rows.map((r) => (
                <tr key={r.id} className="border-b border-border align-middle last:border-0">
                  <td className="px-4 py-2">
                    <Input value={r.description} onChange={(e) => set(r.id, { description: e.target.value })} className="w-72" disabled={!canManage} maxLength={255} aria-label={m("col.description")} />
                  </td>
                  <td className="px-4 py-2">{r.type ?? "—"}</td>
                  <td className="px-4 py-2">
                    <Input type="number" min="0" step="any" value={r.qty} onChange={(e) => set(r.id, { qty: e.target.value })} className="w-24" disabled={!canManage} aria-label={`${m("col.qty")} ${r.description}`} />
                  </td>
                  <td className="px-4 py-2">
                    <UomSelect value={r.uom} onChange={(v) => set(r.id, { uom: v })} compact className="w-32" disabled={!canManage} aria-label={`${m("col.uom")} ${r.description}`} />
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
        <p className="text-[13px] text-foreground-muted">{m("items_count", { n: rows.length })}</p>
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
        title={m("add_title", { no: data.challan.challan_no })}
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
            <Button onClick={addItem} loading={pending}>
              <Plus /> {m("add_item")}
            </Button>
          </>
        }
      >
        <div className="grid gap-4 sm:grid-cols-2">
          <label className={`${label} sm:col-span-2`}>
            <span>
              {m("col.description")}
              <span className="text-danger">*</span>
            </span>
            <Input value={draft.description} onChange={(e) => setDraft((d) => ({ ...d, description: e.target.value }))} maxLength={255} />
          </label>
          <label className={label}>
            <span>{m("col.type")}</span>
            <Select value={draft.sample_type_id} onValueChange={(v) => setDraft((d) => ({ ...d, sample_type_id: v ?? "" }))} options={[{ value: "", label: m("select") }, ...data.types]} placeholder={m("select")} />
          </label>
          <label className={label}>
            <span>
              {m("col.qty")}
              <span className="text-danger">*</span>
            </span>
            <Input type="number" min="0" step="any" value={draft.qty} onChange={(e) => setDraft((d) => ({ ...d, qty: e.target.value }))} />
          </label>
          <label className={label}>
            <span>{m("col.uom")}</span>
            <UomSelect value={draft.uom} onChange={(v) => setDraft((d) => ({ ...d, uom: v }))} />
          </label>
        </div>
        {draftErrors ? <p className="mt-3 text-sm text-danger">{draftErrors.join(" · ")}</p> : null}
      </Modal>
    </div>
  );
}

export { ChallanItems };
