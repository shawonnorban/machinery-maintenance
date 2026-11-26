"use client";

import { useState, useTransition } from "react";
import { useRouter } from "next/navigation";
import Link from "next/link";
import { SearchingData } from "@/components/erp/searching-data";
import { DataTable } from "@/components/ui/data-table";
import { StatusBadge } from "@/components/ui/status-badge";
import { ConfirmDialog } from "@/components/ui/confirm-dialog";
import { useToastManager } from "@/components/ui/toast";
import { useT } from "@/lib/i18n";
import { refOf } from "@/lib/ref-path";

function VendorsTable({ vendors, meta, page, search, status, archiveVendor, addHref }) {
  const t = useT("vendor");
  const tc = useT("common");
  const router = useRouter();

  const statusOptions = [
    { value: "", label: t("all_statuses") },
    { value: "ACTIVE", label: t("status_active") },
    { value: "INACTIVE", label: t("status_inactive") },
    { value: "BLACKLISTED", label: t("status_blacklisted") },
  ];
  const [archiving, setArchiving] = useState(null);
  const [pending, startTransition] = useTransition();
  const toastManager = useToastManager();


  function navigate(next) {
    const params = new URLSearchParams({
      page: String(next.page ?? page),
      q: next.q ?? search,
      status: next.status ?? status,
    });
    for (const [key, value] of [...params.entries()]) {
      if (!value) params.delete(key);
    }
    router.push(`/vendors?${params.toString()}`);
  }

  function runArchive() {
    startTransition(async () => {
      const result = await archiveVendor(archiving.id);
      if (result?.status === "success") {
        toastManager.add({ title: t("archived_toast"), type: "success" });
        setArchiving(null);
        router.refresh();
      } else if (result?.status === "error") {
        toastManager.add({ title: result.message, type: "danger" });
      }
    });
  }

  return (
    <div className="flex flex-col gap-4">
      <SearchingData
        fields={[
          { name: "search", label: t("vendor"), placeholder: t("search") },
          { name: "status", label: t("status"), type: "select", options: statusOptions },
        ]}
        values={{ search, status }}
        onSearch={(values) => navigate({ ...values, page: 1 })}
        addHref={addHref}
      />

      <DataTable
        columns={[
          {
            key: "name",
            header: t("vendor"),
            render: (v) => (
              <div>
                <Link href={`/vendors/${refOf(v, "code")}`} className="font-medium text-brand hover:underline">
                  {v.name}
                </Link>
                <div className="text-xs text-foreground-muted">{v.code}</div>
              </div>
            ),
          },
          { key: "vendor_type", header: t("type"), render: (v) => t(`type_${v.vendor_type?.toLowerCase()}`) },
          { key: "country_of_origin", header: t("country_of_origin"), render: (v) => v.country_of_origin ?? "—" },
          { key: "contact_name", header: t("contact"), render: (v) => v.contact_name ?? "—" },
          { key: "warranties_count", header: t("warranties"), align: "right", render: (v) => v.warranties_count ?? 0 },
          { key: "contracts_count", header: t("contracts"), align: "right", render: (v) => v.contracts_count ?? 0 },
          {
            key: "status",
            header: t("status"),
            render: (v) => <StatusBadge status={v.status} label={t(`status_${v.status?.toLowerCase()}`)} />,
          },
        ]}
        rows={vendors}
        rowKey={(v) => v.id}
        emptyTitle={t("no_vendors_found")}
        rowActions={(v) => [
          { icon: "view", label: tc("view"), onSelect: () => router.push(`/vendors/${refOf(v, "code")}`) },
          { icon: "edit", label: tc("edit"), onSelect: () => router.push(`/vendors/${refOf(v, "code")}/edit`) },
          { label: t("archive"), destructive: true, onSelect: () => setArchiving(v) },
        ]}
        pagination={{ page: meta.current_page, perPage: meta.per_page, total: meta.total }}
        perPageSelect
        onPageChange={(nextPage) => navigate({ page: nextPage })}
      />

      <ConfirmDialog
        open={Boolean(archiving)}
        onOpenChange={() => setArchiving(null)}
        title={t("archive_confirm_title", { name: archiving?.name })}
        description={t("archive_confirm")}
        confirmLabel={t("archive")}
        loading={pending}
        onConfirm={runArchive}
      />
    </div>
  );
}

export { VendorsTable };
