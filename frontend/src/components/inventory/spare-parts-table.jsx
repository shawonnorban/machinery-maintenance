"use client";

import { useEffect, useState } from "react";
import { useRouter } from "next/navigation";
import Link from "next/link";
import { Search } from "lucide-react";
import { DataTable } from "@/components/ui/data-table";
import { Select } from "@/components/ui/select";
import { Input } from "@/components/ui/input";
import { Badge } from "@/components/ui/badge";
import { Card, CardBody } from "@/components/ui/card";

const ACTIVE_OPTIONS = [
  { value: "", label: "All parts" },
  { value: "1", label: "Active only" },
  { value: "0", label: "Inactive only" },
];

function SparePartsTable({ parts, meta, page, search, active, sort, direction }) {
  const router = useRouter();
  const [searchInput, setSearchInput] = useState(search);

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
      <Card>
        <CardBody className="flex flex-wrap items-center gap-3 py-3">
          <div className="relative w-full flex-1 min-w-[200px]">
            <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-foreground-subtle" />
            <Input
              value={searchInput}
              onChange={(event) => setSearchInput(event.target.value)}
              placeholder="Search part number, name, brand…"
              className="pl-9"
            />
          </div>
          <div className="w-full max-w-[200px]">
            <Select options={ACTIVE_OPTIONS} value={active} onValueChange={(value) => navigate({ active: value, page: 1 })} />
          </div>
        </CardBody>
      </Card>

      <DataTable
        columns={[
          {
            key: "part_number",
            header: "Part",
            sortable: true,
            render: (part) => (
              <div>
                <Link href={`/inventory/parts/${part.id}`} className="font-medium text-brand hover:underline">
                  {part.part_number}
                </Link>
                <div className="text-xs text-foreground-muted">{part.name}</div>
              </div>
            ),
          },
          { key: "category", header: "Category", render: (part) => part.category?.name ?? "—" },
          { key: "unit", header: "Unit" },
          {
            key: "reorder_level",
            header: "Reorder level",
            align: "right",
            render: (part) => `${part.reorder_level ?? "—"}`,
          },
          {
            key: "flags",
            header: "",
            render: (part) => (
              <div className="flex gap-1">
                {part.is_critical_spare ? <Badge variant="danger">Critical</Badge> : null}
                {!part.active ? <Badge variant="neutral">Inactive</Badge> : null}
              </div>
            ),
          },
        ]}
        rows={parts}
        rowKey={(part) => part.id}
        sort={{ key: sort, direction }}
        onSortChange={toggleSort}
        emptyTitle="No parts found."
        emptyDescription="Try a different search or filter."
        rowActions={(part) => [{ label: "View", onSelect: () => router.push(`/inventory/parts/${part.id}`) }]}
        pagination={{ page: meta.current_page, perPage: meta.per_page, total: meta.total }}
        onPageChange={(nextPage) => navigate({ page: nextPage })}
      />
    </div>
  );
}

export { SparePartsTable };
