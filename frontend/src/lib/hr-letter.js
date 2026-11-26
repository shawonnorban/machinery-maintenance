/*
 * HR letters (AnnotechERP 24/09): a template merged with an employee's
 * details into a letter, and the letter as one A4 sheet on the company's
 * letterhead — the same HTML for the on-screen preview and for printing.
 *
 * A template written in Bangla (any Bangla in its title, subject,
 * salutation or body) gets Bangla digits, dates, money in words and the
 * Bangla salary tables; otherwise English. **Bold** marks bold text;
 * {{Field}} is replaced by the employee's detail.
 */

const BLANK = "__________";

const LETTER_FIELDS = [
  ["EmployeeNumber", "Employee Number"], ["EmployeeID", "Employee ID"], ["CardNumber", "Card Number"], ["EmployeeName", "Employee Name"],
  ["FatherName", "Father's Name"], ["MotherName", "Mother's Name"], ["Address", "Address"], ["Designation", "Designation"], ["Department", "Department"],
  ["Section", "Section"], ["Floor", "Floor"], ["Grade", "Grade"], ["EmploymentType", "Employment Type"], ["JoiningDate", "Joining Date"],
  ["EffectiveDate", "Effective Date"], ["LetterDate", "Letter Date"], ["ProbationPeriod", "Probation Period"], ["BasicSalary", "Basic Salary (Gazette)"],
  ["GrossA", "Gross-A (Gazette)"], ["CompanyGrossSalary", "Company Gross Salary"], ["CompanyGrossSalaryInWords", "Company Gross in Words"],
  ["ActualSalary", "Actual Salary"], ["GrossSalaryB", "Gross Salary B"], ["GazetteName", "Gazette Name"], ["GazetteYear", "Gazette Year"],
  ["CompanyName", "Company Name"], ["FactoryName", "Factory Name"],
].map(([key, label]) => ({ key, label }));

const BANGLA = /[ঀ-৿]/;
const bnDigits = (v) => String(v ?? "").replace(/[0-9]/g, (d) => "০১২৩৪৫৬৭৮৯"[Number(d)]);
const num = (v) => Number(v).toLocaleString("en-IN", { maximumFractionDigits: 2 });
const bnTaka = (v) => bnDigits(num(v));
const pct = (v) => (Number.isInteger(v) ? String(v) : Number(v).toFixed(2).replace(/0+$/, "").replace(/\.$/, ""));
const dmy = (d) => {
  const m = /^(\d{4})-(\d{2})-(\d{2})/.exec(d ?? "");
  return m ? `${m[3]}-${m[2]}-${m[1]}` : "";
};
const MONTHS = ["January", "February", "March", "April", "May", "June", "July", "August", "September", "October", "November", "December"];
const BN_MONTHS = ["জানুয়ারি", "ফেব্রুয়ারি", "মার্চ", "এপ্রিল", "মে", "জুন", "জুলাই", "আগস্ট", "সেপ্টেম্বর", "অক্টোবর", "নভেম্বর", "ডিসেম্বর"];
const longDate = (d) => {
  const m = /^(\d{4})-(\d{2})-(\d{2})/.exec(d ?? "");
  return m ? `${MONTHS[Number(m[2]) - 1]} ${m[3]}, ${m[1]}` : "";
};
const bnLongDate = (d) => {
  const m = /^(\d{4})-(\d{2})-(\d{2})/.exec(d ?? "");
  return m ? `${bnDigits(m[3])} ${BN_MONTHS[Number(m[2]) - 1]} ${bnDigits(m[1])}` : "";
};

