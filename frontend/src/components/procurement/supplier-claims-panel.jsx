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

const STATUSES = ["RAISED", "ACCEPTED", "PARTIALLY_SETTLED", "SETTLED", "REJECTED", "WITHDRAWN"];
const REASONS = ["QUALITY", "SHORT_QUANTITY", "DAMAGE", "LATE_DELIVERY", "OTHER"];

/**
 * Money claimed from a supplier (Procurement spec §12).
 *
 * A claim is what is left when a return is impossible — the cloth has been
 * cut, so nothing can go back and the loss is settled in money. That is
 * why this list is about amounts and what is still outstanding rather than
 * about quantities, and why no stock movement appears anywhere on it.
 *
 * Settling happens here rather than on a detail page: an instalment is one
 * number and a reference, and making somebody open a document to type two
 * fields is how a part-payment goes unrecorded.
 */
function SupplierClaimsPanel({ rows, meta, page, search, status, factories, vendors, canManage, actions }) {
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
    router.push(`/procurement/claims?${params.toString()}`);
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
              placeholder={t("claim_search_placeholder")}
              className="pl-9"
            />
          </div>
          <div className="w-full sm:max-w-[220px]">
            <Select
              options={[
                { value: "", label: t("all_statuses") },
                ...STATUSES.map((value) => ({ value, label: t(`claim_statuses.${value}`) })),
              ]}
              value={status}
              onValueChange={(value) => navigate({ status: value, page: 1 })}
            />
          </div>
          {canManage ? (
            <Button size="sm" onClick={() => setDialog({ kind: "create" })}>
              <Plus /> {t("new_claim")}
            </Button>
          ) : null}
        </CardBody>
      </Card>

      <DataTable
        columns={[
          { key: "claim_no", header: t("claim_no"), render: (row) => <span className="font-medium">{row.claim_no}</span> },
          { key: "claim_date", header: t("claim_date"), render: (row) => <FormattedDateTime value={row.claim_date} mode="date" /> },
          { key: "vendor", header: t("vendor"), render: (row) => row.vendor?.name ?? "—" },
          { key: "reason", header: t("reason"), render: (row) => t(`claim_reasons.${row.reason}`) },
          {
            key: "claim_amount",
            header: t("claim_amount"),
            align: "right",
            render: (row) => `${formatNumber(row.claim_amount, { maximumFractionDigits: 2 })} ${row.currency}`,
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
          { key: "status", header: t("status"), render: (row) => <StatusBadge status={row.status} label={t(`claim_statuses.${row.status}`)} /> },
        ]}
        rows={rows}
        rowKey={(row) => row.id}
        emptyTitle={t("no_claims")}
        emptyDescription={t("claim_hint")}
        rowActions={
          canManage
            ? (row) =>
                [
                  row.can_accept && {
                    label: t("accept_claim"),
                    onSelect: () => setDialog({ kind: "accept", claim: row }),
                  },
                  row.can_settle && {
                    label: t("settle_claim"),
                    onSelect: () => setDialog({ kind: "settle", claim: row }),
                  },
                  row.can_reject && {
                    label: tc("reject"),
                    onSelect: () => setDialog({ kind: "reject", claim: row }),
                    destructive: true,
                  },
                  row.can_withdraw && {
                    label: t("withdraw_claim"),
                    onSelect: () => run(() => actions.withdraw(row.id), t("claim_withdrawn", { number: row.claim_no })),
                  },
                ].filter(Boolean)
            : undefined
        }
        pagination={{ page: meta.current_page, perPage: meta.per_page, total: meta.total }}
        onPageChange={(nextPage) => navigate({ page: nextPage })}
      />

      {dialog?.kind === "create" ? (
        <FormDialog
          title={t("new_claim")}
          description={t("claim_hint")}
          initial={{ factory_id: factories[0]?.id ?? "", reason: "QUALITY" }}
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
            {
              name: "reason",
              label: t("reason"),
              type: "select",
              options: REASONS.map((value) => ({ value, label: t(`claim_reasons.${value}`) })),
            },
            { name: "claim_amount", label: t("claim_amount"), type: "number", required: true },
            { name: "claim_date", label: t("claim_date"), type: "date" },
            { name: "description", label: t("description"), type: "textarea" },
          ]}
          onClose={() => setDialog(null)}
          onSubmit={(values) => run(() => actions.create(values), t("claim_created", { number: "" }))}
          submitLabel={tc("save")}
        />
      ) : null}

      {dialog?.kind === "accept" ? (
        <FormDialog
          title={t("accept_claim")}
          description={dialog.claim.claim_no}
          fields={[{ name: "reference", label: t("settlement_reference") }]}
          onClose={() => setDialog(null)}
          onSubmit={(values) =>
            run(
              () => actions.accept(dialog.claim.id, values.reference),
              t("claim_accepted", { number: dialog.claim.claim_no }),
            )
          }
          submitLabel={tc("confirm")}
        />
      ) : null}

      {dialog?.kind === "settle" ? (
        <FormDialog
          title={t("settle_claim")}
          description={t("outstanding_amount", {
            amount: formatNumber(dialog.claim.outstanding, { maximumFractionDigits: 2 }),
          })}
          fields={[
            { name: "amount", label: t("amount"), type: "number", required: true },
            { name: "reference", label: t("settlement_reference") },
          ]}
          onClose={() => setDialog(null)}
          onSubmit={(values) =>
            run(
              () => actions.settle(dialog.claim.id, values),
              t("claim_settled", { number: dialog.claim.claim_no, amount: values.amount }),
            )
          }
          submitLabel={tc("save")}
        />
      ) : null}

      {dialog?.kind === "reject" ? (
        <FormDialog
          title={tc("reject")}
          description={dialog.claim.claim_no}
          fields={[{ name: "reason", label: t("reject_reason"), type: "textarea", required: true }]}
          onClose={() => setDialog(null)}
          onSubmit={(values) =>
            run(
              () => actions.reject(dialog.claim.id, values.reason),
              t("claim_rejected", { number: dialog.claim.claim_no }),
            )
          }
          submitLabel={tc("confirm")}
        />
      ) : null}
    </div>
  );
}

export { SupplierClaimsPanel };
