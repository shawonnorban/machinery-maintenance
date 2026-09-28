import Link from "next/link";
import { ArrowLeft, Pencil } from "lucide-react";
import { apiFetch } from "@/lib/api-server";
import { PageHeader } from "@/components/layout/page-header";
import { buttonVariants } from "@/components/ui/button";
import { Badge } from "@/components/ui/badge";
import { cn } from "@/lib/utils";
import { ItemDetail } from "@/components/item-master/item-detail";
import { getT } from "@/lib/i18n-server";
import { setItemActive } from "../actions";

export default async function ItemDetailPage({ params }) {
  const { itemId } = await params;

  const [item, permissions, t, tc] = await Promise.all([
    apiFetch(`/items/${itemId}`),
    apiFetch("/auth/permissions"),
    getT("itemmaster"),
    getT("common"),
  ]);

  const canManage = new Set(permissions.permissions).has("itemmaster.item.manage");

  return (
    <>
      <PageHeader
        breadcrumb={[{ label: t("item_library"), href: "/item-master" }, { label: item.name }]}
        title={
          <span className="flex flex-wrap items-center gap-2">
            {item.name}
            <Badge variant={item.active ? "success" : "neutral"}>{item.active ? t("active") : t("inactive")}</Badge>
          </span>
        }
        description={`${t("item_code")}: ${item.item_code}`}
        actions={
          <div className="flex flex-wrap items-center gap-2">
            {canManage ? (
              <Link href={`/item-master/${itemId}/edit`} className={cn(buttonVariants({ variant: "outline", size: "sm" }))}>
                <Pencil /> {tc("edit")}
              </Link>
            ) : null}
            <Link href="/item-master" className={cn(buttonVariants({ variant: "outline", size: "sm" }))}>
              <ArrowLeft /> {tc("back")}
            </Link>
          </div>
        }
      />

      <ItemDetail item={item} canManage={canManage} setItemActive={setItemActive} />
    </>
  );
}