const ONES = ["", "One", "Two", "Three", "Four", "Five", "Six", "Seven", "Eight", "Nine", "Ten", "Eleven", "Twelve", "Thirteen", "Fourteen", "Fifteen", "Sixteen", "Seventeen", "Eighteen", "Nineteen"];
const TENS = ["", "", "Twenty", "Thirty", "Forty", "Fifty", "Sixty", "Seventy", "Eighty", "Ninety"];
const twoWords = (n) => (n < 20 ? ONES[n] : `${TENS[Math.floor(n / 10)]}${n % 10 ? ` ${ONES[n % 10]}` : ""}`);
function words(v) {
  let n = Math.floor(Math.abs(v));
  if (n === 0) return "Zero";
  const out = [];
  const crore = Math.floor(n / 1e7);
  n %= 1e7;
  const lakh = Math.floor(n / 1e5);
  n %= 1e5;
  const thousand = Math.floor(n / 1e3);
  n %= 1e3;
  if (crore) out.push(`${words(crore)} Crore`);
  if (lakh) out.push(`${twoWords(lakh)} Lakh`);
  if (thousand) out.push(`${twoWords(thousand)} Thousand`);
  if (n) out.push([Math.floor(n / 100) ? `${ONES[Math.floor(n / 100)]} Hundred` : "", n % 100 ? twoWords(n % 100) : ""].filter(Boolean).join(" "));
  return out.join(" ");
}
const BN_WORDS = ["শূন্য", "এক", "দুই", "তিন", "চার", "পাঁচ", "ছয়", "সাত", "আট", "নয়", "দশ", "এগারো", "বারো", "তেরো", "চৌদ্দ", "পনেরো", "ষোলো", "সতেরো", "আঠারো", "উনিশ", "বিশ", "একুশ", "বাইশ", "তেইশ", "চব্বিশ", "পঁচিশ", "ছাব্বিশ", "সাতাশ", "আটাশ", "উনত্রিশ", "ত্রিশ", "একত্রিশ", "বত্রিশ", "তেত্রিশ", "চৌত্রিশ", "পঁয়ত্রিশ", "ছত্রিশ", "সাঁইত্রিশ", "আটত্রিশ", "উনচল্লিশ", "চল্লিশ", "একচল্লিশ", "বিয়াল্লিশ", "তেতাল্লিশ", "চুয়াল্লিশ", "পঁয়তাল্লিশ", "ছেচল্লিশ", "সাতচল্লিশ", "আটচল্লিশ", "উনপঞ্চাশ", "পঞ্চাশ", "একান্ন", "বাহান্ন", "তিপ্পান্ন", "চুয়ান্ন", "পঞ্চান্ন", "ছাপ্পান্ন", "সাতান্ন", "আটান্ন", "উনষাট", "ষাট", "একষট্টি", "বাষট্টি", "তেষট্টি", "চৌষট্টি", "পঁয়ষট্টি", "ছেষট্টি", "সাতষট্টি", "আটষট্টি", "উনসত্তর", "সত্তর", "একাত্তর", "বাহাত্তর", "তিয়াত্তর", "চুয়াত্তর", "পঁচাত্তর", "ছিয়াত্তর", "সাতাত্তর", "আটাত্তর", "উনআশি", "আশি", "একাশি", "বিরাশি", "তিরাশি", "চুরাশি", "পঁচাশি", "ছিয়াশি", "সাতাশি", "আটাশি", "উননব্বই", "নব্বই", "একানব্বই", "বিরানব্বই", "তিরানব্বই", "চুরানব্বই", "পঁচানব্বই", "ছিয়ানব্বই", "সাতানব্বই", "আটানব্বই", "নিরানব্বই"];
function bnWords(v) {
  let n = Math.floor(Math.abs(v));
  if (n === 0) return BN_WORDS[0];
  const out = [];
  const crore = Math.floor(n / 1e7);
  n %= 1e7;
  const lakh = Math.floor(n / 1e5);
  n %= 1e5;
  const thousand = Math.floor(n / 1e3);
  n %= 1e3;
  const hundred = Math.floor(n / 100);
  n %= 100;
  if (crore) out.push(`${bnWords(crore)} কোটি`);
  if (lakh) out.push(`${BN_WORDS[lakh]} লক্ষ`);
  if (thousand) out.push(`${BN_WORDS[thousand]} হাজার`);
  if (hundred) out.push(`${BN_WORDS[hundred]} শত`);
  if (n) out.push(BN_WORDS[n]);
  return out.join(" ");
}

