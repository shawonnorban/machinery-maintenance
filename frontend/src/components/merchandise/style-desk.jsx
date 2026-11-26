"use client";

import { useState, useTransition } from "react";
import { Copy, Layers, Loader2, Plus, X } from "lucide-react";
import { cn } from "@/lib/utils";
import { Button } from "@/components/ui/button";
import { useToastManager } from "@/components/ui/toast";
import { useT } from "@/lib/i18n";
import { StyleForm } from "@/components/merchandise/style-form";
import { StyleGallery } from "@/components/merchandise/style-gallery";
import { StyleItemDesk } from "@/components/merchandise/style-item-desk";
import { StyleColourGrid } from "@/components/merchandise/style-colour-grid";
import { StyleFabrication } from "@/components/merchandise/style-fabrication";
import { StyleServices } from "@/components/merchandise/style-services";
import { StyleBom } from "@/components/merchandise/style-bom";
import { StyleFabricMapping } from "@/components/merchandise/style-fabric-mapping";
import { StyleOrders } from "@/components/merchandise/style-orders";
import { StylePacking } from "@/components/merchandise/style-packing";

/** The desk's tabs, in Brickland's order, as each is built. */
const PANELS = ["registration", "colours", "fabrication", "services", "bom", "orders", "yarn_fabric", "packing"];

/**
 * A style's sheet with its Item/Combo/Model (Fresh / Copy) desk (AnnotechERP
 * 06/05). Opening the desk, closing it and moving between its tabs happen on
 * the page — no navigation, no reload, the url left as it is; a tab's data is
 * read from the server when the tab opens.
 *
 * @param {{ row: object, look: object, items: object, images: object[], canManage: boolean, recordsHref: string, actions: object }} props
 */
function StyleSheetDesk({ row, look, items, images, canManage, orders = { view: false, manage: false }, recordsHref, actions }) {
  const t = useT("merchandise");
  const m = (k, p) => t(`master.itm.${k}`, p);
  const toastManager = useToastManager();
  const [desk, setDesk] = useState(null);
  const [panel, setPanel] = useState("registration");
  const [loaded, setLoaded] = useState({});
  const [loading, startLoading] = useTransition();
  const style = { id: row.id, style_no: row.style_no, is_set: row.is_set };

  /** A tab's data, read from the server each time the tab opens, so it shows what was last saved. */
  function open(next) {
    setPanel(next);
    const loaders = { colours: actions.loadStyleColours, fabrication: actions.loadStyleFabrication, services: actions.loadStyleServices, bom: actions.loadStyleBom, yarn_fabric: actions.loadStyleFabricMapping, orders: actions.loadStyleOrders, packing: actions.loadStylePacking };
    if (!loaders[next]) return;
    setLoaded((all) => ({ ...all, [next]: null }));
    startLoading(async () => {
      const result = await loaders[next](row.id);
      if (result?.status === "success") setLoaded((all) => ({ ...all, [next]: result.data }));
      else toastManager.add({ title: result?.message, type: "danger" });
    });
  }

  const deskButton = (mode, Icon) => (
    <Button type="button" variant={desk === mode ? "primary" : "outline"} onClick={() => setDesk(desk === mode ? null : mode)}>
      <Icon /> {m(mode)}
    </Button>
  );

  return (
    <>
      <StyleForm
        mode="sheet"
        row={row}
        look={look}
        save={actions.saveStyle}
        recordsHref={recordsHref}
        extra={
          <>
            {canManage ? deskButton("fresh", Plus) : null}
            {canManage ? deskButton("copy", Copy) : null}
            <StyleGallery style={row.style_no} images={images} />
          </>
        }
      />
      {desk ? (
        <div className="mt-4 rounded-lg border border-border bg-surface">
          <div className="flex items-center justify-between gap-3 border-b border-border px-5 py-3">
            <h2 className="flex items-center gap-2 text-base font-semibold text-foreground">
              <Layers className="size-4" /> {m(desk === "copy" ? "title_copy" : "title_fresh")}
              <span className="text-[13px] font-normal text-foreground-muted">{m("for", { style: row.style_no })}</span>
            </h2>
            <Button variant="outline" size="sm" onClick={() => setDesk(null)}>
              <X /> {m("close")}
            </Button>
          </div>
          <div className="flex flex-wrap gap-1 border-b border-border px-5" role="tablist">
            {PANELS.filter((key) => key !== "orders" || orders.view).map((key) => (
              <button
                key={key}
                type="button"
                role="tab"
                aria-selected={key === panel}
                onClick={() => open(key)}
                className={cn("-mb-px border-b-2 px-3 py-3 text-sm font-semibold", key === panel ? "border-brand text-brand" : "border-transparent text-foreground hover:text-brand")}
              >
                {m(`tab_${key}`)}
              </button>
            ))}
          </div>
          {panel === "registration" ? (
            <StyleItemDesk key={desk} mode={desk} style={style} data={items} save={actions.saveStyleItem} canManage={canManage} />
          ) : !loaded[panel] ? (
            <div className="flex items-center justify-center gap-2 py-16 text-sm text-foreground-muted">{loading ? <Loader2 className="size-4 animate-spin" /> : null}</div>
          ) : panel === "colours" ? (
            <StyleColourGrid styleId={row.id} data={loaded.colours} save={actions.saveStyleColours} canManage={canManage} />
          ) : panel === "fabrication" ? (
            <StyleFabrication styleId={row.id} data={loaded.fabrication} save={actions.saveStyleFabrication} canManage={canManage} />
          ) : panel === "services" ? (
            <StyleServices styleId={row.id} data={loaded.services} save={actions.saveStyleServices} canManage={canManage} />
          ) : panel === "orders" ? (
            <StyleOrders styleId={row.id} data={loaded.orders} create={actions.createStyleOrder} update={actions.updateStyleOrder} canManage={orders.manage} />
          ) : panel === "packing" ? (
            <StylePacking styleId={row.id} data={loaded.packing} save={actions.saveStylePacking} canManage={canManage} />
          ) : panel === "yarn_fabric" ? (
            <StyleFabricMapping styleId={row.id} data={loaded.yarn_fabric} save={actions.saveStyleFabricMapping} canManage={canManage} />
          ) : (
            <StyleBom styleId={row.id} styleNo={row.style_no} data={loaded.bom} save={actions.saveStyleBom} approve={actions.approveStyleBom} canManage={canManage} />
          )}
        </div>
      ) : null}
    </>
  );
}

export { StyleSheetDesk };
