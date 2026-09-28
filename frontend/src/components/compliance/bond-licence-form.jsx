"use client";

import { useState, useTransition } from "react";
import { useRouter } from "next/navigation";
import { Plus, Trash2 } from "lucide-react";
import { Card, CardHeader, CardTitle, CardBody } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Select } from "@/components/ui/select";
import { Textarea } from "@/components/ui/textarea";
import { FormField } from "@/components/ui/form-field";
import { useToastManager } from "@/components/ui/toast";
import { ItemPicker } from "@/components/procurement/item-picker";
import { useT } from "@/lib/i18n";

/**
 * A new bond licence and the entitlements printed on it (Shared Systems §3.1).
 *
 * Entitlements are optional here: the licence often arrives before the
 * annexure listing quantities does, and refusing to record it until then
 * would leave the imports it permits with nothing to hang off.
 */
function BondLicenceForm({ factories, bases, departments, searchItems, onSubmit }) {
  const t = useT("compliance");
  const tc = useT("common");
  const router = useRouter();
  const toastManager = useToastManager();
  const [pending, startTransition] = useTransition();

  const [factoryId, setFactoryId] = useState(factories[0]?.id ?? "");
  const [licenceNo, setLicenceNo] = useState("");
  const [issuingOffice, setIssuingOffice] = useState("");
  const [issuedDate, setIssuedDate] = useState("");
  const [validFrom, setValidFrom] = useState("");
  const [validTo, setValidTo] = useState("");
  const [notes, setNotes] = useState("");
  const [lines, setLines] = useState([]);
  const [errors, setErrors] = useState({});

  const ready = factoryId && licenceNo && issuingOffice && validFrom && validTo;

  function setLine(index, key, value) {
    setLines((current) => current.map((line, position) => (position === index ? { ...line, [key]: value } : line)));
  }

  function submit(event) {
    event.preventDefault();
    setErrors({});

    startTransition(async () => {
      const result = await onSubmit({
        factory_id: factoryId,
        licence_no: licenceNo,
        issuing_office: issuingOffice,
        issued_date: issuedDate || undefined,
        valid_from: validFrom,
        valid_to: validTo,
        notes: notes || undefined,
        entitlements: lines
          .filter((line) => line.item && line.entitled_quantity !== "")
          .map((line) => ({
            item_id: line.item.id,
            entitled_quantity: line.entitled_quantity,
            basis: line.basis || undefined,
            period_start: line.period_start || undefined,
            period_end: line.period_end || undefined,
          })),
      });

      if (result?.status === "error") {
        setErrors(result.errors ?? {});
        toastManager.add({ type: "danger", title: result.message ?? tc("something_went_wrong") });
        return;
      }

      toastManager.add({ type: "success", title: t("licence_created", { number: result.data?.licence_no ?? "" }) });
      router.push(`/compliance/bond-licences/${result.data.id}`);
    });
  }

  return (
    <form onSubmit={submit} className="flex flex-col gap-4">
      <Card>
        <CardHeader>
          <CardTitle>{t("bond_licence")}</CardTitle>
        </CardHeader>
        <CardBody className="grid grid-cols-1 gap-4 sm:grid-cols-3">
          <FormField label={t("licence_no")} required error={errors.licence_no?.[0]}>
            {(props) => <Input {...props} value={licenceNo} onChange={(event) => setLicenceNo(event.target.value)} />}
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

          <FormField label={t("issuing_office")} required error={errors.issuing_office?.[0]}>
            {(props) => (
              <Input {...props} value={issuingOffice} onChange={(event) => setIssuingOffice(event.target.value)} />
            )}
          </FormField>

          <FormField label={t("issued_date")} error={errors.issued_date?.[0]}>
            {(props) => (
              <Input {...props} type="date" value={issuedDate} onChange={(event) => setIssuedDate(event.target.value)} />
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
        </CardBody>
      </Card>

      <Card>
        <CardHeader>
          <CardTitle>{t("entitlements")}</CardTitle>
          <Button
            type="button"
            size="sm"
            variant="outline"
            onClick={() =>
              setLines((current) => [
                ...current,
                { item: null, entitled_quantity: "", basis: bases[0] ?? "ANNUAL", period_start: "", period_end: "" },
              ])
            }
          >
            <Plus /> {t("entitled_quantity")}
          </Button>
        </CardHeader>
        <CardBody className="flex flex-col gap-3">
          {errors.entitlements ? <p className="text-xs text-danger">{errors.entitlements[0]}</p> : null}

          {lines.map((line, index) => (
            <div key={index} className="grid grid-cols-1 gap-2 sm:grid-cols-12">
              <div className="sm:col-span-4">
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
                  min="0"
                  placeholder={t("entitled_quantity")}
                  value={line.entitled_quantity}
                  onChange={(event) => setLine(index, "entitled_quantity", event.target.value)}
                />
              </div>
              <div className="sm:col-span-2">
                <Select
                  options={bases.map((value) => ({ value, label: t(`bases.${value}`) }))}
                  value={line.basis}
                  onValueChange={(value) => setLine(index, "basis", value)}
                />
              </div>
              {/* An annual ceiling still has a window; a per-order one takes the order's. */}
              <div className="grid grid-cols-2 gap-2 sm:col-span-3">
                <Input
                  type="date"
                  value={line.period_start}
                  onChange={(event) => setLine(index, "period_start", event.target.value)}
                />
                <Input
                  type="date"
                  value={line.period_end}
                  onChange={(event) => setLine(index, "period_end", event.target.value)}
                />
              </div>
              <div className="flex items-center sm:col-span-1">
                <Button
                  type="button"
                  size="sm"
                  variant="outline"
                  onClick={() => setLines((current) => current.filter((_, position) => position !== index))}
                >
                  <Trash2 />
                </Button>
              </div>
            </div>
          ))}

          <p className="text-xs text-foreground-muted">{t("entitlement_hint")}</p>
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

export { BondLicenceForm };
