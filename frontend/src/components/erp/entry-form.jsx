"use client";

import { startTransition, useActionState, useEffect, useState } from "react";
import { useRouter } from "next/navigation";
import { Plus, RotateCcw, Save, X } from "lucide-react";
import { cn } from "@/lib/utils";
import { Input } from "@/components/ui/input";
import { Select } from "@/components/ui/select";
import { Checkbox } from "@/components/ui/checkbox";
import { Textarea } from "@/components/ui/textarea";
import { Button } from "@/components/ui/button";
import { Alert } from "@/components/ui/alert";
import { useToastManager } from "@/components/ui/toast";
import { useT } from "@/lib/i18n";
import { useScreenWords } from "@/components/erp/screen-words";
import { UomSelect } from "@/components/erp/uom-select";

/** The value a field starts with: the record's, the field's default, or empty. */
function startValue(field, initial) {
  const value = initial?.[field.name];
  if (field.type === "checkbox") return value ?? field.default ?? false;
  if (Array.isArray(value)) return value.join(",");
  if (value !== undefined && value !== null) return String(value);
  return field.default !== undefined && field.default !== null ? String(field.default) : "";
}

function startValues(fields, initial) {
  return Object.fromEntries(fields.map((field) => [field.name, startValue(field, initial)]));
}

/** How many numbers from..to covers, or blank until both are set and in order. */
function spanOf(from, to) {
  if (from === "" || to === "" || from === undefined || to === undefined) return "";
  const n = Number(to) - Number(from) + 1;
  return Number.isFinite(n) && n > 0 ? String(n) : "";
}

/**
 * Brickland's entry form (AnnotechERP UI parity): a card with a title, one line
 * of explanation and the fields in a grid (help text under a field), then a
 * separate action card — a note, Reset and "Save X". The same form serves the
 * "New X" tab and the Edit dialog.
 *
 * `action` is a server action `(previousState, formData)`; it answers
 * `{status: "success"}` or `{status: "error", message, errors}`. Checkboxes
 * are sent as "1"/"0"; an empty field is left out.
 *
 * @param {{
 *   fields: { name: string, label: string, type?: string, required?: boolean, options?: {value: string, label: string}[],
 *             help?: string, placeholder?: string, span?: 1|2|3, readOnly?: boolean }[],
 *   action: Function, initial?: object, title?: string, description?: string, submitLabel: string,
 *   note?: string, columns?: 2|3, successHref?: string, onDone?: () => void, dialog?: boolean,
 *   secondaryLabel?: string,
 * }} props
 *
 * `type: "count"` shows, read only, how many numbers `range: [from, to]`
 * spans (a cheque book's leaves: 1001 to 1050 is 50).
 * `type: "derived"` shows, read only, `values[form[from]]` (the floor of the
 * machine picked). `optionsFrom: {field, map}` makes a select's options
 * `map[form[field]]` (a group's sub-groups). `type: "filepath"` is a text box
 * with an Attach button that posts the file to `upload` and takes back the
 * stored path.
 *
 * `secondaryLabel` adds a second submit button ("Save draft" beside "Submit
 * request"); the action reads which one was pressed from `__intent`
 * ("secondary" or "primary").
 *
 * Brickland's "Edit X" tab: `cancelHref` puts a Cancel link where Reset was;
 * `onCancel` does the same for an edit dialog, closing it.
 * `inline` keeps the submit button inside the card, bottom right, with no
 * separate action card (Festival Calendar's Add Festival). `columns={4}` for a
 * four-across grid. `type: "radio"` is a row of choices; `showWhen: {field,
 * value}` shows a field only while another holds that value (and leaves it
 * out of the post otherwise). `monthsFrom: "<date field>"` turns a select's
 * options into the month of that date and the months either side of it, as
 * YYYY-MM ("Pick the date first" until there is one).
 */
