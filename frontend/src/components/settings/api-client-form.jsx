"use client";

import Link from "next/link";
import { startTransition, useActionState, useState } from "react";
import { useRouter } from "next/navigation";
import { FormField } from "@/components/ui/form-field";
import { Input } from "@/components/ui/input";
import { DatePicker } from "@/components/ui/date-picker";
import { Checkbox } from "@/components/ui/checkbox";
import { Button } from "@/components/ui/button";
import { Alert } from "@/components/ui/alert";

/**
 * Mirrors `ApiClientController`'s create/edit form. Nothing is ticked by
 * default — a credential that can do nothing is the safe reading of "not
 * decided yet"; "everything" is not. On create, the secret comes back once
 * in the action's own result (same reasoning as `WebhookForm`'s own
 * one-time secret reveal).
 */
function ApiClientForm({ client = null, modules, action }) {
  const isEdit = client !== null;
  const router = useRouter();
  const [state, dispatch, pending] = useActionState(action, null);
  const [name, setName] = useState(client?.name ?? "");
  const [expiresAt, setExpiresAt] = useState(null);
  const [scopes, setScopes] = useState(client?.scopes ?? []);

  function toggleScope(name) {
    setScopes((current) => (current.includes(name) ? current.filter((s) => s !== name) : [...current, name]));
  }

  function handleSubmit(event) {
    event.preventDefault();

    const formData = new FormData();
    if (!isEdit) {
      formData.set("name", name);
      if (expiresAt) formData.set("expires_at", expiresAt);
    }
    for (const scope of scopes) formData.append("scopes", scope);

    startTransition(() => dispatch(formData));
  }

  if (!isEdit && state?.status === "success") {
    return (
      <div className="flex flex-col gap-4">
        <Alert variant="success" title="Credential created">
          Copy the client id and secret now — the secret will not be shown again.
        </Alert>
        <div className="flex flex-col gap-2">
          <div className="rounded-sm border border-border bg-surface-muted px-4 py-3 font-mono text-sm break-all">{state.clientId}</div>
          <div className="rounded-sm border border-border bg-surface-muted px-4 py-3 font-mono text-sm break-all">{state.secret}</div>
        </div>
        <div>
          <Link href="/settings/api-clients" className="text-sm font-medium text-brand hover:underline">
            Back to API clients →
          </Link>
        </div>
      </div>
    );
  }

  return (
    <form onSubmit={handleSubmit} className="flex flex-col gap-5">
      {state?.status === "error" && !state.errors ? <Alert variant="danger">{state.message}</Alert> : null}

      {!isEdit ? (
        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
          <FormField label="Name" required error={state?.errors?.name?.[0]}>
            {(fieldProps) => <Input {...fieldProps} value={name} onChange={(e) => setName(e.target.value)} maxLength={255} required placeholder="Dye house controller" />}
          </FormField>
          <FormField label="Expires" helperText="Leave blank for no expiry." error={state?.errors?.expires_at?.[0]}>
            {() => <DatePicker value={expiresAt} onChange={setExpiresAt} />}
          </FormField>
        </div>
      ) : null}

      <FormField label="Scopes" required error={state?.errors?.scopes?.[0]} helperText="Nothing is ticked by default — a credential that can do nothing is the safe reading.">
        {() => (
          <div className="flex flex-col gap-4 rounded-sm border border-border-strong p-4">
            {modules.map((group) => (
              <div key={group.module}>
                <div className="mb-2 text-xs font-semibold tracking-wide text-foreground-muted uppercase">{group.module}</div>
                <div className="grid grid-cols-1 gap-2 sm:grid-cols-2">
                  {group.permissions.map((permission) => (
                    <label key={permission.name} className="flex items-start gap-2 rounded-sm border border-border p-2 text-sm text-foreground">
                      <Checkbox checked={scopes.includes(permission.name)} onCheckedChange={() => toggleScope(permission.name)} className="mt-0.5" />
                      <span>
                        {permission.description}
                        <div className="font-mono text-xs text-foreground-muted">{permission.name}</div>
                      </span>
                    </label>
                  ))}
                </div>
              </div>
            ))}
          </div>
        )}
      </FormField>

      <div className="flex justify-end gap-2">
        <Button type="button" variant="outline" onClick={() => router.back()}>
          Cancel
        </Button>
        <Button type="submit" loading={pending} disabled={scopes.length === 0}>
          {isEdit ? "Save scopes" : "Create credential"}
        </Button>
      </div>
    </form>
  );
}

export { ApiClientForm };
