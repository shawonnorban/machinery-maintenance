"use client";

import { usePathname } from "next/navigation";
import { Lock } from "lucide-react";
import { useT } from "@/lib/i18n";

/*
 * Master data, company settings and setup screens belong to the admins
 * (user, 2026-11-22). Their links are not in anyone else's sidebar, and a
 * typed-in address shows this notice in place of the page.
 */

function SetupGuard({ restricted = [], locked = [], children }) {
  const pathname = usePathname();
  const t = useT("access");
  const under = (url) => pathname === url || pathname.startsWith(`${url}/`);
  // A module the platform has not switched on for this company.
  const off = locked.some(under);
  const shut = off || restricted.some(under);
  if (!shut) return children;
  const key = off ? "module_guard" : "setup_guard";

  return (
    <section className="flex flex-col items-center gap-2 rounded-xl border border-border bg-surface px-6 py-12 text-center">
      <span className="flex size-12 items-center justify-center rounded-xl bg-surface-muted text-foreground-muted">
        <Lock className="size-5" aria-hidden />
      </span>
      <h2 className="text-base font-semibold text-foreground">{t(`${key}.title`)}</h2>
      <p className="max-w-xl text-sm text-foreground-muted">{t(`${key}.body`)}</p>
    </section>
  );
}

export { SetupGuard };
