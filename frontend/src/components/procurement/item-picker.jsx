"use client";

import { useEffect, useRef, useState } from "react";
import { Search, X } from "lucide-react";
import { Select } from "@/components/ui/select";
import { Input } from "@/components/ui/input";
import { Button } from "@/components/ui/button";
import { cn } from "@/lib/utils";
import { useT } from "@/lib/i18n";

/**
 * Picks one item from the library (Master Data spec §2): item department
 * first, then a typed search against code and name. Nobody types an item
 * name into a document — if it is not here, an admin adds it to the library.
 *
 * Once chosen the line shows the item's code and name with a clear button;
 * the department + search controls only appear while nothing is chosen, so
 * a ten-line requisition is not thirty dropdowns.
 *
 * `departmentId` may be pinned by the parent (a floor requisition targets
 * one department); when it is, the department select is hidden.
 */
function ItemPicker({ value, onChange, departments, searchItems, departmentId: pinnedDepartmentId, error }) {
  const t = useT("procurement");
  const [departmentId, setDepartmentId] = useState(pinnedDepartmentId ?? "");
  const [query, setQuery] = useState("");
  const [results, setResults] = useState([]);
  const [searching, setSearching] = useState(false);
  const [open, setOpen] = useState(false);
  const requestId = useRef(0);
  const wrapper = useRef(null);

  const effectiveDepartment = pinnedDepartmentId ?? departmentId;

  // Debounced lookup; state is only touched from the timer callback, never
  // synchronously in the effect body.
  useEffect(() => {
    if (value || !effectiveDepartment) return undefined;
    const current = ++requestId.current;
    const timeout = setTimeout(async () => {
      setSearching(true);
      const result = await searchItems({ departmentId: effectiveDepartment, search: query });
      if (current !== requestId.current) return;
      setResults(result.items ?? []);
      setSearching(false);
    }, 150);
    return () => clearTimeout(timeout);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [effectiveDepartment, query, value]);

  // The list opens only when the search box is clicked/focused and closes
  // on a click anywhere outside it — never on its own after a fetch, so
  // choosing a department does not drop a list over the rest of the form.
  useEffect(() => {
    if (!open) return undefined;
    function onPointerDown(event) {
      if (wrapper.current && !wrapper.current.contains(event.target)) setOpen(false);
    }
    document.addEventListener("mousedown", onPointerDown);
    return () => document.removeEventListener("mousedown", onPointerDown);
  }, [open]);

  function changeDepartment(id) {
    setDepartmentId(id);
    setResults([]);
    setQuery("");
  }

  if (value) {
    return (
      <div className={cn("flex h-9 items-center justify-between gap-2 rounded-sm border border-border bg-surface-muted px-3 text-sm", error && "border-danger")}>
        <span className="truncate">
          <span className="font-medium">{value.name}</span>
          <span className="ml-2 font-mono text-xs text-foreground-muted">{value.item_code}</span>
        </span>
        <Button type="button" variant="ghost" size="icon" className="size-7 shrink-0" aria-label={t("remove_line")} onClick={() => onChange(null)}>
          <X className="size-4" />
        </Button>
      </div>
    );
  }

  const departmentOptions = departments.map((d) => ({ value: d.id, label: d.name }));

  return (
    <div className="flex flex-col gap-2">
      {pinnedDepartmentId ? null : (
        <Select value={departmentId} onValueChange={changeDepartment} options={departmentOptions} placeholder={t("item_department")} />
      )}
      <div className="relative" ref={wrapper}>
        <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-foreground-subtle" />
        <Input
          value={query}
          onChange={(event) => setQuery(event.target.value)}
          onFocus={() => setOpen(true)}
          onClick={() => setOpen(true)}
          onKeyDown={(event) => {
            if (event.key === "Escape") setOpen(false);
          }}
          placeholder={effectiveDepartment ? t("search_items") : t("pick_department_first")}
          disabled={!effectiveDepartment}
          className={cn("pl-9", error && "border-danger")}
        />
        {open && effectiveDepartment ? (
          <ul className="absolute z-20 mt-1 max-h-64 w-full overflow-auto rounded-sm border border-border bg-surface py-1 shadow-lg">
            {searching ? (
              <li className="px-3 py-2 text-xs text-foreground-muted">…</li>
            ) : results.length === 0 ? (
              <li className="px-3 py-2 text-xs text-foreground-muted">{t("no_items_in_department")}</li>
            ) : (
              results.map((item) => (
                <li key={item.id}>
                  <button
                    type="button"
                    className="flex w-full flex-col items-start px-3 py-1.5 text-left text-sm hover:bg-surface-muted"
                    onMouseDown={(event) => event.preventDefault()}
                    onClick={() => {
                      onChange(item);
                      setQuery("");
                      setOpen(false);
                    }}
                  >
                    <span className="w-full truncate font-medium">{item.name}</span>
                    <span className="font-mono text-xs text-foreground-muted">
                      {item.item_code}
                      {item.uom?.code ? <span className="ml-2 font-sans">· {item.uom.code}</span> : null}
                    </span>
                  </button>
                </li>
              ))
            )}
          </ul>
        ) : null}
      </div>
      {error ? <p className="text-xs text-danger">{error}</p> : null}
    </div>
  );
}

export { ItemPicker };
