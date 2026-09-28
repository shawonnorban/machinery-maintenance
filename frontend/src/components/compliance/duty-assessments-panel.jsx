"use client";

import { useState, useTransition } from "react";
import { useRouter } from "next/navigation";
import { Plus, Search } from "lucide-react";
import { DataTable } from "@/components/ui/data-table";
import { Card, CardBody } from "@/components/ui/card";
import { Alert } from "@/components/ui/alert";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Select } from "@/components/ui/select";
import { Modal } from "@/components/ui/modal";
import { Textarea } from "@/components/ui/textarea";
import { FormField } from "@/components/ui/form-field";
import { FormattedDateTime } from "@/components/ui/formatted-date-time";
import { useToastManager } from "@/components/ui/toast";
import { ItemPicker } from "@/components/procurement/item-picker";
import { formatCurrency, formatNumber } from "@/lib/format";
import { useT } from "@/lib/i18n";

/**
 * Duty raised on bonded stock that left for something other than export
 * (Shared Systems §3.4).
 *
 * There is no automatic path onto this screen, and that is the point:
 * issuing bonded fabric to the cutting floor is the bond doing its job,
 * so only a person can say that a particular movement was the other kind.
 */
function DutyAssessmentsPanel({ rows, meta, page, search, reason, options, licences, departments, searchItems, canAssess, onAssess }) {
  const t = useT("compliance");
  const tc = useT("common");
  const router = useRouter();
  const [searchInput, setSearchInput] = useState(search);
  const [dialogOpen, setDialogOpen] = useState(false);

  function navigate(next) {
    const params = new URLSearchParams();
    const merged = { page: next.page ?? page, q: next.q ?? searchInput, reason: next.reason ?? reason };
    for (const [key, value] of Object.entries(merged)) {
      if (value) params.set(key, String(value));
    }
    router.push(`/compliance/duty-assessments?${params.toString()}`);
  }

  return (
    <div className="flex flex-col gap-4">
      <Alert variant="info">{t("duty_hint")}</Alert>

      <Card>
        <CardBody className="flex flex-wrap items-center gap-3 py-3">
          <div className="relative w-full min-w-[200px] flex-1">
            <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-foreground-subtle" />
            <Input
              value={searchInput}
              onChange={(event) => setSearchInput(event.target.value)}
              onKeyDown={(event) => (event.key === "Enter" ? navigate({ page: 1 }) : null)}
              placeholder={t("assessment_search_placeholder")}
              className="pl-9"
            />
          </div>
          <div className="w-full sm:max-w-[220px]">
            <Select
              options={[
                { value: "", label: tc("all") },
                ...(options.duty_reasons ?? []).map((value) => ({ value, label: t(`duty_reasons.${value}`) })),
              ]}
              value={reason}
              onValueChange={(value) => navigate({ reason: value, page: 1 })}
            />
          </div>
          {canAssess ? (
            <Button size="sm" onClick={() => setDialogOpen(true)}>
              <Plus /> {t("new_assessment")}
            </Button>
          ) : null}
        </CardBody>
      </Card>

      <DataTable
        columns={[
          {
            key: "assessment_no",
            header: t("assessment_no"),
            render: (row) => (
              <div className="flex flex-col">
                <span className="font-medium">{row.assessment_no}</span>
                <span className="text-xs text-foreground-muted">
                  <FormattedDateTime value={row.assessment_date} mode="date" />
                </span>
              </div>
            ),
          },
          {
            key: "item",
            header: t("item"),
            render: (row) => (
              <div className="flex flex-col">
                <span>{row.item_name}</span>
                <span className="font-mono text-xs text-foreground-muted">{row.item_code}</span>
              </div>
            ),
          },
          { key: "reason", header: t("reason"), render: (row) => t(`duty_reasons.${row.reason}`) },
          { key: "quantity", header: t("quantity"), align: "right", render: (row) => formatNumber(row.quantity) },
          {
            key: "assessable_value",
            header: t("assessable_value"),
            align: "right",
            render: (row) => formatCurrency(row.assessable_value, row.currency),
          },
          {
            key: "hs_code",
            header: t("hs_code"),
            render: (row) =>
              row.is_rated ? (
                <span className="font-mono text-xs">{row.hs_code}</span>
              ) : (
                // Written at zero rather than dropped — see `unrated_hint`.
                <Badge variant="warning">{t("unrated")}</Badge>
              ),
          },
          {
            key: "total_payable",
            header: t("total_payable"),
            align: "right",
            render: (row) => (
              <span className={row.is_rated ? "font-medium tabular-nums" : "tabular-nums text-foreground-muted"}>
                {formatCurrency(row.total_payable, row.currency)}
              </span>
            ),
          },
          { key: "store", header: t("bonded_store"), render: (row) => row.store?.name ?? "—" },
        ]}
        rows={rows}
        rowKey={(row) => row.id}
        emptyTitle={t("no_assessments")}
        emptyDescription={t("duty_hint")}
        pagination={{ page: meta.current_page, perPage: meta.per_page, total: meta.total }}
        onPageChange={(nextPage) => navigate({ page: nextPage })}
      />

      {dialogOpen ? (
        <AssessDialog
          options={options}
          licences={licences}
          departments={departments}
          searchItems={searchItems}
          onClose={() => setDialogOpen(false)}
          onAssess={onAssess}
        />
      ) : null}
    </div>
  );
}

