import { ampm, clock12 } from "@/lib/clock";
/**
 * HR & Payroll → Reports (AnnotechERP 24/02): a report on the company
 * letterhead, as Brickland prints it — the letterhead, the title with the
 * report's category and number, the report date / time / by, Selection of
 * Parameters, Summary, Report Data (with the total row), the remarks and
 * the three signatures, then the address band. Wide reports print landscape.
 * Print and PDF open the same sheet; PDF says to choose "Save as PDF".
 */

const NAVY = "#1c2a55";
const GOLD = "#c9a24a";
const TINT = "#eef1f8";
const INK = "#1f2937";
const MUTED = "#6b7280";

const esc = (v) =>
  String(v ?? "")
    .replace(/&/g, "&amp;")
    .replace(/</g, "&lt;")
    .replace(/>/g, "&gt;")
    .replace(/"/g, "&quot;");

const money = new Intl.NumberFormat("en-IN", { minimumFractionDigits: 2, maximumFractionDigits: 2 });
const plain = new Intl.NumberFormat("en-IN", { maximumFractionDigits: 2 });
const MONTHS = ["Jan", "Feb", "Mar", "Apr", "May", "Jun", "Jul", "Aug", "Sep", "Oct", "Nov", "Dec"];

/** "2026-11-11" → "11 Nov 2026" */
function dmy(v) {
  if (!v) return "";
  const [y, m, d] = String(v).slice(0, 10).split("-");
  return y && m && d ? `${d} ${MONTHS[Number(m) - 1]} ${y}` : String(v);
}

/** A cell as the screen and the sheet show it, by the column's kind. */
function cell(v, kind) {
  if (v === null || v === undefined || v === "") return "—";
  if (typeof v === "string" && !["int", "num", "money", "hours", "pct"].includes(kind)) return kind === "date" ? dmy(v) : ampm(v);
  switch (kind) {
    case "money":
      return typeof v === "number" ? money.format(v) : v;
    case "int":
    case "num":
    case "hours":
      return typeof v === "number" ? plain.format(v) : v;
    case "pct":
      return typeof v === "number" ? `${v.toFixed(1)}%` : v;
    case "date":
      return dmy(v);
    default:
      return String(v);
  }
}

const numeric = (kind) => ["int", "num", "money", "hours", "pct"].includes(kind);

function letterhead(head) {
  const initials = (head.company || "")
    .split(/\s+/)
    .filter(Boolean)
    .slice(0, 2)
    .map((w) => w[0])
    .join("")
    .toUpperCase();
  const contact = (icon, text) => (text ? `<span style="display:inline-flex;align-items:center;gap:6px;margin-right:16px"><span style="color:${GOLD}">${icon}</span>${esc(text)}</span>` : "");
  return `<header style="display:flex;justify-content:space-between;gap:16px;align-items:stretch">
  <div style="display:flex;gap:14px;align-items:flex-start">
    <span style="width:68px;height:68px;flex-shrink:0;border-radius:50%;overflow:hidden;display:flex;align-items:center;justify-content:center;background:${TINT};color:${NAVY};font:900 24px 'Times New Roman',serif;border:2px solid ${NAVY}">${head.logo ? `<img src="${esc(head.logo)}" alt="" style="width:100%;height:100%;object-fit:cover">` : esc(initials)}</span>
    <div>
      <p style="margin:0;font:800 22px 'Segoe UI',system-ui,sans-serif;color:${NAVY}">${esc(head.company)}</p>
      <div style="display:flex;gap:20px;margin-top:8px;font-size:10.5px;color:${INK}">
        ${head.corporateAddress ? `<div><p style="margin:0;font-weight:700;color:${NAVY}">${esc(head.words.corporate)}</p><p style="margin:2px 0 0;max-width:200px">${esc(head.corporateAddress)}</p></div>` : ""}
        ${head.address && head.address !== head.corporateAddress ? `<div><p style="margin:0;font-weight:700;color:${NAVY}">${esc(head.words.factory)}</p><p style="margin:2px 0 0;max-width:200px">${esc(head.address)}</p></div>` : ""}
      </div>
      <p style="margin:8px 0 0;font-size:10.5px;color:${INK}">${contact("☎", head.phone)}${contact("✉", head.email)}${contact("⌂", head.website)}</p>
    </div>
  </div>
  <div style="width:150px;flex-shrink:0;background:linear-gradient(135deg,${NAVY} 0%,${NAVY} 55%,${GOLD} 100%);clip-path:polygon(18% 0,100% 0,100% 100%,0 100%)"></div>
</header>`;
}

function section(title, body) {
  return `<section style="margin-top:14px;break-inside:auto">
  <div style="display:inline-block;background:${NAVY};color:#fff;font:700 11px 'Segoe UI',system-ui,sans-serif;letter-spacing:.06em;text-transform:uppercase;padding:5px 26px 5px 12px;clip-path:polygon(0 0,100% 0,88% 100%,0 100%)">${esc(title)}</div>
  <div style="border:1px solid #d6dbe8;border-top:2px solid ${NAVY};border-radius:0 6px 6px 6px;padding:10px 12px">${body}</div>
</section>`;
}

function pairs(items, perRow) {
  const cells = items.map(([k, v]) => `<div style="display:grid;grid-template-columns:110px 10px 1fr;font-size:11px;padding:3px 0"><span style="font-weight:700;color:${INK}">${esc(k)}</span><span>:</span><span>${esc(v)}</span></div>`);
  return `<div style="display:grid;grid-template-columns:repeat(${perRow},1fr);column-gap:18px">${cells.join("")}</div>`;
}

/**
 * The report sheet in its own window.
 *
 * @param {object} p
 * @param {object} p.report     {no, name, categoryName, wide}
 * @param {object} p.result     the run: tiles, columns, rows, foot, note
 * @param {Array}  p.params     [[label, value]] the filters as chosen
 * @param {object} p.head       letterhead + words {corporate, factory, reportDate, reportTime, reportBy, params, summary, data, remarks, remarksText, prepared, checked, approved, signature, sl, pdfHint, print, close, category}
 * @param {string} p.by         who prints
 * @param {boolean} p.pdf       show the "Save as PDF" hint
 */
function printReport({ report, result, params, head, by, pdf = false, win = null }) {
  const w = win ?? window.open("", "_blank");
  if (!w) return null;
  const t = head.words;
  const now = new Date();
  const time = clock12(`${String(now.getHours()).padStart(2, "0")}:${String(now.getMinutes()).padStart(2, "0")}`);
  const today = `${String(now.getDate()).padStart(2, "0")} ${MONTHS[now.getMonth()]} ${now.getFullYear()}`;
  const cols = result.columns;
  const many = cols.length > 12;
  const thead = `<tr><th style="width:28px">${esc(t.sl)}</th>${cols.map((c) => `<th style="text-align:${numeric(c.kind) ? "right" : "left"}">${esc(c.label)}</th>`).join("")}</tr>`;
  const tbody = result.rows
    .map((r, i) => `<tr><td style="text-align:center">${i + 1}</td>${cols.map((c) => `<td style="text-align:${numeric(c.kind) ? "right" : "left"}">${esc(cell(r[c.key], c.kind))}</td>`).join("")}</tr>`)
    .join("");
  const tfoot = result.foot
    ? `<tr class="foot"><td></td>${cols.map((c) => `<td style="text-align:${numeric(c.kind) ? "right" : "left"}">${result.foot[c.key] === undefined ? "" : esc(cell(result.foot[c.key], c.kind))}</td>`).join("")}</tr>`
    : "";
  const tiles = (result.tiles ?? []).map((x) => [x.label, x.kind === "money" ? `৳ ${cell(x.value, "money")}` : x.kind === "hours" ? `${cell(x.value, "hours")} h` : cell(x.value, x.kind === "text" ? "text" : "num")]);
  const body = `<article class="sheet">
${letterhead(head)}
<div style="margin-top:14px;display:flex;justify-content:space-between;gap:16px;align-items:center;background:${TINT};border-left:5px solid ${NAVY};border-radius:6px;padding:12px 16px">
  <div><p style="margin:0;font:800 22px 'Segoe UI',system-ui,sans-serif;color:${NAVY};text-transform:uppercase;letter-spacing:.01em">${esc(report.name)}</p>
  <p style="margin:2px 0 0;font-size:12px;color:${MUTED}">${esc(t.category)} · No. ${esc(report.no)}</p></div>
  <div style="font-size:11px;border-left:1px solid #c7cede;padding-left:14px">${pairs([[t.reportDate, today], [t.reportTime, time], [t.reportBy, by || "—"]], 1)}</div>
</div>
${section(t.params, pairs(params, 3))}
${tiles.length ? section(t.summary, pairs(tiles, 3)) : ""}
${section(t.data, result.rows.length ? `<table class="grid${many ? " tight" : ""}"><thead>${thead}</thead><tbody>${tbody}</tbody>${tfoot ? `<tfoot>${tfoot}</tfoot>` : ""}</table>` : `<p style="margin:6px 0;font-size:12px;color:${MUTED}">${esc(result.empty)}</p>`)}
${result.note ? `<p style="margin:6px 2px 0;font-size:10.5px;color:${MUTED}">${esc(result.note)}</p>` : ""}
<div style="margin-top:22px;display:grid;grid-template-columns:1.2fr 1fr 1fr 1fr;gap:18px;align-items:end;break-inside:avoid">
  <div style="background:${TINT};border-radius:6px;padding:10px 12px;font-size:10.5px"><p style="margin:0;font-weight:700;color:${INK}">${esc(t.remarks)}</p><p style="margin:4px 0 0;color:${NAVY}">${esc(t.remarksText)}</p></div>
  ${[t.prepared, t.checked, t.approved].map((x) => `<div style="text-align:center;font-size:11px"><div style="border-top:1.5px solid ${INK};margin-bottom:4px"></div><p style="margin:0;font-weight:700">${esc(x)}</p><p style="margin:0;font-size:9.5px;color:${MUTED}">${esc(t.signature)}</p></div>`).join("")}
</div>
<footer style="margin-top:22px;border-top:2px solid ${NAVY};padding-top:8px;display:flex;justify-content:space-between;gap:16px;font-size:10px;color:${INK}">
  <span>${esc(head.corporateAddress || head.address || "")}</span><span>${esc([head.phone, head.email, head.website].filter(Boolean).join(" · "))}</span>
</footer>
</article>`;
  const landscape = report.wide || many;
  w.document.open();
  w.document.write(`<!doctype html><html><head><meta charset="utf-8"><title>${esc(report.name)}</title>
<style>@page{size:A4 ${landscape ? "landscape" : "portrait"};margin:10mm}
html,body{margin:0;background:#e5e7eb;font-family:'Segoe UI','Nirmala UI','Noto Sans Bengali',system-ui,sans-serif;color:${INK}}
.bar{position:sticky;top:0;z-index:2;display:flex;align-items:center;justify-content:space-between;gap:12px;padding:10px 16px;background:#fff;border-bottom:1px solid #d1d5db;font-size:12.5px;color:${MUTED}}
.bar button{font:600 13px system-ui,sans-serif;padding:7px 16px;border-radius:6px;border:1px solid #d1d5db;background:#fff;color:${INK};cursor:pointer;margin-left:8px}.bar button.primary{background:${NAVY};border-color:${NAVY};color:#fff}
.sheet{width:${landscape ? "277mm" : "190mm"};margin:16px auto;background:#fff;padding:12mm;box-sizing:border-box;box-shadow:0 1px 6px rgba(0,0,0,.15)}
table.grid{width:100%;border-collapse:collapse;font-size:10.5px;table-layout:auto}table.grid.tight{font-size:8.5px}table.grid.tight th{white-space:normal}table.grid.tight td{padding:4px 3px}
table.grid th{background:${NAVY};color:#fff;font-weight:700;padding:6px 5px;white-space:nowrap}table.grid td{padding:5px;border-bottom:1px solid #e5e7eb;vertical-align:top}
table.grid tbody tr:nth-child(even) td{background:#f8f9fc}table.grid tr.foot td{font-weight:800;background:${TINT};border-top:2px solid ${NAVY}}
thead{display:table-header-group}tr{break-inside:avoid}
@media print{html,body{background:#fff}.bar{display:none}.sheet{width:auto;margin:0;padding:0;box-shadow:none}*{-webkit-print-color-adjust:exact;print-color-adjust:exact}}</style></head>
<body><div class="bar"><span>${esc(pdf ? t.pdfHint : t.printHint)}</span><span><button onclick="window.close()">${esc(t.close)}</button><button class="primary" onclick="window.print()">${esc(t.print)}</button></span></div>${body}
<script>window.addEventListener('load',function(){setTimeout(function(){window.print()},400)})</script></body></html>`);
  w.document.close();
  w.focus();
  return w;
}

export { printReport, cell, dmy, numeric };
