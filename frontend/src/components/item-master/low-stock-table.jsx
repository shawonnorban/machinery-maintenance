"use client";

import Link from "next/link";
import { useRouter } from "next/navigation";
import { DataTable } from "@/components/ui/data-table";
import { Select } from "@/components/ui/select";
import { Badge } from "@/components/ui/badge";
import { formatNumber } from "@/lib/format";
import { useT } from "@/lib/i18n";

function LowStockTable({ items, meta, page, departmentId, departments }) {
  const t = useT("itemmaster");
  const tc = useT("common");
  const router = useRouter();

  function navigate(next) {
    const params = new URLSearchParams({ page: String(next.page ?? page), item_department_id: next.item_department_id ?? departmentId });
    for (const [key, value] of [...params.entries()]) {
      if (!value) params.delete(key);
    }
    router.push(`/item-master/low-stock?${params.toString()}`);
  }

  return (
    <div className="flex flex-col gap-4">
      <div className="w-full sm:max-w-[260px]">
        <Select
          options={[{ value: "", label: t("all_departments") }, ...departments.map((d) => ({ value: d.id, label: d.name }))]}
          placeholder={t("department")}
          value={departmentId}
          onValueChange={(value) => navigate({ item_department_id: value, page: 1 })}
        />
      </div>

      <DataTable
        columns={[
          {
            key: "name",
            header: t("name"),
            render: (row) => (
              <Link href={`/item-master/${row.id}`} className="font-medium text-brand hover:underline">
                {row.name}
              </Link>
            ),
          },
          { key: "item_code", header: t("item_code"), render: (row) => <span className="font-mono">{row.item_code}</span> },
          { key: "item_department", header: t("department"), render: (row) => row.item_department?.name ?? "—" },
          { key: "unit", header: t("unit"), render: (row) => row.uom?.code ?? "—" },
          {
            key: "on_hand",
            header: t("on_hand"),
            align: "right",
            render: (row) => (
              <span className="flex items-center justify-end gap-2">
                {Number(row.on_hand) <= 0 ? <Badge variant="danger">0</Badge> : formatNumber(row.on_hand)}
              </span>
            ),
          },
          { key: "reorder_level", header: t("reorder_level"), align: "right", render: (row) => formatNumber(row.reorder_level) },
        ]}
        rows={items}
        rowKey={(row) => row.id}
        emptyTitle={t("no_low_stock")}
        rowActions={(row) => [{ label: tc("view"), onSelect: () => router.push(`/item-master/${row.id}`) }]}
        pagination={{ page: meta.current_page, perPage: meta.per_page, total: meta.total }}
        onPageChange={(nextPage) => navigate({ page: nextPage })}
      />
    </div>
  );
}

export { LowStockTable };
