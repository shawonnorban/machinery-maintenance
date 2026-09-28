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
 * Create/edit a purchase requisition (Procurement spec §5). Posts the
 * header fields plus `lines` as JSON; the server action turns that into
 * `PurchaseRequisitionApiController::store/update`'s body.
 */
function RequisitionForm({ requisition, options, searchItems, action }) {
  const t = useT("procurement");
  const tc = useT("common");
  const router = useRouter();
  const [state, dispatch, pending] = useActionState(action, null);

  const [factoryId, setFactoryId] = useState(requisition?.factory?.id ?? (options.factories.length === 1 ? options.factories[0].id : ""));
  const [storeId, setStoreId] = useState(requisition?.store?.id ?? "");
  const [prDate, setPrDate] = useState(requisition?.pr_date ?? new Date().toISOString().slice(0, 10));
  const [prType, setPrType] = useState(requisition?.pr_type ?? "MANUAL");
  const [priority, setPriority] = useState(requisition?.priority ?? "NORMAL");
  const [currency, setCurrency] = useState(requisition?.currency ?? "BDT");
  const [notes, setNotes] = useState(requisition?.notes ?? "");
  const [lines, setLines] = useState(() =>
    requisition?.lines?.length
      ? requisition.lines.map((line) =>
          newLine({
            item: { id: line.item_id, item_code: line.item_code, name: line.item_name, uom: line.uom },
            uom_id: line.uom?.id ?? "",
            quantity: line.requested_qty,
            rate: line.estimated_rate ?? "",
            required_date: line.required_date ?? "",
            notes: line.notes ?? "",
          }),
        )
      : [newLine()],
  );

  const stores = options.stores.filter((s) => !factoryId || s.factory_id === factoryId);

  function handleSubmit(event) {
    event.preventDefault();
    const formData = new FormData();
    formData.set("factory_id", factoryId);
    formData.set("store_id", storeId);
    formData.set("pr_date", prDate);
    formData.set("pr_type", prType);
    formData.set("priority", priority);
    formData.set("currency", currency);
    formData.set("notes", notes);
    formData.set(
      "lines",
      JSON.stringify(
        serialiseLines(lines, "requested_qty").map(({ rate, ...line }) => ({ ...line, ...(rate !== undefined ? { estimated_rate: rate } : {}) })),
      ),
    );
    startTransition(() => dispatch(formData));
  }

  return (
    <form onSubmit={handleSubmit} className="flex flex-col gap-4">
      {state?.status === "error" && !state.errors ? <Alert variant="danger">{state.message}</Alert> : null}
      {state?.status === "error" && state.errors?.lines?.[0] ? <Alert variant="danger">{state.errors.lines[0]}</Alert> : null}

      <Card>
        <CardHeader>
          <CardTitle>{t("purchase_requisition")}</CardTitle>
        </CardHeader>
        <CardBody className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
          <FormField label={t("factory")} required error={state?.errors?.factory_id?.[0]}>
            {(fieldProps) => (
              <Select {...fieldProps} value={factoryId} onValueChange={setFactoryId} options={options.factories.map((f) => ({ value: f.id, label: f.name }))} placeholder={t("factory")} disabled={Boolean(requisition)} />
            )}
          </FormField>
          <FormField label={t("store")} error={state?.errors?.store_id?.[0]}>
            {(fieldProps) => (
              <Select {...fieldProps} value={storeId} onValueChange={setStoreId} options={[{ value: "", label: "—" }, ...stores.map((s) => ({ value: s.id, label: s.name }))]} placeholder={t("store")} />
            )}
          </FormField>
          <FormField label={t("date")} required error={state?.errors?.pr_date?.[0]}>
            {(fieldProps) => <Input {...fieldProps} type="date" value={prDate} onChange={(e) => setPrDate(e.target.value)} />}
          </FormField>
          <FormField label={t("pr_type")} error={state?.errors?.pr_type?.[0]}>
            {(fieldProps) => (
              <Select {...fieldProps} value={prType} onValueChange={setPrType} options={options.pr_types.map((type) => ({ value: type, label: t(`pr_types.${type}`) }))} />
            )}
          </FormField>
          <FormField label={t("currency")} error={state?.errors?.currency?.[0]}>
            {(fp) => (
              <Select
                {...fp}
                value={currency}
                onValueChange={setCurrency}
                options={(options.currencies ?? ["BDT"]).map((c) => ({ value: c, label: c }))}
              />
            )}
          </FormField>

          <FormField label={t("priority")} error={state?.errors?.priority?.[0]}>
            {(fieldProps) => (
              <Select {...fieldProps} value={priority} onValueChange={setPriority} options={options.priorities.map((p) => ({ value: p, label: t(`priorities.${p}`) }))} />
            )}
          </FormField>
          <div className="sm:col-span-2 lg:col-span-3">
            <FormField label={t("notes")} error={state?.errors?.notes?.[0]}>
              {(fieldProps) => <Textarea {...fieldProps} value={notes} onChange={(e) => setNotes(e.target.value)} rows={2} maxLength={2000} />}
            </FormField>
          </div>
        </CardBody>
      </Card>

      <DocumentLinesEditor
        lines={lines}
        onChange={setLines}
        departments={options.item_departments}
        uoms={options.uoms}
        searchItems={searchItems}
        fields={["quantity", "uom", "rate", "required_date", "notes"]}
        currency={currency}
        quantityLabel={t("requested_qty")}
        errors={state?.errors}
      />

      <div className="flex justify-end gap-2">
        <Button type="button" variant="outline" onClick={() => router.back()}>
          {tc("cancel")}
        </Button>
        <Button type="submit" loading={pending}>
          {t("save_draft")}
        </Button>
      </div>
    </form>
  );
}

export { RequisitionForm };
