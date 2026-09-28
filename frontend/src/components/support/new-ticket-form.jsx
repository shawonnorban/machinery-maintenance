"use client";

import { useActionState } from "react";
import { useRouter } from "next/navigation";
import { LifeBuoy, Send } from "lucide-react";
import { FormField } from "@/components/ui/form-field";
import { Input } from "@/components/ui/input";
import { Textarea } from "@/components/ui/textarea";
import { Select } from "@/components/ui/select";
import { FileInput } from "@/components/ui/file-input";
import { Button } from "@/components/ui/button";
import { Alert } from "@/components/ui/alert";
import { useT } from "@/lib/i18n";

const PRIORITY_OPTIONS = ["LOW", "MEDIUM", "HIGH", "URGENT"];

/**
 * Mirrors `tickets/create.blade.php`'s two required fields (subject, body),
 * plus two genuinely new ones: a suggested priority (accepted at face
 * value, not authoritative — platform staff still triage via
 * `reclassify()`) and an optional attachment, uploaded as a second call
 * right after the ticket itself is created (see `createTicket`'s own note
 * on why — there's no single endpoint that accepts both at once).
 */
function NewTicketForm({ action }) {
  const t = useT("support");
  const tc = useT("common");
  const router = useRouter();
  const [state, dispatch, pending] = useActionState(action, null);

  return (
    <form action={dispatch} className="flex flex-col gap-5">
      <div className="flex items-center gap-3">
        <span className="flex size-10 shrink-0 items-center justify-center rounded-full bg-brand-subtle text-brand-hover [&_svg]:size-5">
          <LifeBuoy />
        </span>
        <div>
          <h2 className="text-base font-semibold text-foreground">{t("new_ticket_heading")}</h2>
          <p className="text-sm text-foreground-muted">{t("new_ticket_subheading")}</p>
        </div>
      </div>

      {state?.status === "error" && !state.errors ? <Alert variant="danger">{state.message}</Alert> : null}

      <FormField label={t("subject")} required error={state?.errors?.subject?.[0]}>
        {(fieldProps) => <Input {...fieldProps} name="subject" placeholder={t("subject_placeholder")} maxLength={255} required />}
      </FormField>

      <FormField label={t("message")} required error={state?.errors?.body?.[0]}>
        {(fieldProps) => (
          <Textarea {...fieldProps} name="body" placeholder={t("message_placeholder")} rows={6} maxLength={5000} required />
        )}
      </FormField>

      <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <FormField label={t("priority")} helperText={t("priority_hint")} error={state?.errors?.priority?.[0]}>
          {(fieldProps) => (
            <Select
              {...fieldProps}
              name="priority"
              defaultValue="MEDIUM"
              options={PRIORITY_OPTIONS.map((value) => ({ value, label: t(`priority_${value.toLowerCase()}`) }))}
            />
          )}
        </FormField>

        <FormField label={t("attachment_optional")}>
          {(fieldProps) => <FileInput {...fieldProps} name="file" />}
        </FormField>
      </div>

      <div className="flex justify-end gap-2">
        <Button type="button" variant="outline" onClick={() => router.back()}>
          {tc("cancel")}
        </Button>
        <Button type="submit" loading={pending}>
          <Send /> {t("open_ticket")}
        </Button>
      </div>
    </form>
  );
}

export { NewTicketForm };
