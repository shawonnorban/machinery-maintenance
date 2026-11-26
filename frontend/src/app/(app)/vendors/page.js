import { apiFetch } from "@/lib/api-server";
import { ScreenHeader } from "@/components/layout/screen-header";
import { VendorsTable } from "@/components/vendors/vendors-table";
import { getT } from "@/lib/i18n-server";
import { archiveVendor } from "./actions";
import { applyPerPage } from "@/lib/per-page";

/** Mirrors `VendorController::index` — a supplier serves whichever factories buy from them, so this is company-wide, not factory-scoped. */
export default async function VendorsPage({ searchParams }) {
  const params = await searchParams;
  const page = Number(params.page ?? 1);
  const search = params.q ?? "";
  const status = params.status ?? "";

  const query = new URLSearchParams({ page: String(page) });
  await applyPerPage(query, "/vendors");
  if (search) query.set("q", search);
  if (status) query.set("status", status);

  const [vendors, t] = await Promise.all([
    apiFetch(`/vendors?${query.toString()}`, { includeMeta: true }),
    getT("vendor"),
  ]);

  return (
    <>
      <ScreenHeader
        url="/vendors"
        title={t("vendors")}
        description={t("page_description")}
      />

      <VendorsTable addHref="/vendors/create" vendors={vendors.data} meta={vendors.meta} page={page} search={search} status={status} archiveVendor={archiveVendor} />
    </>
  );
}
