import { nf } from "./format";
import { SheetHead } from "./budget-print";

/*
 * Cost Print — Norban's "Knitting & Dyeing / Embellishment Cost : <factory>"
 * PDF: the Dollar Rate, then the Knitting & Dyeing table (the averages over
 * grey cells and the totals in black above its column headers) and the
 * Embellishment / CM table with "CM Cost Per Doz" on its right. A cost this
 * budget has no rate for is left out, as Norban does. (Badge: this ERP's own
 * Knit/Dying Costing part, printed the same way when it has a rate.)
 */

const tk = (v) => `${nf(v, 2)} Tk`;
const usd2 = (v) => `$${nf(v, 2)}`;

export function CostPrint({ b }) {
  const c = b.costing;
  const t = c.totals;
  const fabrics = c.fabrics ?? [];
  const styles = c.styles ?? [];
  const has = (rows, a, z) => rows.some((r) => (r[a] ?? 0) > 0 || (r[z] ?? 0) > 0);
  const show = {
    knit: has(fabrics, "knit_rate", "knit_bdt"),
    dye: has(fabrics, "dye_rate", "dye_bdt"),
    yarnDye: has(fabrics, "yarn_dye_rate", "yarn_dye_bdt"),
    aop: has(fabrics, "aop_rate", "aop_bdt"),
    emb: has(styles, "emb_per_dz", "emb_usd"),
    cm: has(styles, "cm_per_dz", "cm_usd"),
    badge: has(styles, "badge_per_dz", "badge_usd"),
  };
  const knitAvg = t.qty > 0 ? t.knit_bdt / t.qty : 0;
  const dyeAvg = t.qty > 0 ? t.dye_bdt / t.qty : 0;
  const cmPerDz = t.dz > 0 ? t.cm_usd / t.dz : 0;
  const kdTitle = [show.knit && "Knitting", show.dye && "Dyeing", show.yarnDye && "Yarn Dyeing", show.aop && "AOP"].filter(Boolean).join(" & ");
  const parts = [
    show.knit && { avg: knitAvg > 0 ? ["Avg. knitting Cost/per kgs", `${nf(knitAvg, 2)} Tk/kg`] : null, tot: [t.knit_bdt, t.knit_usd], head: ["Knitting Cost in Kg BDT", "Total Knitting Value (BDT)", "Knitting Value (USD)"], k: ["knit_rate", "knit_bdt", "knit_usd"] },
    show.dye && { avg: dyeAvg > 0 ? ["Avg. Dyeing + Heat Seat Cost/per kgs", `${nf(dyeAvg, 2)} Tk/kg`] : null, tot: [t.dye_bdt, t.dye_usd], head: ["Dyeing Finishing Cost Kg in BDT", "Total Dyeing Finishing Cost (BDT)", "Dyeing Finishing Cost (USD)"], k: ["dye_rate", "dye_bdt", "dye_usd"] },
    show.yarnDye && { avg: null, tot: [t.yarn_dye_bdt, t.yarn_dye_usd], head: ["Yarn Dyeing in Kg BDT", "Total Yarn Dyeing Value (BDT)", "Yarn Dyeing Value (USD)"], k: ["yarn_dye_rate", "yarn_dye_bdt", "yarn_dye_usd"] },
    show.aop && { avg: null, tot: [t.aop_bdt, t.aop_usd], head: ["AOP Cost in BDT", "Total AOP Cost (BDT)", "AOP Cost (USD)"], k: ["aop_rate", "aop_bdt", "aop_usd"] },
  ].filter(Boolean);

  return (
    <div className="nbp-cost">
      <SheetHead title="Knitting & Dyeing / Embellishment Cost" budgetNo={b.budget_no} factory={(b.factories ?? []).join(", ")} buyer={b.buyer} contract={b.contract_no} />
      <p className="nbp-details"><span>Dollar Rate (BDT): {tk(c.dollar_rate ?? 0)}</span></p>

      {parts.length ? (
        <div>
          <div className="nbp-ttl">{kdTitle} Cost</div>
          <table className="t">
            <thead>
              <tr className="avg">
                <th /><th /><th />
                {parts.map((p, i) => [
                  <th key={`a${i}`} className="avg-label">{p.avg?.[0] ?? ""}</th>,
                  <th key={`b${i}`} className={p.avg ? "avg-cell" : undefined}>{p.avg?.[1] ?? ""}</th>,
                  <th key={`c${i}`} />,
                ])}
              </tr>
              <tr className="tot">
                <th /><th />
                <th className="tot-cell">{nf(t.qty, 2)}</th>
                {parts.map((p, i) => [
                  <th key={`a${i}`} />,
                  <th key={`b${i}`} className="tot-cell">{tk(p.tot[0])}</th>,
                  <th key={`c${i}`} className="tot-cell">{usd2(p.tot[1])}</th>,
                ])}
              </tr>
              <tr>
                <th className="l">SL No</th>
                <th className="l">Fabrication</th>
                <th>Qty (Kg)</th>
                {parts.flatMap((p, i) => p.head.map((h) => <th key={`${i}${h}`}>{h}</th>))}
              </tr>
            </thead>
            <tbody>
              {fabrics.map((f, i) => (
                <tr key={i}>
                  <td>{i + 1}</td>
                  <td>{f.fabrication}</td>
                  <td className="c">{nf(f.qty, 2)}</td>
                  {parts.flatMap((p, j) => [
                    <td key={`a${j}`} className="c">{tk(f[p.k[0]] ?? 0)}</td>,
                    <td key={`b${j}`} className="c">{tk(f[p.k[1]] ?? 0)}</td>,
                    <td key={`c${j}`} className="c">{usd2(f[p.k[2]] ?? 0)}</td>,
                  ])}
                </tr>
              ))}
            </tbody>
          </table>
          {show.knit && c.knit_note ? <p className="note"><strong>Knitting note:</strong> {c.knit_note}</p> : null}
          {show.dye && c.dye_note ? <p className="note"><strong>Dyeing note:</strong> {c.dye_note}</p> : null}
        </div>
      ) : null}

      {show.emb || show.cm ? (
        <div>
          <div className="head-line">
            <div className="nbp-ttl">{show.emb ? "Embellishment Cost" : "CM Cost"}</div>
            {cmPerDz > 0 ? <div className="cm-per-dz">CM Cost Per Doz: {usd2(cmPerDz)}</div> : null}
          </div>
          <table className="t">
            <thead>
              <tr className="tot">
                <th /><th />
                <th className="tot-cell">{nf(t.order_qty)}</th>
                <th className="tot-cell">{nf(t.dz, 2)}</th>
                {show.emb ? (<><th /><th className="tot-cell">{usd2(t.emb_usd)}</th></>) : null}
                {show.cm ? (<><th /><th className="tot-cell">{usd2(t.cm_usd)}</th></>) : null}
              </tr>
              <tr>
                <th className="l">SL No</th>
                <th>Style No</th>
                <th>Order Qty (Pcs)</th>
                <th>Qty/Dz</th>
                {show.emb ? (<><th>Embellishment Cost/Dz</th><th>Embellishment Value (USD)</th></>) : null}
                {show.cm ? (<><th>CM Cost/Dz</th><th>Total CM Value</th></>) : null}
              </tr>
            </thead>
            <tbody>
              {styles.map((s, i) => (
                <tr key={i}>
                  <td>{i + 1}</td>
                  <td className="c">{s.style}</td>
                  <td className="c">{nf(s.order_qty)}</td>
                  <td className="c">{nf(s.dz, 2)}</td>
                  {show.emb ? (<><td className="c">{usd2(s.emb_per_dz ?? 0)}</td><td className="c">{usd2(s.emb_usd ?? 0)}</td></>) : null}
                  {show.cm ? (<><td className="c">{usd2(s.cm_per_dz ?? 0)}</td><td className="c">{usd2(s.cm_usd ?? 0)}</td></>) : null}
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      ) : null}

      {show.badge ? (
        <div>
          <div className="nbp-ttl">Badge Cost</div>
          <table className="t">
            <thead>
              <tr className="tot">
                <th /><th />
                <th className="tot-cell">{nf(t.order_qty)}</th>
                <th /><th /><th />
                <th className="tot-cell">{usd2(t.badge_usd)}</th>
                <th /><th />
              </tr>
              <tr>
                <th className="l">SL No</th>
                <th>Style No</th>
                <th>Order Qty (Pcs)</th>
                <th>Wastage %</th>
                <th>Qty/Dz</th>
                <th>Badge Cost/Dz</th>
                <th>Badge Value (USD)</th>
                <th>Supplier</th>
                <th>Remarks</th>
              </tr>
            </thead>
            <tbody>
              {styles.map((s, i) => (
                <tr key={i}>
                  <td>{i + 1}</td>
                  <td className="c">{s.style}</td>
                  <td className="c">{nf(s.order_qty)}</td>
                  <td className="c">{nf(s.badge_wastage ?? 0, 2)}%</td>
                  <td className="c">{nf(s.badge_dz, 2)}</td>
                  <td className="c">{usd2(s.badge_per_dz ?? 0)}</td>
                  <td className="c">{usd2(s.badge_usd ?? 0)}</td>
                  <td className="c">{s.badge_supplier ?? ""}</td>
                  <td>{s.badge_remarks ?? ""}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      ) : null}

      {!parts.length && !show.emb && !show.cm && !show.badge ? <p style={{ fontSize: 11 }}>Costing has not submitted this budget&apos;s cost yet.</p> : null}
    </div>
  );
}
