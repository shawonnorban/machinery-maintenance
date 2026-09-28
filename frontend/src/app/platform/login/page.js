import Link from "next/link";
import { ShieldCheck, Check, Sparkles, Wallet, LifeBuoy, Globe } from "lucide-react";
import { PlatformLoginForm } from "@/components/auth/platform-login-form";
import { ThemeToggle } from "@/components/theme/theme-toggle";
import { cn } from "@/lib/utils";

export const metadata = { title: "Platform sign in — Annotech RMG" };

const HIGHLIGHTS = [
  { text: "Every customer, their contract and their status" },
  { text: "Invoices, payments, and what the business is owed" },
  { text: "Support tickets and audited, time-boxed access" },
];

// Illustrative only (no live data belongs on a signed-out page) — mirrors
// the shape of a real usage sparkline (see AnalyticsPanel) purely for the
// brand panel's visual weight. One series per card, tone-matched to that
// card's own icon colour.
const STAT_CARDS = [
  {
    badge: "#1",
    icon: null,
    iconTone: "",
    label: "Active customers",
    value: "142",
    trend: "+8.0%",
    trendTone: "bg-emerald-50 text-emerald-600",
    barColor: "bg-emerald-500",
    heights: [36, 50, 42, 56, 52, 64, 58, 74, 68, 84, 78, 94],
  },
  {
    icon: Wallet,
    iconTone: "bg-brand-subtle text-brand-hover",
    label: "Monthly revenue",
    value: "৳8.4L",
    trend: "+6.5%",
    trendTone: "bg-emerald-50 text-emerald-600",
    barColor: "bg-brand",
    heights: [48, 42, 56, 50, 60, 54, 66, 60, 70, 64, 76, 72],
  },
  {
    icon: LifeBuoy,
    iconTone: "bg-amber-50 text-amber-600",
    label: "Support tickets",
    value: "7",
    trend: "7 open",
    trendTone: "bg-amber-50 text-amber-600",
    barColor: "bg-amber-500",
    heights: [58, 52, 62, 55, 65, 58, 68, 62, 72, 66, 76, 70],
  },
  {
    icon: Globe,
    iconTone: "bg-info-subtle text-info",
    label: "Domains verified",
    value: "128",
    trend: "+2.1%",
    trendTone: "bg-emerald-50 text-emerald-600",
    barColor: "bg-info",
    heights: [40, 46, 44, 52, 48, 58, 54, 62, 58, 68, 64, 74],
  },
];

/**
 * Same split-screen design as the tenant `/login` (on direct request — the
 * console used to run a separate dark/amber chrome to be "deliberately
 * distinguishable," but this project's own colour tokens now do that job
 * consistently everywhere instead). Its own door regardless: a platform
 * admin's token carries no `company_id` at all (`PlatformAuthApiController`),
 * so the form still posts to `/api/platform-auth/login`, not `/api/auth/
 * login` — only the page around it is shared design.
 */
export default function PlatformLoginPage() {
  return (
    <div className="flex min-h-dvh flex-col bg-surface lg:flex-row">
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
              <ShieldCheck className="size-4.5" />
            </div>
            <span className="text-lg font-semibold text-foreground">Annotech RMG</span>
          </div>
          <ThemeToggle />
        </div>

        <div className="relative flex flex-1 flex-col items-center justify-center py-10">
          <div className="w-full max-w-sm">
            <h1 className="text-[26px] font-bold tracking-tight text-foreground">Platform sign in</h1>
            <p className="mt-1.5 mb-7 text-sm text-foreground-muted">Sign in with your platform account.</p>
            <PlatformLoginForm />

            <p className="mt-6 text-center text-sm text-foreground-muted">
              Not platform staff?{" "}
              <Link href="/login" className="font-medium text-brand hover:underline">
                Sign in as a customer
              </Link>
            </p>
          </div>
        </div>

        <div className="relative flex flex-col items-center gap-1.5 lg:items-start">
          <p className="flex items-center gap-1.5 text-xs text-foreground-subtle">
            <ShieldCheck className="size-3.5" />
            Every action logged, every grant time-boxed
          </p>
          <p className="text-xs text-foreground-subtle">App Version : 2.0</p>
        </div>
      </div>

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
            <h2 className="text-4xl font-bold text-balance text-slate-900">Every customer, one console.</h2>
            <p className="text-sm text-slate-600">
              The superadmin console — onboarding, billing, support, and domains, across every company on the
              platform.
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
                  <span className={cn("inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold", card.trendTone)}>
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
