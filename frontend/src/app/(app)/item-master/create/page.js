import { apiFetch } from "@/lib/api-server";
import { PageHeader } from "@/components/layout/page-header";
import { ItemForm } from "@/components/item-master/item-form";
import { getT } from "@/lib/i18n-server";
import { createItem } from "./actions";

export default async function CreateItemPage() {
  const [options, t, tc] = await Promise.all([apiFetch("/items/form-options"), getT("itemmaster"), getT("common")]);

  return (
    <>
      <PageHeader breadcrumb={[{ label: t("item_library"), href: "/item-master" }, { label: tc("new") }]} title={t("new_item")} />

      <ItemForm options={options} action={createItem} />
    </>
  );
}
