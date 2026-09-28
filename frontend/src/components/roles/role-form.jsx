"use client";

import { startTransition, useActionState, useState } from "react";
import Link from "next/link";
import { FormField } from "@/components/ui/form-field";
import { Input } from "@/components/ui/input";
import { Select } from "@/components/ui/select";
import { Checkbox } from "@/components/ui/checkbox";
import { Button } from "@/components/ui/button";
import { Alert } from "@/components/ui/alert";
import { Badge } from "@/components/ui/badge";
import { useT } from "@/lib/i18n";

/** `useActionState` needs a real function even when nothing can ever be submitted (the form's own submit button is hidden in read-only mode, but the hook itself still has to be called). */
async function noopAction() {
  return null;
}

/**
 * `RoleController` on the web has never had a write form of its own — it
 * is read-only there, "a tenant clones one to customize it" was
 * documented intent with no code behind it until `ManageRole` was built.
 * This form (create and clone-from-seeded, and edit for a company's own
 * role) is genuinely new rather than ported, built because the API
 * (`RoleApiController::store`/`update`) already fully supports it.
 *
 * `readOnly` renders a seeded role's own name/scope/permissions with every
 * control disabled and no Save button — the permission set a seeded role
 * actually holds was previously only readable from `RoleSeeder.php`'s own
 * source, not from the product itself.
 *
 * @param {{
 *   role?: object|null, permissionGroups: Record<string, {code:string,name:string,is_elevated:boolean}[]>,
 *   cloneableRoles?: {id:number,name:string}[], action?: Function, readOnly?: boolean,
 * }} props
 */
function RoleForm({ role = null, permissionGroups, cloneableRoles = [], action, readOnly = false }) {
  const t = useT("user");
  const tc = useT("common");
  const SCOPE_OPTIONS = [
    { value: "FACTORY", label: t("factory_scope") },
    { value: "COMPANY", label: t("company_scope") },
  ];
  const isEdit = role !== null;
  const [state, dispatch, pending] = useActionState(readOnly ? noopAction : action, null);
  const [code, setCode] = useState(role?.code ?? "");
  const [name, setName] = useState(role?.name ?? "");
  const [scope, setScope] = useState(role?.scope ?? "FACTORY");
  const [cloneFrom, setCloneFrom] = useState("");
  const [selectedPermissions, setSelectedPermissions] = useState(() => role?.permissions ?? []);

  const cloning = !isEdit && cloneFrom !== "";

  function togglePermission(code) {
    setSelectedPermissions((current) => (current.includes(code) ? current.filter((c) => c !== code) : [...current, code]));
  }

  function toggleGroup(codes, allSelected) {
    setSelectedPermissions((current) =>
      allSelected ? current.filter((c) => !codes.includes(c)) : [...new Set([...current, ...codes])],
    );
  }

  function handleSubmit(event) {
    event.preventDefault();
    const formData = new FormData();
    formData.set("code", code);
    formData.set("name", name);
    if (!isEdit) formData.set("scope", scope);
    if (cloneFrom) formData.set("clone_from", cloneFrom);
    // Omitted entirely when cloning with no permission edited yet: the API
    // copies the source's own set only when `permissions` is absent from
    // the request (`ManageRole::cloneFrom`).
    if (!cloning || selectedPermissions.length > 0) {
      for (const code of selectedPermissions) formData.append("permissions", code);
    }
    startTransition(() => dispatch(formData));
  }

  return (
    <form onSubmit={handleSubmit} className="flex flex-col gap-5">
      {state?.status === "error" && !state.errors ? <Alert variant="danger">{state.message}</Alert> : null}

      <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <FormField label={t("code")} required helperText={t("code_hint")} error={state?.errors?.code?.[0]}>
          {(fieldProps) => <Input {...fieldProps} value={code} onChange={(e) => setCode(e.target.value.toUpperCase())} maxLength={64} required disabled={readOnly} />}
        </FormField>
        <FormField label={t("name")} required error={state?.errors?.name?.[0]}>
          {(fieldProps) => <Input {...fieldProps} value={name} onChange={(e) => setName(e.target.value)} maxLength={255} required disabled={readOnly} />}
        </FormField>

        {isEdit && readOnly ? (
          <FormField label={t("scope")}>
            {(fieldProps) => <Select {...fieldProps} value={scope} options={SCOPE_OPTIONS} disabled />}
          </FormField>
        ) : null}

        {!isEdit ? (
          <>
            <FormField label={t("scope")} required>
              {(fieldProps) => <Select {...fieldProps} value={scope} onValueChange={setScope} options={SCOPE_OPTIONS} />}
            </FormField>
            <FormField label={t("clone_from")} helperText={t("clone_from_hint")}>
              {(fieldProps) => (
                <Select
                  {...fieldProps}
                  value={cloneFrom}
                  onValueChange={(value) => {
                    setCloneFrom(value);
                    setSelectedPermissions([]);
                  }}
                  placeholder={t("start_from_scratch")}
                  options={cloneableRoles.map((r) => ({ value: String(r.id), label: r.name }))}
                />
              )}
            </FormField>
          </>
        ) : null}
      </div>

      <FormField label={t("permissions")} required error={state?.errors?.permissions?.[0]}>
        {() => (
          <div className="flex max-h-[28rem] flex-col gap-4 overflow-y-auto rounded-sm border border-border-strong p-3">
            {cloning && selectedPermissions.length === 0 ? (
              <p className="text-xs text-foreground-muted">{t("copy_permissions_hint")}</p>
            ) : null}
            {Object.entries(permissionGroups).map(([module, permissions]) => {
              const codes = permissions.map((p) => p.code);
              const allSelected = codes.length > 0 && codes.every((c) => selectedPermissions.includes(c));
              return (
                <div key={module}>
                  <div className="mb-1.5 flex items-center gap-2">
                    <Checkbox checked={allSelected} onCheckedChange={() => toggleGroup(codes, allSelected)} disabled={readOnly} />
                    <span className="text-xs font-semibold tracking-wide text-foreground-muted uppercase">{module}</span>
                  </div>
                  <div className="grid grid-cols-1 gap-1 pl-6 sm:grid-cols-2">
                    {permissions.map((permission) => (
                      <label key={permission.code} className="flex items-center gap-2 text-sm text-foreground">
                        <Checkbox checked={selectedPermissions.includes(permission.code)} onCheckedChange={() => togglePermission(permission.code)} disabled={readOnly} />
                        {permission.name}
                        {permission.is_elevated ? <Badge variant="warning">{t("elevated")}</Badge> : null}
                      </label>
                    ))}
                  </div>
                </div>
              );
            })}
          </div>
        )}
      </FormField>

      <div className="flex justify-end gap-2">
        <Link href="/settings/roles" className="inline-flex h-10 items-center rounded-sm border border-border-strong px-4 text-sm font-medium hover:bg-surface-muted">
          {readOnly ? tc("back") : tc("cancel")}
        </Link>
        {readOnly ? null : (
          <Button type="submit" loading={pending}>
            {isEdit ? t("save_changes") : t("create_role")}
          </Button>
        )}
      </div>
    </form>
  );
}

export { RoleForm };
