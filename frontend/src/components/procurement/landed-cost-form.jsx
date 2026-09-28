"use client";

import { useState, useTransition } from "react";
import { useRouter } from "next/navigation";
import { Plus, Trash2 } from "lucide-react";
import { Card, CardHeader, CardTitle, CardBody } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Select } from "@/components/ui/select";
import { Textarea } from "@/components/ui/textarea";
import { Checkbox } from "@/components/ui/checkbox";
import { FormField } from "@/components/ui/form-field";
import { FormattedDateTime } from "@/components/ui/formatted-date-time";
import { useToastManager } from "@/components/ui/toast";
import { formatNumber } from "@/lib/format";
import { useT } from "@/lib/i18n";

const CHARGE_TYPES = ["FREIGHT", "DUTY", "CNF", "INSURANCE", "BANK_CHARGE", "OTHER"];

/**
 * A new landed cost voucher (Procurement spec §11).
 *
 * Two lists rather than a wizard: the receipts the charges came in on, and
 * the charges themselves. Both are needed before anything can be worked
 * out, and a voucher is nearly always typed from one clearing agent's bill
 * covering one consignment — so seeing both at once is faster than being
 * walked through them.
 */
function LandedCostForm({ receipts, factories, vendors, onSubmit }) {
  const t = useT("procurement");
  const tc = useT("common");
  const router = useRouter();
  const toastManager = useToastManager();
  const [pending, startTransition] = useTransition();

  const [factoryId, setFactoryId] = useState(factories[0]?.id ?? "");
  const [basis, setBasis] = useState("VALUE");
  const [voucherDate, setVoucherDate] = useState("");
  const [notes, setNotes] = useState("");
  const [selected, setSelected] = useState([]);
  const [charges, setCharges] = useState([{ charge_type: "FREIGHT", amount: "", label: "", vendor_id: "", reference_no: "" }]);
  const [errors, setErrors] = useState({});

  const available = receipts.filter((receipt) => !factoryId || receipt.factory?.id === factoryId);
  const total = charges.reduce((sum, charge) => sum + (Number(charge.amount) || 0), 0);

  function toggle(id) {
    setSelected((current) => (current.includes(id) ? current.filter((value) => value !== id) : [...current, id]));
  }

  function setCharge(index, key, value) {
    setCharges((current) => current.map((charge, position) => (position === index ? { ...charge, [key]: value } : charge)));
  }

  function submit(event) {
    event.preventDefault();
    setErrors({});

    startTransition(async () => {
      const result = await onSubmit({
        factory_id: factoryId,
        basis,
        voucher_date: voucherDate || undefined,
        notes: notes || undefined,
        goods_receipt_ids: selected,
        charges: charges
          .filter((charge) => charge.amount !== "")
          .map((charge) => ({
            charge_type: charge.charge_type,
            amount: charge.amount,
            label: charge.label || undefined,
            vendor_id: charge.vendor_id || undefined,
            reference_no: charge.reference_no || undefined,
          })),
      });

      if (result?.status === "error") {
        setErrors(result.errors ?? {});
        toastManager.add({ type: "danger", title: result.message ?? tc("something_went_wrong") });
        return;
      }

      toastManager.add({ type: "success", title: t("landed_cost_created", { number: result.data?.voucher_no ?? "" }) });
      router.push(`/procurement/landed-costs/${result.data.id}`);
    });
  }

  return (
    <form onSubmit={submit} className="flex flex-col gap-4">
      <Card>
        <CardHeader>
          <CardTitle>{t("landed_cost")}</CardTitle>
        </CardHeader>
        <CardBody className="grid grid-cols-1 gap-4 sm:grid-cols-3">
          <FormField label={t("factory")} required error={errors.factory_id?.[0]}>
            {(props) => (
              <Select
                {...props}
                options={factories.map((factory) => ({ value: factory.id, label: factory.name }))}
                value={factoryId}
                onValueChange={(value) => {
                  setFactoryId(value);
                  setSelected([]);
                }}
              />
            )}
          </FormField>

          <FormField label={t("basis")} required helperText={t("basis_hint")} error={errors.basis?.[0]}>
            {(props) => (
              <Select
                {...props}
                options={["VALUE", "QTY"].map((value) => ({ value, label: t(`bases.${value}`) }))}
                value={basis}
                onValueChange={setBasis}
              />
            )}
          </FormField>

          <FormField label={t("voucher_date")} error={errors.voucher_date?.[0]}>
            {(props) => <Input {...props} type="date" value={voucherDate} onChange={(event) => setVoucherDate(event.target.value)} />}
          </FormField>
        </CardBody>
      </Card>

      <Card>
        <CardHeader>
          <CardTitle>{t("receipts")}</CardTitle>
        </CardHeader>
        <CardBody className="flex flex-col gap-2">
          {errors.goods_receipt_ids ? <p className="text-xs text-danger">{errors.goods_receipt_ids[0]}</p> : null}
          {available.length === 0 ? (
            <p className="text-sm text-foreground-muted">{t("landed_cost_no_receipts")}</p>
          ) : (
            available.map((receipt) => (
              <label key={receipt.id} className="flex items-center gap-3 rounded-sm border border-border p-2 text-sm">
                <Checkbox checked={selected.includes(receipt.id)} onCheckedChange={() => toggle(receipt.id)} />
                <span className="font-medium">{receipt.grn_no}</span>
                <FormattedDateTime value={receipt.grn_date} mode="date" />
                <span className="text-foreground-muted">{receipt.vendor?.name ?? ""}</span>
                {/* Already part of a valuation: charging it again is a second voucher, not this one. */}
                {receipt.rate_locked ? <span className="ml-auto text-xs text-warning">{t("rate_locked")}</span> : null}
              </label>
            ))
          )}
        </CardBody>
      </Card>

      <Card>
        <CardHeader>
          <CardTitle>{t("charges")}</CardTitle>
          <Button
            type="button"
            size="sm"
            variant="outline"
            onClick={() => setCharges((current) => [...current, { charge_type: "OTHER", amount: "", label: "", vendor_id: "", reference_no: "" }])}
          >
            <Plus /> {t("add_charge")}
          </Button>
        </CardHeader>
        <CardBody className="flex flex-col gap-3">
          {errors.charges ? <p className="text-xs text-danger">{errors.charges[0]}</p> : null}
          {charges.map((charge, index) => (
            <div key={index} className="grid grid-cols-1 gap-2 sm:grid-cols-12">
              <div className="sm:col-span-3">
                <Select
                  options={CHARGE_TYPES.map((value) => ({ value, label: t(`charge_types.${value}`) }))}
                  value={charge.charge_type}
                  onValueChange={(value) => setCharge(index, "charge_type", value)}
                />
              </div>
              <div className="sm:col-span-2">
                <Input
                  type="number"
                  step="any"
                  placeholder={t("amount")}
                  value={charge.amount}
                  onChange={(event) => setCharge(index, "amount", event.target.value)}
                />
              </div>
              <div className="sm:col-span-3">
                <Select
                  options={[{ value: "", label: t("vendor") }, ...vendors.map((vendor) => ({ value: vendor.id, label: vendor.name }))]}
                  value={charge.vendor_id}
                  onValueChange={(value) => setCharge(index, "vendor_id", value)}
                />
              </div>
              <div className="sm:col-span-3">
                <Input
                  placeholder={t("reference_no")}
                  value={charge.reference_no}
                  onChange={(event) => setCharge(index, "reference_no", event.target.value)}
                />
              </div>
              <div className="flex items-center sm:col-span-1">
                <Button
                  type="button"
                  size="sm"
                  variant="outline"
                  disabled={charges.length === 1}
                  onClick={() => setCharges((current) => current.filter((_, position) => position !== index))}
                >
                  <Trash2 />
                </Button>
              </div>
            </div>
          ))}

          <div className="flex items-baseline justify-between border-t border-border pt-2 text-sm font-medium">
            <span>{t("total_charges")}</span>
            <span className="tabular-nums">{formatNumber(total, { maximumFractionDigits: 2 })}</span>
          </div>
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
        <Button type="submit" disabled={pending || selected.length === 0}>
          {tc("save")}
        </Button>
      </div>
    </form>
  );
}

export { LandedCostForm };
