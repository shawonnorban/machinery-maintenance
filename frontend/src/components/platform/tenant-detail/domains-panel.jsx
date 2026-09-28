"use client";

import { useActionState, useState, useTransition } from "react";
import { Globe, Trash2 } from "lucide-react";
import { Card, CardHeader, CardTitle, CardBody, CardFooter } from "@/components/ui/card";
import { FormField } from "@/components/ui/form-field";
import { Input } from "@/components/ui/input";
import { Select } from "@/components/ui/select";
import { Button } from "@/components/ui/button";
import { Alert } from "@/components/ui/alert";
import { Badge } from "@/components/ui/badge";
import { ConfirmDialog } from "@/components/ui/confirm-dialog";
import { useToastManager } from "@/components/ui/toast";

const KIND_OPTIONS = [
  { value: "SUBDOMAIN", label: "Subdomain (works immediately)" },
  { value: "CUSTOM", label: "Customer's own domain" },
];

/**
 * Where this customer reaches their system — two kinds, and they cost the
 * customer very different amounts of effort (mirrors `_domains.blade.php`).
 */
function DomainsPanel({ domains, actions }) {
  const [addOpen, setAddOpen] = useState(false);

  return (
    <Card>
      <CardHeader>
        <CardTitle>Domains</CardTitle>
      </CardHeader>
      <CardBody className="flex flex-col gap-3 p-0">
        {domains.length === 0 ? (
          <p className="p-5 text-sm text-foreground-muted">No domains yet.</p>
        ) : (
          <div className="flex flex-col divide-y divide-border">
            {domains.map((domain) => (
              <DomainRow key={domain.id} domain={domain} actions={actions} />
            ))}
          </div>
        )}
      </CardBody>
      <CardFooter>
        <Button size="sm" variant="outline" onClick={() => setAddOpen((v) => !v)}>
          {addOpen ? "Cancel" : "Add domain"}
        </Button>
      </CardFooter>
      {addOpen ? <AddDomainForm actions={actions} onDone={() => setAddOpen(false)} /> : null}
    </Card>
  );
}

function DomainRow({ domain, actions }) {
  const [pending, startTransition] = useTransition();
  const [removeOpen, setRemoveOpen] = useState(false);
  const toastManager = useToastManager();

  function run(fn) {
    startTransition(async () => {
      const result = await fn();
      if (result?.status === "error") toastManager.add({ title: result.message, type: "danger" });
    });
  }

  return (
    <div className="flex flex-col gap-3 p-5">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <div className="min-w-0">
          <p className="flex items-center gap-2 text-sm font-medium text-foreground">
            <Globe className="size-4 text-foreground-muted" />
            {domain.host}
            {domain.is_primary ? <Badge variant="brand">Primary</Badge> : null}
          </p>
          <p className="text-xs text-foreground-muted">
            {domain.kind === "SUBDOMAIN" ? "Subdomain" : "Custom domain"} ·{" "}
            {domain.is_verified ? <span className="text-success">Verified</span> : <span className="text-warning">Pending</span>}
          </p>
        </div>
        <div className="flex gap-2">
          {!domain.is_verified ? (
            <Button size="sm" variant="outline" loading={pending} onClick={() => run(() => actions.verify(domain.id))}>
              Check now
            </Button>
          ) : null}
          {domain.is_verified && !domain.is_primary ? (
            <Button size="sm" variant="outline" loading={pending} onClick={() => run(() => actions.primary(domain.id))}>
              Make primary
            </Button>
          ) : null}
          <Button size="sm" variant="outline" onClick={() => setRemoveOpen(true)}>
            <Trash2 />
          </Button>
        </div>
      </div>

      {!domain.is_verified && domain.verification_record_name ? (
        <div className="rounded-sm border border-border bg-surface-muted p-3 text-xs text-foreground-muted">
          <p className="mb-1 font-medium text-foreground">DNS steps</p>
          <ol className="ml-4 list-decimal space-y-1">
            <li>
              Point <code className="rounded bg-surface px-1">{domain.host}</code> at this platform&apos;s host with a CNAME record.
            </li>
            <li>
              Add a TXT record: <code className="rounded bg-surface px-1">{domain.verification_record_name}</code> ={" "}
              <code className="rounded bg-surface px-1">{domain.verification_token}</code>
            </li>
            <li>Click &quot;Check now&quot; once DNS has propagated.</li>
          </ol>
          <p className="mt-2">TLS is provisioned separately once verified — not instant.</p>
        </div>
      ) : null}

      <ConfirmDialog
        open={removeOpen}
        onOpenChange={setRemoveOpen}
        title={`Remove ${domain.host}?`}
        confirmLabel="Remove"
        loading={pending}
        onConfirm={() => {
          run(() => actions.remove(domain.id));
          setRemoveOpen(false);
        }}
      />
    </div>
  );
}

function AddDomainForm({ actions, onDone }) {
  const toastManager = useToastManager();

  async function wrapped(previousState, formData) {
    const result = await actions.add(previousState, formData);
    if (result?.status === "success") {
      toastManager.add({ title: "Domain added", type: "success" });
      onDone();
    }
    return result;
  }

  const [state, dispatch, pending] = useActionState(wrapped, null);

  return (
    <div className="border-t border-border p-5">
      {state?.status === "error" ? <Alert variant="danger" className="mb-3">{state.message}</Alert> : null}
      <form action={dispatch} className="flex flex-wrap items-end gap-3">
        <FormField label="Kind" className="w-full sm:w-56">
          {() => <Select name="kind" defaultValue="SUBDOMAIN" options={KIND_OPTIONS} />}
        </FormField>
        <FormField label="Host" required className="flex-1 min-w-[200px]">
          {(p) => <Input {...p} name="host" maxLength={255} placeholder="customer or full domain" required />}
        </FormField>
        <Button type="submit" loading={pending}>
          Add
        </Button>
      </form>
    </div>
  );
}

export { DomainsPanel };
