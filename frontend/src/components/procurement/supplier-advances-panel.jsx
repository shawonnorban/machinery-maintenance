"use client";

import { useState } from "react";
import { useRouter } from "next/navigation";
import { Plus, Search } from "lucide-react";
import { DataTable } from "@/components/ui/data-table";
import { Card, CardBody } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Select } from "@/components/ui/select";
import { StatusBadge } from "@/components/ui/status-badge";
import { FormattedDateTime } from "@/components/ui/formatted-date-time";
import { useToastManager } from "@/components/ui/toast";
import { FormDialog } from "@/components/procurement/form-dialog";
import { formatNumber } from "@/lib/format";
import { useT } from "@/lib/i18n";

const STATUSES = ["OPEN", "ADJUSTED", "REFUNDED", "CLOSED", "CANCELLED"];

/**
 * Money paid to a supplier before the goods arrive (Procurement spec §13).
 *
 * The number that matters is what is still unaccounted for, so it has a
 * column of its own and is coloured while it is above zero. An advance
 * nobody has set against a bill is money the factory has lent its mill,
 * and it is easy for that to sit unnoticed for a season.
 */
function SupplierAdvancesPanel({ rows, meta, page, search, status, factories, vendors, canManage, actions }) {
  const t = useT("procurement");
  const tc = useT("common");
  const router = useRouter();
  const toastManager = useToastManager();
  const [dialog, setDialog] = useState(null);
  const [searchInput, setSearchInput] = useState(search);

  function navigate(next) {
    const params = new URLSearchParams();
    const merged = { page: next.page ?? page, q: next.q ?? searchInput, status: next.status ?? status };
    for (const [key, value] of Object.entries(merged)) {
      if (value) params.set(key, String(value));
    }
    router.push(`/procurement/advances?${params.toString()}`);
  }

  async function run(fn, successTitle) {
    const result = await fn();

    if (result?.status === "error") {
      toastManager.add({ type: "danger", title: result.message ?? tc("something_went_wrong") });
      return false;
    }

    toastManager.add({ type: "success", title: successTitle });
    setDialog(null);
    return true;
  }

  return (
    <div className="flex flex-col gap-4">
      <Card>
        <CardBody className="flex flex-wrap items-center gap-3 py-3">
          <div className="relative w-full flex-1 min-w-[200px]">
            <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-foreground-subtle" />
            <Input
              value={searchInput}
              onChange={(event) => setSearchInput(event.target.value)}
              onKeyDown={(event) => (event.key === "Enter" ? navigate({ page: 1 }) : null)}
              placeholder={t("advance_search_placeholder")}
              className="pl-9"
            />
          </div>
          <div className="w-full sm:max-w-[220px]">
            <Select
              options={[
                { value: "", label: t("all_statuses") },
                ...STATUSES.map((value) => ({ value, label: t(`advance_statuses.${value}`) })),
              ]}
              value={status}
              onValueChange={(value) => navigate({ status: value, page: 1 })}
            />
          </div>
          {canManage ? (
            <Button size="sm" onClick={() => setDialog({ kind: "create" })}>
              <Plus /> {t("new_advance")}
            </Button>
          ) : null}
        </CardBody>
      </Card>

      <DataTable
        columns={[
          { key: "advance_no", header: t("advance_no"), render: (row) => <span className="font-medium">{row.advance_no}</span> },
          { key: "advance_date", header: t("advance_date"), render: (row) => <FormattedDateTime value={row.advance_date} mode="date" /> },
          { key: "vendor", header: t("vendor"), render: (row) => row.vendor?.name ?? "—" },
          { key: "purchase_order", header: t("purchase_order"), render: (row) => row.purchase_order?.po_no ?? "—" },
          {
            key: "amount",
            header: t("advance_amount"),
            align: "right",
            render: (row) => `${formatNumber(row.amount, { maximumFractionDigits: 2 })} ${row.currency}`,
          },
          {
            key: "adjusted_amount",
            header: t("adjusted"),
            align: "right",
            render: (row) => formatNumber(row.adjusted_amount, { maximumFractionDigits: 2 }),
          },
          {
            key: "outstanding",
            header: t("outstanding"),
            align: "right",
            render: (row) => (
              <span className={Number(row.outstanding) > 0 ? "text-warning" : "text-foreground-muted"}>
                {formatNumber(row.outstanding, { maximumFractionDigits: 2 })}
              </span>
            ),
          },
          { key: "status", header: t("status"), render: (row) => <StatusBadge status={row.status} label={t(`advance_statuses.${row.status}`)} /> },
        ]}
        rows={rows}
        rowKey={(row) => row.id}
        emptyTitle={t("no_advances")}
        emptyDescription={t("advance_hint")}
        rowActions={
          canManage
            ? (row) =>
                [
                  row.can_adjust && {
                    label: t("adjust_advance"),
                    onSelect: () => setDialog({ kind: "adjust", advance: row }),
                  },
                  row.can_adjust && {
                    label: t("refund_advance"),
                    onSelect: () => setDialog({ kind: "refund", advance: row }),
                  },
                  row.can_cancel && {
                    label: tc("cancel"),
                    onSelect: () => run(() => actions.cancel(row.id), t("advance_cancelled", { number: row.advance_no })),
                    destructive: true,
                  },
                ].filter(Boolean)
            : undefined
        }
        pagination={{ page: meta.current_page, perPage: meta.per_page, total: meta.total }}
        onPageChange={(nextPage) => navigate({ page: nextPage })}
      />

      {dialog?.kind === "create" ? (
        <FormDialog
          title={t("new_advance")}
          description={t("advance_hint")}
          initial={{ factory_id: factories[0]?.id ?? "" }}
          fields={[
            {
              name: "factory_id",
              label: t("factory"),
              type: "select",
              required: true,
              options: factories.map((factory) => ({ value: factory.id, label: factory.name })),
            },
            {
              name: "vendor_id",
              label: t("vendor"),
              type: "select",
              required: true,
              options: [{ value: "", label: "—" }, ...vendors.map((vendor) => ({ value: vendor.id, label: vendor.name }))],
            },
            { name: "amount", label: t("advance_amount"), type: "number", required: true },
            { name: "advance_date", label: t("advance_date"), type: "date" },
            { name: "reference_no", label: t("reference_no") },
            { name: "notes", label: t("notes"), type: "textarea" },
          ]}
          onClose={() => setDialog(null)}
          onSubmit={(values) => run(() => actions.create(values), t("advance_created", { number: "" }))}
          submitLabel={tc("save")}
        />
      ) : null}

      {dialog?.kind === "adjust" || dialog?.kind === "refund" ? (
        <FormDialog
          title={dialog.kind === "adjust" ? t("adjust_advance") : t("refund_advance")}
          description={t("outstanding_amount", {
            amount: formatNumber(dialog.advance.outstanding, { maximumFractionDigits: 2 }),
          })}
          fields={[
            { name: "amount", label: t("amount"), type: "number", required: true },
            { name: "reference_no", label: t("reference_no") },
            { name: "notes", label: t("notes"), type: "textarea" },
          ]}
          onClose={() => setDialog(null)}
          onSubmit={(values) =>
            run(
              () =>
                dialog.kind === "adjust"
                  ? actions.adjust(dialog.advance.id, values)
                  : actions.refund(dialog.advance.id, values),
              t("advance_adjusted", { amount: values.amount, number: dialog.advance.advance_no }),
            )
          }
          submitLabel={tc("save")}
        />
      ) : null}
    </div>
  );
}

export { SupplierAdvancesPanel };
