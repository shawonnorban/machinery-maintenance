import { apiFetch } from "@/lib/api-server";
import { PageHeader } from "@/components/layout/page-header";
import { FabricBookingForm } from "@/components/procurement/fabric-booking-form";
import { searchItems } from "@/components/procurement/search-items-action";
import { getT } from "@/lib/i18n-server";
import { createFabricBooking } from "./actions";

/** Mirrors `FabricBookingApiController::store` (Procurement spec §3). */
export default async function CreateFabricBookingPage() {
  const [options, t] = await Promise.all([
    apiFetch("/fabric-bookings/form-options"),
    getT("procurement"),
  ]);

  return (
    <>
      <PageHeader
        breadcrumb={[
          { label: t("procurement") },
          { label: t("fabric_bookings"), href: "/procurement/fabric-bookings" },
          { label: t("new_booking") },
        ]}
        title={t("new_booking")}
        description={t("booking_hint")}
      />

      <FabricBookingForm options={options} styles={options.styles} searchItems={searchItems} action={createFabricBooking} />
    </>
  );
}
