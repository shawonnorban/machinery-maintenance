/**
 * Settings → Print & Letterhead: how a printed document will look with the
 * saved letterhead — logo, title, address and contact at the head, a sample
 * body, the signature boxes and the footer — drawn to the page's proportion.
 */
function LetterheadPreview({ values, companyName, logoUrl, title }) {
  const signatures = Array.isArray(values["print.signatures"]) ? values["print.signatures"] : [];
  const ratio = values["print.paper_size"] === "LEGAL" ? 356 / 216 : values["print.paper_size"] === "LETTER" ? 279 / 216 : 297 / 210;
  const margin = Math.max(4, Math.min(30, Number(values["print.margin_mm"] ?? 12)));

  return (
    <div className="rounded-lg border border-border bg-surface p-4">
      <p className="mb-3 text-sm font-semibold text-foreground">{title}</p>
      <div className="mx-auto w-full max-w-[26rem] bg-white text-[9px] text-slate-800 shadow-md ring-1 ring-slate-200" style={{ aspectRatio: `1 / ${ratio}`, padding: `${margin * 0.9}px` }}>
        <div className="flex h-full flex-col">
          <div className="flex items-center gap-3 border-b-2 border-slate-800 pb-2">
            {values["print.show_logo"] !== false ? (
              logoUrl ? (
                // eslint-disable-next-line @next/next/no-img-element -- a tenant-hosted logo
                <img src={logoUrl} alt="" className="size-9 object-contain" />
              ) : (
                <span className="flex size-9 items-center justify-center rounded-full bg-slate-800 text-[11px] font-bold text-white">{(companyName ?? "A").charAt(0)}</span>
              )
            ) : null}
            <div className="min-w-0 flex-1 text-center">
              <p className="truncate text-[12px] font-bold uppercase">{values["print.header_title"] || companyName}</p>
              {values["print.header_address"] ? <p className="truncate">{values["print.header_address"]}</p> : null}
              {values["print.header_contact"] ? <p className="truncate text-slate-500">{values["print.header_contact"]}</p> : null}
            </div>
            {values["print.show_logo"] !== false ? <span className="size-9" aria-hidden /> : null}
          </div>
          <p className="mt-3 text-center text-[10px] font-bold underline">PURCHASE ORDER</p>
          <div className="mt-3 space-y-1.5">
            {[0, 1, 2, 3, 4, 5].map((i) => (
              <div key={i} className="flex gap-2">
                <span className="h-1.5 flex-1 rounded bg-slate-200" />
                <span className="h-1.5 w-10 rounded bg-slate-200" />
                <span className="h-1.5 w-8 rounded bg-slate-200" />
              </div>
            ))}
          </div>
          <div className="mt-auto">
            <div className="flex justify-between gap-2 pt-6">
              {signatures.map((label) => (
                <div key={label} className="flex-1 text-center">
                  <div className="mx-2 border-t border-slate-500 pt-0.5">{label}</div>
                </div>
              ))}
            </div>
            <div className="mt-3 flex justify-between border-t border-slate-200 pt-1 text-[7px] text-slate-500">
              <span className="truncate">{values["print.footer_text"]}</span>
              {values["print.show_print_time"] !== false ? <span className="shrink-0">Printed by … on …</span> : null}
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}

export { LetterheadPreview };
