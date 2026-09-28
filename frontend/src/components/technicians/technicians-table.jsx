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

/** Mirrors `TechnicianController::index` (SRS 25) — who works here and what they look after, carrying no money of any kind. */
function TechniciansTable({ technicians, meta, page, search, factoryId, departmentId, factories, departments, actions }) {
  const t = useT("technician");
  const tc = useT("common");
  const router = useRouter();
  const [searchInput, setSearchInput] = useState(search);
  const [deleting, setDeleting] = useState(null);
  const [pending, startTransition] = useTransition();
  const toastManager = useToastManager();

  useEffect(() => {
    if (searchInput === search) return undefined;
    const timeout = setTimeout(() => navigate({ search: searchInput, page: 1 }), 350);
    return () => clearTimeout(timeout);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [searchInput]);

  function navigate(next) {
    const params = new URLSearchParams({
      page: String(next.page ?? page),
      search: next.search ?? search,
      factory_id: next.factory_id ?? factoryId,
      department_id: next.department_id ?? departmentId,
    });
    for (const [key, value] of [...params.entries()]) {
      if (!value) params.delete(key);
    }
    router.push(`/technicians?${params.toString()}`);
  }

  function runToggle(technician) {
    startTransition(async () => {
      const result = await actions.toggleTechnician(technician);
      if (result?.status === "success") router.refresh();
      else if (result?.status === "error") toastManager.add({ title: result.message, type: "danger" });
    });
  }

  function runDelete() {
    startTransition(async () => {
      const result = await actions.deleteTechnician(deleting.id);
      if (result?.status === "success") {
        toastManager.add({ title: t("technician_deleted_toast"), type: "success" });
        setDeleting(null);
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
          <div className="w-full max-w-[220px]">
            <Select
              options={[{ value: "", label: t("all_factories") }, ...factories.map((f) => ({ value: f.id, label: f.name }))]}
              value={factoryId}
              onValueChange={(value) => navigate({ factory_id: value, page: 1 })}
            />
          </div>
          <div className="w-full max-w-[220px]">
            <Select
              options={[{ value: "", label: t("all_departments") }, ...departments.map((d) => ({ value: d.id, label: d.name }))]}
              value={departmentId}
              onValueChange={(value) => navigate({ department_id: value, page: 1 })}
            />
          </div>
        </CardBody>
      </Card>

      <DataTable
        columns={[
          {
            key: "name",
            header: t("technician"),
            render: (row) => (
              <div>
                <Link href={`/technicians/${row.id}/edit`} className="font-medium text-brand hover:underline">
                  {row.name}
                </Link>
                <div className="text-xs text-foreground-muted">{row.employee_id}</div>
              </div>
            ),
          },
          {
            key: "covers",
            header: t("covers"),
            render: (row) => (
              <div>
                <div>{row.factory?.name ?? "—"}</div>
                <div className="text-xs text-foreground-muted">
                  {row.department ? [row.department, row.production_line].filter(Boolean).join(" · ") : t("whole_factory")}
                </div>
              </div>
            ),
          },
          { key: "specialization", header: t("specialization"), render: (row) => row.specialization ?? "—" },
          {
            key: "max_concurrent_work_orders",
            header: t("workload_limit"),
            align: "right",
            render: (row) => row.max_concurrent_work_orders ?? t("no_limit"),
          },
          {
            key: "status",
            header: t("status"),
            render: (row) => <StatusBadge status={row.status} label={row.status === "ACTIVE" ? t("active") : t("inactive")} />,
          },
        ]}
        rows={technicians}
        rowKey={(row) => row.id}
        emptyTitle={t("no_technicians_found")}
        rowActions={(row) => [
          { label: tc("edit"), onSelect: () => router.push(`/technicians/${row.id}/edit`) },
          { label: row.status === "ACTIVE" ? t("deactivate") : t("activate"), onSelect: () => runToggle(row) },
          { label: tc("delete"), destructive: true, onSelect: () => setDeleting(row) },
        ]}
        pagination={{ page: meta.current_page, perPage: meta.per_page, total: meta.total }}
        onPageChange={(nextPage) => navigate({ page: nextPage })}
      />

      <ConfirmDialog
        open={Boolean(deleting)}
        onOpenChange={() => setDeleting(null)}
        title={t("delete_confirm_title", { name: deleting?.name })}
        description={t("delete_confirm_hint")}
        confirmLabel={tc("delete")}
        loading={pending}
        onConfirm={runDelete}
      />
    </div>
  );
}

export { TechniciansTable };
