"use client";

import { AlertDialog } from "@base-ui/react/alert-dialog";
import { cn } from "@/lib/utils";
import { Button } from "@/components/ui/button";

/**
 * docs/UI-DESIGN-SYSTEM.md §4: "destructive confirmation, built on Modal" —
 * uses Base UI's AlertDialog (Modal's assertive sibling: cannot be dismissed
 * by a backdrop click, only by an explicit choice) rather than Dialog, since
 * an accidental dismiss of a destructive confirmation is its own failure mode.
 *
 * @param {{
 *   open: boolean, onOpenChange: (open: boolean) => void, title: string,
 *   description?: string, confirmLabel?: string, cancelLabel?: string,
 *   destructive?: boolean, loading?: boolean, onConfirm: () => void,
 * }} props
 */
function ConfirmDialog({
  open,
  onOpenChange,
  title,
  description,
  confirmLabel = "Confirm",
  cancelLabel = "Cancel",
  destructive = true,
  loading = false,
  onConfirm,
}) {
  return (
    <AlertDialog.Root open={open} onOpenChange={onOpenChange}>
      <AlertDialog.Portal>
        <AlertDialog.Backdrop className="fixed inset-0 z-50 bg-foreground/40 transition-opacity duration-150 ease-out data-[starting-style]:opacity-0 data-[ending-style]:opacity-0" />
        <AlertDialog.Popup
          className={cn(
            "fixed top-1/2 left-1/2 z-50 w-[calc(100vw-2rem)] max-w-md -translate-x-1/2 -translate-y-1/2",
            "rounded-sm border border-border bg-surface p-6 shadow-lg outline-none",
            "transition-all duration-150 ease-out data-[starting-style]:scale-95 data-[starting-style]:opacity-0",
            "data-[ending-style]:scale-95 data-[ending-style]:opacity-0",
          )}
        >
          <AlertDialog.Title className="text-lg font-semibold text-foreground">{title}</AlertDialog.Title>
          {description ? (
            <AlertDialog.Description className="mt-1 text-sm text-foreground-muted">
              {description}
            </AlertDialog.Description>
          ) : null}

          <div className="mt-6 flex justify-end gap-2">
            <Button variant="outline" onClick={() => onOpenChange(false)} disabled={loading}>
              {cancelLabel}
            </Button>
            <Button variant={destructive ? "danger" : "primary"} onClick={onConfirm} loading={loading}>
              {confirmLabel}
            </Button>
          </div>
        </AlertDialog.Popup>
      </AlertDialog.Portal>
    </AlertDialog.Root>
  );
}

export { ConfirmDialog };
