"use client";

import { useEffect, useState } from "react";
import { useRouter } from "next/navigation";
import Link from "next/link";
import { Search } from "lucide-react";
import { DataTable } from "@/components/ui/data-table";
import { Select } from "@/components/ui/select";
import { Input } from "@/components/ui/input";
import { Card, CardBody } from "@/components/ui/card";
import { Badge } from "@/components/ui/badge";
import { StatusBadge } from "@/components/ui/status-badge";
import { FormattedDateTime } from "@/components/ui/formatted-date-time";
import { useT } from "@/lib/i18n";

const STATUSES = ["PLANNED", "SHIPPED", "IN_TRANSIT", "ARRIVED", "CLEARED", "CANCELLED"];
const MODES = ["SEA", "AIR", "ROAD", "COURIER"];

/**
 * What is on the water (Procurement spec §10).
 *
 * Sorted by ETA rather than by when the row was filed, because the
 * question this screen answers is "what lands this week, and what should
 * already be here". A consignment past its ETA is called out in the ETA
 * column itself — it is the number a merchandiser is scanning for, and a
 * status column saying "in transit" hides it.
 */
function ShipmentsBoard({ rows, meta, page, search, status, mode, inFlight }) {
  const t = useT("procurement");
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
    const params = new URLSearchParams();
    const merged = {
      page: next.page ?? page,
      q: next.q ?? search,
      status: next.status ?? status,
      mode: next.mode ?? mode,
      in_flight: next.in_flight ?? (inFlight ? "1" : ""),
    };
    for (const [key, value] of Object.entries(merged)) {
      if (value && String(value) !== "1" ? true : Boolean(value)) params.set(key, String(value));
    }
    if (!merged.q) params.delete("q");
    router.push(`/procurement/shipments?${params.toString()}`);
  }

  return (
    <div className="flex flex-col gap-4">
      <Card>
        <CardBody className="flex flex-wrap items-center gap-3 py-3">
          <div className="relative w-full flex-1 min-w-[200px]">
            <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-foreground-subtle" />
            <Input
              value={searchInput}
              onChange={(event) => setSearchInput(event.target.value)}
              placeholder={t("shipment_search_placeholder")}
              className="pl-9"
            />
          </div>
          <div className="w-full sm:max-w-[200px]">
            <Select
              options={[
                { value: "", label: t("all_statuses") },
                ...STATUSES.map((value) => ({ value, label: t(`shipment_statuses.${value}`) })),
              ]}
              value={status}
              onValueChange={(value) => navigate({ status: value, page: 1 })}
            />
          </div>
          <div className="w-full sm:max-w-[160px]">
            <Select
              options={[
                { value: "", label: t("all_modes") },
                ...MODES.map((value) => ({ value, label: t(`shipment_modes.${value}`) })),
              ]}
              value={mode}
              onValueChange={(value) => navigate({ mode: value, page: 1 })}
            />
          </div>
          <div className="w-full sm:max-w-[180px]">
            <Select
              options={[
                { value: "1", label: t("shipments_in_flight") },
                { value: "", label: t("all_shipments") },
              ]}
              value={inFlight ? "1" : ""}
              onValueChange={(value) => navigate({ in_flight: value, page: 1 })}
            />
          </div>
        </CardBody>
      </Card>

      <DataTable
        columns={[
          {
            key: "shipment_no",
            header: t("shipment_no"),
            render: (row) => (
              <div className="flex flex-col">
                <Link
                  href={`/procurement/orders/${row.purchase_order?.id}/import-documents`}
                  className="font-medium text-brand hover:underline"
                >
                  {row.shipment_no}
                </Link>
                <span className="text-xs text-foreground-muted">{row.bl_awb_no ?? row.container_no ?? "—"}</span>
              </div>
            ),
          },
          { key: "purchase_order", header: t("purchase_order"), render: (row) => row.purchase_order?.po_no ?? "—" },
          { key: "vendor", header: t("vendor"), render: (row) => row.vendor?.name ?? "—" },
          { key: "mode", header: t("shipment_mode"), render: (row) => t(`shipment_modes.${row.mode}`) },
          {
            key: "eta",
            header: t("eta"),
            render: (row) => <EtaCell row={row} t={t} />,
          },
          {
            key: "status",
            header: t("status"),
            render: (row) => <StatusBadge status={row.status} label={t(`shipment_statuses.${row.status}`)} />,
          },
          {
            key: "document_status",
            header: t("document_status"),
            render: (row) => (
              <span className="text-sm text-foreground-muted">{t(`document_statuses.${row.document_status}`)}</span>
            ),
          },
        ]}
        rows={rows}
        rowKey={(row) => row.id}
        emptyTitle={t("no_shipments")}
        rowActions={(row) =>
          row.purchase_order
            ? [
                {
                  label: tc("view"),
                  onSelect: () => router.push(`/procurement/orders/${row.purchase_order.id}/import-documents`),
                },
              ]
            : []
        }
        pagination={{ page: meta.current_page, perPage: meta.per_page, total: meta.total }}
        onPageChange={(nextPage) => navigate({ page: nextPage })}
      />
    </div>
  );
}

/** The ETA, and how it is running against it. */
function EtaCell({ row, t }) {
  if (!row.eta) return <span className="text-foreground-muted">—</span>;

  const arrived = Boolean(row.actual_arrival_date);

  return (
    <div className="flex flex-col gap-0.5">
      <FormattedDateTime value={row.eta} mode="date" />
      {arrived ? (
        <span className="text-xs text-foreground-muted">
          {row.delay_days === null
            ? null
            : row.delay_days > 0
              ? t("shipment_delay", { days: row.delay_days })
              : row.delay_days < 0
                ? t("shipment_early", { days: Math.abs(row.delay_days) })
                : t("shipment_on_time")}
        </span>
      ) : row.overdue ? (
        <Badge variant="danger" className="w-fit">
          {t("shipment_overdue")}
        </Badge>
      ) : (
        <span className="text-xs text-foreground-muted">{t("days_to_eta")}: {row.days_to_eta}</span>
      )}
    </div>
  );
}

export { ShipmentsBoard };
