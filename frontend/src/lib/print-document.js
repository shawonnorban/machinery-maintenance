/*
 * One printed document for every Print button (AnnotechERP): an A4 sheet —
 * the company's logo and name, the document's title and number, its facts in
 * a box grid, one or more tables (the heading repeats on every page, a row
 * never splits), totals, notes, signature lines, and "Printed by … · Page n
 * of m" at the foot of each page. It opens as a preview in its own window
 * (Print and Close on top, hidden on paper) and asks the browser to print.
 *
 * The company (unless `company` names another heading, a voucher's name)
 * and the signed-in user come from the app shell's `#erp-print-context`;
 * words follow the page's language, `signatures: true` gives the usual three.
 *
 * Cells are text or numbers, or `{ v, align, bold, tone }` — tone is
 * "ok" | "warn" | "bad" | "info" | "muted" for a status pill; `sub` is a small
 * second line under the value (a line's rolls, a note).
 */

const WORDS = {
  en: {
    print: "Print",
    close: "Close",
    printedBy: "Printed by",
    on: "on",
    page: "Page",
    of: "of",
    noRows: "Nothing to print.",
    sign: ["Prepared by", "Checked by", "Approved by"],
  },
  bn: {
    print: "প্রিন্ট",
    close: "বন্ধ করুন",
    printedBy: "প্রিন্ট করেছেন",
    on: "",
    page: "পাতা",
    of: "এর মধ্যে",
    noRows: "প্রিন্ট করার মতো কিছু নেই।",
    sign: ["প্রস্তুতকারী", "যাচাইকারী", "অনুমোদনকারী"],
  },
};

const esc = (v) =>
  String(v ?? "")
    .replaceAll("&", "&amp;")
    .replaceAll("<", "&lt;")
    .replaceAll(">", "&gt;")
    .replaceAll('"', "&quot;");

const isCell = (c) => c !== null && typeof c === "object" && !Array.isArray(c);
const MON = [
  "Jan",
  "Feb",
  "Mar",
  "Apr",
  "May",
  "Jun",
  "Jul",
  "Aug",
  "Sep",
  "Oct",
  "Nov",
  "Dec",
];
/**
 * A date prints as Brickland writes dates, whichever way the screen formatted it:
 * 2026-10-17 and "17 Oct 2026" (a time after it stays) both become 17-Oct-2026.
 */
const asDate = (v) => {
  if (typeof v !== "string") return v;
  if (/^\d{4}-\d{2}-\d{2}$/.test(v))
    return `${v.slice(8)}-${MON[Number(v.slice(5, 7)) - 1]}-${v.slice(0, 4)}`;
  const m = v.match(/^(\d{1,2}) ([A-Z][a-z]{2}) (\d{4})(,? .*)?$/);
  return m && MON.includes(m[2])
    ? `${m[1].padStart(2, "0")}-${m[2]}-${m[3]}${m[4] ?? ""}`
    : v;
};
const text = (c) => asDate(isCell(c) ? c.v : c);
const blank = (v) => v === null || v === undefined || v === "";

function context() {
  const el =
    typeof document !== "undefined"
      ? document.getElementById("erp-print-context")
      : null;
  return {
    company: el?.dataset.company ?? "",
    logo: el?.dataset.logo ?? "",
    user: el?.dataset.user ?? "",
  };
}

function stamp(lang) {
  return new Intl.DateTimeFormat(lang === "bn" ? "bn-BD" : "en-GB", {
    day: "2-digit",
    month: "short",
    year: "numeric",
    hour: "2-digit",
    minute: "2-digit",
    hourCycle: "h12",
    timeZone: "Asia/Dhaka",
  }).format(new Date()).replace(/\b[ap]m\b/, (s) => s.toUpperCase());
}

