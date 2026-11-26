import { display, GROUP_TONE, shortDate, toneOf, TONE, TEXT_TONE } from "./common";

/*
 * Order Summary → Print (View All's Print, user 2026-11-20): the shown
 * columns (Column View) of the filtered or ticked orders on A4 Landscape —
 * the book's title ("<BUYERS> BUYER RUNNING ORDER SUMMARY", update date),
 * the totals of the summed columns, the group row and headers in their
 * colours, every cell as the sheet shows it (its colour rules too) and the
 * artwork. Widths are the columns' own; a wide set shrinks the page.
 */

const PAGE_W = (297 - 16) * (96 / 25.4);

/** The page scale: ~1300px of columns fit A4 Landscape at a readable size; more shrink it. */
export function orderSummaryScale(columns) {
  return PAGE_W / Math.max(1300, columns.reduce((a, c) => a + widthOf(c), 32));
}

// The group's colour as an inline fill (the print sheet's own header grey would cover a class).
const toneBg = (tone) => {
  const hex = /bg-\[(#[0-9a-fA-F]{6})\]/.exec(GROUP_TONE[tone] ?? "")?.[1];
  return hex ? { background: hex } : undefined;
};

const widthOf = (c) => Math.max(36, Math.round((Number(c.width) || 90) * 0.8));

export function OrderSummaryPrint({ rows, columns, groups, totals }) {
  const buyers = [...new Set(rows.map((r) => r.values?.buyer).filter(Boolean))].sort();
  const title = `${buyers.length ? `${buyers.slice(0, 3).join(", ").toUpperCase()}${buyers.length > 3 ? " …" : ""} ` : ""}BUYER RUNNING ORDER SUMMARY`;
  const summed = columns.filter((c) => totals?.[c.key] !== undefined && totals[c.key] !== null);
  // The group row: runs of neighbouring columns of one group.
  const runs = [];
  for (const c of columns) {
    const last = runs[runs.length - 1];
    if (last && last.id === c.group_id) last.span += 1;
    else runs.push({ id: c.group_id, span: 1, group: groups.find((g) => g.id === c.group_id) });
  }
  const total = columns.reduce((a, c) => a + widthOf(c), 32);
  const pct = (w) => `${((w / total) * 100).toFixed(3)}%`;

  return (
    <div className="nbp-req nbp-os">
      <div className="os-head">
        <div className="nbp-title">{title}</div>
        <div className="head-line">UPDATE DATE: {shortDate(new Intl.DateTimeFormat("en-CA", { timeZone: "Asia/Dhaka" }).format(new Date()))}</div>
        <div className="head-line">{rows.length} order(s)</div>
      </div>
      {summed.length ? (
        <table className="sum">
          <tbody>
            <tr>
              {summed.map((c) => (
                <td key={c.key}>
                  <span className="lbl">{c.label}</span>
                  <span className="val">{display(c, totals[c.key])}</span>
                </td>
              ))}
            </tr>
          </tbody>
        </table>
      ) : null}
      <div className="clear" />
      <table className="rq">
        <colgroup>
          <col style={{ width: pct(32) }} />
          {columns.map((c) => <col key={c.key} style={{ width: pct(widthOf(c)) }} />)}
        </colgroup>
        <thead>
          <tr>
            <th className="grp" />
            {runs.map((g, i) => (
              <th key={i} colSpan={g.span} className="grp" style={toneBg(g.group?.tone)}>{g.group?.name ?? ""}</th>
            ))}
          </tr>
          <tr>
            <th>SL</th>
            {columns.map((c) => <th key={c.key} className={c.vertical ? "vert" : undefined}>{c.vertical ? <span>{c.label}</span> : c.label}</th>)}
          </tr>
        </thead>
        <tbody>
          {rows.map((r, i) => (
            <tr key={r.id}>
              <td className="nw c">{i + 1}</td>
              {columns.map((c) => {
                const v = r.values?.[c.key];
                const tone = toneOf(c, v);
                const cls = [tone ? TONE[tone] : c.text_tone ? TEXT_TONE[c.text_tone] : "", c.vertical ? "vert" : "", ["number", "money", "percent", "days"].includes(c.type) ? "r" : "", c.bold ? "font-bold" : ""].join(" ");
                if (c.type === "image") return <td key={c.key} className="c">{r.artwork_url ? <img src={r.artwork_url} alt="" /> : ""}</td>;
                const text = display(c, v);
                return <td key={c.key} className={cls}>{c.vertical && text ? <span>{text}</span> : text}</td>;
              })}
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  );
}
