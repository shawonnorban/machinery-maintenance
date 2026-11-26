"use client";

import { useMemo, useState, useTransition } from "react";
import Link from "next/link";
import { useRouter } from "next/navigation";
import { FilterX, Plus, RotateCcw, Save } from "lucide-react";
import { cn } from "@/lib/utils";
import { Input } from "@/components/ui/input";
import { Select } from "@/components/ui/select";
import { Alert } from "@/components/ui/alert";
import { Button, buttonVariants } from "@/components/ui/button";
import { useToastManager } from "@/components/ui/toast";
import { RecordsGrid } from "@/components/erp/records-grid";
import { useT } from "@/lib/i18n";

/*
 * The pieces every Prototype Desk screen is built from (AnnotechERP 07), as
 * Brickland draws them: a list card with its filter row and status pills
 * over the grid, and a form card of titled sections whose fields carry a
 * hint underneath. Lists filter in place; nothing reloads.
 */

const card = "rounded-xl border border-border bg-surface";

/** An error's field messages, or its message when it names no field. */
const messagesOf = (result) => {
  const fields = [...new Set(Object.values(result?.errors ?? {}).flat())];
  return fields.length ? fields : [result?.message ?? ""];
};
const str = (v) => (v === null || v === undefined ? "" : String(v));
const blank = (v) => (v === "" || v === undefined ? null : v);
const todayIso = () => new Intl.DateTimeFormat("en-CA", { timeZone: "Asia/Dhaka" }).format(new Date());

/** A titled block of a form: Requisition, Buyer & Style, Sample, People … */
function Section({ title, children, className }) {
  return (
    <fieldset className={cn("rounded-lg border border-border p-4", className)}>
      <legend className="px-1.5 text-sm font-semibold text-foreground">{title}</legend>
      <div className="grid gap-x-4 gap-y-3 sm:grid-cols-2 lg:grid-cols-3">{children}</div>
    </fieldset>
  );
}

/** A labelled field with Brickland's hint underneath. */
function Field({ label, required, hint, children, wide, htmlFor }) {
  return (
    <label htmlFor={htmlFor} className={cn("flex min-w-0 flex-col gap-1.5", wide && "sm:col-span-2 lg:col-span-3")}>
      <span className="text-[13px] font-medium text-foreground">
        {label}
        {required ? <span className="text-danger"> *</span> : null}
      </span>
      {children}
      {hint ? <span className="text-xs text-foreground-muted">{hint}</span> : null}
    </label>
  );
}

/** A value the form shows but does not take (Requisition No, Status, Pending At …). */
function ReadOnly({ value, placeholder }) {
  return <Input value={str(value)} placeholder={placeholder} disabled readOnly />;
}

/**
 * A Prototype Desk list: the filter row (selects and a date range, applied as
 * they change), the status pills with their counts, Clear and the add
 * button, then the grid. Everything is data, so a server page can hand it
 * over; a client screen adds its own row buttons.
 *
 * @param {{
 *   rows: object[], columns: object[], rowKey: string, exportName: string,
 *   filters: { key: string, label: string, field: string, options?: { value: string, label: string }[], range?: boolean }[],
 *   pills?: { key: string, label: string, field?: string, value?: any, sum?: string, stat?: (rows: object[]) => string }[],
 *   addHref?: string, addLabel?: string, openHref?: string, openLabel?: string, refKey?: string,
 *   title: string, subtitle?: string, emptyText: string, rowActions?: object[],
 * }} props
 */
