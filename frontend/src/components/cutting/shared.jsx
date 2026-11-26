"use client";

import { cn } from "@/lib/utils";
import { Input } from "@/components/ui/input";
import { useT } from "@/lib/i18n";

/*
 * What the Cutting Floor screens share (AnnotechERP 19): Brickland's numbered
 * section bar, a labelled field, a free-text pick with suggestions, the native
 * select's look, and small formatters.
 */

const firstError = (res) => (res?.errors ? Object.values(res.errors).flat()[0] : res?.message);
const blank = (v) => (v === "" || v === undefined ? null : v);
const dmy = (d) => (d ? d.split("-").reverse().join("-") : "—");

function useWords() {
  const t = useT("cutting");
  return (k, p) => t(k, p);
}

/** A numbered section with Brickland's coloured heading bar. */
function Section({ n, title, right, tone = "bg-brand", children, className }) {
  return (
    <section className={cn("overflow-hidden rounded-lg border border-border bg-surface", className)}>
      <header className={cn("flex items-center justify-between gap-3 px-4 py-2.5 text-white", tone)}>
        <h2 className="flex items-center gap-2.5 text-[15px] font-semibold">
          <span className="flex size-6 items-center justify-center rounded-full bg-white text-xs font-bold text-foreground">{n}</span>
          {title}
        </h2>
        {right}
      </header>
      <div className="p-4">{children}</div>
    </section>
  );
}

function F({ label, required, hint, children, className }) {
  return (
    <label className={cn("flex flex-col gap-1.5", className)}>
      <span className="text-[13px] font-semibold">
        {label}
        {required ? <span className="ml-0.5 text-danger">*</span> : null}
        {hint ? <span className="ml-1.5 text-xs font-normal text-foreground-muted">{hint}</span> : null}
      </span>
      {children}
    </label>
  );
}

/** A text box with Brickland's "Select or type" list under it. */
function Pick({ id, list, value, onChange, placeholder, disabled, ...rest }) {
  return (
    <>
      <Input list={id} value={value ?? ""} onChange={(e) => onChange(e.target.value)} placeholder={placeholder} disabled={disabled} autoComplete="off" {...rest} />
      <datalist id={id}>
        {list.map((v) => (
          <option key={v} value={v} />
        ))}
      </datalist>
    </>
  );
}

const nativeSelect = "h-9 w-full rounded-md border border-border bg-surface px-3 text-sm outline-none focus:border-brand disabled:opacity-60";

export { firstError, blank, dmy, useWords, Section, F, Pick, nativeSelect };
