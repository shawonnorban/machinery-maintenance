"use client";

import { useEffect, useState, useTransition } from "react";
import { useRouter } from "next/navigation";
import { Plus, Search } from "lucide-react";
import { DataTable } from "@/components/ui/data-table";
import { Select } from "@/components/ui/select";
import { Input } from "@/components/ui/input";
import { Button } from "@/components/ui/button";
import { StatusBadge } from "@/components/ui/status-badge";
import { ConfirmDialog } from "@/components/ui/confirm-dialog";
import { Card, CardBody } from "@/components/ui/card";
import { useToastManager } from "@/components/ui/toast";
import { FactoryFormModal } from "@/components/factories/factory-form-modal";

const STATUS_OPTIONS = [
  { value: "", label: "All statuses" },
  { value: "ACTIVE", label: "Active" },
  { value: "INACTIVE", label: "Inactive" },
];

function FactoriesTable({ factories, meta, page, search, status, actions }) {
  const router = useRouter();
  const [searchInput, setSearchInput] = useState(search);
  const [deleting, setDeleting] = useState(null);
  const [creating, setCreating] = useState(false);
  const [editing, setEditing] = useState(null);
  const [pending, startTransition] = useTransition();
  const toastManager = useToastManager();

  useEffect(() => {
    if (searchInput === search) {
      return undefined;
    }
    const timeout = setTimeout(() => navigate({ search: searchInput, page: 1 }), 350);
    return () => clearTimeout(timeout);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [searchInput]);

  function navigate(next) {
    const params = new URLSearchParams({
      page: String(next.page ?? page),
      search: next.search ?? search,
      status: next.status ?? status,
    });
    for (const [key, value] of [...params.entries()]) {
      if (!value) params.delete(key);
    }
    router.push(`/settings/factories?${params.toString()}`);
  }

  function runToggle(factory) {
    startTransition(async () => {
      const result = await actions.toggleFactory(factory);
      if (result?.status === "success") {
        router.refresh();
      } else if (result?.status === "error") {
        toastManager.add({ title: result.message, type: "danger" });
      }
    });
  }

  function runDelete() {
    startTransition(async () => {
      const result = await actions.deleteFactory(deleting.id);
      if (result?.status === "success") {
        toastManager.add({ title: "Factory deleted", type: "success" });
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
              placeholder="Search name or code…"
              className="pl-9"
            />
          </div>
          <div className="w-full max-w-[200px]">
            <Select options={STATUS_OPTIONS} value={status} onValueChange={(value) => navigate({ status: value, page: 1 })} />
          </div>
          <Button size="sm" onClick={() => setCreating(true)}>
            <Plus /> New factory
          </Button>
        </CardBody>
      </Card>

      <DataTable
        columns={[
          {
            key: "name",
            header: "Factory",
            render: (f) => (
              <div>
                <button type="button" onClick={() => setEditing(f)} className="font-medium text-brand hover:underline">
                  {f.name}
                </button>
                <div className="text-xs text-foreground-muted">{f.code}</div>
              </div>
            ),
          },
          { key: "timezone", header: "Timezone" },
          { key: "asset_count", header: "Assets", align: "right", render: (f) => f.asset_count ?? "—" },
          { key: "status", header: "Status", render: (f) => <StatusBadge status={f.status} /> },
        ]}
        rows={factories}
        rowKey={(f) => f.id}
        emptyTitle="No factories found."
        rowActions={(f) => [
          { label: "Edit", onSelect: () => setEditing(f) },
          { label: f.status === "ACTIVE" ? "Deactivate" : "Activate", onSelect: () => runToggle(f) },
          { label: "Delete", destructive: true, onSelect: () => setDeleting(f) },
        ]}
        pagination={{ page: meta.current_page, perPage: meta.per_page, total: meta.total }}
        onPageChange={(nextPage) => navigate({ page: nextPage })}
      />

      <FactoryFormModal key="create" open={creating} onOpenChange={setCreating} factory={null} action={actions.createFactory} />
      <FactoryFormModal
        key={editing?.id ?? "edit"}
        open={Boolean(editing)}
        onOpenChange={(open) => !open && setEditing(null)}
        factory={editing}
        action={editing ? actions.updateFactory.bind(null, editing.id) : actions.updateFactory}
      />

      <ConfirmDialog
        open={Boolean(deleting)}
        onOpenChange={() => setDeleting(null)}
        title={`Delete ${deleting?.name}?`}
        description="Only possible while nothing has ever run in it — a factory that has is closed instead."
        confirmLabel="Delete"
        loading={pending}
        onConfirm={runDelete}
      />
    </div>
  );
}

export { FactoriesTable };
