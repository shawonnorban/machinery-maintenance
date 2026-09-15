import Link from "next/link";
import { Plus } from "lucide-react";
import { apiFetch } from "@/lib/api-server";
import { PageHeader } from "@/components/layout/page-header";
import { buttonVariants } from "@/components/ui/button";
import { cn } from "@/lib/utils";
import { VendorsTable } from "@/components/vendors/vendors-table";
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

  const vendors = await apiFetch(`/vendors?${query.toString()}`, { includeMeta: true });

  return (
    <>
      <PageHeader
        breadcrumb={[{ label: "Vendors" }]}
        title="Vendors"
        description="Suppliers and service providers."
        actions={
          <Link href="/vendors/create" className={cn(buttonVariants({ size: "sm" }))}>
            <Plus /> New vendor
          </Link>
        }
      />

      <VendorsTable vendors={vendors.data} meta={vendors.meta} page={page} search={search} status={status} archiveVendor={archiveVendor} />
    </>
  );
}
