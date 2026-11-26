"use client";

import { createContext, useContext } from "react";
import { cn } from "@/lib/utils";
import { useT } from "@/lib/i18n";

/** True inside a FormSection: a FormField there puts its label on the left, as on every ERP entry screen. */
const HorizontalFields = createContext(false);

function useHorizontalFields() {
  return useContext(HorizontalFields);
}

/**
 * One box of an ERP entry form (Commercial & Export's "Master Info", "Link
 * With PO" …): a bordered panel with its name set in the border, holding
 * label-left fields two to a row.
 *
 * `horizontal={false}` keeps labels above, for a box of line items laid out as rows.
 *
 * @param {{ legend?: string, children: React.ReactNode, className?: string, horizontal?: boolean }} props
 */
function FormSection({ legend, children, className, horizontal = true }) {
  const t = useT("erp");
  return (
    <fieldset className={cn("min-w-0 rounded-lg border border-border bg-surface px-5 pt-1 pb-5", className)}>
      <legend className="px-1.5 text-[13px] font-semibold text-brand">{legend ?? t("master_info")}</legend>
      <HorizontalFields.Provider value={horizontal}>
        <div className="mt-2 flex flex-col gap-3">{children}</div>
      </HorizontalFields.Provider>
    </fieldset>
  );
}

/** The bar under an entry form: Back To List, Save and the rest, left to right, in a card of its own. */
function FormActions({ children, className }) {
  return <div className={cn("flex flex-wrap items-center gap-3 rounded-lg border border-border bg-surface p-4", className)}>{children}</div>;
}

export { FormActions, FormSection, useHorizontalFields };
