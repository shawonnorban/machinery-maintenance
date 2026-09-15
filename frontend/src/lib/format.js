/**
 * Quantity and currency fields come back from the API as fixed-precision
 * decimal strings (Laravel's `decimal:4` cast, e.g. "22.0000") — correct for
 * storage/arithmetic, wrong to print verbatim: a technician reading "22.0000
 * PCS" is reading noise a plain "22 PCS" would not carry. `maximumFraction
 * Digits` alone (no `minimumFractionDigits`) is what drops the trailing
 * zeros while still showing real fractional quantities (e.g. "1.5 M") as
 * themselves, not rounded away.
 */
const DEFAULT_MAX_DECIMALS = 4;

/**
 * `12345` → "12,345" (or "22.0000" → "22", "1.5000" → "1.5"). Returns the
 * fallback for null/undefined/unparseable input rather than printing "NaN".
 */
function formatNumber(value, { maximumFractionDigits = DEFAULT_MAX_DECIMALS, fallback = "—" } = {}) {
  if (value === null || value === undefined || value === "") return fallback;
  const n = Number(value);
  if (Number.isNaN(n)) return fallback;
  return n.toLocaleString(undefined, { maximumFractionDigits });
}

/** `formatNumber` plus a trailing unit label — "22 PCS", "1.5 M", never "22.0000 PCS". */
function formatQuantity(value, unit, options) {
  const formatted = formatNumber(value, options);
  if (formatted === (options?.fallback ?? "—")) return formatted;
  return unit ? `${formatted} ${unit}` : formatted;
}

/**
 * Money is always shown to exactly 2 decimals (never trimmed — "50" and
 * "50.00" read as different precision to anyone reconciling an invoice),
 * with the currency code trailing the amount to match how every existing
 * currency display in this codebase already orders it ("1,234.00 BDT").
 */
function formatCurrency(value, currency, { fallback = "—" } = {}) {
  if (value === null || value === undefined || value === "") return fallback;
  const n = Number(value);
  if (Number.isNaN(n)) return fallback;
  const formatted = n.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  return currency ? `${formatted} ${currency}` : formatted;
}

export { formatNumber, formatQuantity, formatCurrency };
