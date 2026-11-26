/**
 * The app's design tokens. A colour, radius or size typed anywhere else in the
 * app is a defect: change it here and every screen follows. Colours are the
 * web's (frontend/src/app/globals.css), so a status reads the same on both;
 * type sits one step larger and every control is at least 48dp, because a
 * tablet on a line is tapped, not pointed at.
 */

export const palettes = {
  light: {
    background: "#f8fafc",
    surface: "#ffffff",
    surfaceMuted: "#f1f5f9",
    overlay: "rgba(15, 23, 42, 0.45)",
    border: "#e2e8f0",
    borderStrong: "#cbd5e1",
    foreground: "#0f172a",
    foregroundMuted: "#475569",
    foregroundSubtle: "#94a3b8",
    brand: "#4f46e5",
    brandHover: "#4338ca",
    brandSubtle: "#eef2ff",
    brandForeground: "#ffffff",
    success: "#16a34a",
    successSubtle: "#f0fdf4",
    warning: "#d97706",
    warningSubtle: "#fffbeb",
    danger: "#dc2626",
    dangerSubtle: "#fef2f2",
    info: "#0284c7",
    infoSubtle: "#f0f9ff",
    shadow: "#0f172a",
  },
  dark: {
    background: "#0f172a",
    surface: "#1e293b",
    surfaceMuted: "#273449",
    overlay: "rgba(2, 6, 23, 0.65)",
    border: "#334155",
    borderStrong: "#475569",
    foreground: "#f1f5f9",
    foregroundMuted: "#cbd5e1",
    foregroundSubtle: "#94a3b8",
    brand: "#818cf8",
    brandHover: "#a5b4fc",
    brandSubtle: "#1e1b4b",
    brandForeground: "#ffffff",
    success: "#4ade80",
    successSubtle: "#10291b",
    warning: "#fbbf24",
    warningSubtle: "#2e2208",
    danger: "#f87171",
    dangerSubtle: "#33161a",
    info: "#38bdf8",
    infoSubtle: "#082f49",
    shadow: "#000000",
  },
};

/** The 4px scale, and nothing else. */
export const space = { 1: 4, 1.5: 6, 2: 8, 3: 12, 4: 16, 5: 20, 6: 24, 8: 32, 10: 40, 12: 48, 16: 64 };

export const radius = { sm: 8, md: 12, lg: 16, full: 9999 };

/** The minimum height of anything a thumb presses. */
export const touch = 48;

export const fonts = {
  regular: "Inter_400Regular",
  medium: "Inter_500Medium",
  semibold: "Inter_600SemiBold",
  bold: "Inter_700Bold",
  bengali: "AnekBangla_400Regular",
  bengaliSemibold: "AnekBangla_600SemiBold",
};

export const type = {
  pageTitle: { fontSize: 24, lineHeight: 32, fontFamily: fonts.bold },
  sectionTitle: { fontSize: 17, lineHeight: 24, fontFamily: fonts.semibold },
  cardTitle: { fontSize: 15, lineHeight: 22, fontFamily: fonts.semibold },
  body: { fontSize: 15, lineHeight: 22, fontFamily: fonts.regular },
  label: { fontSize: 14, lineHeight: 20, fontFamily: fonts.medium },
  small: { fontSize: 13, lineHeight: 18, fontFamily: fonts.regular },
  figure: { fontSize: 28, lineHeight: 34, fontFamily: fonts.bold, fontVariant: ["tabular-nums"] },
};
