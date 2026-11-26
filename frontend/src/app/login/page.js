import { Suspense } from "react";
import {
  Factory,
  Sparkles,
  ShieldCheck,
  ChevronRight,
  ClipboardList,
  CalendarRange,
  Droplets,
  Scissors,
  BadgeCheck,
  Warehouse,
  Ship,
  Users,
} from "lucide-react";
import { LoginForm } from "@/components/auth/login-form";
import { LocaleSwitcher } from "@/components/auth/locale-switcher";
import { ThemeToggle } from "@/components/theme/theme-toggle";
import { getT } from "@/lib/i18n-server";
import { getLocale } from "next-intl/server";
import { cn } from "@/lib/utils";

export const metadata = { title: "Sign in — Annotech ERP" };

// The order's road through the factory — the brand panel's one-line picture
// of what the ERP covers end to end (user 2026-11-26: the page had only
// spoken about machine maintenance).
const FLOW = ["login_flow_inquiry", "login_flow_order", "login_flow_material", "login_flow_production", "login_flow_quality", "login_flow_shipment"];

// The main module families, not every screen. Descriptions, not numbers: a
// signed-out page has no live data and shows none (no made-up counters).
const MODULES = [
  { key: "merch", icon: ClipboardList, tone: "bg-emerald-50 text-emerald-600" },
  { key: "planning", icon: CalendarRange, tone: "bg-brand-subtle text-brand-hover" },
  { key: "knitdye", icon: Droplets, tone: "bg-info-subtle text-info" },
  { key: "cutsew", icon: Scissors, tone: "bg-amber-50 text-amber-600" },
  { key: "quality", icon: BadgeCheck, tone: "bg-emerald-50 text-emerald-600" },
  { key: "store", icon: Warehouse, tone: "bg-brand-subtle text-brand-hover" },
  { key: "commercial", icon: Ship, tone: "bg-info-subtle text-info" },
  { key: "hr", icon: Users, tone: "bg-amber-50 text-amber-600" },
];

export default async function LoginPage() {
  const [t, locale] = await Promise.all([getT("common"), getLocale()]);

  return (
    <div className="flex min-h-dvh flex-col bg-surface lg:flex-row">
      {/* Form panel — a fixed, comfortably narrow column on large screens so
          the brand panel reads as the page's visual lead. A faint dot
          pattern keeps the empty top/bottom margins from looking unfinished. */}
      <div className="relative flex w-full flex-col justify-between px-6 py-8 sm:px-12 lg:w-[480px] lg:shrink-0 lg:px-14">
        <div
          aria-hidden="true"
          className="pointer-events-none absolute inset-0 text-foreground opacity-[0.025]"
          style={{
            backgroundImage: "radial-gradient(circle at 1px 1px, currentColor 1px, transparent 0)",
            backgroundSize: "24px 24px",
          }}
        />

        <div className="relative flex items-center justify-between">
          <div className="flex items-center gap-2.5">
            <div className="flex size-9 items-center justify-center rounded-full bg-brand text-brand-foreground">
              <Factory className="size-4.5" />
            </div>
            <span className="text-lg font-semibold text-foreground">Annotech ERP</span>
          </div>
          <div className="flex items-center gap-2">
            <LocaleSwitcher locale={locale} />
            <ThemeToggle />
          </div>
        </div>

        <div className="relative flex flex-1 items-center justify-center py-10">
          <div className="w-full max-w-sm">
            <h1 className="text-[26px] font-bold tracking-tight text-foreground">{t("sign_in_to_your_account")}</h1>
            <p className="mt-1.5 mb-7 text-sm text-foreground-muted">{t("login_subtitle")}</p>
            {/* `LoginForm` reads `?from=` (`useSearchParams`), which needs its
                own Suspense boundary to keep this page statically renderable. */}
            <Suspense>
              <LoginForm />
            </Suspense>
          </div>
        </div>

        <div className="relative flex flex-col items-center gap-1.5 lg:items-start">
          <p className="flex items-center gap-1.5 text-xs text-foreground-subtle">
            <ShieldCheck className="size-3.5" />
            {t("login_security_note")}
          </p>
          <p className="text-xs text-foreground-subtle">{t("login_app_version", { version: "2.0" })}</p>
        </div>
      </div>

      {/* Brand panel — hidden below `lg` so a phone gets the form directly.
          Fixed light mint-to-cyan gradient regardless of theme (brand chrome,
          not themed content). */}
      <div
        className="relative hidden flex-1 flex-col justify-between overflow-hidden px-14 py-12 text-slate-900 lg:flex"
        style={{
          background: "linear-gradient(135deg, #d6f8e8 0%, #d3f3ef 45%, #cdeaf7 100%)",
        }}
      >
        <span className="relative inline-flex w-fit items-center gap-1.5 rounded-full border border-emerald-900/10 bg-white/70 px-3 py-1 text-xs font-semibold tracking-wide text-emerald-800 uppercase">
          <Sparkles className="size-3.5" />
          {t("login_badge")}
        </span>

        <div className="relative flex w-full flex-col gap-8">
          <div className="flex max-w-2xl flex-col gap-3">
            <h2 className="text-4xl font-bold text-balance text-slate-900">{t("login_headline")}</h2>
            <p className="text-sm text-slate-600">{t("login_tagline")}</p>
          </div>

          <ol className="flex flex-wrap items-center gap-1.5">
            {FLOW.map((key, index) => (
              <li key={key} className="flex items-center gap-1.5">
                <span className="inline-flex items-center gap-2 rounded-full border border-emerald-900/10 bg-white/80 py-1 pr-3 pl-1 text-xs font-semibold text-slate-700">
                  <span className="flex size-5 items-center justify-center rounded-full bg-emerald-500 text-[10px] font-bold text-white">
                    {index + 1}
                  </span>
                  {t(key)}
                </span>
                {index < FLOW.length - 1 ? <ChevronRight className="size-4 text-emerald-700/50" /> : null}
              </li>
            ))}
          </ol>

          <div className="grid w-full grid-cols-2 gap-4 xl:grid-cols-4">
            {MODULES.map((module) => (
              <div key={module.key} className="min-w-0 rounded-2xl bg-white p-5 shadow-xl">
                <span className={cn("flex size-9 items-center justify-center rounded-xl", module.tone)}>
                  <module.icon className="size-4.5" />
                </span>
                <p className="mt-3 text-sm font-semibold text-slate-900">{t(`login_module_${module.key}`)}</p>
                <p className="mt-1 text-xs leading-relaxed text-slate-500">{t(`login_module_${module.key}_desc`)}</p>
              </div>
            ))}
          </div>
        </div>

        <p className="relative text-xs text-slate-500">{t("login_footer_product_by", { company: "Data State Ltd" })}</p>
      </div>
    </div>
  );
}