function ProtoList({ rows, columns, rowKey, exportName, filters, pills = [], addHref, addLabel, openHref, openLabel, refKey, title, subtitle, emptyText, rowActions }) {
  const t = useT("prototype");
  const te = useT("erp");
  const router = useRouter();
  const empty = () =>
    Object.fromEntries(
      filters.flatMap((f) =>
        f.range
          ? [
              [`${f.key}_from`, ""],
              [`${f.key}_to`, ""],
            ]
          : [[f.key, ""]],
      ),
    );
  const [values, setValues] = useState(empty);
  const [pill, setPill] = useState("");
  const set = (key) => (v) => setValues((all) => ({ ...all, [key]: v ?? "" }));
  const matches = (row) =>
    filters.every((f) => {
      if (f.range) {
        const v = str(row[f.field]).slice(0, 10);
        return (!values[`${f.key}_from`] || v >= values[`${f.key}_from`]) && (!values[`${f.key}_to`] || v <= values[`${f.key}_to`]);
      }
      return !values[f.key] || str(row[f.field]) === values[f.key];
    });
  const filtered = useMemo(() => rows.filter(matches), [rows, values]); // eslint-disable-line react-hooks/exhaustive-deps
  const active = pills.find((p) => p.key === pill);
  const shown = active ? filtered.filter((r) => r[active.field] === active.value) : filtered;
  const actions = [
    ...(openHref
      ? [
          {
            key: "open",
            label: openLabel ?? t("ui.open"),
            onClick: (row) => router.push(`${openHref}${encodeURIComponent(row[refKey ?? rowKey])}`),
          },
        ]
      : []),
    ...(rowActions ?? []),
  ];
  return (
    <div className="flex flex-col gap-4">
      <section className={cn(card, "flex flex-col gap-4 p-4")}>
        {pills.length ? (
          <div className="flex flex-wrap gap-2" role="tablist">
            {pills.map((p) =>
              // A stat pill shows a figure of the filtered rows and does not filter: `sum` is data a server page can pass (Delay Hours), `stat` a client screen's own (Avg. Achievement).
              p.stat || p.sum ? (
                <span key={p.key} className="inline-flex items-center gap-2 rounded-md border border-border px-3 py-1.5 text-[13px] font-medium text-foreground">
                  {p.label}
                  <span className="rounded-full bg-surface-muted px-1.5 text-xs tabular-nums">{p.stat ? p.stat(filtered) : Math.round(filtered.reduce((a, r) => a + (Number(r[p.sum]) || 0), 0) * 100) / 100}</span>
                </span>
              ) : (
                <button
                  key={p.key}
                  type="button"
                  role="tab"
                  aria-selected={pill === p.key}
                  onClick={() => setPill(pill === p.key ? "" : p.key)}
                  className={cn("inline-flex items-center gap-2 rounded-md border px-3 py-1.5 text-[13px] font-medium", pill === p.key ? "border-brand bg-brand-subtle text-brand" : "border-border text-foreground hover:bg-surface-muted")}
                >
                  {p.label}
                  <span className="rounded-full bg-surface-muted px-1.5 text-xs tabular-nums">{filtered.filter((r) => r[p.field] === p.value).length}</span>
                </button>
              ),
            )}
          </div>
        ) : null}
        <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
          {filters.map((f) =>
            f.range ? (
              <div key={f.key} className="flex flex-col gap-1.5 sm:col-span-2">
                <span className="text-[13px] font-medium text-foreground">{f.label}</span>
                <div className="flex items-center gap-2">
                  <Input type="date" value={values[`${f.key}_from`]} onChange={(e) => set(`${f.key}_from`)(e.target.value)} aria-label={`${f.label} ${t("ui.from")}`} />
                  <span className="text-xs text-foreground-muted">{t("ui.to")}</span>
                  <Input type="date" value={values[`${f.key}_to`]} onChange={(e) => set(`${f.key}_to`)(e.target.value)} aria-label={`${f.label} ${t("ui.to")}`} />
                </div>
              </div>
            ) : (
              <div key={f.key} className="flex flex-col gap-1.5">
                <span className="text-[13px] font-medium text-foreground">{f.label}</span>
                <Select value={values[f.key]} onValueChange={set(f.key)} options={[{ value: "", label: t("ui.all") }, ...(f.options ?? [])]} placeholder={t("ui.all")} aria-label={f.label} />
              </div>
            ),
          )}
        </div>
        <div className="flex flex-wrap items-center justify-end gap-2">
          <span className="text-[13px] text-foreground-muted">
            {t("ui.shown", {
              shown: shown.length,
              total: rows.length,
            })}
          </span>
          <button type="button" onClick={() => (setValues(empty()), setPill(""))} className={cn(buttonVariants({ variant: "outline", size: "sm" }))}>
            <FilterX /> {t("ui.clear")}
          </button>
          {addHref ? (
            <Link href={addHref} className={cn(buttonVariants({ size: "sm" }))}>
              <Plus /> {addLabel}
            </Link>
          ) : null}
        </div>
      </section>
      <RecordsGrid
        rows={shown}
        rowKey={rowKey}
        exportName={exportName}
        columns={columns}
        rowActions={actions}
        header={{
          title,
          subtitle,
          searchLabel: t("ui.search"),
          searchPlaceholder: t("ui.search_n", { n: rows.length }),
          exportLabel: te("export"),
        }}
        emptyText={emptyText}
      />
    </div>
  );
}