/** The gazette wage of a grade: Basic, House Rent (% of Basic), Medical, Conveyance, Food, Other; Gross-A their sum. */
function gazetteWage(w, bangla = false) {
  const house = Math.round((w.basic * w.houseRentPercent) / 100);
  const year = w.gazetteYear;
  const rows = bangla
    ? [[`মূল বেতন (${year ? `${bnDigits(year)} সালের ` : ""}গেজেট)`, w.basic], [`বাড়ি ভাড়া (মূল বেতনের ${bnDigits(pct(w.houseRentPercent))}%)`, house], ["চিকিৎসা ভাতা", w.medical], ["যাতায়াত ভাতা", w.conveyance], ["খাদ্য ভাতা", w.food]]
    : [[`Basic Salary (${year ? `${year} Gazette` : "Gazette"})`, w.basic], [`House Rent (${pct(w.houseRentPercent)}% of Basic)`, house], ["Medical Allowance", w.medical], ["Conveyance Allowance", w.conveyance], ["Food Allowance", w.food]];
  if (w.other > 0) rows.push([bangla ? "অন্যান্য ভাতা" : "Other Allowance", w.other]);
  return { houseRent: house, grossA: rows.reduce((a, [, v]) => a + v, 0), rows };
}

function values(e, o, bangla) {
  const wage = e.wage ? gazetteWage(e.wage, bangla) : null;
  const gross = e.companyGross;
  if (bangla) {
    const bn = e.bn ?? {};
    const money = (v) => (v !== null && v !== undefined ? bnTaka(v) : "");
    return {
      EmployeeNumber: bnDigits(e.employeeNumber), EmployeeID: bnDigits(e.employeeNumber), CardNumber: bnDigits(e.cardNumber ?? ""), EmployeeName: bn.name || e.name,
      FatherName: e.fatherName ?? "", MotherName: e.motherName ?? "", Address: e.address ?? "", Designation: e.designation ?? "", Department: e.department ?? "",
      Section: e.section ?? "", Floor: e.floor ?? "", Grade: bnDigits(e.grade ?? ""), EmploymentType: e.employmentType ?? "", JoiningDate: bnDigits(dmy(e.joiningDate)),
      EffectiveDate: bnDigits(dmy(o.effectiveDate)), LetterDate: bnLongDate(o.letterDate),
      ProbationPeriod: o.probationMonths > 0 ? `${bnDigits(String(o.probationMonths).padStart(2, "0"))} (${bnWords(o.probationMonths)}) মাস` : "",
      BasicSalary: money(e.wage?.basic), GrossA: money(wage?.grossA), CompanyGrossSalary: money(gross), CompanyGrossSalaryInWords: gross !== null && gross !== undefined ? `${bnWords(gross)} টাকা মাত্র` : "",
      ActualSalary: money(e.actualSalary), GrossSalaryB: money(e.grossB), GazetteName: e.gazetteName ?? "", GazetteYear: bnDigits(e.gazetteYear ?? ""),
      CompanyName: o.companyName, FactoryName: e.factory || o.companyName,
    };
  }
  const money = (v) => (v !== null && v !== undefined ? num(v) : "");
  return {
    EmployeeNumber: e.employeeNumber, EmployeeID: e.employeeNumber, CardNumber: e.cardNumber ?? "", EmployeeName: e.name, FatherName: e.fatherName ?? "", MotherName: e.motherName ?? "",
    Address: e.address ?? "", Designation: e.designation ?? "", Department: e.department ?? "", Section: e.section ?? "", Floor: e.floor ?? "", Grade: e.grade ?? "",
    EmploymentType: e.employmentType ?? "", JoiningDate: dmy(e.joiningDate), EffectiveDate: dmy(o.effectiveDate), LetterDate: longDate(o.letterDate),
    ProbationPeriod: o.probationMonths > 0 ? `${String(o.probationMonths).padStart(2, "0")} (${words(o.probationMonths)}) month${o.probationMonths === 1 ? "" : "s"}` : "",
    BasicSalary: money(e.wage?.basic), GrossA: money(wage?.grossA), CompanyGrossSalary: money(gross), CompanyGrossSalaryInWords: gross !== null && gross !== undefined ? `Tk. ${words(gross)}` : "",
    ActualSalary: money(e.actualSalary), GrossSalaryB: money(e.grossB), GazetteName: e.gazetteName ?? "", GazetteYear: e.gazetteYear ?? "",
    CompanyName: o.companyName, FactoryName: e.factory || o.companyName,
  };
}

