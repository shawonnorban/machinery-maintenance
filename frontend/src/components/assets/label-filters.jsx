"use client";

import { useState } from "react";
import { useRouter } from "next/navigation";
import { Select } from "@/components/ui/select";
import { Button } from "@/components/ui/button";
import { useT } from "@/lib/i18n";

const STATUSES = [
  "DRAFT", "PURCHASED", "INSTALLED", "COMMISSIONED", "RUNNING", "IDLE",
  "UNDER_MAINTENANCE", "BREAKDOWN", "UNDER_REPAIR", "RETIRED", "SCRAPPED", "LOST",
];

/** An alternative to arriving here with explicit `ids[]` from the Assets list's own "Print labels" bulk action — generate a sheet for a whole factory/status slice instead. */
function LabelFilters({ factories, factoryId, status, hasIds }) {
  const t = useT("asset");
  const router = useRouter();
  const [nextFactoryId, setNextFactoryId] = useState(factoryId);
  const [nextStatus, setNextStatus] = useState(status);

  if (hasIds) {
    return null;
  }

  function generate() {
    const params = new URLSearchParams();
    if (nextFactoryId) params.set("factory_id", nextFactoryId);
    if (nextStatus) params.set("status", nextStatus);
    router.push(`/assets/labels?${params.toString()}`);
  }

  return (
    <div className="mb-4 flex flex-wrap items-end gap-3 print:hidden">
      <div className="flex flex-col gap-1.5">
        <label className="text-sm font-medium text-foreground">{t("factory")}</label>
        <div className="w-52">
          <Select
            value={nextFactoryId}
            onValueChange={setNextFactoryId}
            placeholder={t("all_reachable_factories")}
            options={factories.map((f) => ({ value: f.id, label: f.name }))}
          />
        </div>
      </div>
      <div className="flex flex-col gap-1.5">
        <label className="text-sm font-medium text-foreground">{t("status")}</label>
        <div className="w-52">
          <Select
            value={nextStatus}
            onValueChange={setNextStatus}
            placeholder={t("any_status")}
            options={STATUSES.map((s) => ({ value: s, label: t(`status_${s.toLowerCase()}`) }))}
          />
        </div>
      </div>
      <Button onClick={generate}>{t("generate_sheet")}</Button>
    </div>
  );
}

export { LabelFilters };
