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
import { LocationFormModal } from "@/components/locations/location-form-modal";

/** Mirrors `AssetLocationController::index` (ADR-052) — where machines actually live, configuration rather than day-to-day work. Create/edit happen in a modal over this list rather than a separate page. */
function LocationsTable({ locations, meta, page, search, factoryId, factories, buildings, floors, departments, sections, productionLines, workstations, actions }) {
  const router = useRouter();
  const [searchInput, setSearchInput] = useState(search);
  const [deleting, setDeleting] = useState(null);
  const [creating, setCreating] = useState(false);
  const [editing, setEditing] = useState(null);
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
    });
    for (const [key, value] of [...params.entries()]) {
      if (!value) params.delete(key);
    }
    router.push(`/settings/locations?${params.toString()}`);
  }

  function runToggle(location) {
    startTransition(async () => {
      const result = await actions.toggleLocation(location);
      if (result?.status === "success") router.refresh();
      else if (result?.status === "error") toastManager.add({ title: result.message, type: "danger" });
    });
  }

  function runDelete() {
    startTransition(async () => {
      const result = await actions.deleteLocation(deleting.id);
      if (result?.status === "success") {
        toastManager.add({ title: "Location deleted", type: "success" });
        setDeleting(null);
        router.refresh();
      } else if (result?.status === "error") {
        toastManager.add({ title: result.message, type: "danger" });
      }
    });
  }

  const formLists = { factories, buildings, floors, departments, sections, productionLines, workstations };

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
          <div className="w-full max-w-[220px]">
            <Select
              options={[{ value: "", label: "All factories" }, ...factories.map((f) => ({ value: f.id, label: f.name }))]}
              value={factoryId}
              onValueChange={(value) => navigate({ factory_id: value, page: 1 })}
            />
          </div>
          <Button size="sm" onClick={() => setCreating(true)}>
            <Plus /> New location
          </Button>
        </CardBody>
      </Card>

      <DataTable
        columns={[
          {
            key: "name",
            header: "Location",
            render: (l) => (
              <div>
                <button type="button" onClick={() => setEditing(l)} className="font-medium text-brand hover:underline">
                  {l.name}
                </button>
                <div className="text-xs text-foreground-muted">{l.full_path ?? l.code}</div>
              </div>
            ),
          },
          { key: "factory", header: "Factory", render: (l) => l.factory?.name ?? "—" },
          { key: "asset_count", header: "Assets", align: "right", render: (l) => l.asset_count ?? "—" },
          { key: "status", header: "Status", render: (l) => <StatusBadge status={l.status} /> },
        ]}
        rows={locations}
        rowKey={(l) => l.id}
        emptyTitle="No locations found."
        rowActions={(l) => [
          { label: "Edit", onSelect: () => setEditing(l) },
          { label: l.status === "ACTIVE" ? "Deactivate" : "Activate", onSelect: () => runToggle(l) },
          { label: "Delete", destructive: true, onSelect: () => setDeleting(l) },
        ]}
        pagination={{ page: meta.current_page, perPage: meta.per_page, total: meta.total }}
        onPageChange={(nextPage) => navigate({ page: nextPage })}
      />

      <LocationFormModal
        key="create"
        open={creating}
        onOpenChange={setCreating}
        location={null}
        action={actions.createLocation}
        {...formLists}
      />
      <LocationFormModal
        key={editing?.id ?? "edit"}
        open={Boolean(editing)}
        onOpenChange={(open) => !open && setEditing(null)}
        location={editing}
        action={editing ? actions.updateLocation.bind(null, editing.id) : actions.updateLocation}
        {...formLists}
      />

      <ConfirmDialog
        open={Boolean(deleting)}
        onOpenChange={() => setDeleting(null)}
        title={`Delete ${deleting?.name}?`}
        description="Only possible while nothing references it — a location machines have stood in stays readable instead."
        confirmLabel="Delete"
        loading={pending}
        onConfirm={runDelete}
      />
    </div>
  );
}

export { LocationsTable };
