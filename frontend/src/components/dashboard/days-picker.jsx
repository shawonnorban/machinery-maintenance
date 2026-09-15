"use client";

import { useRouter } from "next/navigation";
import { Select } from "@/components/ui/select";
import { useT } from "@/lib/i18n";

/** Mirrors `dashboard/index.blade.php`'s period switcher — 7/30/90 days, the longest offered (a year of raw scanning is a report, not a tile). */
function DaysPicker({ periods, value }) {
  const router = useRouter();
  const t = useT("dashboard");

  return (
    <div className="w-32">
      <Select
        options={periods.map((p) => ({ value: String(p), label: t("last_days", { days: p }) }))}
        value={String(value)}
        onValueChange={(next) => router.push(`/?days=${next}`)}
      />
    </div>
  );
}

export { DaysPicker };
