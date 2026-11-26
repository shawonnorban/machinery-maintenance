/**
 * Each module's colour, as Brickland's sidebar and page-header icon tiles show
 * it (Access Control rose, Approval Center green, Settings amber, Company
 * Management orange …). `tile` is the soft square behind an icon, `icon` the
 * glyph's own colour. Unknown modules fall back to the indigo accent.
 */
const MODULE_TONES = {
  OVERVIEW: { tile: "bg-indigo-50 text-indigo-600", icon: "text-indigo-600" },
  INSIGHT: { tile: "bg-indigo-50 text-indigo-600", icon: "text-indigo-600" },
  ACCESS: { tile: "bg-rose-50 text-rose-500", icon: "text-rose-500" },
  APPROVAL: { tile: "bg-emerald-50 text-emerald-600", icon: "text-emerald-600" },
  SETTINGS: { tile: "bg-amber-50 text-amber-600", icon: "text-amber-500" },
  COMPANY: { tile: "bg-orange-50 text-orange-600", icon: "text-orange-500" },
  MERCHANDISE: { tile: "bg-pink-50 text-pink-600", icon: "text-pink-500" },
  PROCUREMENT: { tile: "bg-sky-50 text-sky-600", icon: "text-sky-500" },
  STORE: { tile: "bg-teal-50 text-teal-600", icon: "text-teal-500" },
  TRADE: { tile: "bg-cyan-50 text-cyan-600", icon: "text-cyan-600" },
  MACHINERY: { tile: "bg-violet-50 text-violet-600", icon: "text-violet-500" },
};

const FALLBACK = { tile: "bg-brand-subtle text-brand", icon: "text-brand" };

function moduleTone(code) {
  return MODULE_TONES[code] ?? FALLBACK;
}

export { moduleTone };
