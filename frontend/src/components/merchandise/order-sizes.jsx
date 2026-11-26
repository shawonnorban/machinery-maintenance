"use client";

import { useState, useTransition } from "react";
import { useRouter } from "next/navigation";
import { Download, Plus, RotateCcw, Save } from "lucide-react";
import { Input } from "@/components/ui/input";
import { Button } from "@/components/ui/button";
import { Alert } from "@/components/ui/alert";
import { Modal } from "@/components/ui/modal";
import { useToastManager } from "@/components/ui/toast";
import { useT } from "@/lib/i18n";

let seq = 0;
const asLines = (rows) => rows.map((r) => ({ key: r.id, colour: r.colour, size: r.size, qty: String(r.qty) }));
const blankLine = () => ({ colour: "", size: "", qty: "" });

/**
 * Order Register → Size Breakdown (AnnotechERP 06/07): Brickland's colour ×
 * size lines of one order. A quantity is changed in place, Remove drops a
 * line, Add line opens a small form; Save keeps them all and the order's
 * total becomes their sum, Revert goes back to what was saved. The style's
 * colours and sizes are offered while typing.
 *
 * @param {{ orderId: string, data: { order: object, rows: object[], suggest: { colours: string[], sizes: string[] } }, save: Function, canManage: boolean }} props
 */
