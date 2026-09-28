import Link from "next/link";
import { ArrowLeft, Pencil, Plus } from "lucide-react";
import { apiFetch } from "@/lib/api-server";
import { PageHeader } from "@/components/layout/page-header";
import { buttonVariants } from "@/components/ui/button";
import { StatusBadge } from "@/components/ui/status-badge";
import { cn } from "@/lib/utils";
import { VendorDetail } from "@/components/vendors/vendor-detail";
import { getT } from "@/lib/i18n-server";

/**
 * Mirrors `VendorApiController::show` — the vendor's profile plus, for a
 * supplier, the purchase orders raised against them (Procurement spec §7).
 * Editing lives on its own page; this one is what a purchase order's
 * vendor link lands on.
 */
export default async function VendorDetailPage({ params }) {
  const { vendorId } = await params;

  const [vendor, permissions, t, tc] = await Promise.all([
    apiFetch(`/vendors/${vendorId}`),
    apiFetch("/auth/permissions"),
    getT("vendor"),
    getT("common"),
  ]);

  const granted = new Set(permissions.permissions);
  const canEdit = granted.has("vendor.vendor.update");
  const supplies = ["SUPPLIER", "BOTH"].includes(vendor.vendor_type);
  const canSeeOrders = supplies && granted.has("procurement.order.view_any");

  const orders = canSeeOrders
    ? await apiFetch(`/purchase-orders?vendor_id=${vendorId}&per_page=10`, { includeMeta: true })
    : null;

  return (
    <>
      <PageHeader
        breadcrumb={[{ label: t("vendors"), href: "/vendors" }, { label: vendor.name }]}
        title={
          <span className="flex flex-wrap items-center gap-2">
            {vendor.name}
            <StatusBadge status={vendor.status} label={t(`status_${vendor.status?.toLowerCase()}`)} />
          </span>
        }
        description={`${vendor.code ?? ""}${vendor.code ? " · " : ""}${t(`type_${vendor.vendor_type?.toLowerCase()}`)}`}
        actions={
          <div className="flex flex-wrap items-center gap-2">
            {canSeeOrders && granted.has("procurement.order.create") && vendor.status === "ACTIVE" ? (
              <Link href="/procurement/orders/create" className={cn(buttonVariants({ size: "sm" }))}>
                <Plus /> {t("new_order_for_vendor")}
              </Link>
            ) : null}
            {canEdit ? (
              <Link href={`/vendors/${vendorId}/edit`} className={cn(buttonVariants({ variant: "outline", size: "sm" }))}>
                <Pencil /> {tc("edit")}
              </Link>
            ) : null}
            <Link href="/vendors" className={cn(buttonVariants({ variant: "outline", size: "sm" }))}>
              <ArrowLeft /> {tc("back")}
            </Link>
          </div>
        }
      />

      <VendorDetail vendor={vendor} orders={orders} supplies={supplies} canSeeOrders={canSeeOrders} />
    </>
  );
}
