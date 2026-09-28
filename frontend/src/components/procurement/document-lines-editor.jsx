"use client";

import { Trash2, Plus } from "lucide-react";
import { Card, CardHeader, CardTitle, CardBody } from "@/components/ui/card";
import { FormField } from "@/components/ui/form-field";
import { Select } from "@/components/ui/select";
import { Input } from "@/components/ui/input";
import { Button } from "@/components/ui/button";
import { ItemPicker } from "@/components/procurement/item-picker";
import { formatNumber, trimDecimal } from "@/lib/format";
import { useT } from "@/lib/i18n";

let nextRowId = 1;

/** A fresh, empty line. Exported so a form can seed its first row the same way. */
function newLine(overrides = {}) {
  const line = { rowId: nextRowId++, item: null, uom_id: "", quantity: "", rate: "", required_date: "", bin_id: "", lot_no: "", notes: "", ...overrides };
  // API decimals ("3.0000") are trimmed so the inputs open clean.
  line.quantity = trimDecimal(line.quantity);
  line.rate = trimDecimal(line.rate);
  return line;
}

/**
 * The item lines every procurement document edits (Procurement spec §5,
 * §7, §9; Floor/Store Requisition spec §3): pick an item, say how many.
 * Which extra columns show (rate, date, bin, lot) is the document's call
 * via `fields`; the item picker and quantity are always there.
 *
 * `fixedItems` is for a receipt drafted against an order: the items are the
 * order's and cannot be swapped, only their quantity/bin/lot filled in.
 */
