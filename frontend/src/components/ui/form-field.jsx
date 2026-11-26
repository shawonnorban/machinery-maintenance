"use client";

import { useId } from "react";
import { cn } from "@/lib/utils";
import { useHorizontalFields } from "@/components/ui/form-section";

/**
 * docs/UI-DESIGN-SYSTEM.md §4/§6: label, required marker, control, helper
 * text, error — shown against the field with the server's own message.
 *
 * Inside a `FormSection` (an ERP entry screen's box) the label sits to the
 * left, right-aligned and bold with the red `*` before it, the way every
 * Brickland form reads; elsewhere (a modal, a narrow panel) it sits above.
 *
 * `children` is a render function `(fieldProps) => <Control {...fieldProps} />`
 * so the control receives a stable `id`/`aria-describedby`/`aria-invalid`
 * without every call site wiring that up by hand.
 *
 * @param {{
 *   label: string, required?: boolean, helperText?: string, error?: string,
 *   className?: string, children: (fieldProps: { id: string, "aria-describedby"?: string, "aria-invalid"?: boolean }) => React.ReactNode,
 * }} props
 */
function FormField({ label, required = false, helperText, error, className, children }) {
  const id = useId();
  const horizontal = useHorizontalFields();
  const helperId = helperText ? `${id}-helper` : undefined;
  const errorId = error ? `${id}-error` : undefined;

  const control = children({
    id,
    "aria-describedby": errorId ?? helperId,
    "aria-invalid": Boolean(error) || undefined,
  });
  const note = error ? (
    <p id={errorId} className="text-xs text-danger">
      {error}
    </p>
  ) : helperText ? (
    <p id={helperId} className="text-xs text-foreground-muted">
      {helperText}
    </p>
  ) : null;

  if (horizontal) {
    return (
      <div className={cn("grid items-start gap-x-3 gap-y-1 sm:grid-cols-[10.5rem_1fr]", className)}>
        <label htmlFor={id} className="pt-2 text-[13px] font-semibold text-foreground sm:text-right">
          {required ? <span className="mr-0.5 text-danger">*</span> : null}
          {label}
        </label>
        <div className="flex min-w-0 flex-col gap-1">
          {control}
          {note}
        </div>
      </div>
    );
  }

  return (
    <div className={cn("flex flex-col gap-1.5", className)}>
      <label htmlFor={id} className="text-sm font-medium text-foreground">
        {label}
        {required ? <span className="ml-0.5 text-danger">*</span> : null}
      </label>
      {control}
      {note}
    </div>
  );
}

export { FormField };
