import { dmy, nf, rate, usd } from "./format";

/*
 * Budget Print — Norban's "Pre-Budget Approval : <factory>" PDF: the header
 * with the Item Wise Summary on its right, the Knit Yarn and Woven Fabric
 * tables with their supplier prices and the total line under each, then the
 * summaries (Fabric Type / Fabrication, Delivery Date) beside the FOB box and
 * the cost breakdown, and the signature names from the approval chain.
 */

/** Pre-Budget No on the right, "<title> : <factory>", Buyer / Contract No. */
export function SheetHead({ title, budgetNo, factory, buyer, contract, right = null }) {
  return (
    <>
      {budgetNo ? <div className="nbp-no">Pre-Budget No: {budgetNo}</div> : null}
      <table style={{ width: "100%" }}>
        <tbody>
          <tr>
            <td style={{ verticalAlign: "top", padding: 0 }}>
              <div className="nbp-title">{title}{factory ? ` : ${factory}` : ""}</div>
              {buyer || contract ? (
                <p className="nbp-details">
                  {buyer ? <span>Buyer: {buyer}</span> : null}
                  {contract ? <span>Contract No: {contract}</span> : null}
                </p>
              ) : null}
            </td>
            {right ? <td style={{ verticalAlign: "top", padding: 0, width: "25%" }}>{right}</td> : null}
          </tr>
        </tbody>
      </table>
    </>
  );
}

// Column widths: Norban's budget page proportions (px), as shares of the table.
const WIDTHS = {
  YARN: { fixed: [37, 81, 186, 100, 167, 96, 70, 96, 96], tail: [96, 96] },
  WOVEN: { fixed: [37, 81, 146, 146, 137, 167, 96, 96, 96, 96], tail: [96, 96] },
};

function Requisition({ section, data }) {
  const yarn = section === "YARN";
  const unit = yarn ? "kg" : "yds";
  const U = yarn ? "Kg" : "Yds";
  const { suppliers = [], lines = [], totals = {} } = data;
  const remarks = lines.some((l) => String(l.remarks ?? "").trim() !== "");
  const w = WIDTHS[section];
  const px = [...w.fixed, ...suppliers.map(() => 123), ...w.tail, ...(remarks ? [174] : [])];
  const sum = px.reduce((a, b) => a + b, 0);
  const pct = (p) => `${((p / sum) * 100).toFixed(3)}%`;
  const short = lines.some((l) => Math.round(l.excess ?? 0) < 0 && l.shortage_action !== "NOT_PURCHASE");
  const price = (l) => l.price ?? l.lowest_price;
  const excess = (v) => `${Math.round(v ?? 0) > 0 ? "+" : ""}${nf(v)} ${unit}`;
  const stock = (qty, value, has) => (has ? (<><span>{nf(qty)} {unit}</span><span className="stock-sep">{usd(value)}</span></>) : "—");

  return (
    <div className="gap">
      <div className="nbp-ttl">{yarn ? "Knit Yarn Requisition" : "Woven Fabric Requisition"}</div>
      <table className="t">
        <colgroup>{px.map((p, i) => <col key={i} style={{ width: pct(p) }} />)}</colgroup>
        <thead>
          <tr>
            <th className="l">S/L</th>
            {yarn ? (<><th>Yarn Count</th><th>Composition</th></>) : (<><th>Style</th><th>Composition</th><th>Fabrication</th></>)}
            <th>{yarn ? `Total Req Yarn (${U})` : `Total Req Fabric (${U})`}</th>
            <th>Use from Stock ({U})</th>
            <th>Need to Purchase ({U})</th>
            <th>PI Qty ({U})</th>
            <th>Total Stock/Now Purchase ({U})</th>
            <th>Excess/Shortage ({U})</th>
            {suppliers.map((s) => <th key={s}>{s}</th>)}
            <th>{yarn ? "Yarn Price (USD/Kg)" : "Fabric Price (USD/Yd)"}</th>
            <th>{yarn ? "Yarn PI Value" : "Fabric PI Value"}</th>
            {remarks ? <th className="l">Remarks</th> : null}
          </tr>
        </thead>
        <tbody>
          {lines.map((l, i) => (
            <tr key={l.key}>
              <td className="c">{i + 1}</td>
              {yarn ? (
                <><td className="c">{l.yarn_count}</td><td className="c">{l.composition}</td></>
              ) : (
                <><td className="c">{l.style}</td><td className="c">{l.composition}</td><td className="c">{l.fabrication}</td></>
              )}
              <td className="c">{nf(l.total_req)} {unit}</td>
              <td className={`c${l.stock_qty ? " bold" : ""}`}>{stock(l.stock_qty, l.stock_value, (l.stock_qty ?? 0) > 0)}</td>
              <td className="c nw">{nf(l.need_to_purchase)} {unit}</td>
              <td className="c nw tint">{nf(l.pi_qty)} {unit}</td>
              <td className="c tint">{nf(l.stock_purchase)} {unit}</td>
              <td className={`c${Math.round(l.excess ?? 0) !== 0 ? " bold" : ""}`}>{excess(l.excess)}</td>
              {suppliers.map((s) => <td key={s} className="c nw">{rate(l.supplier_prices?.[s])}</td>)}
              <td className="c nw tint">{rate(price(l))}</td>
              <td className="c tint">{usd(l.value)}</td>
              {remarks ? <td>{l.remarks}</td> : null}
            </tr>
          ))}
          <tr className="b">
            <td colSpan={yarn ? 3 : 4} className="r">Grand Total</td>
            <td className="c">{nf(totals.total_req)} {unit}</td>
            <td className="c">{stock(totals.stock_qty, totals.stock_value, true)}</td>
            <td className="c">{nf(totals.need_to_purchase)} {unit}</td>
            <td className="c tint">{nf(totals.pi_qty)} {unit}</td>
            <td className="c tint">{nf(totals.stock_purchase)} {unit}</td>
            <td className="c">{excess(totals.excess)}</td>
            {suppliers.map((s) => <td key={s} />)}
            <td className="tint" />
            <td className="c tint">{usd(totals.value)}</td>
            {remarks ? <td /> : null}
          </tr>
        </tbody>
      </table>
      <div className="foot">
        Total Stock Use Value: {usd(totals.stock_value)}
        {short ? (<><span className="sep">|</span>Total Short Purchase Value: {usd(totals.short_value)}</>) : null}
        <span className="sep">|</span>Grand Total Value: {usd(totals.grand_value)}
      </div>
    </div>
  );
}

