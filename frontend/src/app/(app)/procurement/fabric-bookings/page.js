import Link from "next/link";
import { Plus } from "lucide-react";
import { apiFetch } from "@/lib/api-server";
import { PageHeader } from "@/components/layout/page-header";
import { buttonVariants } from "@/components/ui/button";
import { cn } from "@/lib/utils";
import { FabricBookingsTable } from "@/components/procurement/fabric-bookings-table";
import { getT } from "@/lib/i18n-server";

/** Mirrors `FabricBookingApiController::index` (Procurement spec §3). */
export default async function FabricBookingsPage({ searchParams }) {
  const params = await searchParams;
  const page = Number(params.page ?? 1);
  const search = params.q ?? "";
  const status = params.status ?? "";

  const query = new URLSearchParams({ page: String(page) });
  if (search) query.set("search", search);
  if (status) query.set("status", status);

  const [bookings, permissions, t] = await Promise.all([
    apiFetch(`/fabric-bookings?${query.toString()}`, { includeMeta: true }),
    apiFetch("/auth/permissions"),
    getT("procurement"),
  ]);

  const canManage = new Set(permissions.permissions).has("procurement.booking.manage");

  return (
    <>
      <PageHeader
        breadcrumb={[{ label: t("procurement") }, { label: t("fabric_bookings") }]}
        title={t("fabric_bookings")}
        description={t("booking_hint")}
        actions={
          canManage ? (
            <Link href="/procurement/fabric-bookings/create" className={cn(buttonVariants({ size: "sm" }))}>
              <Plus /> {t("new_booking")}
            </Link>
          ) : null
        }
      />

      <FabricBookingsTable rows={bookings.data} meta={bookings.meta} page={page} search={search} status={status} />
    </>
  );
}
