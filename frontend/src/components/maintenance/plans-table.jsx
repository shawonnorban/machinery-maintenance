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
import { useT } from "@/lib/i18n";

/** Mirrors `PlanController::index` — when maintenance is due, and the rule that decides it. */
function PlansTable({ plans, meta, page, active, actions }) {
  const t = useT("maintenance");
  const tc = useT("common");
  const router = useRouter();
  const [pending, startTransition] = useTransition();
  const [deleting, setDeleting] = useState(null);
  const toastManager = useToastManager();

  const activeOptions = [
    { value: "", label: t("all_plans") },
    { value: "true", label: t("active") },
    { value: "false", label: t("inactive") },
  ];

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
        toastManager.add({ title: t("plan_deleted_toast"), type: "success" });
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
        <Select options={activeOptions} value={active} onValueChange={(value) => navigate({ active: value, page: 1 })} />
      </div>

      <DataTable
        columns={[
          {
            key: "name",
            header: t("plan"),
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
            header: t("trigger"),
            render: (p) => t(`trigger_${p.trigger_type?.toLowerCase()}`),
          },
          { key: "next_due_at", header: t("next_due"), render: (p) => <FormattedDateTime value={p.next_due_at} mode="date" /> },
          { key: "open_schedules_count", header: t("open_occurrences"), align: "right", render: (p) => p.open_schedules_count ?? 0 },
          {
            key: "status",
            header: t("status"),
            render: (p) => <StatusBadge status={p.active ? "ACTIVE" : "INACTIVE"} label={p.active ? t("active") : t("inactive")} />,
          },
        ]}
        rows={plans}
        rowKey={(p) => p.id}
        emptyTitle={t("no_plans")}
        rowActions={(p) => [
          { label: tc("view"), onSelect: () => router.push(`/maintenance/plans/${p.id}`) },
          { label: tc("edit"), onSelect: () => router.push(`/maintenance/plans/${p.id}/edit`) },
          { label: p.active ? t("deactivate") : t("activate"), onSelect: () => runToggle(p) },
          { label: tc("delete"), destructive: true, onSelect: () => setDeleting(p) },
        ]}
        pagination={{ page: meta.current_page, perPage: meta.per_page, total: meta.total }}
        onPageChange={(nextPage) => navigate({ page: nextPage })}
      />

      <ConfirmDialog
        open={Boolean(deleting)}
        onOpenChange={() => setDeleting(null)}
        title={t("delete_plan_confirm", { name: deleting?.name })}
        description={t("delete_plan_hint")}
        confirmLabel={tc("delete")}
        loading={pending}
        onConfirm={runDelete}
      />
    </div>
  );
}

export { PlansTable };