function Summary({ title, head, rows, total }) {
  return (
    <table className="t">
      <thead><tr>{head.map((h, i) => <th key={h} className={i === 0 ? "l" : undefined}>{h}</th>)}</tr></thead>
      <tbody>
        {rows.map((r, i) => <tr key={i}>{r.map((v, j) => <td key={j} className={j === 0 ? undefined : "c"}>{v}</td>)}</tr>)}
        <tr className="b">{total.map((v, j) => <td key={j} className={j === 0 ? undefined : "c"}>{v}</td>)}</tr>
      </tbody>
    </table>
  );
}

function CostTable({ b }) {
  const c = b.costs;
  const net = b.fob.net || 0;
  const pct = (v) => `${nf(net ? ((v ?? 0) / net) * 100 : 0, 2)}%`;
  const costed = b.check.costing;
  const accDone = (b.accessories?.boms ?? 0) > 0;
  const yarn = b.sections.YARN.lines.length > 0;
  const woven = b.sections.WOVEN.lines.length > 0;
  const row = (label, value, pending = false, note = null) => (
    <tr key={label}>
      <td>
        <div>{label}</div>
        {note ? <div className="note">{note}</div> : null}
      </td>
      <td className="r">{pending ? "Pending" : usd(value)}</td>
      <td className="c">{pct(pending ? 0 : value)}</td>
    </tr>
  );
  const badge = (c.badge ?? 0) > 0;
  return (
    <table className="t">
      <colgroup><col style={{ width: "53%" }} /><col style={{ width: "25%" }} /><col style={{ width: "22%" }} /></colgroup>
      <thead><tr><th className="l">Component</th><th>Cost (USD)</th><th>% of FOB</th></tr></thead>
      <tbody>
        {yarn ? row("Yarn Cost", c.yarn) : null}
        {row("Knitting Cost", c.knitting, !costed, b.costing?.knit_note)}
        {row("Dyeing Finishing Cost", c.dyeing, !costed, b.costing?.dye_note)}
        {(c.yarn_dyeing ?? 0) > 0 ? row("Yarn Dyeing Cost", c.yarn_dyeing) : null}
        {(c.aop ?? 0) > 0 ? row("AOP Cost", c.aop) : null}
        {woven ? row("Woven Fabric Cost", c.woven) : null}
        <tr className="cost-total"><td>Total Fabric Cost</td><td className="r">{usd(c.fabric_total)}</td><td className="c">{pct(c.fabric_total)}</td></tr>
        {row("Accessories Cost", c.accessories, !accDone)}
        {(c.embellishment ?? 0) > 0 ? row("Embellishment Cost", c.embellishment) : null}
        {badge ? row("Badge Cost", c.badge) : null}
        <tr className="cost-total"><td>{badge ? "Total Cost (Fab + Acce + Embl + Badge)" : "Total Cost (Fab + Acce + Embl)"}</td><td className="r">{usd(c.total)}</td><td className="c">{pct(c.total)}</td></tr>
        <tr className="cost-cm b">
          <td style={{ fontSize: 11 }}>CM COST PER DOZEN (AVERAGE $ {nf(b.costing?.totals?.cm_per_dz ?? 0, 2)})</td>
          <td className="r">{costed ? usd(c.cm) : "Pending"}</td>
          <td className="c">{pct(costed ? c.cm : 0)}</td>
        </tr>
        <tr className="cost-cm">
          <td>All Utilities, Rent, Bank charges, Commercial expenses, Transportation &amp; Others</td>
          <td className="r">{usd(c.others)}</td>
          <td className="c">{pct(c.others)}</td>
        </tr>
        <tr className="cost-cm">
          <td className="r bold">CM Cost + Other Utilities Total %</td>
          <td />
          <td className="c bold" style={{ fontSize: 14 }}>{pct((costed ? c.cm : 0) + (c.others ?? 0))}</td>
        </tr>
      </tbody>
    </table>
  );
}

