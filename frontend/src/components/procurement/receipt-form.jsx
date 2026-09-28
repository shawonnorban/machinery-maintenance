"use client";

import { startTransition, useActionState, useState } from "react";
import { useRouter } from "next/navigation";
import { Card, CardHeader, CardTitle, CardBody } from "@/components/ui/card";
import { FormField } from "@/components/ui/form-field";
import { Select } from "@/components/ui/select";
import { Input } from "@/components/ui/input";
import { Textarea } from "@/components/ui/textarea";
import { Button } from "@/components/ui/button";
import { Alert } from "@/components/ui/alert";
import { DocumentLinesEditor, newLine, serialiseLines } from "@/components/procurement/document-lines-editor";
import { useT } from "@/lib/i18n";

/**
 * Create/edit a goods receipt (Procurement spec §9).
 *
 * Against an order (`receivable` present — the order's outstanding lines,
 * items fixed, quantities pre-filled with what is still due) or direct
 * (`receivable` absent — pick items from the library). Bins are narrowed
 * to the receiving store; a lot number is asked for on lot-tracked items.
 */
function ReceiptForm({ receipt, receivable, options, searchItems, action }) {
  const t = useT("procurement");
  const tc = useT("common");
  const router = useRouter();
  const [state, dispatch, pending] = useActionState(action, null);

  const order = receivable?.order ?? receipt?.order ?? null;
  const factoryDefault = receipt?.factory?.id ?? order?.factory?.id ?? (options.factories.length === 1 ? options.factories[0].id : "");
  const [factoryId, setFactoryId] = useState(factoryDefault);
  const [storeId, setStoreId] = useState(receipt?.store?.id ?? receivable?.order?.delivery_store_id ?? "");
  const [vendorId, setVendorId] = useState(receipt?.vendor?.id ?? order?.vendor?.id ?? "");
  // Against an order the currency is the order's and cannot be chosen;
  // a direct delivery takes the supplier's, because not every mill
  // invoices in taka.
  const [currency, setCurrency] = useState(
    order?.currency ?? receipt?.lines?.[0]?.currency ?? "BDT",
  );
  const [grnDate, setGrnDate] = useState(receipt?.grn_date ?? new Date().toISOString().slice(0, 10));
  const [gatePassNo, setGatePassNo] = useState(receipt?.gate_pass_no ?? "");
  const [notes, setNotes] = useState(receipt?.notes ?? "");
  const [lines, setLines] = useState(() => {
    if (receipt?.lines?.length) {
      return receipt.lines.map((line) =>
        newLine({
          item: { id: line.item_id, item_code: line.item_code, name: line.item_name, uom: line.uom, tracking_mode: line.tracking_mode },
          uom_id: line.uom?.id ?? "",
          quantity: line.received_qty,
          rate: line.rate ?? "",
          bin_id: line.bin?.id ?? "",
          lot_no: line.lot_no ?? "",
          notes: line.notes ?? "",
          po_line_id: line.po_line_id ?? undefined,
        }),
      );
    }
    if (receivable) {
      return receivable.lines.map((line) =>
        newLine({
          item: { id: line.item_id, item_code: line.item_code, name: line.item_name, uom: line.uom, tracking_mode: line.tracking_mode },
          uom_id: line.uom?.id ?? "",
          quantity: line.pending_qty,
          rate: line.rate,
          po_line_id: line.id,
        }),
      );
    }
    return [newLine()];
  });

  const againstOrder = Boolean(order);
  const stores = options.stores.filter((s) => !factoryId || s.factory_id === factoryId);
  const bins = options.bins.filter((b) => b.store_id === storeId);

  function chooseVendor(id) {
    setVendorId(id);

    const vendor = options.vendors.find((v) => v.id === id);

    if (!againstOrder && vendor?.default_currency) setCurrency(vendor.default_currency);
  }

  function handleSubmit(event) {
    event.preventDefault();
    const formData = new FormData();
    if (order && !receipt) formData.set("po_id", order.id);
    formData.set("factory_id", factoryId);
    formData.set("store_id", storeId);
    formData.set("vendor_id", vendorId);
    formData.set("currency", currency);
    formData.set("grn_date", grnDate);
    formData.set("gate_pass_no", gatePassNo);
    formData.set("notes", notes);
    formData.set("lines", JSON.stringify(serialiseLines(lines, "received_qty")));
    startTransition(() => dispatch(formData));
  }

  return (
    <form onSubmit={handleSubmit} className="flex flex-col gap-4">
      {state?.status === "error" && !state.errors ? <Alert variant="danger">{state.message}</Alert> : null}
      {state?.status === "error" && state.errors?.lines?.[0] ? <Alert variant="danger">{state.errors.lines[0]}</Alert> : null}
      {againstOrder ? <Alert variant="info">{t("against_order")}: {order.po_no}</Alert> : null}
      {receivable && receivable.lines.length === 0 ? <Alert variant="warning">{t("no_outstanding_lines")}</Alert> : null}

      <Card>
        <CardHeader>
          <CardTitle>{t("goods_receipt")}</CardTitle>
        </CardHeader>
        <CardBody className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
          <FormField label={t("factory")} required error={state?.errors?.factory_id?.[0]}>
            {(fieldProps) => (
              <Select {...fieldProps} value={factoryId} onValueChange={(v) => { setFactoryId(v); setStoreId(""); }} options={options.factories.map((f) => ({ value: f.id, label: f.name }))} placeholder={t("factory")} disabled={Boolean(receipt) || againstOrder} />
            )}
          </FormField>
          <FormField label={t("store")} required error={state?.errors?.store_id?.[0]}>
            {(fieldProps) => (
              <Select {...fieldProps} value={storeId} onValueChange={setStoreId} options={stores.map((s) => ({ value: s.id, label: s.name }))} placeholder={t("store")} disabled={Boolean(receipt)} />
            )}
          </FormField>
          <FormField label={t("vendor")} error={state?.errors?.vendor_id?.[0]}>
            {(fieldProps) => (
              <Select {...fieldProps} value={vendorId} onValueChange={chooseVendor} options={[{ value: "", label: "—" }, ...options.vendors.map((v) => ({ value: v.id, label: v.name }))]} placeholder={t("vendor")} disabled={againstOrder} />
            )}
          </FormField>
          <FormField label={t("currency")} error={state?.errors?.currency?.[0]}>
            {(fieldProps) => (
              <Select
                {...fieldProps}
                value={currency}
                onValueChange={setCurrency}
                options={(options.currencies ?? ["BDT"]).map((c) => ({ value: c, label: c }))}
                disabled={againstOrder}
              />
            )}
          </FormField>
          <FormField label={t("date")} required error={state?.errors?.grn_date?.[0]}>
            {(fieldProps) => <Input {...fieldProps} type="date" value={grnDate} onChange={(e) => setGrnDate(e.target.value)} />}
          </FormField>
          <FormField label={t("gate_pass_no")} error={state?.errors?.gate_pass_no?.[0]}>
            {(fieldProps) => <Input {...fieldProps} value={gatePassNo} onChange={(e) => setGatePassNo(e.target.value)} maxLength={64} />}
          </FormField>
          <div className="sm:col-span-2 lg:col-span-3">
            <FormField label={t("notes")} error={state?.errors?.notes?.[0]}>
              {(fieldProps) => <Textarea {...fieldProps} value={notes} onChange={(e) => setNotes(e.target.value)} rows={2} maxLength={2000} />}
            </FormField>
          </div>
        </CardBody>
      </Card>

      <DocumentLinesEditor
        lines={lines}
        onChange={setLines}
        departments={options.item_departments}
        uoms={options.uoms}
        bins={bins}
        searchItems={searchItems}
        fields={["quantity", "uom", "rate", "bin", "lot_no", "notes"]}
        currency={currency}
        quantityLabel={t("received_qty")}
        errors={state?.errors}
        fixedItems={againstOrder}
      />

      <div className="flex justify-end gap-2">
        <Button type="button" variant="outline" onClick={() => router.back()}>
          {tc("cancel")}
        </Button>
        <Button type="submit" loading={pending} disabled={receivable && receivable.lines.length === 0}>
          {t("save_draft")}
        </Button>
      </div>
    </form>
  );
}

export { ReceiptForm };
