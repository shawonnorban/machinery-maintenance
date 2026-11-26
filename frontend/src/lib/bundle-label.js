/*
 * Cutting Floor bundle cards (AnnotechERP 19): the Code 128 barcode a card
 * carries, drawn as SVG, and the label sheet printed from it — one card per
 * label at Brickland's sizes (50 × 75, 75 × 75, 75 × 100 mm or a custom
 * size), opened in its own window and sent to the printer.
 */

// Code 128 bar patterns (module widths), values 0–106; 106 is the stop.
const PATTERNS = [
  "212222", "222122", "222221", "121223", "121322", "131222", "122213", "122312", "132212", "221213",
  "221312", "231212", "112232", "122132", "122231", "113222", "123122", "123221", "223211", "221132",
  "221231", "213212", "223112", "312131", "311222", "321122", "321221", "312212", "322112", "322211",
  "212123", "212321", "232121", "111323", "131123", "131321", "112313", "132113", "132311", "211313",
  "231113", "231311", "112133", "112331", "132131", "113123", "113321", "133121", "313121", "211331",
  "231131", "213113", "213311", "213131", "311123", "311321", "331121", "312113", "312311", "332111",
  "314111", "221411", "431111", "111224", "111422", "121124", "121421", "141122", "141221", "112214",
  "112412", "122114", "122411", "142112", "142211", "241211", "221114", "413111", "241112", "134111",
  "111242", "121142", "121241", "114212", "124112", "124211", "411212", "421112", "421211", "212141",
  "214121", "412121", "111143", "111341", "131141", "114113", "114311", "411113", "411311", "113141",
  "114131", "311141", "411131", "211412", "211214", "211232", "2331112",
];

/** The bar widths of `text` in Code 128 B (printable ASCII), with check and stop. */
function code128(text) {
  const codes = [104];
  for (const ch of String(text)) {
    const c = ch.charCodeAt(0);
    codes.push(c >= 32 && c <= 126 ? c - 32 : 0);
  }
  const check = codes.reduce((sum, c, i) => sum + c * (i === 0 ? 1 : i), 0) % 103;
  return [...codes, check, 106].map((c) => PATTERNS[c]).join("");
}

/** The barcode as an SVG string: `height` in user units, a quiet zone either side. */
function barcodeSvg(text, height = 40) {
  const widths = code128(text);
  const quiet = 10;
  let x = quiet;
  let rects = "";
  [...widths].forEach((w, i) => {
    const n = Number(w);
    if (i % 2 === 0) rects += `<rect x="${x}" y="0" width="${n}" height="${height}"/>`;
    x += n;
  });
  const total = x + quiet;
  return `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ${total} ${height}" preserveAspectRatio="none" width="100%" height="100%" fill="#000" role="img" aria-label="${escape(text)}">${rects}</svg>`;
}

const SIZES = { "50x75": [75, 50], "75x75": [75, 75], "75x100": [100, 75] };

function escape(v) {
  return String(v ?? "").replace(/[&<>"']/g, (c) => ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" })[c]);
}

/** One card's markup: company, barcode and its facts, the date and company at the foot. */
function cardHtml(card, company, words) {
  const row = (k, v, strong = false) => `<tr><td class="k">${escape(words[k])}</td><td class="v${strong ? " b" : ""}">: ${escape(v)}</td></tr>`;
  return `<section class="card">
  <h1>${escape(company)}</h1>
  <div class="top">
    <div class="bar">${barcodeSvg(card.barcode)}<div class="code">${escape(card.barcode)}</div></div>
    <table class="mini">${row("po", card.po_no)}${row("style", card.style)}${row("buyer", card.buyer ?? "")}${row("colour", card.colour)}</table>
  </div>
  <table class="facts">
    ${row("bundle", card.bundle_no, true)}${row("cutting", card.cutting_no, true)}${row("size", card.size, true)}${row("from", card.serial_from, true)}${row("to", card.serial_to, true)}
    ${row("qty", `${card.qty} ${words.pcs}`, true)}${row("parts", card.parts_name, true)}${row("batch", card.batch_no, true)}
  </table>
  <div class="foot"><span>${escape(words.date)} : ${escape(card.cut_date.split("-").reverse().join("-"))}</span><span>${escape(company)}</span></div>
</section>`;
}

/**
 * Print bundle cards: `size` one of "50x75" | "75x75" | "75x100" | "custom"
 * (then `custom` = { w, h } in mm, landscape width first).
 */
function printBundleCards(cards, { size = "50x75", custom = null, company = "", words }) {
  const [w, h] = size === "custom" && custom ? [Number(custom.w) || 75, Number(custom.h) || 50] : SIZES[size] ?? SIZES["50x75"];
  const scale = Math.min(w / 75, h / 50);
  const win = window.open("", "_blank", "width=900,height=700");
  if (!win) return false;
  win.document.write(`<!doctype html><html><head><meta charset="utf-8"><title>${escape(words.title)}</title>
<style>
@page { size: ${w}mm ${h}mm; margin: 0; }
* { box-sizing: border-box; }
body { margin: 0; font-family: Arial, "FreeSerif", sans-serif; color: #000; }
.card { width: ${w}mm; height: ${h}mm; padding: ${2.2 * scale}mm ${2.6 * scale}mm; page-break-after: always; overflow: hidden; display: flex; flex-direction: column; font-size: ${6.2 * scale}pt; }
.card:last-child { page-break-after: auto; }
h1 { margin: 0 0 ${1 * scale}mm; text-align: center; font-size: ${7.4 * scale}pt; letter-spacing: .02em; }
.top { display: flex; gap: ${1.5 * scale}mm; align-items: flex-start; }
.bar { width: 48%; height: ${9 * scale}mm; display: flex; flex-direction: column; }
.bar svg { flex: 1; }
.code { font-size: ${4.6 * scale}pt; text-align: center; letter-spacing: .05em; }
table { border-collapse: collapse; }
td { padding: 0 ${0.8 * scale}mm 0 0; line-height: 1.25; vertical-align: top; }
.mini td { font-size: ${5.4 * scale}pt; }
.facts { margin-top: ${1.4 * scale}mm; }
.k { white-space: nowrap; }
.b { font-weight: 700; }
.foot { margin-top: auto; display: flex; justify-content: space-between; font-size: ${5.2 * scale}pt; font-weight: 700; }
.actions { position: fixed; top: 8px; right: 8px; display: flex; gap: 6px; }
.actions button { font: 13px Arial, sans-serif; padding: 6px 12px; cursor: pointer; }
@media print { .actions { display: none; } }
@media screen { body { background: #eef0f4; padding: 48px 16px; } .card { background: #fff; margin: 0 auto 12px; box-shadow: 0 1px 3px rgba(0,0,0,.2); } }
</style></head><body>
<div class="actions"><button onclick="window.print()">${escape(words.print)}</button><button onclick="window.close()">${escape(words.close)}</button></div>
${cards.map((c) => cardHtml(c, company, words)).join("\n")}
<script>window.addEventListener("load", () => setTimeout(() => window.print(), 300));</script>
</body></html>`);
  win.document.close();
  return true;
}

export { barcodeSvg, printBundleCards };
