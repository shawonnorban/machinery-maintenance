import Link from "next/link";
import { Plus } from "lucide-react";
import { apiFetch } from "@/lib/api-server";
import { PageHeader } from "@/components/layout/page-header";
import { buttonVariants } from "@/components/ui/button";
import { cn } from "@/lib/utils";
import { ItemsTable } from "@/components/item-master/items-table";
import { getT } from "@/lib/i18n-server";
import { setItemActive, deleteItem } from "./actions";

const FILTERS = ["item_department_id", "item_type", "category_id", "sub_category_id", "detail_category_id", "active"];

/**
 * The item library (Master Data spec §2, §9): department first, then search
 * — a general store has five thousand rows, so nobody scrolls it.
 */
export default async function ItemMasterPage({ searchParams }) {
  const params = await searchParams;
  const page = Number(params.page ?? 1);
  const search = params.q ?? "";
  const filters = Object.fromEntries(FILTERS.map((key) => [key, params[key] ?? ""]));

  const query = new URLSearchParams({ page: String(page) });
  if (search) query.set("search", search);
  for (const [key, value] of Object.entries(filters)) {
    if (value) query.set(key, value);
  }

  const [items, options, permissions, t] = await Promise.all([
    apiFetch(`/items?${query.toString()}`, { includeMeta: true }),
    apiFetch("/items/form-options"),
    apiFetch("/auth/permissions"),
    getT("itemmaster"),
  ]);

  const canManage = new Set(permissions.permissions).has("itemmaster.item.manage");

  return (
    <>
      <PageHeader
        breadcrumb={[{ label: t("item_library") }]}
        title={t("item_library")}
        description={t("page_description")}
        actions={
          canManage ? (
            <Link href="/item-master/create" className={cn(buttonVariants({ size: "sm" }))}>
              <Plus /> {t("new_item")}
            </Link>
          ) : null
        }
      />

      <ItemsTable
        items={items.data}
        meta={items.meta}
        page={page}
        search={search}
        filters={filters}
        options={options}
        canManage={canManage}
        setItemActive={setItemActive}
        deleteItem={deleteItem}
      />
    </>
  );
}
