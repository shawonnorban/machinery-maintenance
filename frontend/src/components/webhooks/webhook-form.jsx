"use client";

import Link from "next/link";
import { startTransition, useActionState, useState } from "react";
import { useRouter } from "next/navigation";
import { FormField } from "@/components/ui/form-field";
import { Input } from "@/components/ui/input";
import { Checkbox } from "@/components/ui/checkbox";
import { Button } from "@/components/ui/button";
import { Alert } from "@/components/ui/alert";

/**
 * Mirrors `WebhookController`'s create/edit form — one endpoint, the events
 * it should receive. On create, the signing secret comes back once in the
 * action's own result and is never shown again, so a successful create
 * shows it instead of redirecting straight past it (same reasoning as
 * `UserForm`'s one-time generated password).
 */
function WebhookForm({ endpoint = null, eventOptions, action }) {
  const isEdit = endpoint !== null;
  const router = useRouter();
  const [state, dispatch, pending] = useActionState(action, null);
  const [url, setUrl] = useState(endpoint?.url ?? "");
  const [description, setDescription] = useState(endpoint?.description ?? "");
  const [events, setEvents] = useState(endpoint?.events ?? []);

  function toggleEvent(value) {
    setEvents((current) => (current.includes(value) ? current.filter((e) => e !== value) : [...current, value]));
  }

  function handleSubmit(formEvent) {
    formEvent.preventDefault();

    const formData = new FormData();
    formData.set("url", url);
    if (description) formData.set("description", description);
    for (const value of events) formData.append("events", value);

    startTransition(() => dispatch(formData));
  }

  if (!isEdit && state?.status === "success") {
    return (
      <div className="flex flex-col gap-4">
        <Alert variant="success" title="Endpoint created">
          Copy this signing secret now — it will not be shown again.
        </Alert>
        <div className="rounded-sm border border-border bg-surface-muted px-4 py-3 font-mono text-sm break-all">{state.secret}</div>
        <div>
          <Link href={`/settings/webhooks/${state.endpointId}`} className="text-sm font-medium text-brand hover:underline">
            Go to the endpoint →
          </Link>
        </div>
      </div>
    );
  }

  return (
    <form onSubmit={handleSubmit} className="flex flex-col gap-5">
      {state?.status === "error" && !state.errors ? <Alert variant="danger">{state.message}</Alert> : null}

      <FormField label="URL" required error={state?.errors?.url?.[0]} helperText="Must be a reachable public HTTPS address — a private/internal address is refused.">
        {(fieldProps) => <Input {...fieldProps} type="url" value={url} onChange={(e) => setUrl(e.target.value)} maxLength={2048} required />}
      </FormField>

      <FormField label="Description">
        {(fieldProps) => <Input {...fieldProps} value={description} onChange={(e) => setDescription(e.target.value)} maxLength={255} />}
      </FormField>

      <FormField label="Events" required error={state?.errors?.events?.[0] ?? state?.errors?.["events.0"]?.[0]}>
        {() => (
          <div className="grid grid-cols-1 gap-2 rounded-sm border border-border-strong p-3 sm:grid-cols-2">
            {eventOptions.map((option) => (
              <label key={option.value} className="flex items-center gap-2 rounded-sm border border-border p-2 text-sm text-foreground">
                <Checkbox checked={events.includes(option.value)} onCheckedChange={() => toggleEvent(option.value)} />
                {option.label}
              </label>
            ))}
          </div>
        )}
      </FormField>

      <div className="flex justify-end gap-2">
        <Button type="button" variant="outline" onClick={() => router.back()}>
          Cancel
        </Button>
        <Button type="submit" loading={pending} disabled={events.length === 0}>
          {isEdit ? "Save changes" : "Create endpoint"}
        </Button>
      </div>
    </form>
  );
}

export { WebhookForm };
