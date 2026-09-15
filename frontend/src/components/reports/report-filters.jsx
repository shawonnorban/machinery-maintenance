"use client";

import { useState } from "react";
import { useRouter } from "next/navigation";
import { Select } from "@/components/ui/select";
import { DatePicker } from "@/components/ui/date-picker";
import { Input } from "@/components/ui/input";
import { Button } from "@/components/ui/button";

/** Mirrors `reports::run.blade.php`'s own filter bar — `filters()`'s fixed vocabulary of period/factory/asset/status. */
function ReportFilters({ reportKey, filters, factories, from, to, factoryId, assetId, status }) {
  const router = useRouter();
  const [fromValue, setFromValue] = useState(from);
  const [toValue, setToValue] = useState(to);
  const [factoryValue, setFactoryValue] = useState(factoryId);
  const [statusValue, setStatusValue] = useState(status);

  function apply() {
    const params = new URLSearchParams();
    if (fromValue) params.set("from", fromValue);
    if (toValue) params.set("to", toValue);
    if (factoryValue) params.set("factory_id", factoryValue);
    if (statusValue) params.set("status", statusValue);
    router.push(`/reports/${reportKey}?${params.toString()}`);
  }

  return (
    <div className="flex flex-wrap items-end gap-3">
      {filters.includes("period") ? (
        <>
          <div className="flex flex-col gap-1.5">
            <label className="text-sm font-medium text-foreground">From</label>
            <div className="w-40">
              <DatePicker value={fromValue} onChange={setFromValue} />
            </div>
          </div>
          <div className="flex flex-col gap-1.5">
            <label className="text-sm font-medium text-foreground">To</label>
            <div className="w-40">
              <DatePicker value={toValue} onChange={setToValue} />
            </div>
          </div>
        </>
      ) : null}

      {filters.includes("factory") ? (
        <div className="flex flex-col gap-1.5">
          <label className="text-sm font-medium text-foreground">Factory</label>
          <div className="w-48">
            <Select
              value={factoryValue}
              onValueChange={setFactoryValue}
              placeholder="All factories"
              options={factories.map((f) => ({ value: f.id, label: f.name }))}
            />
          </div>
        </div>
      ) : null}

      {filters.includes("status") ? (
        <div className="flex flex-col gap-1.5">
          <label className="text-sm font-medium text-foreground">Status</label>
          <div className="w-40">
            <Input value={statusValue} onChange={(e) => setStatusValue(e.target.value)} placeholder="Any" />
          </div>
        </div>
      ) : null}

      <Button size="default" onClick={apply}>
        Apply
      </Button>
    </div>
  );
}

export { ReportFilters };
