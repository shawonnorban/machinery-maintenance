"use client";

import Link from "next/link";
import { useRouter } from "next/navigation";
import { DataTable } from "@/components/ui/data-table";
import { Badge } from "@/components/ui/badge";
import { Select } from "@/components/ui/select";
import { FormattedDateTime } from "@/components/ui/formatted-date-time";
import { formatQuantity, formatNumber } from "@/lib/format";
import { useT } from "@/lib/i18n";
import { refOf } from "@/lib/ref-path";

/**
 * What left the store and what came back, newest first — the Issue & Return
 * screen's own log under its form, across every part (a part's own ledger
 * sits on its detail page).
 *
 * @param {{ rows: object[], meta: { current_page: number, per_page: number, total: number }, type: string }} props
 */
function IssueReturnHistory({ rows, meta, type }) {
  const t = useT("inventory");
  const router = useRouter();

  function navigate(next) {
    const params = new URLSearchParams();
    const nextType = next.type ?? type;
    if (nextType) params.set("type", nextType);
    if ((next.page ?? 1) > 1) params.set("page", String(next.page));
    const query = params.toString();
    router.push(query ? `/inventory/issue?${query}` : "/inventory/issue", { scroll: false });
  }

  return (
    <div className="flex flex-col gap-3">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <h2 className="text-base font-semibold text-foreground">{t("history")}</h2>
        <div className="w-44">
          <Select
            aria-label={t("transaction_type")}
            options={[
              { value: "", label: t("issue_and_return_all") },
              { value: "ISSUE", label: t("type_issue") },
              { value: "RETURN", label: t("type_return") },
            ]}
            value={type}
            onValueChange={(value) => navigate({ type: value ?? "", page: 1 })}
          />
        </div>
      </div>
      <DataTable
        columns={[
          { key: "transaction_at", header: t("date"), render: (row) => <FormattedDateTime value={row.transaction_at} /> },
          {
            key: "transaction_type",
            header: t("transaction_type"),
            render: (row) => (
              <Badge variant={row.transaction_type === "ISSUE" ? "warning" : "success"}>{t(`type_${row.transaction_type.toLowerCase()}`)}</Badge>
            ),
          },
          {
            key: "part",
            header: t("part"),
            render: (row) =>
              row.part ? (
                <Link href={`/inventory/parts/${refOf(row.part, "part_number")}`} className="font-medium text-brand hover:underline">
                  {row.part.part_number}
                  <div className="text-xs font-normal text-foreground-muted">{row.part.name}</div>
                </Link>
              ) : (
                "—"
              ),
          },
          { key: "bin", header: t("bin"), render: (row) => row.bin ?? "—" },
          { key: "quantity", header: t("quantity"), align: "right", render: (row) => formatQuantity(row.quantity, row.part?.unit) },
          { key: "balance_after", header: t("balance_after"), align: "right", render: (row) => formatNumber(row.balance_after) },
          { key: "work_order", header: t("work_order"), render: (row) => row.work_order ?? "—" },
          { key: "performed_by", header: t("performed_by"), render: (row) => row.performed_by ?? "—" },
          { key: "notes", header: t("notes"), render: (row) => <span className="line-clamp-2 max-w-64">{row.notes || "—"}</span> },
        ]}
        rows={rows}
        rowKey={(row) => row.id}
        emptyTitle={t("no_issue_return_yet")}
        pagination={{ page: meta.current_page, perPage: meta.per_page, total: meta.total }}
        perPageSelect
        onPageChange={(page) => navigate({ page })}
      />
    </div>
  );
}

export { IssueReturnHistory };