function EntryForm({ fields, action, initial = null, title, description, submitLabel, note, columns = 2, successHref, onDone, dialog = false, secondaryLabel, cancelHref, cancelLabel, onCancel, inline = false }) {
  const t = useT("erp");
  const router = useRouter();
  const toastManager = useToastManager();
  const words = useScreenWords();
  // A new record's plain "Save" reads Brickland's "Save colour" on a screen that has its words.
  if (words && !initial && !dialog && submitLabel === t("save")) submitLabel = words.save;
  const [values, setValues] = useState(() => startValues(fields, initial));
  const [state, dispatch, pending] = useActionState(action, null);
  const [handled, setHandled] = useState(null);
  // Files still uploading: saving now would store the form without them.
  const [uploading, setUploading] = useState(0);
  const onBusy = (busy) => setUploading((n) => Math.max(0, n + (busy ? 1 : -1)));

  // A new record's form empties once it is saved (adjusting state during render).
  if (state !== handled) {
    setHandled(state);
    if (state?.status === "success" && !initial) setValues(startValues(fields, null));
  }

  // What a save means outside this form — the toast, closing a dialog, moving to
  // Records — happens after the render, never during it.
  useEffect(() => {
    if (state?.status !== "success") return;
    // An action may say what happened ("Sent: …"); otherwise "Saved."
    toastManager.add({ title: state.message || t("saved"), type: "success" });
    if (onDone) onDone();
    if (successHref) router.push(successHref);
    router.refresh();
    // Only a new answer should fire this; the callbacks are not stable references.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [state]);

  const set = (name) => (value) => setValues((current) => ({ ...current, [name]: value }));
  const shown = (field) => !field.showWhen || values[field.showWhen.field] === field.showWhen.value;
  const error = (name) => (state?.status === "error" ? state.errors?.[name]?.[0] : null);

  function submit(event) {
    event.preventDefault();
    if (uploading > 0) return;
    const formData = new FormData();
    for (const field of fields) {
      if (field.readOnly || field.type === "derived" || field.type === "count" || !shown(field)) continue;
      const value = values[field.name];
      if (field.type === "checkbox") formData.set(field.name, value ? "1" : "0");
      else if (value !== "" && value !== null && value !== undefined) formData.set(field.name, value);
    }
    if (secondaryLabel) formData.set("__intent", event.nativeEvent?.submitter?.value === "secondary" ? "secondary" : "primary");
    startTransition(() => dispatch(formData));
  }

  const grid = columns === 4 ? "sm:grid-cols-2 lg:grid-cols-4" : columns === 3 ? "sm:grid-cols-2 lg:grid-cols-3" : "sm:grid-cols-2";
  const submitButton = (
    <Button type="submit" value="primary" loading={pending} disabled={uploading > 0}>
      {inline ? <Plus /> : <Save />} {submitLabel}
    </Button>
  );

  return (
    <form onSubmit={submit} className="flex flex-col gap-4">
      <div className={cn("rounded-lg border border-border bg-surface p-5", dialog && "p-4")}>
        {title ? <h2 className="text-base font-semibold text-foreground">{title}</h2> : null}
        {description ? <p className="mt-0.5 text-[13px] text-foreground-muted">{description}</p> : null}
        {state?.status === "error" && !state.errors ? (
          <Alert variant="danger" className="mt-3">
            {state.message}
          </Alert>
        ) : null}

        <div className={cn("mt-4 grid grid-cols-1 gap-x-4 gap-y-4", grid)}>
          {fields.map((field) => {
            const span = field.type === "textarea" || field.type === "multiselect" ? "col-span-full" : field.span === 3 ? "col-span-full" : field.span === 2 ? "sm:col-span-2" : "";
            const id = `f-${field.name}`;

            // A value the form carries but does not show (the list a new entry belongs to).
            if (field.type === "hidden" || !shown(field)) return null;

            if (field.type === "radio") {
              return (
                <div key={field.name} className={cn("flex flex-col gap-1.5", "col-span-full")}>
                  <span className="text-[13px] font-semibold text-foreground">
                    {field.label}
                    {field.required ? <span className="ml-0.5 text-danger">*</span> : null}
                  </span>
                  <div id={id} role="radiogroup" className="flex flex-wrap gap-5 rounded-md border border-border-strong px-3 py-2.5">
                    {(field.options ?? []).map((option) => (
                      <label key={option.value} className="flex cursor-pointer items-center gap-2 text-sm">
                        <input type="radio" name={field.name} value={option.value} checked={values[field.name] === option.value} onChange={() => set(field.name)(option.value)} className="size-4 accent-brand" />
                        {option.label}
                      </label>
                    ))}
                  </div>
                  {field.helpWhen?.[values[field.name]] || field.help ? <p className="text-xs text-foreground-muted">{field.helpWhen?.[values[field.name]] ?? field.help}</p> : null}
                </div>
              );
            }

            if (field.type === "checkbox") {
              return (
                <label key={field.name} htmlFor={id} className={cn("flex items-start gap-2.5 pt-1", span)}>
                  <Checkbox
                    id={id}
                    checked={Boolean(values[field.name])}
                    onCheckedChange={(checked) => set(field.name)(checked === true)}
                    disabled={field.readOnly}
                    className="mt-0.5"
                  />
                  <span>
                    <span className="block text-sm font-medium text-foreground">{field.label}</span>
                    {field.help ? <span className="block text-xs text-foreground-muted">{field.help}</span> : null}
                  </span>
                </label>
              );
            }

            return (
              <div key={field.name} className={cn("flex flex-col gap-1.5", span)}>
                <label htmlFor={id} className="text-[13px] font-semibold text-foreground">
                  {field.label}
                  {field.required ? <span className="ml-0.5 text-danger">*</span> : null}
                </label>
                <FieldControl
                  id={id}
                  field={field.type === "derived" || field.type === "count" ? { ...field, readOnly: true } : field.optionsFrom ? { ...field, options: field.optionsFrom.map[values[field.optionsFrom.field]] ?? [] } : field.monthsFrom ? { ...field, options: monthsAround(values[field.monthsFrom], field.locale) } : field}
                  value={field.type === "derived" ? (field.values?.[values[field.from]] ?? "") : field.type === "count" ? spanOf(values[field.range[0]], values[field.range[1]]) : values[field.name]}
                  onChange={set(field.name)}
                  invalid={Boolean(error(field.name))}
                  placeholder={field.placeholder ?? (field.type === "select" ? t("select") : undefined)}
                  onBusy={onBusy}
                />
                {error(field.name) ? <p className="text-xs text-danger">{error(field.name)}</p> : field.help ? <p className="text-xs text-foreground-muted">{field.help}</p> : null}
              </div>
            );
          })}
        </div>
        {inline ? <div className="mt-4 flex justify-end">{submitButton}</div> : null}
      </div>

      {inline ? null : (
      <div className={cn("flex flex-wrap items-center justify-between gap-3 rounded-lg border border-border bg-surface px-5 py-3.5", dialog && "px-4")}>
        <p className="text-[13px] text-foreground-muted">{note ?? t("saved_note")}</p>
        <div className="flex gap-2">
          {!cancelHref && !onCancel && words && !initial && !dialog && words.secondary === "cancel" ? (
            <Button type="button" variant="outline" onClick={() => router.push("?tab=records")} disabled={pending}>
              <X /> {words.cancel}
            </Button>
          ) : cancelHref || onCancel ? (
            // Brickland's edit dialog and Edit tab close with Cancel, not Reset.
            <Button type="button" variant="outline" onClick={() => (onCancel ? onCancel() : router.push(cancelHref))} disabled={pending}>
              <X /> {cancelLabel ?? t("cancel")}
            </Button>
          ) : (
            <Button type="button" variant="outline" onClick={() => setValues(startValues(fields, initial))} disabled={pending}>
              <RotateCcw /> {words && !initial && words.secondary === "clear" ? words.clear : t("reset")}
            </Button>
          )}
          {secondaryLabel ? (
            <Button type="submit" variant="outline" value="secondary" disabled={pending || uploading > 0}>
              {secondaryLabel}
            </Button>
          ) : null}
          {submitButton}
        </div>
      </div>
      )}
    </form>
  );
}

/** The month of a date and the months either side, as select options (YYYY-MM). */
function monthsAround(date, locale = "en-GB") {
  if (!date || !/^\d{4}-\d{2}/.test(date)) return [];
  const [y, mo] = date.split("-").map(Number);
  return [-1, 0, 1].map((step) => {
    const d = new Date(Date.UTC(y, mo - 1 + step, 1));
    const value = `${d.getUTCFullYear()}-${String(d.getUTCMonth() + 1).padStart(2, "0")}`;
    return { value, label: new Intl.DateTimeFormat(locale, { month: "long", year: "numeric", timeZone: "UTC" }).format(d) };
  });
}

function FieldControl({ id, field, value, onChange, invalid, placeholder, onBusy }) {
  if (field.type === "multiselect") {
    // A set of ticks kept as "A,B,C" — what a LIST setting or a list column takes.
    const chosen = new Set(
      String(value ?? "")
        .split(",")
        .filter(Boolean),
    );
    const toggle = (option, checked) => {
      const next = field.options.map((o) => o.value).filter((v) => (v === option ? checked : chosen.has(v)));
      onChange(next.join(","));
    };
    return (
      <div id={id} className="flex flex-wrap gap-x-4 gap-y-2 rounded-md border border-border-strong px-3 py-2.5">
        {(field.options ?? []).map((option) => (
          <label key={option.value} className="flex items-center gap-1.5 text-sm text-foreground">
            <Checkbox checked={chosen.has(option.value)} onCheckedChange={(checked) => toggle(option.value, checked === true)} disabled={field.readOnly} />
            {option.label}
          </label>
        ))}
      </div>
    );
  }
  // A read-only text block keeps its lines (an AI's summary, the values it found).
  if (field.readOnly && field.type === "textarea") {
    return <Textarea id={id} rows={field.rows ?? 4} value={value ?? ""} readOnly disabled />;
  }
  if (field.readOnly) {
    return <Input id={id} value={value ?? ""} readOnly disabled placeholder={field.placeholder} className={field.type === "derived" || field.type === "count" ? undefined : "font-mono"} />;
  }
  if (field.type === "filepath") {
    return <FilePathControl id={id} field={field} value={value} onChange={onChange} placeholder={placeholder} onBusy={onBusy} />;
  }
  // A unit of measure, picked from the units-of-measure master.
  if (field.type === "uom") {
    return <UomSelect id={id} value={value ?? ""} onChange={onChange} />;
  }
  if (field.type === "select") {
    return <Select id={id} value={value ?? ""} onValueChange={onChange} options={field.options ?? []} placeholder={placeholder} aria-invalid={invalid || undefined} />;
  }
  if (field.type === "textarea") {
    return (
      <Textarea
        id={id}
        rows={field.rows ?? 4}
        value={value ?? ""}
        onChange={(event) => onChange(event.target.value)}
        placeholder={placeholder}
        aria-invalid={invalid || undefined}
      />
    );
  }
  const type = { number: "number", password: "password", email: "email", date: "date", month: "month", time: "time", datetime: "datetime-local" }[field.type] ?? "text";
  if (field.suffix) {
    // A unit inside the box, at its right edge — Brickland's "%" on Efficiency.
    return (
      <div className="relative">
        <Input id={id} type={type} value={value ?? ""} onChange={(event) => onChange(event.target.value)} placeholder={placeholder} autoComplete="off" aria-invalid={invalid || undefined} step={field.step} className="pr-8" />
        <span className="pointer-events-none absolute top-1/2 right-3 -translate-y-1/2 text-sm text-foreground-muted">{field.suffix}</span>
      </div>
    );
  }
  return (
    <Input
      id={id}
      type={type}
      value={value ?? ""}
      onChange={(event) => onChange(event.target.value)}
      placeholder={placeholder}
      autoComplete={type === "password" ? "new-password" : "off"}
      aria-invalid={invalid || undefined}
      step={field.step}
    />
  );
}

/** A stored file's path, typed or filled by uploading one (Finishing Machine Profile's Key Image). */
function FilePathControl({ id, field, value, onChange, placeholder, onBusy }) {
  const t = useT("erp");
  const [busy, setBusy] = useState(false);
  const [problem, setProblem] = useState(null);

  async function upload(file) {
    if (!file) return;
    setBusy(true);
    onBusy?.(true);
    setProblem(null);
    const body = new FormData();
    body.set("file", file);
    try {
      const response = await fetch(field.upload, { method: "POST", body });
      const json = await response.json().catch(() => ({}));
      if (response.ok) onChange(json.data?.path ?? "");
      else setProblem(json.errors ? Object.values(json.errors).flat()[0] : (json.message ?? t("upload_failed")));
    } finally {
      setBusy(false);
      onBusy?.(false);
    }
  }

  return (
    <div className="flex flex-col gap-1">
      <div className="flex gap-2">
        <Input id={id} value={value ?? ""} onChange={(event) => onChange(event.target.value)} placeholder={placeholder} className="flex-1" />
        <label className={cn("inline-flex h-9 cursor-pointer items-center rounded-sm border border-border-strong px-3 text-sm font-medium hover:bg-surface-muted", busy && "pointer-events-none opacity-60")}>
          {busy ? t("uploading") : (field.attachLabel ?? t("attach"))}
          <input type="file" accept={field.accept ?? "image/*"} className="sr-only" onChange={(event) => upload(event.target.files?.[0])} />
        </label>
      </div>
      {problem ? <p className="text-xs text-danger">{problem}</p> : null}
    </div>
  );
}

export { EntryForm };
