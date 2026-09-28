"use client";

import Link from "next/link";
import { Card, CardHeader, CardTitle, CardBody } from "@/components/ui/card";
import { Badge } from "@/components/ui/badge";
import { StatusBadge } from "@/components/ui/status-badge";
import { FormattedDateTime } from "@/components/ui/formatted-date-time";
import { formatNumber } from "@/lib/format";
import { useT } from "@/lib/i18n";

/** The read-only body of a vendor's page: profile sections and recent purchase orders. */
function VendorDetail({ vendor, orders, supplies, canSeeOrders }) {
  const t = useT("vendor");
  const tp = useT("procurement");
  const tc = useT("common");
  const v = vendor;

  return (
    <div className="grid grid-cols-1 gap-4 lg:grid-cols-12">
      <div className="flex flex-col gap-4 lg:col-span-7">
        <Card>
          <CardHeader>
            <CardTitle>{t("contact_section")}</CardTitle>
          </CardHeader>
          <CardBody className="grid grid-cols-1 gap-x-8 gap-y-4 sm:grid-cols-2">
            <Field label={t("contact_name")}>{v.contact_name ?? "—"}</Field>
            <Field label={t("phone")}>{v.phone ?? "—"}</Field>
            <Field label={t("email")}>{v.email ?? "—"}</Field>
            <Field label={t("country_of_origin")}>{v.country_of_origin ?? "—"}</Field>
            <div className="sm:col-span-2">
              <Field label={t("address")}><span className="whitespace-pre-line">{v.address ?? "—"}</span></Field>
            </div>
          </CardBody>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle>{t("registration_section")}</CardTitle>
          </CardHeader>
          <CardBody className="grid grid-cols-1 gap-x-8 gap-y-4 sm:grid-cols-2">
            <Field label={t("tax_reference")}>{v.tax_reference ?? "—"}</Field>
            <Field label={t("bin")}>{v.bin ?? "—"}</Field>
            <Field label={t("trade_license_no")}>{v.trade_license_no ?? "—"}</Field>
            <Field label={t("approval_status")}>
              <StatusBadge status={v.approval_status} label={t(`approval_${v.approval_status?.toLowerCase()}`)} />
            </Field>
            <Field label={t("rating")}>{v.rating ?? "—"}</Field>
          </CardBody>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle>{t("bank_section")}</CardTitle>
          </CardHeader>
          <CardBody className="grid grid-cols-1 gap-x-8 gap-y-4 sm:grid-cols-2">
            <Field label={t("bank")}>{v.bank?.name ?? "—"}</Field>
            <Field label={t("bank_branch")}>{v.bank_branch ?? "—"}</Field>
            <Field label={t("bank_account_no")}>{v.bank_account_no ?? "—"}</Field>
            <Field label={t("bank_swift")}>{v.bank_swift ?? "—"}</Field>
          </CardBody>
        </Card>

        {v.notes ? (
          <Card>
            <CardHeader>
              <CardTitle>{t("notes")}</CardTitle>
            </CardHeader>
            <CardBody className="text-sm whitespace-pre-line">{v.notes}</CardBody>
          </Card>
        ) : null}
      </div>

      <div className="flex flex-col gap-4 lg:col-span-5">
        <Card>
          <CardHeader>
            <CardTitle>{t("procurement_section")}</CardTitle>
          </CardHeader>
          <CardBody className="grid grid-cols-1 gap-x-8 gap-y-4 sm:grid-cols-2">
            <div className="sm:col-span-2">
              <Field label={t("supply_categories")}>
                {v.supply_categories?.length ? (
                  <span className="flex flex-wrap gap-1">
                    {v.supply_categories.map((c) => (
                      <Badge key={c} variant="neutral">{t(`supply_${c.toLowerCase()}`)}</Badge>
                    ))}
                  </span>
                ) : "—"}
              </Field>
            </div>
            <Field label={t("default_currency")}>{v.default_currency ?? "—"}</Field>
            <Field label={t("default_payment_type")}>{v.default_payment_type?.name ?? "—"}</Field>
            <div className="sm:col-span-2">
              <Field label={t("default_payment_terms")}>{v.default_payment_terms ?? "—"}</Field>
            </div>
          </CardBody>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle>{t("purchase_orders")}</CardTitle>
          </CardHeader>
          <CardBody className="flex flex-col gap-2 text-sm">
            {!supplies ? (
              <p className="text-xs text-foreground-muted">{t("not_a_supplier")}</p>
            ) : !canSeeOrders ? null : orders?.data?.length ? (
              <>
                {orders.data.map((o) => (
                  <div key={o.id} className="flex items-center justify-between gap-2">
                    <span className="flex flex-col">
                      <Link href={`/procurement/orders/${o.id}`} className="font-medium text-brand hover:underline">{o.po_no}</Link>
                      <span className="text-xs text-foreground-muted"><FormattedDateTime value={o.po_date} mode="date" /></span>
                    </span>
                    <span className="flex items-center gap-2">
                      <span className="tabular-nums">{formatNumber(o.subtotal, { maximumFractionDigits: 2 })} {o.currency}</span>
                      <StatusBadge status={o.status} label={tp(`statuses.${o.status}`)} />
                    </span>
                  </div>
                ))}
                {orders.meta?.total > orders.data.length ? (
                  <Link href={`/procurement/orders?vendor_id=${v.id}`} className="text-xs text-brand hover:underline">
                    {t("view_all_orders")} ({orders.meta.total})
                  </Link>
                ) : null}
              </>
            ) : (
              <p className="text-xs text-foreground-muted">{t("no_purchase_orders")}</p>
            )}
          </CardBody>
        </Card>

        <Card>
          <CardBody className="grid grid-cols-2 gap-4 text-sm">
            <Field label={t("warranties")}>
              <Link href="/vendors/warranties" className="text-brand hover:underline">{v.warranties_count ?? tc("view")}</Link>
            </Field>
            <Field label={t("contracts")}>
              <Link href="/vendors/service-contracts" className="text-brand hover:underline">{v.contracts_count ?? tc("view")}</Link>
            </Field>
          </CardBody>
        </Card>
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

export { VendorDetail };
