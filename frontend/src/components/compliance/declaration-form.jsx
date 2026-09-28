"use client";

import { useState, useTransition } from "react";
import { useRouter } from "next/navigation";
import { Plus, Trash2 } from "lucide-react";
import { Card, CardHeader, CardTitle, CardBody } from "@/components/ui/card";
import { Alert } from "@/components/ui/alert";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Select } from "@/components/ui/select";
import { Textarea } from "@/components/ui/textarea";
import { FormField } from "@/components/ui/form-field";
import { useToastManager } from "@/components/ui/toast";
import { ItemPicker } from "@/components/procurement/item-picker";
import { useT } from "@/lib/i18n";

/**
 * A utilisation declaration against one licence (Shared Systems §3.2).
 *
 * Wastage is a percentage on the line rather than one figure for the
 * document, because customs allows a different one per material — the
 * fabric's is nothing like the thread's, and one average would either
 * overstate the entitlement or understate it.
 */
function DeclarationForm({ licence, udTypes, departments, searchItems, onSubmit }) {
  const t = useT("compliance");
  const tc = useT("common");
  const router = useRouter();
  const toastManager = useToastManager();
  const [pending, startTransition] = useTransition();

  const [udNo, setUdNo] = useState("");
  const [udType, setUdType] = useState(udTypes[0] ?? "UD");
  const [issueDate, setIssueDate] = useState("");
  const [validTo, setValidTo] = useState("");
  const [buyerReference, setBuyerReference] = useState("");
  const [notes, setNotes] = useState("");
  const [lines, setLines] = useState([{ item: null, declared_quantity: "", wastage_percent: "" }]);
  const [errors, setErrors] = useState({});

  const ready = udNo && lines.some((line) => line.item && line.declared_quantity !== "");

  function setLine(index, key, value) {
    setLines((current) => current.map((line, position) => (position === index ? { ...line, [key]: value } : line)));
  }

  function submit(event) {
    event.preventDefault();
    setErrors({});

    startTransition(async () => {
      const result = await onSubmit({
        ud_no: udNo,
        ud_type: udType,
        issue_date: issueDate || undefined,
        valid_to: validTo || undefined,
        buyer_reference: buyerReference || undefined,
        notes: notes || undefined,
        lines: lines
          .filter((line) => line.item && line.declared_quantity !== "")
          .map((line) => ({
            item_id: line.item.id,
            declared_quantity: line.declared_quantity,
            wastage_percent: line.wastage_percent || undefined,
          })),
      });

      if (result?.status === "error") {
        setErrors(result.errors ?? {});
        toastManager.add({ type: "danger", title: result.message ?? tc("something_went_wrong") });
        return;
      }

      toastManager.add({ type: "success", title: t("declaration_created", { number: result.data?.ud_no ?? "" }) });
      router.push(`/compliance/declarations/${result.data.id}`);
    });
  }

  return (
    <form onSubmit={submit} className="flex flex-col gap-4">
      {/* Issuing against a lapsed or suspended licence is refused by the
          API; saying so here saves filling the whole form first. */}
      {licence.is_live ? null : <Alert variant="warning">{t("licence_expired")}</Alert>}

      <Card>
        <CardHeader>
          <CardTitle>{t("declaration")}</CardTitle>
        </CardHeader>
        <CardBody className="grid grid-cols-1 gap-4 sm:grid-cols-3">
          <FormField label={t("ud_no")} required error={errors.ud_no?.[0]}>
            {(props) => <Input {...props} value={udNo} onChange={(event) => setUdNo(event.target.value)} />}
          </FormField>

          <FormField label={t("ud_type")}>
            {(props) => (
              <Select
                {...props}
                options={udTypes.map((value) => ({ value, label: t(`ud_types.${value}`) }))}
                value={udType}
                onValueChange={setUdType}
              />
            )}
          </FormField>

          <FormField label={t("bond_licence")}>
            {() => (
              <Input value={licence.licence_no} readOnly className="bg-surface-muted" />
            )}
          </FormField>

          <FormField label={t("issued_date")} error={errors.issue_date?.[0]}>
            {(props) => (
              <Input {...props} type="date" value={issueDate} onChange={(event) => setIssueDate(event.target.value)} />
            )}
          </FormField>

          <FormField label={t("valid_to")} error={errors.valid_to?.[0]}>
            {(props) => (
              <Input {...props} type="date" value={validTo} onChange={(event) => setValidTo(event.target.value)} />
            )}
          </FormField>

          <FormField label={t("buyer_reference")}>
            {(props) => (
              <Input {...props} value={buyerReference} onChange={(event) => setBuyerReference(event.target.value)} />
            )}
          </FormField>
        </CardBody>
      </Card>

      <Card>
        <CardHeader>
          <CardTitle>{t("declared_quantity")}</CardTitle>
          <Button
            type="button"
            size="sm"
            variant="outline"
            onClick={() => setLines((current) => [...current, { item: null, declared_quantity: "", wastage_percent: "" }])}
          >
            <Plus /> {t("item")}
          </Button>
        </CardHeader>
        <CardBody className="flex flex-col gap-3">
          {errors.lines ? <p className="text-xs text-danger">{errors.lines[0]}</p> : null}

          {lines.map((line, index) => (
            <div key={index} className="grid grid-cols-1 gap-2 sm:grid-cols-12">
              <div className="sm:col-span-6">
                <ItemPicker
                  value={line.item}
                  onChange={(item) => setLine(index, "item", item)}
                  departments={departments}
                  searchItems={searchItems}
                />
              </div>
              <div className="sm:col-span-2">
                <Input
                  type="number"
                  step="any"
                  min="0"
                  placeholder={t("declared_quantity")}
                  value={line.declared_quantity}
                  onChange={(event) => setLine(index, "declared_quantity", event.target.value)}
                />
              </div>
              <div className="sm:col-span-3">
                <Input
                  type="number"
                  step="any"
                  min="0"
                  placeholder={t("wastage_percent")}
                  value={line.wastage_percent}
                  onChange={(event) => setLine(index, "wastage_percent", event.target.value)}
                />
              </div>
              <div className="flex items-center sm:col-span-1">
                <Button
                  type="button"
                  size="sm"
                  variant="outline"
                  disabled={lines.length === 1}
                  onClick={() => setLines((current) => current.filter((_, position) => position !== index))}
                >
                  <Trash2 />
                </Button>
              </div>
            </div>
          ))}

          <p className="text-xs text-foreground-muted">{t("declaration_hint")}</p>
        </CardBody>
      </Card>

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
        <Button type="submit" disabled={pending || !ready}>
          {tc("save")}
        </Button>
      </div>
    </form>
  );
}

export { DeclarationForm };
