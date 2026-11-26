"use client";

import { useRef, useState, useTransition } from "react";
import { useRouter } from "next/navigation";
import { Download, ImagePlus, Plus, RotateCcw, Save } from "lucide-react";
import { Input } from "@/components/ui/input";
import { Select } from "@/components/ui/select";
import { Textarea } from "@/components/ui/textarea";
import { Button } from "@/components/ui/button";
import { Alert } from "@/components/ui/alert";
import { Modal } from "@/components/ui/modal";
import { useToastManager } from "@/components/ui/toast";
import { useT } from "@/lib/i18n";

const FIELDS = ["style_id", "material_description", "order_id", "nature_of_work", "sample_type_id", "shipment_date", "item", "old_style_no", "print_type", "print_color", "req_qty", "unit", "priority", "instruction"];
const asLines = (rows) => rows.map((r) => ({ ...Object.fromEntries(FIELDS.map((k) => [k, r[k] === null || r[k] === undefined ? "" : String(r[k])])), id: r.id, artwork_file_id: r.artwork_file_id, artwork_name: r.artwork_name }));
const blank = () => Object.fromEntries(FIELDS.map((k) => [k, ""]));
const ACCEPT = "image/jpeg,image/png,image/webp";

/**
 * Print Design Sheet → Style & Order Lines (AnnotechERP 06/10): the styles,
 * orders and instructions one sheet asks a print design and strike-off for.
 * Add line opens Brickland's entry fields with the artwork image to upload —
 * material description, sample type and shipment date first — and keeps the
 * line at once. In the list the style, order, sample type, item, quantity,
 * unit and instruction are changed in place and saved together, a line's
 * artwork is replaced from its thumbnail, and Remove drops a line.
 *
 * @param {{ programId: string, data: { program: object, rows: object[], styles: object[], orders: object[], types: object[], priorities: string[] }, save: Function, canManage: boolean }} props
 */
