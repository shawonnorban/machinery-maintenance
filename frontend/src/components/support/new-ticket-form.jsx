"use client";

import { useActionState } from "react";
import { useRouter } from "next/navigation";
import { FormField } from "@/components/ui/form-field";
import { Input } from "@/components/ui/input";
import { Textarea } from "@/components/ui/textarea";
import { Button } from "@/components/ui/button";
import { Alert } from "@/components/ui/alert";

/** Mirrors `tickets/create.blade.php`. */
function NewTicketForm({ action }) {
  const router = useRouter();
  const [state, dispatch, pending] = useActionState(action, null);

  return (
    <form action={dispatch} className="flex flex-col gap-5">
      {state?.status === "error" && !state.errors ? <Alert variant="danger">{state.message}</Alert> : null}

      <FormField label="Subject" required error={state?.errors?.subject?.[0]}>
        {(fieldProps) => <Input {...fieldProps} name="subject" maxLength={255} required />}
      </FormField>

      <FormField label="Message" required error={state?.errors?.body?.[0]}>
        {(fieldProps) => <Textarea {...fieldProps} name="body" rows={6} maxLength={5000} required />}
      </FormField>

      <div className="flex justify-end gap-2">
        <Button type="button" variant="outline" onClick={() => router.back()}>
          Cancel
        </Button>
        <Button type="submit" loading={pending}>
          Open ticket
        </Button>
      </div>
    </form>
  );
}

export { NewTicketForm };
