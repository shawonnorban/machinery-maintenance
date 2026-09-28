"use client";

import { Printer } from "lucide-react";
import { Button } from "@/components/ui/button";
import { Alert } from "@/components/ui/alert";
import { EmptyState } from "@/components/ui/empty-state";
import { useT } from "@/lib/i18n";

/** Mirrors `asset::labels.sheet.blade.php` — the QR, the asset code and the name, nothing else (Data Dictionary 5.5): anything more is one more thing to go stale on a sticker nobody reprints. */
function LabelSheet({ labels, truncated }) {
  const t = useT("asset");

  if (labels.length === 0) {
    return <EmptyState title={t("no_matching_assets")} description={t("no_matching_assets_hint")} />;
  }

  return (
    <div className="flex flex-col gap-4">
      <div className="flex items-center justify-between print:hidden">
        <span className="text-sm text-foreground-muted">{t("label_count", { count: labels.length })}</span>
        <Button size="sm" onClick={() => window.print()}>
          <Printer /> {t("print")}
        </Button>
      </div>

      {truncated ? (
        <div className="print:hidden">
          <Alert variant="warning">{t("showing_first_200")}</Alert>
        </div>
      ) : null}

      <div className="grid grid-cols-2 gap-4 sm:grid-cols-3 md:grid-cols-4 print:grid-cols-3">
        {labels.map((label) => (
          <div key={label.id} className="flex flex-col items-center gap-2 rounded-sm border border-border p-4 text-center print:break-inside-avoid">
            {/* Trusted, server-rendered SVG from QrCodeRenderer — not user input.
                The SVG itself carries hardcoded width/height="220" attributes;
                without forcing the child element to fill this box, it renders
                at its own 220px size regardless of the wrapper and spills
                over the label text below it. */}
            <div className="size-32 shrink-0 [&_svg]:size-full" dangerouslySetInnerHTML={{ __html: label.svg }} />
            <div>
              <div className="text-sm font-semibold text-foreground">{label.asset_code}</div>
              <div className="text-xs text-foreground-muted">{label.name}</div>
            </div>
          </div>
        ))}
      </div>
    </div>
  );
}

export { LabelSheet };
