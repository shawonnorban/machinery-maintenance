"use client";

import { useState, useTransition } from "react";
import { useRouter } from "next/navigation";
import { Plus, Trash2 } from "lucide-react";
import { Card, CardHeader, CardTitle, CardBody } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Select } from "@/components/ui/select";
import { Switch } from "@/components/ui/switch";
import { Textarea } from "@/components/ui/textarea";
import { FormField } from "@/components/ui/form-field";
import { useToastManager } from "@/components/ui/toast";
import { ItemPicker } from "@/components/procurement/item-picker";
import { useT } from "@/lib/i18n";

/**
 * A new rate contract (Procurement spec §14).
 *
 * Several lines may name the same item, because that is how a price break
 * is written: one line from zero, another from the quantity where the
 * cheaper rate starts. The form does not fight that — the API only refuses
 * two lines at the *same* band.
 */
function RateContractForm({ factories, vendors, departments, searchItems, onSubmit }) {
  const t = useT("procurement");
  const tc = useT("common");
  const router = useRouter();
  const toastManager = useToastManager();
  const [pending, startTransition] = useTransition();

  const [factoryId, setFactoryId] = useState(factories[0]?.id ?? "");
  const [vendorId, setVendorId] = useState("");
  const [companyWide, setCompanyWide] = useState(true);
  const [title, setTitle] = useState("");
  const [supplierReference, setSupplierReference] = useState("");
  const [validFrom, setValidFrom] = useState("");
  const [validTo, setValidTo] = useState("");
  const [currency, setCurrency] = useState("BDT");
  const [notes, setNotes] = useState("");
  const [lines, setLines] = useState([{ item: null, rate: "", min_quantity: "", lead_time_days: "" }]);
  const [errors, setErrors] = useState({});

  const ready = vendorId && validFrom && validTo && lines.some((line) => line.item && line.rate);

  function setLine(index, key, value) {
    setLines((current) => current.map((line, position) => (position === index ? { ...line, [key]: value } : line)));
  }

  function submit(event) {
    event.preventDefault();
    setErrors({});

    startTransition(async () => {
      const result = await onSubmit({
        factory_id: factoryId,
        vendor_id: vendorId,
        company_wide: companyWide,
        title: title || undefined,
        supplier_reference: supplierReference || undefined,
        valid_from: validFrom,
        valid_to: validTo,
        currency,
        notes: notes || undefined,
        lines: lines
          .filter((line) => line.item && line.rate !== "")
          .map((line) => ({
            item_id: line.item.id,
            rate: line.rate,
            min_quantity: line.min_quantity || undefined,
            lead_time_days: line.lead_time_days || undefined,
          })),
      });

      if (result?.status === "error") {
        setErrors(result.errors ?? {});
        toastManager.add({ type: "danger", title: result.message ?? tc("something_went_wrong") });
        return;
      }

      toastManager.add({ type: "success", title: t("contract_created", { number: result.data?.contract_no ?? "" }) });
      router.push(`/procurement/rate-contracts/${result.data.id}`);
    });
  }

  return (
    <form onSubmit={submit} className="flex flex-col gap-4">
      <Card>
        <CardHeader>
          <CardTitle>{t("rate_contract")}</CardTitle>
        </CardHeader>
        <CardBody className="grid grid-cols-1 gap-4 sm:grid-cols-3">
          <FormField label={t("vendor")} required error={errors.vendor_id?.[0]}>
            {(props) => (
              <Select
                {...props}
                options={[
                  { value: "", label: "—" },
                  ...vendors.map((vendor) => ({ value: vendor.id, label: vendor.name })),
                ]}
                value={vendorId}
                onValueChange={(value) => {
                  setVendorId(value);
                  const vendor = vendors.find((row) => row.id === value);
                  if (vendor?.default_currency) setCurrency(vendor.default_currency);
                }}
              />
            )}
          </FormField>

          <FormField label={t("factory")} required error={errors.factory_id?.[0]}>
            {(props) => (
              <Select
                {...props}
                options={factories.map((factory) => ({ value: factory.id, label: factory.name }))}
                value={factoryId}
                onValueChange={setFactoryId}
              />
            )}
          </FormField>

          <FormField label={t("currency")}>
            {(props) => (
              <Select
                {...props}
                options={["BDT", "USD", "EUR", "CNY", "INR"].map((value) => ({ value, label: value }))}
                value={currency}
                onValueChange={setCurrency}
              />
            )}
          </FormField>

          <FormField label={t("contract_title")} className="sm:col-span-2">
            {(props) => <Input {...props} value={title} onChange={(event) => setTitle(event.target.value)} />}
          </FormField>

          <FormField label={t("supplier_reference")}>
            {(props) => (
              <Input
                {...props}
                value={supplierReference}
                onChange={(event) => setSupplierReference(event.target.value)}
              />
            )}
          </FormField>

          <FormField label={t("valid_from")} required error={errors.valid_from?.[0]}>
            {(props) => (
              <Input {...props} type="date" value={validFrom} onChange={(event) => setValidFrom(event.target.value)} />
            )}
          </FormField>

          <FormField label={t("valid_to")} required error={errors.valid_to?.[0]}>
            {(props) => (
              <Input {...props} type="date" value={validTo} onChange={(event) => setValidTo(event.target.value)} />
            )}
          </FormField>

          <label className="flex items-start gap-3 sm:col-span-3">
            <Switch checked={companyWide} onCheckedChange={setCompanyWide} />
            <span className="flex flex-col">
              <span className="text-sm font-medium">{t("company_wide")}</span>
              <span className="text-xs text-foreground-muted">{t("company_wide_hint")}</span>
            </span>
          </label>
        </CardBody>
      </Card>

      <Card>
        <CardHeader>
          <CardTitle>{t("agreed_rate")}</CardTitle>
          <Button
            type="button"
            size="sm"
            variant="outline"
            onClick={() => setLines((current) => [...current, { item: null, rate: "", min_quantity: "", lead_time_days: "" }])}
          >
            <Plus /> {t("add_line")}
          </Button>
        </CardHeader>
        <CardBody className="flex flex-col gap-3">
          {errors.lines ? <p className="text-xs text-danger">{errors.lines[0]}</p> : null}

          {lines.map((line, index) => (
            <div key={index} className="grid grid-cols-1 gap-2 sm:grid-cols-12">
              <div className="sm:col-span-5">
                <ItemPicker
                  value={line.item}
                  onChange={(item) => setLine(index, "item", item)}
                  departments={departments}
                  searchItems={searchItems}
                />
              </div>
              <div className="sm:col-span-2">
                <Input
                  type="number"
                  step="any"
                  placeholder={t("agreed_rate")}
                  value={line.rate}
                  onChange={(event) => setLine(index, "rate", event.target.value)}
                />
              </div>
              <div className="sm:col-span-2">
                <Input
                  type="number"
                  step="any"
                  min="0"
                  placeholder={t("min_quantity")}
                  value={line.min_quantity}
                  onChange={(event) => setLine(index, "min_quantity", event.target.value)}
                />
              </div>
              <div className="sm:col-span-2">
                <Input
                  type="number"
                  min="0"
                  placeholder={t("lead_time_days")}
                  value={line.lead_time_days}
                  onChange={(event) => setLine(index, "lead_time_days", event.target.value)}
                />
              </div>
              <div className="flex items-center sm:col-span-1">
                <Button
                  type="button"
                  size="sm"
                  variant="outline"
                  disabled={lines.length === 1}
                  onClick={() => setLines((current) => current.filter((_, position) => position !== index))}
                >
                  <Trash2 />
                </Button>
              </div>
            </div>
          ))}

          <p className="text-xs text-foreground-muted">{t("min_quantity_hint")}</p>
        </CardBody>
      </Card>

      <Card>
        <CardBody>
          <FormField label={t("notes")}>
            {(props) => <Textarea {...props} rows={3} value={notes} onChange={(event) => setNotes(event.target.value)} />}
          </FormField>
        </CardBody>
      </Card>

      <div className="flex justify-end gap-2">
        <Button type="button" variant="outline" onClick={() => router.back()} disabled={pending}>
          {tc("cancel")}
        </Button>
        <Button type="submit" disabled={pending || !ready}>
          {tc("save")}
        </Button>
      </div>
    </form>
  );
}

export { RateContractForm };