/**
 * A template merged with an employee: the letter's parts, ready to draw.
 *
 * @param {object} t the template; @param {object} e the employee's letter details
 * @param {{companyName: string, letterDate: string, effectiveDate?: string, probationMonths?: number}} o
 */
function mergeLetter(t, e, o, refNo) {
  const bangla = BANGLA.test(`${t.title} ${t.subject} ${t.salutation} ${t.openingMessage}`);
  const v = values(e, { probationMonths: 0, ...o }, bangla);
  const fill = (s) => String(s ?? "").replace(/\{\{\s*([A-Za-z]+)\s*\}\}/g, (all, k) => (k in v ? String(v[k] ?? "").replace(/\*/g, "").trim() || BLANK : all));
  const clauses = (t.clauses ?? []).map((c) => c.trim()).filter(Boolean).map(fill);
  let salary = null;
  if (t.showSalaryTables) {
    const wage = e.wage ? gazetteWage(e.wage, bangla) : null;
    const gname = e.gazetteName || (bangla ? "মজুরি গেজেট" : "Wage Gazette");
    salary = bangla
      ? {
          afterClause: Math.max(0, Math.min(t.salaryTablesAfter ?? 0, clauses.length)), gazetteTitle: `বেতন কাঠামো (${gname} অনুযায়ী)`, gazetteRows: wage ? wage.rows : [],
          gradeLabel: e.wage ? bnDigits(`${e.wage.grade} (${e.wage.category})`) : v.Grade || BLANK, grossALabel: `মোট বেতন-A (${e.gazetteYear ? `${bnDigits(e.gazetteYear)} সালের ` : ""}গেজেট অনুযায়ী)`,
          grossA: wage ? wage.grossA : null,
          companyRows: [["কার্যকর তারিখ", v.EffectiveDate || BLANK], ["বেতন কাঠামো", gname], ["বেতন প্রদান", "মাসিক"], ["উৎসব ভাতা", "বছরে ২ (দুই)টি (কোম্পানির নীতিমালা অনুযায়ী)"]],
          companyGross: e.companyGross,
        }
      : {
          afterClause: Math.max(0, Math.min(t.salaryTablesAfter ?? 0, clauses.length)), gazetteTitle: `Salary Structure (As per ${gname})`, gazetteRows: wage ? wage.rows : [],
          gradeLabel: e.wage ? `${e.wage.grade} (${e.wage.category})` : e.grade || BLANK, grossALabel: `Gross-A (As per${e.gazetteYear ? ` ${e.gazetteYear}` : ""} Gazette)`,
          grossA: wage ? wage.grossA : null,
          companyRows: [["Effective Date", dmy(o.effectiveDate) || BLANK], ["Salary Structure", gname], ["Payment Frequency", "Monthly"], ["Festival Bonus", "2 (Two) per year (As per company policy)"]],
          companyGross: e.companyGross,
        };
  }
  const recipient = bangla
    ? [["কার্ড নং", v.CardNumber || BLANK], ["আইডি নং", v.EmployeeNumber], ...(v.Designation ? [["পদবি", v.Designation]] : []), ...(v.Department ? [["বিভাগ", v.Department]] : [])]
    : [["Card No", e.cardNumber || BLANK], ["Employee ID", e.employeeNumber]];
  if (e.address) recipient.push([bangla ? "ঠিকানা" : "Address", e.address]);
  return {
    refNo, letterDate: o.letterDate, title: fill(t.title), subject: fill(t.subject), recipientName: bangla ? v.EmployeeName : e.name, recipientRows: recipient,
    salutation: fill(t.salutation), opening: fill(t.openingMessage), clauses, salary, closing: fill(t.closingMessage), signatureTitle: fill(t.signatureTitle),
    signatureCompany: fill(t.signatureCompany), footerNote: fill(t.footerNote), language: bangla ? "bn" : "en",
  };
}

/* ── The sheet ─────────────────────────────────────────────────────── */

