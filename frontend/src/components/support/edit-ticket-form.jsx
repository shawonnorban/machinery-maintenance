"use client";

import { useActionState } from "react";
import { useRouter } from "next/navigation";
import { Pencil } from "lucide-react";
import { FormField } from "@/components/ui/form-field";
import { Input } from "@/components/ui/input";
import { Textarea } from "@/components/ui/textarea";
import { Button } from "@/components/ui/button";
import { Alert } from "@/components/ui/alert";
import { useT } from "@/lib/i18n";

/**
 * Only reached while `ticket.can_edit` is true (opener, still OPEN, nobody
 * has replied yet) — no priority/attachment fields here, unlike
 * `NewTicketForm`: priority was only ever a suggestion at creation time,
 * and an attachment has its own flow once the ticket exists.
 */
function EditTicketForm({ ticket, action }) {
  const t = useT("support");
  const tc = useT("common");
  const router = useRouter();
  const [state, dispatch, pending] = useActionState(action, null);

  return (
    <form action={dispatch} className="flex flex-col gap-5">
      {state?.status === "error" && !state.errors ? <Alert variant="danger">{state.message}</Alert> : null}

      <FormField label={t("subject")} required error={state?.errors?.subject?.[0]}>
        {(fieldProps) => <Input {...fieldProps} name="subject" defaultValue={ticket.subject} maxLength={255} required />}
      </FormField>

      <FormField label={t("message")} required error={state?.errors?.body?.[0]}>
        {(fieldProps) => (
          <Textarea {...fieldProps} name="body" defaultValue={ticket.messages[0]?.body} rows={6} maxLength={5000} required />
        )}
      </FormField>

      <div className="flex justify-end gap-2">
        <Button type="button" variant="outline" onClick={() => router.back()}>
          {tc("cancel")}
        </Button>
        <Button type="submit" loading={pending}>
          <Pencil /> {tc("save")}
        </Button>
      </div>
    </form>
  );
}

export { EditTicketForm };
