"use client";

import { useEffect, useState } from "react";
import { useRouter } from "next/navigation";
import { Search } from "lucide-react";
import { DataTable } from "@/components/ui/data-table";
import { Select } from "@/components/ui/select";
import { Input } from "@/components/ui/input";
import { Card, CardBody } from "@/components/ui/card";
import { useT } from "@/lib/i18n";

/**
 * The index table every procurement document shares: a number search, a
 * status filter, the page of rows, and a link into the detail. Columns
 * come from the caller, so this stays one component for four documents.
 */
function DocumentsTable({ rows, meta, page, search, status, statusOptions, basePath, columns, emptyTitle, searchPlaceholder, extraFilters = null }) {
  const tc = useT("common");
  const router = useRouter();
  const [searchInput, setSearchInput] = useState(search);

  useEffect(() => {
    if (searchInput === search) return undefined;
    const timeout = setTimeout(() => navigate({ q: searchInput, page: 1 }), 350);
    return () => clearTimeout(timeout);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [searchInput]);

  function navigate(next) {
    const params = new URLSearchParams({ page: String(next.page ?? page), q: next.q ?? search, status: next.status ?? status });
    for (const [key, value] of [...params.entries()]) {
      if (!value) params.delete(key);
    }
    router.push(`${basePath}?${params.toString()}`);
  }

  return (
    <div className="flex flex-col gap-4">
      <Card>
        <CardBody className="flex flex-wrap items-center gap-3 py-3">
          <div className="relative w-full flex-1 min-w-[200px]">
            <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-foreground-subtle" />
            <Input value={searchInput} onChange={(event) => setSearchInput(event.target.value)} placeholder={searchPlaceholder} className="pl-9" />
          </div>
          <div className="w-full sm:max-w-[220px]">
            <Select options={statusOptions} value={status} onValueChange={(value) => navigate({ status: value, page: 1 })} />
          </div>
          {extraFilters}
        </CardBody>
      </Card>

      <DataTable
        columns={columns}
        rows={rows}
        rowKey={(row) => row.id}
        emptyTitle={emptyTitle}
        rowActions={(row) => [{ label: tc("view"), onSelect: () => router.push(`${basePath}/${row.id}`) }]}
        pagination={{ page: meta.current_page, perPage: meta.per_page, total: meta.total }}
        onPageChange={(nextPage) => navigate({ page: nextPage })}
      />
    </div>
  );
}

export { DocumentsTable };
