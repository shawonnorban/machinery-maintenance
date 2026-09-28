"use client";

import { useState } from "react";
import { useRouter } from "next/navigation";
import { Search } from "lucide-react";
import { Select } from "@/components/ui/select";
import { DatePicker } from "@/components/ui/date-picker";
import { Input } from "@/components/ui/input";
import { Button } from "@/components/ui/button";
import { useT } from "@/lib/i18n";

/**
 * Mirrors `reports::run.blade.php`'s own filter bar — `filters()`'s
 * vocabulary of period/factory/asset/status/search/location/manufacturer.
 * Each report declares which of these it offers; a filter this bar doesn't
 * recognise is simply never requested by any report's own `filters()`.
 */
function ReportFilters({
  reportKey,
  filters,
  factories,
  assets,
  locations,
  manufacturers,
  from,
  to,
  factoryId,
  status,
  assetId,
  search,
  locationId,
  manufacturerId,
}) {
  const t = useT("report");
  const router = useRouter();
  const [fromValue, setFromValue] = useState(from);
  const [toValue, setToValue] = useState(to);
  const [factoryValue, setFactoryValue] = useState(factoryId);
  const [statusValue, setStatusValue] = useState(status);
  const [assetValue, setAssetValue] = useState(assetId ?? "");
  const [searchValue, setSearchValue] = useState(search ?? "");
  const [locationValue, setLocationValue] = useState(locationId ?? "");
  const [manufacturerValue, setManufacturerValue] = useState(manufacturerId ?? "");

  function apply() {
    const params = new URLSearchParams();
    if (fromValue) params.set("from", fromValue);
    if (toValue) params.set("to", toValue);
    if (factoryValue) params.set("factory_id", factoryValue);
    if (statusValue) params.set("status", statusValue);
    if (assetValue) params.set("asset_id", assetValue);
    if (searchValue) params.set("search", searchValue);
    if (locationValue) params.set("location_id", locationValue);
    if (manufacturerValue) params.set("manufacturer_id", manufacturerValue);
    router.push(`/reports/${reportKey}?${params.toString()}`);
  }

  function applyOnEnter(event) {
    if (event.key === "Enter") apply();
  }

  return (
    <div className="flex flex-wrap items-end gap-3">
      {filters.includes("search") ? (
        <div className="flex flex-col gap-1.5">
          <label className="text-sm font-medium text-foreground">{t("search")}</label>
          <div className="relative w-56">
            <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-foreground-subtle" />
            <Input
              value={searchValue}
              onChange={(e) => setSearchValue(e.target.value)}
              onKeyDown={applyOnEnter}
              placeholder={t("search_placeholder")}
              className="pl-9"
            />
          </div>
        </div>
      ) : null}

      {filters.includes("period") ? (
        <>
          <div className="flex flex-col gap-1.5">
            <label className="text-sm font-medium text-foreground">{t("from")}</label>
            <div className="w-40">
              <DatePicker value={fromValue} onChange={setFromValue} />
            </div>
          </div>
          <div className="flex flex-col gap-1.5">
            <label className="text-sm font-medium text-foreground">{t("to")}</label>
            <div className="w-40">
              <DatePicker value={toValue} onChange={setToValue} />
            </div>
          </div>
        </>
      ) : null}

      {filters.includes("factory") ? (
        <div className="flex flex-col gap-1.5">
          <label className="text-sm font-medium text-foreground">{t("factory")}</label>
          <div className="w-48">
            <Select
              value={factoryValue}
              onValueChange={setFactoryValue}
              placeholder={t("all_factories")}
              options={factories.map((f) => ({ value: f.id, label: f.name }))}
            />
          </div>
        </div>
      ) : null}

      {filters.includes("asset") ? (
        <div className="flex flex-col gap-1.5">
          <label className="text-sm font-medium text-foreground">{t("asset")}</label>
          <div className="w-56">
            <Select
              value={assetValue}
              onValueChange={setAssetValue}
              placeholder={t("all_assets")}
              options={assets.map((a) => ({ value: a.id, label: `${a.asset_code} — ${a.name}` }))}
            />
          </div>
        </div>
      ) : null}

      {filters.includes("location") ? (
        <div className="flex flex-col gap-1.5">
          <label className="text-sm font-medium text-foreground">{t("location")}</label>
          <div className="w-56">
            <Select
              value={locationValue}
              onValueChange={setLocationValue}
              placeholder={t("all_locations")}
              options={locations.map((l) => ({ value: l.id, label: l.full_path }))}
            />
          </div>
        </div>
      ) : null}

      {filters.includes("manufacturer") ? (
        <div className="flex flex-col gap-1.5">
          <label className="text-sm font-medium text-foreground">{t("manufacturer")}</label>
          <div className="w-48">
            <Select
              value={manufacturerValue}
              onValueChange={setManufacturerValue}
              placeholder={t("all_manufacturers")}
              options={manufacturers.map((m) => ({ value: m.id, label: m.name }))}
            />
          </div>
        </div>
      ) : null}

      {filters.includes("status") ? (
        <div className="flex flex-col gap-1.5">
          <label className="text-sm font-medium text-foreground">{t("status")}</label>
          <div className="w-40">
            <Input value={statusValue} onChange={(e) => setStatusValue(e.target.value)} onKeyDown={applyOnEnter} placeholder={t("any")} />
          </div>
        </div>
      ) : null}

      <Button size="default" onClick={apply}>
        {t("apply")}
      </Button>
    </div>
  );
}

export { ReportFilters };