export function BudgetPrint({ b, byFabrication = false }) {
  const factory = (b.factories ?? []).join(", ");
  const item = b.item_summary;
  const yarnSum = byFabrication ? b.fabrication_summary : b.fabric_type_summary;
  const woven = b.sections.WOVEN.lines;
  const wovenFab = Object.values(
    woven.reduce((a, l) => {
      const k = l.fabrication || "Unknown";
      a[k] ??= { fabrication: k, n: 0, req: 0 };
      a[k].n += 1;
      a[k].req += l.total_req ?? 0;
      return a;
    }, {}),
  );
  const wovenDelivery = b.woven_delivery_summary ?? { rows: [], total: {} };

  const itemTable = item.rows.length ? (
    <div>
      <div className="nbp-ttl">Item Wise Summary</div>
      <table className="t item-sum">
        <thead><tr><th className="l">Order Type</th><th>Dept.</th><th>Item</th><th>No of Style</th><th>Order Qty</th></tr></thead>
        <tbody>
          {item.rows.map((r, i) => (
            <tr key={i}><td>{r.order_type}</td><td className="c">{r.dept}</td><td className="c">{r.item}</td><td className="c">{nf(r.no_of_style)}</td><td className="c">{nf(r.order_qty)} Pcs</td></tr>
          ))}
          <tr className="b"><td colSpan={3} className="r">Grand Total</td><td className="c">{nf(item.total.no_of_style)}</td><td className="c">{nf(item.total.order_qty)} Pcs</td></tr>
        </tbody>
      </table>
    </div>
  ) : null;

  return (
    <div className="nbp-budget">
      <SheetHead title="Pre-Budget Approval" budgetNo={b.budget_no} factory={factory} buyer={b.buyer} contract={b.contract_no} right={itemTable} />

      {b.sections.YARN.lines.length ? <Requisition section="YARN" data={b.sections.YARN} /> : null}
      {woven.length ? <Requisition section="WOVEN" data={b.sections.WOVEN} /> : null}

      <table className="layout gap">
        <colgroup><col style={{ width: "33.4%" }} /><col style={{ width: 7 }} /><col style={{ width: "33%" }} /><col style={{ width: 7 }} /><col /></colgroup>
        <tbody>
          <tr>
            <td className="bottom"><div className="nbp-ttl">{byFabrication ? "Fabrication" : "Fabric Type"} Wise QTY / Yarn KG</div></td>
            <td />
            <td className="bottom"><div className="nbp-ttl">Delivery Date Wise Summary (Yarn)</div></td>
            <td />
            <td>
              <table className="fob" style={{ marginBottom: 4 }}>
                <tbody>
                  <tr>
                    <td style={{ padding: 0, border: 0, background: "none", verticalAlign: "top" }}>
                      <table className="fob">
                        <tbody>
                          <tr><th>Gross FOB Value</th><td>{usd(b.fob.gross, 2)}</td></tr>
                          <tr><th>Discount/TPA {nf(b.fob.discount_percent, 2).replace(/\.?0+$/, "")}%</th><td>{usd(b.fob.discount, 2)}</td></tr>
                          <tr><th>Net FOB Value</th><td>{usd(b.fob.net, 2)}</td></tr>
                        </tbody>
                      </table>
                    </td>
                    <td style={{ padding: "0 0 0 6px", border: 0, background: "none", verticalAlign: "top" }}>
                      <table className="fob"><tbody><tr><th>Avg FOB</th><td>{usd(b.fob.avg ?? 0, 2)}</td></tr></tbody></table>
                    </td>
                  </tr>
                </tbody>
              </table>
            </td>
          </tr>
          <tr>
            <td>
              <Summary
                head={[byFabrication ? "Fabrication" : "Fabric Type", "Order Qty (Pcs)", "Total Yarn (Kg)"]}
                rows={yarnSum.rows.map((r) => [(byFabrication ? r.fabrication : r.fabric_type) || "Unknown", `${nf(r.order_qty)} pcs`, `${nf(r.total_req)} kg`])}
                total={["Total", `${nf(yarnSum.total.order_qty)} pcs`, `${nf(yarnSum.total.total_req)} kg`]}
              />
            </td>
            <td />
            <td>
              <Summary
                head={["Delivery Date", "Total No of Style", "Total Order Qty", "Total Order Value", "Total Req Yarn (Kg)"]}
                rows={b.delivery_summary.rows.map((r) => [dmy(r.delivery_date), nf(r.no_of_style), `${nf(r.order_qty)} pcs`, usd(r.fob_value), `${nf(r.total_req)} kg`])}
                total={["Grand Total", nf(b.delivery_summary.total.no_of_style), `${nf(b.delivery_summary.total.order_qty)} pcs`, usd(b.delivery_summary.total.fob_value), `${nf(b.delivery_summary.total.total_req)} kg`]}
              />
            </td>
            <td />
            <td rowSpan={woven.length ? 3 : 1}><CostTable b={b} /></td>
          </tr>
          {woven.length ? (
            <>
              <tr>
                <td className="bottom"><div className="nbp-ttl">Fabrication Wise QTY / Fabric Yds</div></td>
                <td />
                <td className="bottom"><div className="nbp-ttl">Delivery Date Wise Summary (Woven)</div></td>
                <td />
              </tr>
              <tr>
                <td>
                  <Summary
                    head={["Fabrication", "Orders", "Total Fabric (yds)"]}
                    rows={wovenFab.map((r) => [r.fabrication, nf(r.n), `${nf(r.req)} yds`])}
                    total={["Total", nf(wovenFab.reduce((a, r) => a + r.n, 0)), `${nf(wovenFab.reduce((a, r) => a + r.req, 0))} yds`]}
                  />
                </td>
                <td />
                <td>
                  <Summary
                    head={["Delivery Date", "Total No of Style", "Total Order Qty", "Total Order Value", "Total Req Fabric (Yds)"]}
                    rows={wovenDelivery.rows.map((r) => [dmy(r.delivery_date), nf(r.no_of_style), `${nf(r.order_qty)} pcs`, usd(r.fob_value), `${nf(r.total_req)} yds`])}
                    total={["Grand Total", nf(wovenDelivery.total.no_of_style), `${nf(wovenDelivery.total.order_qty)} pcs`, usd(wovenDelivery.total.fob_value), `${nf(wovenDelivery.total.total_req)} yds`]}
                  />
                </td>
                <td />
              </tr>
            </>
          ) : null}
        </tbody>
      </table>
    </div>
  );
}

/** The signature names (Norban: at the foot of the page) — names only, the paper is signed by hand. */
export function BudgetSigns({ b }) {
  const levels = b.approval?.levels ?? [];
  // A step's name only once it is approved — whoever approved it (user, 2026-11-20); before that the line stays blank.
  const who = (l) => (l.state === "APPROVED" ? (l.by ?? "") : "");
  const signs = [
    { title: "Prepared By", name: b.approval?.submitted_by ?? b.prepared_by ?? "" },
    ...(levels.length
      ? levels.map((l, i) => ({ title: i === 0 ? "Checked By" : l.name, name: who(l) }))
      : [{ title: "Checked By" }, { title: "CFO" }, { title: "Managing Director" }]),
  ];
  return (
    <div className="nbp-budget">
      <div className="signs">
        {signs.map((s, i) => (
          <div key={i}>
            <u>{s.title}</u>
            <span>{s.name ?? ""}</span>
          </div>
        ))}
      </div>
    </div>
  );
}
