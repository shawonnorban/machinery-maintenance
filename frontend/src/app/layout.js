import { Geist_Mono } from "next/font/google";
import localFont from "next/font/local";
import Script from "next/script";
import { NextIntlClientProvider } from "next-intl";
import { ThemeProvider } from "@/components/theme/theme-provider";
import { ToastProvider } from "@/components/ui/toast";
import "./globals.css";

// The UI's default face is Segoe UI, self-hosted here rather than left to
// each OS's own copy (see the --font-sans stack in globals.css's :root) —
// a Windows visitor sees exactly the same glyphs as a factory-floor Android
// tablet or a Linux desktop. This .ttf carries no Bengali glyphs of its
// own (that's Windows' own font-linking to Nirmala UI, which only exists
// on Windows), so Anek Bangla is stacked right after it — the browser
// renders every Latin run from Segoe UI and falls through to Anek Bangla,
// glyph by glyph, for anything in Bengali script.
const segoeUI = localFont({
  src: "./fonts/Segoe UI.ttf",
  variable: "--font-segoe-ui",
  display: "swap",
});

const anekBangla = localFont({
  src: "./fonts/AnekBangla-Regular.ttf",
  variable: "--font-anek-bangla",
  display: "swap",
});

const geistMono = Geist_Mono({
  variable: "--font-geist-mono",
  subsets: ["latin"],
});

export const metadata = {
  title: "Annotech RMG",
  description: "Machinery maintenance and production management",
};

// Runs before paint, outside React, so an explicit theme choice from a
// previous visit never flashes as the wrong theme while hydration catches
// up. "system" needs no attribute at all — globals.css's media-query block
// already handles it — so this only ever has to set something for an
// explicit light/dark choice.
const themeInitScript = `
(function () {
  try {
    var stored = window.localStorage.getItem("annotech-theme");
    if (stored === "light" || stored === "dark") {
      document.documentElement.setAttribute("data-theme", stored);
    }
  } catch (e) {}
})();
`;

export default function RootLayout({ children }) {
  return (
    <html
      lang="en"
      className={`${segoeUI.variable} ${anekBangla.variable} ${geistMono.variable} h-full antialiased`}
      suppressHydrationWarning
    >
      <body className="min-h-full flex flex-col">
        <NextIntlClientProvider>
          <ThemeProvider>
            <ToastProvider>{children}</ToastProvider>
          </ThemeProvider>
        </NextIntlClientProvider>
        {/* next/script's own API reference places a beforeInteractive
            script exactly here — inside <body>, after the content — and
            notes Next always relocates it into the real <head> itself
            regardless of this JSX position. Neither of the two other
            placements tried here work: inside a manually-authored <head>
            defeats that relocation and logs "scripts are never executed
            when rendering on the client"; as a sibling of <body> at the
            <html> level (matching a different, non-beforeInteractive
            example from the guide doc) produces real HTML-validity errors
            ("<script> cannot be a child of <html>"), since only <body> is
            a valid parent for a literal <script> element. */}
        <Script id="theme-init" strategy="beforeInteractive">{themeInitScript}</Script>
      </body>
    </html>
  );
}
