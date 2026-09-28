"use client";

import { useActionState, useEffect, useRef, useState, useTransition } from "react";
import { KeyRound, Mail, ShieldCheck } from "lucide-react";
import { Card, CardHeader, CardTitle, CardBody } from "@/components/ui/card";
import { FormField } from "@/components/ui/form-field";
import { Input } from "@/components/ui/input";
import { Select } from "@/components/ui/select";
import { Textarea } from "@/components/ui/textarea";
import { Button } from "@/components/ui/button";
import { Alert } from "@/components/ui/alert";
import { Modal } from "@/components/ui/modal";
import { useToastManager } from "@/components/ui/toast";

const LOCALE_OPTIONS = [
  { value: "bn", label: "বাংলা" },
  { value: "en", label: "English" },
];

/**
 * Reading is the common errand here and editing the rare one
 * (`TenantController::edit`'s own docblock) — so this panel shows the
 * company's details as text, with edits made through the fields directly
 * rather than a separate always-open form, and members listed underneath
 * for the two credential-recovery acts a platform admin might need
 * (`TenantAccountController`).
 */
function CompanyPanel({ company, members, updateDetailsAction, updateEmailAction, resetPasswordAction }) {
  return (
    <div className="flex flex-col gap-5">
      <DetailsForm company={company} action={updateDetailsAction} />
      <MembersCard members={members} updateEmailAction={updateEmailAction} resetPasswordAction={resetPasswordAction} />
    </div>
  );
}

