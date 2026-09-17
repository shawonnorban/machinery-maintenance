"use client";

import { useEffect, useState, useTransition } from "react";
import { useRouter } from "next/navigation";
import Link from "next/link";
import { Search } from "lucide-react";
import { DataTable } from "@/components/ui/data-table";
import { Select } from "@/components/ui/select";
import { Input } from "@/components/ui/input";
import { StatusBadge } from "@/components/ui/status-badge";
import { ConfirmDialog } from "@/components/ui/confirm-dialog";
import { Card, CardBody } from "@/components/ui/card";
import { useToastManager } from "@/components/ui/toast";
import { useT } from "@/lib/i18n";

function VendorsTable({ vendors, meta, page, search, status, archiveVendor }) {
  const t = useT("vendor");
  const tc = useT("common");
  const router = useRouter();

  const statusOptions = [
    { value: "", label: t("all_statuses") },
    { value: "ACTIVE", label: t("status_active") },
    { value: "INACTIVE", label: t("status_inactive") },
    { value: "BLACKLISTED", label: t("status_blacklisted") },
  ];
  const [searchInput, setSearchInput] = useState(search);
  const [archiving, setArchiving] = useState(null);
  const [pending, startTransition] = useTransition();
  const toastManager = useToastManager();

  useEffect(() => {
    if (searchInput === search) {
      return undefined;
    }
    const timeout = setTimeout(() => navigate({ q: searchInput, page: 1 }), 350);
    return () => clearTimeout(timeout);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [searchInput]);

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
      <Card>
        <CardBody className="flex flex-wrap items-center gap-3 py-3">
          <div className="relative w-full flex-1 min-w-[200px]">
            <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-foreground-subtle" />
            <Input
              value={searchInput}
              onChange={(event) => setSearchInput(event.target.value)}
              placeholder={t("search")}
              className="pl-9"
            />
          </div>
          <div className="w-full max-w-[200px]">
            <Select options={statusOptions} value={status} onValueChange={(value) => navigate({ status: value, page: 1 })} />
          </div>
        </CardBody>
      </Card>

      <DataTable
        columns={[
          {
            key: "name",
            header: t("vendor"),
            render: (v) => (
              <div>
                <Link href={`/vendors/${v.id}/edit`} className="font-medium text-brand hover:underline">
                  {v.name}
                </Link>
                <div className="text-xs text-foreground-muted">{v.code}</div>
              </div>
            ),
          },
          { key: "vendor_type", header: t("type"), render: (v) => t(`type_${v.vendor_type?.toLowerCase()}`) },
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
          { label: tc("edit"), onSelect: () => router.push(`/vendors/${v.id}/edit`) },
          { label: t("archive"), destructive: true, onSelect: () => setArchiving(v) },
        ]}
        pagination={{ page: meta.current_page, perPage: meta.per_page, total: meta.total }}
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
