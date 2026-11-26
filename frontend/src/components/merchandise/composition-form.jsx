"use client";

import { useMemo, useState, useTransition } from "react";
import { useRouter } from "next/navigation";
import Link from "next/link";
import { RotateCcw, Save, Table2, Trash2, X } from "lucide-react";
import { cn } from "@/lib/utils";
import { Input } from "@/components/ui/input";
import { Select } from "@/components/ui/select";
import { Checkbox } from "@/components/ui/checkbox";
import { Button, buttonVariants } from "@/components/ui/button";
import { Alert } from "@/components/ui/alert";
import { Modal } from "@/components/ui/modal";
import { RecordsGrid } from "@/components/erp/records-grid";
import { useToastManager } from "@/components/ui/toast";
import { useT } from "@/lib/i18n";
import { refsFor } from "@/lib/row-ref";

function format(n) {
  return Number(n)
    .toFixed(2)
    .replace(/\.?0+$/, "");
}

/**
 * Fiber Composition's form (AnnotechERP 06/01): a code, the fibers picked from
 * the Fiber Master with their shares, and the description written from them as
 * you type — "60% Cotton 40% Polyester". It saves only at exactly 100%.
 * With `heading` it is Brickland's card: the title (and the "Rewriting …"
 * line when editing) with a Records button on the right.
 */
