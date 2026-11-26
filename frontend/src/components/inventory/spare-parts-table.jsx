"use client";

import { useRouter } from "next/navigation";
import Link from "next/link";
import { SearchingData } from "@/components/erp/searching-data";
import { DataTable } from "@/components/ui/data-table";
import { Badge } from "@/components/ui/badge";
import { useT } from "@/lib/i18n";
import { formatNumber, formatQuantity } from "@/lib/format";
import { refOf } from "@/lib/ref-path";

function SparePartsTable({ parts, meta, page, search, active, sort, direction, addHref }) {
  const t = useT("inventory");
  const tc = useT("common");
  const router = useRouter();

  const activeOptions = [
    { value: "", label: t("all_parts") },
    { value: "1", label: t("active_only") },
    { value: "0", label: t("inactive_only") },
  ];


  function navigate(next) {
    const params = new URLSearchParams({
      page: String(next.page ?? page),
      search: next.search ?? search,
      active: next.active ?? active,
      sort: next.sort ?? sort,
      direction: next.direction ?? direction,
    });
    for (const [key, value] of [...params.entries()]) {
      if (!value) params.delete(key);
    }
    router.push(`/inventory/parts?${params.toString()}`);
  }

  function toggleSort(key) {
    navigate({ sort: key, direction: sort === key && direction === "asc" ? "desc" : "asc", page: 1 });
  }

  return (
    <div className="flex flex-col gap-4">
      <SearchingData
        fields={[
          { name: "search", label: t("part"), placeholder: t("search_part_placeholder") },
          { name: "active", label: t("status"), type: "select", options: activeOptions },
        ]}
        values={{ search, active }}
        onSearch={(values) => navigate({ ...values, page: 1 })}
        addHref={addHref}
      />

      <DataTable
        columns={[
          {
            key: "part_number",
            header: t("part"),
            sortable: true,
            render: (part) => (
              <div>
                <Link href={`/inventory/parts/${refOf(part, "part_number")}`} className="font-medium text-brand hover:underline">
                  {part.part_number}
                </Link>
                <div className="text-xs text-foreground-muted">{part.name}</div>
              </div>
            ),
          },
          { key: "category", header: t("category"), render: (part) => part.category?.name ?? "—" },
          { key: "unit", header: t("unit") },
          {
            key: "on_hand",
            header: t("on_hand"),
            align: "right",
            // Red at or below the reorder level: the same line Low Stock draws.
            render: (part) => (
              <span className={part.reorder_level !== null && Number(part.on_hand ?? 0) <= Number(part.reorder_level) ? "font-semibold text-danger" : undefined}>
                {formatQuantity(part.on_hand ?? 0, part.unit)}
              </span>
            ),
          },
          {
            key: "reorder_level",
            header: t("reorder_level"),
            align: "right",
            render: (part) => formatNumber(part.reorder_level),
          },
          {
            key: "flags",
            header: t("flags"),
            render: (part) => (
              <div className="flex gap-1">
                {part.is_critical_spare ? <Badge variant="danger">{t("critical")}</Badge> : null}
                {!part.active ? <Badge variant="neutral">{t("inactive")}</Badge> : null}
              </div>
            ),
          },
        ]}
        rows={parts}
        rowKey={(part) => part.id}
        sort={{ key: sort, direction }}
        onSortChange={toggleSort}
        emptyTitle={t("no_parts")}
        emptyDescription={t("try_different_filter")}
        rowActions={(part) => [{ icon: "view", label: tc("view"), onSelect: () => router.push(`/inventory/parts/${refOf(part, "part_number")}`) }]}
        pagination={{ page: meta.current_page, perPage: meta.per_page, total: meta.total }}
        perPageSelect
        onPageChange={(nextPage) => navigate({ page: nextPage })}
      />
    </div>
  );
}

export { SparePartsTable };
