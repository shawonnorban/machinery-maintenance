"use client";

import { ArrowLeft, Save } from "lucide-react";
import { useActionState, useMemo, useState } from "react";
import { useRouter } from "next/navigation";
import { FormActions, FormSection } from "@/components/ui/form-section";
import { FormField } from "@/components/ui/form-field";
import { Select } from "@/components/ui/select";
import { Input } from "@/components/ui/input";
import { Button } from "@/components/ui/button";
import { Alert } from "@/components/ui/alert";
import { useT } from "@/lib/i18n";

/**
 * Mirrors `TechnicianController`'s create/edit form, with the person picked
 * from HR & Payroll's employee list (the user's decision): choosing the
 * employee fills who they are — name, employee ID, mobile, email, joining
 * date, shown read-only since HR owns them — and starts Factory → Department
 * → Line at their HR posting, which stays editable because what a technician
 * looks after can be wider or narrower than where HR posts them.
 *
 * Departments belong to a factory and lines to a department, so the cascade
 * is two filters deep. A record entered before the link keeps its typed
 * fields until someone picks the employee. Skills live in the sibling
 * `TechnicianSkills` component — a skill belongs to a saved record.
 */
function TechnicianForm({ technician, factories, departments, productionLines, users, employees = [], action }) {
  const t = useT("technician");
  const tc = useT("common");
  const [state, dispatch, pending] = useActionState(action, null);
  const router = useRouter();

  const [employeeId, setEmployeeId] = useState(technician?.hr_employee_id ?? "");
  const [factoryId, setFactoryId] = useState(technician?.factory?.id ?? factories[0]?.id ?? "");
  const [departmentId, setDepartmentId] = useState(technician?.department_id ?? "");
  const [lineId, setLineId] = useState(technician?.production_line_id ?? "");

  const employee = employees.find((e) => e.id === employeeId) ?? null;
  const linked = Boolean(employee);

  // Someone already on the roster is not offered again, except this record's own employee.
  const employeeOptions = useMemo(
    () =>
      employees
        .filter((e) => !e.technician_id || e.technician_id === technician?.id)
        .map((e) => ({ value: e.id, label: `${e.employee_code} — ${e.name}${e.designation ? ` (${e.designation})` : ""}` })),
    [employees, technician?.id],
  );
  const departmentOptions = useMemo(() => departments.filter((d) => d.factory_id === factoryId), [departments, factoryId]);
  const lineOptions = useMemo(() => productionLines.filter((l) => l.department_id === departmentId), [productionLines, departmentId]);

  function handleEmployeeChange(nextId) {
    setEmployeeId(nextId);
    const picked = employees.find((e) => e.id === nextId);
    if (picked?.factory_id && factories.some((f) => f.id === picked.factory_id)) {
      setFactoryId(picked.factory_id);
      setDepartmentId(departments.some((d) => d.id === picked.department_id) ? picked.department_id : "");
      setLineId(productionLines.some((l) => l.id === picked.line_id) ? picked.line_id : "");
    }
  }

  function handleFactoryChange(nextFactoryId) {
    setFactoryId(nextFactoryId);
    setDepartmentId("");
    setLineId("");
  }

  function handleDepartmentChange(nextDepartmentId) {
    setDepartmentId(nextDepartmentId);
    setLineId("");
  }

  // HR owns who they are: read-only from the picked employee. Typed only on a
  // record from before the link, until an employee is picked for it.
  const typed = Boolean(technician) && !technician.hr_employee_id && !linked;
  const identity = (name, value, fallback, props = {}) =>
    !typed ? (fieldProps) => <Input {...fieldProps} {...props} value={value ?? ""} readOnly disabled title={t("from_hr")} /> : (fieldProps) => <Input {...fieldProps} {...props} name={name} defaultValue={fallback ?? ""} />;

  return (
    <form action={dispatch} className="flex flex-col gap-4">
      {technician && !technician.hr_employee_id ? <Alert variant="info" title={t("not_linked")} /> : null}

      <FormSection>
        <div className="grid gap-x-8 gap-y-3 lg:grid-cols-2">
          <div className="lg:col-span-2">
            <FormField label={t("hr_employee")} required={!technician} helperText={t("hr_employee_hint")} error={state?.errors?.hr_employee_id?.[0]}>
              {(fieldProps) => (
                <Select
                  {...fieldProps}
                  name="hr_employee_id"
                  value={employeeId}
                  onValueChange={handleEmployeeChange}
                  options={employeeOptions}
                  placeholder={t("select_employee")}
                />
              )}
            </FormField>
          </div>

          <FormField label={t("name")} required error={state?.errors?.name?.[0]}>
            {identity("name", employee?.name, technician?.name, { required: typed })}
          </FormField>
          <FormField label={t("employee_id")} required error={state?.errors?.employee_id?.[0]}>
            {identity("employee_id", employee?.employee_code, technician?.employee_id, { required: typed })}
          </FormField>
          <FormField label={t("phone")}>{identity("phone", employee?.mobile, technician?.phone)}</FormField>
          <FormField label={t("email")} error={state?.errors?.email?.[0]}>
            {identity("email", employee?.email, technician?.email, { type: "email" })}
          </FormField>
          <FormField label={t("joining_date")}>{identity("joining_date", employee?.joining_date, technician?.joining_date, { type: "date" })}</FormField>
        </div>
      </FormSection>

      <FormSection legend={t("coverage")}>
        <div className="grid gap-x-8 gap-y-3 lg:grid-cols-2">
          <FormField label={t("factory")} required error={state?.errors?.factory_id?.[0]}>
            {(fieldProps) => (
              <Select {...fieldProps} name="factory_id" value={factoryId} onValueChange={handleFactoryChange} options={factories.map((f) => ({ value: f.id, label: f.name }))} />
            )}
          </FormField>
          <FormField label={t("department")} error={state?.errors?.department_id?.[0]}>
            {(fieldProps) => (
              <Select {...fieldProps} name="department_id" value={departmentId} onValueChange={handleDepartmentChange} options={departmentOptions.map((d) => ({ value: d.id, label: d.name }))} />
            )}
          </FormField>
          <FormField label={t("production_line")} error={state?.errors?.production_line_id?.[0]}>
            {(fieldProps) => (
              <Select {...fieldProps} name="production_line_id" value={lineId} onValueChange={setLineId} options={lineOptions.map((l) => ({ value: l.id, label: l.name }))} />
            )}
          </FormField>
          <FormField label={t("specialization")}>
            {(fieldProps) => <Input {...fieldProps} name="specialization" defaultValue={technician?.specialization} placeholder={employee?.designation ?? undefined} />}
          </FormField>
          <FormField label={t("workload_limit")} helperText={t("workload_hint")} error={state?.errors?.max_concurrent_work_orders?.[0]}>
            {(fieldProps) => (
              <Input {...fieldProps} type="number" min="1" max="50" name="max_concurrent_work_orders" defaultValue={technician?.max_concurrent_work_orders ?? ""} />
            )}
          </FormField>
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
        </div>

        {state?.status === "error" && !state.errors ? <p className="text-sm text-danger">{state.message}</p> : null}
      </FormSection>

      <FormActions>
        <Button type="button" variant="outline" onClick={() => router.back()}>
          <ArrowLeft /> {tc("cancel")}
        </Button>
        <Button type="submit" loading={pending}>
          <Save /> {tc("save")}
        </Button>
      </FormActions>
    </form>
  );
}

export { TechnicianForm };