function CompositionForm({ fibers, initial = null, save, onDone, successHref, cancelHref, cancelLabel, heading }) {
  const t = useT("merchandise");
  const router = useRouter();
  const toastManager = useToastManager();
  const start = () => ({
    code: initial?.code ?? "",
    active: initial?.active ?? true,
    items: (initial?.items ?? []).map((i) => ({ fiber_id: i.fiber_id, percent: String(i.percent) })),
  });
  const [values, setValues] = useState(start);
  const [errors, setErrors] = useState(null);
  const [pending, startTransition] = useTransition();
  const names = useMemo(() => Object.fromEntries(fibers.map((f) => [f.id, f.name])), [fibers]);

  const total = values.items.reduce((sum, i) => sum + (Number(i.percent) || 0), 0);
  const description = [...values.items]
    .filter((i) => i.fiber_id && Number(i.percent) > 0)
    .sort((a, b) => Number(b.percent) - Number(a.percent))
    .map((i) => `${format(i.percent)}% ${names[i.fiber_id]}`)
    .join(" ");
  const free = fibers.filter((f) => f.active && !values.items.some((i) => i.fiber_id === f.id));

  function submit(event) {
    event.preventDefault();
    startTransition(async () => {
      const result = await save(initial?.id ?? null, {
        code: values.code,
        active: values.active,
        items: values.items.map((i) => ({ fiber_id: i.fiber_id, percent: Number(i.percent) })),
      });
      if (result?.status === "success") {
        toastManager.add({ title: t("master.saved"), type: "success" });
        if (!initial) setValues({ code: "", active: true, items: [] });
        setErrors(null);
        if (onDone) onDone();
        if (successHref) router.push(successHref);
        router.refresh();
      } else {
        setErrors(result?.errors ? Object.values(result.errors).flat() : [result?.message]);
      }
    });
  }

  const form = (
    <form onSubmit={submit} className="flex flex-col gap-4">
      {errors ? <Alert variant="danger">{errors.join(" ")}</Alert> : null}
      <fieldset className="rounded-lg border border-border p-4">
        <legend className="px-1 text-[13px] font-semibold text-brand italic">{t("master.composition.section")}</legend>
        <div className="grid gap-4 sm:grid-cols-2">
          <label className="flex flex-col gap-1.5">
            <span className="text-[13px] font-semibold text-foreground">
              {t("master.f.comp_code")}
              <span className="ml-0.5 text-danger">*</span>
            </span>
            <Input value={values.code} onChange={(e) => setValues((v) => ({ ...v, code: e.target.value }))} placeholder="CVC6040" />
          </label>
          <label className="flex items-center gap-2 pt-6 text-sm font-medium">
            <Checkbox checked={values.active} onCheckedChange={(c) => setValues((v) => ({ ...v, active: c === true }))} /> {t("master.f.is_active_q")}
          </label>
        </div>
        <div className="mt-4 flex flex-col gap-2">
          <span className="text-[13px] font-semibold text-foreground">
            {t("master.f.fiber_type")}
            <span className="ml-0.5 text-danger">*</span>
          </span>
          {values.items.length > 0 ? (
            // Brickland lists the picked fibers as a table: Fiber Type, Percentage, Remove.
            <div className="overflow-hidden rounded-md border border-border">
              <table className="w-full text-sm">
                <thead>
                  <tr className="bg-surface-muted text-left text-[13px]">
                    <th className="px-3 py-2 font-semibold">{t("master.f.fiber_type")}</th>
                    <th className="w-40 px-3 py-2 font-semibold">{t("master.composition.percentage")}</th>
                    <th className="w-20 px-3 py-2 text-right font-semibold">{t("master.remove")}</th>
                  </tr>
                </thead>
                <tbody>
                  {values.items.map((item, index) => (
                    <tr key={item.fiber_id} className="border-t border-border">
                      <td className="px-3 py-2">{names[item.fiber_id]}</td>
                      <td className="px-3 py-2">
                        <span className="flex items-center gap-2">
                          <Input
                            type="number"
                            min="0"
                            max="100"
                            step="0.01"
                            value={item.percent}
                            onChange={(e) => setValues((v) => ({ ...v, items: v.items.map((x, i) => (i === index ? { ...x, percent: e.target.value } : x)) }))}
                            className="w-28"
                            aria-label={`${names[item.fiber_id]} %`}
                          />
                          <span className="text-foreground-muted">%</span>
                        </span>
                      </td>
                      <td className="px-3 py-2 text-right">
                        <Button
                          type="button"
                          variant="outline"
                          size="sm"
                          onClick={() => setValues((v) => ({ ...v, items: v.items.filter((_, i) => i !== index) }))}
                          aria-label={`${t("master.remove")} ${names[item.fiber_id]}`}
                        >
                          <Trash2 />
                        </Button>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          ) : null}
          {free.length > 0 ? (
            <div>
              <Select
                value=""
                // The picker resets itself after a choice and reports an empty value; only a real, new fiber is added.
                onValueChange={(id) => setValues((v) => (!names[id] || v.items.some((i) => i.fiber_id === id) ? v : { ...v, items: [...v.items, { fiber_id: id, percent: "" }] }))}
                options={free.map((f) => ({ value: f.id, label: f.name }))}
                placeholder={t("master.composition.add_fiber")}
              />
            </div>
          ) : null}
        </div>
      </fieldset>
      <fieldset className="rounded-lg border border-border p-4">
        <legend className="px-1 text-[13px] font-semibold text-brand italic">{t("master.composition.desc")}</legend>
        <div className="rounded-md border border-border-strong bg-surface-muted/40 px-3 py-2 text-sm">{description || t("master.composition.empty")}</div>
        <p className={cn("mt-2 text-xs", Math.abs(total - 100) < 0.001 ? "text-success" : "text-foreground-muted")}>{t("master.composition.total", { total: format(total) })}</p>
      </fieldset>
      <div className="flex gap-2">
        {/* Like Brickland, Save is always offered; the server answers a total off 100% or a missing code. */}
        <Button type="submit" loading={pending}>
          <Save /> {t("master.save")}
        </Button>
        {cancelHref ? (
          // Brickland's "Edit fiber composition" tab: Save and Cancel.
          <Button type="button" variant="outline" onClick={() => router.push(cancelHref)} disabled={pending}>
            <X /> {cancelLabel}
          </Button>
        ) : (
          <Button type="button" variant="outline" onClick={() => setValues(start())} disabled={pending}>
            <RotateCcw /> {t("master.reset")}
          </Button>
        )}
      </div>
    </form>
  );

  if (!heading) return form;
  return (
    <div className="rounded-lg border border-border bg-surface">
      <div className="flex flex-wrap items-start justify-between gap-3 border-b border-border px-5 py-4">
        <div>
          <h2 className="text-base font-semibold text-foreground">{heading.title}</h2>
          {heading.subtitle ? <p className="mt-0.5 text-[13px] text-foreground-muted">{heading.subtitle}</p> : null}
        </div>
        <Link href={heading.recordsHref} className={cn(buttonVariants({ variant: "outline", size: "sm" }))}>
          <Table2 /> {heading.recordsLabel}
        </Link>
      </div>
      <div className="p-5">{form}</div>
    </div>
  );
}

/** The compositions grid, each opening the same form to edit. */
function CompositionList({ rows, fibers, columns, save, remove, editHref, deleteLabels }) {
  const t = useT("merchandise");
  const router = useRouter();
  const [editing, setEditing] = useState(null);
  return (
    <>
      <RecordsGrid
        exportName="fiber-compositions"
        rows={rows}
        columns={columns}
        rowOpen={{ label: t("master.edit"), onOpen: (row) => (editHref ? router.push(`${editHref}${encodeURIComponent(refsFor(rows, "code").get(row.id))}`) : setEditing(row)) }}
        remove={remove}
        deleteDescription={t("master.delete_hint")}
        deleteLabels={deleteLabels}
      />
      {editing ? (
        <Modal open onOpenChange={(o) => !o && setEditing(null)} title={t("master.composition.edit", { code: editing.code })} className="max-w-3xl">
          <CompositionForm fibers={fibers} initial={editing} save={save} onDone={() => setEditing(null)} />
        </Modal>
      ) : null}
    </>
  );
}

export { CompositionForm, CompositionList };
