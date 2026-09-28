import { apiFetch } from "@/lib/api-server";
import { PageHeader } from "@/components/layout/page-header";
import { ItemForm } from "@/components/item-master/item-form";
import { getT } from "@/lib/i18n-server";
import { updateItem } from "./actions";

export default async function EditItemPage({ params }) {
  const { itemId } = await params;

  const [item, options, t, tc] = await Promise.all([
    apiFetch(`/items/${itemId}`),
    apiFetch("/items/form-options"),
    getT("itemmaster"),
    getT("common"),
  ]);

  return (
    <>
      <PageHeader
        breadcrumb={[
          { label: t("item_library"), href: "/item-master" },
          { label: item.name, href: `/item-master/${itemId}` },
          { label: tc("edit") },
        ]}
        title={t("edit_item_title", { code: item.name })}
      />

      <ItemForm item={item} options={options} action={updateItem.bind(null, itemId)} />
    </>
  );
}
