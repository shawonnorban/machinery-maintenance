"use client";

import { useRouter } from "next/navigation";
import Link from "next/link";
import { SearchingData } from "@/components/erp/searching-data";
import { DataTable } from "@/components/ui/data-table";
import { StatusBadge } from "@/components/ui/status-badge";
import { useT } from "@/lib/i18n";
import { refOf } from "@/lib/ref-path";
import { FormattedDateTime } from "@/components/ui/formatted-date-time";

/** Mirrors `contracts/index.blade.php`. */
function ServiceContractsTable({ contracts, meta, page, expiring, addHref }) {
  const t = useT("vendor");
  const tc = useT("common");
  const router = useRouter();

  const scopeOptions = [
    { value: "1", label: t("expiring_soon") },
    { value: "", label: t("all_contracts") },
  ];

  function navigate(next) {
    const params = new URLSearchParams({ page: String(next.page ?? page) });
    const expiringValue = next.expiring ?? expiring;
    if (expiringValue) params.set("expiring", expiringValue);
    router.push(`/vendors/service-contracts?${params.toString()}`);
  }

  return (
    <div className="flex flex-col gap-4">
      <SearchingData
        fields={[{ name: "expiring", label: t("show"), type: "select", options: scopeOptions }]}
        values={{ expiring: expiring ? "1" : "" }}
        onSearch={(values) => navigate({ ...values, page: 1 })}
        addHref={addHref}
      />

      <DataTable
        columns={[
          {
            key: "contract_number",
            header: t("contract"),
            render: (row) => (
              <Link href={`/vendors/service-contracts/${refOf(row, "contract_number")}`} className="font-medium text-brand hover:underline">
                {row.contract_number}
                <div className="text-xs font-normal text-foreground-muted">{t(`contract_type_${row.contract_type?.toLowerCase()}`)}</div>
              </Link>
            ),
          },
          { key: "vendor", header: t("vendor"), render: (row) => row.vendor?.name ?? "—" },
          {
            key: "scope",
            header: t("scope"),
            render: (row) => row.asset?.asset_code ?? row.factory?.name ?? t("several_machines"),
          },
          { key: "end_date", header: t("end_date"), render: (row) => <FormattedDateTime value={row.end_date} mode="date" /> },
          { key: "value", header: t("value"), align: "right", render: (row) => (row.value === null ? "—" : row.value) },
          {
            key: "status",
            header: t("status"),
            render: (row) => <StatusBadge status={row.status} label={t(`contract_status_${row.status?.toLowerCase()}`)} />,
          },
        ]}
        rows={contracts}
        rowKey={(row) => row.id}
        emptyTitle={expiring ? t("nothing_expiring") : t("no_contracts_found")}
        rowActions={(row) => [{ icon: "view", label: tc("view"), onSelect: () => router.push(`/vendors/service-contracts/${refOf(row, "contract_number")}`) }]}
        pagination={{ page: meta.current_page, perPage: meta.per_page, total: meta.total }}
        perPageSelect
        onPageChange={(nextPage) => navigate({ page: nextPage })}
      />
    </div>
  );
}

export { ServiceContractsTable };
