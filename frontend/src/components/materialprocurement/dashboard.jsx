"use client";

import Link from "next/link";
import {
  ArrowRight,
  BadgeCheck,
  CircleCheck,
  ClipboardList,
  Database,
  FilePen,
  FilePlus2,
  Handshake,
  MessagesSquare,
  Package,
  Store,
  Truck,
  Warehouse,
} from "lucide-react";
import { cn } from "@/lib/utils";
import { buttonVariants } from "@/components/ui/button";
import { useT } from "@/lib/i18n";

/*
 * Material Procurement → Procurement Dashboard (AnnotechERP 08/02), as
 * Brickland draws it: the complete workflow from master data to GRN, each
 * step opening its screen; eight counts; the latest requisitions (each
 * opening in My Requisitions) and the deliveries being followed up.
 */

const card = "rounded-xl border border-border bg-surface";
const base = "/material-procurement";
const dateFmt = new Intl.DateTimeFormat("en-GB", {
  day: "2-digit",
  month: "short",
  year: "numeric",
  timeZone: "Asia/Dhaka",
});
const day = (iso) => (iso ? dateFmt.format(new Date(iso)) : "—");
const qty = (n) =>
  Number(n ?? 0).toLocaleString("en-US", { maximumFractionDigits: 3 });
const money = (p) =>
  p
    ? `${p.currency} ${Number(p.unit_price).toLocaleString("en-US", { minimumFractionDigits: 2, maximumFractionDigits: 4 })}`
    : "—";

/** The eight steps and the screen each opens, as Brickland links them. */
const STEPS = [
  ["master", Database, "fabric-master"],
  ["requisition", FilePlus2, "create-requisition"],
  ["suppliers", Store, "supplier-portal-access"],
  ["quotation", MessagesSquare, "price-review"],
  ["review", BadgeCheck, "price-review"],
  ["handover", Handshake, "assigned-materials"],
  ["followup", Truck, "delivery-tracking"],
  ["grn", Warehouse, "assigned-materials"],
];

const COUNTS = [
  ["draft", FilePen, "bg-warning-subtle text-warning"],
  ["submitted", ClipboardList, "bg-info-subtle text-info"],
  ["open_lines", Store, "bg-success-subtle text-success"],
  ["quotations", MessagesSquare, "bg-danger-subtle text-danger"],
  ["awaiting_review", CircleCheck, "bg-success-subtle text-success"],
  ["negotiation", MessagesSquare, "bg-brand-subtle text-brand"],
  ["approved_lines", Package, "bg-warning-subtle text-warning"],
  ["moving", Truck, "bg-danger-subtle text-danger"],
];

const prTone = {
  DRAFT: "bg-surface-muted text-foreground-muted",
  SUBMITTED: "bg-info-subtle text-info",
  APPROVED: "bg-success-subtle text-success",
  REJECTED: "bg-danger-subtle text-danger",
};
const lineTone = (s) =>
  ["FULLY_RECEIVED", "IN_HOUSE", "APPROVED"].includes(s)
    ? "bg-success-subtle text-success"
    : s === "PARTIALLY_RECEIVED"
      ? "bg-danger-subtle text-danger"
      : "bg-info-subtle text-info";

function Chip({ tone, children }) {
  return (
    <span
      className={cn(
        "rounded-md px-1.5 py-0.5 text-xs font-medium whitespace-nowrap",
        tone,
      )}
    >
      {children}
    </span>
  );
}

/**
 * Procurement Dashboard.
 *
 * @param {{ data: object, canRequisition: boolean, canTrack: boolean }} props
 */
