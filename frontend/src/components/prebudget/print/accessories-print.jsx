import { nf, trim } from "./format";
import { SheetHead } from "./budget-print";

/*
 * Accessories Print — Norban's "Accessories Budget : <factory>" PDF: the
 * totals strip on the right, then one worksheet table for every style of the
 * budget's BOMs (or of one BOM): the style's own cells (SL … Style Value)
 * spanning its item rows, Brand / Job No / Style No reading bottom-to-top,
 * one row per accessory and the style's yellow TOTAL row. The columns are the
 * company's Acc Budget Column Setup (shown ones, their widths as shares).
 */

const pct2 = (v) => `${nf(v, 2)}%`;
const blank = (v) => v === null || v === undefined || v === "";

/** A cell as Norban prints it (by column), else by the column's type. */
function cell(c, v, line, style) {
  switch (c.key) {
    case "item_order_qty": return nf(blank(v) ? style.order_qty : v);
    case "extra_percent": return pct2(v ?? 0);
    case "qty_with": return nf(v);
    case "cost_pc":
    case "cost_dz": return trim(v, 6);
    case "value": return nf(v, 2);
    case "acc_pct": return pct2(v ?? 0);
    case "rate": return trim(v, 8);
    case "body_cons":
    case "item_cons": return blank(v) || Number(v) === 0 ? "-" : trim(v, 4);
    case "item_code":
    case "remarks": return blank(v) ? "-" : String(v);
    default:
      if (blank(v)) return c.type === "text" ? "-" : "";
      if (c.type === "percent") return pct2(v);
      if (c.type === "number" || c.type === "currency") return Number.isFinite(Number(v)) ? trim(v, Math.max(c.decimals ?? 0, 4)) : String(v);
      return Array.isArray(v) ? v.join(", ") : String(v);
  }
}
const ALIGN = { item_order_qty: "c", extra_percent: "c", qty_with: "c val", cost_pc: "r", cost_dz: "r", value: "r b", acc_pct: "c", rate: "r", unit: "c", body_cons: "c", item_cons: "c", remarks: "c", nominated: "c" };
const align = (c) => ALIGN[c.key] ?? (c.type === "number" || c.type === "percent" || c.type === "currency" ? "r" : "");

function styleCell(c, s, index, apiBase) {
  const v = s.cells?.[c.key];
  if (c.key === "sl") return { cls: "b", body: index + 1 };
  if (c.key === "order_qty") return { cls: "", body: `${nf(v)} Pcs` };
  if (c.key === "fob") return { cls: "", body: trim(v, 4) };
  if (c.key === "style_value") return { cls: "val", body: `$ ${nf(v, 2)}` };
  if (c.key === "artwork") return { cls: "", body: v ? <img src={`${apiBase}${v}`} alt="Artwork" /> : <span className="muted">-</span> };
  return { cls: "b", body: blank(v) ? "N/A" : String(v) };
}

export function AccessoriesPrint({ acc, factory = "", bomNo = null, apiBase = "" }) {
  const boms = (acc.summary?.boms ?? []).filter((m) => !bomNo || m.bom_no === bomNo);
  const styles = boms.flatMap((m) => m.styles);
  const cols = (acc.columns ?? []).filter((c) => !c.hidden && c.access !== "none");
  const styleCols = cols.filter((c) => c.style);
  const itemCols = cols.filter((c) => !c.style);
  const widthSum = cols.reduce((a, c) => a + (Number(c.width) || 80), 0);
  const firstKpi = itemCols.findIndex((c) => c.kpi);
  const totalOrderQty = styles.reduce((a, s) => a + (s.order_qty ?? 0), 0);
  const totalOrderValue = styles.reduce((a, s) => a + (s.style_value ?? 0), 0);
  const totalCost = styles.reduce((a, s) => a + (s.value ?? 0), 0);

  return (
    <div className="nbp-acc">
      <SheetHead title="Accessories Budget" budgetNo={acc.budget_no} factory={factory} buyer={acc.buyer} contract={acc.contract_no} />
      {!styles.length ? (
        <p>No Accessories Budget has been created for this budget yet.</p>
      ) : (
        <>
          <table className="strip">
            <tbody>
              <tr>
                <td><span className="lbl">Total Order Qty</span><span className="val">{nf(totalOrderQty)} Pcs</span></td>
                <td><span className="lbl">Total Order Value</span><span className="val">${nf(totalOrderValue, 2)}</span></td>
                <td><span className="lbl">Total Accessories Cost</span><span className="val">${nf(totalCost, 2)}</span></td>
                <td><span className="lbl">Avg Cost Per Pcs</span><span className="val">${nf(totalOrderQty > 0 ? totalCost / totalOrderQty : 0, 4)}</span></td>
              </tr>
            </tbody>
          </table>
          <div className="clear" />
          <table className="ws">
            <colgroup>{cols.map((c) => <col key={c.key} style={{ width: `${(((Number(c.width) || 80) / widthSum) * 100).toFixed(3)}%` }} />)}</colgroup>
            <thead>
              <tr>{cols.map((c) => <th key={c.key} className={c.vertical ? "rot" : undefined}>{c.vertical ? <span>{c.label}</span> : c.label}</th>)}</tr>
            </thead>
            <tbody>
              {styles.map((s, si) => {
                const lines = s.lines?.length ? s.lines : [null];
                const span = lines.length + 1;
                return [
                  ...lines.map((line, li) => (
                    <tr key={`${si}-${li}`}>
                      {li === 0
                        ? styleCols.map((c) => {
                            const sc = styleCell(c, s, si, apiBase);
                            const rot = c.vertical && c.key !== "sl";
                            return <td key={c.key} rowSpan={span} className={`style-cell ${sc.cls}${rot ? " rot" : ""}`}>{rot ? <span>{sc.body}</span> : sc.body}</td>;
                          })
                        : null}
                      {line === null ? (
                        <td colSpan={itemCols.length} className="c muted">No accessories added</td>
                      ) : (
                        itemCols.map((c) => <td key={c.key} className={align(c)}>{cell(c, line[c.key], line, s)}</td>)
                      )}
                    </tr>
                  )),
                  <tr key={`${si}-t`} className="style-total">
                    {firstKpi !== 0 ? <td colSpan={firstKpi < 0 ? itemCols.length : firstKpi} className="r">TOTAL</td> : null}
                    {(firstKpi < 0 ? [] : itemCols.slice(firstKpi)).map((c, i) =>
                      c.kpi ? (
                        <td key={c.key} className={c.key === "acc_pct" ? "c" : "r"}>{cell(c, s.totals?.[c.key], null, s)}</td>
                      ) : (
                        <td key={c.key || i} />
                      ),
                    )}
                  </tr>,
                ];
              })}
            </tbody>
          </table>
        </>
      )}
    </div>
  );
}
