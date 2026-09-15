"use client";

import { startTransition, useActionState, useEffect } from "react";
import { useRouter } from "next/navigation";
import { Card, CardHeader, CardTitle, CardBody } from "@/components/ui/card";
import { FormField } from "@/components/ui/form-field";
import { Input } from "@/components/ui/input";
import { Select } from "@/components/ui/select";
import { Textarea } from "@/components/ui/textarea";
import { DateTimeField } from "@/components/ui/date-time-field";
import { Button } from "@/components/ui/button";
import { useToastManager } from "@/components/ui/toast";

const METHOD_OPTIONS = ["BANK_TRANSFER", "CASH", "CHEQUE", "CARD", "MOBILE", "GATEWAY"].map((value) => ({
  value,
  label: value.replace(/_/g, " ").toLowerCase().replace(/\b\w/g, (c) => c.toUpperCase()),
}));

/** Mirrors `invoice.blade.php`'s own `@can('billing.payment.manage')` record-payment card — only shown for an open invoice, to whoever can actually confirm money arrived. */
function RecordPaymentForm({ balanceDue, action }) {
  const [state, dispatch, pending] = useActionState(action, null);
  const router = useRouter();
  const toastManager = useToastManager();

  useEffect(() => {
    if (state?.status === "success") {
      toastManager.add({ title: "Payment recorded", type: "success" });
      router.refresh();
    } else if (state?.status === "error" && !state.errors) {
      toastManager.add({ title: state.message, type: "danger" });
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [state]);

  function handleSubmit(event) {
    event.preventDefault();
    const formData = new FormData(event.currentTarget);
    startTransition(() => dispatch(formData));
  }

  return (
    <Card>
      <CardHeader>
        <CardTitle>Record a payment</CardTitle>
      </CardHeader>
      <CardBody>
        <form onSubmit={handleSubmit} className="flex flex-col gap-4">
          <FormField label="Amount" required error={state?.errors?.amount?.[0]}>
            {(fieldProps) => (
              <Input {...fieldProps} name="amount" type="number" step="0.01" min="0.01" defaultValue={balanceDue} required />
            )}
          </FormField>
          <FormField label="Method" required error={state?.errors?.method?.[0]}>
            {(fieldProps) => <Select {...fieldProps} name="method" options={METHOD_OPTIONS} placeholder="Select a method" />}
          </FormField>
          <FormField label="Payment reference" error={state?.errors?.payment_reference?.[0]}>
            {(fieldProps) => <Input {...fieldProps} name="payment_reference" maxLength={64} />}
          </FormField>
          <FormField label="Paid at" error={state?.errors?.paid_at?.[0]}>
            {(fieldProps) => <DateTimeField {...fieldProps} name="paid_at" />}
          </FormField>
          <FormField label="Notes" error={state?.errors?.notes?.[0]}>
            {(fieldProps) => <Textarea {...fieldProps} name="notes" rows={2} maxLength={1000} />}
          </FormField>
          <Button type="submit" loading={pending}>
            Record payment
          </Button>
        </form>
      </CardBody>
    </Card>
  );
}

export { RecordPaymentForm };