function ProcurementDashboard({ data, canRequisition, canTrack }) {
  const t = useT("materialprocurement");
  const d = (k, p) => t(`dash.${k}`, p);
  const head = (k, right) => (
    <th
      className={cn(
        "px-3 py-2.5 text-left text-xs font-semibold uppercase",
        right && "text-right",
      )}
    >
      {d(`col.${k}`)}
    </th>
  );

  return (
    <div className="flex flex-col gap-4">
      <section className={cn(card, "flex flex-col gap-3 p-5")}>
        <div>
          <h2 className="text-base font-semibold text-foreground">
            {d("workflow_title")}
          </h2>
          <p className="mt-0.5 text-[13px] text-foreground-muted">
            {d("workflow_line")}
          </p>
        </div>
        <ol className="flex flex-wrap items-center gap-2">
          {STEPS.map(([k, Icon, slug], i) => (
            <li key={k} className="flex items-center gap-2">
              <Link
                href={`${base}/${slug}`}
                className="flex items-center gap-2 rounded-lg border border-border px-3 py-2 text-[13px] font-medium text-foreground hover:bg-surface-muted"
              >
                <span className="flex size-6 items-center justify-center rounded-md bg-surface-muted text-xs tabular-nums text-foreground-muted">
                  {i + 1}
                </span>
                <Icon className="size-4 text-foreground-muted" aria-hidden />
                {d(`step.${k}`)}
              </Link>
              {i < STEPS.length - 1 ? (
                <ArrowRight
                  className="size-4 text-foreground-muted"
                  aria-hidden
                />
              ) : null}
            </li>
          ))}
        </ol>
      </section>

      <section className="grid grid-cols-2 gap-3 lg:grid-cols-4">
        {COUNTS.map(([k, Icon, tone]) => (
          <div key={k} className={cn(card, "flex items-center gap-3 p-4")}>
            <span
              className={cn(
                "flex size-10 shrink-0 items-center justify-center rounded-lg",
                tone,
              )}
            >
              <Icon className="size-5" aria-hidden />
            </span>
            <div className="flex flex-col">
              <span className="text-[13px] text-foreground-muted">
                {d(`count.${k}`)}
              </span>
              <span className="text-xl font-semibold tabular-nums text-foreground">
                {data.counts[k]}
              </span>
            </div>
          </div>
        ))}
      </section>

      <div className="grid gap-4 xl:grid-cols-2">
        <section className={cn(card, "min-w-0 overflow-hidden")}>
          <div className="flex flex-wrap items-start justify-between gap-3 p-5">
            <div>
              <h2 className="text-base font-semibold text-foreground">
                {d("latest_title")}
              </h2>
              <p className="mt-0.5 text-[13px] text-foreground-muted">
                {d("latest_hint", {
                  items: data.active_items,
                  with: data.suppliers_with_login,
                  suppliers: data.active_suppliers,
                })}
              </p>
            </div>
            {canRequisition ? (
              <Link
                href={`${base}/create-requisition`}
                className={cn(buttonVariants({ size: "sm" }))}
              >
                <FilePlus2 className="size-4" aria-hidden />
                {d("new_requisition")}
              </Link>
            ) : null}
          </div>
          <div className="overflow-x-auto">
            <table className="w-full min-w-[560px] text-sm">
              <thead className="bg-brand text-brand-foreground">
                <tr>
                  {head("pr_no")}
                  {head("type")}
                  {head("order")}
                  {head("lines", true)}
                  {head("date")}
                  {head("status")}
                </tr>
              </thead>
              <tbody>
                {data.requisitions.length === 0 ? (
                  <tr>
                    <td
                      colSpan={6}
                      className="px-3 py-8 text-center text-foreground-muted"
                    >
                      {d("no_requisitions")}
                    </td>
                  </tr>
                ) : null}
                {data.requisitions.map((r) => (
                  <tr key={r.pr_no} className="border-t border-border">
                    <td className="px-3 py-2.5 whitespace-nowrap">
                      <Link
                        href={`${base}/my-requisitions?open=${encodeURIComponent(r.pr_no)}`}
                        className="font-medium text-brand hover:underline"
                      >
                        {r.pr_no}
                      </Link>
                    </td>
                    <td className="px-3 py-2.5">
                      <Chip
                        tone={
                          r.req_type === "BY_ORDER"
                            ? "bg-info-subtle text-info"
                            : "bg-surface-muted text-foreground"
                        }
                      >
                        {t(`pr.type.${r.req_type}`)}
                      </Chip>
                    </td>
                    <td className="px-3 py-2.5 whitespace-nowrap text-foreground">
                      {r.order_no ?? "—"}
                    </td>
                    <td className="px-3 py-2.5 text-right tabular-nums text-foreground">
                      {r.lines}
                    </td>
                    <td className="px-3 py-2.5 whitespace-nowrap text-foreground">
                      {day(r.req_date)}
                    </td>
                    <td className="px-3 py-2.5">
                      <Chip tone={prTone[r.status]}>
                        {t(`pr.status.${r.status}`)}
                      </Chip>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </section>

        <section className={cn(card, "min-w-0 overflow-hidden")}>
          <div className="flex flex-wrap items-start justify-between gap-3 p-5">
            <div>
              <h2 className="text-base font-semibold text-foreground">
                {d("deliveries_title")}
              </h2>
              <p className="mt-0.5 text-[13px] text-foreground-muted">
                {d("deliveries_hint", { n: data.done })}
              </p>
            </div>
            {canTrack ? (
              <Link
                href={`${base}/delivery-tracking`}
                className={cn(
                  buttonVariants({ variant: "outline", size: "sm" }),
                )}
              >
                {d("tracking")}
              </Link>
            ) : null}
          </div>
          <div className="overflow-x-auto">
            <table className="w-full min-w-[560px] text-sm">
              <thead className="bg-brand text-brand-foreground">
                <tr>
                  {head("material")}
                  {head("supplier")}
                  {head("price", true)}
                  {head("balance", true)}
                  {head("status")}
                </tr>
              </thead>
              <tbody>
                {data.deliveries.length === 0 ? (
                  <tr>
                    <td
                      colSpan={5}
                      className="px-3 py-8 text-center text-foreground-muted"
                    >
                      {d("no_deliveries")}
                    </td>
                  </tr>
                ) : null}
                {data.deliveries.map((r) => (
                  <tr key={r.line_id} className="border-t border-border">
                    <td className="px-3 py-2.5 font-medium text-foreground">
                      {r.item_name}
                    </td>
                    <td className="px-3 py-2.5 text-foreground">
                      {r.supplier ?? "—"}
                    </td>
                    <td className="px-3 py-2.5 text-right whitespace-nowrap tabular-nums text-foreground">
                      {money(r.price)}
                    </td>
                    <td className="px-3 py-2.5 text-right whitespace-nowrap tabular-nums text-foreground">
                      {qty(r.balance)} {r.uom}
                    </td>
                    <td className="px-3 py-2.5">
                      <Chip tone={lineTone(r.status)}>
                        {t(`pr.line_status.${r.status}`)}
                      </Chip>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </section>
      </div>
    </div>
  );
}

export { ProcurementDashboard };
