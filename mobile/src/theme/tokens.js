/**
 * The app's design tokens. A colour, radius or size typed anywhere else in the
 * app is a defect: change it here and every screen follows. Colours are the
 * web's (frontend/src/app/globals.css), so a status reads the same on both —
 * except the brand, the blue of the Annotech logo and the annotech-rmg app;
 * type sits one step larger and every control is at least 48dp, because a
 * tablet on a line is tapped, not pointed at.
 */

export const palettes = {
  // The Norban mobile design standard (MOBILE-UI-DESIGN.md, 2026-11-27): one light palette for every module.
  light: {
    background: "#F5F7FB",
    surface: "#FFFFFF",
    surfaceMuted: "#F1F5FB",
    overlay: "rgba(15, 23, 42, 0.45)",
    border: "#E8EDF5",
    borderStrong: "#CBD5E1",
    divider: "#EEF2F7",
    foreground: "#334155",
    foregroundMuted: "#64748B",
    foregroundSubtle: "#94A3B8",
    brand: "#2563EB",
    brandHover: "#1D4ED8",
    brandSubtle: "#EAF1FF",
    brandForeground: "#ffffff",
    success: "#16A34A",
    successSubtle: "#DCFCE7",
    warning: "#D97706",
    warningSubtle: "#FFF7E6",
    danger: "#DC2626",
    dangerSubtle: "#FEE2E2",
    info: "#0284C7",
    infoSubtle: "#E0F2FE",
    shadow: "#0f172a",
    barFrom: "#0b3470",
    barTo: "#2f72d6",
    barText: "#ffffff",
    date: "#fbbf24",
    active: "#2563EB",
    activeSubtle: "#EAF1FF",
    heading: "#172554",
    body: "#334155",
    secondary: "#64748B",
    page: "#F5F7FB",
    line: "#E8EDF5",
    primary: "#2563EB",
    primaryDark: "#1D4ED8",
    tableHead: "#F1F5FB",
    purple: "#7C3AED",
    purpleSubtle: "#F3E8FF",
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
    brand: "#5b93f5",
    brandHover: "#80aaf8",
    brandSubtle: "#17264a",
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
    barFrom: "#071f45",
    barTo: "#1d4f9e",
    barText: "#ffffff",
    date: "#fbbf24",
    active: "#fb923c",
    activeSubtle: "#3a2414",
    heading: "#e0e7ff",
    body: "#cbd5e1",
    secondary: "#94a3b8",
    page: "#0f172a",
    line: "#2b3a52",
    primary: "#5b8def",
    primaryDark: "#3b74e8",
    tableHead: "#1b2638",
    purple: "#a78bfa",
    purpleSubtle: "#2a1d4a",
    divider: "#26344a",
  },
};

/** The feature tiles' colours on Home and More (the annotech-rmg app's, user 2026-11-27). */
export const accents = {
  green: "#16a34a",
  blue: "#2f6fe8",
  orange: "#f59e0b",
  purple: "#7C3AED",
  teal: "#0d9488",
  rose: "#e5484d",
};

/** The 4px scale, and nothing else. */
export const space = { 1: 4, 1.5: 6, 2: 8, 3: 12, 4: 16, 5: 20, 6: 24, 8: 32, 10: 40, 12: 48, 16: 64 };

/** Half the first app's corners (user, 2026-11-27: "cards, buttons, tables — half as round"). */
export const radius = { sm: 4, md: 6, lg: 8, xl: 12, full: 9999 };

/** The minimum height of anything a thumb presses. */
export const touch = 48;

/** A button's height: slimmer than a touch row (user, 2026-11-27), still a thumb's width. */
export const control = 42;

/**
 * The web's faces (frontend/src/app/fonts, read in place — never copied):
 * Segoe UI for Latin, Anek Bangla for Bangla, one regular file each, as on
 * the web. Each weight is its own name for the same file; the Text component
 * gives it its weight (fontWeights), and Android draws the bold as the web's
 * browser does — from the regular face.
 */
export const fonts = {
  regular: "SegoeUI",
  medium: "SegoeUI-Medium",
  semibold: "SegoeUI-Semibold",
  bold: "SegoeUI-Bold",
  bengali: "AnekBangla",
  bengaliSemibold: "AnekBangla-Semibold",
};

export const fontWeights = {
  [fonts.regular]: "400",
  [fonts.medium]: "400",
  [fonts.semibold]: "700",
  [fonts.bold]: "700",
  [fonts.bengali]: "400",
  [fonts.bengaliSemibold]: "700",
};

export const type = {
  // The design standard's type scale (2026-11-27): compact, one hierarchy on every screen.
  pageTitle: { fontSize: 20, lineHeight: 26, fontFamily: fonts.bold },
  sectionTitle: { fontSize: 16, lineHeight: 22, fontFamily: fonts.semibold },
  cardTitle: { fontSize: 14, lineHeight: 20, fontFamily: fonts.semibold },
  body: { fontSize: 13, lineHeight: 19, fontFamily: fonts.regular },
  label: { fontSize: 12, lineHeight: 17, fontFamily: fonts.medium },
  small: { fontSize: 12, lineHeight: 17, fontFamily: fonts.regular },
  helper: { fontSize: 11, lineHeight: 15, fontFamily: fonts.regular },
  figure: { fontSize: 22, lineHeight: 28, fontFamily: fonts.bold, fontVariant: ["tabular-nums"] },
};

/**
 * A budget's state, the same on every page (user, 2026-11-27): the badge's
 * background and text. Never pick a state's colour anywhere else.
 */
export const budgetStates = {
  light: {
    DRAFT: { bg: "#EEF2FF", fg: "#4338CA" },
    PENDING: { bg: "#FFF7E6", fg: "#B45309" },
    APPROVED: { bg: "#DCFCE7", fg: "#15803D" },
    RETURNED: { bg: "#FEE2E2", fg: "#DC2626" },
    REVISED: { bg: "#F3E8FF", fg: "#7E22CE" },
    CANCELLED: { bg: "#F1F5F9", fg: "#475569" },
  },
  dark: {
    DRAFT: { bg: "#1e2257", fg: "#a5b4fc" },
    PENDING: { bg: "#3a2a0b", fg: "#fbbf24" },
    APPROVED: { bg: "#0f3320", fg: "#4ade80" },
    RETURNED: { bg: "#3b1414", fg: "#f87171" },
    REVISED: { bg: "#2e1546", fg: "#d8b4fe" },
    CANCELLED: { bg: "#1e293b", fg: "#94a3b8" },
  },
};
