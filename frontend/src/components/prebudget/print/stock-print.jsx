import { nf, usd } from "./format";
import { SheetHead } from "./budget-print";

/*
 * Stock prints — Norban's "Stock Yarn Budget Declaration : <factory>" (the
 * Import / Local / Grand Total summary on the right, then LOCAL YARN and
 * IMPORT YARN with the budget's stock lots) and the store's "Use from Stock :
 * <factory>" (the same lots in the store's lighter cards, Import first).
 * Woven stock, when the budget uses any, gets its own row and tables.
 */

export function StockPrint({ d, kind = "declaration" }) {
  const store = kind === "store";
  const sections = ["YARN", "WOVEN"].filter((s) => s === "YARN" || d.lots.some((l) => l.section === s));
  const label = { YARN: "Yarn", WOVEN: "Woven" };
  const unit = (s) => (s === "YARN" ? "kg" : "yds");
  const qty = (v, s) => `${nf(v, s === "YARN" ? 0 : 2)} ${unit(s)}`;
  const value = (l) => (l.qty ?? 0) * (l.price ?? 0);
  const of = (s, o) => d.lots.filter((l) => l.section === s && (o === "IMPORT" ? l.origin === "IMPORT" : l.origin !== "IMPORT"));
  const total = (rows, k) => rows.reduce((a, l) => a + (k === "value" ? value(l) : l.qty ?? 0), 0);
  const origins = store ? ["IMPORT", "LOCAL"] : ["LOCAL", "IMPORT"];
  const head = store
    ? ["SL", "Purchase Contract No", "Stock Contract No", "Mill Name", "Yarn Count", "LC No", "Lot No"]
    : ["SL", "Budget No", "Purchase Contract No", "Stock Contract No", "Yarn Mill Name", "Yarn Count", "Composition", "LC No", "Lot No"];

  const summary = (
    <table className="g summary">
      <thead>
        <tr>
          <th rowSpan={2}>Category</th>
          <th colSpan={2} className="h-import">{store ? "Import" : "Import Yarn"}</th>
          <th colSpan={2} className="h-local">{store ? "Local" : "Local Yarn"}</th>
          <th colSpan={2} className="h-grand">Grand Total</th>
        </tr>
        <tr>{[0, 1, 2].flatMap((i) => [<th key={`q${i}`} className="num">Qty</th>, <th key={`v${i}`} className="num">Value</th>])}</tr>
      </thead>
      <tbody>
        {sections.map((s) => {
          const im = of(s, "IMPORT");
          const lo = of(s, "LOCAL");
          return (
            <tr key={s}>
              <td className="left strong">{label[s]}</td>
              <td className="num">{qty(total(im, "qty"), s)}</td>
              <td className="num strong">{usd(total(im, "value"), 2)}</td>
              <td className="num">{qty(total(lo, "qty"), s)}</td>
              <td className="num strong">{usd(total(lo, "value"), 2)}</td>
              <td className="num">{qty(total([...im, ...lo], "qty"), s)}</td>
              <td className="num strong">{usd(total([...im, ...lo], "value"), 2)}</td>
            </tr>
          );
        })}
      </tbody>
    </table>
  );

  const detail = (s, o) => {
    const rows = of(s, o);
    const cols = head.map((h) => (s === "WOVEN" && h === "Yarn Count" ? "Style" : s === "WOVEN" && h === "Composition" ? "Fabrication" : h));
    const table = (
      <table className="g">
        <thead>
          <tr>
            {cols.map((h) => <th key={h}>{h}</th>)}
            <th className="num">Qty</th><th className="num">Price</th><th className="num">Value</th>
          </tr>
        </thead>
        <tbody>
          {rows.length ? (
            rows.map((l, i) => (
              <tr key={i}>
                <td>{i + 1}</td>
                {store ? null : <td>{d.budget_no}</td>}
                <td>{l.purchase_contract_no}</td>
                <td>{l.stock_contract_no}</td>
                <td>{l.mill}</td>
                <td>{(s === "YARN" ? l.yarn_count : l.style) || "—"}</td>
                {store ? null : <td>{(s === "YARN" ? l.composition : l.fabrication) || "—"}</td>}
                <td>{l.lc_no}</td>
                <td>{l.lot_no}</td>
                <td className="num">{qty(l.qty, s)}</td>
                <td className="num">{usd(l.price ?? 0, 2)}</td>
                <td className="num">{usd(value(l), 2)}</td>
              </tr>
            ))
          ) : (
            <tr><td colSpan={cols.length + 3} className="empty">No allocations for this origin yet.</td></tr>
          )}
          {rows.length ? (
            <tr className="total">
              <td colSpan={cols.length} className="left">Total</td>
              <td className="num">{qty(total(rows, "qty"), s)}</td>
              <td />
              <td className="num">{usd(total(rows, "value"), 2)}</td>
            </tr>
          ) : null}
        </tbody>
      </table>
    );
    const title = <div className={`nbp-ttl${o === "IMPORT" ? " nbp-ttl-import" : ""}`}>{o} {label[s].toUpperCase()}</div>;
    return store ? (
      <div key={`${s}${o}`} className="sub-card">{title}{table}</div>
    ) : (
      <div key={`${s}${o}`} className="block">{title}{table}</div>
    );
  };

  const body = (
    <>
      <SheetHead title={store ? "Use from Stock" : "Stock Yarn Budget Declaration"} budgetNo={d.budget_no} factory={(d.factories ?? []).join(", ")} buyer={d.buyer} contract={d.contract_no} />
      <div className="block">{summary}</div>
      {sections.flatMap((s) => origins.map((o) => detail(s, o)))}
    </>
  );

  return <div className={`nbp-stock ${store ? "nbp-store" : "nbp-decl"}`}>{store ? <div className="card">{body}</div> : body}</div>;
}
