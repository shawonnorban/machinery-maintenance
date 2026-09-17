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
import { Trash2, Plus } from "lucide-react";
import { useT } from "@/lib/i18n";

let nextRowId = 1;

/** Mirrors `TransferController::store`'s form — one factory asks, an item line per part/bin/quantity. */
function TransferRequestForm({ factories, bins, spareParts, action }) {
  const t = useT("inventory");
  const tc = useT("common");
  const router = useRouter();
  const [state, dispatch, pending] = useActionState(action, null);

  const [fromFactoryId, setFromFactoryId] = useState("");
  const [toFactoryId, setToFactoryId] = useState("");
  const [notes, setNotes] = useState("");
  const [items, setItems] = useState([{ rowId: nextRowId++, spare_part_id: "", from_bin_id: "", quantity: "" }]);

  const availableBins = bins.filter((b) => !fromFactoryId || b.factory_id === fromFactoryId);
  const factoryOptions = factories.map((f) => ({ value: f.id, label: f.name }));
  const partOptions = spareParts.map((p) => ({ value: p.id, label: `${p.part_number} — ${p.name}` }));
  const binOptions = availableBins.map((b) => ({ value: b.id, label: b.full_path }));

  function updateItem(rowId, field, value) {
    setItems((prev) => prev.map((item) => (item.rowId === rowId ? { ...item, [field]: value } : item)));
  }

  function addItem() {
    setItems((prev) => [...prev, { rowId: nextRowId++, spare_part_id: "", from_bin_id: "", quantity: "" }]);
  }

  function removeItem(rowId) {
    setItems((prev) => (prev.length > 1 ? prev.filter((item) => item.rowId !== rowId) : prev));
  }

  function handleSubmit(event) {
    event.preventDefault();
    const formData = new FormData();
    formData.set("from_factory_id", fromFactoryId);
    formData.set("to_factory_id", toFactoryId);
    if (notes) formData.set("notes", notes);
    formData.set(
      "items",
      JSON.stringify(items.map(({ spare_part_id, from_bin_id, quantity }) => ({ spare_part_id, from_bin_id, quantity }))),
    );
    startTransition(() => dispatch(formData));
  }

  return (
    <form onSubmit={handleSubmit} className="flex flex-col gap-4">
      {state?.status === "error" && !state.errors ? <Alert variant="danger">{state.message}</Alert> : null}

      <Card>
        <CardHeader>
          <CardTitle>{t("route")}</CardTitle>
        </CardHeader>
        <CardBody className="grid grid-cols-1 gap-4 sm:grid-cols-2">
          <FormField label={t("from_factory")} required error={state?.errors?.from_factory_id?.[0]}>
            {(fieldProps) => (
              <Select {...fieldProps} value={fromFactoryId} onValueChange={setFromFactoryId} options={factoryOptions} placeholder={t("select_a_factory")} />
            )}
          </FormField>
          <FormField label={t("to_factory")} required error={state?.errors?.to_factory_id?.[0]}>
            {(fieldProps) => (
              <Select {...fieldProps} value={toFactoryId} onValueChange={setToFactoryId} options={factoryOptions} placeholder={t("select_a_factory")} />
            )}
          </FormField>
          <div className="sm:col-span-2">
            <FormField label={t("notes")} error={state?.errors?.notes?.[0]}>
              {(fieldProps) => <Textarea {...fieldProps} value={notes} onChange={(e) => setNotes(e.target.value)} rows={2} maxLength={2000} />}
            </FormField>
          </div>
        </CardBody>
      </Card>

      <Card>
        <CardHeader>
          <CardTitle>{t("items")}</CardTitle>
        </CardHeader>
        <CardBody className="flex flex-col gap-3">
          {items.map((item, index) => (
            <div key={item.rowId} className="grid grid-cols-1 gap-2 border-b border-border pb-3 sm:grid-cols-12 sm:items-end sm:border-0 sm:pb-0">
              <div className="sm:col-span-6">
                <FormField label={index === 0 ? t("part") : undefined}>
                  {(fieldProps) => (
                    <Select
                      {...fieldProps}
                      value={item.spare_part_id}
                      onValueChange={(value) => updateItem(item.rowId, "spare_part_id", value)}
                      options={partOptions}
                      placeholder={t("select_a_part")}
                    />
                  )}
                </FormField>
              </div>
              <div className="sm:col-span-3">
                <FormField label={index === 0 ? t("from_bin") : undefined}>
                  {(fieldProps) => (
                    <Select
                      {...fieldProps}
                      value={item.from_bin_id}
                      onValueChange={(value) => updateItem(item.rowId, "from_bin_id", value)}
                      options={binOptions}
                      placeholder={t("select_a_bin")}
                    />
                  )}
                </FormField>
              </div>
              <div className="sm:col-span-2">
                <FormField label={index === 0 ? t("quantity") : undefined}>
                  {(fieldProps) => (
                    <Input
                      {...fieldProps}
                      type="number"
                      step="0.0001"
                      min="0"
                      value={item.quantity}
                      onChange={(e) => updateItem(item.rowId, "quantity", e.target.value)}
                    />
                  )}
                </FormField>
              </div>
              <div className="sm:col-span-1">
                <Button type="button" variant="ghost" size="icon" aria-label={t("remove_item")} onClick={() => removeItem(item.rowId)} disabled={items.length === 1}>
                  <Trash2 className="text-danger" />
                </Button>
              </div>
            </div>
          ))}

          {state?.errors?.items?.[0] ? <p className="text-sm text-danger">{state.errors.items[0]}</p> : null}

          <div>
            <Button type="button" variant="outline" size="sm" onClick={addItem}>
              <Plus /> {t("add_item")}
            </Button>
          </div>
        </CardBody>
      </Card>

      <div className="flex justify-end gap-2">
        <Button type="button" variant="outline" onClick={() => router.back()}>
          {tc("cancel")}
        </Button>
        <Button type="submit" loading={pending}>
          {t("request_transfer")}
        </Button>
      </div>
    </form>
  );
}

export { TransferRequestForm };