/**
 * One sample document's form state, as every Prototype Desk form keeps it:
 * the loaded row, the form values, errors, and load/save by number — the
 * address bar follows the open document (`?tab=quality&qc=QC-2026-00001`)
 * without a reload.
 */
function useDocForm({ kind, tab, param, noKey, initial, formOf, payloadOf, actions, savedText }) {
  const router = useRouter();
  const toastManager = useToastManager();
  const [row, setRow] = useState(initial);
  const [form, setForm] = useState(() => formOf(initial));
  const [errors, setErrors] = useState(null);
  const [pending, startTransition] = useTransition();
  const set = (key) => (v) => setForm((f) => ({ ...f, [key]: v ?? "" }));
  const take = (data) => (setRow(data), setForm(formOf(data)));
  const address = (no) => window.history.replaceState(null, "", no ? `?tab=${tab}&${param}=${encodeURIComponent(no)}` : `?tab=${tab}`);

  function load(no) {
    address(no);
    setErrors(null);
    if (!no) return take(null);
    startTransition(async () => {
      const result = await actions.load(kind, no);
      if (result?.status === "success") take(result.data);
      else setErrors(messagesOf(result));
    });
  }

  function save() {
    setErrors(null);
    startTransition(async () => {
      const result = await actions.save(kind, row?.[noKey] ?? null, payloadOf(form));
      if (result?.status !== "success") return setErrors(messagesOf(result));
      take(result.data);
      address(result.data[noKey]);
      toastManager.add({
        title: savedText(result.data[noKey]),
        type: "success",
      });
      // Saved past a soft limit (a day sheet over capacity): the warning the server sent.
      if (result.data.warning)
        toastManager.add({
          title: result.data.warning,
          type: "warning",
        });
      router.refresh();
    });
  }

  return { row, form, set, setForm, errors, setErrors, pending, startTransition, load, save, take, address };
}

/** A form card's head: title, hint, the status badge and the Existing … picker. */
function DocHead({ title, hint, badge, existingLabel, newOption, existing, value, onPick, errors }) {
  const t = useT("prototype");
  return (
    <>
      <div className="flex items-start justify-between gap-3">
        <div>
          <h2 className="text-base font-semibold text-foreground">{title}</h2>
          <p className="mt-0.5 text-[13px] text-foreground-muted">{hint}</p>
        </div>
        <span className="rounded-md bg-brand-subtle px-2 py-0.5 text-xs font-medium text-brand">{badge}</span>
      </div>
      <Field label={existingLabel} hint={t("ui.existing_hint")} wide>
        <Select value={value} onValueChange={(v) => onPick(v ?? "")} options={[{ value: "", label: newOption }, ...existing]} aria-label={existingLabel} />
      </Field>
      {errors ? (
        <Alert variant="danger">
          {errors.map((e) => (
            <p key={e}>{e}</p>
          ))}
        </Alert>
      ) : null}
    </>
  );
}

/** A form card's foot: Brickland's rule note, Clear, any extra buttons and Save. */
function DocFoot({ note, onClear, onSave, saveLabel, pending, children }) {
  const t = useT("prototype");
  return (
    <section className={cn(card, "flex flex-wrap items-center justify-between gap-3 px-5 py-3.5")}>
      <p className="max-w-3xl text-[13px] text-foreground-muted">{note}</p>
      <div className="flex gap-2">
        <Button type="button" variant="outline" onClick={onClear} disabled={pending}>
          <RotateCcw /> {t("ui.clear")}
        </Button>
        {children}
        {onSave ? (
          <Button type="button" onClick={onSave} disabled={pending}>
            <Save /> {saveLabel}
          </Button>
        ) : null}
      </div>
    </section>
  );
}

export { DocFoot, DocHead, Field, ProtoList, ReadOnly, Section, blank, card, messagesOf, str, todayIso, useDocForm };
