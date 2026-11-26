"use client";

import { useRouter } from "next/navigation";
import { SearchingData } from "@/components/erp/searching-data";
import { useT } from "@/lib/i18n";

/** The Stock screen's Searching Data: a part, and the bin it sits in. */
function StockFilters({ bins, search, binId }) {
  const t = useT("inventory");
  const router = useRouter();

  function navigate(next) {
    const params = new URLSearchParams({
      search: next.search ?? search,
      bin_id: next.bin_id ?? binId,
    });
    for (const [key, value] of [...params.entries()]) {
      if (!value) params.delete(key);
    }
    router.push(`/inventory/stock${params.size ? `?${params}` : ""}`);
  }

  return (
    <div className="mb-4">
      <SearchingData
        fields={[
          {
            name: "search",
            label: t("part"),
            placeholder: t("search_stock_placeholder"),
          },
          {
            name: "bin_id",
            label: t("bin"),
            type: "select",
            options: bins.map((b) => ({
              value: b.id,
              label: b.full_path,
            })),
          },
        ]}
        values={{ search, bin_id: binId }}
        onSearch={navigate}
      />
    </div>
  );
}

export { StockFilters };
