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
import { useT } from "@/lib/i18n";

const METHODS = ["BANK_TRANSFER", "CASH", "CHEQUE", "CARD", "MOBILE", "GATEWAY"];

/** Mirrors `invoice.blade.php`'s own `@can('billing.payment.manage')` record-payment card — only shown for an open invoice, to whoever can actually confirm money arrived. */
function RecordPaymentForm({ balanceDue, action }) {
  const t = useT("billing");
  const METHOD_OPTIONS = METHODS.map((value) => ({ value, label: t(`methods.${value}`) }));
  const [state, dispatch, pending] = useActionState(action, null);
  const router = useRouter();
  const toastManager = useToastManager();

  useEffect(() => {
    if (state?.status === "success") {
      toastManager.add({ title: t("payment_recorded"), type: "success" });
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
        <CardTitle>{t("record_payment")}</CardTitle>
      </CardHeader>
      <CardBody>
        <form onSubmit={handleSubmit} className="flex flex-col gap-4">
          <FormField label={t("amount")} required error={state?.errors?.amount?.[0]}>
            {(fieldProps) => (
              <Input {...fieldProps} name="amount" type="number" step="0.01" min="0.01" defaultValue={balanceDue} required />
            )}
          </FormField>
          <FormField label={t("method")} required error={state?.errors?.method?.[0]}>
            {(fieldProps) => <Select {...fieldProps} name="method" options={METHOD_OPTIONS} placeholder={t("select_a_method")} />}
          </FormField>
          <FormField label={t("payment_reference")} error={state?.errors?.payment_reference?.[0]}>
            {(fieldProps) => <Input {...fieldProps} name="payment_reference" maxLength={64} />}
          </FormField>
          <FormField label={t("paid_at_field")} error={state?.errors?.paid_at?.[0]}>
            {(fieldProps) => <DateTimeField {...fieldProps} name="paid_at" />}
          </FormField>
          <FormField label={t("notes")} error={state?.errors?.notes?.[0]}>
            {(fieldProps) => <Textarea {...fieldProps} name="notes" rows={2} maxLength={1000} />}
          </FormField>
          <Button type="submit" loading={pending}>
            {t("record_payment")}
          </Button>
        </form>
      </CardBody>
    </Card>
  );
}

export { RecordPaymentForm };
