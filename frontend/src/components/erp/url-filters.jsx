"use client";

import { usePathname, useRouter, useSearchParams } from "next/navigation";
import { FilterX } from "lucide-react";
import { Select } from "@/components/ui/select";
import { Input } from "@/components/ui/input";
import { Button } from "@/components/ui/button";

/**
 * Brickland's filter strip over a list (AnnotechERP UI parity): labelled
 * selects that apply as soon as one changes, "N of M shown" and Clear. Each
 * select is a url parameter, so the other filters are kept when one changes.
 *
 * `type: "date"` makes a filter a date box instead (Shipment from / to);
 * `type: "text"` a search box applied on Enter or on leaving it (Style No.).
 *
 * @param {{ filters: { name: string, label: string, type?: "date" | "text", options?: {value: string, label: string}[], allLabel?: string }[], shown: string, clearLabel: string }} props
 */
function UrlFilters({ filters, shown, clearLabel }) {
  const router = useRouter();
  const pathname = usePathname();
  const params = useSearchParams();
  const editingInPlace = params.get("edit");
  const go = (next) => router.push(`${pathname}${next.size ? `?${next}` : ""}`, { scroll: false });
  const set = (name, value) => {
    const next = new URLSearchParams(params.toString());
    if (value) next.set(name, value);
    else next.delete(name);
    go(next);
  };
  const active = filters.some((f) => params.get(f.name));

  if (editingInPlace) return null;
  return (
    <div className="flex flex-wrap items-end gap-3">
      {filters.map((f) => {
        if (f.type === "text") {
          // A search box (Brickland's Style No. / Order No.): applied on Enter or on leaving the box.
          const apply = (e) => e.target.value.trim() !== (params.get(f.name) ?? "") && set(f.name, e.target.value.trim());
          return (
            <label key={f.name} className="flex w-40 flex-col gap-1.5">
              <span className="text-[13px] font-semibold text-foreground">{f.label}</span>
              <Input key={params.get(f.name) ?? ""} type="search" defaultValue={params.get(f.name) ?? ""} onBlur={apply} onKeyDown={(e) => e.key === "Enter" && apply(e)} />
            </label>
          );
        }
        if (f.type === "date") {
          return (
            <label key={f.name} className="flex w-40 flex-col gap-1.5">
              <span className="text-[13px] font-semibold text-foreground">{f.label}</span>
              <Input type="date" value={params.get(f.name) ?? ""} onChange={(e) => set(f.name, e.target.value)} />
            </label>
          );
        }
        const known = new Set(f.options.map((o) => o.value));
        const value = params.get(f.name) ?? "";
        return (
          <label key={f.name} className="flex w-44 flex-col gap-1.5">
            <span className="text-[13px] font-semibold text-foreground">{f.label}</span>
            <Select
              value={known.has(value) ? value : ""}
              onValueChange={(next) => {
                // The select emits values it never offered when it resets; only known ones navigate.
                if (next !== "" && !known.has(next)) return;
                set(f.name, next);
              }}
              options={[{ value: "", label: f.allLabel }, ...f.options]}
              placeholder={f.allLabel}
            />
          </label>
        );
      })}
      <div className="ml-auto flex items-center gap-3">
        <span className="text-[13px] text-foreground-muted tabular-nums">{shown}</span>
        <Button variant="outline" size="sm" disabled={!active} onClick={() => go(new URLSearchParams())}>
          <FilterX /> {clearLabel}
        </Button>
      </div>
    </div>
  );
}

export { UrlFilters };
