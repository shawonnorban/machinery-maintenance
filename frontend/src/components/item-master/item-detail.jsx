"use client";

import { useTransition } from "react";
import { useRouter } from "next/navigation";
import Link from "next/link";
import { Card, CardHeader, CardTitle, CardBody } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Badge } from "@/components/ui/badge";
import { StatusBadge } from "@/components/ui/status-badge";
import { useToastManager } from "@/components/ui/toast";
import { formatNumber } from "@/lib/format";
import { useT } from "@/lib/i18n";

function ItemDetail({ item, canManage, setItemActive }) {
  const t = useT("itemmaster");
  const tc = useT("common");
  const router = useRouter();
  const toastManager = useToastManager();
  const [pending, startTransition] = useTransition();

  function toggleActive() {
    startTransition(async () => {
      const result = await setItemActive(item.id, !item.active);
      if (result?.status === "success") {
        toastManager.add({ title: t(item.active ? "item_retired" : "item_restored", { code: item.item_code }), type: "success" });
        router.refresh();
      } else if (result?.status === "error") {
        toastManager.add({ title: result.message, type: "danger" });
      }
    });
  }

  const categoryPath = [item.category?.name, item.sub_category?.name, item.detail_category?.name].filter(Boolean).join(" › ") || "—";

  return (
    <div className="grid grid-cols-1 gap-4 lg:grid-cols-12">
      <div className="flex flex-col gap-4 lg:col-span-7">
        <Card>
          <CardHeader>
            <CardTitle>{t("overview")}</CardTitle>
          </CardHeader>
          <CardBody>
            <div className="grid grid-cols-1 gap-x-8 gap-y-4 sm:grid-cols-2">
              <Field label={t("item_type")}>{t(`item_types.${item.item_type}`)}</Field>
              <Field label={t("tracking_mode")}>{t(`tracking_modes.${item.tracking_mode}`)}</Field>
              <Field label={t("item_code")}><span className="font-mono">{item.item_code}</span></Field>
              <Field label={t("department")}>{item.item_department?.name ?? "—"}</Field>
              <Field label={t("category")}>{categoryPath}</Field>
              <Field label={t("unit")}>{item.uom ? `${item.uom.code} — ${item.uom.name}` : "—"}</Field>
              <Field label={t("purchase_uom")}>{item.purchase_uom ? `${item.purchase_uom.code} — ${item.purchase_uom.name}` : "—"}</Field>
              <Field label={t("brand")}>{item.brand ?? "—"}</Field>
              <Field label={t("model")}>{item.model ?? "—"}</Field>
              <Field label={t("manufacturer")}>{item.manufacturer?.name ?? "—"}</Field>
              <Field label={t("origin")}>{item.origin ?? "—"}</Field>
              <Field label={t("legacy_item_id")}>{item.legacy_item_id ?? "—"}</Field>
              <Field label={t("hazardous")}>{item.hazardous ? tc("yes") : tc("no")}</Field>
              {item.description ? (
                <div className="sm:col-span-2">
                  <Field label={t("description")}>
                    <span className="whitespace-pre-line">{item.description}</span>
                  </Field>
                </div>
              ) : null}
            </div>
          </CardBody>
        </Card>

        {item.item_type === "FABRIC" && item.fabric ? (
          <Card>
            <CardHeader>
              <CardTitle>{t("fabric_spec")}</CardTitle>
            </CardHeader>
            <CardBody>
              <div className="grid grid-cols-1 gap-x-8 gap-y-4 sm:grid-cols-2">
                <Field label={t("fabric_type")}>{item.fabric.fabric_type ? t(`fabric_types.${item.fabric.fabric_type}`) : "—"}</Field>
                <Field label={t("composition")}>{item.fabric.composition ?? "—"}</Field>
                <Field label={t("gsm")}>{item.fabric.gsm ?? "—"}</Field>
                <Field label={t("width")}>{item.fabric.width ?? "—"}</Field>
                <Field label={t("construction")}>{item.fabric.construction ?? "—"}</Field>
                <Field label={t("finish")}>{item.fabric.finish ?? "—"}</Field>
                <Field label={t("shrinkage")}>{item.fabric.shrinkage ?? "—"}</Field>
                <Field label={t("pantone")}>{item.fabric.pantone ?? "—"}</Field>
                <Field label={t("color_fastness")}>{item.fabric.color_fastness ?? "—"}</Field>
              </div>
            </CardBody>
          </Card>
        ) : null}

        {item.item_type === "YARN" && item.yarn ? (
          <Card>
            <CardHeader>
              <CardTitle>{t("yarn_spec")}</CardTitle>
            </CardHeader>
            <CardBody>
              <div className="grid grid-cols-1 gap-x-8 gap-y-4 sm:grid-cols-2">
                <Field label={t("yarn_count")}>{item.yarn.count ?? "—"}</Field>
                <Field label={t("fiber")}>{item.yarn.fiber ?? "—"}</Field>
                <Field label={t("yarn_type")}>{item.yarn.type ?? "—"}</Field>
                <Field label={t("twist")}>{item.yarn.twist ?? "—"}</Field>
                <Field label={t("color")}>{item.yarn.color ?? "—"}</Field>
              </div>
            </CardBody>
          </Card>
        ) : null}

        {item.item_type === "CHEMICAL" && item.chemical ? (
          <Card>
            <CardHeader>
              <CardTitle>{t("chemical_spec")}</CardTitle>
            </CardHeader>
            <CardBody>
              <div className="grid grid-cols-1 gap-x-8 gap-y-4 sm:grid-cols-2">
                <Field label={t("expiry_tracking")}>{item.chemical.expiry_tracking ? tc("yes") : tc("no")}</Field>
                <Field label={t("batch_tracking")}>{item.chemical.batch_tracking ? tc("yes") : tc("no")}</Field>
                {item.chemical.hazard_info ? (
                  <div className="sm:col-span-2">
                    <Field label={t("hazard_info")}>{item.chemical.hazard_info}</Field>
                  </div>
                ) : null}
              </div>
            </CardBody>
          </Card>
        ) : null}
      </div>

      <div className="flex flex-col gap-4 lg:col-span-5">
        {item.tracking_mode === "ROLL" ? (
          <FabricRollsSummary item={item} />
        ) : (
          <Card>
            <CardHeader>
              <CardTitle className="flex items-center gap-2">
                {t("stock")}
                {item.stock?.below_reorder ? <Badge variant="warning">{t("below_reorder")}</Badge> : null}
              </CardTitle>
            </CardHeader>
            <CardBody className="flex flex-col gap-4">
              <div className="grid grid-cols-2 gap-x-8 gap-y-4 sm:grid-cols-4">
                <Field label={t("on_hand")}><span className="text-lg font-semibold tabular-nums">{formatNumber(item.stock?.on_hand)}</span></Field>
                <Field label={t("reserved")}>{formatNumber(item.stock?.reserved)}</Field>
                <Field label={t("available")}>{formatNumber(item.stock?.available)}</Field>
                <Field label={t("stock_value")}>{formatNumber(item.stock?.value, { maximumFractionDigits: 2 })}</Field>
              </div>
              {item.stock?.by_bin?.length ? (
                <table className="w-full text-sm">
                  <thead>
                    <tr className="text-left text-xs text-foreground-muted">
                      <th className="py-1">{t("stock_by_bin")}</th>
                      <th className="py-1">{t("lot_no")}</th>
                      <th className="py-1 text-right">{t("on_hand")}</th>
                      <th className="py-1 text-right">{t("weighted_average_cost")}</th>
                    </tr>
                  </thead>
                  <tbody>
                    {item.stock.by_bin.map((row, index) => (
                      <tr key={index} className="border-t border-border">
                        <td className="py-1">
                          <span className="font-mono">{row.bin?.code}</span>
                          {row.bin?.store ? <span className="ml-1 text-xs text-foreground-muted">{row.bin.store}</span> : null}
                        </td>
                        <td className="py-1">{row.lot_no ?? <span className="text-foreground-muted">{t("no_lot")}</span>}</td>
                        <td className="py-1 text-right tabular-nums">{formatNumber(row.quantity_on_hand)}</td>
                        <td className="py-1 text-right tabular-nums">{formatNumber(row.weighted_average_cost, { maximumFractionDigits: 2 })} {row.currency}</td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              ) : (
                <p className="text-xs text-foreground-muted">{t("no_stock_yet")}</p>
              )}
            </CardBody>
          </Card>
        )}

        <LotsCard item={item} />

        <ReservationsCard item={item} />

        <Card>
          <CardHeader>
            <CardTitle>{t("stock_policy")}</CardTitle>
          </CardHeader>
          <CardBody>
            <div className="grid grid-cols-1 gap-x-8 gap-y-4 sm:grid-cols-2">
              <Field label={t("default_store")}>{item.default_store?.name ?? "—"}</Field>
              <Field label={t("is_returnable")}>{item.is_returnable ? tc("yes") : tc("no")}</Field>
              <Field label={t("minimum_stock")}>{formatNumber(item.minimum_stock)}</Field>
              <Field label={t("reorder_level")}>{formatNumber(item.reorder_level)}</Field>
            </div>
          </CardBody>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle>{t("sourcing")}</CardTitle>
          </CardHeader>
          <CardBody>
            <div className="grid grid-cols-1 gap-x-8 gap-y-4 sm:grid-cols-2">
              <Field label={t("default_vendor")}>{item.default_vendor?.name ?? "—"}</Field>
              <Field label={t("hs_code")}>{item.hs_code ? `${item.hs_code.code} — ${item.hs_code.description}` : "—"}</Field>
            </div>
          </CardBody>
        </Card>

        {canManage ? (
          <Card>
            <CardBody className="flex flex-wrap items-center justify-between gap-3">
              <p className="text-xs text-foreground-muted">{t("retire_hint")}</p>
              <Button variant="outline" size="sm" loading={pending} onClick={toggleActive}>
                {item.active ? t("retire") : t("restore")}
              </Button>
            </CardBody>
          </Card>
        ) : null}
      </div>
    </div>
  );
}

function Field({ label, children }) {
  return (
    <div className="flex flex-col gap-1">
      <span className="text-xs font-medium text-foreground-muted">{label}</span>
      <span className="text-sm text-foreground">{children}</span>
    </div>
  );
}

/**
 * Why stock that is visibly on the shelf cannot be issued (Inventory spec
 * §7). Without this, "available" and "on hand" differ by a number nobody
 * can account for, and the storekeeper's only recourse is to ask around.
 * A claim waiting on stock that has not arrived is listed too — it holds
 * nothing yet, but it is who the goods are for when they land.
 */
/**
 * Which lot to open first (Inventory spec §12).
 *
 * Nearest expiry, then oldest received. An expired lot stays on the list,
 * last and flagged — one that vanished from the screen would be one nobody
 * ever writes off.
 */
function LotsCard({ item }) {
  const t = useT("itemmaster");
  const lots = item.lots ?? [];

  if (lots.length === 0) return null;

  return (
    <Card>
      <CardHeader>
        <CardTitle>{t("lots")}</CardTitle>
      </CardHeader>
      <CardBody className="flex flex-col gap-2 text-sm">
        <p className="text-xs text-foreground-muted">{t("lots_hint")}</p>

        <table className="w-full text-sm">
          <thead>
            <tr className="text-left text-xs text-foreground-muted">
              <th className="py-1">{t("lot_no")}</th>
              <th className="py-1">{t("bin")}</th>
              <th className="py-1">{t("expiry_date")}</th>
              <th className="py-1 text-right">{t("available")}</th>
            </tr>
          </thead>
          <tbody>
            {lots.map((lot, index) => (
              <tr key={`${lot.lot_no}-${lot.bin_id}`} className="border-t border-border">
                <td className="py-1">
                  <span className="font-mono">{lot.lot_no ?? t("no_lot")}</span>
                  {index === 0 && !lot.expired ? (
                    <Badge variant="info" className="ml-2">{t("use_first")}</Badge>
                  ) : null}
                </td>
                <td className="py-1">{lot.bin?.code ?? "—"}</td>
                <td className="py-1">
                  {lot.expiry_date ?? "—"}
                  {lot.expired ? <Badge variant="danger" className="ml-2">{t("expired")}</Badge> : null}
                </td>
                <td className="py-1 text-right tabular-nums">{formatNumber(lot.available)}</td>
              </tr>
            ))}
          </tbody>
        </table>
      </CardBody>
    </Card>
  );
}

function ReservationsCard({ item }) {
  const t = useT("itemmaster");
  const reservations = item.reservations ?? [];

  if (reservations.length === 0) return null;

  return (
    <Card>
      <CardHeader>
        <CardTitle>{t("reserved_for")}</CardTitle>
      </CardHeader>
      <CardBody className="flex flex-col gap-2 text-sm">
        {reservations.map((reservation) => (
          <div key={reservation.id} className="flex flex-wrap items-center justify-between gap-2 border-b border-border pb-2 last:border-0 last:pb-0">
            <div className="flex flex-col gap-1">
              <div className="flex flex-wrap items-center gap-2">
                <StatusBadge status={reservation.status} label={t(`reservation_statuses.${reservation.status}`)} />
                <span className="text-xs text-foreground-muted">{t(`reservation_types.${reservation.reservation_type}`)}</span>
              </div>
              <span className="text-xs text-foreground-muted">
                {reservation.roll_no ?? reservation.bin?.code ?? t("earmark")}
                {reservation.lot_no ? ` · ${reservation.lot_no}` : ""}
                {reservation.reserved_by ? ` · ${reservation.reserved_by}` : ""}
              </span>
            </div>
            <span className="tabular-nums">{formatNumber(reservation.outstanding)}</span>
          </div>
        ))}
      </CardBody>
    </Card>
  );
}

/**
 * The roll-tracked equivalent of the quantity Stock card — a ROLL-tracked
 * item never writes to `item_stock_balances`, so `item.stock` is always
 * null for it; `item.rolls` (`Item::fabricStockSummary()`) is its real
 * stock (Inventory spec §3).
 */
function FabricRollsSummary({ item }) {
  const t = useT("itemmaster");
  const ti = useT("inspection");
  const rolls = item.rolls ?? { roll_count: 0, by_status: {}, net_length: "0.00", net_weight: "0.00" };
  const pending = rolls.by_status.UNDER_INSPECTION ?? 0;

  return (
    <Card>
      <CardHeader>
        <CardTitle className="flex items-center gap-2">
          {ti("rolls")}
          {pending > 0 ? (
            <Link href={`/item-master/inspection?item_id=${item.id}`} className="no-underline">
              <Badge variant="warning">{ti("inspection_queue")}: {pending}</Badge>
            </Link>
          ) : null}
        </CardTitle>
      </CardHeader>
      <CardBody className="flex flex-col gap-4">
        <div className="grid grid-cols-2 gap-x-8 gap-y-4 sm:grid-cols-4">
          <Field label={t("on_hand")}><span className="text-lg font-semibold tabular-nums">{rolls.roll_count}</span></Field>
          <Field label={ti("net_length")}>{formatNumber(rolls.net_length, { maximumFractionDigits: 2 })}</Field>
          <Field label={ti("net_weight")}>{formatNumber(rolls.net_weight, { maximumFractionDigits: 2 })}</Field>
        </div>
        {Object.keys(rolls.by_status).length ? (
          <div className="flex flex-wrap gap-2">
            {Object.entries(rolls.by_status).map(([status, count]) => (
              <Badge key={status} variant={status === "UNDER_INSPECTION" ? "warning" : status === "AVAILABLE" ? "success" : "neutral"}>
                {ti(`statuses.${status}`)}: {count}
              </Badge>
            ))}
          </div>
        ) : (
          <p className="text-xs text-foreground-muted">{t("no_stock_yet")}</p>
        )}
      </CardBody>
    </Card>
  );
}

export { ItemDetail };
