"use client";

import { startTransition, useActionState, useState } from "react";
import { useRouter } from "next/navigation";
import { Card, CardHeader, CardTitle, CardBody } from "@/components/ui/card";
import { FormField } from "@/components/ui/form-field";
import { Select } from "@/components/ui/select";
import { Input } from "@/components/ui/input";
import { Textarea } from "@/components/ui/textarea";
import { Button } from "@/components/ui/button";
import { Alert } from "@/components/ui/alert";
import { DocumentLinesEditor, newLine, serialiseLines } from "@/components/procurement/document-lines-editor";
import { useT } from "@/lib/i18n";

/**
 * Create/edit a floor requisition (Floor/Store Requisition spec §3).
 *
 * The item department dropdown lists every department, whoever is asking
 * (§3.1); once one is chosen the item picker is pinned to it, and changing
 * it clears the lines since they must all come from that department.
 */
function FloorRequisitionForm({ requisition, options, searchItems, action }) {
  const t = useT("requisition");
  const tc = useT("common");
  const router = useRouter();
  const [state, dispatch, pending] = useActionState(action, null);

  const [factoryId, setFactoryId] = useState(requisition?.factory?.id ?? (options.factories.length === 1 ? options.factories[0].id : ""));
  const [targetDepartmentId, setTargetDepartmentId] = useState(requisition?.target_item_department?.id ?? "");
  const [requesterDepartmentId, setRequesterDepartmentId] = useState(requisition?.requester_department?.id ?? "");
  const [frDate, setFrDate] = useState(requisition?.fr_date ?? new Date().toISOString().slice(0, 10));
  const [priority, setPriority] = useState(requisition?.priority ?? "NORMAL");
  const [purpose, setPurpose] = useState(requisition?.purpose ?? "");
  const [lines, setLines] = useState(() =>
    requisition?.lines?.length
      ? requisition.lines.map((line) =>
          newLine({
            item: { id: line.item_id, item_code: line.item_code, name: line.item_name, uom: line.uom },
            uom_id: line.uom?.id ?? "",
            quantity: line.requested_qty,
            notes: line.purpose ?? "",
          }),
        )
      : [newLine()],
  );

  function changeDepartment(id) {
    setTargetDepartmentId(id);
    setLines([newLine()]);
  }

  function handleSubmit(event) {
    event.preventDefault();
    const formData = new FormData();
    formData.set("factory_id", factoryId);
    formData.set("target_item_department_id", targetDepartmentId);
    formData.set("requester_department_id", requesterDepartmentId);
    formData.set("fr_date", frDate);
    formData.set("priority", priority);
    formData.set("purpose", purpose);
    formData.set(
      "lines",
      JSON.stringify(serialiseLines(lines, "requested_qty").map(({ notes, ...line }) => ({ ...line, ...(notes ? { purpose: notes } : {}) }))),
    );
    startTransition(() => dispatch(formData));
  }

  return (
    <form onSubmit={handleSubmit} className="flex flex-col gap-4">
      {state?.status === "error" && !state.errors ? <Alert variant="danger">{state.message}</Alert> : null}
      {state?.status === "error" && state.errors?.lines?.[0] ? <Alert variant="danger">{state.errors.lines[0]}</Alert> : null}

      <Card>
        <CardHeader>
          <CardTitle>{t("floor_requisition")}</CardTitle>
        </CardHeader>
        <CardBody className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
          <FormField label={t("factory")} required error={state?.errors?.factory_id?.[0]}>
            {(fieldProps) => (
              <Select {...fieldProps} value={factoryId} onValueChange={setFactoryId} options={options.factories.map((f) => ({ value: f.id, label: f.name }))} placeholder={t("factory")} disabled={Boolean(requisition)} />
            )}
          </FormField>
          <FormField label={t("item_department")} required helperText={t("item_department_hint")} error={state?.errors?.target_item_department_id?.[0]}>
            {(fieldProps) => (
              <Select {...fieldProps} value={targetDepartmentId} onValueChange={changeDepartment} options={options.item_departments.map((d) => ({ value: d.id, label: d.name }))} placeholder={t("item_department")} />
            )}
          </FormField>
          <FormField label={t("your_department")} helperText={t("your_department_hint")} error={state?.errors?.requester_department_id?.[0]}>
            {(fieldProps) => (
              <Select {...fieldProps} value={requesterDepartmentId} onValueChange={setRequesterDepartmentId} options={[{ value: "", label: "—" }, ...options.departments.map((d) => ({ value: d.id, label: d.name }))]} placeholder={t("your_department")} />
            )}
          </FormField>
          <FormField label={t("priority")} error={state?.errors?.priority?.[0]}>
            {(fieldProps) => (
              <Select {...fieldProps} value={priority} onValueChange={setPriority} options={options.priorities.map((p) => ({ value: p, label: t(`priorities.${p}`) }))} />
            )}
          </FormField>
          <FormField label={t("date")} required error={state?.errors?.fr_date?.[0]}>
            {(fieldProps) => <Input {...fieldProps} type="date" value={frDate} onChange={(e) => setFrDate(e.target.value)} />}
          </FormField>
          <div className="sm:col-span-2 lg:col-span-3">
            <FormField label={t("purpose")} error={state?.errors?.purpose?.[0]}>
              {(fieldProps) => <Textarea {...fieldProps} value={purpose} onChange={(e) => setPurpose(e.target.value)} rows={2} maxLength={2000} />}
            </FormField>
          </div>
        </CardBody>
      </Card>

      {targetDepartmentId ? (
        <DocumentLinesEditor
          lines={lines}
          onChange={setLines}
          departments={options.item_departments}
          uoms={options.uoms}
          searchItems={searchItems}
          fields={["quantity", "uom", "notes"]}
          quantityLabel={t("requested_qty")}
          title={t("lines")}
          errors={state?.errors}
          pinnedDepartmentId={targetDepartmentId}
        />
      ) : (
        <Alert variant="info">{t("item_department_hint")}</Alert>
      )}

      <div className="flex justify-end gap-2">
        <Button type="button" variant="outline" onClick={() => router.back()}>
          {tc("cancel")}
        </Button>
        <Button type="submit" loading={pending} disabled={!targetDepartmentId}>
          {t("save_draft")}
        </Button>
      </div>
    </form>
  );
}

export { FloorRequisitionForm };
