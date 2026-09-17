import Link from "next/link";
import { AlertCircle, Clock } from "lucide-react";
import { apiFetch } from "@/lib/api-server";
import { PageHeader } from "@/components/layout/page-header";
import { StatCard } from "@/components/ui/stat-card";
import { cn } from "@/lib/utils";
import { ApprovalsTable } from "@/components/approvals/approvals-table";
import { getT } from "@/lib/i18n-server";

/** Mirrors `ApprovalController::index` (SRS 14). */
export default async function ApprovalsPage({ searchParams }) {
  const params = await searchParams;
  const status = params.status ?? "PENDING";
  const page = Number(params.page ?? 1);

  const [approvals, t, tc] = await Promise.all([
    apiFetch(`/approval-requests?status=${status}&page=${page}`, { includeMeta: true }),
    getT("approval"),
    getT("common"),
  ]);

  const TABS = [
    { value: "PENDING", label: t("status_pending") },
    { value: "APPROVED", label: t("status_approved") },
    { value: "REJECTED", label: t("status_rejected") },
    { value: "ALL", label: tc("all") },
  ];

  return (
    <>
      <PageHeader breadcrumb={[{ label: t("approvals") }]} title={t("approvals")} />

      <div className="mb-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
        {/* What is waiting on the person reading the screen, not what is waiting in general — the second number is somebody else's problem. */}
        <StatCard label={t("pending_for_me")} value={approvals.meta.counts.mine} icon={<AlertCircle />} tone="warning" />
        <StatCard label={t("all_pending")} value={approvals.meta.counts.pending} icon={<Clock />} tone="info" />
      </div>

      <nav className="mb-4 flex gap-1 border-b border-border">
        {TABS.map((tab) => (
          <Link
            key={tab.value}
            href={`/approvals?status=${tab.value}`}
            className={cn(
              "border-b-2 px-3 py-2 text-sm font-medium transition-colors duration-150",
              status === tab.value
                ? "border-brand text-brand"
                : "border-transparent text-foreground-muted hover:text-foreground",
            )}
          >
            {tab.label}
          </Link>
        ))}
      </nav>

      <ApprovalsTable approvals={approvals.data} meta={approvals.meta} page={page} status={status} />
    </>
  );
}
