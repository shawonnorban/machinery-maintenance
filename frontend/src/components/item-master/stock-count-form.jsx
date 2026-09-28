"use client";

import { useState, useTransition } from "react";
import { useRouter } from "next/navigation";
import { Card, CardHeader, CardTitle, CardBody } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Select } from "@/components/ui/select";
import { Switch } from "@/components/ui/switch";
import { Textarea } from "@/components/ui/textarea";
import { Alert } from "@/components/ui/alert";
import { FormField } from "@/components/ui/form-field";
import { useToastManager } from "@/components/ui/toast";
import { useT } from "@/lib/i18n";

/**
 * Opening a count (Inventory spec §12).
 *
 * There is no draft step: pressing the button generates the sheet and
 * freezes the system quantities, and the form says so. A gap between
 * setting a count up and actually walking the aisles is exactly the gap
 * that turns ordinary movements into invented variances, so the form does
 * not offer one.
 */
function StockCountForm({ factories, stores, departments, categories, onSubmit }) {
  const t = useT("itemmaster");
  const tc = useT("common");
  const router = useRouter();
  const toastManager = useToastManager();
  const [pending, startTransition] = useTransition();

  const [factoryId, setFactoryId] = useState(factories[0]?.id ?? "");
  const [storeId, setStoreId] = useState("");
  const [countType, setCountType] = useState("CYCLE");
  const [isBlind, setIsBlind] = useState(true);
  const [countDate, setCountDate] = useState("");
  const [departmentId, setDepartmentId] = useState("");
  const [categoryId, setCategoryId] = useState("");
  const [notes, setNotes] = useState("");
  const [errors, setErrors] = useState({});

  const available = stores.filter((store) => !factoryId || store.factory_id === factoryId);
  const scoped = countType === "CYCLE";

  function submit(event) {
    event.preventDefault();
    setErrors({});

    startTransition(async () => {
      const result = await onSubmit({
        factory_id: factoryId,
        store_id: storeId,
        count_type: countType,
        is_blind: isBlind,
        count_date: countDate || undefined,
        item_department_id: scoped && departmentId ? departmentId : undefined,
        item_category_id: scoped && categoryId ? categoryId : undefined,
        notes: notes || undefined,
      });

      if (result?.status === "error") {
        setErrors(result.errors ?? {});
        toastManager.add({ type: "danger", title: result.message ?? tc("something_went_wrong") });
        return;
      }

      toastManager.add({ type: "success", title: t("count_created", { number: result.data?.count_no ?? "" }) });
      router.push(`/item-master/stock-counts/${result.data.id}`);
    });
  }

  return (
    <form onSubmit={submit} className="flex flex-col gap-4">
      <Alert variant="info">{t("count_hint")}</Alert>

      <Card>
        <CardHeader>
          <CardTitle>{t("stock_count")}</CardTitle>
        </CardHeader>
        <CardBody className="grid grid-cols-1 gap-4 sm:grid-cols-2">
          <FormField label={t("factory")} required error={errors.factory_id?.[0]}>
            {(props) => (
              <Select
                {...props}
                options={factories.map((factory) => ({ value: factory.id, label: factory.name }))}
                value={factoryId}
                onValueChange={(value) => {
                  setFactoryId(value);
                  setStoreId("");
                }}
              />
            )}
          </FormField>

          <FormField label={t("store")} required error={errors.store_id?.[0]}>
            {(props) => (
              <Select
                {...props}
                options={[
                  { value: "", label: "—" },
                  ...available.map((store) => ({ value: store.id, label: `${store.code} · ${store.name}` })),
                ]}
                value={storeId}
                onValueChange={setStoreId}
              />
            )}
          </FormField>

          <FormField label={t("count_type")} required error={errors.count_type?.[0]}>
            {(props) => (
              <Select
                {...props}
                options={["CYCLE", "FULL"].map((value) => ({ value, label: t(`count_types.${value}`) }))}
                value={countType}
                onValueChange={setCountType}
              />
            )}
          </FormField>

          <FormField label={t("count_date")} error={errors.count_date?.[0]}>
            {(props) => (
              <Input {...props} type="date" value={countDate} onChange={(event) => setCountDate(event.target.value)} />
            )}
          </FormField>

          <label className="flex items-start gap-3 sm:col-span-2">
            <Switch checked={isBlind} onCheckedChange={setIsBlind} />
            <span className="flex flex-col">
              <span className="text-sm font-medium">{t("blind_count")}</span>
              <span className="text-xs text-foreground-muted">{t("blind_count_hint")}</span>
            </span>
          </label>
        </CardBody>
      </Card>

      {scoped ? (
        <Card>
          <CardHeader>
            <CardTitle>{t("count_scope")}</CardTitle>
          </CardHeader>
          <CardBody className="grid grid-cols-1 gap-4 sm:grid-cols-2">
            {/* A cycle count without a scope is a full stock take under
                the wrong name, and the API says so too. */}
            <FormField label={t("item_department")} error={errors.count_type?.[0]}>
              {(props) => (
                <Select
                  {...props}
                  options={[
                    { value: "", label: "—" },
                    ...departments.map((row) => ({ value: row.id, label: row.name })),
                  ]}
                  value={departmentId}
                  onValueChange={setDepartmentId}
                />
              )}
            </FormField>

            <FormField label={t("category")}>
              {(props) => (
                <Select
                  {...props}
                  options={[
                    { value: "", label: "—" },
                    ...categories.map((row) => ({ value: row.id, label: row.name })),
                  ]}
                  value={categoryId}
                  onValueChange={setCategoryId}
                />
              )}
            </FormField>
          </CardBody>
        </Card>
      ) : null}

      <Card>
        <CardBody>
          <FormField label={t("notes")}>
            {(props) => <Textarea {...props} rows={3} value={notes} onChange={(event) => setNotes(event.target.value)} />}
          </FormField>
        </CardBody>
      </Card>

      <div className="flex justify-end gap-2">
        <Button type="button" variant="outline" onClick={() => router.back()} disabled={pending}>
          {tc("cancel")}
        </Button>
        <Button type="submit" disabled={pending || !storeId || (scoped && !departmentId && !categoryId)}>
          {t("new_count")}
        </Button>
      </div>
    </form>
  );
}

export { StockCountForm };
