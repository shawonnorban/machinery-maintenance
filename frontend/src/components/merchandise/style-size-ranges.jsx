"use client";

import { useState, useTransition } from "react";
import { useRouter } from "next/navigation";
import { Plus, RotateCcw, Save, Trash2 } from "lucide-react";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Checkbox } from "@/components/ui/checkbox";
import { Modal } from "@/components/ui/modal";
import { Alert } from "@/components/ui/alert";
import { ConfirmDialog } from "@/components/ui/confirm-dialog";
import { useToastManager } from "@/components/ui/toast";
import { useT } from "@/lib/i18n";

/**
 * Style Development Sheet → Size Range (AnnotechERP 06/05): one style's size
 * sets — SL#, the sizes, the sub-style a line is for (blank is the main line)
 * and the Size Master group they come from — with Add size range (every size
 * as a tick box) and Remove.
 *
 * @param {{ style: {id: string, style_no: string}, lines: object[], sizes: object[], groupLabels: object, add: Function, remove: Function, canManage: boolean }} props
 */
function StyleSizeRanges({ style, lines, sizes, groupLabels, add, remove, canManage }) {
  const t = useT("merchandise");
  const m = (k, p) => t(`master.sty.${k}`, p);
  const router = useRouter();
  const toastManager = useToastManager();
  const fresh = () => ({ size_ids: [], sub_style: "", sl_no: "" });
  const [open, setOpen] = useState(false);
  const [form, setForm] = useState(fresh);
  const [errors, setErrors] = useState(null);
  const [removing, setRemoving] = useState(null);
  const [pending, startTransition] = useTransition();

  function save() {
    startTransition(async () => {
      const result = await add(style.id, { size_ids: form.size_ids, sub_style: form.sub_style || null, sl_no: form.sl_no === "" ? null : Number(form.sl_no) });
      if (result?.status === "success") {
        toastManager.add({ title: t("master.saved"), type: "success" });
        setOpen(false);
        setForm(fresh());
        setErrors(null);
        router.refresh();
      } else {
        setErrors(result?.errors ? [...new Set(Object.values(result.errors).flat())] : [result?.message]);
      }
    });
  }

  function confirmRemove() {
    startTransition(async () => {
      const result = await remove(removing.id);
      if (result?.status === "success") {
        toastManager.add({ title: t("master.deleted"), type: "success" });
        setRemoving(null);
        router.refresh();
      } else {
        toastManager.add({ title: result?.message, type: "danger" });
      }
    });
  }

  const toggle = (id, on) => setForm((f) => ({ ...f, size_ids: on ? [...f.size_ids, id] : f.size_ids.filter((x) => x !== id) }));

  return (
    <>
      {canManage ? (
        <div className="flex justify-end px-5 pb-3">
          <Button size="sm" onClick={() => setOpen(true)}>
            <Plus /> {m("add_range")}
          </Button>
        </div>
      ) : null}
      <div className="overflow-x-auto">
        <table className="w-full min-w-max text-sm">
          <thead>
            <tr className="bg-brand text-left text-brand-foreground">
              {["sl", "size_range", "style", "from_chart", "action"].map((k) => (
                <th key={k} className="px-3 py-2.5 text-[13px] font-semibold">
                  {m(`f.${k}`)}
                </th>
              ))}
            </tr>
          </thead>
          <tbody>
            {lines.length === 0 ? (
              <tr>
                <td colSpan={5} className="px-4 py-8 text-center text-foreground-muted">
                  {m("no_ranges")}
                </td>
              </tr>
            ) : (
              lines.map((l) => (
                <tr key={l.id} className="border-b border-border last:border-0">
                  <td className="px-3 py-2.5 tabular-nums">{l.sl_no}</td>
                  <td className="px-3 py-2.5">{l.sizes}</td>
                  <td className="px-3 py-2.5">{l.sub_style ?? "—"}</td>
                  <td className="px-3 py-2.5">{l.groups.map((g) => groupLabels[g] ?? g).join(", ") || "—"}</td>
                  <td className="px-3 py-2">
                    {canManage ? (
                      <Button variant="outline" size="sm" onClick={() => setRemoving(l)} aria-label={`${m("remove")} ${l.sl_no}`}>
                        <Trash2 /> {m("remove")}
                      </Button>
                    ) : null}
                  </td>
                </tr>
              ))
            )}
          </tbody>
        </table>
      </div>

      <Modal open={open} onOpenChange={setOpen} title={m("add_range_title", { style: style.style_no })} className="max-w-2xl">
        <div className="flex flex-col gap-4">
          <h3 className="text-sm font-semibold text-foreground">{m("new_range")}</h3>
          {errors ? <Alert variant="danger">{errors.join(" · ")}</Alert> : null}
          <div className="flex flex-col gap-1.5">
            <span className="text-[13px] font-semibold text-foreground">
              {m("f.size_range")}
              <span className="ml-0.5 text-danger">*</span>
            </span>
            <div className="flex flex-wrap gap-x-4 gap-y-2 rounded-md border border-border-strong px-3 py-2.5">
              {sizes.length === 0 ? <span className="text-[13px] text-foreground-muted">{m("no_sizes")}</span> : null}
              {sizes.map((s) => (
                <label key={s.value} className="flex items-center gap-1.5 text-sm">
                  <Checkbox checked={form.size_ids.includes(s.value)} onCheckedChange={(c) => toggle(s.value, c === true)} />
                  {s.label}
                </label>
              ))}
            </div>
            <p className="text-xs text-foreground-muted">{m("range_hint")}</p>
          </div>
          <div className="grid gap-4 sm:grid-cols-2">
            <label className="flex flex-col gap-1.5">
              <span className="text-[13px] font-semibold text-foreground">{m("f.style")}</span>
              <Input value={form.sub_style} onChange={(e) => setForm((f) => ({ ...f, sub_style: e.target.value }))} placeholder={style.style_no} />
            </label>
            <label className="flex flex-col gap-1.5">
              <span className="text-[13px] font-semibold text-foreground">{m("f.sl")}</span>
              <Input type="number" min="1" value={form.sl_no} onChange={(e) => setForm((f) => ({ ...f, sl_no: e.target.value }))} />
            </label>
          </div>
          <div className="flex justify-end gap-2 border-t border-border pt-4">
            <Button variant="outline" onClick={() => setForm(fresh())} disabled={pending}>
              <RotateCcw /> {m("reset")}
            </Button>
            <Button onClick={save} loading={pending}>
              <Save /> {m("save")}
            </Button>
          </div>
        </div>
      </Modal>

      <ConfirmDialog
        open={removing !== null}
        onOpenChange={() => setRemoving(null)}
        title={m("remove_line")}
        description={removing ? `${removing.sl_no} · ${removing.sizes}` : ""}
        confirmLabel={m("remove")}
        cancelLabel={m("cancel")}
        loading={pending}
        onConfirm={confirmRemove}
      />
    </>
  );
}

export { StyleSizeRanges };
