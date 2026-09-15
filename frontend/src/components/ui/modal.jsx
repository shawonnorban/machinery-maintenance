"use client";

import { Dialog } from "@base-ui/react/dialog";
import { X } from "lucide-react";
import { cn } from "@/lib/utils";

/**
 * docs/UI-DESIGN-SYSTEM.md §4: focus trap, Escape to close, backdrop click,
 * scroll lock — all handled by Base UI's Dialog primitive underneath.
 *
 * @param {{
 *   open: boolean, onOpenChange: (open: boolean) => void, title: string,
 *   description?: string, children?: React.ReactNode, footer?: React.ReactNode,
 *   className?: string,
 * }} props
 */
function Modal({ open, onOpenChange, title, description, children, footer, className }) {
  return (
    <Dialog.Root open={open} onOpenChange={onOpenChange}>
      <Dialog.Portal>
        <Dialog.Backdrop className="fixed inset-0 z-50 bg-foreground/40 transition-opacity duration-150 ease-out data-[starting-style]:opacity-0 data-[ending-style]:opacity-0" />
        <Dialog.Popup
          className={cn(
            "fixed top-1/2 left-1/2 z-50 w-[calc(100vw-2rem)] max-w-lg -translate-x-1/2 -translate-y-1/2",
            "rounded-sm border border-border bg-surface p-6 shadow-lg outline-none",
            "transition-all duration-150 ease-out data-[starting-style]:scale-95 data-[starting-style]:opacity-0",
            "data-[ending-style]:scale-95 data-[ending-style]:opacity-0",
            className,
          )}
        >
          <div className="mb-4 flex items-start justify-between gap-4">
            <div>
              <Dialog.Title className="text-lg font-semibold text-foreground">{title}</Dialog.Title>
              {description ? (
                <Dialog.Description className="mt-1 text-sm text-foreground-muted">
                  {description}
                </Dialog.Description>
              ) : null}
            </div>
            <Dialog.Close
              className="shrink-0 rounded-sm p-1 text-foreground-muted transition-colors duration-150 ease-out hover:bg-surface-muted hover:text-foreground"
              aria-label="Close"
            >
              <X className="size-4" />
            </Dialog.Close>
          </div>

          {children}

          {footer ? <div className="mt-6 flex justify-end gap-2">{footer}</div> : null}
        </Dialog.Popup>
      </Dialog.Portal>
    </Dialog.Root>
  );
}

export { Modal };
