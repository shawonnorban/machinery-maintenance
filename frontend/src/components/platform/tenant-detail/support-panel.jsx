"use client";

import { useActionState, useState, useTransition } from "react";
import { LifeBuoy } from "lucide-react";
import { Card, CardHeader, CardTitle, CardBody, CardFooter } from "@/components/ui/card";
import { FormField } from "@/components/ui/form-field";
import { Input } from "@/components/ui/input";
import { Textarea } from "@/components/ui/textarea";
import { Button } from "@/components/ui/button";
import { Alert } from "@/components/ui/alert";
import { Modal } from "@/components/ui/modal";
import { Badge } from "@/components/ui/badge";
import { RelativeTime } from "@/components/ui/relative-time";
import { useToastManager } from "@/components/ui/toast";

/**
 * Support access to a customer's data (SRS 5.4) — an audited, time-boxed
 * grant, never standing access. Stepping *inside* the grant as a named user
 * (`enter`) mints a bearer token scoped to this app's own separately-
 * authenticated Next.js session, which has no handoff into it yet (a
 * documented gap in `TenantController::enterSupport`'s own docblock) — so
 * this panel manages grants without offering to enter one.
 */
function SupportPanel({ grants, openAction, closeAction }) {
  const [open, setOpen] = useState(false);

  return (
    <Card>
      <CardHeader>
        <CardTitle>Support access</CardTitle>
      </CardHeader>
      <CardBody className="flex flex-col gap-3 p-0">
        {grants.length === 0 ? (
          <p className="p-5 text-sm text-foreground-muted">No support access has been opened for this customer.</p>
        ) : (
          <div className="flex flex-col divide-y divide-border">
            {grants.map((grant) => (
              <GrantRow key={grant.id} grant={grant} closeAction={closeAction} />
            ))}
          </div>
        )}
      </CardBody>
      <CardFooter>
        <Button size="sm" variant="outline" onClick={() => setOpen(true)}>
          <LifeBuoy /> Open support access
        </Button>
      </CardFooter>
      <OpenGrantModal open={open} onOpenChange={setOpen} action={openAction} />
    </Card>
  );
}

function GrantRow({ grant, closeAction }) {
  const [pending, startTransition] = useTransition();
  const toastManager = useToastManager();

  function close() {
    startTransition(async () => {
      const result = await closeAction(grant.id);
      if (result?.status === "error") toastManager.add({ title: result.message, type: "danger" });
    });
  }

  return (
    <div className="flex flex-wrap items-center justify-between gap-3 p-5">
      <div>
        <p className="text-sm font-medium text-foreground">
          {grant.holder.name} {grant.is_active ? <Badge variant="warning">Active</Badge> : <Badge variant="neutral">Ended</Badge>}
        </p>
        <p className="text-xs text-foreground-muted">{grant.reason}</p>
        <p className="text-xs text-foreground-subtle">
          Opened <RelativeTime value={grant.starts_at} /> · expires <RelativeTime value={grant.expires_at} />
        </p>
      </div>
      {grant.is_active ? (
        <Button size="sm" variant="outline" loading={pending} onClick={close}>
          Close now
        </Button>
      ) : null}
    </div>
  );
}

function OpenGrantModal({ open, onOpenChange, action }) {
  const [state, dispatch, pending] = useActionState(action, null);

  return (
    <Modal
      open={open}
      onOpenChange={onOpenChange}
      title="Open support access"
      description="Time-boxed and audited — the customer is notified, and the grant expires on its own even if nobody closes it."
    >
      <form action={dispatch} className="flex flex-col gap-3">
        {state?.status === "error" ? <Alert variant="danger">{state.message}</Alert> : null}
        <FormField label="Reason" required helperText="Shown to the customer.">
          {(p) => <Textarea {...p} name="reason" rows={2} maxLength={500} required />}
        </FormField>
        <FormField label="Hours" required helperText="1–8 hours.">
          {(p) => <Input {...p} name="hours" type="number" min="1" max="8" defaultValue={1} required />}
        </FormField>
        <div className="flex justify-end gap-2">
          <Button type="button" variant="outline" onClick={() => onOpenChange(false)}>
            Cancel
          </Button>
          <Button type="submit" loading={pending}>
            Open access
          </Button>
        </div>
      </form>
    </Modal>
  );
}

export { SupportPanel };
