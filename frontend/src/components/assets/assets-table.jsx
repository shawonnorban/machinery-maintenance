"use client";

import { useEffect, useState } from "react";
import { useRouter } from "next/navigation";
import Link from "next/link";
import { Search } from "lucide-react";
import { DataTable } from "@/components/ui/data-table";
import { Select } from "@/components/ui/select";
import { Input } from "@/components/ui/input";
import { StatusBadge, formatStatus } from "@/components/ui/status-badge";
import { Badge } from "@/components/ui/badge";
import { Card, CardBody } from "@/components/ui/card";

const CRITICALITY_TONE = { CRITICAL: "danger", HIGH: "warning", MEDIUM: "info", LOW: "neutral" };

/**
 * Search debounces into the URL rather than firing a navigation per
 * keystroke; every other control (status, criticality, sort, page)
 * navigates immediately — page.js re-fetches server-side on each change
 * (docs/12-Stack-Migration-Implementation-Plan.md Phase C §5).
 */
function AssetsTable({ assets, meta, page, search, status, criticality, sort, direction, statusOptions, criticalityOptions }) {
  const router = useRouter();
  const [searchInput, setSearchInput] = useState(search);
  const [selectedKeys, setSelectedKeys] = useState([]);

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
      {/* Search and both filters on one row — mirrors `assets/index.blade.
          php`'s own single filter card, which this had split into a
          search row (inside DataTable) sitting below a separate filter
          row until now. */}
      <Card>
        <CardBody className="flex flex-wrap items-center gap-3 py-3">
          <div className="relative w-full flex-1 min-w-[200px]">
            <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-foreground-subtle" />
            <Input
              value={searchInput}
              onChange={(event) => setSearchInput(event.target.value)}
              placeholder="Search asset code, name, serial…"
              className="pl-9"
            />
          </div>
          <div className="w-full max-w-[220px]">
            <Select
              options={[{ value: "", label: "All statuses" }, ...statusOptions.map((s) => ({ value: s, label: formatStatus(s) }))]}
              value={status}
              onValueChange={(value) => navigate({ status: value, page: 1 })}
              placeholder="All statuses"
            />
          </div>
          <div className="w-full max-w-[180px]">
            <Select
              options={[{ value: "", label: "All criticalities" }, ...criticalityOptions.map((c) => ({ value: c, label: formatStatus(c) }))]}
              value={criticality}
              onValueChange={(value) => navigate({ criticality: value, page: 1 })}
              placeholder="All criticalities"
            />
          </div>
        </CardBody>
      </Card>

      <DataTable
        columns={[
          {
            key: "asset_code",
            header: "Asset",
            sortable: true,
            render: (asset) => (
              <div>
                <Link href={`/assets/${asset.id}`} className="font-medium text-brand hover:underline">
                  {asset.asset_code}
                </Link>
                <div className="text-xs text-foreground-muted">{asset.name}</div>
              </div>
            ),
          },
          { key: "type", header: "Type", render: (asset) => asset.type?.name ?? "—" },
          {
            key: "factory",
            header: "Factory",
            render: (asset) => (
              <div>
                {asset.factory?.name}
                <div className="text-xs text-foreground-muted">{asset.location?.name}</div>
              </div>
            ),
          },
          {
            key: "criticality",
            header: "Criticality",
            sortable: true,
            render: (asset) => <Badge variant={CRITICALITY_TONE[asset.criticality] ?? "neutral"}>{formatStatus(asset.criticality)}</Badge>,
          },
          {
            key: "status",
            header: "Status",
            sortable: true,
            render: (asset) => <StatusBadge status={asset.status} />,
          },
        ]}
        rows={assets}
        rowKey={(asset) => asset.id}
        sort={{ key: sort, direction }}
        onSortChange={toggleSort}
        emptyTitle="No assets found."
        emptyDescription="Try a different search or filter."
        rowActions={(asset) => [{ label: "View", onSelect: () => router.push(`/assets/${asset.id}`) }]}
        selectedKeys={selectedKeys}
        onSelectedKeysChange={setSelectedKeys}
        bulkActions={[
          {
            label: "Print labels",
            onSelect: (keys) => router.push(`/assets/labels?${keys.map((id) => `ids[]=${encodeURIComponent(id)}`).join("&")}`),
          },
        ]}
        pagination={{ page: meta.current_page, perPage: meta.per_page, total: meta.total }}
        onPageChange={(nextPage) => navigate({ page: nextPage })}
      />
    </div>
  );
}

export { AssetsTable };
