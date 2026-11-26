"use client";

import { useState } from "react";
import { Images } from "lucide-react";
import { Button } from "@/components/ui/button";
import { Modal } from "@/components/ui/modal";
import { useT } from "@/lib/i18n";

/**
 * Style Development Sheet → Style Gallery (AnnotechERP 06/05): every picture
 * attached to the style's items — key, 2nd and item images and certificate
 * pictures. Brickland keeps them in the browser; here they are the stored
 * files, so every viewer sees the same gallery.
 *
 * @param {{ style: string, images: {label: string, path: string}[] }} props
 */
function StyleGallery({ style, images }) {
  const t = useT("merchandise");
  const m = (k, p) => t(`master.itm.${k}`, p);
  const [open, setOpen] = useState(false);

  return (
    <>
      <Button type="button" variant="outline" onClick={() => setOpen(true)}>
        <Images /> {m("gallery")}
      </Button>
      <Modal open={open} onOpenChange={setOpen} title={m("gallery_title")} className="max-w-3xl">
        <p className="mb-3 text-[13px] text-foreground-muted">{m("gallery_hint", { style })}</p>
        {images.length === 0 ? (
          <p className="py-8 text-center text-sm text-foreground-muted">{m("gallery_empty")}</p>
        ) : (
          <div className="grid grid-cols-2 gap-3 sm:grid-cols-3">
            {images.map((img) => (
              <a key={img.path} href={img.path} target="_blank" rel="noreferrer" className="group flex flex-col gap-1">
                <span className="flex h-36 items-center justify-center overflow-hidden rounded-md border border-border bg-surface-muted">
                  {/* eslint-disable-next-line @next/next/no-img-element */}
                  <img src={img.path} alt={img.label} className="max-h-full max-w-full object-contain group-hover:opacity-90" />
                </span>
                <span className="truncate text-xs text-foreground-muted">{img.label}</span>
              </a>
            ))}
          </div>
        )}
      </Modal>
    </>
  );
}

export { StyleGallery };
