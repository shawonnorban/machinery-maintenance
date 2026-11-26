import { apiFetch } from "@/lib/api-server";
import { RecordsGrid } from "@/components/erp/records-grid";
import { OrderUpload } from "@/components/merchandise/order-upload";

/*
 * Merchandise Desk → Master Data → Style & Order Upload (AnnotechERP 06/01):
 * Records (every order line uploaded, with its batch) and Upload (attach,
 * verify, upload the batch). The tabs switch without a reload.
 */

async function styleOrderUpload({ m, te, tab, canManage }) {
  const rows = await apiFetch("/merchandise/order-uploads");
  const slug = "upload-style-and-order";
  const tabs = [
    { key: "records", label: te("records"), icon: "Table2", count: rows.length },
    { key: "upload", label: m("oup.upload_tab"), icon: "Upload" },
  ];
  return {
    tabs,
    body: async () => {
      if (tab === "upload") return <OrderUpload canManage={canManage} />;
      return (
        <RecordsGrid
          exportName={slug}
          rows={rows}
          rowKey="id"
          header={{ title: m(`screens.${slug}`), subtitle: m("oup.caption"), searchLabel: m("ldp.search"), searchPlaceholder: m("ldp.search"), exportLabel: m("ldp.export") }}
          columns={[
            { key: "batch_no", label: m("oup.col.batch_no"), type: "mono" },
            { key: "uploaded_at", label: m("oup.col.uploaded_at"), type: "datetime" },
            { key: "buyer_ac", label: m("oup.col.buyerac") },
            { key: "buyer", label: m("oup.col.buyer") },
            { key: "style", label: m("oup.col.style") },
            { key: "job_no", label: m("oup.col.job") },
            { key: "po_no", label: m("oup.col.order") },
            { key: "order_no", label: m("oup.col.order_no"), type: "mono" },
            { key: "ship_date", label: m("oup.col.shipdt"), type: "date" },
            { key: "item", label: m("oup.col.item") },
            { key: "colour", label: m("oup.col.gmtcolor") },
            { key: "size", label: m("oup.col.size") },
            { key: "qty", label: m("oup.col.qty"), type: "number" },
            { key: "rate", label: m("oup.col.rate"), type: "number" },
            { key: "total", label: m("oup.col.total"), type: "number" },
          ]}
          emptyText={m("oup.empty")}
        />
      );
    },
  };
}

const UPLOAD_SCREENS = { "upload-style-and-order": styleOrderUpload };
const UPLOAD_MANAGE = { "upload-style-and-order": "merchandise.order.manage" };

export { UPLOAD_MANAGE, UPLOAD_SCREENS };
