"use client";

import { startTransition, useActionState, useState } from "react";
import Link from "next/link";
import { FormField } from "@/components/ui/form-field";
import { Input } from "@/components/ui/input";
import { Select } from "@/components/ui/select";
import { Button, buttonVariants } from "@/components/ui/button";
import { FormSection, FormActions } from "@/components/ui/form-section";
import { cn } from "@/lib/utils";
import { Alert } from "@/components/ui/alert";
import { Card, CardBody } from "@/components/ui/card";

const LOCALE_OPTIONS = [
  { value: "bn", label: "বাংলা" },
  { value: "en", label: "English" },
];

/** Mirrors `tenants/create.blade.php` — company, first factory, and owner account, one form. */
function TenantCreateForm({ action }) {
  const [state, dispatch, pending] = useActionState(action, null);

  const [name, setName] = useState("");
  const [code, setCode] = useState("");
  const [legalName, setLegalName] = useState("");
  const [currency, setCurrency] = useState("BDT");
  const [timezone, setTimezone] = useState("Asia/Dhaka");
  const [locale, setLocale] = useState("bn");
  const [factoryName, setFactoryName] = useState("");
  const [factoryCode, setFactoryCode] = useState("");
  const [ownerName, setOwnerName] = useState("");
  const [ownerEmail, setOwnerEmail] = useState("");

  if (state?.status === "success") {
    return (
      <Card className="max-w-xl">
        <CardBody className="flex flex-col gap-4">
          <Alert variant="success" title={`${state.companyName} created`}>
            Share this password with the new owner — it will not be shown again.
          </Alert>
          <div>
            <p className="text-xs font-medium text-foreground-muted">Owner email</p>
            <p className="text-sm text-foreground">{state.ownerEmail}</p>
          </div>
          <div>
            <p className="text-xs font-medium text-foreground-muted">Password</p>
            <div className="mt-1 rounded-sm border border-border bg-surface-muted px-4 py-3 font-mono text-sm">
              {state.password}
            </div>
          </div>
          <div>
            <Link href={`/platform/tenants/${state.companyId}`} className="text-sm font-medium text-brand hover:underline">
              Go to their account →
            </Link>
          </div>
        </CardBody>
      </Card>
    );
  }

  function handleSubmit(event) {
    event.preventDefault();

    const formData = new FormData();
    formData.set("name", name);
    formData.set("code", code);
    if (legalName) formData.set("legal_name", legalName);
    formData.set("base_currency", currency);
    formData.set("timezone", timezone);
    formData.set("default_locale", locale);
    formData.set("factory_name", factoryName);
    formData.set("factory_code", factoryCode);
    formData.set("owner_name", ownerName);
    formData.set("owner_email", ownerEmail);

    startTransition(() => dispatch(formData));
  }

  return (
    <form onSubmit={handleSubmit} className="flex flex-col gap-4">
      {state?.status === "error" && !state.errors ? <Alert variant="danger">{state.message}</Alert> : null}

      <FormSection legend="Company">
        <div className="grid grid-cols-1 gap-3 lg:grid-cols-2">
          <FormField label="Company name" required error={state?.errors?.name?.[0]}>
            {(fieldProps) => <Input {...fieldProps} value={name} onChange={(e) => setName(e.target.value)} maxLength={255} required />}
          </FormField>
          <FormField label="Company code" required helperText="Part of every number this customer issues." error={state?.errors?.code?.[0]}>
            {(fieldProps) => <Input {...fieldProps} value={code} onChange={(e) => setCode(e.target.value.toUpperCase())} maxLength={32} required />}
          </FormField>
          <FormField label="Legal name">
            {(fieldProps) => <Input {...fieldProps} value={legalName} onChange={(e) => setLegalName(e.target.value)} maxLength={255} />}
          </FormField>
          <FormField label="Locale">
            {(fieldProps) => <Select {...fieldProps} value={locale} onValueChange={setLocale} options={LOCALE_OPTIONS} />}
          </FormField>
          <FormField label="Currency" required error={state?.errors?.base_currency?.[0]}>
            {(fieldProps) => <Input {...fieldProps} value={currency} onChange={(e) => setCurrency(e.target.value.toUpperCase())} maxLength={3} required />}
          </FormField>
          <FormField label="Timezone" required error={state?.errors?.timezone?.[0]}>
            {(fieldProps) => <Input {...fieldProps} value={timezone} onChange={(e) => setTimezone(e.target.value)} maxLength={64} required />}
          </FormField>
        </div>
      </FormSection>

      <div className="grid grid-cols-1 gap-4 lg:grid-cols-2">
        <FormSection legend="First Factory">
          <p className="text-xs text-foreground-muted">Created with the company — machines live in a factory, numbers are issued per factory and the working calendar hangs off it.</p>
          <FormField label="Factory name" required error={state?.errors?.factory_name?.[0]}>
            {(fieldProps) => <Input {...fieldProps} value={factoryName} onChange={(e) => setFactoryName(e.target.value)} maxLength={255} required />}
          </FormField>
          <FormField label="Factory code" required error={state?.errors?.factory_code?.[0]}>
            {(fieldProps) => <Input {...fieldProps} value={factoryCode} onChange={(e) => setFactoryCode(e.target.value.toUpperCase())} maxLength={32} required />}
          </FormField>
        </FormSection>

        <FormSection legend="Owner Account">
          <p className="text-xs text-foreground-muted">A password is generated and shown once (most people on a factory floor have no working email for a reset link).</p>
          <FormField label="Owner name" required error={state?.errors?.owner_name?.[0]}>
            {(fieldProps) => <Input {...fieldProps} value={ownerName} onChange={(e) => setOwnerName(e.target.value)} maxLength={255} required />}
          </FormField>
          <FormField label="Owner email" required error={state?.errors?.owner_email?.[0]}>
            {(fieldProps) => <Input {...fieldProps} type="email" value={ownerEmail} onChange={(e) => setOwnerEmail(e.target.value)} maxLength={255} required />}
          </FormField>
        </FormSection>
      </div>

      <p className="text-xs text-foreground-muted">The new customer starts with the core modules only (Access Control, Approval Center, Settings, Company Management, Chat, HR &amp; Payroll); switch on what was sold in its Modules tab.</p>

      <FormActions>
        <Link href="/platform" className={cn(buttonVariants({ variant: "outline" }))}>
          Back to customers
        </Link>
        <Button type="submit" loading={pending}>
          Create customer
        </Button>
      </FormActions>
    </form>
  );
}

export { TenantCreateForm };
