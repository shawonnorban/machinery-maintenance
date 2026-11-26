"use client";

import { useState, useTransition } from "react";
import { Plus, RotateCcw, Search, Trash2 } from "lucide-react";
import { cn } from "@/lib/utils";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Select } from "@/components/ui/select";
import { useT } from "@/lib/i18n";

/*
 * What the Store & Material screens share (AnnotechERP 09): the filter row
 * Brickland puts on its views (buyer → PO → style → material, a date range),
 * the employee field a receiver is found by, the roll-wise entry a fabric
 * line is received in, and the small formatting helpers.
 */

const card = "rounded-xl border border-border bg-surface";
const dateFmt = new Intl.DateTimeFormat("en-GB", {
  day: "2-digit",
  month: "short",
  year: "numeric",
  timeZone: "Asia/Dhaka",
});
const day = (iso) => (iso ? dateFmt.format(new Date(iso)) : "—");
const qty = (n) =>
  Number(n ?? 0).toLocaleString("en-US", { maximumFractionDigits: 3 });
const dash = (v) => (v === null || v === undefined || v === "" ? "—" : v);
const messagesOf = (result) => result?.errors ?? { _: [result?.message ?? ""] };
const blankFilters = {
  buyer: "",
  po: "",
  style: "",
  material: "",
  from: "",
  to: "",
};

/**
 * The filter row: the fields asked for, Search and Reset. It hands the filled
 * values (empty ones dropped) to `onSearch`.
 *
 * @param {{ fields: string[], options: object, onSearch: (f: object) => void, pending?: boolean }} props
 */
function StoreFilters({ fields, options, onSearch, pending }) {
  const t = useT("storematerial");
  const [f, setF] = useState(blankFilters);
  const set = (k) => (v) => setF((all) => ({ ...all, [k]: v ?? "" }));
  const lists = {
    buyer: options.buyers,
    po: options.pos,
    style: options.styles,
    material: options.materials,
    category: options.categories,
  };
  const labels = {
    buyer: "buyer",
    po: "po",
    style: "style",
    material: "material",
    category: "category",
    from: "from",
    to: "to",
  };
  const filled = (all) =>
    Object.fromEntries(Object.entries(all).filter(([, v]) => v));

  return (
    <form
      className={cn(
        card,
        "grid gap-3 p-4 sm:grid-cols-2",
        // One row on a wide screen, as Brickland lays its filters: the fields, then Search and Reset.
        {
          2: "lg:grid-cols-[repeat(2,minmax(0,1fr))_auto]",
          3: "lg:grid-cols-[repeat(3,minmax(0,1fr))_auto]",
          5: "lg:grid-cols-[repeat(5,minmax(0,1fr))_auto]",
          6: "lg:grid-cols-[repeat(6,minmax(0,1fr))_auto]",
        }[fields.length],
      )}
      onSubmit={(ev) => {
        ev.preventDefault();
        onSearch(filled(f));
      }}
    >
      {fields.map((k) => (
        <label key={k} className="flex flex-col gap-1.5">
          <span className="text-[13px] font-medium text-foreground">
            {t(`f.${labels[k]}`)}
          </span>
          {k === "from" || k === "to" ? (
            <Input
              type="date"
              value={f[k] ?? ""}
              onChange={(ev) => set(k)(ev.target.value)}
              aria-label={t(`f.${labels[k]}`)}
            />
          ) : (
            <Select
              value={f[k] ?? ""}
              onValueChange={set(k)}
              placeholder={t("f.all")}
              options={[
                { value: "", label: t("f.all") },
                ...(lists[k] ?? []).map((o) => ({
                  value: o.value,
                  label: o.label,
                })),
              ]}
              aria-label={t(`f.${labels[k]}`)}
            />
          )}
        </label>
      ))}
      <div className="flex items-end gap-2">
        <Button type="submit" loading={pending}>
          <Search className="size-4" aria-hidden />
          {t("f.search")}
        </Button>
        <Button
          type="button"
          variant="outline"
          onClick={() => {
            setF(blankFilters);
            onSearch({});
          }}
        >
          <RotateCcw className="size-4" aria-hidden />
          {t("f.reset")}
        </Button>
      </div>
    </form>
  );
}

/**
 * An employee found by ID or card / proxy number: the search button looks
 * them up and shows the name, or Brickland's "No employee with …" message.
 *
 * @param {{ label: string, value: string, onChange: (v: string) => void, error?: string, lookup: (id: string) => Promise<object> }} props
 */
function EmployeeField({ label, required, value, onChange, error, lookup }) {
  const t = useT("storematerial");
  const [found, setFound] = useState(null);
  const [miss, setMiss] = useState(null);
  const [pending, startTransition] = useTransition();

  function find() {
    setFound(null);
    setMiss(null);
    if (!value.trim()) return setMiss(t("employee_required"));
    startTransition(async () => {
      const result = await lookup(value.trim());
      if (result?.status === "success")
        setFound({ ...result.data, for: value.trim() });
      else
        setMiss(Object.values(result?.errors ?? {})[0]?.[0] ?? result?.message);
    });
  }

  return (
    <label className="flex flex-col gap-1.5">
      <span className="text-[13px] font-medium text-foreground">
        {label}
        {required ? <span className="text-danger"> *</span> : null}
      </span>
      <div className="flex gap-2">
        <Input
          value={value}
          onChange={(ev) => {
            onChange(ev.target.value);
            setFound(null);
            setMiss(null);
          }}
          onBlur={() => value.trim() && !found && find()}
          placeholder={t("employee_ph")}
          aria-label={label}
        />
        <Button
          type="button"
          variant="primary"
          size="icon"
          onClick={find}
          loading={pending}
          aria-label={t("find_employee")}
        >
          <Search className="size-4" aria-hidden />
        </Button>
      </div>
      {error ? (
        <span className="text-xs text-danger">{error}</span>
      ) : miss ? (
        <span className="text-xs text-danger">{miss}</span>
      ) : found && found.for === value.trim() ? (
        // Only while the field still holds the number it was found by — a reset form drops it.
        <span className="text-xs text-success">{found.label}</span>
      ) : null}
    </label>
  );
}

