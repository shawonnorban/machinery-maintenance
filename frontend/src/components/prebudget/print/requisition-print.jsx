import { display } from "@/components/ordersummary/common";
import { dmy, nf } from "./format";

/*
 * Knit/Woven Requisition print — Norban's All Requisition "Print": the
 * header (buyers and brands of the rows), the totals strip on the right, then
 * the shown columns of every filtered (or ticked) requisition, grouped under
 * "STYLE: …" rows. Columns get Norban's print widths (px at its 10px text);
 * a very wide set makes the page scale down (`requisitionScale`).
 */

// Norban's PDF column widths (px), by this sheet's column keys.
const WIDTH = {
  serial_no: 30, sl: 24, entry_type: 34, factory: 40, buyer: 80, brand: 36, season: 40, delivery_date: 54, order_booking_date: 54,
  po_wise_rfi_sd_date: 54, yarn_booking_date: 54, style: 90, order_type: 46, job_no: 66, contract_no: 66, dept: 40,
  artwork: 46, item: 54, wash_type: 40, order_color: 56, fabric_type: 80, fabric_composition: 80, fabrication: 110, gsm: 46,
  sustainability: 76, parts: 46, yarn_type: 54, yarn_count: 48, composition: 96, additional_process: 60, quantity_ticket: 44, pac: 32,
  order_qty: 48, fob_price: 44, fob_value: 58, quantity_pcs: 48, cutting_percent: 42, qty_with_percent: 56, cad_consumption: 56,
  process_loss_percent: 44, cad_with_loss: 56, feeding_percent: 46, sample_qty: 56, bulk_qty: 64, total_req: 64, entry_date: 62,
};
const widthOf = (c) => WIDTH[c.key] ?? 50;
// Kept on one line: dates, numbers and codes; the rest may break between words.
const NOWRAP = new Set(["serial_no", "sl", "entry_type", "delivery_date", "order_booking_date", "po_wise_rfi_sd_date", "yarn_booking_date", "job_no", "contract_no", "gsm", "yarn_count", "quantity_ticket", "pac", "order_qty", "fob_price", "fob_value", "quantity_pcs", "cutting_percent", "qty_with_percent", "cad_consumption", "process_loss_percent", "cad_with_loss", "feeding_percent", "sample_qty", "bulk_qty", "total_req", "entry_date"]);
// Quantities in the row's unit: Knit kg, Woven yds (Norban: "0.044 Kg", "204.10 Kg").
const UNIT = { cad_consumption: 3, cad_with_loss: 3, sample_qty: 2, bulk_qty: 2, total_req: 2 };

/** The page scale: A4 Landscape holds ~1200px at Norban's PDF scale; more columns shrink the page. */
export function requisitionScale(columns) {
  const pageW = (297 - 12) * (96 / 25.4);
  return pageW / Math.max(1200, columns.reduce((a, c) => a + widthOf(c), 0));
}

function cellOf(c, row) {
  const v = row.values?.[c.key];
  if (c.type === "image") return row.artwork_url ? <img src={row.artwork_url} alt="Artwork" /> : "-";
  if (c.type === "date" || ["entry_date"].includes(c.key)) return v ? dmy(v) : "";
  if (UNIT[c.key] !== undefined) {
    if (v === null || v === undefined || v === "") return "";
    return `${nf(v, UNIT[c.key])} ${row.entry_type === "WOVEN" ? "Yds" : "Kg"}`;
  }
  return display(c, v);
}

export function RequisitionPrint({ rows, columns }) {
  const knit = rows.filter((r) => r.entry_type !== "WOVEN");
  const woven = rows.filter((r) => r.entry_type === "WOVEN");
  const sum = (list, k) => list.reduce((a, r) => a + (Number(r.values?.[k]) || 0), 0);
  const distinct = (k) => [...new Set(rows.map((r) => r.values?.[k]).filter(Boolean))].sort((a, b) => String(a).localeCompare(String(b))).join(", ");
  const strip = [
    ["TTL Order Qty", sum(rows, "order_qty"), (v) => `${nf(v)} Pcs`],
    ["TTL Sample Yarn", sum(knit, "sample_qty"), (v) => `${nf(v, 2)} Kg`],
    ["TTL Sample Woven Fabric", sum(woven, "sample_qty"), (v) => `${nf(v, 2)} Yds`],
    ["TTL Bulk Yarn", sum(knit, "bulk_qty"), (v) => `${nf(v, 2)} Kg`],
    ["TTL Bulk Fabric", sum(woven, "bulk_qty"), (v) => `${nf(v, 2)} Yds`],
    ["TTL Req Yarn", sum(knit, "total_req"), (v) => `${nf(v, 2)} Kg`],
    ["TTL Req Woven Fabric", sum(woven, "total_req"), (v) => `${nf(v, 2)} Yds`],
  ].filter(([, v]) => Math.round(v * 100) > 0);
  const total = columns.reduce((a, c) => a + widthOf(c), 0);
  // Grouped by style, in serial order (a style's rows together, where it first comes).
  const ordered = [...rows].sort((a, b) => (a.serial_no ?? 0) - (b.serial_no ?? 0));
  const groups = [];
  const at = new Map();
  for (const r of ordered) {
    const s = String(r.values?.style ?? "");
    if (!at.has(s)) {
      at.set(s, groups.length);
      groups.push({ style: s, rows: [] });
    }
    groups[at.get(s)].rows.push(r);
  }

  return (
    <div className="nbp-req">
      <div className="nbp-title">Knit/Woven Requisition</div>
      <div className="head-line">Buyer: {distinct("buyer")}</div>
      <div className="head-line">Brand: {distinct("brand")}</div>
      {strip.length ? (
        <table className="sum">
          <tbody>
            <tr>{strip.map(([label, v, f]) => <td key={label}><span className="lbl">{label}</span><span className="val">{f(v)}</span></td>)}</tr>
          </tbody>
        </table>
      ) : null}
      <div className="clear" />
      <table className="rq">
        <colgroup>{columns.map((c) => <col key={c.key} style={{ width: `${((widthOf(c) / total) * 100).toFixed(3)}%` }} />)}</colgroup>
        <thead><tr>{columns.map((c) => <th key={c.key}>{c.label}</th>)}</tr></thead>
        <tbody>
          {groups.map((g) => [
            <tr key={`s-${g.style}`} className="style-row"><td colSpan={columns.length}>STYLE: {g.style}</td></tr>,
            ...g.rows.map((r) => (
              <tr key={r.id}>
                {columns.map((c) => <td key={c.key} className={NOWRAP.has(c.key) ? "nw" : undefined}>{cellOf(c, r)}</td>)}
              </tr>
            )),
          ])}
        </tbody>
      </table>
    </div>
  );
}
