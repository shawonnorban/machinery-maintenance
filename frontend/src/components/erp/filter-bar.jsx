"use client";

import { useEffect, useRef, useState } from "react";
import { useRouter, useSearchParams } from "next/navigation";
import { RotateCcw, Search } from "lucide-react";
import { Select } from "@/components/ui/select";
import { Input } from "@/components/ui/input";
import { Button } from "@/components/ui/button";
import { useT } from "@/lib/i18n";

/**
 * Brickland's filter card above a log or report (Integration Activity Log,
 * Device Punch Log …): a row of fields with Show and Reset. The choice lives in
 * the url, so a filtered view can be bookmarked and the server does the
 * filtering. `instant` is the Knit / Dye / Finishing lists' strip: a select
 * applies as soon as it changes, a box shortly after typing stops, and only
 * Clear is offered.
 *
 * @param {{
 *   basePath: string, values: object, keep?: object,
 *   fields: { name: string, label: string, type?: "select"|"date"|"text", options?: {value: string, label: string}[], placeholder?: string }[],
 *   labels: { show: string, reset: string }, instant?: boolean,
 * }} props
 */
function FilterBar({ basePath, fields, values, keep = {}, labels, title, hint, instant = false }) {
  const te = useT("erp");
  const router = useRouter();
  // A row being edited in place (Brickland's "Edit X") shows only its form.
  const editingInPlace = useSearchParams().get("edit");
  const [draft, setDraft] = useState(() => Object.fromEntries(fields.map((f) => [f.name, values[f.name] ?? ""])));

  const typing = useRef(null);

  function go(next) {
    const params = new URLSearchParams();
    for (const [key, value] of Object.entries({ ...keep, ...next })) if (value) params.set(key, value);
    router.push(`${basePath}${params.size ? `?${params}` : ""}`, { scroll: false });
  }

  useEffect(() => () => clearTimeout(typing.current), []);

  /** Set one field; an instant strip applies it (a typed box once typing pauses). */
  function change(name, value, typed = false) {
    const next = { ...draft, [name]: value };
    setDraft(next);
    if (!instant) return;
    clearTimeout(typing.current);
    if (typed) typing.current = setTimeout(() => go(next), 450);
    else go(next);
  }

  if (editingInPlace) return null;
  return (
    <div className="mb-4 flex flex-wrap items-end gap-3 rounded-lg border border-border bg-surface p-4">
      {title ? (
        // A titled panel (Brickland's "Searching Data") heads its boxes with what they do.
        <div className="w-full">
          <h2 className="text-[15px] font-semibold">{title}</h2>
          {hint ? <p className="mt-0.5 text-[13px] text-foreground-muted">{hint}</p> : null}
        </div>
      ) : null}
      {fields.map((field) => (
        <label key={field.name} className="flex min-w-44 flex-col gap-1.5">
          <span className="text-[13px] font-semibold text-foreground">{field.label}</span>
          {field.type === "select" ? (
            <Select
              value={draft[field.name]}
              onValueChange={(v) => {
                // The select emits values it never offered when it resets; only a real change counts.
                const next = v ?? "";
                if (next === draft[field.name] || (next !== "" && !(field.options ?? []).some((o) => o.value === next))) return;
                change(field.name, next);
              }}
              options={field.options ?? []}
              placeholder={field.placeholder ?? te("select")}
            />
          ) : (
            <Input type={field.type === "date" ? "date" : "text"} value={draft[field.name]} onChange={(e) => change(field.name, e.target.value, field.type !== "date")} placeholder={field.placeholder} />
          )}
        </label>
      ))}
      <div className="flex gap-2">
        {instant ? null : (
          <Button onClick={() => go(draft)}>
            <Search /> {labels.show}
          </Button>
        )}
        <Button
          variant="outline"
          onClick={() => {
            clearTimeout(typing.current);
            setDraft(Object.fromEntries(fields.map((f) => [f.name, ""])));
            go({});
          }}
        >
          <RotateCcw /> {labels.reset}
        </Button>
      </div>
    </div>
  );
}

export { FilterBar };
