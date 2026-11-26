"use client";

import { ArrowLeft, Save } from "lucide-react";
import { startTransition, useActionState, useState } from "react";
import { useRouter } from "next/navigation";
import { FormActions, FormSection } from "@/components/ui/form-section";
import { FormField } from "@/components/ui/form-field";
import { Input } from "@/components/ui/input";
import { Textarea } from "@/components/ui/textarea";
import { Select } from "@/components/ui/select";
import { Checkbox } from "@/components/ui/checkbox";
import { Button } from "@/components/ui/button";
import { Alert } from "@/components/ui/alert";
import { useT } from "@/lib/i18n";

function blank(option) {
  return [{ value: "", label: "—" }, ...option];
}

/**
 * Mirrors `vendor::vendors._form` — code is required on both create and edit
 * (unlike Asset/Factory's immutable-after-create pattern), since
 * `VendorApiController::validated()` accepts it either way.
 *
 * The procurement fields (registration, bank, what they supply, vetting)
 * sit in their own sections below the maintenance-era basics; a
 * service-only contractor can leave them all empty.
 */
function VendorForm({ vendor = null, options = {}, action }) {
  const t = useT("vendor");
  const tc = useT("common");
  const TYPE_OPTIONS = ["SUPPLIER", "SERVICE", "BOTH"].map((value) => ({ value, label: t(`type_${value.toLowerCase()}`) }));
  const STATUS_OPTIONS = ["ACTIVE", "INACTIVE", "BLACKLISTED"].map((value) => ({ value, label: t(`status_${value.toLowerCase()}`) }));
  const APPROVAL_OPTIONS = (options.approval_statuses ?? ["PENDING", "APPROVED"]).map((value) => ({ value, label: t(`approval_${value.toLowerCase()}`) }));
  const isEdit = vendor !== null;
  const router = useRouter();
  const [state, dispatch, pending] = useActionState(action, null);
  const [name, setName] = useState(vendor?.name ?? "");
  const [code, setCode] = useState(vendor?.code ?? "");
  const [vendorType, setVendorType] = useState(vendor?.vendor_type ?? "SUPPLIER");
  const [status, setStatus] = useState(vendor?.status ?? "ACTIVE");
  const [approvalStatus, setApprovalStatus] = useState(vendor?.approval_status ?? "PENDING");
  const [contactName, setContactName] = useState(vendor?.contact_name ?? "");
  const [phone, setPhone] = useState(vendor?.phone ?? "");
  const [email, setEmail] = useState(vendor?.email ?? "");
  const [address, setAddress] = useState(vendor?.address ?? "");
  const [countryOfOrigin, setCountryOfOrigin] = useState(vendor?.country_of_origin ?? "");
  const [taxReference, setTaxReference] = useState(vendor?.tax_reference ?? "");
  const [bin, setBin] = useState(vendor?.bin ?? "");
  const [tradeLicenseNo, setTradeLicenseNo] = useState(vendor?.trade_license_no ?? "");
  const [bankId, setBankId] = useState(vendor?.bank?.id ?? "");
  const [bankBranch, setBankBranch] = useState(vendor?.bank_branch ?? "");
  const [bankAccountNo, setBankAccountNo] = useState(vendor?.bank_account_no ?? "");
  const [bankSwift, setBankSwift] = useState(vendor?.bank_swift ?? "");
  const [supplyCategories, setSupplyCategories] = useState(vendor?.supply_categories ?? []);
  const [defaultCurrency, setDefaultCurrency] = useState(vendor?.default_currency ?? "");
  const [defaultPaymentTypeId, setDefaultPaymentTypeId] = useState(vendor?.default_payment_type?.id ?? "");
  const [defaultPaymentTerms, setDefaultPaymentTerms] = useState(vendor?.default_payment_terms ?? "");
  const [notes, setNotes] = useState(vendor?.notes ?? "");

  function toggleSupplyCategory(category, checked) {
    setSupplyCategories((current) => (checked ? [...new Set([...current, category])] : current.filter((c) => c !== category)));
  }

  function handleSubmit(event) {
    event.preventDefault();

    const formData = new FormData();
    formData.set("name", name);
    formData.set("code", code);
    formData.set("vendor_type", vendorType);
    formData.set("status", status);
    formData.set("approval_status", approvalStatus);
    if (contactName) formData.set("contact_name", contactName);
    if (phone) formData.set("phone", phone);
    if (email) formData.set("email", email);
    if (address) formData.set("address", address);
    if (countryOfOrigin) formData.set("country_of_origin", countryOfOrigin);
    if (taxReference) formData.set("tax_reference", taxReference);
    if (bin) formData.set("bin", bin);
    if (tradeLicenseNo) formData.set("trade_license_no", tradeLicenseNo);
    if (bankId) formData.set("bank_id", bankId);
    if (bankBranch) formData.set("bank_branch", bankBranch);
    if (bankAccountNo) formData.set("bank_account_no", bankAccountNo);
    if (bankSwift) formData.set("bank_swift", bankSwift);
    for (const category of supplyCategories) formData.append("supply_categories", category);
    if (defaultCurrency) formData.set("default_currency", defaultCurrency);
    if (defaultPaymentTypeId) formData.set("default_payment_type_id", defaultPaymentTypeId);
    if (defaultPaymentTerms) formData.set("default_payment_terms", defaultPaymentTerms);
    if (notes) formData.set("notes", notes);

    startTransition(() => dispatch(formData));
  }

  return (
    <form onSubmit={handleSubmit} className="flex flex-col gap-4">
      {state?.status === "error" && !state.errors ? <Alert variant="danger">{state.message}</Alert> : null}

      <FormSection>
      <div className="grid gap-x-8 gap-y-3 lg:grid-cols-2">
        <FormField label={t("name")} required error={state?.errors?.name?.[0]}>
          {(fieldProps) => <Input {...fieldProps} value={name} onChange={(e) => setName(e.target.value)} maxLength={255} required />}
        </FormField>
        <FormField label={t("code")} required error={state?.errors?.code?.[0]}>
          {(fieldProps) => <Input {...fieldProps} value={code} onChange={(e) => setCode(e.target.value)} maxLength={48} required />}
        </FormField>
        <FormField label={t("type")} required>
          {(fieldProps) => <Select {...fieldProps} value={vendorType} onValueChange={setVendorType} options={TYPE_OPTIONS} />}
        </FormField>
        <FormField label={t("status")} required>
          {(fieldProps) => <Select {...fieldProps} value={status} onValueChange={setStatus} options={STATUS_OPTIONS} />}
        </FormField>
        <FormField label={t("contact_name")}>
          {(fieldProps) => <Input {...fieldProps} value={contactName} onChange={(e) => setContactName(e.target.value)} maxLength={255} />}
        </FormField>
        <FormField label={t("phone")}>
          {(fieldProps) => <Input {...fieldProps} value={phone} onChange={(e) => setPhone(e.target.value)} maxLength={32} />}
        </FormField>
        <FormField label={t("email")} error={state?.errors?.email?.[0]}>
          {(fieldProps) => <Input {...fieldProps} type="email" value={email} onChange={(e) => setEmail(e.target.value)} />}
        </FormField>
        <FormField label={t("country_of_origin")}>
          {(fieldProps) => (
            <Input {...fieldProps} value={countryOfOrigin} onChange={(e) => setCountryOfOrigin(e.target.value)} maxLength={100} />
          )}
        </FormField>
      </div>

      <FormField label={t("address")}>
        {(fieldProps) => <Textarea {...fieldProps} value={address} onChange={(e) => setAddress(e.target.value)} rows={2} maxLength={1000} />}
      </FormField>

      </FormSection>

      <Section title={t("registration_section")}>
        <FormField label={t("tax_reference")} helperText={t("tax_reference_hint")}>
          {(fieldProps) => <Input {...fieldProps} value={taxReference} onChange={(e) => setTaxReference(e.target.value)} maxLength={64} />}
        </FormField>
        <FormField label={t("bin")} error={state?.errors?.bin?.[0]}>
          {(fieldProps) => <Input {...fieldProps} value={bin} onChange={(e) => setBin(e.target.value)} maxLength={32} />}
        </FormField>
        <FormField label={t("trade_license_no")} error={state?.errors?.trade_license_no?.[0]}>
          {(fieldProps) => <Input {...fieldProps} value={tradeLicenseNo} onChange={(e) => setTradeLicenseNo(e.target.value)} maxLength={64} />}
        </FormField>
        <FormField label={t("approval_status")} required error={state?.errors?.approval_status?.[0]}>
          {(fieldProps) => <Select {...fieldProps} value={approvalStatus} onValueChange={setApprovalStatus} options={APPROVAL_OPTIONS} />}
        </FormField>
      </Section>

      <Section title={t("bank_section")}>
        <FormField label={t("bank")} error={state?.errors?.bank_id?.[0]}>
          {(fieldProps) => (
            <Select {...fieldProps} value={bankId} onValueChange={setBankId} options={blank((options.banks ?? []).map((b) => ({ value: b.id, label: b.name })))} />
          )}
        </FormField>
        <FormField label={t("bank_branch")} error={state?.errors?.bank_branch?.[0]}>
          {(fieldProps) => <Input {...fieldProps} value={bankBranch} onChange={(e) => setBankBranch(e.target.value)} maxLength={255} />}
        </FormField>
        <FormField label={t("bank_account_no")} error={state?.errors?.bank_account_no?.[0]}>
          {(fieldProps) => <Input {...fieldProps} value={bankAccountNo} onChange={(e) => setBankAccountNo(e.target.value)} maxLength={64} />}
        </FormField>
        <FormField label={t("bank_swift")} error={state?.errors?.bank_swift?.[0]}>
          {(fieldProps) => <Input {...fieldProps} value={bankSwift} onChange={(e) => setBankSwift(e.target.value)} maxLength={16} />}
        </FormField>
      </Section>

      <Section title={t("procurement_section")}>
        <div className="grid items-start gap-x-3 gap-y-1 sm:grid-cols-[10.5rem_1fr] lg:col-span-2">
          <span className="pt-0.5 text-[13px] font-semibold text-foreground sm:text-right">{t("supply_categories")}</span>
          <div>
          <p className="text-xs text-foreground-muted">{t("supply_categories_hint")}</p>
          <div className="mt-2 flex flex-wrap gap-x-5 gap-y-2">
            {(options.supply_categories ?? []).map((category) => (
              <label key={category} className="flex items-center gap-2 text-sm text-foreground">
                <Checkbox checked={supplyCategories.includes(category)} onCheckedChange={(checked) => toggleSupplyCategory(category, checked)} />
                {t(`supply_${category.toLowerCase()}`)}
              </label>
            ))}
          </div>
          {state?.errors?.supply_categories?.[0] ? <p className="mt-1 text-xs text-danger">{state.errors.supply_categories[0]}</p> : null}
          </div>
        </div>
        <FormField label={t("default_currency")} error={state?.errors?.default_currency?.[0]}>
          {(fieldProps) => (
            <Input {...fieldProps} value={defaultCurrency} onChange={(e) => setDefaultCurrency(e.target.value.toUpperCase())} maxLength={3} placeholder="BDT" />
          )}
        </FormField>
        <FormField label={t("default_payment_type")} error={state?.errors?.default_payment_type_id?.[0]}>
          {(fieldProps) => (
            <Select
              {...fieldProps}
              value={defaultPaymentTypeId}
              onValueChange={setDefaultPaymentTypeId}
              options={blank((options.payment_types ?? []).map((p) => ({ value: p.id, label: p.name })))}
            />
          )}
        </FormField>
        <FormField label={t("default_payment_terms")} helperText={t("default_payment_terms_hint")} error={state?.errors?.default_payment_terms?.[0]}>
          {(fieldProps) => <Input {...fieldProps} value={defaultPaymentTerms} onChange={(e) => setDefaultPaymentTerms(e.target.value)} maxLength={64} />}
        </FormField>
      </Section>

      <FormSection legend={t("notes")}>
      <FormField label={t("notes")}>
        {(fieldProps) => <Textarea {...fieldProps} value={notes} onChange={(e) => setNotes(e.target.value)} rows={2} maxLength={2000} />}
      </FormField>
      </FormSection>

      <FormActions>
        <Button type="button" variant="outline" onClick={() => router.back()}>
          <ArrowLeft /> {tc("cancel")}
        </Button>
        <Button type="submit" loading={pending}>
          <Save /> {isEdit ? t("save_changes") : t("create_vendor")}
        </Button>
      </FormActions>
    </form>
  );
}

function Section({ title, children }) {
  return (
    <FormSection legend={title}>
      <div className="grid gap-x-8 gap-y-3 lg:grid-cols-2">{children}</div>
    </FormSection>
  );
}

export { VendorForm };
