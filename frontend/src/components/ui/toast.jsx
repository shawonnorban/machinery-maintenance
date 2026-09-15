"use client";

import { Toast } from "@base-ui/react/toast";
import { CheckCircle2, AlertTriangle, XCircle, Info, X } from "lucide-react";
import { cn } from "@/lib/utils";

/**
 * docs/UI-DESIGN-SYSTEM.md §4: transient confirmation. `ToastProvider` wraps
 * the app (see layout.js); call `useToastManager()` from any client
 * component to fire one: `toastManager.add({ title, description, type })`
 * where `type` is "info" | "success" | "warning" | "danger" (default "info").
 */
const ICONS = { info: Info, success: CheckCircle2, warning: AlertTriangle, danger: XCircle };

const TONE = {
  info: "border-info/30 text-info",
  success: "border-success/30 text-success",
  warning: "border-warning/30 text-warning",
  danger: "border-danger/30 text-danger",
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
      <Toast.Viewport className="fixed right-4 bottom-4 z-[60] flex w-80 flex-col gap-2">
        {toasts.map((toastItem) => {
          const Icon = ICONS[toastItem.type ?? "info"];

          return (
            <Toast.Root
              key={toastItem.id}
              toast={toastItem}
              className={cn(
                "flex items-start gap-3 rounded-sm border bg-surface p-4 shadow-lg",
                "transition-all duration-200 ease-out",
                "data-[starting-style]:translate-x-full data-[starting-style]:opacity-0",
                "data-[ending-style]:opacity-0",
                TONE[toastItem.type ?? "info"],
              )}
            >
              <Icon className="mt-0.5 size-4 shrink-0" />
              <div className="flex flex-1 flex-col gap-0.5">
                <Toast.Title className="text-sm font-medium text-foreground" />
                <Toast.Description className="text-sm text-foreground-muted" />
              </div>
              <Toast.Close
                className="shrink-0 rounded-sm p-1 text-foreground-muted hover:bg-surface-muted hover:text-foreground"
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