function PrintLines({ programId, data, save, canManage }) {
  const t = useT("merchandise");
  const m = (k, p) => t(`master.pds.${k}`, p);
  const router = useRouter();
  const toastManager = useToastManager();
  const [base, setBase] = useState(data.rows);
  const [lines, setLines] = useState(() => asLines(data.rows));
  const [adding, setAdding] = useState(false);
  const [draft, setDraft] = useState(blank);
  const [artwork, setArtwork] = useState(null);
  const [draftError, setDraftError] = useState(null);
  const [errors, setErrors] = useState(null);
  const [pending, startTransition] = useTransition();
  const replaceFor = useRef(null);
  const picker = useRef(null);
  const pick = (list) => [{ value: "", label: m("select") }, ...list];
  const strip = (list) => list.map(({ value, label }) => ({ value, label }));
  const ordersFor = (styleId) => data.orders.filter((o) => !styleId || o.style_id === styleId);
  const priorities = data.priorities.map((p) => ({ value: p, label: m(`priority.${p}`) }));
  const set = (id, patch) => setLines((all) => all.map((l) => (l.id === id ? { ...l, ...patch } : l)));
  const kept = (rows) => {
    setBase(rows);
    setLines(asLines(rows));
    router.refresh();
  };
  const problems = (body) => (body?.errors ? [...new Set(Object.values(body.errors).flat())] : [body?.message ?? m("upload_failed")]);

  // Multipart through the route handler: an artwork can run past the one megabyte server actions stop at.
  async function post(url, form) {
    const response = await fetch(url, { method: "POST", body: form });
    const body = await response.json().catch(() => null);
    return response.ok ? { ok: true, data: body?.data } : { ok: false, body };
  }

  function addLine() {
    const need = !draft.material_description.trim() ? "material" : !draft.sample_type_id ? "type" : !draft.shipment_date ? "shipment" : null;
    if (need) {
      setDraftError(m(`need.${need}`));
      return;
    }
    const form = new FormData();
    Object.entries(draft).forEach(([k, v]) => v !== "" && form.append(k, v));
    if (artwork) form.append("artwork", artwork);
    startTransition(async () => {
      const result = await post(`/api/merchandise/print-lines/${programId}`, form);
      if (result.ok) {
        kept(result.data.rows);
        toastManager.add({ title: m("line_added"), type: "success" });
        setDraft(blank());
        setArtwork(null);
        setDraftError(null);
        setAdding(false);
      } else {
        setDraftError(problems(result.body).join(" · "));
      }
    });
  }

  function replaceArtwork(file) {
    const line = replaceFor.current;
    if (!file || !line) return;
    const form = new FormData();
    form.append("artwork", file);
    startTransition(async () => {
      const result = await post(`/api/merchandise/print-lines/${programId}/${line}/artwork`, form);
      if (result.ok) {
        // Only the artwork is taken from the answer; what is being edited in place stays.
        const fresh = result.data.rows.find((r) => r.id === line);
        setBase((all) => all.map((r) => (r.id === line ? { ...r, artwork_file_id: fresh.artwork_file_id, artwork_name: fresh.artwork_name } : r)));
        set(line, { artwork_file_id: fresh.artwork_file_id, artwork_name: fresh.artwork_name });
        toastManager.add({ title: m("artwork_saved"), type: "success" });
      } else {
        setErrors(problems(result.body));
      }
    });
  }

  function submit() {
    startTransition(async () => {
      const result = await save(programId, { rows: lines.map((l) => ({ id: l.id, ...Object.fromEntries(FIELDS.map((k) => [k, l[k] === "" ? null : l[k]])), req_qty: l.req_qty === "" ? null : Number(l.req_qty) })) });
      if (result?.status === "success") {
        kept(result.data.rows);
        setErrors(null);
        toastManager.add({ title: m("saved"), type: "success" });
      } else {
        setErrors(result?.errors ? [...new Set(Object.values(result.errors).flat())] : [result?.message]);
      }
    });
  }

  function exportCsv() {
    const quote = (v) => `"${String(v ?? "").replace(/"/g, '""')}"`;
    const name = (list, id) => list.find((x) => x.value === id)?.label ?? "";
    const keys = ["style", "order", "sample_type", "item", "req_qty", "unit", "image_url", "instruction"];
    const rows = [keys.map((k) => quote(m(`col.${k}`))).join(","), ...lines.map((l) => [name(data.styles, l.style_id), name(data.orders, l.order_id), name(data.types, l.sample_type_id), l.item, l.req_qty, l.unit, l.artwork_name, l.instruction].map(quote).join(","))];
    const link = document.createElement("a");
    link.href = URL.createObjectURL(new Blob([`﻿${rows.join("\r\n")}`], { type: "text/csv;charset=utf-8" }));
    link.download = `print-design-${data.program.program_no}.csv`;
    link.click();
    URL.revokeObjectURL(link.href);
  }

  const th = "px-3 py-2.5 text-left text-[13px] font-semibold whitespace-nowrap";
  const label = "flex flex-col gap-1.5 text-[13px] font-semibold text-foreground";
  const star = <span className="text-danger">*</span>;
  const text = (key, required = false, props = {}) => (
    <label key={key} className={label}>
      <span>
        {m(`f.${key}`)}
        {required ? star : null}
      </span>
      <Input value={draft[key]} onChange={(e) => setDraft((x) => ({ ...x, [key]: e.target.value }))} {...props} />
    </label>
  );
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
      <input ref={picker} type="file" accept={ACCEPT} className="hidden" onChange={(e) => { replaceArtwork(e.target.files?.[0]); e.target.value = ""; }} />
      {lines.length === 0 ? (
        <div className="flex flex-col items-center gap-1 px-6 py-14 text-center">
          <p className="font-semibold text-foreground">{m("no_lines_under", { no: data.program.program_no })}</p>
          <p className="text-sm text-foreground-muted">{m("one_line_needed")}</p>
        </div>
      ) : (
        <div className="overflow-x-auto">
          <table className="w-full text-sm">
            <thead>
              <tr className="bg-brand text-brand-foreground">
                {["style", "order", "sample_type", "item", "req_qty", "unit", "image_url", "instruction", "remove"].map((k) => (
                  <th key={k} className={th}>
                    {m(`col.${k}`)}
                  </th>
                ))}
              </tr>
            </thead>
            <tbody>
              {lines.map((l) => (
                <tr key={l.id} className="border-b border-border align-middle last:border-0">
                  <td className="px-3 py-2">
                    <Select value={l.style_id} onValueChange={(v) => set(l.id, { style_id: v ?? "" })} options={[{ value: "", label: "—" }, ...strip(data.styles)]} placeholder="—" className="w-52" disabled={!canManage} />
                  </td>
                  <td className="px-3 py-2">
                    <Select value={l.order_id} onValueChange={(v) => set(l.id, { order_id: v ?? "" })} options={[{ value: "", label: "—" }, ...strip(ordersFor(""))]} placeholder="—" className="w-52" disabled={!canManage} />
                  </td>
                  <td className="px-3 py-2">
                    <Select value={l.sample_type_id} onValueChange={(v) => v && set(l.id, { sample_type_id: v })} options={data.types} className="w-40" disabled={!canManage} />
                  </td>
                  <td className="px-3 py-2">
                    <Input value={l.item} onChange={(e) => set(l.id, { item: e.target.value })} className="w-28" disabled={!canManage} aria-label={`${m("col.item")} ${l.material_description}`} />
                  </td>
                  <td className="px-3 py-2">
                    <Input type="number" min="0" step="any" value={l.req_qty} onChange={(e) => set(l.id, { req_qty: e.target.value })} className="w-20" disabled={!canManage} aria-label={`${m("col.req_qty")} ${l.material_description}`} />
                  </td>
                  <td className="px-3 py-2">
                    <Input value={l.unit} onChange={(e) => set(l.id, { unit: e.target.value })} className="w-16" disabled={!canManage} aria-label={`${m("col.unit")} ${l.material_description}`} />
                  </td>
                  <td className="px-3 py-2">
                    <div className="flex items-center gap-2">
                      {l.artwork_file_id ? (
                        <a href={`/api/files/${l.artwork_file_id}/download`} target="_blank" rel="noreferrer" title={l.artwork_name ?? ""}>
                          {/* eslint-disable-next-line @next/next/no-img-element -- a private file served through the API, not a static asset */}
                          <img src={`/api/files/${l.artwork_file_id}/download`} alt={l.artwork_name ?? m("col.image_url")} className="size-10 rounded border border-border object-cover" />
                        </a>
                      ) : (
                        <span className="text-foreground-muted">—</span>
                      )}
                      {canManage ? (
                        <Button
                          variant="outline"
                          size="icon"
                          className="size-8"
                          aria-label={`${m(l.artwork_file_id ? "replace_artwork" : "upload_artwork")} ${l.material_description}`}
                          title={m(l.artwork_file_id ? "replace_artwork" : "upload_artwork")}
                          onClick={() => {
                            replaceFor.current = l.id;
                            picker.current?.click();
                          }}
                          disabled={pending}
                        >
                          <ImagePlus />
                        </Button>
                      ) : null}
                    </div>
                  </td>
                  <td className="px-3 py-2">
                    <Input value={l.instruction} onChange={(e) => set(l.id, { instruction: e.target.value })} className="w-56" disabled={!canManage} aria-label={`${m("col.instruction")} ${l.material_description}`} />
                  </td>
                  <td className="px-3 py-2">
                    {canManage ? (
                      <Button variant="outline" size="sm" className="text-danger" onClick={() => setLines((all) => all.filter((x) => x.id !== l.id))}>
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
              <Save /> {m("save_lines")}
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
        title={m("add_title", { no: data.program.program_no })}
        description={m("list")}
        className="sm:max-w-3xl"
        footer={
          <>
            <Button
              variant="outline"
              onClick={() => {
                setDraft(blank());
                setArtwork(null);
                setDraftError(null);
              }}
              disabled={pending}
            >
              {m("cancel")}
            </Button>
            <Button onClick={addLine} loading={pending}>
              <Plus /> {m("add")}
            </Button>
          </>
        }
      >
        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
          <label className={label}>
            <span>{m("f.style")}</span>
            <Select value={draft.style_id} onValueChange={(v) => setDraft((x) => ({ ...x, style_id: v ?? "", order_id: "" }))} options={pick(strip(data.styles))} placeholder={m("select")} />
          </label>
          {text("material_description", true, { maxLength: 255 })}
          <label className={label}>
            <span>{m("f.order")}</span>
            <Select
              value={draft.order_id}
              onValueChange={(v) => {
                const order = data.orders.find((o) => o.value === v);
                setDraft((x) => ({ ...x, order_id: v ?? "", shipment_date: x.shipment_date || order?.ship_date || "" }));
              }}
              options={pick(strip(ordersFor(draft.style_id)))}
              placeholder={m("select")}
            />
          </label>
          {text("nature_of_work", false, { maxLength: 160 })}
          <label className={label}>
            <span>
              {m("f.sample_type")}
              {star}
            </span>
            <Select value={draft.sample_type_id} onValueChange={(v) => setDraft((x) => ({ ...x, sample_type_id: v ?? "" }))} options={pick(data.types)} placeholder={m("select")} />
          </label>
          {text("shipment_date", true, { type: "date" })}
          {text("item", false, { maxLength: 96 })}
          {text("old_style_no", false, { maxLength: 96 })}
          {text("print_type", false, { maxLength: 96 })}
          {text("print_color", false, { maxLength: 96 })}
          {text("req_qty", false, { type: "number", min: "0", step: "any" })}
          {text("unit", false, { maxLength: 16 })}
          <label className={label}>
            <span>{m("f.priority")}</span>
            <Select value={draft.priority} onValueChange={(v) => setDraft((x) => ({ ...x, priority: v ?? "" }))} options={pick(priorities)} placeholder={m("select")} />
          </label>
          <label className={`${label} sm:col-span-2`}>
            <span>{m("f.image_url")}</span>
            <Input type="file" accept={ACCEPT} onChange={(e) => setArtwork(e.target.files?.[0] ?? null)} />
            <span className="text-xs font-normal text-foreground-muted">{m("artwork_hint")}</span>
          </label>
          <label className={`${label} sm:col-span-2 lg:col-span-3`}>
            <span>{m("f.instruction")}</span>
            <Textarea value={draft.instruction} onChange={(e) => setDraft((x) => ({ ...x, instruction: e.target.value }))} rows={2} />
          </label>
        </div>
        {draftError ? <p className="mt-3 text-sm text-danger">{draftError}</p> : null}
      </Modal>
    </div>
  );
}

export { PrintLines };
