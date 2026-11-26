"use client";

import { useEffect, useLayoutEffect, useRef, useState } from "react";
import { useRouter, useSearchParams } from "next/navigation";
import { Printer, X } from "lucide-react";
import { useT } from "@/lib/i18n";

/*
 * The frame every Pre-Budget print sits in (Norban's PDFs, made with the
 * browser's own print): an A4 sheet — Landscape, or Portrait where the print
 * offers it — margins 8/8/11/8 mm kept inside the sheet. The sheet is laid out at a
 * fixed width and scaled to the page (`scale`, Norban's PDF scale: the budget
 * 0.79, the cost / accessories sheets 0.9), so its text comes out the size of
 * Norban's; `onePage` shrinks it further when it would run over one page
 * (Norban's Portrait budget). On screen it shows as the sheet it prints, the
 * toolbar above (hidden on paper), and it asks the browser to print once
 * laid out. `footer`: a line at the foot of each page (Norban's views that
 * print "<title> | printed …").
 */

const MM = 96 / 25.4;
// The page's margins live inside the sheet (not in @page): a print dialog set to Margins "None" — or a printer that
// cannot print to the edge — then still leaves them, and nothing is cut (user, 2026-11-20). Sides as padding; top and
// bottom as a table's header / footer rows, which the browser repeats on every page.
const MARGIN = { t: 8, r: 8, b: 11, l: 8 };

export function PrintFrame({ title, orientation = "landscape", scale = 0.85, onePage = false, footer = null, portraitToggle = false, bottom = null, children }) {
  const t = useT("prebudget");
  const router = useRouter();
  const params = useSearchParams();
  const ref = useRef(null);
  const contentRef = useRef(null);
  const bottomRef = useRef(null);
  const paper = orientation === "portrait" ? { w: 210, h: 297 } : { w: 297, h: 210 };
  const pageW = (paper.w - MARGIN.l - MARGIN.r) * MM;
  const pageH = (paper.h - MARGIN.t - MARGIN.b) * MM;
  const layoutW = Math.floor(pageW / scale);
  const [zoom, setZoom] = useState(scale);
  const [ready, setReady] = useState(false);

  // One page: shrink until the sheet's height fits (its width stays laid out the same).
  useLayoutEffect(() => {
    const el = ref.current;
    if (!el) return;
    const fit = () => {
      // Its own (unscaled) height — the content and the bottom block, not the page-high box
      // around them: the drawn height over the zoom it is drawn at.
      const z = Number(el.style.zoom) || 1;
      const h = ((contentRef.current?.getBoundingClientRect().height ?? 0) + (bottomRef.current?.getBoundingClientRect().height ?? 0)) / z;
      setZoom(onePage && h * scale > pageH ? pageH / h : scale);
    };
    fit();
    const ro = new ResizeObserver(fit);
    ro.observe(contentRef.current);
    return () => ro.disconnect();
  }, [onePage, scale, pageH]);

  // Ask to print once fonts and pictures are in (not when `?noprint=1`, the self-check).
  useEffect(() => {
    let off = false;
    const imgs = [...(ref.current?.querySelectorAll("img") ?? [])].map((i) => (i.complete ? null : new Promise((r) => { i.onload = r; i.onerror = r; })));
    Promise.all([document.fonts?.ready, ...imgs]).then(() => {
      if (off) return;
      setReady(true);
      if (params.get("noprint") !== "1") setTimeout(() => window.print(), 300);
    });
    return () => { off = true; };
  }, [params]);

  const setOrientation = (o) => {
    const q = new URLSearchParams(params.toString());
    if (o === "portrait") q.set("orientation", "portrait");
    else q.delete("orientation");
    q.set("noprint", "1");
    router.replace(`?${q.toString()}`);
  };

  const pageCss = `@page { size: A4 ${orientation}; margin: 0; }`;

  return (
    <div className="nbp-screen">
      <style>{pageCss}</style>
      <div className="nbp-bar">
        <span className="nbp-bar-title">{title}</span>
        {portraitToggle ? (
          <>
            <button type="button" aria-pressed={orientation === "landscape"} onClick={() => setOrientation("landscape")}>{t("print.landscape")}</button>
            <button type="button" aria-pressed={orientation === "portrait"} onClick={() => setOrientation("portrait")}>{t("print.portrait")}</button>
          </>
        ) : null}
        <button type="button" className="nbp-primary" disabled={!ready} onClick={() => window.print()}><Printer size={15} /> {t("print.print")}</button>
        <button type="button" onClick={() => window.close()}><X size={15} /> {t("print.close")}</button>
      </div>
      <div className="nbp-paper" style={{ width: `${paper.w}mm`, minHeight: `${paper.h}mm` }}>
        <table className="nbp-page">
          <thead><tr><td style={{ height: `${MARGIN.t}mm` }} /></tr></thead>
          <tfoot><tr><td style={{ height: `${MARGIN.b}mm` }} /></tr></tfoot>
          <tbody>
            <tr>
              <td style={{ padding: `0 ${MARGIN.r}mm 0 ${MARGIN.l}mm` }}>
                {/* `bottom` (the signatures) sits at the foot of the page however little the sheet holds. */}
                <div ref={ref} className="nbp" style={{ width: layoutW, zoom, ...(bottom ? { display: "flex", flexDirection: "column", minHeight: Math.floor(pageH / zoom) - 4 } : {}) }}>
                  <div ref={contentRef}>{children}</div>
                  {bottom ? <div ref={bottomRef} style={{ marginTop: "auto" }}>{bottom}</div> : null}
                </div>
              </td>
            </tr>
          </tbody>
        </table>
        {/* "<title> | printed …": at the foot of every printed page (fixed), at the paper's foot on screen. */}
        {footer ? <div className="nbp-foot" style={{ left: `${MARGIN.l}mm` }}>{footer}</div> : null}
      </div>
    </div>
  );
}
