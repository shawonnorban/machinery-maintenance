import Link from "next/link";
import { Plus } from "lucide-react";
import { apiFetch } from "@/lib/api-server";
import { PageHeader } from "@/components/layout/page-header";
import { buttonVariants } from "@/components/ui/button";
import { cn } from "@/lib/utils";
import { VendorsTable } from "@/components/vendors/vendors-table";
import { getT } from "@/lib/i18n-server";
import { archiveVendor } from "./actions";

/** Mirrors `VendorController::index` — a supplier serves whichever factories buy from them, so this is company-wide, not factory-scoped. */
export default async function VendorsPage({ searchParams }) {
  const params = await searchParams;
  const page = Number(params.page ?? 1);
  const search = params.q ?? "";
  const status = params.status ?? "";

  const query = new URLSearchParams({ page: String(page) });
  if (search) query.set("q", search);
  if (status) query.set("status", status);

  const [vendors, t] = await Promise.all([
    apiFetch(`/vendors?${query.toString()}`, { includeMeta: true }),
    getT("vendor"),
  ]);

  return (
    <>
      <PageHeader
        breadcrumb={[{ label: t("vendors") }]}
        title={t("vendors")}
        description={t("page_description")}
        actions={
          <Link href="/vendors/create" className={cn(buttonVariants({ size: "sm" }))}>
            <Plus /> {t("new_vendor")}
          </Link>
        }
      />

      <VendorsTable vendors={vendors.data} meta={vendors.meta} page={page} search={search} status={status} archiveVendor={archiveVendor} />
    </>
  );
}
