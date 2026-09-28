"use client";

import { useState, useEffect } from "react";
import { useRouter } from "next/navigation";
import { Search } from "lucide-react";
import { Input } from "@/components/ui/input";
import { Select } from "@/components/ui/select";
import { Card, CardBody } from "@/components/ui/card";
import { useT } from "@/lib/i18n";

function StockFilters({ bins, search, binId }) {
  const t = useT("inventory");
  const router = useRouter();
  const [searchInput, setSearchInput] = useState(search);

  useEffect(() => {
    if (searchInput === search) return undefined;
    const timeout = setTimeout(() => navigate({ search: searchInput, page: 1 }), 350);
    return () => clearTimeout(timeout);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [searchInput]);

  function navigate(next) {
    const params = new URLSearchParams({
      page: String(next.page ?? 1),
      search: next.search ?? search,
      bin_id: next.bin_id ?? binId,
    });
    for (const [key, value] of [...params.entries()]) {
      if (!value) params.delete(key);
    }
    router.push(`/inventory/stock?${params.toString()}`);
  }

  return (
    <Card>
      <CardBody className="flex flex-wrap items-center gap-3 py-3">
        <div className="relative w-full flex-1 min-w-[200px]">
          <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-foreground-subtle" />
          <Input
            value={searchInput}
            onChange={(e) => setSearchInput(e.target.value)}
            placeholder={t("search_stock_placeholder")}
            className="pl-9"
          />
        </div>
        <div className="w-full max-w-xs">
          <Select
            options={[{ value: "", label: t("all_bins") }, ...bins.map((b) => ({ value: b.id, label: b.full_path }))]}
            value={binId}
            onValueChange={(value) => navigate({ bin_id: value })}
            placeholder={t("all_bins")}
          />
        </div>
      </CardBody>
    </Card>
  );
}

export { StockFilters };
