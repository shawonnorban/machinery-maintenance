"use client";

import { useEffect, useMemo, useRef, useState, useSyncExternalStore } from "react";
import { Columns3 } from "lucide-react";
import { Button } from "@/components/ui/button";
import { Checkbox } from "@/components/ui/checkbox";

/**
 * Which columns a wide sheet shows (Norban's "Columns" / "Column View" panel,
 * used by Dev Sample Status, Order Summary and Knit Woven): ticks per column,
 * All / None, Apply and Reset to the screen's default. The choice is this
 * viewer's own — kept in the browser under `storageKey`.
 *
 * `useColumnView(key, all, defaults)` returns `[visible, setVisible]`, the
 * chosen keys in the sheet's own order.
 *
 * @param {{
 *   columns: { key: string, label: string, group?: string }[],
 *   visible: string[], defaults: string[], onChange: (keys: string[]) => void,
 *   labels: { button: string, all: string, none: string, apply: string, reset: string, hint?: string },
 * }} props
 */
function ColumnView({ columns, visible, defaults, onChange, labels }) {
  const [open, setOpen] = useState(false);
  const [draft, setDraft] = useState(visible);
  const box = useRef(null);

  useEffect(() => {
    if (!open) return undefined;
    const close = (event) => {
      if (box.current && !box.current.contains(event.target)) setOpen(false);
    };
    document.addEventListener("mousedown", close);
    return () => document.removeEventListener("mousedown", close);
  }, [open]);

  const toggle = (key) => setDraft((d) => (d.includes(key) ? d.filter((k) => k !== key) : [...d, key]));
  const ordered = (keys) => columns.map((c) => c.key).filter((k) => keys.includes(k));
  const groups = [...new Set(columns.map((c) => c.group ?? ""))];

  return (
    <div className="relative" ref={box}>
      <Button
        type="button"
        variant="outline"
        size="sm"
        aria-expanded={open}
        onClick={() => {
          setDraft(visible);
          setOpen((o) => !o);
        }}
      >
        <Columns3 /> {labels.button}
      </Button>
      {open ? (
        <div role="dialog" aria-label={labels.button} className="absolute right-0 z-30 mt-1 w-[min(36rem,90vw)] rounded-lg border border-border bg-surface p-3 shadow-lg">
          {labels.hint ? <p className="mb-2 text-xs text-foreground-muted">{labels.hint}</p> : null}
          <div className="max-h-80 overflow-y-auto pr-1">
            {groups.map((g) => (
              <div key={g || "_"} className="mb-2">
                {g ? <p className="mb-1 text-[11px] font-semibold uppercase tracking-wide text-brand">{g}</p> : null}
                <div className="grid grid-cols-2 gap-x-3 gap-y-1 sm:grid-cols-3">
                  {columns
                    .filter((c) => (c.group ?? "") === g)
                    .map((c) => (
                      <label key={c.key} className="flex cursor-pointer items-center gap-2 text-[13px]">
                        <Checkbox checked={draft.includes(c.key)} onCheckedChange={() => toggle(c.key)} aria-label={c.label} />
                        <span className="truncate">{c.label}</span>
                      </label>
                    ))}
                </div>
              </div>
            ))}
          </div>
          <div className="mt-3 flex gap-2 border-t border-border pt-3">
            <Button type="button" size="sm" variant="outline" onClick={() => setDraft(columns.map((c) => c.key))}>
              {labels.all}
            </Button>
            <Button type="button" size="sm" variant="outline" onClick={() => setDraft([])}>
              {labels.none}
            </Button>
            <Button type="button" size="sm" variant="outline" onClick={() => setDraft(defaults)}>
              {labels.reset}
            </Button>
            <Button
              type="button"
              size="sm"
              className="ml-auto"
              onClick={() => {
                onChange(ordered(draft));
                setOpen(false);
              }}
            >
              {labels.apply}
            </Button>
          </div>
        </div>
      ) : null}
    </div>
  );
}

const listeners = new Set();

function subscribe(callback) {
  listeners.add(callback);
  window.addEventListener("storage", callback);
  return () => {
    listeners.delete(callback);
    window.removeEventListener("storage", callback);
  };
}

function readStored(key) {
  try {
    return window.localStorage.getItem(key);
  } catch {
    return null;
  }
}

/**
 * The viewer's chosen columns for one sheet, kept in the browser; the
 * screen's default until they choose (on the server, and wherever storage is
 * unavailable — then the choice holds for this visit only).
 */
function useColumnView(storageKey, allKeys, defaults) {
  const stored = useSyncExternalStore(subscribe, () => readStored(storageKey), () => null);
  const [unsaved, setUnsaved] = useState(null);
  const visible = useMemo(() => {
    let chosen = unsaved;
    if (!chosen && stored) {
      try {
        chosen = JSON.parse(stored);
      } catch {
        chosen = null;
      }
    }
    return Array.isArray(chosen) ? allKeys.filter((k) => chosen.includes(k)) : defaults;
  }, [stored, unsaved, allKeys, defaults]);
  const choose = (keys) => {
    try {
      window.localStorage.setItem(storageKey, JSON.stringify(keys));
      listeners.forEach((l) => l());
    } catch {
      setUnsaved(keys);
    }
  };
  return [visible, choose];
}

export { ColumnView, useColumnView };
