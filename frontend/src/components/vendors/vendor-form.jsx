"use client";

import { startTransition, useActionState, useState } from "react";
import { useRouter } from "next/navigation";
import { FormField } from "@/components/ui/form-field";
import { Input } from "@/components/ui/input";
import { Textarea } from "@/components/ui/textarea";
import { Select } from "@/components/ui/select";
import { Button } from "@/components/ui/button";
import { Alert } from "@/components/ui/alert";
import { formatStatus } from "@/components/ui/status-badge";

const TYPE_OPTIONS = ["SUPPLIER", "SERVICE", "BOTH"].map((value) => ({ value, label: formatStatus(value) }));
const STATUS_OPTIONS = ["ACTIVE", "INACTIVE", "BLACKLISTED"].map((value) => ({ value, label: formatStatus(value) }));

/** Mirrors `vendor::vendors._form` — code is required on both create and edit (unlike Asset/Factory's immutable-after-create pattern), since `VendorApiController::validated()` accepts it either way. */
function VendorForm({ vendor = null, action }) {
  const isEdit = vendor !== null;
  const router = useRouter();
  const [state, dispatch, pending] = useActionState(action, null);
  const [name, setName] = useState(vendor?.name ?? "");
  const [code, setCode] = useState(vendor?.code ?? "");
  const [vendorType, setVendorType] = useState(vendor?.vendor_type ?? "SUPPLIER");
  const [status, setStatus] = useState(vendor?.status ?? "ACTIVE");
  const [contactName, setContactName] = useState(vendor?.contact_name ?? "");
  const [phone, setPhone] = useState(vendor?.phone ?? "");
  const [email, setEmail] = useState(vendor?.email ?? "");
  const [address, setAddress] = useState(vendor?.address ?? "");
  const [taxReference, setTaxReference] = useState(vendor?.tax_reference ?? "");
  const [notes, setNotes] = useState(vendor?.notes ?? "");

  function handleSubmit(event) {
    event.preventDefault();

    const formData = new FormData();
    formData.set("name", name);
    formData.set("code", code);
    formData.set("vendor_type", vendorType);
    formData.set("status", status);
    if (contactName) formData.set("contact_name", contactName);
    if (phone) formData.set("phone", phone);
    if (email) formData.set("email", email);
    if (address) formData.set("address", address);
    if (taxReference) formData.set("tax_reference", taxReference);
    if (notes) formData.set("notes", notes);

    startTransition(() => dispatch(formData));
  }

  return (
    <form onSubmit={handleSubmit} className="flex flex-col gap-5">
      {state?.status === "error" && !state.errors ? <Alert variant="danger">{state.message}</Alert> : null}

      <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <FormField label="Name" required error={state?.errors?.name?.[0]}>
          {(fieldProps) => <Input {...fieldProps} value={name} onChange={(e) => setName(e.target.value)} maxLength={255} required />}
        </FormField>
        <FormField label="Code" required error={state?.errors?.code?.[0]}>
          {(fieldProps) => <Input {...fieldProps} value={code} onChange={(e) => setCode(e.target.value)} maxLength={48} required />}
        </FormField>
        <FormField label="Type" required>
          {(fieldProps) => <Select {...fieldProps} value={vendorType} onValueChange={setVendorType} options={TYPE_OPTIONS} />}
        </FormField>
        <FormField label="Status" required>
          {(fieldProps) => <Select {...fieldProps} value={status} onValueChange={setStatus} options={STATUS_OPTIONS} />}
        </FormField>
        <FormField label="Contact name">
          {(fieldProps) => <Input {...fieldProps} value={contactName} onChange={(e) => setContactName(e.target.value)} maxLength={255} />}
        </FormField>
        <FormField label="Phone">
          {(fieldProps) => <Input {...fieldProps} value={phone} onChange={(e) => setPhone(e.target.value)} maxLength={32} />}
        </FormField>
        <FormField label="Email" error={state?.errors?.email?.[0]}>
          {(fieldProps) => <Input {...fieldProps} type="email" value={email} onChange={(e) => setEmail(e.target.value)} />}
        </FormField>
        <FormField label="Tax reference">
          {(fieldProps) => <Input {...fieldProps} value={taxReference} onChange={(e) => setTaxReference(e.target.value)} maxLength={64} />}
        </FormField>
      </div>

      <FormField label="Address">
        {(fieldProps) => <Textarea {...fieldProps} value={address} onChange={(e) => setAddress(e.target.value)} rows={2} maxLength={1000} />}
      </FormField>

      <FormField label="Notes">
        {(fieldProps) => <Textarea {...fieldProps} value={notes} onChange={(e) => setNotes(e.target.value)} rows={2} maxLength={2000} />}
      </FormField>

      <div className="flex justify-end gap-2">
        <Button type="button" variant="outline" onClick={() => router.back()}>
          Cancel
        </Button>
        <Button type="submit" loading={pending}>
          {isEdit ? "Save changes" : "Create vendor"}
        </Button>
      </div>
    </form>
  );
}

export { VendorForm };
