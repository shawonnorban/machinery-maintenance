import Link from "next/link";
import { StatCard } from "@/components/ui/stat-card";
import { Card, CardHeader, CardTitle, CardBody } from "@/components/ui/card";
import { buttonVariants } from "@/components/ui/button";
import { StatusDonut } from "@/components/dashboard/status-donut";
import { cn } from "@/lib/utils";
import { DashboardSectionHeader } from "@/components/dashboard/section-header";
import { getT } from "@/lib/i18n-server";
import { Warehouse, Boxes, TriangleAlert, Siren, PackageX } from "lucide-react";

function currency(value) {
  return Number(value).toLocaleString(undefined, { maximumFractionDigits: 0 });
}

function trimmed(value) {
  const n = Number(value);
  return Number.isInteger(n) ? String(n) : String(n).replace(/\.?0+$/, "");
}

/** Mirrors `dashboard/_store.blade.php` — critical-spare shortage is kept separate from ordinary low stock, since one stops a critical machine and the other doesn't. */
async function StorePanel({ data }) {
  const t = await getT("dashboard");

  return (
    <section className="flex flex-col gap-4">
      <DashboardSectionHeader icon={<Warehouse />} title={t("store")} tone="info" />

      <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <StatCard label={t("stock_value")} icon={<Boxes />} tone="brand" value={currency(data.stock_value)} />
        <StatCard label={t("low_stock")} icon={<TriangleAlert />} tone={data.low_stock > 0 ? "warning" : "success"} value={data.low_stock} supportingText={t("low_stock_hint")} />
        <StatCard label={t("critical_low")} icon={<Siren />} tone={data.critical_low > 0 ? "danger" : "success"} value={data.critical_low} supportingText={t("critical_low_hint")} />
        <StatCard label={t("out_of_stock")} icon={<PackageX />} tone={data.out_of_stock > 0 ? "danger" : "success"} value={data.out_of_stock} />
      </div>

      <div className="grid grid-cols-1 gap-4 lg:grid-cols-12">
        <Card className="lg:col-span-5">
          <CardHeader>
            <CardTitle>{t("part_health")}</CardTitle>
          </CardHeader>
          <CardBody className="flex flex-col items-center gap-4 sm:flex-row">
            <StatusDonut
              segments={[
                { key: "healthy", label: t("healthy"), value: Math.max(data.total_parts - data.low_stock, 0), color: "var(--success)" },
                { key: "low_stock", label: t("low_stock"), value: Math.max(data.low_stock - data.out_of_stock, 0), color: "var(--warning)" },
                { key: "out_of_stock", label: t("out_of_stock"), value: data.out_of_stock, color: "var(--danger)" },
              ]}
              total={data.total_parts}
              totalLabel={t("total_parts")}
            />
          </CardBody>
        </Card>

        <Card className="lg:col-span-7">
          <CardHeader className="items-center">
            <CardTitle>{t("store")}</CardTitle>
            <Link href="/inventory/stock" className={cn(buttonVariants({ variant: "outline", size: "sm" }))}>
              {t("low_stock")}
            </Link>
          </CardHeader>
          <CardBody>
            <dl className="flex flex-col gap-2 text-sm">
              <div className="flex justify-between">
                <dt>{t("reserved")}</dt>
                <dd className="tabular">{trimmed(data.reserved_quantity)}</dd>
              </div>
              <div className="flex justify-between">
                <dt>{t("active_reservations")}</dt>
                <dd className="tabular">{data.active_reservations}</dd>
              </div>
              <div className="flex justify-between">
                <dt>{t("parts_issued")}</dt>
                <dd className="tabular">{currency(data.issued_value)}</dd>
              </div>
            </dl>
          </CardBody>
        </Card>
      </div>
    </section>
  );
}

export { StorePanel };
