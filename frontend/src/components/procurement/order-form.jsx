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
 * Create/edit/revise a purchase order (Procurement spec §7, local flow).
 *
 * Three modes share the form: a direct order (`order` absent, `requisition`
 * absent), an order raised from an approved requisition (`requisition`
 * present — lines are the requisition's outstanding lines, items fixed),
 * and an edit/revision of an existing order (`order` present; `revision`
 * adds the reason field ManagePurchaseOrder::revise requires).
 */
function OrderForm({ order, requisition, options, searchItems, action, revision = false }) {
  const t = useT("procurement");
  const tc = useT("common");
  const router = useRouter();
  const [state, dispatch, pending] = useActionState(action, null);

  const factoryDefault = order?.factory?.id ?? requisition?.factory?.id ?? (options.factories.length === 1 ? options.factories[0].id : "");
  const [factoryId, setFactoryId] = useState(factoryDefault);
  const [vendorId, setVendorId] = useState(order?.vendor?.id ?? "");
  const [poDate, setPoDate] = useState(order?.po_date ?? new Date().toISOString().slice(0, 10));
  const [currency, setCurrency] = useState(order?.currency ?? requisition?.currency ?? "BDT");
  const [paymentTypeId, setPaymentTypeId] = useState(order?.payment_type?.id ?? "");
  const [paymentTerms, setPaymentTerms] = useState(order?.payment_terms ?? "");
  const [deliveryDate, setDeliveryDate] = useState(order?.delivery_date ?? "");
  const [deliveryStoreId, setDeliveryStoreId] = useState(order?.delivery_store?.id ?? requisition?.store?.id ?? "");
  const [notes, setNotes] = useState(order?.notes ?? "");
  const [reason, setReason] = useState("");
  const [lines, setLines] = useState(() => {
    if (order?.lines?.length) {
      return order.lines.map((line) =>
        newLine({
          item: { id: line.item_id, item_code: line.item_code, name: line.item_name, uom: line.uom },
          uom_id: line.uom?.id ?? "",
          quantity: line.ordered_qty,
          rate: line.rate,
          required_date: line.required_date ?? "",
          notes: line.notes ?? "",
          pr_line_id: line.pr_line_id ?? undefined,
        }),
      );
    }
    if (requisition) {
      return requisition.lines
        .filter((line) => Number(line.pending_qty) > 0)
        .map((line) =>
          newLine({
            item: { id: line.item_id, item_code: line.item_code, name: line.item_name, uom: line.uom },
            uom_id: line.uom?.id ?? "",
            quantity: line.pending_qty,
            rate: line.estimated_rate ?? "",
            required_date: line.required_date ?? "",
            pr_line_id: line.id,
          }),
        );
    }
    return [newLine()];
  });

  const fromRequisition = Boolean(requisition);
  const stores = options.stores.filter((s) => !factoryId || s.factory_id === factoryId);
  const vendorOptions = options.vendors.map((v) => ({ value: v.id, label: v.code ? `${v.code} — ${v.name}` : v.name }));

  function pickVendor(id) {
    setVendorId(id);
    const vendor = options.vendors.find((v) => v.id === id);
    if (vendor && !order) {
      if (vendor.default_currency) setCurrency(vendor.default_currency);
      if (vendor.default_payment_type_id) setPaymentTypeId(vendor.default_payment_type_id);
      if (vendor.default_payment_terms) setPaymentTerms(vendor.default_payment_terms);
    }
  }

  function handleSubmit(event) {
    event.preventDefault();
    const formData = new FormData();
    formData.set("factory_id", factoryId);
    formData.set("vendor_id", vendorId);
    formData.set("po_date", poDate);
    formData.set("currency", currency);
    formData.set("payment_type_id", paymentTypeId);
    formData.set("payment_terms", paymentTerms);
    formData.set("delivery_date", deliveryDate);
    formData.set("delivery_store_id", deliveryStoreId);
    formData.set("notes", notes);
    if (revision) formData.set("reason", reason);
    const serialised = serialiseLines(lines, "ordered_qty");
    formData.set(
      "lines",
      JSON.stringify(fromRequisition ? serialised.map(({ item_id, uom_id, ...line }) => line) : serialised),
    );
    startTransition(() => dispatch(formData));
  }

  return (
    <form onSubmit={handleSubmit} className="flex flex-col gap-4">
      {state?.status === "error" && !state.errors ? <Alert variant="danger">{state.message}</Alert> : null}
      {state?.status === "error" && state.errors?.lines?.[0] ? <Alert variant="danger">{state.errors.lines[0]}</Alert> : null}
      {fromRequisition ? <Alert variant="info">{t("from_requisition")}: {requisition.pr_no}</Alert> : null}

      <Card>
        <CardHeader>
          <CardTitle>{t("purchase_order")}</CardTitle>
        </CardHeader>
        <CardBody className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
          <FormField label={t("factory")} required error={state?.errors?.factory_id?.[0]}>
            {(fieldProps) => (
              <Select {...fieldProps} value={factoryId} onValueChange={setFactoryId} options={options.factories.map((f) => ({ value: f.id, label: f.name }))} placeholder={t("factory")} disabled={Boolean(order) || fromRequisition} />
            )}
          </FormField>
          <FormField label={t("vendor")} required error={state?.errors?.vendor_id?.[0]}>
            {(fieldProps) => <Select {...fieldProps} value={vendorId} onValueChange={pickVendor} options={vendorOptions} placeholder={t("pick_vendor")} disabled={Boolean(order)} />}
          </FormField>
          <FormField label={t("date")} required error={state?.errors?.po_date?.[0]}>
            {(fieldProps) => <Input {...fieldProps} type="date" value={poDate} onChange={(e) => setPoDate(e.target.value)} />}
          </FormField>
          <FormField label={t("currency")} error={state?.errors?.currency?.[0]}>
            {(fieldProps) => <Select {...fieldProps} value={currency} onValueChange={setCurrency} options={options.currencies.map((c) => ({ value: c, label: c }))} />}
          </FormField>
          <FormField label={t("payment_type")} error={state?.errors?.payment_type_id?.[0]}>
            {(fieldProps) => (
              <Select {...fieldProps} value={paymentTypeId} onValueChange={setPaymentTypeId} options={[{ value: "", label: "—" }, ...options.payment_types.map((p) => ({ value: p.id, label: p.name }))]} placeholder={t("payment_type")} />
            )}
          </FormField>
          <FormField label={t("payment_terms")} error={state?.errors?.payment_terms?.[0]}>
            {(fieldProps) => <Input {...fieldProps} value={paymentTerms} onChange={(e) => setPaymentTerms(e.target.value)} maxLength={255} />}
          </FormField>
          <FormField label={t("delivery_date")} error={state?.errors?.delivery_date?.[0]}>
            {(fieldProps) => <Input {...fieldProps} type="date" value={deliveryDate} onChange={(e) => setDeliveryDate(e.target.value)} />}
          </FormField>
          <FormField label={t("delivery_store")} error={state?.errors?.delivery_store_id?.[0]}>
            {(fieldProps) => (
              <Select {...fieldProps} value={deliveryStoreId} onValueChange={setDeliveryStoreId} options={[{ value: "", label: "—" }, ...stores.map((s) => ({ value: s.id, label: s.name }))]} placeholder={t("delivery_store")} />
            )}
          </FormField>
          <div className="sm:col-span-2 lg:col-span-4">
            <FormField label={t("notes")} error={state?.errors?.notes?.[0]}>
              {(fieldProps) => <Textarea {...fieldProps} value={notes} onChange={(e) => setNotes(e.target.value)} rows={2} maxLength={2000} />}
            </FormField>
          </div>
          {revision ? (
            <div className="sm:col-span-2 lg:col-span-4">
              <FormField label={t("revision_reason")} required error={state?.errors?.reason?.[0]}>
                {(fieldProps) => <Textarea {...fieldProps} value={reason} onChange={(e) => setReason(e.target.value)} rows={2} maxLength={1000} />}
              </FormField>
            </div>
          ) : null}
        </CardBody>
      </Card>

      <DocumentLinesEditor
        lines={lines}
        onChange={setLines}
        departments={options.item_departments}
        uoms={options.uoms}
        searchItems={searchItems}
        fields={["quantity", "uom", "rate", "required_date", "notes"]}
        quantityLabel={t("ordered_qty")}
        title={fromRequisition ? t("lines_to_order") : t("lines")}
        errors={state?.errors}
        fixedItems={fromRequisition}
        currency={currency}
      />

      <div className="flex justify-end gap-2">
        <Button type="button" variant="outline" onClick={() => router.back()}>
          {tc("cancel")}
        </Button>
        <Button type="submit" loading={pending}>
          {revision ? t("revise") : t("save_draft")}
        </Button>
      </div>
    </form>
  );
}

export { OrderForm };