function DocumentLinesEditor({
  lines,
  onChange,
  departments,
  uoms,
  bins = [],
  searchItems,
  fields = ["quantity", "uom"],
  quantityLabel,
  title,
  errors = {},
  pinnedDepartmentId,
  fixedItems = false,
  currency = "BDT",
}) {
  const t = useT("procurement");
  const has = (field) => fields.includes(field);

  const uomOptions = uoms.map((u) => ({ value: u.id, label: `${u.code} — ${u.name}` }));
  const binOptions = bins.map((b) => ({ value: b.id, label: b.name ? `${b.code} — ${b.name}` : b.code }));

  function update(rowId, patch) {
    onChange(lines.map((line) => (line.rowId === rowId ? { ...line, ...patch } : line)));
  }

  function pick(rowId, item) {
    update(rowId, { item, uom_id: item?.uom?.id ?? "" });
  }

  function remove(rowId) {
    if (lines.length > 1) onChange(lines.filter((line) => line.rowId !== rowId));
  }

  const total = has("rate")
    ? lines.reduce((sum, line) => sum + (Number(line.quantity) || 0) * (Number(line.rate) || 0), 0)
    : null;

  return (
    <Card>
      <CardHeader>
        <CardTitle>{title ?? t("lines")}</CardTitle>
      </CardHeader>
      <CardBody className="flex flex-col gap-4">
        {lines.map((line, index) => {
          const err = (key) => errors?.[`lines.${index}.${key}`]?.[0];
          return (
            <div key={line.rowId} className="flex flex-col gap-2 rounded-sm border border-border p-3">
              <div className="grid grid-cols-1 gap-2 sm:grid-cols-12 sm:items-start">
                <div className="sm:col-span-6">
                  <FormField label={t("item")} required error={err("item_id") ?? err("po_line_id") ?? err("pr_line_id")}>
                    {() =>
                      fixedItems ? (
                        <div className="flex h-9 items-center rounded-sm border border-border bg-surface-muted px-3 text-sm">
                          <span className="truncate font-medium">{line.item?.name}</span>
                          <span className="ml-2 font-mono text-xs text-foreground-muted">{line.item?.item_code}</span>
                        </div>
                      ) : (
                        <ItemPicker
                          value={line.item}
                          onChange={(item) => pick(line.rowId, item)}
                          departments={departments}
                          searchItems={searchItems}
                          departmentId={pinnedDepartmentId}
                        />
                      )
                    }
                  </FormField>
                </div>
                <div className="sm:col-span-2">
                  <FormField label={quantityLabel ?? t("quantity")} required error={err("quantity") ?? err("requested_qty") ?? err("ordered_qty") ?? err("received_qty")}>
                    {(fieldProps) => (
                      <Input {...fieldProps} type="number" step="0.0001" min="0" value={line.quantity} onChange={(e) => update(line.rowId, { quantity: e.target.value })} />
                    )}
                  </FormField>
                </div>
                {has("uom") ? (
                  <div className="sm:col-span-3">
                    <FormField label={t("unit")} error={err("uom_id")}>
                      {(fieldProps) => (
                        <Select {...fieldProps} value={line.uom_id} onValueChange={(value) => update(line.rowId, { uom_id: value })} options={uomOptions} placeholder={t("unit")} disabled={fixedItems} />
                      )}
                    </FormField>
                  </div>
                ) : null}
                <div className="sm:col-span-1 sm:pt-6">
                  <Button type="button" variant="ghost" size="icon" aria-label={t("remove_line")} onClick={() => remove(line.rowId)} disabled={lines.length === 1}>
                    <Trash2 className="text-danger" />
                  </Button>
                </div>
              </div>

              {has("rate") || has("required_date") || has("bin") || has("lot_no") || has("notes") ? (
                <div className="grid grid-cols-1 gap-2 sm:grid-cols-12">
                  {has("rate") ? (
                    <div className="sm:col-span-2">
                      <FormField label={`${t("rate")} (${currency})`} error={err("rate") ?? err("estimated_rate")}>
                        {(fieldProps) => (
                          <Input {...fieldProps} type="number" step="0.0001" min="0" value={line.rate} onChange={(e) => update(line.rowId, { rate: e.target.value })} disabled={fixedItems} />
                        )}
                      </FormField>
                    </div>
                  ) : null}
                  {has("rate") ? (
                    <div className="sm:col-span-2">
                      <FormField label={t("amount")}>
                        {() => (
                          <div className="flex h-9 items-center justify-end rounded-sm border border-border bg-surface-muted px-3 text-sm tabular-nums">
                            {formatNumber((Number(line.quantity) || 0) * (Number(line.rate) || 0), { maximumFractionDigits: 2 })}
                          </div>
                        )}
                      </FormField>
                    </div>
                  ) : null}
                  {has("required_date") ? (
                    <div className="sm:col-span-2">
                      <FormField label={t("required_date")} error={err("required_date")}>
                        {(fieldProps) => <Input {...fieldProps} type="date" value={line.required_date} onChange={(e) => update(line.rowId, { required_date: e.target.value })} />}
                      </FormField>
                    </div>
                  ) : null}
                  {has("bin") ? (
                    <div className="sm:col-span-3">
                      <FormField label={t("bin")} error={err("bin_id")}>
                        {(fieldProps) => (
                          <Select {...fieldProps} value={line.bin_id} onValueChange={(value) => update(line.rowId, { bin_id: value })} options={binOptions} placeholder={t("pick_bin")} />
                        )}
                      </FormField>
                    </div>
                  ) : null}
                  {has("lot_no") ? (
                    <div className="sm:col-span-2">
                      <FormField label={t("lot_no")} required={line.item?.tracking_mode === "LOT"} error={err("lot_no")}>
                        {(fieldProps) => <Input {...fieldProps} value={line.lot_no} onChange={(e) => update(line.rowId, { lot_no: e.target.value })} maxLength={64} />}
                      </FormField>
                    </div>
                  ) : null}
                  {has("notes") ? (
                    <div className={has("bin") ? "sm:col-span-3" : "sm:col-span-4"}>
                      <FormField label={t("line_notes")} error={err("notes") ?? err("purpose")}>
                        {(fieldProps) => <Input {...fieldProps} value={line.notes} onChange={(e) => update(line.rowId, { notes: e.target.value })} maxLength={500} />}
                      </FormField>
                    </div>
                  ) : null}
                </div>
              ) : null}
            </div>
          );
        })}

        {errors?.lines?.[0] ? <p className="text-sm text-danger">{errors.lines[0]}</p> : null}

        <div className="flex items-center justify-between">
          {fixedItems ? <span /> : (
            <Button type="button" variant="outline" size="sm" onClick={() => onChange([...lines, newLine()])}>
              <Plus /> {t("add_line")}
            </Button>
          )}
          {total !== null ? (
            <span className="text-sm">
              <span className="text-foreground-muted">{t("subtotal")}: </span>
              <span className="font-semibold tabular-nums">{formatNumber(total, { maximumFractionDigits: 2 })} {currency}</span>
            </span>
          ) : null}
        </div>
      </CardBody>
    </Card>
  );
}

/** The wire shape `lines[]` every controller validates, from the editor's rows. */
function serialiseLines(lines, quantityKey) {
  return lines.map((line) => {
    const out = { item_id: line.item?.id ?? null, [quantityKey]: line.quantity };
    if (line.uom_id) out.uom_id = line.uom_id;
    if (line.rate !== "" && line.rate !== undefined) out.rate = line.rate;
    if (line.required_date) out.required_date = line.required_date;
    if (line.bin_id) out.bin_id = line.bin_id;
    if (line.lot_no) out.lot_no = line.lot_no;
    if (line.notes) out.notes = line.notes;
    if (line.po_line_id) out.po_line_id = line.po_line_id;
    if (line.pr_line_id) out.pr_line_id = line.pr_line_id;
    return out;
  });
}

export { DocumentLinesEditor, newLine, serialiseLines };
