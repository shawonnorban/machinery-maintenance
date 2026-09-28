import Link from "next/link";
import { ArrowLeft } from "lucide-react";
import { apiFetch } from "@/lib/api-server";
import { PageHeader } from "@/components/layout/page-header";
import { buttonVariants } from "@/components/ui/button";
import { StatusBadge } from "@/components/ui/status-badge";
import { cn } from "@/lib/utils";
import { FabricBookingDetail } from "@/components/procurement/fabric-booking-detail";
import { getT } from "@/lib/i18n-server";
import { confirmFabricBooking, cancelFabricBooking, convertFabricBooking } from "./actions";

/** Mirrors `FabricBookingApiController::show` (Procurement spec §3). */
export default async function FabricBookingPage({ params }) {
  const { bookingId } = await params;

  const [booking, t, tc] = await Promise.all([
    apiFetch(`/fabric-bookings/${bookingId}`),
    getT("procurement"),
    getT("common"),
  ]);

  return (
    <>
      <PageHeader
        breadcrumb={[
          { label: t("procurement") },
          { label: t("fabric_bookings"), href: "/procurement/fabric-bookings" },
          { label: booking.booking_no },
        ]}
        title={
          <span className="flex flex-wrap items-center gap-2">
            {booking.booking_no}
            <StatusBadge status={booking.status} label={t(`booking_statuses.${booking.status}`)} />
          </span>
        }
        description={`${booking.vendor?.name ?? ""}${booking.style ? ` · ${booking.style.style_no}` : ""}`}
        actions={
          <Link href="/procurement/fabric-bookings" className={cn(buttonVariants({ variant: "outline", size: "sm" }))}>
            <ArrowLeft /> {tc("back")}
          </Link>
        }
      />

      <FabricBookingDetail
        booking={booking}
        actions={{
          confirm: confirmFabricBooking.bind(null, bookingId),
          cancel: cancelFabricBooking.bind(null, bookingId),
          convert: convertFabricBooking.bind(null, bookingId),
        }}
      />
    </>
  );
}
