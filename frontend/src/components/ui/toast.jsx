"use client";

import { Toast } from "@base-ui/react/toast";
import { CheckCircle2, AlertTriangle, XCircle, Info, X } from "lucide-react";
import { cn } from "@/lib/utils";

/**
 * docs/UI-DESIGN-SYSTEM.md §4: transient confirmation. `ToastProvider` wraps
 * the app (see layout.js); call `useToastManager()` from any client
 * component to fire one: `toastManager.add({ title, description, type })`
 * where `type` is "info" | "success" | "warning" | "danger" (default "info").
 * Shown at the top, in the middle (user, 2026-11-25) — never over what is being
 * worked on below; a refusal (danger) is larger and filled, to be seen from a floor.
 */
const ICONS = { info: Info, success: CheckCircle2, warning: AlertTriangle, danger: XCircle };

const TONE = {
  info: "border-info/30 text-info",
  success: "border-success/30 text-success",
  warning: "border-warning/30 text-warning",
  danger: "border-danger bg-danger text-white [&_h2]:text-base [&_h2]:font-semibold [&_h2]:text-white [&_p]:text-white",
};

function ToastProvider({ children }) {
  return (
    <Toast.Provider>
      {children}
      <ToastViewport />
    </Toast.Provider>
  );
}

function ToastViewport() {
  const { toasts } = Toast.useToastManager();

  return (
    <Toast.Portal>
      <Toast.Viewport className="pointer-events-none fixed top-4 left-1/2 z-[60] flex w-[calc(100vw-2rem)] max-w-md -translate-x-1/2 flex-col gap-2">
        {toasts.map((toastItem) => {
          // An unknown type falls back to info rather than rendering nothing.
          const Icon = ICONS[toastItem.type] ?? ICONS.info;

          return (
            <Toast.Root
              key={toastItem.id}
              toast={toastItem}
              className={cn(
                "pointer-events-auto flex items-start gap-3 rounded-sm border bg-surface p-4 shadow-lg",
                "transition-all duration-200 ease-out",
                "data-[starting-style]:-translate-y-full data-[starting-style]:opacity-0",
                "data-[ending-style]:opacity-0",
                TONE[toastItem.type ?? "info"],
              )}
            >
              <Icon className={cn("mt-0.5 shrink-0", toastItem.type === "danger" ? "size-5" : "size-4")} />
              <div className="flex flex-1 flex-col gap-0.5">
                <Toast.Title className="text-sm font-medium text-foreground" />
                <Toast.Description className="text-sm text-foreground-muted" />
              </div>
              <Toast.Close
                className={cn("shrink-0 rounded-sm p-1", toastItem.type === "danger" ? "text-white hover:bg-white/20" : "text-foreground-muted hover:bg-surface-muted hover:text-foreground")}
                aria-label="Dismiss"
              >
                <X className="size-4" />
              </Toast.Close>
            </Toast.Root>
          );
        })}
      </Toast.Viewport>
    </Toast.Portal>
  );
}

const useToastManager = Toast.useToastManager;

export { ToastProvider, useToastManager };
