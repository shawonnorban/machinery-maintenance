import { Suspense } from "react";
import { Wrench, Check, Sparkles, ClipboardCheck, PackageSearch, CalendarCheck, ShieldCheck } from "lucide-react";
import { LoginForm } from "@/components/auth/login-form";
import { ThemeToggle } from "@/components/theme/theme-toggle";
import { cn } from "@/lib/utils";

export const metadata = { title: "Sign in — Annotech RMG" };

const HIGHLIGHTS = [
  { text: "Preventive maintenance, scheduled and never missed" },
  { text: "Breakdowns reported and resolved from the factory floor" },
  { text: "Spare parts and inventory under one roof" },
];

// Illustrative only (no live data belongs on a signed-out page) — mirrors
// the shape of a real usage sparkline (see AnalyticsPanel) purely for the
// brand panel's visual weight. One series per card, tone-matched to that
// card's own icon colour.
const STAT_CARDS = [
  {
    badge: "#1",
    badgeTone: "dark",
    icon: null,
    iconTone: "",
    label: "Total assets",
    value: "2,847",
    trend: "+12.4%",
    barColor: "bg-emerald-500",
    heights: [30, 42, 26, 50, 58, 52, 70, 64, 80, 90, 76, 96],
  },
  {
    icon: ClipboardCheck,
    iconTone: "bg-brand-subtle text-brand-hover",
    label: "Work orders closed",
    value: "1,204",
    trend: "+5.2%",
    barColor: "bg-brand",
    heights: [45, 40, 55, 50, 62, 58, 66, 60, 72, 68, 78, 74],
  },
  {
    icon: PackageSearch,
    iconTone: "bg-amber-50 text-amber-600",
    label: "Spare parts tracked",
    value: "3,562",
    trend: "+3.1%",
    barColor: "bg-amber-500",
    heights: [60, 55, 65, 58, 70, 62, 68, 72, 66, 78, 74, 82],
  },
  {
    icon: CalendarCheck,
    iconTone: "bg-info-subtle text-info",
    label: "PM tasks completed",
    value: "5,120",
    trend: "+9.3%",
    barColor: "bg-info",
    heights: [38, 46, 42, 54, 50, 60, 56, 64, 60, 70, 66, 76],
  },
];

export default function LoginPage() {
  return (
    <div className="flex min-h-dvh flex-col bg-surface lg:flex-row">
      {/* Form panel — a fixed, comfortably narrow column on large screens
          (not a 50/50 split) so the brand panel reads as the page's visual
          lead, matching the reference layout. A faint dot-pattern watermark
          (same technique as the brand panel, far lower opacity) keeps the
          large empty top/bottom margins from reading as unfinished. */}
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
              <Wrench className="size-4.5" />
            </div>
            <span className="text-lg font-semibold text-foreground">Annotech RMG</span>
          </div>
          <ThemeToggle />
        </div>

        <div className="relative flex flex-1 items-center justify-center py-10">
          <div className="w-full max-w-sm">
            <h1 className="text-[26px] font-bold tracking-tight text-foreground">Sign in to your account</h1>
            <p className="mt-1.5 mb-7 text-sm text-foreground-muted">
              Enter your credentials to access Annotech RMG.
            </p>
            {/* `LoginForm` reads `?from=` (`useSearchParams`) to return a
                visitor to whatever page sent them here — that hook opts a
                client component out of static rendering unless it has its
                own Suspense boundary. */}
            <Suspense>
              <LoginForm />
            </Suspense>
          </div>
        </div>

        <div className="relative flex flex-col items-center gap-1.5 lg:items-start">
          <p className="flex items-center gap-1.5 text-xs text-foreground-subtle">
            <ShieldCheck className="size-3.5" />
            Every session encrypted, every company's data kept apart
          </p>
          <p className="text-xs text-foreground-subtle">App Version : 2.0</p>
        </div>
      </div>

      {/* Brand panel — hidden below `lg` so a phone gets the form directly,
          not a scroll past a full-height hero it didn't ask for. Fixed,
          light mint-to-cyan gradient regardless of theme (brand chrome, not
          themed content) to match the reference exactly. */}
      <div
        className="relative hidden flex-1 flex-col justify-between overflow-hidden px-14 py-12 text-slate-900 lg:flex"
        style={{
          background: "linear-gradient(135deg, #d6f8e8 0%, #d3f3ef 45%, #cdeaf7 100%)",
        }}
      >
        <span className="relative inline-flex w-fit items-center gap-1.5 rounded-full border border-emerald-900/10 bg-white/70 px-3 py-1 text-xs font-semibold tracking-wide text-emerald-800 uppercase">
          <Sparkles className="size-3.5" />
          Annotech RMG
        </span>

        <div className="relative flex w-full flex-col gap-8">
          <div className="flex max-w-xl flex-col gap-3">
            <h2 className="text-4xl font-bold text-balance text-slate-900">
              Keep every machine running, and every record straight.
            </h2>
            <p className="text-sm text-slate-600">
              Maintenance, breakdowns, and asset lifecycle management built for RMG factory floors.
            </p>
          </div>

          <ul className="flex max-w-xl flex-col gap-3.5">
            {HIGHLIGHTS.map(({ text }) => (
              <li key={text} className="flex items-center gap-3 text-sm font-medium text-slate-800">
                <span className="flex size-6 shrink-0 items-center justify-center rounded-full bg-emerald-500 text-white">
                  <Check className="size-3.5" strokeWidth={3} />
                </span>
                {text}
              </li>
            ))}
          </ul>

          {/* Four cards side by side, filling the full panel width. Plain
              flexbox with `flex-1`/`min-w-0` (equal-width columns) rather
              than a `grid-cols-4` utility — every class here was already
              compiled elsewhere in this same file, so there's no risk of a
              dev-server CSS chunk missing a brand-new utility class and the
              cards falling back to overlapping/un-sized boxes. */}
          <div className="flex w-full gap-4">
            {STAT_CARDS.map((card) => (
              <div key={card.label} className="min-w-0 flex-1 overflow-hidden rounded-2xl bg-white p-5 shadow-xl">
                <div className="flex items-center justify-between gap-2">
                  {card.icon ? (
                    <span className={cn("flex size-8 items-center justify-center rounded-full", card.iconTone)}>
                      <card.icon className="size-4" />
                    </span>
                  ) : (
                    <span className="flex size-8 items-center justify-center rounded-lg bg-slate-900 text-xs font-bold text-white">
                      {card.badge}
                    </span>
                  )}
                  <span className="inline-flex items-center rounded-full bg-emerald-50 px-2 py-0.5 text-xs font-semibold text-emerald-600">
                    {card.trend}
                  </span>
                </div>
                <span className="mt-2.5 block text-[10px] font-semibold tracking-wide text-slate-400 uppercase">
                  {card.label}
                </span>
                <p className="tabular mt-1 text-2xl font-bold tracking-tight text-slate-900">{card.value}</p>
                {/* `flex-1` (not `w-full`) so the bars divide the row's
                    actual width between them — `w-full` here made every bar
                    demand the full row width, blowing the row (and the card
                    under it) out far past its container. */}
                <div className="mt-4 flex h-9 items-end gap-1">
                  {card.heights.map((height, index) => (
                    <div
                      key={index}
                      className={cn("min-w-0 flex-1 rounded-t-sm", card.barColor)}
                      style={{ height: `${height}%` }}
                    />
                  ))}
                </div>
              </div>
            ))}
          </div>
        </div>

        <p className="relative text-xs text-slate-500">A Product by Data State Ltd</p>
      </div>
    </div>
  );
}
