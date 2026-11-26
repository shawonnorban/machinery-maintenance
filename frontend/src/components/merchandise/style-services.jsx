"use client";

import { useState, useTransition } from "react";
import { Plus, Save, Trash2 } from "lucide-react";
import { Input } from "@/components/ui/input";
import { Select } from "@/components/ui/select";
import { Button } from "@/components/ui/button";
import { Alert } from "@/components/ui/alert";
import { useToastManager } from "@/components/ui/toast";
import { useT } from "@/lib/i18n";
import { ImageBox } from "@/components/merchandise/style-item-desk";

let seq = 0;
const SERVICE_FIELDS = ["aop_type", "yd_type", "print_type", "embroidery_type", "wash_type"];
const blank = () => ({ key: `p${++seq}`, process: "", type: "", supplier_id: "", remarks: "", image_id: null, image: null });
const asColours = (rows) => rows.map((c) => ({ ...c, ...Object.fromEntries(SERVICE_FIELDS.map((f) => [f, c[f] ?? ""])) }));
const asProcesses = (rows) => rows.map((p) => ({ ...blank(), ...p, type: p.type ?? "", supplier_id: p.supplier_id ?? "", remarks: p.remarks ?? "", key: p.id }));

/**
 * Item/Combo/Model → Services (AnnotechERP 06/05): Brickland's colour table —
 * the AOP, Y/D, print, embroidery and garment-wash type of each mapped colour —
 * and its Process Details, a line per process with its type, supplier, remarks
 * and a picture. Save keeps both.
 *
 * @param {{ styleId: string, data: object, save: Function, canManage: boolean }} props
 */
