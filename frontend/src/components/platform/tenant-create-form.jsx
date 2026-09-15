"use client";

import { startTransition, useActionState, useState } from "react";
import Link from "next/link";
import { FormField } from "@/components/ui/form-field";
import { Input } from "@/components/ui/input";
import { Select } from "@/components/ui/select";
import { Button } from "@/components/ui/button";
import { Alert } from "@/components/ui/alert";
import { Card, CardHeader, CardTitle, CardBody, CardFooter } from "@/components/ui/card";

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
    <form onSubmit={handleSubmit} className="grid grid-cols-1 gap-5 lg:grid-cols-2">
      {state?.status === "error" && !state.errors ? (
        <Alert variant="danger" className="lg:col-span-2">
          {state.message}
        </Alert>
      ) : null}

      <Card>
        <CardHeader>
          <CardTitle>Company</CardTitle>
        </CardHeader>
        <CardBody className="flex flex-col gap-4">
          <FormField label="Company name" required error={state?.errors?.name?.[0]}>
            {(fieldProps) => <Input {...fieldProps} value={name} onChange={(e) => setName(e.target.value)} maxLength={255} required />}
          </FormField>
          <FormField
            label="Company code"
            required
            helperText="Ends up inside every work order and breakdown number this customer will ever issue."
            error={state?.errors?.code?.[0]}
          >
            {(fieldProps) => <Input {...fieldProps} value={code} onChange={(e) => setCode(e.target.value)} maxLength={32} required />}
          </FormField>
          <FormField label="Legal name">
            {(fieldProps) => <Input {...fieldProps} value={legalName} onChange={(e) => setLegalName(e.target.value)} maxLength={255} />}
          </FormField>
          <div className="grid grid-cols-3 gap-3">
            <FormField label="Currency" required className="col-span-1" error={state?.errors?.base_currency?.[0]}>
              {(fieldProps) => <Input {...fieldProps} value={currency} onChange={(e) => setCurrency(e.target.value.toUpperCase())} maxLength={3} required />}
            </FormField>
            <FormField label="Timezone" required className="col-span-2" error={state?.errors?.timezone?.[0]}>
              {(fieldProps) => <Input {...fieldProps} value={timezone} onChange={(e) => setTimezone(e.target.value)} maxLength={64} required />}
            </FormField>
          </div>
          <FormField label="Locale">
            {(fieldProps) => <Select {...fieldProps} value={locale} onValueChange={setLocale} options={LOCALE_OPTIONS} />}
          </FormField>
        </CardBody>
      </Card>

      <div className="flex flex-col gap-5">
        <Card>
          <CardHeader>
            <CardTitle>First factory</CardTitle>
          </CardHeader>
          <CardBody className="flex flex-col gap-4">
            <p className="text-xs text-foreground-muted">
              Created with the company — almost nothing works without one: machines live in a factory, numbers are
              issued per factory, the working calendar hangs off it.
            </p>
            <FormField label="Factory name" required error={state?.errors?.factory_name?.[0]}>
              {(fieldProps) => <Input {...fieldProps} value={factoryName} onChange={(e) => setFactoryName(e.target.value)} maxLength={255} required />}
            </FormField>
            <FormField label="Factory code" required error={state?.errors?.factory_code?.[0]}>
              {(fieldProps) => <Input {...fieldProps} value={factoryCode} onChange={(e) => setFactoryCode(e.target.value)} maxLength={32} required />}
            </FormField>
          </CardBody>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle>Owner account</CardTitle>
          </CardHeader>
          <CardBody className="flex flex-col gap-4">
            <p className="text-xs text-foreground-muted">
              Most people on a factory floor have no working email address, so a password is generated rather than
              sent as a reset link.
            </p>
            <FormField label="Owner name" required error={state?.errors?.owner_name?.[0]}>
              {(fieldProps) => <Input {...fieldProps} value={ownerName} onChange={(e) => setOwnerName(e.target.value)} maxLength={255} required />}
            </FormField>
            <FormField label="Owner email" required error={state?.errors?.owner_email?.[0]}>
              {(fieldProps) => <Input {...fieldProps} type="email" value={ownerEmail} onChange={(e) => setOwnerEmail(e.target.value)} maxLength={255} required />}
            </FormField>
          </CardBody>
          <CardFooter className="flex justify-end gap-2">
            <Link
              href="/platform"
              className="inline-flex h-10 items-center rounded-sm border border-border-strong px-4 text-sm font-medium hover:bg-surface-muted"
            >
              Cancel
            </Link>
            <Button type="submit" loading={pending}>
              Create customer
            </Button>
          </CardFooter>
        </Card>
      </div>
    </form>
  );
}

export { TenantCreateForm };