/**
 * Raising one assessment. The store list holds bonded stores only, since
 * nothing leaving an ordinary store owes duty in the first place.
 */
function AssessDialog({ options, licences, departments, searchItems, onClose, onAssess }) {
  const t = useT("compliance");
  const tc = useT("common");
  const toastManager = useToastManager();
  const [pending, startTransition] = useTransition();

  const factories = options.factories ?? [];
  const stores = options.bonded_stores ?? [];
  const reasons = options.duty_reasons ?? [];

  const [factoryId, setFactoryId] = useState(factories[0]?.id ?? "");
  const [item, setItem] = useState(null);
  const [reason, setReason] = useState(reasons[0] ?? "LOCAL_SALE");
  const [quantity, setQuantity] = useState("");
  const [unitValue, setUnitValue] = useState("");
  const [storeId, setStoreId] = useState("");
  const [licenceId, setLicenceId] = useState("");
  const [assessmentDate, setAssessmentDate] = useState("");
  const [notes, setNotes] = useState("");
  const [errors, setErrors] = useState({});

  // The store select narrows with the factory, so a Dhaka assessment
  // cannot be pinned to a Chattogram bond.
  const factoryStores = stores.filter((store) => !factoryId || store.factory_id === factoryId);

  const ready = factoryId && item && quantity !== "";

  function submit(event) {
    event.preventDefault();
    setErrors({});

    startTransition(async () => {
      const result = await onAssess({
        factory_id: factoryId,
        item_id: item.id,
        reason,
        quantity,
        store_id: storeId || undefined,
        unit_value: unitValue || undefined,
        bond_licence_id: licenceId || undefined,
        assessment_date: assessmentDate || undefined,
        notes: notes || undefined,
      });

      if (result?.status === "error") {
        setErrors(result.errors ?? {});
        toastManager.add({ type: "danger", title: result.message ?? tc("something_went_wrong") });
        return;
      }

      toastManager.add({
        type: "success",
        title: t("assessment_created", {
          number: result.data?.assessment_no ?? "",
          amount: formatCurrency(result.data?.total_payable, result.data?.currency),
        }),
      });
      onClose();
    });
  }

  return (
    <Modal
      open
      onOpenChange={(open) => (open ? null : onClose())}
      title={t("new_assessment")}
      description={t("duty_hint")}
      footer={
        <>
          <Button type="button" variant="outline" onClick={onClose} disabled={pending}>
            {tc("cancel")}
          </Button>
          <Button type="submit" form="assess-duty" disabled={pending || !ready}>
            {tc("save")}
          </Button>
        </>
      }
    >
      <form id="assess-duty" onSubmit={submit} className="grid grid-cols-1 gap-3 sm:grid-cols-2">
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

        <FormField label={t("reason")} required error={errors.reason?.[0]}>
          {(props) => (
            <Select
              {...props}
              options={reasons.map((value) => ({ value, label: t(`duty_reasons.${value}`) }))}
              value={reason}
              onValueChange={setReason}
            />
          )}
        </FormField>

        <FormField label={t("item")} required className="sm:col-span-2" error={errors.item_id?.[0]}>
          {() => <ItemPicker value={item} onChange={setItem} departments={departments} searchItems={searchItems} />}
        </FormField>

        <FormField label={t("quantity")} required error={errors.quantity?.[0]}>
          {(props) => (
            <Input
              {...props}
              type="number"
              step="any"
              min="0"
              value={quantity}
              onChange={(event) => setQuantity(event.target.value)}
            />
          )}
        </FormField>

        <FormField
          label={t("unit_value")}
          helperText={t("unrated_hint")}
          error={errors.unit_value?.[0]}
        >
          {(props) => (
            <Input
              {...props}
              type="number"
              step="any"
              min="0"
              value={unitValue}
              onChange={(event) => setUnitValue(event.target.value)}
            />
          )}
        </FormField>

        <FormField label={t("bonded_store")} error={errors.store_id?.[0]}>
          {(props) => (
            <Select
              {...props}
              options={[
                { value: "", label: "—" },
                ...factoryStores.map((store) => ({ value: store.id, label: `${store.name} (${store.code})` })),
              ]}
              value={storeId}
              onValueChange={setStoreId}
            />
          )}
        </FormField>

        <FormField label={t("bond_licence")} error={errors.bond_licence_id?.[0]}>
          {(props) => (
            <Select
              {...props}
              options={[
                { value: "", label: "—" },
                ...licences.map((licence) => ({ value: licence.id, label: licence.licence_no })),
              ]}
              value={licenceId}
              onValueChange={setLicenceId}
            />
          )}
        </FormField>

        <FormField label={t("assessment_date")} error={errors.assessment_date?.[0]}>
          {(props) => (
            <Input
              {...props}
              type="date"
              value={assessmentDate}
              onChange={(event) => setAssessmentDate(event.target.value)}
            />
          )}
        </FormField>

        <FormField label={t("notes")} className="sm:col-span-2">
          {(props) => <Textarea {...props} rows={3} value={notes} onChange={(event) => setNotes(event.target.value)} />}
        </FormField>
      </form>
    </Modal>
  );
}

export { DutyAssessmentsPanel };
