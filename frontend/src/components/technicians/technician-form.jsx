"use client";

import { useActionState, useMemo, useState } from "react";
import { useRouter } from "next/navigation";
import { FormField } from "@/components/ui/form-field";
import { Select } from "@/components/ui/select";
import { Input } from "@/components/ui/input";
import { Button } from "@/components/ui/button";
import { useT } from "@/lib/i18n";

/**
 * Mirrors `TechnicianController`'s create/edit form. Departments belong to a
 * factory and production lines belong to a department (`ProductionLineData`
 * has no `factory_id` of its own) — so the cascade is Factory → Department →
 * Line, two filters deep, not one. Skills (`technicians/form.blade.php`'s
 * own second card, shown only once a technician exists) live in the
 * sibling `TechnicianSkills` component instead of here — a skill belongs
 * to a saved record, not to this form's own draft state.
 */
function TechnicianForm({ technician, factories, departments, productionLines, users, action }) {
  const t = useT("technician");
  const tc = useT("common");
  const [state, dispatch, pending] = useActionState(action, null);
  const [factoryId, setFactoryId] = useState(technician?.factory?.id ?? factories[0]?.id ?? "");
  const [departmentId, setDepartmentId] = useState(technician?.department_id ?? "");
  const router = useRouter();

  const departmentOptions = useMemo(() => departments.filter((d) => d.factory_id === factoryId), [departments, factoryId]);
  const lineOptions = useMemo(() => productionLines.filter((l) => l.department_id === departmentId), [productionLines, departmentId]);

  function handleFactoryChange(nextFactoryId) {
    setFactoryId(nextFactoryId);
    setDepartmentId("");
  }

  return (
    <form action={dispatch} className="flex flex-col gap-4">
      <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <FormField label={t("name")} required error={state?.errors?.name?.[0]}>
          {(fieldProps) => <Input {...fieldProps} name="name" defaultValue={technician?.name} required />}
        </FormField>
        <FormField label={t("employee_id")} required error={state?.errors?.employee_id?.[0]}>
          {(fieldProps) => <Input {...fieldProps} name="employee_id" defaultValue={technician?.employee_id} required />}
        </FormField>
      </div>

      <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
        <FormField label={t("factory")} required error={state?.errors?.factory_id?.[0]}>
          {(fieldProps) => (
            <Select {...fieldProps} name="factory_id" value={factoryId} onValueChange={handleFactoryChange} options={factories.map((f) => ({ value: f.id, label: f.name }))} />
          )}
        </FormField>
        <FormField label={t("department")}>
          {(fieldProps) => (
            <Select {...fieldProps} name="department_id" value={departmentId} onValueChange={setDepartmentId} options={departmentOptions.map((d) => ({ value: d.id, label: d.name }))} />
          )}
        </FormField>
        <FormField label={t("production_line")}>
          {(fieldProps) => (
            <Select {...fieldProps} name="production_line_id" defaultValue={technician?.production_line_id ?? ""} options={lineOptions.map((l) => ({ value: l.id, label: l.name }))} />
          )}
        </FormField>
      </div>

      <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <FormField label={t("phone")}>
          {(fieldProps) => <Input {...fieldProps} name="phone" defaultValue={technician?.phone} />}
        </FormField>
        <FormField label={t("email")} error={state?.errors?.email?.[0]}>
          {(fieldProps) => <Input {...fieldProps} type="email" name="email" defaultValue={technician?.email} />}
        </FormField>
      </div>

      <FormField label={t("login")} helperText={t("login_hint")} error={state?.errors?.user_id?.[0]}>
        {(fieldProps) => (
          <Select
            {...fieldProps}
            name="user_id"
            defaultValue={technician?.user_id ?? ""}
            placeholder={t("no_login")}
            options={users.map((u) => ({ value: u.id, label: `${u.name} (${u.email})` }))}
          />
        )}
      </FormField>

      <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
        <FormField label={t("specialization")}>
          {(fieldProps) => <Input {...fieldProps} name="specialization" defaultValue={technician?.specialization} />}
        </FormField>
        <FormField label={t("joining_date")}>
          {(fieldProps) => <Input {...fieldProps} type="date" name="joining_date" defaultValue={technician?.joining_date ?? ""} />}
        </FormField>
        <FormField label={t("workload_limit")} helperText={t("workload_hint")} error={state?.errors?.max_concurrent_work_orders?.[0]}>
          {(fieldProps) => (
            <Input {...fieldProps} type="number" min="1" max="50" name="max_concurrent_work_orders" defaultValue={technician?.max_concurrent_work_orders ?? ""} />
          )}
        </FormField>
      </div>

      {state?.status === "error" && !state.errors ? <p className="text-sm text-danger">{state.message}</p> : null}

      <div className="flex justify-end gap-2">
        <Button type="button" variant="outline" onClick={() => router.back()}>
          {tc("cancel")}
        </Button>
        <Button type="submit" loading={pending}>
          {tc("save")}
        </Button>
      </div>
    </form>
  );
}

export { TechnicianForm };
