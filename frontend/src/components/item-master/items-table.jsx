"use client";

import { useEffect, useState, useTransition } from "react";
import { useRouter } from "next/navigation";
import Link from "next/link";
import { Search } from "lucide-react";
import { DataTable } from "@/components/ui/data-table";
import { Select } from "@/components/ui/select";
import { Input } from "@/components/ui/input";
import { StatusBadge } from "@/components/ui/status-badge";
import { ConfirmDialog } from "@/components/ui/confirm-dialog";
import { Card, CardBody } from "@/components/ui/card";
import { useToastManager } from "@/components/ui/toast";
import { useT } from "@/lib/i18n";

function ItemsTable({ items, meta, page, search, filters, options, canManage, setItemActive, deleteItem }) {
  const t = useT("itemmaster");
  const tc = useT("common");
  const router = useRouter();
  const toastManager = useToastManager();
  const [searchInput, setSearchInput] = useState(search);
  const [deleting, setDeleting] = useState(null);
  const [pending, startTransition] = useTransition();

  useEffect(() => {
    if (searchInput === search) {
      return undefined;
    }
    const timeout = setTimeout(() => navigate({ q: searchInput, page: 1 }), 350);
    return () => clearTimeout(timeout);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [searchInput]);

  function navigate(next) {
    const params = new URLSearchParams({
      page: String(next.page ?? page),
      q: next.q ?? search,
      item_department_id: next.item_department_id ?? filters.item_department_id,
      item_type: next.item_type ?? filters.item_type,
      category_id: next.category_id ?? filters.category_id,
      sub_category_id: next.sub_category_id ?? filters.sub_category_id,
      detail_category_id: next.detail_category_id ?? filters.detail_category_id,
      active: next.active ?? filters.active,
    });
    for (const [key, value] of [...params.entries()]) {
      if (!value) params.delete(key);
    }
    router.push(`/item-master?${params.toString()}`);
  }

  function toggleActive(item) {
    startTransition(async () => {
      const result = await setItemActive(item.id, !item.active);
      if (result?.status === "success") {
        toastManager.add({ title: t(item.active ? "item_retired" : "item_restored", { code: item.item_code }), type: "success" });
        router.refresh();
      } else if (result?.status === "error") {
        toastManager.add({ title: result.message, type: "danger" });
      }
    });
  }

  function runDelete() {
    startTransition(async () => {
      const result = await deleteItem(deleting.id);
      if (result?.status === "success") {
        toastManager.add({ title: t("item_deleted", { code: deleting.item_code }), type: "success" });
        setDeleting(null);
        router.refresh();
      } else if (result?.status === "error") {
        toastManager.add({ title: result.message, type: "danger" });
        setDeleting(null);
      }
    });
  }

  const departmentOptions = [
    { value: "", label: t("all_departments") },
    ...(options.item_departments ?? []).map((d) => ({ value: d.id, label: d.name })),
  ];
  const typeOptions = [
    { value: "", label: t("all_item_types") },
    ...(options.item_types ?? []).map((type) => ({ value: type, label: t(`item_types.${type}`) })),
  ];
  // Three independent pickers, one per level — a storekeeper who knows the
  // sub-category should not have to find its parent first. Levels are
  // worked out from the tree: top has no parent, sub's parent is top,
  // detail's parent is a sub.
  const categories = options.categories ?? [];
  const byId = new Map(categories.map((c) => [c.id, c]));
  const level = (c) => (!c.parent_id ? 1 : byId.get(c.parent_id)?.parent_id ? 3 : 2);
  const atLevel = (n) => categories.filter((c) => level(c) === n).map((c) => ({ value: c.id, label: c.name }));
  const categoryOptions = [{ value: "", label: t("all_categories") }, ...atLevel(1)];
  const subCategoryOptions = [{ value: "", label: t("all_sub_categories") }, ...atLevel(2)];
  const detailCategoryOptions = [{ value: "", label: t("all_detail_categories") }, ...atLevel(3)];
  const activeOptions = [
    { value: "", label: t("all_items") },
    { value: "1", label: t("active_only") },
    { value: "0", label: t("inactive_only") },
  ];

  return (
    <div className="flex flex-col gap-4">
      <Card>
        <CardBody className="flex flex-wrap items-center gap-3 py-3">
          <div className="relative w-full flex-1 min-w-[200px]">
            <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-foreground-subtle" />
            <Input value={searchInput} onChange={(event) => setSearchInput(event.target.value)} placeholder={t("search_placeholder")} className="pl-9" />
          </div>
          <div className="w-full sm:max-w-[220px]">
            <Select options={departmentOptions} placeholder={t("department")} value={filters.item_department_id} onValueChange={(value) => navigate({ item_department_id: value, page: 1 })} />
          </div>
          <div className="w-full sm:max-w-[170px]">
            <Select options={typeOptions} placeholder={t("item_type")} value={filters.item_type} onValueChange={(value) => navigate({ item_type: value, page: 1 })} />
          </div>
          <div className="w-full sm:max-w-[200px]">
            <Select options={categoryOptions} placeholder={t("category")} value={filters.category_id} onValueChange={(value) => navigate({ category_id: value, page: 1 })} />
          </div>
          <div className="w-full sm:max-w-[200px]">
            <Select options={subCategoryOptions} placeholder={t("sub_category")} value={filters.sub_category_id} onValueChange={(value) => navigate({ sub_category_id: value, page: 1 })} />
          </div>
          <div className="w-full sm:max-w-[200px]">
            <Select options={detailCategoryOptions} placeholder={t("detail_category")} value={filters.detail_category_id} onValueChange={(value) => navigate({ detail_category_id: value, page: 1 })} />
          </div>
          <div className="w-full sm:max-w-[150px]">
            <Select options={activeOptions} placeholder={t("status")} value={filters.active} onValueChange={(value) => navigate({ active: value, page: 1 })} />
          </div>
        </CardBody>
      </Card>

      <DataTable
        columns={[
          {
            key: "name",
            header: t("name"),
            render: (item) => (
              <Link href={`/item-master/${item.id}`} className="font-medium text-brand hover:underline">
                {item.name}
              </Link>
            ),
          },
          { key: "item_code", header: t("item_code"), render: (item) => <span className="font-mono text-xs">{item.item_code}</span> },
          {
            key: "description",
            header: t("description"),
            render: (item) => <span className="line-clamp-2 max-w-[260px] text-xs text-foreground-muted">{item.description ?? "—"}</span>,
          },
          { key: "item_department", header: t("department"), render: (item) => item.item_department?.name ?? "—" },
          { key: "category", header: t("category"), render: (item) => item.category?.name ?? "—" },
          { key: "sub_category", header: t("sub_category"), render: (item) => item.sub_category?.name ?? "—" },
          { key: "detail_category", header: t("detail_category"), render: (item) => item.detail_category?.name ?? "—" },
          { key: "uom", header: t("unit"), render: (item) => item.uom?.code ?? "—" },
          { key: "model", header: t("model"), render: (item) => item.model ?? "—" },
          { key: "brand", header: t("brand"), render: (item) => item.brand ?? "—" },
          { key: "origin", header: t("origin"), render: (item) => item.origin ?? "—" },
          {
            key: "active",
            header: t("status"),
            render: (item) => <StatusBadge status={item.active ? "ACTIVE" : "INACTIVE"} label={item.active ? t("active") : t("inactive")} />,
          },
        ]}
        rows={items}
        rowKey={(item) => item.id}
        emptyTitle={t("no_items")}
        emptyDescription={search || Object.values(filters).some(Boolean) ? t("try_different_filter") : t("no_items_hint")}
        // View is always offered: somebody who may only read the library
        // still needs a way into an item, and the name link alone is easy
        // to miss in a row this wide.
        rowActions={(item) => [
          { label: tc("view"), onSelect: () => router.push(`/item-master/${item.id}`) },
          ...(canManage
            ? [
                { label: tc("edit"), onSelect: () => router.push(`/item-master/${item.id}/edit`) },
                { label: item.active ? t("retire") : t("restore"), onSelect: () => toggleActive(item) },
                { label: t("delete"), destructive: true, onSelect: () => setDeleting(item) },
              ]
            : []),
        ]}
        pagination={{ page: meta.current_page, perPage: meta.per_page, total: meta.total }}
        onPageChange={(nextPage) => navigate({ page: nextPage })}
      />

      <ConfirmDialog
        open={Boolean(deleting)}
        onOpenChange={() => setDeleting(null)}
        title={t("delete")}
        description={t("delete_confirm", { code: deleting?.item_code })}
        confirmLabel={t("delete")}
        loading={pending}
        onConfirm={runDelete}
      />
    </div>
  );
}

export { ItemsTable };
