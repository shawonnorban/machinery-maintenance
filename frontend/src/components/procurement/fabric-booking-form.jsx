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
 * A new fabric booking (Procurement spec §3).
 *
 * The style is optional on purpose: early in a season a merchandiser books
 * against the buyer's forecast before the style exists, and naming one is
 * what lets the over-booking check say anything at all.
 */
function FabricBookingForm({ options, styles, searchItems, action }) {
  const t = useT("procurement");
  const tc = useT("common");
  const router = useRouter();
  const [state, dispatch, pending] = useActionState(action, null);

  const [factoryId, setFactoryId] = useState(options.factories.length === 1 ? options.factories[0].id : "");
  const [vendorId, setVendorId] = useState("");
  const [currency, setCurrency] = useState("BDT");
  const [styleId, setStyleId] = useState("");
  const [bookingDate, setBookingDate] = useState(new Date().toISOString().slice(0, 10));
  const [requiredDate, setRequiredDate] = useState("");
  const [notes, setNotes] = useState("");
  const [lines, setLines] = useState([newLine()]);

  const errors = state?.errors ?? {};

  function chooseVendor(id) {
    setVendorId(id);

    const vendor = options.vendors.find((v) => v.id === id);

    if (vendor?.default_currency) setCurrency(vendor.default_currency);
  }

  function handleSubmit(event) {
    event.preventDefault();

    const formData = new FormData();
    formData.set("factory_id", factoryId);
    formData.set("vendor_id", vendorId);
    formData.set("currency", currency);
    formData.set("style_id", styleId);
    formData.set("booking_date", bookingDate);
    formData.set("required_date", requiredDate);
    formData.set("notes", notes);
    formData.set(
      "lines",
      JSON.stringify(
        // The shared editor calls the price column `rate`; a booking's is
        // tentative, which is the whole point of booking before ordering.
        serialiseLines(lines, "booked_qty").map(({ rate, ...line }) => ({
          ...line,
          ...(rate ? { tentative_rate: rate } : {}),
        })),
      ),
    );

    startTransition(() => dispatch(formData));
  }

  return (
    <form onSubmit={handleSubmit} className="flex flex-col gap-4">
      {state?.status === "error" && !state.errors ? <Alert variant="danger">{state.message}</Alert> : null}
      {errors.lines?.[0] ? <Alert variant="danger">{errors.lines[0]}</Alert> : null}

      <Card>
        <CardHeader>
          <CardTitle>{t("fabric_booking")}</CardTitle>
        </CardHeader>
        <CardBody className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
          <FormField label={t("factory")} required error={errors.factory_id?.[0]}>
            {(fp) => (
              <Select
                {...fp}
                value={factoryId}
                onValueChange={setFactoryId}
                options={options.factories.map((f) => ({ value: f.id, label: f.name }))}
                placeholder={t("factory")}
              />
            )}
          </FormField>

          <FormField label={t("vendor")} required error={errors.vendor_id?.[0]}>
            {(fp) => (
              <Select
                {...fp}
                value={vendorId}
                onValueChange={chooseVendor}
                options={options.vendors.map((v) => ({ value: v.id, label: v.code ? `${v.name} (${v.code})` : v.name }))}
                placeholder={t("vendor")}
              />
            )}
          </FormField>

          <FormField label={t("style")} error={errors.style_id?.[0]}>
            {(fp) => (
              <Select
                {...fp}
                value={styleId}
                onValueChange={setStyleId}
                options={[{ value: "", label: "—" }, ...styles.map((s) => ({ value: s.id, label: s.label }))]}
                placeholder={t("style")}
              />
            )}
          </FormField>

          <FormField label={t("currency")} error={errors.currency?.[0]}>
            {(fp) => (
              <Select
                {...fp}
                value={currency}
                onValueChange={setCurrency}
                options={(options.currencies ?? ["BDT"]).map((c) => ({ value: c, label: c }))}
              />
            )}
          </FormField>

          <FormField label={t("booking_date")} required error={errors.booking_date?.[0]}>
            {(fp) => <Input {...fp} type="date" value={bookingDate} onChange={(e) => setBookingDate(e.target.value)} />}
          </FormField>

          <FormField label={t("required_date")} error={errors.required_date?.[0]}>
            {(fp) => <Input {...fp} type="date" value={requiredDate} onChange={(e) => setRequiredDate(e.target.value)} />}
          </FormField>

          <div className="sm:col-span-2 lg:col-span-3">
            <FormField label={t("notes")} error={errors.notes?.[0]}>
              {(fp) => <Textarea {...fp} rows={2} value={notes} onChange={(e) => setNotes(e.target.value)} maxLength={2000} />}
            </FormField>
          </div>
        </CardBody>
      </Card>

      <DocumentLinesEditor
        lines={lines}
        onChange={setLines}
        departments={options.item_departments}
        uoms={options.uoms}
        searchItems={searchItems}
        fields={["quantity", "uom", "rate", "date"]}
        currency={currency}
        quantityLabel={t("booked_qty")}
        title={t("lines")}
        errors={errors}
      />

      <div className="flex justify-end gap-2">
        <Button type="button" variant="outline" onClick={() => router.back()}>
          {tc("cancel")}
        </Button>
        <Button type="submit" loading={pending} disabled={!factoryId || !vendorId}>
          {tc("save")}
        </Button>
      </div>
    </form>
  );
}

export { FabricBookingForm };
