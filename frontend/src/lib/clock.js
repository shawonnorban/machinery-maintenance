/*
 * Every time in the product is shown on a 12-hour clock with AM / PM (user,
 * 2026-11-25; Settings → Time format is 12H). Values stay 24-hour underneath
 * — what is saved, sorted and put in <input type="time"> — and are turned
 * here, only where they are shown.
 */

const TIME = "([01]\\d|2[0-3]):([0-5]\\d)(:[0-5]\\d)?";
const ONE = new RegExp(`^${TIME}$`);
const RANGE = new RegExp(`^(${TIME})(\\s*[-–]\\s*)(${TIME})$`);
const STAMP = new RegExp(`^(\\d{4}-\\d{2}-\\d{2}|\\d{2}-\\d{2}-\\d{4}|\\d{1,2}[ -][A-Za-z]{3}[ -]\\d{4})([ T]|, )(${TIME})$`);

/** "17:05" → "05:05 PM" (seconds kept); anything else comes back as it is. */
function clock12(v) {
  const m = typeof v === "string" ? v.match(ONE) : null;
  if (!m) return v;
  const h = Number(m[1]);
  return `${String(h % 12 || 12).padStart(2, "0")}:${m[2]}${m[3] ?? ""} ${h < 12 ? "AM" : "PM"}`;
}

/** A grid's text: a time, a "08:00–17:00" range or a "2026-11-25 17:05" stamp in AM / PM; any other value untouched. */
function ampm(v) {
  if (typeof v !== "string" || v.length < 5 || v.length > 24 || !v.includes(":")) return v;
  if (ONE.test(v)) return clock12(v);
  const range = v.match(RANGE);
  if (range) return `${clock12(range[1])}${range[5]}${clock12(range[6])}`;
  const stamp = v.match(STAMP);
  return stamp ? `${stamp[1]}${stamp[2] === ", " ? ", " : " "}${clock12(stamp[3])}` : v;
}

/** An hour of the day: 17 → "05:00 PM". */
const hour12 = (h) => clock12(`${String(h).padStart(2, "0")}:00`);

/** An Intl date-and-time format ("25 Nov 2026, 05:05 PM"): `.format(date)`. */
function clockFormat(options, locale = "en-GB") {
  const fmt = new Intl.DateTimeFormat(locale, { ...options, hourCycle: "h12" });
  return { format: (d) => fmt.format(d).replace(/\b[ap]m\b/, (s) => s.toUpperCase()) };
}

export { clock12, ampm, hour12, clockFormat };
