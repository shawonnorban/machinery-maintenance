"use client";

import { useState, useTransition } from "react";
import { useRouter } from "next/navigation";
import { RefreshCw, Search } from "lucide-react";
import { DataTable } from "@/components/ui/data-table";
import { Card, CardBody } from "@/components/ui/card";
import { Alert } from "@/components/ui/alert";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { FormattedDateTime } from "@/components/ui/formatted-date-time";
import { useToastManager } from "@/components/ui/toast";
import { formatNumber } from "@/lib/format";
import { useT } from "@/lib/i18n";

/**
 * How suppliers have been performing (Procurement spec §15).
 *
 * Ranked by overall score, but every row says which measures were behind
 * it. A card built from delivery alone and one built from all four are
 * both "out of 100", and treating them as the same number is how a mill
 * gets blamed for a figure nobody could actually compute.
 */
function SupplierScorecardsPanel({ rows, meta, page, search, canCalculate, onCalculate }) {
  const t = useT("procurement");
  const tc = useT("common");
  const router = useRouter();
  const toastManager = useToastManager();
  const [pending, startTransition] = useTransition();
  const [searchInput, setSearchInput] = useState(search);

  function navigate(next) {
    const params = new URLSearchParams();
    const merged = { page: next.page ?? page, q: next.q ?? searchInput };
    for (const [key, value] of Object.entries(merged)) {
      if (value) params.set(key, String(value));
    }
    router.push(`/procurement/scorecards?${params.toString()}`);
  }

  function recalculate() {
    startTransition(async () => {
      const result = await onCalculate();

      if (result?.status === "error") {
        toastManager.add({ type: "danger", title: result.message ?? tc("something_went_wrong") });
        return;
      }

      toastManager.add({
        type: "success",
        title: t("scorecards_calculated", { count: result.data?.scored ?? 0 }),
      });
    });
  }

  return (
    <div className="flex flex-col gap-4">
      <Alert variant="info">{t("scorecard_hint")}</Alert>

      <Card>
        <CardBody className="flex flex-wrap items-center gap-3 py-3">
          <div className="relative w-full flex-1 min-w-[200px]">
            <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-foreground-subtle" />
            <Input
              value={searchInput}
              onChange={(event) => setSearchInput(event.target.value)}
              onKeyDown={(event) => (event.key === "Enter" ? navigate({ page: 1 }) : null)}
              placeholder={t("scorecard_search_placeholder")}
              className="pl-9"
            />
          </div>
          {canCalculate ? (
            <Button size="sm" onClick={recalculate} disabled={pending}>
              <RefreshCw /> {t("calculate_scorecards")}
            </Button>
          ) : null}
        </CardBody>
      </Card>

      <DataTable
        columns={[
          { key: "vendor", header: t("vendor"), render: (row) => row.vendor?.name ?? "—" },
          {
            key: "overall_score",
            header: t("overall_score"),
            align: "right",
            render: (row) => <ScoreCell value={row.overall_score} />,
          },
          {
            key: "on_time_percent",
            header: t("on_time_percent"),
            align: "right",
            render: (row) => <Percent value={row.on_time_percent} good="high" />,
          },
          {
            key: "quality_reject_percent",
            header: t("quality_reject_percent"),
            align: "right",
            render: (row) => <Percent value={row.quality_reject_percent} good="low" />,
          },
          {
            key: "price_index",
            header: t("price_index"),
            align: "right",
            render: (row) =>
              row.price_index === null ? (
                <span className="text-foreground-muted">—</span>
              ) : (
                <span className={Number(row.price_index) > 1 ? "text-danger" : "text-success"}>
                  {formatNumber(row.price_index, { maximumFractionDigits: 2 })}
                </span>
              ),
          },
          { key: "claim_count", header: t("claim_frequency_percent"), align: "right", render: (row) => row.claim_count },
          {
            key: "components",
            header: t("scorecard_components"),
            render: (row) => (
              <span className="flex flex-wrap gap-1">
                {(row.components ?? []).map((key) => (
                  <Badge key={key} variant="neutral">
                    {t(`components.${key}`)}
                  </Badge>
                ))}
              </span>
            ),
          },
          {
            key: "period",
            header: t("scorecard_period"),
            render: (row) => <FormattedDateTime value={row.period_end} mode="date" />,
          },
        ]}
        rows={rows}
        rowKey={(row) => row.id}
        emptyTitle={t("no_scorecards")}
        emptyDescription={t("scorecard_hint")}
        pagination={{ page: meta.current_page, perPage: meta.per_page, total: meta.total }}
        onPageChange={(nextPage) => navigate({ page: nextPage })}
      />
    </div>
  );
}

/** Out of 100, coloured by band. */
function ScoreCell({ value }) {
  if (value === null) return <span className="text-foreground-muted">—</span>;

  const score = Number(value);
  const tone = score >= 85 ? "text-success" : score >= 60 ? "text-warning" : "text-danger";

  return <span className={`font-medium tabular-nums ${tone}`}>{formatNumber(value, { maximumFractionDigits: 1 })}</span>;
}

/**
 * A percentage that may legitimately be absent — which is different from
 * zero, and is shown as such.
 */
function Percent({ value, good }) {
  if (value === null) return <span className="text-foreground-muted">—</span>;

  const number = Number(value);
  const healthy = good === "high" ? number >= 90 : number <= 2;

  return (
    <span className={`tabular-nums ${healthy ? "text-success" : "text-warning"}`}>
      {formatNumber(value, { maximumFractionDigits: 1 })}%
    </span>
  );
}

export { SupplierScorecardsPanel };