function StyleServices({ styleId, data, save, canManage }) {
  const t = useT("merchandise");
  const m = (k, p) => t(`master.svc.${k}`, p);
  const mi = (k, p) => t(`master.itm.${k}`, p);
  const mc = (k) => t(`master.clr.${k}`);
  const toastManager = useToastManager();
  const [base, setBase] = useState(data);
  const [colours, setColours] = useState(() => asColours(data.colours));
  const [processes, setProcesses] = useState(() => asProcesses(data.rows));
  const [errors, setErrors] = useState(null);
  const [pending, startTransition] = useTransition();
  const pick = (list) => [{ value: "", label: m("select") }, ...list];
  const serviceOptions = Object.fromEntries(SERVICE_FIELDS.map((f) => [f, pick(data.services[f].map((v) => ({ value: v, label: m(`opt.${v}`) })))]));
  const processOptions = pick(data.processes.map((v) => ({ value: v, label: m(`process.${v}`) })));
  const supplierOptions = pick(data.suppliers);

  const setColour = (id, field, value) => setColours((all) => all.map((c) => (c.id === id ? { ...c, [field]: value } : c)));
  const setProcess = (key, patch) => setProcesses((all) => all.map((p) => (p.key === key ? { ...p, ...patch } : p)));

  function reset(source) {
    setColours(asColours(source.colours));
    setProcesses(asProcesses(source.rows));
  }

  function submit() {
    startTransition(async () => {
      const result = await save(styleId, {
        colours: colours.map((c) => ({ id: c.id, ...Object.fromEntries(SERVICE_FIELDS.map((f) => [f, c[f] || null])) })),
        processes: processes.map((p) => ({ process: p.process || null, type: p.type || null, supplier_id: p.supplier_id || null, remarks: p.remarks || null, image_id: p.image_id })),
      });
      if (result?.status === "success") {
        setBase(result.data);
        reset(result.data);
        setErrors(null);
        toastManager.add({ title: m("saved"), type: "success" });
      } else {
        setErrors(result?.errors ? [...new Set(Object.values(result.errors).flat())] : [result?.message]);
      }
    });
  }

  const th = "px-2 py-2.5 text-[13px] font-semibold whitespace-nowrap";
  const colourHead = ["colour", "type", ...SERVICE_FIELDS];
  const processHead = ["image", "process", "type", "supplier", "remarks", "action"];

  return (
    <div className="flex flex-col gap-5 p-5">
      {errors ? <Alert variant="danger">{errors.join(" · ")}</Alert> : null}
      <section className="flex flex-col gap-2">
        <h3 className="text-sm font-semibold text-foreground">{m("colours")}</h3>
        <div className="overflow-x-auto rounded-md border border-border">
          <table className="w-full min-w-max text-sm">
            <thead>
              <tr className="bg-brand text-left text-brand-foreground">
                {colourHead.map((h) => (
                  <th key={h} className={th}>
                    {m(`f.${h}`)}
                  </th>
                ))}
              </tr>
            </thead>
            <tbody>
              {colours.length === 0 ? (
                <tr>
                  <td colSpan={colourHead.length} className="px-4 py-12 text-center text-foreground-muted">
                    {m("no_colour")}
                  </td>
                </tr>
              ) : (
                colours.map((c) => (
                  <tr key={c.id} className="border-b border-border align-middle last:border-0">
                    <td className="px-2 py-1.5 font-medium whitespace-nowrap text-foreground">{c.colour}</td>
                    <td className="px-2 py-1.5 whitespace-nowrap text-foreground-muted">{c.colour_type ? mc(`type.${c.colour_type}`) : "—"}</td>
                    {SERVICE_FIELDS.map((f) => (
                      <td key={f} className="w-44 px-2 py-1.5">
                        <Select value={c[f]} onValueChange={(v) => setColour(c.id, f, v ?? "")} options={serviceOptions[f]} placeholder={m("select")} disabled={!canManage} aria-label={m(`f.${f}`)} />
                      </td>
                    ))}
                  </tr>
                ))
              )}
            </tbody>
          </table>
        </div>
      </section>

      <section className="flex flex-col gap-2">
        <div className="flex items-center justify-between gap-3">
          <h3 className="text-sm font-semibold text-foreground">{m("processes")}</h3>
          {canManage ? (
            <Button variant="outline" size="sm" onClick={() => setProcesses((all) => [...all, blank()])}>
              <Plus /> {m("add_process")}
            </Button>
          ) : null}
        </div>
        <div className="overflow-x-auto rounded-md border border-border">
          <table className="w-full min-w-max text-sm">
            <thead>
              <tr className="bg-brand text-left text-brand-foreground">
                {processHead.map((h) => (
                  <th key={h} className={th}>
                    {m(`f.${h}`)}
                  </th>
                ))}
              </tr>
            </thead>
            <tbody>
              {processes.length === 0 ? (
                <tr>
                  <td colSpan={processHead.length} className="px-4 py-12 text-center text-foreground-muted">
                    {m("no_process")}
                  </td>
                </tr>
              ) : (
                processes.map((p) => (
                  <tr key={p.key} className="border-b border-border align-middle last:border-0">
                    <td className="px-2 py-1.5">
                      <ImageBox compact label={m("f.image")} path={p.image} onUploaded={(f) => setProcess(p.key, { image_id: f.id, image: f.path })} onClear={() => setProcess(p.key, { image_id: null, image: null })} m={mi} disabled={!canManage} />
                    </td>
                    <td className="w-44 px-2 py-1.5">
                      <Select value={p.process} onValueChange={(v) => setProcess(p.key, { process: v ?? "" })} options={processOptions} placeholder={m("select")} disabled={!canManage} aria-label={m("f.process")} />
                    </td>
                    <td className="px-2 py-1.5">
                      <Input value={p.type} onChange={(e) => setProcess(p.key, { type: e.target.value })} placeholder={m("ph_type")} className="w-44" disabled={!canManage} aria-label={m("f.type")} />
                    </td>
                    <td className="w-56 px-2 py-1.5">
                      <Select value={p.supplier_id} onValueChange={(v) => setProcess(p.key, { supplier_id: v ?? "" })} options={supplierOptions} placeholder={m("select")} disabled={!canManage} aria-label={m("f.supplier")} />
                    </td>
                    <td className="px-2 py-1.5">
                      <Input value={p.remarks} onChange={(e) => setProcess(p.key, { remarks: e.target.value })} className="w-56" disabled={!canManage} aria-label={m("f.remarks")} />
                    </td>
                    <td className="px-2 py-1.5">
                      {canManage ? (
                        <Button variant="outline" size="sm" onClick={() => setProcesses((all) => all.filter((x) => x.key !== p.key))} aria-label={m("remove")}>
                          <Trash2 />
                        </Button>
                      ) : null}
                    </td>
                  </tr>
                ))
              )}
            </tbody>
          </table>
        </div>
      </section>

      {canManage ? (
        <div className="flex gap-2">
          <Button onClick={submit} loading={pending}>
            <Save /> {mi("save")}
          </Button>
          <Button variant="outline" onClick={() => reset(base)} disabled={pending}>
            {mi("cancel")}
          </Button>
        </div>
      ) : null}
    </div>
  );
}

export { StyleServices };
