import { barcodeSvg } from "@/lib/bundle-label";
import { escapeHtml as esc } from "@/lib/print-document";

/*
 * Assembly Floor → Packing Process (AnnotechERP 20/05): the two sheets
 * Brickland prints from a packing batch — the poly labels (one tag per size:
 * buyer, PO, style, colour, size, quantity, destination and the label's
 * barcode) and the carton marking (one tag per carton: the shipping mark,
 * buyer, PO, style, colour, size break-up, carton quantity, "n of m",
 * weights, destination, country, the special instruction and the carton's
 * barcode). Each opens as a preview with Print and Close, then asks the
 * browser to print.
 */

const SHEET_CSS = `
* { box-sizing: border-box; }
body { margin: 0; font-family: Arial, "FreeSerif", sans-serif; color: #000; }
.sheet { display: flex; flex-wrap: wrap; gap: 8px; padding: 8px; }
.tag { border: 1px solid #000; padding: 8px; page-break-inside: avoid; break-inside: avoid; background: #fff; }
.tag table { width: 100%; border-collapse: collapse; font-size: 11px; }
.tag th, .tag td { border: 1px solid #999; padding: 2px 4px; text-align: left; vertical-align: top; }
.tag th { width: 38%; font-weight: 600; background: #f3f4f6; }
.tag svg { max-width: 100%; height: auto; }
.code { font-family: monospace; font-size: 11px; text-align: center; letter-spacing: .08em; }
.actions { position: fixed; top: 8px; right: 8px; display: flex; gap: 6px; }
.actions button { font: 13px Arial, sans-serif; padding: 6px 12px; cursor: pointer; }
@media print { .actions { display: none; } }
@media screen { body { background: #eef0f4; padding-top: 44px; } }
`;

function openSheet(title, body, words) {
  const win = window.open("", "_blank", "width=900,height=700");
  if (!win) return false;
  win.document.write(`<!doctype html><html><head><meta charset="utf-8"><title>${esc(title)}</title><style>${SHEET_CSS}</style></head><body>
<div class="actions"><button onclick="window.print()">${esc(words.print)}</button><button onclick="window.close()">${esc(words.close)}</button></div>
${body}
<script>window.addEventListener("load", () => setTimeout(() => window.print(), 300));</script>
</body></html>`);
  win.document.close();
  return true;
}

/** One poly label per size of the batch. */
function printPolyLabels(batch, words) {
  const tags = batch.labels
    .map(
      (l) => `<div class="tag" style="width:250px">
  <div style="font-size:11px"><b>${esc(batch.buyer)}</b> · ${esc(words.po)} ${esc(batch.po_no)}</div>
  <div style="font-size:11px">${esc(batch.style)} · ${esc(batch.colour)} · ${esc(words.size)} <b>${esc(l.size)}</b> · ${esc(words.qty)} ${l.qty}</div>
  <div style="font-size:11px">${esc(words.dest)}: ${esc(batch.destination ?? "—")}</div>
  <div style="text-align:center;margin-top:4px">${barcodeSvg(l.barcode, 44)}</div>
  <div class="code">${esc(l.barcode)}</div>
</div>`,
    )
    .join("");
  return openSheet(`${words.labels} ${batch.packing_no}`, `<div class="sheet">${tags}</div>`, words);
}

/** One carton's marking tag. */
function markingHtml(batch, carton, words) {
  const row = (k, v) => `<tr><th>${esc(k)}</th><td>${esc(v ?? "—")}</td></tr>`;
  return `<div class="tag" style="width:340px">
  <div style="font-size:16px;font-weight:bold;text-align:center;margin-bottom:4px">${esc(batch.shipping_mark)}</div>
  <table>
    ${row(words.buyer, `${batch.buyer ?? ""}${batch.buyer_code ? ` (${batch.buyer_code})` : ""}`)}${row(words.poNo, batch.po_no)}${row(words.styleNo, batch.style)}${row(words.colour, batch.colour)}
    ${row(words.sizeQty, carton.lines.map((x) => `${x.size}:${x.qty}`).join(" "))}${row(words.cartonQty, `${carton.qty} ${words.pcs}`)}
    ${row(words.cartonNo, `${carton.carton_no} (${words.nOf.replace(":n", carton.serial).replace(":of", batch.carton_list.length)})`)}${row(words.grossNet, `${carton.gross_weight ?? "—"} / ${carton.net_weight ?? "—"} kg`)}
    ${row(words.destination, batch.destination)}${row(words.country, batch.country)}${batch.special_instruction ? row(words.instruction, batch.special_instruction) : ""}
  </table>
  <div style="text-align:center;margin-top:6px">${barcodeSvg(carton.barcode, 50)}</div>
  <div class="code">${esc(carton.barcode)}</div>
</div>`;
}

/** The marking of every carton of the batch. */
function printCartonMarking(batch, words) {
  return openSheet(`${words.marking} ${batch.packing_no}`, `<div class="sheet">${batch.carton_list.map((c) => markingHtml(batch, c, words)).join("")}</div>`, words);
}

export { printPolyLabels, printCartonMarking, markingHtml };
