"use client";

import { useState, useTransition } from "react";
import { useRouter } from "next/navigation";
import Link from "next/link";
import { DataTable } from "@/components/ui/data-table";
import { Select } from "@/components/ui/select";
import { StatusBadge } from "@/components/ui/status-badge";
import { ConfirmDialog } from "@/components/ui/confirm-dialog";
import { FormattedDateTime } from "@/components/ui/formatted-date-time";
import { useToastManager } from "@/components/ui/toast";

const ACTIVE_OPTIONS = [
  { value: "", label: "All plans" },
  { value: "true", label: "Active" },
  { value: "false", label: "Inactive" },
];

/** Mirrors `PlanController::index` — when maintenance is due, and the rule that decides it. */
function PlansTable({ plans, meta, page, active, actions }) {
  const router = useRouter();
  const [pending, startTransition] = useTransition();
  const [deleting, setDeleting] = useState(null);
  const toastManager = useToastManager();

  function navigate(next) {
    const params = new URLSearchParams({
      page: String(next.page ?? page),
      active: next.active ?? active,
    });
    for (const [key, value] of [...params.entries()]) {
      if (!value) params.delete(key);
    }
    router.push(`/maintenance/plans?${params.toString()}`);
  }

  function runToggle(plan) {
    startTransition(async () => {
      const result = plan.active ? await actions.deactivatePlan(plan.id) : await actions.activatePlan(plan.id);
      if (result?.status === "success") router.refresh();
      else if (result?.status === "error") toastManager.add({ title: result.message, type: "danger" });
    });
  }

  function runDelete() {
    startTransition(async () => {
      const result = await actions.deletePlan(deleting.id);
      if (result?.status === "success") {
        toastManager.add({ title: "Plan deleted", type: "success" });
        setDeleting(null);
        router.refresh();
      } else if (result?.status === "error") {
        toastManager.add({ title: result.message, type: "danger" });
        setDeleting(null);
      }
    });
  }

  return (
    <div className="flex flex-col gap-4">
      <div className="w-full max-w-[220px]">
        <Select options={ACTIVE_OPTIONS} value={active} onValueChange={(value) => navigate({ active: value, page: 1 })} />
      </div>

      <DataTable
        columns={[
          {
            key: "name",
            header: "Plan",
            render: (p) => (
              <div>
                <Link href={`/maintenance/plans/${p.id}`} className="font-medium text-brand hover:underline">
                  {p.name}
                </Link>
                <div className="text-xs text-foreground-muted">{p.asset?.asset_code ?? p.asset_type ?? "—"}</div>
              </div>
            ),
          },
          {
            key: "trigger_type",
            header: "Trigger",
            render: (p) => p.trigger_type.charAt(0) + p.trigger_type.slice(1).toLowerCase(),
          },
          { key: "next_due_at", header: "Next due", render: (p) => <FormattedDateTime value={p.next_due_at} mode="date" /> },
          { key: "open_schedules_count", header: "Open", align: "right", render: (p) => p.open_schedules_count ?? 0 },
          { key: "status", header: "Status", render: (p) => <StatusBadge status={p.active ? "ACTIVE" : "INACTIVE"} /> },
        ]}
        rows={plans}
        rowKey={(p) => p.id}
        emptyTitle="No maintenance plans yet."
        rowActions={(p) => [
          { label: "View", onSelect: () => router.push(`/maintenance/plans/${p.id}`) },
          { label: "Edit", onSelect: () => router.push(`/maintenance/plans/${p.id}/edit`) },
          { label: p.active ? "Deactivate" : "Activate", onSelect: () => runToggle(p) },
          { label: "Delete", destructive: true, onSelect: () => setDeleting(p) },
        ]}
        pagination={{ page: meta.current_page, perPage: meta.per_page, total: meta.total }}
        onPageChange={(nextPage) => navigate({ page: nextPage })}
      />

      <ConfirmDialog
        open={Boolean(deleting)}
        onOpenChange={() => setDeleting(null)}
        title={`Delete ${deleting?.name}?`}
        description="Only possible while the plan has never generated an occurrence."
        confirmLabel="Delete"
        loading={pending}
        onConfirm={runDelete}
      />
    </div>
  );
}

export { PlansTable };