function OrderSizes({ orderId, data, save, canManage }) {
  const t = useT("merchandise");
  const m = (k, p) => t(`master.ord.${k}`, p);
  const router = useRouter();
  const toastManager = useToastManager();
  const [base, setBase] = useState(data.rows);
  const [lines, setLines] = useState(() => asLines(data.rows));
  const [adding, setAdding] = useState(false);
  const [draft, setDraft] = useState(blankLine);
  const [errors, setErrors] = useState(null);
  const [draftError, setDraftError] = useState(null);
  const [pending, startTransition] = useTransition();
  const changed = JSON.stringify(lines.map(({ colour, size, qty }) => [colour, size, qty])) !== JSON.stringify(asLines(base).map(({ colour, size, qty }) => [colour, size, qty]));
  const total = lines.reduce((sum, l) => sum + (Number(l.qty) || 0), 0);
  const same = (a, b) => a.trim().toUpperCase() === b.trim().toUpperCase();

  function addLine() {
    if (!draft.colour.trim() || !draft.size.trim() || !(Number(draft.qty) > 0)) {
      setDraftError(m("line_hint"));
      return;
    }
    if (lines.some((l) => same(l.colour, draft.colour) && same(l.size, draft.size))) {
      setDraftError(t("order.line_twice", { colour: draft.colour.trim(), size: draft.size.trim() }));
      return;
    }
    setLines((all) => [...all, { key: `l${++seq}`, colour: draft.colour.trim(), size: draft.size.trim(), qty: String(Number(draft.qty)) }]);
    setDraft(blankLine());
    setDraftError(null);
    setAdding(false);
  }

  function submit() {
    startTransition(async () => {
      const result = await save(orderId, { rows: lines.map((l) => ({ colour: l.colour, size: l.size, qty: Number(l.qty) || null })) });
      if (result?.status === "success") {
        setBase(result.data.rows);
        setLines(asLines(result.data.rows));
        setErrors(null);
        toastManager.add({ title: m("saved", { qty: result.data.order.total_qty }), type: "success" });
        router.refresh();
      } else {
        setErrors(result?.errors ? [...new Set(Object.values(result.errors).flat())] : [result?.message]);
      }
    });
  }

  function exportCsv() {
    const quote = (v) => `"${String(v ?? "").replace(/"/g, '""')}"`;
    const rows = [[m("f.colour"), m("f.size"), m("f.qty")].map(quote).join(","), ...lines.map((l) => [l.colour, l.size, l.qty].map(quote).join(","))];
    const link = document.createElement("a");
    link.href = URL.createObjectURL(new Blob([`﻿${rows.join("\r\n")}`], { type: "text/csv;charset=utf-8" }));
    link.download = `size-breakdown-${data.order.order_no}.csv`;
    link.click();
    URL.revokeObjectURL(link.href);
  }

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
      <div className="overflow-x-auto">
        <table className="w-full text-sm">
          <thead>
            <tr className="bg-brand text-left text-brand-foreground">
              <th className="px-4 py-2.5 text-[13px] font-semibold">{m("f.colour")}</th>
              <th className="px-4 py-2.5 text-[13px] font-semibold">{m("f.size")}</th>
              <th className="px-4 py-2.5 text-right text-[13px] font-semibold">{m("f.qty")}</th>
              <th className="px-4 py-2.5 text-right text-[13px] font-semibold">{m("f.action")}</th>
            </tr>
          </thead>
          <tbody>
            {lines.length === 0 ? (
              <tr>
                <td colSpan={4} className="px-4 py-14 text-center text-foreground-muted">
                  {m("no_lines")}
                </td>
              </tr>
            ) : (
              lines.map((l) => (
                <tr key={l.key} className="border-b border-border align-middle last:border-0">
                  <td className="px-4 py-2 font-medium text-foreground">{l.colour}</td>
                  <td className="px-4 py-2">{l.size}</td>
                  <td className="px-4 py-2">
                    <Input type="number" min="1" step="1" value={l.qty} onChange={(e) => setLines((all) => all.map((x) => (x.key === l.key ? { ...x, qty: e.target.value } : x)))} className="ml-auto w-28 text-right" disabled={!canManage} aria-label={`${m("f.qty")} ${l.colour} ${l.size}`} />
                  </td>
                  <td className="px-4 py-2 text-right">
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
        <p className="text-[13px] text-foreground-muted">
          {m("lines_note", { n: lines.length })} <b className="text-foreground">{m("pcs", { qty: total })}</b>
        </p>
        {canManage ? (
          <div className="flex gap-2">
            <Button variant="outline" onClick={() => { setLines(asLines(base)); setErrors(null); }} disabled={pending || !changed}>
              <RotateCcw /> {m("revert")}
            </Button>
            <Button onClick={submit} loading={pending} disabled={!changed}>
              <Save /> {m("save")}
            </Button>
          </div>
        ) : null}
      </div>

      <Modal open={adding} onOpenChange={(open) => { setAdding(open); if (!open) setDraftError(null); }} title={m("add_line_of", { order: data.order.order_no })} className="max-w-xl">
          <div className="flex flex-col gap-4">
            <div className="rounded-lg border border-border p-4">
              <h3 className="text-sm font-semibold text-foreground">{m("new_line")}</h3>
              <p className="mt-0.5 text-[13px] text-foreground-muted">{m("line_hint")}</p>
              <div className="mt-3 grid gap-3 sm:grid-cols-3">
                {[
                  ["colour", "colours"],
                  ["size", "sizes"],
                ].map(([field, list]) => (
                  <label key={field} className="flex flex-col gap-1.5 text-[13px] font-semibold text-foreground">
                    <span>
                      {m(`f.${field}`)}
                      <span className="text-danger">*</span>
                    </span>
                    <Input list={`order-${field}-${orderId}`} value={draft[field]} onChange={(e) => setDraft((d) => ({ ...d, [field]: e.target.value }))} autoComplete="off" />
                    <datalist id={`order-${field}-${orderId}`}>
                      {data.suggest[list].map((v) => (
                        <option key={v} value={v} />
                      ))}
                    </datalist>
                  </label>
                ))}
                <label className="flex flex-col gap-1.5 text-[13px] font-semibold text-foreground">
                  <span>
                    {m("f.qty")}
                    <span className="text-danger">*</span>
                  </span>
                  <Input type="number" min="1" step="1" value={draft.qty} onChange={(e) => setDraft((d) => ({ ...d, qty: e.target.value }))} />
                </label>
              </div>
              {draftError ? <p className="mt-2 text-xs text-danger">{draftError}</p> : null}
            </div>
            <div className="flex gap-2">
              <Button variant="outline" onClick={() => { setDraft(blankLine()); setDraftError(null); }}>
                <RotateCcw /> {m("reset")}
              </Button>
              <Button onClick={addLine}>
                <Save /> {m("add_line")}
              </Button>
            </div>
          </div>
      </Modal>
    </div>
  );
}

export { OrderSizes };