function DetailsForm({ company, action }) {
  const [state, dispatch, pending] = useActionState(action, null);
  const toastManager = useToastManager();
  const lastState = useRef(null);

  useEffect(() => {
    if (state && state !== lastState.current) {
      lastState.current = state;
      if (state.status === "success") {
        toastManager.add({ title: "Details saved", type: "success" });
      }
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [state]);

  return (
    <Card>
      <CardHeader>
        <CardTitle>Company details</CardTitle>
      </CardHeader>
      <CardBody>
        {state?.status === "error" && !state.errors ? (
          <Alert variant="danger" className="mb-4">
            {state.message}
          </Alert>
        ) : null}
        <form action={dispatch} className="grid grid-cols-1 gap-4 sm:grid-cols-2">
          <FormField label="Company name" required error={state?.errors?.name?.[0]}>
            {(p) => <Input {...p} name="name" defaultValue={company.name} maxLength={255} required />}
          </FormField>
          <FormField label="Legal name">
            {(p) => <Input {...p} name="legal_name" defaultValue={company.legal_name ?? ""} maxLength={255} />}
          </FormField>
          <FormField label="Email" error={state?.errors?.email?.[0]}>
            {(p) => <Input {...p} type="email" name="email" defaultValue={company.email ?? ""} maxLength={255} />}
          </FormField>
          <FormField label="Phone">
            {(p) => <Input {...p} name="phone" defaultValue={company.phone ?? ""} maxLength={32} />}
          </FormField>
          <FormField label="Country">
            {(p) => <Input {...p} name="country" defaultValue={company.country ?? ""} maxLength={100} />}
          </FormField>
          <FormField label="Address">
            {(p) => <Input {...p} name="address" defaultValue={company.address ?? ""} maxLength={500} />}
          </FormField>
          <FormField label="Currency" required error={state?.errors?.base_currency?.[0]}>
            {(p) => <Input {...p} name="base_currency" defaultValue={company.base_currency} maxLength={3} required />}
          </FormField>
          <FormField label="Timezone" required error={state?.errors?.timezone?.[0]}>
            {(p) => <Input {...p} name="timezone" defaultValue={company.timezone} maxLength={64} required />}
          </FormField>
          <FormField label="Locale">
            {() => <Select name="default_locale" defaultValue={company.default_locale} options={LOCALE_OPTIONS} />}
          </FormField>
          <div className="flex items-end sm:col-span-2">
            <Button type="submit" loading={pending}>
              Save changes
            </Button>
          </div>
        </form>
      </CardBody>
    </Card>
  );
}

function MembersCard({ members, updateEmailAction, resetPasswordAction }) {
  return (
    <Card>
      <CardHeader>
        <CardTitle>Members ({members.length})</CardTitle>
      </CardHeader>
      <CardBody className="flex flex-col divide-y divide-border p-0">
        {members.map((member) => (
          <div key={member.id} className="flex flex-wrap items-center justify-between gap-3 px-5 py-3">
            <div className="flex items-center gap-3">
              <span className="flex size-8 shrink-0 items-center justify-center rounded-full bg-surface-muted text-xs font-semibold text-foreground-muted">
                {member.name.charAt(0).toUpperCase()}
              </span>
              <div>
                <p className="text-sm font-medium text-foreground">
                  {member.name}
                  {member.is_owner ? (
                    <span className="ml-2 inline-flex items-center gap-1 rounded-full bg-brand-subtle px-2 py-0.5 text-[11px] font-semibold text-brand-hover">
                      <ShieldCheck className="size-3" /> Owner
                    </span>
                  ) : null}
                </p>
                <p className="text-xs text-foreground-muted">{member.email}</p>
              </div>
            </div>
            <MemberActions member={member} updateEmailAction={updateEmailAction} resetPasswordAction={resetPasswordAction} />
          </div>
        ))}
      </CardBody>
    </Card>
  );
}

function MemberActions({ member, updateEmailAction, resetPasswordAction }) {
  const [emailOpen, setEmailOpen] = useState(false);
  const [passwordOpen, setPasswordOpen] = useState(false);
  const [revealedPassword, setRevealedPassword] = useState(null);
  const [pending, startTransitionLocal] = useTransition();
  const toastManager = useToastManager();

  function submitEmail(event) {
    event.preventDefault();
    const formData = new FormData(event.currentTarget);
    startTransitionLocal(async () => {
      const result = await updateEmailAction(member.id, null, formData);
      if (result?.status === "error") {
        toastManager.add({ title: result.message, type: "danger" });
      } else {
        toastManager.add({ title: "Sign-in email updated", type: "success" });
        setEmailOpen(false);
      }
    });
  }

  function submitPassword(event) {
    event.preventDefault();
    const formData = new FormData(event.currentTarget);
    startTransitionLocal(async () => {
      const result = await resetPasswordAction(member.id, null, formData);
      if (result?.status === "error") {
        toastManager.add({ title: result.message, type: "danger" });
      } else {
        setRevealedPassword(result.password);
      }
    });
  }

  return (
    <>
      <div className="flex gap-2">
        <Button size="sm" variant="outline" onClick={() => setEmailOpen(true)}>
          <Mail /> Change email
        </Button>
        <Button size="sm" variant="outline" onClick={() => setPasswordOpen(true)}>
          <KeyRound /> Reset password
        </Button>
      </div>

      <Modal
        open={emailOpen}
        onOpenChange={setEmailOpen}
        title={`Change ${member.name}'s sign-in email`}
        description="Heavy enough an act that it carries a written reason and a notice to the company, same as a support grant."
      >
        <form onSubmit={submitEmail} className="flex flex-col gap-4">
          <FormField label="New email" required>
            {(p) => <Input {...p} type="email" name="email" defaultValue={member.email} required />}
          </FormField>
          <FormField label="Reason" required helperText="At least 10 characters — shown in the audit trail.">
            {(p) => <Textarea {...p} name="reason" rows={2} minLength={10} maxLength={500} required />}
          </FormField>
          <div className="flex justify-end gap-2">
            <Button type="button" variant="outline" onClick={() => setEmailOpen(false)}>
              Cancel
            </Button>
            <Button type="submit" loading={pending}>
              Save
            </Button>
          </div>
        </form>
      </Modal>

      <Modal
        open={passwordOpen}
        onOpenChange={(open) => {
          setPasswordOpen(open);
          if (!open) setRevealedPassword(null);
        }}
        title={revealedPassword ? "Password reset" : `Reset ${member.name}'s password`}
        description={
          revealedPassword
            ? "Share this with them — it will not be shown again."
            : "A new password is generated immediately. Their current one stops working."
        }
      >
        {revealedPassword ? (
          <div className="rounded-sm border border-border bg-surface-muted px-4 py-3 font-mono text-sm">
            {revealedPassword}
          </div>
        ) : (
          <form onSubmit={submitPassword} className="flex flex-col gap-4">
            <FormField label="Reason" required helperText="At least 10 characters — shown in the audit trail.">
              {(p) => <Textarea {...p} name="reason" rows={2} minLength={10} maxLength={500} required />}
            </FormField>
            <div className="flex justify-end gap-2">
              <Button type="button" variant="outline" onClick={() => setPasswordOpen(false)}>
                Cancel
              </Button>
              <Button type="submit" loading={pending}>
                Reset password
              </Button>
            </div>
          </form>
        )}
      </Modal>
    </>
  );
}

export { CompanyPanel };
