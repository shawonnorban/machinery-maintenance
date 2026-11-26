"use client";

import { useActionState, useEffect, useRef, useState, useTransition } from "react";
import { ChevronLeft, ChevronRight, KeyRound, Mail, Search, ShieldCheck, Users } from "lucide-react";
import { FormSection, FormActions } from "@/components/ui/form-section";
import { Panel, PTable } from "@/components/platform/ui";
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
    <div className="flex flex-col gap-4">
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
    <form action={dispatch} className="flex flex-col gap-3">
      {state?.status === "error" && !state.errors ? <Alert variant="danger">{state.message}</Alert> : null}
      <FormSection legend="Company Details">
        <div className="grid grid-cols-1 gap-3 lg:grid-cols-2">
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
        </div>
      </FormSection>
      <FormActions>
        <Button type="submit" loading={pending}>
          Save changes
        </Button>
      </FormActions>
    </form>
  );
}

const PAGE = 15;

/** Every member of the company, searchable and a page at a time (a big customer has hundreds). */
function MembersCard({ members, updateEmailAction, resetPasswordAction }) {
  const [search, setSearch] = useState("");
  const [page, setPage] = useState(1);
  const needle = search.trim().toLowerCase();
  const filtered = needle ? members.filter((m) => m.name.toLowerCase().includes(needle) || (m.email ?? "").toLowerCase().includes(needle)) : members;
  const last = Math.max(1, Math.ceil(filtered.length / PAGE));
  const current = Math.min(page, last);
  const rows = filtered.slice((current - 1) * PAGE, current * PAGE);

  return (
    <Panel
      title="Members"
      subtitle={`${filtered.length} of ${members.length} users · change a sign-in email or reset a password, with a written reason`}
      icon={Users}
      action={
        <span className="relative">
          <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-foreground-subtle" />
          <Input
            value={search}
            onChange={(e) => {
              setSearch(e.target.value);
              setPage(1);
            }}
            placeholder="Name or email…"
            className="w-64 pl-9"
          />
        </span>
      }
    >
      <PTable
        dense
        rows={rows}
        rowKey={(m) => m.id}
        empty="No member matches this search."
        columns={[
          {
            key: "name",
            label: "Name",
            render: (m) => (
              <span className="flex items-center gap-2.5">
                <span className="flex size-7 shrink-0 items-center justify-center rounded-full bg-surface-muted text-xs font-semibold text-foreground-muted">{m.name.charAt(0).toUpperCase()}</span>
                <b className="font-semibold">{m.name}</b>
                {m.is_owner ? (
                  <span className="inline-flex items-center gap-1 rounded-full bg-brand-subtle px-2 py-0.5 text-[11px] font-semibold text-brand-hover">
                    <ShieldCheck className="size-3" /> Owner
                  </span>
                ) : null}
              </span>
            ),
          },
          { key: "email", label: "Sign-in email", render: (m) => <span className="text-foreground-muted">{m.email}</span> },
          { key: "act", label: "", align: "right", render: (m) => <MemberActions member={m} updateEmailAction={updateEmailAction} resetPasswordAction={resetPasswordAction} /> },
        ]}
      />
      {last > 1 ? (
        <div className="flex items-center justify-end gap-2 border-t border-border px-5 py-2.5 text-sm">
          <Button size="sm" variant="outline" disabled={current <= 1} onClick={() => setPage(current - 1)} aria-label="Previous page">
            <ChevronLeft />
          </Button>
          <span className="tabular-nums text-foreground-muted">
            Page {current} of {last}
          </span>
          <Button size="sm" variant="outline" disabled={current >= last} onClick={() => setPage(current + 1)} aria-label="Next page">
            <ChevronRight />
          </Button>
        </div>
      ) : null}
    </Panel>
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
      <div className="flex justify-end gap-1.5">
        <Button size="sm" variant="outline" onClick={() => setEmailOpen(true)}>
          <Mail /> Email
        </Button>
        <Button size="sm" variant="outline" onClick={() => setPasswordOpen(true)}>
          <KeyRound /> Password
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
