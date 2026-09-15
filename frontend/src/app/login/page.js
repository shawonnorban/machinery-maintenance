import { Suspense } from "react";
import { Wrench, ClipboardCheck, PackageSearch, ShieldCheck } from "lucide-react";
import { LoginForm } from "@/components/auth/login-form";

export const metadata = { title: "Sign in — Annotech RMG" };

const HIGHLIGHTS = [
  { icon: ClipboardCheck, text: "Preventive maintenance scheduling, never missed" },
  { icon: Wrench, text: "Breakdowns reported and resolved from the factory floor" },
  { icon: PackageSearch, text: "Spare parts and inventory under one roof" },
  { icon: ShieldCheck, text: "Every asset, every factory, one auditable record" },
];

export default function LoginPage() {
  return (
    <div className="grid min-h-dvh grid-cols-1 bg-background lg:grid-cols-2">
      {/* Brand panel — hidden below `lg` so a phone gets the form immediately,
          not a scroll past a full-height hero it didn't ask for. */}
      <div className="relative hidden flex-col justify-between overflow-hidden bg-gradient-to-br from-brand to-brand-hover px-12 py-12 text-brand-foreground lg:flex">
        <div
          aria-hidden="true"
          className="pointer-events-none absolute inset-0 opacity-[0.07]"
          style={{
            backgroundImage:
              "radial-gradient(circle at 1px 1px, currentColor 1px, transparent 0)",
            backgroundSize: "28px 28px",
          }}
        />

        <div className="relative flex items-center gap-2.5">
          <div className="flex size-9 items-center justify-center rounded-sm bg-white/15">
            <Wrench className="size-5" />
          </div>
          <span className="text-lg font-semibold">Annotech RMG</span>
        </div>

        <div className="relative flex max-w-md flex-col gap-8">
          <div className="flex flex-col gap-3">
            <h1 className="text-3xl font-semibold text-balance">
              Keep every machine running, and every record straight.
            </h1>
            <p className="text-sm text-white/80">
              Maintenance, breakdowns, and asset lifecycle management built for RMG factory floors.
            </p>
          </div>

          <ul className="flex flex-col gap-3.5">
            {HIGHLIGHTS.map(({ icon: Icon, text }) => (
              <li key={text} className="flex items-center gap-3 text-sm">
                <span className="flex size-7 shrink-0 items-center justify-center rounded-sm bg-white/15">
                  <Icon className="size-4" />
                </span>
                {text}
              </li>
            ))}
          </ul>
        </div>

        <p className="relative text-xs text-white/60">A Product by Data State Ltd</p>
      </div>

      {/* Form panel */}
      <div className="flex flex-col justify-between px-6 py-10 sm:px-12 lg:px-16">
        <div className="flex items-center gap-2.5 lg:hidden">
          <div className="flex size-8 items-center justify-center rounded-sm bg-brand text-brand-foreground">
            <Wrench className="size-4" />
          </div>
          <span className="text-base font-semibold text-foreground">Annotech RMG</span>
        </div>

        <div className="flex flex-1 items-center justify-center">
          <div className="w-full max-w-sm">
            <h2 className="text-xl font-semibold text-foreground">Sign in</h2>
            <p className="mt-1 mb-6 text-sm text-foreground-muted">
              Enter your credentials to access your account.
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

        <p className="text-center text-xs text-foreground-subtle lg:text-right">App Version : 2.0</p>
      </div>
    </div>
  );
}
