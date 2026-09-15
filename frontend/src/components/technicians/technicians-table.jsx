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

/** Mirrors `TechnicianController::index` (SRS 25) — who works here and what they look after, carrying no money of any kind. */
function TechniciansTable({ technicians, meta, page, search, factoryId, departmentId, factories, departments, actions }) {
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
        toastManager.add({ title: "Technician deleted", type: "success" });
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
              placeholder="Search name or employee id…"
              className="pl-9"
            />
          </div>
          <div className="w-full max-w-[220px]">
            <Select
              options={[{ value: "", label: "All factories" }, ...factories.map((f) => ({ value: f.id, label: f.name }))]}
              value={factoryId}
              onValueChange={(value) => navigate({ factory_id: value, page: 1 })}
            />
          </div>
          <div className="w-full max-w-[220px]">
            <Select
              options={[{ value: "", label: "All departments" }, ...departments.map((d) => ({ value: d.id, label: d.name }))]}
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
            header: "Technician",
            render: (t) => (
              <div>
                <Link href={`/technicians/${t.id}/edit`} className="font-medium text-brand hover:underline">
                  {t.name}
                </Link>
                <div className="text-xs text-foreground-muted">{t.employee_id}</div>
              </div>
            ),
          },
          {
            key: "covers",
            header: "Covers",
            render: (t) => (
              <div>
                <div>{t.factory?.name ?? "—"}</div>
                <div className="text-xs text-foreground-muted">
                  {t.department ? [t.department, t.production_line].filter(Boolean).join(" · ") : "Whole factory"}
                </div>
              </div>
            ),
          },
          { key: "specialization", header: "Specialization", render: (t) => t.specialization ?? "—" },
          { key: "max_concurrent_work_orders", header: "Workload limit", align: "right", render: (t) => t.max_concurrent_work_orders ?? "No limit" },
          { key: "status", header: "Status", render: (t) => <StatusBadge status={t.status} /> },
        ]}
        rows={technicians}
        rowKey={(t) => t.id}
        emptyTitle="No technicians found."
        rowActions={(t) => [
          { label: "Edit", onSelect: () => router.push(`/technicians/${t.id}/edit`) },
          { label: t.status === "ACTIVE" ? "Deactivate" : "Activate", onSelect: () => runToggle(t) },
          { label: "Delete", destructive: true, onSelect: () => setDeleting(t) },
        ]}
        pagination={{ page: meta.current_page, perPage: meta.per_page, total: meta.total }}
        onPageChange={(nextPage) => navigate({ page: nextPage })}
      />

      <ConfirmDialog
        open={Boolean(deleting)}
        onOpenChange={() => setDeleting(null)}
        title={`Delete ${deleting?.name}?`}
        description="Only possible while nothing references this technician."
        confirmLabel="Delete"
        loading={pending}
        onConfirm={runDelete}
      />
    </div>
  );
}

export { TechniciansTable };