const NAVY = "#1c2a55";
const INK_NAVY = "#131d3d";
const GOLD = "#c9a24a";
const INK = "#111827";
const MUTED = "#4b5563";
const TINT = "#dbe7f7";
const LINE = "#9fb3d1";
const WORDS = {
  en: { ref: "Ref", date: "Date", subject: "Subject", particulars: "Particulars", amount: "Amount (Tk.)", details: "Details", grade: "Grade (Gazette)", noWage: "No gazette wage is set for this grade.", company: "Company Salary Information", offered: "Company Offered Salary (Gross)", factory: "Factory", headOffice: "Head Office" },
  bn: { ref: "সূত্র", date: "তারিখ", subject: "বিষয়", particulars: "বিবরণ", amount: "টাকা", details: "তথ্য", grade: "গ্রেড (গেজেট)", noWage: "এই গ্রেডের গেজেট মজুরি নির্ধারণ করা নেই।", company: "কোম্পানির বেতন তথ্য", offered: "কোম্পানি প্রদত্ত বেতন (মোট)", factory: "কারখানা", headOffice: "প্রধান কার্যালয়" },
};
const FONTS = { en: "'Times New Roman', Times, serif", bn: "'Noto Serif Bengali', 'Noto Sans Bengali', 'Nirmala UI', Vrinda, SolaimanLipi, Kalpurush, 'Times New Roman', serif" };
const esc = (v) => String(v ?? "").replaceAll("&", "&amp;").replaceAll("<", "&lt;").replaceAll(">", "&gt;").replaceAll('"', "&quot;");
const bold = (s) => esc(s).replace(/\*\*([^*]+)\*\*/g, "<strong>$1</strong>");
const lines = (s) => String(s ?? "").split("\n").map((l) => `<p style="margin:0">${bold(l)}</p>`).join("");

const ICON = {
  phone: '<path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.8 19.8 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6A19.8 19.8 0 0 1 2.12 4.18 2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.13.96.36 1.9.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.91.34 1.85.57 2.81.7A2 2 0 0 1 22 16.92z"/>',
  mail: '<rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 7-10 6L2 7"/>',
  globe: '<circle cx="12" cy="12" r="10"/><path d="M2 12h20M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/>',
  pin: '<path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0z"/><circle cx="12" cy="10" r="3"/>',
};
const svg = (name, size, color) => `<svg width="${size}" height="${size}" viewBox="0 0 24 24" fill="none" stroke="${color}" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">${ICON[name]}</svg>`;

function header(head) {
  const contacts = [["phone", head.phone], ["mail", head.email], ["globe", head.website]].filter(([, v]) => v);
  const initials = String(head.company ?? "").split(/\s+/).filter(Boolean).slice(0, 2).map((w) => w[0]).join("").toUpperCase();
  return `<header style="padding:20px 32px 0">
  <div style="display:flex;align-items:center;gap:16px">
    <span style="width:92px;height:92px;flex-shrink:0;border-radius:50%;overflow:hidden;display:flex;align-items:center;justify-content:center;background:${TINT};color:${NAVY};font:900 34px 'Times New Roman',serif">${head.logo ? `<img src="${esc(head.logo)}" alt="" style="width:100%;height:100%;object-fit:cover">` : esc(initials)}</span>
    <div style="flex:1;min-width:0;font-family:'Times New Roman',Times,serif"><p style="margin:0;font-size:40px;line-height:1;font-weight:900;letter-spacing:-0.5px;color:${NAVY}">${esc(head.company)}</p></div>
    ${contacts.length ? `<ul style="list-style:none;margin:0;padding:0 0 0 16px;border-left:2px solid ${NAVY};color:${INK_NAVY};font:12px Arial,sans-serif;display:flex;flex-direction:column;gap:4px">${contacts.map(([i, v]) => `<li style="display:flex;align-items:center;gap:8px"><span style="width:18px;height:18px;border:1px solid ${NAVY};border-radius:2px;display:flex;align-items:center;justify-content:center">${svg(i, 11, NAVY)}</span>${esc(v)}</li>`).join("")}</ul>` : ""}
  </div>
  <div style="margin-top:12px;height:3px;background:${NAVY}"></div><div style="margin-top:2px;height:2px;background:${GOLD}"></div>
</header>`;
}

