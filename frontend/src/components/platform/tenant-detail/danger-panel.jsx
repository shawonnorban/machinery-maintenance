"use client";

import { useState, useTransition } from "react";
import { Ban, PlayCircle } from "lucide-react";
import { Card, CardHeader, CardTitle, CardBody } from "@/components/ui/card";
import { FormField } from "@/components/ui/form-field";
import { Input } from "@/components/ui/input";
import { Textarea } from "@/components/ui/textarea";
import { Button } from "@/components/ui/button";
import { ConfirmDialog } from "@/components/ui/confirm-dialog";
import { useToastManager } from "@/components/ui/toast";

/**
 * Two things this page can do *to* a customer, in the order of how hard
 * they are to undo — suspension lifts this afternoon, closing takes a
 * decision to reverse (mirrors `_danger.blade.php`).
 */
function DangerPanel({ company, suspendAction, reactivateAction, closeAction }) {
  return (
    <div className="flex flex-col gap-5">
      <SuspendCard company={company} suspendAction={suspendAction} reactivateAction={reactivateAction} />
      <CloseCard company={company} closeAction={closeAction} />
    </div>
  );
}

function SuspendCard({ company, suspendAction, reactivateAction }) {
  const [reason, setReason] = useState("");
  const [confirming, setConfirming] = useState(false);
  const [pending, startTransition] = useTransition();
  const toastManager = useToastManager();

  if (company.status === "SUSPENDED") {
    return (
      <Card className="border-danger/30">
        <CardHeader>
          <CardTitle>Suspended</CardTitle>
        </CardHeader>
        <CardBody className="flex flex-col gap-3">
          <p className="text-sm text-foreground-muted">{company.suspension_reason}</p>
          <div>
            <Button
              variant="outline"
              loading={pending}
              onClick={() =>
                startTransition(async () => {
                  const result = await reactivateAction();
                  if (result?.status === "error") toastManager.add({ title: result.message, type: "danger" });
                })
              }
            >
              <PlayCircle /> Reactivate
            </Button>
          </div>
        </CardBody>
      </Card>
    );
  }

  function submit() {
    startTransition(async () => {
      const result = await suspendAction(reason);
      if (result?.status === "error") {
        toastManager.add({ title: result.message, type: "danger" });
      }
      setConfirming(false);
    });
  }

  return (
    <Card className="border-danger/30">
      <CardHeader>
        <CardTitle>Suspend</CardTitle>
      </CardHeader>
      <CardBody className="flex flex-col gap-3">
        <p className="text-xs text-foreground-muted">
          Stops the company using the product without touching a row of its data — a billing state, not a deletion.
        </p>
        <FormField label="Reason" required helperText="Shown to the customer verbatim — 'policy' answers nothing for somebody whose factory has just lost its maintenance system.">
          {(p) => (
            <Textarea
              {...p}
              value={reason}
              onChange={(e) => setReason(e.target.value)}
              rows={2}
              minLength={10}
              maxLength={500}
              required
            />
          )}
        </FormField>
        <div>
          <Button variant="outline" disabled={reason.trim().length < 10} onClick={() => setConfirming(true)}>
            <Ban /> Suspend
          </Button>
        </div>
        <ConfirmDialog
          open={confirming}
          onOpenChange={setConfirming}
          title={`Suspend ${company.name}?`}
          description="Every member loses access immediately. Their data stays exactly where it is."
          confirmLabel="Suspend"
          loading={pending}
          onConfirm={submit}
        />
      </CardBody>
    </Card>
  );
}

function CloseCard({ company, closeAction }) {
  const [confirmCode, setConfirmCode] = useState("");
  const [reason, setReason] = useState("");
  const [pending, startTransition] = useTransition();
  const [error, setError] = useState(null);

  function submit(event) {
    event.preventDefault();
    const formData = new FormData();
    formData.set("confirm_code", confirmCode);
    formData.set("reason", reason);
    startTransition(async () => {
      const result = await closeAction(null, formData);
      if (result?.status === "error") {
        setError(result.message);
      }
    });
  }

  return (
    <Card className="border-danger/30">
      <CardHeader>
        <CardTitle>Close account</CardTitle>
      </CardHeader>
      <CardBody className="flex flex-col gap-3">
        <p className="text-xs text-foreground-muted">
          Ends the account without destroying anything — the customer leaves the list, nobody in it can sign in, and
          every row is still on disk. This can be reversed from the closed-accounts list.
        </p>
        {error ? <p className="text-sm text-danger">{error}</p> : null}
        <form onSubmit={submit} className="flex flex-col gap-3">
          <FormField label="Reason" required>
            {(p) => (
              <Input {...p} value={reason} onChange={(e) => setReason(e.target.value)} minLength={10} maxLength={500} required />
            )}
          </FormField>
          <FormField label={`Type "${company.code}" to confirm`} required>
            {(p) => (
              <Input {...p} value={confirmCode} onChange={(e) => setConfirmCode(e.target.value)} placeholder={company.code} required />
            )}
          </FormField>
          <div>
            <Button type="submit" variant="danger" loading={pending} disabled={confirmCode !== company.code || reason.trim().length < 10}>
              Close account
            </Button>
          </div>
        </form>
      </CardBody>
    </Card>
  );
}

export { DangerPanel };
