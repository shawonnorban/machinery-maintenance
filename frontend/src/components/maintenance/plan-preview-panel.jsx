"use client";

import { useEffect, useState } from "react";
import { useT } from "@/lib/i18n";

/**
 * Mirrors `plans::_form.blade.php`'s own live preview panel (Frontend 5.4)
 * — a combined OR rule on a rolling schedule with a non-working-day policy
 * is genuinely hard to reason about, so this turns a guess into a check.
 * Advisory only: a failed preview must never block the form, matching the
 * Blade script's own catch that just clears the list on error.
 */
function PlanPreviewPanel({ scheduleMode, startDate, intervalValue, intervalUnit, nonWorkingDayPolicy, previewAction }) {
  const t = useT("maintenance");
  const [result, setResult] = useState(null);

  useEffect(() => {
    let cancelled = false;

    const timer = setTimeout(async () => {
      try {
        const data = await previewAction({
          schedule_mode: scheduleMode,
          start_date: startDate || null,
          interval_value: Number(intervalValue) || 0,
          interval_unit: intervalUnit,
          non_working_day_policy: nonWorkingDayPolicy,
          factory_id: null,
        });
        if (!cancelled) setResult(data);
      } catch {
        if (!cancelled) setResult(null);
      }
    }, 300);

    return () => {
      cancelled = true;
      clearTimeout(timer);
    };
  }, [scheduleMode, startDate, intervalValue, intervalUnit, nonWorkingDayPolicy, previewAction]);

  const dates = result?.dates ?? [];

  return (
    <div className="sticky top-20 flex flex-col rounded-sm border border-border bg-surface p-4">
      <h3 className="mb-3 text-sm font-semibold text-foreground">{t("preview")}</h3>

      {dates.length === 0 ? (
        <p className="text-sm text-foreground-muted">{result?.note || t("preview_needs_interval")}</p>
      ) : (
        <>
          <ol className="flex flex-col divide-y divide-border">
            {dates.map((entry) => (
              <li key={entry.date} className="flex items-center justify-between py-1.5 text-sm">
                <span className="text-foreground">{entry.date}</span>
                {entry.moved ? <span className="text-xs text-foreground-muted">{t("preview_moved")}</span> : null}
              </li>
            ))}
          </ol>
          {result.note ? <p className="mt-3 text-sm text-foreground-muted">{result.note}</p> : null}
        </>
      )}

      <p className="mt-3 text-xs text-foreground-muted">{t("preview_hint")}</p>
    </div>
  );
}

export { PlanPreviewPanel };
