"use client";

import { createContext, useContext } from "react";
import { cn } from "@/lib/utils";
import { Select } from "@/components/ui/select";

/*
 * The unit pick list (AnnotechERP): every screen that keeps a quantity's unit
 * as its code picks it from the units-of-measure master (Settings → Master
 * Data) instead of typing it, so "Pcs", "pcs" and "PCS" stop being three
 * units. The app layout loads the list once and provides it here.
 */
const UomContext = createContext([]);

/** @param {{ uoms: { code: string, name: string }[], children: React.ReactNode }} props */
function UomProvider({ uoms, children }) {
  return <UomContext.Provider value={uoms}>{children}</UomContext.Provider>;
}

/** The measurement units a garment measurement is written in. */
const MEASURE_UNITS = [
  { code: "CM", name: "Centimetre" },
  { code: "INCH", name: "Inch" },
];

/**
 * A unit picker: the master's units by default, or the list given (measure points).
 * A value outside the list (an old spelling) still shows, so nothing is lost on open.
 *
 * @param {{ id?: string, value: string|null, onChange: (code: string) => void, units?: { code: string, name: string }[], size?: "sm", compact?: boolean, className?: string, disabled?: boolean, "aria-label"?: string }} props
 */
function UomSelect({ id, value, onChange, units, size, compact = size === "sm", className, disabled, "aria-label": ariaLabel }) {
  const master = useContext(UomContext);
  const list = units ?? master;
  // A grid cell has room for the code alone; a form field shows the name beside it.
  const options = [{ value: "", label: "—" }, ...list.map((u) => ({ value: u.code, label: compact ? u.code : `${u.code} — ${u.name}` }))];
  if (value && !list.some((u) => u.code === value)) options.push({ value, label: value });
  return (
    <div className={cn("min-w-0", className)}>
      <Select id={id} value={value ?? ""} onValueChange={(v) => onChange(v ?? "")} options={options} placeholder="—" disabled={disabled} aria-label={ariaLabel} className={size === "sm" ? "h-8" : undefined} />
    </div>
  );
}

export { MEASURE_UNITS, UomProvider, UomSelect };
