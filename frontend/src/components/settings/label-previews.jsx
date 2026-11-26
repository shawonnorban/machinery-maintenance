import { cn } from "@/lib/utils";

/** Sample data a preview label prints. */
const SAMPLE = {
  CODE: "RL-24-00318",
  NAME: "Single Jersey 160 GSM",
  DESCRIPTION: "100% Cotton, open width",
  QUANTITY: "42.50",
  UOM: "KG",
  LOT: "LOT-0912",
  COLOR: "Navy",
  SIZE: "M",
  BUYER: "H&M",
  ORDER: "ORD-1042",
  DATE: "10-Oct-2026",
  LOCATION: "A-03-02",
};

/** A barcode drawn as bars, or a QR / Data Matrix drawn as a square of cells. */
function Code({ symbology }) {
  if (symbology === "QR" || symbology === "DATAMATRIX") {
    const cells = Array.from({ length: 49 }, (_, i) => (i * 7 + (i % 5) * 3) % 3 !== 0);
    return (
      <div className="grid aspect-square h-full grid-cols-7 gap-px">
        {cells.map((on, i) => (
          <span key={i} className={on ? "bg-slate-900" : "bg-white"} />
        ))}
      </div>
    );
  }
  const bars = Array.from({ length: 38 }, (_, i) => ((i * 13) % 4) + 1);
  return (
    <div className="flex h-full items-stretch gap-[1px]">
      {bars.map((w, i) => (
        <span key={i} className="bg-slate-900" style={{ width: `${w}px` }} />
      ))}
    </div>
  );
}

/**
 * Settings → Barcode & Label: every template as the label it prints, at its
 * proportion, with sample data in the fields it carries.
 */
function LabelPreviews({ templates, fieldLabels, emptyText }) {
  if (templates.length === 0) {
    return <div className="rounded-lg border border-border bg-surface px-5 py-14 text-center text-sm text-foreground-muted">{emptyText}</div>;
  }

  return (
    <div className="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
      {templates.map((template) => {
        const square = template.symbology === "QR" || template.symbology === "DATAMATRIX";
        const fields = (template.fields ?? []).filter((f) => f !== "CODE");
        return (
          <div key={template.id} className="rounded-lg border border-border bg-surface p-4">
            <div className="mb-3 flex items-center justify-between gap-2">
              <span className="text-sm font-semibold text-foreground">{template.name}</span>
              <span className="font-mono text-xs text-foreground-muted">{template.size}</span>
            </div>
            <div className="flex justify-center rounded-md bg-surface-muted p-4">
              <div className={cn("flex w-full max-w-64 gap-2 rounded-sm bg-white p-2 text-slate-900 shadow ring-1 ring-slate-200", square ? "flex-row items-center" : "flex-col")} style={{ aspectRatio: `${Number(template.width_mm)} / ${Number(template.height_mm)}` }}>
                <div className={square ? "h-full py-1" : "h-1/2"}>
                  <Code symbology={template.symbology} />
                </div>
                <div className="min-w-0 flex-1 text-[9px] leading-tight">
                  {(template.fields ?? []).includes("CODE") ? <p className="font-mono font-bold">{SAMPLE.CODE}</p> : null}
                  {fields.slice(0, 4).map((field) => (
                    <p key={field} className="truncate">
                      <span className="text-slate-500">{fieldLabels[field]}: </span>
                      {SAMPLE[field]}
                    </p>
                  ))}
                </div>
              </div>
            </div>
          </div>
        );
      })}
    </div>
  );
}

export { LabelPreviews };
