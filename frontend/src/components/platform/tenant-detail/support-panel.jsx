"use client";

import { useActionState, useState, useTransition } from "react";
import { LifeBuoy, LogIn } from "lucide-react";
import { Card, CardHeader, CardTitle, CardBody, CardFooter } from "@/components/ui/card";
import { FormField } from "@/components/ui/form-field";
import { Input } from "@/components/ui/input";
import { Select } from "@/components/ui/select";
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
 * (`enter`) mints a bearer token scoped to that user and company
 * (`PlatformSupportGrantApiController::enter`, already built server-side);
 * `enterAction` below sets that token as this app's own tenant session
 * cookie and redirects into `/` — no cross-app handoff needed the way the
 * old Blade console required one, since the platform console and the
 * tenant app are the same Next.js deployment.
 */
function SupportPanel({ grants, members, openAction, closeAction, enterAction }) {
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
              <GrantRow key={grant.id} grant={grant} members={members} closeAction={closeAction} enterAction={enterAction} />
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

function GrantRow({ grant, members, closeAction, enterAction }) {
  const [pending, startTransition] = useTransition();
  const [actingAs, setActingAs] = useState("");
  const [error, setError] = useState(null);
  const toastManager = useToastManager();

  function close() {
    startTransition(async () => {
      const result = await closeAction(grant.id);
      if (result?.status === "error") toastManager.add({ title: result.message, type: "danger" });
    });
  }

  function enter() {
    setError(null);
    startTransition(async () => {
      // `enterAction` redirects to `/` on success (`redirect()` inside the
      // Server Action) — a returned value only ever means it didn't.
      const result = await enterAction(grant.id, actingAs);
      if (result?.status === "error") setError(result.message);
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
        {error ? <p className="mt-1 text-xs text-danger">{error}</p> : null}
      </div>
      {grant.is_active ? (
        <div className="flex items-center gap-2">
          <div className="w-44">
            <Select
              options={members.map((member) => ({ value: member.id, label: member.name }))}
              value={actingAs}
              onValueChange={setActingAs}
              placeholder="Act as…"
              disabled={pending}
            />
          </div>
          <Button size="sm" variant="primary" loading={pending} disabled={!actingAs} onClick={enter}>
            <LogIn /> Enter
          </Button>
          <Button size="sm" variant="outline" loading={pending} onClick={close}>
            Close now
          </Button>
        </div>
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
