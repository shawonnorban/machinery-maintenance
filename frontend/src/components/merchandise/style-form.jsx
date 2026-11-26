"use client";

import { useMemo, useRef, useState, useTransition } from "react";
import Link from "next/link";
import { useRouter } from "next/navigation";
import { Pencil, Plus, RotateCcw, Save, Table2, X } from "lucide-react";
import { cn } from "@/lib/utils";
import { Input } from "@/components/ui/input";
import { Select } from "@/components/ui/select";
import { Checkbox } from "@/components/ui/checkbox";
import { Button, buttonVariants } from "@/components/ui/button";
import { Alert } from "@/components/ui/alert";
import { useToastManager } from "@/components/ui/toast";
import { useT } from "@/lib/i18n";

const blankLine = (sl) => ({ key: `n${sl}-${Math.random().toString(36).slice(2, 7)}`, sl_no: String(sl), size_ids: [], sub_style: "" });

function start(row) {
  return {
    buyer_id: row?.buyer_id ?? "",
    buyer_office_id: row?.buyer_office_id ?? "",
    inquiry_id: row?.inquiry_id ?? "",
    style_ref: row?.style_ref ?? "",
    style_ext: row?.style_ext ?? "",
    description: row?.description ?? "",
    is_set: row?.is_set ?? false,
    is_model: row?.is_model ?? false,
    is_combo: row?.is_combo ?? false,
    is_colour_pair: row?.is_colour_pair ?? false,
    prev_style_id: row?.prev_style_id ?? "",
    qty_unit: row?.qty_unit ?? "PCS",
    pcs_per_unit: String(row?.pcs_per_unit ?? 1),
    season: row?.season ?? "",
    ref_no: row?.ref_no ?? "",
    nature_of_work: row?.nature_of_work ?? "",
    supplier_id: row?.supplier_id ?? "",
    origin_country_id: row?.origin_country_id ?? "",
    status: row?.status ?? "DEVELOPMENT",
    lines: row?.lines?.length ? row.lines.map((l) => ({ key: l.id, sl_no: String(l.sl_no), size_ids: l.size_ids, sub_style: l.sub_style ?? "" })) : [blankLine(1)],
  };
}

/**
 * Style Development Sheet's registration form (AnnotechERP 06/05), laid out as
 * Brickland's "Style Information" box: buyer and account, the confirmed
 * inquiry, the main reference and extension, set / model / combo / colour
 * pair, unit (a set or dozen counts its pieces), season, supplier, origin and
 * status — then the size range lines, each a set of sizes from Size Master.
 * `mode` is "new", "edit" or "sheet" (the sheet also offers Running / Closed
 * and the nature of work, and stays open after Update).
 *
 * `extra` sits beside Update on the sheet: Brickland's Item/Combo/Model
 * (Fresh / Copy) and Style Gallery.
 *
 * `onSaved` given (the Development Style wizard), a save hands the style back
 * instead of moving to Records, and the card names the style without a
 * Records link.
 *
 * @param {{ mode: "new"|"edit"|"sheet", row?: object, look: object, save: Function, recordsHref: string, extra?: React.ReactNode, onSaved?: Function }} props
 */