function footer(head, w) {
  const parts = [[w.factory, head.address], [w.headOffice, head.corporateAddress]].filter(([, v]) => v);
  if (!parts.length) return "";
  return `<footer style="margin-top:24px;display:grid;grid-template-columns:repeat(${parts.length},1fr);gap:16px;padding:12px 32px;font:11px Arial,sans-serif;color:#fff;background:${NAVY};border-top:3px solid ${GOLD}">${parts
    .map(([k, v], i) => `<p style="margin:0;display:flex;align-items:flex-start;gap:8px;${i ? "padding-left:16px;border-left:1px solid rgba(255,255,255,.4)" : ""}">${svg("pin", 15, GOLD)}<span><strong style="color:${GOLD}">${esc(k)}:</strong> ${esc(v)}</span></p>`)
    .join("")}</footer>`;
}

function clauseList(clauses, start, bangla) {
  if (!clauses.length) return "";
  return `<ol style="list-style:none;margin:8px 0 0;padding:0;text-align:justify">${clauses.map((c, i) => `<li style="display:grid;grid-template-columns:2rem 1fr;margin-bottom:2px"><span style="padding-left:4px">${bangla ? bnDigits(String(start + i)) : start + i}.</span><span>${bold(c)}</span></li>`).join("")}</ol>`;
}

function salaryTables(s, w, bangla) {
  const money = bangla ? bnTaka : num;
  const cell = "border:1px solid " + LINE + ";padding:3px 8px";
  const head = `${cell};background:${TINT};color:${INK_NAVY};font-weight:700;text-align:center`;
  const total = `${cell};background:#bcd2f0;color:${INK_NAVY};font-weight:700`;
  const gazette = s.gazetteRows.length
    ? s.gazetteRows.map(([k, v]) => `<tr><td style="${cell}">${esc(k)}</td><td style="${cell};text-align:right">${money(v)}</td></tr>`).join("")
    : `<tr><td colspan="2" style="${cell};text-align:center;font-style:italic;color:${MUTED}">${esc(w.noWage)}</td></tr>`;
  return `<div style="margin:8px 0;display:grid;grid-template-columns:1.12fr 1fr;gap:8px;font-size:11.5px;line-height:1.25;break-inside:avoid">
  <table style="width:100%;border-collapse:collapse"><thead><tr><th colspan="2" style="${head}">${esc(s.gazetteTitle)}</th></tr><tr><th style="${head}">${esc(w.particulars)}</th><th style="${head}">${esc(w.amount)}</th></tr></thead>
  <tbody><tr><td style="${cell}">${esc(w.grade)}</td><td style="${cell};text-align:center">${esc(s.gradeLabel)}</td></tr>${gazette}
  <tr><td style="${total}">${esc(s.grossALabel)}</td><td style="${total};text-align:right;font-size:13px">${s.grossA === null ? "—" : money(s.grossA)}</td></tr></tbody></table>
  <table style="width:100%;border-collapse:collapse;align-self:start"><thead><tr><th colspan="2" style="${head}">${esc(w.company)}</th></tr><tr><th style="${head}">${esc(w.particulars)}</th><th style="${head}">${esc(w.details)}</th></tr></thead>
  <tbody><tr><td style="${cell}">${esc(w.offered)}</td><td style="${cell};text-align:center;font-size:13px;font-weight:700">${s.companyGross === null || s.companyGross === undefined ? "—" : money(s.companyGross)}</td></tr>
  ${s.companyRows.map(([k, v]) => `<tr><td style="${cell}">${esc(k)}</td><td style="${cell}">${esc(v)}</td></tr>`).join("")}</tbody></table>
</div>`;
}