function cellHtml(c, col, tag = "td", dash = true) {
  const align = (isCell(c) && c.align) || col?.align;
  const cls = [
    align === "right" ? "r" : align === "center" ? "c" : "",
    isCell(c) && c.bold ? "b" : "",
    col?.nowrap ? "nw" : "",
  ]
    .filter(Boolean)
    .join(" ");
  const v = text(c);
  // `{ img: url }`: a small picture in the cell (a style's artwork), the text under it if any.
  const img = isCell(c) && c.img ? `<img src="${esc(new URL(c.img, window.location.origin).href)}" alt="" style="display:block;margin:0 auto;max-height:46px;max-width:70px;object-fit:contain">` : "";
  const inner = img ||
    (isCell(c) && c.tone
      ? `<span class="pill ${esc(c.tone)}">${esc(v)}</span>`
      : blank(v)
        ? dash
          ? "—"
          : ""
        : esc(v));
  const sub = isCell(c) && c.sub ? `<div class="sub">${esc(c.sub)}</div>` : "";
  return `<${tag}${cls ? ` class="${cls}"` : ""}>${inner}${sub}</${tag}>`;
}

function tableHtml(section, w) {
  const cols = section.columns ?? [];
  const widths = cols
    .map((c) => (c.width ? `<col style="width:${esc(c.width)}">` : "<col>"))
    .join("");
  const head = cols
    .map(
      (c) =>
        `<th class="${c.align === "right" ? "r" : c.align === "center" ? "c" : ""}">${esc(c.label)}</th>`,
    )
    .join("");
  const body = (section.rows ?? []).length
    ? section.rows
        .map(
          (r) => `<tr>${r.map((c, i) => cellHtml(c, cols[i])).join("")}</tr>`,
        )
        .join("")
    : `<tr><td class="c muted" colspan="${cols.length}">${esc(section.empty ?? w.noRows)}</td></tr>`;
  const foot = (section.foot ?? []).length
    ? `<tfoot>${section.foot.map((r) => `<tr>${r.map((c, i) => cellHtml(c, cols[i], "td", false)).join("")}</tr>`).join("")}</tfoot>`
    : "";
  return `${section.title ? `<h3>${esc(section.title)}</h3>` : ""}<table class="grid${section.dense ? " dense" : ""}"><colgroup>${widths}</colgroup><thead><tr>${head}</tr></thead><tbody>${body}</tbody>${foot}</table>${section.note ? `<p class="note">${esc(section.note)}</p>` : ""}`;
}

/**
 * @param {{
 *   title: string, docNo?: string, subtitle?: string, orientation?: "portrait"|"landscape",
 *   meta?: [string, any][], metaColumns?: number,
 *   sections?: { title?: string, columns: { label: string, align?: "left"|"right"|"center", width?: string, nowrap?: boolean }[], rows: any[][], foot?: any[][], note?: string, empty?: string, dense?: boolean }[],
 *   blocks?: { title?: string, html: string }[],
 *   company?: string, unit?: string, notes?: string[], signatures?: string[] | true, autoPrint?: boolean,
 * }} doc
 */