function StyleForm({ mode, row = null, look, save, recordsHref, extra = null, onSaved = null }) {
  const t = useT("merchandise");
  const m = (k, p) => t(`master.sty.${k}`, p);
  const router = useRouter();
  const toastManager = useToastManager();
  const [values, setValues] = useState(() => start(row));
  const [errors, setErrors] = useState(null);
  const [pending, startTransition] = useTransition();
  const set = (field) => (value) => setValues((v) => ({ ...v, [field]: value }));

  const offices = values.buyer_id ? (look.buyer_offices[values.buyer_id] ?? []) : [];
  const inquiries = values.buyer_id ? look.inquiries.filter((i) => i.buyer_id === values.buyer_id) : [];
  const statuses = mode === "sheet" ? look.sheet_statuses : look.form_statuses;
  const styleNo = values.style_ext ? `${values.style_ref}-${values.style_ext}` : values.style_ref;

  function pickBuyer(next) {
    // An account or inquiry of another buyer no longer applies.
    setValues((v) => (v.buyer_id === next ? v : { ...v, buyer_id: next, buyer_office_id: "", inquiry_id: "" }));
  }

  function setLine(key, field, value) {
    setValues((v) => ({ ...v, lines: v.lines.map((l) => (l.key === key ? { ...l, [field]: value } : l)) }));
  }

  function addLine() {
    setValues((v) => ({ ...v, lines: [...v.lines, blankLine(Math.max(0, ...v.lines.map((l) => Number(l.sl_no) || 0)) + 1)] }));
  }

  function submit(event) {
    event.preventDefault();
    startTransition(async () => {
      const payload = {
        ...values,
        buyer_office_id: values.buyer_office_id || null,
        inquiry_id: values.inquiry_id || null,
        prev_style_id: values.prev_style_id || null,
        season: values.season || null,
        supplier_id: values.supplier_id || null,
        origin_country_id: values.origin_country_id || null,
        pcs_per_unit: Number(values.pcs_per_unit) || 1,
        nature_of_work: mode === "sheet" ? values.nature_of_work : (row?.nature_of_work ?? null),
        lines: values.lines.map((l) => ({ sl_no: Number(l.sl_no) || null, size_ids: l.size_ids, sub_style: l.sub_style || null })),
      };
      delete payload.key;
      const result = await save(row?.id ?? null, payload);
      if (result?.status === "success") {
        toastManager.add({ title: m("saved_to", { style: result.style.style_no }), type: "success" });
        setErrors(null);
        if (onSaved) {
          setValues(start(result.style));
          onSaved(result.style);
        } else if (mode === "sheet") {
          setValues(start(result.style));
          router.refresh();
        } else {
          router.push(recordsHref);
          router.refresh();
        }
      } else {
        setErrors(result?.errors ? [...new Set(Object.values(result.errors).flat())] : [result?.message]);
      }
    });
  }

  const opts = (list, placeholder) => [{ value: "", label: placeholder }, ...list];

  return (
    <form onSubmit={submit} className="rounded-lg border border-border bg-surface">
      <div className="flex flex-wrap items-center justify-between gap-3 border-b border-border px-5 py-4">
        <h2 className="text-base font-semibold text-foreground">{mode === "edit" && row ? m("registration_of", { style: row.style_no }) : m("registration")}</h2>
        {onSaved ? null : mode === "sheet" && row ? (
          <span className="text-[13px] text-foreground-muted">{m("saved_to", { style: row.style_no })}</span>
        ) : (
          <Link href={recordsHref} className={cn(buttonVariants({ variant: "outline", size: "sm" }))}>
            <Table2 /> {m("records")}
          </Link>
        )}
      </div>

      <div className="flex flex-col gap-4 p-5">
        {errors ? <Alert variant="danger">{errors.join(" · ")}</Alert> : null}
        <fieldset className="rounded-lg border border-border px-4 pt-2 pb-4">
          <legend className="px-1 text-[13px] font-semibold text-brand italic">{m("information")}</legend>
          <div className="grid gap-x-6 gap-y-3 lg:grid-cols-3">
            <Row label={m("f.buyer_ac")}>
              <Select value={values.buyer_office_id} onValueChange={(v) => set("buyer_office_id")(v ?? "")} options={opts(offices, m("ph.buyer_ac"))} placeholder={m("ph.buyer_ac")} />
            </Row>
            <Row label={m("f.buyer")} required>
              <Select value={values.buyer_id} onValueChange={(v) => v && pickBuyer(v)} options={look.buyers} placeholder={m("ph.buyer")} />
            </Row>
            <Row label={m("f.inquiry")}>
              <Select value={values.inquiry_id} onValueChange={(v) => set("inquiry_id")(v ?? "")} options={opts(inquiries, m("ph.inquiry"))} placeholder={m("ph.inquiry")} />
            </Row>

            <Row label={m("f.main_ref")} required>
              <div className="grid grid-cols-[1fr_6.5rem] gap-2">
                <Input value={values.style_ref} onChange={(e) => set("style_ref")(e.target.value)} aria-label={m("f.main_ref")} />
                <Input value={values.style_ext} onChange={(e) => set("style_ext")(e.target.value)} placeholder={m("ph.ext")} aria-label={m("ph.ext")} />
              </div>
            </Row>
            <Row label={m("f.style_desc")} required>
              <Input value={values.description} onChange={(e) => set("description")(e.target.value)} aria-label={m("f.style_desc")} />
            </Row>
            <Row label={m("f.prev_style")}>
              <Select value={values.prev_style_id} onValueChange={(v) => set("prev_style_id")(v ?? "")} options={opts(look.styles.filter((s) => s.value !== row?.id), m("ph.prev"))} placeholder={m("ph.prev")} />
            </Row>

            <Row label="">
              <div className="flex flex-wrap gap-x-4 gap-y-1.5">
                {["is_set", "is_model", "is_combo", "is_colour_pair"].map((flag) => (
                  <label key={flag} className="flex items-center gap-1.5 text-[13px] font-semibold text-foreground">
                    <Checkbox checked={values[flag]} onCheckedChange={(c) => set(flag)(c === true)} />
                    {m(`f.${flag === "is_colour_pair" ? "is_col_pair" : flag}`)}
                  </label>
                ))}
              </div>
            </Row>
            <Row label={m("f.qty_unit")}>
              <div className="grid grid-cols-[1fr_1fr] gap-2">
                <Select value={values.qty_unit} onValueChange={(v) => v && set("qty_unit")(v)} options={look.qty_units} />
                <Input type="number" min="1" value={values.qty_unit === "PCS" ? "1" : values.pcs_per_unit} onChange={(e) => set("pcs_per_unit")(e.target.value)} disabled={values.qty_unit === "PCS"} placeholder={m("ph.pcs")} aria-label={m("ph.pcs")} />
              </div>
            </Row>
            <Row label={m("f.season")}>
              <div className="grid grid-cols-[1fr_6.5rem] gap-2">
                <Select value={values.season} onValueChange={(v) => set("season")(v ?? "")} options={opts(look.seasons, m("ph.season"))} placeholder={m("ph.season")} />
                <Input value={values.ref_no} onChange={(e) => set("ref_no")(e.target.value)} placeholder={m("ph.ref")} aria-label={m("ph.ref")} />
              </div>
            </Row>

            {mode === "sheet" ? (
              <>
                <div className="hidden lg:block" />
                <Row label={m("f.nature_of_work")}>
                  <Input value={values.nature_of_work} onChange={(e) => set("nature_of_work")(e.target.value)} aria-label={m("f.nature_of_work")} />
                </Row>
                <div className="hidden lg:block" />
              </>
            ) : null}

            <Row label={m("f.supplier")}>
              <Select value={values.supplier_id} onValueChange={(v) => set("supplier_id")(v ?? "")} options={opts(look.suppliers, m("ph.supplier"))} placeholder={m("ph.supplier")} />
            </Row>
            <Row label={m("f.origin")}>
              <Select value={values.origin_country_id} onValueChange={(v) => set("origin_country_id")(v ?? "")} options={opts(look.countries, m("ph.origin"))} placeholder={m("ph.origin")} />
            </Row>
            <Row label={m("f.status")}>
              <Select value={values.status} onValueChange={(v) => v && set("status")(v)} options={statuses} placeholder={m("ph.status")} />
            </Row>
          </div>

          <div className="mt-4 flex flex-col gap-2 rounded-md border border-border bg-surface-muted/40 p-3">
            {values.lines.map((line, index) => (
              <div key={line.key} className="grid items-center gap-3 md:grid-cols-[3rem_5rem_6rem_1fr_3.5rem_14rem_2.25rem]">
                <span className="text-right text-[13px] font-semibold text-foreground">{m("f.sl")}</span>
                <Input type="number" min="1" value={line.sl_no} onChange={(e) => setLine(line.key, "sl_no", e.target.value)} aria-label={m("f.sl")} />
                <span className="text-right text-[13px] font-semibold text-foreground">
                  {m("f.size_range")}
                  <span className="ml-0.5 text-danger">*</span>
                </span>
                <SizePicker sizes={look.sizes} groupLabel={look.group_labels} value={line.size_ids} onChange={(ids) => setLine(line.key, "size_ids", ids)} placeholder={m("ph.search")} empty={m("no_sizes")} />
                <span className="text-right text-[13px] font-semibold text-foreground">{m("f.style")}</span>
                <Input value={line.sub_style} onChange={(e) => setLine(line.key, "sub_style", e.target.value)} placeholder={styleNo || m("ph.style")} aria-label={m("f.style")} />
                {index === 0 ? (
                  <Button type="button" variant="outline" size="sm" onClick={addLine} aria-label={m("add_line")}>
                    <Plus />
                  </Button>
                ) : (
                  <Button type="button" variant="outline" size="sm" onClick={() => setValues((v) => ({ ...v, lines: v.lines.filter((l) => l.key !== line.key) }))} aria-label={m("remove_line")}>
                    <X />
                  </Button>
                )}
              </div>
            ))}
          </div>
          <p className="mt-2 text-[13px] text-foreground-muted">{m("lines_hint")}</p>
          {mode === "sheet" && row?.created_at ? <p className="mt-1 text-right text-[13px] font-semibold text-warning">{m("created_on", { date: new Date(row.created_at).toLocaleDateString("en-GB", { day: "2-digit", month: "short", year: "numeric" }).replaceAll(" ", "-") })}</p> : null}
        </fieldset>

        <div className="flex flex-wrap gap-2">
          <Button type="submit" loading={pending}>
            {mode === "sheet" ? <Pencil /> : <Save />} {mode === "sheet" ? m("update") : m("save")}
          </Button>
          <Button type="button" variant="outline" onClick={() => (mode === "new" || onSaved ? setValues(start(row)) : router.push(recordsHref))} disabled={pending}>
            <RotateCcw /> {m("cancel")}
          </Button>
          {extra}
        </div>
      </div>
    </form>
  );
}