/** One letter as an A4 sheet (794 × 1123 px). */
function letterHtml(letter, head) {
  const bangla = letter.language === "bn";
  const w = WORDS[bangla ? "bn" : "en"];
  const s = letter.salary;
  const before = s ? letter.clauses.slice(0, s.afterClause) : letter.clauses;
  const after = s ? letter.clauses.slice(s.afterClause) : [];
  return `<article class="letter-sheet" style="width:794px;min-height:1123px;margin:0 auto;display:flex;flex-direction:column;background:#fff;color:${INK};font-family:${FONTS[bangla ? "bn" : "en"]};font-size:12.5px;line-height:1.38;box-sizing:border-box">
${header(head)}
<div style="flex:1;padding:16px 48px 0">
  <div style="display:flex;justify-content:space-between;gap:24px"><p style="margin:0">${esc(w.ref)}: ${esc(letter.refNo)}</p><p style="margin:0">${esc(w.date)}: ${esc(bangla ? bnLongDate(letter.letterDate) : longDate(letter.letterDate))}</p></div>
  <div style="margin-top:12px"><p style="margin:0;font-size:13.5px;font-weight:700">${esc(letter.recipientName)}</p>
    <dl style="margin:0;display:grid;grid-template-columns:6.5rem 1fr;column-gap:4px">${letter.recipientRows.map(([k, v]) => `<dt>${esc(k)}</dt><dd style="margin:0;display:flex;gap:6px"><span>:</span><span>${esc(v)}</span></dd>`).join("")}</dl></div>
  <p style="margin:12px auto 0;width:fit-content;padding:4px 48px;text-align:center;font-size:17px;font-weight:700;background:${TINT};color:${INK_NAVY}">${esc(w.subject)}: ${esc(letter.subject)}</p>
  <div style="margin-top:12px;text-align:justify">${letter.salutation ? `<p style="margin:0">${bold(letter.salutation)}</p>` : ""}${letter.opening ? `<div style="margin-top:4px">${lines(letter.opening)}</div>` : ""}</div>
  ${clauseList(before, 1, bangla)}
  ${s ? salaryTables(s, w, bangla) : ""}
  ${clauseList(after, before.length + 1, bangla)}
  ${letter.closing ? `<div style="margin-top:12px">${lines(letter.closing)}</div>` : ""}
  <div style="margin-top:56px;width:240px;break-inside:avoid"><div style="border-top:1px solid ${INK}"></div><p style="margin:4px 0 0;font-weight:700">${esc(letter.signatureTitle)}</p><p style="margin:0;font-weight:700">${esc(letter.signatureCompany)}</p></div>
  ${letter.footerNote ? `<div style="margin-top:16px;font-size:11px;color:${MUTED}">${lines(letter.footerNote)}</div>` : ""}
</div>
${footer(head, w)}
</article>`;
}

/**
 * Letters in their own window, one A4 page each, printed (or saved as PDF from the print dialog).
 *
 * @param {object[]} letters merged letters; @param {object} head the letterhead
 */
function printLetters(letters, head, { title = "Letters", autoPrint = true, win = null } = {}) {
  const w = win ?? window.open("", "_blank");
  if (!w) return null;
  const body = letters.map((l, i) => `<div style="${i ? "break-before:page;" : ""}">${letterHtml(l, head)}</div>`).join("");
  w.document.open();
  w.document.write(`<!doctype html><html><head><meta charset="utf-8"><title>${esc(title)}</title>
<style>@page{size:A4;margin:0}html,body{margin:0;background:#e5e7eb}.bar{position:sticky;top:0;display:flex;justify-content:flex-end;gap:8px;padding:10px 16px;background:#111827}
.bar button{font:600 13px system-ui,sans-serif;padding:8px 16px;border-radius:6px;border:1px solid #4b5563;background:#1f2937;color:#fff;cursor:pointer}.bar button.primary{background:#4f46e5;border-color:#4f46e5}
.pages{padding:16px 0;display:flex;flex-direction:column;gap:16px}.letter-sheet{box-shadow:0 1px 6px rgba(0,0,0,.15)}
@media print{html,body{background:#fff}.bar{display:none}.pages{padding:0;gap:0}.letter-sheet{box-shadow:none;min-height:297mm!important}*{-webkit-print-color-adjust:exact;print-color-adjust:exact}}</style></head>
<body><div class="bar"><button onclick="window.close()">Close</button><button class="primary" onclick="window.print()">Print</button></div><div class="pages">${body}</div>
${autoPrint ? "<script>window.addEventListener('load',function(){setTimeout(function(){window.print()},400)})</script>" : ""}</body></html>`);
  w.document.close();
  w.focus();
  return w;
}

export { LETTER_FIELDS, BLANK, mergeLetter, gazetteWage, letterHtml, printLetters, dmy, num, words, bnWords, bnDigits };
