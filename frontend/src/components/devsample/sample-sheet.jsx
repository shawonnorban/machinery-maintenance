"use client";

import { useMemo } from "react";
import { CheckCircle2, Clock3, DollarSign, Layers, Package, Send, Tag, XCircle } from "lucide-react";
import { OrderSheet } from "@/components/ordersummary/sheet";
import { StatCard } from "@/components/ui/stat-card";
import { useT } from "@/lib/i18n";

/*
 * Dev Sample Status → View All / Add Dev Sample (Column Setup, user
 * 2026-11-21): Order Summary's sheet on the development samples — every
 * column as Column Setup makes it, a filter on each header, tick rows and
 * Bulk Edit to change them in place, copy and paste like Excel; Add Dev
 * Sample types new samples into the sheet. Norban's ten summary cards
 * are on the Dashboard.
 */

/** The columns View All starts with: Norban's default view. */
const DEFAULT_COLUMNS = [
  "serial_no", "buyer", "styling", "artwork", "job_no", "items", "sample_type", "fabrication", "colours", "sample_qty",
  "buyer_requested_date", "submission_deadline", "sample_status", "order_confirmation", "remarks", "last_update",
];

const qty = (n) => Number(n ?? 0).toLocaleString("en-US");
const money = (n) => `$${Number(n ?? 0).toLocaleString("en-US", { minimumFractionDigits: 2, maximumFractionDigits: 4 })}`;

function SummaryCards({ stats }) {
  const t = useT("devsample");
  const s = stats;
  const cards = [
    ["total_records", qty(s.total_records), "brand", Layers],
    ["submission_pending", qty(s.submission_pending), "warning", Clock3],
    ["submitted", qty(s.submitted), "success", Send],
    ["order_dropped", qty(s.order_dropped), "danger", XCircle],
    ["order_confirmed", qty(s.order_confirmed), "info", CheckCircle2],
    ["total_sample_qty", `${qty(s.total_sample_qty)} ${t("help.pcs")}`, "brand", Package],
    ["total_possible_qty", `${qty(s.total_possible_qty)} ${t("help.pcs")}`, "warning", Package],
    ["avg_target_price", money(s.avg_target_price), "success", Tag],
    ["avg_fob_price", money(s.avg_fob_price), "danger", Tag],
    ["total_fob_value", money(s.total_fob_value), "info", DollarSign],
  ];

  return (
    <div className="grid grid-cols-2 gap-3 md:grid-cols-5">
      {cards.map(([key, value, tone, Icon]) => (
        <StatCard key={key} label={t(`kpi.${key}`)} value={value} tone={tone} icon={<Icon />} />
      ))}
    </div>
  );
}

function SampleSheet({ mode, meta, data, queryString, act }) {
  const config = useMemo(
    () => ({
      viewKey: "devsample",
      listPath: "/dev-sample-status/list",
      artworkUrl: "/api/dev-sample/image",
      titleKeys: ["styling", "buyer"],
      defaultColumns: DEFAULT_COLUMNS,
      rowHeight: { new: 50, list: 60 },
      // View All keeps the grid as tall as Order Summary's full one even with few samples (user, 2026-11-21).
      fillHeight: { list: true },
      // Last Price Quote Date shows who entered it under the date (Norban's list).
      companions: { last_price_quote_date: "last_price_quote_by" },
      // Job No in the company's form (26NCL00001); no factory column here, so the dialog picks the factory.
      jobNo: { column: "job_no", factory: null, url: "/api/job-numbers?module=ds" },
      excelUrl: null, // Order Summary's book-layout Excel is not the samples'.
      printUrl: null,
    }),
    [],
  );

  // The summary cards live on the Dashboard (user, 2026-11-21); the sheet stands alone, like Order Summary's.
  return <OrderSheet mode={mode} meta={meta} data={data} queryString={queryString} act={act} config={config} />;
}

export { SampleSheet, SummaryCards };