/** A label beside its control, as Brickland's registration box lays them out. */
function Row({ label, required = false, children }) {
  return (
    <div className="grid items-center gap-3 sm:grid-cols-[8.5rem_1fr]">
      <span className="text-[13px] font-semibold text-foreground sm:text-right">
        {label}
        {required ? <span className="ml-0.5 text-danger">*</span> : null}
      </span>
      {children}
    </div>
  );
}

/** Sizes from Size Master as chips, picked from a searchable list showing each size's group. */
function SizePicker({ sizes, groupLabel, value, onChange, placeholder, empty }) {
  const [open, setOpen] = useState(false);
  const [query, setQuery] = useState("");
  const box = useRef(null);
  const byId = useMemo(() => Object.fromEntries(sizes.map((s) => [s.value, s])), [sizes]);
  const needle = query.trim().toLowerCase();
  const free = sizes.filter((s) => !value.includes(s.value) && (!needle || s.label.toLowerCase().includes(needle)));

  return (
    <div
      ref={box}
      className="relative"
      onBlur={(e) => {
        if (!box.current?.contains(e.relatedTarget)) setOpen(false);
      }}
    >
      <div className="flex min-h-10 flex-wrap items-center gap-1.5 rounded-md border border-border-strong bg-surface px-2 py-1.5">
        {value.map((id) => (
          <span key={id} className="inline-flex items-center gap-1 rounded bg-brand-subtle px-1.5 py-0.5 text-xs font-semibold text-brand">
            {byId[id]?.label ?? "?"}
            <button type="button" onClick={() => onChange(value.filter((x) => x !== id))} aria-label={`× ${byId[id]?.label ?? ""}`} className="opacity-70 hover:opacity-100">
              <X className="size-3" />
            </button>
          </span>
        ))}
        <input
          value={query}
          onChange={(e) => {
            setQuery(e.target.value);
            setOpen(true);
          }}
          onFocus={() => setOpen(true)}
          placeholder={value.length ? "" : placeholder}
          className="min-w-20 flex-1 bg-transparent text-sm outline-none placeholder:text-foreground-muted"
        />
      </div>
      {open ? (
        <ul className="absolute z-20 mt-1 max-h-60 w-full overflow-y-auto rounded-md border border-border bg-surface shadow-lg">
          {sizes.length === 0 ? <li className="px-3 py-2 text-[13px] text-foreground-muted">{empty}</li> : null}
          {free.map((s) => (
            <li key={s.value}>
              <button
                type="button"
                className="flex w-full items-center justify-between px-3 py-2 text-left text-sm hover:bg-surface-muted"
                onClick={() => {
                  onChange([...value, s.value]);
                  setQuery("");
                }}
              >
                <span>{s.label}</span>
                <span className="text-xs text-foreground-muted">{groupLabel[s.group] ?? s.group}</span>
              </button>
            </li>
          ))}
        </ul>
      ) : null}
    </div>
  );
}

export { StyleForm };
