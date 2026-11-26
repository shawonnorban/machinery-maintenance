/* Number formats of Norban's prints (PHP number_format / trimmedDecimal). */

const num = (v) => {
  const x = typeof v === "string" ? Number(v.replace(/,/g, "")) : Number(v);
  return Number.isFinite(x) ? x : 0;
};

/** number_format($v, $d): thousands commas, $d decimals. */
export const nf = (v, d = 0) => num(v).toLocaleString("en-US", { minimumFractionDigits: d, maximumFractionDigits: d });

/** "$1,234" — money with $d decimals; a negative one "-$1,234". */
export const usd = (v, d = 0) => (num(v) < 0 ? "-" : "") + "$" + nf(Math.abs(num(v)), d);

/** A rate as Norban prints it: 2 decimals, or 4 when it uses them ("$3.60", "$0.0001"). */
export const rate = (v) => (v === null || v === undefined || v === "" ? "" : "$" + nf(v, Math.round(num(v) * 100) / 100 === num(v) ? 2 : 4));

/** trimmedDecimal: up to $d decimals, no padded zeros (0.25, 1, 0.014714). */
export const trim = (v, d = 6) => {
  if (v === null || v === undefined || v === "") return "";
  const s = num(v).toFixed(d).replace(/\.?0+$/, "");
  return s === "-0" ? "0" : s;
};

/** "23-01-2027" from "2027-01-23". */
export const dmy = (v) => {
  const m = /^(\d{4})-(\d{2})-(\d{2})/.exec(String(v ?? ""));
  return m ? `${m[3]}-${m[2]}-${m[1]}` : v || "N/A";
};

/** "20-11-2026 09:37 AM" — Norban's "printed …" stamp, Dhaka time. */
export const stamp = (d = new Date()) => {
  // en-US, h12: noon reads 12:05 PM (en-GB's 12-hour clock gives 00:05 PM).
  const p = new Intl.DateTimeFormat("en-US", { timeZone: "Asia/Dhaka", day: "2-digit", month: "2-digit", year: "numeric", hour: "2-digit", minute: "2-digit", hourCycle: "h12" }).formatToParts(d);
  const g = (k) => p.find((x) => x.type === k)?.value ?? "";
  return `${g("day")}-${g("month")}-${g("year")} ${g("hour")}:${g("minute")} ${g("dayPeriod").toUpperCase()}`;
};

export { num };
