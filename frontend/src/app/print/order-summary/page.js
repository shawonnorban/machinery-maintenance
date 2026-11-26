import { apiFetch } from "@/lib/api-server";
import { getT } from "@/lib/i18n-server";
import { PrintFrame } from "@/components/prebudget/print/print-frame";
import { OrderSummaryPrint, orderSummaryScale } from "@/components/ordersummary/print-sheet";

export const metadata = { title: "Order-Summary" };

// View All's filters (f / r / q / season), sort, the shown columns (cols) and the ticked rows (selected).
const listQuery = (q) => {
  const out = new URLSearchParams();
  for (const [k, v] of Object.entries(q)) {
    if (/^(f|r)\[/.test(k) || ["q", "season", "sort", "dir"].includes(k)) (Array.isArray(v) ? v : [v]).forEach((x) => out.append(k, x));
  }
  out.set("per_page", "all");
  return out.toString();
};

/** Order Summary → Print (A4 Landscape, opens the print dialog): what View All shows, laid out like the book. */
export default async function OrderSummaryPrintPage({ searchParams }) {
  const q = (await searchParams) ?? {};
  const t = await getT("ordersummary");
  try {
    const [meta, data] = await Promise.all([apiFetch("/order-summary/meta"), apiFetch(`/order-summary/orders?${listQuery(q)}`)]);
    const want = String(q.cols ?? "").split(",").filter(Boolean);
    const byKey = new Map(meta.columns.filter((c) => c.access !== "none").map((c) => [c.key, c]));
    const columns = (want.length ? want : meta.columns.map((c) => c.key)).map((k) => byKey.get(k)).filter(Boolean);
    const picked = new Set(String(q.selected ?? "").split(",").filter(Boolean));
    const rows = picked.size ? data.rows.filter((r) => picked.has(r.id)) : data.rows;
    return (
      <PrintFrame title={t("sheet.print_title")} scale={orderSummaryScale(columns)}>
        <OrderSummaryPrint rows={rows} columns={columns} groups={meta.groups} totals={data.totals} />
      </PrintFrame>
    );
  } catch (e) {
    return <p style={{ padding: 24, fontFamily: "Segoe UI, Arial, sans-serif" }}>{e?.message ?? "Error"}</p>;
  }
}
