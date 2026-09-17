"use client";

import { useActionState, useEffect, useMemo, useState } from "react";
import { useRouter } from "next/navigation";
import { FormField } from "@/components/ui/form-field";
import { Select } from "@/components/ui/select";
import { Input } from "@/components/ui/input";
import { Button } from "@/components/ui/button";
import { useToastManager } from "@/components/ui/toast";
import { useT } from "@/lib/i18n";

/**
 * Mirrors `AssetLocationController`'s create/edit form — a location names a
 * specific combination of factory, building, department and line, so each
 * dropdown narrows to the chosen factory's own rows. Floor/Section/
 * Workstation are a level narrower still — a floor belongs to a building,
 * a section AND a production line both belong to a department (`ProductionLine`
 * has no `factory_id` of its own, only `department_id` — filtering it by
 * factory instead left this dropdown empty for every real record), and a
 * workstation belongs to a production line — so those three cascade off
 * the pick one level up rather than off the factory directly, the same
 * pattern the technician form already uses for department → production line.
 */
function LocationForm({ location, factories, buildings, floors, departments, sections, productionLines, workstations, action, onDone, onCancel }) {
  const t = useT("asset");
  const tc = useT("common");
  const isEdit = location != null;
  const [state, dispatch, pending] = useActionState(action, null);
  const [factoryId, setFactoryId] = useState(location?.factory_id ?? factories[0]?.id ?? "");
  const [buildingId, setBuildingId] = useState(location?.building_id ?? "");
  const [departmentId, setDepartmentId] = useState(location?.department_id ?? "");
  const [productionLineId, setProductionLineId] = useState(location?.production_line_id ?? "");
  const router = useRouter();
  const toastManager = useToastManager();

  useEffect(() => {
    if (state?.status === "success") {
      toastManager.add({ title: isEdit ? t("location_saved_toast") : t("location_created_toast"), type: "success" });
      queueMicrotask(() => onDone?.());
      router.refresh();
    }
    // toastManager/router/onDone/isEdit are deliberately excluded — see
    // every other form in this app for why including them re-fires this
    // effect every render once state first becomes "success".
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [state]);

  const buildingOptions = useMemo(() => buildings.filter((b) => b.factory_id === factoryId), [buildings, factoryId]);
  const floorOptions = useMemo(() => floors.filter((f) => f.building_id === buildingId), [floors, buildingId]);
  const departmentOptions = useMemo(() => departments.filter((d) => d.factory_id === factoryId), [departments, factoryId]);
  const sectionOptions = useMemo(() => sections.filter((s) => s.department_id === departmentId), [sections, departmentId]);
  const lineOptions = useMemo(() => productionLines.filter((l) => l.department_id === departmentId), [productionLines, departmentId]);
  const workstationOptions = useMemo(
    () => workstations.filter((w) => w.production_line_id === productionLineId),
    [workstations, productionLineId],
  );

  function handleFactoryChange(nextFactoryId) {
    setFactoryId(nextFactoryId);
    setBuildingId("");
    setDepartmentId("");
    setProductionLineId("");
  }

  function handleDepartmentChange(nextDepartmentId) {
    setDepartmentId(nextDepartmentId);
    setProductionLineId("");
  }

  return (
    <form action={dispatch} className="flex flex-col gap-4">
      <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <FormField label={t("factory")} required error={state?.errors?.factory_id?.[0]}>
          {(fieldProps) => (
            <Select
              {...fieldProps}
              name="factory_id"
              value={factoryId}
              onValueChange={handleFactoryChange}
              options={factories.map((f) => ({ value: f.id, label: f.name }))}
            />
          )}
        </FormField>
        <FormField label={t("location_code")} required helperText={t("location_code_hint")} error={state?.errors?.code?.[0]}>
          {(fieldProps) => <Input {...fieldProps} name="code" defaultValue={location?.code} required />}
        </FormField>
      </div>

      <FormField label={t("location_name")} required error={state?.errors?.name?.[0]}>
        {(fieldProps) => <Input {...fieldProps} name="name" defaultValue={location?.name} required />}
      </FormField>

      <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
        <FormField label={t("building")}>
          {(fieldProps) => (
            <Select
              {...fieldProps}
              name="building_id"
              value={buildingId}
              onValueChange={setBuildingId}
              options={buildingOptions.map((b) => ({ value: b.id, label: b.name }))}
            />
          )}
        </FormField>
        <FormField label={t("department")}>
          {(fieldProps) => (
            <Select
              {...fieldProps}
              name="department_id"
              value={departmentId}
              onValueChange={handleDepartmentChange}
              options={departmentOptions.map((d) => ({ value: d.id, label: d.name }))}
            />
          )}
        </FormField>
        <FormField label={t("production_line")} helperText={departmentId ? undefined : t("select_a_department_first")}>
          {(fieldProps) => (
            <Select
              {...fieldProps}
              name="production_line_id"
              value={productionLineId}
              onValueChange={setProductionLineId}
              disabled={!departmentId}
              options={lineOptions.map((l) => ({ value: l.id, label: l.name }))}
            />
          )}
        </FormField>
      </div>

      <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
        <FormField label={t("floor")} helperText={buildingId ? undefined : t("select_a_building_first")}>
          {(fieldProps) => (
            <Select
              {...fieldProps}
              name="floor_id"
              defaultValue={location?.floor_id ?? ""}
              disabled={!buildingId}
              options={floorOptions.map((f) => ({ value: f.id, label: f.name }))}
            />
          )}
        </FormField>
        <FormField label={t("section")} helperText={departmentId ? undefined : t("select_a_department_first")}>
          {(fieldProps) => (
            <Select
              {...fieldProps}
              name="section_id"
              defaultValue={location?.section_id ?? ""}
              disabled={!departmentId}
              options={sectionOptions.map((s) => ({ value: s.id, label: s.name }))}
            />
          )}
        </FormField>
        <FormField label={t("workstation")} helperText={productionLineId ? undefined : t("select_a_production_line_first")}>
          {(fieldProps) => (
            <Select
              {...fieldProps}
              name="workstation_id"
              defaultValue={location?.workstation_id ?? ""}
              disabled={!productionLineId}
              options={workstationOptions.map((w) => ({ value: w.id, label: w.name }))}
            />
          )}
        </FormField>
      </div>

      {state?.status === "error" && !state.errors ? <p className="text-sm text-danger">{state.message}</p> : null}

      <div className="flex justify-end gap-2">
        <Button type="button" variant="outline" onClick={onCancel}>
          {tc("cancel")}
        </Button>
        <Button type="submit" loading={pending}>
          {tc("save")}
        </Button>
      </div>
    </form>
  );
}

export { LocationForm };
