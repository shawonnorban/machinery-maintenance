"use client";

import { useState } from "react";
import { useRouter } from "next/navigation";
import Link from "next/link";
import { DataTable } from "@/components/ui/data-table";
import { SearchingData } from "@/components/erp/searching-data";
import { StatusBadge } from "@/components/ui/status-badge";
import { Badge } from "@/components/ui/badge";
import { useT } from "@/lib/i18n";
import { refOf } from "@/lib/ref-path";

const CRITICALITY_TONE = { CRITICAL: "danger", HIGH: "warning", MEDIUM: "info", LOW: "neutral" };

/**
 * Search debounces into the URL rather than firing a navigation per
 * keystroke; every other control (status, criticality, sort, page)
 * navigates immediately — page.js re-fetches server-side on each change
 * (docs/12-Stack-Migration-Implementation-Plan.md Phase C §5).
 */
function AssetsTable({ assets, meta, page, search, status, criticality, sort, direction, statusOptions, criticalityOptions, addHref }) {
  const t = useT("asset");
  const tc = useT("common");
  const router = useRouter();
  const [selectedKeys, setSelectedKeys] = useState([]);

  function navigate(next) {
    const params = new URLSearchParams({
      page: String(next.page ?? page),
      search: next.search ?? search,
      status: next.status ?? status,
      criticality: next.criticality ?? criticality,
      sort: next.sort ?? sort,
      direction: next.direction ?? direction,
    });
    for (const [key, value] of [...params.entries()]) {
      if (!value) params.delete(key);
    }
    router.push(`/assets?${params.toString()}`);
  }

  function toggleSort(key) {
    navigate({
      sort: key,
      direction: sort === key && direction === "asc" ? "desc" : "asc",
      page: 1,
    });
  }

  return (
    <div className="flex flex-col gap-4">
      <SearchingData
        fields={[
          { name: "search", label: t("asset"), placeholder: t("search_placeholder") },
          { name: "status", label: t("status"), type: "select", options: statusOptions.map((s) => ({ value: s, label: t(`status_${s.toLowerCase()}`) })) },
          { name: "criticality", label: t("criticality"), type: "select", options: criticalityOptions.map((c) => ({ value: c, label: t(`criticality_${c.toLowerCase()}`) })) },
        ]}
        values={{ search, status, criticality }}
        onSearch={(values) => navigate({ ...values, page: 1 })}
        addHref={addHref}
      />

      <DataTable
        columns={[
          {
            key: "asset_code",
            header: t("asset"),
            sortable: true,
            render: (asset) => (
              <div>
                <Link href={`/assets/${refOf(asset, "asset_code")}`} className="font-medium text-brand hover:underline">
                  {asset.asset_code}
                </Link>
                <div className="text-xs text-foreground-muted">{asset.name}</div>
              </div>
            ),
          },
          { key: "type", header: t("type"), render: (asset) => asset.type?.name ?? "—" },
          {
            key: "factory",
            header: t("factory"),
            render: (asset) => (
              <div>
                {asset.factory?.name}
                <div className="text-xs text-foreground-muted">{asset.location?.name}</div>
              </div>
            ),
          },
          {
            key: "criticality",
            header: t("criticality"),
            sortable: true,
            render: (asset) => <Badge variant={CRITICALITY_TONE[asset.criticality] ?? "neutral"}>{t(`criticality_${asset.criticality?.toLowerCase()}`)}</Badge>,
          },
          {
            key: "status",
            header: t("status"),
            sortable: true,
            render: (asset) => <StatusBadge status={asset.status} label={t(`status_${asset.status?.toLowerCase()}`)} />,
          },
        ]}
        rows={assets}
        rowKey={(asset) => asset.id}
        sort={{ key: sort, direction }}
        onSortChange={toggleSort}
        emptyTitle={t("no_assets")}
        emptyDescription={t("try_different_filter")}
        rowActions={(asset) => [{ icon: "view", label: tc("view"), onSelect: () => router.push(`/assets/${refOf(asset, "asset_code")}`) }]}
        selectedKeys={selectedKeys}
        onSelectedKeysChange={setSelectedKeys}
        bulkActions={[
          {
            label: t("print_labels"),
            onSelect: (keys) => router.push(`/assets/labels?${keys.map((id) => `ids[]=${encodeURIComponent(id)}`).join("&")}`),
          },
        ]}
        pagination={{ page: meta.current_page, perPage: meta.per_page, total: meta.total }}
        perPageSelect
        onPageChange={(nextPage) => navigate({ page: nextPage })}
      />
    </div>
  );
}

export { AssetsTable };