function printDocument(doc) {
  const lang =
    typeof document !== "undefined" &&
    document.documentElement.lang?.startsWith("bn")
      ? "bn"
      : "en";
  const w = WORDS[lang];
  const ctx = context();
  const win = window.open("", "_blank", "width=1100,height=900");
  if (!win) return null;
  const landscape = doc.orientation === "landscape";
  const meta = (doc.meta ?? []).filter(([, v]) => !blank(text(v)));
  // As many columns as there are facts (up to the asked number); a short last row's final fact takes what is left.
  const cols = Math.min(
    doc.metaColumns ?? (landscape ? 4 : 3),
    meta.length || 1,
  );
  const spare = meta.length % cols ? cols - (meta.length % cols) + 1 : 1;
  const metaHtml = meta.length
    ? `<div class="meta" style="grid-template-columns:repeat(${cols},1fr)">${meta
        .map(
          ([k, v], i) =>
            `<div class="fact"${i === meta.length - 1 && spare > 1 ? ` style="grid-column:span ${spare}"` : ""}><span>${esc(k)}</span><strong>${isCell(v) && v.tone ? `<span class="pill ${esc(v.tone)}">${esc(text(v))}</span>` : esc(text(v))}</strong></div>`,
        )
        .join("")}</div>`
    : "";
  const signatures = doc.signatures === true ? w.sign : doc.signatures || [];
  const printed =
    `${w.printedBy} ${ctx.user || "—"} ${w.on} ${stamp(lang)}`.replace(
      /\s+/g,
      " ",
    );
  const html = `<!doctype html><html lang="${lang}"><head><meta charset="utf-8"><title>${esc(doc.title)}${doc.docNo ? ` — ${esc(doc.docNo)}` : ""}</title><style>
@page{size:A4 ${landscape ? "landscape" : "portrait"};margin:12mm 11mm 14mm;
  @bottom-left{content:"${esc(printed)}";font:8.5px 'Segoe UI','Nirmala UI',system-ui,sans-serif;color:#6b7280}
  @bottom-right{content:"${w.page} " counter(page) " ${w.of} " counter(pages);font:8.5px 'Segoe UI','Nirmala UI',system-ui,sans-serif;color:#6b7280}}
*{box-sizing:border-box;-webkit-print-color-adjust:exact;print-color-adjust:exact}
body{margin:0;font:11.5px/1.45 'Segoe UI','Noto Sans','Nirmala UI','Noto Sans Bengali',system-ui,sans-serif;color:#111827;background:#e5e7eb}
.bar{position:sticky;top:0;z-index:2;display:flex;justify-content:flex-end;gap:8px;padding:10px 16px;background:#111827}
.bar button{font:600 13px system-ui,sans-serif;padding:7px 16px;border-radius:6px;border:1px solid #4b5563;background:#1f2937;color:#fff;cursor:pointer}
.bar button.primary{background:#4f46e5;border-color:#4f46e5}
.sheet{width:${landscape ? "297mm" : "210mm"};min-height:${landscape ? "210mm" : "297mm"};margin:18px auto;padding:12mm 11mm;background:#fff;box-shadow:0 2px 12px rgba(0,0,0,.18)}
header{display:flex;justify-content:space-between;align-items:flex-start;gap:16px;padding-bottom:10px;border-bottom:2.5px solid #4f46e5}
.org{display:flex;align-items:center;gap:10px}.org img{height:40px;width:auto}
.org .name{font-size:17px;font-weight:700;letter-spacing:.2px}.org .sub{font-size:10.5px;color:#4b5563}
.doc{text-align:right}.doc .t{font-size:15px;font-weight:700;text-transform:uppercase;letter-spacing:.6px;color:#312e81}
.doc .no{margin-top:2px;font:600 12px ui-monospace,Consolas,monospace}.doc .s{margin-top:2px;font-size:10.5px;color:#4b5563;max-width:110mm}
.meta{display:grid;gap:0;margin:12px 0 4px;border:1px solid #d1d5db;border-radius:4px;overflow:hidden}
.fact{padding:5px 8px;border-right:1px solid #e5e7eb;border-bottom:1px solid #e5e7eb}
.fact>span{display:block;font-size:9px;text-transform:uppercase;letter-spacing:.4px;color:#6b7280}.fact strong{font-size:11.5px;font-weight:600}
h3{margin:14px 0 5px;font-size:11.5px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:#312e81}
table.grid{width:100%;border-collapse:collapse;margin-top:6px;font-variant-numeric:tabular-nums}
.grid th{background:#eef0fb;color:#1e1b4b;font-size:9.5px;font-weight:700;text-transform:uppercase;letter-spacing:.3px;text-align:left;padding:5px 6px;border:1px solid #c7cbe0}
.grid td{padding:4px 6px;border:1px solid #d1d5db;vertical-align:top}
.grid tbody tr:nth-child(even) td{background:#fafafa}
.grid tfoot td{font-weight:700;background:#f3f4f6;border-top:1.5px solid #9ca3af}
.grid.dense th,.grid.dense td{padding:3px 2.5px;font-size:8.5px;letter-spacing:0}
thead{display:table-header-group}tfoot{display:table-footer-group}tr{break-inside:avoid;page-break-inside:avoid}
.sub{margin-top:2px;font-size:9.5px;font-weight:400;color:#4b5563}
.r{text-align:right}.c{text-align:center}.b{font-weight:700}.nw{white-space:nowrap}.muted{color:#6b7280}
.pill{display:inline-block;white-space:nowrap;padding:1px 7px;border-radius:9px;font-size:9.5px;font-weight:600;border:1px solid}
.pill.ok{color:#166534;background:#dcfce7;border-color:#86efac}.pill.warn{color:#92400e;background:#fef3c7;border-color:#fcd34d}
.pill.bad{color:#991b1b;background:#fee2e2;border-color:#fca5a5}.pill.info{color:#1e40af;background:#dbeafe;border-color:#93c5fd}.pill.muted{color:#374151;background:#f3f4f6;border-color:#d1d5db}
.note{margin:6px 0 0;font-size:10px;color:#4b5563}
.notes{margin-top:12px;padding:8px 10px;border-left:3px solid #c7cbe0;background:#f9fafb;font-size:10.5px}.notes p{margin:2px 0}
.block{margin-top:10px}
.cards{display:grid;gap:10px}.card{border:1px solid #9ca3af;border-radius:4px;padding:8px 10px;break-inside:avoid;page-break-inside:avoid}
.card h4{margin:0 0 6px;padding-bottom:4px;border-bottom:1.5px solid #4f46e5;font-size:12px;font-weight:700;color:#312e81}
.card table{width:100%;border-collapse:collapse}.card td{padding:2px 0;border-bottom:1px dotted #d1d5db;font-size:10.5px;color:#4b5563}.card td.v{text-align:right;font-weight:600;color:#111827}
.card p{margin:6px 0 0;font-size:9.5px;color:#4b5563}
.sign{display:flex;justify-content:space-between;gap:24px;margin-top:46px;break-inside:avoid}
.sign div{flex:1;text-align:center;border-top:1px solid #374151;padding-top:4px;font-size:10.5px;font-weight:600}
@media print{body{background:#fff}.bar{display:none}.sheet{width:auto;min-height:0;margin:0;padding:0;box-shadow:none}}
</style></head><body>
<div class="bar"><button onclick="window.close()">${w.close}</button><button class="primary" onclick="window.print()">${w.print}</button></div>
<div class="sheet">
<header><div class="org">${ctx.logo ? `<img src="${esc(ctx.logo)}" alt="">` : ""}<div><div class="name">${esc(doc.company || ctx.company || "")}</div>${doc.unit ? `<div class="sub">${esc(doc.unit)}</div>` : ""}</div></div>
<div class="doc"><div class="t">${esc(doc.title)}</div>${doc.docNo ? `<div class="no">${esc(doc.docNo)}</div>` : ""}${doc.subtitle ? `<div class="s">${esc(doc.subtitle)}</div>` : ""}</div></header>
${metaHtml}
${(doc.blocks ?? []).map((b) => `<div class="block">${b.title ? `<h3>${esc(b.title)}</h3>` : ""}${b.html}</div>`).join("")}
${(doc.sections ?? []).map((s) => tableHtml(s, w)).join("")}
${
  (doc.notes ?? []).filter(Boolean).length
    ? `<div class="notes">${doc.notes
        .filter(Boolean)
        .map((n) => `<p>${esc(n)}</p>`)
        .join("")}</div>`
    : ""
}
${signatures.length ? `<div class="sign">${signatures.map((s) => `<div>${esc(s)}</div>`).join("")}</div>` : ""}
</div>
${doc.autoPrint === false ? "" : "<script>window.addEventListener('load',function(){setTimeout(function(){window.print()},300)})</script>"}
</body></html>`;
  win.document.open();
  win.document.write(html);
  win.document.close();
  win.focus();
  return win;
}

/**
 * Cards for a `blocks` entry — a sample card, a label: each a heading, label–value lines and a note.
 *
 * @param {{ title: string, rows: [string, any][], note?: string }[]} cards
 * @param {number} [columns]
 */
function cardGrid(cards, columns = 2) {
  return `<div class="cards" style="grid-template-columns:repeat(${columns},1fr)">${cards
    .map(
      (c) =>
        `<div class="card"><h4>${esc(c.title)}</h4><table>${c.rows.map(([k, v]) => `<tr><td>${esc(k)}</td><td class="v">${blank(v) ? "—" : esc(asDate(v))}</td></tr>`).join("")}</table>${c.note ? `<p>${esc(c.note)}</p>` : ""}</div>`,
    )
    .join("")}</div>`;
}

export { printDocument, cardGrid, esc as escapeHtml };
