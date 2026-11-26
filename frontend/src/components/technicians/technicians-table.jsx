"use client";

import { useEffect, useState, useTransition } from "react";
import { useRouter } from "next/navigation";
import Link from "next/link";
import { SearchingData } from "@/components/erp/searching-data";
import { DataTable } from "@/components/ui/data-table";
import { StatusBadge } from "@/components/ui/status-badge";
import { ConfirmDialog } from "@/components/ui/confirm-dialog";
import { useToastManager } from "@/components/ui/toast";
import { useT } from "@/lib/i18n";
import { refOf } from "@/lib/ref-path";

/** Mirrors `TechnicianController::index` (SRS 25) — who works here and what they look after, carrying no money of any kind. */
function TechniciansTable({ technicians, meta, page, search, factoryId, departmentId, factories, departments, actions, addHref }) {
  const t = useT("technician");
  const tc = useT("common");
  const router = useRouter();
  const [deleting, setDeleting] = useState(null);
  const [pending, startTransition] = useTransition();
  const toastManager = useToastManager();


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
      <SearchingData
        fields={[
          { name: "search", label: t("technician"), placeholder: t("search") },
          { name: "factory_id", label: t("factory"), type: "select", options: factories.map((f) => ({ value: f.id, label: f.name })) },
          { name: "department_id", label: t("department"), type: "select", options: departments.map((d) => ({ value: d.id, label: d.name })) },
        ]}
        values={{ search, factory_id: factoryId, department_id: departmentId }}
        onSearch={(values) => navigate({ ...values, page: 1 })}
        addHref={addHref}
      />

      <DataTable
        columns={[
          {
            key: "name",
            header: t("technician"),
            render: (row) => (
              <div>
                <Link href={`/technicians/${refOf(row, "employee_id")}/edit`} className="font-medium text-brand hover:underline">
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
          { icon: "edit", label: tc("edit"), onSelect: () => router.push(`/technicians/${refOf(row, "employee_id")}/edit`) },
          { icon: "toggle", label: row.status === "ACTIVE" ? t("deactivate") : t("activate"), onSelect: () => runToggle(row) },
          { icon: "delete", label: tc("delete"), destructive: true, onSelect: () => setDeleting(row) },
        ]}
        pagination={{ page: meta.current_page, perPage: meta.per_page, total: meta.total }}
        perPageSelect
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
