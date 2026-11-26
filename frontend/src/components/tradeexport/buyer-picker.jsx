"use client";

import { useMemo, useState } from "react";
import Link from "next/link";
import { Search } from "lucide-react";
import { cn } from "@/lib/utils";
import { Input } from "@/components/ui/input";

/**
 * Trade & Export → Buyer-Wise Notify Party (AnnotechERP 22/01): Brickland's
 * "Select Buyer" box — a search and the buyer list; picking one puts it in
 * the url, and the form and the notify party list beside it follow.
 *
 * @param {{ title: string, buyers: {value: string, label: string}[], active?: string, hrefFor: string, placeholder: string, empty: string }} props
 */
function BuyerPicker({ title, buyers, active, hrefFor, placeholder, empty }) {
  const [query, setQuery] = useState("");
  const shown = useMemo(() => buyers.filter((b) => b.label.toLowerCase().includes(query.trim().toLowerCase())), [buyers, query]);
  return (
    <div className="self-start rounded-lg border border-border bg-surface">
      <div className="px-4 pt-4 pb-3 text-[15px] font-semibold">{title}</div>
      <div className="relative px-4 pb-3">
        <Search className="pointer-events-none absolute top-[18px] left-7 size-4 -translate-y-1/2 text-foreground-subtle" />
        <Input value={query} onChange={(e) => setQuery(e.target.value)} placeholder={placeholder} className="pl-9" />
      </div>
      <ul className="border-t border-border">
        {shown.map((b) => (
          <li key={b.value}>
            <Link href={`${hrefFor}${b.value}`} className={cn("block px-4 py-2.5 text-sm hover:bg-surface-muted", active === b.value && "bg-brand-subtle font-semibold text-brand")}>
              {b.label}
            </Link>
          </li>
        ))}
        {shown.length === 0 ? <li className="px-4 py-3 text-sm text-foreground-muted">{empty}</li> : null}
      </ul>
    </div>
  );
}

export { BuyerPicker };
