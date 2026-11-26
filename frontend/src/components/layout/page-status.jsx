"use client";

import Link from "next/link";
import { useRouter } from "next/navigation";
import { ArrowLeft, FileQuestion, LayoutDashboard, ShieldX } from "lucide-react";
import { Button, buttonVariants } from "@/components/ui/button";
import { useT } from "@/lib/i18n";

/*
 * The page a screen shows instead of itself (user, 2026-11-20): "Page not
 * found" for an address that leads nowhere, and "You do not have access" for
 * a screen the caller's role does not include — in the app's own look, with
 * a way back, instead of the bare "404 This page could not be found".
 */
export function PageStatus({ kind = "not-found" }) {
  const t = useT("common");
  const router = useRouter();
  const forbidden = kind === "forbidden";
  const Icon = forbidden ? ShieldX : FileQuestion;
  return (
    <div className="flex min-h-[60vh] items-center justify-center px-4 py-12">
      <div className="w-full max-w-md rounded-xl border border-border bg-surface p-8 text-center shadow-sm">
        <div className={forbidden ? "mx-auto flex size-14 items-center justify-center rounded-full bg-danger-subtle text-danger" : "mx-auto flex size-14 items-center justify-center rounded-full bg-brand/10 text-brand"}>
          <Icon className="size-7" />
        </div>
        <p className="mt-4 font-mono text-sm font-semibold text-foreground-muted">{forbidden ? "403" : "404"}</p>
        <h1 className="mt-1 text-xl font-semibold text-foreground">{t(forbidden ? "page_status.fb_title" : "page_status.nf_title")}</h1>
        <p className="mt-2 text-sm text-foreground-muted">{t(forbidden ? "page_status.fb_text" : "page_status.nf_text")}</p>
        <div className="mt-6 flex flex-wrap justify-center gap-2">
          <Button type="button" variant="outline" onClick={() => router.back()}>
            <ArrowLeft /> {t("page_status.back")}
          </Button>
          <Link href="/" className={buttonVariants()}>
            <LayoutDashboard /> {t("page_status.home")}
          </Link>
        </div>
      </div>
    </div>
  );
}
