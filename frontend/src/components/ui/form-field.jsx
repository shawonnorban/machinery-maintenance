import { useId } from "react";
import { cn } from "@/lib/utils";

/**
 * docs/UI-DESIGN-SYSTEM.md §4/§6: label, required marker, control, helper
 * text, error — shown against the field with the server's own message.
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
  const helperId = helperText ? `${id}-helper` : undefined;
  const errorId = error ? `${id}-error` : undefined;

  return (
    <div className={cn("flex flex-col gap-1.5", className)}>
      <label htmlFor={id} className="text-sm font-medium text-foreground">
        {label}
        {required ? <span className="ml-0.5 text-danger">*</span> : null}
      </label>

      {children({
        id,
        "aria-describedby": errorId ?? helperId,
        "aria-invalid": Boolean(error) || undefined,
      })}

      {error ? (
        <p id={errorId} className="text-xs text-danger">
          {error}
        </p>
      ) : helperText ? (
        <p id={helperId} className="text-xs text-foreground-muted">
          {helperText}
        </p>
      ) : null}
    </div>
  );
}

export { FormField };
