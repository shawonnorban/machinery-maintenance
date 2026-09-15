"use client";

import { startTransition, useActionState, useState } from "react";
import Link from "next/link";
import { FormField } from "@/components/ui/form-field";
import { Input } from "@/components/ui/input";
import { Select } from "@/components/ui/select";
import { Checkbox } from "@/components/ui/checkbox";
import { Button } from "@/components/ui/button";
import { Alert } from "@/components/ui/alert";

const LOCALE_OPTIONS = [
  { value: "en", label: "English" },
  { value: "bn", label: "বাংলা (Bengali)" },
];

/**
 * Mirrors `identity::users.form.blade.php` — one form for inviting and
 * editing, so a field cannot be added to one and forgotten in the other.
 * The email field locks on edit (the address identifies the account
 * across every company it belongs to, not just this one) and the
 * password notice only shows on invite.
 *
 * Editing resubmits `roles`/`factory_id` alongside the profile fields in
 * the same call, preselected from the user's current assignments — the
 * same shape the web's own edit form has always had (`UserController::
 * edit()`'s `assignedRoleIds`/`assignedFactoryId`, the first factory found
 * among their assignments). A user holding roles across more than one
 * factory already collapses to that first factory on the web's own edit
 * screen; this is exact parity with that pre-existing behavior, not a new
 * risk introduced here — `ManageCompanyUser::syncRoles()` replaces every
 * assignment on any save regardless of which screen calls it.
 */
function UserForm({ user = null, roles, factories, departments = [], productionLines = [], action }) {
  const isEdit = user !== null;
  const [state, dispatch, pending] = useActionState(action, null);
  const [name, setName] = useState(user?.name ?? "");
  const [email, setEmail] = useState(user?.email ?? "");
  const [phone, setPhone] = useState(user?.phone ?? "");
  const [locale, setLocale] = useState(user?.locale ?? "en");
  const [factoryId, setFactoryId] = useState(() => user?.role_assignments?.find((a) => a.factory_id)?.factory_id ?? "");
  const [selectedRoles, setSelectedRoles] = useState(() => user?.role_assignments?.map((a) => a.role_id) ?? []);
  const [departmentId, setDepartmentId] = useState(user?.department_id ?? "");
  const [productionLineId, setProductionLineId] = useState(user?.production_line_id ?? "");

  if (!isEdit && state?.status === "success") {
    return (
      <div className="flex flex-col gap-4">
        <Alert variant="success" title="User created">
          Share this password with them — it will not be shown again.
        </Alert>
        <div className="rounded-sm border border-border bg-surface-muted px-4 py-3 font-mono text-sm">{state.password}</div>
        <div>
          <Link href={`/settings/users/${state.userId}`} className="text-sm font-medium text-brand hover:underline">
            Go to their profile →
          </Link>
        </div>
      </div>
    );
  }

  function toggleRole(roleId) {
    setSelectedRoles((current) => (current.includes(roleId) ? current.filter((id) => id !== roleId) : [...current, roleId]));
  }

  function handleSubmit(event) {
    event.preventDefault();

    const formData = new FormData();
    formData.set("name", name);
    if (!isEdit) formData.set("email", email);
    if (phone) formData.set("phone", phone);
    formData.set("locale", locale);
    if (factoryId) formData.set("factory_id", factoryId);
    if (departmentId) formData.set("department_id", departmentId);
    if (productionLineId) formData.set("production_line_id", productionLineId);
    for (const roleId of selectedRoles) formData.append("roles", String(roleId));

    startTransition(() => dispatch(formData));
  }

  return (
    <form onSubmit={handleSubmit} className="flex flex-col gap-5">
      {state?.status === "error" && !state.errors ? <Alert variant="danger">{state.message}</Alert> : null}

      <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <FormField label="Name" required error={state?.errors?.name?.[0]}>
          {(fieldProps) => <Input {...fieldProps} value={name} onChange={(e) => setName(e.target.value)} maxLength={255} required />}
        </FormField>
        <FormField label="Email" required={!isEdit} helperText={isEdit ? "The address identifies the account — not this company's to change." : undefined} error={state?.errors?.email?.[0]}>
          {(fieldProps) => <Input {...fieldProps} type="email" value={email} onChange={(e) => setEmail(e.target.value)} disabled={isEdit} required={!isEdit} />}
        </FormField>
        <FormField label="Phone">
          {(fieldProps) => <Input {...fieldProps} value={phone} onChange={(e) => setPhone(e.target.value)} maxLength={32} />}
        </FormField>
        <FormField label="Locale">
          {(fieldProps) => <Select {...fieldProps} value={locale} onValueChange={setLocale} options={LOCALE_OPTIONS} />}
        </FormField>
        <FormField label="Factory" helperText="Only factory-scoped roles use it; a company role covers every factory whatever is chosen here.">
          {(fieldProps) => (
            <Select {...fieldProps} value={factoryId} onValueChange={setFactoryId} placeholder="Company-wide" options={factories.map((f) => ({ value: f.id, label: f.name }))} />
          )}
        </FormField>
        <FormField label="Department" helperText="Only used by Line Chief — restricts which machines they may report a breakdown against to this department.">
          {(fieldProps) => (
            <Select {...fieldProps} value={departmentId} onValueChange={setDepartmentId} placeholder="Whole factory" options={departments.map((d) => ({ value: d.id, label: d.name }))} />
          )}
        </FormField>
        <FormField label="Production line" helperText="Narrower than department — leave unset unless this person covers one specific line.">
          {(fieldProps) => (
            <Select {...fieldProps} value={productionLineId} onValueChange={setProductionLineId} placeholder="Whole department" options={productionLines.map((l) => ({ value: l.id, label: l.name }))} />
          )}
        </FormField>
      </div>

      {!isEdit ? (
        <p className="text-xs text-foreground-muted">
          Most people on a factory floor have no working email address, so a password is generated rather than sent as a reset link.
        </p>
      ) : null}

      <FormField label="Roles" required error={state?.errors?.roles?.[0]}>
        {() => (
          <div className="grid grid-cols-1 gap-2 rounded-sm border border-border-strong p-3 sm:grid-cols-2">
            {roles.map((role) => (
              <label key={role.id} className="flex items-start gap-2 rounded-sm border border-border p-2 text-sm text-foreground">
                <Checkbox checked={selectedRoles.includes(role.id)} onCheckedChange={() => toggleRole(role.id)} className="mt-0.5" />
                <span>
                  <span className="font-medium">{role.name}</span>
                  <span className="ml-1.5 text-xs text-foreground-muted">{role.scope === "FACTORY" ? "Factory" : "Company"}</span>
                </span>
              </label>
            ))}
          </div>
        )}
      </FormField>

      <div className="flex justify-end gap-2">
        <Link href={isEdit ? `/settings/users/${user.id}` : "/settings/users"} className="inline-flex h-10 items-center rounded-sm border border-border-strong px-4 text-sm font-medium hover:bg-surface-muted">
          Cancel
        </Link>
        <Button type="submit" loading={pending} disabled={selectedRoles.length === 0}>
          {isEdit ? "Save changes" : "Invite user"}
        </Button>
      </div>
    </form>
  );
}

export { UserForm };
