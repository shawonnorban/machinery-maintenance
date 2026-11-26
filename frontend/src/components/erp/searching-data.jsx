"use client";

import { useState } from "react";
import { useRouter } from "next/navigation";
import Link from "next/link";
import { Plus, RefreshCw, RotateCcw, Search } from "lucide-react";
import { Select } from "@/components/ui/select";
import { Input } from "@/components/ui/input";
import { Button, buttonVariants } from "@/components/ui/button";
import { useT } from "@/lib/i18n";

/**
 * The ERP's "Searching Data" card over a list (Commercial & Export, Sales &
 * Shipment …): labelled boxes four to a row, then Search, Reset, the screen's
 * own buttons (Add New) and Refresh. Nothing applies until Search, the same
 * as every Brickland screen.
 *
 * For a list the server filters, `onSearch` receives every field's value
 * ("" for an emptied one) and puts them in the url; Reset sends them all
 * empty.
 *
 * @param {{
 *   fields: { name: string, label: string, type?: "text"|"select"|"date", options?: { value: string, label: string }[], placeholder?: string, all?: boolean }[],
 *   values: Record<string, string>,
 *   onSearch: (values: Record<string, string>) => void,
 *   actions?: React.ReactNode,
 *   addHref?: string | null,
 *   title?: string,
 * }} props
 */
function SearchingData({ fields, values, onSearch, actions = null, addHref = null, title }) {
  const t = useT("erp");
  const router = useRouter();
  const blank = () => Object.fromEntries(fields.map((f) => [f.name, ""]));
  const [draft, setDraft] = useState(() => ({ ...blank(), ...Object.fromEntries(fields.map((f) => [f.name, values?.[f.name] ?? ""])) }));
  const set = (name, value) => setDraft((d) => ({ ...d, [name]: value }));

  return (
    <section className="rounded-lg border border-border bg-surface p-5">
      <h3 className="mb-3 text-[15px] font-semibold">{title ?? t("searching_data")}</h3>
      <form
        className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4"
        onSubmit={(event) => {
          event.preventDefault();
          onSearch(draft);
        }}
      >
        {fields.map((field) => (
          <label key={field.name} className="flex min-w-0 flex-col gap-1.5">
            <span className="text-[13px] font-semibold text-foreground">{field.label}</span>
            {field.type === "select" ? (
              <Select
                value={draft[field.name] ?? ""}
                onValueChange={(value) => set(field.name, value ?? "")}
                // `all: false` for a choice that is never empty (Open only / All breakdowns).
                options={field.all === false ? (field.options ?? []) : [{ value: "", label: t("all") }, ...(field.options ?? []).filter((o) => o.value !== "")]}
                placeholder={field.all === false ? undefined : t("all")}
                aria-label={field.label}
              />
            ) : (
              <Input
                type={field.type === "date" ? "date" : "text"}
                value={draft[field.name] ?? ""}
                placeholder={field.placeholder}
                onChange={(event) => set(field.name, event.target.value)}
                aria-label={field.label}
              />
            )}
          </label>
        ))}
        <div className="flex flex-wrap items-end gap-2 sm:col-span-2 lg:col-span-4">
          <Button type="submit">
            <Search /> {t("search")}
          </Button>
          <Button
            type="button"
            variant="outline"
            onClick={() => {
              setDraft(blank());
              onSearch(blank());
            }}
          >
            <RotateCcw /> {t("reset")}
          </Button>
          {addHref ? (
            <Link href={addHref} className={buttonVariants({ variant: "outline" })}>
              <Plus /> {t("add_new")}
            </Link>
          ) : null}
          {actions}
          <Button type="button" variant="outline" onClick={() => router.refresh()}>
            <RefreshCw /> {t("refresh")}
          </Button>
        </div>
      </form>
    </section>
  );
}

export { SearchingData };