const blankRoll = () => ({
  roll_no: "",
  dye_lot: "",
  gsm: "",
  width: "",
  qty: "",
});

/**
 * A fabric line received roll by roll: each roll's number, dye lot, GSM,
 * width and quantity. Optional — a line with no rolls is received as a
 * whole; once rolls are entered they must add up to the line's quantity.
 *
 * @param {{ rolls: object[], onChange: (rolls: object[]) => void, uom: string, lineQty: number, error?: string, label: string }} props
 */
function RollEntry({ rolls, onChange, uom, lineQty, error, label }) {
  const t = useT("storematerial");
  const r = (k, p) => t(`roll.${k}`, p);
  const total = rolls.reduce((sum, x) => sum + Number(x.qty || 0), 0);
  const off =
    rolls.length > 0 && Math.abs(total - Number(lineQty || 0)) > 0.0005;
  const setRoll = (i, k) => (ev) =>
    onChange(
      rolls.map((x, j) => (j === i ? { ...x, [k]: ev.target.value } : x)),
    );

  return (
    <div className="flex flex-col gap-2 rounded-lg border border-dashed border-border bg-surface-muted/40 p-3">
      <div className="flex flex-wrap items-center justify-between gap-2">
        <p className="text-[13px] font-semibold text-foreground">
          {r("title")}{" "}
          <span className="font-normal text-foreground-muted">{r("hint")}</span>
        </p>
        <Button
          type="button"
          variant="outline"
          size="sm"
          onClick={() => onChange([...rolls, blankRoll()])}
          aria-label={`${r("add")} ${label}`}
        >
          <Plus className="size-4" aria-hidden />
          {r("add")}
        </Button>
      </div>
      {rolls.length ? (
        <div className="overflow-x-auto">
          <table className="w-full min-w-[640px] text-sm">
            <thead>
              <tr className="text-left text-xs font-semibold uppercase text-foreground-muted">
                {["roll_no", "dye_lot", "gsm", "width", "qty"].map((k) => (
                  <th
                    key={k}
                    className={cn("px-2 py-1.5", k === "qty" && "text-right")}
                  >
                    {r(`col.${k}`)}
                    {k === "roll_no" || k === "qty" ? (
                      <span className="text-danger"> *</span>
                    ) : null}
                  </th>
                ))}
                <th className="w-10" />
              </tr>
            </thead>
            <tbody>
              {rolls.map((x, i) => (
                <tr key={i}>
                  {["roll_no", "dye_lot", "gsm", "width", "qty"].map((k) => (
                    <td key={k} className="px-2 py-1">
                      <Input
                        type={k === "qty" ? "number" : "text"}
                        min={k === "qty" ? "0" : undefined}
                        step={k === "qty" ? "any" : undefined}
                        value={x[k]}
                        onChange={setRoll(i, k)}
                        placeholder={k === "qty" ? "0" : ""}
                        aria-label={`${r(`col.${k}`)} ${i + 1} ${label}`}
                        className={cn("h-9", k === "qty" && "text-right")}
                      />
                    </td>
                  ))}
                  <td className="px-1 py-1">
                    <Button
                      type="button"
                      variant="outline"
                      size="icon"
                      onClick={() => onChange(rolls.filter((_, j) => j !== i))}
                      aria-label={`${r("remove")} ${i + 1} ${label}`}
                    >
                      <Trash2 className="size-4" aria-hidden />
                    </Button>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      ) : null}
      {rolls.length ? (
        <p
          className={cn(
            "text-xs",
            off ? "text-danger" : "text-foreground-muted",
          )}
        >
          {r("total", {
            n: rolls.length,
            total: qty(total),
            line: qty(lineQty),
            uom,
          })}
        </p>
      ) : null}
      {error ? <span className="text-xs text-danger">{error}</span> : null}
    </div>
  );
}

/** The rolls a payload sends: the filled ones, blanks as null. */
const rollPayload = (rolls) =>
  (rolls ?? [])
    .filter((x) => x.roll_no.trim() || x.qty !== "")
    .map((x) =>
      Object.fromEntries(
        Object.entries(x).map(([k, v]) => [k, v === "" ? null : v]),
      ),
    );

/** The rolls entered don't add up to the line. */
const rollsOff = (rolls, lineQty) =>
  (rolls ?? []).length > 0 &&
  Math.abs(
    rolls.reduce((sum, x) => sum + Number(x.qty || 0), 0) -
      Number(lineQty || 0),
  ) > 0.0005;

export {
  card,
  day,
  qty,
  dash,
  messagesOf,
  StoreFilters,
  EmployeeField,
  RollEntry,
  rollPayload,
  rollsOff,
};
